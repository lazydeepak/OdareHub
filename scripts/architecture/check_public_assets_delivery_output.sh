#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_public_assets_delivery_output"
echo "- read-only diagnostic for public asset delivery/source boundaries"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "missing required binary: php" >&2
  exit 2
fi

"$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
$warnings = [];
$failures = [];

/** @return list<string> */
function files_under(string $path): array
{
    if (!is_dir($path)) {
        return [];
    }
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $name = $file->getFilename();
        if ($name === '.DS_Store') {
            continue;
        }
        $files[] = $file->getPathname();
    }
    sort($files);
    return $files;
}

function relpath(string $root, string $path): string
{
    return ltrim(str_replace(rtrim($root, '/') . '/', '', $path), '/');
}

/** @return array<string,mixed> */
function read_json(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

/** @return list<array<string,mixed>> */
function owner_style_targets(string $root): array
{
    $items = [];
    $manifests = array_merge(glob($root . '/apps/*/manifest.json') ?: [], glob($root . '/apps/Generated/*/manifest.json') ?: []);
    sort($manifests);
    foreach ($manifests as $manifestFile) {
        $manifest = read_json($manifestFile);
        $styles = $manifest['styles'] ?? [];
        if (!is_array($styles)) {
            continue;
        }
        $appDir = dirname($manifestFile);
        $appKey = strtolower(trim((string)($manifest['app_key'] ?? basename($appDir))));
        $generated = str_contains($manifestFile, '/apps/Generated/');
        foreach ($styles as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $path = trim((string)($entry['path'] ?? ''));
            $scope = strtolower(trim((string)($entry['scope'] ?? 'app')));
            $module = strtolower(trim((string)($entry['module'] ?? '')));
            if ($path === '') {
                continue;
            }
            $target = $scope === 'module'
                ? 'public/assets/apps/' . $appKey . '/modules/' . $module . '/styles.css'
                : 'public/assets/apps/' . $appKey . '/styles/' . basename($path);
            $source = relpath($root, $appDir . '/' . ltrim($path, '/'));
            $items[] = [
                'key' => (string)($entry['key'] ?? $appKey . '.style.' . $index),
                'app_key' => $appKey,
                'source' => $source,
                'target' => $target,
                'generated' => $generated,
                'scope' => $scope,
                'module' => $module !== '' ? $module : null,
                'source_exists' => is_file($root . '/' . $source),
                'target_exists' => is_file($root . '/' . $target),
                'matches_source' => is_file($root . '/' . $source) && is_file($root . '/' . $target)
                    ? hash_file('sha256', $root . '/' . $source) === hash_file('sha256', $root . '/' . $target)
                    : false,
            ];
        }
    }
    return $items;
}

/** @return int */
function reference_count(string $root, string $needle): int
{
    $roots = ['app', 'apps', 'plugins', 'public', 'scripts', 'docs'];
    $count = 0;
    foreach ($roots as $scanRoot) {
        $path = $root . '/' . $scanRoot;
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
            if ($raw !== false && str_contains($raw, $needle)) {
                $count++;
            }
        }
    }
    return $count;
}

$ownerStyles = owner_style_targets($root);
$targetMap = [];
foreach ($ownerStyles as $item) {
    $targetMap[(string)$item['target']] = $item;
}

$publicFiles = files_under($root . '/public/assets');
foreach (['public/operator-sw.js'] as $extraPublicAsset) {
    $full = $root . '/' . $extraPublicAsset;
    if (is_file($full)) {
        $publicFiles[] = $full;
    }
}
sort($publicFiles);
$rows = [];
$counts = [];
$appSpecificOnlyPublic = [];
$duplicatedFromOwner = [];

foreach ($publicFiles as $file) {
    $rel = relpath($root, $file);
    $classification = 'INVESTIGATE';
    $owner = 'unknown';
    $source = '';
    $note = '';

    if (isset($targetMap[$rel])) {
        $item = $targetMap[$rel];
        $classification = !empty($item['generated']) ? 'GENERATED_OUTPUT' : 'DELIVERY_OUTPUT';
        $owner = (string)$item['app_key'];
        $source = (string)$item['source'];
        $note = !empty($item['matches_source']) ? 'matches owner source' : 'does not match owner source';
        if (!empty($item['matches_source'])) {
            $duplicatedFromOwner[] = $rel;
        } else {
            $warnings[] = "{$rel} is a published target but does not match owner source {$source}";
        }
    } elseif (str_starts_with($rel, 'public/assets/apps/')) {
        $classification = 'INVESTIGATE';
        $owner = 'unknown app delivery';
        $note = 'under app delivery root without manifest-declared owner source';
        $appSpecificOnlyPublic[] = $rel;
        $warnings[] = "{$rel} is app-scoped public output without a manifest-declared source";
    } elseif (str_starts_with($rel, 'public/assets/branding/platform/')) {
        $classification = 'SHARED_PLATFORM_ASSET';
        $owner = 'Platform branding';
        $source = $rel;
        $note = 'canonical platform branding public asset';
    } elseif (str_starts_with($rel, 'public/assets/branding/')) {
        $classification = 'BRANDING_ASSET';
        $owner = 'Platform/Organization branding compatibility';
        $source = $rel;
        $note = 'public branding source/compatibility asset';
    } elseif (in_array(basename($rel), ['normalize.css', 'theme.css', 'layout.css', 'wrapper-shared.css'], true)) {
        $classification = 'SHARED_PLATFORM_ASSET';
        $owner = 'Shell shared runtime';
        $source = $rel;
        $note = 'global stylesheet loaded by StyleRegistryService';
    } elseif (in_array(basename($rel), ['admin-surface.css', 'operator-surface.css', 'display-floor.css', 'procurement.css', 'work-entry.css', 'app.css'], true)) {
        $classification = 'KEEP_COMPAT';
        $owner = 'compatibility shim';
        $source = $rel;
        $note = 'deprecated public shim or stub retained for compatibility';
    } elseif (str_starts_with(basename($rel), 'operator-')) {
        $classification = 'SOURCE_ASSET';
        $owner = 'Shell/operator runtime compatibility';
        $source = $rel;
        $note = 'operator static runtime asset served directly from public';
    }

    $counts[$classification] = ($counts[$classification] ?? 0) + 1;
    $rows[] = [
        'asset' => $rel,
        'classification' => $classification,
        'owner' => $owner,
        'source' => $source,
        'refs' => reference_count($root, '/' . preg_replace('#^public/#', '', $rel)),
        'note' => $note,
    ];
}

ksort($counts);

echo 'asset|classification|owner|source|reference_files|note' . PHP_EOL;
foreach ($rows as $row) {
    echo implode('|', [
        $row['asset'],
        $row['classification'],
        $row['owner'],
        $row['source'],
        (string)$row['refs'],
        $row['note'],
    ]) . PHP_EOL;
}

echo PHP_EOL . 'owner_style_publish_map:' . PHP_EOL;
echo 'key|source|target|classification|matches_source' . PHP_EOL;
foreach ($ownerStyles as $item) {
    echo implode('|', [
        (string)$item['key'],
        (string)$item['source'],
        (string)$item['target'],
        !empty($item['generated']) ? 'GENERATED_OUTPUT' : 'DELIVERY_OUTPUT',
        !empty($item['matches_source']) ? 'yes' : 'no',
    ]) . PHP_EOL;
}

echo PHP_EOL . 'metrics:' . PHP_EOL;
echo 'public_assets.files=' . count($publicFiles) . PHP_EOL;
echo 'public_assets.by_classification=' . json_encode($counts, JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'owner_style_entries=' . count($ownerStyles) . PHP_EOL;
echo 'owner_style_targets_matching=' . count(array_filter($ownerStyles, static fn(array $item): bool => !empty($item['matches_source']))) . PHP_EOL;
echo 'public_app_assets.without_manifest_source=' . count($appSpecificOnlyPublic) . PHP_EOL;
echo 'public_app_assets.duplicated_from_owner_source=' . count($duplicatedFromOwner) . PHP_EOL;

echo PHP_EOL . 'answers:' . PHP_EOL;
echo 'generated_or_published_output=public/assets/apps/* files declared by app/generated manifests and matching owner source CSS' . PHP_EOL;
echo 'true_source_assets=global Shell runtime CSS, branding assets, operator realtime/offline static assets, and compatibility shims until moved behind owner source paths' . PHP_EOL;
echo 'owner_sources=apps/*/styles, apps/*/modules/*/styles.css, apps/Generated/*/styles.css, apps/Shell/styles for Shell globals/app surfaces, public branding for platform branding' . PHP_EOL;
echo 'app_specific_public_only=' . (count($appSpecificOnlyPublic) === 0 ? 'none_detected_under_public_assets_apps' : implode(',', $appSpecificOnlyPublic)) . PHP_EOL;
echo 'public_duplicates_owner_css=yes_public_assets_apps_are_delivery_duplicates; compatibility shims import app-owned CSS' . PHP_EOL;
echo 'cleanup_requirements=manifest/source parity, zero app-specific public-only findings, reference impact updates, publisher/readiness pass, and owner-approved relocation for true source/branding/operator assets' . PHP_EOL;

if ($appSpecificOnlyPublic !== []) {
    $failures[] = 'app-scoped public assets without manifest-declared owner sources found';
}

foreach ($warnings as $warning) {
    echo 'warning: ' . $warning . PHP_EOL;
}
foreach ($failures as $failure) {
    echo 'failure: ' . $failure . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, "RESULT: FAIL (public asset source/output boundary violations found)\n");
    exit(1);
}

echo 'RESULT: PASS (read-only public asset delivery diagnostic complete)' . PHP_EOL;
PHP
