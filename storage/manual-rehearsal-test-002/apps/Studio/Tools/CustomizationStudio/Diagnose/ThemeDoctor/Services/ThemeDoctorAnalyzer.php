<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Diagnose\ThemeDoctor\Services;

final class ThemeDoctorAnalyzer
{
    private const COMPILED_ASSET_PATH = APP_ROOT . '/public/assets/theme.css';
    private const THEME_SOURCES_DIR = APP_ROOT . '/resources/themes';
    private const REQUIRED_TOKEN_SETS = ['base', 'light', 'dark', 'paper', 'liquid-glass'];
    private const COMPILED_SIZE_SUSPICIOUS_THRESHOLD = 1024;
    private const REQUIRED_ASSET_SELECTORS = [
        ':root',
        '[data-theme="light"]',
        '[data-theme="dark"]',
        '[data-color-style="liquid-glass"]',
        '[data-color-style="paper"]',
    ];

    public static function analyze(
        array $registry,
        array $sourceFiles,
        array $availableStyles,
    ): array {
        $findings = [];
        try {
            $registryFound = !empty($registry['approved_registry_found']);
            $approvedThemes = isset($registry['approved_themes']) && is_array($registry['approved_themes'])
                ? $registry['approved_themes'] : [];
            $draftThemes = isset($registry['draft_themes']) && is_array($registry['draft_themes'])
                ? $registry['draft_themes'] : [];
            $activeTheme = trim((string)($registry['active_theme'] ?? ''));
            $defaultTheme = trim((string)($registry['default_theme'] ?? ''));
            $activeThemeSource = trim((string)($registry['active_theme_source'] ?? 'unresolved'));
            $degradedReasons = isset($registry['degraded_reasons']) && is_array($registry['degraded_reasons'])
                ? $registry['degraded_reasons'] : [];

            self::checkRegistry($findings, $registryFound, $approvedThemes, $activeTheme, $defaultTheme, $activeThemeSource);
            self::checkRuntimeFallback($findings, $degradedReasons);
            self::checkCompiledAssets($findings, $sourceFiles);
            self::checkThemeInventory($findings, $approvedThemes, $draftThemes, $availableStyles);
            self::checkTokenSets($findings, $sourceFiles);
        } catch (\Throwable $e) {
            $findings[] = [
                'code' => 'TD900',
                'severity' => 'blocked',
                'title' => 'Analysis Exception',
                'description' => 'The theme analysis pipeline encountered an unexpected error.',
                'recommendation' => 'Check file permissions and availability of theme assets: php scripts/assets/compile_theme_sources.php --apply',
                'evidence' => $e->getMessage(),
            ];
        }

        $healthScore = self::computeHealthScore($findings);

        return [
            'health_score' => $healthScore,
            'findings' => $findings,
        ];
    }

    /**
     * @param list<array<string,mixed>> $findings
     */
    private static function checkRegistry(
        array &$findings,
        bool $registryFound,
        array $approvedThemes,
        string $activeTheme,
        string $defaultTheme,
        string $activeThemeSource,
    ): void {
        if (!$registryFound) {
            $findings[] = [
                'code' => 'TD001',
                'severity' => 'blocked',
                'title' => 'Registry Missing',
                'description' => 'The approved theme registry file (registry.json) does not exist.',
                'recommendation' => 'Restore or recreate: storage/theme_registry/registry.json',
                'evidence' => 'Registry file not found at storage/theme_registry/registry.json',
            ];
            return;
        }

        if ($approvedThemes === []) {
            $findings[] = [
                'code' => 'TD003',
                'severity' => 'warning',
                'title' => 'No Approved Themes',
                'description' => 'The approved registry exists but contains no theme records.',
                'recommendation' => 'Add approved theme records to the registry to enable governed workflows.',
                'evidence' => 'registry.json contains zero theme entries',
            ];
            return;
        }

        $approvedKeys = [];
        foreach ($approvedThemes as $t) {
            $k = strtolower(trim((string)($t['key'] ?? '')));
            if ($k !== '') {
                $approvedKeys[] = $k;
            }
        }

        if ($activeTheme !== '' && !in_array(strtolower($activeTheme), $approvedKeys, true)) {
            $findings[] = [
                'code' => 'TD004',
                'severity' => 'warning',
                'title' => 'Active Theme Not Approved',
                'description' => 'The active theme "' . $activeTheme . '" is not in the approved registry.',
                'recommendation' => 'Add "' . $activeTheme . '" to the approved registry or change the active theme to an approved one.',
                'evidence' => 'Active theme: ' . $activeTheme . ', Source: ' . $activeThemeSource,
            ];
        }

        if ($defaultTheme !== '' && !in_array(strtolower($defaultTheme), $approvedKeys, true)) {
            $findings[] = [
                'code' => 'TD005',
                'severity' => 'info',
                'title' => 'Default Theme Not Approved',
                'description' => 'The default theme "' . $defaultTheme . '" is not recorded in the approved registry.',
                'recommendation' => 'Add "' . $defaultTheme . '" to the approved registry as the default entry.',
                'evidence' => 'Default theme: ' . $defaultTheme,
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $findings
     * @param list<string> $degradedReasons
     */
    private static function checkRuntimeFallback(array &$findings, array $degradedReasons): void
    {
        if (in_array('runtime_fallback_in_use', $degradedReasons, true)) {
            $findings[] = [
                'code' => 'TD002',
                'severity' => 'warning',
                'title' => 'Runtime Fallback Active',
                'description' => 'Runtime theme resolution could not use the approved registry.',
                'recommendation' => 'Restore an approved registry entry and verify registry.json.',
                'evidence' => 'Active theme source: runtime_fallback',
            ];
        }
    }

    /**
     * @param list<array<string,mixed>> $findings
     * @param list<array<string,mixed>> $sourceFiles
     */
    private static function checkCompiledAssets(array &$findings, array $sourceFiles): void
    {
        if (!is_file(self::COMPILED_ASSET_PATH)) {
            $findings[] = [
                'code' => 'TD100',
                'severity' => 'blocked',
                'title' => 'Compiled Asset Missing',
                'description' => 'The compiled theme CSS file (theme.css) does not exist at the runtime path.',
                'recommendation' => 'Run the theme compiler: php scripts/assets/compile_theme_sources.php --apply',
                'evidence' => 'File not found at /public/assets/theme.css',
            ];
            return;
        }

        $compiledSize = filesize(self::COMPILED_ASSET_PATH);
        if ($compiledSize === false || $compiledSize === 0) {
            $findings[] = [
                'code' => 'TD101',
                'severity' => 'warning',
                'title' => 'Compiled Asset Empty',
                'description' => 'The compiled theme.css file exists but is empty.',
                'recommendation' => 'Recompile theme sources: php scripts/assets/compile_theme_sources.php --apply',
                'evidence' => 'File size: 0 bytes',
            ];
            return;
        }

        $compiledMtime = filemtime(self::COMPILED_ASSET_PATH);
        if ($compiledMtime === false) {
            return;
        }

        // TD103: compiled size suspiciously small (avoids duplicating TD101 — only runs when size > 0)
        if ($compiledSize !== false && $compiledSize < self::COMPILED_SIZE_SUSPICIOUS_THRESHOLD) {
            $findings[] = [
                'code' => 'TD103',
                'severity' => 'warning',
                'title' => 'Compiled Asset Suspiciously Small',
                'description' => 'The compiled theme.css exists but is unusually small, which may indicate a dry-run or partial compilation.',
                'recommendation' => 'Run the full compiler: php scripts/assets/compile_theme_sources.php --apply',
                'evidence' => 'File size: ' . $compiledSize . ' bytes',
            ];
        }

        $staleSources = [];
        foreach ($sourceFiles as $sf) {
            $sourcePath = (string)($sf['absolute_path'] ?? '');
            if ($sourcePath === '' || !is_file($sourcePath)) {
                continue;
            }
            $sourceMtime = filemtime($sourcePath);
            if ($sourceMtime !== false && $sourceMtime > $compiledMtime) {
                $staleSources[] = $sf['path'] ?? basename($sourcePath);
            }
        }

        if ($staleSources !== []) {
            $findings[] = [
                'code' => 'TD102',
                'severity' => 'warning',
                'title' => 'Compiled Asset Older Than Sources',
                'description' => 'One or more theme source files are newer than the compiled theme.css.',
                'recommendation' => 'Recompile theme sources: php scripts/assets/compile_theme_sources.php --apply',
                'evidence' => 'Stale sources: ' . implode(', ', $staleSources),
            ];
        }

        // TD104: missing required selectors (only runs when file exists and is non-empty)
        $content = is_file(self::COMPILED_ASSET_PATH) && filesize(self::COMPILED_ASSET_PATH) > 0
            ? @file_get_contents(self::COMPILED_ASSET_PATH)
            : false;
        if (is_string($content) && $content !== '') {
            $missingSelectors = [];
            foreach (self::REQUIRED_ASSET_SELECTORS as $sel) {
                if (!str_contains($content, $sel)) {
                    $missingSelectors[] = $sel;
                }
            }
            if ($missingSelectors !== []) {
                $missingRoot = in_array(':root', $missingSelectors, true);
                $findings[] = [
                    'code' => 'TD104',
                    'severity' => $missingRoot ? 'blocked' : 'warning',
                    'title' => 'Compiled Asset Missing Required Selectors',
                    'description' => 'The compiled theme.css is missing one or more required selectors.',
                    'recommendation' => 'Recompile theme sources with all required variant files present: php scripts/assets/compile_theme_sources.php --apply',
                    'evidence' => 'Missing selectors: ' . implode(', ', $missingSelectors),
                ];
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $findings
     * @param list<array<string,mixed>> $approvedThemes
     * @param list<array<string,mixed>> $draftThemes
     * @param array<string,string> $availableStyles
     */
    private static function checkThemeInventory(
        array &$findings,
        array $approvedThemes,
        array $draftThemes,
        array $availableStyles,
    ): void {
        $approvedKeys = [];
        foreach ($approvedThemes as $t) {
            $k = strtolower(trim((string)($t['key'] ?? '')));
            if ($k !== '') {
                if (in_array($k, $approvedKeys, true)) {
                    $findings[] = [
                        'code' => 'TD200',
                        'severity' => 'warning',
                        'title' => 'Duplicate Theme Key',
                        'description' => 'The approved registry contains duplicate theme key: "' . $k . '".',
                        'recommendation' => 'Remove duplicate entries from the approved registry.',
                        'evidence' => 'Duplicate key: ' . $k . ' in approved themes',
                    ];
                }
                $approvedKeys[] = $k;
            }
        }

        $draftKeys = [];
        foreach ($draftThemes as $d) {
            $k = strtolower(trim((string)($d['key'] ?? '')));
            if ($k !== '') {
                $draftKeys[] = $k;
            }
        }

        foreach ($draftThemes as $d) {
            $k = strtolower(trim((string)($d['key'] ?? '')));
            if ($k !== '' && !in_array($k, $approvedKeys, true)) {
                $findings[] = [
                    'code' => 'TD201',
                    'severity' => 'info',
                    'title' => 'Draft Theme Without Approval',
                    'description' => 'Draft theme "' . $k . '" has no corresponding approved registry entry.',
                    'recommendation' => 'Add "' . $k . '" to the approved registry or remove the draft file.',
                    'evidence' => 'Draft key: ' . $k . ', no matching approved entry',
                ];
            }
        }

        if ($approvedThemes === []) {
            return;
        }

        foreach ($approvedThemes as $t) {
            $k = strtolower(trim((string)($t['key'] ?? '')));
            if ($k === '') {
                continue;
            }
            $found = false;
            foreach ($availableStyles as $sk => $sl) {
                $normalized = strtolower(trim((string)$sk));
                if ($normalized === $k) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $findings[] = [
                    'code' => 'TD202',
                    'severity' => 'warning',
                    'title' => 'Registry References Missing Theme',
                    'description' => 'Approved registry references theme "' . $k . '" but no matching source file exists.',
                    'recommendation' => 'Ensure the source file for "' . $k . '" exists or remove the reference from the registry.',
                    'evidence' => 'Approved key: ' . $k . ', not found in available styles',
                ];
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $findings
     * @param list<array<string,mixed>> $sourceFiles
     */
    private static function checkTokenSets(array &$findings, array $sourceFiles): void
    {
        $foundSets = [];
        foreach ($sourceFiles as $sf) {
            $path = basename((string)($sf['path'] ?? ''));
            $name = strtolower(pathinfo($path, PATHINFO_FILENAME));
            if ($name !== '' && $name !== 'theme-manifest') {
                $foundSets[] = $name;
            }
        }

        foreach (self::REQUIRED_TOKEN_SETS as $required) {
            if (!in_array($required, $foundSets, true)) {
                $severity = in_array($required, ['base', 'light', 'dark'], true) ? 'info' : 'info';
                $label = ucfirst($required);
                $findings[] = [
                    'code' => $required === 'base' ? 'TD300' : ($required === 'light' ? 'TD301' : ($required === 'dark' ? 'TD302' : ($required === 'paper' ? 'TD303' : 'TD304'))),
                    'severity' => $severity,
                    'title' => 'Missing ' . $label . ' Token Set',
                    'description' => 'The required "' . $required . '" token set is missing from resources/themes/.',
                    'recommendation' => 'Create resources/themes/' . $required . '.css with the required token values.',
                    'evidence' => 'Missing file: resources/themes/' . $required . '.css',
                ];
            }
        }
    }

    /**
     * @param list<array<string,mixed>> $findings
     */
    private static function computeHealthScore(array $findings): int
    {
        $score = 100;
        foreach ($findings as $f) {
            $severity = (string)($f['severity'] ?? '');
            match ($severity) {
                'blocked' => $score -= 30,
                'warning' => $score -= 10,
                'info' => $score -= 2,
                default => null,
            };
        }
        return max(0, min(100, $score));
    }
}
