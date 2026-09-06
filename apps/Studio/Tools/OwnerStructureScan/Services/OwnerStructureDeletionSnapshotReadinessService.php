<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionSnapshotReadinessService.php';

use Apps\Studio\Services\StudioDeletionSnapshotReadinessService;

/** Owner Structure adapter for canonical deletion snapshot-readiness verification. */
final class OwnerStructureDeletionSnapshotReadinessService
{
    /** @return array<string,mixed> */
    public static function assess(
        array $selectedOwner,
        array $changeSet,
        array $approvalReadiness,
        array $dryRun,
        array $executorReadiness,
        ?array $claim,
        ?array $actor,
        ?string $root = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'partial',
                'effect' => StudioDeletionSnapshotReadinessService::EFFECT,
                'snapshot_readiness_version' => StudioDeletionSnapshotReadinessService::VERSION,
                'snapshot_readiness' => 'unknown',
                'snapshot_ready' => 'no',
                'claim_valid' => 'no',
                'executor_identity_valid' => 'no',
                'can_snapshot' => 'no',
                'would_write' => 'no',
                'would_archive' => 'no',
                'would_delete' => 'no',
                'grants_execution_authority' => 'no',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_SNAPSHOT_READINESS_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current claimed deletion target.',
                    'path' => $ownerKey,
                ]],
            ];
        }
        return StudioDeletionSnapshotReadinessService::assess($changeSet, $approvalReadiness, $dryRun, $executorReadiness, $claim, $actor, $root);
    }
}
