<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services;

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceRepairEngineService.php';

/**
 * V1 deterministic single-declaration fixer.
 *
 * Browser authority is limited to proposal_id. Every target fact is resolved
 * from a fresh server-side scanner proposal before writing.
 */
final class StyleComplianceDeterministicFixOneService
{
    public const STATE_FIXED = 'fixed';
    public const STATE_STALE = 'stale';
    public const STATE_AMBIGUOUS = 'ambiguous';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_FAILED = 'failed';

    /**
     * @return array<string,mixed>
     */
    public static function fixByProposalId(string $proposalId): array
    {
        $proposalId = trim($proposalId);
        if (!preg_match('/^tar-[a-f0-9]{16}$/', $proposalId)) {
            return self::result($proposalId, self::STATE_BLOCKED, 'Invalid proposal identifier.');
        }

        $resolved = self::resolveProposal($proposalId);
        if ($resolved['state'] !== 'ready') {
            return self::result($proposalId, (string)$resolved['state'], (string)$resolved['reason']);
        }

        $proposal = is_array($resolved['proposal'] ?? null) ? $resolved['proposal'] : [];
        $eligibility = self::eligibility($proposal);
        if ($eligibility['state'] !== 'ready') {
            return self::result($proposalId, (string)$eligibility['state'], (string)$eligibility['reason'], self::expectedChange($proposal));
        }

        $filePath = (string)$proposal['file_path'];
        $absolute = self::absolutePath($filePath);
        if ($absolute === null || !is_file($absolute)) {
            return self::result($proposalId, self::STATE_STALE, 'Source file is no longer readable.', self::expectedChange($proposal));
        }

        $source = @file_get_contents($absolute);
        if (!is_string($source)) {
            return self::result($proposalId, self::STATE_STALE, 'Source file could not be read.', self::expectedChange($proposal));
        }

        $target = self::resolveDeclarationTarget($proposal, $source);
        if ($target['state'] !== 'ready') {
            return self::result($proposalId, (string)$target['state'], (string)$target['reason'], self::expectedChange($proposal) + [
                'match_count' => (int)($target['match_count'] ?? 0),
            ]);
        }

        $replacement = self::replaceOneDeclaration($proposal, $source);
        if ($replacement['state'] !== 'ready') {
            return self::result($proposalId, (string)$replacement['state'], (string)$replacement['reason'], self::expectedChange($proposal));
        }

        $snapshotPath = StyleComplianceRepairEngineService::createSnapshot($filePath, $source);
        if ($snapshotPath === null) {
            return self::result($proposalId, self::STATE_BLOCKED, 'Could not create pre-write snapshot.', self::expectedChange($proposal));
        }

        $newSource = (string)$replacement['contents'];
        if (@file_put_contents($absolute, $newSource, LOCK_EX) === false) {
            return self::result($proposalId, self::STATE_FAILED, 'Source write failed.', self::expectedChange($proposal) + [
                'snapshot_path' => $snapshotPath,
            ]);
        }

        $owner = (string)($proposal['source_owner'] ?? '');
        $rescan = StyleComplianceScannerService::scan(StyleComplianceScannerService::SCOPE_OWNER, $owner);
        $fixableCount = self::fixableProposalCount($rescan);
        $gone = !self::scanContainsProposal($rescan, $proposalId);
        if (!$gone) {
            @file_put_contents($absolute, $source, LOCK_EX);
            return self::result($proposalId, self::STATE_FAILED, 'Post-write re-scan did not clear the original finding; source was restored.', self::expectedChange($proposal) + [
                'snapshot_path' => $snapshotPath,
                'rolled_back' => true,
                'original_finding_disappeared' => false,
                'fixable_now_count' => $fixableCount,
            ]);
        }

        return self::result($proposalId, self::STATE_FIXED, 'One deterministic declaration was fixed and re-scanned.', self::expectedChange($proposal) + [
            'snapshot_path' => $snapshotPath,
            'changed_declaration' => (string)$replacement['matched_declaration'],
            'replacement_declaration' => (string)$replacement['replacement_declaration'],
            'original_finding_disappeared' => true,
            'fixable_now_count' => $fixableCount,
            'rescan_scope' => StyleComplianceScannerService::SCOPE_OWNER,
            'rescan_owner' => $owner,
        ]);
    }

    /**
     * @return array{state:string,reason:string,proposal?:array<string,mixed>}
     */
    public static function resolveProposal(string $proposalId): array
    {
        $scan = StyleComplianceScannerService::scan(StyleComplianceScannerService::SCOPE_ALL_OWNERS, '');
        $queue = isset($scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'])
            && is_array($scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'])
            ? $scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply']
            : [];
        $matches = [];
        foreach ($queue as $proposal) {
            if (is_array($proposal) && (string)($proposal['proposal_id'] ?? '') === $proposalId) {
                $matches[] = $proposal;
            }
        }
        if ($matches === []) {
            return ['state' => self::STATE_STALE, 'reason' => 'Proposal is not present in the current server-side scan.'];
        }
        if (count($matches) > 1) {
            return ['state' => self::STATE_AMBIGUOUS, 'reason' => 'Proposal id resolved to multiple current proposals.'];
        }
        return ['state' => 'ready', 'reason' => 'Resolved one current proposal.', 'proposal' => $matches[0]];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array{state:string,reason:string}
     */
    public static function eligibility(array $proposal): array
    {
        $file = (string)($proposal['file_path'] ?? '');
        $property = strtolower((string)($proposal['property'] ?? ''));
        $evidence = isset($proposal['evidence']) && is_array($proposal['evidence']) ? $proposal['evidence'] : [];

        $blocked = '';
        if ((string)($proposal['proposal_class'] ?? '') !== 'deterministic_theme_value_fix') {
            $blocked = 'Proposal is not a deterministic same-owner value correction.';
        } elseif ((string)($proposal['proposal_status'] ?? '') !== 'ready_for_future_apply') {
            $blocked = 'Proposal is not ready in scanner output.';
        } elseif ((string)($proposal['source_scope'] ?? '') !== StyleComplianceScannerService::SCOPE_OWNER) {
            $blocked = 'Only owner-scope CSS sources are Fixable Now.';
        } elseif (!self::isStaticOwnerCssPath($file)) {
            $blocked = 'Source is not a supported static owner-owned CSS file.';
        } elseif ((string)($proposal['migration_state'] ?? '') !== 'value_fix') {
            $blocked = 'Migrations are review-only in V1.';
        } elseif ((string)($evidence['scan_category'] ?? '') !== 'semantic_token_misuse') {
            $blocked = 'Only semantic token misuse value corrections are Fixable Now.';
        } elseif (in_array($property, ['box-shadow', 'text-shadow', 'filter', 'backdrop-filter', 'opacity', 'animation', 'transition', 'transform'], true)) {
            $blocked = 'Effects, opacity, animation, and transform findings are review-only.';
        } elseif (!self::isCanonicalReplacement((string)($proposal['replacement_token'] ?? ''), (string)($proposal['replacement_value'] ?? ''))) {
            $blocked = 'Replacement is not one exact canonical token value.';
        } elseif ((string)($proposal['current_value'] ?? '') === '') {
            $blocked = 'Current value is missing from scanner proposal.';
        } elseif ((string)($proposal['selector'] ?? '') === '' || str_starts_with((string)($proposal['selector'] ?? ''), '[inline style')) {
            $blocked = 'Inline or missing selector context is review-only.';
        }

        return $blocked === ''
            ? ['state' => 'ready', 'reason' => 'Eligible for deterministic Fix One.']
            : ['state' => self::STATE_BLOCKED, 'reason' => $blocked];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array{state:string,reason:string,match_count:int}
     */
    public static function resolveDeclarationTarget(array $proposal, string $source): array
    {
        $property = (string)($proposal['property'] ?? '');
        $oldValue = trim((string)($proposal['current_value'] ?? ''));
        if ($property === '' || $oldValue === '') {
            return ['state' => self::STATE_BLOCKED, 'reason' => 'Proposal is missing property or current value.', 'match_count' => 0];
        }
        $pattern = self::declarationPattern($property, $oldValue);
        $count = preg_match_all($pattern, $source);
        if ($count === false || $count === 0) {
            return ['state' => self::STATE_STALE, 'reason' => 'Current source value no longer matches scanner proposal.', 'match_count' => 0];
        }
        if ($count > 1) {
            return ['state' => self::STATE_AMBIGUOUS, 'reason' => 'More than one matching declaration exists.', 'match_count' => $count];
        }
        return ['state' => 'ready', 'reason' => 'Exactly one matching declaration exists.', 'match_count' => 1];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array{state:string,reason:string,contents?:string,matched_declaration?:string,replacement_declaration?:string}
     */
    public static function replaceOneDeclaration(array $proposal, string $source): array
    {
        $property = (string)($proposal['property'] ?? '');
        $oldValue = trim((string)($proposal['current_value'] ?? ''));
        $newValue = trim((string)($proposal['replacement_value'] ?? ''));
        $pattern = self::declarationPattern($property, $oldValue);
        $matched = '';
        $replacementDeclaration = '';
        $newSource = preg_replace_callback($pattern, static function (array $m) use ($newValue, &$matched, &$replacementDeclaration): string {
            $matched = (string)$m[0];
            $replacementDeclaration = (string)$m[1] . $newValue . (string)$m[3];
            return $replacementDeclaration;
        }, $source, 1, $replacements);
        if (!is_string($newSource) || $replacements !== 1) {
            return ['state' => self::STATE_FAILED, 'reason' => 'Replacement failed before write.'];
        }
        return [
            'state' => 'ready',
            'reason' => 'Replacement prepared.',
            'contents' => $newSource,
            'matched_declaration' => $matched,
            'replacement_declaration' => $replacementDeclaration,
        ];
    }

    /**
     * @param array<string,mixed> $scan
     */
    public static function fixableProposalCount(array $scan): int
    {
        $queue = isset($scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'])
            && is_array($scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'])
            ? $scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply']
            : [];
        $count = 0;
        foreach ($queue as $proposal) {
            if (is_array($proposal) && self::eligibility($proposal)['state'] === 'ready') {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<string,mixed> $scan
     */
    private static function scanContainsProposal(array $scan, string $proposalId): bool
    {
        $queue = isset($scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'])
            && is_array($scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply'])
            ? $scan['theme_repair_proposals']['queues']['ready_for_future_guarded_apply']
            : [];
        foreach ($queue as $proposal) {
            if (is_array($proposal) && (string)($proposal['proposal_id'] ?? '') === $proposalId) {
                return true;
            }
        }
        return false;
    }

    private static function declarationPattern(string $property, string $oldValue): string
    {
        return '/((?<![-\w])' . preg_quote($property, '/') . '\s*:\s*)(' . preg_quote($oldValue, '/') . ')(\s*(?:!important\s*)?[;}])/i';
    }

    private static function isCanonicalReplacement(string $token, string $value): bool
    {
        return $token !== ''
            && preg_match('/^--[\w-]+$/', $token) === 1
            && $value === 'var(' . $token . ')';
    }

    private static function isStaticOwnerCssPath(string $file): bool
    {
        $path = ltrim(str_replace('\\', '/', $file), '/');
        $lower = strtolower($path);
        return preg_match('#^(apps|plugins)/#', $path) === 1
            && !str_starts_with($path, 'apps/Shell/')
            && !str_starts_with($path, 'apps/Generated/')
            && str_ends_with($lower, '.css')
            && !str_ends_with($lower, '.min.css')
            && !str_contains($lower, '/tests/')
            && !str_contains($lower, '/fixtures/')
            && !str_contains($lower, '/vendor/')
            && !str_contains($lower, '/node_modules/')
            && !str_contains($lower, '/dist/')
            && !str_contains($lower, '/build/')
            && !str_contains($lower, '/generated/')
            && !str_contains($lower, 'print')
            && !str_contains($lower, 'pdf');
    }

    private static function absolutePath(string $filePath): ?string
    {
        $path = ltrim(str_replace('\\', '/', $filePath), '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        $absolute = APP_ROOT . '/' . $path;
        $real = realpath($absolute);
        $root = realpath(APP_ROOT);
        if (!is_string($real) || !is_string($root)) {
            return null;
        }
        return str_starts_with($real, $root . DIRECTORY_SEPARATOR) ? $real : null;
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private static function expectedChange(array $proposal): array
    {
        return [
            'file_path' => (string)($proposal['file_path'] ?? ''),
            'selector' => (string)($proposal['selector'] ?? ''),
            'property' => (string)($proposal['property'] ?? ''),
            'current_value' => (string)($proposal['current_value'] ?? ''),
            'replacement_value' => (string)($proposal['replacement_value'] ?? ''),
            'replacement_token' => (string)($proposal['replacement_token'] ?? ''),
            'reason' => (string)($proposal['replacement_rationale'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $evidence
     * @return array<string,mixed>
     */
    private static function result(string $proposalId, string $state, string $reason, array $evidence = []): array
    {
        return [
            'ok' => $state === self::STATE_FIXED,
            'proposal_id' => $proposalId,
            'state' => $state,
            'reason' => $reason,
            'evidence' => $evidence,
        ];
    }
}
