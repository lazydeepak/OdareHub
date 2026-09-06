<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionChangeSetService.php';

/** Read-only page adapter for the Owner Structure deletion change-set workspace. */
final class OwnerStructureDeletionChangeSetWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $deletionPlan
     * @param callable(array<string,mixed>,array<string,mixed>,?array,?string):array<string,mixed>|null $builder
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $deletionPlan = null,
        ?string $root = null,
        ?callable $builder = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'change_set' => null,
            'change_set_state' => 'not_built',
            'summary' => [],
            'source_plan' => [],
            'operation_manifest' => [],
            'proposed_changes' => [
                'file_changes' => [],
                'archive_changes' => [],
                'delete_changes' => [],
            ],
            'approval_contract' => [],
            'snapshot_contract' => [],
            'verification_contract' => [],
            'blocking_reasons' => [],
            'change_set_fingerprint' => '',
            'diagnostics' => [],
        ];

        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_CHANGE_SET_OWNER_UNAVAILABLE', 'The selected owner is unavailable for deletion change-set review.', $selectedOwnerKey);
        }
        if ($deletionPlan === null) {
            return self::error($base, 'OSS_DELETION_CHANGE_SET_PLAN_REQUIRED', 'Deletion change-set generation requires a completed deletion plan.', $selectedOwnerKey);
        }

        try {
            $changeSet = $builder !== null
                ? $builder($selectedOwner, [], $deletionPlan, $root)
                : OwnerStructureDeletionChangeSetService::build($selectedOwner, [], $deletionPlan, $root);
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_CHANGE_SET_WORKSPACE_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Deletion change-set generation failed.',
                $selectedOwnerKey
            );
        }

        if (!is_array($changeSet)) {
            return self::error($base, 'OSS_DELETION_CHANGE_SET_RESULT_INVALID', 'Deletion change-set capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = (string)($changeSet['status'] ?? 'error') === 'error' ? 'error' : 'ready';
        $base['change_set'] = $changeSet;
        $base['change_set_state'] = (string)($changeSet['change_set_state'] ?? 'unknown');
        $base['summary'] = self::arrayValue($changeSet, 'summary');
        $base['source_plan'] = self::arrayValue($changeSet, 'source_plan');
        $base['operation_manifest'] = self::arrayList($changeSet['operation_manifest'] ?? []);
        $base['proposed_changes'] = self::proposedChanges($changeSet['proposed_changes'] ?? []);
        $base['approval_contract'] = self::arrayValue($changeSet, 'approval_contract');
        $base['snapshot_contract'] = self::arrayValue($changeSet, 'snapshot_contract');
        $base['verification_contract'] = self::arrayValue($changeSet, 'verification_contract');
        $base['blocking_reasons'] = self::stringList($changeSet['blocking_reasons'] ?? []);
        $base['change_set_fingerprint'] = (string)($changeSet['change_set_fingerprint'] ?? '');
        $base['diagnostics'] = self::arrayList($changeSet['diagnostics'] ?? []);
        return $base;
    }

    /** @param array<string,mixed> $base @return array<string,mixed> */
    private static function error(array $base, string $code, string $message, string $path): array
    {
        $base['status'] = 'error';
        $base['diagnostics'][] = [
            'code' => $code,
            'severity' => 'error',
            'message' => $message,
            'path' => $path,
        ];
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

    /** @param mixed $value @return array<string,array<int,array<string,mixed>>> */
    private static function proposedChanges($value): array
    {
        $value = is_array($value) ? $value : [];
        return [
            'file_changes' => self::arrayList($value['file_changes'] ?? []),
            'archive_changes' => self::arrayList($value['archive_changes'] ?? []),
            'delete_changes' => self::arrayList($value['delete_changes'] ?? []),
        ];
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
