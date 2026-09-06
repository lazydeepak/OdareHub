<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;

require_once __DIR__ . '/ModuleSurfaceWidgetFactory.php';

final class HostSurfaceContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>|array<int,array<string,mixed>>
     */
    public static function contribute(array $request): array
    {
        $surface = trim((string)($request['surface'] ?? ''));
        $region = trim((string)($request['region'] ?? ''));
        $context = is_array($request['context'] ?? null) ? (array)$request['context'] : [];

        return match ($surface . ':' . $region) {
            'me:header_actions' => self::meHeaderActions($context),
            'me:summary_cards' => self::meSummaryCards($context),
            'me:quick_links' => self::meQuickLinks($context),
            'me:monitoring_sections' => self::meMonitoringSections($context),
            'approval_inbox:header_actions' => self::approvalHeaderActions(),
            'role_inbox:header_actions' => self::roleInboxHeaderActions(),
            default => [],
        };
    }

    /**
     * Get operational focus values supported by Manufacturing app.
     *
     * @return array<string,string> Map of focus key to localized label
     */
    public static function operationalFocusValues(): array
    {
        return [
            'production' => t('ops.operational_focus.production'),
            'assembly' => t('ops.operational_focus.assembly'),
            'qc' => t('ops.operational_focus.qc'),
            'dispatch' => t('ops.operational_focus.dispatch'),
            'planner' => t('ops.operational_focus.planner'),
        ];
    }

    /**
     * Get per-app operational role values supported by Manufacturing.
     *
     * @return array<string,string> Map of role key to localized label
     */
    public static function operationalRoleValues(): array
    {
        return [
            'my_work' => t('ops.access_control.app_role.role.my_work'),
            'operator' => t('ops.access_control.app_role.role.operator'),
            'production_leader' => t('ops.access_control.app_role.role.production_leader'),
            'assembly_leader' => t('ops.access_control.app_role.role.assembly_leader'),
            'qc_leader' => t('ops.access_control.app_role.role.qc_leader'),
            'dispatch_leader' => t('ops.access_control.app_role.role.dispatch_leader'),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function meHeaderActions(array $context): array
    {
        $user = is_array($context['user'] ?? null) ? (array)$context['user'] : [];
        $roleSlug = strtolower(trim((string)($user['role'] ?? '')));
        $roleSlug = str_replace([' ', '-'], '', $roleSlug);

        $actions = [
            [
                'label' => t('nav.manufacturing_portal'),
                'url' => '/apps/manufacturing',
                'weight' => 5,
            ],
        ];

        if (in_array($roleSlug, ['productionleader', 'assemblyleader', 'productionoperations'], true)) {
            $actions[] = ['label' => t('nav.machine_leader'), 'url' => '/apps/manufacturing/production-workboard', 'weight' => 10];
        }
        if (in_array($roleSlug, ['qcleader', 'qcoperations'], true)) {
            $actions[] = ['label' => t('nav.qc_leader'), 'url' => '/apps/manufacturing/qc-workboard', 'weight' => 11];
        }
        if (in_array($roleSlug, ['dispatchleader', 'dispatchoperations'], true)) {
            $actions[] = ['label' => t('nav.dispatch_ops'), 'url' => '/apps/manufacturing/dispatch-ops', 'weight' => 12];
        }

        $actions[] = ['label' => t('nav.coverage_dashboard'), 'url' => '/apps/manufacturing/coverage', 'weight' => 15];
        $actions[] = ['label' => t('nav.demand_workspace'), 'url' => '/apps/manufacturing/demands', 'weight' => 16];
        $actions[] = ['label' => 'Materials & Supply', 'url' => '/apps/manufacturing/materials', 'weight' => 17];

        return self::annotateActionWidgets($actions);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meSummaryCards(array $context = []): array
    {
        $lowCoverage = self::countOpenOrders("LOWER(COALESCE(coverage_status, '')) IN ('low','partial')");
        $urgentRisk = self::countOpenOrders("COALESCE(shortage_qty, 0) > 0");
        $pendingApprovals = self::pendingApprovalCount();
        $materialSummary = self::materialSummary();
        $materialRisk = (int)($materialSummary['shortage_risk_count'] ?? 0);

        $cards = [
            [
                'key' => 'coverage_dashboard',
                'title' => t('nav.coverage_dashboard'),
                'value' => $lowCoverage,
                'meta' => t('ops.supervisor_cockpit.kpi_due_soon_low_coverage'),
                'url' => '/apps/manufacturing/coverage',
                'tone' => 'warn',
                'weight' => 10,
            ],
            [
                'key' => 'high_risk_orders',
                'title' => t('ops.supervisor_cockpit.kpi_high_risk_orders'),
                'value' => $urgentRisk,
                'meta' => t('ops.my_work.reason.waiting_upstream'),
                'url' => '/apps/manufacturing/demands',
                'tone' => 'danger',
                'weight' => 11,
            ],
            [
                'key' => 'approval_inbox',
                'title' => t('nav.approval_inbox'),
                'value' => $pendingApprovals,
                'meta' => t('ops.common.awaiting_approval'),
                'url' => '/ops/approval-inbox',
                'tone' => 'info',
                'weight' => 14,
            ],
        ];

        $cards = array_merge($cards, self::moduleWidgets('summary_cards', $context));

        if ($materialSummary !== []) {
            $cards[] = [
                'key' => 'materials_risk',
                'title' => 'Materials & Supply',
                'value' => $materialRisk,
                'meta' => 'Material shortage risk requiring planning action.',
                'url' => '/apps/manufacturing/materials/stock?status=Critical',
                'tone' => $materialRisk > 0 ? 'danger' : 'success',
                'weight' => 16,
            ];
        }

        return self::annotateSummaryWidgets($cards);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function meQuickLinks(array $context): array
    {
        $links = [
            [
                'key' => 'manufacturing_portal',
                'label' => t('nav.manufacturing_portal'),
                'url' => '/apps/manufacturing',
                'description' => t('ops.my_work.primary_work_area'),
                'weight' => 18,
            ],
            [
                'label' => t('nav.coverage_dashboard'),
                'key' => 'coverage',
                'url' => '/apps/manufacturing/coverage',
                'description' => t('ops.common.cross_functional_work'),
                'weight' => 20,
            ],
            [
                'label' => t('nav.demand_workspace'),
                'key' => 'demands',
                'url' => '/apps/manufacturing/demands',
                'description' => t('ops.common.cross_functional_work'),
                'weight' => 21,
            ],
            [
                'label' => t('nav.dispatch_ops'),
                'key' => 'dispatch_ops',
                'url' => '/apps/manufacturing/dispatch-ops',
                'description' => t('ops.common.cross_functional_work'),
                'weight' => 22,
            ],
            [
                'label' => 'Materials & Supply',
                'key' => 'materials',
                'url' => '/apps/manufacturing/materials',
                'description' => 'Material stock, coverage, and planning command surface.',
                'weight' => 22,
            ],
            [
                'label' => t('nav.approval_inbox'),
                'key' => 'approval_inbox',
                'url' => '/ops/approval-inbox',
                'description' => t('ops.common.awaiting_approval'),
                'weight' => 23,
            ],
        ];

        return self::annotateReferenceWidgets($links);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meMonitoringSections(array $context = []): array
    {
        return self::annotateMonitoringWidgets(self::moduleWidgets('monitoring_sections', $context));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function approvalHeaderActions(): array
    {
        return self::annotateActionWidgets([
            ['label' => t('nav.coverage_dashboard'), 'url' => '/apps/manufacturing/coverage', 'weight' => 10],
            ['label' => t('nav.demand_workspace'), 'url' => '/apps/manufacturing/demands', 'weight' => 11],
        ], ['leader', 'admin']);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function roleInboxHeaderActions(): array
    {
        return self::annotateActionWidgets([
            ['label' => t('nav.manufacturing_portal'), 'url' => '/apps/manufacturing', 'weight' => 10],
            ['label' => t('nav.coverage_dashboard'), 'url' => '/apps/manufacturing/coverage', 'weight' => 11],
            ['label' => 'Materials & Supply', 'url' => '/apps/manufacturing/materials', 'weight' => 12],
        ], ['worker', 'leader', 'admin']);
    }

    /**
     * @return array<string,mixed>
     */
    private static function materialSummary(): array
    {
        if (!class_exists('Plugins\\MaterialManagement\\Services\\MaterialManagementService')) {
            return [];
        }

        if (!class_exists('Plugins\\MaterialManagement\\Services\\MaterialAccessService')) {
            return [];
        }

        if (!\Plugins\MaterialManagement\Services\MaterialAccessService::canAccessAny()) {
            return [];
        }

        return (array)\Plugins\MaterialManagement\Services\MaterialManagementService::summary();
    }

    private static function countOpenOrders(string $extraWhere): int
    {
        if (!self::tableExists('daily_orders')) {
            return 0;
        }

        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total
             FROM daily_orders
             WHERE LOWER(COALESCE(status,'')) NOT IN ('closed','completed','cancelled','canceled')
               AND {$extraWhere}"
        );

        return (int)($row['total'] ?? 0);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function moduleWidgets(string $region, array $context = []): array
    {
        $widgets = [];
        foreach (self::moduleWidgetProviders() as $provider) {
            $regions = array_values((array)($provider['regions'] ?? []));
            if (!in_array($region, $regions, true)) {
                continue;
            }

            $class = trim((string)($provider['class'] ?? ''));
            $file = trim((string)($provider['file'] ?? ''));
            if ($class === '' || $file === '' || !is_file($file)) {
                continue;
            }

            if (!self::isProviderModuleActive($file)) {
                continue;
            }

            if (!class_exists($class, false)) {
                require_once $file;
            }

            if (!class_exists($class) || !method_exists($class, 'contribute')) {
                continue;
            }

            try {
                $result = $class::contribute($region, $context);
            } catch (\Throwable $e) {
                continue;
            }

            if (!is_array($result)) {
                continue;
            }

            foreach ($result as $item) {
                if (is_array($item)) {
                    $widgets[] = $item;
                }
            }
        }

        return $widgets;
    }

    private static function isProviderModuleActive(string $providerFile): bool
    {
        static $statusCache = [];

        $moduleDir = dirname(dirname($providerFile));
        $manifestPath = $moduleDir . '/plugin.json';
        if (!is_file($manifestPath)) {
            return false;
        }

        $moduleName = basename($moduleDir);
        try {
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (is_array($manifest) && trim((string)($manifest['name'] ?? '')) !== '') {
                $moduleName = trim((string)$manifest['name']);
            }
        } catch (\Throwable $e) {
            return false;
        }

        if (isset($statusCache[$moduleName])) {
            return (bool)$statusCache[$moduleName];
        }

        try {
            $row = DB::fetchOne('SELECT status FROM installed_plugins WHERE name = ? LIMIT 1', [$moduleName]);
            $statusCache[$moduleName] = strtolower(trim((string)($row['status'] ?? 'inactive'))) === 'active';
        } catch (\Throwable $e) {
            $statusCache[$moduleName] = false;
        }

        return (bool)$statusCache[$moduleName];
    }

    private static function pendingApprovalCount(): int
    {
        $tables = ['production_plans', 'qc_entries', 'dispatch_entries'];
        $total = 0;
        foreach ($tables as $table) {
            if (!self::tableExists($table)) {
                continue;
            }
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS total
                 FROM {$table}
                 WHERE LOWER(COALESCE(approval_status,'')) IN ('pending approval','reopened','draft')"
            );
            $total += (int)($row['total'] ?? 0);
        }

        return $total;
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $actions
     * @param array<int,string> $profiles
     * @return array<int,array<string,mixed>>
     */
    private static function annotateActionWidgets(array $actions, array $profiles = ['worker', 'leader', 'admin']): array
    {
        return array_values(array_map(static function (array $action) use ($profiles): array {
            $action['widget_key'] = (string)($action['widget_key'] ?? self::widgetKeyFromLabel((string)($action['label'] ?? 'action')));
            $action['view_kind'] = (string)($action['view_kind'] ?? 'cards');
            $action['widget_type'] = 'action';
            $action['placement_zone'] = 'operator_actions';
            $action['interaction_profiles'] = array_values((array)($action['interaction_profiles'] ?? $profiles));
            return $action;
        }, $actions));
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     * @return array<int,array<string,mixed>>
     */
    private static function annotateSummaryWidgets(array $cards): array
    {
        return array_values(array_map(static function (array $card): array {
            $tone = strtolower(trim((string)($card['tone'] ?? '')));
            $card['widget_key'] = (string)($card['widget_key'] ?? $card['key'] ?? self::widgetKeyFromLabel((string)($card['title'] ?? 'summary')));
            $card['view_kind'] = (string)($card['view_kind'] ?? 'kpi');
            $card['widget_type'] = (string)($card['widget_type'] ?? (in_array($tone, ['warn', 'danger'], true) ? 'alert' : 'informative'));
            $card['placement_zone'] = (string)($card['placement_zone'] ?? 'dashboard_summary');
            $card['interaction_profiles'] = array_values((array)($card['interaction_profiles'] ?? ['worker', 'leader', 'admin', 'read_only']));
            return $card;
        }, $cards));
    }

    /**
     * @param array<int,array<string,mixed>> $links
     * @return array<int,array<string,mixed>>
     */
    private static function annotateReferenceWidgets(array $links): array
    {
        return array_values(array_map(static function (array $link): array {
            $link['widget_key'] = (string)($link['widget_key'] ?? $link['key'] ?? self::widgetKeyFromLabel((string)($link['label'] ?? 'reference')));
            $link['view_kind'] = (string)($link['view_kind'] ?? 'cards');
            $link['widget_type'] = (string)($link['widget_type'] ?? 'reference');
            $link['placement_zone'] = (string)($link['placement_zone'] ?? 'supporting_visibility');
            $link['interaction_profiles'] = array_values((array)($link['interaction_profiles'] ?? ['worker', 'leader', 'admin', 'read_only']));
            return $link;
        }, $links));
    }

    /**
     * @param array<int,array<string,mixed>> $sections
     * @return array<int,array<string,mixed>>
     */
    private static function annotateMonitoringWidgets(array $sections): array
    {
        return array_values(array_map(static function (array $section): array {
            $section['widget_key'] = (string)($section['widget_key'] ?? self::widgetKeyFromLabel((string)($section['title'] ?? 'monitoring')));
            $section['view_kind'] = (string)($section['view_kind'] ?? strtolower(trim((string)($section['kind'] ?? 'table'))));
            $section['widget_type'] = (string)($section['widget_type'] ?? 'queue');
            $section['placement_zone'] = (string)($section['placement_zone'] ?? 'monitoring');
            $section['interaction_profiles'] = array_values((array)($section['interaction_profiles'] ?? ['worker', 'leader', 'admin', 'read_only']));
            return $section;
        }, $sections));
    }

    private static function widgetKeyFromLabel(string $label): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($label)) ?? '');
        $normalized = trim($normalized, '_');
        return $normalized !== '' ? $normalized : 'widget';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function moduleWidgetProviders(): array
    {
        return [
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\Coverage\\Services\\CoverageWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/Coverage/Services/CoverageWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\DailyOrders\\Services\\DailyOrderWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/DailyOrders/Services/DailyOrderWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\Machines\\Services\\MachinesWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/Machines/Services/MachinesWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\ProductionEntries\\Services\\ProductionEntryWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/ProductionEntries/Services/ProductionEntryWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\QCEntries\\Services\\QCEntryWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/QCEntries/Services/QCEntryWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\ProductionPlans\\Services\\ProductionPlanWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/ProductionPlans/Services/ProductionPlanWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\ProductionQueue\\Services\\ProductionQueueWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/ProductionQueue/Services/ProductionQueueWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Apps\\Manufacturing\\Modules\\AssemblyPlans\\Services\\AssemblyPlanWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyPlans/Services/AssemblyPlanWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Apps\\Manufacturing\\Modules\\AssemblyEntries\\Services\\AssemblyEntryWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/AssemblyEntries/Services/AssemblyEntryWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\DispatchEntries\\Services\\DispatchEntryWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/DispatchEntries/Services/DispatchEntryWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\Products\\Services\\ProductWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/Products/Services/ProductWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\MaterialManagement\\Services\\MaterialManagementWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialManagementWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\PartMachineMap\\Services\\PartMachineMapWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/PartMachineMap/Services/PartMachineMapWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\PreOrders\\Services\\PreOrderWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/PreOrders/Services/PreOrderWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\Ledger\\Services\\LedgerWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/Ledger/Services/LedgerWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'monitoring_sections'],
                'class' => 'Plugins\\QCPlans\\Services\\QCPlanWidgetRegistry',
                'file' => APP_ROOT . '/apps/Manufacturing/modules/QCPlans/Services/QCPlanWidgetRegistry.php',
            ],
        ];
    }

    /**
     * Get Manufacturing plugin card metadata for experience layout configuration.
     * Returns card key, label, and URL for each Manufacturing card available in the
     * access control experience layout builder.
     *
     * @return array<string,array<string,string>> Map of card key to metadata (label, url)
     */
    public static function getPluginCardMetadata(): array
    {
        return [
            'manufacturing_portal' => [
                'label' => t('admin.experience_layout.manufacturing_portal'),
                'url' => '/apps/manufacturing',
            ],
            'daily_orders' => [
                'label' => t('admin.experience_layout.daily_orders'),
                'url' => '/apps/manufacturing/daily-orders',
            ],
            'production_plans' => [
                'label' => t('admin.experience_layout.production_plans'),
                'url' => '/apps/manufacturing/production-plans',
            ],
            'dispatch_entries' => [
                'label' => t('admin.experience_layout.dispatch_entries'),
                'url' => '/apps/manufacturing/dispatch-entries',
            ],
            'machine_workboard' => [
                'label' => t('admin.experience_layout.machine_workboard'),
                'url' => '/apps/manufacturing/production-workboard',
            ],
            'qc_workboard' => [
                'label' => t('admin.experience_layout.qc_workboard'),
                'url' => '/apps/manufacturing/qc-workboard',
            ],
            'dispatch_ops' => [
                'label' => t('admin.experience_layout.dispatch_ops'),
                'url' => '/apps/manufacturing/dispatch-ops',
            ],
            'coverage' => [
                'label' => t('admin.experience_layout.coverage_analytics'),
                'url' => '/apps/manufacturing/coverage',
            ],
            'materials' => [
                'label' => t('admin.experience_layout.material_stock'),
                'url' => '/apps/manufacturing/materials',
            ],
            'demands' => [
                'label' => t('admin.experience_layout.demand_workspace'),
                'url' => '/apps/manufacturing/demands',
            ],
        ];
    }

    /**
     * Get Manufacturing access-board surface label overrides.
     * These labels are used in the access control dashboard_assignments board
     * to label Manufacturing-specific UI surfaces (boards, queues, etc.).
     *
     * @return array<string,string> Map of surface key to display label
     */
    public static function getAccessBoardSurfaceLabels(): array
    {
        return [
            'manufacturing_portal' => t('admin.access_board.manufacturing_portal'),
            'prod_kpi' => t('admin.access_board.prod_kpi'),
            'prod_backlog' => t('admin.access_board.prod_backlog'),
            'qc_release_board' => t('admin.access_board.qc_release_board'),
            'dispatch_board' => t('admin.access_board.dispatch_board'),
            'stage_board' => t('admin.access_board.stage_board'),
            'production_queue' => t('admin.access_board.production_queue'),
            'production_plans' => t('admin.access_board.production_plans'),
            'qc_entries' => t('admin.access_board.qc_entries'),
            'qc_plans' => t('admin.access_board.qc_plans'),
            'dispatch_entries' => t('admin.access_board.dispatch_entries'),
            'qc_pass_rate' => t('admin.access_board.qc_pass_rate'),
            'dispatch_volume' => t('admin.access_board.dispatch_volume'),
            'production' => t('admin.access_board.production'),
            'assembly' => t('admin.access_board.assembly'),
            'qc' => t('admin.access_board.qc'),
            'dispatch' => t('admin.access_board.dispatch'),
            'demands' => t('admin.access_board.demands'),
            'coverage' => t('admin.access_board.coverage'),
            'materials' => t('admin.access_board.materials'),
        ];
    }

    /**
     * Get operator dashboard payload for Manufacturing-specific operational roles.
     * Supports roles: production_leader, assembly_leader, qc_leader, dispatch_leader.
     *
     * @param string $operationalRole
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null null if role not supported by Manufacturing
     */
    public static function getOperatorDashboardPayload(string $operationalRole, array $context): ?array
    {
        $operationalRole = strtolower(trim($operationalRole));
        $scope = (array)($context['scope'] ?? []);

        return match ($operationalRole) {
            'production_leader' => self::buildProductionLeaderDashboard($scope),
            'assembly_leader' => self::buildAssemblyLeaderDashboard($scope),
            'qc_leader' => self::buildQcLeaderDashboard($scope),
            'dispatch_leader' => self::buildDispatchLeaderDashboard($scope),
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function buildProductionLeaderDashboard(array $scope): array
    {
        $assignedMachines = count((array)($scope['machine_ids'] ?? []));
        $assignedParts = count((array)($scope['part_ids'] ?? []));
        $assignedTasks = count((array)($scope['task_types'] ?? []));

        $filters = self::scopeFilters($scope, 'production_plans', ['product_id' => 'part_ids', 'machine_id' => 'machine_ids']);
        $openQueue = self::countFrom('production_plans', "LOWER(COALESCE(status,'')) NOT IN ('completed','closed')" . $filters['sql'], $filters['params']);
        $delays = self::countFrom('production_plans', "COALESCE(plan_date,CURDATE()) < CURDATE() AND LOWER(COALESCE(status,'')) NOT IN ('completed','closed')" . $filters['sql'], $filters['params']);
        $plannedToday = self::sumFrom('production_plans', 'planned_qty', 'COALESCE(plan_date,CURDATE())=CURDATE()' . $filters['sql'], $filters['params']);

        $filtersEntries = self::scopeFilters($scope, 'production_entries', ['product_id' => 'part_ids', 'machine_id' => 'machine_ids']);
        $producedToday = self::sumFrom('production_entries', 'good_qty', 'COALESCE(production_date,CURDATE())=CURDATE()' . $filtersEntries['sql'], $filtersEntries['params']);
        $rejectedToday = self::sumFrom('production_entries', 'rejected_qty', 'COALESCE(production_date,CURDATE())=CURDATE()' . $filtersEntries['sql'], $filtersEntries['params']);
        $shortages = self::countFrom('daily_orders', "COALESCE(shortage_qty,0) > 0" . self::partScopeSql($scope, 'daily_orders'), self::partScopeParams($scope));
        $planVsProducedGap = round($plannedToday - $producedToday, 2);

        return [
            'title' => t('admin.dashboard_role.production_leader'),
            'subtitle' => t('admin.dashboard_role.production_leader_subtitle'),
            'cards' => [
                ['key' => 'assigned_machines', 'label' => t('admin.dashboard_role.assigned_machines'), 'value' => $assignedMachines, 'tone' => 'info'],
                ['key' => 'assigned_parts', 'label' => t('admin.dashboard_role.assigned_parts'), 'value' => $assignedParts, 'tone' => 'info'],
                ['key' => 'assigned_queue_rows', 'label' => t('admin.dashboard_role.assigned_queue_rows'), 'value' => $openQueue, 'tone' => 'warn'],
                ['key' => 'production_delays', 'label' => t('admin.dashboard_role.production_delays'), 'value' => $delays, 'tone' => $delays > 0 ? 'danger' : 'ok'],
                ['key' => 'planned_today', 'label' => t('admin.dashboard_role.planned_today'), 'value' => number_format($plannedToday, 2, '.', ','), 'tone' => 'warn'],
                ['key' => 'produced_today', 'label' => t('admin.dashboard_role.produced_today'), 'value' => number_format($producedToday, 2, '.', ','), 'tone' => 'ok'],
                ['key' => 'plan_production_gap', 'label' => t('admin.dashboard_role.plan_production_gap'), 'value' => number_format($planVsProducedGap, 2, '.', ','), 'tone' => $planVsProducedGap > 0 ? 'warn' : 'ok'],
                ['key' => 'rejected_today', 'label' => t('admin.dashboard_role.rejected_today'), 'value' => number_format($rejectedToday, 2, '.', ','), 'tone' => $rejectedToday > 0 ? 'danger' : 'ok'],
                ['key' => 'shortage_affected_orders', 'label' => t('admin.dashboard_role.shortage_affected_orders'), 'value' => $shortages, 'tone' => $shortages > 0 ? 'danger' : 'ok'],
            ],
            'sections' => [
                [
                    'title' => t('admin.dashboard_role.assigned_workload_scope'),
                    'items' => [
                        ['label' => t('admin.dashboard_role.task_types'), 'value' => $assignedTasks > 0 ? implode(', ', (array)$scope['task_types']) : '-'],
                        ['label' => t('admin.dashboard_role.department'), 'value' => (string)($scope['department_code'] ?? '-')],
                        ['label' => t('admin.dashboard_role.branch'), 'value' => (string)($scope['branch_code'] ?? '-')],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => t('admin.dashboard_role.production_queue'), 'url' => '/u/{username}/production'],
                ['label' => t('admin.dashboard_role.production_plans'), 'url' => '/apps/manufacturing/production-plans'],
                ['label' => t('admin.dashboard_role.daily_orders'), 'url' => '/apps/manufacturing/daily-orders'],
                ['label' => t('admin.dashboard_role.coverage_analytics'), 'url' => '/apps/manufacturing/coverage'],
            ],
            'placeholders' => [],
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function buildAssemblyLeaderDashboard(array $scope): array
    {
        $assemblyFilters = self::scopeFilters($scope, 'mfg_part_demands', ['product_id' => 'part_ids']);
        $openAssembly = self::countFrom('mfg_part_demands', "demand_type='assembly' AND status <> 'approved'" . $assemblyFilters['sql'], $assemblyFilters['params']);
        $readyAssembly = self::countScopedStageRows($scope, "r.stage='assembly' AND (r.override_status='released' OR COALESCE(r.released_qty,0)>0)");
        $blockedByProduction = self::countScopedStageRows($scope, "r.stage='production' AND r.override_status='blocked'");
        $readyForQc = self::countScopedStageRows($scope, "r.stage='qc' AND COALESCE(r.override_status,'') NOT IN ('blocked','released') AND COALESCE(r.released_qty,0)=0");

        $entryFilters = self::scopeFilters($scope, 'mfg_assembly_entries', ['product_id' => 'part_ids']);
        $completedQty = self::sumFrom('mfg_assembly_entries', 'completed_qty', "LOWER(COALESCE(status,'')) IN ('completed','approved')" . $entryFilters['sql'], $entryFilters['params']);
        $pendingQty = self::sumFrom('mfg_assembly_entries', 'GREATEST(planned_qty-completed_qty,0)', "LOWER(COALESCE(status,'')) NOT IN ('cancelled','approved')" . $entryFilters['sql'], $entryFilters['params']);

        return [
            'title' => t('admin.dashboard_role.assembly_leader'),
            'subtitle' => t('admin.dashboard_role.assembly_leader_subtitle'),
            'cards' => [
                ['key' => 'assembly_demand_workload', 'label' => t('admin.dashboard_role.assembly_demand_workload'), 'value' => $openAssembly, 'tone' => 'warn'],
                ['key' => 'assembly_ready_releases', 'label' => t('admin.dashboard_role.assembly_ready_releases'), 'value' => $readyAssembly, 'tone' => 'info'],
                ['key' => 'blocked_upstream_production', 'label' => t('admin.dashboard_role.blocked_upstream_production'), 'value' => $blockedByProduction, 'tone' => $blockedByProduction > 0 ? 'danger' : 'ok'],
                ['key' => 'pending_qc_handoff', 'label' => t('admin.dashboard_role.pending_qc_handoff'), 'value' => $readyForQc, 'tone' => 'info'],
                ['key' => 'completed_assembly_qty', 'label' => t('admin.dashboard_role.completed_assembly_qty'), 'value' => number_format($completedQty, 2, '.', ','), 'tone' => 'ok'],
                ['key' => 'pending_assembly_qty', 'label' => t('admin.dashboard_role.pending_assembly_qty'), 'value' => number_format($pendingQty, 2, '.', ','), 'tone' => 'warn'],
            ],
            'sections' => [
                [
                    'title' => t('admin.dashboard_role.scope'),
                    'items' => [
                        ['label' => t('admin.dashboard_role.assigned_parts'), 'value' => self::scopeCountLabel((array)($scope['part_ids'] ?? []))],
                        ['label' => t('admin.dashboard_role.assigned_task_types'), 'value' => self::scopeTextLabel((array)($scope['task_types'] ?? []))],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => t('admin.dashboard_role.assembly_queue'), 'url' => '/u/{username}/assembly'],
                ['label' => t('admin.dashboard_role.assembly_plans'), 'url' => '/manufacturing/assembly-plans'],
                ['label' => t('admin.dashboard_role.stage_board'), 'url' => '/manufacturing/stage-board'],
                ['label' => t('admin.dashboard_role.qc_entries'), 'url' => '/qc-entries'],
            ],
            'placeholders' => [],
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function buildQcLeaderDashboard(array $scope): array
    {
        $qcFilters = self::scopeFilters($scope, 'mfg_part_demands', ['product_id' => 'part_ids']);
        $qcWork = self::countFrom('mfg_part_demands', "demand_type='qc' AND status <> 'approved'" . $qcFilters['sql'], $qcFilters['params']);
        $qcFailed = self::sumFrom('qc_entries', 'GREATEST(checked_qty-pass_qty,0)', '1=1' . self::partScopeSql($scope, 'qc_entries'), self::partScopeParams($scope));
        $qcPending = self::countScopedStageRows($scope, "r.stage='qc' AND COALESCE(r.override_status,'') <> 'released'");
        $releaseCandidates = self::countScopedStageRows($scope, "r.stage='qc' AND (r.override_status='released' OR COALESCE(r.released_qty,0)>0)");
        $blockedRows = self::countScopedStageRows($scope, "r.stage='qc' AND r.override_status='blocked'");

        return [
            'title' => t('admin.dashboard_role.qc_leader'),
            'subtitle' => t('admin.dashboard_role.qc_leader_subtitle'),
            'cards' => [
                ['key' => 'qc_required_workload', 'label' => t('admin.dashboard_role.qc_required_workload'), 'value' => $qcWork, 'tone' => 'warn'],
                ['key' => 'failed_qty', 'label' => t('admin.dashboard_role.failed_qty'), 'value' => number_format($qcFailed, 2, '.', ','), 'tone' => $qcFailed > 0 ? 'danger' : 'ok'],
                ['key' => 'pending_qc_checks', 'label' => t('admin.dashboard_role.pending_qc_checks'), 'value' => $qcPending, 'tone' => 'warn'],
                ['key' => 'packaging_release_candidates', 'label' => t('admin.dashboard_role.packaging_release_candidates'), 'value' => $releaseCandidates, 'tone' => 'info'],
                ['key' => 'qc_blocked_exceptions', 'label' => t('admin.dashboard_role.qc_blocked_exceptions'), 'value' => $blockedRows, 'tone' => $blockedRows > 0 ? 'danger' : 'ok'],
            ],
            'sections' => [
                [
                    'title' => t('admin.dashboard_role.scope'),
                    'items' => [
                        ['label' => t('admin.dashboard_role.assigned_parts'), 'value' => self::scopeCountLabel((array)($scope['part_ids'] ?? []))],
                        ['label' => t('admin.dashboard_role.qc_lanes_tasks'), 'value' => self::scopeTextLabel((array)($scope['task_types'] ?? []))],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => t('admin.dashboard_role.qc_queue'), 'url' => '/u/{username}/qc'],
                ['label' => t('admin.dashboard_role.qc_plans'), 'url' => '/qc-plans'],
                ['label' => t('admin.dashboard_role.qc_entries'), 'url' => '/qc-entries'],
                ['label' => t('admin.dashboard_role.stage_board'), 'url' => '/manufacturing/stage-board'],
                ['label' => t('admin.dashboard_role.order_processing'), 'url' => '/manufacturing/dispatch-ops'],
            ],
            'placeholders' => [],
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<string,mixed>
     */
    private static function buildDispatchLeaderDashboard(array $scope): array
    {
        $filters = self::scopeFilters($scope, 'dispatch_entries', ['product_id' => 'part_ids']);
        $readyToPack = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='ready'" . $filters['sql'], $filters['params']);
        $packedPending = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='prepared'" . $filters['sql'], $filters['params']);
        $casesPalletPending = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft')) IN ('ready','prepared') AND (COALESCE(cases_count,0)=0 OR COALESCE(pallets_count,0)=0)" . $filters['sql'], $filters['params']);
        $blockedDispatches = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='draft'" . $filters['sql'], $filters['params']);
        $completedToday = self::sumFrom('dispatch_entries', 'dispatchable_qty', "COALESCE(dispatch_date,CURDATE())=CURDATE() AND LOWER(COALESCE(completion_status,'draft'))='completed'" . $filters['sql'], $filters['params']);
        $pendingToday = self::sumFrom('dispatch_entries', 'dispatchable_qty', "COALESCE(dispatch_date,CURDATE())=CURDATE() AND LOWER(COALESCE(completion_status,'draft')) IN ('draft','ready','prepared')" . $filters['sql'], $filters['params']);

        return [
            'title' => t('admin.dashboard_role.dispatch_leader'),
            'subtitle' => t('admin.dashboard_role.dispatch_leader_subtitle'),
            'cards' => [
                ['key' => 'ready_to_pack', 'label' => t('admin.dashboard_role.ready_to_pack'), 'value' => $readyToPack, 'tone' => 'info'],
                ['key' => 'packed_pending_complete', 'label' => t('admin.dashboard_role.packed_pending_complete'), 'value' => $packedPending, 'tone' => 'warn'],
                ['key' => 'cases_pallet_updates_pending', 'label' => t('admin.dashboard_role.cases_pallet_updates_pending'), 'value' => $casesPalletPending, 'tone' => $casesPalletPending > 0 ? 'danger' : 'ok'],
                ['key' => 'blocked_dispatches', 'label' => t('admin.dashboard_role.blocked_dispatches'), 'value' => $blockedDispatches, 'tone' => $blockedDispatches > 0 ? 'danger' : 'ok'],
                ['key' => 'completed_qty_today', 'label' => t('admin.dashboard_role.completed_qty_today'), 'value' => number_format($completedToday, 2, '.', ','), 'tone' => 'ok'],
                ['key' => 'pending_qty_today', 'label' => t('admin.dashboard_role.pending_qty_today'), 'value' => number_format($pendingToday, 2, '.', ','), 'tone' => 'warn'],
            ],
            'sections' => [
                [
                    'title' => t('admin.dashboard_role.scope'),
                    'items' => [
                        ['label' => t('admin.dashboard_role.assigned_parts'), 'value' => self::scopeCountLabel((array)($scope['part_ids'] ?? []))],
                        ['label' => t('admin.dashboard_role.assigned_task_types'), 'value' => self::scopeTextLabel((array)($scope['task_types'] ?? []))],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => t('admin.dashboard_role.dispatch_workbench'), 'url' => '/u/{username}/dispatch'],
                ['label' => t('admin.dashboard_role.order_preparation_form'), 'url' => '/manufacturing/dispatch-ops/preparation'],
                ['label' => t('admin.dashboard_role.dispatch_entries'), 'url' => '/dispatch-entries'],
                ['label' => t('admin.dashboard_role.assembly_queue'), 'url' => '/manufacturing/assembly-queue'],
            ],
            'placeholders' => [],
        ];
    }

    /**
     * Scope filter SQL builder - used by dashboard methods.
     * @param array<string,mixed> $scope
     * @param string $table
     * @param array<string,string> $fieldMap
     * @return array<string,mixed>
     */
    private static function scopeFilters(array $scope, string $table, array $fieldMap): array
    {
        $filters = ['sql' => '', 'params' => []];
        foreach ($fieldMap as $field => $scopeKey) {
            $values = (array)($scope[$scopeKey] ?? []);
            if (!empty($values)) {
                $placeholders = implode(',', array_fill(0, count($values), '?'));
                $filters['sql'] .= " AND $table.$field IN ($placeholders)";
                $filters['params'] = array_merge($filters['params'], $values);
            }
        }
        return $filters;
    }

    /**
     * Get SQL fragment for part scope filtering.
     * @param array<string,mixed> $scope
     * @param string $table
     * @return string
     */
    private static function partScopeSql(array $scope, string $table): string
    {
        $partIds = (array)($scope['part_ids'] ?? []);
        if (empty($partIds)) {
            return '';
        }
        $placeholders = implode(',', array_fill(0, count($partIds), '?'));
        return " AND $table.product_id IN ($placeholders)";
    }

    /**
     * Get parameters for part scope filtering.
     * @param array<string,mixed> $scope
     * @return array<int,mixed>
     */
    private static function partScopeParams(array $scope): array
    {
        $partIds = (array)($scope['part_ids'] ?? []);
        return !empty($partIds) ? $partIds : [];
    }

    /**
     * Count rows from a table with condition.
     * @param string $table
     * @param string $condition
     * @param array<int,mixed> $params
     * @return int
     */
    private static function countFrom(string $table, string $condition, array $params = []): int
    {
        try {
            $result = DB::fetchOne("SELECT COUNT(*) as cnt FROM $table WHERE $condition", $params);
            return (int)($result['cnt'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Sum a field from a table with condition.
     * @param string $table
     * @param string $field
     * @param string $condition
     * @param array<int,mixed> $params
     * @return float
     */
    private static function sumFrom(string $table, string $field, string $condition, array $params = []): float
    {
        try {
            $result = DB::fetchOne("SELECT COALESCE(SUM($field), 0) as total FROM $table WHERE $condition", $params);
            return (float)($result['total'] ?? 0);
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Count staged rows across scope with condition.
     * @param array<string,mixed> $scope
     * @param string $condition
     * @return int
     */
    private static function countScopedStageRows(array $scope, string $condition): int
    {
        $partIds = (array)($scope['part_ids'] ?? []);
        if (empty($partIds)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($partIds), '?'));
        try {
            $result = DB::fetchOne(
                "SELECT COUNT(*) as cnt FROM mfg_part_stage_rows r WHERE r.product_id IN ($placeholders) AND $condition",
                $partIds
            );
            return (int)($result['cnt'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Format scope part IDs count label.
     * @param array<int,mixed> $values
     * @return string
     */
    private static function scopeCountLabel(array $values): string
    {
        return count($values) > 0 ? (string)count($values) : '-';
    }

    /**
     * Format scope text label.
     * @param array<int,string> $values
     * @return string
     */
    private static function scopeTextLabel(array $values): string
    {
        return !empty($values) ? implode(', ', (array)$values) : '-';
    }
}
