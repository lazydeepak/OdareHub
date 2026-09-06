<?php
declare(strict_types=1);

namespace Platform\Security;

final class EngineeringWorkspacePageContextResolver
{
    private const SURFACE_WORKSPACE_ROOT = 'workspace_root';
    private const SURFACE_WORKSPACE_CHILD = 'workspace_child';
    private const SURFACE_NON_WORKSPACE = 'non_workspace';

    private const STATE_LINKED_VALID = 'linked_valid';
    private const STATE_INITIALIZATION_REQUIRED = 'initialization_required';
    private const STATE_CONTRACT_REPAIR_REQUIRED = 'contract_repair_required';
    private const STATE_EXCLUDED = 'excluded';
    private const STATE_UNRESOLVED = 'unresolved';

    /**
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>|null
     */
    public static function resolveCurrentRequest(?array $actor): ?array
    {
        $requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        return self::resolveForRequest($requestUri, $actor, $_GET);
    }

    /**
     * @param array<string,mixed>|null $actor
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    public static function resolveForRequest(string $requestUri, ?array $actor, array $query = []): ?array
    {
        if (!self::developerModeActive()) {
            return null;
        }

        if (!PlatformAuthority::canManageEngineeringWorkspaces($actor)) {
            return null;
        }

        $path = self::normalizePath((string)(parse_url($requestUri, PHP_URL_PATH) ?: '/'));
        if ($path === '/apps/studio/engineering-workspaces') {
            return null;
        }

        $surface = self::resolveSurfaceForPath($path, $query);
        $classification = (string)($surface['surface_classification'] ?? '');
        if ($classification !== self::SURFACE_WORKSPACE_ROOT && $classification !== self::SURFACE_WORKSPACE_CHILD) {
            return null;
        }

        $workspaceKey = trim((string)($surface['workspace_key'] ?? ''));
        if ($workspaceKey === '') {
            return null;
        }

        $state = (string)($surface['resolution_state'] ?? self::STATE_UNRESOLVED);
        if ($state === self::STATE_INITIALIZATION_REQUIRED || $state === self::STATE_UNRESOLVED || $state === self::STATE_EXCLUDED) {
            return null;
        }

        return self::presentationForWorkspace($workspaceKey, $actor, $surface);
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function resolveSurfaceForRequest(string $requestUri, array $query = []): array
    {
        $path = self::normalizePath((string)(parse_url($requestUri, PHP_URL_PATH) ?: '/'));
        return self::resolveSurfaceForPath($path, $query);
    }

    /**
     * Resolve the canonical navigable parent page for an Engineering Workspace key.
     *
     * @return array{label:string,url:string,workspace_key:string,resolved:bool}
     */
    public static function parentPageForWorkspace(string $workspaceKey): array
    {
        $workspaceKey = trim($workspaceKey, "/ \t\n\r\0\x0B");
        $fallback = self::engineeringWorkspacesHubParent($workspaceKey);
        if ($workspaceKey === '') {
            return $fallback;
        }

        $best = null;
        $bestLength = 0;
        foreach (self::registeredSurfaces() as $surface) {
            $classification = (string)($surface['surface_classification'] ?? '');
            if ($classification !== self::SURFACE_WORKSPACE_ROOT && $classification !== self::SURFACE_WORKSPACE_CHILD) {
                continue;
            }

            $candidateWorkspace = trim((string)($surface['workspace_key'] ?? ''), "/ \t\n\r\0\x0B");
            if ($candidateWorkspace === '') {
                continue;
            }
            if ($workspaceKey !== $candidateWorkspace && !str_starts_with($workspaceKey . '/', $candidateWorkspace . '/')) {
                continue;
            }

            $url = self::canonicalSurfaceUrl($surface);
            if ($url === '') {
                continue;
            }

            $length = strlen($candidateWorkspace);
            if ($length > $bestLength) {
                $bestLength = $length;
                $best = [
                    'label' => self::parentPageLabel($candidateWorkspace),
                    'url' => $url,
                    'workspace_key' => $candidateWorkspace,
                    'resolved' => true,
                ];
            }
        }

        return $best ?? $fallback;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    private static function resolveSurfaceForPath(string $path, array $query): array
    {
        $explicit = self::explicitWorkspaceSurfaceForRequest($path, $query);
        if ($explicit !== null) {
            return $explicit + [
                'registered' => true,
                'resolution_state' => self::workspaceState((string)($explicit['workspace_key'] ?? '')),
            ];
        }

        $candidate = self::bestRegisteredSurfaceForPath($path);
        if ($candidate === null) {
            return [
                'registered' => false,
                'surface_classification' => '',
                'resolution_state' => self::STATE_UNRESOLVED,
                'workspace_key' => '',
            ];
        }

        if (($candidate['surface_classification'] ?? '') === self::SURFACE_NON_WORKSPACE) {
            return $candidate + [
                'registered' => true,
                'resolution_state' => self::STATE_EXCLUDED,
                'workspace_key' => '',
            ];
        }

        $workspaceKey = trim((string)($candidate['workspace_key'] ?? ''));
        return $candidate + [
            'registered' => true,
            'resolution_state' => self::workspaceState($workspaceKey),
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    private static function explicitWorkspaceSurfaceForRequest(string $path, array $query): ?array
    {
        if ($path !== '/apps/studio/tools/owner-structure-scan') {
            return null;
        }

        $ownerKey = trim((string)($query['owner'] ?? ''));
        $serviceFile = APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
        if (!is_file($serviceFile)) {
            return null;
        }
        require_once $serviceFile;

        $service = '\\Apps\\Studio\\Tools\\OwnerStructureScan\\Services\\OwnerStructureOwnerDiscoveryService';
        if (!class_exists($service)) {
            return null;
        }

        $resolution = $service::resolve($ownerKey);
        $selectedOwnerKey = trim((string)($resolution['selected_owner_key'] ?? ''));
        if ($selectedOwnerKey === '') {
            return null;
        }

        return [
            'surface_classification' => self::SURFACE_WORKSPACE_CHILD,
            'workspace_key' => EngineeringWorkspaceResolver::ownerKeyToWorkspaceKey($selectedOwnerKey),
            'source' => 'owner_structure_selection',
            'route' => $path,
            'match_length' => strlen($path),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function bestRegisteredSurfaceForPath(string $path): ?array
    {
        $best = null;
        foreach (self::registeredSurfaces() as $surface) {
            $match = self::matchSurface($path, $surface);
            if ($match === null) {
                continue;
            }
            $candidate = $match + $surface;
            if ($best === null || self::surfaceRank($candidate) > self::surfaceRank($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function registeredSurfaces(): array
    {
        return array_merge(
            self::studioToolManifestSurfaces(),
            self::navigationSurfaces()
        );
    }

    /**
     * @param array<string,mixed>|null $actor
     * @return array<string,mixed>|null
     */
    private static function presentationForWorkspace(string $workspaceKey, ?array $actor, array $surface): ?array
    {
        $links = EngineeringWorkspaceResolver::buildWorkspaceLinks($workspaceKey, $actor);
        if ($links === null || empty($links['available'])) {
            return null;
        }

        return [
            'workspace_key' => (string)($links['workspace_key'] ?? $workspaceKey),
            'workspace_display_name' => self::displayName((string)($links['workspace_label'] ?? $workspaceKey)),
            'surface_classification' => (string)($surface['surface_classification'] ?? ''),
            'resolution_state' => (string)($surface['resolution_state'] ?? ''),
            'breadcrumbs' => self::workspaceBreadcrumbs($workspaceKey),
            'overview_url' => !empty($links['has_overview']) ? (string)($links['overview_url'] ?? '') : '',
            'work_url' => !empty($links['has_work']) ? (string)($links['work_url'] ?? '') : '',
            'rules_url' => !empty($links['has_rules']) ? (string)($links['rules_url'] ?? '') : '',
            'decisions_url' => !empty($links['has_decisions']) ? (string)($links['decisions_url'] ?? '') : '',
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function studioToolManifestSurfaces(): array
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $studioRoot = $appRoot . '/apps/Studio';
        $toolsRoot = $studioRoot . '/Tools';
        if (!is_dir($toolsRoot)) {
            return [];
        }

        $appName = basename($studioRoot);
        $surfaces = [];
        foreach (glob($toolsRoot . '/*/manifest.php') ?: [] as $manifestPath) {
            $manifest = require $manifestPath;
            if (!is_array($manifest)) {
                continue;
            }
            $route = self::normalizeRoute((string)($manifest['canonical_route'] ?? ''));
            $toolDir = basename(dirname($manifestPath));
            if ($route === '' || $toolDir === '') {
                continue;
            }
            $workspace = isset($manifest['engineering_workspace']) && is_array($manifest['engineering_workspace'])
                ? $manifest['engineering_workspace']
                : [];
            $classification = self::normalizeSurfaceClassification((string)($workspace['surface_classification'] ?? self::SURFACE_WORKSPACE_ROOT));
            $workspaceKey = trim((string)($workspace['workspace_key'] ?? ($appName . '/tools/' . $toolDir)), "/ \t\n\r\0\x0B");
            $surfaces[] = [
                'surface_classification' => $classification,
                'workspace_key' => $classification === self::SURFACE_NON_WORKSPACE ? '' : $workspaceKey,
                'source' => 'studio_tool_manifest',
                'route' => $route,
                'exact_paths' => [$route],
                'prefix_paths' => $classification === self::SURFACE_NON_WORKSPACE ? [] : [$route],
            ];
        }

        return $surfaces;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function navigationSurfaces(): array
    {
        $surfaces = [];
        foreach (self::registeredNavigationFiles() as $navigationFile) {
            $navigation = require $navigationFile['path'];
            if (!is_array($navigation)) {
                continue;
            }
            $items = isset($navigation['items']) && is_array($navigation['items']) ? $navigation['items'] : [];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $surface = self::surfaceFromNavigationItem($item, $navigationFile);
                if ($surface !== null) {
                    $surfaces[] = $surface;
                }
            }
        }

        return $surfaces;
    }

    /**
     * @return array<int,array{path:string,app:string,app_slug:string,module:string,type:string}>
     */
    private static function registeredNavigationFiles(): array
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $files = [];
        foreach (glob($appRoot . '/apps/*/navigation.php') ?: [] as $path) {
            $appName = basename(dirname($path));
            if ($appName === '') {
                continue;
            }
            $files[] = [
                'path' => $path,
                'app' => $appName,
                'app_slug' => self::slugForSegment($appName),
                'module' => '',
                'type' => 'app',
            ];
        }
        foreach (glob($appRoot . '/apps/*/modules/*/navigation.php') ?: [] as $path) {
            $moduleDir = dirname($path);
            $appDir = dirname(dirname($moduleDir));
            $appName = basename($appDir);
            $moduleName = basename($moduleDir);
            if ($appName === '' || $moduleName === '') {
                continue;
            }
            $files[] = [
                'path' => $path,
                'app' => $appName,
                'app_slug' => self::slugForSegment($appName),
                'module' => $moduleName,
                'type' => 'module',
            ];
        }

        return $files;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,string> $navigationFile
     * @return array<string,mixed>|null
     */
    private static function surfaceFromNavigationItem(array $item, array $navigationFile): ?array
    {
        $workspace = isset($item['engineering_workspace']) && is_array($item['engineering_workspace'])
            ? $item['engineering_workspace']
            : [];
        $classification = self::normalizeSurfaceClassification((string)($workspace['surface_classification'] ?? ''));
        $workspaceKey = trim((string)($workspace['workspace_key'] ?? ''), "/ \t\n\r\0\x0B");

        if ($classification === '') {
            $workspaceKey = $workspaceKey !== '' ? $workspaceKey : self::workspaceKeyForNavigationItem($item, $navigationFile);
            if ($workspaceKey === '') {
                return null;
            }
            $classification = self::SURFACE_WORKSPACE_ROOT;
        }

        $paths = self::navigationItemPaths($item);
        if (self::isAppLandingNavigationItem($item, $navigationFile)) {
            $paths['prefix'] = [];
        }
        if ($paths['exact'] === [] && $paths['prefix'] === []) {
            return null;
        }

        return [
            'surface_classification' => $classification,
            'workspace_key' => $classification === self::SURFACE_NON_WORKSPACE ? '' : $workspaceKey,
            'source' => 'navigation_registry',
            'route' => (string)($item['url'] ?? ''),
            'exact_paths' => $paths['exact'],
            'prefix_paths' => $classification === self::SURFACE_NON_WORKSPACE ? [] : $paths['prefix'],
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,string> $navigationFile
     */
    private static function workspaceKeyForNavigationItem(array $item, array $navigationFile): string
    {
        $appName = (string)($navigationFile['app'] ?? '');
        $moduleName = (string)($navigationFile['module'] ?? '');
        if ($appName === '') {
            return '';
        }
        if ($moduleName !== '') {
            return $appName . '/' . $moduleName;
        }

        $url = self::normalizeRoute((string)($item['url'] ?? ''));
        $appSlug = (string)($navigationFile['app_slug'] ?? '');
        if ($url === '' || $appSlug === '') {
            return '';
        }

        $appRoot = '/apps/' . $appSlug;
        if ($url === $appRoot) {
            return $appName;
        }
        if (!str_starts_with($url . '/', $appRoot . '/')) {
            return '';
        }

        $tail = trim(substr($url, strlen($appRoot)), '/');
        $firstSegment = explode('/', $tail)[0] ?? '';
        $workspaceSegment = self::workspaceSegmentFromRouteSlug($appName, $firstSegment);
        return $workspaceSegment !== '' ? $appName . '/' . $workspaceSegment : '';
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,string> $navigationFile
     */
    private static function isAppLandingNavigationItem(array $item, array $navigationFile): bool
    {
        if (($navigationFile['type'] ?? '') !== 'app') {
            return false;
        }
        $appSlug = (string)($navigationFile['app_slug'] ?? '');
        if ($appSlug === '') {
            return false;
        }

        return self::normalizeRoute((string)($item['url'] ?? '')) === '/apps/' . $appSlug;
    }

    /**
     * @param array<string,mixed> $item
     * @return array{exact:string[],prefix:string[]}
     */
    private static function navigationItemPaths(array $item): array
    {
        $exact = [];
        $prefix = [];
        $url = self::normalizeRoute((string)($item['url'] ?? ''));
        if ($url !== '') {
            $exact[] = $url;
        }

        $patterns = isset($item['active_patterns']) && is_array($item['active_patterns']) ? $item['active_patterns'] : [];
        foreach (['exact' => 'exact', 'prefix' => 'prefix'] as $source => $target) {
            $values = isset($patterns[$source]) && is_array($patterns[$source]) ? $patterns[$source] : [];
            foreach ($values as $value) {
                $path = self::normalizeRoute((string)$value);
                if ($path !== '') {
                    ${$target}[] = $path;
                }
            }
        }

        return [
            'exact' => array_values(array_unique($exact)),
            'prefix' => array_values(array_unique($prefix)),
        ];
    }

    /**
     * @param array<string,mixed> $surface
     * @return array<string,mixed>|null
     */
    private static function matchSurface(string $path, array $surface): ?array
    {
        $bestLength = 0;
        $matchType = '';
        foreach (($surface['exact_paths'] ?? []) as $candidate) {
            $candidate = self::normalizePath((string)$candidate);
            if ($path === $candidate && strlen($candidate) > $bestLength) {
                $bestLength = strlen($candidate);
                $matchType = 'exact';
            }
        }
        foreach (($surface['prefix_paths'] ?? []) as $candidate) {
            $candidate = self::normalizePath((string)$candidate);
            if ($path !== $candidate && str_starts_with($path . '/', $candidate . '/') && strlen($candidate) > $bestLength) {
                $bestLength = strlen($candidate);
                $matchType = 'prefix';
            }
        }

        if ($bestLength === 0) {
            return null;
        }

        return [
            'match_type' => $matchType,
            'match_length' => $bestLength,
            'surface_classification' => $matchType === 'prefix'
                && ($surface['surface_classification'] ?? '') === self::SURFACE_WORKSPACE_ROOT
                    ? self::SURFACE_WORKSPACE_CHILD
                    : (string)($surface['surface_classification'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $surface
     */
    private static function surfaceRank(array $surface): int
    {
        $length = (int)($surface['match_length'] ?? 0);
        $state = self::workspaceState((string)($surface['workspace_key'] ?? ''));
        $stateRank = [
            self::STATE_LINKED_VALID => 4,
            self::STATE_CONTRACT_REPAIR_REQUIRED => 3,
            self::STATE_INITIALIZATION_REQUIRED => 2,
            self::STATE_EXCLUDED => 1,
            self::STATE_UNRESOLVED => 0,
        ][$state] ?? 0;
        $exactBonus = (($surface['match_type'] ?? '') === 'exact') ? 10 : 0;

        return ($length * 100) + $exactBonus + $stateRank;
    }

    private static function workspaceState(string $workspaceKey): string
    {
        $workspaceKey = trim($workspaceKey, "/ \t\n\r\0\x0B");
        if ($workspaceKey === '' || !preg_match('#^[A-Za-z0-9_./-]+$#', $workspaceKey) || str_contains($workspaceKey, '..')) {
            return self::STATE_UNRESOLVED;
        }
        if (EngineeringWorkspaceContentContract::isReservedTemplateWorkspaceKey($workspaceKey)) {
            return self::STATE_EXCLUDED;
        }

        $workspaceDir = APP_ROOT . '/engineering/' . $workspaceKey;
        if (!is_dir($workspaceDir)) {
            return self::STATE_INITIALIZATION_REQUIRED;
        }
        if (!EngineeringWorkspaceContentContract::pathIsInsideEngineeringRoot($workspaceDir)) {
            return self::STATE_UNRESOLVED;
        }

        $missing = [];
        $invalid = [];
        foreach (EngineeringWorkspaceContentContract::allowedDocumentKeys() as $docKey) {
            $filename = EngineeringWorkspaceContentContract::canonicalFilename($docKey);
            $path = $filename !== null ? $workspaceDir . '/' . $filename : '';
            if ($path === '' || !is_file($path)) {
                $missing[] = $docKey;
                continue;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                $invalid[] = $docKey;
                continue;
            }
            $validation = EngineeringWorkspaceContentContract::validateDocumentContent($docKey, $content);
            if (empty($validation['ok'])) {
                $invalid[] = $docKey;
            }
        }

        return $missing === [] && $invalid === []
            ? self::STATE_LINKED_VALID
            : self::STATE_CONTRACT_REPAIR_REQUIRED;
    }

    private static function normalizeSurfaceClassification(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === 'workspace_root' || $normalized === 'root') {
            return self::SURFACE_WORKSPACE_ROOT;
        }
        if ($normalized === 'workspace_child' || $normalized === 'child') {
            return self::SURFACE_WORKSPACE_CHILD;
        }
        if ($normalized === 'non_workspace' || $normalized === 'excluded') {
            return self::SURFACE_NON_WORKSPACE;
        }

        return '';
    }

    /**
     * @param array<string,mixed> $surface
     */
    private static function canonicalSurfaceUrl(array $surface): string
    {
        $route = self::normalizeRoute((string)($surface['route'] ?? ''));
        if ($route !== '') {
            return $route;
        }

        foreach (($surface['exact_paths'] ?? []) as $candidate) {
            $path = self::normalizeRoute((string)$candidate);
            if ($path !== '') {
                return $path;
            }
        }

        return '';
    }

    /** @var array<string,string> Workspace keys that need a custom parent label distinct from the key-derived name. */
    private static array $parentLabelOverrides = [
        'Studio/tools/HelperTool' => 'Repository Scanner',
    ];

    private static function parentPageLabel(string $workspaceKey): string
    {
        if (isset(self::$parentLabelOverrides[$workspaceKey])) {
            return self::$parentLabelOverrides[$workspaceKey];
        }

        $breadcrumbs = self::workspaceBreadcrumbs($workspaceKey);
        $last = $breadcrumbs !== [] ? end($breadcrumbs) : false;
        if (is_array($last)) {
            $label = trim((string)($last['label'] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }

        $segments = array_values(array_filter(explode('/', trim($workspaceKey, '/')), 'strlen'));
        $lastSegment = (string)end($segments);
        return $lastSegment !== '' ? self::workspaceSegmentLabel($lastSegment) : 'Engineering Workspaces';
    }

    /**
     * @return array{label:string,url:string,workspace_key:string,resolved:bool}
     */
    private static function engineeringWorkspacesHubParent(string $workspaceKey): array
    {
        return [
            'label' => 'Engineering Workspaces',
            'url' => '/apps/studio/tools/engineering-workspaces',
            'workspace_key' => $workspaceKey,
            'resolved' => false,
        ];
    }

    private static function developerModeActive(): bool
    {
        return function_exists('app_dev_tools_enabled') && (bool)app_dev_tools_enabled();
    }

    private static function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    private static function normalizeRoute(string $value): string
    {
        $route = self::normalizePath($value);
        return $route === '/' ? '' : $route;
    }

    private static function displayName(string $workspaceKey): string
    {
        $clean = trim($workspaceKey, "/ \t\n\r\0\x0B");
        return $clean === '' ? 'Engineering Workspace' : str_replace('/', ' / ', $clean);
    }

    /**
     * Build clickable breadcrumbs only for path segments that map to real Studio pages.
     *
     * @return array<int,array{label:string,url:string}>
     */
    public static function workspaceBreadcrumbs(string $workspaceKey): array
    {
        $clean = trim($workspaceKey, "/ \t\n\r\0\x0B");
        if ($clean === '') {
            return [];
        }

        $routeMap = self::registeredRouteBreadcrumbMap();
        $routeMap += [
            'Studio' => ['label' => 'Studio', 'url' => '/apps/studio'],
            'Studio/tools' => ['label' => 'Tools', 'url' => '/apps/studio/tools/registry'],
        ];
        $routeMap += self::ownerBreadcrumbMap();

        $segments = array_values(array_filter(explode('/', $clean), 'strlen'));
        $breadcrumbs = [];
        if (($segments[0] ?? '') !== 'Studio' && isset($routeMap['Apps'], $routeMap[$segments[0] ?? ''])) {
            $breadcrumbs[] = $routeMap['Apps'];
        }
        $prefix = '';
        foreach ($segments as $segment) {
            $prefix = $prefix === '' ? $segment : $prefix . '/' . $segment;
            if (!isset($routeMap[$prefix])) {
                continue;
            }
            $breadcrumbs[] = $routeMap[$prefix];
        }

        return $breadcrumbs;
    }

    /**
     * @return array<string,array{label:string,url:string}>
     */
    private static function ownerBreadcrumbMap(): array
    {
        $map = [];
        foreach (self::registeredAppNavigation() as $appName => $appInfo) {
            $appSlug = (string)($appInfo['slug'] ?? '');
            $items = isset($appInfo['items']) && is_array($appInfo['items']) ? $appInfo['items'] : [];
            if ($appName === '' || $appSlug === '' || $items === []) {
                continue;
            }
            $map['Apps'] = ['label' => 'Apps', 'url' => ''];
            $map[$appName] = [
                'label' => self::workspaceSegmentLabel($appName),
                'url' => self::appHomeUrl($appSlug, $items),
            ];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $url = self::normalizeAppRoute((string)($item['url'] ?? ''));
                if ($url === '' || !str_starts_with($url . '/', '/apps/' . $appSlug . '/')) {
                    continue;
                }
                $tail = trim(substr($url, strlen('/apps/' . $appSlug)), '/');
                if ($tail === '') {
                    continue;
                }
                $firstSegment = explode('/', $tail)[0] ?? '';
                $workspaceSegment = self::workspaceSegmentFromRouteSlug($appName, $firstSegment);
                if ($workspaceSegment === '') {
                    continue;
                }
                $map[$appName . '/' . $workspaceSegment] = [
                    'label' => self::workspaceSegmentLabel($workspaceSegment),
                    'url' => $url,
                ];
            }
        }

        return $map;
    }

    /**
     * @return array<string,array{label:string,url:string}>
     */
    private static function registeredRouteBreadcrumbMap(): array
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $toolsRoot = $appRoot . '/apps/Studio/Tools';
        if (!is_dir($toolsRoot)) {
            return [];
        }

        $map = [];
        foreach (glob($toolsRoot . '/*/manifest.php') ?: [] as $manifestPath) {
            $manifest = require $manifestPath;
            if (!is_array($manifest)) {
                continue;
            }
            $route = self::normalizeStudioRoute((string)($manifest['canonical_route'] ?? ''));
            $name = trim((string)($manifest['name'] ?? ''));
            $toolDir = basename(dirname($manifestPath));
            if ($route === '' || $name === '' || $toolDir === '') {
                continue;
            }
            $map['Studio/tools/' . $toolDir] = [
                'label' => $name,
                'url' => $route,
            ];
        }

        return $map;
    }

    /**
     * @return array<string,array{slug:string,items:array<int,array<string,mixed>>}>
     */
    private static function registeredAppNavigation(): array
    {
        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $appsDir = $appRoot . '/apps';
        if (!is_dir($appsDir)) {
            return [];
        }

        $map = [];
        foreach (glob($appsDir . '/*/navigation.php') ?: [] as $navigationPath) {
            $appName = basename(dirname($navigationPath));
            if ($appName === '' || $appName === 'Studio') {
                continue;
            }
            $navigation = require $navigationPath;
            if (!is_array($navigation)) {
                continue;
            }
            $items = isset($navigation['items']) && is_array($navigation['items']) ? $navigation['items'] : [];
            if ($items === []) {
                continue;
            }
            $map[$appName] = [
                'slug' => self::slugForSegment($appName),
                'items' => $items,
            ];
        }

        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $items
     */
    private static function appHomeUrl(string $appSlug, array $items): string
    {
        $target = '/apps/' . $appSlug;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $url = self::normalizeAppRoute((string)($item['url'] ?? ''));
            if ($url === $target) {
                return $url;
            }
        }

        return '';
    }

    private static function workspaceSegmentFromRouteSlug(string $appName, string $routeSlug): string
    {
        $routeSlug = trim($routeSlug);
        if ($routeSlug === '') {
            return '';
        }

        $appRoot = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
        $modulesDir = $appRoot . '/apps/' . $appName . '/modules';
        if (is_dir($modulesDir)) {
            foreach (glob($modulesDir . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
                $moduleName = basename($moduleDir);
                if (self::slugForSegment($moduleName) === $routeSlug) {
                    return $moduleName;
                }
            }
        }

        return self::studlyFromSlug($routeSlug);
    }

    private static function normalizeAppRoute(string $value): string
    {
        $route = '/' . trim($value, '/');
        if (!str_starts_with($route, '/apps/')) {
            return '';
        }

        return rtrim($route, '/');
    }

    private static function workspaceSegmentLabel(string $segment): string
    {
        if ($segment !== '' && strtoupper($segment) === $segment) {
            return $segment;
        }
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $segment) ?: $segment;
        return trim((string)$label);
    }

    private static function slugForSegment(string $segment): string
    {
        if ($segment !== '' && strtoupper($segment) === $segment) {
            return strtolower($segment);
        }
        $withBreaks = preg_replace('/(?<!^)([A-Z])/', '-$1', $segment) ?: $segment;
        $slug = strtolower((string)preg_replace('/[^A-Za-z0-9]+/', '-', $withBreaks));
        return trim($slug, '-');
    }

    private static function studlyFromSlug(string $slug): string
    {
        $parts = array_values(array_filter(explode('-', strtolower($slug)), 'strlen'));
        if ($parts === []) {
            return '';
        }

        return implode('', array_map(static fn(string $part): string => ucfirst($part), $parts));
    }

    private static function normalizeStudioRoute(string $value): string
    {
        $route = '/' . trim($value, '/');
        if (!str_starts_with($route, '/apps/studio/')) {
            return '';
        }
        return rtrim($route, '/');
    }
}
