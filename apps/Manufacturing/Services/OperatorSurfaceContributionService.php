<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

final class OperatorSurfaceContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>|array<int,array<string,mixed>>
     */
    public static function contribute(array $request): array
    {
        $surface = strtolower(trim((string)($request['surface'] ?? '')));
        $region = strtolower(trim((string)($request['region'] ?? '')));
        $context = is_array($request['context'] ?? null) ? (array)$request['context'] : [];

        if ($surface !== 'operator') {
            return [];
        }
        if (!self::isManufacturingAssigned($context)) {
            return [];
        }

        return match ($region) {
            'sidebar' => self::sidebarContribution($context),
            'focus_views' => self::focusViewContribution(),
            'data_exchange' => self::dataExchangeContribution($context),
            'breadcrumb_parents' => self::breadcrumbParentContribution(),
            'focus_labels' => self::focusLabelContribution(),
            'bottom_actions' => self::bottomActionContribution(),
            default => [],
        };
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function sidebarContribution(array $context): array
    {
        $canSeeView = self::canSeeViewFactory($context);

        $items = [];
        if ($canSeeView('production', ['production', 'demands', 'coverage'])) {
            $items[] = [
                'icon' => '🏭',
                'label' => self::tr('operator.sidebar.production_control', 'Production'),
                'route' => '/u/{user}/production',
                'badge' => null,
            ];
        }
        if ($canSeeView('demand', ['demands'])) {
            $items[] = [
                'icon' => '📈',
                'label' => self::tr('operator.sidebar.demand', 'Demand'),
                'route' => '/u/{user}/demand',
                'badge' => null,
            ];
        }
        if ($canSeeView('orders', ['demands'])) {
            $items[] = [
                'icon' => '📋',
                'label' => self::tr('operator.sidebar.orders', 'Orders'),
                'route' => '/u/{user}/orders',
                'badge' => null,
            ];
        }
        if ($canSeeView('parts', ['materials'])) {
            $items[] = [
                'icon' => '🧩',
                'label' => self::tr('operator.sidebar.parts', 'Parts'),
                'route' => '/u/{user}/parts',
                'badge' => null,
            ];
        }
        if ($canSeeView('coverage', ['coverage'])) {
            $items[] = [
                'icon' => '📶',
                'label' => self::tr('operator.sidebar.coverage', 'Coverage'),
                'route' => '/u/{user}/coverage',
                'badge' => null,
            ];
        }
        if ($canSeeView('machines', ['production'])) {
            $items[] = [
                'icon' => '🛠️',
                'label' => self::tr('operator.sidebar.machines', 'Machines'),
                'route' => '/u/{user}/machines',
                'badge' => null,
            ];
        }
        if ($canSeeView('processing', ['production', 'assembly', 'qc'])) {
            $items[] = [
                'icon' => '⚙️',
                'label' => self::tr('operator.sidebar.processing_control', 'Processing'),
                'route' => '/u/{user}/processing',
                'badge' => null,
            ];
        }
        if ($canSeeView('assembly', ['assembly'])) {
            $items[] = [
                'icon' => '🔧',
                'label' => self::tr('operator.sidebar.assembly', 'Assembly'),
                'route' => '/u/{user}/assembly',
                'badge' => null,
            ];
        }
        if ($canSeeView('qc', ['qc'])) {
            $items[] = [
                'icon' => '🧪',
                'label' => self::tr('operator.sidebar.qc', 'Quality Control'),
                'route' => '/u/{user}/qc',
                'badge' => null,
            ];
        }
        if ($canSeeView('fulfillment', ['dispatch'])) {
            $items[] = [
                'icon' => '🚚',
                'label' => self::tr('operator.sidebar.dispatch', 'Dispatch'),
                'route' => '/u/{user}/fulfillment?tab=dispatch',
                'badge' => null,
            ];
        }
        if ($canSeeView('preparation', ['dispatch'])) {
            $items[] = [
                'icon' => '📦',
                'label' => self::tr('operator.sidebar.preparation', 'Preparation'),
                'route' => '/u/{user}/fulfillment?tab=prepare',
                'badge' => null,
            ];
        }
        if ($canSeeView('materials', ['materials'])) {
            $items[] = [
                'icon' => '📦',
                'label' => self::tr('operator.sidebar.materials', 'Materials'),
                'route' => '/u/{user}/materials',
                'badge' => null,
            ];
        }

        if ($items === []) {
            return [];
        }

        return [
            'sections' => [
                [
                    'title' => self::tr('operator.sidebar.operations', 'Operations'),
                    'items' => $items,
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function focusViewContribution(): array
    {
        return [
            'view_map' => [
                'production' => APP_ROOT . '/apps/Manufacturing/Views/operator/production.php',
                'processing' => APP_ROOT . '/apps/Manufacturing/Views/operator/processing.php',
                'fulfillment' => APP_ROOT . '/apps/Manufacturing/Views/operator/fulfillment.php',
                'materials' => APP_ROOT . '/apps/Manufacturing/Views/operator/materials.php',
                'assembly' => APP_ROOT . '/apps/Manufacturing/Views/operator/assembly.php',
                'coverage' => APP_ROOT . '/apps/Manufacturing/Views/operator/coverage.php',
                'demand' => APP_ROOT . '/apps/Manufacturing/Views/operator/demand.php',
                'dispatch' => APP_ROOT . '/apps/Manufacturing/Views/operator/dispatch.php',
                'dispatch_adapter' => APP_ROOT . '/apps/Manufacturing/Views/operator/dispatch-adapter.php',
                'dispatch_detail' => APP_ROOT . '/apps/Manufacturing/Views/operator/dispatch-detail.php',
                'machines' => APP_ROOT . '/apps/Manufacturing/Views/operator/machines.php',
                'orders' => APP_ROOT . '/apps/Manufacturing/Views/operator/orders.php',
                'preparation' => APP_ROOT . '/apps/Manufacturing/Views/operator/preparation.php',
                'qc' => APP_ROOT . '/apps/Manufacturing/Views/operator/qc.php',
                'handoff' => APP_ROOT . '/apps/Manufacturing/Views/operator/handoff.php',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function dataExchangeContribution(array $context): array
    {
        $canSeeAny = self::canSeeAnyFactory($context);
        $definitions = [];

        if ($canSeeAny(['production'])) {
            $definitions[] = [
                'key' => 'mfg.production_daily',
                'app_key' => 'manufacturing',
                'module_key' => 'production',
                'title' => self::tr('operator.data_exchange.mfg.production_daily.title', 'Production Daily Import/Export'),
                'description' => self::tr('operator.data_exchange.mfg.production_daily.desc', 'Exchange production output, reject quantities, and shift summaries.'),
                'supports_import' => true,
                'supports_export' => true,
                'template_name' => self::tr('operator.data_exchange.template.production_daily', 'production_daily_template.csv'),
                'governance_mode' => 'approval_required',
                'import_columns' => ['production_date', 'machine_no', 'part_number', 'produced_qty', 'rejected_qty', 'shift'],
                'export_columns' => ['production_date', 'machine_no', 'part_number', 'produced_qty', 'rejected_qty', 'shift'],
            ];
        }

        if ($canSeeAny(['orders', 'demand', 'production'])) {
            $definitions[] = [
                'key' => 'mfg.daily_orders',
                'app_key' => 'manufacturing',
                'module_key' => 'daily_orders',
                'title' => self::tr('operator.data_exchange.mfg.daily_orders.title', 'Daily Order Import/Export'),
                'description' => self::tr('operator.data_exchange.mfg.daily_orders.desc', 'Exchange daily order demand rows with customer, part, quantity, and schedule fields.'),
                'supports_import' => true,
                'supports_export' => true,
                'template_name' => self::tr('operator.data_exchange.template.daily_orders', 'daily_orders_template.csv'),
                'governance_mode' => 'approval_required',
                'import_columns' => ['order_date', 'required_date', 'customer_name', 'product_id', 'parts_number', 'parts_name', 'qty', 'dispatch_deadline', 'status', 'notes'],
                'export_columns' => ['order_date', 'required_date', 'customer_name', 'product_id', 'parts_number', 'parts_name', 'qty', 'dispatch_deadline', 'status', 'notes'],
            ];
        }

        if ($canSeeAny(['dispatch', 'fulfillment', 'dispatch-adapter'])) {
            $definitions[] = [
                'key' => 'mfg.dispatch_manifest',
                'app_key' => 'manufacturing',
                'module_key' => 'dispatch',
                'title' => self::tr('operator.data_exchange.mfg.dispatch_manifest.title', 'Dispatch Manifest Import/Export'),
                'description' => self::tr('operator.data_exchange.mfg.dispatch_manifest.desc', 'Exchange dispatch plans, routes, and confirmation status.'),
                'supports_import' => true,
                'supports_export' => true,
                'template_name' => self::tr('operator.data_exchange.template.dispatch_manifest', 'dispatch_manifest_template.csv'),
                'governance_mode' => 'approval_required',
                'import_columns' => ['dispatch_date', 'route_code', 'part_number', 'dispatch_qty', 'truck_no', 'driver_name', 'status'],
                'export_columns' => ['dispatch_date', 'route_code', 'part_number', 'dispatch_qty', 'truck_no', 'driver_name', 'status'],
            ];
        }

        if ($definitions === []) {
            return [];
        }

        return ['definitions' => $definitions];
    }

    /**
     * @return array<string,array<string,array<string,string>>>
     */
    private static function breadcrumbParentContribution(): array
    {
        $productionParent = [
            'label' => self::tr('wrapper.operator.focus.production', 'Production'),
            'route' => '/u/{user}/production',
        ];
        $processingParent = [
            'label' => self::tr('wrapper.operator.focus.processing', 'Processing'),
            'route' => '/u/{user}/processing',
        ];
        $fulfillmentParent = [
            'label' => self::tr('wrapper.operator.focus.fulfillment', 'Fulfillment'),
            'route' => '/u/{user}/fulfillment',
        ];

        return [
            'parents' => [
                'demand' => $productionParent,
                'orders' => $productionParent,
                'machines' => $productionParent,
                'coverage' => $productionParent,
                'parts' => $productionParent,
                'parts-detail' => $productionParent,
                'materials' => $productionParent,
                'assembly' => $processingParent,
                'qc' => $processingParent,
                'dispatch' => $fulfillmentParent,
                'dispatch-detail' => $fulfillmentParent,
                'dispatch-adapter' => $fulfillmentParent,
                'preparation' => $fulfillmentParent,
                'processing' => $fulfillmentParent,
            ],
        ];
    }

    /**
     * @return array<string,array<string,string>>
     */
    private static function focusLabelContribution(): array
    {
        return [
            'labels' => [
                'production' => self::tr('wrapper.operator.focus.production', 'Production'),
                'demand' => self::tr('wrapper.operator.focus.demand', 'Demand'),
                'orders' => self::tr('wrapper.operator.focus.orders', 'Daily Orders'),
                'processing' => self::tr('wrapper.operator.focus.processing', 'Processing'),
                'preparation' => self::tr('wrapper.operator.focus.preparation', 'Preparation'),
                'assembly' => self::tr('wrapper.operator.focus.assembly', 'Assembly'),
                'dispatch' => self::tr('wrapper.operator.focus.dispatch', 'Dispatch'),
                'dispatch-detail' => self::tr('wrapper.operator.focus.dispatch_detail', 'Dispatch Detail'),
                'dispatch-adapter' => self::tr('wrapper.operator.focus.dispatch_adapter', 'Dispatch'),
                'qc' => self::tr('wrapper.operator.focus.qc', 'Quality Control'),
                'fulfillment' => self::tr('wrapper.operator.focus.fulfillment', 'Fulfillment'),
                'parts' => self::tr('wrapper.operator.focus.parts', 'Parts'),
                'parts-detail' => self::tr('wrapper.operator.focus.parts_detail', 'Part Detail'),
                'machines' => self::tr('wrapper.operator.focus.machines', 'Machines'),
                'materials' => self::tr('wrapper.operator.focus.materials', 'Materials'),
                'coverage' => self::tr('wrapper.operator.focus.coverage', 'Coverage'),
            ],
        ];
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private static function bottomActionContribution(): array
    {
        return [
            'action_sets' => [
                'production' => [
                    ['icon' => 'work_entry', 'label' => self::tr('operator.bottom_nav.action.work_entry', 'Log Work Entry'), 'href' => '/u/{user}/work-entry?action=update'],
                    ['icon' => 'orders', 'label' => self::tr('operator.bottom_nav.action.orders', 'Daily Orders'), 'href' => '/u/{user}/orders'],
                    ['icon' => 'demand', 'label' => self::tr('operator.bottom_nav.action.demand', 'Demand'), 'href' => '/u/{user}/demand'],
                    ['icon' => 'machines', 'label' => self::tr('operator.bottom_nav.action.machines', 'Machines'), 'href' => '/u/{user}/machines'],
                ],
                'demand' => 'production',
                'orders' => 'production',
                'machines' => 'production',
                'coverage' => 'production',
                'parts' => 'production',
                'parts-detail' => 'production',
                'materials' => 'production',
                'work-entry' => [
                    ['icon' => 'orders', 'label' => self::tr('operator.bottom_nav.action.orders', 'Daily Orders'), 'href' => '/u/{user}/orders'],
                    ['icon' => 'plan', 'label' => self::tr('operator.bottom_nav.action.production', 'Production'), 'href' => '/u/{user}/production'],
                    ['icon' => 'critical', 'label' => self::tr('operator.bottom_nav.action.critical', 'Critical Items'), 'href' => '/u/{user}/critical'],
                ],
                'fulfillment' => [
                    ['icon' => 'work_entry', 'label' => self::tr('operator.bottom_nav.action.work_entry', 'Log Work Entry'), 'href' => '/u/{user}/work-entry?action=update'],
                    ['icon' => 'processing', 'label' => self::tr('operator.bottom_nav.action.processing', 'Processing'), 'href' => '/u/{user}/processing'],
                    ['icon' => 'qc', 'label' => self::tr('operator.bottom_nav.action.qc', 'Quality Control'), 'href' => '/u/{user}/qc'],
                ],
                'dispatch' => 'fulfillment',
                'dispatch-detail' => 'fulfillment',
                'dispatch-adapter' => 'fulfillment',
                'processing' => 'fulfillment',
                'assembly' => 'fulfillment',
                'qc' => 'fulfillment',
                'preparation' => 'fulfillment',
                'default' => [
                    ['icon' => 'work_entry', 'label' => self::tr('operator.bottom_nav.action.work_entry', 'Log Work Entry'), 'href' => '/u/{user}/work-entry?action=update'],
                    ['icon' => 'orders', 'label' => self::tr('operator.bottom_nav.action.orders', 'Daily Orders'), 'href' => '/u/{user}/orders'],
                    ['icon' => 'critical', 'label' => self::tr('operator.bottom_nav.action.critical', 'Critical Items'), 'href' => '/u/{user}/critical'],
                    ['icon' => 'demand', 'label' => self::tr('operator.bottom_nav.action.demand', 'Demand'), 'href' => '/u/{user}/demand'],
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function isManufacturingAssigned(array $context): bool
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );

        return in_array('manufacturing', $apps, true);
    }

    /**
     * @param array<string,mixed> $context
     * @return \Closure(array<int,string>):bool
     */
    private static function canSeeAnyFactory(array $context): \Closure
    {
        $moduleVisibility = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['module_visibility'] ?? [])
        );

        return static function (array $keys) use ($moduleVisibility): bool {
            if ($moduleVisibility === []) {
                return true;
            }
            foreach ($keys as $key) {
                if (in_array(strtolower(trim((string)$key)), $moduleVisibility, true)) {
                    return true;
                }
            }
            return false;
        };
    }

    /**
     * @param array<string,mixed> $context
     * @return \Closure(string, array<int,string>):bool
     */
    private static function canSeeViewFactory(array $context): \Closure
    {
        $rawOperatorViews = trim((string)($context['operator_views'] ?? ''));
        $explicitOperatorViews = self::normalizeOperatorViews($rawOperatorViews);
        $canSeeAny = self::canSeeAnyFactory($context);

        return static function (string $viewKey, array $moduleKeys = []) use ($rawOperatorViews, $explicitOperatorViews, $canSeeAny): bool {
            if ($rawOperatorViews !== '') {
                return in_array(strtolower(trim($viewKey)), $explicitOperatorViews, true);
            }
            return $moduleKeys === [] || $canSeeAny($moduleKeys);
        };
    }

    /**
     * @return array<int,string>
     */
    private static function normalizeOperatorViews(string $csv): array
    {
        $allowed = array_flip([
            'dashboard', 'work-entry', 'data-exchange', 'critical', 'recent', 'tasks',
            'production', 'demand', 'orders', 'parts', 'coverage', 'machines',
            'processing', 'assembly', 'qc', 'fulfillment', 'preparation', 'dispatch',
            'materials', 'handoff', 'account', 'notifications', 'messages', 'preferences',
            'sbaio',
        ]);
        $aliases = [
            'parts-detail' => 'parts',
            'dispatch-detail' => 'dispatch',
            'dispatch-adapter' => 'dispatch',
            'alerts' => 'notifications',
        ];

        $out = [];
        foreach (preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [] as $token) {
            $token = trim($token);
            $token = $aliases[$token] ?? $token;
            if ($token !== '' && isset($allowed[$token])) {
                $out[$token] = $token;
            }
        }

        return array_values($out);
    }

    private static function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }
        return strtr($fallback, $replace);
    }
}
