<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\DB;

final class OperatorDashboardInsightsComposer
{
    public static function buildOrdersInsights(callable $tr): array
    {
        $today = new \DateTimeImmutable('today');
        $todayStr = $today->format('Y-m-d');

        try {
            $todayCount = self::countOrdersBetween($todayStr, $todayStr);
        } catch (\Throwable $e) {
            error_log('OperatorDashboardInsightsComposer::buildOrdersInsights: ' . $e->getMessage());
            return ['today' => '--', 'trends' => []];
        }

        $dayPrevDate = $today->modify('-1 day');
        $dayPrevCount = self::countOrdersBetween($dayPrevDate->format('Y-m-d'), $dayPrevDate->format('Y-m-d'));

        $weekStart = $today->modify('monday this week');
        $weekElapsedDays = (int)$today->diff($weekStart)->format('%a') + 1;
        $weekCurrentCount = self::countOrdersBetween($weekStart->format('Y-m-d'), $todayStr);
        $prevWeekStart = $weekStart->modify('-7 days');
        $prevWeekEnd = $prevWeekStart->modify('+' . ($weekElapsedDays - 1) . ' days');
        $weekPrevCount = self::countOrdersBetween($prevWeekStart->format('Y-m-d'), $prevWeekEnd->format('Y-m-d'));

        $monthStart = $today->modify('first day of this month');
        $monthCurrentCount = self::countOrdersBetween($monthStart->format('Y-m-d'), $todayStr);
        $monthDayIndex = (int)$today->format('j');
        $prevMonthStart = $monthStart->modify('-1 month');
        $prevMonthLastDay = (int)$prevMonthStart->format('t');
        $prevMonthDay = min($monthDayIndex, $prevMonthLastDay);
        $prevMonthEnd = $prevMonthStart->setDate((int)$prevMonthStart->format('Y'), (int)$prevMonthStart->format('m'), $prevMonthDay);
        $monthPrevCount = self::countOrdersBetween($prevMonthStart->format('Y-m-d'), $prevMonthEnd->format('Y-m-d'));

        $yearStart = $today->setDate((int)$today->format('Y'), 1, 1);
        $yearCurrentCount = self::countOrdersBetween($yearStart->format('Y-m-d'), $todayStr);
        $yearDayIndex = (int)$today->format('z');
        $prevYearStart = $yearStart->modify('-1 year');
        $prevYearEnd = $prevYearStart->modify('+' . $yearDayIndex . ' days');
        $yearPrevCount = self::countOrdersBetween($prevYearStart->format('Y-m-d'), $prevYearEnd->format('Y-m-d'));

        $barRaw = [
            ['period' => $tr('operator.dashboard.period_day', 'Day'), 'value' => self::sumOrderedPartsBetween($todayStr, $todayStr)],
            ['period' => $tr('operator.dashboard.period_week', 'Week'), 'value' => self::sumOrderedPartsBetween($weekStart->format('Y-m-d'), $todayStr)],
            ['period' => $tr('operator.dashboard.period_month', 'Month'), 'value' => self::sumOrderedPartsBetween($monthStart->format('Y-m-d'), $todayStr)],
            ['period' => $tr('operator.dashboard.period_year', 'Year'), 'value' => self::sumOrderedPartsBetween($yearStart->format('Y-m-d'), $todayStr)],
        ];
        $maxBarValue = 0;
        foreach ($barRaw as $barItem) {
            $maxBarValue = max($maxBarValue, (int)($barItem['value'] ?? 0));
        }
        $bars = [];
        foreach ($barRaw as $barItem) {
            $value = (int)($barItem['value'] ?? 0);
            $bars[] = [
                'period' => (string)($barItem['period'] ?? ''),
                'value' => $value,
                'value_label' => number_format($value, 0, '.', ''),
                'percent' => $maxBarValue > 0 ? round(($value / $maxBarValue) * 100, 2) : 0.0,
            ];
        }

        return [
            'today' => $todayCount,
            'trends' => [
                self::makeDeltaTrend($tr('operator.dashboard.period_day', 'Day'), $todayCount, $dayPrevCount),
                self::makeDeltaTrend($tr('operator.dashboard.period_week', 'Week'), $weekCurrentCount, $weekPrevCount),
                self::makeDeltaTrend($tr('operator.dashboard.period_month', 'Month'), $monthCurrentCount, $monthPrevCount),
                self::makeDeltaTrend($tr('operator.dashboard.period_year', 'Year'), $yearCurrentCount, $yearPrevCount),
            ],
            'bars' => $bars,
        ];
    }

    public static function buildPartsInsights(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        try {
            $countRow = DB::fetchOne(
                "SELECT COUNT(DISTINCT product_id) AS c
                 FROM daily_orders
                 WHERE order_date = ?
                   AND (
                       LOWER(COALESCE(coverage_status, '')) = 'low'
                       OR COALESCE(shortage_qty, 0) > 0
                   )",
                [$today]
            );

            $topRows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(SUM(COALESCE(d.shortage_qty, 0)), 0) AS total_shortage,
                    MIN(COALESCE(d.coverage_pct, 100)) AS min_coverage
                 FROM daily_orders d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE d.order_date = ?
                   AND (
                       LOWER(COALESCE(d.coverage_status, '')) = 'low'
                       OR COALESCE(d.shortage_qty, 0) > 0
                   )
                 GROUP BY d.product_id, part_name
                 ORDER BY total_shortage DESC, min_coverage ASC, part_name ASC
                 LIMIT 4",
                [$today]
            );
        } catch (\Throwable $e) {
            error_log('OperatorDashboardInsightsComposer::buildPartsInsights: ' . $e->getMessage());
            return ['count' => '--', 'items' => []];
        }

        $items = [];
        foreach ((array)$topRows as $topRow) {
            $name = trim((string)($topRow['part_name'] ?? ''));
            if ($name !== '') {
                $items[] = $name;
            }
        }

        return ['count' => (int)($countRow['c'] ?? 0), 'items' => $items];
    }

    public static function buildOverstockInsights(callable $tr): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $strictCount = 0;

        try {
            $countRow = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM (
                    SELECT d.product_id
                    FROM daily_orders d
                    WHERE d.order_date = ?
                    GROUP BY d.product_id
                    HAVING COALESCE(MAX(d.usable_stock_qty), 0) > COALESCE(SUM(d.qty), 0)
                 ) overstock",
                [$today]
            );
            $strictCount = (int)($countRow['c'] ?? 0);

            $topRows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(SUM(COALESCE(d.qty, 0)), 0) AS demand_qty,
                    COALESCE(MAX(d.usable_stock_qty), 0) AS stock_qty,
                    CASE
                        WHEN COALESCE(SUM(COALESCE(d.qty, 0)), 0) <= 0 THEN 0
                        ELSE COALESCE(MAX(d.usable_stock_qty), 0) / COALESCE(SUM(COALESCE(d.qty, 0)), 0)
                    END AS days_cover
                 FROM daily_orders d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE d.order_date = ?
                 GROUP BY d.product_id, part_name
                 HAVING stock_qty > demand_qty
                 ORDER BY days_cover DESC, stock_qty DESC, part_name ASC
                 LIMIT 4",
                [$today]
            );

            if ($topRows === []) {
                $topRows = DB::fetchAll(
                    "SELECT
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                        COALESCE(SUM(COALESCE(d.qty, 0)), 0) AS demand_qty,
                        COALESCE(MAX(d.usable_stock_qty), 0) AS stock_qty,
                        CASE
                            WHEN COALESCE(SUM(COALESCE(d.qty, 0)), 0) <= 0 THEN 0
                            ELSE COALESCE(MAX(d.usable_stock_qty), 0) / COALESCE(SUM(COALESCE(d.qty, 0)), 0)
                        END AS days_cover
                     FROM daily_orders d
                     LEFT JOIN products p ON p.id = d.product_id
                     WHERE d.order_date = ?
                     GROUP BY d.product_id, part_name
                     HAVING demand_qty > 0
                     ORDER BY days_cover DESC, stock_qty DESC, part_name ASC
                     LIMIT 4",
                    [$today]
                );
            }
        } catch (\Throwable $e) {
            error_log('OperatorDashboardInsightsComposer::buildOverstockInsights: ' . $e->getMessage());
            return ['count' => '--', 'items' => []];
        }

        $items = [];
        foreach ((array)$topRows as $topRow) {
            $name = trim((string)($topRow['part_name'] ?? ''));
            $daysCover = (float)($topRow['days_cover'] ?? 0);
            if ($name === '') {
                continue;
            }
            $items[] = $name . ' (' . number_format($daysCover, 1, '.', '') . ' ' . $tr('operator.dashboard.days', 'days') . ')';
        }

        return [
            'count' => $strictCount > 0 ? $strictCount : count($items),
            'items' => $items,
        ];
    }

    public static function buildWasteInsights(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        try {
            $totalRow = DB::fetchOne(
                'SELECT COALESCE(SUM(COALESCE(rejected_qty, 0)), 0) AS total_waste
                 FROM production_entries
                 WHERE production_date = ?
                   AND COALESCE(rejected_qty, 0) > 0',
                [$today]
            );

            $topRows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', pe.product_id)) AS part_name,
                    COALESCE(SUM(COALESCE(pe.rejected_qty, 0)), 0) AS waste_qty
                 FROM production_entries pe
                 LEFT JOIN products p ON p.id = pe.product_id
                 WHERE pe.production_date = ?
                   AND COALESCE(pe.rejected_qty, 0) > 0
                 GROUP BY pe.product_id, part_name
                 ORDER BY waste_qty DESC, part_name ASC
                 LIMIT 4",
                [$today]
            );
        } catch (\Throwable $e) {
            error_log('OperatorDashboardInsightsComposer::buildWasteInsights: ' . $e->getMessage());
            return ['total' => '--', 'items' => []];
        }

        $items = [];
        foreach ((array)$topRows as $topRow) {
            $name = trim((string)($topRow['part_name'] ?? ''));
            $wasteQty = (float)($topRow['waste_qty'] ?? 0);
            if ($name === '' || $wasteQty <= 0) {
                continue;
            }
            $items[] = $name . ' (' . number_format($wasteQty, 0, '.', '') . ')';
        }

        return [
            'total' => (string)number_format((float)($totalRow['total_waste'] ?? 0), 0, '.', ''),
            'items' => $items,
        ];
    }

    public static function buildZairyoInsights(): array
    {
        try {
            $countRow = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM (
                    SELECT m.id AS material_id
                    FROM production_plans pp
                    INNER JOIN part_material_map pm ON pm.product_id = pp.product_id
                    INNER JOIN materials m ON m.id = pm.material_id
                    LEFT JOIN (
                        SELECT material_id, SUM(qty_delta) AS on_hand_qty
                        FROM material_ledger
                        GROUP BY material_id
                    ) led ON led.material_id = m.id
                    LEFT JOIN (
                        SELECT material_id, SUM(GREATEST(reserved_qty - fulfilled_qty, 0)) AS reserved_qty
                        FROM material_reservations
                        WHERE LOWER(COALESCE(status, 'active')) IN ('active', 'partial')
                        GROUP BY material_id
                    ) res ON res.material_id = m.id
                    WHERE pp.plan_date = ?
                      AND LOWER(COALESCE(pp.status, 'planned')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
                      AND COALESCE(pm.is_active, 1) = 1
                    GROUP BY m.id, led.on_hand_qty, res.reserved_qty
                    HAVING GREATEST(
                        SUM(pp.planned_qty * COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) * (1 + COALESCE(pm.scrap_pct, 0) / 100))
                        - (COALESCE(led.on_hand_qty, 0) - COALESCE(res.reserved_qty, 0)),
                        0
                    ) > 0
                 ) x",
                [date('Y-m-d')]
            );

            $rows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(m.material_name), ''), CONCAT('Material #', m.id)) AS material_name,
                    GREATEST(
                        SUM(pp.planned_qty * COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) * (1 + COALESCE(pm.scrap_pct, 0) / 100))
                        - (COALESCE(led.on_hand_qty, 0) - COALESCE(res.reserved_qty, 0)),
                        0
                    ) AS shortage_qty,
                    COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom
                 FROM production_plans pp
                 INNER JOIN part_material_map pm ON pm.product_id = pp.product_id
                 INNER JOIN materials m ON m.id = pm.material_id
                 LEFT JOIN (
                     SELECT material_id, SUM(qty_delta) AS on_hand_qty
                     FROM material_ledger
                     GROUP BY material_id
                 ) led ON led.material_id = m.id
                 LEFT JOIN (
                     SELECT material_id, SUM(GREATEST(reserved_qty - fulfilled_qty, 0)) AS reserved_qty
                     FROM material_reservations
                     WHERE LOWER(COALESCE(status, 'active')) IN ('active', 'partial')
                     GROUP BY material_id
                 ) res ON res.material_id = m.id
                 WHERE pp.plan_date = ?
                   AND LOWER(COALESCE(pp.status, 'planned')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
                   AND COALESCE(pm.is_active, 1) = 1
                 GROUP BY m.id, material_name, uom, led.on_hand_qty, res.reserved_qty
                 HAVING shortage_qty > 0
                 ORDER BY shortage_qty ASC, material_name ASC
                 LIMIT 4",
                [date('Y-m-d')]
            );
        } catch (\Throwable $e) {
            error_log('OperatorDashboardInsightsComposer::buildZairyoInsights: ' . $e->getMessage());
            return ['count' => '--', 'items' => []];
        }

        $items = [];
        foreach ((array)$rows as $row) {
            $name = trim((string)($row['material_name'] ?? ''));
            $qty = (float)($row['shortage_qty'] ?? 0);
            $uom = trim((string)($row['uom'] ?? $row['unit'] ?? 'kg'));
            if ($name === '' || $qty <= 0.0001) {
                continue;
            }
            $items[] = $name . ' (' . number_format($qty, 2, '.', '') . ' ' . ($uom !== '' ? $uom : 'kg') . ')';
        }

        return [
            'count' => (int)($countRow['c'] ?? 0),
            'items' => $items,
        ];
    }

    public static function buildDispatchInsights(callable $tr): array
    {
        $today = date('Y-m-d');

        try {
            $row = DB::fetchOne(
                "SELECT
                    COALESCE(SUM(CASE
                        WHEN LOWER(COALESCE(dispatch_status, '')) IN ('hold', 'blocked')
                          OR LOWER(COALESCE(status_reason, '')) LIKE '%delay%'
                          OR LOWER(COALESCE(status_note, '')) LIKE '%delay%'
                          OR LOWER(COALESCE(remarks, '')) LIKE '%delay%'
                        THEN 1 ELSE 0 END), 0) AS delayed_count,
                    COALESCE(SUM(CASE
                        WHEN LOWER(COALESCE(status_reason, '')) LIKE '%postpon%'
                          OR LOWER(COALESCE(status_note, '')) LIKE '%postpon%'
                          OR LOWER(COALESCE(remarks, '')) LIKE '%postpon%'
                        THEN 1 ELSE 0 END), 0) AS postponed_count,
                    COALESCE(SUM(CASE
                        WHEN LOWER(COALESCE(dispatch_status, '')) IN ('cancelled', 'canceled')
                          OR LOWER(COALESCE(status_reason, '')) LIKE '%cancel%'
                          OR LOWER(COALESCE(status_note, '')) LIKE '%cancel%'
                          OR LOWER(COALESCE(remarks, '')) LIKE '%cancel%'
                        THEN 1 ELSE 0 END), 0) AS cancelled_count,
                    COALESCE(SUM(CASE
                        WHEN LOWER(COALESCE(status_reason, '')) LIKE '%prepon%'
                          OR LOWER(COALESCE(status_note, '')) LIKE '%prepon%'
                          OR LOWER(COALESCE(remarks, '')) LIKE '%prepon%'
                        THEN 1 ELSE 0 END), 0) AS preponned_count
                 FROM dispatch_entries
                 WHERE dispatch_date = ?",
                [$today]
            );
        } catch (\Throwable $e) {
            error_log('OperatorDashboardInsightsComposer::buildDispatchInsights: ' . $e->getMessage());
            return ['total' => '--', 'items' => []];
        }

        $delayed = (int)($row['delayed_count'] ?? 0);
        $postponed = (int)($row['postponed_count'] ?? 0);
        $cancelled = (int)($row['cancelled_count'] ?? 0);
        $preponned = (int)($row['preponned_count'] ?? 0);

        $items = [
            $tr('operator.dashboard.dispatch_delayed', 'Delayed') . ' (' . $delayed . ')',
            $tr('operator.dashboard.dispatch_postponed', 'Postponed') . ' (' . $postponed . ')',
            $tr('operator.dashboard.dispatch_cancelled', 'Cancelled') . ' (' . $cancelled . ')',
            $tr('operator.dashboard.dispatch_preponned', 'Preponed') . ' (' . $preponned . ')',
        ];

        return [
            'total' => $delayed + $postponed + $cancelled + $preponned,
            'items' => $items,
        ];
    }

    private static function countOrdersBetween(string $fromDate, string $toDate): int
    {
        $row = DB::fetchOne(
            'SELECT COUNT(*) AS c FROM daily_orders WHERE order_date BETWEEN ? AND ?',
            [$fromDate, $toDate]
        );
        return (int)($row['c'] ?? 0);
    }

    private static function sumOrderedPartsBetween(string $fromDate, string $toDate): int
    {
        $row = DB::fetchOne(
            'SELECT COALESCE(SUM(COALESCE(qty, 0)), 0) AS total_qty FROM daily_orders WHERE order_date BETWEEN ? AND ?',
            [$fromDate, $toDate]
        );
        return (int)round((float)($row['total_qty'] ?? 0));
    }

    private static function makeDeltaTrend(string $period, int $current, int $previous): array
    {
        $delta = $current - $previous;
        $tone = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');

        if ($previous === 0) {
            $deltaPct = $current === 0 ? 0.0 : 100.0;
        } else {
            $deltaPct = ($delta / $previous) * 100;
        }

        return [
            'period' => $period,
            'delta_label' => sprintf('%+d (%0.1f%%)', $delta, $deltaPct),
            'tone' => $tone,
        ];
    }
}
