<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

require_once __DIR__ . '/StudioDeletionImpactDiscoveryService.php';

/**
 * Canonical read-only Studio capability that converts deletion-impact evidence
 * into a deterministic removal plan. It never mutates repository state.
 */
final class StudioDeletionPlanService
{
    public const EFFECT = 'plan';

    public const STATE_READY = 'ready';
    public const STATE_NEEDS_REVIEW = 'needs_review';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_UNKNOWN = 'unknown';

    /** @param array<string,mixed> $request @return array<string,mixed> */
    public static function plan(array $request, ?string $root = null): array
    {
        return self::compose($request, StudioDeletionImpactDiscoveryService::discover($request, $root));
    }

    /** @param array<string,mixed> $request @param array<string,mixed> $impact @return array<string,mixed> */
    public static function compose(array $request, array $impact): array
    {
        $target = self::arrayValue($impact, 'target');
        $targetPath = self::normalizePath((string)($target['target_path'] ?? ''));
        $ownerKey = trim((string)($target['owner_key'] ?? $request['owner_key'] ?? ''));
        $targetType = trim((string)($target['target_type'] ?? $request['target_type'] ?? 'owner'));
        $impactStatus = trim((string)($impact['status'] ?? 'error'));
        $impactReadiness = trim((string)($impact['deletion_readiness'] ?? 'unknown'));
        $blockingReasons = self::stringList($impact['blocking_reasons'] ?? []);
        $diagnostics = self::arrayList($impact['diagnostics'] ?? []);

        if ($targetPath === '' || $ownerKey === '' || $impactStatus === 'error') {
            if ($targetPath === '' || $ownerKey === '') {
                $diagnostics[] = [
                    'code' => 'DELETION_PLAN_TARGET_UNAVAILABLE',
                    'severity' => 'error',
                    'message' => 'Deletion planning requires a resolved target owner and canonical target path.',
                    'path' => $targetPath,
                ];
            }
            if ($impactStatus === 'error') {
                $blockingReasons[] = 'IMPACT_ASSESSMENT_INCOMPLETE';
            }
            return self::unknownResult($target, $blockingReasons, $diagnostics, $impact);
        }

        $referenceOperations = self::referenceOperations(self::arrayList($impact['references'] ?? []));
        $requiredReferenceIds = [];
        $reviewOperationIds = [];
        $cleanupOperationIds = [];

        foreach ($referenceOperations as $operation) {
            $operationId = (string)($operation['operation_id'] ?? '');
            $severity = (string)($operation['severity'] ?? 'cleanup');
            if ($operationId === '') {
                continue;
            }
            if ($severity === 'blocking') {
                $requiredReferenceIds[] = $operationId;
            } elseif ($severity === 'review') {
                $reviewOperationIds[] = $operationId;
                $requiredReferenceIds[] = $operationId;
            } else {
                $cleanupOperationIds[] = $operationId;
            }
        }

        $planState = self::stateFromImpact($impactReadiness, $impactStatus);
        $targetState = $planState === self::STATE_BLOCKED
            ? 'blocked'
            : ($planState === self::STATE_NEEDS_REVIEW ? 'review_required' : ($planState === self::STATE_UNKNOWN ? 'blocked' : 'planned'));

        $archiveOperation = [
            'operation_id' => self::operationId('archive_target', $targetPath),
            'phase' => 'archive',
            'operation_type' => 'archive_target',
            'owner_key' => $ownerKey,
            'target_type' => $targetType,
            'path' => $targetPath,
            'severity' => 'required',
            'required' => 'yes',
            'blocking' => 'no',
            'execution_state' => $targetState,
            'reason' => 'Preserve a rollback source before removing the target.',
            'depends_on' => $requiredReferenceIds,
        ];

        $deleteOperation = [
            'operation_id' => self::operationId('delete_target', $targetPath),
            'phase' => 'delete',
            'operation_type' => 'delete_target',
            'owner_key' => $ownerKey,
            'target_type' => $targetType,
            'path' => $targetPath,
            'severity' => 'required',
            'required' => 'yes',
            'blocking' => $planState === self::STATE_READY ? 'no' : 'yes',
            'execution_state' => $targetState,
            'reason' => 'Remove the canonical target only after prerequisites and archive evidence are satisfied.',
            'depends_on' => array_values(array_unique(array_merge($requiredReferenceIds, [(string)$archiveOperation['operation_id']]))),
        ];

        $verificationOperations = self::verificationOperations($ownerKey, $targetType, $targetPath, (string)$deleteOperation['operation_id']);
        $operations = array_values(array_merge($referenceOperations, [$archiveOperation, $deleteOperation], $verificationOperations));
        $archiveScope = [[
            'scope' => 'target',
            'owner_key' => $ownerKey,
            'target_type' => $targetType,
            'path' => $targetPath,
            'required' => 'yes',
            'reason' => 'ROLLBACK_SOURCE_REQUIRED',
        ]];
        $postDeletionChecks = array_values(array_map(static fn(array $operation): array => [
            'check_id' => (string)($operation['operation_id'] ?? ''),
            'check_type' => (string)($operation['operation_type'] ?? ''),
            'owner_key' => (string)($operation['owner_key'] ?? ''),
            'path' => (string)($operation['path'] ?? ''),
            'required' => (string)($operation['required'] ?? 'yes'),
        ], $verificationOperations));
        $deletionOrder = array_values(array_map(static fn(array $operation): string => (string)($operation['operation_id'] ?? ''), $operations));
        $summary = [
            'operation_count' => count($operations),
            'reference_change_count' => count($referenceOperations),
            'blocking_reference_changes' => count($requiredReferenceIds) - count($reviewOperationIds),
            'review_reference_changes' => count($reviewOperationIds),
            'cleanup_reference_changes' => count($cleanupOperationIds),
            'archive_item_count' => count($archiveScope),
            'delete_item_count' => 1,
            'verification_count' => count($verificationOperations),
        ];
        $fingerprint = self::fingerprint([
            'target' => $target,
            'state' => $planState,
            'operations' => $operations,
            'archive_scope' => $archiveScope,
            'post_deletion_checks' => $postDeletionChecks,
        ]);

        return [
            'status' => $planState === self::STATE_UNKNOWN ? 'partial' : 'ok',
            'effect' => self::EFFECT,
            'plan_version' => 'studio.deletion-plan.v1',
            'plan_state' => $planState,
            'can_execute' => 'no',
            'requires_approval' => 'yes',
            'requires_snapshot' => 'yes',
            'rollback_requirement' => 'archive_before_delete',
            'target' => $target,
            'summary' => $summary,
            'operations' => $operations,
            'archive_scope' => $archiveScope,
            'deletion_order' => $deletionOrder,
            'post_deletion_checks' => $postDeletionChecks,
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'dependent_owners' => self::arrayList($impact['dependent_owners'] ?? []),
            'impact_evidence' => [
                'status' => $impactStatus,
                'deletion_readiness' => $impactReadiness,
                'safe_to_delete' => (string)($impact['safe_to_delete'] ?? 'unknown'),
                'summary' => self::arrayValue($impact, 'summary'),
            ],
            'plan_fingerprint' => $fingerprint,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @param array<int,array<string,mixed>> $references @return array<int,array<string,mixed>> */
    private static function referenceOperations(array $references): array
    {
        $groups = [];
        foreach ($references as $reference) {
            $filePath = self::normalizePath((string)($reference['file_path'] ?? ''));
            if ($filePath === '') {
                continue;
            }
            $severity = (string)($reference['impact_severity'] ?? 'cleanup');
            if (!in_array($severity, ['blocking', 'review', 'cleanup'], true)) {
                $severity = 'review';
            }
            $key = $severity . '|' . $filePath;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'file_path' => $filePath,
                    'severity' => $severity,
                    'owner_key' => (string)($reference['referencing_owner_key'] ?? ''),
                    'owner_type' => (string)($reference['referencing_owner_type'] ?? ''),
                    'line_numbers' => [],
                    'reference_count' => 0,
                    'relevance' => [],
                ];
            }
            $lineNumber = (int)($reference['line_number'] ?? 0);
            if ($lineNumber > 0) {
                $groups[$key]['line_numbers'][$lineNumber] = true;
            }
            $relevance = trim((string)($reference['relevance'] ?? ''));
            if ($relevance !== '') {
                $groups[$key]['relevance'][$relevance] = true;
            }
            $groups[$key]['reference_count']++;
        }

        $severityOrder = ['blocking' => 0, 'review' => 1, 'cleanup' => 2];
        uasort($groups, static function (array $left, array $right) use ($severityOrder): int {
            $severityCompare = ($severityOrder[(string)$left['severity']] ?? 9) <=> ($severityOrder[(string)$right['severity']] ?? 9);
            return $severityCompare !== 0 ? $severityCompare : strcasecmp((string)$left['file_path'], (string)$right['file_path']);
        });

        $operations = [];
        foreach ($groups as $group) {
            $severity = (string)$group['severity'];
            $filePath = (string)$group['file_path'];
            $lineNumbers = array_map('intval', array_keys((array)$group['line_numbers']));
            sort($lineNumbers, SORT_NUMERIC);
            $relevance = array_map('strval', array_keys((array)$group['relevance']));
            sort($relevance, SORT_NATURAL | SORT_FLAG_CASE);
            $operationType = $severity === 'blocking' ? 'remove_blocking_reference' : ($severity === 'review' ? 'review_reference' : 'cleanup_reference');
            $operations[] = [
                'operation_id' => self::operationId($operationType, $filePath),
                'phase' => 'prepare',
                'operation_type' => $operationType,
                'owner_key' => (string)$group['owner_key'],
                'owner_type' => (string)$group['owner_type'],
                'path' => $filePath,
                'line_numbers' => $lineNumbers,
                'reference_count' => (int)$group['reference_count'],
                'relevance' => $relevance,
                'severity' => $severity,
                'required' => $severity === 'cleanup' ? 'no' : 'yes',
                'blocking' => $severity === 'blocking' ? 'yes' : 'no',
                'execution_state' => $severity === 'blocking' ? 'blocked' : ($severity === 'review' ? 'review_required' : 'planned'),
                'reason' => $severity === 'blocking'
                    ? 'Runtime reference must be removed or migrated before deletion.'
                    : ($severity === 'review'
                        ? 'Ambiguous or tooling reference requires an explicit decision before deletion.'
                        : 'Non-runtime reference should be cleaned up as part of deletion hygiene.'),
                'depends_on' => [],
            ];
        }
        return $operations;
    }

    /** @return array<int,array<string,mixed>> */
    private static function verificationOperations(string $ownerKey, string $targetType, string $targetPath, string $deleteOperationId): array
    {
        $operations = [[
            'operation_id' => self::operationId('verify_target_absent', $targetPath),
            'phase' => 'verify',
            'operation_type' => 'verify_target_absent',
            'owner_key' => $ownerKey,
            'target_type' => $targetType,
            'path' => $targetPath,
            'severity' => 'required',
            'required' => 'yes',
            'blocking' => 'no',
            'execution_state' => 'planned',
            'reason' => 'Confirm the canonical target path no longer exists.',
            'depends_on' => [$deleteOperationId],
        ], [
            'operation_id' => self::operationId('verify_no_inbound_references', $targetPath),
            'phase' => 'verify',
            'operation_type' => 'verify_no_inbound_references',
            'owner_key' => $ownerKey,
            'target_type' => $targetType,
            'path' => $targetPath,
            'severity' => 'required',
            'required' => 'yes',
            'blocking' => 'no',
            'execution_state' => 'planned',
            'reason' => 'Re-run reference discovery and confirm no inbound references remain.',
            'depends_on' => [$deleteOperationId],
        ]];
        if ($targetType === 'owner') {
            $operations[] = [
                'operation_id' => self::operationId('verify_owner_unregistered', $ownerKey),
                'phase' => 'verify',
                'operation_type' => 'verify_owner_unregistered',
                'owner_key' => $ownerKey,
                'target_type' => $targetType,
                'path' => $targetPath,
                'severity' => 'required',
                'required' => 'yes',
                'blocking' => 'no',
                'execution_state' => 'planned',
                'reason' => 'Re-run owner discovery and confirm the owner no longer resolves.',
                'depends_on' => [$deleteOperationId],
            ];
        }
        return $operations;
    }

    private static function stateFromImpact(string $readiness, string $status): string
    {
        if ($status === 'error' || $readiness === '' || $readiness === 'unknown') {
            return self::STATE_UNKNOWN;
        }
        if ($readiness === 'blocked') {
            return self::STATE_BLOCKED;
        }
        if ($readiness === 'needs_review') {
            return self::STATE_NEEDS_REVIEW;
        }
        return in_array($readiness, ['ready', 'ready_with_cleanup'], true) ? self::STATE_READY : self::STATE_UNKNOWN;
    }

    /** @param array<string,mixed> $target @param array<int,string> $blockingReasons @param array<int,array<string,mixed>> $diagnostics @param array<string,mixed> $impact @return array<string,mixed> */
    private static function unknownResult(array $target, array $blockingReasons, array $diagnostics, array $impact): array
    {
        return [
            'status' => 'partial',
            'effect' => self::EFFECT,
            'plan_version' => 'studio.deletion-plan.v1',
            'plan_state' => self::STATE_UNKNOWN,
            'can_execute' => 'no',
            'requires_approval' => 'yes',
            'requires_snapshot' => 'yes',
            'rollback_requirement' => 'archive_before_delete',
            'target' => $target,
            'summary' => [
                'operation_count' => 0,
                'reference_change_count' => 0,
                'blocking_reference_changes' => 0,
                'review_reference_changes' => 0,
                'cleanup_reference_changes' => 0,
                'archive_item_count' => 0,
                'delete_item_count' => 0,
                'verification_count' => 0,
            ],
            'operations' => [],
            'archive_scope' => [],
            'deletion_order' => [],
            'post_deletion_checks' => [],
            'blocking_reasons' => array_values(array_unique($blockingReasons)),
            'dependent_owners' => self::arrayList($impact['dependent_owners'] ?? []),
            'impact_evidence' => [
                'status' => (string)($impact['status'] ?? 'error'),
                'deletion_readiness' => (string)($impact['deletion_readiness'] ?? 'unknown'),
                'safe_to_delete' => (string)($impact['safe_to_delete'] ?? 'unknown'),
                'summary' => self::arrayValue($impact, 'summary'),
            ],
            'plan_fingerprint' => self::fingerprint(['state' => self::STATE_UNKNOWN, 'target' => $target, 'blocking_reasons' => $blockingReasons]),
            'diagnostics' => $diagnostics,
        ];
    }

    private static function operationId(string $type, string $path): string
    {
        return 'delete-plan:' . $type . ':' . substr(sha1($type . '|' . $path), 0, 12);
    }

    /** @param array<string,mixed> $value */
    private static function fingerprint(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return 'deletion-plan:' . substr(sha1(is_string($encoded) ? $encoded : serialize($value)), 0, 20);
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
                $items[] = $item;
            }
        }
        return array_values(array_unique($items));
    }
}
