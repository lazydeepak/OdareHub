<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services\OperatorLayerAdapters;

use App\Core\DB;

/**
 * DailyOrdersAdapter — operator-layer adapter for the daily_orders table.
 * Provides a date-filtered, status-filtered view of customer orders by part.
 */
final class DailyOrdersAdapter
{
    /**
     * @param array<string,mixed> $query
     * @return array<string,mixed>
     */
    public static function getData(array $query = []): array
    {
        $defaults = [
            'summary'    => [
                'total_orders'   => 0,
                'total_qty'      => 0.0,
                'shortage_count' => 0,
                'total_shortage' => 0.0,
                'covered_count'  => 0,
                'total_dispatched' => 0.0,
                'low_coverage_count' => 0,
                'total_open_demand' => 0.0,
                'total_planned_supply' => 0.0,
            ],
            'rows'       => [],
            'date'       => date('Y-m-d'),
            'status'     => 'open',
            'coverage'   => 'all',
            'q'          => '',
            'range_mode' => 'week',   // 'day' | 'week'
            'empty'      => true,
            'error'      => '',
        ];

        try {
            $rawDate   = trim((string)($query['date'] ?? date('Y-m-d')));
            $anchorDate = self::sanitizeDate($rawDate, date('Y-m-d'));

            $statusFilter = strtolower(trim((string)($query['status'] ?? 'open')));
            if (!in_array($statusFilter, ['open', 'all', 'dispatched'], true)) {
                $statusFilter = 'open';
            }

            $coverageFilter = strtolower(trim((string)($query['coverage'] ?? 'all')));
            if (!in_array($coverageFilter, ['all', 'low', 'partial', 'full'], true)) {
                $coverageFilter = 'all';
            }

            $search = trim((string)($query['q'] ?? ''));
            if (strlen($search) > 80) {
                $search = substr($search, 0, 80);
            }

            $rangeMode = strtolower(trim((string)($query['range'] ?? 'week')));
            if (!in_array($rangeMode, ['day', 'week'], true)) {
                $rangeMode = 'week';
            }

            if ($rangeMode === 'week') {
                $fromDate = (new \DateTimeImmutable($anchorDate))->modify('monday this week')->format('Y-m-d');
                $toDate   = (new \DateTimeImmutable($anchorDate))->modify('sunday this week')->format('Y-m-d');
            } else {
                $fromDate = $anchorDate;
                $toDate   = $anchorDate;
            }

            $statusCondition = '';
            $params = [$fromDate, $toDate];
            if ($statusFilter !== 'all') {
                $statusCondition = ' AND LOWER(d.status) = ?';
                $params[] = $statusFilter;
            }

            $coverageCondition = '';
            if ($coverageFilter !== 'all') {
                $coverageMap = [
                    'low' => 'low',
                    'partial' => 'partial',
                    'full' => 'full',
                ];
                $coverageCondition = ' AND LOWER(d.coverage_status) = ?';
                $params[] = $coverageMap[$coverageFilter] ?? 'low';
            }

            $searchCondition = '';
            if ($search !== '') {
                $searchCondition = " AND (d.customer_name LIKE ? OR p.parts_name LIKE ? OR p.parts_number LIKE ? OR CAST(d.id AS CHAR) LIKE ?)";
                $like = '%' . $search . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $columns = self::dailyOrderColumns();
            $usableStockExpr = self::columnExpr($columns, 'usable_stock_qty', 'usable_stock_qty');
            $qcPassExpr = self::columnExpr($columns, 'qc_pass_qty', 'qc_pass_qty');
            $dispatchedExpr = self::columnExpr($columns, 'dispatched_qty', 'dispatched_qty');
            $usableSupplyExpr = self::columnExpr($columns, 'usable_supply_qty', 'usable_supply_qty');
            $openDemandExpr = self::columnExpr($columns, 'open_demand_qty', 'open_demand_qty');
            $forecastPressureExpr = self::columnExpr($columns, 'forecast_pressure_qty', 'forecast_pressure_qty');
            $lastRecalcExpr = self::columnExpr($columns, 'coverage_last_recalculated_at', 'coverage_last_recalculated_at', 'NULL');

            $sql = "
                SELECT
                    d.id,
                    d.order_date,
                    COALESCE(d.required_date, d.order_date) AS required_date,
                    d.customer_name,
                    d.product_id,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                    d.qty,
                    d.shortage_qty,
                    d.coverage_pct,
                    d.coverage_status,
                    d.planned_supply_qty,
                    {$usableStockExpr},
                    {$qcPassExpr},
                    {$dispatchedExpr},
                    {$usableSupplyExpr},
                    {$openDemandExpr},
                    {$forecastPressureExpr},
                    {$lastRecalcExpr},
                    d.status,
                    d.notes
                FROM daily_orders d
                LEFT JOIN products p ON p.id = d.product_id
                WHERE d.order_date BETWEEN ? AND ?
                {$statusCondition}
                {$coverageCondition}
                {$searchCondition}
                ORDER BY d.order_date ASC, d.coverage_pct ASC, d.customer_name ASC
                LIMIT 500
            ";

            $rows = DB::fetchAll($sql, $params);

            if (!is_array($rows)) {
                $rows = [];
            }

            $shaped     = array_values(array_map([self::class, 'shapeRow'], $rows));
            $summary    = self::summarize($shaped);

            $defaults['rows']       = $shaped;
            $defaults['summary']    = $summary;
            $defaults['date']       = $anchorDate;
            $defaults['status']     = $statusFilter;
            $defaults['coverage']   = $coverageFilter;
            $defaults['q']          = $search;
            $defaults['range_mode'] = $rangeMode;
            $defaults['empty']      = ($shaped === []);
        } catch (\Throwable $e) {
            error_log('DailyOrdersAdapter::getData: ' . $e->getMessage());
            $defaults['error'] = 'operator.orders.error.load';
        }

        return $defaults;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function shapeRow(array $row): array
    {
        $qty        = round((float)($row['qty'] ?? 0), 2);
        $shortage   = round(max(0.0, (float)($row['shortage_qty'] ?? 0)), 2);
        $coveragePct = round((float)($row['coverage_pct'] ?? 0), 1);
        $dispatched = round((float)($row['dispatched_qty'] ?? 0), 2);
        $status     = strtolower(trim((string)($row['status'] ?? 'open')));
        $coverageStatus = strtolower(trim((string)($row['coverage_status'] ?? 'low')));

        $tone = 'success';
        if ($shortage > 0) {
            $tone = 'danger';
        } elseif ($coveragePct < 100 || in_array($coverageStatus, ['low', 'partial'], true)) {
            $tone = 'warning';
        }

        return [
            'id'           => (int)($row['id'] ?? 0),
            'order_date'   => (string)($row['order_date'] ?? ''),
            'required_date'=> (string)($row['required_date'] ?? ''),
            'customer_name'=> (string)($row['customer_name'] ?? ''),
            'product_id'   => (int)($row['product_id'] ?? 0),
            'part_name'    => (string)($row['part_name'] ?? ''),
            'part_number'  => (string)($row['part_number'] ?? ''),
            'qty'          => $qty,
            'shortage_qty' => $shortage,
            'coverage_pct' => $coveragePct,
            'coverage_status' => $coverageStatus,
            'planned_supply_qty' => round((float)($row['planned_supply_qty'] ?? 0), 2),
            'usable_stock_qty' => round((float)($row['usable_stock_qty'] ?? 0), 2),
            'qc_pass_qty' => round((float)($row['qc_pass_qty'] ?? 0), 2),
            'usable_supply_qty' => round((float)($row['usable_supply_qty'] ?? 0), 2),
            'open_demand_qty' => round((float)($row['open_demand_qty'] ?? 0), 2),
            'forecast_pressure_qty' => round((float)($row['forecast_pressure_qty'] ?? 0), 2),
            'dispatched_qty'=> $dispatched,
            'coverage_last_recalculated_at' => (string)($row['coverage_last_recalculated_at'] ?? ''),
            'status'       => $status,
            'notes'        => (string)($row['notes'] ?? ''),
            'tone'         => $tone,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,int|float>
     */
    private static function summarize(array $rows): array
    {
        $totalOrders   = count($rows);
        $totalQty      = 0.0;
        $shortageCount = 0;
        $totalShortage = 0.0;
        $coveredCount  = 0;
        $totalDispatched = 0.0;
        $lowCoverageCount = 0;
        $totalOpenDemand = 0.0;
        $totalPlannedSupply = 0.0;

        foreach ($rows as $row) {
            $totalQty     += (float)($row['qty'] ?? 0);
            $shortage      = (float)($row['shortage_qty'] ?? 0);
            $totalShortage += $shortage;
            $totalDispatched += (float)($row['dispatched_qty'] ?? 0);
            $totalOpenDemand += (float)($row['open_demand_qty'] ?? 0);
            $totalPlannedSupply += (float)($row['planned_supply_qty'] ?? 0);
            if ($shortage > 0) {
                $shortageCount++;
            } else {
                $coveredCount++;
            }
            if ((float)($row['coverage_pct'] ?? 0) < 100 || in_array((string)($row['coverage_status'] ?? ''), ['low', 'partial'], true)) {
                $lowCoverageCount++;
            }
        }

        return [
            'total_orders'   => $totalOrders,
            'total_qty'      => round($totalQty, 2),
            'shortage_count' => $shortageCount,
            'total_shortage' => round($totalShortage, 2),
            'covered_count'  => $coveredCount,
            'total_dispatched' => round($totalDispatched, 2),
            'low_coverage_count' => $lowCoverageCount,
            'total_open_demand' => round($totalOpenDemand, 2),
            'total_planned_supply' => round($totalPlannedSupply, 2),
        ];
    }

    /**
     * @return array<string,bool>
     */
    private static function dailyOrderColumns(): array
    {
        static $columns = null;
        if (is_array($columns)) {
            return $columns;
        }

        $columns = [];
        foreach (DB::fetchAll('SHOW COLUMNS FROM daily_orders') as $row) {
            $field = (string)($row['Field'] ?? '');
            if ($field !== '') {
                $columns[$field] = true;
            }
        }

        return $columns;
    }

    /**
     * @param array<string,bool> $columns
     */
    private static function columnExpr(array $columns, string $column, string $alias, string $fallback = '0'): string
    {
        if (isset($columns[$column])) {
            return 'COALESCE(d.`' . $column . '`, ' . $fallback . ') AS `' . $alias . '`';
        }

        return $fallback . ' AS `' . $alias . '`';
    }

    private static function sanitizeDate(string $value, string $fallback): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $ts = strtotime($value);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        }
        return $fallback;
    }
}
