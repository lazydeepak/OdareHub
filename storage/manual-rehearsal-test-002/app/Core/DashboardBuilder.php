<?php
declare(strict_types=1);

namespace App\Core;

use Plugins\Base\Services\UserDashboardAssignmentService;

final class DashboardBuilder
{
    /**
     * @param array{
     *   activePlugins?:array<int,string>,
     *   availableGetRoutes?:array<int,string>,
    *   availablePostRoutes?:array<int,string>,
     *   pluginWidgets?:array<int,array<string,mixed>>,
     *   coverageDashboard?:array<string,mixed>|null,
     *   loggedIn?:bool,
     *   isAdmin?:bool,
    *   hasAdminToolsAccess?:bool,
     *   canAccessBase?:bool,
     *   role?:string,
     *   appEnvironment?:string
     * } $context
     * @return array<string,mixed>
     */
    public static function buildHome(array $context): array
    {
        $activePlugins = array_values(array_unique(array_map('strval', (array)($context['activePlugins'] ?? []))));
        $availableGetRoutes = array_values(array_unique(array_map('strval', (array)($context['availableGetRoutes'] ?? []))));
        $availablePostRoutes = array_values(array_unique(array_map('strval', (array)($context['availablePostRoutes'] ?? []))));
        $pluginWidgets = array_values((array)($context['pluginWidgets'] ?? []));
        $coverageDashboard = is_array($context['coverageDashboard'] ?? null) ? $context['coverageDashboard'] : null;

        $visibility = [
            'logged_in' => (bool)($context['loggedIn'] ?? false),
            'is_admin' => (bool)($context['isAdmin'] ?? false),
            'can_access_base' => (bool)($context['canAccessBase'] ?? false),
            'has_admin_tools_access' => (bool)($context['hasAdminToolsAccess'] ?? false),
            'role' => strtolower(trim((string)($context['role'] ?? ''))),
        ];

        $currentUser = is_array($context['user'] ?? null) ? (array)$context['user'] : null;
        $allowedModules = [];
        $hasModuleRestrictions = false;
        if (($context['loggedIn'] ?? false) && class_exists(UserDashboardAssignmentService::class)) {
            try {
                $allowedModules = UserDashboardAssignmentService::enabledModulesForUser($currentUser);
                $hasModuleRestrictions = !empty($allowedModules);
            } catch (\Throwable $e) {
                $allowedModules = [];
                $hasModuleRestrictions = false;
            }
        }

        $routeMap = array_fill_keys($availableGetRoutes, true);
        $pluginMap = array_fill_keys($activePlugins, true);

        $moduleIndex = ModuleRegistry::all();
        $ownerPluginMap = self::buildOwnerPluginMap($moduleIndex);

        $zones = [
            'platform' => [],
            'manufacturing' => [],
            'extensions' => [],
        ];

        foreach (self::loadCoreWidgets() as $def) {
            if (!self::isVisible((string)($def['visible_if'] ?? 'always'), $visibility)) {
                continue;
            }
            $widget = self::normalizeConfigWidget($def, $routeMap, $pluginMap);
            if (!self::moduleVisibleForWidget($widget, $allowedModules, $hasModuleRestrictions)) {
                continue;
            }
            $zones['platform'][] = $widget;
        }

        $manufacturingPriority = self::buildManufacturingPriorityWidgets($coverageDashboard);
        foreach ($manufacturingPriority as $widget) {
            if (!self::isVisible((string)($widget['visible_if'] ?? 'always'), $visibility)) {
                continue;
            }
            if (!self::moduleVisibleForWidget($widget, $allowedModules, $hasModuleRestrictions)) {
                continue;
            }
            $zones['manufacturing'][] = $widget;
        }

        foreach ($pluginWidgets as $widget) {
            if (!is_array($widget) || empty($widget['key'])) {
                continue;
            }
            $normalized = self::normalizePluginWidget($widget, $ownerPluginMap, $routeMap, $pluginMap);
            if (!self::isVisible((string)($normalized['visible_if'] ?? 'always'), $visibility)) {
                continue;
            }
            if (!self::moduleVisibleForWidget($normalized, $allowedModules, $hasModuleRestrictions)) {
                continue;
            }
            if (($normalized['zone'] ?? '') === 'extensions') {
                $zones['extensions'][] = $normalized;
            } elseif (($normalized['domain'] ?? '') === 'platform' || ($normalized['domain'] ?? '') === 'admin') {
                $zones['platform'][] = $normalized;
            } else {
                $zones['manufacturing'][] = $normalized;
            }
        }

        foreach (self::loadExtensionPlaceholders() as $placeholder) {
            if (!self::isVisible((string)($placeholder['visible_if'] ?? 'always'), $visibility)) {
                continue;
            }
            $widget = self::normalizeConfigWidget($placeholder, $routeMap, $pluginMap);
            if (!self::moduleVisibleForWidget($widget, $allowedModules, $hasModuleRestrictions)) {
                continue;
            }
            $zones['extensions'][] = $widget;
        }

        foreach ($zones as &$zoneWidgets) {
            usort($zoneWidgets, static fn(array $a, array $b): int => ((int)($a['priority'] ?? 99)) <=> ((int)($b['priority'] ?? 99)));
        }
        unset($zoneWidgets);

        $actionLayer = class_exists(ActionBuilder::class)
            ? ActionBuilder::buildHome([
                'role' => (string)($context['role'] ?? ''),
                'loggedIn' => (bool)($context['loggedIn'] ?? false),
                'isAdmin' => (bool)($context['isAdmin'] ?? false),
                'hasAdminToolsAccess' => (bool)($context['hasAdminToolsAccess'] ?? false),
                'canAccessBase' => (bool)($context['canAccessBase'] ?? false),
                'availableGetRoutes' => $availableGetRoutes,
                'availablePostRoutes' => $availablePostRoutes,
                'coverageAvailable' => is_array($coverageDashboard),
            ])
            : ['widget_actions' => [], 'coverage' => ['global' => [], 'states' => []]];

        $widgetActionsMap = (array)($actionLayer['widget_actions'] ?? []);
        foreach ($zones as $zoneKey => $zoneWidgets) {
            foreach ($zoneWidgets as $idx => $widget) {
                $widgetKey = (string)($widget['key'] ?? '');
                $zones[$zoneKey][$idx]['actions'] = array_values((array)($widgetActionsMap[$widgetKey] ?? []));
            }
        }

        $activeModules = ModuleRegistry::getActive();
        $plannedModules = ModuleRegistry::getPlanned();

        return [
            'overview' => [
                'active_plugin_count' => count($activePlugins),
                'active_module_count' => count($activeModules),
                'planned_module_count' => count($plannedModules),
                'app_environment' => (string)($context['appEnvironment'] ?? 'production'),
                'has_module_restrictions' => $hasModuleRestrictions,
                'enabled_modules' => $allowedModules,
            ],
            'zones' => [
                'platform' => [
                    'key' => 'platform',
                    'label_key' => 'dashboard.zone_platform_title',
                    'subtitle_key' => 'dashboard.zone_platform_subtitle',
                    'widgets' => $zones['platform'],
                ],
                'manufacturing' => [
                    'key' => 'manufacturing',
                    'label_key' => 'dashboard.zone_manufacturing_title',
                    'subtitle_key' => 'dashboard.zone_manufacturing_subtitle',
                    'widgets' => $zones['manufacturing'],
                ],
                'coverage' => [
                    'key' => 'coverage',
                    'label_key' => 'dashboard.zone_coverage_title',
                    'subtitle_key' => 'dashboard.zone_coverage_subtitle',
                    'module_key' => 'manufacturing_ipm',
                    'domain' => 'manufacturing',
                    'owner_plugin' => 'Base',
                    'status' => is_array($coverageDashboard) ? 'active' : 'inactive',
                    'actions' => (array)($actionLayer['coverage'] ?? ['global' => [], 'states' => []]),
                ],
                'extensions' => [
                    'key' => 'extensions',
                    'label_key' => 'dashboard.zone_extensions_title',
                    'subtitle_key' => 'dashboard.zone_extensions_subtitle',
                    'widgets' => $zones['extensions'],
                ],
            ],
            'actions' => $actionLayer,
        ];
    }

    /**
     * @param array<string, array<string,mixed>> $moduleIndex
     * @return array<string, array{module_key:string,domain:string}>
     */
    private static function buildOwnerPluginMap(array $moduleIndex): array
    {
        $map = [];
        foreach ($moduleIndex as $module) {
            $moduleKey = (string)($module['key'] ?? '');
            $domain = (string)($module['domain'] ?? 'extension');
            $ownerPluginRaw = trim((string)($module['owner_plugin'] ?? ''));
            if ($moduleKey === '' || $ownerPluginRaw === '') {
                continue;
            }

            foreach (array_map('trim', explode(',', $ownerPluginRaw)) as $pluginName) {
                if ($pluginName === '') {
                    continue;
                }
                $map[$pluginName] = [
                    'module_key' => $moduleKey,
                    'domain' => $domain,
                ];
            }
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $widget
     * @param array<string, array{module_key:string,domain:string}> $ownerPluginMap
     * @param array<string,bool> $routeMap
     * @param array<string,bool> $pluginMap
     * @return array<string,mixed>
     */
    private static function normalizePluginWidget(array $widget, array $ownerPluginMap, array $routeMap, array $pluginMap): array
    {
        $sourcePlugin = trim((string)($widget['source_plugin'] ?? ''));
        $owner = $ownerPluginMap[$sourcePlugin] ?? ['module_key' => 'manufacturing_ipm', 'domain' => 'manufacturing'];
        $url = trim((string)($widget['url'] ?? ''));

        return [
            'key' => (string)$widget['key'],
            'title' => trim((string)($widget['title'] ?? $widget['key'])),
            'label_key' => (string)($widget['label_key'] ?? ''),
            'module_key' => (string)($widget['module_key'] ?? $owner['module_key']),
            'domain' => (string)($widget['domain'] ?? $owner['domain']),
            'owner_plugin' => (string)($widget['owner_plugin'] ?? $sourcePlugin),
            'zone' => (string)($widget['zone'] ?? ($owner['domain'] === 'extension' ? 'extensions' : 'manufacturing')),
            'priority' => (int)($widget['priority'] ?? $widget['order'] ?? 99),
            'visible_if' => (string)($widget['visible_if'] ?? 'always'),
            'status' => (string)($widget['status'] ?? 'active'),
            'is_placeholder' => (bool)($widget['is_placeholder'] ?? false),
            'url' => $url,
            'count' => $widget['count'] ?? null,
            'detail' => (string)($widget['detail'] ?? ''),
            'extra' => (string)($widget['extra'] ?? ''),
            'ready' => ($url !== '' && isset($routeMap[$url])),
            'plugin_active' => ($sourcePlugin === '' || isset($pluginMap[$sourcePlugin])),
        ];
    }

    /**
     * @param array<string,mixed> $def
     * @param array<string,bool> $routeMap
     * @param array<string,bool> $pluginMap
     * @return array<string,mixed>
     */
    private static function normalizeConfigWidget(array $def, array $routeMap, array $pluginMap): array
    {
        $url = trim((string)($def['url'] ?? ''));
        $ownerPlugin = trim((string)($def['owner_plugin'] ?? ''));
        $pluginActive = ($ownerPlugin === '' || isset($pluginMap[$ownerPlugin]));

        return [
            'key' => (string)($def['key'] ?? ''),
            'title' => $def['label_key'] ? t((string)$def['label_key']) : (string)($def['key'] ?? ''),
            'label_key' => (string)($def['label_key'] ?? ''),
            'module_key' => (string)($def['module_key'] ?? ''),
            'domain' => (string)($def['domain'] ?? ''),
            'owner_plugin' => $ownerPlugin,
            'zone' => (string)($def['zone'] ?? ''),
            'priority' => (int)($def['priority'] ?? 99),
            'visible_if' => (string)($def['visible_if'] ?? 'always'),
            'status' => (string)($def['status'] ?? 'active'),
            'is_placeholder' => (bool)($def['is_placeholder'] ?? false),
            'url' => $url,
            'count' => null,
            'detail' => ($def['description_key'] ?? '') !== '' ? t((string)$def['description_key']) : '',
            'extra' => '',
            'ready' => ($url !== '' && isset($routeMap[$url]) && $pluginActive),
            'plugin_active' => $pluginActive,
        ];
    }

    /**
     * @param array<string,mixed> $widget
     * @param array<int,string> $allowedModules
     */
    private static function moduleVisibleForWidget(array $widget, array $allowedModules, bool $hasModuleRestrictions): bool
    {
        if (!$hasModuleRestrictions) {
            return true;
        }

        $moduleKey = (string)($widget['module_key'] ?? '');
        $widgetKey = strtolower((string)($widget['key'] ?? ''));

        if ($moduleKey === 'platform') {
            return true;
        }
        if ($moduleKey === 'admin_tools') {
            return in_array('admin', $allowedModules, true);
        }
        if ($moduleKey === 'manufacturing_ipm') {
            $token = self::inferModuleTokenFromWidgetKey($widgetKey);
            if ($token === '') {
                return false;
            }
            return in_array($token, $allowedModules, true);
        }

        return true;
    }

    private static function inferModuleTokenFromWidgetKey(string $widgetKey): string
    {
        if (str_contains($widgetKey, 'qc')) {
            return 'qc';
        }
        if (str_contains($widgetKey, 'assembly')) {
            return 'assembly';
        }
        if (str_contains($widgetKey, 'dispatch')) {
            return 'dispatch';
        }
        if (str_contains($widgetKey, 'demand')) {
            return 'demands';
        }
        if (str_contains($widgetKey, 'coverage')) {
            return 'coverage';
        }
        if (str_contains($widgetKey, 'production') || str_contains($widgetKey, 'daily') || str_contains($widgetKey, 'pre_order')) {
            return 'production';
        }

        return '';
    }

    /**
     * @param array<string,mixed>|null $coverageDashboard
     * @return array<int,array<string,mixed>>
     */
    private static function buildManufacturingPriorityWidgets(?array $coverageDashboard): array
    {
        if (!is_array($coverageDashboard)) {
            return [];
        }

        $summary = is_array($coverageDashboard['summary'] ?? null) ? $coverageDashboard['summary'] : [];

        return [
            [
                'key' => 'mfg.open_orders',
                'title' => t('dashboard.mfg_open_orders'),
                'module_key' => 'manufacturing_ipm',
                'domain' => 'manufacturing',
                'owner_plugin' => 'DailyOrders',
                'zone' => 'manufacturing',
                'priority' => 1,
                'visible_if' => 'admin_strict',
                'status' => 'active',
                'is_placeholder' => false,
                'url' => '/daily-orders',
                'count' => (int)($summary['open_orders'] ?? 0),
                'detail' => t('dashboard.mfg_open_orders_desc'),
                'extra' => '',
                'ready' => true,
                'plugin_active' => true,
            ],
            [
                'key' => 'mfg.coverage_pct',
                'title' => t('dashboard.mfg_coverage_pct'),
                'module_key' => 'manufacturing_ipm',
                'domain' => 'manufacturing',
                'owner_plugin' => 'Base',
                'zone' => 'manufacturing',
                'priority' => 2,
                'visible_if' => 'role_ops',
                'status' => 'active',
                'is_placeholder' => false,
                'url' => '/manufacturing/coverage',
                'count' => number_format((float)($summary['coverage_pct'] ?? 0), 1, '.', ',') . '%',
                'detail' => t('dashboard.mfg_coverage_pct_desc'),
                'extra' => '',
                'ready' => true,
                'plugin_active' => true,
            ],
            [
                'key' => 'mfg.shortage_qty',
                'title' => t('dashboard.mfg_shortage_qty'),
                'module_key' => 'manufacturing_ipm',
                'domain' => 'manufacturing',
                'owner_plugin' => 'DailyOrders',
                'zone' => 'manufacturing',
                'priority' => 3,
                'visible_if' => 'admin_strict',
                'status' => 'active',
                'is_placeholder' => false,
                'url' => '/daily-orders',
                'count' => number_format((float)($summary['shortage_qty'] ?? 0), 2, '.', ','),
                'detail' => t('dashboard.mfg_shortage_qty_desc'),
                'extra' => '',
                'ready' => true,
                'plugin_active' => true,
            ],
            [
                'key' => 'mfg.forecast_pressure',
                'title' => t('dashboard.mfg_forecast_pressure'),
                'module_key' => 'manufacturing_ipm',
                'domain' => 'manufacturing',
                'owner_plugin' => 'PreOrders',
                'zone' => 'manufacturing',
                'priority' => 4,
                'visible_if' => 'admin_strict',
                'status' => 'active',
                'is_placeholder' => false,
                'url' => '/pre-orders',
                'count' => number_format((float)($summary['forecast_pressure_qty'] ?? 0), 2, '.', ','),
                'detail' => t('dashboard.mfg_forecast_pressure_desc'),
                'extra' => '',
                'ready' => true,
                'plugin_active' => true,
            ],
            [
                'key' => 'mfg.ready_dispatch',
                'title' => t('dashboard.mfg_ready_dispatch'),
                'module_key' => 'manufacturing_ipm',
                'domain' => 'manufacturing',
                'owner_plugin' => 'DispatchEntries',
                'zone' => 'manufacturing',
                'priority' => 5,
                'visible_if' => 'admin_or_dispatch',
                'status' => 'active',
                'is_placeholder' => false,
                'url' => '/dispatch-entries/leader',
                'count' => (int)($summary['full_orders'] ?? 0),
                'detail' => t('dashboard.mfg_ready_dispatch_desc'),
                'extra' => '',
                'ready' => true,
                'plugin_active' => true,
            ],
            [
                'key' => 'mfg.qc_pressure',
                'title' => t('dashboard.mfg_qc_pressure'),
                'module_key' => 'manufacturing_ipm',
                'domain' => 'manufacturing',
                'owner_plugin' => 'QCEntries',
                'zone' => 'manufacturing',
                'priority' => 6,
                'visible_if' => 'admin_or_qc',
                'status' => 'active',
                'is_placeholder' => false,
                'url' => '/qc-entries/leader',
                'count' => (int)($summary['low_orders'] ?? 0),
                'detail' => t('dashboard.mfg_qc_pressure_desc'),
                'extra' => '',
                'ready' => true,
                'plugin_active' => true,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function loadCoreWidgets(): array
    {
        $config = self::loadConfig();
        return array_values((array)($config['core_widgets'] ?? []));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function loadExtensionPlaceholders(): array
    {
        $config = self::loadConfig();
        return array_values((array)($config['extension_placeholders'] ?? []));
    }

    /**
     * @return array<string,mixed>
     */
    private static function loadConfig(): array
    {
        $path = APP_ROOT . '/app/Dashboard/widgets.php';
        if (!is_file($path)) {
            return [];
        }

        $config = require $path;
        return is_array($config) ? $config : [];
    }

    /**
     * @param array<string,mixed> $visibility
     */
    private static function isVisible(string $rule, array $visibility): bool
    {
        return AclPolicy::allowsVisibilityRule($rule, $visibility);
    }
}
