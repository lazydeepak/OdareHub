<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioReferenceDiscoveryService.php';

use Apps\Studio\Services\StudioReferenceDiscoveryService;

/**
 * Owner Structure compatibility adapter. Repository traversal and reference
 * matching belong to StudioReferenceDiscoveryService; this adapter owns only
 * migration-plan selection, readiness grouping, and legacy output shape.
 */
final class OwnerStructureReferenceDiscoveryService
{
    private const READINESS_BLOCKED_RUNTIME = 'blocked_runtime_references';
    private const READINESS_NEEDS_TOOLING_REVIEW = 'needs_tooling_review';
    private const READINESS_READY_MANUAL_RENAME = 'ready_for_manual_rename';

    /**
     * @param array<string,mixed> $selectedOwner
     * @param array<string,mixed> $scanResult
     * @param array<string,mixed> $migrationPlan
     * @return array<string,mixed>
     */
    public static function discover(array $selectedOwner, array $scanResult, array $migrationPlan): array
    {
        unset($scanResult);

        $ownerKey = (string)($selectedOwner['owner_key'] ?? '');
        $operations = isset($migrationPlan['operations']) && is_array($migrationPlan['operations'])
            ? $migrationPlan['operations']
            : [];
        $requests = [];

        foreach ($operations as $operation) {
            if (!is_array($operation) || (string)($operation['blocker_status'] ?? '') !== 'BLOCKED_BY_REFERENCE_DISCOVERY') {
                continue;
            }
            $source = (string)($operation['source_path'] ?? '');
            $target = (string)($operation['target_path'] ?? '');
            $request = [
                'request_id' => (string)($operation['operation_id'] ?? self::referenceKey($source, $target)),
                'operation_id' => (string)($operation['operation_id'] ?? ''),
                'operation_type' => (string)($operation['operation_type'] ?? ''),
                'owner_key' => $ownerKey,
                'source_path' => $source,
                'target_path' => $target,
                'reference_key' => self::referenceKey($source, $target),
            ];
            if (self::isShellStyleToDesignSystemOperation($source, $target)) {
                $request['include_path_prefixes'] = ['apps/Shell/'];
            }
            $requests[] = $request;
        }

        $root = defined('APP_ROOT') ? (string)APP_ROOT : '';
        $discovery = StudioReferenceDiscoveryService::discover($requests, $root);
        $items = [];
        foreach (($discovery['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $operation = [
                'operation_id' => (string)($item['operation_id'] ?? ''),
                'operation_type' => (string)($item['operation_type'] ?? ''),
            ];
            $items[] = self::discoveryItem(
                $operation,
                (string)($item['source_path'] ?? ''),
                (string)($item['target_path'] ?? ''),
                (string)($item['reference_key'] ?? ''),
                isset($item['references']) && is_array($item['references']) ? $item['references'] : []
            );
        }

        $searchScope = isset($discovery['search_scope']) && is_array($discovery['search_scope'])
            ? $discovery['search_scope']
            : [];
        $searchScope['root'] = '.';

        return [
            'summary' => self::summary($items),
            'groups' => self::groups($items),
            'items' => $items,
            'search_scope' => $searchScope,
        ];
    }

    /**
     * @param array<string,mixed> $operation
     * @param array<int,array<string,mixed>> $references
     * @return array<string,mixed>
     */
    private static function discoveryItem(array $operation, string $source, string $target, string $referenceKey, array $references): array
    {
        $referenceCount = count($references);
        $confidences = array_values(array_unique(array_map(static fn(array $reference): string => (string)($reference['confidence'] ?? 'low'), $references)));
        $relevanceSummary = self::relevanceSummary($references);
        $categorySummary = self::categorySummary($references);
        $runtimeBlocking = (int)($relevanceSummary[StudioReferenceDiscoveryService::RELEVANCE_RUNTIME_BLOCKING] ?? 0);
        $buildToolingBlocking =
            (int)($relevanceSummary[StudioReferenceDiscoveryService::RELEVANCE_STUDIO_TOOLING] ?? 0)
            + (int)($relevanceSummary[StudioReferenceDiscoveryService::RELEVANCE_LOW_CONFIDENCE_TEXT] ?? 0);
        $migrationReadinessState = self::migrationReadinessState($runtimeBlocking, $buildToolingBlocking);
        $hasLow = in_array('low', $confidences, true);
        $hasMedium = in_array('medium', $confidences, true);
        $allHigh = $referenceCount > 0 && $confidences === ['high'];
        $safe = ($runtimeBlocking === 0 && $buildToolingBlocking === 0) ? 'yes' : 'no';

        if ($referenceCount === 0) {
            $group = 'no_references_found';
            $reason = 'No references were discovered for this blocker.';
        } elseif ($runtimeBlocking > 0) {
            $group = 'needs_review';
            $reason = 'Runtime-blocking references still exist; keep rename blocked until these references are migrated.';
        } elseif ($buildToolingBlocking > 0) {
            $group = 'needs_review';
            $reason = 'Build/tooling references require review before promotion.';
        } elseif ($safe === 'yes' && $allHigh) {
            $group = 'ready_to_promote';
            $reason = 'All discovered references are high-confidence exact path matches.';
        } elseif ($hasLow) {
            $group = 'low_confidence_matches';
            $reason = 'Low-confidence owner-key matches require human review before promotion.';
        } elseif ($hasMedium) {
            $group = 'needs_review';
            $reason = 'Basename or category matches are not exact enough to promote automatically.';
        } else {
            $group = 'needs_review';
            $reason = 'Reference evidence requires review before promotion.';
        }

        return [
            'operation_id' => (string)($operation['operation_id'] ?? ''),
            'operation_type' => (string)($operation['operation_type'] ?? ''),
            'source_path' => $source,
            'target_path' => $target,
            'reference_key' => $referenceKey,
            'references' => $references,
            'reference_count' => $referenceCount,
            'files_referencing' => array_values(array_unique(array_map(static fn(array $reference): string => (string)($reference['file_path'] ?? ''), $references))),
            'confidence' => self::overallConfidence($references),
            'relevance_summary' => $relevanceSummary,
            'category_summary' => $categorySummary,
            'migration_readiness_state' => $migrationReadinessState,
            'safe_to_promote' => $safe,
            'promotion_reason' => $reason,
            'group' => $group,
        ];
    }

    private static function migrationReadinessState(int $runtimeBlocking, int $buildToolingBlocking): string
    {
        if ($runtimeBlocking > 0) {
            return self::READINESS_BLOCKED_RUNTIME;
        }
        if ($buildToolingBlocking > 0) {
            return self::READINESS_NEEDS_TOOLING_REVIEW;
        }
        return self::READINESS_READY_MANUAL_RENAME;
    }

    /** @param array<int,array<string,mixed>> $references */
    private static function overallConfidence(array $references): string
    {
        if ($references === []) {
            return 'low';
        }
        $values = array_map(static fn(array $reference): string => (string)($reference['confidence'] ?? 'low'), $references);
        if (in_array('low', $values, true)) {
            return 'low';
        }
        if (in_array('medium', $values, true)) {
            return 'medium';
        }
        return 'high';
    }

    /** @param array<int,array<string,mixed>> $references @return array<string,int> */
    private static function relevanceSummary(array $references): array
    {
        $summary = [
            StudioReferenceDiscoveryService::RELEVANCE_RUNTIME_BLOCKING => 0,
            StudioReferenceDiscoveryService::RELEVANCE_STUDIO_TOOLING => 0,
            StudioReferenceDiscoveryService::RELEVANCE_ENGINEERING_WORKSPACE => 0,
            StudioReferenceDiscoveryService::RELEVANCE_DOCUMENTATION_HISTORY => 0,
            StudioReferenceDiscoveryService::RELEVANCE_OWNER_METADATA => 0,
            StudioReferenceDiscoveryService::RELEVANCE_SELF_REFERENCE => 0,
            StudioReferenceDiscoveryService::RELEVANCE_LOW_CONFIDENCE_TEXT => 0,
        ];
        foreach ($references as $reference) {
            $relevance = (string)($reference['relevance'] ?? '');
            if (isset($summary[$relevance])) {
                $summary[$relevance]++;
            }
        }
        return $summary;
    }

    /** @param array<int,array<string,mixed>> $references @return array<string,int> */
    private static function categorySummary(array $references): array
    {
        $summary = [
            StudioReferenceDiscoveryService::CATEGORY_RUNTIME => 0,
            StudioReferenceDiscoveryService::CATEGORY_TOOLING => 0,
            StudioReferenceDiscoveryService::CATEGORY_DOCS => 0,
            StudioReferenceDiscoveryService::CATEGORY_SELF_REFERENCE => 0,
        ];
        foreach ($references as $reference) {
            $category = (string)($reference['category'] ?? '');
            if (isset($summary[$category])) {
                $summary[$category]++;
            }
        }
        return $summary;
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,int> */
    private static function summary(array $items): array
    {
        $summary = [
            'total_blockers' => count($items),
            'ready_to_promote' => 0,
            'needs_review' => 0,
            'no_references_found' => 0,
            'low_confidence_matches' => 0,
            'total_references' => 0,
        ];
        foreach ($items as $item) {
            $group = (string)($item['group'] ?? 'needs_review');
            if (isset($summary[$group])) {
                $summary[$group]++;
            }
            $summary['total_references'] += (int)($item['reference_count'] ?? 0);
        }
        return $summary;
    }

    /** @param array<int,array<string,mixed>> $items @return array<string,array<int,array<string,mixed>>> */
    private static function groups(array $items): array
    {
        $groups = [
            'ready_to_promote' => [],
            'needs_review' => [],
            'no_references_found' => [],
            'low_confidence_matches' => [],
        ];
        foreach ($items as $item) {
            $group = (string)($item['group'] ?? 'needs_review');
            $groups[$group][] = $item;
        }
        return $groups;
    }

    private static function isShellStyleToDesignSystemOperation(string $source, string $target): bool
    {
        return $source === 'apps/Shell/Style' && $target === 'apps/Shell/DesignSystem';
    }

    private static function referenceKey(string $source, string $target): string
    {
        return basename($source) . ' -> ' . basename($target);
    }
}
