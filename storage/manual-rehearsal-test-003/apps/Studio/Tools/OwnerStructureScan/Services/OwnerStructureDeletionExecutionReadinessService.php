<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionReadinessService.php';

use Apps\Studio\Services\StudioDeletionExecutionReadinessService;

/** Owner Structure adapter for canonical deletion execution-readiness verification. */
final class OwnerStructureDeletionExecutionReadinessService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed>|null $exactApproval
     * @param array<string,mixed>|null $latestOwnerApproval
     * @return array<string,mixed>
     */
    public static function assess(
        array $selectedOwner,
        array $changeSet,
        ?array $exactApproval,
        ?array $latestOwnerApproval = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'partial',
                'effect' => 'verify',
                'readiness_version' => StudioDeletionExecutionReadinessService::VERSION,
                'execution_readiness' => 'unknown',
                'execution_eligible' => 'no',
                'approval_valid' => 'no',
                'can_execute' => 'no',
                'can_apply' => 'no',
                'grants_execution_authority' => 'no',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_EXECUTION_READINESS_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current deletion change-set target.',
                    'path' => $ownerKey,
                ]],
            ];
        }

        return StudioDeletionExecutionReadinessService::assess($changeSet, $exactApproval, $latestOwnerApproval);
    }
}
