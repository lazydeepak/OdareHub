<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use App\Core\DB;

/**
 * CoverageAdapter
 *
 * Data-only adapter for the /u/{operator}/coverage operator view.
 * Queries DB directly and returns pure data arrays — no HTML, no rendering.
 * Views consume this data to render operator-specific coverage UI.
 */
final class CoverageAdapter
{
    /**
     * Fetch all coverage data needed by the operator coverage view.
     *
     * @return array{
     *   summary: array<string,int|float>,
     *   risk_bands: array<int,array<string,mixed>>,
     *   demand_window: array<int,array<string,mixed>>,
     *   critical_orders: array<int,array<string,mixed>>,
     *   low_coverage_orders: array<int,array<string,mixed>>,
     *   empty: bool,
     *   error: string
     * }
     */
    public static function getData(): array
    {
        $defaults = [
            'summary' => [
                'open_orders'                => 0,
                'demand_qty'                 => 0.0,
                'shortage_qty'               => 0.0,
                'coverage_pct'               => 0.0,
                'critical_orders_count'      => 0,
                'low_coverage_orders_count'  => 0,
                'fully_covered_orders_count' => 0,
                'window_today_count'         => 0,
                'window_3day_count'          => 0,
                'window_7day_count'          => 0,
            ],
            'risk_bands' => [],
            'demand_window' => [],
            'critical_orders' => [],
            'low_coverage_orders' => [],
            'empty' => true,
            'error' => '',
        ];

        try {
            $summary = self::fetchSummary();
            $defaults['summary'] = $summary;
            $defaults['empty'] = ($summary['open_orders'] === 0);

            $defaults['risk_bands'] = [
                ['label' => 'Critical',      'count' => $summary['critical_orders_count'],     'pct' => self::sharePct($summary['critical_orders_count'], $summary['open_orders']),     'tone' => 'danger'],
                ['label' => 'Low Coverage',  'count' => $summary['low_coverage_orders_count'],  'pct' => self::sharePct($summary['low_coverage_orders_count'], $summary['open_orders']),  'tone' => 'warning'],
                ['label' => 'Fully Covered', 'count' => $summary['fully_covered_orders_count'], 'pct' => self::sharePct($summary['fully_covered_orders_count'], $summary['open_orders']), 'tone' => 'success'],
            ];

            $defaults['demand_window'] = [
                ['label' => 'Due Today',   'count' => $summary['window_today_count'], 'tone' => 'danger'],
                ['label' => 'Due ≤ 3 Days', 'count' => $summary['window_3day_count'], 'tone' => 'warning'],
                ['label' => 'Due ≤ 7 Days', 'count' => $summary['window_7day_count'], 'tone' => 'info'],
            ];

            $defaults['critical_orders'] = self::fetchCriticalOrders();
            $defaults['low_coverage_orders'] = self::fetchLowCoverageOrders();
        } catch (\Throwable $e) {
            error_log('CoverageAdapter::getData: ' . $e->getMessage());
            $defaults['error'] = 'Coverage data could not be loaded. Please try again later.';
        }

        return $defaults;
    }

    /**
     * @return array<string,int|float>
     */
    private static function fetchSummary(): array
    {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_orders,
                COALESCE(SUM(qty), 0) AS demand_qty,
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
                    WHEN DATE(COALESCE(required_date, order_date)) <= CURDATE() THEN 1 ELSE 0 END), 0) AS window_today_count,
                COALESCE(SUM(CASE
                    WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END), 0) AS window_3day_count,
                COALESCE(SUM(CASE
                    WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS window_7day_count
             FROM daily_orders
             WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
        );

        if (!is_array($row)) {
            return ['open_orders' => 0, 'demand_qty' => 0.0, 'shortage_qty' => 0.0, 'coverage_pct' => 0.0, 'critical_orders_count' => 0, 'low_coverage_orders_count' => 0, 'fully_covered_orders_count' => 0, 'window_today_count' => 0, 'window_3day_count' => 0, 'window_7day_count' => 0];
        }

        return [
            'open_orders'                => (int)($row['open_orders'] ?? 0),
            'demand_qty'                 => (float)($row['demand_qty'] ?? 0.0),
            'shortage_qty'               => (float)($row['shortage_qty'] ?? 0.0),
            'coverage_pct'               => (float)($row['coverage_pct'] ?? 0.0),
            'critical_orders_count'      => (int)($row['critical_orders_count'] ?? 0),
            'low_coverage_orders_count'  => (int)($row['low_coverage_orders_count'] ?? 0),
            'fully_covered_orders_count' => (int)($row['fully_covered_orders_count'] ?? 0),
            'window_today_count'         => (int)($row['window_today_count'] ?? 0),
            'window_3day_count'          => (int)($row['window_3day_count'] ?? 0),
            'window_7day_count'          => (int)($row['window_7day_count'] ?? 0),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchCriticalOrders(): array
    {
        $rows = DB::fetchAll(
            "SELECT d.id, d.product_id, COALESCE(p.parts_name, p.parts_number, CONCAT('#', d.id)) AS product_name,
                    d.qty, d.shortage_qty, d.coverage_pct,
                    COALESCE(d.required_date, d.order_date) AS due_date, d.status
             FROM daily_orders d
             LEFT JOIN products p ON p.id = d.product_id
             WHERE LOWER(COALESCE(d.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND d.qty > 0
               AND (
                   COALESCE(d.shortage_qty, 0) / NULLIF(d.qty, 0) >= 0.50
                   OR COALESCE(d.coverage_pct, 0) < 25
               )
             ORDER BY d.coverage_pct ASC, due_date ASC
             LIMIT 20"
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchLowCoverageOrders(): array
    {
        $rows = DB::fetchAll(
            "SELECT d.id, d.product_id, COALESCE(p.parts_name, p.parts_number, CONCAT('#', d.id)) AS product_name,
                    d.qty, d.shortage_qty, d.coverage_pct,
                    COALESCE(d.required_date, d.order_date) AS due_date, d.status
             FROM daily_orders d
             LEFT JOIN products p ON p.id = d.product_id
             WHERE LOWER(COALESCE(d.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND d.qty > 0
               AND COALESCE(d.shortage_qty, 0) > 0
               AND NOT (
                   COALESCE(d.shortage_qty, 0) / NULLIF(d.qty, 0) >= 0.50
                   OR COALESCE(d.coverage_pct, 0) < 25
               )
             ORDER BY d.coverage_pct ASC, due_date ASC
             LIMIT 20"
        );

        return is_array($rows) ? $rows : [];
    }

    private static function sharePct(int $count, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }
        return round(min(100.0, ($count / $total) * 100.0), 1);
    }
}
