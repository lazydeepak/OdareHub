<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/scripts/assets/first_boot_css_compiler.php';

$assertions = 0;
$failures = 0;

function theme_healing_assert(bool $condition, string $message): void
{
    global $assertions, $failures;
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
}

function theme_healing_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item instanceof SplFileInfo) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
    }
    @rmdir($path);
}

$root = sys_get_temp_dir() . '/susankhya-theme-self-heal-' . bin2hex(random_bytes(6));
$paths = [
    $root . '/resources/themes',
    $root . '/scripts/assets',
    $root . '/public/assets',
    $root . '/apps/Shell/Resources',
];
foreach ($paths as $path) {
    mkdir($path, 0775, true);
}

$manifest = [
    'status' => 'runtime_wired_ready',
    'legacy_base' => ['enabled' => false, 'path' => 'public/assets/theme.legacy.css'],
    'sources' => [
        ['id' => 'foundation', 'path' => 'foundation.css', 'kind' => 'base', 'enabled' => true],
    ],
];
file_put_contents($root . '/resources/themes/theme-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
file_put_contents($root . '/resources/themes/foundation.css', ":root{--probe-theme-value:#123456;}\n");
copy(APP_ROOT . '/scripts/assets/compile_theme_sources.php', $root . '/scripts/assets/compile_theme_sources.php');
copy(APP_ROOT . '/scripts/assets/theme_source_fingerprint.php', $root . '/scripts/assets/theme_source_fingerprint.php');

$target = $root . '/public/assets/theme.css';
file_put_contents($target, "/* stale generated asset */\n:root{--probe-theme-value:#000;}\n");
$staleBefore = (string)file_get_contents($target);

$dryStale = compileThemeCssIfAvailable($root, false);
theme_healing_assert(($dryStale['theme_css_status'] ?? '') === 'would_update', 'dry-run detects a stale existing theme asset');
theme_healing_assert((string)file_get_contents($target) === $staleBefore, 'dry-run never mutates the stale asset');

$applyStale = compileThemeCssIfAvailable($root, true);
$firstCompiled = (string)file_get_contents($target);
$firstFingerprint = susankhyaCompiledThemeFingerprint($target);
theme_healing_assert(($applyStale['ok'] ?? false) === true, 'apply repairs a stale theme asset');
theme_healing_assert($firstFingerprint !== null, 'compiled theme records its deterministic source fingerprint');
theme_healing_assert(str_contains($firstCompiled, '--probe-theme-value:#123456'), 'compiled theme contains current source content');
theme_healing_assert($firstFingerprint === susankhyaThemeSourceFingerprint($root), 'target fingerprint matches current sources');

$targetMtime = (int)filemtime($target);
file_put_contents($root . '/resources/themes/foundation.css', ":root{--probe-theme-value:#abcdef;}\n");
touch($root . '/resources/themes/foundation.css', max(1, $targetMtime - 3600));
$dryOlderSource = compileThemeCssIfAvailable($root, false);
theme_healing_assert(
    ($dryOlderSource['theme_css_status'] ?? '') === 'would_update',
    'fingerprint detects changed source even when its timestamp is older than the generated asset'
);

$applyOlderSource = compileThemeCssIfAvailable($root, true);
$secondCompiled = (string)file_get_contents($target);
theme_healing_assert(($applyOlderSource['ok'] ?? false) === true, 'apply repairs timestamp-preserved deployment content');
theme_healing_assert(str_contains($secondCompiled, '--probe-theme-value:#abcdef'), 'recompiled target contains changed older-timestamp source');
theme_healing_assert(
    susankhyaCompiledThemeFingerprint($target) === susankhyaThemeSourceFingerprint($root),
    'recompiled target records the new source fingerprint'
);

$current = compileThemeCssIfAvailable($root, true);
theme_healing_assert(($current['theme_css_status'] ?? '') === 'already_current', 'current target skips redundant compilation');

unlink($target);
$dryMissing = compileThemeCssIfAvailable($root, false);
theme_healing_assert(($dryMissing['theme_css_status'] ?? '') === 'would_create', 'dry-run reports a missing runtime target');
theme_healing_assert(!is_file($target), 'missing-target dry-run does not create output');
$applyMissing = compileThemeCssIfAvailable($root, true);
theme_healing_assert(($applyMissing['ok'] ?? false) === true && is_file($target), 'apply recreates a missing runtime target');

theme_healing_remove_tree($root);

if ($failures > 0) {
    fwrite(STDERR, "[probe] runtime theme asset self-healing: {$failures} failure(s) / {$assertions} assertions\n");
    exit(1);
}

echo "[probe] runtime theme asset self-healing: {$assertions}/{$assertions} assertions passed\n";
