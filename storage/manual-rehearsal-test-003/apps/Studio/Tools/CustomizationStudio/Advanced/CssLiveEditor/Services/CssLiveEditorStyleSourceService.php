<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorStyleSourceService
{
    /**
     * @return array<string,array{owner:string,source:string}>
     */
    public static function catalog(): array
    {
        $catalog = [
            '/assets/normalize.css' => [
                'owner' => 'Shell',
                'source' => 'public/assets/normalize.css',
            ],
            '/assets/theme.css' => [
                'owner' => 'Theme',
                'source' => 'resources/themes/theme-manifest.json',
            ],
            '/assets/layout.css' => [
                'owner' => 'Shell',
                'source' => 'public/assets/layout.css',
            ],
            '/assets/wrapper-shared.css' => [
                'owner' => 'Shell',
                'source' => 'public/assets/wrapper-shared.css',
            ],
        ];

        foreach (self::manifestPaths() as $manifestPath) {
            $manifestRaw = @file_get_contents($manifestPath);
            $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
            if (!is_array($manifest)) {
                continue;
            }

            $appRoot = dirname($manifestPath);
            $appKey = strtolower(trim((string)($manifest['app_key'] ?? $manifest['id'] ?? basename($appRoot))));
            $owner = trim((string)($manifest['name'] ?? basename($appRoot)));
            if ($appKey === '' || $owner === '') {
                continue;
            }

            foreach ((array)($manifest['styles'] ?? []) as $style) {
                if (!is_array($style)) {
                    continue;
                }

                $path = self::normalizeRelativePath((string)($style['path'] ?? ''));
                $scope = strtolower(trim((string)($style['scope'] ?? 'app')));
                if ($path === '' || !is_file($appRoot . '/' . $path)) {
                    continue;
                }

                if ($scope === 'module') {
                    $moduleKey = strtolower(trim((string)($style['module'] ?? '')));
                    if ($moduleKey === '') {
                        continue;
                    }
                    $url = '/assets/apps/' . $appKey . '/modules/' . $moduleKey . '/styles.css';
                } else {
                    $url = '/assets/apps/' . $appKey . '/styles/' . basename($path);
                }

                $catalog[$url] = [
                    'owner' => $owner,
                    'source' => self::relativeToRoot($appRoot . '/' . $path),
                ];
            }
        }

        ksort($catalog);
        return $catalog;
    }

    /**
     * @return list<string>
     */
    private static function manifestPaths(): array
    {
        $paths = glob(APP_ROOT . '/apps/*/manifest.json') ?: [];
        $generated = glob(APP_ROOT . '/apps/Generated/*/manifest.json') ?: [];
        $paths = array_values(array_unique(array_merge($paths, $generated)));
        sort($paths, SORT_STRING);
        return $paths;
    }

    private static function normalizeRelativePath(string $path): string
    {
        return ltrim(str_replace('\\', '/', trim($path)), '/');
    }

    private static function relativeToRoot(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', APP_ROOT), '/') . '/';
        return str_starts_with($normalized, $root)
            ? substr($normalized, strlen($root))
            : $normalized;
    }
}
