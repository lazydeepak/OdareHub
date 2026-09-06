<?php
declare(strict_types=1);

namespace App\Core;

final class RouteRuntimeAuthority
{
    /** @var array<string, array<string, bool>> */
    private static array $loaded = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * @param array<string, array<string, callable>> $routeMap
     */
    public static function seed(array $routeMap): void
    {
        self::$loaded = [
            'GET' => [],
            'POST' => [],
        ];

        foreach (['GET', 'POST'] as $method) {
            foreach (array_keys((array)($routeMap[$method] ?? [])) as $path) {
                $normalized = self::normalizePath((string)$path);
                self::$loaded[$method][$normalized] = true;
            }
        }
    }

    public static function hasLoadedRoute(string $path, string $method = 'GET'): bool
    {
        $method = strtoupper(trim($method));
        if (!isset(self::$loaded[$method])) {
            return false;
        }

        $normalized = self::normalizePath($path);
        return isset(self::$loaded[$method][$normalized]);
    }

    /**
     * @return array<int,string>
     */
    public static function loadedRoutes(string $method = 'GET'): array
    {
        $method = strtoupper(trim($method));
        $paths = array_keys((array)(self::$loaded[$method] ?? []));
        sort($paths, SORT_STRING);
        return $paths;
    }

    /**
     * @param array<int,string> $candidateUrls
     */
    public static function firstLoadedRoute(array $candidateUrls, string $method = 'GET'): ?string
    {
        foreach ($candidateUrls as $url) {
            $candidate = trim((string)$url);
            if ($candidate === '' || !str_starts_with($candidate, '/')) {
                continue;
            }

            if (self::hasLoadedRoute($candidate, $method)) {
                return self::normalizePath($candidate);
            }
        }

        return null;
    }

    /**
     * @param array<int,string> $fallbackUrls
     */
    public static function resolveNavigableUrl(string $primaryUrl, array $fallbackUrls = [], string $method = 'GET'): ?string
    {
        $primaryUrl = trim($primaryUrl);
        if ($primaryUrl !== '' && !str_starts_with($primaryUrl, '/')) {
            return $primaryUrl;
        }

        $candidates = [];
        if ($primaryUrl !== '') {
            $candidates[] = $primaryUrl;
        }
        foreach ($fallbackUrls as $fallback) {
            $candidate = trim((string)$fallback);
            if ($candidate !== '') {
                $candidates[] = $candidate;
            }
        }

        return self::firstLoadedRoute($candidates, $method);
    }

    /**
     * @return array<string,mixed>
     */
    public static function diagnostics(): array
    {
        $loadedGet = self::loadedRoutes('GET');
        $loadedPost = self::loadedRoutes('POST');

        $appStatuses = self::appStatusMap();
        $declared = self::declaredAppRoutes($appStatuses);
        $linked = self::linkedNavigationRoutes($appStatuses);
        $routeContracts = self::registeredRouteContracts($appStatuses);

        $summary = [
            'loaded_get' => count($loadedGet),
            'loaded_post' => count($loadedPost),
            'declared_total' => count($declared),
            'declared_loaded' => 0,
            'declared_post_only' => 0,
            'declared_missing' => 0,
            'declared_disabled' => 0,
            'linked_total' => count($linked),
            'linked_loaded' => 0,
            'linked_broken' => 0,
            'linked_fallback' => 0,
            'linked_disabled' => 0,
            'contract_route_total' => count($routeContracts),
            'contract_route_loaded' => 0,
            'contract_route_alias' => 0,
            'contract_route_compatibility' => 0,
            'contract_route_deprecated' => 0,
        ];

        foreach ($declared as $row) {
            $status = (string)($row['status'] ?? 'declared_missing');
            if ($status === 'loaded' || $status === 'loaded_post_only') {
                $summary['declared_loaded']++;
                if ($status === 'loaded_post_only') {
                    $summary['declared_post_only']++;
                }
            } elseif ($status === 'disabled_by_app_status') {
                $summary['declared_disabled']++;
            } else {
                $summary['declared_missing']++;
            }
        }

        foreach ($linked as $row) {
            $status = (string)($row['status'] ?? 'broken_missing');
            if ($status === 'loaded') {
                $summary['linked_loaded']++;
            } elseif ($status === 'legacy_fallback') {
                $summary['linked_fallback']++;
            } elseif ($status === 'disabled_by_app_status') {
                $summary['linked_disabled']++;
            } else {
                $summary['linked_broken']++;
            }
        }

        foreach ($routeContracts as $row) {
            if ((bool)($row['loaded_get'] ?? false) || (bool)($row['loaded_post'] ?? false)) {
                $summary['contract_route_loaded']++;
            }

            if ((string)($row['kind'] ?? 'canonical') === 'alias') {
                $summary['contract_route_alias']++;
            }

            if ((bool)($row['compatibility'] ?? false)) {
                $summary['contract_route_compatibility']++;
            }

            if ((bool)($row['deprecated'] ?? false)) {
                $summary['contract_route_deprecated']++;
            }
        }

        return [
            'summary' => $summary,
            'loaded_routes' => [
                'GET' => $loadedGet,
                'POST' => $loadedPost,
            ],
            'declared_routes' => $declared,
            'linked_routes' => $linked,
            'route_contracts' => $routeContracts,
        ];
    }

    private static function normalizePath(string $path): string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $normalized = '/' . ltrim((string)($parsed ?: '/'), '/');
        return rtrim($normalized, '/') ?: '/';
    }

    /**
     * @return array<string,string>
     */
    private static function appStatusMap(): array
    {
        if (!self::tableExists('core_apps')) {
            return [];
        }

        try {
            $rows = DB::fetchAll('SELECT app_key, status FROM core_apps');
        } catch (\Throwable $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
            if ($appKey === '') {
                continue;
            }
            $map[$appKey] = strtolower(trim((string)($row['status'] ?? '')));
        }

        return $map;
    }

    /**
     * @param array<string,string> $appStatuses
     * @return array<int,array<string,mixed>>
     */
    private static function declaredAppRoutes(array $appStatuses): array
    {
        if (!self::tableExists('core_apps')) {
            return [];
        }

        try {
            $rows = DB::fetchAll('SELECT app_key, status, manifest_json FROM core_apps ORDER BY app_key ASC');
        } catch (\Throwable $e) {
            return [];
        }

        $declared = [];
        foreach ($rows as $row) {
            $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
            if ($appKey === '') {
                continue;
            }

            $status = strtolower(trim((string)($row['status'] ?? '')));
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            if (!is_array($manifest)) {
                continue;
            }

            foreach ((array)($manifest['routes'] ?? []) as $route) {
                if (!is_array($route)) {
                    continue;
                }

                $path = trim((string)($route['path'] ?? ''));
                if ($path === '' || !str_starts_with($path, '/')) {
                    continue;
                }

                $normalized = self::normalizePath($path);
                $loadedGet = self::hasLoadedRoute($normalized, 'GET');
                $loadedPost = self::hasLoadedRoute($normalized, 'POST');

                $declaredStatus = 'declared_missing';
                if ($loadedGet) {
                    $declaredStatus = 'loaded';
                } elseif ($loadedPost) {
                    $declaredStatus = 'loaded_post_only';
                } elseif ($status !== 'enabled') {
                    $declaredStatus = 'disabled_by_app_status';
                }

                $declared[] = [
                    'app_key' => $appKey,
                    'app_status' => $status,
                    'path' => $normalized,
                    'status' => $declaredStatus,
                    'compat_redirect' => trim((string)($route['compat_redirect'] ?? '')),
                ];
            }
        }

        usort($declared, static function (array $a, array $b): int {
            $left = (string)($a['app_key'] ?? '') . '|' . (string)($a['path'] ?? '');
            $right = (string)($b['app_key'] ?? '') . '|' . (string)($b['path'] ?? '');
            return strcmp($left, $right);
        });

        return $declared;
    }

    /**
     * @param array<string,string> $appStatuses
     * @return array<int,array<string,mixed>>
     */
    private static function linkedNavigationRoutes(array $appStatuses): array
    {
        $linked = [];
        $files = self::uniqueExistingFiles(array_merge(
            glob(APP_ROOT . '/apps/*/navigation.php') ?: [],
            glob(APP_ROOT . '/apps/*/modules/*/navigation.php') ?: [],
            glob(APP_ROOT . '/plugins/*/navigation.php') ?: []
        ));

        foreach ($files as $file) {
            if (!is_string($file) || !is_file($file)) {
                continue;
            }

            $payload = require $file;
            if (!is_array($payload)) {
                continue;
            }

            $ownerType = str_contains($file, '/apps/') ? 'app' : 'plugin';
            $ownerKey = strtolower(trim((string)basename((string)dirname($file))));
            $appStatus = $ownerType === 'app' ? (string)($appStatuses[$ownerKey] ?? 'unknown') : 'n/a';

            foreach ((array)($payload['items'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $url = trim((string)($item['url'] ?? ''));
                if ($url === '' || !str_starts_with($url, '/')) {
                    continue;
                }

                $normalized = self::normalizePath($url);
                $fallbacks = [];
                foreach ((array)($item['runtime_fallback_urls'] ?? []) as $fallbackUrl) {
                    $fallback = trim((string)$fallbackUrl);
                    if ($fallback !== '' && str_starts_with($fallback, '/')) {
                        $fallbacks[] = self::normalizePath($fallback);
                    }
                }

                $loaded = self::hasLoadedRoute($normalized, 'GET');
                $fallbackLoaded = !$loaded ? self::firstLoadedRoute($fallbacks, 'GET') : null;

                $status = 'broken_missing';
                if ($loaded) {
                    $status = 'loaded';
                } elseif ($fallbackLoaded !== null) {
                    $status = 'legacy_fallback';
                } elseif ($ownerType === 'app' && $appStatus !== 'enabled') {
                    $status = 'disabled_by_app_status';
                }

                $linked[] = [
                    'owner_type' => $ownerType,
                    'owner_key' => $ownerKey,
                    'owner_status' => $appStatus,
                    'source_key' => (string)($item['source_key'] ?? ''),
                    'key' => (string)($item['key'] ?? ''),
                    'label' => (string)($item['label'] ?? ''),
                    'url' => $normalized,
                    'runtime_url' => $fallbackLoaded !== null ? $fallbackLoaded : $normalized,
                    'fallback_urls' => $fallbacks,
                    'status' => $status,
                    'visible_if' => (string)($item['visible_if'] ?? 'always'),
                ];
            }
        }

        usort($linked, static function (array $a, array $b): int {
            $left = (string)($a['owner_key'] ?? '') . '|' . (string)($a['source_key'] ?? '') . '|' . (string)($a['url'] ?? '');
            $right = (string)($b['owner_key'] ?? '') . '|' . (string)($b['source_key'] ?? '') . '|' . (string)($b['url'] ?? '');
            return strcmp($left, $right);
        });

        return $linked;
    }

    /**
     * @param array<int,string> $files
     * @return array<int,string>
     */
    private static function uniqueExistingFiles(array $files): array
    {
        $unique = [];
        foreach ($files as $file) {
            $path = trim((string)$file);
            if ($path === '' || !is_file($path)) {
                continue;
            }

            $resolved = realpath($path);
            $key = $resolved !== false ? $resolved : $path;
            $unique[$key] = $path;
        }

        $paths = array_values($unique);
        sort($paths, SORT_STRING);
        return $paths;
    }

    /**
     * @param array<string,string> $appStatuses
     * @return array<int,array<string,mixed>>
     */
    private static function registeredRouteContracts(array $appStatuses): array
    {
        if (!self::tableExists('core_app_hooks')) {
            return [];
        }

        try {
            $rows = DB::fetchAll(
                "SELECT h.app_key, h.hook_key, h.payload_json, h.is_enabled
                 FROM core_app_hooks h
                 INNER JOIN core_apps a ON a.app_key = h.app_key
                 WHERE h.hook_type='route'
                 ORDER BY h.app_key ASC, h.hook_key ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
            if ($appKey === '') {
                continue;
            }

            $appStatus = (string)($appStatuses[$appKey] ?? 'unknown');
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            $payload = is_array($payload) ? $payload : [];

            $path = self::normalizePath((string)($payload['path'] ?? $row['hook_key'] ?? ''));
            if ($path === '') {
                continue;
            }

            $kind = strtolower(trim((string)($payload['kind'] ?? ((string)($payload['compat_redirect'] ?? '') !== '' ? 'alias' : 'canonical'))));
            if (!in_array($kind, ['canonical', 'alias'], true)) {
                $kind = 'canonical';
            }

            $canonicalTarget = self::normalizePath((string)($payload['canonical_target'] ?? (string)($payload['compat_redirect'] ?? '')));

            $out[] = [
                'app_key' => $appKey,
                'app_status' => $appStatus,
                'path' => $path,
                'feature_key' => trim((string)($payload['feature_key'] ?? '')),
                'owner_app' => trim((string)($payload['owner_app'] ?? $appKey)),
                'kind' => $kind,
                'canonical_target' => $canonicalTarget,
                'compatibility' => (bool)($payload['compatibility'] ?? ((string)($payload['compat_redirect'] ?? '') !== '')),
                'deprecated' => (bool)($payload['deprecated'] ?? false),
                'nav_visible' => (bool)($payload['nav_visible'] ?? false),
                'search_visible' => (bool)($payload['search_visible'] ?? false),
                'lifecycle_bound' => array_key_exists('lifecycle_bound', $payload) ? (bool)$payload['lifecycle_bound'] : true,
                'role_visibility' => is_array($payload['role_visibility'] ?? null) ? array_values(array_map('strval', (array)$payload['role_visibility'])) : [],
                'hook_enabled' => (int)($row['is_enabled'] ?? 0) === 1,
                'loaded_get' => self::hasLoadedRoute($path, 'GET'),
                'loaded_post' => self::hasLoadedRoute($path, 'POST'),
            ];
        }

        return $out;
    }

    private static function tableExists(string $table): bool
    {
        try {
            $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
            if ($safe === '') {
                return false;
            }
            $row = DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$safe]
            );
            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
