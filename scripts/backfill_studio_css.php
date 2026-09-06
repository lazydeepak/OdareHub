<?php
/**
 * Backfill CSS files for Studio-generated apps
 *
 * This script adds styles.css files and updates manifests for generated apps
 * that were created before CSS compilation feature (commit 2d0ab099, May 17).
 *
 * Usage: php scripts/backfill_studio_css.php [--dry-run]
 */

declare(strict_types=1);

// Define APP_ROOT if not already defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__ . '/../'));
}

$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$stats = [
    'apps_scanned' => 0,
    'modules_scanned' => 0,
    'css_files_created' => 0,
    'manifests_updated' => 0,
    'app_manifests_created' => 0,
    'app_styles_created' => 0,
    'errors' => [],
];

echo "Studio CSS Backfill Utility\n";
echo "=========================\n";
echo "Dry run: " . ($dryRun ? 'YES' : 'NO') . "\n\n";

$generatedRoot = rtrim((string)APP_ROOT, '/') . '/apps/Generated';
if (!is_dir($generatedRoot)) {
    echo "ERROR: Generated apps directory not found: $generatedRoot\n";
    exit(1);
}

echo "Scanning: $generatedRoot\n\n";

$appDirs = array_filter(glob($generatedRoot . '/*'), 'is_dir');
sort($appDirs);

foreach ($appDirs as $appDir) {
    $appKey = basename($appDir);
    $stats['apps_scanned']++;

    if ($verbose) {
        echo "App: $appKey\n";
    }

    // Backfill module-level CSS
    $moduleDirs = array_filter(glob($appDir . '/*'), 'is_dir');
    sort($moduleDirs);

    foreach ($moduleDirs as $moduleDir) {
        $moduleKey = basename($moduleDir);
        $manifestFile = $moduleDir . '/manifest.json';

        if (!is_file($manifestFile)) {
            if ($verbose) {
                echo "  ⊘ Module $moduleKey: no manifest.json, skipping\n";
            }
            continue;
        }

        $stats['modules_scanned']++;

        $manifestContent = @file_get_contents($manifestFile);
        if (!is_string($manifestContent)) {
            $stats['errors'][] = "Failed to read $manifestFile";
            continue;
        }

        $manifest = json_decode($manifestContent, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($manifest)) {
            $stats['errors'][] = "Invalid JSON in $manifestFile";
            continue;
        }

        $cssPath = $moduleDir . '/styles.css';
        $cssExists = is_file($cssPath);
        $manifestHasStyles = isset($manifest['styles']) && is_array($manifest['styles']);

        // Generate CSS if missing
        if (!$cssExists) {
            try {
                $context = extractContextFromManifest($manifest, $moduleDir);
                $css = generateModuleStylesCSS($context);

                if ($dryRun) {
                    echo "  [DRY] Would create: $cssPath\n";
                } else {
                    if (@file_put_contents($cssPath, $css, LOCK_EX) !== false) {
                        $stats['css_files_created']++;
                        echo "  ✓ Created: $cssPath\n";
                    } else {
                        $stats['errors'][] = "Failed to write $cssPath";
                    }
                }
            } catch (Throwable $e) {
                $stats['errors'][] = "Error generating CSS for $moduleKey: " . $e->getMessage();
            }
        } else if ($verbose) {
            echo "  ✓ $moduleKey: styles.css already exists\n";
        }

        // Update manifest with styles[] if missing
        if (!$manifestHasStyles) {
            $manifest['styles'] = [[
                'key' => "generated.$appKey.$moduleKey",
                'path' => 'styles.css',
                'scope' => 'module',
                'module' => $moduleKey,
                'surfaces' => ['admin', 'operator'],
                'order' => 300,
            ]];

            if ($dryRun) {
                echo "  [DRY] Would update manifest: $manifestFile\n";
            } else {
                $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
                if (@file_put_contents($manifestFile, $json, LOCK_EX) !== false) {
                    $stats['manifests_updated']++;
                    echo "  ✓ Updated manifest: $manifestFile\n";
                } else {
                    $stats['errors'][] = "Failed to update $manifestFile";
                }
            }
        } else if ($verbose) {
            echo "  ✓ $moduleKey: manifest already has styles[]\n";
        }
    }

    // Backfill app-level manifest
    $appManifestFile = $appDir . '/manifest.json';
    if (!is_file($appManifestFile)) {
        try {
            $appDisplayName = ucfirst(str_replace('_', ' ', $appKey));
            $appManifest = generateAppManifestJSON($appKey, $appDisplayName);

            if ($dryRun) {
                echo "  [DRY] Would create app manifest: $appManifestFile\n";
            } else {
                if (@file_put_contents($appManifestFile, $appManifest, LOCK_EX) !== false) {
                    $stats['app_manifests_created']++;
                    echo "  ✓ Created app manifest: $appManifestFile\n";
                } else {
                    $stats['errors'][] = "Failed to write app manifest $appManifestFile";
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = "Error creating app manifest for $appKey: " . $e->getMessage();
        }
    } else if ($verbose) {
        echo "  ✓ App manifest already exists\n";
    }

    // Backfill app-level styles.css
    $appStylesFile = $appDir . '/styles.css';
    if (!is_file($appStylesFile)) {
        try {
            $appStyles = generateAppStylesCSS($appKey);

            if ($dryRun) {
                echo "  [DRY] Would create app styles: $appStylesFile\n";
            } else {
                if (@file_put_contents($appStylesFile, $appStyles, LOCK_EX) !== false) {
                    $stats['app_styles_created']++;
                    echo "  ✓ Created app styles: $appStylesFile\n";
                } else {
                    $stats['errors'][] = "Failed to write app styles $appStylesFile";
                }
            }
        } catch (Throwable $e) {
            $stats['errors'][] = "Error creating app styles for $appKey: " . $e->getMessage();
        }
    } else if ($verbose) {
        echo "  ✓ App styles already exist\n";
    }

    if ($verbose) {
        echo "\n";
    }
}

// Summary
echo "\n=========================\n";
echo "SUMMARY\n";
echo "=========================\n";
echo "Apps scanned: {$stats['apps_scanned']}\n";
echo "Modules scanned: {$stats['modules_scanned']}\n";
echo "CSS files created: {$stats['css_files_created']}\n";
echo "Manifests updated: {$stats['manifests_updated']}\n";
echo "App manifests created: {$stats['app_manifests_created']}\n";
echo "App styles created: {$stats['app_styles_created']}\n";

if (!empty($stats['errors'])) {
    echo "\nERRORS:\n";
    foreach ($stats['errors'] as $error) {
        echo "  • $error\n";
    }
    exit(1);
}

echo "\n✓ Backfill complete" . ($dryRun ? ' (dry run)' : '') . "\n";

/**
 * Extract context array from manifest for CSS generation
 */
function extractContextFromManifest(array $manifest, string $moduleDir): array
{
    return [
        'app_key' => (string)($manifest['app_key'] ?? ''),
        'module_key' => (string)($manifest['module_key'] ?? ''),
        'view_key' => (string)($manifest['view_key'] ?? 'index'),
        'display_name' => (string)($manifest['display_name'] ?? ''),
        'route_path' => (string)($manifest['route_path'] ?? ''),
        'relative_path' => str_replace((string)APP_ROOT, '', $moduleDir),
    ];
}

/**
 * Generate module-level styles.css content
 */
function generateModuleStylesCSS(array $context): string
{
    $appKey = (string)$context['app_key'];
    $moduleKey = (string)$context['module_key'];
    $viewKey = (string)($context['view_key'] ?? 'index');

    $generated = "/* studio:generated-start  app={$appKey} module={$moduleKey}  do-not-edit */\n"
        . "[data-studio-app=\"{$appKey}\"][data-studio-module=\"{$moduleKey}\"] {\n"
        . "    /* module root scope */\n"
        . "}\n"
        . "[data-studio-app=\"{$appKey}\"][data-studio-module=\"{$moduleKey}\"] [data-view-id=\"{$viewKey}\"] {\n"
        . "    /* view: {$viewKey} */\n"
        . "}\n"
        . "/* studio:generated-end */\n";

    $userBlock = "/* studio:user-start */\n"
        . "/* Hand-edited styles below this line are preserved across Studio re-applies. */\n"
        . "/* studio:user-end */\n";

    return $generated . "\n" . $userBlock;
}

/**
 * Generate app-level manifest JSON
 */
function generateAppManifestJSON(string $appKey, string $appDisplayName): string
{
    $manifest = [
        'schema_version' => 'studio.generated-app.v1',
        'generated_by' => 'studio',
        'app_key' => $appKey,
        'display_name' => $appDisplayName,
        'created_at' => gmdate('c'),
        'styles' => [[
            'key' => 'generated.' . $appKey . '.app',
            'path' => 'styles.css',
            'scope' => 'app',
            'surfaces' => ['admin', 'operator'],
            'order' => 200,
        ]],
    ];
    return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
}

/**
 * Generate app-level styles.css content
 */
function generateAppStylesCSS(string $appKey): string
{
    return "/* studio:generated-start  app={$appKey}  do-not-edit */\n"
        . "[data-studio-app=\"{$appKey}\"] {\n"
        . "    /* app-scope root */\n"
        . "}\n"
        . "/* studio:generated-end */\n\n"
        . "/* studio:user-start */\n"
        . "/* Hand-edited app-level styles below this line are preserved. */\n"
        . "/* studio:user-end */\n";
}
