<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Controllers;

use App\Core\Auth;
use App\Core\DB;
use App\Services\TileActionResolverService;
use App\Core\View;

final class DemandDashboardController
{
    public static function index(View $view): void
    {
        $summary = [
            'open_orders' => 0,
            'pre_orders' => 0,
            'pre_orders_qty' => 0.0,
            'due_today' => 0,
            'delayed' => 0,
            'coverage_pct' => 0.0,
            'shortage_qty' => 0.0,
            'critical_orders' => 0,
            'production_demand_rows' => 0,
            'production_demand_qty' => 0.0,
            'procurement_demand_rows' => 0,
            'procurement_demand_qty' => 0.0,
            'pending_plans' => 0,
            'approved_plans_today' => 0,
        ];
        $riskRows = [];

        try {
            if (self::tableExists('daily_orders')) {
                $columns = self::dailyOrderColumns();
                $dueDateExpr = isset($columns['required_date']) ? 'COALESCE(required_date, order_date)' : 'order_date';

                $summaryRow = DB::fetchOne(
                    "SELECT
                        COUNT(*) AS open_orders,
                        COALESCE(ROUND(AVG(COALESCE(coverage_pct, 0)), 2), 0) AS coverage_pct,
                        COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS shortage_qty,
                        COALESCE(SUM(CASE WHEN {$dueDateExpr} IS NOT NULL AND DATE({$dueDateExpr}) = CURDATE() THEN 1 ELSE 0 END), 0) AS due_today,
                        COALESCE(SUM(CASE WHEN {$dueDateExpr} IS NOT NULL AND DATE({$dueDateExpr}) < CURDATE() THEN 1 ELSE 0 END), 0) AS delayed,
                        COALESCE(SUM(CASE
                            WHEN qty > 0 AND (
                                COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                                OR COALESCE(coverage_pct, 0) < 25
                            ) THEN 1 ELSE 0 END), 0) AS critical_orders
                     FROM daily_orders
                     WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
                );

                if (is_array($summaryRow)) {
                    $summary['open_orders'] = (int)($summaryRow['open_orders'] ?? 0);
                    $summary['coverage_pct'] = (float)($summaryRow['coverage_pct'] ?? 0);
                    $summary['shortage_qty'] = (float)($summaryRow['shortage_qty'] ?? 0);
                    $summary['due_today'] = (int)($summaryRow['due_today'] ?? 0);
                    $summary['delayed'] = (int)($summaryRow['delayed'] ?? 0);
                    $summary['critical_orders'] = (int)($summaryRow['critical_orders'] ?? 0);
                }

                $riskRows = DB::fetchAll(
                    "SELECT
                        id,
                        order_date,
                        required_date,
                        customer_name,
                        parts_name,
                        parts_number,
                        qty,
                        COALESCE(coverage_pct, 0) AS coverage_pct,
                        COALESCE(shortage_qty, 0) AS shortage_qty
                     FROM daily_orders
                     WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                     ORDER BY COALESCE(shortage_qty, 0) DESC, COALESCE(required_date, order_date) ASC, id ASC
                    LIMIT 10"
                );
            }

            if (self::tableExists('pre_orders')) {
                $preOrders = DB::fetchOne(
                    "SELECT
                        COUNT(*) AS pre_orders,
                        COALESCE(SUM(COALESCE(planned_qty, 0)), 0) AS pre_orders_qty
                     FROM pre_orders"
                );

                if (is_array($preOrders)) {
                    $summary['pre_orders'] = (int)($preOrders['pre_orders'] ?? 0);
                    $summary['pre_orders_qty'] = (float)($preOrders['pre_orders_qty'] ?? 0);
                }
            }

            if (self::tableExists('mfg_part_demands')) {
                $production = DB::fetchOne(
                    "SELECT
                        COALESCE(SUM(CASE
                            WHEN status='approved' THEN COALESCE(approved_qty, adjusted_qty, system_qty, 0)
                            ELSE COALESCE(adjusted_qty, system_qty, 0)
                        END), 0) AS demand_qty,
                        COUNT(*) AS demand_rows
                     FROM mfg_part_demands
                     WHERE demand_type='production'
                       AND demand_date >= CURDATE()"
                );
                if (is_array($production)) {
                    $summary['production_demand_rows'] = (int)($production['demand_rows'] ?? 0);
                    $summary['production_demand_qty'] = (float)($production['demand_qty'] ?? 0);
                }
            }

            if (self::tableExists('mfg_procurement_demands')) {
                $procurement = DB::fetchOne(
                    "SELECT
                        COUNT(*) AS demand_rows,
                        COALESCE(SUM(COALESCE(required_qty, 0)), 0) AS demand_qty
                     FROM mfg_procurement_demands
                     WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
                );
                if (is_array($procurement)) {
                    $summary['procurement_demand_rows'] = (int)($procurement['demand_rows'] ?? 0);
                    $summary['procurement_demand_qty'] = (float)($procurement['demand_qty'] ?? 0);
                }
            }

            if (self::tableExists('production_plans')) {
                $plans = DB::fetchOne(
                    "SELECT
                        COALESCE(SUM(CASE
                            WHEN LOWER(COALESCE(status, '')) IN ('approved', 'released', 'done', 'completed', 'closed')
                                 AND plan_date = CURDATE() THEN 1 ELSE 0 END), 0) AS approved_today,
                        COALESCE(SUM(CASE
                            WHEN LOWER(COALESCE(status, '')) NOT IN ('approved', 'released', 'done', 'completed', 'closed', 'cancelled', 'canceled')
                                 THEN 1 ELSE 0 END), 0) AS pending_plans
                     FROM production_plans"
                );
                if (is_array($plans)) {
                    $summary['approved_plans_today'] = (int)($plans['approved_today'] ?? 0);
                    $summary['pending_plans'] = (int)($plans['pending_plans'] ?? 0);
                }
            }
        } catch (\Throwable $e) {
            // Keep dashboard available if one data source is not ready.
        }

        $currentUser = Auth::user();
        $role = strtolower(trim((string)($currentUser['role'] ?? '')));
        $ctx = platform_user_context_contract()->resolveUserContext($currentUser);
        $quickLinks = self::quickLinksForRole($role, array_values(array_map('strval', (array)($ctx['module_visibility'] ?? []))));
        $quickLinks = TileActionResolverService::annotateDemandQuickLinks($quickLinks, [
            'role' => $role,
        ]);

        $view->render('manufacturing::demand/dashboard.php', [
            'pageTitle' => 'Demand Workspace',
            'summary' => $summary,
            'risk_rows' => is_array($riskRows) ? $riskRows : [],
            'quick_links' => $quickLinks,
            'role_label' => (string)($currentUser['role'] ?? 'Operations'),
            'suite_role_template_labels' => (array)($ctx['suite_role_template_labels'] ?? []),
            'module_permission_template_labels' => (array)($ctx['module_permission_template_labels'] ?? []),
        ]);
    }

    private static function tableExists(string $table): bool
    {
        $safe = DB::conn()->real_escape_string($table);
        return DB::fetchOne("SHOW TABLES LIKE '{$safe}'") !== null;
    }

    /**
     * @return array<string,string>
     */
    private static function dailyOrderColumns(): array
    {
        $rows = DB::fetchAll('SHOW COLUMNS FROM daily_orders');
        $columns = [];
        foreach ($rows as $row) {
            $field = strtolower(trim((string)($row['Field'] ?? '')));
            if ($field !== '') {
                $columns[$field] = $field;
            }
        }
        return $columns;
    }

    /**
     * @return array<int,array{label:string,url:string,hint:string}>
     */
    private static function quickLinksForRole(string $role, array $moduleVisibility = []): array
    {
        $links = [
            ['key' => 'daily_orders', 'label' => 'Daily Orders', 'url' => '/apps/manufacturing/daily-orders', 'hint' => 'Order intake, filters, and 360 links', 'module' => 'demands'],
            ['key' => 'coverage_dashboard', 'label' => 'Coverage Analytics', 'url' => '/apps/manufacturing/coverage', 'hint' => 'Coverage and shortage pressure view', 'module' => 'coverage'],
            ['key' => 'production_plans', 'label' => 'Production Plans', 'url' => '/apps/manufacturing/production-plans', 'hint' => 'Plan capacity and sequence work', 'module' => 'production'],
            ['key' => 'demand_workspace', 'label' => 'Demand Workspace', 'url' => '/apps/manufacturing/demands', 'hint' => 'Recalculate and approve demand quantities', 'module' => 'demands'],
            ['key' => 'stage_board', 'label' => 'Stage Board', 'url' => '/apps/manufacturing/stage-board', 'hint' => 'Cross-role stage progression and handoffs', 'module' => 'production'],
            ['key' => 'qc_queue', 'label' => 'QC Queue', 'url' => '/apps/manufacturing/qc-queue', 'hint' => 'Quality gate before preparation and dispatch', 'module' => 'qc'],
            ['key' => 'order_processing', 'label' => 'Order Preparation', 'url' => '/apps/manufacturing/dispatch-ops/preparation', 'hint' => 'Preparation stage before dispatch release', 'module' => 'dispatch'],
            ['key' => 'dispatch_ops', 'label' => 'Dispatch Ops', 'url' => '/apps/manufacturing/dispatch-ops', 'hint' => 'Preparation and dispatch execution board', 'module' => 'dispatch'],
            ['key' => 'manufacturing_portal', 'label' => 'Manufacturing Workspace', 'url' => '/apps/manufacturing', 'hint' => 'Execution-focused command center', 'module' => 'production'],
        ];

        if (str_contains($role, 'machine')) {
            $links[] = ['key' => 'production_queue', 'label' => 'Production Queue', 'url' => '/apps/manufacturing/production-queue', 'hint' => 'Machine-facing queue for active plans', 'module' => 'production'];
            $links[] = ['key' => 'production_dashboard', 'label' => 'Production Workspace', 'url' => '/apps/manufacturing/production-dashboard', 'hint' => 'Machine-leader workload view', 'module' => 'production'];
        } elseif (str_contains($role, 'assembly')) {
            $links[] = ['key' => 'assembly_queue', 'label' => 'Assembly Queue', 'url' => '/apps/manufacturing/assembly-queue', 'hint' => 'Assembly work waiting for ownership', 'module' => 'assembly'];
            $links[] = ['key' => 'assembly_dashboard', 'label' => 'Assembly Dashboard', 'url' => '/apps/manufacturing/assembly-dashboard', 'hint' => 'Assembly leader throughput view', 'module' => 'assembly'];
        } elseif (str_contains($role, 'qc')) {
            $links[] = ['key' => 'qc_queue', 'label' => 'QC Queue', 'url' => '/apps/manufacturing/qc-queue', 'hint' => 'Quality work waiting for review', 'module' => 'qc'];
            $links[] = ['key' => 'qc_dashboard', 'label' => 'QC Workspace', 'url' => '/apps/manufacturing/qc-dashboard', 'hint' => 'QC leader quality and flow view', 'module' => 'qc'];
        } elseif (str_contains($role, 'dispatch')) {
            $links[] = ['key' => 'dispatch_dashboard', 'label' => 'Dispatch Workspace', 'url' => '/apps/manufacturing/dispatch-dashboard', 'hint' => 'Dispatch readiness and release status', 'module' => 'dispatch'];
        }

        if ($moduleVisibility !== []) {
            $links = array_values(array_filter($links, static function (array $link) use ($moduleVisibility): bool {
                $module = (string)($link['module'] ?? '');
                return $module === '' || in_array($module, $moduleVisibility, true);
            }));
        }

        return self::filterRoutableLinks($links);
    }

    /**
     * @param array<int,array{label:string,url:string,hint:string}> $links
     * @return array<int,array{label:string,url:string,hint:string}>
     */
    private static function filterRoutableLinks(array $links): array
    {
        $out = [];
        $seen = [];

        foreach ($links as $link) {
            $url = trim((string)($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $dedupeKey = strtolower($url . '|' . (string)($link['label'] ?? ''));
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            if (str_starts_with($url, '/')
                && class_exists('\App\Core\RouteRuntimeAuthority')
                && !\App\Core\RouteRuntimeAuthority::hasLoadedRoute($url, 'GET')
            ) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $out[] = [
                'key' => (string)($link['key'] ?? ''),
                'label' => (string)($link['label'] ?? 'Open'),
                'url' => $url,
                'hint' => (string)($link['hint'] ?? ''),
            ];
        }

        return $out;
    }
}
