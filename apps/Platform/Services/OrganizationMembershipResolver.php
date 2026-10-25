<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Pure resolver for an already-authoritative user-membership evidence set.
 *
 * This class deliberately performs no authentication, database, ACL, Staff,
 * or Leave work. A caller must obtain the evidence from an approved authority
 * before invoking it.
 */
final class OrganizationMembershipResolver
{
    /**
     * @param array<int,array<string,mixed>> $memberships
     * @return array{
     *     allowed:bool,
     *     status:string,
     *     reason_code:string,
     *     context:OrganizationMembershipContext|null,
     *     eligible_organization_ids:array<int,int>
     * }
     */
    public static function resolve(
        int $userId,
        array $memberships,
        ?int $requestedOrganizationId = null,
        ?DateTimeInterface $at = null
    ): array {
        if ($userId <= 0 || ($requestedOrganizationId !== null && $requestedOrganizationId <= 0)) {
            return self::denied('invalid_identity', 'invalid_identity');
        }

        $effectiveAt = $at instanceof DateTimeInterface ? $at : new DateTimeImmutable('now');
        $eligible = [];

        foreach ($memberships as $membership) {
            if (!is_array($membership)) {
                return self::denied('malformed_evidence', 'malformed_evidence');
            }

            $memberUserId = self::positiveId($membership['user_id'] ?? null);
            if ($memberUserId === null) {
                return self::denied('malformed_evidence', 'malformed_evidence');
            }

            if ($memberUserId !== $userId) {
                continue;
            }

            $organizationId = self::positiveId($membership['organization_id'] ?? null);
            $active = self::strictBoolean($membership['is_active'] ?? null);
            $revoked = self::strictBoolean($membership['is_revoked'] ?? null);

            if ($organizationId === null || $active === null || $revoked === null) {
                return self::denied('malformed_evidence', 'malformed_evidence');
            }

            if (!$active || $revoked) {
                continue;
            }

            $validity = self::validityStatus($membership, $effectiveAt);
            if ($validity === null || !self::optionalEvidenceIsSafe($membership)) {
                return self::denied('malformed_evidence', 'malformed_evidence');
            }

            if (!$validity) {
                continue;
            }

            if (isset($eligible[$organizationId])) {
                return self::denied('ambiguous_membership', 'duplicate_active_membership');
            }

            $eligible[$organizationId] = $membership;
        }

        $organizationIds = array_keys($eligible);
        sort($organizationIds, SORT_NUMERIC);
        $organizationIds = array_map('intval', $organizationIds);

        if ($requestedOrganizationId !== null) {
            if (!isset($eligible[$requestedOrganizationId])) {
                return self::denied('organization_not_member', 'organization_not_member', $organizationIds);
            }
            $selectedOrganizationId = $requestedOrganizationId;
        } elseif (count($organizationIds) !== 1) {
            return self::denied(
                count($organizationIds) === 0 ? 'no_active_membership' : 'ambiguous_membership',
                count($organizationIds) === 0 ? 'no_active_membership' : 'organization_selection_required',
                $organizationIds
            );
        } else {
            $selectedOrganizationId = $organizationIds[0];
        }

        $selected = $eligible[$selectedOrganizationId];
        $fingerprintPayload = [
            'user_id' => $userId,
            'organization_id' => $selectedOrganizationId,
            'membership' => self::canonicalEvidence($selected),
        ];

        $fingerprint = hash(
            'sha256',
            (string)json_encode($fingerprintPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        return [
            'allowed' => true,
            'status' => 'resolved',
            'reason_code' => 'resolved',
            'context' => new OrganizationMembershipContext($userId, $selectedOrganizationId, $fingerprint),
            'eligible_organization_ids' => $organizationIds,
        ];
    }

    /**
     * @param array<string,mixed> $membership
     */
    private static function validityStatus(array $membership, DateTimeInterface $at): ?bool
    {
        foreach (['valid_from', 'valid_until'] as $field) {
            if (!array_key_exists($field, $membership) || $membership[$field] === null || $membership[$field] === '') {
                continue;
            }

            if (!is_string($membership[$field])) {
                return null;
            }

            try {
                $boundary = new DateTimeImmutable($membership[$field]);
            } catch (\Throwable) {
                return null;
            }

            if ($field === 'valid_from' && $at < $boundary) {
                return false;
            }
            if ($field === 'valid_until' && $at >= $boundary) {
                return false;
            }
        }

        return true;
    }

    /**
     * Optional evidence is included in the fingerprint, so reject values that
     * cannot be represented deterministically instead of throwing or coercing.
     *
     * @param array<string,mixed> $membership
     */
    private static function optionalEvidenceIsSafe(array $membership): bool
    {
        if (array_key_exists('membership_key', $membership)
            && $membership['membership_key'] !== null
            && !is_string($membership['membership_key'])
        ) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string,mixed> $membership
     * @return array<string,mixed>
     */
    private static function canonicalEvidence(array $membership): array
    {
        $evidence = [
            'user_id' => self::positiveId($membership['user_id'] ?? null),
            'organization_id' => self::positiveId($membership['organization_id'] ?? null),
            'is_active' => self::strictBoolean($membership['is_active'] ?? null),
            'is_revoked' => self::strictBoolean($membership['is_revoked'] ?? null),
        ];

        foreach (['membership_key', 'valid_from', 'valid_until'] as $field) {
            if (array_key_exists($field, $membership)) {
                $evidence[$field] = $membership[$field];
            }
        }

        ksort($evidence);
        return $evidence;
    }

    private static function positiveId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/', $value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int)$id;
    }

    private static function strictBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === '0') {
            return false;
        }
        if ($value === 1 || $value === '1') {
            return true;
        }
        return null;
    }

    /**
     * @param array<int,int> $organizationIds
     * @return array{
     *     allowed:bool,
     *     status:string,
     *     reason_code:string,
     *     context:null,
     *     eligible_organization_ids:array<int,int>
     * }
     */
    private static function denied(string $status, string $reasonCode, array $organizationIds = []): array
    {
        return [
            'allowed' => false,
            'status' => $status,
            'reason_code' => $reasonCode,
            'context' => null,
            'eligible_organization_ids' => $organizationIds,
        ];
    }
}
