<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionExecutionReadinessService.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionApprovalRecordStore.php';

use Apps\Studio\Services\StudioDeletionApprovalRecordStore;

/** Read-only presenter for approval validity and deletion execution readiness. */
final class OwnerStructureDeletionExecutionReadinessWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $changeSet
     * @param callable(string,string,?string):array<string,mixed>|null|null $exactReader
     * @param callable(string,?string):array<string,mixed>|null|null $ownerReader
     * @param callable(array<string,mixed>,array<string,mixed>,?array,?array):array<string,mixed>|null $assessor
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
        ?string $root = null,
        ?callable $exactReader = null,
        ?callable $ownerReader = null,
        ?callable $assessor = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'change_set' => $changeSet,
            'assessment' => null,
            'execution_readiness' => 'not_assessed',
            'execution_eligible' => 'no',
            'approval_valid' => 'no',
            'current_packet' => [],
            'approval_evidence' => [],
            'blocking_reasons' => [],
            'readiness_fingerprint' => '',
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_READINESS_OWNER_UNAVAILABLE', 'The selected owner is unavailable for execution-readiness verification.', $selectedOwnerKey);
        }
        if ($changeSet === null) {
            return self::error($base, 'OSS_DELETION_EXECUTION_READINESS_CHANGE_SET_REQUIRED', 'A current deletion change set is required before execution readiness can be verified.', $selectedOwnerKey);
        }

        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($changeSetFingerprint === '' || $packetOwnerKey !== $selectedOwnerKey) {
            return self::error($base, 'OSS_DELETION_EXECUTION_READINESS_CHANGE_SET_INVALID', 'The current deletion change set is missing identity or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $exactApproval = $exactReader !== null
                ? $exactReader($selectedOwnerKey, $changeSetFingerprint, $root)
                : StudioDeletionApprovalRecordStore::latest($selectedOwnerKey, $changeSetFingerprint, $root);
            $latestOwnerApproval = $ownerReader !== null
                ? $ownerReader($selectedOwnerKey, $root)
                : StudioDeletionApprovalRecordStore::latestForOwner($selectedOwnerKey, $root);
            $assessment = $assessor !== null
                ? $assessor($selectedOwner, $changeSet, is_array($exactApproval) ? $exactApproval : null, is_array($latestOwnerApproval) ? $latestOwnerApproval : null)
                : OwnerStructureDeletionExecutionReadinessService::assess(
                    $selectedOwner,
                    $changeSet,
                    is_array($exactApproval) ? $exactApproval : null,
                    is_array($latestOwnerApproval) ? $latestOwnerApproval : null
                );
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_EXECUTION_READINESS_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Execution-readiness verification failed.',
                $selectedOwnerKey
            );
        }

        if (!is_array($assessment)) {
            return self::error($base, 'OSS_DELETION_EXECUTION_READINESS_RESULT_INVALID', 'Execution-readiness capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = 'ready';
        $base['assessment'] = $assessment;
        $base['execution_readiness'] = (string)($assessment['execution_readiness'] ?? 'unknown');
        $base['execution_eligible'] = (string)($assessment['execution_eligible'] ?? 'no');
        $base['approval_valid'] = (string)($assessment['approval_valid'] ?? 'no');
        $base['current_packet'] = self::arrayValue($assessment, 'current_packet');
        $base['approval_evidence'] = self::arrayValue($assessment, 'approval_evidence');
        $base['blocking_reasons'] = self::stringList($assessment['blocking_reasons'] ?? []);
        $base['readiness_fingerprint'] = (string)($assessment['readiness_fingerprint'] ?? '');
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

    /** @param array<string,mixed> $source @return array<string,mixed> */
    private static function arrayValue(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    /** @param mixed $value @return array<int,array<string,mixed>> */
    private static function arrayList($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /** @param mixed $value @return array<int,string> */
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
