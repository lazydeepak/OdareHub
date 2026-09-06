<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutorReadinessService.php';

use Apps\Studio\Services\StudioDeletionExecutorReadinessService;

/** Owner Structure adapter for canonical executor-readiness verification. */
final class OwnerStructureDeletionExecutorReadinessService
{
    /** @return array<string,mixed> */
    public static function assess(
        array $selectedOwner,
        array $changeSet,
        array $readiness,
        array $dryRun,
        ?array $exactRequest,
        ?array $latestOwnerRequest,
        ?array $actor,
        ?array $useEvidence = null,
        ?callable $clock = null
    ): array {
        $selectedOwnerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $targetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($selectedOwnerKey === '' || $selectedOwnerKey !== $targetOwnerKey) {
            return [
                'status' => 'partial',
                'effect' => 'verify',
                'executor_readiness_version' => StudioDeletionExecutorReadinessService::VERSION,
                'executor_readiness' => 'unknown',
                'executor_eligible' => 'no',
                'request_valid' => 'no',
                'executor_identity_valid' => 'no',
                'single_use_available' => 'no',
                'can_claim' => 'no',
                'can_execute' => 'no',
                'grants_execution_authority' => 'no',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_EXECUTOR_READINESS_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current deletion target.',
                    'path' => $selectedOwnerKey,
                ]],
            ];
        }

        return StudioDeletionExecutorReadinessService::assess(
            $changeSet,
            $readiness,
            $dryRun,
            $exactRequest,
            $latestOwnerRequest,
            $actor,
            $useEvidence,
            $clock
        );
    }
}
