#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_runtime_registry_source_truth"
echo "- read-only diagnostic for runtime registry source-of-truth boundaries"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "missing required binary: php" >&2
  exit 2
fi

"$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
$failures = [];
$warnings = [];

/** @return list<string> */
function files_for(string $pattern): array
{
    $files = glob($pattern) ?: [];
    sort($files);
    return array_values(array_filter($files, 'is_file'));
}

/** @return array<string,mixed> */
function read_json_assoc(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/** @return int */
function grep_count(array $roots, string $pattern): int
{
    $count = 0;
    foreach ($roots as $path) {
        if (!file_exists($path)) {
            continue;
        }
        $iterator = is_dir($path)
            ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS))
            : new ArrayIterator([new SplFileInfo($path)]);
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $filePath = $file->getPathname();
            if (str_contains($filePath, '/vendor/') || str_contains($filePath, '/.git/')) {
                continue;
            }
            $raw = @file_get_contents($filePath);
            if ($raw !== false && preg_match($pattern, $raw) === 1) {
                $count++;
            }
        }
    }
    return $count;
}

/** @return list<string> */
function core_apps_keys(string $root): array
{
    $dbFile = $root . '/app/Core/DB.php';
    if (!is_file($dbFile)) {
        return [];
    }
    try {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $root);
        }
        require_once $dbFile;
        $rows = \App\Core\DB::fetchAll('SELECT app_key FROM core_apps ORDER BY app_key');
        $keys = [];
        foreach ($rows as $row) {
            $key = trim((string)($row['app_key'] ?? ''));
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        return $keys;
    } catch (Throwable $e) {
        echo "core_apps_db_status=unavailable:" . preg_replace('/\s+/', '_', $e->getMessage()) . PHP_EOL;
        return [];
    }
}

$generatedAppKeys = array_map('basename', array_values(array_filter(glob($root . '/apps/Generated/*') ?: [], 'is_dir')));
sort($generatedAppKeys);
$coreApps = core_apps_keys($root);
$appstudioRegistry = read_json_assoc($root . '/storage/appstudio/apps_registry.json');
$appstudioKeys = array_keys($appstudioRegistry);
sort($appstudioKeys);

$generatedInCore = array_values(array_intersect($generatedAppKeys, $coreApps));
$enabledGenerated = [];
foreach ($appstudioRegistry as $key => $row) {
    if (is_array($row) && (string)($row['status'] ?? '') === 'enabled') {
        $enabledGenerated[] = (string)$key;
    }
}
sort($enabledGenerated);

$sourceRows = [
    ['DB:core_apps', 'installed app lifecycle registry', 'Core/App lifecycle governance', 'SOURCE_OF_TRUTH', 'app services; Platform/Base governance; readiness checks', 'app install/lifecycle services', 'high', 'read-only diagnostics; governed lifecycle updates', 'direct cleanup deletion or generated-app-only truth'],
    ['storage/appstudio/apps_registry.json', 'Studio-generated app registry', 'Studio', 'SOURCE_OF_TRUTH', 'Studio loaders; generated archive diagnostic', 'Studio apply/publish flows', 'high', 'read-only diagnostics; governed Studio writes', 'delete/archive generated app without checking'],
    ['apps/*/manifest.json', 'owner app manifests', 'owning app', 'SOURCE_OF_TRUTH', 'discovery; CSS publisher; system app gates', 'app owner/lifecycle', 'high', 'owner-approved edits', 'Shell/Core ownership absorption'],
    ['apps/Generated/*/manifest.json', 'generated app manifests', 'Studio-generated artifact owner', 'COMPATIBILITY_TRUTH', 'generated loaders; CSS publisher; diagnostics', 'Studio apply/publish flows', 'high', 'preserve pending archive policy', 'delete/archive without registry/storage checks'],
    ['apps/*/navigation.php', 'app navigation contributions', 'owning app', 'SOURCE_OF_TRUTH', 'SidebarBuilder compatibility path; route authority', 'app owner', 'medium', 'owner-owned edits', 'Shell/Core consolidation'],
    ['apps/*/modules/*/navigation.php', 'module navigation contributions', 'owning app/module', 'SOURCE_OF_TRUTH', 'SidebarBuilder compatibility path; route authority', 'module owner', 'medium', 'module-owned edits', 'ACL/Shell presentation truth'],
    ['plugins/*/navigation.php', 'plugin navigation contributions', 'owning plugin', 'SOURCE_OF_TRUTH', 'SidebarBuilder compatibility path', 'plugin owner', 'medium', 'plugin-owned edits', 'cross-owner business links'],
    ['DB:menus', 'legacy menu fallback/search input', 'legacy/Core compatibility', 'RUNTIME_FALLBACK + INVESTIGATE', 'SidebarBuilder; SearchService; Base bootstrap', 'bootstrap/lifecycle/runtime registry code', 'high', 'read-only investigation', 'deletion before classification'],
    ['routes.php files', 'route registration', 'owner app/module/plugin', 'SOURCE_OF_TRUTH', 'public router; route authority', 'owner app/module/plugin', 'high', 'owner-scoped route edits', 'cleanup route behavior changes'],
    ['public/assets/apps', 'published CSS delivery output', 'owner CSS publisher', 'CACHE_OR_OUTPUT', 'browser/runtime asset loading; asset gates', 'registered CSS publisher', 'medium', 'regenerate from owner CSS', 'hand-edit as source truth'],
    ['Studio generated lifecycle services', 'generated apply/publish registry workflow', 'Studio', 'SOURCE_OF_TRUTH', 'Studio controllers/services/diagnostics', 'Studio governed operations', 'high', 'read-only diagnostics; governed Studio changes', 'Platform/Base/Core ownership absorption'],
    ['packages/storage package artifacts', 'lifecycle transport metadata', 'Packages/Studio package flows', 'COMPATIBILITY_TRUTH + CACHE_OR_OUTPUT', 'PackageManager; Studio package services', 'package/export/install flows', 'medium', 'retention diagnostics', 'treat package as feature owner'],
];

echo 'source|current_role|owner|label|readers|writers|cleanup_risk|allowed_future_action|blocked_future_action' . PHP_EOL;
foreach ($sourceRows as $row) {
    echo implode('|', $row) . PHP_EOL;
}

echo PHP_EOL . 'metrics:' . PHP_EOL;
echo 'core_apps.keys=' . implode(',', $coreApps) . PHP_EOL;
echo 'generated_app.keys=' . implode(',', $generatedAppKeys) . PHP_EOL;
echo 'generated_in_core_apps=' . ($generatedInCore === [] ? 'none' : implode(',', $generatedInCore)) . PHP_EOL;
echo 'appstudio_registry.keys=' . implode(',', $appstudioKeys) . PHP_EOL;
echo 'appstudio_registry.enabled=' . implode(',', $enabledGenerated) . PHP_EOL;
echo 'app_manifests.count=' . count(files_for($root . '/apps/*/manifest.json')) . PHP_EOL;
echo 'generated_app_manifests.count=' . count(files_for($root . '/apps/Generated/*/manifest.json')) . PHP_EOL;
echo 'app_navigation.count=' . count(files_for($root . '/apps/*/navigation.php')) . PHP_EOL;
echo 'module_navigation.count=' . count(files_for($root . '/apps/*/modules/*/navigation.php')) . PHP_EOL;
echo 'plugin_navigation.count=' . count(files_for($root . '/plugins/*/navigation.php')) . PHP_EOL;
echo 'public_assets_apps_css.count=' . count(files_for($root . '/public/assets/apps/*/styles/*.css')) . PHP_EOL;
echo 'generated_data.count=' . count(files_for($root . '/storage/appstudio/generated_data/*/*.json')) . PHP_EOL;
echo 'snapshots.count=' . count(files_for($root . '/storage/appstudio/snapshots/*.json')) . PHP_EOL;

$menusRefs = grep_count([$root . '/app', $root . '/apps', $root . '/plugins', $root . '/public', $root . '/scripts'], '/(FROM\s+menus|DELETE\s+FROM\s+menus|INSERT\s+INTO\s+menus|UPDATE\s+menus|SELECT\s+[^;]*menu_key)/i');
echo 'db_menus.runtime_reference_files=' . $menusRefs . PHP_EOL;
if ($menusRefs > 0) {
    $warnings[] = 'DB menus still has runtime/search/lifecycle references; classify before cleanup.';
}

if ($generatedInCore !== []) {
    $warnings[] = 'Generated keys also appear in core_apps; archive/delete policy must treat them as installed app state.';
}

echo PHP_EOL . 'answers:' . PHP_EOL;
echo 'core_apps_authority=installed system/business/framework app lifecycle; not sufficient for generated Studio archive decisions' . PHP_EOL;
echo 'appstudio_apps_registry_authority=Studio-generated app lifecycle and enablement registry' . PHP_EOL;
echo 'db_menus_truth=RUNTIME_FALLBACK_INVESTIGATE' . PHP_EOL;
echo 'public_assets_truth=CACHE_OR_OUTPUT' . PHP_EOL;
echo 'generated_archive_required_registry=storage/appstudio/apps_registry.json plus core_apps safety check and route/nav/storage/public/script/docs coupling' . PHP_EOL;

foreach ($warnings as $warning) {
    echo 'warning: ' . $warning . PHP_EOL;
}

foreach ($failures as $failure) {
    echo 'failure: ' . $failure . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, "RESULT: FAIL (runtime registry source truth violations found)\n");
    exit(1);
}

echo 'RESULT: PASS (read-only source-of-truth diagnostic complete)' . PHP_EOL;
PHP
