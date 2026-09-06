#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_generated_apps_archive_candidates"
echo "- read-only diagnostic for generated app archive/delete candidacy"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "missing required binary: php" >&2
  exit 2
fi

"$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
$generatedRoot = $root . '/apps/Generated';

if (!is_dir($generatedRoot)) {
    fwrite(STDERR, "RESULT: FAIL (apps/Generated missing)\n");
    exit(1);
}

/** @return list<string> */
function files_matching(string $pattern): array
{
    $files = glob($pattern) ?: [];
    sort($files);
    return array_values(array_filter($files, 'is_file'));
}

/** @return list<string> */
function rels(array $paths, string $root): array
{
    return array_map(static fn(string $path): string => str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path, $paths);
}

/** @return array<string,mixed> */
function read_json_file(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : [];
}

/** @return int */
function grep_count(string $needle, array $roots): int
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
            if ($raw !== false && str_contains($raw, $needle)) {
                $count++;
            }
        }
    }
    return $count;
}

/** @return array<string,array<string,mixed>> */
function load_appstudio_registry(string $root): array
{
    $path = $root . '/storage/appstudio/apps_registry.json';
    $decoded = read_json_file($path);
    return $decoded;
}

/** @return array<string,string> */
function core_apps_evidence(string $root, array $appKeys): array
{
    $result = [];
    foreach ($appKeys as $key) {
        $result[$key] = 'not_checked';
    }

    $dbFile = $root . '/app/Core/DB.php';
    if (!is_file($dbFile)) {
        foreach ($appKeys as $key) {
            $result[$key] = 'unavailable:no_db_helper';
        }
        return $result;
    }

    try {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $root);
        }
        require_once $dbFile;
        if (!class_exists('\\App\\Core\\DB')) {
            throw new RuntimeException('DB class unavailable');
        }
        $rows = \App\Core\DB::fetchAll('SELECT app_key FROM core_apps');
        $registered = [];
        foreach ($rows as $row) {
            $registered[(string)($row['app_key'] ?? '')] = true;
        }
        foreach ($appKeys as $key) {
            $result[$key] = isset($registered[$key]) ? 'registered' : 'not_registered';
        }
    } catch (Throwable $e) {
        foreach ($appKeys as $key) {
            $result[$key] = 'unavailable:' . preg_replace('/\s+/', '_', $e->getMessage());
        }
    }

    return $result;
}

$appDirs = array_values(array_filter(glob($generatedRoot . '/*') ?: [], 'is_dir'));
sort($appDirs);
$appKeys = array_map('basename', $appDirs);
$registry = load_appstudio_registry($root);
$coreApps = core_apps_evidence($root, $appKeys);

$failures = [];
$warnings = [];

echo "app|module|classification|safe_action|registry|core_apps|routes|navigation|public_assets|generated_data|snapshots|grep_refs\n";

foreach ($appDirs as $appDir) {
    $appKey = basename($appDir);
    $appManifest = $appDir . '/manifest.json';
    $moduleDirs = array_values(array_filter(glob($appDir . '/*') ?: [], 'is_dir'));
    sort($moduleDirs);
    $moduleKeys = $moduleDirs !== [] ? array_map('basename', $moduleDirs) : ['(app)'];

    $registryRow = is_array($registry[$appKey] ?? null) ? $registry[$appKey] : [];
    $registryStatus = $registryRow !== []
        ? (string)($registryRow['status'] ?? 'present')
        : 'absent';
    $coreEvidence = $coreApps[$appKey] ?? 'not_checked';

    foreach ($moduleKeys as $moduleKey) {
        $modulePath = $moduleKey === '(app)' ? $appDir : $appDir . '/' . $moduleKey;
        $manifestFiles = array_merge(
            is_file($appManifest) ? [$appManifest] : [],
            files_matching($modulePath . '/manifest.json'),
            files_matching($modulePath . '/module.json')
        );
        $routeFiles = files_matching($modulePath . '/routes.php');
        $navFiles = files_matching($modulePath . '/navigation.php');
        $publicAssets = files_matching($root . '/public/assets/apps/' . $appKey . '/styles/*.css');
        $generatedData = $moduleKey === '(app)'
            ? files_matching($root . '/storage/appstudio/generated_data/' . $appKey . '/*.json')
            : files_matching($root . '/storage/appstudio/generated_data/' . $appKey . '/' . $moduleKey . '.json');
        $snapshotFiles = [];
        foreach (files_matching($root . '/storage/appstudio/snapshots/*.json') as $snapshot) {
            $raw = (string)file_get_contents($snapshot);
            if (str_contains($raw, $appKey) && ($moduleKey === '(app)' || str_contains($raw, $moduleKey))) {
                $snapshotFiles[] = $snapshot;
            }
        }

        $grepRefs = grep_count($appKey, [
            $root . '/app',
            $root . '/apps',
            $root . '/plugins',
            $root . '/public',
            $root . '/scripts',
            $root . '/docs',
        ]);

        $classification = 'INVESTIGATE';
        $safeAction = 'preserve until owner/runtime evidence is reviewed';

        if ($appKey === 'tmp') {
            $classification = 'TEMP_STAGING';
            $safeAction = 'preserve until Studio tmp retention policy exists';
        } elseif ($appKey === 'runtime') {
            $classification = 'ACTIVE_RUNTIME';
            $safeAction = 'keep: generated runtime support artifact';
        } elseif ($appKey === 'sample_app') {
            $classification = 'GENERATED_SAMPLE';
            $safeAction = 'keep for now: sample/template evidence and registry/storage coupling exist';
        } elseif (in_array($appKey, ['hardening_app', 'lifecycle_app', 'rollback_app'], true)) {
            $classification = 'TEST_FIXTURE';
            $safeAction = 'keep for now: fixture/hardening lifecycle evidence exists';
        } elseif ($registryStatus === 'enabled') {
            $classification = 'ACTIVE_RUNTIME';
            $safeAction = 'keep: enabled in appstudio registry';
        }

        $runtimeCoupling = $routeFiles !== [] || $navFiles !== [] || $publicAssets !== [] || $generatedData !== [] || $snapshotFiles !== [] || $grepRefs > count($manifestFiles);
        if ($classification === 'DELETE_CANDIDATE' && $runtimeCoupling) {
            $failures[] = "{$appKey}/{$moduleKey}: DELETE_CANDIDATE has coupling";
        }
        if ($classification === 'ARCHIVE_CANDIDATE' && $registryStatus === 'enabled') {
            $failures[] = "{$appKey}/{$moduleKey}: ARCHIVE_CANDIDATE is enabled in appstudio registry";
        }
        if ($classification === 'INVESTIGATE') {
            $warnings[] = "{$appKey}/{$moduleKey}: uncertainty remains; preserved as INVESTIGATE";
        }

        echo implode('|', [
            $appKey,
            $moduleKey,
            $classification,
            $safeAction,
            $registryStatus,
            $coreEvidence,
            (string)count($routeFiles),
            (string)count($navFiles),
            (string)count($publicAssets),
            (string)count($generatedData),
            (string)count($snapshotFiles),
            (string)$grepRefs,
        ]) . PHP_EOL;

        echo '  path: ' . str_replace($root . '/', '', $modulePath) . PHP_EOL;
        echo '  manifest_files: ' . implode(', ', rels($manifestFiles, $root)) . PHP_EOL;
        echo '  route_files: ' . implode(', ', rels($routeFiles, $root)) . PHP_EOL;
        echo '  navigation_files: ' . implode(', ', rels($navFiles, $root)) . PHP_EOL;
        echo '  public_assets: ' . implode(', ', rels($publicAssets, $root)) . PHP_EOL;
        echo '  generated_data: ' . implode(', ', rels($generatedData, $root)) . PHP_EOL;
        echo '  snapshot_refs: ' . implode(', ', rels($snapshotFiles, $root)) . PHP_EOL;
    }
}

echo PHP_EOL . "Policy:" . PHP_EOL;
echo "- DELETE_CANDIDATE requires zero runtime, storage, public, route, nav, script, and docs coupling." . PHP_EOL;
echo "- ARCHIVE_CANDIDATE requires no active registry/runtime use but may preserve historical/snapshot evidence." . PHP_EOL;
echo "- TEMP_STAGING must be preserved unless a Studio tmp retention policy says otherwise." . PHP_EOL;
echo "- Any uncertainty becomes INVESTIGATE." . PHP_EOL;

if ($warnings !== []) {
    foreach ($warnings as $warning) {
        echo "warning: {$warning}" . PHP_EOL;
    }
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo "failure: {$failure}" . PHP_EOL;
    }
    fwrite(STDERR, "RESULT: FAIL (generated app archive policy violation)\n");
    exit(1);
}

echo "RESULT: PASS (diagnostic only; no generated apps deleted, archived, or moved)" . PHP_EOL;
PHP
