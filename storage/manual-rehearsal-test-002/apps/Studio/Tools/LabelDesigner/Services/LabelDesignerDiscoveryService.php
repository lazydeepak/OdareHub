<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\LabelDesigner\Services;

/**
 * Read-only discovery for owner-owned label resource folders.
 *
 * This service lists existing resources only. It must not create owner folders,
 * parse business data, write files, touch DB state, or become label truth.
 */
final class LabelDesignerDiscoveryService
{
    private const RESOURCE_TYPES = [
        'contexts' => 'Resources/labels/contexts',
        'templates' => 'Resources/labels/templates',
        'rules' => 'Resources/labels/rules',
    ];

    /**
     * @return array{owners:array<int,array<string,mixed>>,owner_count:int,resource_count:int}
     */
    public static function discover(): array
    {
        $owners = [];
        $resourceCount = 0;

        foreach (self::ownerRoots() as $owner) {
            $resources = [];
            $ownerResourceCount = 0;

            foreach (self::RESOURCE_TYPES as $type => $relativeDir) {
                $dir = $owner['root_path'] . '/' . $relativeDir;
                $files = self::listResourceFiles($dir);
                $resources[$type] = [
                    'path' => self::relativePath($dir),
                    'files' => $files,
                    'count' => count($files),
                ];
                $ownerResourceCount += count($files);
            }

            if ($ownerResourceCount <= 0) {
                continue;
            }

            $resourceCount += $ownerResourceCount;
            $owners[] = [
                'owner_key' => $owner['owner_key'],
                'owner_type' => $owner['owner_type'],
                'root_path' => self::relativePath($owner['root_path']),
                'resources' => $resources,
                'resource_count' => $ownerResourceCount,
            ];
        }

        return [
            'owners' => $owners,
            'owner_count' => count($owners),
            'resource_count' => $resourceCount,
        ];
    }

    /**
     * @return array<int,array{owner_key:string,owner_type:string,root_path:string}>
     */
    private static function ownerRoots(): array
    {
        $roots = [];

        foreach (self::childDirectories(APP_ROOT . '/apps') as $appDir) {
            $appKey = basename($appDir);
            $roots[] = [
                'owner_key' => $appKey,
                'owner_type' => 'app',
                'root_path' => $appDir,
            ];

            foreach (self::childDirectories($appDir . '/modules') as $moduleDir) {
                $roots[] = [
                    'owner_key' => $appKey . '/' . basename($moduleDir),
                    'owner_type' => 'module',
                    'root_path' => $moduleDir,
                ];
            }
        }

        foreach (self::childDirectories(APP_ROOT . '/plugins') as $pluginDir) {
            $roots[] = [
                'owner_key' => basename($pluginDir),
                'owner_type' => 'plugin',
                'root_path' => $pluginDir,
            ];
        }

        return $roots;
    }

    /**
     * @return array<int,string>
     */
    private static function childDirectories(string $dir): array
    {
        $realDir = realpath($dir);
        if (!is_string($realDir) || !is_dir($realDir) || !self::isInsideAppRoot($realDir)) {
            return [];
        }

        $children = scandir($realDir);
        if (!is_array($children)) {
            return [];
        }

        $dirs = [];
        foreach ($children as $child) {
            if ($child === '.' || $child === '..' || str_starts_with($child, '.')) {
                continue;
            }
            $path = $realDir . '/' . $child;
            if (is_dir($path)) {
                $dirs[] = $path;
            }
        }

        sort($dirs);
        return $dirs;
    }

    /**
     * @return array<int,array{name:string,path:string}>
     */
    private static function listResourceFiles(string $dir): array
    {
        $realDir = realpath($dir);
        if (!is_string($realDir) || !is_dir($realDir) || !self::isInsideAppRoot($realDir)) {
            return [];
        }

        $entries = scandir($realDir);
        if (!is_array($entries)) {
            return [];
        }

        $files = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                continue;
            }

            $path = $realDir . '/' . $entry;
            if (!is_file($path) || strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'json') {
                continue;
            }

            $files[] = [
                'name' => $entry,
                'path' => self::relativePath($path),
            ];
        }

        usort($files, static fn (array $a, array $b): int => strcmp((string)$a['name'], (string)$b['name']));
        return $files;
    }

    private static function isInsideAppRoot(string $path): bool
    {
        $root = realpath(APP_ROOT);
        if (!is_string($root)) {
            return false;
        }

        return $path === $root || str_starts_with($path, $root . '/');
    }

    private static function relativePath(string $path): string
    {
        $root = realpath(APP_ROOT);
        $realPath = realpath($path);
        if (!is_string($root)) {
            return $path;
        }

        $normalized = is_string($realPath) ? $realPath : $path;
        if ($normalized === $root) {
            return '.';
        }

        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }

        return $path;
    }
}
