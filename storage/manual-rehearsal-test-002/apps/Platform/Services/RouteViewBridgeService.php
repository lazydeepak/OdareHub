<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use Apps\Studio\Services\GuiStudioService;

final class RouteViewBridgeService
{
    /**
     * @param array<string,mixed> $diagnostics
     * @return array<string,mixed>
     */
    public static function enrichRouteDiagnostics(array $diagnostics): array
    {
        $moduleMap = self::generatedRouteModuleMap();

        $declaredRoutes = is_array($diagnostics['declared_routes'] ?? null)
            ? array_values(array_filter($diagnostics['declared_routes'], 'is_array'))
            : [];
        foreach ($declaredRoutes as $idx => $row) {
            $declaredRoutes[$idx] = self::decorateRouteRow($row, [
                trim((string)($row['path'] ?? '')),
            ], $moduleMap);
        }

        $linkedRoutes = is_array($diagnostics['linked_routes'] ?? null)
            ? array_values(array_filter($diagnostics['linked_routes'], 'is_array'))
            : [];
        foreach ($linkedRoutes as $idx => $row) {
            $linkedRoutes[$idx] = self::decorateRouteRow($row, [
                trim((string)($row['runtime_url'] ?? '')),
                trim((string)($row['url'] ?? '')),
            ], $moduleMap);
        }

        $diagnostics['declared_routes'] = $declaredRoutes;
        $diagnostics['linked_routes'] = $linkedRoutes;
        return $diagnostics;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,string> $candidateRoutes
     * @param array<string,array<string,string>> $moduleMap
     * @return array<string,mixed>
     */
    private static function decorateRouteRow(array $row, array $candidateRoutes, array $moduleMap): array
    {
        $mapping = self::resolveModuleMapping($candidateRoutes, $moduleMap);
        $status = trim((string)($row['status'] ?? ''));

        if ($mapping === null) {
            $row['studio_mapping_status'] = self::fallbackMappingStatus($status);
            $row['studio_action'] = 'unavailable';
            $row['studio_open_url'] = '';
            $row['studio_app_key'] = '';
            $row['studio_module_key'] = '';
            $row['studio_view_key'] = '';
            return $row;
        }

        $mappingSource = trim((string)($mapping['mapping_source'] ?? 'generated'));
        $row['studio_mapping_status'] = $mappingSource === 'generated' ? 'mapped_generated' : 'mapped_inferred';
        $row['studio_action'] = 'open';

        if ($mappingSource === 'generated') {
            $row['studio_open_url'] = '/apps/studio/import-view?app_key='
                . rawurlencode($mapping['app_key'])
                . '&module_key=' . rawurlencode($mapping['module_key'])
                . '&view_key=' . rawurlencode($mapping['view_key']);
        } else {
            $routeFocus = self::normalizeRoute((string)($mapping['route_path'] ?? ''));
            $row['studio_open_url'] = '/apps/studio?library_item=source%3Aroute_authority&route_focus=' . rawurlencode($routeFocus);
        }

        $row['studio_app_key'] = $mapping['app_key'];
        $row['studio_module_key'] = $mapping['module_key'];
        $row['studio_view_key'] = $mapping['view_key'];

        return $row;
    }

    private static function fallbackMappingStatus(string $status): string
    {
        $normalized = strtolower(trim($status));
        if ($normalized === 'disabled_by_app_status') {
            return 'app_disabled';
        }
        if ($normalized === 'legacy_fallback') {
            return 'legacy_route';
        }
        if ($normalized === 'declared_missing' || $normalized === 'broken_missing') {
            return 'runtime_missing';
        }
        return 'mapping_pending';
    }

    /**
     * @param array<int,string> $candidateRoutes
     * @param array<string,array<string,string>> $moduleMap
     * @return array<string,string>|null
     */
    private static function resolveModuleMapping(array $candidateRoutes, array $moduleMap): ?array
    {
        foreach ($candidateRoutes as $routePath) {
            $normalized = self::normalizeRoute($routePath);
            if ($normalized === '') {
                continue;
            }
            if (isset($moduleMap[$normalized]) && is_array($moduleMap[$normalized])) {
                return $moduleMap[$normalized];
            }

            $inferred = self::inferMappingFromRoute($normalized);
            if ($inferred !== null) {
                return $inferred;
            }
        }

        return null;
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function generatedRouteModuleMap(): array
    {
        $map = [];
        if (!self::loadStudioServiceIfEnabled()) {
            return $map;
        }

        foreach (GuiStudioService::listGeneratedAppsWithModules() as $appEntry) {
            if (!is_array($appEntry)) {
                continue;
            }
            $appKey = trim((string)($appEntry['app_key'] ?? ''));
            $modules = is_array($appEntry['modules'] ?? null)
                ? array_values(array_filter($appEntry['modules'], 'is_array'))
                : [];
            foreach ($modules as $moduleEntry) {
                $moduleKey = trim((string)($moduleEntry['module_key'] ?? ''));
                $routePath = self::normalizeRoute((string)($moduleEntry['route_path'] ?? ''));
                if ($appKey === '' || $moduleKey === '' || $routePath === '') {
                    continue;
                }
                $map[$routePath] = [
                    'app_key' => $appKey,
                    'module_key' => $moduleKey,
                    'view_key' => 'index',
                    'route_path' => $routePath,
                    'mapping_source' => 'generated',
                ];
            }
        }

        return $map;
    }

    private static function loadStudioServiceIfEnabled(): bool
    {
        try {
            $row = \App\Core\DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['studio']);
            if (!is_array($row) || (string)($row['status'] ?? '') !== 'enabled') {
                return false;
            }
        } catch (\Throwable $e) {
            return false;
        }

        $servicePath = APP_ROOT . '/apps/Studio/Services/GuiStudioService.php';
        if (!class_exists(GuiStudioService::class) && is_file($servicePath)) {
            require_once $servicePath;
        }

        return class_exists(GuiStudioService::class);
    }

    /** @return array<string,string>|null */
    private static function inferMappingFromRoute(string $normalizedRoute): ?array
    {
        if (!preg_match('#^/apps/([a-z0-9\-]+)(?:/([a-z0-9\-]+))?(?:/([a-z0-9\-]+))?#', $normalizedRoute, $matches)) {
            return null;
        }

        $appSegment = trim((string)($matches[1] ?? ''));
        if ($appSegment === '') {
            return null;
        }

        $moduleSegment = trim((string)($matches[2] ?? ''));
        $viewSegment = trim((string)($matches[3] ?? ''));
        return [
            'app_key' => str_replace('-', '_', $appSegment),
            'module_key' => $moduleSegment !== '' ? str_replace('-', '_', $moduleSegment) : '',
            'view_key' => $viewSegment !== '' ? str_replace('-', '_', $viewSegment) : 'index',
            'route_path' => $normalizedRoute,
            'mapping_source' => 'inferred',
        ];
    }

    private static function normalizeRoute(string $routePath): string
    {
        $trimmed = trim($routePath);
        if ($trimmed === '' || $trimmed[0] !== '/') {
            return '';
        }

        $withoutQuery = explode('?', $trimmed, 2)[0];
        $normalized = rtrim($withoutQuery, '/');
        return $normalized === '' ? '/' : $normalized;
    }
}
