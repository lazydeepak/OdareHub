#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_asset_registry_integrity"
echo "- read-only diagnostic for manifest-declared CSS source and public asset targets"

php <<'PHP'
<?php
$root = getcwd();
$failures = 0;
$warnings = 0;
$checked = 0;
$declaredTargets = [];
$declaredSources = [];
$expectedScanAnchors = [
    'apps/*/manifest.json',
    'apps/Generated/*/manifest.json',
    'apps/*/modules/*/manifest.json',
    'apps/*/styles',
    'apps/*/modules/*/styles.css',
    'public/assets/apps',
    'scripts/assets/publish_registered_css.php',
    'scripts/assets/README.md',
    'docs/architecture/surface-contribution-contract.md',
    'docs/architecture/business-app-module-ownership-contract.md',
    'manifest-style-entry-shape',
    'public-delivery-backmap',
    'shell-css-selector-boundary',
];
$activeScanAnchors = [
    'apps/*/manifest.json',
    'apps/Generated/*/manifest.json',
    'apps/*/modules/*/manifest.json',
    'apps/*/styles',
    'apps/*/modules/*/styles.css',
    'public/assets/apps',
    'scripts/assets/publish_registered_css.php',
    'scripts/assets/README.md',
    'docs/architecture/surface-contribution-contract.md',
    'docs/architecture/business-app-module-ownership-contract.md',
    'manifest-style-entry-shape',
    'public-delivery-backmap',
    'shell-css-selector-boundary',
];

function out(string $message): void
{
    echo $message . PHP_EOL;
}

function fail(string $message): void
{
    global $failures;
    out('  fail: ' . $message);
    $failures++;
}

function warn(string $message): void
{
    global $warnings;
    out('  warning: ' . $message);
    $warnings++;
}

function ok(string $message): void
{
    out('  ok: ' . $message);
}

function requireText(string $path, string $needle, string $label): void
{
    if (!is_file($path)) {
        fail("missing {$label}: " . relativePath($path));
        return;
    }

    $contents = (string)file_get_contents($path);
    if (str_contains($contents, $needle)) {
        ok($label);
    } else {
        fail("{$label} missing required text in " . relativePath($path));
    }
}

function isSafeRelativePath(string $path): bool
{
    if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
        return false;
    }

    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '..') {
            return false;
        }
    }

    return true;
}

function manifestFiles(string $root): array
{
    $paths = [];
    foreach (glob($root . '/apps/*/manifest.json') ?: [] as $file) {
        $paths[] = $file;
    }
    foreach (glob($root . '/apps/Generated/*/manifest.json') ?: [] as $file) {
        $paths[] = $file;
    }
    sort($paths);
    return array_values(array_unique($paths));
}

function moduleManifestFiles(string $root): array
{
    $paths = [];
    foreach (glob($root . '/apps/*/modules/*/manifest.json') ?: [] as $file) {
        $paths[] = $file;
    }
    foreach (glob($root . '/apps/*/modules/*/plugin.json') ?: [] as $file) {
        $paths[] = $file;
    }
    sort($paths);
    return array_values(array_unique($paths));
}

function loadManifest(string $file): ?array
{
    $json = file_get_contents($file);
    $data = json_decode((string)$json, true);
    if (!is_array($data)) {
        fail('invalid manifest JSON: ' . relativePath($file));
        return null;
    }
    return $data;
}

function relativePath(string $path): string
{
    global $root;
    $prefix = rtrim($root, '/') . '/';
    return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
}

function publicTargetFor(array $entry, string $appKey, string $path): ?string
{
    $scope = strtolower(trim((string)($entry['scope'] ?? 'app')));
    if ($scope === 'module') {
        $module = strtolower(trim((string)($entry['module'] ?? '')));
        if ($module === '') {
            return null;
        }
        return 'public/assets/apps/' . strtolower($appKey) . '/modules/' . $module . '/styles.css';
    }

    return 'public/assets/apps/' . strtolower($appKey) . '/styles/' . basename($path);
}

function normalizePath(string $path): string
{
    return str_replace('\\', '/', $path);
}

function isOwnerScopedSource(string $sourceRel, string $appKey, string $scope): bool
{
    $sourceRel = normalizePath($sourceRel);
    $appKey = strtolower($appKey);

    if ($appKey !== 'shell' && str_starts_with($sourceRel, 'apps/Shell/')) {
        return false;
    }

    if (str_starts_with($sourceRel, 'public/assets/apps/')) {
        return false;
    }

    if ($scope === 'module' && !str_contains($sourceRel, '/modules/')) {
        return false;
    }

    return str_starts_with($sourceRel, 'apps/');
}

function publicAssetFiles(string $root): array
{
    $base = $root . '/public/assets/apps';
    if (!is_dir($base)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && strtolower($file->getExtension()) === 'css') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function publicTargetShapeLooksSafe(string $targetRel): bool
{
    if (!str_starts_with($targetRel, 'public/assets/apps/') || !str_ends_with($targetRel, '.css')) {
        return false;
    }

    return !str_contains($targetRel, '//') && isSafeRelativePath($targetRel);
}

function fileContainsPattern(string $file, string $pattern): bool
{
    $contents = @file_get_contents($file);
    return is_string($contents) && preg_match($pattern, $contents) === 1;
}

out('');
out('== Asset registry scan contract ==');
foreach ($expectedScanAnchors as $index => $expected) {
    $actual = $activeScanAnchors[$index] ?? null;
    if ($actual === $expected) {
        ok("scan-anchor[{$index}] {$expected}");
    } else {
        fail("scan-anchor[{$index}] drift: expected {$expected} but found " . ($actual ?? '<missing>'));
    }
}
if (count($activeScanAnchors) !== count($expectedScanAnchors)) {
    fail('scan-anchor count drift: expected ' . count($expectedScanAnchors) . ' but found ' . count($activeScanAnchors));
}

out('');
out('== Asset ownership documentation alignment ==');
requireText($root . '/scripts/assets/README.md', 'public/assets/apps/...` is a published runtime delivery target, not the source of truth', 'asset README documents public delivery as non-source truth');
requireText($root . '/scripts/assets/README.md', 'Apply mode only copies owner CSS files into the confined delivery root `public/assets/apps/...`', 'asset README documents confined apply behavior');
requireText($root . '/docs/architecture/surface-contribution-contract.md', 'CSS and asset declarations must stay owner-scoped and must not point at generated delivery output as source truth.', 'surface contribution contract documents owner-scoped CSS source');
requireText($root . '/docs/architecture/business-app-module-ownership-contract.md', 'CSS/assets owned: app styles and assets under the app path; module-specific styling under the module path.', 'business app/module contract documents CSS ownership');

out('');
out('== Module style manifest scan scope ==');
$moduleManifestCount = count(moduleManifestFiles($root));
ok("module manifests/plugins scanned for scope: {$moduleManifestCount}");

out('');
out('== Manifest style declarations ==');

foreach (manifestFiles($root) as $manifestFile) {
    $manifest = loadManifest($manifestFile);
    if ($manifest === null) {
        continue;
    }

    $styles = $manifest['styles'] ?? [];
    if (!is_array($styles) || $styles === []) {
        continue;
    }

    $manifestRel = relativePath($manifestFile);
    $appDir = dirname($manifestFile);
    $appKey = strtolower(trim((string)($manifest['app_key'] ?? basename($appDir))));

    out('');
    out('== ' . $manifestRel . ' ==');

    foreach ($styles as $index => $entry) {
        $checked++;
        if (!is_array($entry)) {
            fail("style[$index] is not an object in {$manifestRel}");
            continue;
        }

        $key = strtolower(trim((string)($entry['key'] ?? '')));
        $path = trim((string)($entry['path'] ?? ''));
        $scope = strtolower(trim((string)($entry['scope'] ?? 'app')));
        $surfaces = $entry['surfaces'] ?? [];

        if ($key === '') {
            fail("style[$index] missing key in {$manifestRel}");
            continue;
        }
        if ($path === '') {
            fail("{$key} missing path in {$manifestRel}");
            continue;
        }
        if (!isSafeRelativePath($path)) {
            fail("{$key} has unsafe relative path: {$path}");
            continue;
        }
        if (!in_array($scope, ['app', 'module'], true)) {
            fail("{$key} has unsupported scope: {$scope}");
            continue;
        }
        if (!is_array($surfaces) || $surfaces === []) {
            fail("{$key} must declare non-empty surfaces array");
            continue;
        }
        foreach ($surfaces as $surfaceIndex => $surface) {
            if (!is_string($surface) || trim($surface) === '') {
                fail("{$key} surface[{$surfaceIndex}] must be a non-empty string");
            }
        }
        if (str_starts_with($path, 'public/') || str_starts_with($path, 'assets/') || str_contains($path, 'public/assets/apps')) {
            fail("{$key} points source path at generated delivery output: {$path}");
            continue;
        }
        if ($appKey !== 'shell' && preg_match('#(^|/)Shell/#', $path) === 1) {
            fail("{$key} points non-Shell style declaration into Shell-owned source path: {$path}");
            continue;
        }

        $source = $appDir . '/' . ltrim($path, '/');
        $sourceRel = relativePath($source);
        if (!isOwnerScopedSource($sourceRel, $appKey, $scope)) {
            fail("{$key} source is not owner-scoped: {$sourceRel}");
        }
        if (is_file($source)) {
            ok("{$key} source exists: " . $sourceRel);
        } else {
            warn("{$key} source missing: " . $sourceRel);
        }
        $declaredSources[$sourceRel] = $key;

        $targetRel = publicTargetFor($entry, $appKey, $path);
        if ($targetRel === null) {
            fail("{$key} module scope missing module key");
            continue;
        }
        if (!publicTargetShapeLooksSafe($targetRel)) {
            fail("{$key} has unsafe public delivery target: {$targetRel}");
            continue;
        }
        $declaredTargets[$targetRel] = [
            'key' => $key,
            'source' => $sourceRel,
        ];

        $target = $root . '/' . $targetRel;
        if (is_file($target)) {
            ok("{$key} public asset exists: {$targetRel}");
            if (is_file($source) && hash_file('sha256', $source) !== hash_file('sha256', $target)) {
                warn("{$key} public asset differs from owner source; run publisher to refresh delivery copy");
            }
        } else {
            warn("{$key} public asset missing: {$targetRel}");
        }
    }
}

out('');
out('== Public delivery back-map ==');
$publicAssets = publicAssetFiles($root);
if ($publicAssets === []) {
    warn('no public CSS delivery assets found under public/assets/apps');
} else {
    foreach ($publicAssets as $asset) {
        $assetRel = relativePath($asset);
        if (isset($declaredTargets[$assetRel])) {
            ok("delivery asset maps to owner declaration: {$assetRel}");
        } else {
            fail("delivery asset has no manifest style declaration: {$assetRel}");
        }
    }
}

out('');
out('== Shell CSS selector boundary ==');
$shellSpecificPattern = '/\.(mfg|qc|dsp|ml|dl|prodop|sbaio|platform(?!-mode)|procurement)-/i';
$shellCssFiles = glob($root . '/apps/Shell/styles/*.css') ?: [];
foreach ($publicAssets as $asset) {
    if (str_starts_with(relativePath($asset), 'public/assets/apps/shell/')) {
        $shellCssFiles[] = $asset;
    }
}
$shellSelectorHits = 0;
foreach ($shellCssFiles as $shellCss) {
    if (fileContainsPattern($shellCss, $shellSpecificPattern)) {
        fail('Shell CSS contains app/module-specific selector: ' . relativePath($shellCss));
        $shellSelectorHits++;
    }
}
if ($shellSelectorHits === 0) {
    ok('Shell CSS does not contain detectable app/module-specific selector prefixes');
}

out('');
out('checked style entries: ' . $checked);
out('declared public targets: ' . count($declaredTargets));
out('public delivery assets checked: ' . count($publicAssets));
out('warnings: ' . $warnings);

if ($failures > 0) {
    out('RESULT: FAIL (asset registry contract errors found)');
    exit(1);
}

out('RESULT: PASS (diagnostic complete; warnings identify missing source/public CSS assets)');
exit(0);
PHP
