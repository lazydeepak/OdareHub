<?php
declare(strict_types=1);

namespace App\Services;

final class AppManifestService
{
    private const REQUIRED_KEYS = [
        'id',
        'name',
        'version',
        'type',
        'min_core_version',
        'dependencies',
        'entry',
        'migrations_path',
        'permissions',
        'can_disable',
        'can_uninstall',
        'can_export',
    ];

    public static function loadFromFile(string $manifestPath): array
    {
        if (!is_file($manifestPath)) {
            throw new \RuntimeException('manifest.json not found');
        }

        $raw = (string)file_get_contents($manifestPath);
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new \RuntimeException('manifest.json must be a valid JSON object');
        }

        return self::validate($json);
    }

    public static function validate(array $manifest): array
    {
        $missing = [];
        foreach (self::REQUIRED_KEYS as $key) {
            if (!array_key_exists($key, $manifest)) {
                $missing[] = $key;
            }
        }
        if ($missing) {
            throw new \RuntimeException('manifest.json missing required keys: ' . implode(', ', $missing));
        }

        $id = trim((string)$manifest['id']);
        if ($id === '' || !preg_match('/^[a-z0-9][a-z0-9_\-\.]*$/', $id)) {
            throw new \RuntimeException('manifest id must be lowercase slug-like string');
        }

        if (!is_array($manifest['dependencies'])) {
            throw new \RuntimeException('manifest dependencies must be an array');
        }
        if (!is_array($manifest['permissions'])) {
            throw new \RuntimeException('manifest permissions must be an array');
        }

        $normalized = $manifest;
        $normalized['id'] = $id;
        $normalized['name'] = trim((string)$manifest['name']);
        $normalized['version'] = trim((string)$manifest['version']);
        $normalized['type'] = trim((string)$manifest['type']);
        $normalized['min_core_version'] = trim((string)$manifest['min_core_version']);
        $normalized['entry'] = ltrim((string)$manifest['entry'], '/');
        $normalized['migrations_path'] = trim((string)$manifest['migrations_path']);
        $normalized['dependencies'] = array_values(array_map('strval', (array)$manifest['dependencies']));
        $normalized['permissions'] = array_values(array_map('strval', (array)$manifest['permissions']));
        $normalized['can_disable'] = (bool)$manifest['can_disable'];
        $normalized['can_uninstall'] = (bool)$manifest['can_uninstall'];
        $normalized['can_export'] = (bool)$manifest['can_export'];

        $normalized['runtime_contract'] = self::buildRuntimeContract($normalized);
        $normalized['contract_warnings'] = self::collectContractWarnings($normalized);
        $normalized['contract_version'] = '1.0';

        return $normalized;
    }

    /**
     * @return array<int,array{hook_type:string,hook_key:string,payload:array<string,mixed>}>
     */
    public static function surfaceHooksFromManifest(array $manifest): array
    {
        $hooks = [];

        foreach ((array)($manifest['hooks'] ?? []) as $hook) {
            if (!is_array($hook)) {
                continue;
            }
            $hookType = trim((string)($hook['type'] ?? 'runtime'));
            $hookKey = trim((string)($hook['key'] ?? ''));
            if ($hookType === '' || $hookKey === '') {
                continue;
            }
            $hooks[] = [
                'hook_type' => $hookType,
                'hook_key' => $hookKey,
                'payload' => $hook,
            ];
        }

        foreach ((array)($manifest['menus'] ?? []) as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $menuKey = trim((string)($menu['key'] ?? ''));
            if ($menuKey === '') {
                continue;
            }
            $hooks[] = [
                'hook_type' => 'menu',
                'hook_key' => $menuKey,
                'payload' => $menu,
            ];
        }

        foreach ((array)($manifest['widgets'] ?? []) as $widget) {
            if (!is_array($widget)) {
                continue;
            }
            $widgetKey = trim((string)($widget['key'] ?? ''));
            if ($widgetKey === '') {
                continue;
            }
            $hooks[] = [
                'hook_type' => 'widget',
                'hook_key' => $widgetKey,
                'payload' => $widget,
            ];
        }

        $contract = (array)($manifest['runtime_contract'] ?? []);

        foreach ((array)($contract['routes'] ?? []) as $route) {
            if (!is_array($route)) {
                continue;
            }
            $path = trim((string)($route['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $hooks[] = [
                'hook_type' => 'route',
                'hook_key' => $path,
                'payload' => $route,
            ];
        }

        foreach (['charts' => 'chart', 'dashboards' => 'dashboard', 'search_entries' => 'search_entry', 'notifications' => 'notification', 'resolvers' => 'resolver', 'plugin_modules' => 'plugin_contract', 'module_contracts' => 'module_contract'] as $contractKey => $hookType) {
            foreach ((array)($contract[$contractKey] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $hookKey = trim((string)($entry['key'] ?? ''));
                if ($hookKey === '') {
                    continue;
                }
                $hooks[] = [
                    'hook_type' => $hookType,
                    'hook_key' => $hookKey,
                    'payload' => $entry,
                ];
            }
        }

        return $hooks;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<string,mixed>
     */
    private static function buildRuntimeContract(array $manifest): array
    {
        $appId = (string)($manifest['id'] ?? '');
        $existing = is_array($manifest['runtime_contract'] ?? null) ? (array)$manifest['runtime_contract'] : [];

        $routes = self::normalizeRouteContracts($appId, (array)($manifest['routes'] ?? []), (array)($existing['routes'] ?? []));
        $navigationItems = self::normalizeNavigationContracts($appId, (array)($manifest['menus'] ?? []), (array)($existing['navigation_items'] ?? []));
        $widgets = self::normalizeSurfaceContracts($appId, 'widget', (array)($manifest['widgets'] ?? []), (array)($existing['widgets'] ?? []));
        $charts = self::normalizeSurfaceContracts($appId, 'chart', (array)($manifest['charts'] ?? []), (array)($existing['charts'] ?? []));
        $dashboards = self::normalizeSurfaceContracts($appId, 'dashboard', (array)($manifest['dashboards'] ?? []), (array)($existing['dashboards'] ?? []));
        $searchEntries = self::normalizeSurfaceContracts($appId, 'search_entry', (array)($manifest['search_entries'] ?? []), (array)($existing['search_entries'] ?? []));
        $notifications = self::normalizeSurfaceContracts($appId, 'notification', (array)($manifest['notifications'] ?? []), (array)($existing['notifications'] ?? []));
        $resolvers = self::normalizeResolverContracts($appId, (array)($existing['resolvers'] ?? []));
        $moduleContracts = self::normalizeModuleContracts($appId, (array)($manifest['native_modules'] ?? []), (array)($existing['module_contracts'] ?? []));
        $pluginContracts = self::normalizePluginContracts($appId, (array)($manifest['legacy_bridge_plugins'] ?? []), (array)($existing['plugin_modules'] ?? []));

        return [
            'owner_app' => $appId,
            'routes' => $routes,
            'navigation_items' => $navigationItems,
            'widgets' => $widgets,
            'charts' => $charts,
            'dashboards' => $dashboards,
            'search_entries' => $searchEntries,
            'notifications' => $notifications,
            'resolvers' => $resolvers,
            'permissions' => array_values(array_map('strval', (array)($manifest['permissions'] ?? []))),
            'migrations' => [
                'path' => (string)($manifest['migrations_path'] ?? ''),
                'lifecycle_bound' => true,
            ],
            'purge' => is_array($manifest['purge'] ?? null) ? (array)$manifest['purge'] : ['tables' => [], 'drop_columns' => []],
            'module_contracts' => $moduleContracts,
            'plugin_modules' => $pluginContracts,
        ];
    }

    /**
     * @param array<int,mixed> $manifestRoutes
     * @param array<int,mixed> $contractRoutes
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeRouteContracts(string $appId, array $manifestRoutes, array $contractRoutes): array
    {
        $indexed = [];

        foreach ($contractRoutes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $path = self::normalizePath((string)($route['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $indexed[$path] = [
                'path' => $path,
                'owner_app' => $appId,
                'feature_key' => trim((string)($route['feature_key'] ?? self::featureFromPath($path))),
                'kind' => self::normalizeRouteKind((string)($route['kind'] ?? 'canonical')),
                'canonical_target' => self::normalizePath((string)($route['canonical_target'] ?? '')),
                'compatibility' => (bool)($route['compatibility'] ?? false),
                'deprecated' => (bool)($route['deprecated'] ?? false),
                'nav_visible' => (bool)($route['nav_visible'] ?? false),
                'search_visible' => (bool)($route['search_visible'] ?? false),
                'lifecycle_bound' => array_key_exists('lifecycle_bound', $route) ? (bool)$route['lifecycle_bound'] : true,
                'role_visibility' => self::normalizeStringList((array)($route['role_visibility'] ?? [])),
            ];
        }

        foreach ($manifestRoutes as $route) {
            if (!is_array($route)) {
                continue;
            }
            $path = self::normalizePath((string)($route['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $target = self::normalizePath((string)($route['compat_redirect'] ?? ''));
            $kind = $target !== '' ? 'alias' : 'canonical';

            $existing = $indexed[$path] ?? [];
            $indexed[$path] = [
                'path' => $path,
                'owner_app' => $appId,
                'feature_key' => trim((string)($existing['feature_key'] ?? self::featureFromPath($target !== '' ? $target : $path))),
                'kind' => self::normalizeRouteKind((string)($existing['kind'] ?? $kind)),
                'canonical_target' => self::normalizePath((string)($existing['canonical_target'] ?? $target)),
                'compatibility' => array_key_exists('compatibility', $existing) ? (bool)$existing['compatibility'] : ($target !== ''),
                'deprecated' => (bool)($existing['deprecated'] ?? false),
                'nav_visible' => (bool)($existing['nav_visible'] ?? false),
                'search_visible' => (bool)($existing['search_visible'] ?? false),
                'lifecycle_bound' => array_key_exists('lifecycle_bound', $existing) ? (bool)$existing['lifecycle_bound'] : true,
                'role_visibility' => self::normalizeStringList((array)($existing['role_visibility'] ?? [])),
            ];
        }

        $canonicalPaths = [];
        foreach ($indexed as $entry) {
            if ((string)($entry['kind'] ?? 'canonical') === 'canonical') {
                $canonicalPaths[(string)($entry['path'] ?? '')] = true;
            }
        }

        $out = array_values($indexed);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['path'] ?? ''), (string)($b['path'] ?? ''));
        });

        foreach ($out as &$entry) {
            if ((string)($entry['kind'] ?? '') !== 'alias') {
                continue;
            }

            $target = self::normalizePath((string)($entry['canonical_target'] ?? ''));
            if ($target === '' || !isset($canonicalPaths[$target])) {
                $entry['kind'] = 'canonical';
                $entry['canonical_target'] = '';
                $entry['compatibility'] = false;
                $entry['deprecated'] = false;
            }
        }
        unset($entry);

        return $out;
    }

    /**
     * @param array<int,mixed> $menus
     * @param array<int,mixed> $navigationItems
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeNavigationContracts(string $appId, array $menus, array $navigationItems): array
    {
        $indexed = [];

        foreach ($navigationItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = trim((string)($item['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $indexed[$key] = [
                'key' => $key,
                'owner_app' => $appId,
                'feature_key' => trim((string)($item['feature_key'] ?? $key)),
                'url' => self::normalizePath((string)($item['url'] ?? '')),
                'role_visibility' => self::normalizeStringList((array)($item['role_visibility'] ?? [])),
                'label_key' => trim((string)($item['label_key'] ?? '')),
                'label' => trim((string)($item['label'] ?? '')),
                'declared_in_contract' => true,
            ];
        }

        foreach ($menus as $menu) {
            if (!is_array($menu)) {
                continue;
            }
            $key = trim((string)($menu['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $existing = $indexed[$key] ?? [];
            $indexed[$key] = [
                'key' => $key,
                'owner_app' => $appId,
                'feature_key' => trim((string)($existing['feature_key'] ?? $key)),
                'url' => self::normalizePath((string)($existing['url'] ?? (string)($menu['url'] ?? ''))),
                'role_visibility' => self::normalizeStringList((array)($existing['role_visibility'] ?? [])),
                'label_key' => trim((string)($existing['label_key'] ?? (string)($menu['label_key'] ?? ''))),
                'label' => trim((string)($existing['label'] ?? (string)($menu['label'] ?? ''))),
                'declared_in_contract' => array_key_exists('declared_in_contract', $existing) ? (bool)$existing['declared_in_contract'] : true,
            ];
        }

        $out = array_values($indexed);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['key'] ?? ''), (string)($b['key'] ?? ''));
        });
        return $out;
    }

    /**
     * @param array<int,mixed> $legacy
     * @param array<int,mixed> $contracts
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeSurfaceContracts(string $appId, string $surfaceType, array $legacy, array $contracts): array
    {
        $indexed = [];
        foreach ($contracts as $surface) {
            if (!is_array($surface)) {
                continue;
            }
            $key = trim((string)($surface['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $indexed[$key] = [
                'key' => $key,
                'owner_app' => $appId,
                'surface_type' => $surfaceType,
                'feature_key' => trim((string)($surface['feature_key'] ?? $key)),
                'url' => self::normalizePath((string)($surface['url'] ?? '')),
                'role_visibility' => self::normalizeStringList((array)($surface['role_visibility'] ?? [])),
                'nav_visible' => (bool)($surface['nav_visible'] ?? false),
                'search_visible' => (bool)($surface['search_visible'] ?? false),
                'label_key' => trim((string)($surface['label_key'] ?? '')),
                'label' => trim((string)($surface['label'] ?? '')),
                'meta' => trim((string)($surface['meta'] ?? '')),
                'declared_in_contract' => true,
            ];
        }

        foreach ($legacy as $surface) {
            if (!is_array($surface)) {
                continue;
            }
            $key = trim((string)($surface['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $existing = $indexed[$key] ?? [];
            $label = trim((string)($surface['title'] ?? ($surface['label'] ?? '')));
            $indexed[$key] = [
                'key' => $key,
                'owner_app' => $appId,
                'surface_type' => $surfaceType,
                'feature_key' => trim((string)($existing['feature_key'] ?? $key)),
                'url' => self::normalizePath((string)($existing['url'] ?? (string)($surface['url'] ?? ''))),
                'role_visibility' => self::normalizeStringList((array)($existing['role_visibility'] ?? (isset($surface['visible_if']) ? [(string)$surface['visible_if']] : []))),
                'nav_visible' => (bool)($existing['nav_visible'] ?? false),
                'search_visible' => (bool)($existing['search_visible'] ?? false),
                'label_key' => trim((string)($existing['label_key'] ?? (string)($surface['label_key'] ?? (string)($surface['title_key'] ?? '')))),
                'label' => trim((string)($existing['label'] ?? $label)),
                'meta' => trim((string)($existing['meta'] ?? ($surface['detail'] ?? ''))),
                'declared_in_contract' => array_key_exists('declared_in_contract', $existing) ? (bool)$existing['declared_in_contract'] : true,
            ];
        }

        $out = array_values($indexed);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['key'] ?? ''), (string)($b['key'] ?? ''));
        });
        return $out;
    }

    /**
     * @param array<int,mixed> $contracts
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeResolverContracts(string $appId, array $contracts): array
    {
        $out = [];
        foreach ($contracts as $resolver) {
            if (!is_array($resolver)) {
                continue;
            }
            $key = trim((string)($resolver['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $out[] = [
                'key' => $key,
                'owner_app' => $appId,
                'feature_key' => trim((string)($resolver['feature_key'] ?? $key)),
                'target_service' => trim((string)($resolver['target_service'] ?? '')),
                'description' => trim((string)($resolver['description'] ?? '')),
                'lifecycle_bound' => array_key_exists('lifecycle_bound', $resolver) ? (bool)$resolver['lifecycle_bound'] : true,
            ];
        }
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['key'] ?? ''), (string)($b['key'] ?? ''));
        });
        return $out;
    }

    /**
     * @param array<int,mixed> $nativeModules
     * @param array<int,mixed> $contracts
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeModuleContracts(string $appId, array $nativeModules, array $contracts): array
    {
        $indexed = [];
        foreach ($contracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }
            $featureKey = trim((string)($contract['feature_key'] ?? ''));
            if ($featureKey === '') {
                continue;
            }
            $indexed[$featureKey] = [
                'feature_key' => $featureKey,
                'owner_app' => $appId,
                'dependencies' => self::normalizeStringList((array)($contract['dependencies'] ?? [])),
                'canonical_surfaces' => self::normalizeStringList((array)($contract['canonical_surfaces'] ?? [])),
                'compatibility_surfaces' => self::normalizeStringList((array)($contract['compatibility_surfaces'] ?? [])),
                'nav_visible' => (bool)($contract['nav_visible'] ?? false),
                'search_visible' => (bool)($contract['search_visible'] ?? false),
                'role_scoped' => (bool)($contract['role_scoped'] ?? false),
            ];
        }

        foreach ($nativeModules as $module) {
            $featureKey = trim((string)$module);
            if ($featureKey === '') {
                continue;
            }
            $indexed[$featureKey] = $indexed[$featureKey] ?? [
                'feature_key' => $featureKey,
                'owner_app' => $appId,
                'dependencies' => [],
                'canonical_surfaces' => [],
                'compatibility_surfaces' => [],
                'nav_visible' => false,
                'search_visible' => false,
                'role_scoped' => false,
            ];
        }

        $out = array_values($indexed);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['feature_key'] ?? ''), (string)($b['feature_key'] ?? ''));
        });
        return $out;
    }

    /**
     * @param array<int,mixed> $legacyPlugins
     * @param array<int,mixed> $contracts
     * @return array<int,array<string,mixed>>
     */
    private static function normalizePluginContracts(string $appId, array $legacyPlugins, array $contracts): array
    {
        $indexed = [];

        foreach ($contracts as $contract) {
            if (!is_array($contract)) {
                continue;
            }
            $name = trim((string)($contract['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $indexed[$name] = [
                'key' => strtolower($name),
                'name' => $name,
                'owner_app' => $appId,
                'feature_key' => trim((string)($contract['feature_key'] ?? strtolower($name))),
                'dependencies' => self::normalizeStringList((array)($contract['dependencies'] ?? [])),
                'runtime_surfaces' => self::normalizeStringList((array)($contract['runtime_surfaces'] ?? [])),
                'kind' => in_array((string)($contract['kind'] ?? 'compatibility'), ['canonical', 'compatibility'], true) ? (string)$contract['kind'] : 'compatibility',
                'nav_visible' => (bool)($contract['nav_visible'] ?? false),
                'search_visible' => (bool)($contract['search_visible'] ?? false),
                'role_scoped' => (bool)($contract['role_scoped'] ?? false),
            ];
        }

        foreach ($legacyPlugins as $plugin) {
            $name = '';
            $featureKey = '';
            if (is_array($plugin)) {
                $name = trim((string)($plugin['name'] ?? ''));
                $featureKey = trim((string)($plugin['feature_key'] ?? ''));
            } else {
                $name = trim((string)$plugin);
            }

            if ($name === '') {
                continue;
            }

            if (!isset($indexed[$name])) {
                $indexed[$name] = [
                    'key' => strtolower($name),
                    'name' => $name,
                    'owner_app' => $appId,
                    'feature_key' => $featureKey !== '' ? $featureKey : strtolower($name),
                    'dependencies' => [],
                    'runtime_surfaces' => [],
                    'kind' => 'compatibility',
                    'nav_visible' => false,
                    'search_visible' => false,
                    'role_scoped' => false,
                ];
            }
        }

        $out = array_values($indexed);
        usort($out, static function (array $a, array $b): int {
            return strcmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
        });
        return $out;
    }

    /**
     * @param array<string,mixed> $manifest
     * @return array<int,string>
     */
    private static function collectContractWarnings(array $manifest): array
    {
        $warnings = [];
        $contract = (array)($manifest['runtime_contract'] ?? []);

        foreach ((array)($contract['navigation_items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = (string)($item['key'] ?? 'unknown-nav-item');
            if (!(bool)($item['declared_in_contract'] ?? false)) {
                continue;
            }
            $labelKey = trim((string)($item['label_key'] ?? ''));
            $label = trim((string)($item['label'] ?? ''));
            if ($labelKey === '' && $label !== '') {
                $warnings[] = 'navigation item `' . $key . '` uses hardcoded label; add label_key for EN/JA readiness';
            }
        }

        foreach (['widgets', 'dashboards', 'charts', 'search_entries'] as $surfaceType) {
            foreach ((array)($contract[$surfaceType] ?? []) as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $key = (string)($entry['key'] ?? ('unknown-' . $surfaceType));
                if (!(bool)($entry['declared_in_contract'] ?? false)) {
                    continue;
                }
                $labelKey = trim((string)($entry['label_key'] ?? ''));
                $label = trim((string)($entry['label'] ?? ''));
                if ($labelKey === '' && $label !== '') {
                    $warnings[] = $surfaceType . ' entry `' . $key . '` uses hardcoded label; add label_key for EN/JA readiness';
                }
            }
        }

        return $warnings;
    }

    private static function normalizeRouteKind(string $kind): string
    {
        $kind = strtolower(trim($kind));
        return in_array($kind, ['canonical', 'alias'], true) ? $kind : 'canonical';
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || !str_starts_with($path, '/')) {
            return '';
        }
        $path = preg_replace('#/+#', '/', $path) ?: $path;
        return rtrim($path, '/') ?: '/';
    }

    /**
     * @param array<int,mixed> $values
     * @return array<int,string>
     */
    private static function normalizeStringList(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            $item = trim((string)$value);
            if ($item !== '') {
                $out[] = $item;
            }
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);
        return $out;
    }

    private static function featureFromPath(string $path): string
    {
        $path = trim($path, '/');
        if ($path === '') {
            return 'root';
        }

        $parts = explode('/', $path);
        $leaf = (string)end($parts);
        $leaf = preg_replace('/[^a-z0-9_\-]+/i', '_', $leaf) ?: 'surface';
        return strtolower(trim($leaf, '_')) ?: 'surface';
    }
}
