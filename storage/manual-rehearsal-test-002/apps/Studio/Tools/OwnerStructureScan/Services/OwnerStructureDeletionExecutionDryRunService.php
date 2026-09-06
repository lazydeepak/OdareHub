<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionDryRunService.php';

use Apps\Studio\Services\StudioDeletionExecutionDryRunService;

/** Owner Structure adapter for canonical deletion execution dry-run simulation. */
final class OwnerStructureDeletionExecutionDryRunService
{
    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $changeSet
     * @param array<string,mixed> $readiness
     * @param callable(string,string):array<string,mixed>|null $inspector
     * @return array<string,mixed>
     */
    public static function simulate(
        array $selectedOwner,
        array $changeSet,
        array $readiness,
        ?string $root = null,
        ?callable $inspector = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $ownerKey !== $packetOwnerKey) {
            return [
                'status' => 'partial',
                'effect' => 'simulate',
                'dry_run_version' => StudioDeletionExecutionDryRunService::VERSION,
                'dry_run_state' => 'unknown',
                'simulation_complete' => 'no',
                'would_execute' => 'no',
                'would_write' => 'no',
                'would_delete' => 'no',
                'can_execute' => 'no',
                'grants_execution_authority' => 'no',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_EXECUTION_DRY_RUN_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current deletion change-set target.',
                    'path' => $ownerKey,
                ]],
            ];
        }
        return StudioDeletionExecutionDryRunService::simulate($changeSet, $readiness, $root, $inspector);
    }
}
