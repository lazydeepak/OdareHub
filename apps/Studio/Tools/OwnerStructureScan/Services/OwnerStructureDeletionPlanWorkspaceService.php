<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionPlanService.php';

/**
 * Read-only page adapter for the Owner Structure deletion-plan workspace.
 */
final class OwnerStructureDeletionPlanWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $impactAssessment
     * @param callable(array<string,mixed>,array<string,mixed>,?array,?string):array<string,mixed>|null $planner
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $impactAssessment = null,
        ?string $root = null,
        ?callable $planner = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'plan' => null,
            'plan_state' => 'not_planned',
            'summary' => [],
            'operations' => [],
            'archive_scope' => [],
            'deletion_order' => [],
            'post_deletion_checks' => [],
            'blocking_reasons' => [],
            'plan_fingerprint' => '',
            'diagnostics' => [],
        ];

        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_PLAN_OWNER_UNAVAILABLE',
                'severity' => 'error',
                'message' => 'The selected owner is unavailable for deletion planning.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }
        if ($impactAssessment === null) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_PLAN_IMPACT_REQUIRED',
                'severity' => 'error',
                'message' => 'Deletion planning requires a completed deletion-impact assessment.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }

        try {
            $plan = $planner !== null
                ? $planner($selectedOwner, [], $impactAssessment, $root)
                : OwnerStructureDeletionPlanService::plan($selectedOwner, [], $impactAssessment, $root);
        } catch (\Throwable $exception) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_PLAN_WORKSPACE_FAILED',
                'severity' => 'error',
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Deletion planning failed.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }

        if (!is_array($plan)) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_PLAN_RESULT_INVALID',
                'severity' => 'error',
                'message' => 'Deletion-plan capability returned an invalid result.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }

        $base['status'] = (string)($plan['status'] ?? 'error') === 'error' ? 'error' : 'ready';
        $base['plan'] = $plan;
        $base['plan_state'] = (string)($plan['plan_state'] ?? 'unknown');
        $base['summary'] = self::arrayValue($plan, 'summary');
        $base['operations'] = self::arrayList($plan['operations'] ?? []);
        $base['archive_scope'] = self::arrayList($plan['archive_scope'] ?? []);
        $base['deletion_order'] = self::stringList($plan['deletion_order'] ?? []);
        $base['post_deletion_checks'] = self::arrayList($plan['post_deletion_checks'] ?? []);
        $base['blocking_reasons'] = self::stringList($plan['blocking_reasons'] ?? []);
        $base['plan_fingerprint'] = (string)($plan['plan_fingerprint'] ?? '');
        $base['diagnostics'] = self::arrayList($plan['diagnostics'] ?? []);
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
                $items[] = $item;
            }
        }
        return array_values(array_unique($items));
    }
}
