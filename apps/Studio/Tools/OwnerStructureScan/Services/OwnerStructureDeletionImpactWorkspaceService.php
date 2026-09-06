<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionImpactService.php';

/**
 * Read-only page adapter for the Owner Structure deletion-impact workspace.
 * It normalizes capability output for rendering and owns no discovery policy.
 */
final class OwnerStructureDeletionImpactWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param callable(array<string,mixed>,array<string,mixed>,?string):array<string,mixed>|null $assessor
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?string $root = null,
        ?callable $assessor = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'assessment' => null,
            'summary' => [],
            'deletion_readiness' => 'not_assessed',
            'safe_to_delete' => 'unknown',
            'blocking_reasons' => [],
            'dependent_owners' => [],
            'references' => [],
            'diagnostics' => [],
        ];

        if (!$requested) {
            return $base;
        }

        if ($selectedOwner === null) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_IMPACT_OWNER_UNAVAILABLE',
                'severity' => 'error',
                'message' => 'The selected owner is unavailable for deletion-impact assessment.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }

        try {
            $assessment = $assessor !== null
                ? $assessor($selectedOwner, [], $root)
                : OwnerStructureDeletionImpactService::assess($selectedOwner, [], $root);
        } catch (\Throwable $exception) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_IMPACT_WORKSPACE_FAILED',
                'severity' => 'error',
                'message' => $exception->getMessage() !== ''
                    ? $exception->getMessage()
                    : 'Deletion-impact assessment failed.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }

        if (!is_array($assessment)) {
            $base['status'] = 'error';
            $base['diagnostics'][] = [
                'code' => 'OSS_DELETION_IMPACT_RESULT_INVALID',
                'severity' => 'error',
                'message' => 'Deletion-impact capability returned an invalid result.',
                'path' => $selectedOwnerKey,
            ];
            return $base;
        }

        $assessmentStatus = (string)($assessment['status'] ?? 'error');
        $base['status'] = $assessmentStatus === 'error' ? 'error' : 'ready';
        $base['assessment'] = $assessment;
        $base['summary'] = self::arrayValue($assessment, 'summary');
        $base['deletion_readiness'] = (string)($assessment['deletion_readiness'] ?? 'unknown');
        $base['safe_to_delete'] = (string)($assessment['safe_to_delete'] ?? 'unknown');
        $base['blocking_reasons'] = self::stringList($assessment['blocking_reasons'] ?? []);
        $base['dependent_owners'] = self::arrayList($assessment['dependent_owners'] ?? []);
        $base['references'] = self::arrayList($assessment['references'] ?? []);
        $base['diagnostics'] = self::arrayList($assessment['diagnostics'] ?? []);

        return $base;
    }

    /** @param array<int,array<string,mixed>> $owners @return array<string,mixed>|null */
    private static function findOwner(array $owners, string $selectedOwnerKey): ?array
    {
        foreach ($owners as $owner) {
            if (!is_array($owner)) {
                continue;
            }
            if ((string)($owner['owner_key'] ?? '') === $selectedOwnerKey) {
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
