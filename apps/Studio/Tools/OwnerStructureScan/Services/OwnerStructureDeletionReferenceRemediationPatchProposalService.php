<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once dirname(__DIR__, 3) . '/Services/StudioDeletionReferenceRemediationPatchProposalService.php';

use Apps\Studio\Services\StudioDeletionReferenceRemediationPatchProposalService;

final class OwnerStructureDeletionReferenceRemediationPatchProposalService
{
    /** @return array<string,mixed> */
    public static function propose(array $selectedOwner, array $readiness, ?string $root = null): array
    {
        $ownerKey = trim((string)($selectedOwner['owner_key'] ?? ''));
        $packetOwner = trim((string)($readiness['target']['owner_key'] ?? ''));
        if ($ownerKey === '' || $packetOwner === '' || $ownerKey !== $packetOwner) {
            return [
                'status' => 'partial', 'effect' => 'plan',
                'patch_proposal_version' => StudioDeletionReferenceRemediationPatchProposalService::VERSION,
                'patch_proposal_state' => 'unknown', 'proposals_ready' => 'no', 'all_decisions_resolved' => 'no',
                'can_write' => 'no', 'can_apply' => 'no', 'can_execute' => 'no', 'can_archive' => 'no', 'can_delete' => 'no',
                'grants_execution_authority' => 'no', 'requires_separate_patch_apply_capability' => 'yes',
                'blocking_reasons' => ['OWNER_TARGET_MISMATCH'],
                'diagnostics' => [[
                    'code' => 'OSS_DELETION_REFERENCE_PATCH_PROPOSAL_OWNER_MISMATCH', 'severity' => 'error',
                    'message' => 'Selected owner does not match the reference-remediation packet target.', 'path' => $ownerKey,
                ]],
            ];
        }
        return StudioDeletionReferenceRemediationPatchProposalService::propose($readiness, $root);
    }
}
