<?php
declare(strict_types=1);

require_once __DIR__ . '/theme_source_fingerprint.php';

const FIRST_BOOT_MANIFEST = 'scripts/assets/first_boot_css_manifest.json';

/**
 * @return array<string,mixed>
 */
function compileFirstBootCssAssets(string $root, bool $apply): array
{
    $result = [
        'schema' => 'susankhya.first_boot_css_compile.v1',
        'mode' => $apply ? 'apply' : 'dry-run',
        'manifest' => FIRST_BOOT_MANIFEST,
        'ok' => false,
        'assets' => [],
        'errors' => [],
    ];

    $manifestPath = $root . '/' . FIRST_BOOT_MANIFEST;
    $manifestRaw = @file_get_contents($manifestPath);
    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest) || !is_array($manifest['assets'] ?? null)) {
        $result['errors'][] = 'manifest_missing_or_invalid';
        return $result;
    }

    foreach ($manifest['assets'] as $index => $asset) {
        if (!is_array($asset)) {
            $result['errors'][] = 'invalid_asset_entry:' . $index;
            continue;
        }

        $target = normalizeFirstBootRelativePath((string)($asset['target'] ?? ''));
        $sources = array_values(array_filter(
            is_array($asset['sources'] ?? null) ? $asset['sources'] : [],
            static fn ($source): bool => is_string($source) && trim($source) !== ''
        ));

        if (!isAllowedFirstBootTarget($target) || $sources === []) {
            $result['errors'][] = 'invalid_asset_contract:' . $index;
            continue;
        }

        $compiled = "/* GENERATED FILE: {$target} */\n";
        $compiled .= "/* Source manifest: " . FIRST_BOOT_MANIFEST . " */\n\n";
        $sourceRecords = [];

        foreach ($sources as $source) {
            $source = normalizeFirstBootRelativePath($source);
            if (!isAllowedFirstBootSource($source)) {
                $result['errors'][] = 'invalid_source:' . $source;
                continue 2;
            }

            $sourcePath = $root . '/' . $source;
            $css = @file_get_contents($sourcePath);
            if (!is_string($css)) {
                $result['errors'][] = 'missing_or_unreadable_source:' . $source;
                continue 2;
            }

            $sourceRecords[] = [
                'path' => $source,
                'sha256' => hash('sha256', $css),
            ];
            $compiled .= "/* source: {$source} */\n" . rtrim($css) . "\n\n";
        }

        $targetPath = $root . '/' . $target;
        $current = @file_get_contents($targetPath);
        $status = is_string($current) && hash_equals(hash('sha256', $current), hash('sha256', $compiled))
            ? 'current'
            : (is_file($targetPath) ? 'update' : 'create');

        if ($apply && $status !== 'current') {
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
                $result['errors'][] = 'target_directory_create_failed:' . $target;
                continue;
            }

            $temporary = $targetPath . '.tmp-' . getmypid();
            if (@file_put_contents($temporary, $compiled) === false || !@rename($temporary, $targetPath)) {
                @unlink($temporary);
                $result['errors'][] = 'target_write_failed:' . $target;
                continue;
            }
            $status = 'applied';
        }

        $result['assets'][] = [
            'target' => $target,
            'sources' => $sourceRecords,
            'status' => $status,
            'sha256' => hash('sha256', $compiled),
        ];
    }

    $result['ok'] = $result['errors'] === [];

    // Also ensure theme.css is available for first-boot surfaces
    $themeResult = compileThemeCssIfAvailable($root, $apply);
    $result['theme_css'] = $themeResult;
    if (!$themeResult['ok']) {
        $result['ok'] = false;
        $result['errors'][] = 'theme_css_generation_failed';
    }

    return $result;
}

/**
 * Also compile the runtime theme.css from source themes if available.
 * This ensures public/assets/theme.css exists for first-boot surfaces
 * (setup, login, etc.) that reference it in auth_header.php.
 *
 * @return array{ok: bool, theme_css_status: string, error?: string}
 */
function compileThemeCssIfAvailable(string $root, bool $apply = true): array
{
    $themeManifestPath = $root . '/resources/themes/theme-manifest.json';
    $themeCompilerPath = $root . '/scripts/assets/compile_theme_sources.php';
    $themeTargetPath = $root . '/public/assets/theme.css';

    if (!is_file($themeManifestPath)) {
        return ['ok' => true, 'theme_css_status' => 'skipped_no_manifest'];
    }

    $sourceFingerprint = susankhyaThemeSourceFingerprint($root);
    if ($sourceFingerprint === null) {
        return ['ok' => false, 'theme_css_status' => 'source_fingerprint_failed'];
    }
    $targetFingerprint = susankhyaCompiledThemeFingerprint($themeTargetPath);
    if ($targetFingerprint !== null && hash_equals($sourceFingerprint, $targetFingerprint)) {
        return ['ok' => true, 'theme_css_status' => 'already_current'];
    }
    if (!$apply) {
        return [
            'ok' => true,
            'theme_css_status' => is_file($themeTargetPath) ? 'would_update' : 'would_create',
        ];
    }

    // Try exec-based compilation first (full compiler with manifest-based ordering).
    $execAvailable = is_file($themeCompilerPath);
    if ($execAvailable) {
        $phpBin = defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== ''
            ? PHP_BINARY
            : 'php';

        $command = escapeshellarg($phpBin)
            . ' '
            . escapeshellarg($themeCompilerPath)
            . ' --apply --json 2>&1';

        $output = [];
        $exitCode = 0;
        @exec($command, $output, $exitCode);

        $compiledFingerprint = susankhyaCompiledThemeFingerprint($themeTargetPath);
        if ($exitCode === 0 && $compiledFingerprint !== null && hash_equals($sourceFingerprint, $compiledFingerprint)) {
            return ['ok' => true, 'theme_css_status' => 'compiled'];
        }
    }

    // Fallback: in-process aggregation when exec() is unavailable or fails.
    // Reads the theme manifest and concatenates enabled source files directly.
    $manifestRaw = @file_get_contents($themeManifestPath);
    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest) || !is_array($manifest['sources'] ?? null)) {
        return [
            'ok' => false,
            'theme_css_status' => 'fallback_manifest_invalid',
            'error' => 'in-process fallback: manifest missing or invalid',
        ];
    }

    $compiled = "/* GENERATED FILE: assets/theme.css */\n";
    $compiled .= "/* Compiled by first_boot_css_compiler.php in-process fallback */\n";
    $compiled .= "/* Source fingerprint: {$sourceFingerprint} */\n\n";

    foreach ($manifest['sources'] as $entry) {
        if (!is_array($entry) || empty($entry['enabled'])) {
            continue;
        }

        $sourcePath = trim((string)($entry['path'] ?? ''));
        if ($sourcePath === '' || str_contains($sourcePath, '..') || str_starts_with($sourcePath, '/')) {
            continue;
        }

        $absolutePath = $root . '/resources/themes/' . ltrim(str_replace('\\', '/', $sourcePath), '/');
        $css = @file_get_contents($absolutePath);
        if (!is_string($css) || $css === '') {
            continue;
        }

        $id = $entry['id'] ?? $sourcePath;
        $compiled .= "/* source: {$id} ({$sourcePath}) */\n" . rtrim($css) . "\n\n";
    }

    // Check if any source content was aggregated.
    $emptyHeader = "/* GENERATED FILE: assets/theme.css */\n/* Compiled by first_boot_css_compiler.php in-process fallback */\n/* Source fingerprint: {$sourceFingerprint} */\n\n";
    if (trim($compiled) === '' || $compiled === $emptyHeader) {
        return [
            'ok' => false,
            'theme_css_status' => 'fallback_empty',
            'error' => 'in-process fallback: no sources compiled',
        ];
    }

    $targetDir = dirname($themeTargetPath);
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0775, true);
    }

    $temporary = $themeTargetPath . '.tmp-' . getmypid();
    if (@file_put_contents($temporary, $compiled) === false || !@rename($temporary, $themeTargetPath)) {
        @unlink($temporary);
        return [
            'ok' => false,
            'theme_css_status' => 'fallback_write_failed',
            'error' => 'in-process fallback: target write failed',
        ];
    }

    return ['ok' => true, 'theme_css_status' => 'compiled_via_fallback'];
}

function normalizeFirstBootRelativePath(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function isSafeFirstBootRelativePath(string $path): bool
{
    return $path !== ''
        && !str_contains($path, "\0")
        && !str_contains('/' . $path . '/', '/../')
        && !str_contains($path, '//');
}

function isAllowedFirstBootSource(string $path): bool
{
    return isSafeFirstBootRelativePath($path)
        && str_ends_with($path, '.css')
        && (str_starts_with($path, 'apps/Shell/') || str_starts_with($path, 'apps/Platform/'));
}

function isAllowedFirstBootTarget(string $path): bool
{
    if (!isSafeFirstBootRelativePath($path) || !str_ends_with($path, '.css')) {
        return false;
    }

    foreach (['public/assets/system/', 'public/assets/rendering/', 'public/assets/effects/', 'public/assets/themes/'] as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
}
