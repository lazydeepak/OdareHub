<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

require_once __DIR__ . '/OwnerStructureDeletionReferenceRemediationPatchProposalService.php';

/** Read-only presenter for deterministic reference-remediation patch proposals. */
final class OwnerStructureDeletionReferenceRemediationPatchProposalWorkspaceService
{
    /** @return array<string,mixed> */
    public static function build(array $owners, string $selectedOwnerKey, bool $requested, ?array $readiness, ?string $root = null, ?callable $proposer = null): array
    {
        $selectedOwner = self::findOwner($owners, $selectedOwnerKey);
        $base = [
            'requested' => $requested, 'status' => $requested ? 'loading' : 'idle',
            'selected_owner_key' => $selectedOwnerKey, 'selected_owner' => $selectedOwner,
            'readiness' => $readiness, 'proposal' => null, 'patch_proposal_state' => 'not_assessed',
            'proposals_ready' => 'no', 'all_decisions_resolved' => 'no', 'summary' => [],
            'patch_proposals' => [], 'decision_items' => [], 'blocking_reasons' => [],
            'patch_proposal_fingerprint' => '', 'diagnostics' => [],
        ];
        if (!$requested) { return $base; }
        if ($selectedOwner === null) { return self::error($base, 'OSS_DELETION_REFERENCE_PATCH_PROPOSAL_OWNER_UNAVAILABLE', 'The selected owner is unavailable for patch proposal generation.'); }
        if ($readiness === null) { return self::error($base, 'OSS_DELETION_REFERENCE_PATCH_PROPOSAL_READINESS_REQUIRED', 'Reference-remediation readiness is required before patch proposals can be generated.'); }
        try {
            $proposal = $proposer !== null ? $proposer($selectedOwner, $readiness, $root) : OwnerStructureDeletionReferenceRemediationPatchProposalService::propose($selectedOwner, $readiness, $root);
        } catch (\Throwable $exception) {
            return self::error($base, 'OSS_DELETION_REFERENCE_PATCH_PROPOSAL_FAILED', $exception->getMessage() !== '' ? $exception->getMessage() : 'Patch proposal generation failed.');
        }
        if (!is_array($proposal)) { return self::error($base, 'OSS_DELETION_REFERENCE_PATCH_PROPOSAL_RESULT_INVALID', 'Patch proposal capability returned an invalid result.'); }
        $base['status'] = 'ready';
        $base['proposal'] = $proposal;
        $base['patch_proposal_state'] = (string)($proposal['patch_proposal_state'] ?? 'unknown');
        $base['proposals_ready'] = (string)($proposal['proposals_ready'] ?? 'no');
        $base['all_decisions_resolved'] = (string)($proposal['all_decisions_resolved'] ?? 'no');
        $base['summary'] = self::arr($proposal, 'summary');
        $base['patch_proposals'] = self::arrays($proposal['patch_proposals'] ?? []);
        $base['decision_items'] = self::arrays($proposal['decision_items'] ?? []);
        $base['blocking_reasons'] = self::strings($proposal['blocking_reasons'] ?? []);
        $base['patch_proposal_fingerprint'] = (string)($proposal['patch_proposal_fingerprint'] ?? '');
        $base['diagnostics'] = self::arrays($proposal['diagnostics'] ?? []);
        return $base;
    }

    private static function error(array $base, string $code, string $message): array { $base['status'] = 'error'; $base['diagnostics'][] = ['code' => $code, 'severity' => 'error', 'message' => $message, 'path' => (string)$base['selected_owner_key']]; return $base; }
    private static function findOwner(array $owners, string $key): ?array { foreach ($owners as $owner) { if (is_array($owner) && (string)($owner['owner_key'] ?? '') === $key) { return $owner; } } return null; }
    private static function arr(array $source, string $key): array { return isset($source[$key]) && is_array($source[$key]) ? $source[$key] : []; }
    private static function arrays($value): array { return is_array($value) ? array_values(array_filter($value, 'is_array')) : []; }
    private static function strings($value): array { if (!is_array($value)) { return []; } $out = []; foreach ($value as $item) { $item = trim((string)$item); if ($item !== '') { $out[$item] = true; } } return array_map('strval', array_keys($out)); }
}
