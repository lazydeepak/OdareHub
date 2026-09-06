<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionReferenceRemediationReadinessService.php';

use Apps\Studio\Services\StudioDeletionReferenceRemediationReadinessService;

/** Thin Owner Structure adapter for canonical reference-remediation readiness. */
final class OwnerStructureDeletionReferenceRemediationReadinessService
{
    /** @return array<string,mixed> */
    public static function assess(
        array $selectedOwner,
        array $changeSet,
        array $snapshotIntegrity,
        ?string $root = null,
        ?callable $referenceResolver = null,
        ?callable $fileInspector = null
    ): array {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $targetOwner = trim((string)($changeSet['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $targetOwner === '' || $ownerKey !== $targetOwner) {
            return [
                'status' => 'partial',
                'effect' => 'verify',
                'reference_remediation_readiness_version' => StudioDeletionReferenceRemediationReadinessService::VERSION,
                'reference_remediation_readiness' => 'unknown',
                'preconditions_ready' => 'no',
                'reference_remediation_ready' => 'no',
                'can_write' => 'no',
                'can_apply' => 'no',
                'can_execute' => 'no',
                'can_archive' => 'no',
                'can_delete' => 'no',
                'grants_execution_authority' => 'no',
                'requires_separate_patch_capability' => 'yes',
                'requires_separate_execution_capability' => 'yes',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_REFERENCE_REMEDIATION_OWNER_MISMATCH',
                    'severity' => 'error',
                    'message' => 'Selected owner does not match the current deletion change-set target.',
                    'path' => $ownerKey,
                ]],
            ];
        }

        return StudioDeletionReferenceRemediationReadinessService::assess(
            $changeSet,
            $snapshotIntegrity,
            $root,
            $referenceResolver,
            $fileInspector
        );
    }
}
