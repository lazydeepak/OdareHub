<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

final class OwnerStructureContractV2DiagnosisService
{
    private const STATUS_NATIVE = 'Native v2';
    private const STATUS_MIGRATION = 'Migration required';
    private const STATUS_ACCEPTED_LEGACY = 'Current Legacy';
    private const STATUS_CLEANUP = 'Cleanup required';
    private const STATUS_INVALID = 'Invalid/unknown';
    private const DECOMPOSITION_MIN_LINES = 900;

    /**
     * @param array<string,mixed> $scanResult
     * @return array<string,mixed>
     */
    public static function diagnose(array $scanResult): array
    {
        $ownerRoot = (string)($scanResult['owner_root_relative_path'] ?? '');
        $entries = isset($scanResult['entries']) && is_array($scanResult['entries']) ? $scanResult['entries'] : [];
        $byOwnerPath = self::indexByOwnerPath($entries);
        $findings = [];
        $decompositionCandidates = [];

        self::addNativeFindings($findings, $ownerRoot, $byOwnerPath);
        self::addMigrationFindings($findings, $ownerRoot, $byOwnerPath);
        self::addCleanupFindings($findings, $ownerRoot, $entries);
        self::addAcceptedLegacyFindings($findings, $ownerRoot, $entries, $decompositionCandidates);
        self::addInvalidUnknownFindings($findings, $ownerRoot, $entries);
        self::sortDecompositionCandidates($decompositionCandidates);
        $shellDomainModel = self::buildShellDomainModel($ownerRoot, $byOwnerPath);

        usort($findings, static function (array $a, array $b): int {
            $severityOrder = ['high' => 0, 'medium' => 1, 'low' => 2, 'info' => 3];
            $aSeverity = $severityOrder[(string)($a['severity'] ?? 'info')] ?? 4;
            $bSeverity = $severityOrder[(string)($b['severity'] ?? 'info')] ?? 4;
            if ($aSeverity !== $bSeverity) {
                return $aSeverity <=> $bSeverity;
            }
            return strcasecmp((string)($a['current_path'] ?? ''), (string)($b['current_path'] ?? ''));
        });

        return [
            'contract_version' => 'Owner Contract v2',
            'summary' => self::summary($findings),
            'findings' => $findings,
            'decomposition_candidates' => $decompositionCandidates,
            'shell_domain_model' => $shellDomainModel,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<string,array<string,mixed>>
     */
    private static function indexByOwnerPath(array $entries): array
    {
        $indexed = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $ownerPath = (string)($entry['owner_relative_path'] ?? '');
            if ($ownerPath !== '') {
                $indexed[$ownerPath] = $entry;
            }
        }
        return $indexed;
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @param array<string,array<string,mixed>> $byOwnerPath
     */
    private static function addNativeFindings(array &$findings, string $ownerRoot, array $byOwnerPath): void
    {
        $nativePaths = [
            'routes.php' => 'Owner routes remain a native root contract file in v2.',
            'Controllers' => 'Controller folder already matches the v2 owner skeleton.',
            'Services' => 'Service folder already matches the v2 owner skeleton.',
            'Views' => 'View folder already matches the v2 owner skeleton.',
            'Resources' => 'Resources folder already matches the v2 owner skeleton.',
            'Resources/lang' => 'Localization resources are physically placed correctly.',
            'Resources/labels' => 'Label resources are physically placed correctly.',
            'Resources/labels/contexts' => 'Label contexts are physically placed correctly.',
            'Resources/labels/templates' => 'Label templates are physically placed correctly.',
            'Resources/labels/rules' => 'Label rules are physically placed correctly.',
        ];
        if (self::isShellOwnerRoot($ownerRoot)) {
            $nativePaths += [
                'AGENTS.md' => 'Shell root agent instructions are an intentional owner documentation contract.',
                'dashboard_widgets.php' => 'Shell dashboard widget composition config remains an intentional Shell root contract file.',
                'layout_contract.php' => 'Shell layout ownership metadata remains an intentional Shell root contract file.',
                'sidebar.php' => 'Shell sidebar taxonomy config remains an intentional Shell root contract file.',
                'styles' => 'Shell runtime CSS is an intentional Shell domain loaded through the shell.css manifest.',
                'DesignSystem' => 'Shell design-system governance, contracts, diagnostics, and socket catalog live under the Shell DesignSystem domain.',
                'Overlay' => 'Shell overlay framework implementation package lives under the approved Overlay runtime domain.',
                'Resources/css/essential' => 'Shell boot and pre-auth essential CSS is an intentional first-boot domain.',
                'Resources/rendering' => 'Rendering foundation CSS is an intentional Shell-owned foundation domain.',
                'Composers' => 'Shell composition classes are an intentional Shell composition layer.',
                'sidebar_sources' => 'Sidebar source definition files are intentional Shell navigation-source inputs.',
            ];
        }

        foreach ($nativePaths as $ownerPath => $reason) {
            if (!isset($byOwnerPath[$ownerPath])) {
                continue;
            }
            $findings[] = self::finding(
                self::joinPath($ownerRoot, $ownerPath),
                self::joinPath($ownerRoot, $ownerPath),
                self::STATUS_NATIVE,
                'info',
                $reason,
                'Keep this location as part of the v2 contract.'
            );
        }
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @param array<string,array<string,mixed>> $byOwnerPath
     */
    private static function addMigrationFindings(array &$findings, string $ownerRoot, array $byOwnerPath): void
    {
        $migrations = [
            'plugin.json' => [
                'target' => 'manifest.json',
                'severity' => 'high',
                'reason' => 'v2 names the owner manifest manifest.json; this owner still uses plugin.json.',
                'recommendation' => 'Plan a manifest migration that preserves current metadata and runtime compatibility.',
            ],
            'menu.php' => [
                'target' => 'navigation.php',
                'severity' => 'medium',
                'reason' => 'v2 names navigation contribution navigation.php; this owner still uses menu.php.',
                'recommendation' => 'Plan a navigation contract migration after confirming the runtime loader supports navigation.php.',
            ],
            'bootstrap.php' => [
                'target' => 'lifecycle/bootstrap.php',
                'severity' => 'medium',
                'reason' => 'v2 moves lifecycle hooks under lifecycle/.',
                'recommendation' => 'Move only after the module loader supports lifecycle hook discovery.',
            ],
            'install.php' => [
                'target' => 'lifecycle/install.php',
                'severity' => 'medium',
                'reason' => 'v2 moves lifecycle hooks under lifecycle/.',
                'recommendation' => 'Move only after install tooling supports lifecycle hook discovery.',
            ],
            'uninstall.php' => [
                'target' => 'lifecycle/uninstall.php',
                'severity' => 'medium',
                'reason' => 'v2 moves lifecycle hooks under lifecycle/.',
                'recommendation' => 'Move only after uninstall tooling supports lifecycle hook discovery.',
            ],
            'migrations' => [
                'target' => 'Database/migrations',
                'severity' => 'medium',
                'reason' => 'v2 places database migrations under Database/migrations/.',
                'recommendation' => 'Plan a database folder migration with migration runner compatibility checks.',
            ],
            'dashboard.php' => [
                'target' => 'Views/dashboard.php',
                'severity' => 'low',
                'reason' => 'v2 treats dashboard rendering as a view unless runtime confirms root dashboard.php is a required hook.',
                'recommendation' => 'Confirm dashboard hook loading before deciding whether to move or preserve as accepted legacy.',
            ],
        ];

        foreach ($migrations as $ownerPath => $rule) {
            if (!isset($byOwnerPath[$ownerPath])) {
                continue;
            }
            $findings[] = self::finding(
                self::joinPath($ownerRoot, $ownerPath),
                self::joinPath($ownerRoot, (string)$rule['target']),
                self::STATUS_MIGRATION,
                (string)$rule['severity'],
                (string)$rule['reason'],
                (string)$rule['recommendation']
            );
        }

        if (self::isShellOwnerRoot($ownerRoot) && isset($byOwnerPath['Style'])) {
            $findings[] = self::finding(
                self::joinPath($ownerRoot, 'Style'),
                self::joinPath($ownerRoot, 'DesignSystem'),
                self::STATUS_MIGRATION,
                'medium',
                'Shell style-governance assets are being standardized under the DesignSystem domain path.',
                'Keep runtime behavior unchanged in this slice and move Style artifacts to DesignSystem in a dedicated structural migration slice.'
            );
        }

        if (!isset($byOwnerPath['lifecycle/upgrade.php'])) {
            $findings[] = self::finding(
                '',
                self::joinPath($ownerRoot, 'lifecycle/upgrade.php'),
                self::STATUS_MIGRATION,
                'low',
                'v2 includes an upgrade lifecycle hook; this owner has no current upgrade hook file.',
                'Add only when an owner upgrade workflow is defined.'
            );
        }
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @param array<int,array<string,mixed>> $entries
     */
    private static function addCleanupFindings(array &$findings, string $ownerRoot, array $entries): void
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $ownerPath = (string)($entry['owner_relative_path'] ?? '');
            $name = (string)($entry['name'] ?? '');
            if ($name === '.DS_Store') {
                $findings[] = self::finding(
                    self::joinPath($ownerRoot, $ownerPath),
                    '',
                    self::STATUS_CLEANUP,
                    'high',
                    'macOS metadata is not an owner artifact.',
                    'Remove from the owner tree and prevent recurrence with ignore rules.'
                );
                continue;
            }
            if (str_ends_with($name, '.metadata-migration-backup')) {
                $findings[] = self::finding(
                    self::joinPath($ownerRoot, $ownerPath),
                    '',
                    self::STATUS_CLEANUP,
                    'medium',
                    'Metadata migration backups should not remain beside canonical label resources.',
                    'Archive outside the owner resource tree or remove after migration acceptance.'
                );
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @param array<int,array<string,mixed>> $entries
     */
    private static function addAcceptedLegacyFindings(array &$findings, string $ownerRoot, array $entries, array &$decompositionCandidates): void
    {
        foreach ($entries as $entry) {
            if (!is_array($entry) || (string)($entry['type'] ?? '') !== 'file') {
                continue;
            }
            $ownerPath = (string)($entry['owner_relative_path'] ?? '');
            $name = (string)($entry['name'] ?? '');
            $physicalPath = (string)($entry['physical_path'] ?? '');
            $contents = is_file($physicalPath) ? (string)@file_get_contents($physicalPath) : '';
            if (str_ends_with($name, '.php') && str_contains($contents, 'namespace Plugins\\Products')) {
                $findings[] = self::finding(
                    self::joinPath($ownerRoot, $ownerPath),
                    '',
                    self::STATUS_ACCEPTED_LEGACY,
                    'info',
                    'The file uses the legacy Plugins\\Products namespace, which remains accepted legacy for this owner.',
                    'Keep compatible for now; consider namespace migration only in a dedicated runtime-safe pass.'
                );
            }
            $lineCount = $contents === '' ? 0 : substr_count($contents, "\n") + 1;
            if ($lineCount >= self::DECOMPOSITION_MIN_LINES && self::isDecompositionCandidateExtension($name)) {
                $decompositionCandidates[] = self::decompositionCandidate(
                    self::joinPath($ownerRoot, $ownerPath),
                    $lineCount,
                    (int)($entry['size'] ?? 0),
                    self::decompositionCategory($ownerPath, $name),
                    'Large file detected; this is an engineering improvement opportunity, not an Owner Contract violation.',
                    'Consider later decomposition after contract and runtime behavior are stable.'
                );
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @param array<int,array<string,mixed>> $entries
     */
    private static function addInvalidUnknownFindings(array &$findings, string $ownerRoot, array $entries): void
    {
        $knownRootFiles = [
            'plugin.json',
            'manifest.json',
            'routes.php',
            'menu.php',
            'navigation.php',
            'bootstrap.php',
            'install.php',
            'uninstall.php',
            'dashboard.php',
        ];
        $knownRootFolders = [
            '.',
            'Controllers',
            'Services',
            'Views',
            'Resources',
            'migrations',
            'lifecycle',
            'Database',
            'Tests',
            'Docs',
        ];
        if (self::isShellOwnerRoot($ownerRoot)) {
            $knownRootFiles = array_merge($knownRootFiles, [
                'AGENTS.md',
                'dashboard_widgets.php',
                'layout_contract.php',
                'sidebar.php',
            ]);
            $knownRootFolders = array_merge($knownRootFolders, [
                'styles',
                'Style',
                'DesignSystem',
                'Overlay',
                'Composers',
                'sidebar_sources',
            ]);
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $ownerPath = (string)($entry['owner_relative_path'] ?? '');
            if ($ownerPath === '' || str_contains($ownerPath, '/')) {
                continue;
            }
            $type = (string)($entry['type'] ?? 'file');
            $known = $type === 'folder'
                ? in_array($ownerPath, $knownRootFolders, true)
                : in_array($ownerPath, $knownRootFiles, true);
            if ($known || $ownerPath === '.DS_Store') {
                continue;
            }
            $findings[] = self::finding(
                self::joinPath($ownerRoot, $ownerPath),
                '',
                self::STATUS_INVALID,
                'low',
                'Root-level artifact is not part of the v2 skeleton or the accepted Products legacy mapping.',
                'Review ownership and either classify it explicitly or move it into the appropriate v2 folder.'
            );
        }
    }

    /**
     * @param array<int,array<string,mixed>> $findings
     * @return array<string,mixed>
     */
    private static function summary(array $findings): array
    {
        $counts = [
            self::STATUS_NATIVE => 0,
            self::STATUS_MIGRATION => 0,
            self::STATUS_ACCEPTED_LEGACY => 0,
            self::STATUS_CLEANUP => 0,
            self::STATUS_INVALID => 0,
        ];
        foreach ($findings as $finding) {
            $status = (string)($finding['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }
        return [
            'total_findings' => count($findings),
            'counts' => $counts,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function finding(
        string $currentPath,
        string $targetPath,
        string $status,
        string $severity,
        string $reason,
        string $recommendation
    ): array {
        return [
            'current_path' => $currentPath,
            'target_path' => $targetPath,
            'status' => $status,
            'severity' => $severity,
            'reason' => $reason,
            'recommendation' => $recommendation,
            'auto_fix_eligible' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function decompositionCandidate(
        string $currentPath,
        int $lineCount,
        int $size,
        string $category,
        string $reason,
        string $recommendation
    ): array {
        return [
            'current_path' => $currentPath,
            'line_count' => $lineCount,
            'size' => $size,
            'category' => $category,
            'reason' => $reason,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $candidates
     */
    private static function sortDecompositionCandidates(array &$candidates): void
    {
        usort($candidates, static function (array $a, array $b): int {
            $lineCompare = (int)($b['line_count'] ?? 0) <=> (int)($a['line_count'] ?? 0);
            if ($lineCompare !== 0) {
                return $lineCompare;
            }
            return strcasecmp((string)($a['current_path'] ?? ''), (string)($b['current_path'] ?? ''));
        });
    }

    private static function isShellOwnerRoot(string $ownerRoot): bool
    {
        return trim(str_replace('\\', '/', $ownerRoot), '/') === 'apps/Shell';
    }

    private static function isDecompositionCandidateExtension(string $name): bool
    {
        foreach (['.php', '.css', '.js', '.mjs', '.ts'] as $extension) {
            if (str_ends_with($name, $extension)) {
                return true;
            }
        }
        return false;
    }

    private static function decompositionCategory(string $ownerPath, string $name): string
    {
        $normalized = trim(str_replace('\\', '/', $ownerPath), '/');
        if ($normalized === 'routes.php' || $name === 'routes.php') {
            return 'Routing surface';
        }
        if (str_starts_with($normalized, 'Composers/')) {
            return 'Composition layer';
        }
        if (str_starts_with($normalized, 'styles/') || str_ends_with(strtolower($name), '.css')) {
            return 'Stylesheet';
        }
        return 'Implementation file';
    }

    private static function joinPath(string $root, string $path): string
    {
        if ($root === '') {
            return $path;
        }
        if ($path === '') {
            return $root;
        }
        return rtrim($root, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param array<string,array<string,mixed>> $byOwnerPath
     * @return array<string,mixed>
     */
    private static function buildShellDomainModel(string $ownerRoot, array $byOwnerPath): array
    {
        if (!self::isShellOwnerRoot($ownerRoot)) {
            return [];
        }

        return [
            'model_version' => 'Shell Domain Model v1',
            'domains' => [
                [
                    'key' => 'runtime',
                    'label' => 'Runtime',
                    'purpose' => 'Owns executable Shell behavior, composition assembly, runtime orchestration, and runtime surface rendering.',
                    'architectural_domains' => [
                        self::domainPathMeta($byOwnerPath, 'Composers', 'Composition domain that assembles runtime view models.'),
                        self::domainPathMeta($byOwnerPath, 'Services', 'Runtime orchestration domain for executable Shell behavior.'),
                        self::domainPathMeta($byOwnerPath, 'Views', 'Rendering domain for Shell runtime surfaces.'),
                        self::domainPathMeta($byOwnerPath, 'Resources', 'Runtime asset domain consumed by Shell surfaces.'),
                        self::domainPathMeta($byOwnerPath, 'Overlay', 'Overlay framework implementation package for Shell-managed overlay orchestration.'),
                    ],
                    'implementation_folders' => [
                        self::domainPathMeta($byOwnerPath, 'Services/OperatorLayerAdapters', 'Operator runtime data adapters used by Shell surfaces.'),
                        self::domainPathMeta($byOwnerPath, 'Views/operator', 'Operator runtime view implementations.'),
                        self::domainPathMeta($byOwnerPath, 'Views/display', 'Display runtime view implementations.'),
                    ],
                ],
                [
                    'key' => 'styling',
                    'label' => 'Styling',
                    'purpose' => 'Separates runtime CSS delivery from style-governance sources and first-boot essentials.',
                    'architectural_domains' => [
                        self::domainPathMeta($byOwnerPath, 'styles', 'Runtime CSS manifest domain loaded by Shell runtime.'),
                        self::domainPathMeta($byOwnerPath, 'DesignSystem', 'Design-system governance domain for contracts, diagnostics, and socket catalog.'),
                        self::domainPathMeta($byOwnerPath, 'Resources/css/essential', 'First-boot and pre-auth essential CSS domain.'),
                        self::domainPathMeta($byOwnerPath, 'Resources/rendering', 'Rendering foundation CSS domain.'),
                    ],
                    'implementation_folders' => [
                        self::domainPathMeta($byOwnerPath, 'styles/components.css', 'Shared component runtime stylesheet.'),
                        self::domainPathMeta($byOwnerPath, 'styles/operator.css', 'Operator runtime stylesheet.'),
                    ],
                ],
                [
                    'key' => 'contracts',
                    'label' => 'Contracts',
                    'purpose' => 'Defines Shell public interfaces and stable entrypoints for routing, navigation, layout, manifest/bootstrap, and dashboard composition.',
                    'architectural_domains' => [
                        self::domainPathMeta($byOwnerPath, 'routes.php', 'Route contract entrypoint for Shell owner.'),
                        self::domainPathMeta($byOwnerPath, 'navigation.php', 'Navigation contract source for shell menus.'),
                        self::domainPathMeta($byOwnerPath, 'layout_contract.php', 'Layout ownership contract file.'),
                        self::domainPathMeta($byOwnerPath, 'dashboard_widgets.php', 'Dashboard widget composition contract file.'),
                        self::domainPathMeta($byOwnerPath, 'sidebar.php', 'Sidebar taxonomy contract file.'),
                    ],
                    'implementation_folders' => [
                        self::domainPathMeta($byOwnerPath, 'sidebar_sources', 'Sidebar source inputs used to build sidebar contract output.'),
                    ],
                ],
                [
                    'key' => 'quality',
                    'label' => 'Quality',
                    'purpose' => 'Holds tests, engineering documentation, probes, and architecture-validation assets used to verify Shell contracts.',
                    'architectural_domains' => [
                        self::domainPathMeta($byOwnerPath, 'Tests', 'Quality verification domain for tests and probes.'),
                        self::domainPathMeta($byOwnerPath, 'AGENTS.md', 'Owner-local engineering and operational documentation contract.'),
                    ],
                    'implementation_folders' => [
                        self::domainPathMeta($byOwnerPath, 'Resources/lang', 'Localization assets consumed by quality probes and runtime verification surfaces.'),
                    ],
                ],
            ],
            'taxonomy_decisions' => [
                'design_system_vs_styles' => 'DesignSystem owns governance, contracts, diagnostics, design tokens, and style metadata; styles owns runtime CSS assets consumed by Shell; runtime code must not consume DesignSystem metadata directly.',
                'runtime_domain_scope' => 'Runtime domains define executable behavior, composition assembly, orchestration, rendering, and runtime assets; implementation details may evolve without changing this ownership boundary.',
                'additional_domains' => 'No extra top-level domains required in this slice; existing Shell domains cover runtime, styling, contracts, and quality concerns.',
            ],
            'ownership_boundaries' => [
                [
                    'domain' => 'Runtime',
                    'owns' => 'Executes Shell behavior and assembles runtime surfaces.',
                    'never' => 'Never defines theme values or style-governance metadata.',
                ],
                [
                    'domain' => 'DesignSystem',
                    'owns' => 'Defines style governance, contracts, diagnostics, design tokens, and metadata.',
                    'never' => 'Never renders runtime UI or executes runtime behavior directly.',
                ],
                [
                    'domain' => 'styles',
                    'owns' => 'Holds runtime CSS assets that consume approved design tokens.',
                    'never' => 'Must not own business semantics or design-governance policy.',
                ],
                [
                    'domain' => 'Contracts',
                    'owns' => 'Defines public Shell interfaces and stable entrypoints.',
                    'never' => 'Never owns runtime business payloads or style-value governance.',
                ],
                [
                    'domain' => 'Quality',
                    'owns' => 'Provides verification assets: tests, probes, engineering docs, and architecture validation.',
                    'never' => 'Never consumed as runtime behavior payload.',
                ],
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $byOwnerPath
     * @return array<string,mixed>
     */
    private static function domainPathMeta(array $byOwnerPath, string $ownerPath, string $rationale): array
    {
        $exists = self::ownerPathExists($byOwnerPath, $ownerPath);

        return [
            'path' => $ownerPath,
            'status' => $exists ? 'present' : 'missing',
            'rationale' => $rationale,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $byOwnerPath
     */
    private static function ownerPathExists(array $byOwnerPath, string $ownerPath): bool
    {
        if (isset($byOwnerPath[$ownerPath])) {
            return true;
        }

        $prefix = rtrim($ownerPath, '/') . '/';
        foreach (array_keys($byOwnerPath) as $indexedPath) {
            if (str_starts_with((string)$indexedPath, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
