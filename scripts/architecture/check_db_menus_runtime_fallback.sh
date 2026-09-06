#!/bin/bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

echo "[architecture] check_db_menus_runtime_fallback"
echo "- read-only diagnostic for DB menus fallback/source-of-truth status"

PHP_BIN="${PHP_BIN:-$(command -v php || true)}"
if [[ -z "$PHP_BIN" ]]; then
  echo "missing required binary: php" >&2
  exit 2
fi

"$PHP_BIN" <<'PHP'
<?php
declare(strict_types=1);

$root = getcwd();
$scanRoots = ['app', 'apps', 'plugins', 'packages', 'public', 'scripts'];
$failures = [];
$warnings = [];

/** @return list<string> */
function collect_files(string $root, array $scanRoots): array
{
    $files = [];
    foreach ($scanRoots as $scanRoot) {
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
            $relative = ltrim(str_replace($root, '', $filePath), '/');
            if ($relative === 'scripts/architecture/check_db_menus_runtime_fallback.sh'
                || $relative === 'docs/migration-cleanup/maps/batch-5-db-menus-runtime-fallback.md'
            ) {
                continue;
            }
            $files[] = $filePath;
        }
    }
    sort($files);
    return $files;
}

function relpath(string $root, string $path): string
{
    return ltrim(str_replace($root, '', $path), '/');
}

/** @return list<array{file:string,line:int,text:string,op:string,label:string}> */
function scan_patterns(string $root, array $files): array
{
    $sqlPatterns = [
        'SELECT' => '/\bSELECT\b[\s\S]{0,260}\bFROM\s+menus\b/i',
        'INSERT' => '/\bINSERT\s+INTO\s+menus\b/i',
        'UPDATE' => '/\bUPDATE\s+menus\b/i',
        'DELETE' => '/\bDELETE\s+FROM\s+menus\b/i',
        'TRUNCATE' => '/\bTRUNCATE\s+TABLE\s+menus\b/i',
        'ALTER' => '/\bALTER\s+TABLE\s+menus\b/i',
    ];
    $linePatterns = [
        'BASE_REGISTER_CALL' => '/\bbase_register_menus\s*\(/i',
        'MENU_FIELD' => '/\b(menu_key|parent_key|display_order|source_app_key)\b/i',
    ];

    $results = [];
    foreach ($files as $file) {
        $raw = @file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        $relative = relpath($root, $file);

        foreach ($sqlPatterns as $op => $pattern) {
            if (preg_match_all($pattern, $raw, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            foreach ($matches[0] as [$snippet, $offset]) {
                $lineNo = substr_count(substr($raw, 0, (int)$offset), "\n") + 1;
                $label = $op === 'SELECT'
                    ? (str_contains($relative, 'SearchService.php') ? 'SEARCH_ENRICHMENT' : 'RUNTIME_FALLBACK')
                    : 'COMPATIBILITY_TRUTH';
                $results[] = [
                    'file' => $relative,
                    'line' => $lineNo,
                    'text' => trim((string)$snippet),
                    'op' => $op,
                    'label' => $label,
                ];
            }
        }

        $isMenuContext = str_ends_with($relative, '/menu.php')
            || str_ends_with($relative, '/navigation.php')
            || str_contains($relative, 'SidebarBuilder.php')
            || str_contains($relative, 'SearchService.php')
            || str_contains($relative, 'AppRuntimeRegistryService.php')
            || str_contains($relative, 'AppManifestService.php')
            || str_contains($relative, 'ScaffoldGeneratorService.php')
            || str_contains($relative, 'plugins/Base/bootstrap.php')
            || str_contains($relative, 'plugins/Base/migrations/001_base_tables.sql')
            || (preg_match('/(manifest|plugin)\.json$/', $relative) === 1 && str_contains($raw, '"menus"'));

        $lines = preg_split('/\R/', $raw) ?: [];
        foreach ($lines as $idx => $line) {
            foreach ($linePatterns as $op => $pattern) {
                if ($op === 'MENU_FIELD' && !$isMenuContext) {
                    continue;
                }
                if ($op === 'MENU_FIELD' && str_contains(strtolower($line), 'permissions')) {
                    continue;
                }
                if (preg_match($pattern, $line) !== 1) {
                    continue;
                }
                $label = 'INVESTIGATE';
                if ($op === 'BASE_REGISTER_CALL') {
                    $label = 'COMPATIBILITY_TRUTH';
                } elseif (str_contains($relative, 'AppRuntimeRegistryService.php') || str_contains($relative, 'plugins/Base/bootstrap.php')) {
                    $label = 'COMPATIBILITY_TRUTH';
                } elseif (str_ends_with($relative, '/menu.php') || str_contains($relative, '/migrations/')) {
                    $label = 'LEGACY_SEED';
                } elseif (str_ends_with($relative, '/navigation.php') || preg_match('/(manifest|plugin)\.json$/', $relative) === 1) {
                    $label = 'SOURCE_OF_TRUTH_CANDIDATE';
                } elseif (str_contains($relative, 'SearchService.php')) {
                    $label = 'SEARCH_ENRICHMENT';
                } elseif (str_contains($relative, 'SidebarBuilder.php')) {
                    $label = 'RUNTIME_FALLBACK';
                } elseif (str_contains($relative, 'AppManifestService.php') || str_contains($relative, 'ScaffoldGeneratorService.php')) {
                    $label = 'SOURCE_OF_TRUTH_CANDIDATE';
                }
                $results[] = [
                    'file' => $relative,
                    'line' => $idx + 1,
                    'text' => trim((string)$line),
                    'op' => $op,
                    'label' => $label,
                ];
            }
        }
    }

    return $results;
}

/** @return list<string> */
function files_matching(string $root, array $files, callable $predicate): array
{
    $matches = [];
    foreach ($files as $file) {
        $relative = relpath($root, $file);
        if ($predicate($relative, $file)) {
            $matches[] = $relative;
        }
    }
    sort($matches);
    return $matches;
}

/** @return array<string,mixed> */
function read_db_evidence(string $root): array
{
    $dbFile = $root . '/app/Core/DB.php';
    if (!is_file($dbFile)) {
        return ['status' => 'unavailable', 'reason' => 'missing app/Core/DB.php'];
    }

    try {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', $root);
        }
        require_once $dbFile;
        $count = \App\Core\DB::fetchOne('SELECT COUNT(*) AS c FROM menus');
        $sourceTypes = [];
        try {
            $rows = \App\Core\DB::fetchAll("SELECT COALESCE(source_type, '<null>') AS source_type, COUNT(*) AS c FROM menus GROUP BY source_type ORDER BY source_type");
            foreach ($rows as $row) {
                $sourceTypes[(string)($row['source_type'] ?? '<unknown>')] = (int)($row['c'] ?? 0);
            }
        } catch (Throwable $e) {
            $sourceTypes['unavailable'] = 0;
        }
        return [
            'status' => 'available',
            'count' => (int)($count['c'] ?? 0),
            'source_types' => $sourceTypes,
        ];
    } catch (Throwable $e) {
        return ['status' => 'unavailable', 'reason' => preg_replace('/\s+/', '_', $e->getMessage())];
    }
}

$files = collect_files($root, $scanRoots);
$refs = scan_patterns($root, $files);
$menuFiles = files_matching($root, $files, static fn(string $relative): bool => str_ends_with($relative, '/menu.php'));
$navigationFiles = files_matching($root, $files, static fn(string $relative): bool => str_ends_with($relative, '/navigation.php'));
$manifestMenuFiles = files_matching($root, $files, static function (string $relative, string $file): bool {
    if (!preg_match('/(manifest|plugin)\.json$/', $relative)) {
        return false;
    }
    $raw = @file_get_contents($file);
    return $raw !== false && str_contains($raw, '"menus"');
});
$dbEvidence = read_db_evidence($root);

$opCounts = [];
$labelCounts = [];
foreach ($refs as $ref) {
    $opCounts[$ref['op']] = ($opCounts[$ref['op']] ?? 0) + 1;
    $labelCounts[$ref['label']] = ($labelCounts[$ref['label']] ?? 0) + 1;
}
ksort($opCounts);
ksort($labelCounts);

echo 'reference|operation|label|snippet' . PHP_EOL;
foreach ($refs as $ref) {
    echo $ref['file'] . ':' . $ref['line']
        . '|' . $ref['op']
        . '|' . $ref['label']
        . '|' . preg_replace('/\s+/', ' ', $ref['text'])
        . PHP_EOL;
}

echo PHP_EOL . 'metrics:' . PHP_EOL;
echo 'references.by_operation=' . json_encode($opCounts, JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'references.by_label=' . json_encode($labelCounts, JSON_UNESCAPED_SLASHES) . PHP_EOL;
echo 'menu_php.files=' . count($menuFiles) . PHP_EOL;
echo 'navigation_php.files=' . count($navigationFiles) . PHP_EOL;
echo 'manifest_or_plugin_json_with_menus.files=' . count($manifestMenuFiles) . PHP_EOL;
echo 'db_menus.status=' . (string)($dbEvidence['status'] ?? 'unknown') . PHP_EOL;
if (($dbEvidence['status'] ?? '') === 'available') {
    echo 'db_menus.row_count=' . (int)($dbEvidence['count'] ?? 0) . PHP_EOL;
    echo 'db_menus.source_type_counts=' . json_encode($dbEvidence['source_types'] ?? [], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    echo 'db_menus.reason=' . (string)($dbEvidence['reason'] ?? 'unknown') . PHP_EOL;
}

echo PHP_EOL . 'classification:' . PHP_EOL;
echo 'DB:menus=RUNTIME_FALLBACK + SEARCH_ENRICHMENT + COMPATIBILITY_TRUTH + LEGACY_SEED + INVESTIGATE' . PHP_EOL;
echo 'menu.php files=LEGACY_SEED + COMPATIBILITY_TRUTH' . PHP_EOL;
echo 'manifest menus=SOURCE_OF_TRUTH candidate for owner contracts; still bridged through DB menus in lifecycle paths' . PHP_EOL;
echo 'navigation.php files=SOURCE_OF_TRUTH for owner navigation contributions; DB menus can duplicate or enrich them' . PHP_EOL;

echo PHP_EOL . 'answers:' . PHP_EOL;
echo 'readers=app/Core/SidebarBuilder.php; app/Core/SearchService.php; plugins/Base/bootstrap.php legacy render helper; diagnostics/scripts/docs' . PHP_EOL;
echo 'writers=plugins/Base/bootstrap.php base_register_menus; module/plugin bootstraps through base_register_menus; app/Services/AppRuntimeRegistryService.php; app/Core/PluginManager.php reload truncate path' . PHP_EOL;
echo 'runtime_sidebar_required=current_compatibility_yes: SidebarBuilder reads DB menus for fallback/dynamic extension/dedupe and Base legacy renderer reads menus directly' . PHP_EOL;
echo 'duplicates_navigation_contracts=yes_partial: menu.php/manifest menus/navigation.php overlap; SidebarBuilder explicitly dedupes menu table URLs' . PHP_EOL;
echo 'future_cleanup_status=can_be_treated_as_fallback_cache_only_after_parity_proof; must remain compatibility truth until runtime/search/write paths are retired or redirected' . PHP_EOL;
echo 'before_reduction=prove sidebar/search parity without DB menus; map rows to owner navigation/manifest contracts; retire or redirect base_register_menus writers; preserve lifecycle sync semantics; confirm no Studio/generated/package dependency on DB menus' . PHP_EOL;

if (($opCounts['SELECT'] ?? 0) > 0) {
    $warnings[] = 'DB menus is actively read by runtime/search code; do not remove or demote yet.';
}
if ((($opCounts['INSERT'] ?? 0) + ($opCounts['DELETE'] ?? 0) + ($opCounts['TRUNCATE'] ?? 0) + ($opCounts['ALTER'] ?? 0) + ($opCounts['BASE_REGISTER_CALL'] ?? 0)) > 0) {
    $warnings[] = 'DB menus has active writer/bootstrap/lifecycle paths; cleanup requires a migration plan.';
}

foreach ($warnings as $warning) {
    echo 'warning: ' . $warning . PHP_EOL;
}
foreach ($failures as $failure) {
    echo 'failure: ' . $failure . PHP_EOL;
}

if ($failures !== []) {
    fwrite(STDERR, "RESULT: FAIL (DB menus fallback diagnostic found blockers)\n");
    exit(1);
}

echo 'RESULT: PASS (read-only DB menus fallback diagnostic complete)' . PHP_EOL;
PHP
