<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;

final class PartsMasterSnapshotService
{
    private const CLOSED_ORDER_STATUSES = ['closed', 'completed', 'cancelled', 'canceled'];
    private const DEFAULT_SORT = 'coverage_balance_qty';
    private const DEFAULT_DIR = 'asc';

    /**
     * @return array<string,mixed>
     */
    public static function build(
        string $search,
        string $active,
        string $anchorDate,
        int $userId,
        string $scope,
        string $sort,
        string $dir
    ): array {
        $anchorDate = self::normalizeDate($anchorDate);
        $requestedScope = strtolower(trim($scope));
        $sort = self::normalizeSort($sort);
        $dir = self::normalizeDirection($dir);

        $assignedPartIds = $userId > 0 ? self::assignedPartIds($userId) : [];
        $baseRows = self::baseRows($search, $active, $userId);
        $rows = self::hydrateOperationalMetrics($baseRows, $anchorDate, $assignedPartIds);

        $scopeMode = in_array($requestedScope, ['my', 'all'], true)
            ? $requestedScope
            : 'my';
        $scopeSource = $assignedPartIds !== [] ? 'assigned_parts' : 'top_risk_fallback';

        if ($scopeMode === 'my') {
            if ($assignedPartIds !== []) {
                $rows = array_values(array_filter($rows, static function (array $row): bool {
                    return !empty($row['is_assigned']);
                }));
            } else {
                $riskRanked = $rows;
                self::sortRows($riskRanked, 'coverage_balance_qty', 'asc');
                $riskIds = array_map(static fn(array $row): int => (int)($row['part_id'] ?? 0), array_slice($riskRanked, 0, 5));
                $rows = array_values(array_filter($rows, static function (array $row) use ($riskIds): bool {
                    return in_array((int)($row['part_id'] ?? 0), $riskIds, true);
                }));
            }
        }

        self::sortRows($rows, $sort, $dir);

        return [
            'rows' => $rows,
            'anchor_date' => $anchorDate,
            'scope_mode' => $scopeMode,
            'scope_source' => $scopeSource,
            'assigned_part_ids' => $assignedPartIds,
            'has_assigned_parts' => $assignedPartIds !== [],
            'sort' => $sort,
            'dir' => $dir,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function baseRows(string $search, string $active, int $userId): array
    {
        $params = [];
        $columns = self::productColumnMap();
        $hasLedger = self::tableExists('stock_ledger_entries');

        $stockSql = $hasLedger
            ? 'COALESCE(lb.stock_balance, 0)'
            : '0';
        $leadSql = isset($columns['default_procurement_lead_days'])
            ? 'COALESCE(p.default_procurement_lead_days, 0)'
            : '0';
        $safetySql = isset($columns['safety_stock_qty'])
            ? 'COALESCE(p.safety_stock_qty, ' . (isset($columns['max_buffer_qty']) ? 'p.max_buffer_qty' : '0') . ', 0)'
            : (isset($columns['max_buffer_qty']) ? 'COALESCE(p.max_buffer_qty, 0)' : '0');
        $assignedSql = $userId > 0
            ? 'CASE WHEN uos.user_id IS NULL THEN 0 ELSE 1 END'
            : '0';

        $itemRefSql = isset($columns['item_ref']) ? ', p.item_ref AS item_ref' : '';
        $sql = "SELECT
                    p.id AS part_id,
                    p.parts_name AS part_name,
                    p.parts_number AS part_number,
                    {$stockSql} AS stock_qty,
                    {$leadSql} AS lead_workdays,
                    {$safetySql} AS safety_stock_qty,
                    p.is_active,
                    p.updated_at,
                    {$assignedSql} AS is_assigned
                    {$itemRefSql}
                FROM products p";

        if ($hasLedger) {
            $sql .= "
                LEFT JOIN (
                    SELECT product_id, COALESCE(SUM(qty_delta), 0) AS stock_balance
                    FROM stock_ledger_entries
                    GROUP BY product_id
                ) lb ON lb.product_id = p.id";
        }

        if ($userId > 0) {
            $sql .= "
                LEFT JOIN (
                    SELECT DISTINCT part_id, user_id
                    FROM user_operational_scopes
                    WHERE user_id = ?
                      AND is_active = 1
                ) uos ON uos.part_id = p.id";
            $params[] = $userId;
        }

        $sql .= ' WHERE 1=1';

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $search . '%';
            $sql .= ' AND (p.parts_name LIKE ? OR p.parts_number LIKE ? OR COALESCE(p.model, \'\') LIKE ? OR COALESCE(p.producer, \'\') LIKE ?)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($active === '1' || $active === '0') {
            $sql .= ' AND p.is_active = ?';
            $params[] = (int)$active;
        }

        $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 500';

        return DB::fetchAll($sql, $params);
    }

    /**
     * @param array<int,array<string,mixed>> $baseRows
     * @param array<int,int> $assignedPartIds
     * @return array<int,array<string,mixed>>
     */
    private static function hydrateOperationalMetrics(array $baseRows, string $anchorDate, array $assignedPartIds): array
    {
        if ($baseRows === []) {
            return [];
        }

        $productIds = [];
        $targetDates = [];
        foreach ($baseRows as $row) {
            $productId = (int)($row['part_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $productIds[] = $productId;
            $leadDays = max(0, (int)ceil((float)($row['lead_workdays'] ?? 0)));
            $targetDates[] = self::addWorkdays($anchorDate, $leadDays);
        }

        $demandMap = self::dailyOrderQtyByDate(array_values(array_unique($productIds)), array_values(array_unique($targetDates)));
        $rows = [];

        foreach ($baseRows as $row) {
            $productId = (int)($row['part_id'] ?? 0);
            $leadDays = max(0, (int)ceil((float)($row['lead_workdays'] ?? 0)));
            $targetDate = self::addWorkdays($anchorDate, $leadDays);
            $demandQty = (float)($demandMap[$productId . '|' . $targetDate] ?? 0.0);
            $stockQty = round((float)($row['stock_qty'] ?? 0), 2);
            $safetyStockQty = round(max(0.0, (float)($row['safety_stock_qty'] ?? 0)), 2);
            $targetTodayQty = round($demandQty + $safetyStockQty, 2);

            $rows[] = [
                'part_id' => $productId,
                'part_name' => (string)($row['part_name'] ?? ''),
                'part_number' => (string)($row['part_number'] ?? ''),
                'stock_qty' => $stockQty,
                'lead_workdays' => $leadDays,
                'safety_stock_qty' => $safetyStockQty,
                'target_date' => $targetDate,
                'target_demand_qty' => round($demandQty, 2),
                'target_today_qty' => $targetTodayQty,
                'coverage_balance_qty' => round($stockQty - $targetTodayQty, 2),
                'is_active' => (int)($row['is_active'] ?? 1),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'is_assigned' => !empty($row['is_assigned']) || in_array($productId, $assignedPartIds, true),
            ];
        }

        return $rows;
    }

    /**
     * @param array<int,int> $productIds
     * @param array<int,string> $targetDates
     * @return array<string,float>
     */
    private static function dailyOrderQtyByDate(array $productIds, array $targetDates): array
    {
        if ($productIds === [] || $targetDates === [] || !self::tableExists('daily_orders')) {
            return [];
        }

        $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
        $datePlaceholders = implode(',', array_fill(0, count($targetDates), '?'));
        $statusPlaceholders = implode(',', array_fill(0, count(self::CLOSED_ORDER_STATUSES), '?'));

        $params = array_merge($productIds, $targetDates, self::CLOSED_ORDER_STATUSES);
        $rows = DB::fetchAll(
            "SELECT
                product_id,
                COALESCE(required_date, order_date) AS demand_date,
                COALESCE(SUM(qty), 0) AS target_qty
             FROM daily_orders
             WHERE product_id IN ({$productPlaceholders})
               AND COALESCE(required_date, order_date) IN ({$datePlaceholders})
               AND LOWER(COALESCE(status, '')) NOT IN ({$statusPlaceholders})
             GROUP BY product_id, COALESCE(required_date, order_date)",
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $productId = (int)($row['product_id'] ?? 0);
            $demandDate = (string)($row['demand_date'] ?? '');
            if ($productId <= 0 || $demandDate === '') {
                continue;
            }
            $map[$productId . '|' . $demandDate] = round((float)($row['target_qty'] ?? 0), 2);
        }

        return $map;
    }

    /**
     * @return array<int,int>
     */
    private static function assignedPartIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = DB::fetchAll(
            'SELECT DISTINCT part_id
             FROM user_operational_scopes
             WHERE user_id = ?
               AND is_active = 1
               AND part_id IS NOT NULL',
            [$userId]
        );

        return array_values(array_filter(array_map(static fn(array $row): int => (int)($row['part_id'] ?? 0), $rows)));
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function sortRows(array &$rows, string $sort, string $dir): void
    {
        usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
            $left = $a[$sort] ?? null;
            $right = $b[$sort] ?? null;

            if (in_array($sort, ['part_id', 'stock_qty', 'lead_workdays', 'safety_stock_qty', 'target_demand_qty', 'target_today_qty', 'coverage_balance_qty', 'is_active'], true)) {
                $cmp = ((float)$left <=> (float)$right);
            } else {
                $cmp = strcmp(strtolower(trim((string)$left)), strtolower(trim((string)$right)));
            }

            if ($cmp === 0) {
                $cmp = strcmp(strtolower(trim((string)($a['part_name'] ?? ''))), strtolower(trim((string)($b['part_name'] ?? ''))));
            }

            return $dir === 'desc' ? -$cmp : $cmp;
        });
    }

    private static function normalizeDate(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        return date('Y-m-d');
    }

    private static function normalizeSort(string $value): string
    {
        $value = strtolower(trim($value));
        $allowed = [
            'part_id',
            'part_name',
            'part_number',
            'stock_qty',
            'target_today_qty',
            'coverage_balance_qty',
            'is_active',
            'updated_at',
        ];

        return in_array($value, $allowed, true) ? $value : self::DEFAULT_SORT;
    }

    private static function normalizeDirection(string $value): string
    {
        $value = strtolower(trim($value));
        return $value === 'desc' ? 'desc' : self::DEFAULT_DIR;
    }

    private static function addWorkdays(string $anchorDate, int $workdays): string
    {
        $date = new \DateTimeImmutable($anchorDate);
        $remaining = max(0, $workdays);

        while ($remaining > 0) {
            $date = $date->modify('+1 day');
            $dayOfWeek = (int)$date->format('N');
            if ($dayOfWeek >= 6) {
                continue;
            }
            $remaining--;
        }

        return $date->format('Y-m-d');
    }

    /**
     * @return array<string,bool>
     */
    private static function productColumnMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = [];
        foreach (DB::fetchAll('SHOW COLUMNS FROM products') as $column) {
            $name = (string)($column['Field'] ?? '');
            if ($name !== '') {
                $map[$name] = true;
            }
        }

        return $map;
    }

    private static function tableExists(string $table): bool
    {
        $escaped = DB::conn()->real_escape_string($table);
        return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
    }
}
