<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

use App\Core\RouteRuntimeAuthority;

final class AppStudioRegistryService
{
    /**
     * @param array<string,mixed> $routeMap
     * @return array<string,mixed>
     */
    public static function buildGlobalLibrary(array $routeMap): array
    {
        $appsRoot = self::buildAppsRoot();
        $pluginsRoot = self::buildPluginsRoot();
        $generatedRoot = self::buildGeneratedRoot();
        $routesRoot = self::buildRouteAuthorityRoot($routeMap);

        $tree = array_values(array_filter([
            $appsRoot,
            $pluginsRoot,
            $generatedRoot,
            $routesRoot,
        ], static fn(array $node): bool => !empty($node['children'])));

        $index = [];
        foreach ($tree as $node) {
            self::indexTreeNode($node, $index);
        }

        return [
            'tree' => $tree,
            'index' => $index,
            'counts' => self::countByEntityType($index),
            'route_summary' => is_array($routesRoot['meta']['summary'] ?? null) ? $routesRoot['meta']['summary'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $library
     * @return array<string,mixed>|null
     */
    public static function findNodeById(array $library, string $nodeId): ?array
    {
        $trimmed = trim($nodeId);
        if ($trimmed === '') {
            return null;
        }

        $index = is_array($library['index'] ?? null) ? $library['index'] : [];
        $node = $index[$trimmed] ?? null;
        return is_array($node) ? $node : null;
    }

    /** @return array<string,mixed> */
    private static function buildAppsRoot(): array
    {
        $appsRootPath = APP_ROOT . '/apps';
        $appDirs = self::listSubdirectories($appsRootPath);
        $children = [];

        foreach ($appDirs as $dirName) {
            if (strtolower($dirName) === 'generated') {
                continue;
            }

            $appPath = $appsRootPath . '/' . $dirName;
            $source = 'apps';
            $safeKey = self::safeNodeKey($dirName);
            if ($safeKey === '') {
                continue;
            }

            $moduleNodes = self::buildModuleNodes($appPath, $source, $safeKey);
            $viewNodes = self::collectViewNodes($appPath, $source, $safeKey, 'app');
            $dashboardNodes = self::collectDashboardNodes($appPath, $source, $safeKey, 'app');
            $routeNodes = self::collectRouteNodes($appPath, $source, $safeKey, 'app');
            $navNodes = self::collectNavigationNodes($appPath, $source, $safeKey, 'app');

            $children[] = [
                'id' => 'app:' . $source . ':' . $safeKey,
                'type' => 'app',
                'label' => $dirName,
                'meta' => [
                    'source' => $source,
                    'path' => self::toRelativePath($appPath),
                    'modules' => count($moduleNodes),
                    'views' => count($viewNodes),
                    'dashboards' => count($dashboardNodes),
                    'routes' => count($routeNodes),
                    'navs' => count($navNodes),
                ],
                'children' => self::buildCategoryNodes($source, $safeKey, [
                    'modules' => $moduleNodes,
                    'views' => $viewNodes,
                    'dashboards' => $dashboardNodes,
                    'routes' => $routeNodes,
                    'navs' => $navNodes,
                ]),
            ];
        }

        usort($children, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

        return [
            'id' => 'source:apps',
            'type' => 'source',
            'label' => 'apps',
            'meta' => ['path' => 'apps'],
            'children' => $children,
        ];
    }

    /** @return array<string,mixed> */
    private static function buildPluginsRoot(): array
    {
        $pluginsRootPath = APP_ROOT . '/plugins';
        $pluginDirs = self::listSubdirectories($pluginsRootPath);
        $children = [];

        foreach ($pluginDirs as $dirName) {
            $pluginPath = $pluginsRootPath . '/' . $dirName;
            $source = 'plugins';
            $safeKey = self::safeNodeKey($dirName);
            if ($safeKey === '') {
                continue;
            }

            $moduleNodes = self::buildModuleNodes($pluginPath, $source, $safeKey);
            $viewNodes = self::collectViewNodes($pluginPath, $source, $safeKey, 'plugin');
            $dashboardNodes = self::collectDashboardNodes($pluginPath, $source, $safeKey, 'plugin');
            $routeNodes = self::collectRouteNodes($pluginPath, $source, $safeKey, 'plugin');
            $navNodes = self::collectNavigationNodes($pluginPath, $source, $safeKey, 'plugin');

            $children[] = [
                'id' => 'plugin:' . $source . ':' . $safeKey,
                'type' => 'plugin',
                'label' => $dirName,
                'meta' => [
                    'source' => $source,
                    'path' => self::toRelativePath($pluginPath),
                    'modules' => count($moduleNodes),
                    'views' => count($viewNodes),
                    'dashboards' => count($dashboardNodes),
                    'routes' => count($routeNodes),
                    'navs' => count($navNodes),
                ],
                'children' => self::buildCategoryNodes($source, $safeKey, [
                    'modules' => $moduleNodes,
                    'views' => $viewNodes,
                    'dashboards' => $dashboardNodes,
                    'routes' => $routeNodes,
                    'navs' => $navNodes,
                ]),
            ];
        }

        usort($children, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

        return [
            'id' => 'source:plugins',
            'type' => 'source',
            'label' => 'plugins',
            'meta' => ['path' => 'plugins'],
            'children' => $children,
        ];
    }

    /** @return array<string,mixed> */
    private static function buildGeneratedRoot(): array
    {
        $generatedRootPath = APP_ROOT . '/apps/Generated';
        $appDirs = self::listSubdirectories($generatedRootPath);
        $children = [];

        foreach ($appDirs as $appDirName) {
            if (strtolower($appDirName) === 'tmp') {
                continue;
            }

            $safeAppKey = self::safeNodeKey($appDirName);
            if ($safeAppKey === '') {
                continue;
            }

            $appPath = $generatedRootPath . '/' . $appDirName;
            $moduleDirs = self::listSubdirectories($appPath);
            $moduleNodes = [];
            $allViews = [];
            $allDashboards = [];
            $allRoutes = [];
            $allNavs = [];

            foreach ($moduleDirs as $moduleDirName) {
                $safeModuleKey = self::safeNodeKey($moduleDirName);
                if ($safeModuleKey === '') {
                    continue;
                }
                $modulePath = $appPath . '/' . $moduleDirName;
                $moduleViews = self::collectViewNodes($modulePath, 'generated', $safeAppKey . ':' . $safeModuleKey, 'module');
                $moduleDashboards = self::collectDashboardNodes($modulePath, 'generated', $safeAppKey . ':' . $safeModuleKey, 'module');
                $moduleRoutes = self::collectRouteNodes($modulePath, 'generated', $safeAppKey . ':' . $safeModuleKey, 'module');
                $moduleNavs = self::collectNavigationNodes($modulePath, 'generated', $safeAppKey . ':' . $safeModuleKey, 'module');

                $moduleNodes[] = [
                    'id' => 'module:generated:' . $safeAppKey . ':' . $safeModuleKey,
                    'type' => 'module',
                    'label' => $moduleDirName,
                    'meta' => [
                        'source' => 'generated',
                        'path' => self::toRelativePath($modulePath),
                        'app_key' => $safeAppKey,
                        'views' => count($moduleViews),
                        'dashboards' => count($moduleDashboards),
                        'routes' => count($moduleRoutes),
                        'navs' => count($moduleNavs),
                    ],
                    'children' => self::buildCategoryNodes('generated', $safeAppKey . ':' . $safeModuleKey, [
                        'views' => $moduleViews,
                        'dashboards' => $moduleDashboards,
                        'routes' => $moduleRoutes,
                        'navs' => $moduleNavs,
                    ]),
                ];

                $allViews = array_merge($allViews, $moduleViews);
                $allDashboards = array_merge($allDashboards, $moduleDashboards);
                $allRoutes = array_merge($allRoutes, $moduleRoutes);
                $allNavs = array_merge($allNavs, $moduleNavs);
            }

            usort($moduleNodes, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

            $children[] = [
                'id' => 'app:generated:' . $safeAppKey,
                'type' => 'app',
                'label' => $appDirName,
                'meta' => [
                    'source' => 'generated',
                    'path' => self::toRelativePath($appPath),
                    'modules' => count($moduleNodes),
                    'views' => count($allViews),
                    'dashboards' => count($allDashboards),
                    'routes' => count($allRoutes),
                    'navs' => count($allNavs),
                ],
                'children' => self::buildCategoryNodes('generated', $safeAppKey, [
                    'modules' => $moduleNodes,
                    'views' => $allViews,
                    'dashboards' => $allDashboards,
                    'routes' => $allRoutes,
                    'navs' => $allNavs,
                ]),
            ];
        }

        usort($children, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));

        return [
            'id' => 'source:generated',
            'type' => 'source',
            'label' => 'generated',
            'meta' => ['path' => 'apps/Generated'],
            'children' => $children,
        ];
    }

    /**
     * @param array<string,mixed> $routeMap
     * @return array<string,mixed>
     */
    private static function buildRouteAuthorityRoot(array $routeMap): array
    {
        RouteRuntimeAuthority::seed($routeMap);
        $diagnostics = RouteRuntimeAuthority::diagnostics();
        $declaredRoutes = is_array($diagnostics['declared_routes'] ?? null)
            ? array_values(array_filter($diagnostics['declared_routes'], 'is_array'))
            : [];
        $linkedRoutes = is_array($diagnostics['linked_routes'] ?? null)
            ? array_values(array_filter($diagnostics['linked_routes'], 'is_array'))
            : [];

        $declaredNodes = [];
        foreach ($declaredRoutes as $index => $row) {
            $path = trim((string)($row['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $status = trim((string)($row['status'] ?? 'unknown'));
            $appKey = trim((string)($row['app_key'] ?? ''));
            $declaredNodes[] = [
                'id' => 'route:declared:' . self::safeNodeKey($path) . ':' . (string)$index,
                'type' => 'route',
                'label' => $path,
                'meta' => [
                    'source' => 'route_authority_declared',
                    'owner' => $appKey,
                    'status' => $status,
                    'app_status' => (string)($row['app_status'] ?? ''),
                ],
                'children' => [],
            ];
        }

        $linkedNodes = [];
        foreach ($linkedRoutes as $index => $row) {
            $runtimeUrl = trim((string)($row['runtime_url'] ?? ''));
            $navUrl = trim((string)($row['url'] ?? ''));
            $label = $runtimeUrl !== '' ? $runtimeUrl : $navUrl;
            if ($label === '') {
                continue;
            }
            $linkedNodes[] = [
                'id' => 'route:linked:' . self::safeNodeKey($label) . ':' . (string)$index,
                'type' => 'route',
                'label' => $label,
                'meta' => [
                    'source' => 'route_authority_linked',
                    'owner' => trim((string)($row['owner_type'] ?? '')) . ':' . trim((string)($row['owner_key'] ?? '')),
                    'status' => (string)($row['status'] ?? ''),
                    'nav_url' => $navUrl,
                    'visible_if' => (string)($row['visible_if'] ?? ''),
                ],
                'children' => [],
            ];
        }

        $children = self::buildCategoryNodes('route_authority', 'route_authority', [
            'declared_routes' => $declaredNodes,
            'linked_routes' => $linkedNodes,
        ]);

        return [
            'id' => 'source:route_authority',
            'type' => 'source',
            'label' => 'route_authority',
            'meta' => [
                'summary' => is_array($diagnostics['summary'] ?? null) ? $diagnostics['summary'] : [],
            ],
            'children' => $children,
        ];
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $categories
     * @return array<int,array<string,mixed>>
     */
    private static function buildCategoryNodes(string $source, string $ownerKey, array $categories): array
    {
        $nodes = [];
        foreach ($categories as $category => $children) {
            if ($children === []) {
                continue;
            }
            $nodes[] = [
                'id' => 'category:' . self::safeNodeKey($source) . ':' . self::safeNodeKey($ownerKey) . ':' . self::safeNodeKey((string)$category),
                'type' => 'category',
                'label' => (string)$category,
                'meta' => [
                    'source' => $source,
                    'owner' => $ownerKey,
                    'count' => count($children),
                ],
                'children' => array_values($children),
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));
        return $nodes;
    }

    /** @return array<int,array<string,mixed>> */
    private static function buildModuleNodes(string $ownerPath, string $source, string $ownerKey): array
    {
        $modulesPath = $ownerPath . '/modules';
        if (!is_dir($modulesPath)) {
            return [];
        }

        $moduleDirs = self::listSubdirectories($modulesPath);
        $nodes = [];
        foreach ($moduleDirs as $moduleDirName) {
            $safeModuleKey = self::safeNodeKey($moduleDirName);
            if ($safeModuleKey === '') {
                continue;
            }

            $modulePath = $modulesPath . '/' . $moduleDirName;
            $moduleViews = self::collectViewNodes($modulePath, $source, $ownerKey . ':' . $safeModuleKey, 'module');
            $moduleDashboards = self::collectDashboardNodes($modulePath, $source, $ownerKey . ':' . $safeModuleKey, 'module');
            $moduleRoutes = self::collectRouteNodes($modulePath, $source, $ownerKey . ':' . $safeModuleKey, 'module');
            $moduleNavs = self::collectNavigationNodes($modulePath, $source, $ownerKey . ':' . $safeModuleKey, 'module');

            $nodes[] = [
                'id' => 'module:' . $source . ':' . $ownerKey . ':' . $safeModuleKey,
                'type' => 'module',
                'label' => $moduleDirName,
                'meta' => [
                    'source' => $source,
                    'path' => self::toRelativePath($modulePath),
                    'views' => count($moduleViews),
                    'dashboards' => count($moduleDashboards),
                    'routes' => count($moduleRoutes),
                    'navs' => count($moduleNavs),
                ],
                'children' => self::buildCategoryNodes($source, $ownerKey . ':' . $safeModuleKey, [
                    'views' => $moduleViews,
                    'dashboards' => $moduleDashboards,
                    'routes' => $moduleRoutes,
                    'navs' => $moduleNavs,
                ]),
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')));
        return $nodes;
    }

    /** @return array<int,array<string,mixed>> */
    private static function collectViewNodes(string $ownerPath, string $source, string $ownerKey, string $ownerType): array
    {
        $nodes = [];
        foreach (self::findPhpFilesUnderDirectory($ownerPath . '/Views') as $relativePath) {
            $nodes[] = [
                'id' => 'view:' . $source . ':' . $ownerKey . ':' . self::safeNodeKey($relativePath),
                'type' => 'view',
                'label' => basename($relativePath),
                'meta' => [
                    'source' => $source,
                    'owner_type' => $ownerType,
                    'owner_key' => $ownerKey,
                    'path' => $relativePath,
                ],
                'children' => [],
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => strcmp((string)($a['meta']['path'] ?? ''), (string)($b['meta']['path'] ?? '')));
        return $nodes;
    }

    /** @return array<int,array<string,mixed>> */
    private static function collectDashboardNodes(string $ownerPath, string $source, string $ownerKey, string $ownerType): array
    {
        $nodes = [];
        foreach (self::findPhpFilesUnderDirectory($ownerPath . '/Views') as $relativePath) {
            $normalized = strtolower($relativePath);
            if (!str_contains($normalized, 'dashboard')) {
                continue;
            }
            $nodes[] = [
                'id' => 'dashboard:' . $source . ':' . $ownerKey . ':' . self::safeNodeKey($relativePath),
                'type' => 'dashboard',
                'label' => basename($relativePath),
                'meta' => [
                    'source' => $source,
                    'owner_type' => $ownerType,
                    'owner_key' => $ownerKey,
                    'path' => $relativePath,
                ],
                'children' => [],
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => strcmp((string)($a['meta']['path'] ?? ''), (string)($b['meta']['path'] ?? '')));
        return $nodes;
    }

    /** @return array<int,array<string,mixed>> */
    private static function collectRouteNodes(string $ownerPath, string $source, string $ownerKey, string $ownerType): array
    {
        $nodes = [];
        $routeCandidates = [
            $ownerPath . '/routes.php',
            $ownerPath . '/routes/admin.php',
            $ownerPath . '/routes/operator.php',
            $ownerPath . '/routes/api.php',
        ];

        foreach ($routeCandidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $relativePath = self::toRelativePath($candidate);
            $nodes[] = [
                'id' => 'route_file:' . $source . ':' . $ownerKey . ':' . self::safeNodeKey($relativePath),
                'type' => 'route',
                'label' => basename($candidate),
                'meta' => [
                    'source' => $source,
                    'owner_type' => $ownerType,
                    'owner_key' => $ownerKey,
                    'path' => $relativePath,
                ],
                'children' => [],
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => strcmp((string)($a['meta']['path'] ?? ''), (string)($b['meta']['path'] ?? '')));
        return $nodes;
    }

    /** @return array<int,array<string,mixed>> */
    private static function collectNavigationNodes(string $ownerPath, string $source, string $ownerKey, string $ownerType): array
    {
        $nodes = [];
        $navigationCandidates = [
            $ownerPath . '/navigation.php',
            $ownerPath . '/navigation/admin.php',
            $ownerPath . '/navigation/operator.php',
        ];

        foreach ($navigationCandidates as $candidate) {
            if (!is_file($candidate)) {
                continue;
            }
            $relativePath = self::toRelativePath($candidate);
            $nodes[] = [
                'id' => 'nav:' . $source . ':' . $ownerKey . ':' . self::safeNodeKey($relativePath),
                'type' => 'nav',
                'label' => basename($candidate),
                'meta' => [
                    'source' => $source,
                    'owner_type' => $ownerType,
                    'owner_key' => $ownerKey,
                    'path' => $relativePath,
                ],
                'children' => [],
            ];
        }

        usort($nodes, static fn(array $a, array $b): int => strcmp((string)($a['meta']['path'] ?? ''), (string)($b['meta']['path'] ?? '')));
        return $nodes;
    }

    /** @return array<int,string> */
    private static function listSubdirectories(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $entries = @scandir($path);
        if (!is_array($entries)) {
            return [];
        }

        $dirs = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (!is_dir($path . '/' . $entry)) {
                continue;
            }
            $dirs[] = $entry;
        }

        sort($dirs);
        return $dirs;
    }

    /** @return array<int,string> */
    private static function findPhpFilesUnderDirectory(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }
            if (!$fileInfo->isFile()) {
                continue;
            }
            $pathName = (string)$fileInfo->getPathname();
            if (strtolower((string)$fileInfo->getExtension()) !== 'php') {
                continue;
            }
            $files[] = self::toRelativePath($pathName);
        }

        sort($files);
        return $files;
    }

    /**
     * @param array<string,mixed> $node
     * @param array<string,array<string,mixed>> $index
     */
    private static function indexTreeNode(array $node, array &$index): void
    {
        $id = trim((string)($node['id'] ?? ''));
        if ($id !== '') {
            $index[$id] = $node;
        }

        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            self::indexTreeNode($child, $index);
        }
    }

    /**
     * @param array<string,array<string,mixed>> $index
     * @return array<string,int>
     */
    private static function countByEntityType(array $index): array
    {
        $counts = [
            'apps' => 0,
            'plugins' => 0,
            'modules' => 0,
            'views' => 0,
            'dashboards' => 0,
            'routes' => 0,
            'navs' => 0,
        ];

        foreach ($index as $node) {
            if (!is_array($node)) {
                continue;
            }
            $type = strtolower(trim((string)($node['type'] ?? '')));
            if ($type === 'app') {
                $source = strtolower(trim((string)($node['meta']['source'] ?? '')));
                if ($source === 'apps' || $source === 'generated') {
                    $counts['apps']++;
                }
                continue;
            }
            if ($type === 'plugin') {
                $counts['plugins']++;
                continue;
            }
            if ($type === 'module') {
                $counts['modules']++;
                continue;
            }
            if ($type === 'view') {
                $counts['views']++;
                continue;
            }
            if ($type === 'dashboard') {
                $counts['dashboards']++;
                continue;
            }
            if ($type === 'route') {
                $counts['routes']++;
                continue;
            }
            if ($type === 'nav') {
                $counts['navs']++;
                continue;
            }
        }

        return $counts;
    }

    private static function safeNodeKey(string $value): string
    {
        $trimmed = strtolower(trim($value));
        if ($trimmed === '') {
            return '';
        }

        $safe = preg_replace('/[^a-z0-9_:\/.\-]+/', '_', $trimmed);
        return is_string($safe) ? trim($safe, '_') : '';
    }

    private static function toRelativePath(string $absolutePath): string
    {
        $root = rtrim((string)APP_ROOT, '/');
        $normalized = str_replace('\\', '/', $absolutePath);
        if (str_starts_with($normalized, $root . '/')) {
            return substr($normalized, strlen($root) + 1);
        }
        return ltrim($normalized, '/');
    }
}
