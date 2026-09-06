<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionApprovalRecordService.php';

/** Read-only presenter for the deletion approval decision workspace. */
final class OwnerStructureDeletionApprovalWorkspaceService
{
    /**
     * @param array<int,array<string,mixed>> $owners
     * @param array<string,mixed>|null $changeSet
     * @param array<string,mixed>|null $flash
     * @param callable(array<string,mixed>,?string):array<string,mixed>|null|null $latestReader
     * @return array<string,mixed>
     */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
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
            'change_set_fingerprint' => '',
            'plan_fingerprint' => '',
            'approval_readiness' => 'blocked',
            'required_confirmations' => [],
            'can_approve' => 'no',
            'can_reject' => 'no',
            'latest_record' => null,
            'flash' => $flash,
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_APPROVAL_OWNER_UNAVAILABLE', 'The selected owner is unavailable for an approval decision.', $selectedOwnerKey);
        }
        if ($changeSet === null) {
            return self::error($base, 'OSS_DELETION_APPROVAL_CHANGE_SET_REQUIRED', 'A current deletion change set is required before recording a decision.', $selectedOwnerKey);
        }

        $changeSetFingerprint = trim((string)($changeSet['change_set_fingerprint'] ?? ''));
        $planFingerprint = trim((string)($changeSet['source_plan']['fingerprint'] ?? ''));
        $packetOwnerKey = trim((string)($changeSet['target']['owner_key'] ?? ''));
        $approvalContract = isset($changeSet['approval_contract']) && is_array($changeSet['approval_contract'])
            ? $changeSet['approval_contract']
            : [];
        $approvalReadiness = trim((string)($approvalContract['approval_readiness'] ?? 'blocked'));
        if ($changeSetFingerprint === '' || $planFingerprint === '' || $packetOwnerKey !== $selectedOwnerKey) {
            return self::error($base, 'OSS_DELETION_APPROVAL_CHANGE_SET_INVALID', 'The current deletion change set is missing identity or targets a different owner.', $selectedOwnerKey);
        }

        try {
            $latest = $latestReader !== null
                ? $latestReader($changeSet, $root)
                : OwnerStructureDeletionApprovalRecordService::latest($changeSet, $root);
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_APPROVAL_HISTORY_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Approval history could not be read.',
                $selectedOwnerKey
            );
        }

        $base['status'] = 'ready';
        $base['change_set_fingerprint'] = $changeSetFingerprint;
        $base['plan_fingerprint'] = $planFingerprint;
        $base['approval_readiness'] = $approvalReadiness;
        $base['required_confirmations'] = self::stringList($approvalContract['required_confirmations'] ?? []);
        $base['can_approve'] = $approvalReadiness === 'ready' ? 'yes' : 'no';
        $base['can_reject'] = 'yes';
        $base['latest_record'] = is_array($latest) ? $latest : null;
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
