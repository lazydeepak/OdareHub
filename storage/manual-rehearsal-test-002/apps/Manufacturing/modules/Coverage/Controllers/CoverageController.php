<?php
declare(strict_types=1);

namespace Plugins\Coverage\Controllers;

use App\Core\DB;
use App\Core\View;

final class CoverageController
{
    public static function index(View $view): void
    {
        $summary = [
            'open_orders' => 0,
            'demand_qty' => 0.0,
            'covered_qty' => 0.0,
            'shortage_qty' => 0.0,
            'coverage_pct' => 0.0,
            'critical_orders_count' => 0,
            'low_coverage_orders_count' => 0,
            'fully_covered_orders_count' => 0,
            'window_today_count' => 0,
            'window_3day_count' => 0,
            'window_7day_count' => 0,
        ];
        $rows = [];

        try {
            $dailyOrdersTable = DB::fetchOne("SHOW TABLES LIKE 'daily_orders'") !== null;
            if ($dailyOrdersTable) {
                $columns = self::dailyOrderColumns();
                $availableStockExpr = self::columnExpr($columns, 'usable_stock_qty', '0');
                $plannedSupplyExpr = self::columnExpr($columns, 'planned_supply_qty', '0');
                $qcPassExpr = self::columnExpr($columns, 'qc_pass_qty', '0');
                $dispatchedExpr = self::columnExpr($columns, 'dispatched_qty', '0');
                $usableSupplyExpr = self::columnExpr(
                    $columns,
                    'usable_supply_qty',
                    '(' . $availableStockExpr . ' + ' . $plannedSupplyExpr . ' + ' . $qcPassExpr . ' - ' . $dispatchedExpr . ')'
                );
                $dueDateExpr = isset($columns['required_date']) ? 'COALESCE(required_date, order_date)' : 'order_date';

                $summary = DB::fetchOne(
                    "SELECT
                        COUNT(*) AS open_orders,
                        COALESCE(SUM(qty), 0) AS demand_qty,
                        COALESCE(SUM(GREATEST(qty - COALESCE(shortage_qty, 0), 0)), 0) AS covered_qty,
                        COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS shortage_qty,
                        COALESCE(ROUND(AVG(COALESCE(coverage_pct, 0)), 2), 0) AS coverage_pct,
                        COALESCE(SUM(CASE
                            WHEN qty > 0 AND (
                                COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                                OR COALESCE(coverage_pct, 0) < 25
                            ) THEN 1 ELSE 0 END), 0) AS critical_orders_count,
                        COALESCE(SUM(CASE
                            WHEN qty > 0
                                 AND COALESCE(shortage_qty, 0) > 0
                                 AND NOT (
                                     COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                                     OR COALESCE(coverage_pct, 0) < 25
                                 )
                            THEN 1 ELSE 0 END), 0) AS low_coverage_orders_count,
                        COALESCE(SUM(CASE
                            WHEN qty > 0 AND COALESCE(shortage_qty, 0) <= 0 THEN 1 ELSE 0 END), 0) AS fully_covered_orders_count,
                        COALESCE(SUM(CASE
                            WHEN {$dueDateExpr} IS NOT NULL AND DATE({$dueDateExpr}) <= CURDATE() THEN 1 ELSE 0 END), 0) AS window_today_count,
                        COALESCE(SUM(CASE
                            WHEN {$dueDateExpr} IS NOT NULL AND DATE({$dueDateExpr}) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END), 0) AS window_3day_count,
                        COALESCE(SUM(CASE
                            WHEN {$dueDateExpr} IS NOT NULL AND DATE({$dueDateExpr}) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS window_7day_count
                     FROM daily_orders
                     WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
                ) ?: $summary;

                $rows = DB::fetchAll(
                    "SELECT
                        id,
                        order_date,
                        required_date,
                        customer_name,
                        parts_name,
                        parts_number,
                        qty,
                        COALESCE(coverage_pct, 0) AS coverage_pct,
                        COALESCE(shortage_qty, 0) AS shortage_qty,
                        COALESCE(coverage_status, 'Low') AS coverage_status,
                        {$availableStockExpr} AS available_stock_qty,
                        {$plannedSupplyExpr} AS planned_supply_qty,
                        {$usableSupplyExpr} AS usable_supply_qty,
                        GREATEST(qty - COALESCE(shortage_qty, 0), 0) AS net_coverage_qty,
                        GREATEST(COALESCE(shortage_qty, 0), 0) AS net_shortage_qty
                     FROM daily_orders
                     WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                     ORDER BY COALESCE(shortage_qty, 0) DESC, order_date ASC, id ASC
                     LIMIT 120"
                );

                $rows = self::decorateRows($rows);
            }
        } catch (\Throwable $e) {
            // Keep the page usable even if coverage tables are temporarily unavailable.
        }

        $view->render('Coverage::coverage/index.php', [
            'pageTitle' => 'Manufacturing Coverage Analytics',
            'summary' => is_array($summary) ? $summary : [],
            'rows' => is_array($rows) ? $rows : [],
        ]);
    }

    /**
     * @return array<int,string>
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
     * @param array<int,string> $columns
     */
    private static function columnExpr(array $columns, string $column, string $fallback): string
    {
        return isset($columns[strtolower($column)]) ? 'COALESCE(' . $column . ', 0)' : $fallback;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function decorateRows(array $rows): array
    {
        $decorated = [];
        foreach ($rows as $row) {
            $demandQty = (float)($row['qty'] ?? 0);
            $coveragePct = (float)($row['coverage_pct'] ?? 0);
            $netShortage = max(0.0, (float)($row['net_shortage_qty'] ?? 0));
            $availableStock = (float)($row['available_stock_qty'] ?? 0);
            $plannedSupply = (float)($row['planned_supply_qty'] ?? 0);
            $usableSupply = (float)($row['usable_supply_qty'] ?? 0);
            $effectiveSupply = max($availableStock + $plannedSupply, $usableSupply);

            $classification = self::classifyCoverage($demandQty, $coveragePct, $netShortage, $effectiveSupply);
            $row['coverage_classification'] = $classification;

            $actionLinks = [
                ['label' => 'Open Order 360', 'url' => '/daily-orders/360?id=' . (int)($row['id'] ?? 0)],
            ];
            if ($classification === 'Critical' || $classification === 'Low') {
                $actionLinks[] = ['label' => 'Open Production Queue', 'url' => '/apps/manufacturing/production-queue'];
                $actionLinks[] = ['label' => 'Open Assembly Queue', 'url' => '/apps/manufacturing/assembly-queue'];
            }
            if ($plannedSupply > 0 || $effectiveSupply > 0) {
                $actionLinks[] = ['label' => 'Open QC Queue', 'url' => '/apps/manufacturing/qc-queue'];
            }
            if ($classification === 'Balanced' || $classification === 'High') {
                $actionLinks[] = ['label' => 'Open Dispatch Ops', 'url' => '/apps/manufacturing/dispatch-ops'];
            }
            $row['action_links'] = $actionLinks;
            $decorated[] = $row;
        }

        return $decorated;
    }

    private static function classifyCoverage(float $demandQty, float $coveragePct, float $netShortage, float $effectiveSupply): string
    {
        if ($demandQty <= 0.0001) {
            return $effectiveSupply > 0.0001 ? 'Supply Only' : 'No Data';
        }

        if ($netShortage > 0.0001) {
            $shortageRatio = $netShortage / max($demandQty, 0.0001);
            if ($shortageRatio >= 0.50 || $coveragePct < 25) {
                return 'Critical';
            }
            return 'Low';
        }

        if ($coveragePct >= 130) {
            return 'High';
        }

        return 'Balanced';
    }
}
