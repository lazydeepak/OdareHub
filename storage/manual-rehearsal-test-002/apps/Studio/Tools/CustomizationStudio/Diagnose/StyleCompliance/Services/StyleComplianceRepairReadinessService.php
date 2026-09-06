<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services;

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceGuardedRepairCapabilityService.php';

/**
 * Read-only readiness checks for a future browser-driven guarded repair.
 *
 * The browser is allowed to submit only a stable proposal id. This service
 * resolves the current proposal from fresh trusted scan output before checking
 * whether a future executor could safely target exactly one declaration.
 */
final class StyleComplianceRepairReadinessService
{
    public const STATE_READY = 'ready_for_guarded_repair';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_STALE = 'stale';
    public const STATE_AMBIGUOUS = 'ambiguous';
    public const STATE_UNSUPPORTED = 'unsupported';
    public const STATE_REVIEW_REQUIRED = 'review_required';

    /**
     * @return array<string,mixed>
     */
    public static function checkByProposalId(string $proposalId): array
    {
        $proposalId = trim($proposalId);
        if (!preg_match('/^tar-[a-f0-9]{16}$/', $proposalId)) {
            return self::result($proposalId, self::STATE_UNSUPPORTED, 'Invalid proposal identifier.', [], [], []);
        }

        $matches = self::resolveCurrentProposals($proposalId);
        if ($matches === []) {
            return self::result($proposalId, self::STATE_STALE, 'Candidate is not present in the current trusted scan result.', [], [], [
                self::check('current_trusted_scan_candidate', 'stale', 'No current proposal matched this identifier.'),
            ]);
        }
        if (count($matches) > 1) {
            return self::result($proposalId, self::STATE_AMBIGUOUS, 'Candidate identifier resolved to more than one current proposal.', [], [], [
                self::check('current_trusted_scan_candidate', self::STATE_AMBIGUOUS, 'Multiple current proposals matched this identifier.'),
            ]);
        }

        $match = $matches[0];
        $proposal = isset($match['proposal']) && is_array($match['proposal']) ? $match['proposal'] : [];
        $scan = isset($match['scan']) && is_array($match['scan']) ? $match['scan'] : [];

        return self::checkProposalForReadiness($proposal, null, [
            'scan_scope' => (string)($scan['scope'] ?? ''),
            'scan_owner_key' => (string)($scan['owner_key'] ?? ''),
            'scope_descriptor' => isset($scan['scope_descriptor']) && is_array($scan['scope_descriptor']) ? $scan['scope_descriptor'] : [],
        ]);
    }

    /**
     * @param array<string,mixed> $proposal
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function checkProposalForReadiness(array $proposal, ?string $sourceContents = null, array $options = []): array
    {
        $proposalId = (string)($proposal['proposal_id'] ?? '');
        $filePath = (string)($proposal['file_path'] ?? '');
        $property = strtolower(trim((string)($proposal['property'] ?? '')));
        $currentValue = trim((string)($proposal['current_value'] ?? ''));
        $replacementToken = (string)($proposal['replacement_token'] ?? '');
        $replacementValue = trim((string)($proposal['replacement_value'] ?? ''));
        $sourceInfo = self::sourceInfo($filePath, $sourceContents);
        $contents = (string)$sourceInfo['contents'];
        $ownerType = self::ownerTypeForSource($filePath);
        $contentContext = self::contentContext($filePath, $contents);
        $target = self::resolveDeclarationTarget($proposal, $contents);
        $sourceFingerprint = self::sourceFingerprint($contents);
        $expectedFingerprint = isset($options['expected_source_fingerprint']) && $options['expected_source_fingerprint'] !== '' ? (string)$options['expected_source_fingerprint'] : $sourceFingerprint;

        $checks = [
            self::check('current_trusted_scan_candidate', $proposalId !== '' ? 'pass' : self::STATE_STALE, $proposalId !== '' ? 'Candidate resolved from trusted scan data.' : 'Candidate id is missing.'),
            self::check('deterministic_value_fix_class', (string)($proposal['proposal_class'] ?? '') === 'deterministic_theme_value_fix' ? 'pass' : self::STATE_REVIEW_REQUIRED, 'Proposal must be a deterministic Theme value-fix candidate.'),
            self::check('ready_future_status', (string)($proposal['proposal_status'] ?? '') === 'ready_for_future_apply' ? 'pass' : self::STATE_BLOCKED, 'Proposal must already be in the ready future-review state.'),
            self::check('migration_state_value_fix', (string)($proposal['migration_state'] ?? '') === 'value_fix' ? 'pass' : self::STATE_REVIEW_REQUIRED, 'Migration state must be value_fix.'),
            self::check('theme_governance_domain', (string)($proposal['governance_domain'] ?? '') === 'theme_related' && (string)($proposal['governance_required_domain'] ?? '') === 'theme_related' ? 'pass' : self::STATE_REVIEW_REQUIRED, 'Governance and required governance domain must be Theme-related.'),
            self::check('canonical_replacement_contract', self::isCanonicalReplacement($replacementToken, $replacementValue) ? 'pass' : self::STATE_BLOCKED, 'Replacement must be the canonical token expression emitted by the scanner.'),
            self::check('source_file_readable', (bool)$sourceInfo['readable'] ? 'pass' : self::STATE_STALE, 'Source file must be readable at preflight time.'),
            self::check('source_fingerprint_matches', $sourceFingerprint !== '' && $sourceFingerprint === $expectedFingerprint ? 'pass' : self::STATE_STALE, 'Source fingerprint must match the expected current source.'),
            self::check('single_declaration_target', $target['state'], (string)$target['detail']),
            self::check('editable_source_scope', self::isEditableSourcePath($filePath) ? 'pass' : self::STATE_UNSUPPORTED, 'Source path must be owner-editable and not generated/vendor/fixture/compiled output.'),
            self::check('content_context_supported', self::isSupportedContentContext($contentContext, $property) ? 'pass' : self::STATE_UNSUPPORTED, 'Content context must support this repair strategy.'),
            self::check('not_dynamic_generated_or_print_only', self::hasDynamicOrPrintEvidence($proposal, $contentContext) ? self::STATE_UNSUPPORTED : 'pass', 'Dynamic, generated, and print-only declarations are not repair-ready.'),
            self::check('not_special_effect_governed', self::isSpecialEffectGoverned($proposal, $property) ? self::STATE_UNSUPPORTED : 'pass', 'Special Effect governed declarations route to Special Effects.'),
            self::check('owner_type_context_not_prohibitive', self::isOwnerTypeSupported($ownerType, $contentContext, $property) ? 'pass' : self::STATE_REVIEW_REQUIRED, 'Owner type and content context must not prohibit this strategy.'),
        ];

        $capability = StyleComplianceGuardedRepairCapabilityService::contract();
        $state = self::summarizeState($checks);
        $reason = self::firstReason($checks, $state);
        if ($state === self::STATE_READY) {
            $reason = 'All readiness checks passed for a future guarded repair.';
        }
        if ($state === self::STATE_READY && empty($capability['executor_enabled'])) {
            $state = self::STATE_BLOCKED;
            $reason = 'Readiness checks passed, but guarded repair execution is not enabled for this tool.';
        }

        return self::result($proposalId, $state, $reason, $proposal, [
            'file_path' => $filePath,
            'line' => (int)($proposal['line'] ?? 0),
            'selector' => (string)($proposal['selector'] ?? ''),
            'property' => $property,
            'current_value' => $currentValue,
            'expected_current_value' => $currentValue,
            'replacement_value' => $replacementValue,
            'replacement_token' => $replacementToken,
            'source_fingerprint' => $sourceFingerprint,
            'owner_type' => $ownerType,
            'content_context' => $contentContext,
            'declaration_match_count' => (int)($target['match_count'] ?? 0),
            'proposal_class' => (string)($proposal['proposal_class'] ?? ''),
            'proposal_status' => (string)($proposal['proposal_status'] ?? ''),
            'migration_state' => (string)($proposal['migration_state'] ?? ''),
            'governance_domain' => (string)($proposal['governance_domain'] ?? ''),
            'governance_required_domain' => (string)($proposal['governance_required_domain'] ?? ''),
        ], $checks);
    }

    /**
     * @return array<int,array{proposal:array<string,mixed>,scan:array<string,mixed>}>
     */
    private static function resolveCurrentProposals(string $proposalId): array
    {
        $matches = [];
        foreach (self::scanContexts() as $context) {
            $scan = StyleComplianceScannerService::scan((string)$context['scope'], (string)$context['owner']);
            $queues = isset($scan['theme_repair_proposals']['queues']) && is_array($scan['theme_repair_proposals']['queues'])
                ? $scan['theme_repair_proposals']['queues']
                : [];
            $ready = isset($queues['ready_for_future_guarded_apply']) && is_array($queues['ready_for_future_guarded_apply'])
                ? $queues['ready_for_future_guarded_apply']
                : [];
            foreach ($ready as $proposal) {
                if (is_array($proposal) && (string)($proposal['proposal_id'] ?? '') === $proposalId) {
                    $matches[] = ['proposal' => $proposal, 'scan' => $scan];
                }
            }
        }
        return $matches;
    }

    /**
     * @return array<int,array{scope:string,owner:string}>
     */
    private static function scanContexts(): array
    {
        $contexts = [
            ['scope' => StyleComplianceScannerService::SCOPE_SHELL, 'owner' => ''],
            ['scope' => StyleComplianceScannerService::SCOPE_THEME, 'owner' => ''],
        ];
        foreach (StyleComplianceScannerService::discoverOwners() as $owner) {
            if (!is_array($owner)) {
                continue;
            }
            $ownerKey = (string)($owner['owner_key'] ?? '');
            if ($ownerKey !== '') {
                $contexts[] = ['scope' => StyleComplianceScannerService::SCOPE_OWNER, 'owner' => $ownerKey];
            }
        }
        return $contexts;
    }

    /**
     * @return array{readable:bool,contents:string}
     */
    private static function sourceInfo(string $filePath, ?string $sourceContents): array
    {
        if ($sourceContents !== null) {
            return ['readable' => true, 'contents' => $sourceContents];
        }
        $absolute = self::absolutePath($filePath);
        if ($absolute === null || !is_file($absolute)) {
            return ['readable' => false, 'contents' => ''];
        }
        $contents = @file_get_contents($absolute);
        return is_string($contents)
            ? ['readable' => true, 'contents' => $contents]
            : ['readable' => false, 'contents' => ''];
    }

    private static function absolutePath(string $filePath): ?string
    {
        $trimmed = ltrim($filePath, '/');
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }
        $absolute = APP_ROOT . '/' . $trimmed;
        $real = realpath($absolute);
        $root = realpath(APP_ROOT);
        if (!is_string($real) || !is_string($root)) {
            return null;
        }
        return str_starts_with($real, $root . DIRECTORY_SEPARATOR) || str_starts_with($real, $root . '/') ? $real : null;
    }

    private static function sourceFingerprint(string $contents): string
    {
        return $contents !== '' ? 'sha256:' . hash('sha256', $contents) : '';
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array{state:string,detail:string,match_count:int}
     */
    private static function resolveDeclarationTarget(array $proposal, string $contents): array
    {
        if ($contents === '') {
            return ['state' => self::STATE_STALE, 'detail' => 'Source content is unavailable.', 'match_count' => 0];
        }
        $property = preg_quote((string)($proposal['property'] ?? ''), '/');
        $value = preg_quote(trim((string)($proposal['current_value'] ?? '')), '/');
        if ($property === '' || $value === '') {
            return ['state' => self::STATE_BLOCKED, 'detail' => 'Proposal is missing property or expected value.', 'match_count' => 0];
        }
        preg_match_all('/(?<![-\w])' . $property . '\s*:\s*' . $value . '\s*(?:!important\s*)?[;}]/i', $contents, $matches);
        $count = count($matches[0]);
        if ($count === 1) {
            return ['state' => 'pass', 'detail' => 'Exactly one matching declaration was resolved.', 'match_count' => 1];
        }
        if ($count === 0) {
            return ['state' => self::STATE_STALE, 'detail' => 'Expected declaration is no longer present.', 'match_count' => 0];
        }
        // Multiple matches: try selector-context disambiguation.
        // The scanner stores the exact CSS selector text for each declaration,
        // which provides enough context to distinguish declarations with the
        // same property:value appearing under different selectors.
        $selector = trim((string)($proposal['selector'] ?? ''));
        if ($selector !== '' && !str_starts_with($selector, '[inline style')) {
            $selQuoted = preg_quote($selector, '/');
            preg_match_all('/' . $selQuoted . '\s*\{[^}]*' . $property . '\s*:\s*' . $value . '\s*(?:!important\s*)?[;}]/i', $contents, $selMatches);
            $selCount = count($selMatches[0]);
            if ($selCount === 1) {
                return ['state' => 'pass', 'detail' => 'Exactly one matching declaration was resolved via selector context.', 'match_count' => 1, 'selector_resolved' => true];
            }
        }
        return ['state' => self::STATE_AMBIGUOUS, 'detail' => 'More than one matching declaration was found.', 'match_count' => $count];
    }

    private static function isCanonicalReplacement(string $token, string $value): bool
    {
        return $token !== ''
            && str_starts_with($token, '--')
            && $value === 'var(' . $token . ')'
            && preg_match('/^--[\w-]+$/', $token) === 1;
    }

    private static function isEditableSourcePath(string $filePath): bool
    {
        $lower = strtolower($filePath);
        return preg_match('#^(apps|resources/themes)/#', ltrim($filePath, '/')) === 1
            && !str_contains($lower, '/vendor/')
            && !str_contains($lower, '/node_modules/')
            && !str_contains($lower, '/tests/fixtures/')
            && !str_contains($lower, '/snapshot')
            && !str_contains($lower, '/snapshots/')
            && !str_contains($lower, '/compiled/')
            && !str_contains($lower, '/dist/')
            && !str_contains($lower, '/build/')
            && !str_ends_with($lower, '.min.css');
    }

    private static function ownerTypeForSource(string $filePath): string
    {
        $path = ltrim(str_replace('\\', '/', $filePath), '/');
        if (str_starts_with($path, 'resources/themes/')) {
            return 'system';
        }
        if (str_starts_with($path, 'apps/Shell/')) {
            return 'shell';
        }
        if (str_starts_with($path, 'apps/Generated/')) {
            return 'generated';
        }
        if (str_contains($path, '/modules/')) {
            return 'module';
        }
        if (str_starts_with($path, 'plugins/')) {
            return 'plugin';
        }
        if (str_starts_with($path, 'apps/Studio/Tools/')) {
            return 'studio_tool';
        }
        if (str_starts_with($path, 'apps/')) {
            return 'app';
        }
        return 'unknown';
    }

    private static function contentContext(string $filePath, string $contents): string
    {
        $lowerPath = strtolower($filePath);
        $lowerContents = strtolower($contents);
        if (str_contains($lowerPath, 'runtime-generated') || str_contains($lowerContents, 'runtime-generated')) {
            return 'runtime_generated';
        }
        if (str_contains($lowerPath, 'print') || str_contains($lowerPath, 'pdf') || str_contains($lowerContents, '@media print')) {
            return 'report_or_print_template';
        }
        if (str_contains($lowerPath, 'label-template') || str_contains($lowerContents, 'label-template')) {
            return 'label_template';
        }
        return 'interactive_ui';
    }

    private static function isSupportedContentContext(string $context, string $property): bool
    {
        if ($context === 'runtime_generated') {
            return false;
        }
        if (in_array($context, ['report_or_print_template', 'label_template'], true)) {
            return !in_array($property, ['width', 'height', 'top', 'right', 'bottom', 'left', 'position', 'margin', 'padding'], true);
        }
        return true;
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function hasDynamicOrPrintEvidence(array $proposal, string $context): bool
    {
        $reviewReason = (string)($proposal['review_reason_code'] ?? '');
        $evidence = isset($proposal['evidence']) && is_array($proposal['evidence']) ? $proposal['evidence'] : [];
        return $context === 'runtime_generated'
            || $reviewReason === 'dynamic_or_unsupported'
            || $reviewReason === 'print_pdf_candidate'
            || (string)($evidence['scan_category'] ?? '') === 'dynamic_unsupported'
            || (string)($evidence['scan_category'] ?? '') === 'print_pdf';
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function isSpecialEffectGoverned(array $proposal, string $property): bool
    {
        $blocked = isset($proposal['blocked_by']) && is_array($proposal['blocked_by']) ? array_map('strval', $proposal['blocked_by']) : [];
        $evidence = isset($proposal['evidence']) && is_array($proposal['evidence']) ? $proposal['evidence'] : [];
        return (string)($proposal['governance_domain'] ?? '') === 'special_effect'
            || (string)($proposal['governance_required_domain'] ?? '') === 'special_effect'
            || in_array('special_effect_candidate', $blocked, true)
            || in_array($property, ['box-shadow', 'filter', 'opacity', 'transform', 'transition', 'animation', 'backdrop-filter'], true)
            || in_array((string)($evidence['value_construct'] ?? ''), ['shadow', 'filter'], true);
    }

    private static function isOwnerTypeSupported(string $ownerType, string $context, string $property): bool
    {
        if (in_array($ownerType, ['vendor', 'fixture', 'generated'], true)) {
            return false;
        }
        if ($context === 'runtime_generated') {
            return false;
        }
        if (in_array($context, ['report_or_print_template', 'label_template'], true)) {
            return !in_array($property, ['width', 'height', 'top', 'right', 'bottom', 'left', 'position', 'margin', 'padding'], true);
        }
        return in_array($ownerType, ['shell', 'system', 'app', 'module', 'plugin', 'studio_tool', 'unknown'], true);
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    private static function summarizeState(array $checks): string
    {
        $priority = [self::STATE_STALE, self::STATE_AMBIGUOUS, self::STATE_UNSUPPORTED, self::STATE_REVIEW_REQUIRED, self::STATE_BLOCKED];
        foreach ($priority as $state) {
            foreach ($checks as $check) {
                if ((string)($check['state'] ?? '') === $state) {
                    return $state;
                }
            }
        }
        return self::STATE_READY;
    }

    /**
     * @param array<int,array<string,string>> $checks
     */
    private static function firstReason(array $checks, string $state): string
    {
        foreach ($checks as $check) {
            if ((string)($check['state'] ?? '') === $state) {
                return (string)($check['detail'] ?? 'Readiness check did not pass.');
            }
        }
        return 'Readiness check did not pass.';
    }

    /**
     * @return array<string,mixed>
     */
    private static function result(string $proposalId, string $state, string $reason, array $proposal, array $change, array $checks): array
    {
        $capability = StyleComplianceGuardedRepairCapabilityService::contract();
        return [
            'ok' => true,
            'proposal_id' => $proposalId,
            'state' => $state,
            'reason' => $reason,
            'expected_change' => $change,
            'checks' => $checks,
            'future_executor_contract' => $capability,
        ];
    }

    /**
     * @return array{key:string,state:string,detail:string}
     */
    private static function check(string $key, string|bool $state, string $detail): array
    {
        if (is_bool($state)) {
            $state = $state ? 'pass' : self::STATE_BLOCKED;
        }
        return ['key' => $key, 'state' => $state, 'detail' => $detail];
    }
}
