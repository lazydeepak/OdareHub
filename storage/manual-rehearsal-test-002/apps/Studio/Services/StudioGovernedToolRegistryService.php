<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioGovernedToolRegistryService
{
    private const TOOLS_ROOT = APP_ROOT . '/apps/Studio/Tools';

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listTools(): array
    {
        $root = self::TOOLS_ROOT;
        if (!is_dir($root)) {
            return [];
        }

        $tools = [];
        foreach (self::manifestPaths($root) as $manifestPath) {
            $manifest = require $manifestPath;
            if (!is_array($manifest)) {
                continue;
            }

            $normalized = self::normalizeManifest($manifest, basename(dirname($manifestPath)));
            if ($normalized === null) {
                continue;
            }
            $tools[] = $normalized;
        }

        usort(
            $tools,
            static fn(array $a, array $b): int => strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''))
        );
        return $tools;
    }

    /**
     * @return array<int,string>
     */
    private static function manifestPaths(string $root): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile() || $fileInfo->getFilename() !== 'manifest.php') {
                continue;
            }
            $paths[] = $fileInfo->getPathname();
        }
        sort($paths, SORT_STRING);
        return $paths;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findTool(string $key): ?array
    {
        $safeKey = self::normalizeKey($key);
        if ($safeKey === '') {
            return null;
        }

        foreach (self::listTools() as $tool) {
            if ((string)($tool['key'] ?? '') === $safeKey) {
                return $tool;
            }
        }
        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listEligibleToolsForResourceType(string $resourceType): array
    {
        $safeType = self::normalizeKey($resourceType);
        if ($safeType === '') {
            return [];
        }

        $eligible = [];
        foreach (self::listTools() as $tool) {
            $canLoad = is_array($tool['can_load'] ?? null) ? $tool['can_load'] : [];
            if (in_array($safeType, $canLoad, true)) {
                $eligible[] = $tool;
            }
        }
        return $eligible;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>|null
     */
    private static function normalizeManifest(array $manifest, string $fallbackKey): ?array
    {
        $key = self::normalizeKey((string)($manifest['key'] ?? $fallbackKey));
        if ($key === '') {
            return null;
        }

        $canLoad = [];
        foreach ((array)($manifest['can_load'] ?? []) as $type) {
            if (!is_string($type)) {
                continue;
            }
            $normalizedType = self::normalizeKey($type);
            if ($normalizedType !== '') {
                $canLoad[] = $normalizedType;
            }
        }
        $canLoad = array_values(array_unique($canLoad));
        $requiredPermissions = self::normalizeStringList((array)($manifest['required_permissions'] ?? []));
        $allowedEnvironments = self::normalizeStringList((array)($manifest['allowed_environments'] ?? []));
        $routes = self::normalizeStringList((array)($manifest['routes'] ?? []));
        $views = self::normalizeStringList((array)($manifest['views'] ?? []));
        $services = self::normalizeStringList((array)($manifest['services'] ?? []));

        return [
            'key' => $key,
            'name' => trim((string)($manifest['name'] ?? $key)),
            'name_key' => trim((string)($manifest['name_key'] ?? ('studio.tool.' . $key . '.name'))),
            'description_key' => trim((string)($manifest['description_key'] ?? ('studio.tool.' . $key . '.description'))),
            'category' => self::normalizeKey((string)($manifest['category'] ?? 'governance')),
            'home_group' => self::normalizeKey((string)($manifest['home_group'] ?? 'governance')),
            'status' => self::normalizeKey((string)($manifest['status'] ?? 'planned')),
            'canonical_route' => self::normalizeRoute((string)($manifest['canonical_route'] ?? '')),
            'placeholder' => !empty($manifest['placeholder']),
            'visible_on_home' => !array_key_exists('visible_on_home', $manifest) || !empty($manifest['visible_on_home']),
            'migration' => is_array($manifest['migration'] ?? null) ? $manifest['migration'] : [],
            'risk_level' => self::normalizeKey((string)($manifest['risk_level'] ?? 'medium')),
            'default_enabled' => !empty($manifest['default_enabled']),
            'can_disable' => !array_key_exists('can_disable', $manifest) || !empty($manifest['can_disable']),
            'required_permissions' => $requiredPermissions,
            'allowed_environments' => $allowedEnvironments,
            'routes' => $routes,
            'views' => $views,
            'services' => $services,
            'owner' => self::normalizeKey((string)($manifest['owner'] ?? 'studio')),
            'can_load' => $canLoad,
            'can_modify' => !empty($manifest['can_modify']),
            'requires_approval' => !empty($manifest['requires_approval']),
            'writes_to_owner_artifact' => !empty($manifest['writes_to_owner_artifact']),
            'supports_diff' => !empty($manifest['supports_diff']),
            'supports_snapshot' => !empty($manifest['supports_snapshot']),
            'supports_rollback' => !empty($manifest['supports_rollback']),
        ];
    }

    private static function normalizeKey(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }
        return (string)preg_replace('/[^a-z0-9_]+/', '_', $normalized);
    }

    private static function normalizeRoute(string $value): string
    {
        $route = trim($value);
        if ($route === '' || !str_starts_with($route, '/apps/studio/')) {
            return '';
        }
        return rtrim($route, '/');
    }

    /**
     * @return array<int,string>
     */
    private static function normalizeStringList(array $values): array
    {
        $normalized = [];
        foreach ($values as $value) {
            if (!is_string($value)) {
                continue;
            }
            $item = trim($value);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }
        return array_values(array_unique($normalized));
    }
}
