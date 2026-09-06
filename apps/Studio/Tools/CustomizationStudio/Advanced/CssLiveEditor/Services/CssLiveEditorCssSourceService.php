<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorCssSourceService
{
    /**
     * @param array<int,array<string,mixed>> $targets
     * @return array{targets:array<string,array<string,mixed>>,sources:array<string,array<string,mixed>>}
     */
    public static function catalog(array $targets): array
    {
        $catalog = ['targets' => [], 'sources' => []];
        foreach ($targets as $target) {
            if (!is_array($target)) {
                continue;
            }

            $targetId = trim((string)($target['id'] ?? ''));
            if ($targetId === '') {
                continue;
            }

            $candidatePaths = self::candidatePaths($target);
            $catalog['targets'][$targetId] = [
                'feed_type' => (string)($target['adapter_name'] ?? $target['target_type'] ?? ''),
                'owner' => (string)($target['owner'] ?? ''),
                'source' => (string)($target['source_hint'] ?? $target['route'] ?? ''),
                'candidates' => $candidatePaths,
            ];

            foreach ($candidatePaths as $candidatePath) {
                if (isset($catalog['sources'][$candidatePath])) {
                    continue;
                }
                $absolutePath = self::approvedCssAbsolutePath($candidatePath);
                if ($absolutePath === '') {
                    continue;
                }
                $catalog['sources'][$candidatePath] = [
                    'path' => $candidatePath,
                    'selectors' => self::selectors($absolutePath),
                ];
            }
        }

        ksort($catalog['targets'], SORT_NATURAL | SORT_FLAG_CASE);
        ksort($catalog['sources'], SORT_NATURAL | SORT_FLAG_CASE);
        return $catalog;
    }

    /**
     * @param array<string,mixed> $target
     * @return list<string>
     */
    private static function candidatePaths(array $target): array
    {
        $paths = [];
        foreach ((array)($target['css_sources'] ?? []) as $declaredPath) {
            $path = self::normalizeRelativePath((string)$declaredPath);
            if (self::approvedCssAbsolutePath($path) !== '') {
                $paths[$path] = true;
            }
        }

        if ($paths === []) {
            foreach (self::discoverOwnerCss(self::normalizeRelativePath((string)($target['owner_root'] ?? ''))) as $path) {
                $paths[$path] = true;
            }
        }

        $result = array_keys($paths);
        sort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return $result;
    }

    /**
     * @return list<string>
     */
    private static function discoverOwnerCss(string $ownerRoot): array
    {
        $absoluteRoot = self::approvedOwnerAbsolutePath($ownerRoot);
        if ($absoluteRoot === '') {
            return [];
        }

        $paths = [];
        if (is_file($absoluteRoot . '/styles.css')) {
            $relative = self::relativeToRoot($absoluteRoot . '/styles.css');
            $paths[strtolower($relative)] = $relative;
        }

        foreach (['styles', 'Styles', 'assets', 'Assets', 'Resources'] as $folder) {
            $directory = $absoluteRoot . '/' . $folder;
            if (!is_dir($directory)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'css') {
                    continue;
                }
                $relative = self::relativeToRoot((string)$file->getRealPath());
                if (self::approvedCssAbsolutePath($relative) !== '') {
                    $key = strtolower($relative);
                    if (!isset($paths[$key])) {
                        $paths[$key] = $relative;
                    }
                }
            }
        }

        return array_values($paths);
    }

    /**
     * @return list<array{selector:string,declaration:string}>
     */
    private static function selectors(string $absolutePath): array
    {
        $css = @file_get_contents($absolutePath);
        if (!is_string($css) || $css === '') {
            return [];
        }

        $css = (string)preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/(?:^|})\s*([^{}@][^{}]*?)\s*\{([^}]*)\}/m', $css, $matches);
        $result = [];
        foreach ((array)($matches[1] ?? []) as $i => $selectorGroup) {
            $declaration = trim((string)($matches[2][$i] ?? ''));
            foreach (explode(',', (string)$selectorGroup) as $selector) {
                $normalized = trim((string)preg_replace('/\s+/', ' ', $selector));
                if ($normalized === '' || (!str_contains($normalized, '.') && !str_contains($normalized, '#'))) {
                    continue;
                }
                $result[] = [
                    'selector' => $normalized,
                    'declaration' => $declaration,
                ];
                if (count($result) >= 800) {
                    break 2;
                }
            }
        }

        return $result;
    }

    private static function approvedOwnerAbsolutePath(string $ownerRoot): string
    {
        if ($ownerRoot === '' || self::isForbiddenPath($ownerRoot)) {
            return '';
        }
        if (!preg_match('#^(apps/(?:[^/]+|Studio/Tools/[^/]+|[^/]+/(?:Modules|modules)/[^/]+)|plugins/[^/]+)$#', $ownerRoot)) {
            return '';
        }

        $absolute = realpath(APP_ROOT . '/' . $ownerRoot);
        $root = realpath(APP_ROOT);
        return $absolute !== false
            && $root !== false
            && is_dir($absolute)
            && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)
                ? $absolute
                : '';
    }

    private static function approvedCssAbsolutePath(string $relativePath): string
    {
        if ($relativePath === '' || self::isForbiddenPath($relativePath) || strtolower(pathinfo($relativePath, PATHINFO_EXTENSION)) !== 'css') {
            return '';
        }
        if (!preg_match('#^(apps|plugins)/#', $relativePath)) {
            return '';
        }
        if (!preg_match('#/(?:styles|Styles|assets|Assets|Resources)(?:/|\.css$)#', $relativePath)
            && !str_ends_with($relativePath, '/styles.css')) {
            return '';
        }

        $absolute = realpath(APP_ROOT . '/' . $relativePath);
        $root = realpath(APP_ROOT);
        return $absolute !== false
            && $root !== false
            && is_file($absolute)
            && str_starts_with($absolute, $root . DIRECTORY_SEPARATOR)
                ? $absolute
                : '';
    }

    private static function normalizeRelativePath(string $path): string
    {
        $normalized = trim(str_replace('\\', '/', $path));
        if ($normalized === '' || str_starts_with($normalized, '/') || str_contains($normalized, "\0")) {
            return '';
        }
        $parts = array_values(array_filter(explode('/', $normalized), static fn(string $part): bool => $part !== ''));
        if ($parts === [] || in_array('.', $parts, true) || in_array('..', $parts, true)) {
            return '';
        }
        return implode('/', $parts);
    }

    private static function isForbiddenPath(string $path): bool
    {
        return (bool)preg_match('#(^|/)(?:app|Core|core|vendor|public|storage|cache)(/|$)#', $path);
    }

    private static function relativeToRoot(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', (string)realpath(APP_ROOT)), '/') . '/';
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : '';
    }
}
