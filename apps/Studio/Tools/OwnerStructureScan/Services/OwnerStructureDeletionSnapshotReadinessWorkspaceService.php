<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionSnapshotReadinessService.php';
require_once dirname(__DIR__, 3) . '/Services/StudioDeletionExecutionClaimStore.php';

use Apps\Studio\Services\StudioDeletionExecutionClaimStore;

/** Read-only presenter for claim validity and snapshot readiness. */
final class OwnerStructureDeletionSnapshotReadinessWorkspaceService
{
    /** @return array<string,mixed> */
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
        ?callable $assessor = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'change_set' => $changeSet,
            'approval_readiness' => $approvalReadiness,
            'dry_run' => $dryRun,
            'executor_readiness_result' => $executorReadiness,
            'actor' => $actor,
            'assessment' => null,
            'snapshot_readiness' => 'not_assessed',
            'snapshot_ready' => 'no',
            'claim_valid' => 'no',
            'executor_identity_valid' => 'no',
            'source_available' => 'no',
            'destination_available' => 'no',
            'current_packet' => [],
            'claim_evidence' => [],
            'actor_evidence' => [],
            'snapshot_contract' => [],
            'preflight_checks' => [],
            'blocking_reasons' => [],
            'snapshot_readiness_fingerprint' => '',
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_READINESS_OWNER_UNAVAILABLE', 'The selected owner is unavailable for snapshot-readiness verification.', $selectedOwnerKey);
        }
        if ($changeSet === null || $approvalReadiness === null || $dryRun === null || $executorReadiness === null) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_READINESS_PREREQUISITES_REQUIRED', 'Current change-set, approval readiness, dry-run, and executor-readiness evidence are required.', $selectedOwnerKey);
        }

        $targetOwner = trim((string)($changeSet['target']['owner_key'] ?? ''));
        $requestId = trim((string)($executorReadiness['request_evidence']['request_id'] ?? ''));
        if ($targetOwner !== $selectedOwnerKey || $requestId === '') {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_READINESS_PACKET_INVALID', 'The current claimed packet is missing request identity or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $claim = $claimReader !== null ? $claimReader($requestId, $root) : StudioDeletionExecutionClaimStore::latest($requestId, $root);
            $assessment = $assessor !== null
                ? $assessor($selectedOwner, $changeSet, $approvalReadiness, $dryRun, $executorReadiness, is_array($claim) ? $claim : null, $actor, $root)
                : OwnerStructureDeletionSnapshotReadinessService::assess($selectedOwner, $changeSet, $approvalReadiness, $dryRun, $executorReadiness, is_array($claim) ? $claim : null, $actor, $root);
        } catch (\Throwable $exception) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_READINESS_FAILED', $exception->getMessage() !== '' ? $exception->getMessage() : 'Snapshot-readiness verification failed.', $selectedOwnerKey);
        }

        if (!is_array($assessment)) {
            return self::error($base, 'OSS_DELETION_SNAPSHOT_READINESS_RESULT_INVALID', 'Snapshot-readiness capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = 'ready';
        $base['assessment'] = $assessment;
        foreach (['snapshot_readiness','snapshot_ready','claim_valid','executor_identity_valid','source_available','destination_available','snapshot_readiness_fingerprint'] as $field) {
            $base[$field] = (string)($assessment[$field] ?? $base[$field]);
        }
        $base['current_packet'] = self::arrayValue($assessment, 'current_packet');
        $base['claim_evidence'] = self::arrayValue($assessment, 'claim_evidence');
        $base['actor_evidence'] = self::arrayValue($assessment, 'actor_evidence');
        $base['snapshot_contract'] = self::arrayValue($assessment, 'snapshot_contract');
        $base['preflight_checks'] = self::arrayList($assessment['preflight_checks'] ?? []);
        $base['blocking_reasons'] = self::stringList($assessment['blocking_reasons'] ?? []);
        $base['diagnostics'] = self::arrayList($assessment['diagnostics'] ?? []);
        return $base;
    }

    private static function error(array $base, string $code, string $message, string $path): array
    {
        $base['status'] = 'error';
        $base['diagnostics'][] = ['code' => $code, 'severity' => 'error', 'message' => $message, 'path' => $path];
        return $base;
    }

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
