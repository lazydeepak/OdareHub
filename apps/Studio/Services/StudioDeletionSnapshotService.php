<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionSnapshotStore.php';

/**
 * Canonical snapshot-creation capability for one exact claimed deletion packet.
 * It writes only the rollback snapshot and immutable manifest.
 */
final class StudioDeletionSnapshotService
{
    public const EFFECT = 'mutate';
    public const VERSION = 'studio.deletion-snapshot.v1';

    /**
     * @param array<string,mixed> $snapshotReadiness
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $actor
     * @param callable(array<string,mixed>,?string):array<string,mixed>|null $writer
     * @param callable():mixed|null $clock
     * @return array<string,mixed>
     */
    public static function create(
        array $snapshotReadiness,
        array $input,
        ?array $actor,
        ?string $root = null,
        ?callable $writer = null,
        ?callable $clock = null
    ): array {
        $currentPacket = self::arrayValue($snapshotReadiness, 'current_packet');
        $target = self::arrayValue($currentPacket, 'target');
        $claim = self::arrayValue($snapshotReadiness, 'claim_evidence');
        $snapshotContract = self::arrayValue($snapshotReadiness, 'snapshot_contract');
        $actorEvidence = self::arrayValue($snapshotReadiness, 'actor_evidence');
        $snapshotReadinessFingerprint = trim((string)($snapshotReadiness['snapshot_readiness_fingerprint'] ?? ''));
        $claimId = trim((string)($claim['claim_id'] ?? ''));
        $claimFingerprint = trim((string)($claim['claim_fingerprint'] ?? ''));
        $ownerKey = trim((string)($target['owner_key'] ?? ''));
        $targetPath = self::normalizePath((string)($snapshotContract['source_path'] ?? $target['target_path'] ?? ''));
        $destinationPath = self::normalizePath((string)($snapshotContract['destination_path'] ?? ''));
        $diagnostics = [];

        foreach (self::readinessProblems(
            $snapshotReadiness,
            $currentPacket,
            $target,
            $claim,
            $snapshotContract,
            $actorEvidence,
            $snapshotReadinessFingerprint,
            $claimId,
            $claimFingerprint,
            $ownerKey,
            $targetPath,
            $destinationPath
        ) as $reason) {
            $diagnostics[] = self::diagnostic(
                'DELETION_SNAPSHOT_CREATION_READINESS_INVALID',
                'Snapshot creation requires the exact current ready claim and safe deterministic snapshot destination.',
                ['reason' => $reason]
            );
        }

        $bindings = [
            'snapshot_readiness_fingerprint' => $snapshotReadinessFingerprint,
            'claim_id' => $claimId,
            'claim_fingerprint' => $claimFingerprint,
        ];
        foreach ($bindings as $field => $expected) {
            $presented = trim((string)($input[$field] ?? ''));
            if ($expected === '' || $presented === '' || !hash_equals($expected, $presented)) {
                $diagnostics[] = self::diagnostic(
                    'DELETION_SNAPSHOT_CREATION_BINDING_STALE',
                    'The submitted snapshot request does not match the current server-side claim and snapshot readiness.',
                    ['field' => $field]
                );
            }
        }

        $actorRecord = self::actorRecord((array)$actor);
        $claimExecutor = self::arrayValue($claim, 'executor');
        $claimExecutorId = trim((string)($claimExecutor['actor_id'] ?? ''));
        if (!self::isPlatformAdmin($actor) || $actorRecord['actor_id'] === '') {
            $diagnostics[] = self::diagnostic('DELETION_SNAPSHOT_CREATION_ACTOR_UNAUTHORIZED', 'Only an identified platform administrator may create the claimed snapshot.');
        }
        if ($claimExecutorId === '' || $actorRecord['actor_id'] === '' || !hash_equals($claimExecutorId, $actorRecord['actor_id'])) {
            $diagnostics[] = self::diagnostic('DELETION_SNAPSHOT_CREATION_ACTOR_MISMATCH', 'Only the platform-admin executor who owns the immutable claim may create the snapshot.');
        }
        if ((string)($input['snapshot_confirmed'] ?? '') !== 'yes') {
            $diagnostics[] = self::diagnostic('DELETION_SNAPSHOT_CREATION_CONFIRMATION_REQUIRED', 'Explicit snapshot creation confirmation is required.');
        }
        $reason = trim((string)($input['reason'] ?? ''));
        if ($reason === '') {
            $diagnostics[] = self::diagnostic('DELETION_SNAPSHOT_CREATION_REASON_REQUIRED', 'A snapshot creation reason is required.');
        } elseif (strlen($reason) > 1000) {
            $diagnostics[] = self::diagnostic('DELETION_SNAPSHOT_CREATION_REASON_TOO_LONG', 'Snapshot creation reason must not exceed 1000 bytes.');
        }

        $sourceBindings = [];
        foreach ([
            'change_set_fingerprint',
            'plan_fingerprint',
            'readiness_fingerprint',
            'dry_run_fingerprint',
            'approval_record_id',
            'approval_record_fingerprint',
            'request_id',
            'request_fingerprint',
        ] as $field) {
            $sourceBindings[$field] = trim((string)($currentPacket[$field] ?? ''));
        }
        $sourceBindings['claim_id'] = $claimId;
        $sourceBindings['claim_fingerprint'] = $claimFingerprint;
        $sourceBindings['snapshot_readiness_fingerprint'] = $snapshotReadinessFingerprint;

        if ($diagnostics !== []) {
            return self::errorResult($sourceBindings, $target, $snapshotContract, $diagnostics);
        }

        $createdAt = self::now($clock)->format('Y-m-d\TH:i:s.u\Z');
        $snapshotId = 'deletion-snapshot-' . substr(
            sha1($claimId . '|' . $claimFingerprint . '|' . $snapshotReadinessFingerprint),
            0,
            20
        );
        $metadata = [
            'version' => self::VERSION,
            'effect' => self::EFFECT,
            'snapshot_id' => $snapshotId,
            'created_at_utc' => $createdAt,
            'target' => [
                'owner_key' => $ownerKey,
                'target_type' => (string)($target['target_type'] ?? 'owner'),
                'target_path' => $targetPath,
            ],
            'source' => $sourceBindings,
            'executor' => $actorRecord,
            'reason' => $reason,
            'snapshot' => [
                'source_path' => $targetPath,
                'destination_path' => $destinationPath,
            ],
            'creation_contract' => [
                'claim_required' => 'yes',
                'snapshot_readiness_required' => 'yes',
                'copy_only' => 'yes',
                'atomic_publish_required' => 'yes',
                'overwrite_allowed' => 'no',
                'manifest_required' => 'yes',
                'checksum_algorithm' => 'sha256',
                'snapshot_is_execution_authority' => 'no',
            ],
        ];

        try {
            $result = $writer !== null
                ? $writer($metadata, $root)
                : StudioDeletionSnapshotStore::create($metadata, $root);
        } catch (\Throwable $exception) {
            return self::errorResult($sourceBindings, $target, $snapshotContract, [
                self::diagnostic(
                    str_contains(strtolower($exception->getMessage()), 'already exists')
                        ? 'DELETION_SNAPSHOT_CREATION_ALREADY_EXISTS'
                        : 'DELETION_SNAPSHOT_CREATION_STORAGE_FAILED',
                    $exception->getMessage() !== '' ? $exception->getMessage() : 'Snapshot creation failed.'
                ),
            ]);
        }

        if (!is_array($result)) {
            return self::errorResult($sourceBindings, $target, $snapshotContract, [
                self::diagnostic('DELETION_SNAPSHOT_CREATION_RESULT_INVALID', 'Snapshot writer returned an invalid result.'),
            ]);
        }
        return $result;
    }

    /** @return array<int,string> */
    private static function readinessProblems(
        array $readiness,
        array $currentPacket,
        array $target,
        array $claim,
        array $contract,
        array $actorEvidence,
        string $readinessFingerprint,
        string $claimId,
        string $claimFingerprint,
        string $ownerKey,
        string $targetPath,
        string $destinationPath
    ): array {
        $problems = [];
        if ($readinessFingerprint === '' || $claimId === '' || $claimFingerprint === '' || $ownerKey === '' || $targetPath === '' || $destinationPath === '') {
            $problems[] = 'SNAPSHOT_CREATION_IDENTITY_MISSING';
        }
        if ((string)($readiness['effect'] ?? '') !== 'verify'
            || (string)($readiness['snapshot_readiness'] ?? '') !== 'ready'
            || (string)($readiness['snapshot_ready'] ?? 'no') !== 'yes'
            || (string)($readiness['claim_valid'] ?? 'no') !== 'yes'
            || (string)($readiness['executor_identity_valid'] ?? 'no') !== 'yes'
            || (string)($readiness['source_available'] ?? 'no') !== 'yes'
            || (string)($readiness['destination_available'] ?? 'no') !== 'yes') {
            $problems[] = 'SNAPSHOT_READINESS_NOT_READY';
        }
        foreach (['can_snapshot', 'would_write', 'would_archive', 'would_delete', 'can_execute', 'can_apply', 'can_archive', 'can_delete', 'grants_execution_authority'] as $field) {
            if ((string)($readiness[$field] ?? 'yes') !== 'no') {
                $problems[] = 'SNAPSHOT_READINESS_AUTHORITY_INVALID:' . $field;
            }
        }
        if ((string)($readiness['requires_separate_snapshot_capability'] ?? '') !== 'yes'
            || (string)($readiness['requires_separate_execution_capability'] ?? '') !== 'yes') {
            $problems[] = 'SNAPSHOT_READINESS_SEPARATION_INVALID';
        }
        if (self::stringList($readiness['blocking_reasons'] ?? []) !== []) {
            $problems[] = 'SNAPSHOT_READINESS_BLOCKERS_PRESENT';
        }
        if ((string)($claim['present'] ?? 'no') !== 'yes'
            || trim((string)($claim['claim_id'] ?? '')) !== $claimId
            || trim((string)($claim['claim_fingerprint'] ?? '')) !== $claimFingerprint) {
            $problems[] = 'SNAPSHOT_CLAIM_EVIDENCE_INVALID';
        }
        if ((string)($claim['executor']['actor_id'] ?? '') !== (string)($actorEvidence['actor_id'] ?? '')
            || (string)($actorEvidence['authority_role'] ?? '') !== 'platform_admin') {
            $problems[] = 'SNAPSHOT_EXECUTOR_EVIDENCE_INVALID';
        }
        if ((string)($contract['required'] ?? 'no') !== 'yes'
            || self::normalizePath((string)($contract['source_path'] ?? '')) !== $targetPath
            || self::normalizePath((string)($contract['destination_path'] ?? '')) !== $destinationPath
            || (string)($contract['source_exists'] ?? 'no') !== 'yes'
            || (string)($contract['source_readable'] ?? 'no') !== 'yes'
            || (string)($contract['destination_exists'] ?? 'yes') !== 'no'
            || (string)($contract['archive_mode'] ?? '') !== 'copy_only'
            || (string)($contract['overwrite_allowed'] ?? 'yes') !== 'no'
            || (string)($contract['requires_post_write_manifest'] ?? '') !== 'yes') {
            $problems[] = 'SNAPSHOT_CONTRACT_INVALID';
        }
        if (!str_starts_with($destinationPath, 'storage/studio/deletion-execution-snapshots/')
            || $destinationPath === $targetPath
            || str_starts_with($destinationPath . '/', $targetPath . '/')
            || str_starts_with($targetPath . '/', $destinationPath . '/')) {
            $problems[] = 'SNAPSHOT_DESTINATION_UNSAFE';
        }
        if ((string)($target['owner_key'] ?? '') !== $ownerKey
            || self::normalizePath((string)($target['target_path'] ?? '')) !== $targetPath
            || trim((string)($currentPacket['change_set_fingerprint'] ?? '')) === '') {
            $problems[] = 'SNAPSHOT_CURRENT_PACKET_INVALID';
        }
        return array_values(array_unique($problems));
    }

    /** @return array<string,mixed> */
    private static function errorResult(array $source, array $target, array $contract, array $diagnostics): array
    {
        return [
            'status' => 'error',
            'effect' => self::EFFECT,
            'snapshot_version' => self::VERSION,
            'snapshot_state' => 'not_created',
            'recorded' => 'no',
            'immutable' => 'yes',
            'append_only' => 'yes',
            'atomic_publish' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'can_archive' => 'no',
            'can_delete' => 'no',
            'execution_authorized' => 'no',
            'grants_execution_authority' => 'no',
            'requires_separate_execution_capability' => 'yes',
            'source' => $source,
            'target' => $target,
            'snapshot' => [
                'source_path' => self::normalizePath((string)($contract['source_path'] ?? $target['target_path'] ?? '')),
                'destination_path' => self::normalizePath((string)($contract['destination_path'] ?? '')),
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    private static function isPlatformAdmin(?array $actor): bool
    {
        return is_array($actor) && strtolower(trim((string)($actor['authority_role'] ?? ''))) === 'platform_admin';
    }

    /** @return array<string,string> */
    private static function actorRecord(array $actor): array
    {
        $id = trim((string)($actor['user_id'] ?? $actor['id'] ?? $actor['actor_id'] ?? ''));
        $label = trim((string)($actor['display_name'] ?? $actor['name'] ?? $actor['email'] ?? $actor['username'] ?? $id));
        return [
            'actor_id' => $id,
            'display_name' => $label,
            'authority_role' => strtolower(trim((string)($actor['authority_role'] ?? ''))),
        ];
    }

    private static function now(?callable $clock): \DateTimeImmutable
    {
        $value = $clock !== null ? $clock() : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        if ($value instanceof \DateTimeImmutable) {
            return $value->setTimezone(new \DateTimeZone('UTC'));
        }
        if ($value instanceof \DateTime) {
            return \DateTimeImmutable::createFromMutable($value)->setTimezone(new \DateTimeZone('UTC'));
        }
        try {
            return (new \DateTimeImmutable(trim((string)$value)))->setTimezone(new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        }
    }

    private static function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    /** @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @return array<int,string> */
    private static function stringList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $items[$item] = true;
            }
        }
        return array_map('strval', array_keys($items));
    }

    private static function diagnostic(string $code, string $message, array $extra = []): array
    {
        return array_merge(['code' => $code, 'severity' => 'error', 'message' => $message], $extra);
    }
}
