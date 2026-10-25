<?php
declare(strict_types=1);

namespace Plugins\Organization\Services;

use App\Core\DB;
use DateTimeImmutable;
use DateTimeInterface;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;

require_once __DIR__ . '/../Contracts/OrganizationMembershipRepositoryContract.php';

/**
 * Platform Organization persistence boundary for explicit memberships.
 *
 * This service is deliberately not connected to Auth, ACL, Staff, Leave, or
 * Timecard. It persists and reads only membership evidence.
 */
final class OrganizationMembershipRepository implements OrganizationMembershipRepositoryContract
{
    private const TABLE = 'org_user_memberships';

    public function listEvidenceForUser(int $userId, DateTimeInterface $observedAt): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = DB::fetchAll(
            'SELECT id, user_id, organization_id, status, valid_from, valid_until,
                    revoked_at
             FROM org_user_memberships
             WHERE user_id = ?
             ORDER BY organization_id ASC, id ASC',
            [$userId]
        );

        $evidence = [];
        foreach ($rows as $row) {
            $evidence[] = [
                'membership_key' => (string)($row['id'] ?? ''),
                'user_id' => (int)($row['user_id'] ?? 0),
                'organization_id' => (int)($row['organization_id'] ?? 0),
                'is_active' => (string)($row['status'] ?? '') === 'active',
                'is_revoked' => (string)($row['status'] ?? '') === 'revoked',
                'valid_from' => $row['valid_from'] ?? null,
                'valid_until' => $row['valid_until'] ?? null,
            ];
        }

        return $evidence;
    }

    public function findById(int $membershipId): ?array
    {
        if ($membershipId <= 0) {
            return null;
        }

        return DB::fetchOne(
            'SELECT id, user_id, organization_id, status, valid_from, valid_until,
                    activated_at, activated_by_user_id, revoked_at, revoked_by_user_id, revocation_reason, created_at,
                    created_by_user_id, updated_at
             FROM org_user_memberships WHERE id = ? LIMIT 1',
            [$membershipId]
        );
    }

    public function create(array $command): array
    {
        $normalized = $this->normalizeCreateCommand($command);
        if ($normalized === null) {
            return $this->failure('invalid_membership_command');
        }

        $db = DB::conn();
        try {
            $db->begin_transaction();
            if (!$this->lockParents($normalized['user_id'], $normalized['organization_id'])) {
                $db->rollback();
                return $this->failure('membership_parent_not_found');
            }
            if (!$this->organizationIsActive($normalized['organization_id'])) {
                $db->rollback();
                return $this->failure('organization_inactive');
            }
            $this->lockMembershipPair($normalized['user_id'], $normalized['organization_id']);
            if ($normalized['status'] === 'active' && $this->hasActivePair($normalized['user_id'], $normalized['organization_id'])) {
                $db->rollback();
                return $this->failure('membership_conflict');
            }

            $id = $this->insertMembership($normalized);
            $record = $this->findById($id);
            if ($record === null) {
                throw new \RuntimeException('Membership read-back failed after insert.');
            }
            $db->commit();
            return $this->success('membership_created', $record);
        } catch (\Throwable $e) {
            $this->rollbackQuietly($db);
            return $this->failure($this->isDuplicateError($e) ? 'membership_conflict' : 'membership_write_failed');
        }
    }

    public function activate(int $membershipId, int $actorUserId, DateTimeInterface $observedAt): array
    {
        if ($membershipId <= 0 || $actorUserId <= 0) {
            return $this->failure('invalid_membership_command');
        }

        $db = DB::conn();
        try {
            $db->begin_transaction();
            $identity = DB::fetchOne(
                'SELECT user_id, organization_id FROM org_user_memberships WHERE id = ? LIMIT 1',
                [$membershipId]
            );
            if ($identity === null) {
                $db->rollback();
                return $this->failure('membership_not_found');
            }
            if (!$this->lockParents((int)$identity['user_id'], (int)$identity['organization_id'])
                || !$this->organizationIsActive((int)$identity['organization_id'])
            ) {
                $db->rollback();
                return $this->failure('membership_parent_or_validity_invalid');
            }
            $row = DB::fetchOne('SELECT * FROM org_user_memberships WHERE id = ? FOR UPDATE', [$membershipId]);
            if ($row === null) {
                $db->rollback();
                return $this->failure('membership_not_found');
            }
            if ((string)$row['status'] !== 'inactive') {
                $db->rollback();
                return $this->failure('membership_state_conflict');
            }
            if (!$this->validWindow($row['valid_from'] ?? null, $row['valid_until'] ?? null)) {
                $db->rollback();
                return $this->failure('membership_parent_or_validity_invalid');
            }
            $this->lockMembershipPair((int)$row['user_id'], (int)$row['organization_id']);
            if ($this->hasActivePair((int)$row['user_id'], (int)$row['organization_id'])) {
                $db->rollback();
                return $this->failure('membership_conflict');
            }

            DB::query(
                'UPDATE org_user_memberships
                 SET status = ?, activated_at = ?, activated_by_user_id = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = ?',
                ['active', $observedAt->format('Y-m-d H:i:s'), $actorUserId, $membershipId, 'inactive']
            );
            $record = $this->findById($membershipId);
            $db->commit();
            return $this->success('membership_activated', $record);
        } catch (\Throwable $e) {
            $this->rollbackQuietly($db);
            return $this->failure($this->isDuplicateError($e) ? 'membership_conflict' : 'membership_write_failed');
        }
    }

    public function revoke(int $membershipId, int $actorUserId, DateTimeInterface $observedAt, string $reason): array
    {
        $reason = trim($reason);
        if ($membershipId <= 0 || $actorUserId <= 0 || $reason === '' || strlen($reason) > 255) {
            return $this->failure('invalid_membership_command');
        }

        $db = DB::conn();
        try {
            $db->begin_transaction();
            $identity = DB::fetchOne(
                'SELECT user_id, organization_id FROM org_user_memberships WHERE id = ? LIMIT 1',
                [$membershipId]
            );
            if ($identity === null) {
                $db->rollback();
                return $this->failure('membership_not_found');
            }
            if (!$this->lockParents((int)$identity['user_id'], (int)$identity['organization_id'])) {
                $db->rollback();
                return $this->failure('membership_parent_not_found');
            }
            $row = DB::fetchOne('SELECT * FROM org_user_memberships WHERE id = ? FOR UPDATE', [$membershipId]);
            if ($row === null) {
                $db->rollback();
                return $this->failure('membership_not_found');
            }
            if ((string)$row['status'] === 'revoked') {
                $db->rollback();
                return $this->success('already_revoked', $row);
            }
            if ((string)$row['status'] !== 'active') {
                $db->rollback();
                return $this->failure('membership_state_conflict');
            }

            DB::query(
                'UPDATE org_user_memberships
                 SET status = ?, revoked_at = ?, revoked_by_user_id = ?, revocation_reason = ?, updated_at = CURRENT_TIMESTAMP
                 WHERE id = ? AND status = ?',
                ['revoked', $observedAt->format('Y-m-d H:i:s'), $actorUserId, $reason, $membershipId, 'active']
            );
            $record = $this->findById($membershipId);
            $db->commit();
            return $this->success('membership_revoked', $record);
        } catch (\Throwable $e) {
            $this->rollbackQuietly($db);
            return $this->failure($this->isDuplicateError($e) ? 'membership_conflict' : 'membership_write_failed');
        }
    }

    public function replaceActive(
        int $userId,
        int $organizationId,
        array $command,
        int $actorUserId,
        DateTimeInterface $observedAt
    ): array {
        $normalized = $this->normalizeCreateCommand(array_merge($command, [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'status' => 'active',
            'created_by_user_id' => $actorUserId,
        ]));
        if ($normalized === null || $actorUserId <= 0) {
            return $this->failure('invalid_membership_command');
        }

        $db = DB::conn();
        try {
            $db->begin_transaction();
            if (!$this->lockParents($userId, $organizationId) || !$this->organizationIsActive($organizationId)) {
                $db->rollback();
                return $this->failure('membership_parent_not_found');
            }
            $this->lockMembershipPair($userId, $organizationId);
            $activeRows = DB::fetchAll(
                'SELECT id FROM org_user_memberships WHERE user_id = ? AND organization_id = ? AND status = ? FOR UPDATE',
                [$userId, $organizationId, 'active']
            );
            foreach ($activeRows as $activeRow) {
                DB::query(
                    'UPDATE org_user_memberships
                     SET status = ?, revoked_at = ?, revoked_by_user_id = ?, revocation_reason = ?, updated_at = CURRENT_TIMESTAMP
                     WHERE id = ? AND status = ?',
                    ['revoked', $observedAt->format('Y-m-d H:i:s'), $actorUserId, 'replaced', (int)$activeRow['id'], 'active']
                );
            }
            $id = $this->insertMembership($normalized);
            $record = $this->findById($id);
            if ($record === null) {
                throw new \RuntimeException('Membership read-back failed after replacement.');
            }
            $db->commit();
            return $this->success('membership_replaced', $record);
        } catch (\Throwable $e) {
            $this->rollbackQuietly($db);
            return $this->failure($this->isDuplicateError($e) ? 'membership_conflict' : 'membership_write_failed');
        }
    }

    /** @param array<string,mixed> $command @return array<string,mixed>|null */
    private function normalizeCreateCommand(array $command): ?array
    {
        $userId = $this->positiveInt($command['user_id'] ?? null);
        $organizationId = $this->positiveInt($command['organization_id'] ?? null);
        $actorId = $this->positiveInt($command['created_by_user_id'] ?? null);
        $status = (string)($command['status'] ?? 'inactive');
        if ($userId === null || $organizationId === null || $actorId === null || !in_array($status, ['active', 'inactive'], true)) {
            return null;
        }
        $validFrom = $this->normalizeDate($command['valid_from'] ?? null);
        $validUntil = $this->normalizeDate($command['valid_until'] ?? null);
        if (($command['valid_from'] ?? null) !== null && $validFrom === null
            || ($command['valid_until'] ?? null) !== null && $validUntil === null
            || !$this->validWindow($validFrom, $validUntil)
        ) {
            return null;
        }

        return [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'status' => $status,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'created_by_user_id' => $actorId,
        ];
    }

    /** @param array<string,mixed> $row */
    private function insertMembership(array $row): int
    {
        DB::query(
            'INSERT INTO org_user_memberships
                (user_id, organization_id, status, valid_from, valid_until, activated_at, activated_by_user_id, created_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $row['user_id'],
                $row['organization_id'],
                $row['status'],
                $row['valid_from'],
                $row['valid_until'],
                $row['status'] === 'active' ? date('Y-m-d H:i:s') : null,
                $row['status'] === 'active' ? $row['created_by_user_id'] : null,
                $row['created_by_user_id'],
            ]
        );
        return (int)DB::conn()->insert_id;
    }

    private function lockParents(int $userId, int $organizationId): bool
    {
        $user = DB::fetchOne('SELECT id FROM users WHERE id = ? FOR UPDATE', [$userId]);
        $organization = DB::fetchOne('SELECT id, is_active FROM org_companies WHERE id = ? FOR UPDATE', [$organizationId]);
        return $user !== null && $organization !== null;
    }

    private function organizationIsActive(int $organizationId): bool
    {
        $row = DB::fetchOne('SELECT is_active FROM org_companies WHERE id = ? LIMIT 1', [$organizationId]);
        return $row !== null && (int)$row['is_active'] === 1;
    }

    private function hasActivePair(int $userId, int $organizationId): bool
    {
        return DB::fetchOne(
            'SELECT id FROM org_user_memberships WHERE user_id = ? AND organization_id = ? AND status = ? LIMIT 1',
            [$userId, $organizationId, 'active']
        ) !== null;
    }

    private function lockMembershipPair(int $userId, int $organizationId): void
    {
        DB::fetchAll(
            'SELECT id FROM org_user_memberships WHERE user_id = ? AND organization_id = ? ORDER BY id ASC FOR UPDATE',
            [$userId, $organizationId]
        );
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!$value instanceof DateTimeInterface && !is_string($value)) {
            return null;
        }
        try {
            return (new DateTimeImmutable($value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : $value))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function validWindow(?string $from, ?string $until): bool
    {
        if ($from === null || $until === null) {
            return true;
        }
        return $from < $until;
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_int($value) && $value > 0 ? $value : null;
    }

    private function isDuplicateError(\Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'duplicate');
    }

    private function rollbackQuietly(\mysqli $db): void
    {
        try {
            $db->rollback();
        } catch (\Throwable) {
        }
    }

    /** @return array{ok:bool,state:string,record:array<string,mixed>|null} */
    private function success(string $state, ?array $record): array
    {
        return ['ok' => true, 'state' => $state, 'record' => $record];
    }

    /** @return array{ok:bool,state:string,record:null} */
    private function failure(string $state): array
    {
        return ['ok' => false, 'state' => $state, 'record' => null];
    }
}
