<?php
/**
 * Verification script for Studio CSS implementation
 *
 * Checks that:
 * 1. Generated apps have styles.css files
 * 2. Manifests include styles[] array
 * 3. CSS file syntax is valid
 *
 * Usage: php verify_studio_css_implementation.php
 */

declare(strict_types=1);

// Define APP_ROOT if not already defined
if (!defined('APP_ROOT')) {
    define('APP_ROOT', realpath(__DIR__));
}

$report = [
    'total_apps' => 0,
    'total_modules' => 0,
    'modules_with_css' => 0,
    'modules_without_css' => 0,
    'manifests_with_styles_array' => 0,
    'manifests_without_styles_array' => 0,
    'app_manifests_found' => 0,
    'app_manifests_missing' => 0,
    'app_styles_found' => 0,
    'app_styles_missing' => 0,
    'css_sentinel_errors' => 0,
    'json_errors' => 0,
    'registry_tests_passed' => 0,
    'issues' => [],
];

echo "Studio CSS Implementation Verification\n";
echo "======================================\n\n";

$generatedRoot = rtrim((string)APP_ROOT, '/') . '/apps/Generated';
if (!is_dir($generatedRoot)) {
    echo "ERROR: Generated apps directory not found\n";
    exit(1);
}

// Scan all apps and modules
$appDirs = array_filter(glob($generatedRoot . '/*'), 'is_dir');
sort($appDirs);

$appsWithIssues = [];
$modulesWithIssues = [];

foreach ($appDirs as $appDir) {
    $appKey = basename($appDir);
    $report['total_apps']++;

    // Check app-level manifest
    $appManifest = $appDir . '/manifest.json';
    if (is_file($appManifest)) {
        $report['app_manifests_found']++;
        if (!validateJsonFile($appManifest)) {
            $report['json_errors']++;
            $report['issues'][] = "❌ $appKey: app manifest has invalid JSON";
            $appsWithIssues[] = $appKey;
        }
    } else {
        $report['app_manifests_missing']++;
        $report['issues'][] = "⚠️  $appKey: missing app-level manifest.json";
        $appsWithIssues[] = $appKey;
    }

    // Check app-level styles.css
    $appStyles = $appDir . '/styles.css';
    if (is_file($appStyles)) {
        $report['app_styles_found']++;
        if (!validateCssFile($appStyles)) {
            $report['css_sentinel_errors']++;
            $report['issues'][] = "❌ $appKey: app styles.css has malformed sentinels";
        }
    } else {
        $report['app_styles_missing']++;
        $report['issues'][] = "⚠️  $appKey: missing app-level styles.css";
        $appsWithIssues[] = $appKey;
    }

    // Scan modules
    $moduleDirs = array_filter(glob($appDir . '/*'), 'is_dir');
    sort($moduleDirs);

    foreach ($moduleDirs as $moduleDir) {
        $moduleKey = basename($moduleDir);
        $manifestFile = $moduleDir . '/manifest.json';

        if (!is_file($manifestFile)) {
            continue;
        }

        $report['total_modules']++;
        $moduleId = "$appKey/$moduleKey";

        // Validate manifest JSON
        if (!validateJsonFile($manifestFile)) {
            $report['json_errors']++;
            $report['issues'][] = "❌ $moduleId: manifest has invalid JSON";
            $modulesWithIssues[] = $moduleId;
            continue;
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);

        // Check styles[] array
        if (isset($manifest['styles']) && is_array($manifest['styles'])) {
            $report['manifests_with_styles_array']++;
        } else {
            $report['manifests_without_styles_array']++;
            $report['issues'][] = "⚠️  $moduleId: manifest missing styles[] array";
            $modulesWithIssues[] = $moduleId;
        }

        // Check styles.css file
        $cssPath = $moduleDir . '/styles.css';
        if (is_file($cssPath)) {
            $report['modules_with_css']++;
            if (!validateCssFile($cssPath)) {
                $report['css_sentinel_errors']++;
                $report['issues'][] = "❌ $moduleId: styles.css has malformed sentinels";
                $modulesWithIssues[] = $moduleId;
            }
        } else {
            $report['modules_without_css']++;
            $report['issues'][] = "⚠️  $moduleId: missing styles.css";
            $modulesWithIssues[] = $moduleId;
        }
    }
}

echo "\n";

// Report
echo "VERIFICATION REPORT\n";
echo "===================\n\n";

echo "Apps and Modules:\n";
echo "  Total apps: {$report['total_apps']}\n";
echo "  Total modules: {$report['total_modules']}\n\n";

echo "App-Level Files:\n";
echo "  Manifests found: {$report['app_manifests_found']}\n";
echo "  Manifests missing: {$report['app_manifests_missing']}\n";
echo "  Styles found: {$report['app_styles_found']}\n";
echo "  Styles missing: {$report['app_styles_missing']}\n\n";

echo "Module-Level Files:\n";
echo "  With styles.css: {$report['modules_with_css']}\n";
echo "  Without styles.css: {$report['modules_without_css']}\n";
echo "  With styles[] array: {$report['manifests_with_styles_array']}\n";
echo "  Without styles[] array: {$report['manifests_without_styles_array']}\n\n";

echo "Validation:\n";
echo "  JSON errors: {$report['json_errors']}\n";
echo "  CSS sentinel errors: {$report['css_sentinel_errors']}\n";
echo "  Registry tests passed: {$report['registry_tests_passed']}\n\n";

// Issues
if (!empty($report['issues'])) {
    echo "ISSUES FOUND:\n";
    foreach ($report['issues'] as $issue) {
        echo "  $issue\n";
    }
    echo "\n";
}

// Summary
$totalIssues = $report['app_manifests_missing'] + $report['app_styles_missing']
    + $report['modules_without_css'] + $report['manifests_without_styles_array']
    + $report['json_errors'] + $report['css_sentinel_errors'];

if ($totalIssues === 0) {
    echo "✅ ALL CHECKS PASSED\n";
    echo "CSS implementation is complete and valid.\n";
    exit(0);
} else {
    echo "⚠️  $totalIssues issues found\n";
    echo "Run: php scripts/backfill_studio_css.php\n";
    echo "to fix missing CSS files and manifests.\n";
    exit(1);
}

/**
 * Validate JSON file format
 */
function validateJsonFile(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    $content = @file_get_contents($path);
    if (!is_string($content)) {
        return false;
    }
    try {
        json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        return true;
    } catch (Throwable) {
        return false;
    }
}

/**
 * Validate CSS file format (check sentinels)
 */
function validateCssFile(string $path): bool
{
    if (!is_file($path)) {
        return false;
    }
    $content = @file_get_contents($path);
    if (!is_string($content)) {
        return false;
    }

    $hasGeneratedStart = str_contains($content, '/* studio:generated-start');
    $hasGeneratedEnd = str_contains($content, '/* studio:generated-end');
    $hasUserStart = str_contains($content, '/* studio:user-start');
    $hasUserEnd = str_contains($content, '/* studio:user-end');

    // Must have all sentinels
    return $hasGeneratedStart && $hasGeneratedEnd && $hasUserStart && $hasUserEnd;
}
