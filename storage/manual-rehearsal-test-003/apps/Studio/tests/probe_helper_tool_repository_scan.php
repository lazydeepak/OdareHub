<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/apps/Studio/Tools/HelperTool/Services/RepoTreeScannerService.php';

use Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService;

$passed = 0;
$failed = 0;

function ht_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    echo "  FAIL [{$label}]\n";
}

function ht_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }

    $failed++;
    echo "  FAIL [{$label}]: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . "\n";
}

function ht_write_fixture_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($path, $content);
}

function ht_remove_dir(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $entries = scandir($path);
    if ($entries === false) {
        return;
    }

    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child) && !is_link($child)) {
            ht_remove_dir($child);
            continue;
        }
        @unlink($child);
    }
    @rmdir($path);
}

function ht_category(array $scan, string $key): array
{
    $summary = isset($scan['suspicious_summary']) && is_array($scan['suspicious_summary']) ? $scan['suspicious_summary'] : [];
    $categories = isset($summary['categories']) && is_array($summary['categories']) ? $summary['categories'] : [];
    return isset($categories[$key]) && is_array($categories[$key]) ? $categories[$key] : [];
}

function ht_type_row(array $scan, string $type): array
{
    $summary = isset($scan['file_type_summary']) && is_array($scan['file_type_summary']) ? $scan['file_type_summary'] : [];
    $types = isset($summary['types']) && is_array($summary['types']) ? $summary['types'] : [];
    foreach ($types as $row) {
        if (is_array($row) && (string)($row['type'] ?? '') === $type) {
            return $row;
        }
    }
    return [];
}

function ht_find_tree_node(array $node, string $relativePath): array
{
    if ((string)($node['relative_path'] ?? '') === $relativePath) {
        return $node;
    }

    $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
    foreach ($children as $child) {
        if (!is_array($child)) {
            continue;
        }
        $found = ht_find_tree_node($child, $relativePath);
        if ($found !== []) {
            return $found;
        }
    }

    return [];
}

$fixtureRoot = sys_get_temp_dir() . '/sbaio-helper-scan-' . bin2hex(random_bytes(4));
mkdir($fixtureRoot, 0777, true);

try {
    ht_write_fixture_file($fixtureRoot . '/src/a.php', '<?php echo "a";');
    ht_write_fixture_file($fixtureRoot . '/src/b.md', '# B');
    ht_write_fixture_file($fixtureRoot . '/docs/readme.txt', 'readme');
    ht_write_fixture_file($fixtureRoot . '/docs/long.txt', str_repeat("large preview line\n", 3000));
    ht_write_fixture_file($fixtureRoot . '/var/tmp/cache.tmp', 't');
    ht_write_fixture_file($fixtureRoot . '/.DS_Store', 'd');
    ht_write_fixture_file($fixtureRoot . '/backup/config.php.bak', 'b');
    ht_write_fixture_file($fixtureRoot . '/mystery', 'u');
    ht_write_fixture_file($fixtureRoot . '/Dockerfile', 'FROM scratch');
    ht_write_fixture_file($fixtureRoot . '/assets/app.css', 'body{}');
    ht_write_fixture_file($fixtureRoot . '/assets/app.js', 'console.log(1);');
    ht_write_fixture_file($fixtureRoot . '/assets/data.json', '{}');
    ht_write_fixture_file($fixtureRoot . '/assets/pixel.bin', "abc\0def");
    ht_write_fixture_file($fixtureRoot . '/config/app.yml', 'a: b');
    ht_write_fixture_file($fixtureRoot . '/config/app.neon', 'x: y');
    ht_write_fixture_file($fixtureRoot . '/data/feed.xml', '<x/>');
    ht_write_fixture_file($fixtureRoot . '/data/list.csv', "a,b\n");
    ht_write_fixture_file($fixtureRoot . '/composer.lock', '{}');
    ht_write_fixture_file($fixtureRoot . '/.opencode/node_modules/pkg/index.js', 'module.exports = {};');
    ht_write_fixture_file($fixtureRoot . '/vendor/bin/phpunit', '#!/usr/bin/env php');
    ht_write_fixture_file($fixtureRoot . '/vendor/package/composer.json', '{}');
    ht_write_fixture_file($fixtureRoot . '/storage/studio-snapshots/snapshot.bak', 'snapshot');
    ht_write_fixture_file($fixtureRoot . '/.git/ignored.php', '<?php');

    $largePath = $fixtureRoot . '/storage/logs/php-error.log';
    ht_write_fixture_file($largePath, '');
    $largeHandle = fopen($largePath, 'wb');
    if ($largeHandle !== false) {
        fseek($largeHandle, 5242880 - 1);
        fwrite($largeHandle, 'x');
        fclose($largeHandle);
    }

    $scan = RepoTreeScannerService::scanPath($fixtureRoot);
    $inventory = isset($scan['inventory_summary']) && is_array($scan['inventory_summary']) ? $scan['inventory_summary'] : [];

    ht_assert_eq(23, $scan['counters']['files'] ?? null, 'scan counts files from fixture evidence');
    ht_assert_eq(18, $scan['counters']['dirs'] ?? null, 'scan counts traversed folders from fixture evidence');
    ht_assert_eq(1, $scan['counters']['skipped'] ?? null, 'scan skips .git at repository root');
    ht_assert_eq(23, $inventory['total_files'] ?? null, 'inventory exposes total files');
    ht_assert_eq(18, $inventory['total_folders'] ?? null, 'inventory exposes total folders');
    ht_assert_true((int)($inventory['total_bytes'] ?? 0) >= 5242880, 'inventory exposes total bytes');
    ht_assert_eq($scan['duration_ms'] ?? null, $inventory['scan_duration_ms'] ?? null, 'inventory mirrors scan duration');

    ht_assert_true(ht_type_row($scan, 'other') !== [], 'file type summary includes other bucket beyond top types');
    ht_assert_true((int)($scan['file_type_summary']['total_types'] ?? 0) > 10, 'file type summary records total type count');

    ht_assert_eq(4, ht_category($scan, 'review_candidates')['count'] ?? null, 'review candidate count separates actual review files');
    ht_assert_eq(3, ht_category($scan, 'runtime_dependency_artifacts')['count'] ?? null, 'runtime/dependency artifact count separates dependency files');
    ht_assert_eq(1, ht_category($scan, 'snapshot_recovery_artifacts')['count'] ?? null, 'snapshot/recovery artifact count separates snapshot backups');
    ht_assert_eq(1, ht_category($scan, 'unknown_needs_classification')['count'] ?? null, 'unknown classification count excludes dependency artifacts');
    ht_assert_true(str_contains(json_encode(ht_category($scan, 'review_candidates'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), '.DS_Store'), 'DS_Store remains a review candidate');
    ht_assert_true(str_contains(json_encode(ht_category($scan, 'review_candidates'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'php-error.log'), 'oversized php-error.log remains a review candidate');
    ht_assert_true(str_contains(json_encode(ht_category($scan, 'runtime_dependency_artifacts'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), '.opencode/node_modules'), '.opencode/node_modules is dependency/runtime artifact');
    ht_assert_true(str_contains(json_encode(ht_category($scan, 'runtime_dependency_artifacts'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'vendor/bin/phpunit'), 'vendor/bin extensionless file is dependency/runtime artifact');
    ht_assert_true(str_contains(json_encode(ht_category($scan, 'snapshot_recovery_artifacts'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'storage/studio-snapshots/snapshot.bak'), 'studio snapshot .bak is snapshot/recovery artifact');

    $tree = isset($scan['tree']) && is_array($scan['tree']) ? $scan['tree'] : [];
    ht_assert_true(in_array('runtime_dependency_artifacts', ht_find_tree_node($tree, '.opencode/node_modules/pkg/index.js')['artifact_categories'] ?? [], true), 'tree metadata marks .opencode dependency artifact');
    ht_assert_true(in_array('snapshot_recovery_artifacts', ht_find_tree_node($tree, 'storage/studio-snapshots/snapshot.bak')['artifact_categories'] ?? [], true), 'tree metadata marks snapshot artifact');
    ht_assert_true(in_array('review_candidates', ht_find_tree_node($tree, '.DS_Store')['artifact_categories'] ?? [], true), 'tree metadata marks DS_Store review candidate');
    ht_assert_true(in_array('unknown_needs_classification', ht_find_tree_node($tree, 'mystery')['artifact_categories'] ?? [], true), 'tree metadata marks unknown extensionless file');

    $textInspection = RepoTreeScannerService::inspectFileFromRoot('src/a.php', $fixtureRoot);
    ht_assert_eq(true, $textInspection['ok'] ?? null, 'text inspector opens scanned text file');
    ht_assert_eq('src/a.php', $textInspection['path'] ?? null, 'text inspector returns normalized path');
    ht_assert_eq('.php', $textInspection['type'] ?? null, 'text inspector returns file type');
    ht_assert_eq(true, $textInspection['preview']['is_text'] ?? null, 'text inspector marks text preview');
    ht_assert_eq(1, $textInspection['preview']['lines'][0]['line'] ?? null, 'text inspector includes line number');
    ht_assert_true(str_contains((string)($textInspection['preview']['lines'][0]['text'] ?? ''), 'echo'), 'text inspector includes preview content');

    $largeInspection = RepoTreeScannerService::inspectFileFromRoot('docs/long.txt', $fixtureRoot);
    ht_assert_eq(true, $largeInspection['ok'] ?? null, 'large text inspector opens file');
    ht_assert_eq(true, $largeInspection['preview']['is_text'] ?? null, 'large text inspector remains text');
    ht_assert_eq(true, $largeInspection['preview']['is_truncated'] ?? null, 'large text inspector truncates preview');
    ht_assert_true(count($largeInspection['preview']['lines'] ?? []) <= 400, 'large text inspector caps preview lines');

    $binaryInspection = RepoTreeScannerService::inspectFileFromRoot('assets/pixel.bin', $fixtureRoot);
    ht_assert_eq(true, $binaryInspection['ok'] ?? null, 'binary inspector opens file metadata');
    ht_assert_eq(false, $binaryInspection['preview']['is_text'] ?? null, 'binary inspector suppresses text preview');
    ht_assert_eq([], $binaryInspection['preview']['lines'] ?? null, 'binary inspector returns no preview lines');

    $escapeInspection = RepoTreeScannerService::inspectFileFromRoot('../AGENTS.md', $fixtureRoot);
    ht_assert_eq(false, $escapeInspection['ok'] ?? null, 'inspector blocks traversal outside root');

    $skippedPaths = isset($scan['skipped_paths']) && is_array($scan['skipped_paths']) ? $scan['skipped_paths'] : [];
    ht_assert_eq('.git', $skippedPaths[0]['path'] ?? null, 'skipped path evidence includes .git');
    ht_assert_eq('repository_metadata', $skippedPaths[0]['reason'] ?? null, 'skipped path evidence includes reason');

    $helperToolModel = [
        'scan_requested' => true,
        'scan_result' => $scan,
    ];
    ob_start();
    require APP_ROOT . '/apps/Studio/Tools/HelperTool/Views/preview.php';
    $html = (string)ob_get_clean();

    ht_assert_true(str_contains($html, 'Scan Summary'), 'view renders scan summary section heading');
    ht_assert_true(str_contains($html, 'Repository Inventory Summary'), 'view renders inventory summary panel');
    ht_assert_true(str_contains($html, 'File Type Summary'), 'view renders file type summary panel');
    ht_assert_true(str_contains($html, 'Review Buckets'), 'view renders review buckets section heading');
    ht_assert_true(str_contains($html, 'Review candidates are not errors'), 'view clarifies review candidates are not errors');
    ht_assert_true(str_contains($html, 'Runtime/Dependency Artifacts'), 'view renders runtime/dependency artifact category');
    ht_assert_true(str_contains($html, 'Snapshot/Recovery Artifacts'), 'view renders snapshot/recovery artifact category');
    ht_assert_true(str_contains($html, 'Unknown/Needs Classification'), 'view renders unknown classification category');
    ht_assert_true(str_contains($html, 'data-tree-filter="all"'), 'view renders show-all tree filter');
    ht_assert_true(str_contains($html, 'data-tree-filter="hide-runtime"'), 'view renders hide-runtime tree filter');
    ht_assert_true(str_contains($html, 'data-tree-filter="hide-snapshot"'), 'view renders hide-snapshot tree filter');
    ht_assert_true(str_contains($html, 'data-tree-filter="review-only"'), 'view renders review-only tree filter');
    ht_assert_true(str_contains($html, 'data-tree-filter="unknown-only"'), 'view renders unknown-only tree filter');
    ht_assert_true(str_contains($html, 'data-default-filter="hide-runtime"'), 'tree defaults to hiding runtime/dependency artifacts');
    ht_assert_true(str_contains($html, 'data-categories="runtime_dependency_artifacts"'), 'tree renders runtime/dependency category metadata');
    ht_assert_true(str_contains($html, 'data-categories="snapshot_recovery_artifacts"'), 'tree renders snapshot/recovery category metadata');
    ht_assert_true(str_contains($html, 'data-file-inspector'), 'view renders file inspector panel');
    ht_assert_true(str_contains($html, 'data-inspector-path="src/a.php"'), 'view renders clickable file inspector path');
    ht_assert_true(str_contains($html, 'File Inspector'), 'view renders file inspector title');
    ht_assert_true(str_contains($html, 'ht-tree-list'), 'view keeps existing tree output');
    ht_assert_true(str_contains($html, 'src/a.php'), 'view renders scanned tree file evidence');
    ht_assert_true(str_contains($html, 'ht-root-files-group'), 'tree renders root files group for top-level files');
    ht_assert_true(str_contains($html, 'ht-file-badge'), 'tree renders classification badges on files');
    ht_assert_true(str_contains($html, 'Root files'), 'root files group has human-readable label');
    ht_assert_true(str_contains($html, 'Review'), 'classification badge renders for review candidate category');
    $firstDirPos = strpos($html, 'ht-tree-dir');
    $firstGroupPos = strpos($html, 'ht-root-files-group');
    ht_assert_true($firstDirPos !== false && $firstGroupPos !== false && $firstDirPos < $firstGroupPos, 'directories render before root files group (folder-first order)');
    $dsStorePos = strpos($html, 'data-inspector-path=".DS_Store"');
    $mysteryPos = strpos($html, 'data-inspector-path="mystery"');
    ht_assert_true($dsStorePos !== false && $mysteryPos !== false, 'root files appear inside root files group');
    ht_assert_true($dsStorePos > $firstGroupPos && $mysteryPos > $firstGroupPos, 'root files appear after ht-root-files-group open');
    ht_assert_true(preg_match('/<li[^>]*data-categories="[^"]*\bruntime_dependency_artifacts\b[^"]*"[^>]*\bhidden\b/', $html) === 1, 'runtime dependency file/dir has hidden attribute in server render');
    ht_assert_true(preg_match('/<li[^>]*data-categories="review_candidates"[^>]*\bhidden\b/', $html) === 0, 'review candidate nodes do NOT have hidden attribute');
// === Subtree Filtering Tests ===
$filterRoot = sys_get_temp_dir() . '/sbaio-helper-filter-' . bin2hex(random_bytes(4));
mkdir($filterRoot, 0777, true);

try {
    mkdir($filterRoot . '/.opencode', 0777, true);
    file_put_contents($filterRoot . '/.opencode/config.json', '{}');
    file_put_contents($filterRoot . '/.opencode/package.json', '{}');

    mkdir($filterRoot . '/.opencode/node_modules/pkg-a/src', 0777, true);
    file_put_contents($filterRoot . '/.opencode/node_modules/pkg-a/src/index.js', 'a');
    file_put_contents($filterRoot . '/.opencode/node_modules/pkg-a/package.json', '{}');
    mkdir($filterRoot . '/.opencode/node_modules/pkg-a/node_modules/dep-x', 0777, true);
    file_put_contents($filterRoot . '/.opencode/node_modules/pkg-a/node_modules/dep-x/index.js', 'b');
    mkdir($filterRoot . '/.opencode/node_modules/pkg-b', 0777, true);
    file_put_contents($filterRoot . '/.opencode/node_modules/pkg-b/index.js', 'c');

    mkdir($filterRoot . '/vendor/composer', 0777, true);
    file_put_contents($filterRoot . '/vendor/composer/installed.json', '{}');
    file_put_contents($filterRoot . '/vendor/autoload.php', '<?php');
    file_put_contents($filterRoot . '/vendor/.DS_Store', 'd');

    mkdir($filterRoot . '/var/tmp', 0777, true);
    file_put_contents($filterRoot . '/var/tmp/cache.tmp', 'cached');
    file_put_contents($filterRoot . '/.DS_Store', 'd');
    file_put_contents($filterRoot . '/mystery', 'u');

    mkdir($filterRoot . '/src', 0777, true);
    file_put_contents($filterRoot . '/src/app.php', '<?php');

    $filterScan = RepoTreeScannerService::scanPath($filterRoot);
    $filterTree = $filterScan['tree'] ?? [];

    function ht_collect_file_paths(array $node): array {
        $paths = [];
        if (($node['type'] ?? '') === 'file') {
            $paths[] = $node['relative_path'] ?? '';
        }
        foreach ($node['children'] ?? [] as $child) {
            $paths = array_merge($paths, ht_collect_file_paths($child));
        }
        return $paths;
    }

    function ht_find_file_node(array $node, string $relPath): ?array {
        if (($node['relative_path'] ?? '') === $relPath && ($node['type'] ?? '') === 'file') {
            return $node;
        }
        foreach ($node['children'] ?? [] as $child) {
            $found = ht_find_file_node($child, $relPath);
            if ($found !== null) { return $found; }
        }
        return null;
    }

    function ht_should_count_file(array $fileNode, string $filter, bool $dirIsHideCat): bool {
        $cats = $fileNode['artifact_categories'] ?? [];
        if ($filter === 'all') { return true; }
        if ($filter === 'hide-runtime') {
            if ($dirIsHideCat && in_array('review_candidates', $cats, true) && !in_array('runtime_dependency_artifacts', $cats, true)) {
                return false;
            }
            return !in_array('runtime_dependency_artifacts', $cats, true);
        }
        if ($filter === 'hide-snapshot') {
            if ($dirIsHideCat && in_array('review_candidates', $cats, true) && !in_array('snapshot_recovery_artifacts', $cats, true)) {
                return false;
            }
            return !in_array('snapshot_recovery_artifacts', $cats, true);
        }
        if ($filter === 'review-only') { return in_array('review_candidates', $cats, true); }
        if ($filter === 'unknown-only') { return in_array('unknown_needs_classification', $cats, true); }
        return true;
    }

    function ht_dir_has_visible_file(array $dirNode, string $filter): bool {
        $hideCat = null;
        if ($filter === 'hide-runtime') { $hideCat = 'runtime_dependency_artifacts'; }
        elseif ($filter === 'hide-snapshot') { $hideCat = 'snapshot_recovery_artifacts'; }
        $dirHasHideCat = $hideCat && in_array($hideCat, $dirNode['artifact_categories'] ?? [], true);
        foreach ($dirNode['children'] ?? [] as $child) {
            if ($child['type'] === 'file') {
                if (ht_should_count_file($child, $filter, $dirHasHideCat)) { return true; }
            } elseif (ht_dir_has_visible_file($child, $filter)) {
                return true;
            }
        }
        return false;
    }

    function ht_visible_dir_paths(array $node, string $filter): array {
        $paths = [];
        if (($node['type'] ?? '') !== 'dir') { return $paths; }
        foreach ($node['children'] ?? [] as $child) {
            $paths = array_merge($paths, ht_visible_dir_paths($child, $filter));
        }
        if (ht_dir_has_visible_file($node, $filter)) {
            $paths[] = $node['relative_path'] ?? '';
        }
        return $paths;
    }

    $hideRuntimeDirs = ht_visible_dir_paths($filterTree, 'hide-runtime');
    ht_assert_true(in_array('.', $hideRuntimeDirs) || in_array('', $hideRuntimeDirs), 'root visible in hide-runtime');
    ht_assert_true(in_array('.opencode', $hideRuntimeDirs), '.opencode/ visible in hide-runtime (has config.json)');
    ht_assert_true(in_array('var', $hideRuntimeDirs), 'var/ visible in hide-runtime (has cache.tmp)');
    ht_assert_true(in_array('var/tmp', $hideRuntimeDirs), 'var/tmp/ visible in hide-runtime (has cache.tmp)');
    ht_assert_true(in_array('src', $hideRuntimeDirs), 'src/ visible in hide-runtime (has app.php)');
    ht_assert_true(!in_array('.opencode/node_modules', $hideRuntimeDirs), 'node_modules/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('.opencode/node_modules/pkg-a', $hideRuntimeDirs), 'pkg-a/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('.opencode/node_modules/pkg-a/src', $hideRuntimeDirs), 'pkg-a/src/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('.opencode/node_modules/pkg-a/node_modules', $hideRuntimeDirs), 'nested node_modules/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('.opencode/node_modules/pkg-a/node_modules/dep-x', $hideRuntimeDirs), 'dep-x/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('.opencode/node_modules/pkg-b', $hideRuntimeDirs), 'pkg-b/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('vendor', $hideRuntimeDirs), 'vendor/ hidden in hide-runtime (all runtime)');
    ht_assert_true(!in_array('vendor/composer', $hideRuntimeDirs), 'vendor/composer/ hidden in hide-runtime (all runtime)');

    $reviewDirs = ht_visible_dir_paths($filterTree, 'review-only');
    ht_assert_true(in_array('.', $reviewDirs) || in_array('', $reviewDirs), 'root visible in review-only');
    ht_assert_true(in_array('var', $reviewDirs), 'var/ visible in review-only (has cache.tmp)');
    ht_assert_true(in_array('var/tmp', $reviewDirs), 'var/tmp/ visible in review-only (has cache.tmp)');
    ht_assert_true(!in_array('.opencode', $reviewDirs), '.opencode/ hidden in review-only (no candidates)');
    ht_assert_true(!in_array('.opencode/node_modules', $reviewDirs), 'node_modules/ hidden in review-only (no candidates)');
    ht_assert_true(!in_array('src', $reviewDirs), 'src/ hidden in review-only (no candidates)');
    ht_assert_true(in_array('vendor', $reviewDirs), 'vendor/ visible in review-only (has .DS_Store)');

    $unknownDirs = ht_visible_dir_paths($filterTree, 'unknown-only');
    ht_assert_true(in_array('.', $unknownDirs) || in_array('', $unknownDirs), 'root visible in unknown-only');
    ht_assert_true(!in_array('src', $unknownDirs), 'src/ hidden in unknown-only (no unknown files)');
    ht_assert_true(!in_array('var', $unknownDirs), 'var/ hidden in unknown-only (no unknown files)');
    ht_assert_true(!in_array('.opencode', $unknownDirs), '.opencode/ hidden in unknown-only (no unknown files)');

    $allDirs = ht_visible_dir_paths($filterTree, 'all');
    ht_assert_true(in_array('.', $allDirs) || in_array('', $allDirs), 'root visible in all');
    ht_assert_true(in_array('.opencode', $allDirs), '.opencode/ visible in all');
    ht_assert_true(in_array('.opencode/node_modules', $allDirs), 'node_modules/ visible in all');
    ht_assert_true(in_array('.opencode/node_modules/pkg-a', $allDirs), 'pkg-a/ visible in all');
    ht_assert_true(in_array('.opencode/node_modules/pkg-a/node_modules', $allDirs), 'nested node_modules/ visible in all');
    ht_assert_true(in_array('vendor', $allDirs), 'vendor/ visible in all');
    ht_assert_true(in_array('vendor/composer', $allDirs), 'vendor/composer/ visible in all');
    ht_assert_true(in_array('var', $allDirs), 'var/ visible in all');
    ht_assert_true(in_array('var/tmp', $allDirs), 'var/tmp/ visible in all');
    ht_assert_true(in_array('src', $allDirs), 'src/ visible in all');

    $summaryCat = $filterScan['suspicious_summary']['categories'] ?? [];
    ht_assert_true(($summaryCat['review_candidates']['count'] ?? 0) >= 3, 'subtree fixture review count includes 2x .DS_Store and cache.tmp');
    ht_assert_true(($summaryCat['runtime_dependency_artifacts']['count'] ?? 0) >= 5, 'subtree fixture runtime count includes all deps');
    ht_assert_true(($summaryCat['unknown_needs_classification']['count'] ?? 0) >= 1, 'subtree fixture unknown count includes mystery');
} finally {
    ht_remove_dir($filterRoot);
}

// === Owner Lens Tests ===
$ownerRoot = sys_get_temp_dir() . '/sbaio-helper-owner-' . bin2hex(random_bytes(4));
mkdir($ownerRoot, 0777, true);
try {
    mkdir($ownerRoot . '/apps/MyApp/Controllers', 0777, true);
    mkdir($ownerRoot . '/apps/MyApp/Views', 0777, true);
    mkdir($ownerRoot . '/apps/MyApp/Routes', 0777, true);
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/index.php', 'a');
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/routes.php', '<?php $router->get(\'/apps/my-app\', function () { return null; });');
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/Routes/admin.php', '<?php $router->post(\'/apps/my-app/admin\', function () { return null; });');
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/Controllers/FooController.php', '<?php namespace Apps\MyApp\Controllers; final class FooController { public static function index($view) {} }');
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/Views/dashboard.php', '<h1>Dashboard</h1>');
    mkdir($ownerRoot . '/apps/MyApp/modules/MyModule/Services', 0777, true);
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/modules/MyModule/start.php', 'c');
    ht_write_fixture_file($ownerRoot . '/apps/MyApp/modules/MyModule/Services/BarService.php', '<?php namespace Apps\MyApp\Modules\MyModule\Services; final class BarService {}');
    mkdir($ownerRoot . '/apps/NonOwner/views', 0777, true);
    ht_write_fixture_file($ownerRoot . '/apps/NonOwner/views/test.txt', 'e');
    mkdir($ownerRoot . '/platform/Services', 0777, true);
    ht_write_fixture_file($ownerRoot . '/platform/Services/Thing.php', 'f');
    ht_write_fixture_file($ownerRoot . '/platform/bootstrap.php', 'g');
    mkdir($ownerRoot . '/plugins/MyPlugin/Controllers', 0777, true);
    ht_write_fixture_file($ownerRoot . '/plugins/MyPlugin/start.php', 'h');
    mkdir($ownerRoot . '/engineering/Studio', 0777, true);
    mkdir($ownerRoot . '/engineering/Studio/Resources', 0777, true);
    ht_write_fixture_file($ownerRoot . '/engineering/Studio/overview.md', '# Studio');
    ht_write_fixture_file($ownerRoot . '/engineering/Studio/work.md', 'work');
    ht_write_fixture_file($ownerRoot . '/engineering/Studio/Resources/template.json', '{}');
    mkdir($ownerRoot . '/engineering/Studio/CustomizationStudio', 0777, true);
    mkdir($ownerRoot . '/engineering/Studio/CustomizationStudio/Resources', 0777, true);
    ht_write_fixture_file($ownerRoot . '/engineering/Studio/CustomizationStudio/overview.md', '# Customization');
    ht_write_fixture_file($ownerRoot . '/engineering/Studio/CustomizationStudio/Resources/template.json', '{}');
    mkdir($ownerRoot . '/engineering/Manufacturing/Products', 0777, true);
    mkdir($ownerRoot . '/engineering/Manufacturing/Products/Resources', 0777, true);
    ht_write_fixture_file($ownerRoot . '/engineering/Manufacturing/Products/overview.md', '# Mfg Products');
    ht_write_fixture_file($ownerRoot . '/engineering/Manufacturing/Products/decisions.md', '# decisions');
    ht_write_fixture_file($ownerRoot . '/engineering/Manufacturing/Products/Resources/template.json', '{}');
    mkdir($ownerRoot . '/misc', 0777, true);
    ht_write_fixture_file($ownerRoot . '/misc/data.txt', 'i');

    $ownerScan = RepoTreeScannerService::scanPath($ownerRoot);
    $ownerOwners = $ownerScan['owner_owners'] ?? [];
    $ownerRepoOwners = $ownerScan['owner_repo_owners'] ?? [];
    $ownerEwOwners = $ownerScan['owner_ew_owners'] ?? [];
    $ownerHierarchy = $ownerScan['owner_hierarchy'] ?? [];
    $ownerStats = $ownerScan['owner_stats'] ?? [];

    ht_assert_true(is_array($ownerOwners), 'scan result includes owner_owners array');
    ht_assert_true(is_array($ownerRepoOwners), 'scan result includes owner_repo_owners array');
    ht_assert_true(is_array($ownerEwOwners), 'scan result includes owner_ew_owners');
    ht_assert_true(is_array($ownerHierarchy), 'scan result includes owner_hierarchy');
    ht_assert_true(is_array($ownerStats), 'scan result includes owner_stats array');
    ht_assert_true(count($ownerOwners) >= 1, 'owner discovery finds at least one owner');
    ht_assert_true(count($ownerEwOwners) >= 1, 'EW discovery finds at least one workspace');

    /* === Canonical owner identity: no duplicate keys === */
    $seenKeys = [];
    foreach ($ownerOwners as $oo) {
        $ok = (string)($oo['owner_key'] ?? '');
        if ($ok === '') { continue; }
        ht_assert_true(!isset($seenKeys[$ok]), 'owner key [' . $ok . '] appears exactly once in owner_owners');
        $seenKeys[$ok] = true;
    }

    $foundMyApp = false;
    $foundMyModule = false;
    $foundPlatform = false;
    foreach ($ownerOwners as $oo) {
        $ok = (string)($oo['owner_key'] ?? '');
        if ($ok === 'MyApp') { $foundMyApp = true; }
        if ($ok === 'MyApp/MyModule') { $foundMyModule = true; }
        if ($ok === 'Platform') { $foundPlatform = true; }
    }
    ht_assert_true($foundMyApp, 'owner discovery finds MyApp app owner');
    ht_assert_true($foundMyModule, 'owner discovery finds MyApp/MyModule module owner');
    ht_assert_true($foundPlatform, 'owner discovery finds Platform owner');

    /* === EW separation: repo owners don't include EW === */
    $repoHasEw = false;
    foreach ($ownerRepoOwners as $ro) {
        $rtype = (string)($ro['owner_type'] ?? '');
        if ($rtype === 'engineering_workspace') { $repoHasEw = true; break; }
    }
    ht_assert_true(!$repoHasEw, 'repo owners exclude engineering_workspace type');

    /* === EW separation: EW owners are correctly classified === */
    $ewKeys = [];
    foreach ($ownerEwOwners as $ew) {
        $ewk = (string)($ew['owner_key'] ?? '');
        if ($ewk === '') { continue; }
        $ewKeys[] = $ewk;
        ht_assert_eq('engineering_workspace', (string)($ew['owner_type'] ?? ''), 'EW [' . $ewk . '] has engineering_workspace type');
    }
    ht_assert_true(in_array('EW/Studio', $ewKeys, true), 'EW/Studio found in ew_owners');
    ht_assert_true(in_array('EW/Manufacturing/Products', $ewKeys, true), 'EW/Manufacturing/Products found in ew_owners');

    /* === EW separation: EW keys NOT in repo owners === */
    foreach ($ewKeys as $ewk) {
        $inRepo = false;
        foreach ($ownerRepoOwners as $ro) { if ((string)($ro['owner_key'] ?? '') === $ewk) { $inRepo = true; break; } }
        ht_assert_true(!$inRepo, 'EW key [' . $ewk . '] does not appear in repo owners');
    }

    ht_assert_true(isset($ownerStats['MyApp']), 'owner stats includes MyApp');
    ht_assert_true(isset($ownerStats['MyApp/MyModule']), 'owner stats includes MyApp/MyModule');
    ht_assert_true(isset($ownerStats['Platform']), 'owner stats includes Platform');
    ht_assert_true(isset($ownerStats['EW/Studio']), 'owner stats includes EW/Studio');
    ht_assert_true(isset($ownerStats['EW/Studio/CustomizationStudio']), 'owner stats includes EW child workspace');
    ht_assert_true(isset($ownerStats['EW/Manufacturing/Products']), 'owner stats includes EW/Manufacturing/Products');

    /* === Hierarchy: modules grouped under parent === */
    ht_assert_true(is_array($ownerHierarchy), 'owner_hierarchy is an array');
    $foundAppRoot = false;
    $foundModuleChild = false;
    $moduleParentKey = '';
    foreach ($ownerHierarchy as $hItem) {
        $po = $hItem['owner'] ?? [];
        $pk = (string)($po['owner_key'] ?? '');
        $children = $hItem['children'] ?? [];
        if ($pk === 'MyApp') {
            $foundAppRoot = true;
            ht_assert_true(isset($children['MyApp/MyModule']), 'MyApp has MyApp/MyModule as child');
            $foundModuleChild = true;
            $moduleParentKey = $pk;
        }
    }
    ht_assert_true($foundAppRoot, 'hierarchy contains MyApp as root parent');
    ht_assert_true($foundModuleChild, 'hierarchy places MyApp/MyModule as child of MyApp');

    /* === Hierarchy: Platform and Plugin/MyPlugin are root entries (no parent needed) === */
    $foundPlatformRoot = false;
    $foundPluginRoot = false;
    $orphanModules = [];
    foreach ($ownerHierarchy as $hItem) {
        $po = $hItem['owner'] ?? [];
        $pk = (string)($po['owner_key'] ?? '');
        if ($pk === 'Platform') { $foundPlatformRoot = true; }
        if ($pk === 'Plugin/MyPlugin') { $foundPluginRoot = true; }
        /* Plugin/MyPlugin has a slash but should still be a root (no 'Plugin' parent) */
        /* Count children that have parent keys not matching any root */
        foreach (($hItem['children'] ?? []) as $ck => $co) {
            $parentPrefix = explode('/', $ck)[0];
            if ($parentPrefix !== $pk) {
                $orphanModules[] = $ck;
            }
        }
    }
    ht_assert_true($foundPlatformRoot, 'hierarchy contains Platform as root');
    ht_assert_true($foundPluginRoot, 'hierarchy contains Plugin/MyPlugin as root');

    /* === Deduplication check: no owner key appears more than once in the hierarchy === */
    $hierarchyKeys = [];
    foreach ($ownerHierarchy as $hItem) {
        $po = $hItem['owner'] ?? [];
        $pk = (string)($po['owner_key'] ?? '');
        if ($pk !== '' && !isset($hierarchyKeys[$pk])) {
            $hierarchyKeys[$pk] = true;
        } else if ($pk !== '') {
            ht_assert_true(false, 'duplicate hierarchy key: ' . $pk);
        }
        foreach (($hItem['children'] ?? []) as $co) {
            $ck = (string)($co['owner_key'] ?? '');
            if ($ck !== '' && !isset($hierarchyKeys[$ck])) {
                $hierarchyKeys[$ck] = true;
            } else if ($ck !== '') {
                ht_assert_true(false, 'duplicate hierarchy child key: ' . $ck);
            }
        }
    }

    $tree = $ownerScan['tree'] ?? [];
    $myAppNode = ht_find_tree_node($tree, 'apps/MyApp/index.php');
    ht_assert_true(($myAppNode['owner_key'] ?? '') === 'MyApp', 'MyApp/index.php assigned to MyApp owner');
    $myModuleNode = ht_find_tree_node($tree, 'apps/MyApp/modules/MyModule/start.php');
    ht_assert_true(($myModuleNode['owner_key'] ?? '') === 'MyApp/MyModule', 'MyModule/start.php assigned to MyApp/MyModule owner');
    $platformNode = ht_find_tree_node($tree, 'platform/Services/Thing.php');
    ht_assert_true(($platformNode['owner_key'] ?? '') === 'Platform', 'platform/Services/Thing.php assigned to Platform owner');
    $miscNode = ht_find_tree_node($tree, 'misc/data.txt');
    ht_assert_true(($miscNode['owner_key'] ?? '') === '', 'misc/data.txt assigned no owner (outside any known owner root)');

    $helperToolModel = [
        'scan_requested' => true,
        'scan_result' => $ownerScan,
    ];
    ob_start();
    require APP_ROOT . '/apps/Studio/Tools/HelperTool/Views/preview.php';
    $ownerHtml = (string)ob_get_clean();
    ht_assert_true(str_contains($ownerHtml, 'Repository Owner Lens'), 'view renders owner lens section title');
    ht_assert_true(str_contains($ownerHtml, 'data-owner-select'), 'view renders owner filter select');
    ht_assert_true(str_contains($ownerHtml, 'data-owner-cards'), 'view renders owner cards container');
    ht_assert_true(str_contains($ownerHtml, 'data-owner-card="MyApp"'), 'view renders owner card for MyApp');
    ht_assert_true(str_contains($ownerHtml, 'data-owner="MyApp"'), 'tree nodes have data-owner attributes');
    ht_assert_true(str_contains($ownerHtml, 'data-owner="MyApp/MyModule"'), 'tree nodes have data-owner for modules');
    ht_assert_true(str_contains($ownerHtml, 'ht-owner-type-app'), 'owner type badge renders for app');
    ht_assert_true(str_contains($ownerHtml, 'ht-owner-type-module'), 'owner type badge renders for module');
    ht_assert_true(str_contains($ownerHtml, 'ht-owner-type-platform'), 'owner type badge renders for platform');

    /* === View renders hierarchy === */
    ht_assert_true(str_contains($ownerHtml, 'ht-hierarchy-list'), 'view renders hierarchy container');
    ht_assert_true(str_contains($ownerHtml, 'ht-hierarchy-group'), 'view renders hierarchy expandable group');
    ht_assert_true(str_contains($ownerHtml, 'ht-hierarchy-children'), 'view renders hierarchy children container');
    ht_assert_true(str_contains($ownerHtml, 'ht-hierarchy-child-card'), 'view renders hierarchy child card');

    /* === View renders EW section === */
    ht_assert_true(str_contains($ownerHtml, 'data-ew-section'), 'view renders EW section');
    ht_assert_true(str_contains($ownerHtml, 'Engineering Workspaces'), 'view renders EW section title');
    ht_assert_true(str_contains($ownerHtml, 'documentation workspaces, not repository owners'), 'view clarifies EW is not a repo owner');

    /* === Owner ↔ EW workspace linking === */
    $ownerToEw = \Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::resolveOwnerToWorkspaceMap($ownerOwners);
    ht_assert_true(is_array($ownerToEw), 'resolveOwnerToWorkspaceMap returns array');
    ht_assert_eq(0, count($ownerToEw), 'no repo owners link to EWs in fixture (no matching EW directories)');
    ht_assert_true(str_contains($ownerHtml, 'helper-ws-not-linked'), 'owner cards show Not linked for unmatched owners');
    ht_assert_true(str_contains($ownerHtml, 'helper-summary-grid'), 'view renders workspace linking summary grid');
    ht_assert_true(str_contains($ownerHtml, 'Total workspaces'), 'linking summary shows total workspaces');
    ht_assert_true(str_contains($ownerHtml, 'Linked owners'), 'linking summary shows linked owners');
    ht_assert_true(str_contains($ownerHtml, 'Unlinked owners'), 'linking summary shows unlinked owners');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-ew-key'), 'owner cards have data-ht-ew-key attribute');
    $ewKeyCount = substr_count($ownerHtml, 'data-ht-ew-key=""');
    ht_assert_true($ewKeyCount >= 5, 'at least 5 owner cards have empty data-ht-ew-key (unmatched)');

    /* === Layout Polish V1 section flow === */
    $flowMarkers = [
        'scan_summary' => 'data-ht-scan-summary',
        'search' => 'data-ht-search-section',
        'review_buckets' => 'data-ht-review-buckets',
        'owner_lens' => 'data-helper-owner-lens',
        'entity_explorer' => 'data-entity-explorer',
        'engineering_workspaces' => 'data-ew-section',
        'repository_statistics' => 'data-ht-repository-statistics',
        'raw_tree' => 'data-ht-raw-tree',
    ];
    $flowPositions = [];
    foreach ($flowMarkers as $flowKey => $flowMarker) {
        $flowPositions[$flowKey] = strpos($ownerHtml, $flowMarker);
        ht_assert_true($flowPositions[$flowKey] !== false, 'layout polish marker present: ' . $flowKey);
    }
    ht_assert_true(
        $flowPositions['scan_summary'] < $flowPositions['search']
        && $flowPositions['search'] < $flowPositions['review_buckets']
        && $flowPositions['review_buckets'] < $flowPositions['owner_lens']
        && $flowPositions['owner_lens'] < $flowPositions['entity_explorer']
        && $flowPositions['entity_explorer'] < $flowPositions['engineering_workspaces']
        && $flowPositions['engineering_workspaces'] < $flowPositions['repository_statistics']
        && $flowPositions['repository_statistics'] < $flowPositions['raw_tree'],
        'layout polish section order matches task flow'
    );
    ht_assert_true(str_contains($ownerHtml, 'Repository Statistics'), 'view renders repository statistics section title');
    ht_assert_true(preg_match('/<details\b(?=[^>]*data-ht-repository-statistics)(?![^>]*\bopen\b)[^>]*>/', $ownerHtml) === 1, 'repository statistics collapsed by default');
    ht_assert_true(preg_match('/<details\b(?=[^>]*data-ht-raw-tree)(?![^>]*\bopen\b)[^>]*>/', $ownerHtml) === 1, 'raw tree collapsed by default');
    ht_assert_true(str_contains($ownerHtml, 'data-closed-label="Expand"'), 'collapsible sections expose expand affordance');
    ht_assert_true(str_contains($ownerHtml, 'data-open-label="Collapse"'), 'collapsible sections expose collapse affordance');

    /* === View renders owner select for repo owners only (no EW options) === */
    ht_assert_true(!str_contains($ownerHtml, 'data-ew-owner='), 'owner select has no EW owner options');

    $inspectorResult = RepoTreeScannerService::inspectFileFromRoot('apps/MyApp/index.php', $ownerRoot);
    ht_assert_eq(true, $inspectorResult['ok'] ?? null, 'owner-aware inspector opens file');
    ht_assert_eq('MyApp', $inspectorResult['owner_key'] ?? null, 'inspector returns owner_key');

    /* === Entity Discovery Tests === */
    $ownerEntities = $ownerScan['owner_entities'] ?? [];
    ht_assert_true(is_array($ownerEntities), 'scan result includes owner_entities array');

    /* MyApp entities */
    $myAppEntities = $ownerEntities['MyApp'] ?? null;
    ht_assert_true(is_array($myAppEntities), 'MyApp has entity entries');

    $myAppControllers = array_values(array_filter($myAppEntities, static fn($e) => ($e['type'] ?? '') === 'controller'));
    $myAppServices = array_values(array_filter($myAppEntities, static fn($e) => ($e['type'] ?? '') === 'service'));
    $myAppViews = array_values(array_filter($myAppEntities, static fn($e) => ($e['type'] ?? '') === 'view'));
    $myAppRoutes = array_values(array_filter($myAppEntities, static fn($e) => ($e['type'] ?? '') === 'route'));

    ht_assert_eq(1, count($myAppControllers), 'MyApp has 1 controller entity');
    ht_assert_eq(0, count($myAppServices), 'MyApp has 0 service entities (no Services/ dir)');
    ht_assert_eq(1, count($myAppViews), 'MyApp has 1 view entity');
    ht_assert_eq(2, count($myAppRoutes), 'MyApp has 2 route entities (routes.php + Routes/admin.php)');

    ht_assert_eq('FooController', $myAppControllers[0]['name'] ?? '', 'MyApp controller entity name from class declaration');
    ht_assert_true($myAppControllers[0]['is_certain'] ?? false, 'MyApp controller is certain (class found)');
    ht_assert_eq('apps/MyApp/Controllers/FooController.php', $myAppControllers[0]['source_path'] ?? '', 'MyApp controller source path correct');
    ht_assert_eq('controller', $myAppControllers[0]['type'] ?? '', 'MyApp controller entity has type=controller');

    ht_assert_eq('dashboard', $myAppViews[0]['name'] ?? '', 'MyApp view entity name from filename (no extension)');
    ht_assert_true($myAppViews[0]['is_certain'] ?? false, 'MyApp view is certain (file found)');
    ht_assert_eq('apps/MyApp/Views/dashboard.php', $myAppViews[0]['source_path'] ?? '', 'MyApp view source path correct');

    ht_assert_true($myAppRoutes[0]['is_certain'] ?? false, 'MyApp route is certain');
    ht_assert_true(str_contains($myAppRoutes[0]['evidence'] ?? '', 'Route registration:'), 'MyApp route evidence contains Route registration');
    ht_assert_true(str_contains($myAppRoutes[0]['source_path'] ?? '', 'routes.php'), 'MyApp route source path references routes.php');

    /* MyApp/MyModule entities */
    $myModuleEntities = $ownerEntities['MyApp/MyModule'] ?? null;
    ht_assert_true(is_array($myModuleEntities), 'MyApp/MyModule has entity entries');

    $modServices = array_values(array_filter($myModuleEntities, static fn($e) => ($e['type'] ?? '') === 'service'));
    ht_assert_true(count($modServices) >= 1, 'MyApp/MyModule has at least 1 service entity');
    ht_assert_eq('BarService', $modServices[0]['name'] ?? '', 'MyModule service entity name from class declaration');
    ht_assert_true($modServices[0]['is_certain'] ?? false, 'MyModule service is certain (class found)');
    ht_assert_eq('MyApp/MyModule', $modServices[0]['owner_key'] ?? '', 'MyModule service owner_key correct');

    /* Platform entities (Services dir has Thing.php) */
    $platformEntities = $ownerEntities['Platform'] ?? null;
    ht_assert_true(is_array($platformEntities), 'Platform has entity entries');
    $platServices = array_values(array_filter($platformEntities, static fn($e) => ($e['type'] ?? '') === 'service'));
    ht_assert_true(count($platServices) >= 1, 'Platform has at least 1 service entity');
    ht_assert_eq('Thing', $platServices[0]['name'] ?? '', 'Platform service entity name from filename (no extension)');
    ht_assert_true(!($platServices[0]['is_certain'] ?? true), 'Platform service is uncertain (no class declaration in file)');

    /* === View renders entity section === */
    ht_assert_true(str_contains($ownerHtml, 'Entity Explorer'), 'view renders entity explorer section title');
    ht_assert_true(str_contains($ownerHtml, 'data-entity-explorer'), 'view has data-entity-explorer attribute');
    ht_assert_true(str_contains($ownerHtml, 'data-entity-json='), 'view embeds entity JSON data');
    ht_assert_true(str_contains($ownerHtml, 'Select an owner above'), 'view shows entity no-selection message');

    /* === Entity type registry === */
    $registry = \Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::getEntityTypeRegistry();
    ht_assert_true(is_array($registry), 'entity type registry is an array');
    ht_assert_true(count($registry) >= 4, 'entity type registry has at least 4 types');
    ht_assert_true(isset($registry['controller']), 'registry has controller');
    ht_assert_true(isset($registry['service']), 'registry has service');
    ht_assert_true(isset($registry['view']), 'registry has view');
    ht_assert_true(isset($registry['route']), 'registry has route');
    ht_assert_eq('Controllers', $registry['controller']['label'], 'registry controller label');
    ht_assert_eq('class_declaration', $registry['controller']['evidence_strategy'], 'registry controller evidence strategy');
    ht_assert_eq('class_found', $registry['controller']['confidence_rule'], 'registry controller confidence rule');
    ht_assert_eq('file_path', $registry['view']['evidence_strategy'], 'registry view evidence strategy');
    ht_assert_eq('always_certain', $registry['view']['confidence_rule'], 'registry view confidence rule');

    /* === Entity type keys sorted by sort_order === */
    $keys = \Apps\Studio\Tools\HelperTool\Services\RepoTreeScannerService::getEntityTypeKeys();
    ht_assert_eq('controller', $keys[0], 'first type key is controller');
    ht_assert_eq('service', $keys[1], 'second type key is service');
    ht_assert_eq('view', $keys[2], 'third type key is view');
    ht_assert_eq('route', $keys[3], 'fourth type key is route');

    /* === View has entity registry data attribute === */
    ht_assert_true(str_contains($ownerHtml, 'data-entity-registry='), 'view embeds entity registry JSON');

    /* === Entity summary cards are buttons with data-entity-filter === */
    ht_assert_true(str_contains($ownerHtml, 'entity-filter-all'), 'view has entity-filter-all button');
    ht_assert_true(str_contains($ownerHtml, 'data-entity-filter'), 'summary cards have data-entity-filter');

    /* === Repository Entity Summary === */
    $entitySummary = $ownerScan['entity_summary'] ?? null;
    ht_assert_true(is_array($entitySummary), 'scan result has entity_summary');
    ht_assert_eq(6, $entitySummary['total'], 'entity summary total is 6');

    $esCounts = $entitySummary['counts'] ?? [];
    ht_assert_eq(1, $esCounts['controller'], 'entity summary: 1 controller across all owners');
    ht_assert_eq(2, $esCounts['service'], 'entity summary: 2 services across all owners');
    ht_assert_eq(1, $esCounts['view'], 'entity summary: 1 view across all owners');
    ht_assert_eq(2, $esCounts['route'], 'entity summary: 2 routes across all owners');

    $esDistrib = $entitySummary['distribution'] ?? [];
    ht_assert_true(isset($esDistrib['controller']), 'entity summary has controller distribution');
    ht_assert_true(isset($esDistrib['service']), 'entity summary has service distribution');
    ht_assert_true(isset($esDistrib['view']), 'entity summary has view distribution');
    ht_assert_true(isset($esDistrib['route']), 'entity summary has route distribution');

    /* Controller distribution: MyApp=1 */
    $ctrlDist = $esDistrib['controller'];
    ht_assert_eq(1, count($ctrlDist), 'controller distribution has 1 owner');
    ht_assert_eq('MyApp', $ctrlDist[0]['owner_key'], 'controller top owner is MyApp');
    ht_assert_eq(1, $ctrlDist[0]['count'], 'MyApp has 1 controller');

    /* Service distribution: Platform=1, MyApp/MyModule=1 */
    $svcDist = $esDistrib['service'];
    ht_assert_eq(2, count($svcDist), 'service distribution has 2 owners');
    /* Sorted descending by count, both have 1 so stable sort order by input order */
    ht_assert_eq(1, $svcDist[0]['count'], 'top service owner has 1 service');
    ht_assert_eq(1, $svcDist[1]['count'], 'second service owner has 1 service');

    /* Route distribution: MyApp=2 */
    $routeDist = $esDistrib['route'];
    ht_assert_eq(1, count($routeDist), 'route distribution has 1 owner');
    ht_assert_eq('MyApp', $routeDist[0]['owner_key'], 'route top owner is MyApp');
    ht_assert_eq(2, $routeDist[0]['count'], 'MyApp has 2 routes');

    /* Verify repository total equals sum of per-owner entity counts */
    $ownerTotalSum = 0;
    foreach ($ownerEntities as $ok => $ents) {
        $ownerTotalSum += count($ents);
    }
    ht_assert_eq($entitySummary['total'], $ownerTotalSum, 'entity summary total equals sum of per-owner entity counts');

    /* === View renders repository entity summary section === */
    ht_assert_true(str_contains($ownerHtml, 'Repository Entity Summary'), 'view renders entity summary title');
    ht_assert_true(str_contains($ownerHtml, 'entity-summary-badge'), 'view shows entity summary badge');
    ht_assert_true(str_contains($ownerHtml, 'data-entity-summary-section'), 'view has data-entity-summary-section');
    ht_assert_true(str_contains($ownerHtml, 'data-entity-summary-type'), 'summary cards have data-entity-summary-type');
    ht_assert_true(str_contains($ownerHtml, 'entity-repo-distrib-owner'), 'summary shows owner distribution');

    /* Verify entity type filter buttons still function */
    ht_assert_true(str_contains($ownerHtml, 'data-entity-filter'), 'entity filter buttons present');
    ht_assert_true(str_contains($ownerHtml, 'is-active'), 'entity filter has active state');

    /* === Search V1: buildSearchIndex() unit tests === */
    $searchIndex = RepoTreeScannerService::buildSearchIndex($ownerScan);
    ht_assert_true(is_array($searchIndex), 'buildSearchIndex returns array');
    ht_assert_true(count($searchIndex) > 0, 'buildSearchIndex produces entries from owner scan');

    /* Every entry has required fields */
    foreach ($searchIndex as $si) {
        ht_assert_true(isset($si['result_type']), 'search entry has result_type');
        ht_assert_true(isset($si['label']), 'search entry has label');
        ht_assert_true(isset($si['sublabel']), 'search entry has sublabel');
        ht_assert_true(isset($si['path']), 'search entry has path');
        ht_assert_true(isset($si['owner_key']), 'search entry has owner_key');
        ht_assert_true(isset($si['action']), 'search entry has action');
        ht_assert_true(isset($si['evidence']), 'search entry has evidence');
        break; /* check first entry shape once */
    }

    /* File entries */
    $fileEntries = array_filter($searchIndex, static fn($e) => ($e['result_type'] ?? '') === 'file');
    ht_assert_true(count($fileEntries) > 0, 'buildSearchIndex includes file entries');
    $sampleFile = array_values($fileEntries)[0];
    ht_assert_eq('inspect_file', $sampleFile['action'], 'file entry action is inspect_file');
    ht_assert_true($sampleFile['path'] !== '', 'file entry has non-empty path');

    /* Owner entries */
    $ownerEntries = array_filter($searchIndex, static fn($e) => ($e['result_type'] ?? '') === 'owner');
    ht_assert_true(count($ownerEntries) > 0, 'buildSearchIndex includes owner entries');
    $sampleOwner = array_values($ownerEntries)[0];
    ht_assert_eq('select_owner', $sampleOwner['action'], 'owner entry action is select_owner');
    ht_assert_true($sampleOwner['owner_key'] !== '', 'owner entry has non-empty owner_key');

    /* Workspace entries */
    $wsEntries = array_filter($searchIndex, static fn($e) => ($e['result_type'] ?? '') === 'workspace');
    ht_assert_true(count($wsEntries) > 0, 'buildSearchIndex includes workspace entries');
    $sampleWs = array_values($wsEntries)[0];
    ht_assert_eq('none', $sampleWs['action'], 'workspace entry action is none');
    ht_assert_eq('engineering_workspace', $sampleWs['sublabel'], 'workspace entry sublabel is engineering_workspace');

    /* Entity entries (controller, service, or view) */
    $entityEntries = array_filter($searchIndex, static fn($e) => ($e['result_type'] ?? '') === 'entity');
    ht_assert_true(count($entityEntries) > 0, 'buildSearchIndex includes entity entries');
    $sampleEntity = array_values($entityEntries)[0];
    ht_assert_eq('inspect_file', $sampleEntity['action'], 'entity entry action is inspect_file');
    ht_assert_true($sampleEntity['path'] !== '', 'entity entry has non-empty source path');
    ht_assert_true(str_contains($sampleEntity['sublabel'], $sampleEntity['owner_key']), 'entity sublabel contains owner key');

    /* Route entries */
    $routeEntries = array_filter($searchIndex, static fn($e) => ($e['result_type'] ?? '') === 'route');
    ht_assert_true(count($routeEntries) > 0, 'buildSearchIndex includes route entries');
    $sampleRoute = array_values($routeEntries)[0];
    ht_assert_eq('inspect_file', $sampleRoute['action'], 'route entry action is inspect_file');

    /* Specific searchable content: FooController should appear as entity or file */
    $fooResults = array_filter($searchIndex, static fn($e) => str_contains($e['label'] ?? '', 'FooController'));
    ht_assert_true(count($fooResults) > 0, 'FooController appears in search index');

    /* MyApp owner should appear as an owner entry */
    $myAppOwner = array_filter($ownerEntries, static fn($e) => ($e['owner_key'] ?? '') === 'MyApp');
    ht_assert_true(count($myAppOwner) > 0, 'MyApp appears as owner entry in search index');

    /* EW/Studio should appear as workspace entry */
    $studioWs = array_filter($wsEntries, static fn($e) => str_contains($e['owner_key'] ?? '', 'Studio'));
    ht_assert_true(count($studioWs) > 0, 'Studio engineering workspace appears in search index');

    /* === Search V1: view renders search section === */
    ht_assert_true(str_contains($ownerHtml, 'ht-search-section'), 'view renders search section');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-section'), 'view has data-ht-search-section marker');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-input'), 'view renders search input');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-results'), 'view renders search results container');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-result-list'), 'view renders search result list container');
    ht_assert_true(str_contains($ownerHtml, 'ht-search-index-data'), 'view embeds search index JSON script tag');
    ht_assert_true(str_contains($ownerHtml, 'application/json'), 'search index script tag uses application/json type');
    ht_assert_true(str_contains($ownerHtml, '"result_type"'), 'embedded JSON contains result_type field');
    ht_assert_true(str_contains($ownerHtml, '"file"'), 'embedded JSON has file result_type entries');
    ht_assert_true(str_contains($ownerHtml, '"owner"'), 'embedded JSON has owner result_type entries');
    ht_assert_true(str_contains($ownerHtml, '"workspace"'), 'embedded JSON has workspace result_type entries');
    ht_assert_true(str_contains($ownerHtml, '"inspect_file"'), 'embedded JSON has inspect_file action entries');
    ht_assert_true(str_contains($ownerHtml, '"select_owner"'), 'embedded JSON has select_owner action entries');

    /* Search results container starts hidden (empty query shows nothing) */
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-results hidden') || preg_match('/data-ht-search-results[^>]*\bhidden\b/', $ownerHtml) === 1, 'search results container hidden on initial render (empty query shows no results)');

    /* Search label data attributes present */
    ht_assert_true(str_contains($ownerHtml, 'data-label-type-file'), 'search section has data-label-type-file attribute');
    ht_assert_true(str_contains($ownerHtml, 'data-label-type-owner'), 'search section has data-label-type-owner attribute');
    ht_assert_true(str_contains($ownerHtml, 'data-label-type-workspace'), 'search section has data-label-type-workspace attribute');
    ht_assert_true(str_contains($ownerHtml, 'data-label-action-inspect'), 'search section has data-label-action-inspect attribute');

    /* Polish V1: type chips bar */
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-chips'), 'view renders search chips container');
    ht_assert_true(str_contains($ownerHtml, 'ht-search-chips'), 'search chips CSS class present');

    /* Polish V1: owner-only filter checkbox */
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-owner-filter'), 'view renders owner-only filter checkbox');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-owner-filter-row'), 'view renders owner-filter row');
    ht_assert_true(str_contains($ownerHtml, 'ht-search-owner-filter-label'), 'owner filter label CSS class present');

    /* Polish V1: updated empty-state text */
    ht_assert_true(str_contains($ownerHtml, 'Type to search repository data'), 'search hint uses updated empty state text');
    ht_assert_true(str_contains($ownerHtml, 'No repository matches found'), 'no-results label uses updated text');

    /* File Inspector links still work (existing tree must still render) */
    ht_assert_true(str_contains($ownerHtml, 'data-file-inspector'), 'file inspector panel still present after search section added');
    ht_assert_true(str_contains($ownerHtml, 'data-inspector-path'), 'file inspector clickable paths still present');

    /* JS search IIFE present */
    ht_assert_true(str_contains($ownerHtml, 'Repository Search V1'), 'search JS IIFE present');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-inspect'), 'search result inspect button data attr present in JS');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-select-owner'), 'search result select-owner button data attr present in JS');

    /* Polish V1: JS owner filter + chips logic markers */
    ht_assert_true(str_contains($ownerHtml, 'data-ht-search-owner-filter'), 'owner filter checkbox referenced in JS');
    ht_assert_true(str_contains($ownerHtml, 'renderChips'), 'renderChips function present in search JS');
    ht_assert_true(str_contains($ownerHtml, 'renderResults'), 'renderResults function present in search JS');
    ht_assert_true(str_contains($ownerHtml, 'ownerOnly'), 'owner-only filter logic present in search JS');
    ht_assert_true(str_contains($ownerHtml, 'data-ht-chip'), 'chip data attribute present in search JS');

} finally {
    ht_remove_dir($ownerRoot);
}

} finally {
    ht_remove_dir($fixtureRoot);
}

echo "HelperToolRepositoryScan: {$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    echo "SOME TESTS FAILED\n";
    exit(1);
}

echo "ALL TESTS PASSED\n";
exit(0);
