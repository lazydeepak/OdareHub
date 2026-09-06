<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionExecutorReadinessService.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionRequestStore.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionRequestUseEvidenceStore.php';

use Apps\Studio\Services\StudioDeletionExecutionRequestStore;
use Apps\Studio\Services\StudioDeletionExecutionRequestUseEvidenceStore;

/** Read-only presenter for execution-request validity and named-executor readiness. */
final class OwnerStructureDeletionExecutorReadinessWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param callable(string,string,string,?string):array<string,mixed>|null|null $exactReader
     * @param callable(string,?string):array<string,mixed>|null|null $ownerReader
     * @param callable(string,?string):array<string,mixed>|null|null $useReader
     * @param callable(array<string,mixed>,array<string,mixed>,array<string,mixed>,array<string,mixed>,?array,?array,?array,?array):array<string,mixed>|null $assessor
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
        ?array $readiness,
        ?array $dryRun,
        ?array $actor,
        ?string $root = null,
        ?callable $exactReader = null,
        ?callable $ownerReader = null,
        ?callable $useReader = null,
        ?callable $assessor = null
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
            'actor' => $actor,
            'assessment' => null,
            'executor_readiness' => 'not_assessed',
            'executor_eligible' => 'no',
            'request_valid' => 'no',
            'executor_identity_valid' => 'no',
            'single_use_available' => 'no',
            'current_packet' => [],
            'request_evidence' => [],
            'actor_evidence' => [],
            'single_use_evidence' => [],
            'blocking_reasons' => [],
            'executor_readiness_fingerprint' => '',
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_EXECUTOR_READINESS_OWNER_UNAVAILABLE', 'The selected owner is unavailable for executor-readiness verification.', $selectedOwnerKey);
        }
        if ($changeSet === null || $readiness === null || $dryRun === null) {
            return self::error($base, 'OSS_DELETION_EXECUTOR_READINESS_PACKET_REQUIRED', 'Current change-set, approval readiness, and dry-run evidence are required.', $selectedOwnerKey);
        }

        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $dryRunFingerprint = trim((string)($dryRun['dry_run_fingerprint'] ?? ''));
        $targetOwner = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($changeSetFingerprint === '' || $dryRunFingerprint === '' || $targetOwner !== $selectedOwnerKey) {
            return self::error($base, 'OSS_DELETION_EXECUTOR_READINESS_PACKET_INVALID', 'The current deletion packet identity is missing or targets another owner.', $selectedOwnerKey);
        }

        try {
            $exactRequest = $exactReader !== null
                ? $exactReader($selectedOwnerKey, $changeSetFingerprint, $dryRunFingerprint, $root)
                : StudioDeletionExecutionRequestStore::latest($selectedOwnerKey, $changeSetFingerprint, $dryRunFingerprint, $root);
            $latestOwnerRequest = $ownerReader !== null
                ? $ownerReader($selectedOwnerKey, $root)
                : StudioDeletionExecutionRequestStore::latestForOwner($selectedOwnerKey, $root);
            $requestId = is_array($exactRequest) ? trim((string)($exactRequest['request_id'] ?? '')) : '';
            $useEvidence = $requestId !== ''
                ? ($useReader !== null ? $useReader($requestId, $root) : StudioDeletionExecutionRequestUseEvidenceStore::latest($requestId, $root))
                : null;
            $assessment = $assessor !== null
                ? $assessor($selectedOwner, $changeSet, $readiness, $dryRun, is_array($exactRequest) ? $exactRequest : null, is_array($latestOwnerRequest) ? $latestOwnerRequest : null, $actor, is_array($useEvidence) ? $useEvidence : null)
                : OwnerStructureDeletionExecutorReadinessService::assess(
                    $selectedOwner,
                    $changeSet,
                    $readiness,
                    $dryRun,
                    is_array($exactRequest) ? $exactRequest : null,
                    is_array($latestOwnerRequest) ? $latestOwnerRequest : null,
                    $actor,
                    is_array($useEvidence) ? $useEvidence : null
                );
        } catch (\Throwable $exception) {
            return self::error($base, 'OSS_DELETION_EXECUTOR_READINESS_FAILED', $exception->getMessage() !== '' ? $exception->getMessage() : 'Executor-readiness verification failed.', $selectedOwnerKey);
        }

        if (!is_array($assessment)) {
            return self::error($base, 'OSS_DELETION_EXECUTOR_READINESS_RESULT_INVALID', 'Executor-readiness capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = 'ready';
        $base['assessment'] = $assessment;
        foreach (['executor_readiness', 'executor_eligible', 'request_valid', 'executor_identity_valid', 'single_use_available', 'executor_readiness_fingerprint'] as $field) {
            $base[$field] = (string)($assessment[$field] ?? $base[$field]);
        }
        $base['current_packet'] = self::arrayValue($assessment, 'current_packet');
        $base['request_evidence'] = self::arrayValue($assessment, 'request_evidence');
        $base['actor_evidence'] = self::arrayValue($assessment, 'actor_evidence');
        $base['single_use_evidence'] = self::arrayValue($assessment, 'single_use_evidence');
        $base['blocking_reasons'] = self::stringList($assessment['blocking_reasons'] ?? []);
        $base['diagnostics'] = self::arrayList($assessment['diagnostics'] ?? []);
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
        if (!is_array($value)) { return []; }
        $items = [];
        foreach ($value as $item) {
            $item = trim((string)$item);
            if ($item !== '') { $items[$item] = true; }
        }
        return array_map('strval', array_keys($items));
    }
}
