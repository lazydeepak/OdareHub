<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionReferenceRemediationReadinessService.php';

/** Read-only presenter for deterministic reference patch preconditions. */
final class OwnerStructureDeletionReferenceRemediationReadinessWorkspaceService
{
    /** @return array<string,mixed> */
    public static function build(
        array $owners,
        string $selectedOwnerKey,
        bool $requested,
        ?array $changeSet,
        ?array $snapshotIntegrity,
        ?string $root = null,
        ?callable $referenceResolver = null,
        ?callable $fileInspector = null,
        ?callable $assessor = null
    ): array {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested,
            'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey,
            'selected_owner' => $selectedOwner,
            'assessment' => null,
            'reference_remediation_readiness' => 'not_assessed',
            'preconditions_ready' => 'no',
            'reference_remediation_ready' => 'no',
            'requires_review_decision' => 'no',
            'source' => [],
            'target' => [],
            'summary' => [],
            'patch_preconditions' => [],
            'file_checks' => [],
            'blocking_reasons' => [],
            'reference_remediation_readiness_fingerprint' => '',
            'diagnostics' => [],
        ];
        if (!$requested) {
            return $base;
        }
        if ($selectedOwner === null) {
            return self::error($base, 'OSS_DELETION_REFERENCE_REMEDIATION_OWNER_UNAVAILABLE', 'The selected owner is unavailable for reference-remediation verification.', $selectedOwnerKey);
        }
        if ($changeSet === null || $snapshotIntegrity === null) {
            return self::error($base, 'OSS_DELETION_REFERENCE_REMEDIATION_PREREQUISITES_REQUIRED', 'Current deletion change-set and snapshot-integrity evidence are required.', $selectedOwnerKey);
        }
        if ((string)($changeSet['target']['owner_key'] ?? '') !== $selectedOwnerKey) {
            return self::error($base, 'OSS_DELETION_REFERENCE_REMEDIATION_PACKET_INVALID', 'The current packet targets a different owner.', $selectedOwnerKey);
        }

        try {
            $assessment = $assessor !== null
                ? $assessor($selectedOwner, $changeSet, $snapshotIntegrity, $root, $referenceResolver, $fileInspector)
                : OwnerStructureDeletionReferenceRemediationReadinessService::assess(
                    $selectedOwner,
                    $changeSet,
                    $snapshotIntegrity,
                    $root,
                    $referenceResolver,
                    $fileInspector
                );
        } catch (\Throwable $exception) {
            return self::error(
                $base,
                'OSS_DELETION_REFERENCE_REMEDIATION_FAILED',
                $exception->getMessage() !== '' ? $exception->getMessage() : 'Reference-remediation readiness failed.',
                $selectedOwnerKey
            );
        }
        if (!is_array($assessment)) {
            return self::error($base, 'OSS_DELETION_REFERENCE_REMEDIATION_RESULT_INVALID', 'Reference-remediation capability returned an invalid result.', $selectedOwnerKey);
        }

        $base['status'] = 'ready';
        $base['assessment'] = $assessment;
        foreach (['reference_remediation_readiness','preconditions_ready','reference_remediation_ready','requires_review_decision','reference_remediation_readiness_fingerprint'] as $field) {
            $base[$field] = (string)($assessment[$field] ?? $base[$field]);
        }
        foreach (['source','target','summary'] as $field) {
            $base[$field] = self::arr($assessment, $field);
        }
        $base['patch_preconditions'] = self::arrays($assessment['patch_preconditions'] ?? []);
        $base['file_checks'] = self::arrays($assessment['file_checks'] ?? []);
        $base['blocking_reasons'] = self::strings($assessment['blocking_reasons'] ?? []);
        $base['diagnostics'] = self::arrays($assessment['diagnostics'] ?? []);
        return $base;
    }

    private static function error(array $base, string $code, string $message, string $path): array
    {
        $base['status'] = 'error';
        $base['diagnostics'][] = ['code' => $code, 'severity' => 'error', 'message' => $message, 'path' => $path];
        return $base;
    }

    private static function findOwner(array $owners, string $key): ?array
    {
        foreach ($owners as $owner) {
            if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $key) {
                return $owner;
            }
        }
        return null;
    }

    private static function arr(array $source, string $key): array
    {
        return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : [];
    }

    private static function arrays($value): array
    {
        return is_array($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    private static function strings($value): array
    {
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_map('strval', $value));
    }
}
