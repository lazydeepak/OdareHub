<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionExecutionRequestService.php';

/** Read-only presenter for the immutable deletion execution request workspace. */
final class OwnerStructureDeletionExecutionRequestWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $changeSet
     * @param array<string,mixed>|null $readiness
     * @param array<string,mixed>|null $dryRun
     * @param array<string,mixed>|null $flash
     * @param callable(array<string,mixed>,array<string,mixed>,?string):array<string,mixed>|null|null $latestReader
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
        ?array $readiness,
        ?array $dryRun,
        ?array $flash = null,
        ?string $root = null,
        ?callable $latestReader = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'change_set' => $changeSet,
            'readiness' => $readiness,
            'dry_run' => $dryRun,
            'can_request' => 'no',
            'source' => [],
            'target' => [],
            'dry_run_state' => 'unknown',
            'execution_readiness' => 'unknown',
            'latest_request' => null,
            'flash' => $flash,
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_REQUEST_OWNER_UNAVAILABLE', 'The selected owner is unavailable for an execution request.', $selectedOwnerKey);
        }
        if ($changeSet === null || $readiness === null || $dryRun === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_REQUEST_PREREQUISITES_REQUIRED', 'The current change set, readiness assessment, and dry run are required before requesting execution.', $selectedOwnerKey);
        }

        $ownerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $dryRunFingerprint = trim((string)($dryRun['dry_run_fingerprint'] ?? ''));
        if ($ownerKey !== $selectedOwnerKey || $changeSetFingerprint === '' || $dryRunFingerprint === '') {
            return self::error($base, 'OSS_DELETION_EXECUTION_REQUEST_PACKET_INVALID', 'The current deletion packet is missing identity or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $latest = $latestReader !== null
                ? $latestReader($changeSet, $dryRun, $root)
                : OwnerStructureDeletionExecutionRequestService::latest($changeSet, $dryRun, $root);
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_EXECUTION_REQUEST_HISTORY_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Execution request history could not be read.',
                $selectedOwnerKey
            );
        }

        $readinessState = (string)($readiness['execution_readiness'] ?? 'unknown');
        $dryRunState = (string)($dryRun['dry_run_state'] ?? 'unknown');
        $canRequest = $readinessState === 'ready'
            && (string)($readiness['execution_eligible'] ?? 'no') === 'yes'
            && $dryRunState === 'ready'
            && (string)($dryRun['simulation_complete'] ?? 'no') === 'yes'
            && (string)($dryRun['execution_eligible_at_simulation'] ?? 'no') === 'yes'
            && self::stringList($dryRun['blocking_reasons'] ?? []) === [];

        $base['status'] = 'ready';
        $base['can_request'] = $canRequest ? 'yes' : 'no';
        $base['source'] = [
            'change_set_fingerprint' => $changeSetFingerprint,
            'plan_fingerprint' => (string)($changeSet['source_plan']['fingerprint'] ?? ''),
            'readiness_fingerprint' => (string)($readiness['readiness_fingerprint'] ?? ''),
            'dry_run_fingerprint' => $dryRunFingerprint,
            'approval_record_id' => (string)($readiness['approval_evidence']['record_id'] ?? ''),
            'approval_record_fingerprint' => (string)($readiness['approval_evidence']['record_fingerprint'] ?? ''),
        ];
        $base['target'] = self::arrayValue($changeSet, 'target');
        $base['dry_run_state'] = $dryRunState;
        $base['execution_readiness'] = $readinessState;
        $base['latest_request'] = is_array($latest) ? $latest : null;
        if (!$canRequest) {
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_EXECUTION_REQUEST_NOT_READY',
                'severity' => 'error',
                'message' => 'The current deletion packet is not ready for an execution request.',
                'path' => $selectedOwnerKey,
            ];
        }
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
}
