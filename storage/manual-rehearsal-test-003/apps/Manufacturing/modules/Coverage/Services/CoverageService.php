<?php
declare(strict_types=1);

namespace Plugins\Coverage\Services;

use App\Core\AuditLogService;
use App\Core\Auth;
use App\Core\DB;

final class CoverageService
{
    private const CLOSED_ORDER_STATUSES = ['closed', 'completed', 'cancelled', 'canceled'];
    private const VALID_PLAN_STATUSES = ['planned', 'scheduled', 'inprogress', 'in progress', 'released', 'confirmed', 'approved', 'ready'];
    private const INVALID_QC_STATUSES = ['cancelled', 'canceled', 'void', 'rejected'];
    private const VALID_DISPATCHED_STATUSES = ['partial', 'dispatched', 'completed', 'closed', 'delivered'];

    /** @var null|callable(array<int,int>):void */
    private static $handoffSyncResolver = null;

    /** @var null|callable():array<string,string> */
    private static $auditContextResolver = null;

    /**
     * Register app-owned hooks that extend core coverage processing.
     *
     * @param null|callable(array<int,int>):void $handoffSyncResolver
     * @param null|callable():array<string,string> $auditContextResolver
     */
    public static function setResolvers($handoffSyncResolver, $auditContextResolver): void
    {
        self::$handoffSyncResolver = is_callable($handoffSyncResolver) ? $handoffSyncResolver : null;
        self::$auditContextResolver = is_callable($auditContextResolver) ? $auditContextResolver : null;
    }

    public static function recalculateForProduct(int $productId): void
    {
        if ($productId <= 0) {
            return;
        }
        if (!self::tableExists('daily_orders')) {
            return;
        }

        $beforeSummary = self::coverageSummary($productId);

        $openRows = DB::fetchAll(
            'SELECT id, qty
             FROM daily_orders
             WHERE product_id = ? AND ' . self::openStatusSql('status') . '
             ORDER BY order_date ASC, required_date ASC, id ASC',
            array_merge([$productId], self::CLOSED_ORDER_STATUSES)
        );

        $columns = self::dailyOrdersColumnMap();

        if (empty($openRows)) {
            if (isset($columns['coverage_last_recalculated_at'])) {
                DB::query(
                    'UPDATE daily_orders SET coverage_last_recalculated_at = NOW() WHERE product_id = ?',
                    [$productId]
                );
            }
            self::logCoverageAudit($productId, $beforeSummary, self::coverageSummary($productId));
            return;
        }

        $usableStockQty = self::usableStockQty($productId);
        $plannedSupplyQty = self::plannedSupplyQty($productId);
        $qcPassQty = self::qcPassQty($productId);
        $dispatchedQty = self::dispatchedQty($productId);
        $usableSupplyQty = round(max(0.0, $usableStockQty + $plannedSupplyQty + $qcPassQty - $dispatchedQty), 2);
        $openDemandQty = round(array_reduce($openRows, static function (float $sum, array $row): float {
            return $sum + (float)($row['qty'] ?? 0);
        }, 0.0), 2);
        $forecastPressureQty = self::forecastPressureQty($productId);

        $allocated = 0.0;
        foreach ($openRows as $row) {
            $orderId = (int)($row['id'] ?? 0);
            $orderQty = round(max(0.0, (float)($row['qty'] ?? 0)), 2);
            if ($orderId <= 0) {
                continue;
            }

            $availableBeforeOrder = round(max(0.0, $usableSupplyQty - $allocated), 2);
            $allocatedToOrder = round(min($orderQty, $availableBeforeOrder), 2);
            $shortageQty = round(max(0.0, $orderQty - $allocatedToOrder), 2);
            $coveragePct = $orderQty > 0 ? round(($allocatedToOrder / $orderQty) * 100, 2) : 0.0;
            $coverageStatus = self::coverageStatus($coveragePct, $shortageQty);

            $setParts = [];
            $params = [];

            self::pushUpdateField($columns, $setParts, $params, 'coverage_pct', $coveragePct);
            self::pushUpdateField($columns, $setParts, $params, 'coverage_status', $coverageStatus);
            self::pushUpdateField($columns, $setParts, $params, 'shortage_qty', $shortageQty);
            self::pushUpdateField($columns, $setParts, $params, 'planned_supply_qty', $plannedSupplyQty);
            self::pushUpdateField($columns, $setParts, $params, 'usable_stock_qty', $usableStockQty);
            self::pushUpdateField($columns, $setParts, $params, 'qc_pass_qty', $qcPassQty);
            self::pushUpdateField($columns, $setParts, $params, 'dispatched_qty', $dispatchedQty);
            self::pushUpdateField($columns, $setParts, $params, 'usable_supply_qty', $usableSupplyQty);
            self::pushUpdateField($columns, $setParts, $params, 'open_demand_qty', $openDemandQty);
            self::pushUpdateField($columns, $setParts, $params, 'forecast_pressure_qty', $forecastPressureQty);
            if (isset($columns['coverage_last_recalculated_at'])) {
                $setParts[] = 'coverage_last_recalculated_at = NOW()';
            }
            if (isset($columns['updated_at'])) {
                $setParts[] = 'updated_at = NOW()';
            }

            if (!empty($setParts)) {
                $params[] = $orderId;
                DB::query(
                    'UPDATE daily_orders SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1',
                    $params
                );
            }

            $allocated = round($allocated + $allocatedToOrder, 2);
        }

        $closedSetParts = [];
        $closedParams = [];
        self::pushUpdateField($columns, $closedSetParts, $closedParams, 'usable_stock_qty', $usableStockQty);
        self::pushUpdateField($columns, $closedSetParts, $closedParams, 'qc_pass_qty', $qcPassQty);
        self::pushUpdateField($columns, $closedSetParts, $closedParams, 'dispatched_qty', $dispatchedQty);
        self::pushUpdateField($columns, $closedSetParts, $closedParams, 'usable_supply_qty', $usableSupplyQty);
        self::pushUpdateField($columns, $closedSetParts, $closedParams, 'open_demand_qty', $openDemandQty);
        self::pushUpdateField($columns, $closedSetParts, $closedParams, 'forecast_pressure_qty', $forecastPressureQty);
        if (isset($columns['coverage_last_recalculated_at'])) {
            $closedSetParts[] = 'coverage_last_recalculated_at = NOW()';
        }
        if (isset($columns['updated_at'])) {
            $closedSetParts[] = 'updated_at = NOW()';
        }

        if (!empty($closedSetParts)) {
            $closedParams[] = $productId;
            $closedParams = array_merge($closedParams, self::CLOSED_ORDER_STATUSES);
            DB::query(
                'UPDATE daily_orders SET ' . implode(', ', $closedSetParts) . ' WHERE product_id = ? AND NOT (' . self::openStatusSql('status') . ')',
                $closedParams
            );
        }

        self::logCoverageAudit($productId, $beforeSummary, self::coverageSummary($productId));
    }

    public static function recalculateForProducts(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));
        foreach ($ids as $productId) {
            self::recalculateForProduct($productId);
        }

        if (!empty($ids) && is_callable(self::$handoffSyncResolver)) {
            try {
                call_user_func(self::$handoffSyncResolver, $ids);
            } catch (\Throwable $e) {
                // Keep coverage recalculation resilient when optional app hooks fail.
            }
        }
    }

    public static function recalculateAllOpenOrders(): void
    {
        $rows = DB::fetchAll(
            'SELECT DISTINCT product_id FROM daily_orders WHERE ' . self::openStatusSql('status'),
            self::CLOSED_ORDER_STATUSES
        );
        $ids = array_map(static fn (array $row): int => (int)($row['product_id'] ?? 0), $rows);
        self::recalculateForProducts($ids);
    }

    private static function openStatusSql(string $column): string
    {
        $placeholders = implode(',', array_fill(0, count(self::CLOSED_ORDER_STATUSES), '?'));
        return "LOWER(COALESCE({$column}, '')) NOT IN ({$placeholders})";
    }

    private static function usableStockQty(int $productId): float
    {
        if (!self::tableExists('stock_ledger_entries')) {
            return 0.0;
        }
        $row = DB::fetchOne(
            'SELECT COALESCE(SUM(qty_delta), 0) AS total
             FROM stock_ledger_entries
             WHERE product_id = ?',
            [$productId]
        );
        return round(max(0.0, (float)($row['total'] ?? 0)), 2);
    }

    private static function plannedSupplyQty(int $productId): float
    {
        if (!self::tableExists('production_plans')) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count(self::VALID_PLAN_STATUSES), '?'));
        $params = array_merge([$productId], self::VALID_PLAN_STATUSES);
        $row = DB::fetchOne(
            'SELECT COALESCE(SUM(planned_qty), 0) AS total
             FROM production_plans
             WHERE product_id = ?
                             AND LOWER(COALESCE(status, \'\')) IN (' . $placeholders . ')',
            $params
        );
        return round(max(0.0, (float)($row['total'] ?? 0)), 2);
    }

    private static function qcPassQty(int $productId): float
    {
        if (!self::tableExists('qc_entries')) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count(self::INVALID_QC_STATUSES), '?'));
        $params = array_merge([$productId], self::INVALID_QC_STATUSES);
        $row = DB::fetchOne(
            'SELECT COALESCE(SUM(pass_qty), 0) AS total
             FROM qc_entries
             WHERE product_id = ?
                             AND LOWER(COALESCE(status, \'\')) NOT IN (' . $placeholders . ')',
            $params
        );
        return round(max(0.0, (float)($row['total'] ?? 0)), 2);
    }

    private static function dispatchedQty(int $productId): float
    {
        if (!self::tableExists('dispatch_entries')) {
            return 0.0;
        }
        $placeholders = implode(',', array_fill(0, count(self::VALID_DISPATCHED_STATUSES), '?'));
        $params = array_merge([$productId], self::VALID_DISPATCHED_STATUSES);
        $row = DB::fetchOne(
            'SELECT COALESCE(SUM(dispatchable_qty), 0) AS total
             FROM dispatch_entries
             WHERE product_id = ?
                             AND LOWER(COALESCE(dispatch_status, \'\')) IN (' . $placeholders . ')',
            $params
        );
        return round(max(0.0, (float)($row['total'] ?? 0)), 2);
    }

    private static function forecastPressureQty(int $productId): float
    {
        if (!self::tableExists('pre_orders')) {
            return 0.0;
        }
        $row = DB::fetchOne(
            'SELECT COALESCE(SUM(CASE WHEN balance_qty > 0 THEN balance_qty ELSE planned_qty END), 0) AS total
             FROM pre_orders
             WHERE product_id = ?',
            [$productId]
        );
        return round(max(0.0, (float)($row['total'] ?? 0)), 2);
    }

    private static function coverageStatus(float $coveragePct, float $shortageQty): string
    {
        if ($shortageQty <= 0.0 || $coveragePct >= 100.0) {
            return 'Full';
        }
        if ($coveragePct > 0.0) {
            return 'Partial';
        }
        return 'Low';
    }

    private static function tableExists(string $tableName): bool
    {
        $escaped = DB::conn()->real_escape_string($tableName);
        return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
    }

    private static function dailyOrdersColumnMap(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $cache = [];
        $cols = DB::fetchAll('SHOW COLUMNS FROM daily_orders');
        foreach ($cols as $col) {
            $name = (string)($col['Field'] ?? '');
            if ($name !== '') {
                $cache[$name] = true;
            }
        }
        return $cache;
    }

    private static function pushUpdateField(array $columns, array &$setParts, array &$params, string $field, mixed $value): void
    {
        if (!isset($columns[$field])) {
            return;
        }
        $setParts[] = $field . ' = ?';
        $params[] = $value;
    }

    /**
     * @return array<string,mixed>
     */
    private static function coverageSummary(int $productId): array
    {
        if (!self::tableExists('daily_orders') || $productId <= 0) {
            return [
                'open_orders' => 0,
                'avg_coverage_pct' => 0.0,
                'shortage_qty' => 0.0,
                'usable_supply_qty' => 0.0,
                'open_demand_qty' => 0.0,
            ];
        }

        $row = DB::fetchOne(
            'SELECT
                COUNT(*) AS open_orders,
                COALESCE(ROUND(AVG(COALESCE(coverage_pct, 0)), 2), 0) AS avg_coverage_pct,
                COALESCE(ROUND(SUM(COALESCE(shortage_qty, 0)), 2), 0) AS shortage_qty,
                COALESCE(ROUND(MAX(COALESCE(usable_supply_qty, 0)), 2), 0) AS usable_supply_qty,
                COALESCE(ROUND(MAX(COALESCE(open_demand_qty, 0)), 2), 0) AS open_demand_qty
             FROM daily_orders
             WHERE product_id = ? AND ' . self::openStatusSql('status'),
            array_merge([$productId], self::CLOSED_ORDER_STATUSES)
        ) ?: [];

        return [
            'open_orders' => (int)($row['open_orders'] ?? 0),
            'avg_coverage_pct' => (float)($row['avg_coverage_pct'] ?? 0),
            'shortage_qty' => (float)($row['shortage_qty'] ?? 0),
            'usable_supply_qty' => (float)($row['usable_supply_qty'] ?? 0),
            'open_demand_qty' => (float)($row['open_demand_qty'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    private static function logCoverageAudit(int $productId, array $before, array $after): void
    {
        if ($productId <= 0) {
            return;
        }

        AuditLogService::ensureSchema();

        $auditContext = ['app' => 'platform', 'module' => 'coverage'];
        if (is_callable(self::$auditContextResolver)) {
            try {
                $resolved = call_user_func(self::$auditContextResolver);
                if (is_array($resolved)) {
                    $auditContext['app'] = (string)($resolved['app'] ?? $auditContext['app']);
                    $auditContext['module'] = (string)($resolved['module'] ?? $auditContext['module']);
                }
            } catch (\Throwable $e) {
                // Keep default context if provider hook fails.
            }
        }

        AuditLogService::logEvent(
            'coverage',
            $productId,
            AuditLogService::EVENT_SYSTEM,
            AuditLogService::ACTION_RECALCULATED,
            null,
            [
                'app' => $auditContext['app'],
                'module' => $auditContext['module'],
                'old_state' => 'before',
                'new_state' => 'after',
                'note' => 'Coverage recalculated for product.',
                'diff' => AuditLogService::diffImportantFields($before, $after, ['open_orders', 'avg_coverage_pct', 'shortage_qty', 'usable_supply_qty', 'open_demand_qty']),
                'metadata' => [
                    'product_id' => $productId,
                    'before' => $before,
                    'after' => $after,
                ],
            ]
        );
    }
}
