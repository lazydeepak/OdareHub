<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionSnapshotIntegrityService.php';

use Apps\Studio\Services\StudioDeletionSnapshotIntegrityService;

/** Owner Structure adapter for canonical post-snapshot integrity verification. */
final class OwnerStructureDeletionSnapshotIntegrityService
{
    /** @return array<string,mixed> */
    public static function assess(
        array $selectedOwner,
        array $changeSet,
        array $approvalReadiness,
        array $dryRun,
        array $executorReadiness,
        ?array $claim,
        ?array $manifest,
        ?array $actor,
        ?string $root = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwner = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $packetOwner === '' || $ownerKey !== $packetOwner) {
            return [
                'status' => 'partial',
                'effect' => StudioDeletionSnapshotIntegrityService::EFFECT,
                'snapshot_integrity_version' => StudioDeletionSnapshotIntegrityService::VERSION,
                'snapshot_integrity' => StudioDeletionSnapshotIntegrityService::STATE_UNKNOWN,
                'snapshot_integrity_valid' => 'no',
                'post_snapshot_execution_ready' => 'no',
                'can_execute' => 'no',
                'can_apply' => 'no',
                'can_archive' => 'no',
                'can_delete' => 'no',
                'grants_execution_authority' => 'no',
                'requires_separate_execution_capability' => 'yes',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_SNAPSHOT_INTEGRITY_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current snapshot target.',
                    'path' => $ownerKey,
                ]],
            ];
        }

        return StudioDeletionSnapshotIntegrityService::assess(
            $changeSet,
            $approvalReadiness,
            $dryRun,
            $executorReadiness,
            $claim,
            $manifest,
            $actor,
            $root
        );
    }
}
