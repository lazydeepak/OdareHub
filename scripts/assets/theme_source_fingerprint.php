<?php
declare(strict_types=1);

/**
 * Return the exact source files that determine public/assets/theme.css.
 *
 * @return array<int,string>|null Absolute paths, sorted by repository-relative path
 */
function odarehubThemeFingerprintFiles(string $root): ?array
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $manifestPath = $root . '/resources/themes/theme-manifest.json';
    $manifestRaw = @file_get_contents($manifestPath);
    $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
    if (!is_array($manifest) || !is_array($manifest['sources'] ?? null)) {
        return null;
    }

    $files = [
        $manifestPath,
        $root . '/scripts/assets/compile_theme_sources.php',
        $root . '/scripts/assets/theme_source_fingerprint.php',
    ];
    $includedPaths = [];
    $disabledPaths = [];

    foreach ($manifest['sources'] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $path = odarehubNormalizeThemeRelativePath((string)($entry['path'] ?? ''));
        if (!odarehubIsSafeThemeRelativePath($path)) {
            continue;
        }

        if (!empty($entry['enabled'])) {
            $includedPaths[$path] = true;
            $files[] = $root . '/resources/themes/' . $path;
            continue;
        }

        $kind = strtolower(trim((string)($entry['kind'] ?? '')));
        if (in_array($kind, ['style', 'custom'], true)) {
            $disabledPaths[$path] = true;
        }
    }

    $themeRoot = $root . '/resources/themes';
    if (is_dir($themeRoot)) {
        $excludedBase = ['foundation.css' => true, 'light.css' => true, 'dark.css' => true];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($themeRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()
                || strtolower((string)$fileInfo->getExtension()) !== 'css'
            ) {
                continue;
            }
            $absolute = str_replace('\\', '/', (string)$fileInfo->getPathname());
            $relative = odarehubNormalizeThemeRelativePath((string)substr($absolute, strlen($themeRoot) + 1));
            if ($relative === '' || isset($excludedBase[$relative]) || isset($includedPaths[$relative]) || isset($disabledPaths[$relative])) {
                continue;
            }
            $files[] = $absolute;
        }
    }

    $legacy = $manifest['legacy_base'] ?? null;
    if (is_array($legacy) && (!array_key_exists('enabled', $legacy) || !empty($legacy['enabled']))) {
        $legacyPath = odarehubNormalizeThemeRelativePath((string)($legacy['path'] ?? 'public/assets/theme.legacy.css'));
        if (odarehubIsSafeThemeRelativePath($legacyPath)) {
            $files[] = $root . '/' . $legacyPath;
        }
    }

    $files = array_values(array_unique(array_map(static fn (string $path): string => str_replace('\\', '/', $path), $files)));
    foreach ($files as $path) {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
    }
    usort($files, static fn (string $a, string $b): int => strcmp(substr($a, strlen($root) + 1), substr($b, strlen($root) + 1)));
    return $files;
}

function odarehubThemeSourceFingerprint(string $root): ?string
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $files = odarehubThemeFingerprintFiles($root);
    if ($files === null || $files === []) {
        return null;
    }

    $hash = hash_init('sha256');
    foreach ($files as $path) {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }
        $relative = substr(str_replace('\\', '/', $path), strlen($root) + 1);
        hash_update($hash, $relative . "\0" . hash('sha256', $contents) . "\0");
    }
    return hash_final($hash);
}

function odarehubCompiledThemeFingerprint(string $targetPath): ?string
{
    $head = @file_get_contents($targetPath, false, null, 0, 512);
    if (!is_string($head) || !preg_match('/Source fingerprint:\s*([a-f0-9]{64})/i', $head, $matches)) {
        return null;
    }
    return strtolower((string)$matches[1]);
}

function odarehubNormalizeThemeRelativePath(string $path): string
{
    return ltrim(str_replace('\\', '/', trim($path)), '/');
}

function odarehubIsSafeThemeRelativePath(string $path): bool
{
    return $path !== ''
        && !str_contains($path, "\0")
        && !str_contains('/' . $path . '/', '/../')
        && !str_contains($path, '//');
}
