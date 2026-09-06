<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionSnapshotIntegrityService.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionClaimStore.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionSnapshotStore.php';

use Apps\Studio\Services\StudioDeletionExecutionClaimStore;
use Apps\Studio\Services\StudioDeletionSnapshotStore;

/** Read-only presenter for snapshot integrity and post-snapshot execution readiness. */
final class OwnerStructureDeletionSnapshotIntegrityWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param callable(string,?string):array<string,mixed>|null|null $claimReader
     * @param callable(string,?string):array<string,mixed>|null|null $manifestReader
     * @param callable(array<string,mixed>,array<string,mixed>,array<string,mixed>,array<string,mixed>,array<string,mixed>,?array,?array,?array,?string):array<string,mixed>|null $assessor
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
        ?array $approvalReadiness,
        ?array $dryRun,
        ?array $executorReadiness,
        ?array $actor,
        ?string $root = null,
        ?callable $claimReader = null,
        ?callable $manifestReader = null,
        ?callable $assessor = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'assessment' => null,
            'snapshot_integrity' => 'not_assessed',
            'snapshot_integrity_valid' => 'no',
            'post_snapshot_execution_ready' => 'no',
            'manifest_valid' => 'no',
            'checksums_valid' => 'no',
            'source_matches_snapshot' => 'no',
            'claim_valid' => 'no',
            'executor_identity_valid' => 'no',
            'current_packet' => [],
            'claim_evidence' => [],
            'actor_evidence' => [],
            'snapshot_evidence' => [],
            'snapshot_checks' => [],
            'source_checks' => [],
            'blocking_reasons' => [],
            'snapshot_integrity_fingerprint' => '',
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_INTEGRITY_OWNER_UNAVAILABLE', 'The selected owner is unavailable for snapshot-integrity verification.', $selectedOwnerKey);
        }
        if ($changeSet === null || $approvalReadiness === null || $dryRun === null || $executorReadiness === null) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_INTEGRITY_PREREQUISITES_REQUIRED', 'Current change-set, approval readiness, dry-run, and executor-readiness evidence are required.', $selectedOwnerKey);
        }

        $targetOwner = trim((string)($changeSet['target']['owner_key'] ?? ''));
        $requestId = trim((string)($executorReadiness['request_evidence']['request_id'] ?? ''));
        $destinationPath = trim((string)($dryRun['snapshot_plan']['destination_path'] ?? ''));
        if ($targetOwner !== $selectedOwnerKey || $requestId === '' || $destinationPath === '') {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_INTEGRITY_PACKET_INVALID', 'The current packet is missing request or snapshot identity, or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $claim = $claimReader !== null
                ? $claimReader($requestId, $root)
                : StudioDeletionExecutionClaimStore::latest($requestId, $root);
            $manifest = $manifestReader !== null
                ? $manifestReader($destinationPath, $root)
                : StudioDeletionSnapshotStore::read($destinationPath, $root);
            $assessment = $assessor !== null
                ? $assessor($selectedOwner, $changeSet, $approvalReadiness, $dryRun, $executorReadiness, is_array($claim) ? $claim : null, is_array($manifest) ? $manifest : null, $actor, $root)
                : OwnerStructureDeletionSnapshotIntegrityService::assess($selectedOwner, $changeSet, $approvalReadiness, $dryRun, $executorReadiness, is_array($claim) ? $claim : null, is_array($manifest) ? $manifest : null, $actor, $root);
        } catch (\Throwable $exception) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_INTEGRITY_FAILED', $exception->getMessage() !== '' ? $exception->getMessage() : 'Snapshot-integrity verification failed.', $selectedOwnerKey);
        }

        if (!is_array($assessment)) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_INTEGRITY_RESULT_INVALID', 'Snapshot-integrity capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = 'ready';
        $base['assessment'] = $assessment;
        foreach (['snapshot_integrity', 'snapshot_integrity_valid', 'post_snapshot_execution_ready', 'manifest_valid', 'checksums_valid', 'source_matches_snapshot', 'claim_valid', 'executor_identity_valid', 'snapshot_integrity_fingerprint'] as $field) {
            $base[$field] = (string)($assessment[$field] ?? $base[$field]);
        }
        $base['current_packet'] = self::arr($assessment, 'current_packet');
        $base['claim_evidence'] = self::arr($assessment, 'claim_evidence');
        $base['actor_evidence'] = self::arr($assessment, 'actor_evidence');
        $base['snapshot_evidence'] = self::arr($assessment, 'snapshot_evidence');
        $base['snapshot_checks'] = self::arrays($assessment['snapshot_checks'] ?? []);
        $base['source_checks'] = self::arrays($assessment['source_checks'] ?? []);
        $base['blocking_reasons'] = self::strings($assessment['blocking_reasons'] ?? []);
        $base['diagnostics'] = self::arrays($assessment['diagnostics'] ?? []);
        return $base;
    }

    /** @param array<string,mixed> $base @return array<string,mixed> */
    private static function error(array $base, string $code, string $message, string $path): array
    {
        $base['status'] = 'error';
        $base['diagnostics'][] = ['code' => $code, 'severity' => 'error', 'message' => $message, 'path' => $path];
        return $base;
    }

    /** @param array<int,array<string,mixed>> $owners @return array<string,mixed>|null */
    private static function findOwner(array $owners, string $selectedOwnerKey): ?array
    {
        foreach ($owners as $owner) {
            if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $selectedOwnerKey) {
                return $owner;
            }
        }
        return null;
    }

    /** @return array<string,mixed> */
    private static function arr(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @return array<int,array<string,mixed>> */
    private static function arrays($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /** @return array<int,string> */
    private static function strings($value): array
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
}
