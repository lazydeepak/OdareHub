<?php
declare(strict_types=1);

namespace Apps\Platform\Services\Search;

use App\Core\AclPolicy;
use App\Core\DB;
use App\Core\RouteRuntimeAuthority;
use App\Core\SidebarBuilder;
use Platform\Search\SearchProviderContext;
use Platform\Search\SearchProviderInterface;

/**
 * Platform-owned discovery for authorized UI, navigation, and runtime artifacts.
 *
 * Owner navigation, hooks, dashboard descriptors, and runtime route authority
 * remain the source of truth; this provider only adapts them to search results.
 */
final class PlatformArtifactSearchProvider implements SearchProviderInterface
{
    private const DEFAULT_RESULT_LIMIT = 40;

    /** @return array<string,string> */
    public function groupLabels(): array
    {
        return [
            'pages' => 'Pages',
            'menu_items' => 'Menu Items',
            'tiles' => 'Tiles',
            'charts' => 'Charts',
            'form_endpoints' => 'Form Endpoints',
            'permissions' => 'Permissions',
            'forms' => 'Forms',
            'actions' => 'Actions',
        ];
    }

    public function groupPriorities(): array
    {
        return [
            'default' => [
                'pages' => 60,
                'menu_items' => 70,
                'tiles' => 80,
                'charts' => 90,
                'form_endpoints' => 100,
                'permissions' => 120,
                'forms' => 130,
                'actions' => 140,
            ],
            'log' => [
                'pages' => 1,
                'menu_items' => 2,
                'actions' => 3,
                'tiles' => 4,
                'charts' => 5,
                'forms' => 6,
            ],
        ];
    }

    public function search(string $query, SearchProviderContext $context): array
    {
        $pages = self::searchPages($query, $context);
        $menuItems = self::searchMenuItems($query, $context);
        $tiles = self::mergeSearchItems(
            self::searchTiles($query, $context),
            self::searchDynamicTiles($query, $context)
        );
        $charts = self::searchCharts($query, $context);
        $formEndpoints = self::searchFormEndpoints($query, $context);
        $permissions = self::searchPermissions($query, $context);
        [$forms, $actions] = self::searchFormsAndActions($query, $context);

        return [
            'pages' => $pages,
            'menu_items' => $menuItems,
            'tiles' => $tiles,
            'charts' => $charts,
            'form_endpoints' => $formEndpoints,
            'permissions' => $permissions,
            'forms' => $forms,
            'actions' => $actions,
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, status?: string, url: string, meta?: string}>
     */
    private static function searchMenuItems(string $query, SearchProviderContext $context): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT menu_key, label, url, parent_key
                 FROM menus
                 WHERE COALESCE(NULLIF(TRIM(url), ''), '') <> ''
                 ORDER BY COALESCE(parent_key, ''), display_order ASC, label ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }

        $results = [];
        $seen = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $url = trim((string)($row['url'] ?? ''));
            if ($url === '' || !str_starts_with($url, '/')) {
                continue;
            }

            $path = self::pathFromUrl($url);
            if ($path !== '' && !$context->pathAllowed($path)) {
                continue;
            }

            $label = trim((string)($row['label'] ?? ''));
            $menuKey = trim((string)($row['menu_key'] ?? ''));
            $parent = trim((string)($row['parent_key'] ?? ''));
            if (!$context->matches($query, [$label, $menuKey, $parent, $url, 'menu', 'menu item', 'navigation'])) {
                continue;
            }

            $dedupe = strtolower($menuKey !== '' ? $menuKey : $url);
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $results[] = [
                'id' => 'menu:' . md5($dedupe),
                'label' => $label !== '' ? $label : $url,
                'status' => 'available',
                'url' => $url,
                'meta' => trim('Menu item' . ($parent !== '' ? ' • parent ' . $parent : '') . ($menuKey !== '' ? ' • key ' . $menuKey : '')),
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }

    /**
     * @return array<int, array{id: string, label: string, status?: string, url: string, meta?: string}>
     */
    private static function searchTiles(string $query, SearchProviderContext $context): array
    {
        $widgetsFile = APP_ROOT . '/app/Dashboard/widgets.php';
        if (!is_file($widgetsFile)) {
            return [];
        }

        $payload = include $widgetsFile;
        if (!is_array($payload)) {
            return [];
        }

        $widgetRows = [];
        foreach ((array)($payload['core_widgets'] ?? []) as $row) {
            if (is_array($row)) {
                $widgetRows[] = $row;
            }
        }
        foreach ((array)($payload['extension_placeholders'] ?? []) as $row) {
            if (is_array($row)) {
                $widgetRows[] = $row;
            }
        }

        $results = [];
        $seen = [];
        foreach ($widgetRows as $row) {
            $status = strtolower(trim((string)($row['status'] ?? 'active')));
            if ($status !== '' && !in_array($status, ['active', 'planned'], true)) {
                continue;
            }

            if (!self::isVisibilityRuleAllowed((string)($row['visible_if'] ?? 'always'), $context)) {
                continue;
            }

            $url = trim((string)($row['url'] ?? ''));
            if ($url !== '') {
                $path = self::pathFromUrl($url);
                if ($path !== '' && !$context->pathAllowed($path)) {
                    continue;
                }
            }

            $label = self::resolveLabel((string)($row['label_key'] ?? ''), (string)($row['key'] ?? 'Tile'));
            $description = self::resolveLabel((string)($row['description_key'] ?? ''), '');
            $module = trim((string)($row['module_key'] ?? ''));
            $zone = trim((string)($row['zone'] ?? ''));
            $key = trim((string)($row['key'] ?? ''));

            if (!$context->matches($query, [$label, $description, $module, $zone, $key, $url, 'tile', 'dashboard tile', 'widget'])) {
                continue;
            }

            $dedupe = strtolower($key !== '' ? $key : ($label . '|' . $url));
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;

            $results[] = [
                'id' => 'tile:' . md5($dedupe),
                'label' => $label,
                'status' => $status === '' ? 'active' : $status,
                'url' => $url !== '' ? $url : '/ops/dashboard',
                'meta' => trim('Dashboard tile' . ($zone !== '' ? ' • ' . $zone : '') . ($module !== '' ? ' • ' . $module : '') . ($description !== '' ? ' • ' . $description : '')),
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }

    /**
     * @return array<int, array{id: string, label: string, status?: string, url: string, meta?: string}>
     */
    private static function searchCharts(string $query, SearchProviderContext $context): array
    {
        $ctx = (array)($context->scopeContext['user_context'] ?? []);
        $tokens = array_values(array_unique(array_map('strval', (array)($ctx['chart_access'] ?? []))));
        if ($tokens === []) {
            return [];
        }

        $catalog = self::chartCatalog();
        $results = [];
        foreach ($tokens as $token) {
            $key = strtolower(trim($token));
            if ($key === '') {
                continue;
            }

            $meta = (array)($catalog[$key] ?? [
                'label' => ucwords(str_replace('_', ' ', $key)),
                'url' => '/ops/dashboard',
                'meta' => 'Operational chart',
                '_search_precedence' => 0,
            ]);

            $label = trim((string)($meta['label'] ?? $key));
            $url = trim((string)($meta['url'] ?? '/ops/dashboard'));
            $extra = trim((string)($meta['meta'] ?? 'Operational chart'));

            $path = self::pathFromUrl($url);
            if ($path !== '' && !$context->pathAllowed($path)) {
                continue;
            }

            if (!$context->matches($query, [$label, $key, $extra, $url, 'chart', 'dashboard chart', 'analytics'])) {
                continue;
            }

            $results[] = [
                'id' => 'chart:' . md5($key),
                'label' => $label,
                'status' => 'available',
                'url' => $url,
                'meta' => trim('Chart • ' . $extra . ' • token ' . $key),
                '_search_precedence' => (int)($meta['_search_precedence'] ?? 0),
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }

    /**
     * @return array<string, array{label:string,url:string,meta:string}>
     */
    private static function chartCatalog(): array
    {
        $fallback = [
            'ops_pressure' => [
                'label' => 'Ops Pressure',
                'url' => '/ops/platform-operations',
                'meta' => 'Governance and queue pressure chart',
                '_search_precedence' => 100,
            ],
            'schema_sync' => [
                'label' => 'Schema Sync',
                'url' => '/admin/base',
                'meta' => 'Platform schema sync health chart',
                '_search_precedence' => 100,
            ],
            'security_alerts' => [
                'label' => 'Security Alerts',
                'url' => '/admin/architecture-health',
                'meta' => 'Security posture alert chart',
                '_search_precedence' => 100,
            ],
        ];

        $fromApps = self::appChartCatalog();
        return $fromApps + $fallback;
    }

    /**
     * @return array<string, array{label:string,url:string,meta:string}>
     */
    private static function appChartCatalog(): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT h.hook_key, h.payload_json
                 FROM core_app_hooks h
                 INNER JOIN core_apps a ON a.app_key = h.app_key
                 WHERE a.status='enabled' AND h.is_enabled=1 AND h.hook_type='chart'
                 ORDER BY h.id ASC"
            );
        } catch (\Throwable $e) {
            return [];
        }

        $catalog = [];
        foreach ($rows as $row) {
            $payload = json_decode((string)($row['payload_json'] ?? '{}'), true);
            if (!is_array($payload)) {
                continue;
            }

            $key = strtolower(trim((string)($payload['key'] ?? $row['hook_key'] ?? '')));
            $label = trim((string)($payload['label'] ?? $payload['title'] ?? ''));
            $url = trim((string)($payload['url'] ?? ''));
            $meta = trim((string)($payload['meta'] ?? $payload['detail'] ?? 'Operational chart'));

            if ($key === '' || $label === '' || $url === '') {
                continue;
            }

            $catalog[$key] = [
                'label' => $label,
                'url' => $url,
                'meta' => $meta,
                '_search_precedence' => 200,
            ];
        }

        return $catalog;
    }

    /**
     * @param array<int, array{id:string,label:string,status?:string,url:string,meta?:string}> $first
     * @param array<int, array{id:string,label:string,status?:string,url:string,meta?:string}> $second
     * @return array<int, array{id:string,label:string,status?:string,url:string,meta?:string}>
     */
    private static function mergeSearchItems(array $first, array $second): array
    {
        $out = [];
        $seen = [];
        foreach ([$first, $second] as $list) {
            foreach ($list as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $key = strtolower(trim((string)($item['id'] ?? '')));
                if ($key === '') {
                    $key = strtolower(trim((string)($item['label'] ?? '') . '|' . (string)($item['url'] ?? '')));
                }
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $item;
            }
        }

        return array_slice($out, 0, self::DEFAULT_RESULT_LIMIT);
    }

    /**
     * @return array<int, array{id:string,label:string,status?:string,url:string,meta?:string}>
     */
    private static function searchDynamicTiles(string $query, SearchProviderContext $context): array
    {
        $results = [];
        foreach (self::dashboardWidgetFiles() as $file) {
            try {
                $factory = include $file;
            } catch (\Throwable $e) {
                continue;
            }

            if (!is_callable($factory)) {
                continue;
            }

            try {
                $widget = $factory();
            } catch (\Throwable $e) {
                continue;
            }

            if (!is_array($widget)) {
                continue;
            }

            if (!self::isVisibilityRuleAllowed((string)($widget['visible_if'] ?? 'always'), $context)) {
                continue;
            }

            $url = trim((string)($widget['url'] ?? '/ops/dashboard'));
            $path = self::pathFromUrl($url);
            if ($path !== '' && !$context->pathAllowed($path)) {
                continue;
            }

            $label = trim((string)($widget['title'] ?? (string)($widget['key'] ?? 'Widget')));
            $detail = trim((string)($widget['detail'] ?? ''));
            $extra = trim((string)($widget['extra'] ?? ''));
            $key = trim((string)($widget['key'] ?? ''));
            if (!$context->matches($query, [$label, $detail, $extra, $key, $url, 'tile', 'widget', 'dashboard'])) {
                continue;
            }

            $results[] = [
                'id' => 'tile:dynamic:' . md5($file . '|' . $key),
                'label' => $label,
                'status' => 'available',
                'url' => $url,
                'meta' => trim('Dashboard widget' . ($detail !== '' ? ' • ' . $detail : '') . ($extra !== '' ? ' • ' . $extra : '')),
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }

    /**
     * @return array<int,string>
     */
    private static function dashboardWidgetFiles(): array
    {
        $moduleFiles = glob(APP_ROOT . '/apps/*/modules/*/dashboard.php') ?: [];
        $pluginFiles = glob(APP_ROOT . '/plugins/*/dashboard.php') ?: [];
        return self::uniqueExistingFiles(array_merge($moduleFiles, $pluginFiles));
    }

    /**
     * @return array<int, array{id:string,label:string,status?:string,url:string,meta?:string}>
     */
    private static function searchFormEndpoints(string $query, SearchProviderContext $context): array
    {
        $results = [];
        foreach (self::routeEndpointsFromRuntime() as $route) {
            $method = strtoupper((string)($route['method'] ?? 'GET'));
            $path = trim((string)($route['path'] ?? ''));
            if ($method !== 'POST' || $path === '') {
                continue;
            }

            if (!$context->pathAllowed($path)) {
                continue;
            }

            $label = self::humanizePath($path);
            if (!$context->matches($query, [$label, $path, 'post endpoint', 'form endpoint', 'submit', 'action'])) {
                continue;
            }

            $results[] = [
                'id' => 'form-endpoint:' . md5($method . '|' . $path),
                'label' => $label,
                'status' => 'available',
                'url' => $path,
                'meta' => 'POST form endpoint',
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }


    /**
     * Return the final runtime route catalog seeded after all enabled owners
     * register their routes.
     *
     * @return array<int,array{method:string,path:string}>
     */
    private static function routeEndpointsFromRuntime(): array
    {
        $routes = [];
        foreach (['GET', 'POST'] as $method) {
            foreach (RouteRuntimeAuthority::loadedRoutes($method) as $path) {
                $routes[] = ['method' => $method, 'path' => $path];
            }
        }
        return $routes;
    }

    /**
     * @param array<int,string> $files
     * @param null|callable(string):bool $predicate
     * @return array<int,string>
     */
    private static function uniqueExistingFiles(array $files, ?callable $predicate = null): array
    {
        $unique = [];
        foreach ($files as $file) {
            $path = trim((string)$file);
            if ($path === '' || !is_file($path)) {
                continue;
            }
            if ($predicate !== null && !$predicate($path)) {
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
     * @return array<int, array{id:string,label:string,status?:string,url:string,meta?:string}>
     */
    private static function searchPermissions(string $query, SearchProviderContext $context): array
    {
        $accessUrl = '/ops/access-control';
        if (!$context->pathAllowed($accessUrl)) {
            return [];
        }

        $registry = method_exists(AclPolicy::class, 'permissionRegistry') ? AclPolicy::permissionRegistry() : [];
        if (!is_array($registry) || $registry === []) {
            return [];
        }

        $results = [];
        foreach ($registry as $perm => $description) {
            $permKey = trim((string)$perm);
            if ($permKey === '') {
                continue;
            }

            if (method_exists(AclPolicy::class, 'can') && !AclPolicy::can($permKey, $context->user)) {
                continue;
            }

            $desc = trim((string)$description);
            if (!$context->matches($query, [$permKey, $desc, 'permission acl access control'])) {
                continue;
            }

            $results[] = [
                'id' => 'perm:' . md5($permKey),
                'label' => $permKey,
                'status' => 'granted',
                'url' => $accessUrl,
                'meta' => $desc,
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }

    private static function humanizePath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '') {
            return 'Endpoint';
        }

        $base = parse_url($trimmed, PHP_URL_PATH);
        if (!is_string($base) || $base === '') {
            $base = $trimmed;
        }

        $parts = array_values(array_filter(explode('/', trim($base, '/')), static fn(string $s): bool => $s !== ''));
        if ($parts === []) {
            return 'Root Endpoint';
        }

        $tail = str_replace(['-', '_'], ' ', end($parts));
        return ucwords($tail) . ' Endpoint';
    }

    /**
     * @return array<int, array{id: string, label: string, status?: string, url: string, meta?: string}>
     */
    private static function searchPages(string $query, SearchProviderContext $context): array
    {
        $results = [];
        $seen = [];

        foreach (self::visibleSidebarItemsForUser($context) as $item) {
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $path = self::pathFromUrl($url);
            if ($path !== '' && !$context->pathAllowed($path)) {
                continue;
            }

            $label = trim((string)($item['label'] ?? $url));
            $meta = trim((string)($item['meta'] ?? ''));
            if (!$context->matches($query, [$label, $meta, $url])) {
                continue;
            }

            $dedupeKey = strtolower($url);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $results[] = [
                'id' => 'page:' . md5($url),
                'label' => $label,
                'status' => 'available',
                'url' => $url,
                'meta' => $meta,
            ];
        }

        $modulesFile = APP_ROOT . '/app/Navigation/modules.php';
        if (is_file($modulesFile)) {
            $modules = include $modulesFile;
            if (is_array($modules)) {
                foreach ($modules as $module) {
                    if (!is_array($module)) {
                        continue;
                    }

                    $url = trim((string)($module['entry_url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }
                    if ((bool)($module['is_placeholder'] ?? false)) {
                        continue;
                    }

                    $path = self::pathFromUrl($url);
                    if ($path !== '' && !$context->pathAllowed($path)) {
                        continue;
                    }

                    $label = self::resolveLabel((string)($module['label_key'] ?? ''), (string)($module['key'] ?? 'Module'));
                    $meta = trim((string)($module['description'] ?? ''));
                    if (!$context->matches($query, [$label, $meta, $url, (string)($module['key'] ?? '')])) {
                        continue;
                    }

                    $dedupeKey = strtolower($url);
                    if (isset($seen[$dedupeKey])) {
                        continue;
                    }
                    $seen[$dedupeKey] = true;

                    $results[] = [
                        'id' => 'page:module:' . md5($url . '|' . (string)($module['key'] ?? '')),
                        'label' => $label,
                        'status' => 'available',
                        'url' => $url,
                        'meta' => $meta !== '' ? $meta : 'Module entry page',
                    ];
                }
            }
        }

        foreach (self::pageAliasCatalog() as $alias) {
            $url = trim((string)($alias['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $path = self::pathFromUrl($url);
            if ($path !== '' && !$context->pathAllowed($path)) {
                continue;
            }

            $label = trim((string)($alias['label'] ?? $url));
            $meta = trim((string)($alias['meta'] ?? ''));
            $keywords = trim((string)($alias['keywords'] ?? ''));
            if (!$context->matches($query, [$label, $meta, $keywords, $url])) {
                continue;
            }

            $dedupeKey = strtolower($url);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $results[] = [
                'id' => 'page:alias:' . md5($url),
                'label' => $label,
                'status' => 'available',
                'url' => $url,
                'meta' => $meta,
            ];
        }

        foreach (self::routeInventoryPages($context) as $alias) {
            $url = trim((string)($alias['url'] ?? ''));
            $label = trim((string)($alias['label'] ?? $url));
            $meta = trim((string)($alias['meta'] ?? 'Route inventory page'));
            if ($url === '' || !$context->matches($query, [$label, $meta, $url])) {
                continue;
            }

            $dedupeKey = strtolower($url);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;

            $results[] = [
                'id' => 'page:route:' . md5($url),
                'label' => $label,
                'status' => 'available',
                'url' => $url,
                'meta' => $meta,
            ];
        }

        return array_slice($results, 0, self::DEFAULT_RESULT_LIMIT);
    }

    /**
     * @return array<int,array{label:string,url:string,meta:string}>
     */
    private static function routeInventoryPages(SearchProviderContext $context): array
    {
        $labelOverrides = [
            '/ops/navigation-tree' => 'Navigation Tree',
            '/ops/access-control' => 'Access Control Board',
            '/ops/audit-log' => 'Audit Explorer',
            '/admin/routes' => 'Logs / Diagnostics',
        ];

        $out = [];
        foreach (self::routeEndpointsFromRuntime() as $route) {
            $method = strtoupper((string)($route['method'] ?? 'GET'));
            $path = trim((string)($route['path'] ?? ''));
            if ($method !== 'GET' || $path === '' || str_starts_with($path, '/api/')) {
                continue;
            }
            if (!(str_starts_with($path, '/ops/') || str_starts_with($path, '/admin/'))) {
                continue;
            }
            if (!$context->pathAllowed($path)) {
                continue;
            }

            $label = $labelOverrides[$path] ?? self::humanizePath($path);
            $out[] = [
                'label' => $label,
                'url' => $path,
                'meta' => 'Route inventory page',
            ];
        }

        return $out;
    }

    /**
     * @return array<int,array{label:string,url:string,meta:string,keywords:string}>
     */
    private static function pageAliasCatalog(): array
    {
        return [
            [
                'label' => 'Navigation Tree',
                'url' => '/ops/navigation-tree',
                'meta' => 'System navigation and route integrity explorer',
                'keywords' => 'system navigation tree links pages forms dashboards charts',
            ],
            [
                'label' => 'Access Control Board',
                'url' => '/ops/access-control',
                'meta' => 'Role and permission governance board',
                'keywords' => 'acl permissions roles access control board',
            ],
            [
                'label' => 'Audit Explorer',
                'url' => '/ops/audit-log',
                'meta' => 'Audit log review and operational investigation',
                'keywords' => 'audit explorer audit log logs log files diagnostics trace history',
            ],
            [
                'label' => 'Logs / Diagnostics',
                'url' => '/admin/routes',
                'meta' => 'Route diagnostics and admin operational logs',
                'keywords' => 'logs diagnostics log files route diagnostics admin logs',
            ],
        ];
    }

    /**
     * @return array{0:array<int, array{id: string, label: string, status?: string, url: string, meta?: string}>,1:array<int, array{id: string, label: string, status?: string, url: string, meta?: string}>}
     */
    private static function searchFormsAndActions(string $query, SearchProviderContext $context): array
    {
        $actionsFile = APP_ROOT . '/app/Actions/actions.php';
        if (!is_file($actionsFile)) {
            return [[], []];
        }

        $rows = include $actionsFile;
        if (!is_array($rows)) {
            return [[], []];
        }

        $forms = [];
        $actions = [];
        $seenForms = [];
        $seenActions = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $status = strtolower(trim((string)($row['status'] ?? 'active')));
            if ($status !== '' && $status !== 'active') {
                continue;
            }

            $url = trim((string)($row['target_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $path = self::pathFromUrl($url);
            if ($path !== '' && !$context->pathAllowed($path)) {
                continue;
            }

            if (!self::isVisibilityRuleAllowed((string)($row['visible_if'] ?? 'always'), $context)) {
                continue;
            }

            $label = self::resolveLabel((string)($row['label_key'] ?? ''), (string)($row['key'] ?? 'Action'));
            $meta = trim(
                ((string)($row['action_type'] ?? '') !== '' ? (string)($row['action_type'] ?? '') : 'navigate') .
                ((string)($row['module_key'] ?? '') !== '' ? ' • ' . (string)($row['module_key'] ?? '') : '')
            );

            if (!$context->matches($query, [
                $label,
                (string)($row['key'] ?? ''),
                (string)($row['source_signal'] ?? ''),
                (string)($row['target'] ?? ''),
                $url,
                $meta,
            ])) {
                continue;
            }

            $isForm = self::looksLikeFormAction($row, $label, $url);
            if ($isForm) {
                $dedupeKey = strtolower((string)($row['key'] ?? $url));
                if (isset($seenForms[$dedupeKey])) {
                    continue;
                }
                $seenForms[$dedupeKey] = true;
                $forms[] = [
                    'id' => 'form:' . md5((string)($row['key'] ?? $url)),
                    'label' => $label,
                    'status' => $status === '' ? 'active' : $status,
                    'url' => $url,
                    'meta' => $meta !== '' ? $meta : 'Form action',
                ];
                continue;
            }

            $dedupeKey = strtolower((string)($row['key'] ?? $url));
            if (isset($seenActions[$dedupeKey])) {
                continue;
            }
            $seenActions[$dedupeKey] = true;
            $actions[] = [
                'id' => 'action:' . md5((string)($row['key'] ?? $url)),
                'label' => $label,
                'status' => $status === '' ? 'active' : $status,
                'url' => $url,
                'meta' => $meta !== '' ? $meta : 'Action',
            ];
        }

        return [
            array_slice($forms, 0, self::DEFAULT_RESULT_LIMIT),
            array_slice($actions, 0, self::DEFAULT_RESULT_LIMIT),
        ];
    }

    /**
     * @return array<int,array{label:string,url:string,meta:string}>
     */
    private static function visibleSidebarItemsForUser(SearchProviderContext $context): array
    {
        $builder = self::sidebarBuilderClass();
        if ($builder === '') {
            return [];
        }

        $isAdmin = function_exists('base_is_admin_user')
            ? (bool)base_is_admin_user($context->user)
            : strtolower(trim((string)($context->user['role'] ?? ''))) === 'admin';
        $canAccessBase = function_exists('base_can_access_builder')
            ? (bool)base_can_access_builder($context->user)
            : $isAdmin;
        $devToolsEnabled = function_exists('app_dev_tools_enabled') ? (bool)app_dev_tools_enabled() : false;

        try {
            $payload = $builder::build([
                'loggedIn' => true,
                'user' => $context->user,
                'isAdmin' => $isAdmin,
                'canAccessBase' => $canAccessBase,
                'devToolsEnabled' => $devToolsEnabled,
                'currentPath' => '/',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $items = [];
        foreach ((array)($payload['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }
            $sectionLabel = trim((string)($section['label'] ?? ''));

            foreach ((array)($section['groups'] ?? []) as $group) {
                if (!is_array($group)) {
                    continue;
                }
                $groupLabel = trim((string)($group['label'] ?? ''));

                foreach ((array)($group['items'] ?? []) as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $url = trim((string)($item['url'] ?? ''));
                    if ($url === '') {
                        continue;
                    }

                    $label = trim((string)($item['label'] ?? $url));
                    $meta = trim($sectionLabel . ($groupLabel !== '' ? ' • ' . $groupLabel : ''));
                    $keywords = (array)($item['search_keywords'] ?? []);
                    $items[] = [
                        'label' => $label,
                        'url' => $url,
                        'meta' => trim($meta . (!empty($keywords) ? ' • ' . implode(' ', array_map('strval', $keywords)) : '')),
                    ];
                }
            }
        }

        return $items;
    }

    private static function sidebarBuilderClass(): string
    {
        $class = SidebarBuilder::class;
        if (class_exists($class)) {
            return $class;
        }

        $file = APP_ROOT . '/app/Core/SidebarBuilder.php';
        if (is_file($file)) {
            require_once $file;
        }

        return class_exists($class) ? $class : '';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function looksLikeFormAction(array $row, string $label, string $url): bool
    {
        $method = strtolower(trim((string)($row['method'] ?? 'link')));
        $key = strtolower(trim((string)($row['key'] ?? '')));
        $source = strtolower(trim((string)($row['source_signal'] ?? '')));
        $text = strtolower(trim($label . ' ' . $key . ' ' . $source . ' ' . $url));

        if (
            str_contains($text, '/start-draft') ||
            str_contains($text, '/create') ||
            str_contains($text, '/new') ||
            str_contains($text, '/edit') ||
            str_contains($text, 'draft') ||
            str_contains($text, 'form') ||
            str_contains($text, 'create') ||
            str_contains($text, 'new ') ||
            str_contains($text, ' edit')
        ) {
            return true;
        }

        return in_array($method, ['post', 'modal'], true);
    }

    private static function resolveLabel(string $labelKey, string $fallback): string
    {
        $labelKey = trim($labelKey);
        if ($labelKey !== '' && function_exists('t')) {
            $resolved = trim((string)t($labelKey));
            if ($resolved !== '' && $resolved !== $labelKey) {
                return $resolved;
            }
        }

        $fallback = trim($fallback);
        if ($fallback !== '') {
            return $fallback;
        }

        return 'Untitled';
    }

    private static function pathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || trim($path) === '') {
            return '';
        }

        $normalized = '/' . ltrim($path, '/');
        return rtrim($normalized, '/') ?: '/';
    }


    private static function isVisibilityRuleAllowed(string $rule, SearchProviderContext $context): bool
    {
        $rule = trim($rule);
        if ($rule === '' || !method_exists(AclPolicy::class, 'allowsVisibilityRule')) {
            return true;
        }

        try {
            return (bool)AclPolicy::allowsVisibilityRule(
                $rule,
                (array)($context->scopeContext['visibility_context'] ?? [])
            );
        } catch (\Throwable) {
            return true;
        }
    }
}
