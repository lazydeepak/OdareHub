<?php

declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services;

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceGuardedRepairCapabilityService.php';

/**
 * Style Compliance — Read-only scanner.
 *
 * Scans CSS plus PHP style surfaces in a chosen scope and classifies findings:
 *   - theme_aware:        token usage, token alias, theme selector declaration,
 *                         or theme source file declaration.
 *   - unaware:            literal custom property or hard-coded style property
 *                         with no theme-aware repair yet.
 *   - repairable_unaware: subset of unaware where the literal value is stable
 *                         enough to propose a semantic mapping/replacement.
 *
 * Scopes (mutually exclusive):
 *   - owner:  scans one app or module under `apps/{App}` or `apps/{App}/modules/{Module}`
 *   - shell:  scans `apps/Shell/styles/**`
 *   - theme:  scans `resources/themes/**`
 *
 * This service is strictly read-only:
 *   - No file writes.
 *   - No DB access.
 *   - No HTTP calls.
 *   - No shell/exec calls.
 *
 * Repair candidates produced here are *proposals only*. Apply behavior is
 * intentionally out of MVP scope and must be added in a later, guarded slice
 * with snapshot-backed atomic writes.
 */
final class StyleComplianceScannerService
{
    public const SCOPE_OWNER = 'owner';
    public const SCOPE_SHELL = 'shell';
    public const SCOPE_THEME = 'theme';
    public const SCOPE_ALL_OWNERS = 'all_owners';

    /** @var array<int,string> */
    private const ALLOWED_SCOPES = [self::SCOPE_OWNER, self::SCOPE_SHELL, self::SCOPE_THEME, self::SCOPE_ALL_OWNERS];

    private const DOMAIN_THEME = 'theme';
    private const DOMAIN_SHELL_FOUNDATION = 'shell_foundation';
    private const DOMAIN_OWNER_SURFACE = 'owner_surface';
    private const DOMAIN_SPECIAL_EFFECT = 'special_effect';
    private const DOMAIN_STRUCTURAL = 'structural';
    private const DOMAIN_PRINT_PDF = 'print_pdf';
    private const DOMAIN_UNSUPPORTED = 'unsupported';

    public const MIGRATION_STATE_NONE = 'none';
    public const MIGRATION_STATE_VALUE_FIX = 'value_fix';
    public const MIGRATION_STATE_DOMAIN_MIGRATION = 'domain_migration';
    public const MIGRATION_STATE_CLASSIFICATION_REVIEW = 'classification_review';
    public const MIGRATION_STATE_FUTURE_HANDOFF = 'future_handoff';
    public const MIGRATION_STATE_NOT_APPLICABLE = 'not_applicable';

    /**
     * Shared Shell wrapper chrome selectors that belong in shell_foundation.
     *
     * Each pattern is narrow and specific to Shell-owned UI chrome:
     *
     * - .layout-sidebar, .layout-main, .topbar, .app-shell
     *     Core Shell frame components. Never owner-local.
     *
     * - .hamburger, .avatar-panel, .action-panel, .shell-overlay
     *     Shell overlay/navigation surfaces. Never app business.
     *
     * - .search-trigger, .user-menu, .header-company
     *     Shell topbar/header chrome. Not module content.
     *
     * - .profile-panel, .nav-trigger, .sidebar-nav
     *     Shell navigation/profile components. Owner-local nav is rare
     *     and typically uses different class names.
     *
     * Patterns are deliberately NOT broad:
     * - No .card, .kpi, .panel, .table, .form-* — these are app/module
     *   business UI that must remain in owner_surface domain.
     * - No .coverage-*, .app-quicklink-*, .menu .group — these are
     *   app-local or shared-infrastructure but not Shell chrome.
     *   Ambiguous selectors like these must NOT become domain_migration
     *   without stronger evidence.
     */
    private const SHARED_SHELL_SELECTOR_PATTERNS = [
        '/\.(layout-sidebar|layout-main|topbar|app-shell)\b/i',
        '/\.(hamburger|avatar-panel|action-panel|shell-overlay)\b/i',
        '/\.(search-trigger|user-menu|header-company)\b/i',
        '/\.(profile-panel|nav-trigger|sidebar-nav)\b/i',
    ];

    /** Heuristic patterns for theme-aware selectors. */
    private const THEME_SELECTOR_PATTERNS = [
        '/\[data-theme\b/i',
        '/\.theme-[a-z0-9_-]+/i',
        '/:root\.[a-z0-9_-]+/i',
        '/@media\s*\(\s*prefers-color-scheme/i',
        '/\bbody\.dark\b/i',
        '/\bhtml\.dark\b/i',
        '/\.dark\s/i',
    ];

    /** Patterns suggesting print/PDF-related file paths. */
    private const PRINT_PATH_PATTERNS = [
        '/print/i',
        '/pdf/i',
        '/label/i',
        '/receipt/i',
        '/invoice/i',
        '/packing/i',
    ];

    /**
     * Top-level entry. Returns the full scan result for the view.
     *
     * @return array<string,mixed>
     */
    public static function scan(string $scope, string $ownerKey): array
    {
        $scope = in_array($scope, self::ALLOWED_SCOPES, true) ? $scope : self::SCOPE_ALL_OWNERS;
        $ownerKey = self::normalizeOwnerKey($ownerKey);
        if ($scope === self::SCOPE_OWNER && $ownerKey === '') {
            $ownerKey = self::defaultOwnerKeyWithStyleSurfaces();
        }

        if ($scope === self::SCOPE_ALL_OWNERS) {
            return self::scanAllOwners();
        }

        $scopeDescriptor = self::resolveScopeDescriptor($scope, $ownerKey);
        $owners = self::discoverOwners();

        if ($scopeDescriptor['root'] === '') {
            return [
                'scope' => $scope,
                'owner_key' => $ownerKey,
                'owners' => $owners,
                'scope_descriptor' => $scopeDescriptor,
                'summary' => self::emptySummary(),
                'tokens' => [],
                'candidates' => [],
                'theme_repair_proposals' => self::emptyThemeRepairProposalContract(),
                'boundary_findings' => [],
                'foundation_concerns' => [],
                'affected_files' => [],
                'shell_inventory' => self::emptyShellFoundationInventory(),
                'governance' => self::governanceNote($scope, $scopeDescriptor, 0),
                'scan_ready' => false,
            ];
        }

        $files = self::collectStyleSourceFiles($scopeDescriptor['root']);
        $tokens = self::scanTokens($files, $scopeDescriptor, $scope);

        // Remove test fixture tokens at the entry point so all downstream
        // aggregators (repair candidates, summary, boundary, affected files)
        // never see fixture data. This is defense-in-depth alongside the
        // file-level exclusion in collectStyleSourceFiles().
        $tokens = array_values(array_filter($tokens, static fn(array $row): bool => self::isOperationalFinding($row)));

        $candidates = self::buildRepairCandidates($tokens);
        $themeRepairProposals = self::buildThemeAwareRepairProposals($tokens);
        $boundary = self::detectBoundaryFindings($tokens, $scope, $ownerKey);
        $affected = self::summarizeAffectedFiles($tokens);
        $foundationConcerns = self::detectFoundationConcerns($files);
        $summary = self::buildSummary($tokens, $candidates, $boundary, $foundationConcerns);
        $shellInventory = self::buildShellFoundationInventory($tokens);
        $governance = self::governanceNote($scope, $scopeDescriptor, count($files));

        return [
            'scope' => $scope,
            'owner_key' => $ownerKey,
            'owners' => $owners,
            'scope_descriptor' => $scopeDescriptor,
            'summary' => $summary,
            'tokens' => $tokens,
            'candidates' => $candidates,
            'theme_repair_proposals' => $themeRepairProposals,
            'boundary_findings' => $boundary,
            'foundation_concerns' => $foundationConcerns,
            'affected_files' => $affected,
            'shell_inventory' => $shellInventory,
            'governance' => $governance,
            'scan_ready' => true,
        ];
    }

    /**
     * Scan all discovered owners and merge results.
     *
     * @return array<string,mixed>
     */
    private static function scanAllOwners(): array
    {
        $allTokens = [];
        $allFiles = [];
        $owners = self::discoverOwners();
        $ownersScanned = 0;

        foreach ($owners as $owner) {
            $ok = (string)($owner['owner_key'] ?? '');
            $root = (string)($owner['root_path'] ?? '');
            if ($ok === '' || $root === '') {
                continue;
            }
            $files = self::collectStyleSourceFiles($root);
            if ($files === []) {
                continue;
            }

            $scopeDescriptor = [
                'root' => $root,
                'label' => $ok,
                'relative' => ltrim(str_replace(APP_ROOT, '', $root), '/'),
                'description' => 'Owner: ' . $ok,
            ];
            $tokens = self::scanTokens($files, $scopeDescriptor, self::SCOPE_OWNER);
            $tokens = array_values(array_filter($tokens, static fn(array $row): bool => self::isOperationalFinding($row)));
            foreach ($tokens as $token) {
                $token['source_scope'] = 'owner';
                $token['source_owner'] = $ok;
            }
            $allTokens = array_merge($allTokens, $tokens);
            $allFiles = array_merge($allFiles, $files);
            $ownersScanned++;
        }

        $candidates = self::buildRepairCandidates($allTokens);
        $themeRepairProposals = self::buildThemeAwareRepairProposals($allTokens);
        $boundary = self::detectBoundaryFindings($allTokens, self::SCOPE_ALL_OWNERS, '');
        $affected = self::summarizeAffectedFiles($allTokens);
        $foundationConcerns = self::detectFoundationConcerns($allFiles);
        $summary = self::buildSummary($allTokens, $candidates, $boundary, $foundationConcerns);
        $shellInventory = self::buildShellFoundationInventory($allTokens);

        $scopeDescriptor = [
            'root' => APP_ROOT,
            'label' => 'All Owners',
            'relative' => 'apps/*',
            'description' => 'All discovered owners (' . $ownersScanned . ' with CSS/PHP style surfaces)',
        ];
        $governance = self::governanceNote(self::SCOPE_ALL_OWNERS, $scopeDescriptor, count($allFiles));

        return [
            'scope' => self::SCOPE_ALL_OWNERS,
            'owner_key' => '',
            'owners' => $owners,
            'scope_descriptor' => $scopeDescriptor,
            'summary' => $summary,
            'tokens' => $allTokens,
            'candidates' => $candidates,
            'theme_repair_proposals' => $themeRepairProposals,
            'boundary_findings' => $boundary,
            'foundation_concerns' => $foundationConcerns,
            'affected_files' => $affected,
            'shell_inventory' => $shellInventory,
            'governance' => $governance,
            'scan_ready' => true,
            'owners_scanned' => $ownersScanned,
            'files_scanned' => count($allFiles),
        ];
    }

    /**
     * Lightweight model for opening the tool without streaming full scan output.
     *
     * @return array<string,mixed>
     */
    public static function initialState(string $scope = self::SCOPE_ALL_OWNERS, string $ownerKey = ''): array
    {
        $scope = in_array($scope, self::ALLOWED_SCOPES, true) ? $scope : self::SCOPE_ALL_OWNERS;
        $ownerKey = self::normalizeOwnerKey($ownerKey);
        if ($scope === self::SCOPE_OWNER && $ownerKey === '') {
            $ownerKey = self::defaultOwnerKeyWithStyleSurfaces();
        }

        if ($scope === self::SCOPE_ALL_OWNERS) {
            return [
                'scope' => self::SCOPE_ALL_OWNERS,
                'owner_key' => '',
                'owners' => self::discoverOwners(),
                'scope_descriptor' => [
                    'root' => APP_ROOT,
                    'label' => 'All Owners',
                    'relative' => 'apps/*',
                    'description' => 'All discovered owners with CSS/PHP style surfaces.',
                ],
                'summary' => self::emptySummary(),
                'tokens' => [],
                'candidates' => [],
                'theme_repair_proposals' => self::emptyThemeRepairProposalContract(),
                'boundary_findings' => [],
                'foundation_concerns' => [],
                'affected_files' => [],
                'shell_inventory' => self::emptyShellFoundationInventory(),
                'governance' => self::governanceNote(self::SCOPE_ALL_OWNERS, ['root' => APP_ROOT, 'label' => 'All Owners', 'relative' => 'apps/*', 'description' => ''], 0),
                'scan_ready' => false,
                'scan_initialized' => false,
            ];
        }
        $ownerKey = self::normalizeOwnerKey($ownerKey);
        if ($scope === self::SCOPE_OWNER && $ownerKey === '') {
            $ownerKey = self::defaultOwnerKeyWithStyleSurfaces();
        }

        return [
            'scope' => $scope,
            'owner_key' => $ownerKey,
            'owners' => self::discoverOwners(),
            'scope_descriptor' => [],
            'summary' => self::emptySummary(),
            'tokens' => [],
            'candidates' => [],
            'theme_repair_proposals' => self::emptyThemeRepairProposalContract(),
            'boundary_findings' => [],
            'foundation_concerns' => [],
            'affected_files' => [],
            'shell_inventory' => self::emptyShellFoundationInventory(),
            'governance' => self::governanceNote($scope, [], 0),
            'scan_ready' => false,
            'scan_initialized' => false,
        ];
    }

    /**
     * @return array<int,array{owner_key:string,owner_type:string,root_path:string}>
     */
    public static function discoverOwners(): array
    {
        $owners = [];

        $appsRoot = APP_ROOT . '/apps';
        foreach (self::childDirectories($appsRoot) as $appDir) {
            $appKey = basename($appDir);
            // Shell has a dedicated scope because it owns shared chrome primitives.
            // Studio remains an owner so Studio tool CSS/PHP can be scanned.
            if ($appKey === 'Shell') {
                continue;
            }
            $owners[] = [
                'owner_key' => $appKey,
                'owner_type' => 'app',
                'root_path' => $appDir,
            ];
            foreach (self::childDirectories($appDir . '/modules') as $moduleDir) {
                $owners[] = [
                    'owner_key' => $appKey . '/' . basename($moduleDir),
                    'owner_type' => 'module',
                    'root_path' => $moduleDir,
                ];
            }
        }

        foreach (self::childDirectories(APP_ROOT . '/plugins') as $pluginDir) {
            $owners[] = [
                'owner_key' => 'plugins/' . basename($pluginDir),
                'owner_type' => 'plugin',
                'root_path' => $pluginDir,
            ];
        }

        usort($owners, static fn($a, $b) => strcmp($a['owner_key'], $b['owner_key']));
        return $owners;
    }

    private static function defaultOwnerKeyWithStyleSurfaces(): string
    {
        $owners = self::discoverOwners();
        foreach ($owners as $owner) {
            if ((string)($owner['owner_key'] ?? '') !== 'Studio') {
                continue;
            }
            $root = (string)($owner['root_path'] ?? '');
            if ($root !== '' && self::collectStyleSourceFiles($root) !== []) {
                return 'Studio';
            }
        }

        foreach ($owners as $owner) {
            $root = (string)($owner['root_path'] ?? '');
            if ($root !== '' && self::collectStyleSourceFiles($root) !== []) {
                return (string)($owner['owner_key'] ?? '');
            }
        }

        return '';
    }

    /**
     * @return array{root:string,label:string,relative:string,description:string}
     */
    private static function resolveScopeDescriptor(string $scope, string $ownerKey): array
    {
        if ($scope === self::SCOPE_SHELL) {
            $root = APP_ROOT . '/apps/Shell/styles';
            return [
                'root' => is_dir($root) ? $root : '',
                'label' => 'Shell',
                'relative' => 'apps/Shell/styles',
                'description' => 'Shell wrapper chrome, shared primitives, and global tokens.',
            ];
        }
        if ($scope === self::SCOPE_THEME) {
            $root = APP_ROOT . '/resources/themes';
            return [
                'root' => is_dir($root) ? $root : '',
                'label' => 'Theme Sources',
                'relative' => 'resources/themes',
                'description' => 'Canonical theme source files (foundation, semantic, variants).',
            ];
        }

        if ($ownerKey === '') {
            return ['root' => '', 'label' => '', 'relative' => '', 'description' => ''];
        }

        // Look up the owner's actual filesystem root via discovery (handles
        // module paths like Manufacturing/Coverage => apps/Manufacturing/modules/Coverage
        // and plugin paths like plugins/Base => plugins/Base).
        $resolved = '';
        foreach (self::discoverOwners() as $own) {
            if (($own['owner_key'] ?? '') === $ownerKey) {
                $resolved = (string)($own['root_path'] ?? '');
                break;
            }
        }
        if ($resolved === '') {
            return ['root' => '', 'label' => $ownerKey, 'relative' => $ownerKey, 'description' => ''];
        }
        $real = realpath($resolved);
        if (!is_string($real) || !is_dir($real) || !self::isInsideAppRoot($real)) {
            return ['root' => '', 'label' => $ownerKey, 'relative' => $ownerKey, 'description' => ''];
        }

        return [
            'root' => $real,
            'label' => $ownerKey,
            'relative' => self::relativePath($real),
            'description' => 'Owner-scoped CSS for ' . $ownerKey . '.',
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function collectCssFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $ext = strtolower($entry->getExtension());
            if ($ext !== 'css') {
                continue;
            }
            $path = $entry->getRealPath();
            if (!is_string($path) || !self::isInsideAppRoot($path)) {
                continue;
            }
            // Skip generated/minified outputs.
            if (str_contains($path, '/node_modules/') || str_contains($path, '/vendor/')) {
                continue;
            }
            if (str_ends_with($path, '.min.css')) {
                continue;
            }
            // Skip test fixture files — they are for probe validation only.
            if (str_contains($path, '/Tests/fixtures/')) {
                continue;
            }
            $files[] = $path;
        }
        sort($files, SORT_STRING);
        return $files;
    }

    /**
     * @return array<int,string>
     */
    private static function collectStyleSourceFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }
        $files = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($it as $entry) {
            if (!$entry->isFile()) {
                continue;
            }
            $ext = strtolower($entry->getExtension());
            if (!in_array($ext, ['css', 'php'], true)) {
                continue;
            }
            $path = $entry->getRealPath();
            if (!is_string($path) || !self::isInsideAppRoot($path)) {
                continue;
            }
            if (str_contains($path, '/node_modules/') || str_contains($path, '/vendor/')) {
                continue;
            }
            // Skip test fixture files — they are for probe validation only,
            // not production scanning. Calibration fixtures live under
            // Tests/fixtures/ in any owner root.
            if (str_contains($path, '/Tests/fixtures/')) {
                continue;
            }
            if ($ext === 'css' && str_ends_with($path, '.min.css')) {
                continue;
            }
            $files[] = $path;
        }
        sort($files, SORT_STRING);
        return $files;
    }

    /**
     * @param array<int,string> $files
     * @return array<int,array<string,mixed>>
     */
    private static function scanTokens(array $files, array $scopeDescriptor, string $scope = self::SCOPE_OWNER): array
    {
        $scopeRoot = $scopeDescriptor['root'];
        $tokens = [];

        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if (!is_string($contents) || $contents === '') {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $isThemeSourceFile = self::isThemeSourceFile($file);
            if ($ext === 'css') {
                array_push($tokens, ...self::scanCssLikeContents($contents, $file, $isThemeSourceFile));
                continue;
            }
            if ($ext === 'php') {
                array_push($tokens, ...self::scanPhpStyleContents($contents, $file, $isThemeSourceFile));
            }
        }

        // Collapse duplicates (same token+file+selector) but keep theme_aware true if any variant is aware.
        $byKey = [];
        foreach ($tokens as $row) {
            if (!self::isCompleteTokenRow($row)) {
                continue;
            }
            $key = (string)$row['kind'] . '|' . (string)$row['token'] . '|' . (string)$row['file'] . '|' . (string)$row['selector'] . '|' . (string)$row['value'];
            if (!isset($byKey[$key])) {
                $byKey[$key] = $row;
                continue;
            }
            if ($row['theme_aware']) {
                $byKey[$key]['theme_aware'] = true;
            }
        }
        $deduped = array_values($byKey);

        foreach ($deduped as &$row) {
            $row['token_theme_aware_overall'] = !empty($row['theme_aware']);
            $row['source_scope'] = $scope;
            $row = self::withRepairLaneMetadata($row);
            $row = self::withMigrationDecision($row);
        }
        unset($row);

        usort($deduped, static function (array $a, array $b): int {
            $cmp = strcmp((string)($a['token'] ?? ''), (string)($b['token'] ?? ''));
            if ($cmp !== 0) {
                return $cmp;
            }
            return strcmp((string)($a['file'] ?? ''), (string)($b['file'] ?? ''));
        });

        // Avoid lint warning for unused $scopeRoot — kept for future cross-scope diagnostics.
        unset($scopeRoot);
        return $deduped;
    }

    private static function isCompleteTokenRow(array $row): bool
    {
        foreach (['kind', 'token', 'file', 'selector', 'value'] as $key) {
            if (!array_key_exists($key, $row) || !is_scalar($row[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string,mixed>
     */
    private static function withRepairLaneMetadata(array $row): array
    {
        $domain = self::classifyStyleDomain($row);
        $row['style_domain'] = $domain;
        $row['repair_owner'] = self::repairOwnerForDomain($domain, (string)($row['file'] ?? ''));
        $row['recommended_tool'] = self::recommendedToolForDomain($domain);
        $row['repair_lane'] = self::repairLaneForDomain($domain);
        return $row;
    }

    private static function classifyStyleDomain(array $row): string
    {
        $scanCategory = (string)($row['scan_category'] ?? '');
        $file = (string)($row['file'] ?? '');
        $property = strtolower((string)($row['property'] ?? $row['token'] ?? ''));

        if (!empty($row['is_dynamic']) || $scanCategory === 'dynamic_unsupported') {
            return self::DOMAIN_UNSUPPORTED;
        }
        if (!empty($row['is_print_style']) || $scanCategory === 'print_pdf') {
            return self::DOMAIN_PRINT_PDF;
        }
        if (!empty($row['is_effect_candidate']) || $scanCategory === 'effect_candidate' || self::isEffectProperty($property)) {
            return self::DOMAIN_SPECIAL_EFFECT;
        }
        if ($scanCategory === 'structural_out_of_scope' || self::isStructuralProperty($property)) {
            return self::DOMAIN_STRUCTURAL;
        }
        if ($scanCategory === 'semantic_token_misuse') {
            return self::DOMAIN_THEME;
        }
        if (!empty($row['in_theme_source']) || str_starts_with($file, 'resources/themes/')) {
            return self::DOMAIN_THEME;
        }
        if (str_starts_with($file, 'apps/Shell/')) {
            return self::DOMAIN_SHELL_FOUNDATION;
        }
        if (str_starts_with($file, 'apps/Studio/styles/')) {
            return self::DOMAIN_SHELL_FOUNDATION;
        }

        return self::DOMAIN_OWNER_SURFACE;
    }

    private static function repairOwnerForDomain(string $domain, string $file): string
    {
        return match ($domain) {
            self::DOMAIN_THEME => 'Theme',
            self::DOMAIN_SHELL_FOUNDATION => 'Shell/Foundation',
            self::DOMAIN_SPECIAL_EFFECT => 'Special Effects',
            self::DOMAIN_STRUCTURAL => 'Manual Structural Review',
            self::DOMAIN_PRINT_PDF => 'Print/PDF Review',
            self::DOMAIN_UNSUPPORTED => 'Unsupported/Dynamic Review',
            default => self::ownerKeyFromRelativeFile($file),
        };
    }

    private static function recommendedToolForDomain(string $domain): string
    {
        return match ($domain) {
            self::DOMAIN_THEME => 'theme_doctor',
            self::DOMAIN_SPECIAL_EFFECT => 'special_effects',
            self::DOMAIN_STRUCTURAL => 'manual_structural_review',
            self::DOMAIN_PRINT_PDF => 'print_pdf_review',
            self::DOMAIN_UNSUPPORTED => 'manual_review',
            default => 'style_compliance',
        };
    }

    private static function repairLaneForDomain(string $domain): string
    {
        return match ($domain) {
            self::DOMAIN_THEME => 'Theme token issues',
            self::DOMAIN_SHELL_FOUNDATION => 'Shell/Foundation issues',
            self::DOMAIN_SPECIAL_EFFECT => 'Special Effects candidates',
            self::DOMAIN_STRUCTURAL => 'Structural evidence',
            self::DOMAIN_PRINT_PDF => 'Print/PDF review',
            self::DOMAIN_UNSUPPORTED => 'Unsupported/Dynamic review',
            default => 'Owner surface issues',
        };
    }

    /**
     * @return array<string,mixed>
     */
    private static function withMigrationDecision(array $row): array
    {
        $currentDomain = (string)($row['style_domain'] ?? 'owner_surface');
        $row['governance_domain'] = $currentDomain;

        $scanCategory = (string)($row['scan_category'] ?? '');
        $file = (string)($row['file'] ?? '');
        $selector = (string)($row['selector'] ?? '');
        $property = strtolower((string)($row['property'] ?? $row['token'] ?? ''));
        $inThemeSource = !empty($row['in_theme_source']);
        $isEffect = self::isEffectProperty($property);
        $hasShellSelector = self::matchesSharedShellSelector($selector);
        $inShellFile = str_starts_with($file, 'apps/Shell/');

        // Determine governance_required_domain based on file path, selector, and property.
        $requiredDomain = self::determineRequiredDomain(
            $currentDomain, $scanCategory, $file, $inThemeSource, $inShellFile,
            $hasShellSelector, $isEffect,
        );
        $row['governance_required_domain'] = $requiredDomain;

        // Normalize governance domain values for display.
        // Raw classifier labels (theme, owner_surface, structural, etc.) are not user-facing.
        // Map to a four-value enum: theme_related, shell_foundation, special_effect, not_applicable.
        $row['governance_domain'] = self::normalizeGovernanceDomain($row['governance_domain']);
        $row['governance_required_domain'] = self::normalizeGovernanceDomain($requiredDomain);
        $row['source_owner'] = self::ownerKeyFromRelativeFile($file);

        // Determine migration state, reason, and confidence.
        $isStructuralCat = $scanCategory === 'structural_out_of_scope';
        $isDynamicCat = $scanCategory === 'dynamic_unsupported';
        $isPrintCat = $scanCategory === 'print_pdf';
        $isDefinition = $scanCategory === 'token_definition';
        $isMisuse = $scanCategory === 'semantic_token_misuse';
        $isEffectCat = $scanCategory === 'effect_candidate';
        $isAware = !empty($row['theme_aware']);

        if ($currentDomain === self::DOMAIN_STRUCTURAL || $isStructuralCat) {
            $row['migration_state'] = self::MIGRATION_STATE_NOT_APPLICABLE;
            $row['migration_reason'] = 'Structural/geometry declaration — out of compliance scope.';
            $row['migration_confidence'] = 'high';
        } elseif ($currentDomain === self::DOMAIN_PRINT_PDF || $isPrintCat) {
            $row['migration_state'] = self::MIGRATION_STATE_NOT_APPLICABLE;
            $row['migration_reason'] = 'Print/PDF style — out of compliance scope.';
            $row['migration_confidence'] = 'high';
        } elseif ($currentDomain === self::DOMAIN_UNSUPPORTED || $isDynamicCat) {
            $row['migration_state'] = self::MIGRATION_STATE_NOT_APPLICABLE;
            $row['migration_reason'] = 'Dynamic or unsupported construct — not applicable for compliance migration.';
            $row['migration_confidence'] = 'high';
        } elseif ($currentDomain === self::DOMAIN_SPECIAL_EFFECT || $isEffectCat || $isEffect) {
            $row['migration_state'] = self::MIGRATION_STATE_FUTURE_HANDOFF;
            $row['migration_reason'] = 'Special effect property — handle via future Special Effects pipeline.';
            $row['migration_confidence'] = 'high';
        } elseif ($currentDomain !== $requiredDomain) {
            // Domain migration needed.
            $row['migration_state'] = self::MIGRATION_STATE_DOMAIN_MIGRATION;
            $confidence = 'high';
            $reason = '';
            if ($requiredDomain === self::DOMAIN_SHELL_FOUNDATION) {
                $reason = 'Shared Shell chrome selector found in non-Shell CSS — should move to Shell/Foundation.';
                if ($inThemeSource) {
                    $confidence = 'high';
                } elseif (!$hasShellSelector) {
                    $confidence = 'medium';
                    $reason = 'Selector pattern suggests Shell/Foundation ownership — verify before migration.';
                }
            } elseif ($requiredDomain === self::DOMAIN_SPECIAL_EFFECT) {
                $reason = 'Effect property declared outside Special Effects domain.';
                $confidence = 'medium';
            } elseif ($requiredDomain === self::DOMAIN_THEME) {
                $reason = 'Value uses theme tokens or definitions that belong in theme source files.';
                $confidence = 'medium';
            } else {
                $reason = 'Style declaration belongs to a different compliance domain.';
                $confidence = 'medium';
            }
            $row['migration_reason'] = $reason;
            $row['migration_confidence'] = $confidence;
        } elseif ($isMisuse) {
            $row['migration_state'] = self::MIGRATION_STATE_VALUE_FIX;
            $row['migration_reason'] = 'Semantic token role mismatch — needs corrected token reference.';
            $row['migration_confidence'] = 'high';
        } elseif ($isDefinition) {
            $row['migration_state'] = self::MIGRATION_STATE_NONE;
            $row['migration_reason'] = 'Token definition — no migration needed.';
            $row['migration_confidence'] = 'high';
        } elseif ($isAware) {
            $row['migration_state'] = self::MIGRATION_STATE_NONE;
            $row['migration_reason'] = 'Already theme-aware — no migration needed.';
            $row['migration_confidence'] = 'high';
        } elseif ($scanCategory === 'visual_literal' || $scanCategory === 'token_consumer') {
            // Same domain, needs value fix or review.
            $isStableLiteral = !empty($row['value_classification']['is_literal']);
            if ($isStableLiteral && $requiredDomain === self::DOMAIN_OWNER_SURFACE) {
                $row['migration_state'] = self::MIGRATION_STATE_VALUE_FIX;
                $row['migration_reason'] = 'Literal visual value in owner surface — needs token replacement.';
                $row['migration_confidence'] = 'high';
            } elseif ($isStableLiteral && $requiredDomain === self::DOMAIN_THEME) {
                $row['migration_state'] = self::MIGRATION_STATE_VALUE_FIX;
                $row['migration_reason'] = 'Literal value in theme source — should use var() reference.';
                $row['migration_confidence'] = 'high';
            } elseif ($isStableLiteral) {
                $row['migration_state'] = self::MIGRATION_STATE_VALUE_FIX;
                $row['migration_reason'] = 'Literal visual value — needs semantic token assignment.';
                $row['migration_confidence'] = 'medium';
            } else {
                $row['migration_state'] = self::MIGRATION_STATE_CLASSIFICATION_REVIEW;
                $row['migration_reason'] = 'Non-literal value needs classification review before repair.';
                $row['migration_confidence'] = 'low';
            }
        } else {
            $row['migration_state'] = self::MIGRATION_STATE_CLASSIFICATION_REVIEW;
            $row['migration_reason'] = 'Unclassified finding — needs manual review.';
            $row['migration_confidence'] = 'low';
        }

        // Target owner and tool based on required domain.
        $row['target_owner'] = match ($requiredDomain) {
            self::DOMAIN_THEME => 'Theme',
            self::DOMAIN_SHELL_FOUNDATION => 'Shell/Foundation',
            self::DOMAIN_SPECIAL_EFFECT => 'Special Effects Pipeline',
            self::DOMAIN_STRUCTURAL => 'N/A',
            self::DOMAIN_PRINT_PDF => 'Print/PDF Review',
            self::DOMAIN_UNSUPPORTED => 'N/A',
            default => self::ownerKeyFromRelativeFile($file),
        };
        $row['target_tool'] = match ($requiredDomain) {
            self::DOMAIN_THEME => 'css_token_editor',
            self::DOMAIN_SHELL_FOUNDATION => 'style_compliance',
            self::DOMAIN_SPECIAL_EFFECT => 'special_effects',
            self::DOMAIN_STRUCTURAL => 'none',
            self::DOMAIN_PRINT_PDF => 'none',
            self::DOMAIN_UNSUPPORTED => 'none',
            default => 'style_compliance',
        };

        // Studio tool-internal CSS: intentionally hardcoded presentation values
        // (background: transparent, border: 0/none, opacity: 0.xx, etc.) should
        // not produce classification_review findings.
        if ($row['migration_state'] === self::MIGRATION_STATE_CLASSIFICATION_REVIEW && self::isStudioToolCssPath($file)) {
            $row['migration_state'] = self::MIGRATION_STATE_NOT_APPLICABLE;
            $row['migration_reason'] = 'Studio tool-internal CSS: intentionally hardcoded presentation value.';
            $row['migration_confidence'] = 'high';
        }

        return $row;
    }

    /**
     * Determine the required compliance domain for a token row.
     */
    private static function determineRequiredDomain(
        string $currentDomain,
        string $scanCategory,
        string $file,
        bool $inThemeSource,
        bool $inShellFile,
        bool $hasShellSelector,
        bool $isEffect,
    ): string {
        // Structural, dynamic, print categories stay in their current domain.
        if ($scanCategory === 'structural_out_of_scope') {
            return self::DOMAIN_STRUCTURAL;
        }
        if ($scanCategory === 'dynamic_unsupported') {
            return self::DOMAIN_UNSUPPORTED;
        }
        if ($scanCategory === 'print_pdf') {
            return self::DOMAIN_PRINT_PDF;
        }

        // Effect properties belong in special_effect domain regardless of location.
        if ($isEffect || $scanCategory === 'effect_candidate') {
            return self::DOMAIN_SPECIAL_EFFECT;
        }

        // Shared Shell chrome selectors found outside Shell CSS belong in shell_foundation.
        if ($hasShellSelector && !$inShellFile) {
            return self::DOMAIN_SHELL_FOUNDATION;
        }

        // Theme source tokens: definitions stay, consumers/literals with shell selectors migrate.
        if ($inThemeSource || str_starts_with($file, 'resources/themes/')) {
            if ($hasShellSelector) {
                return self::DOMAIN_SHELL_FOUNDATION;
            }
            return self::DOMAIN_THEME;
        }

        // Shell CSS stays in shell_foundation.
        if ($inShellFile || str_starts_with($file, 'apps/Shell/')) {
            return self::DOMAIN_SHELL_FOUNDATION;
        }

        // Studio styles are treated as Shell/Foundation for domain purposes.
        if (str_starts_with($file, 'apps/Studio/styles/')) {
            return self::DOMAIN_SHELL_FOUNDATION;
        }

        // All other tokens stay in their current domain.
        return $currentDomain;
    }

    /**
     * Check if a CSS selector matches known shared Shell chrome patterns.
     */
    private static function matchesSharedShellSelector(string $selector): bool
    {
        if ($selector === '') {
            return false;
        }
        foreach (self::SHARED_SHELL_SELECTOR_PATTERNS as $pattern) {
            if (preg_match($pattern, $selector) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build an advisory Shell/Foundation inventory. This catalog is read-only
     * and intentionally separate from migration_state.
     *
     * @param array<int,array<string,mixed>> $tokens
     * @return array{summary:array<string,int>,items:array<int,array<string,mixed>>}
     */
    public static function buildShellFoundationInventory(array $tokens): array
    {
        $operational = array_values(array_filter($tokens, static fn(array $row): bool => self::isOperationalFinding($row)));
        $usage = self::shellInventoryUsageIndex($operational);
        $items = [];
        $outsideInventoryCount = 0;

        foreach ($operational as $row) {
            $record = self::shellInventoryRecord($row, $usage);
            if ($record === null) {
                $outsideInventoryCount++;
                continue;
            }
            $items[] = $record;
        }

        usort($items, static function (array $a, array $b): int {
            $dispOrder = [
                'candidate_shared_shell_primitive' => 0,
                'remain_owner_local_shell_governed' => 1,
                'classification_review' => 2,
                'future_special_effect_handoff' => 3,
                'remain_theme_related' => 4,
                'intentional_exception' => 5,
                'excluded_or_unsupported' => 6,
            ];
            $critOrder = ['critical' => 0, 'high' => 1, 'normal' => 2, 'unknown' => 3];
            $d = ($dispOrder[(string)($a['recommended_disposition'] ?? '')] ?? 9)
                <=> ($dispOrder[(string)($b['recommended_disposition'] ?? '')] ?? 9);
            if ($d !== 0) {
                return $d;
            }
            $c = ($critOrder[(string)($a['criticality'] ?? '')] ?? 9)
                <=> ($critOrder[(string)($b['criticality'] ?? '')] ?? 9);
            if ($c !== 0) {
                return $c;
            }
            $owners = (int)($b['distinct_owner_count'] ?? 0) <=> (int)($a['distinct_owner_count'] ?? 0);
            if ($owners !== 0) {
                return $owners;
            }
            return strcmp((string)($a['inventory_id'] ?? ''), (string)($b['inventory_id'] ?? ''));
        });

        return [
            'summary' => self::summarizeShellInventory($items, count($operational), $outsideInventoryCount),
            'items' => $items,
        ];
    }

    /**
     * @return array{summary:array<string,int>,items:array<int,array<string,mixed>>}
     */
    private static function emptyShellFoundationInventory(): array
    {
        return [
            'summary' => [
                'total' => 0,
                'critical' => 0,
                'high' => 0,
                'normal' => 0,
                'unknown' => 0,
                'candidate_shared_shell_primitive' => 0,
                'remain_owner_local_shell_governed' => 0,
                'classification_review' => 0,
                'future_special_effect_handoff' => 0,
                'remain_theme_related' => 0,
                'intentional_exception' => 0,
                'excluded_or_unsupported' => 0,
                'declarations_scanned' => 0,
                'shell_relevant_evidence' => 0,
                'eligible_candidates' => 0,
                'evidence_only' => 0,
                'outside_inventory' => 0,
                'excluded' => 0,
            ],
            'items' => [],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $tokens
     * @return array<string,array{usage:int,owners:array<string,bool>}>
     */
    private static function shellInventoryUsageIndex(array $tokens): array
    {
        $usage = [];
        foreach ($tokens as $row) {
            $key = self::shellInventoryReusableKey($row);
            if ($key === '') {
                continue;
            }
            if (!isset($usage[$key])) {
                $usage[$key] = ['usage' => 0, 'owners' => []];
            }
            $usage[$key]['usage']++;
            $owner = (string)($row['source_owner'] ?? '');
            if ($owner === '') {
                $owner = self::ownerKeyFromRelativeFile((string)($row['file'] ?? ''));
            }
            if ($owner !== '') {
                $usage[$key]['owners'][$owner] = true;
            }
        }
        return $usage;
    }

    private static function shellInventoryReusableKey(array $row): string
    {
        return self::structuralSignatureForRow($row);
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,array{usage:int,owners:array<string,bool>}> $usage
     * @return array<string,mixed>|null
     */
    private static function shellInventoryRecord(array $row, array $usage): ?array
    {
        $property = strtolower((string)($row['property'] ?? $row['token'] ?? ''));
        $value = (string)($row['normalized_value'] ?? $row['value'] ?? '');
        $rawValue = (string)($row['raw_value'] ?? $value);
        $selector = (string)($row['selector'] ?? '');
        $file = (string)($row['file'] ?? '');
        $scanCategory = (string)($row['scan_category'] ?? '');
        $governanceDomain = (string)($row['governance_domain'] ?? 'not_applicable');
        $requiredDomain = (string)($row['governance_required_domain'] ?? $governanceDomain);
        $styleDomain = (string)($row['style_domain'] ?? '');
        $sourceOwner = (string)($row['source_owner'] ?? self::ownerKeyFromRelativeFile($file));
        $sourceScope = (string)($row['source_scope'] ?? 'owner');
        $isEffect = !empty($row['is_effect_candidate']) || self::isShellInventoryEffectLike($property, $rawValue, $selector);
        $isTheme = $governanceDomain === 'theme_related' || $styleDomain === self::DOMAIN_THEME;
        $isUnsupported = !empty($row['is_dynamic']) || $scanCategory === 'dynamic_unsupported' || $styleDomain === self::DOMAIN_UNSUPPORTED;
        $isPrint = !empty($row['is_print_style']) || $scanCategory === 'print_pdf' || $styleDomain === self::DOMAIN_PRINT_PDF;
        $isStructural = $scanCategory === 'structural_out_of_scope' || self::isShellInventoryStructuralEvidence($property, $selector, $rawValue);
        $isShellSelector = self::matchesSharedShellSelector($selector);
        $hasFocusRisk = self::isFocusVisibilityRisk($property, $rawValue, $selector);

        if (!$isStructural && !$isTheme && !$isEffect && !$isUnsupported && !$isPrint && !$hasFocusRisk && !$isShellSelector) {
            return null;
        }

        $structuralSignature = self::structuralSignatureForRow($row);
        $usageKey = $structuralSignature;
        $usageRow = $usageKey !== '' && isset($usage[$usageKey]) ? $usage[$usageKey] : ['usage' => 0, 'owners' => []];
        $crossOwnerUsage = (int)($usageRow['usage'] ?? 0);
        $ownerMap = isset($usageRow['owners']) && is_array($usageRow['owners']) ? $usageRow['owners'] : [];
        $distinctOwnerCount = count($ownerMap);
        $crossOwnerEvidence = array_keys($ownerMap);
        sort($crossOwnerEvidence, SORT_STRING);
        $candidateType = self::shellCandidateType($row, $isShellSelector, $isTheme, $isEffect, $isUnsupported, $isPrint, $hasFocusRisk);
        [$population, $inclusionReason] = self::shellInventoryPopulation($row, $candidateType, $isShellSelector, $distinctOwnerCount, $isTheme, $isEffect, $isUnsupported, $isPrint, $hasFocusRisk);
        [$criticality, $criticalityReason] = self::shellInventoryCriticality($row, $candidateType, $population, $isShellSelector);
        $confidence = self::shellInventoryConfidence($row, $candidateType, $isShellSelector, $distinctOwnerCount, $hasFocusRisk, $isUnsupported);
        [$disposition, $reason, $targetOwner] = self::shellInventoryDisposition(
            $row,
            $candidateType,
            $population,
            $confidence,
            $criticality,
            $crossOwnerUsage,
            $distinctOwnerCount,
            $isShellSelector,
            $isTheme,
            $isEffect,
            $isUnsupported,
            $isPrint,
            $hasFocusRisk
        );
        $reviewReasonCode = self::shellInventoryReviewReasonCode($row, $disposition, $isUnsupported, $isPrint, $hasFocusRisk, $isEffect);

        $evidence = [];
        if ($isShellSelector) {
            $evidence[] = 'shared_shell_selector';
        }
        if ($isStructural) {
            $evidence[] = 'structural_declaration';
        }
        if ($distinctOwnerCount >= 2) {
            $evidence[] = 'cross_owner_reuse';
        }
        if ($isTheme) {
            $evidence[] = 'theme_related_domain';
        }
        if ($isEffect) {
            $evidence[] = 'effect_like_declaration';
        }
        if ($hasFocusRisk) {
            $evidence[] = 'focus_visibility_review';
        }
        if ($isPrint) {
            $evidence[] = 'print_context';
        }
        if ($isUnsupported) {
            $evidence[] = 'unsupported_dynamic_source';
        }

        return [
            'inventory_id' => 'sfi-' . substr(sha1($file . '|' . (string)($row['line_start'] ?? '0') . '|' . $selector . '|' . $property . '|' . $value), 0, 16),
            'file_path' => $file,
            'line' => (int)($row['line_start'] ?? 0),
            'selector' => $selector,
            'property' => $property,
            'value' => $value,
            'source_scope' => $sourceScope,
            'source_owner' => $sourceOwner,
            'governance_domain' => $governanceDomain,
            'inventory_population' => $population,
            'inventory_inclusion_reason' => $inclusionReason,
            'structural_signature' => $structuralSignature,
            'review_reason_code' => $reviewReasonCode,
            'criticality_reason' => $criticalityReason,
            'cross_owner_evidence' => $crossOwnerEvidence,
            'shell_candidate_type' => $candidateType,
            'shell_evidence' => $evidence,
            'confidence' => $confidence,
            'cross_owner_usage_count' => $crossOwnerUsage,
            'distinct_owner_count' => $distinctOwnerCount,
            'criticality' => $criticality,
            'recommended_disposition' => $disposition,
            'disposition_reason' => $reason,
            'target_owner' => $targetOwner,
            'review_required' => $disposition === 'classification_review' || $population === 'eligible_candidate',
            'migration_state' => (string)($row['migration_state'] ?? self::MIGRATION_STATE_NONE),
            'governance_required_domain' => $requiredDomain,
        ];
    }

    private static function structuralSignatureForRow(array $row): string
    {
        $selector = strtolower((string)($row['selector'] ?? ''));
        $property = strtolower((string)($row['property'] ?? $row['token'] ?? ''));
        $value = strtolower((string)($row['normalized_value'] ?? $row['value'] ?? $row['raw_value'] ?? ''));
        $role = self::selectorRoleFamily($selector, (string)($row['file'] ?? ''));
        $family = self::propertyFamily($property);
        $intent = self::structuralIntent($property, $value, $selector);
        if ($role === '' || $family === '' || $intent === '') {
            return '';
        }
        if ($role === 'owner-component' && in_array($intent, ['generic-flex-alignment', 'flex-layout', 'grid-layout', 'block-layout', 'relative-positioning', 'generic-overflow', 'layering'], true)) {
            return '';
        }
        return $role . '+' . $family . '+' . $intent;
    }

    private static function selectorRoleFamily(string $selector, string $file): string
    {
        $text = strtolower($file . ' ' . $selector);
        if (preg_match('/\b(app-shell|layout-sidebar|layout-main|topbar|sidebar-nav|shell-overlay|nav-trigger|user-menu|profile-panel|app-shell__sidebar)\b/i', $text) === 1) {
            return 'shell-navigation';
        }
        if (preg_match('/\b(dialog|modal|drawer|popover|shell-overlay)\b/i', $text) === 1) {
            return 'dialog-overlay';
        }
        if (preg_match('/\b(table|data-table|table-wrapper|grid-table)\b/i', $text) === 1) {
            return 'table-wrapper';
        }
        if (preg_match('/\b(input|select|textarea|button|form-control|field)\b/i', $text) === 1) {
            return 'form-control';
        }
        if (str_contains($text, 'focus-visible') || str_contains($text, ':focus')) {
            return 'focus-visible';
        }
        if (preg_match('/\b(login|setup|bootstrap|auth)\b/i', $text) === 1) {
            return 'bootstrap-fallback';
        }
        if (preg_match('/@media|\bresponsive|breakpoint|container\b/i', $text) === 1) {
            return 'responsive-layout';
        }
        if (preg_match('/\b(root|app|main|page|workspace|viewport)\b/i', $text) === 1) {
            return 'root-containment';
        }
        return 'owner-component';
    }

    private static function propertyFamily(string $property): string
    {
        if (in_array($property, ['display', 'flex', 'flex-direction', 'flex-wrap', 'align-items', 'justify-content', 'gap', 'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows'], true)) {
            return 'layout';
        }
        if (str_starts_with($property, 'overflow')) {
            return 'overflow-containment';
        }
        if (in_array($property, ['position', 'inset', 'top', 'right', 'bottom', 'left', 'z-index'], true)) {
            return 'positioning';
        }
        if (in_array($property, ['outline', 'outline-offset', 'box-shadow'], true)) {
            return 'focus-visibility';
        }
        if (in_array($property, ['width', 'min-width', 'max-width', 'height', 'min-height', 'max-height', 'box-sizing', 'aspect-ratio'], true)) {
            return 'sizing';
        }
        if (in_array($property, ['table-layout', 'border-collapse', 'border-spacing'], true)) {
            return 'table-structure';
        }
        if ($property === 'transform') {
            return 'transform';
        }
        if ($property === 'opacity') {
            return 'opacity';
        }
        return $property !== '' ? 'structural' : '';
    }

    private static function structuralIntent(string $property, string $value, string $selector): string
    {
        $prop = strtolower($property);
        $val = strtolower($value);
        $sel = strtolower($selector);
        if (str_contains($sel, 'focus') && in_array($prop, ['outline', 'box-shadow'], true)) {
            return 'accessibility-outline';
        }
        if (str_starts_with($prop, 'overflow')) {
            if ($prop === 'overflow-x' || str_contains($val, 'auto')) {
                return str_contains($sel, 'table') ? 'horizontal-overflow' : 'scroll-containment';
            }
            return 'generic-overflow';
        }
        if ($prop === 'display' && in_array($val, ['flex', 'grid', 'block'], true)) {
            return $val . '-layout';
        }
        if (in_array($prop, ['align-items', 'justify-content'], true) && $val === 'center') {
            return 'generic-flex-alignment';
        }
        if ($prop === 'position' && $val === 'relative') {
            return 'relative-positioning';
        }
        if ($prop === 'z-index') {
            return 'layering';
        }
        if ($prop === 'transform') {
            return str_contains($val, 'translate') ? 'transform-positioning' : 'transform-unknown';
        }
        if ($prop === 'opacity') {
            return str_contains($sel, 'disabled') ? 'disabled-state-visibility' : 'opacity-unknown';
        }
        if (in_array($prop, ['grid-template-columns', 'grid-template-rows', 'grid-template', 'flex-direction', 'flex-wrap', 'gap'], true)) {
            return 'layout-composition';
        }
        if (in_array($prop, ['min-width', 'max-width', 'min-height', 'max-height', 'box-sizing', 'aspect-ratio'], true)) {
            return 'containment-sizing';
        }
        return 'structural-baseline';
    }

    private static function isShellInventoryStructuralEvidence(string $property, string $selector, string $value): bool
    {
        if (!self::isStructuralProperty($property)) {
            return false;
        }
        if (self::isShellInventoryEffectLike($property, $value, $selector)) {
            return false;
        }
        return true;
    }

    private static function isShellInventoryEffectLike(string $property, string $value, string $selector): bool
    {
        $prop = strtolower($property);
        $val = strtolower($value);
        $sel = strtolower($selector);
        if (self::isEffectProperty($prop)) {
            return true;
        }
        if (str_contains($val, 'blur(') || str_contains($val, 'drop-shadow(')) {
            return true;
        }
        if (in_array($prop, ['transition', 'animation', 'animation-name', 'animation-duration', 'transition-duration'], true)) {
            return true;
        }
        if ($prop === 'transform') {
            if (str_contains($sel, ':hover') || str_contains($sel, ':active') || str_contains($sel, '.hover')) {
                return true;
            }
            if (preg_match('/translate[xy]?\(\s*-?([0-9.]+)(px|rem|em)\s*\)/i', $val, $m) === 1 && (float)$m[1] <= 6.0) {
                return true;
            }
        }
        if ($prop === 'opacity' && !str_contains($sel, 'disabled') && !str_contains($sel, '[disabled]')) {
            return true;
        }
        return false;
    }

    private static function isFocusVisibilityRisk(string $property, string $value, string $selector): bool
    {
        $prop = strtolower($property);
        $val = strtolower(trim($value));
        $sel = strtolower($selector);
        return str_contains($sel, 'focus')
            && in_array($prop, ['outline', 'box-shadow'], true)
            && ($val === 'none' || $val === '0' || $val === '0 none' || $val === 'none 0');
    }

    private static function shellCandidateType(array $row, bool $isShellSelector, bool $isTheme, bool $isEffect, bool $isUnsupported, bool $isPrint, bool $hasFocusRisk): string
    {
        $property = strtolower((string)($row['property'] ?? $row['token'] ?? ''));
        $selector = strtolower((string)($row['selector'] ?? ''));
        $atRule = strtolower((string)($row['at_rule_context'] ?? ''));
        if ($isUnsupported || $isTheme || $isEffect) {
            return 'not_a_shell_candidate';
        }
        if ($isPrint) {
            return 'print_structure';
        }
        if ($hasFocusRisk || str_contains($selector, 'focus')) {
            return 'accessibility_focus';
        }
        if ($isShellSelector || preg_match('/\b(app-shell|layout-|topbar|sidebar|shell|nav|main-region)\b/i', $selector) === 1) {
            return 'shell_navigation';
        }
        if (str_contains($atRule, '@media') || preg_match('/\b(container|responsive|breakpoint)\b/i', $selector) === 1) {
            return 'responsive_shell';
        }
        if (str_contains($property, 'overflow')) {
            return 'overflow_containment';
        }
        if (preg_match('/\b(input|select|textarea|button|form-control|field)\b/i', $selector) === 1) {
            return 'form_control_baseline';
        }
        if (preg_match('/\b(table|grid-table|data-table)\b/i', $selector) === 1 || in_array($property, ['table-layout', 'border-collapse', 'border-spacing'], true)) {
            return 'table_baseline';
        }
        if (preg_match('/\b(dialog|modal|drawer|popover|shell-overlay)\b/i', $selector) === 1) {
            return 'dialog_or_overlay_structure';
        }
        if (preg_match('/\b(login|setup|bootstrap|auth|admin)\b/i', (string)($row['file'] ?? '') . ' ' . $selector) === 1) {
            return 'bootstrap_fallback';
        }
        if (in_array($property, ['display', 'grid', 'grid-template', 'grid-template-columns', 'grid-template-rows', 'flex', 'flex-direction', 'flex-wrap', 'gap', 'box-sizing'], true)) {
            return 'layout_primitive';
        }
        return 'structural_selector_review';
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function shellInventoryPopulation(array $row, string $candidateType, bool $isShellSelector, int $distinctOwnerCount, bool $isTheme, bool $isEffect, bool $isUnsupported, bool $isPrint, bool $hasFocusRisk): array
    {
        if ($isUnsupported) {
            return ['excluded', 'Dynamic or unsupported source is excluded from active Shell inventory decisions.'];
        }
        if ($isTheme || $isEffect || $isPrint) {
            return ['evidence_only', 'Relevant architecture evidence is retained, but this is not a normal Shell/Foundation candidate.'];
        }
        $role = self::selectorRoleFamily((string)($row['selector'] ?? ''), (string)($row['file'] ?? ''));
        $hasShellRoleEvidence = $role !== 'owner-component';
        if ($hasFocusRisk || $isShellSelector || ($distinctOwnerCount >= 2 && $hasShellRoleEvidence) || ($hasShellRoleEvidence && in_array($candidateType, ['shell_navigation', 'responsive_shell', 'overflow_containment', 'form_control_baseline', 'table_baseline', 'accessibility_focus', 'dialog_or_overlay_structure', 'bootstrap_fallback'], true))) {
            return ['eligible_candidate', 'Selector, context, or cross-owner evidence supports Shell/Foundation disposition review.'];
        }
        return ['evidence_only', 'Owner-local structural evidence is cataloged but not promoted into the active candidate queue.'];
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function shellInventoryCriticality(array $row, string $candidateType, string $population, bool $isShellSelector): array
    {
        $text = strtolower((string)($row['file'] ?? '') . ' ' . (string)($row['selector'] ?? ''));
        if ($population !== 'eligible_candidate') {
            return ['normal', 'Not an eligible active Shell/Foundation candidate.'];
        }
        if (preg_match('/\b(login|setup|bootstrap|auth)\b/i', $text) === 1) {
            return ['critical', 'Setup/login/bootstrap rendering path.'];
        }
        if ($isShellSelector || preg_match('/\b(app-shell|layout-main|layout-sidebar|topbar|sidebar-nav)\b/i', $text) === 1) {
            return ['critical', 'Known Shell selector contract controls shared navigation or app containment.'];
        }
        if (preg_match('/\b(modal|dialog|drawer|popover|shell-overlay)\b/i', $text) === 1) {
            return ['critical', 'Modal/dialog containment affects essential interaction flow.'];
        }
        if ($candidateType === 'accessibility_focus') {
            return ['critical', 'Essential focus visibility affects keyboard accessibility.'];
        }
        if (in_array($candidateType, ['table_baseline', 'form_control_baseline', 'responsive_shell', 'overflow_containment', 'accessibility_focus'], true)) {
            return ['high', 'Shared baseline behavior affects repeated layout or control mechanics.'];
        }
        if ($candidateType === 'not_a_shell_candidate') {
            return ['unknown', 'Not enough Shell/Foundation rendering-path evidence.'];
        }
        return ['normal', 'Owner-local structural behavior without critical shared rendering evidence.'];
    }

    private static function shellInventoryConfidence(array $row, string $candidateType, bool $isShellSelector, int $distinctOwnerCount, bool $hasFocusRisk, bool $isUnsupported): string
    {
        if ($isUnsupported) {
            return 'none';
        }
        $parse = (string)($row['parse_confidence'] ?? 'high');
        if ($parse === 'low') {
            return 'low';
        }
        if ($hasFocusRisk) {
            return 'medium';
        }
        if ($candidateType === 'not_a_shell_candidate') {
            return 'high';
        }
        if ($isShellSelector || $distinctOwnerCount >= 2) {
            return $parse === 'partial' ? 'medium' : 'high';
        }
        if ($parse === 'partial') {
            return 'low';
        }
        return 'medium';
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function shellInventoryDisposition(
        array $row,
        string $candidateType,
        string $population,
        string $confidence,
        string $criticality,
        int $crossOwnerUsage,
        int $distinctOwnerCount,
        bool $isShellSelector,
        bool $isTheme,
        bool $isEffect,
        bool $isUnsupported,
        bool $isPrint,
        bool $hasFocusRisk
    ): array {
        if ($isUnsupported) {
            return ['excluded_or_unsupported', 'Dynamic or unsupported style source; keep out of Shell promotion planning.', 'N/A'];
        }
        if ($isEffect) {
            return ['future_special_effect_handoff', 'Effect-like declaration belongs in the future Special Effects review path, not Shell/Foundation.', 'Special Effects Pipeline'];
        }
        if ($isTheme) {
            return ['remain_theme_related', 'Theme-semantic value or theme-source declaration; not a Shell/Foundation promotion candidate.', 'Theme'];
        }
        if ($isPrint) {
            return ['classification_review', 'Print/PDF structural rule needs print-contract review and must not become a normal Shell primitive automatically.', 'Print/PDF Review'];
        }
        if ($hasFocusRisk) {
            return ['classification_review', 'Focus styling suppresses visible outline; review accessible focus replacement before catalog disposition.', 'Shell/Foundation'];
        }
        if ($population === 'evidence_only') {
            return ['remain_owner_local_shell_governed', 'Owner-local structural evidence is retained for Shell/Foundation governance context but is not an active review or promotion candidate.', (string)($row['source_owner'] ?? self::ownerKeyFromRelativeFile((string)($row['file'] ?? '')))];
        }
        $stableReusable = !self::selectorLooksBusinessSpecific((string)($row['selector'] ?? ''));
        $hasFallbackRole = in_array($candidateType, ['bootstrap_fallback', 'shell_navigation', 'layout_primitive', 'overflow_containment', 'form_control_baseline', 'table_baseline', 'dialog_or_overlay_structure', 'accessibility_focus'], true);
        $canPromote = $confidence === 'high'
            && $population === 'eligible_candidate'
            && ($distinctOwnerCount >= 2 || $isShellSelector)
            && $stableReusable
            && $hasFallbackRole
            && !in_array($criticality, ['unknown'], true);
        if ($canPromote) {
            return ['candidate_shared_shell_primitive', 'High-confidence reusable structural behavior appears cross-owner or matches a shared Shell selector contract.', 'Shell/Foundation'];
        }
        if ($confidence === 'low' || $candidateType === 'structural_selector_review') {
            return ['classification_review', 'Structural selector needs manual architecture review before a Shell/Foundation disposition is trusted.', 'Shell/Foundation'];
        }
        unset($crossOwnerUsage);
        return ['remain_owner_local_shell_governed', 'Owner-local structural CSS needs Shell/Foundation governance conventions but no shared primitive promotion evidence.', (string)($row['source_owner'] ?? self::ownerKeyFromRelativeFile((string)($row['file'] ?? '')))];
    }

    private static function shellInventoryReviewReasonCode(array $row, string $disposition, bool $isUnsupported, bool $isPrint, bool $hasFocusRisk, bool $isEffect): string
    {
        if ($disposition !== 'classification_review') {
            return '';
        }
        $property = strtolower((string)($row['property'] ?? $row['token'] ?? ''));
        $selector = strtolower((string)($row['selector'] ?? ''));
        if ($hasFocusRisk) {
            return 'accessibility_focus_risk';
        }
        if ($isPrint) {
            return 'print_context';
        }
        if ($isUnsupported || (string)($row['parse_confidence'] ?? 'high') === 'partial') {
            return 'dynamic_or_template_context';
        }
        if ($isEffect) {
            return 'unknown_transform_intent';
        }
        if ($property === 'z-index') {
            return 'unknown_overlay_or_z_index_role';
        }
        if ($property === 'transform') {
            return 'unknown_transform_intent';
        }
        if ($property === 'opacity') {
            return 'unknown_opacity_intent';
        }
        if (str_contains($selector, 'theme') || str_contains($selector, 'dark')) {
            return 'mixed_theme_and_structure';
        }
        return $selector === '' ? 'missing_context' : 'ambiguous_selector_role';
    }

    private static function selectorLooksBusinessSpecific(string $selector): bool
    {
        return preg_match('/\b(product|coverage|order|invoice|payroll|employee|customer|machine|assembly|material|dispatch|report|kpi|chart)\b/i', $selector) === 1;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,int>
     */
    private static function summarizeShellInventory(array $items, int $declarationsScanned, int $outsideInventoryCount): array
    {
        $summary = self::emptyShellFoundationInventory()['summary'];
        $summary['total'] = count($items);
        $summary['declarations_scanned'] = $declarationsScanned;
        $summary['shell_relevant_evidence'] = count($items);
        $summary['outside_inventory'] = $outsideInventoryCount;
        foreach ($items as $item) {
            $crit = (string)($item['criticality'] ?? 'unknown');
            if (array_key_exists($crit, $summary)) {
                $summary[$crit]++;
            }
            $disp = (string)($item['recommended_disposition'] ?? '');
            if (array_key_exists($disp, $summary)) {
                $summary[$disp]++;
            }
            $population = (string)($item['inventory_population'] ?? '');
            if ($population === 'eligible_candidate') {
                $summary['eligible_candidates']++;
            } elseif ($population === 'evidence_only') {
                $summary['evidence_only']++;
            } elseif ($population === 'excluded') {
                $summary['excluded']++;
            }
        }
        return $summary;
    }

    /**
     * Normalize raw style domain into a governance display value.
     *
     * The raw classifier labels (theme, owner_surface, structural, etc.) are
     * not user-facing. Map to a four-value enum:
     *   - theme_related   (was: theme)
     *   - shell_foundation (unchanged)
     *   - special_effect   (unchanged)
     *   - not_applicable   (was: owner_surface, structural, print_pdf, unsupported)
     */
    private static function normalizeGovernanceDomain(string $domain): string
    {
        return match ($domain) {
            self::DOMAIN_THEME => 'theme_related',
            self::DOMAIN_SHELL_FOUNDATION => 'shell_foundation',
            self::DOMAIN_SPECIAL_EFFECT => 'special_effect',
            default => 'not_applicable',
        };
    }

    private static function ownerKeyFromRelativeFile(string $file): string
    {
        $parts = explode('/', trim($file, '/'));
        if (($parts[0] ?? '') !== 'apps' || ($parts[1] ?? '') === '') {
            return 'Owner';
        }
        $app = (string)$parts[1];
        $modulesIndex = array_search('modules', $parts, true);
        if (is_int($modulesIndex) && isset($parts[$modulesIndex + 1]) && $parts[$modulesIndex + 1] !== '') {
            return $app . '/' . (string)$parts[$modulesIndex + 1];
        }
        return $app;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function scanCssLikeContents(string $contents, string $file, bool $isThemeSourceFile, string $sourceType = 'css', int $fileContentOffset = 0): array
    {
        return self::processRuleBlocks($contents, $file, $isThemeSourceFile, '', '', $sourceType, $fileContentOffset);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function scanPhpStyleContents(string $contents, string $file, bool $isThemeSourceFile): array
    {
        $tokens = [];

        // Static CSS in <style> blocks
        if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $contents, $styleBlocks, PREG_OFFSET_CAPTURE)) {
            foreach ($styleBlocks[1] as $i => $cssMatch) {
                $css = (string)$cssMatch[0];
                $tagStart = (int)$styleBlocks[0][$i][1];
                // Find end of opening <style ...> tag
                $styleTagEnd = strpos($contents, '>', $tagStart) + 1;
                $cssOffset = $styleTagEnd;
                // Check for PHP inside the style block
                $phpInside = strpos($css, '<?php') !== false;
                $parseConf = $phpInside ? 'partial' : 'high';
                $cssTokens = self::scanCssLikeContents($css, $file, $isThemeSourceFile, 'php_style_block', $cssOffset);
                // Mark partial confidence if PHP is present
                if ($phpInside) {
                    foreach ($cssTokens as &$ct) {
                        $ct['parse_confidence'] = 'partial';
                    }
                    unset($ct);
                }
                array_push($tokens, ...$cssTokens);
            }
        }

        // Inline style="" attributes
        if (preg_match_all('/\sstyle\s*=\s*(["\'])(.*?)\1/is', $contents, $inlineStyles, PREG_OFFSET_CAPTURE)) {
            foreach ($inlineStyles[2] as $i => $styleMatch) {
                $body = html_entity_decode((string)$styleMatch[0], ENT_QUOTES | ENT_HTML5);
                $valueStart = (int)$styleMatch[1]; // offset of value start within the match
                $matchOffset = (int)$inlineStyles[0][$i][1];
                $absOffset = $matchOffset + $valueStart + 1; // +1 for the opening quote

                $tokens[] = self::buildInlineTokenRow(
                    $body,
                    $file,
                    $isThemeSourceFile,
                    $absOffset,
                    $contents
                );
            }
        }

        return $tokens;
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildInlineTokenRow(string $body, string $file, bool $isThemeSourceFile, int $absOffset, string $fileContents): array
    {
        $relativeFile = self::relativePath($file);
        $line = self::lineNumberAtOffset($fileContents, $absOffset);
        $hasVar = str_contains($body, 'var(');

        // Parse simple inline declarations (semicolon-separated)
        $decls = explode(';', $body);
        $firstProp = '';
        $firstValue = '';
        $tokenReferences = [];

        foreach ($decls as $decl) {
            $d = trim($decl);
            if ($d === '') {
                continue;
            }
            $colonPos = strpos($d, ':');
            if ($colonPos === false) {
                continue;
            }
            $prop = trim(substr($d, 0, $colonPos));
            $val = trim(substr($d, $colonPos + 1));
            if ($firstProp === '') {
                $firstProp = $prop;
                $firstValue = $val;
            }
            // Collect token references across all declarations
            if (str_contains($val, 'var(')) {
                $tokenReferences = array_merge($tokenReferences, self::extractTokenReferences($val));
            }
            // Only process the first visual declaration for the main token row
            if ($firstProp !== '' && $firstValue !== '') {
                break;
            }
        }

        if ($firstProp === '' && $firstValue === '') {
            return [];
        }

        $isCustomProp = str_starts_with($firstProp, '--');
        $normalized = self::normalizeValue($firstValue);

        if ($isCustomProp) {
            $classification = self::classifyValue($firstValue);
            $kind = 'declaration';
            $tokenName = $firstProp;
            $isThemeAware = $isThemeSourceFile || $classification['type'] === 'alias';
        } elseif ($hasVar) {
            $kind = 'usage';
            $tokenName = $tokenReferences !== [] ? $tokenReferences[0] : $firstProp;
            $isThemeAware = true;
            $classification = ['type' => 'alias', 'is_literal' => false, 'canonical' => $normalized];
        } else {
            $kind = 'literal_declaration';
            $tokenName = $firstProp;
            $isThemeAware = $isThemeSourceFile;
            $classification = self::classifyLiteralDeclarationValue($firstValue);
        }
        $semanticTokenMisuse = $hasVar ? self::detectSemanticTokenMisuse($firstProp, $tokenReferences) : [];
        if ($semanticTokenMisuse !== []) {
            $isThemeAware = false;
        }

        // Determine scan category for inline row
        $inlineScanCategory = 'visual_literal';
        $inlineComplianceScope = 'in_scope';
        $inlineRepairEligibility = 'not_repairable';
        $inlineIsEffect = false;
        $inlineIsDynamic = false;
        if ($isCustomProp) {
            $inlineScanCategory = 'token_definition';
            $inlineComplianceScope = 'evidence_only';
            $inlineRepairEligibility = !empty($classification['is_literal']) ? 'needs_semantic_decision' : 'not_repairable';
        } elseif ($hasVar) {
            $inlineScanCategory = 'token_consumer';
            $inlineRepairEligibility = $isThemeAware ? 'not_repairable' : 'repair_ready';
        } elseif (self::isStructuralProperty($firstProp)) {
            $inlineScanCategory = 'structural_out_of_scope';
            $inlineComplianceScope = 'evidence_only';
        } else {
            $inlineRepairEligibility = !empty($classification['is_literal']) ? 'needs_semantic_decision' : 'not_repairable';
        }
        $inlineIsEffect = false;
        if ($inlineScanCategory === 'visual_literal') {
            $inlineIsEffect = self::isEffectProperty($firstProp);
        }
        $inlineIsDynamic = str_contains($firstValue, '<?');
        $inlineIsPrint = self::isPrintRelated($relativeFile);

        // Override scan_category for dynamic, effect, and print tokens
        // Cascade: dynamic_unsupported > effect_candidate > print_pdf > original
        if ($inlineIsDynamic) {
            $inlineScanCategory = 'dynamic_unsupported';
            $inlineComplianceScope = 'evidence_only';
        } elseif ($semanticTokenMisuse !== []) {
            $inlineScanCategory = 'semantic_token_misuse';
            $inlineRepairEligibility = 'needs_semantic_decision';
        } elseif ($inlineIsEffect) {
            $inlineScanCategory = 'effect_candidate';
        } elseif ($inlineIsPrint) {
            $inlineScanCategory = 'print_pdf';
            $inlineComplianceScope = 'evidence_only';
        }

        return [
            'token' => $tokenName,
            'value' => $normalized,
            'kind' => $kind,
            'file' => $relativeFile,
            'selector' => '[inline style line ' . $line . ']',
            'theme_aware' => $isThemeAware,
            'value_classification' => $classification,
            'in_theme_source' => $isThemeSourceFile,
            'property' => $firstProp,
            'raw_value' => $firstValue,
            'normalized_value' => $normalized,
            'token_references' => $tokenReferences,
            'value_construct' => self::classifyValueConstruct($firstValue),
            'source_type' => 'php_inline_style',
            'line_start' => $line,
            'line_end' => $line,
            'at_rule_context' => '',
            'parse_confidence' => 'high',
            'scan_category' => $inlineScanCategory,
            'compliance_scope' => $inlineComplianceScope,
            'repair_eligibility' => $inlineRepairEligibility,
            'is_effect_candidate' => $inlineIsEffect,
            'is_dynamic' => $inlineIsDynamic,
            'is_print_style' => $inlineIsPrint,
            'semantic_token_misuse' => $semanticTokenMisuse,
            'semantic_token_misuse_reason' => (string)($semanticTokenMisuse['reason'] ?? ''),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function scanDeclarationBody(string $body, string $file, string $selector, bool $selectorIsThemeAware, bool $isThemeSourceFile, string $atRuleContext = '', int $bodyOffset = 0, string $sourceType = 'css'): array
    {
        $tokens = [];
        $relativeFile = self::relativePath($file);
        $declarations = self::parseDeclarations($body, $bodyOffset);

        foreach ($declarations as $decl) {
            // Split on first colon to get property and value
            $colonPos = strpos($decl['text'], ':');
            if ($colonPos === false) {
                continue;
            }
            $property = trim(substr($decl['text'], 0, $colonPos));
            $rawValue = trim(substr($decl['text'], $colonPos + 1));
            if ($property === '' || $rawValue === '') {
                continue;
            }

            $isCustomProp = str_starts_with($property, '--');
            $hasVar = str_contains($rawValue, 'var(');

            // Accept both visual and structural properties for classification
            if (!$isCustomProp && !self::isStyleProperty($property)) {
                continue;
            }

            $tokenReferences = $hasVar ? self::extractTokenReferences($rawValue) : [];
            $valueConstruct = self::classifyValueConstruct($rawValue);
            // Override value construct based on property for patterns that can't be
            // reliably detected from value alone (e.g. box-shadow values contain no "shadow" keyword)
            $lowerProp = strtolower($property);
            if ($valueConstruct === 'unsupported' || $valueConstruct === 'keyword') {
                if ($lowerProp === 'box-shadow' || $lowerProp === 'text-shadow') {
                    $valueConstruct = 'shadow';
                }
            }
            $lineStart = self::lineNumberAtOffsetForContent($body, $bodyOffset, $decl['offset']);
            $lineEnd = $lineStart; // approximate for single-line declarations
            $hasPhpInterpolation = str_contains($rawValue, '<?');
            $complianceScope = 'in_scope';

            if ($isCustomProp) {
                $classification = self::classifyValue($rawValue);
                $kind = 'declaration';
                $tokenName = $property;
                $isThemeAware = $selectorIsThemeAware || $classification['type'] === 'alias';
            } elseif ($hasVar) {
                $kind = 'usage';
                $tokenName = $tokenReferences !== [] ? $tokenReferences[0] : $property;
                $isThemeAware = true;
                $classification = ['type' => 'alias', 'is_literal' => false, 'canonical' => self::normalizeValue($rawValue)];
            } else {
                $kind = 'literal_declaration';
                $tokenName = $property;
                $isThemeAware = $selectorIsThemeAware;
                $classification = self::classifyLiteralDeclarationValue($rawValue);
            }
            $semanticTokenMisuse = $hasVar ? self::detectSemanticTokenMisuse($property, $tokenReferences) : [];
            if ($semanticTokenMisuse !== []) {
                $isThemeAware = false;
            }

            // Determine scan category
            $scanCategory = 'visual_literal';
            if ($isCustomProp) {
                $scanCategory = 'token_definition';
            } elseif ($hasVar) {
                $scanCategory = 'token_consumer';
            } elseif (self::isStructuralProperty($property)) {
                $scanCategory = 'structural_out_of_scope';
            }

            // Override scan_category for dynamic, effect, and print tokens
            // Cascade: dynamic_unsupported > effect_candidate > print_pdf > original
            if ($hasPhpInterpolation) {
                $scanCategory = 'dynamic_unsupported';
                $complianceScope = 'evidence_only';
            } elseif ($semanticTokenMisuse !== []) {
                $scanCategory = 'semantic_token_misuse';
            } elseif (self::isEffectProperty($property)) {
                $scanCategory = 'effect_candidate';
            } elseif (self::isPrintRelated($relativeFile)) {
                $scanCategory = 'print_pdf';
                $complianceScope = 'evidence_only';
            }

            // Determine compliance scope
            if ($scanCategory === 'structural_out_of_scope' || $scanCategory === 'token_definition') {
                $complianceScope = 'evidence_only';
            }

            // Determine repair eligibility
            $repairEligibility = 'not_repairable';
            $isLiteral = !empty($classification['is_literal']);
            if ($scanCategory === 'visual_literal' && $isLiteral) {
                $repairEligibility = 'needs_semantic_decision';
            } elseif ($scanCategory === 'semantic_token_misuse') {
                $repairEligibility = 'needs_semantic_decision';
            } elseif ($scanCategory === 'token_consumer' && !$isThemeAware) {
                $repairEligibility = 'repair_ready';
            } elseif ($scanCategory === 'token_definition' && $isLiteral) {
                $repairEligibility = 'needs_semantic_decision';
            }

            $normalized = self::normalizeValue($rawValue);

            $tokens[] = [
                // Backward-compat fields
                'token' => $tokenName,
                'value' => $normalized,
                'kind' => $kind,
                'file' => $relativeFile,
                'selector' => $selector,
                'theme_aware' => $isThemeAware,
                'value_classification' => $classification,
                'in_theme_source' => $isThemeSourceFile,
                // New evidence fields
                'property' => $property,
                'raw_value' => $rawValue,
                'normalized_value' => $normalized,
                'token_references' => $tokenReferences,
                'value_construct' => $valueConstruct,
                'source_type' => $sourceType,
                'line_start' => $lineStart,
                'line_end' => $lineEnd,
                'at_rule_context' => $atRuleContext,
                'parse_confidence' => 'high',
                // Classification fields
                'scan_category' => $scanCategory,
                'compliance_scope' => $complianceScope,
                'repair_eligibility' => $repairEligibility,
                'is_effect_candidate' => self::isEffectProperty($property),
                'is_dynamic' => $hasPhpInterpolation,
                'is_print_style' => self::isPrintRelated($relativeFile),
                'semantic_token_misuse' => $semanticTokenMisuse,
                'semantic_token_misuse_reason' => (string)($semanticTokenMisuse['reason'] ?? ''),
            ];
        }

        return $tokens;
    }

    /**
     * @return array<int,array{selector:string,body:string,selector_offset:int,body_offset:int,closing_offset:int}>
     */
    private static function splitBlocksRaw(string $css): array
    {
        $blocks = [];
        $len = strlen($css);
        $depth = 0;
        $selectorStart = 0;
        $bodyStart = -1;
        $currentSelector = '';

        $i = 0;
        while ($i < $len) {
            $ch = $css[$i];

            // Skip strings
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $i++;
                while ($i < $len && $css[$i] !== $quote) {
                    if ($css[$i] === '\\') {
                        $i++;
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            // Skip CSS comments; advance selectorStart past comment when between blocks
            if ($i < $len - 1 && $ch === '/' && $css[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && !($css[$i] === '*' && $css[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                if ($depth === 0) {
                    $selectorStart = $i;
                }
                continue;
            }

            if ($ch === '{') {
                if ($depth === 0) {
                    $currentSelector = trim(substr($css, $selectorStart, $i - $selectorStart));
                    $bodyStart = $i + 1;
                }
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0 && $bodyStart >= 0) {
                    $body = substr($css, $bodyStart, $i - $bodyStart);
                    $blocks[] = [
                        'selector' => $currentSelector,
                        'body' => $body,
                        'selector_offset' => $selectorStart,
                        'body_offset' => $bodyStart,
                        'closing_offset' => $i,
                    ];
                    $selectorStart = $i + 1;
                    $bodyStart = -1;
                    $currentSelector = '';
                }
            }

            $i++;
        }

        return $blocks;
    }

    private static function selectorLooksThemeAware(string $selector): bool
    {
        if ($selector === '') {
            return false;
        }
        foreach (self::THEME_SELECTOR_PATTERNS as $pattern) {
            if (preg_match($pattern, $selector) === 1) {
                return true;
            }
        }
        return false;
    }

    private static function isThemeSourceFile(string $path): bool
    {
        $themeRoot = realpath(APP_ROOT . '/resources/themes');
        if (!is_string($themeRoot)) {
            return false;
        }
        return str_starts_with($path, $themeRoot . DIRECTORY_SEPARATOR)
            || str_starts_with($path, $themeRoot . '/');
    }

    /**
     * Returns true when the given file path belongs to a Studio tool-internal
     * CSS source (tool assets, view templates, or global Studio chrome).
     * Such paths contain intentionally hardcoded presentation values that should
     * not produce classification_review findings.
     */
    private static function isStudioToolCssPath(string $path): bool
    {
        return str_contains($path, '/apps/Studio/Tools/');
    }

    /**
     * @return array{type:string,is_literal:bool,canonical:string}
     */
    private static function classifyValue(string $value): array
    {
        $v = strtolower(trim($value));
        if ($v === '') {
            return ['type' => 'empty', 'is_literal' => false, 'canonical' => ''];
        }
        // var(...) — alias, not a literal.
        if (str_starts_with($v, 'var(')) {
            return ['type' => 'alias', 'is_literal' => false, 'canonical' => $v];
        }
        // Hex color.
        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v) === 1) {
            return ['type' => 'color_hex', 'is_literal' => true, 'canonical' => $v];
        }
        // rgb/hsl color.
        if (preg_match('/^(rgb|rgba|hsl|hsla)\s*\(/i', $v) === 1) {
            return ['type' => 'color_func', 'is_literal' => true, 'canonical' => $v];
        }
        // Length.
        if (preg_match('/^-?\d*\.?\d+(px|rem|em|%|vh|vw|pt|ch)$/i', $v) === 1) {
            return ['type' => 'length', 'is_literal' => true, 'canonical' => $v];
        }
        // Unitless number.
        if (preg_match('/^-?\d*\.?\d+$/', $v) === 1) {
            return ['type' => 'number', 'is_literal' => true, 'canonical' => $v];
        }
        return ['type' => 'other', 'is_literal' => false, 'canonical' => $v];
    }

    /**
     * @return array{type:string,is_literal:bool,canonical:string}
     */
    private static function classifyLiteralDeclarationValue(string $value): array
    {
        $v = strtolower(trim($value));
        if (preg_match('/#([0-9a-f]{8}|[0-9a-f]{6}|[0-9a-f]{3,4})(?!\s*[0-9a-f])/i', $v, $m) === 1) {
            return ['type' => 'color_hex', 'is_literal' => true, 'canonical' => '#' . strtolower($m[1])];
        }
        if (preg_match('/(rgba?|hsla?)\s*\(([^;{}]+?)\)/i', $v, $m) === 1) {
            return ['type' => str_starts_with(strtolower($m[1]), 'rgb') ? 'color_func' : 'color_func', 'is_literal' => true, 'canonical' => strtolower($m[1]) . '(' . trim($m[2]) . ')'];
        }
        return ['type' => 'other', 'is_literal' => false, 'canonical' => $v];
    }

    /**
     * Central operational guard: returns true if a token row should be included
     * in production scan aggregators (summary, repair candidates, boundary
     * findings, affected files). Excludes test fixture data and invalid rows.
     *
     * @param array<string,mixed> $row
     */
    private static function isOperationalFinding(array $row): bool
    {
        $file = (string)($row['file'] ?? '');
        if ($file === '') {
            return false;
        }
        // Exclude test fixture files — they are for probe validation only.
        // Calibration fixtures live under Tests/fixtures/ in any owner root.
        if (str_contains($file, '/Tests/fixtures/')) {
            return false;
        }
        return true;
    }

    /**
     * @param array<int,array<string,mixed>> $tokens
     * @return array<int,array<string,mixed>>
     */
    private static function buildRepairCandidates(array $tokens): array
    {
        $candidates = [];
        $seen = [];

        foreach ($tokens as $row) {
            $scanCategory = (string)($row['scan_category'] ?? '');
            // Exclude structural and token-definition from repair proposals
            if ($scanCategory === 'structural_out_of_scope' || $scanCategory === 'token_definition') {
                continue;
            }
            if (!empty($row['theme_aware'])) {
                continue;
            }
            if ($scanCategory === 'semantic_token_misuse') {
                $misuse = isset($row['semantic_token_misuse']) && is_array($row['semantic_token_misuse'])
                    ? $row['semantic_token_misuse']
                    : [];
                $misusedToken = (string)($misuse['token'] ?? $row['token'] ?? '');
                $propertyRole = (string)($misuse['property_role'] ?? '');
                $tokenRole = (string)($misuse['token_role'] ?? '');
                $key = 'semantic_token_misuse|' . $misusedToken . '|' . (string)($row['property'] ?? '') . '|' . (string)($row['raw_value'] ?? '');
                if (isset($seen[$key])) {
                    $seen[$key]['occurrences']++;
                    $seen[$key]['files'][$row['file']] = true;
                    continue;
                }

                $replacementHint = self::semanticTokenCorrectionHint($propertyRole, $tokenRole, $misusedToken);
                $entry = [
                    'token' => $misusedToken !== '' ? $misusedToken : (string)($row['token'] ?? ''),
                    'current_value' => (string)($row['raw_value'] ?? $row['value'] ?? ''),
                    'value_type' => 'semantic_token_misuse',
                    'proposed_semantic' => $replacementHint,
                    'proposed_light' => 'Use a role-matched token',
                    'proposed_dark' => 'Use the same role-matched token',
                    'proposed_default' => $replacementHint,
                    'repair_action' => 'semantic_token_correction',
                    'confidence' => 'high',
                    'rationale' => (string)($row['semantic_token_misuse_reason'] ?? 'Theme token role does not match the CSS property role.'),
                    'occurrences' => 1,
                    'files' => [$row['file'] => true],
                ];
                $seen[$key] = &$entry;
                $candidates[] = &$entry;
                unset($entry);
                continue;
            }
            $cls = $row['value_classification'];
            if (empty($cls['is_literal'])) {
                continue;
            }
            // Exclude dynamic, effect, and print tokens from repair proposals
            if (!empty($row['is_dynamic']) || !empty($row['is_effect_candidate']) || !empty($row['is_print_style'])) {
                continue;
            }
            $key = $row['kind'] . '|' . $row['token'] . '|' . $cls['canonical'];
            if (isset($seen[$key])) {
                $seen[$key]['occurrences']++;
                $seen[$key]['files'][$row['file']] = true;
                continue;
            }

            if ($scanCategory === 'visual_literal') {
                // For visual literals, do not generate fake token names
                $entry = [
                    'token' => $row['token'],
                    'current_value' => $cls['canonical'],
                    'value_type' => $cls['type'],
                    'proposed_semantic' => 'Needs semantic token decision',
                    'proposed_light' => $cls['canonical'],
                    'proposed_dark' => $cls['canonical'],
                    'proposed_default' => $cls['canonical'],
                    'repair_action' => 'manual_review',
                    'confidence' => 'low',
                    'rationale' => 'Literal visual value without approved token mapping. A semantic design decision is required before a token can be assigned.',
                    'occurrences' => 1,
                    'files' => [$row['file'] => true],
                ];
            } else {
                $proposal = self::proposeSemantic($row['token'], $cls, (string)($row['kind'] ?? 'declaration'));
                $entry = [
                    'token' => $row['token'],
                    'current_value' => $cls['canonical'],
                    'value_type' => $cls['type'],
                    'proposed_semantic' => $proposal['semantic_name'],
                    'proposed_light' => $proposal['light'],
                    'proposed_dark' => $proposal['dark'],
                    'proposed_default' => $proposal['default'],
                    'repair_action' => $proposal['repair_action'],
                    'confidence' => $proposal['confidence'],
                    'rationale' => $proposal['rationale'],
                    'occurrences' => 1,
                    'files' => [$row['file'] => true],
                ];
            }
            $seen[$key] = &$entry;
            $candidates[] = &$entry;
            unset($entry);
        }

        // Finalize files map -> sorted list.
        foreach ($candidates as &$c) {
            $files = array_keys($c['files']);
            sort($files, SORT_STRING);
            $c['files'] = $files;
        }
        unset($c);

        // Sort by confidence desc, then token name asc.
        $order = ['high' => 3, 'medium' => 2, 'low' => 1];
        usort($candidates, static function (array $a, array $b) use ($order): int {
            $ca = $order[$a['confidence']] ?? 0;
            $cb = $order[$b['confidence']] ?? 0;
            if ($ca !== $cb) {
                return $cb - $ca;
            }
            return strcmp($a['token'], $b['token']);
        });

        return $candidates;
    }

    /**
     * Strict read-only Theme-Aware Repair proposal contract.
     *
     * Only semantic token role corrections that are same-domain, high confidence,
     * and backed by an existing canonical token receive executable replacement
     * values. Literal colors, effects, print/PDF, dynamic constructs, and
     * accessibility-risk findings are queued for review or future tools.
     *
     * @param array<int,array<string,mixed>> $tokens
     * @return array<string,mixed>
     */
    public static function buildThemeAwareRepairProposals(array $tokens): array
    {
        $items = [];
        $ready = [];
        $manual = [];
        $review = [];
        $handoff = [];
        $excluded = [];
        $filesWithDeterministic = [];
        $filesWithManual = [];

        foreach ($tokens as $row) {
            if (!is_array($row) || !self::isOperationalFinding($row)) {
                continue;
            }

            $proposal = self::themeRepairProposalRecord($row);
            if ($proposal === null) {
                continue;
            }

            $items[] = $proposal;
            $class = (string)$proposal['proposal_class'];
            if ($class === 'deterministic_theme_value_fix') {
                $ready[] = $proposal;
                $file = (string)$proposal['file_path'];
                if ($file !== '') {
                    $filesWithDeterministic[$file] = true;
                }
            } elseif ($class === 'manual_semantic_decision') {
                $manual[] = $proposal;
                $file = (string)$proposal['file_path'];
                if ($file !== '') {
                    $filesWithManual[$file] = true;
                }
            } elseif ($class === 'future_tool_handoff') {
                $handoff[] = $proposal;
            } elseif ($class === 'classification_review' || (string)($proposal['review_reason_code'] ?? '') === 'accessibility_review') {
                $review[] = $proposal;
            } else {
                $excluded[] = $proposal;
            }
        }

        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['proposal_id'], (string)$b['proposal_id']));
        foreach (['ready', 'manual', 'review', 'handoff', 'excluded'] as $queueName) {
            usort($$queueName, static fn(array $a, array $b): int => strcmp((string)$a['proposal_id'], (string)$b['proposal_id']));
        }

        $summary = [
            'deterministic_future_apply' => count($ready),
            'manual_semantic_decisions' => count($manual),
            'classification_review_items' => count($review),
            'accessibility_review_items' => count(array_filter($review, static fn(array $p): bool => (string)($p['review_reason_code'] ?? '') === 'accessibility_review')),
            'future_effects_handoffs' => count($handoff),
            'excluded_unsupported' => count($excluded),
            'files_with_deterministic' => count($filesWithDeterministic),
            'files_with_manual' => count($filesWithManual),
            'total_proposals' => count($items),
        ];
        $nonExecutableSummary = self::themeRepairNonExecutableSummary($items);
        $guardedApplyPreflight = self::buildGuardedApplyPreflightPlans($items);
        $capability = StyleComplianceGuardedRepairCapabilityService::contract();

        $readiness = self::themeRepairReadinessChecks($items, $ready);

        return [
            'summary' => $summary,
            'non_executable_summary' => $nonExecutableSummary,
            'guarded_apply_preflight' => $guardedApplyPreflight,
            'readiness' => $readiness,
            'queues' => [
                'ready_for_future_guarded_apply' => $ready,
                'manual_semantic_decisions' => $manual,
                'classification_and_accessibility_review' => $review,
                'future_tool_handoffs' => $handoff,
                'excluded' => $excluded,
            ],
            'items' => $items,
            'future_apply_enabled' => !empty($capability['executor_enabled']),
            'execution_capability' => $capability,
        ];
    }

    /**
     * @return array{summary:array<string,int>,readiness:array<int,array<string,string>>,queues:array<string,array<int,array<string,mixed>>>,items:array<int,array<string,mixed>>,future_apply_enabled:bool}
     */
    private static function emptyThemeRepairProposalContract(): array
    {
        return [
            'summary' => [
                'deterministic_future_apply' => 0,
                'manual_semantic_decisions' => 0,
                'classification_review_items' => 0,
                'accessibility_review_items' => 0,
                'future_effects_handoffs' => 0,
                'excluded_unsupported' => 0,
                'files_with_deterministic' => 0,
                'files_with_manual' => 0,
                'total_proposals' => 0,
            ],
            'non_executable_summary' => self::themeRepairNonExecutableSummary([]),
            'guarded_apply_preflight' => self::emptyGuardedApplyPreflight(),
            'readiness' => self::themeRepairReadinessChecks([], []),
            'queues' => [
                'ready_for_future_guarded_apply' => [],
                'manual_semantic_decisions' => [],
                'classification_and_accessibility_review' => [],
                'future_tool_handoffs' => [],
                'excluded' => [],
            ],
            'items' => [],
            'future_apply_enabled' => !empty(StyleComplianceGuardedRepairCapabilityService::contract()['executor_enabled']),
            'execution_capability' => StyleComplianceGuardedRepairCapabilityService::contract(),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private static function themeRepairProposalRecord(array $row): ?array
    {
        $scanCategory = (string)($row['scan_category'] ?? '');
        $property = strtolower(trim((string)($row['property'] ?? $row['token'] ?? '')));
        $value = (string)($row['raw_value'] ?? $row['normalized_value'] ?? $row['value'] ?? '');
        $file = (string)($row['file'] ?? '');
        $sourceOwner = (string)($row['source_owner'] ?? self::ownerKeyFromRelativeFile($file));
        $governanceDomain = (string)($row['governance_domain'] ?? 'not_applicable');
        $requiredDomain = (string)($row['governance_required_domain'] ?? 'not_applicable');
        $migrationState = (string)($row['migration_state'] ?? self::MIGRATION_STATE_NONE);
        $confidence = (string)($row['migration_confidence'] ?? 'low');
        $sourceScope = (string)($row['source_scope'] ?? '');
        $valueConstruct = (string)($row['value_construct'] ?? '');
        $isDynamic = !empty($row['is_dynamic']) || $scanCategory === 'dynamic_unsupported';
        $isPrint = !empty($row['is_print_style']) || $scanCategory === 'print_pdf';
        $isEffect = !empty($row['is_effect_candidate']) || $scanCategory === 'effect_candidate' || self::isEffectProperty($property);
        $isAccessibilityRisk = self::isAccessibilityReviewRisk($row);

        $class = '';
        $status = 'blocked';
        $replacementToken = '';
        $replacementValue = '';
        $replacementRationale = '';
        $blockedBy = [];
        $preconditions = [
            'read_only_contract',
            'future_guarded_apply_not_enabled',
        ];
        $reviewReason = '';

        $deterministic = self::deterministicThemeTokenReplacement($row);
        if ($deterministic !== null) {
            [$replacementToken, $replacementValue, $replacementRationale, $blockedBy] = $deterministic;
            $class = $blockedBy === [] ? 'deterministic_theme_value_fix' : 'excluded';
            $status = $blockedBy === [] ? 'ready_for_future_apply' : 'blocked';
            if (in_array('not_high_confidence', $blockedBy, true) || in_array('not_deterministic_property_role', $blockedBy, true)) {
                $class = 'classification_review';
                $status = 'requires_review';
                $reviewReason = in_array('not_high_confidence', $blockedBy, true) ? 'low_confidence' : 'classification_review';
            }
            if ($class === 'deterministic_theme_value_fix') {
                $preconditions = array_merge($preconditions, [
                    'migration_state_value_fix',
                    'same_theme_governance_domain',
                    'high_confidence',
                    'canonical_target_token_exists',
                    'no_literal_semantic_inference',
                    'no_selector_rewrite',
                    'no_declaration_restructuring',
                ]);
            }
        } elseif ($isEffect) {
            $class = 'future_tool_handoff';
            $status = 'blocked';
            $reviewReason = 'future_effects_handoff';
            $blockedBy[] = 'special_effect_candidate';
        } elseif ($isDynamic || $isPrint) {
            $class = 'excluded';
            $status = 'excluded';
            $reviewReason = $isPrint ? 'print_pdf_candidate' : 'dynamic_or_unsupported';
            $blockedBy[] = $reviewReason;
        } elseif ($isAccessibilityRisk) {
            $class = 'classification_review';
            $status = 'requires_review';
            $reviewReason = 'accessibility_review';
            $blockedBy[] = 'accessibility_review';
        } elseif ($migrationState === self::MIGRATION_STATE_CLASSIFICATION_REVIEW || $confidence !== 'high') {
            $class = 'classification_review';
            $status = 'requires_review';
            $reviewReason = $confidence !== 'high' ? 'low_confidence' : 'classification_review';
            $blockedBy[] = $reviewReason;
        } elseif ($scanCategory === 'visual_literal') {
            $class = 'manual_semantic_decision';
            $status = 'requires_semantic_mapping';
            $reviewReason = 'semantic_mapping_required';
            $blockedBy[] = 'literal_semantic_inference_required';
        } else {
            return null;
        }

        if ($class === 'excluded' && $reviewReason === '') {
            $reviewReason = 'excluded_unsupported';
        }

        [$detectionConfidence, $mappingConfidence, $futureApplyEligibility] = self::themeRepairConfidenceModel(
            $class,
            $confidence,
            $replacementToken,
            $reviewReason
        );

        $proposalId = self::themeRepairProposalId($row, $class, $replacementToken);
        $declarationId = self::themeRepairDeclarationId($row);

        return [
            'proposal_id' => $proposalId,
            'finding_id' => $declarationId,
            'declaration_id' => $declarationId,
            'proposal_class' => $class,
            'proposal_status' => $status,
            'file_path' => $file,
            'line' => (int)($row['line_start'] ?? 0),
            'selector' => (string)($row['selector'] ?? ''),
            'property' => $property,
            'current_value' => $value,
            'replacement_value' => $class === 'deterministic_theme_value_fix' ? $replacementValue : '',
            'replacement_token' => $class === 'deterministic_theme_value_fix' ? $replacementToken : '',
            'replacement_rationale' => $class === 'deterministic_theme_value_fix' ? $replacementRationale : '',
            'source_scope' => $sourceScope,
            'source_owner' => $sourceOwner,
            'governance_domain' => $governanceDomain,
            'governance_required_domain' => $requiredDomain,
            'target_owner' => (string)($row['target_owner'] ?? ''),
            'target_tool' => (string)($row['target_tool'] ?? ''),
            'migration_state' => $migrationState,
            'repair_lane' => (string)($row['repair_lane'] ?? ''),
            'confidence' => $confidence,
            'detection_confidence' => $detectionConfidence,
            'semantic_mapping_confidence' => $mappingConfidence,
            'future_apply_eligibility' => $futureApplyEligibility,
            'apply_preconditions' => $preconditions,
            'blocked_by' => array_values(array_unique($blockedBy)),
            'review_reason_code' => $reviewReason,
            'evidence' => [
                'scan_category' => $scanCategory,
                'value_construct' => $valueConstruct,
                'token_references' => isset($row['token_references']) && is_array($row['token_references']) ? array_values($row['token_references']) : [],
                'semantic_token_misuse' => isset($row['semantic_token_misuse']) && is_array($row['semantic_token_misuse']) ? $row['semantic_token_misuse'] : [],
                'parse_confidence' => (string)($row['parse_confidence'] ?? ''),
            ],
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private static function themeRepairConfidenceModel(string $class, string $detectionConfidence, string $replacementToken, string $reviewReason): array
    {
        $detectionConfidence = in_array($detectionConfidence, ['high', 'medium', 'low', 'none'], true)
            ? $detectionConfidence
            : 'low';

        if ($class === 'deterministic_theme_value_fix' && $replacementToken !== '') {
            return [$detectionConfidence, 'known', 'eligible'];
        }

        if ($class === 'manual_semantic_decision') {
            return [$detectionConfidence, 'unknown', 'review_required'];
        }

        if ($class === 'classification_review') {
            return [$detectionConfidence, $reviewReason === 'low_confidence' ? 'advisory' : 'unknown', 'review_required'];
        }

        if ($class === 'future_tool_handoff') {
            return [$detectionConfidence, 'not_applicable', 'blocked'];
        }

        return [$detectionConfidence, 'not_applicable', 'not_applicable'];
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @return array<string,int>
     */
    private static function themeRepairNonExecutableSummary(array $items): array
    {
        $summary = [
            'print_pdf_review' => 0,
            'dynamic_unsupported' => 0,
            'not_repairable' => 0,
            'excluded_source' => 0,
            'future_tool_handoff' => 0,
            'manual_semantic_decision' => 0,
            'classification_review' => 0,
            'accessibility_review' => 0,
        ];

        foreach ($items as $proposal) {
            $class = (string)($proposal['proposal_class'] ?? '');
            $reason = (string)($proposal['review_reason_code'] ?? '');
            $blockedBy = isset($proposal['blocked_by']) && is_array($proposal['blocked_by']) ? $proposal['blocked_by'] : [];

            if ($class === 'future_tool_handoff') {
                $summary['future_tool_handoff']++;
            }
            if ($class === 'manual_semantic_decision') {
                $summary['manual_semantic_decision']++;
            }
            if ($class === 'classification_review') {
                $summary['classification_review']++;
            }
            if ($reason === 'accessibility_review') {
                $summary['accessibility_review']++;
            }
            if ($reason === 'print_pdf_candidate') {
                $summary['print_pdf_review']++;
            }
            if ($reason === 'dynamic_or_unsupported') {
                $summary['dynamic_unsupported']++;
            }
            if ($reason === 'excluded_unsupported') {
                $summary['not_repairable']++;
            }
            if (in_array('not_editable_owned_source', array_map('strval', $blockedBy), true)) {
                $summary['excluded_source']++;
            }
        }

        return $summary;
    }

    /**
     * @param array<int,array<string,mixed>> $proposals
     * @return array{summary:array<string,int>,plans:array<int,array<string,mixed>>,future_executor_enabled:bool}
     */
    public static function buildGuardedApplyPreflightPlans(array $proposals): array
    {
        $capability = StyleComplianceGuardedRepairCapabilityService::contract();
        $executorEnabled = !empty($capability['executor_enabled']);
        $plans = [];
        foreach ($proposals as $proposal) {
            if (!is_array($proposal)) {
                continue;
            }
            if ((string)($proposal['proposal_class'] ?? '') !== 'deterministic_theme_value_fix') {
                continue;
            }
            if ((string)($proposal['proposal_status'] ?? '') !== 'ready_for_future_apply') {
                continue;
            }
            $plans[] = self::guardedApplyPreflightPlanRecord($proposal);
        }

        usort($plans, static fn(array $a, array $b): int => strcmp((string)$a['apply_plan_id'], (string)$b['apply_plan_id']));

        $summary = [
            'eligible_after_preflight' => 0,
            'blocked' => 0,
            'stale' => 0,
            'review_required' => 0,
            'owner_review_required' => count($plans),
            'single_declaration_scope' => count($plans),
            'future_executor_not_enabled' => $executorEnabled ? 0 : count($plans),
            'total_plans' => count($plans),
        ];
        foreach ($plans as $plan) {
            $status = (string)($plan['preflight_status'] ?? 'blocked');
            if ($status === 'ready') {
                $summary['eligible_after_preflight']++;
            } elseif (isset($summary[$status])) {
                $summary[$status]++;
            } else {
                $summary['blocked']++;
            }
        }

        return [
            'summary' => $summary,
            'plans' => $plans,
            'future_executor_enabled' => $executorEnabled,
            'execution_capability' => $capability,
        ];
    }

    /**
     * @return array{summary:array<string,int>,plans:array<int,array<string,mixed>>,future_executor_enabled:bool}
     */
    private static function emptyGuardedApplyPreflight(): array
    {
        $capability = StyleComplianceGuardedRepairCapabilityService::contract();
        return [
            'summary' => [
                'eligible_after_preflight' => 0,
                'blocked' => 0,
                'stale' => 0,
                'review_required' => 0,
                'owner_review_required' => 0,
                'single_declaration_scope' => 0,
                'future_executor_not_enabled' => 0,
                'total_plans' => 0,
            ],
            'plans' => [],
            'future_executor_enabled' => !empty($capability['executor_enabled']),
            'execution_capability' => $capability,
        ];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private static function guardedApplyPreflightPlanRecord(array $proposal): array
    {
        $filePath = (string)($proposal['file_path'] ?? '');
        $expectedCurrentValue = (string)($proposal['current_value'] ?? '');
        $replacementValue = (string)($proposal['replacement_value'] ?? '');
        $replacementToken = (string)($proposal['replacement_token'] ?? '');
        $sourceFingerprint = self::sourceFingerprintForRelativePath($filePath);
        $declarationFingerprint = self::declarationFingerprintForProposal($proposal);
        $proposalFingerprint = self::proposalFingerprintForProposal($proposal, $declarationFingerprint);
        $preflightChecks = self::guardedApplyPreflightChecks(
            $proposal,
            $sourceFingerprint,
            $declarationFingerprint,
            $proposalFingerprint
        );

        $blockedBy = [];
        $invalidatedBy = [];
        $reviewRequired = false;
        foreach ($preflightChecks as $check) {
            $state = (string)($check['state'] ?? 'blocked');
            $key = (string)($check['key'] ?? '');
            if ($state === 'stale') {
                $invalidatedBy[] = $key;
            } elseif ($state === 'review_required') {
                $reviewRequired = true;
                $blockedBy[] = $key;
            } elseif ($state !== 'pass') {
                $blockedBy[] = $key;
            }
        }

        $preflightStatus = 'ready';
        if ($invalidatedBy !== []) {
            $preflightStatus = 'stale';
        } elseif ($reviewRequired) {
            $preflightStatus = 'review_required';
        } elseif ($blockedBy !== []) {
            $preflightStatus = 'blocked';
        }

        $capability = StyleComplianceGuardedRepairCapabilityService::contract();
        $executorEnabled = !empty($capability['executor_enabled']);

        return [
            'apply_plan_id' => self::guardedApplyPlanId($proposal, $proposalFingerprint),
            'proposal_id' => (string)($proposal['proposal_id'] ?? ''),
            'finding_id' => (string)($proposal['finding_id'] ?? ''),
            'source_owner' => (string)($proposal['source_owner'] ?? ''),
            'target_owner' => (string)($proposal['target_owner'] ?? ''),
            'target_tool' => 'theme_aware_repair',
            'file_path' => $filePath,
            'line' => (int)($proposal['line'] ?? 0),
            'selector' => (string)($proposal['selector'] ?? ''),
            'property' => (string)($proposal['property'] ?? ''),
            'expected_current_value' => $expectedCurrentValue,
            'proposed_replacement_value' => $replacementValue,
            'proposed_replacement_token' => $replacementToken,
            'source_fingerprint' => $sourceFingerprint,
            'declaration_fingerprint' => $declarationFingerprint,
            'proposal_fingerprint' => $proposalFingerprint,
            'authority_requirement' => 'owner_review_required',
            'required_reviewer' => (string)($proposal['source_owner'] ?? ''),
            'authority_reason' => 'The source owner remains the approval authority for future guarded apply.',
            'apply_scope' => 'single_declaration',
            'preflight_status' => $preflightStatus,
            'preflight_checks' => $preflightChecks,
            'blocked_by' => array_values(array_unique($blockedBy)),
            'invalidated_by' => array_values(array_unique($invalidatedBy)),
            'required_validations' => self::guardedApplyRequiredValidations(),
            'required_evidence' => self::guardedApplyRequiredEvidence(),
            'snapshot_contract' => $executorEnabled ? 'snapshot_required_before_guarded_write' : 'future_executor_required_not_available_in_read_only_phase',
            'rollback_contract' => $executorEnabled ? 'rollback_snapshot_required_for_guarded_write' : 'future_executor_required_not_available_in_read_only_phase',
            'mutation_endpoint' => $executorEnabled ? '/apps/studio/tools/customization-studio/diagnose/style-compliance/repair-execute' : '',
            'apply_action' => $executorEnabled ? 'single_guarded_repair_after_successful_preflight' : '',
        ];
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<int,array{key:string,state:string,detail:string}>
     */
    private static function guardedApplyPreflightChecks(array $proposal, string $sourceFingerprint, string $declarationFingerprint, string $proposalFingerprint): array
    {
        $filePath = (string)($proposal['file_path'] ?? '');
        $expectedCurrentValue = (string)($proposal['current_value'] ?? '');
        $replacementValue = (string)($proposal['replacement_value'] ?? '');
        $replacementToken = (string)($proposal['replacement_token'] ?? '');
        $blockedBy = isset($proposal['blocked_by']) && is_array($proposal['blocked_by']) ? array_map('strval', $proposal['blocked_by']) : [];
        $canonicalTokens = self::canonicalThemeTokens();
        $sourceFile = self::absolutePathForRelativeSource($filePath);
        $contents = is_string($sourceFile) && is_file($sourceFile) ? @file_get_contents($sourceFile) : false;
        $fileExists = is_string($contents);
        $currentValueStillPresent = $fileExists && $expectedCurrentValue !== '' && str_contains((string)$contents, $expectedCurrentValue);
        $expectedDeclarationFingerprint = (string)($proposal['expected_declaration_fingerprint'] ?? $declarationFingerprint);
        $expectedProposalFingerprint = (string)($proposal['expected_proposal_fingerprint'] ?? $proposalFingerprint);

        $checks = [
            ['proposal_class_deterministic', (string)($proposal['proposal_class'] ?? '') === 'deterministic_theme_value_fix'],
            ['detection_confidence_high', (string)($proposal['detection_confidence'] ?? $proposal['confidence'] ?? '') === 'high'],
            ['semantic_mapping_known', (string)($proposal['semantic_mapping_confidence'] ?? '') === 'known'],
            ['future_apply_eligible', (string)($proposal['future_apply_eligibility'] ?? '') === 'eligible'],
            ['source_owner_known', (string)($proposal['source_owner'] ?? '') !== ''],
            ['target_owner_known', (string)($proposal['target_owner'] ?? '') !== ''],
            ['source_file_owned_editable', self::isOwnedEditableSourcePath($filePath)],
            ['source_path_supported', !self::isUnsupportedApplySourcePath($filePath)],
            ['source_fingerprint_present', $sourceFingerprint !== ''],
            ['declaration_fingerprint_present', $declarationFingerprint !== ''],
            ['expected_current_value_present', $expectedCurrentValue !== ''],
            ['replacement_token_exists', $replacementToken !== '' && isset($canonicalTokens[$replacementToken])],
            ['replacement_value_deterministic', $replacementValue !== '' && preg_match('/^var\(\s*--[\w-]+\s*\)$/', $replacementValue) === 1],
            ['governance_domain_theme_related', (string)($proposal['governance_domain'] ?? '') === 'theme_related'],
            ['required_governance_domain_theme_related', (string)($proposal['governance_required_domain'] ?? '') === 'theme_related'],
            ['not_print_pdf', (string)($proposal['review_reason_code'] ?? '') !== 'print_pdf_candidate'],
            ['not_dynamic', !in_array('dynamic_value', $blockedBy, true) && (string)($proposal['review_reason_code'] ?? '') !== 'dynamic_or_unsupported'],
            ['not_accessibility_review', (string)($proposal['review_reason_code'] ?? '') !== 'accessibility_review'],
            ['not_special_effect', !in_array('special_effect_candidate', $blockedBy, true)],
            ['no_selector_rewrite', !in_array('selector_rewrite_required', $blockedBy, true)],
            ['no_declaration_restructuring', !in_array('declaration_restructure_required', $blockedBy, true)],
            ['no_token_creation', !in_array('target_token_missing', $blockedBy, true) && !in_array('replacement_token_unknown', $blockedBy, true)],
            ['no_cross_file_migration', (string)($proposal['migration_state'] ?? '') === self::MIGRATION_STATE_VALUE_FIX],
            ['no_policy_authority_block', $blockedBy === []],
        ];

        $out = [];
        foreach ($checks as [$key, $passed]) {
            $out[] = [
                'key' => $key,
                'state' => $passed ? 'pass' : 'blocked',
                'detail' => $passed ? 'Precondition satisfied.' : 'Precondition failed.',
            ];
        }

        $staleChecks = [
            ['file_content_unchanged', $fileExists, $fileExists ? 'pass' : 'stale', 'Source file no longer exists or cannot be read.'],
            ['current_value_matches_expected', $currentValueStillPresent, $currentValueStillPresent ? 'pass' : 'stale', 'Expected current value is not present in source.'],
            ['declaration_fingerprint_matches', $expectedDeclarationFingerprint === $declarationFingerprint, $expectedDeclarationFingerprint === $declarationFingerprint ? 'pass' : 'stale', 'Declaration fingerprint changed after planning.'],
            ['proposal_fingerprint_matches', $expectedProposalFingerprint === $proposalFingerprint, $expectedProposalFingerprint === $proposalFingerprint ? 'pass' : 'stale', 'Proposal fingerprint changed after planning.'],
        ];
        foreach ($staleChecks as [$key, $passed, $state, $detail]) {
            $out[] = [
                'key' => (string)$key,
                'state' => $passed ? 'pass' : (string)$state,
                'detail' => $passed ? 'Integrity fact still matches.' : (string)$detail,
            ];
        }

        return $out;
    }

    /**
     * @return array<int,string>
     */
    private static function guardedApplyRequiredValidations(): array
    {
        return [
            'css_source_parse_declaration_integrity',
            'php_lint_for_php_inline_style_sources',
            'theme_token_contract_existence',
            'theme_compile_runtime_asset_health',
            'target_owner_route_render_smoke',
            'light_paper',
            'light_liquid_glass',
            'dark_paper',
            'dark_liquid_glass',
            'effects_disabled',
            'reduced_motion_enabled',
            'post_apply_rescan_confirms_value_fix_resolved',
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function guardedApplyRequiredEvidence(): array
    {
        return [
            'pre_apply_source_snapshot',
            'snapshot_manifest',
            'file_fingerprint_before_apply',
            'declaration_fingerprint_before_apply',
            'exact_old_declaration_text',
            'exact_proposed_new_declaration_text',
            'post_write_syntax_integrity_validation',
            'post_write_source_fingerprint',
            'post_write_rescan',
            'proposal_resolution_result',
            'owner_and_timestamp_audit_evidence',
            'rollback_eligibility_metadata',
        ];
    }

    private static function sourceFingerprintForRelativePath(string $filePath): string
    {
        $absolute = self::absolutePathForRelativeSource($filePath);
        if (!is_string($absolute) || !is_file($absolute)) {
            return '';
        }
        $contents = @file_get_contents($absolute);
        if (!is_string($contents)) {
            return '';
        }
        return 'sha256:' . hash('sha256', $contents);
    }

    private static function absolutePathForRelativeSource(string $filePath): ?string
    {
        $trimmed = ltrim($filePath, '/');
        if ($trimmed === '' || str_contains($trimmed, '..')) {
            return null;
        }
        $absolute = APP_ROOT . '/' . $trimmed;
        $real = realpath($absolute);
        if (!is_string($real) || !self::isInsideAppRoot($real)) {
            return null;
        }
        return $real;
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function declarationFingerprintForProposal(array $proposal): string
    {
        $parts = [
            (string)($proposal['file_path'] ?? ''),
            (string)($proposal['line'] ?? ''),
            (string)($proposal['selector'] ?? ''),
            (string)($proposal['property'] ?? ''),
            (string)($proposal['current_value'] ?? ''),
        ];
        return 'sha256:' . hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function proposalFingerprintForProposal(array $proposal, string $declarationFingerprint): string
    {
        $parts = [
            (string)($proposal['proposal_id'] ?? ''),
            $declarationFingerprint,
            (string)($proposal['replacement_token'] ?? ''),
            (string)($proposal['replacement_value'] ?? ''),
            (string)($proposal['semantic_mapping_confidence'] ?? ''),
            (string)($proposal['future_apply_eligibility'] ?? ''),
        ];
        return 'sha256:' . hash('sha256', implode('|', $parts));
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function guardedApplyPlanId(array $proposal, string $proposalFingerprint): string
    {
        return 'gap-' . substr(sha1((string)($proposal['proposal_id'] ?? '') . '|' . $proposalFingerprint), 0, 16);
    }

    private static function isOwnedEditableSourcePath(string $filePath): bool
    {
        $absolute = self::absolutePathForRelativeSource($filePath);
        return is_string($absolute)
            && is_file($absolute)
            && preg_match('#^(apps|resources/themes)/#', ltrim($filePath, '/')) === 1
            && !self::isUnsupportedApplySourcePath($filePath);
    }

    private static function isUnsupportedApplySourcePath(string $filePath): bool
    {
        $lower = strtolower($filePath);
        return $filePath === ''
            || str_contains($lower, '/vendor/')
            || str_contains($lower, '/node_modules/')
            || str_contains($lower, '/tests/fixtures/')
            || str_contains($lower, '/snapshot')
            || str_contains($lower, '/snapshots/')
            || str_contains($lower, '/compiled/')
            || str_contains($lower, '/dist/')
            || str_contains($lower, '/build/')
            || str_ends_with($lower, '.min.css');
    }

    /**
     * @param array<string,mixed> $row
     * @return array{0:string,1:string,2:string,3:array<int,string>}|null
     */
    private static function deterministicThemeTokenReplacement(array $row): ?array
    {
        if ((string)($row['scan_category'] ?? '') !== 'semantic_token_misuse') {
            return null;
        }

        $blocked = [];
        $migrationState = (string)($row['migration_state'] ?? '');
        $governanceDomain = (string)($row['governance_domain'] ?? '');
        $requiredDomain = (string)($row['governance_required_domain'] ?? '');
        $confidence = (string)($row['migration_confidence'] ?? 'low');
        $sourceScope = (string)($row['source_scope'] ?? '');
        $file = (string)($row['file'] ?? '');
        $sourceOwner = (string)($row['source_owner'] ?? self::ownerKeyFromRelativeFile($file));
        $property = strtolower(trim((string)($row['property'] ?? '')));
        $value = (string)($row['raw_value'] ?? $row['value'] ?? '');

        if ($migrationState !== self::MIGRATION_STATE_VALUE_FIX) {
            $blocked[] = 'migration_state_not_value_fix';
        }
        if ($governanceDomain !== 'theme_related' || $requiredDomain !== 'theme_related') {
            $blocked[] = 'not_same_theme_governance_domain';
        }
        if (!in_array($sourceScope, [self::SCOPE_OWNER, self::SCOPE_SHELL, self::SCOPE_THEME], true)) {
            $blocked[] = 'unsupported_source_scope';
        }
        if ($file === '' || str_contains($file, '/vendor/') || str_contains($file, '/node_modules/') || str_ends_with($file, '.min.css') || str_contains($file, '/Tests/fixtures/')) {
            $blocked[] = 'not_editable_owned_source';
        }
        if ($sourceOwner === '') {
            $blocked[] = 'unknown_source_owner';
        }
        if ($confidence !== 'high') {
            $blocked[] = 'not_high_confidence';
        }
        if (!self::isDeterministicTokenRoleProperty($property)) {
            $blocked[] = 'not_deterministic_property_role';
        }
        if (!empty($row['is_dynamic'])) {
            $blocked[] = 'dynamic_value';
        }
        if (!empty($row['is_print_style'])) {
            $blocked[] = 'print_pdf_candidate';
        }
        if (!empty($row['is_effect_candidate']) || self::isEffectProperty($property)) {
            $blocked[] = 'special_effect_candidate';
        }
        if (self::isAccessibilityReviewRisk($row)) {
            $blocked[] = 'accessibility_review';
        }

        $misuse = isset($row['semantic_token_misuse']) && is_array($row['semantic_token_misuse']) ? $row['semantic_token_misuse'] : [];
        $misusedToken = (string)($misuse['token'] ?? '');
        $propertyRole = (string)($misuse['property_role'] ?? self::semanticPropertyRole($property));
        $replacementToken = self::semanticTokenCorrectionHint($propertyRole, (string)($misuse['token_role'] ?? ''), $misusedToken);
        if (!str_starts_with($replacementToken, '--')) {
            $blocked[] = 'replacement_token_unknown';
        }
        $canonicalTokens = self::canonicalThemeTokens();
        if ($replacementToken === '' || !isset($canonicalTokens[$replacementToken])) {
            $blocked[] = 'target_token_missing';
        }
        if (!preg_match('/^var\(\s*--[\w-]+(?:\s*,[^)]*)?\)$/', trim($value))) {
            $blocked[] = 'declaration_restructure_required';
        }

        $replacementValue = $replacementToken !== '' && str_starts_with($replacementToken, '--')
            ? 'var(' . $replacementToken . ')'
            : '';
        $rationale = $replacementToken !== ''
            ? 'Replace role-mismatched token reference with canonical ' . $propertyRole . '-role token.'
            : '';

        return [$replacementToken, $replacementValue, $rationale, array_values(array_unique($blocked))];
    }

    private static function isDeterministicTokenRoleProperty(string $property): bool
    {
        return self::semanticPropertyRole($property) !== '';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function isAccessibilityReviewRisk(array $row): bool
    {
        $property = strtolower(trim((string)($row['property'] ?? '')));
        $value = strtolower(trim((string)($row['raw_value'] ?? $row['value'] ?? '')));
        return in_array($property, ['outline', 'outline-style', 'outline-width', 'outline-color'], true)
            && ($value === 'none' || $value === '0' || $value === '0px');
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<int,array<string,mixed>> $ready
     * @return array<int,array<string,string>>
     */
    private static function themeRepairReadinessChecks(array $items, array $ready): array
    {
        $allReadyHigh = true;
        $literalIncluded = false;
        $effectsIncluded = false;
        $printIncluded = false;
        $accessibilityIncluded = false;
        $ownerKnown = true;
        $targetTokenExists = true;

        foreach ($ready as $proposal) {
            if ((string)($proposal['confidence'] ?? '') !== 'high') {
                $allReadyHigh = false;
            }
            if ((string)($proposal['source_owner'] ?? '') === '') {
                $ownerKnown = false;
            }
            if ((string)($proposal['replacement_token'] ?? '') === '') {
                $targetTokenExists = false;
            }
        }

        foreach ($items as $proposal) {
            $class = (string)($proposal['proposal_class'] ?? '');
            $reviewReason = (string)($proposal['review_reason_code'] ?? '');
            if ($class === 'manual_semantic_decision') {
                $literalIncluded = true;
            }
            if ($class === 'future_tool_handoff') {
                $effectsIncluded = true;
            }
            if ($reviewReason === 'print_pdf_candidate') {
                $printIncluded = true;
            }
            if ($reviewReason === 'accessibility_review') {
                $accessibilityIncluded = true;
            }
        }

        return [
            ['key' => 'readiness_deterministic_set', 'state' => $ready !== [] ? 'pass' : 'blocked'],
            ['key' => 'readiness_all_high_confidence', 'state' => $allReadyHigh ? 'pass' : 'blocked'],
            ['key' => 'readiness_no_literal_inference', 'state' => !$literalIncluded ? 'pass' : 'blocked'],
            ['key' => 'readiness_no_effects', 'state' => !$effectsIncluded ? 'pass' : 'blocked'],
            ['key' => 'readiness_no_print_pdf', 'state' => !$printIncluded ? 'pass' : 'blocked'],
            ['key' => 'readiness_no_accessibility_risk', 'state' => !$accessibilityIncluded ? 'pass' : 'blocked'],
            ['key' => 'readiness_owner_boundary_known', 'state' => $ownerKnown ? 'pass' : 'blocked'],
            ['key' => 'readiness_target_token_exists', 'state' => $targetTokenExists ? 'pass' : 'blocked'],
            ['key' => 'readiness_future_apply_contract', 'state' => 'pending'],
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function themeRepairProposalId(array $row, string $class, string $replacementToken): string
    {
        $parts = [
            (string)($row['file'] ?? ''),
            (string)($row['line_start'] ?? ''),
            (string)($row['selector'] ?? ''),
            (string)($row['property'] ?? $row['token'] ?? ''),
            (string)($row['raw_value'] ?? $row['value'] ?? ''),
            $class,
            $replacementToken,
        ];
        return 'tar-' . substr(sha1(implode('|', $parts)), 0, 16);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function themeRepairDeclarationId(array $row): string
    {
        $parts = [
            (string)($row['file'] ?? ''),
            (string)($row['line_start'] ?? ''),
            (string)($row['selector'] ?? ''),
            (string)($row['property'] ?? $row['token'] ?? ''),
            (string)($row['raw_value'] ?? $row['value'] ?? ''),
        ];
        return 'decl-' . substr(sha1(implode('|', $parts)), 0, 16);
    }

    private static function semanticTokenCorrectionHint(string $propertyRole, string $tokenRole, string $token): string
    {
        $tone = '';
        if (preg_match('/^--tone-([a-z0-9-]+?)(?:-(?:bg|background|text|foreground|fg|border|line|stroke))?$/', strtolower($token), $m) === 1) {
            $tone = (string)$m[1];
        }

        $suffix = match ($propertyRole) {
            'background' => 'bg',
            'text' => 'text',
            'border' => 'border',
            default => '',
        };

        if ($tone !== '' && $suffix !== '') {
            return '--tone-' . $tone . '-' . $suffix;
        }

        if ($propertyRole !== '') {
            return 'Use a ' . $propertyRole . '-role token for this property';
        }

        if ($tokenRole !== '') {
            return 'Replace with a token whose semantic role matches the property';
        }

        return 'Review semantic token role before repair';
    }

    /**
     * @param array{type:string,is_literal:bool,canonical:string} $cls
     * @return array{semantic_name:string,light:string,dark:string,default:string,repair_action:string,confidence:string,rationale:string}
     */
    private static function proposeSemantic(string $tokenName, array $cls, string $kind = 'declaration'): array
    {
        $stripped = ltrim($tokenName, '-');
        $hint = self::categoryHint($stripped, $cls['type']);
        $canonicalTokens = self::canonicalThemeTokens();
        $hasCanonicalToken = isset($canonicalTokens[$tokenName]);
        $isLiteralDeclaration = $kind === 'literal_declaration';
        $semantic = $hasCanonicalToken && !$isLiteralDeclaration ? $tokenName : ('--' . $hint . '-' . self::shortNameFrom($stripped));
        $value = $cls['canonical'];

        // For colors, propose a light=current, dark=invert-luminance hint as a starting
        // suggestion. We do NOT compute precise dark colors — that's apply-phase work.
        $isColor = in_array($cls['type'], ['color_hex', 'color_func'], true);
        $light = $value;
        $dark = $isColor ? ('/* TODO derive dark variant for ' . $value . ' */') : $value;
        $default = $value;

        // Confidence rubric:
        //   high   = literal hex/length, token name already implies semantic intent
        //   medium = literal value but name is generic
        //   low    = literal value but name conflicts with existing semantic prefixes
        $hasSemanticHint = self::nameHasSemanticHint($stripped) || ($hasCanonicalToken && !$isLiteralDeclaration);
        $confidence = $hasSemanticHint ? 'high' : 'medium';
        if ((!$hasCanonicalToken || $isLiteralDeclaration) && self::nameConflictsWithSemanticPrefix($stripped)) {
            $confidence = 'low';
        }

        $repairAction = $isLiteralDeclaration ? 'replace_literal_with_token' : ($hasCanonicalToken ? 'theme_scope_migration' : 'semantic_alias');
        $proposalVerb = match ($repairAction) {
            'theme_scope_migration' => 'moving the canonical token into theme-aware selectors',
            'replace_literal_with_token' => 'replacing the literal declaration with a semantic token',
            default => 'semantic mapping',
        };
        $rationale = sprintf(
            'Finding "%s" uses literal %s value "%s" with no theme-aware repair. Propose %s "%s".',
            $tokenName,
            $cls['type'],
            $value,
            $proposalVerb,
            $semantic
        );

        return [
            'semantic_name' => $semantic,
            'light' => $light,
            'dark' => $dark,
            'default' => $default,
            'repair_action' => $repairAction,
            'confidence' => $confidence,
            'rationale' => $rationale,
        ];
    }

    /**
     * @return array<string,true>
     */
    private static function canonicalThemeTokens(): array
    {
        static $tokens = null;
        if (is_array($tokens)) {
            return $tokens;
        }

        $tokens = [];
        $roots = [
            APP_ROOT . '/resources/themes',
        ];

        foreach ($roots as $root) {
            foreach (self::collectCssFiles($root) as $file) {
                $contents = @file_get_contents($file);
                if (!is_string($contents) || $contents === '') {
                    continue;
                }
                if (preg_match_all('/(--[\w-]+)\s*:/i', $contents, $matches)) {
                    foreach ($matches[1] as $token) {
                        $tokens[(string)$token] = true;
                    }
                }
            }
        }

        return $tokens;
    }

    private static function categoryHint(string $name, string $valueType): string
    {
        $lower = strtolower($name);
        if (str_contains($lower, 'bg') || str_contains($lower, 'background')) {
            return 'surface';
        }
        if (str_contains($lower, 'text') || str_contains($lower, 'fg') || str_contains($lower, 'foreground')) {
            return 'text';
        }
        if (str_contains($lower, 'border')) {
            return 'border';
        }
        if (str_contains($lower, 'accent') || str_contains($lower, 'primary')) {
            return 'accent';
        }
        if (str_contains($lower, 'radius')) {
            return 'radius';
        }
        if (str_contains($lower, 'space') || str_contains($lower, 'gap') || str_contains($lower, 'pad') || str_contains($lower, 'margin')) {
            return 'space';
        }
        if (str_contains($lower, 'shadow')) {
            return 'shadow';
        }
        if ($valueType === 'color_hex' || $valueType === 'color_func') {
            return 'color';
        }
        if ($valueType === 'length') {
            return 'length';
        }
        return 'value';
    }

    private static function shortNameFrom(string $name): string
    {
        $parts = preg_split('/[-_]/', $name) ?: [];
        $tail = array_slice($parts, -2);
        return implode('-', $tail) ?: $name;
    }

    private static function nameHasSemanticHint(string $name): bool
    {
        $hints = ['bg', 'text', 'border', 'accent', 'primary', 'surface', 'fg', 'radius', 'shadow', 'space', 'gap'];
        $lower = strtolower($name);
        foreach ($hints as $h) {
            if (str_contains($lower, $h)) {
                return true;
            }
        }
        return false;
    }

    private static function nameConflictsWithSemanticPrefix(string $name): bool
    {
        $reserved = ['theme', 'system', 'platform', 'core', 'shell'];
        $lower = strtolower($name);
        foreach ($reserved as $r) {
            if (str_starts_with($lower, $r . '-')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Detect Foundation rendering concerns — table-like CSS selectors that
     * lack overflow containment (scroll wrapping) or cell-level overflow-wrap.
     *
     * A table without overflow-x: auto on itself or a parent container allows
     * wide content (long file paths, CSS values in <code> blocks) to escape
     * the viewport. We use only dynamic/semantic CSS evidence: selector shape,
     * property declarations, and containment patterns — no per-owner or
     * per-path special cases.
     *
     * @param array<int,string> $files  Absolute paths to style source files
     * @return array<int,array<string,mixed>>
     */
    private static function detectFoundationConcerns(array $files): array
    {
        $concerns = [];

        foreach ($files as $file) {
            $relative = self::relativePath($file);
            // Only scan CSS files — PHP/JS/HTML parsing via splitBlocksRaw produces false selectors
            if (!str_ends_with($file, '.css')) {
                continue;
            }
            $contents = @file_get_contents($file);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            // Parse all rule blocks in this file
            $blocks = self::splitBlocksRaw($contents);

            // Pass 1: find table-like selectors and check each for overflow
            // Pass 2: check if the file has any overflow-x container (wrapper pattern)
            $fileHasOverflowXContainer = false;

            $tableSelectors = [];
            foreach ($blocks as $block) {
                $selector = trim($block['selector']);
                $body = $block['body'];

                $isTable = self::isTableLikeSelector($selector);
                $hasOverflow = self::ruleBodyHasOverflowContainment($body);

                if ($isTable) {
                    $tableSelectors[] = [
                        'selector' => $selector,
                        'body' => $body,
                        'has_self_overflow' => $hasOverflow,
                        'has_cell_overflow_wrap' => self::ruleBodyHasCellOverflowWrap($body),
                    ];
                }

                if ($hasOverflow) {
                    $fileHasOverflowXContainer = true;
                }
            }

            // Classify each table selector
            foreach ($tableSelectors as $info) {
                $missing = [];

                if (!$info['has_self_overflow']) {
                    $missing[] = 'overflow-x: auto';
                }

                if (preg_match('/\b(th|td)\b/i', $info['selector']) && !$info['has_cell_overflow_wrap']) {
                    $missing[] = 'overflow-wrap: anywhere';
                }

                if ($missing === []) {
                    continue;
                }

                // Classify severity based on evidence
                $severity = 'info';
                if (!$info['has_self_overflow'] && !$fileHasOverflowXContainer) {
                    $severity = 'warning';
                }
                if (count($missing) >= 2) {
                    $severity = 'warning';
                }

                $concerns[] = [
                    'file' => $relative,
                    'selector' => $info['selector'],
                    'missing' => $missing,
                    'severity' => $severity,
                ];
            }
        }

        return $concerns;
    }

    /**
     * Check whether a CSS selector string matches a table-like pattern.
     *
     * Table-like selectors are those directly selecting:
     *   - table, th, td HTML elements
     *   - class names containing "table" (e.g. .data-table, .sc-table)
     *   - display: table layout classes
     *
     * Does NOT match:
     *   - @media / @keyframes / @font-face at-rules (filtered by caller)
     *   - print-specific table selectors
     *   - selectors containing :*() pseudos that change semantics (handled by caller)
     */
    private static function isTableLikeSelector(string $selector): bool
    {
        if ($selector === '' || preg_match('/^@\w+/', $selector)) {
            return false;
        }

        // Direct element selectors
        if (preg_match('/\btable\b/i', $selector)) {
            return true;
        }
        if (preg_match('/\b(th|td|thead|tbody|tfoot|tr|colgroup|caption)\b/i', $selector)) {
            return true;
        }

        // Class-based table patterns
        if (preg_match('/[.-]table\b/i', $selector)) {
            return true;
        }
        if (preg_match('/[.-]data-table\b/i', $selector)) {
            return true;
        }
        if (preg_match('/[.-]list-view\b/i', $selector)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether a CSS rule body declares overflow-x: auto/scroll/hidden or overflow: auto/scroll/hidden
     * on the rule itself (self containment) or references .table-wrap / *-table-wrap patterns.
     */
    private static function ruleBodyHasOverflowContainment(string $body): bool
    {
        // Parse each declaration in the body
        $decls = self::parseDeclarationsSimple($body);
        foreach ($decls as $decl) {
            $prop = strtolower(trim($decl['property']));
            $val = strtolower(trim($decl['value']));

            if ($prop === 'overflow-x' && in_array($val, ['auto', 'scroll', 'hidden'], true)) {
                return true;
            }
            if ($prop === 'overflow' && in_array($val, ['auto', 'scroll', 'hidden'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether a CSS rule body declares overflow-wrap or word-break for cell content wrapping.
     */
    private static function ruleBodyHasCellOverflowWrap(string $body): bool
    {
        $decls = self::parseDeclarationsSimple($body);
        foreach ($decls as $decl) {
            $prop = strtolower(trim($decl['property']));
            $val = strtolower(trim($decl['value']));

            if ($prop === 'overflow-wrap' && in_array($val, ['anywhere', 'break-word'], true)) {
                return true;
            }
            if ($prop === 'word-break' && $val === 'break-all') {
                return true;
            }
            if ($prop === 'word-wrap' && in_array($val, ['anywhere', 'break-word'], true)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Simple declaration parser for Foundation scanning.
     * Extracts property:value pairs from a CSS rule body.
     *
     * @return array<int,array{property:string,value:string}>
     */
    private static function parseDeclarationsSimple(string $body): array
    {
        $decls = [];
        if ($body === '') {
            return $decls;
        }

        $parts = explode(';', $body);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $colon = strpos($part, ':');
            if ($colon === false) {
                continue;
            }
            $prop = substr($part, 0, $colon);
            $val = substr($part, $colon + 1);
            $prop = trim($prop);
            $val = trim($val);
            // Skip empty property (malformed declaration)
            if ($prop === '' || $val === '') {
                continue;
            }
            $decls[] = ['property' => $prop, 'value' => $val];
        }

        return $decls;
    }

    /**
     * @param array<int,array<string,mixed>> $tokens
     * @return array<int,array<string,mixed>>
     */
    private static function detectBoundaryFindings(array $tokens, string $scope, string $ownerKey): array
    {
        // For MVP we only flag declarations in theme-source scope that look owner-specific
        // (e.g. --mfg-foo declared inside resources/themes/). Cross-owner var(...) usage
        // analysis is reserved for a later slice once token consumption scanning lands.
        if ($scope !== self::SCOPE_THEME) {
            return [];
        }
        $findings = [];
        foreach ($tokens as $row) {
            $name = strtolower($row['token']);
            // Owner-namespaced tokens (heuristic): leading prefix matches a known business app.
            if (preg_match('/^--(mfg|sbaio|payroll|coverage|qc|dispatch|machines|assembly|materials|prodop)\b/i', $name) === 1) {
                $findings[] = [
                    'kind' => 'owner_namespaced_token_in_theme_source',
                    'token' => $row['token'],
                    'file' => $row['file'],
                    'message' => 'Owner-namespaced token declared inside theme source; ownership should move to the owning app/module CSS.',
                ];
            }
        }
        unset($ownerKey);
        return $findings;
    }

    /**
     * @param array<int,array<string,mixed>> $tokens
     * @return array<int,array{file:string,declarations:int,theme_aware:int,unaware:int}>
     */
    private static function summarizeAffectedFiles(array $tokens): array
    {
        $by = [];
        foreach ($tokens as $row) {
            $file = $row['file'];
            if (!isset($by[$file])) {
                $by[$file] = ['file' => $file, 'declarations' => 0, 'theme_aware' => 0, 'unaware' => 0];
            }
            $by[$file]['declarations']++;
            if (!empty($row['theme_aware'])) {
                $by[$file]['theme_aware']++;
            } else {
                $by[$file]['unaware']++;
            }
        }
        $list = array_values($by);
        usort($list, static fn($a, $b) => $b['unaware'] - $a['unaware']);
        return $list;
    }

    /**
     * @param array<int,array<string,mixed>> $tokens
     * @param array<int,array<string,mixed>> $candidates
     * @param array<int,array<string,mixed>> $boundary
     * @return array<string,int>
     */
    private static function buildSummary(array $tokens, array $candidates, array $boundary, array $foundationConcerns = []): array
    {
        $awareCount = 0;
        $unawareCount = 0;
        $tokenDefCount = 0;
        $tokenConsumerCount = 0;
        $visualLiteralCount = 0;
        $structuralCount = 0;
        $inScopeCount = 0;
        $evidenceOnlyCount = 0;
        $inScopeAwareCount = 0;
        $inScopeUnawareCount = 0;
        $effectCount = 0;
        $dynamicCount = 0;
        $printCount = 0;
        $semanticMisuseCount = 0;
        $themeDomainCount = 0;
        $shellFoundationDomainCount = 0;
        $ownerSurfaceDomainCount = 0;
        $specialEffectDomainCount = 0;
        $structuralDomainCount = 0;
        $printPdfDomainCount = 0;
        $unsupportedDomainCount = 0;
        $migNoneCount = 0;
        $migValueFixCount = 0;
        $migDomainCount = 0;
        $migReviewCount = 0;
        $migHandoffCount = 0;
        $migNaCount = 0;
        foreach ($tokens as $row) {
            if (!self::isOperationalFinding($row)) {
                continue;
            }
            $isAware = !empty($row['theme_aware']);
            if ($isAware) {
                $awareCount++;
            } else {
                $unawareCount++;
            }
            $cat = (string)($row['scan_category'] ?? '');
            match ($cat) {
                'token_definition' => $tokenDefCount++,
                'token_consumer' => $tokenConsumerCount++,
                'visual_literal' => $visualLiteralCount++,
                'structural_out_of_scope' => $structuralCount++,
                'semantic_token_misuse' => $semanticMisuseCount++,
                default => null,
            };
            $scope = (string)($row['compliance_scope'] ?? 'in_scope');
            if ($scope === 'in_scope') {
                $inScopeCount++;
                if ($isAware) {
                    $inScopeAwareCount++;
                } else {
                    $inScopeUnawareCount++;
                }
            } else {
                $evidenceOnlyCount++;
            }
            if (!empty($row['is_effect_candidate'])) {
                $effectCount++;
            }
            if (!empty($row['is_dynamic'])) {
                $dynamicCount++;
            }
            if (!empty($row['is_print_style'])) {
                $printCount++;
            }
            match ((string)($row['style_domain'] ?? 'owner_surface')) {
                self::DOMAIN_THEME => $themeDomainCount++,
                self::DOMAIN_SHELL_FOUNDATION => $shellFoundationDomainCount++,
                self::DOMAIN_SPECIAL_EFFECT => $specialEffectDomainCount++,
                self::DOMAIN_STRUCTURAL => $structuralDomainCount++,
                self::DOMAIN_PRINT_PDF => $printPdfDomainCount++,
                self::DOMAIN_UNSUPPORTED => $unsupportedDomainCount++,
                default => $ownerSurfaceDomainCount++,
            };
            match ((string)($row['migration_state'] ?? self::MIGRATION_STATE_NONE)) {
                self::MIGRATION_STATE_NONE => $migNoneCount++,
                self::MIGRATION_STATE_VALUE_FIX => $migValueFixCount++,
                self::MIGRATION_STATE_DOMAIN_MIGRATION => $migDomainCount++,
                self::MIGRATION_STATE_CLASSIFICATION_REVIEW => $migReviewCount++,
                self::MIGRATION_STATE_FUTURE_HANDOFF => $migHandoffCount++,
                self::MIGRATION_STATE_NOT_APPLICABLE => $migNaCount++,
                default => null,
            };
        }
        return [
            'total_tokens' => count($tokens),
            'theme_aware' => $awareCount,
            'unaware' => $unawareCount,
            'repairable' => count($candidates),
            'boundary_findings' => count($boundary),
            'declarations' => count($tokens),
            'in_scope' => $inScopeCount,
            'in_scope_aware' => $inScopeAwareCount,
            'in_scope_unaware' => $inScopeUnawareCount,
            'evidence_only' => $evidenceOnlyCount,
            'token_definitions' => $tokenDefCount,
            'token_consumers' => $tokenConsumerCount,
            'visual_literals' => $visualLiteralCount,
            'structural' => $structuralCount,
            'effect_candidates' => $effectCount,
            'dynamic_unsupported' => $dynamicCount,
            'print_pdf' => $printCount,
            'semantic_token_misuse' => $semanticMisuseCount,
            'domain_theme' => $themeDomainCount,
            'domain_shell_foundation' => $shellFoundationDomainCount,
            'domain_owner_surface' => $ownerSurfaceDomainCount,
            'domain_special_effect' => $specialEffectDomainCount,
            'domain_structural' => $structuralDomainCount,
            'domain_print_pdf' => $printPdfDomainCount,
            'domain_unsupported' => $unsupportedDomainCount,
            'migration_none' => $migNoneCount,
            'migration_value_fix' => $migValueFixCount,
            'migration_domain' => $migDomainCount,
            'migration_review' => $migReviewCount,
            'migration_handoff' => $migHandoffCount,
            'migration_not_applicable' => $migNaCount,
            'foundation_concerns' => count($foundationConcerns),
        ];
    }

    private static function emptySummary(): array
    {
        return [
            'total_tokens' => 0,
            'theme_aware' => 0,
            'unaware' => 0,
            'repairable' => 0,
            'boundary_findings' => 0,
            'foundation_concerns' => 0,
            'declarations' => 0,
            'in_scope' => 0,
            'evidence_only' => 0,
            'token_definitions' => 0,
            'token_consumers' => 0,
            'visual_literals' => 0,
            'structural' => 0,
            'effect_candidates' => 0,
            'dynamic_unsupported' => 0,
            'print_pdf' => 0,
            'semantic_token_misuse' => 0,
            'domain_theme' => 0,
            'domain_shell_foundation' => 0,
            'domain_owner_surface' => 0,
            'domain_special_effect' => 0,
            'domain_structural' => 0,
            'domain_print_pdf' => 0,
            'domain_unsupported' => 0,
            'migration_none' => 0,
            'migration_value_fix' => 0,
            'migration_domain' => 0,
            'migration_review' => 0,
            'migration_handoff' => 0,
            'migration_not_applicable' => 0,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function governanceNote(string $scope, array $scopeDescriptor, int $fileCount): array
    {
        return [
            'scan_timestamp' => date('c'),
            'scope' => $scope,
            'scanned_root' => $scopeDescriptor['relative'] ?? '',
            'files_scanned' => $fileCount,
            'exclusions' => [
                'node_modules/**',
                'vendor/**',
                '*.min.css',
                'non-CSS/PHP files',
                'JavaScript literal colors',
            ],
            'writes_performed' => 'none',
            'snapshot_written' => 'none',
            'apply_authorized' => false,
            'tool_phase' => 'read-only MVP',
        ];
    }

    private static function normalizeOwnerKey(string $key): string
    {
        $trim = trim($key);
        if ($trim === '') {
            return '';
        }
        // Disallow traversal.
        if (str_contains($trim, '..')) {
            return '';
        }
        return $trim;
    }

    /**
     * @return array<int,string>
     */
    private static function childDirectories(string $dir): array
    {
        $real = realpath($dir);
        if (!is_string($real) || !is_dir($real)) {
            return [];
        }
        $children = @scandir($real);
        if (!is_array($children)) {
            return [];
        }
        $out = [];
        foreach ($children as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $real . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $out[] = $path;
            }
        }
        sort($out, SORT_STRING);
        return $out;
    }

    private static function isInsideAppRoot(string $path): bool
    {
        $root = realpath(APP_ROOT);
        if (!is_string($root)) {
            return false;
        }
        return str_starts_with($path, $root . DIRECTORY_SEPARATOR)
            || str_starts_with($path, $root . '/');
    }

    private static function relativePath(string $absolute): string
    {
        $root = realpath(APP_ROOT);
        if (!is_string($root)) {
            return $absolute;
        }
        if (str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)) {
            return ltrim(substr($absolute, strlen($root)), DIRECTORY_SEPARATOR);
        }
        if (str_starts_with($absolute, $root . '/')) {
            return ltrim(substr($absolute, strlen($root)), '/');
        }
        return $absolute;
    }

    private static function lineNumberAtOffset(string $contents, int $offset): int
    {
        if ($offset <= 0) {
            return 1;
        }
        return substr_count(substr($contents, 0, $offset), "\n") + 1;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function processRuleBlocks(string $contents, string $file, bool $isThemeSourceFile, string $parentSelector = '', string $atRuleContext = '', string $sourceType = 'css', int $fileContentOffset = 0): array
    {
        $tokens = [];
        $blocks = self::splitBlocksRaw($contents);

        foreach ($blocks as $block) {
            $selector = trim($parentSelector !== '' ? $parentSelector . ' ' . $block['selector'] : $block['selector']);
            $body = $block['body'];
            $absBodyOffset = $fileContentOffset + $block['body_offset'];

            // At-rule detection
            if (preg_match('/^@\w+/', $selector, $m)) {
                $newAtRule = trim($atRuleContext !== '' ? $atRuleContext . ' ' . $selector : $selector);
                array_push($tokens, ...self::processRuleBlocks($body, $file, $isThemeSourceFile, '', $newAtRule, $sourceType, $absBodyOffset));
            } else {
                $isThemeAware = $isThemeSourceFile || self::selectorLooksThemeAware($selector);
                array_push($tokens, ...self::scanDeclarationBody(
                    $body,
                    $file,
                    $selector,
                    $isThemeAware,
                    $isThemeSourceFile,
                    $atRuleContext,
                    $absBodyOffset,
                    $sourceType
                ));
            }
        }

        return $tokens;
    }

    /**
     * @return array<int,array{text:string,offset:int}>
     */
    private static function parseDeclarations(string $body, int $bodyOffset): array
    {
        $declarations = [];
        $len = strlen($body);
        $i = 0;
        $start = 0;

        while ($i < $len) {
            $ch = $body[$i];

            // Skip strings
            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $i++;
                while ($i < $len && $body[$i] !== $quote) {
                    if ($body[$i] === '\\') {
                        $i++;
                    }
                    $i++;
                }
                $i++;
                continue;
            }

            // Skip CSS comments
            if ($i < $len - 1 && $ch === '/' && $body[$i + 1] === '*') {
                $i += 2;
                while ($i < $len - 1 && !($body[$i] === '*' && $body[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }

            // Skip parenthesized groups (e.g. rgb(), var(), calc(), etc.)
            if ($ch === '(') {
                $depth = 1;
                $i++;
                while ($i < $len && $depth > 0) {
                    if ($body[$i] === '(') {
                        $depth++;
                    } elseif ($body[$i] === ')') {
                        $depth--;
                    }
                    $i++;
                }
                continue;
            }

            // Declaration separator
            if ($ch === ';') {
                $text = self::stripCssComments(trim(substr($body, $start, $i - $start)));
                if ($text !== '') {
                    $declarations[] = ['text' => $text, 'offset' => $bodyOffset + $start];
                }
                $start = $i + 1;
            }

            $i++;
        }

        // Trailing declaration (no semicolon)
        $text = self::stripCssComments(trim(substr($body, $start)));
        if ($text !== '') {
            $declarations[] = ['text' => $text, 'offset' => $bodyOffset + $start];
        }

        return $declarations;
    }

    private static function stripCssComments(string $text): string
    {
        return trim(preg_replace('/\/\*.*?\*\//s', '', $text));
    }

    private static function isVisualProperty(string $property): bool
    {
        static $visual = null;
        if ($visual === null) {
            $visual = [
                'color' => true,
                'background' => true,
                'background-color' => true,
                'background-image' => true,
                'background-gradient' => true,
                'border-color' => true,
                'border-top-color' => true,
                'border-right-color' => true,
                'border-bottom-color' => true,
                'border-left-color' => true,
                'border' => true,
                'border-top' => true,
                'border-right' => true,
                'border-bottom' => true,
                'border-left' => true,
                'outline' => true,
                'outline-color' => true,
                'box-shadow' => true,
                'text-shadow' => true,
                'fill' => true,
                'stroke' => true,
                'opacity' => true,
                'filter' => true,
                'backdrop-filter' => true,
                'caret-color' => true,
                'accent-color' => true,
                'column-rule-color' => true,
                'text-decoration-color' => true,
                'text-emphasis-color' => true,
                'stop-color' => true,
                'flood-color' => true,
                'lighting-color' => true,
                'mask' => true,
                'clip-path' => true,
            ];
        }
        return isset($visual[strtolower($property)]);
    }

    private static function isStructuralProperty(string $property): bool
    {
        static $structural = null;
        if ($structural === null) {
            $structural = [
                'display' => true, 'position' => true,
                'width' => true, 'min-width' => true, 'max-width' => true,
                'height' => true, 'min-height' => true, 'max-height' => true,
                'margin' => true, 'margin-top' => true, 'margin-right' => true,
                'margin-bottom' => true, 'margin-left' => true,
                'padding' => true, 'padding-top' => true, 'padding-right' => true,
                'padding-bottom' => true, 'padding-left' => true,
                'gap' => true, 'row-gap' => true, 'column-gap' => true,
                'grid' => true, 'grid-template' => true, 'grid-template-columns' => true,
                'grid-template-rows' => true, 'grid-column' => true, 'grid-row' => true,
                'grid-column-start' => true, 'grid-column-end' => true,
                'grid-row-start' => true, 'grid-row-end' => true, 'grid-area' => true,
                'flex' => true, 'flex-basis' => true, 'flex-grow' => true, 'flex-shrink' => true,
                'flex-direction' => true, 'flex-wrap' => true,
                'align-items' => true, 'align-content' => true, 'align-self' => true,
                'justify-content' => true, 'justify-items' => true, 'justify-self' => true,
                'order' => true, 'overflow' => true, 'overflow-x' => true, 'overflow-y' => true,
                'z-index' => true, 'transform' => true, 'transform-origin' => true,
                'transition' => true, 'transition-property' => true, 'transition-duration' => true,
                'transition-timing-function' => true, 'transition-delay' => true,
                'animation' => true, 'animation-name' => true, 'animation-duration' => true,
                'animation-timing-function' => true, 'animation-delay' => true,
                'animation-iteration-count' => true, 'animation-direction' => true,
                'cursor' => true, 'pointer-events' => true,
                'font-size' => true, 'font-family' => true, 'font-weight' => true,
                'font-style' => true, 'line-height' => true,
                'border-radius' => true, 'border-top-left-radius' => true,
                'border-top-right-radius' => true, 'border-bottom-left-radius' => true,
                'border-bottom-right-radius' => true,
                'text-align' => true, 'vertical-align' => true,
                'white-space' => true, 'word-break' => true, 'overflow-wrap' => true,
                'visibility' => true, 'float' => true, 'clear' => true,
                'top' => true, 'right' => true, 'bottom' => true, 'left' => true,
                'inset' => true, 'inset-inline' => true, 'inset-block' => true,
                'object-fit' => true, 'object-position' => true,
                'table-layout' => true, 'border-collapse' => true, 'border-spacing' => true,
                'list-style' => true, 'list-style-type' => true, 'list-style-position' => true,
                'content' => true, 'counter-increment' => true, 'counter-reset' => true,
                'clip' => true, 'clip-path' => true,
                'resize' => true, 'user-select' => true, 'scroll-behavior' => true,
                'aspect-ratio' => true, 'box-sizing' => true,
            ];
        }
        return isset($structural[strtolower($property)]);
    }

    private static function isEffectProperty(string $property): bool
    {
        $lower = strtolower($property);
        return $lower === 'filter' || $lower === 'backdrop-filter' || $lower === 'mix-blend-mode' || $lower === 'isolation' || $lower === 'background-blend-mode';
    }

    private static function isStyleProperty(string $property): bool
    {
        return self::isVisualProperty($property) || self::isStructuralProperty($property) || self::isEffectProperty($property);
    }

    private static function isPrintRelated(string $relativeFile): bool
    {
        foreach (self::PRINT_PATH_PATTERNS as $pattern) {
            if (preg_match($pattern, $relativeFile)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<int,string>
     */
    private static function extractTokenReferences(string $value): array
    {
        $refs = [];
        if (preg_match_all('/var\(\s*(--[\w-]+)/i', $value, $matches)) {
            foreach ($matches[1] as $name) {
                $refs[] = (string)$name;
            }
        }
        return $refs;
    }

    /**
     * @param array<int,string> $tokenReferences
     * @return array<string,string>
     */
    private static function detectSemanticTokenMisuse(string $property, array $tokenReferences): array
    {
        if ($tokenReferences === []) {
            return [];
        }

        $propertyRole = self::semanticPropertyRole($property);
        if ($propertyRole === '') {
            return [];
        }

        foreach ($tokenReferences as $token) {
            $tokenRole = self::semanticTokenRole($token);
            if ($tokenRole === '' || $tokenRole === $propertyRole) {
                continue;
            }

            return [
                'token' => $token,
                'property_role' => $propertyRole,
                'token_role' => $tokenRole,
                'reason' => "Token {$token} is a {$tokenRole} token used on a {$propertyRole} property.",
            ];
        }

        return [];
    }

    private static function semanticPropertyRole(string $property): string
    {
        $lower = strtolower(trim($property));
        if (in_array($lower, ['background', 'background-color', 'background-image'], true)) {
            return 'background';
        }
        if (in_array($lower, ['color', 'fill', 'stroke', 'caret-color', 'accent-color', 'text-decoration-color', 'text-emphasis-color'], true)) {
            return 'text';
        }
        if ($lower === 'border' || str_starts_with($lower, 'border-') || $lower === 'outline' || $lower === 'outline-color' || $lower === 'column-rule' || $lower === 'column-rule-color') {
            return 'border';
        }

        return '';
    }

    private static function semanticTokenRole(string $token): string
    {
        $lower = strtolower(trim($token));
        if (preg_match('/-(bg|background)$/', $lower) === 1) {
            return 'background';
        }
        if (preg_match('/-(text|foreground|fg)$/', $lower) === 1) {
            return 'text';
        }
        if (preg_match('/-(border|line|stroke)$/', $lower) === 1) {
            return 'border';
        }
        if (preg_match('/^--tone-[a-z0-9-]+$/', $lower) === 1 && preg_match('/-(bg|background|text|foreground|fg|border|line|stroke)$/', $lower) !== 1) {
            return 'raw-tone';
        }

        return '';
    }

    private static function classifyValueConstruct(string $value): string
    {
        $v = strtolower(trim($value));
        if ($v === '') {
            return 'empty';
        }
        if (str_contains($v, 'var(')) {
            return 'alias';
        }
        if (preg_match('/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v) === 1) {
            return 'literal_color';
        }
        if (preg_match('/^(rgb|rgba|hsl|hsla|oklch|oklab|lab|lch|color)\s*\(/i', $v) === 1) {
            return 'literal_color';
        }
        if (preg_match('/\b(gradient|linear-gradient|radial-gradient|conic-gradient|repeating-linear-gradient|repeating-radial-gradient|repeating-conic-gradient)\s*\(/i', $v) === 1) {
            return 'gradient';
        }
        if (preg_match('/^(\d+px\s+)?(\d+px\s+)?(inset\s+)?(#[0-9a-f]|rgba?|hsla?)/i', $v) === 1 && (str_contains($v, 'shadow') || str_contains($v, 'drop-shadow'))) {
            return 'shadow';
        }
        if (str_contains($v, 'drop-shadow(') || str_contains($v, 'blur(') || str_contains($v, 'brightness(') || str_contains($v, 'contrast(') || str_contains($v, 'grayscale(') || str_contains($v, 'hue-rotate(') || str_contains($v, 'invert(') || str_contains($v, 'saturate(') || str_contains($v, 'sepia(') || str_contains($v, 'opacity(')) {
            return 'filter';
        }
        if ($v === '0' || $v === 'none' || $v === 'transparent' || $v === 'currentcolor' || $v === 'inherit' || $v === 'initial' || $v === 'unset') {
            return 'keyword';
        }
        if (preg_match('/^#[0-9a-f]/i', $v) === 1 || preg_match('/\b(rgb|hsl|oklch|oklab)\s*\(/i', $v) === 1) {
            return 'literal_color';
        }
        return 'unsupported';
    }

    private static function normalizeValue(string $value): string
    {
        $v = trim($value);
        // Sort var() references for canonical comparison
        $refs = self::extractTokenReferences($v);
        if (count($refs) > 1) {
            sort($refs, SORT_STRING);
            $sorted = implode(', ', $refs);
            $v = preg_replace_callback('/var\(\s*--[\w-]+\s*(?:,\s*[^)]+)?\)/i', static function () use (&$sorted) {
                return 'var(' . $sorted . ')';
            }, $v, 1);
        }
        return strtolower($v);
    }

    private static function lineNumberAtOffsetForContent(string $body, int $bodyOffset, int $declOffset): int
    {
        $relativeOffset = $declOffset - $bodyOffset;
        if ($relativeOffset <= 0) {
            return self::lineNumberAtOffset('', 0) + self::lineNumberAtOffset('', $bodyOffset);
        }
        // Line within body from body start
        $lineInBody = substr_count(substr($body, 0, $relativeOffset), "\n") + 1;
        // Line within file from file start
        // We approximate by counting newlines in the body before this point
        // and adding to the file-level line of bodyOffset
        $fileStartLine = self::lineNumberAtOffset('', $bodyOffset);
        return $fileStartLine + $lineInBody - 1;
    }
}
