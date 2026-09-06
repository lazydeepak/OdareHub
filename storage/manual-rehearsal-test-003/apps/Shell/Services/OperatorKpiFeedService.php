<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

/**
 * OperatorKpiFeedService
 *
 * Provides lightweight scalar KPI counts for the live-polling JSON endpoint
 * /u/kpi-feed. Mirrors the value returned by OperatorSurfaceComposer dashboard
 * tile builders but executes a single cheap query per metric.
 *
 * Used by: apps/Shell/routes.php GET /u/kpi-feed
 */
final class OperatorKpiFeedService
{
    /**
     * Return an array of {key, value} pairs for the six dashboard tiles.
     * All values are safe for JSON encoding. Queries are intentionally simple.
     *
     * @return array<int, array{key:string,value:string}>
     */
    public static function getKpis(): array
    {
        $today = date('Y-m-d');

        return [
            ['key' => 'orders',    'value' => (string)self::ordersToday($today)],
            ['key' => 'parts',     'value' => (string)self::criticalPartsToday($today)],
            ['key' => 'overstock', 'value' => (string)self::overstockToday($today)],
            ['key' => 'plans',     'value' => (string)self::wasteToday($today)],
            ['key' => 'processing','value' => (string)self::materialShortageToday($today)],
            ['key' => 'dispatch',  'value' => (string)self::dispatchProblemsToday($today)],
        ];
    }

    private static function ordersToday(string $today): int
    {
        try {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c FROM daily_orders WHERE order_date = ?',
                [$today]
            );
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('OperatorKpiFeedService::ordersToday: ' . $e->getMessage());
            return 0;
        }
    }

    private static function criticalPartsToday(string $today): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(DISTINCT d.product_id) AS c
                 FROM daily_orders d
                 WHERE d.order_date = ?
                   AND COALESCE(d.usable_stock_qty, 0) < COALESCE(d.qty, 0)",
                [$today]
            );
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('OperatorKpiFeedService::criticalPartsToday: ' . $e->getMessage());
            return 0;
        }
    }

    private static function overstockToday(string $today): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(DISTINCT product_id) AS c
                 FROM daily_orders
                 WHERE order_date = ?
                 GROUP BY order_date
                 HAVING COUNT(CASE WHEN usable_stock_qty > qty THEN 1 END) > 0",
                [$today]
            );
            // The above returns one row if any overstock exists; count distinct instead:
            $row2 = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM (
                   SELECT product_id
                   FROM daily_orders
                   WHERE order_date = ?
                   GROUP BY product_id
                   HAVING MAX(usable_stock_qty) > SUM(qty)
                 ) t",
                [$today]
            );
            return (int)($row2['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('OperatorKpiFeedService::overstockToday: ' . $e->getMessage());
            return 0;
        }
    }

    private static function wasteToday(string $today): string
    {
        try {
            $row = DB::fetchOne(
                "SELECT COALESCE(SUM(COALESCE(rejected_qty, 0)), 0) AS total
                 FROM production_entries
                 WHERE production_date = ?
                   AND COALESCE(rejected_qty, 0) > 0",
                [$today]
            );
            return number_format((float)($row['total'] ?? 0), 0, '.', '');
        } catch (\Throwable $e) {
            error_log('OperatorKpiFeedService::wasteToday: ' . $e->getMessage());
            return '0';
        }
    }

    private static function materialShortageToday(string $today): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM (
                   SELECT m.id
                   FROM production_plans pp
                   INNER JOIN part_material_map pm ON pm.product_id = pp.product_id
                   INNER JOIN materials m ON m.id = pm.material_id
                   LEFT JOIN (
                     SELECT material_id, SUM(qty_delta) AS on_hand
                     FROM material_ledger GROUP BY material_id
                   ) led ON led.material_id = m.id
                   LEFT JOIN (
                     SELECT material_id, SUM(GREATEST(reserved_qty - fulfilled_qty, 0)) AS reserved
                     FROM material_reservations
                     WHERE LOWER(COALESCE(status,'active')) IN ('active','partial')
                     GROUP BY material_id
                   ) res ON res.material_id = m.id
                   WHERE pp.plan_date = ?
                     AND LOWER(COALESCE(pp.status,'planned')) NOT IN ('cancelled','canceled','completed','closed')
                     AND COALESCE(pm.is_active, 1) = 1
                   GROUP BY m.id
                   HAVING SUM(pp.planned_qty * COALESCE(NULLIF(pm.qty_per_part,0), pm.usage_qty, 0))
                        > COALESCE(MAX(led.on_hand),0) - COALESCE(MAX(res.reserved),0)
                 ) x",
                [$today]
            );
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('OperatorKpiFeedService::materialShortageToday: ' . $e->getMessage());
            return 0;
        }
    }

    private static function dispatchProblemsToday(string $today): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COALESCE(SUM(CASE
                    WHEN LOWER(COALESCE(dispatch_status,'')) IN ('hold','blocked')
                      OR LOWER(COALESCE(status_reason,'')) LIKE '%delay%'
                      OR LOWER(COALESCE(status_note,'')) LIKE '%delay%'
                      OR LOWER(COALESCE(remarks,'')) LIKE '%delay%'
                      OR LOWER(COALESCE(status_reason,'')) LIKE '%postpon%'
                      OR LOWER(COALESCE(status_reason,'')) LIKE '%cancel%'
                    THEN 1 ELSE 0 END), 0) AS c
                 FROM dispatch_entries WHERE dispatch_date = ?",
                [$today]
            );
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            error_log('OperatorKpiFeedService::dispatchProblemsToday: ' . $e->getMessage());
            return 0;
        }
    }
}
