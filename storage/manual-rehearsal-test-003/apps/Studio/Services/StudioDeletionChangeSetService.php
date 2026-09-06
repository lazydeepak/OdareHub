<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionPlanService.php';

/**
 * Canonical read-only Studio capability that converts a deletion plan into an
 * immutable review packet. It never mutates repository state or grants
 * execution authority.
 */
final class StudioDeletionChangeSetService
{
    public const EFFECT = 'plan';
    public const VERSION = 'studio.deletion-change-set.v1';

    public const STATE_READY_FOR_REVIEW = 'ready_for_review';
    public const STATE_REVIEW_REQUIRED = 'review_required';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public static function build(array $request, ?string $root = null): array
    {
        return self::compose($request, StudioDeletionPlanService::plan($request, $root));
    }

    /** @param array<string,mixed> $request @param array<string,mixed> $plan @return array<string,mixed> */
    public static function compose(array $request, array $plan): array
    {
        $target = self::arrayValue($plan, 'target');
        $ownerKey = trim((string)($target['owner_key'] ?? $request['owner_key'] ?? ''));
        $targetType = trim((string)($target['target_type'] ?? $request['target_type'] ?? 'owner'));
        $targetPath = self::normalizePath((string)($target['target_path'] ?? ''));
        $planVersion = trim((string)($plan['plan_version'] ?? ''));
        $planState = trim((string)($plan['plan_state'] ?? 'unknown'));
        $planFingerprint = trim((string)($plan['plan_fingerprint'] ?? ''));
        $operations = self::arrayList($plan['operations'] ?? []);
        $archiveScope = self::arrayList($plan['archive_scope'] ?? []);
        $postDeletionChecks = self::arrayList($plan['post_deletion_checks'] ?? []);
        $blockingReasons = self::stringList($plan['blocking_reasons'] ?? []);
        $diagnostics = self::arrayList($plan['diagnostics'] ?? []);

        $invalidReasons = [];
        if ($ownerKey === '' || $targetPath === '') {
            $invalidReasons[] = 'TARGET_UNRESOLVED';
        }
        if ($planVersion === '' || $planFingerprint === '') {
            $invalidReasons[] = 'PLAN_IDENTITY_MISSING';
        }
        if (!in_array($planState, ['ready', 'needs_review', 'blocked', 'unknown'], true)) {
            $invalidReasons[] = 'PLAN_STATE_INVALID';
        }
        if ((string)($plan['can_execute'] ?? 'no') !== 'no') {
            $invalidReasons[] = 'SOURCE_PLAN_EXECUTION_AUTHORITY_INVALID';
        }

        if ($invalidReasons !== []) {
            $diagnostics[] = [
                'code' => 'DELETION_CHANGE_SET_PLAN_INVALID',
                'severity' => 'error',
                'message' => 'Deletion change-set generation requires a resolved, identified, non-executable deletion plan.',
                'path' => $targetPath,
                'reasons' => $invalidReasons,
            ];
            return self::unknownResult($target, $plan, $blockingReasons, $diagnostics);
        }

        $operationManifest = self::operationManifest($operations);
        $fileChanges = self::fileChanges($operationManifest);
        $archiveChanges = self::archiveChanges($operationManifest, $archiveScope);
        $deleteChanges = self::deleteChanges($operationManifest);
        $verificationContract = self::verificationContract($postDeletionChecks, $operationManifest, $ownerKey, $targetType, $targetPath);
        $snapshotContract = self::snapshotContract($archiveScope, $operationManifest, $planFingerprint);
        [$packetState, $approvalReadiness, $derivedBlockers] = self::stateFromPlan($planState);
        $blockingReasons = array_values(array_unique(array_merge($blockingReasons, $derivedBlockers)));

        $summary = [
            'operation_count' => count($operationManifest),
            'file_change_count' => count($fileChanges),
            'archive_change_count' => count($archiveChanges),
            'delete_change_count' => count($deleteChanges),
            'verification_check_count' => count($verificationContract['checks']),
            'blocking_change_count' => self::countBySeverity($fileChanges, 'blocking'),
            'review_change_count' => self::countBySeverity($fileChanges, 'review'),
            'cleanup_change_count' => self::countBySeverity($fileChanges, 'cleanup'),
        ];

        $approvalContract = [
            'required' => 'yes',
            'approval_scope' => 'deletion_change_set',
            'approval_readiness' => $approvalReadiness,
            'approval_binds_to' => [
                'change_set_fingerprint' => 'self',
                'plan_fingerprint' => $planFingerprint,
                'owner_key' => $ownerKey,
                'target_path' => $targetPath,
            ],
            'required_confirmations' => [
                'target_identity_confirmed',
                'impact_evidence_reviewed',
                'plan_fingerprint_matches',
                'required_reference_changes_resolved',
                'snapshot_created',
                'rollback_source_verified',
                'verification_contract_accepted',
            ],
        ];

        $integrityMaterial = [
            'version' => self::VERSION,
            'source_plan' => [
                'version' => $planVersion,
                'state' => $planState,
                'fingerprint' => $planFingerprint,
            ],
            'target' => [
                'owner_key' => $ownerKey,
                'target_type' => $targetType,
                'target_path' => $targetPath,
            ],
            'operation_manifest' => $operationManifest,
            'file_changes' => $fileChanges,
            'archive_changes' => $archiveChanges,
            'delete_changes' => $deleteChanges,
            'snapshot_contract' => $snapshotContract,
            'verification_contract' => $verificationContract,
            'approval_contract' => $approvalContract,
            'blocking_reasons' => $blockingReasons,
        ];
        $changeSetFingerprint = self::fingerprint($integrityMaterial);
        $approvalContract['approval_binds_to']['change_set_fingerprint'] = $changeSetFingerprint;

        return [
            'status' => $packetState === self::STATE_UNKNOWN ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'change_set_version' => self::VERSION,
            'change_set_state' => $packetState,
            'immutable' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'requires_approval' => 'yes',
            'requires_snapshot' => 'yes',
            'target' => [
                'owner_key' => $ownerKey,
                'target_type' => $targetType,
                'target_path' => $targetPath,
            ],
            'source_plan' => [
                'version' => $planVersion,
                'state' => $planState,
                'fingerprint' => $planFingerprint,
            ],
            'summary' => $summary,
            'operation_manifest' => $operationManifest,
            'proposed_changes' => [
                'file_changes' => $fileChanges,
                'archive_changes' => $archiveChanges,
                'delete_changes' => $deleteChanges,
            ],
            'approval_contract' => $approvalContract,
            'snapshot_contract' => $snapshotContract,
            'verification_contract' => $verificationContract,
            'blocking_reasons' => $blockingReasons,
            'dependent_owners' => self::arrayList($plan['dependent_owners'] ?? []),
            'change_set_fingerprint' => $changeSetFingerprint,
            'integrity_contract' => [
                'algorithm' => 'sha1-canonical-json-v1',
                'source_plan_fingerprint' => $planFingerprint,
                'change_set_fingerprint' => $changeSetFingerprint,
                'recompute_before_approval' => 'yes',
                'reject_on_mismatch' => 'yes',
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<int,array<string,mixed>> $operations @return array<int,array<string,mixed>> */
    private static function operationManifest(array $operations): array
    {
        $manifest = [];
        $seen = [];
        foreach ($operations as $operation) {
            $operationId = trim((string)($operation['operation_id'] ?? ''));
            if ($operationId === '' || isset($seen[$operationId])) {
                continue;
            }
            $seen[$operationId] = true;
            $manifest[] = [
                'sequence' => count($manifest) + 1,
                'operation_id' => $operationId,
                'phase' => trim((string)($operation['phase'] ?? '')),
                'operation_type' => trim((string)($operation['operation_type'] ?? '')),
                'owner_key' => trim((string)($operation['owner_key'] ?? '')),
                'owner_type' => trim((string)($operation['owner_type'] ?? '')),
                'target_type' => trim((string)($operation['target_type'] ?? '')),
                'path' => self::normalizePath((string)($operation['path'] ?? '')),
                'line_numbers' => self::integerList($operation['line_numbers'] ?? []),
                'reference_count' => (int)($operation['reference_count'] ?? 0),
                'relevance' => self::stringList($operation['relevance'] ?? []),
                'severity' => trim((string)($operation['severity'] ?? '')),
                'required' => (string)($operation['required'] ?? 'no'),
                'blocking' => (string)($operation['blocking'] ?? 'no'),
                'execution_state' => trim((string)($operation['execution_state'] ?? 'planned')),
                'reason' => trim((string)($operation['reason'] ?? '')),
                'depends_on' => self::stringList($operation['depends_on'] ?? []),
            ];
        }
        return $manifest;
    }

    /** @param array<int,array<string,mixed>> $manifest @return array<int,array<string,mixed>> */
    private static function fileChanges(array $manifest): array
    {
        $changes = [];
        foreach ($manifest as $operation) {
            if ((string)($operation['phase'] ?? '') !== 'prepare') {
                continue;
            }
            $severity = (string)($operation['severity'] ?? 'review');
            $changes[] = [
                'change_id' => 'change-set:file:' . substr(sha1((string)$operation['operation_id']), 0, 14),
                'operation_id' => (string)$operation['operation_id'],
                'owner_key' => (string)$operation['owner_key'],
                'path' => (string)$operation['path'],
                'change_type' => (string)$operation['operation_type'],
                'line_numbers' => self::integerList($operation['line_numbers'] ?? []),
                'reference_count' => (int)($operation['reference_count'] ?? 0),
                'severity' => $severity,
                'required' => (string)$operation['required'],
                'decision_required' => in_array($severity, ['blocking', 'review'], true) ? 'yes' : 'no',
                'reason' => (string)$operation['reason'],
            ];
        }
        return $changes;
    }

    /** @param array<int,array<string,mixed>> $manifest @param array<int,array<string,mixed>> $archiveScope @return array<int,array<string,mixed>> */
    private static function archiveChanges(array $manifest, array $archiveScope): array
    {
        $archiveOperations = [];
        foreach ($manifest as $operation) {
            if ((string)($operation['operation_type'] ?? '') === 'archive_target') {
                $archiveOperations[(string)($operation['path'] ?? '')] = (string)($operation['operation_id'] ?? '');
            }
        }
        $changes = [];
        foreach ($archiveScope as $scope) {
            $path = self::normalizePath((string)($scope['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $changes[] = [
                'change_id' => 'change-set:archive:' . substr(sha1($path), 0, 14),
                'operation_id' => (string)($archiveOperations[$path] ?? ''),
                'scope' => (string)($scope['scope'] ?? 'target'),
                'owner_key' => (string)($scope['owner_key'] ?? ''),
                'target_type' => (string)($scope['target_type'] ?? ''),
                'path' => $path,
                'required' => (string)($scope['required'] ?? 'yes'),
                'reason' => (string)($scope['reason'] ?? 'ROLLBACK_SOURCE_REQUIRED'),
            ];
        }
        return $changes;
    }

    /** @param array<int,array<string,mixed>> $manifest @return array<int,array<string,mixed>> */
    private static function deleteChanges(array $manifest): array
    {
        $changes = [];
        foreach ($manifest as $operation) {
            if ((string)($operation['operation_type'] ?? '') !== 'delete_target') {
                continue;
            }
            $changes[] = [
                'change_id' => 'change-set:delete:' . substr(sha1((string)$operation['operation_id']), 0, 14),
                'operation_id' => (string)$operation['operation_id'],
                'owner_key' => (string)$operation['owner_key'],
                'target_type' => (string)$operation['target_type'],
                'path' => (string)$operation['path'],
                'required' => (string)$operation['required'],
                'blocked' => (string)$operation['blocking'],
                'depends_on' => self::stringList($operation['depends_on'] ?? []),
                'reason' => (string)$operation['reason'],
            ];
        }
        return $changes;
    }

    /** @param array<int,array<string,mixed>> $checks @param array<int,array<string,mixed>> $manifest @return array<string,mixed> */
    private static function verificationContract(array $checks, array $manifest, string $ownerKey, string $targetType, string $targetPath): array
    {
        $operationById = [];
        foreach ($manifest as $operation) {
            $operationById[(string)($operation['operation_id'] ?? '')] = $operation;
        }
        $normalized = [];
        foreach ($checks as $check) {
            $checkId = trim((string)($check['check_id'] ?? ''));
            if ($checkId === '') {
                continue;
            }
            $operation = $operationById[$checkId] ?? [];
            $normalized[] = [
                'check_id' => $checkId,
                'check_type' => (string)($check['check_type'] ?? $operation['operation_type'] ?? ''),
                'owner_key' => (string)($check['owner_key'] ?? $ownerKey),
                'target_type' => (string)($operation['target_type'] ?? $targetType),
                'path' => self::normalizePath((string)($check['path'] ?? $targetPath)),
                'required' => (string)($check['required'] ?? 'yes'),
                'expected_evidence' => self::expectedEvidence((string)($check['check_type'] ?? $operation['operation_type'] ?? '')),
            ];
        }
        return [
            'required' => 'yes',
            'run_after_delete' => 'yes',
            'all_checks_must_pass' => 'yes',
            'checks' => $normalized,
        ];
    }

    /** @param array<int,array<string,mixed>> $archiveScope @param array<int,array<string,mixed>> $manifest @return array<string,mixed> */
    private static function snapshotContract(array $archiveScope, array $manifest, string $planFingerprint): array
    {
        $archiveOperationIds = [];
        foreach ($manifest as $operation) {
            if ((string)($operation['operation_type'] ?? '') === 'archive_target') {
                $archiveOperationIds[] = (string)$operation['operation_id'];
            }
        }
        return [
            'required' => 'yes',
            'source_plan_fingerprint' => $planFingerprint,
            'scope' => array_values(array_map(static fn(array $scope): array => [
                'owner_key' => (string)($scope['owner_key'] ?? ''),
                'target_type' => (string)($scope['target_type'] ?? ''),
                'path' => self::normalizePath((string)($scope['path'] ?? '')),
                'required' => (string)($scope['required'] ?? 'yes'),
            ], $archiveScope)),
            'archive_operation_ids' => array_values(array_unique(array_filter($archiveOperationIds))),
            'must_exist_before_delete' => 'yes',
            'restore_validation_required' => 'yes',
        ];
    }

    /** @return array{0:string,1:string,2:array<int,string>} */
    private static function stateFromPlan(string $planState): array
    {
        if ($planState === 'ready') {
            return [self::STATE_READY_FOR_REVIEW, 'ready', []];
        }
        if ($planState === 'needs_review') {
            return [self::STATE_REVIEW_REQUIRED, 'review_required', ['PLAN_REVIEW_REQUIRED']];
        }
        if ($planState === 'blocked') {
            return [self::STATE_BLOCKED, 'blocked', ['PLAN_BLOCKED']];
        }
        return [self::STATE_UNKNOWN, 'blocked', ['PLAN_INCOMPLETE']];
    }

    /** @param array<string,mixed> $target @param array<string,mixed> $plan @param array<int,string> $blockingReasons @param array<int,array<string,mixed>> $diagnostics @return array<string,mixed> */
    private static function unknownResult(array $target, array $plan, array $blockingReasons, array $diagnostics): array
    {
        $planFingerprint = trim((string)($plan['plan_fingerprint'] ?? ''));
        $fingerprint = self::fingerprint([
            'version' => self::VERSION,
            'state' => self::STATE_UNKNOWN,
            'target' => $target,
            'source_plan_fingerprint' => $planFingerprint,
            'blocking_reasons' => $blockingReasons,
        ]);
        return [
            'status' => 'partial',
            'effect' => self::EFFECT,
            'change_set_version' => self::VERSION,
            'change_set_state' => self::STATE_UNKNOWN,
            'immutable' => 'yes',
            'can_execute' => 'no',
            'can_apply' => 'no',
            'requires_approval' => 'yes',
            'requires_snapshot' => 'yes',
            'target' => $target,
            'source_plan' => [
                'version' => (string)($plan['plan_version'] ?? ''),
                'state' => (string)($plan['plan_state'] ?? 'unknown'),
                'fingerprint' => $planFingerprint,
            ],
            'summary' => [
                'operation_count' => 0,
                'file_change_count' => 0,
                'archive_change_count' => 0,
                'delete_change_count' => 0,
                'verification_check_count' => 0,
                'blocking_change_count' => 0,
                'review_change_count' => 0,
                'cleanup_change_count' => 0,
            ],
            'operation_manifest' => [],
            'proposed_changes' => [
                'file_changes' => [],
                'archive_changes' => [],
                'delete_changes' => [],
            ],
            'approval_contract' => [
                'required' => 'yes',
                'approval_scope' => 'deletion_change_set',
                'approval_readiness' => 'blocked',
                'approval_binds_to' => [
                    'change_set_fingerprint' => $fingerprint,
                    'plan_fingerprint' => $planFingerprint,
                ],
                'required_confirmations' => [],
            ],
            'snapshot_contract' => [
                'required' => 'yes',
                'source_plan_fingerprint' => $planFingerprint,
                'scope' => [],
                'archive_operation_ids' => [],
                'must_exist_before_delete' => 'yes',
                'restore_validation_required' => 'yes',
            ],
            'verification_contract' => [
                'required' => 'yes',
                'run_after_delete' => 'yes',
                'all_checks_must_pass' => 'yes',
                'checks' => [],
            ],
            'blocking_reasons' => array_values(array_unique(array_merge($blockingReasons, ['PLAN_INCOMPLETE']))),
            'dependent_owners' => self::arrayList($plan['dependent_owners'] ?? []),
            'change_set_fingerprint' => $fingerprint,
            'integrity_contract' => [
                'algorithm' => 'sha1-canonical-json-v1',
                'source_plan_fingerprint' => $planFingerprint,
                'change_set_fingerprint' => $fingerprint,
                'recompute_before_approval' => 'yes',
                'reject_on_mismatch' => 'yes',
            ],
            'diagnostics' => $diagnostics,
        ];
    }

    private static function expectedEvidence(string $checkType): string
    {
        return match ($checkType) {
            'verify_target_absent' => 'target_path_absent',
            'verify_no_inbound_references' => 'reference_scan_zero_inbound',
            'verify_owner_unregistered' => 'owner_discovery_not_resolved',
            default => 'check_passed',
        };
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function countBySeverity(array $items, string $severity): int
    {
        $count = 0;
        foreach ($items as $item) {
            if ((string)($item['severity'] ?? '') === $severity) {
                $count++;
            }
        }
        return $count;
    }

    /** @param array<string,mixed> $value */
    private static function fingerprint(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'deletion-change-set:' . substr(sha1(is_string($encoded) ? $encoded : serialize($value)), 0, 24);
    }

    private static function normalizePath(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', trim($path)), '/');
        $parts = [];
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                return '';
            }
            $parts[] = $part;
        }
        return implode('/', $parts);
    }

    /** @param mixed $value @return array<int,int> */
    private static function integerList($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $items = [];
        foreach ($value as $item) {
            $item = (int)$item;
            if ($item > 0) {
                $items[$item] = true;
            }
        }
        $items = array_map('intval', array_keys($items));
        sort($items, SORT_NUMERIC);
        return $items;
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
        $items = array_map('strval', array_keys($items));
        sort($items, SORT_NATURAL | SORT_FLAG_CASE);
        return $items;
    }
}
