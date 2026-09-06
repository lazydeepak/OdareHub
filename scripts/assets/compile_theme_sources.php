<?php
declare(strict_types=1);

require_once __DIR__ . '/theme_source_fingerprint.php';

/**
 * Theme source compiler
 *
 * Compiles enabled source files from resources/themes/theme-manifest.json
 * into the runtime asset public/assets/theme.css.
 *
 * Usage:
 *   php scripts/assets/compile_theme_sources.php
 *   php scripts/assets/compile_theme_sources.php --json
 *   php scripts/assets/compile_theme_sources.php --apply
 *   php scripts/assets/compile_theme_sources.php --apply --json
 */

const THEME_SOURCE_DIR = 'resources/themes';
const THEME_MANIFEST = 'resources/themes/theme-manifest.json';
const THEME_RUNTIME_TARGET = 'public/assets/theme.css';
const THEME_OPTIONS_TARGET = 'apps/Shell/Resources/published-theme-options.json';
const DEFAULT_LEGACY_BASE = 'public/assets/theme.legacy.css';

/** @var array<int,string> $argv */
$argv = $_SERVER['argv'] ?? [];
$apply = in_array('--apply', $argv, true);
$json = in_array('--json', $argv, true);

$root = dirname(__DIR__, 2);
$manifestPath = $root . DIRECTORY_SEPARATOR . THEME_MANIFEST;
$sourceDir = $root . DIRECTORY_SEPARATOR . THEME_SOURCE_DIR;
$targetPath = $root . DIRECTORY_SEPARATOR . THEME_RUNTIME_TARGET;
$optionsTargetPath = $root . DIRECTORY_SEPARATOR . THEME_OPTIONS_TARGET;

$result = [
    'ok' => false,
    'mode' => $apply ? 'apply' : 'dry-run',
    'manifest' => THEME_MANIFEST,
    'target' => THEME_RUNTIME_TARGET,
    'options_target' => THEME_OPTIONS_TARGET,
    'enabled_sources' => [],
    'warnings' => [],
    'errors' => [],
    'compiled_bytes' => 0,
    'applied' => false,
    'options_applied' => false,
    'manifest_status' => '',
    'legacy_base' => null,
    'legacy_base_included' => false,
    'legacy_base_bytes' => 0,
    'unchanged' => false,
];

$knownBaseThemeFiles = [
    'foundation.css' => true,
    'light.css' => true,
    'dark.css' => true,
];

/** @var array<string,bool> $explicitlyDisabledIds */
$explicitlyDisabledIds = [];
/** @var array<string,bool> $explicitlyDisabledPaths */
$explicitlyDisabledPaths = [];
/** @var array<string,bool> $alreadyIncludedPaths */
$alreadyIncludedPaths = [];
/** @var array<string,string> $publishedStyles */
$publishedStyles = [];

if (!is_file($manifestPath)) {
    $result['errors'][] = 'manifest_not_found';
    emit($result, $json);
}

$manifestRaw = @file_get_contents($manifestPath);
if (!is_string($manifestRaw) || $manifestRaw === '') {
    $result['errors'][] = 'manifest_unreadable';
    emit($result, $json);
}

$manifest = json_decode($manifestRaw, true);
if (!is_array($manifest)) {
    $result['errors'][] = 'manifest_invalid_json';
    emit($result, $json);
}

$manifestStatus = strtolower(trim((string)($manifest['status'] ?? '')));
$result['manifest_status'] = $manifestStatus;

$sources = $manifest['sources'] ?? null;
if (!is_array($sources)) {
    $result['errors'][] = 'manifest_sources_missing';
    emit($result, $json);
}

$legacyBaseEnabled = true;
$legacyBasePath = DEFAULT_LEGACY_BASE;
$legacyBaseConfig = $manifest['legacy_base'] ?? null;
if (is_array($legacyBaseConfig)) {
    if (array_key_exists('enabled', $legacyBaseConfig)) {
        $legacyBaseEnabled = (bool)$legacyBaseConfig['enabled'];
    }
    $cfgPath = trim((string)($legacyBaseConfig['path'] ?? ''));
    if ($cfgPath !== '') {
        $legacyBasePath = $cfgPath;
    }
}

$legacyBaseAbs = '';
if ($legacyBaseEnabled) {
    if (str_contains($legacyBasePath, '..') || str_starts_with($legacyBasePath, '/')) {
        $result['errors'][] = 'legacy_base_path_not_allowed:' . $legacyBasePath;
        emit($result, $json);
    }
    $legacyBaseAbs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $legacyBasePath);
    $result['legacy_base'] = $legacyBasePath;
}

$compiledParts = [];
foreach ($sources as $idx => $source) {
    if (!is_array($source)) {
        $result['warnings'][] = 'source_entry_invalid_at_' . $idx;
        continue;
    }

    $id = trim((string)($source['id'] ?? ''));
    $path = trim((string)($source['path'] ?? ''));
    $kind = strtolower(trim((string)($source['kind'] ?? '')));
    $enabled = (bool)($source['enabled'] ?? false);

    if ($id === '' || $path === '') {
        $result['warnings'][] = 'source_entry_missing_fields_at_' . $idx;
        continue;
    }

    if (!$enabled) {
        if (in_array($kind, ['style', 'custom'], true)) {
            if ($id !== '') {
                $explicitlyDisabledIds[strtolower($id)] = true;
            }
            if ($path !== '') {
                $explicitlyDisabledPaths[normalize_rel_path($path)] = true;
            }
        }
        continue;
    }

    if (str_contains($path, '..') || str_starts_with($path, '/')) {
        $result['errors'][] = 'source_path_not_allowed:' . $path;
        emit($result, $json);
    }

    $normalizedRelPath = normalize_rel_path($path);
    $alreadyIncludedPaths[$normalizedRelPath] = true;

    $abs = $sourceDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (!is_file($abs)) {
        $result['errors'][] = 'source_file_missing:' . $path;
        emit($result, $json);
    }

    $css = @file_get_contents($abs);
    if (!is_string($css)) {
        $result['errors'][] = 'source_file_unreadable:' . $path;
        emit($result, $json);
    }

    $result['enabled_sources'][] = [
        'id' => $id,
        'path' => $path,
        'bytes' => strlen($css),
    ];
    if (in_array($kind, ['style', 'custom'], true)) {
        $publishedStyles[strtolower($id)] = humanize_theme_id($id);
    }

    $compiledParts[] = "/* source: {$id} ({$path}) */\n" . rtrim($css) . "\n";
}

$autoDiscoveredSources = discover_theme_style_sources($sourceDir, array_keys($knownBaseThemeFiles));
foreach ($autoDiscoveredSources as $autoSource) {
    $autoId = (string)$autoSource['id'];
    $autoPath = (string)$autoSource['path'];
    $autoAbs = (string)$autoSource['abs'];

    $autoIdNorm = strtolower($autoId);
    $autoPathNorm = normalize_rel_path($autoPath);

    if (isset($alreadyIncludedPaths[$autoPathNorm])) {
        continue;
    }
    if (isset($explicitlyDisabledIds[$autoIdNorm]) || isset($explicitlyDisabledPaths[$autoPathNorm])) {
        continue;
    }

    $css = @file_get_contents($autoAbs);
    if (!is_string($css)) {
        $result['errors'][] = 'source_file_unreadable:' . $autoPath;
        emit($result, $json);
    }

    $result['enabled_sources'][] = [
        'id' => $autoId,
        'path' => $autoPath,
        'bytes' => strlen($css),
    ];
    $publishedStyles[strtolower($autoId)] = humanize_theme_id($autoId);

    $compiledParts[] = "/* source: {$autoId} ({$autoPath}) */\n" . rtrim($css) . "\n";
}

$sourceFingerprint = susankhyaThemeSourceFingerprint($root);
if ($sourceFingerprint === null) {
    $result['errors'][] = 'source_fingerprint_failed';
    emit($result, $json);
}

$compiled = "/* GENERATED FILE: public/assets/theme.css */\n"
    . "/* Source manifest: " . THEME_MANIFEST . " */\n"
    . "/* Source fingerprint: " . $sourceFingerprint . " */\n\n";

if ($legacyBaseEnabled) {
    if (!is_file($legacyBaseAbs)) {
        $result['errors'][] = 'legacy_base_missing:' . $legacyBasePath;
        emit($result, $json);
    }
    $legacyRaw = @file_get_contents($legacyBaseAbs);
    if (!is_string($legacyRaw)) {
        $result['errors'][] = 'legacy_base_unreadable:' . $legacyBasePath;
        emit($result, $json);
    }

    $result['legacy_base_included'] = true;
    $result['legacy_base_bytes'] = strlen($legacyRaw);

    $compiled .= "/* BEGIN LEGACY BASE: {$legacyBasePath} */\n"
        . rtrim($legacyRaw) . "\n"
        . "/* END LEGACY BASE: {$legacyBasePath} */\n\n";
}

$compiled .= implode("\n", $compiledParts);
$result['compiled_bytes'] = strlen($compiled);
ksort($publishedStyles);
$compiledOptions = json_encode([
    'schema' => 'susankhya.shell.published-theme-options.v1',
    'generated_from' => THEME_MANIFEST,
    'styles' => $publishedStyles,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($compiledOptions)) {
    $result['errors'][] = 'options_encode_failed';
    emit($result, $json);
}
$compiledOptions .= PHP_EOL;

if ($apply) {
    if ($manifestStatus !== 'runtime_wired_ready') {
        $result['errors'][] = 'apply_blocked_manifest_status:' . ($manifestStatus !== '' ? $manifestStatus : 'unknown');
        $result['warnings'][] = 'Use dry-run while manifest status is scaffold-only. Set status=runtime_wired_ready only after migration readiness.';
        emit($result, $json);
    }

    $currentRaw = @file_get_contents($targetPath);
    $currentOptionsRaw = @file_get_contents($optionsTargetPath);
    if (is_string($currentRaw) && $currentRaw === $compiled
        && is_string($currentOptionsRaw) && $currentOptionsRaw === $compiledOptions
    ) {
        $result['unchanged'] = true;
        $result['applied'] = false;
        $result['ok'] = true;
        emit($result, $json);
    }

    if (!is_string($currentRaw) || $currentRaw !== $compiled) {
        if (!write_compiled_target($targetPath, $compiled)) {
            $result['errors'][] = 'target_write_failed';
            emit($result, $json);
        }
        $result['applied'] = true;
    }

    if (!is_string($currentOptionsRaw) || $currentOptionsRaw !== $compiledOptions) {
        if (!write_compiled_target($optionsTargetPath, $compiledOptions)) {
            $result['errors'][] = 'options_target_write_failed';
            emit($result, $json);
        }
        $result['options_applied'] = true;
    }
}

$result['ok'] = true;
emit($result, $json);

/**
 * @param array<string,mixed> $result
 */
function emit(array $result, bool $json): void
{
    if ($json) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        echo 'theme source compiler' . PHP_EOL;
        echo 'mode: ' . (string)($result['mode'] ?? 'unknown') . PHP_EOL;
        echo 'manifest: ' . (string)($result['manifest'] ?? '') . PHP_EOL;
        echo 'target: ' . (string)($result['target'] ?? '') . PHP_EOL;
        echo 'options target: ' . (string)($result['options_target'] ?? '') . PHP_EOL;

        $sources = $result['enabled_sources'] ?? [];
        if (is_array($sources)) {
            echo 'enabled sources: ' . count($sources) . PHP_EOL;
            foreach ($sources as $s) {
                if (is_array($s)) {
                    echo '  - ' . (string)($s['id'] ?? '?') . ' (' . (string)($s['path'] ?? '?') . ')' . PHP_EOL;
                }
            }
        }

        $warnings = $result['warnings'] ?? [];
        if (is_array($warnings) && $warnings !== []) {
            echo 'warnings:' . PHP_EOL;
            foreach ($warnings as $w) {
                echo '  - ' . (string)$w . PHP_EOL;
            }
        }

        $errors = $result['errors'] ?? [];
        if (is_array($errors) && $errors !== []) {
            echo 'errors:' . PHP_EOL;
            foreach ($errors as $e) {
                echo '  - ' . (string)$e . PHP_EOL;
            }
        }

        echo 'compiled bytes: ' . (int)($result['compiled_bytes'] ?? 0) . PHP_EOL;
        echo 'applied: ' . ((bool)($result['applied'] ?? false) ? 'yes' : 'no') . PHP_EOL;
        echo 'options applied: ' . ((bool)($result['options_applied'] ?? false) ? 'yes' : 'no') . PHP_EOL;
    }

    exit((!empty($result['ok']) && empty($result['errors'])) ? 0 : 1);
}

/**
 * @return array<int,array{id:string,path:string,abs:string}>
 */
function discover_theme_style_sources(string $sourceDir, array $excludedBaseFiles): array
{
    if (!is_dir($sourceDir)) {
        return [];
    }

    $excluded = [];
    foreach ($excludedBaseFiles as $name) {
        $excluded[normalize_rel_path($name)] = true;
    }

    $out = [];
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iter as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $ext = strtolower((string)$fileInfo->getExtension());
        if ($ext !== 'css') {
            continue;
        }

        $absolutePath = str_replace('\\', '/', (string)$fileInfo->getPathname());
        $sourceRoot = rtrim(str_replace('\\', '/', $sourceDir), '/');
        if (!str_starts_with($absolutePath, $sourceRoot . '/')) {
            continue;
        }

        $relativePath = substr($absolutePath, strlen($sourceRoot) + 1);
        if (!is_string($relativePath) || $relativePath === '') {
            continue;
        }

        $relativePath = normalize_rel_path($relativePath);
        if (isset($excluded[$relativePath])) {
            continue;
        }

        $id = preg_replace('/\.css$/i', '', $relativePath);
        $id = str_replace('/', '.', (string)$id);
        if ($id === '') {
            continue;
        }

        $out[] = [
            'id' => $id,
            'path' => $relativePath,
            'abs' => str_replace('/', DIRECTORY_SEPARATOR, $sourceRoot . '/' . $relativePath),
        ];
    }

    usort(
        $out,
        static fn(array $a, array $b): int => strcmp((string)$a['path'], (string)$b['path'])
    );

    return $out;
}

function normalize_rel_path(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function humanize_theme_id(string $id): string
{
    return ucwords(str_replace(['-', '_', '.'], ' ', strtolower(trim($id))));
}

function write_compiled_target(string $targetPath, string $contents): bool
{
    $tmp = $targetPath . '.tmp-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, $contents) === false) {
        return false;
    }

    if (@rename($tmp, $targetPath)) {
        return true;
    }

    @unlink($tmp);
    return false;
}
