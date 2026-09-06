<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionExecutionDryRunService.php';

/** Read-only presenter for the approved deletion execution dry run. */
final class OwnerStructureDeletionExecutionDryRunWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $changeSet
     * @param array<string,mixed>|null $readiness
     * @param callable(array<string,mixed>,array<string,mixed>,array<string,mixed>,?string):array<string,mixed>|null $simulator
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
        ?array $readiness,
        ?string $root = null,
        ?callable $simulator = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'change_set' => $changeSet,
            'readiness' => $readiness,
            'dry_run' => null,
            'dry_run_state' => 'not_simulated',
            'simulation_complete' => 'no',
            'execution_eligible_at_simulation' => 'no',
            'source' => [],
            'summary' => [],
            'preflight_checks' => [],
            'snapshot_plan' => [],
            'simulated_operations' => [],
            'verification_commands' => [],
            'blocking_reasons' => [],
            'dry_run_fingerprint' => '',
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_DRY_RUN_OWNER_UNAVAILABLE', 'The selected owner is unavailable for deletion dry-run simulation.', $selectedOwnerKey);
        }
        if ($changeSet === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_DRY_RUN_CHANGE_SET_REQUIRED', 'A current deletion change set is required before dry-run simulation.', $selectedOwnerKey);
        }
        if ($readiness === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_DRY_RUN_READINESS_REQUIRED', 'A current execution-readiness assessment is required before dry-run simulation.', $selectedOwnerKey);
        }

        try {
            $dryRun = $simulator !== null
                ? $simulator($selectedOwner, $changeSet, $readiness, $root)
                : OwnerStructureDeletionExecutionDryRunService::simulate($selectedOwner, $changeSet, $readiness, $root);
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_EXECUTION_DRY_RUN_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Deletion dry-run simulation failed.',
                $selectedOwnerKey
            );
        }

        if (!is_array($dryRun)) {
            return self::error($base, 'OSS_DELETION_EXECUTION_DRY_RUN_RESULT_INVALID', 'Deletion dry-run capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = 'ready';
        $base['dry_run'] = $dryRun;
        $base['dry_run_state'] = (string)($dryRun['dry_run_state'] ?? 'unknown');
        $base['simulation_complete'] = (string)($dryRun['simulation_complete'] ?? 'no');
        $base['execution_eligible_at_simulation'] = (string)($dryRun['execution_eligible_at_simulation'] ?? 'no');
        $base['source'] = self::arrayValue($dryRun, 'source');
        $base['summary'] = self::arrayValue($dryRun, 'summary');
        $base['preflight_checks'] = self::arrayList($dryRun['preflight_checks'] ?? []);
        $base['snapshot_plan'] = self::arrayValue($dryRun, 'snapshot_plan');
        $base['simulated_operations'] = self::arrayList($dryRun['simulated_operations'] ?? []);
        $base['verification_commands'] = self::arrayList($dryRun['verification_commands'] ?? []);
        $base['blocking_reasons'] = self::stringList($dryRun['blocking_reasons'] ?? []);
        $base['dry_run_fingerprint'] = (string)($dryRun['dry_run_fingerprint'] ?? '');
        $base['diagnostics'] = self::arrayList($dryRun['diagnostics'] ?? []);
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

    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    private static function arrayList($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

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
}
