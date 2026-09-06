<?php
declare(strict_types=1);

namespace App\Core;

class MyWorkEntityQuery
{
    public const ENTITY_DAILY_ORDER = 'daily_order';
    public const ENTITY_PRODUCTION_ENTRY = 'production_entry';
    public const ENTITY_PRODUCTION_PLAN = 'production_plan';
    public const ENTITY_QC_ENTRY = 'qc_entry';
    public const ENTITY_DISPATCH_ENTRY = 'dispatch_entry';

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function fetchDailyOrders(array $options = []): array
    {
        if (!self::tableExists('daily_orders')) {
            return [];
        }

        $limit = (int)($options['limit'] ?? 40);
        $excludeStatuses = $options['exclude_statuses'] ?? ['completed', 'closed', 'cancelled', 'fulfilled'];

        $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
        $params = $excludeStatuses;

        try {
            $rows = DB::fetchAll(
                "SELECT o.id, o.qty, o.status, o.updated_at, o.required_date, o.order_date, o.customer_name, o.product_id,
                        p.parts_name, p.parts_number
                 FROM daily_orders o
                 LEFT JOIN products p ON p.id = o.product_id
                 WHERE LOWER(COALESCE(o.status,'open')) NOT IN ({$placeholders})
                 ORDER BY o.id DESC
                 LIMIT {$limit}",
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::normalizeDailyOrderRows($rows);
    }

    public static function fetchProductionEntries(array $options = []): array
    {
        if (!self::tableExists('production_entries')) {
            return [];
        }

        $limit = (int)($options['limit'] ?? 40);
        $excludeStatuses = $options['exclude_statuses'] ?? ['completed', 'closed', 'cancelled'];

        $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
        $params = $excludeStatuses;

        try {
            $rows = DB::fetchAll(
                "SELECT pe.id, pe.qty_produced, pe.good_qty, pe.rejected_qty, pe.status, pe.production_date, pe.updated_at,
                        pe.machine_id, pe.product_id, pe.shift,
                        p.parts_name, p.parts_number
                 FROM production_entries pe
                 LEFT JOIN products p ON p.id = pe.product_id
                 WHERE LOWER(COALESCE(pe.status,'draft')) NOT IN ({$placeholders})
                 ORDER BY pe.id DESC
                 LIMIT {$limit}",
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::normalizeProductionEntryRows($rows);
    }

    public static function fetchProductionPlans(array $options = []): array
    {
        if (!self::tableExists('production_plans')) {
            return [];
        }

        $limit = (int)($options['limit'] ?? 40);
        $excludeStatuses = $options['exclude_statuses'] ?? ['completed', 'closed', 'cancelled'];

        $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
        $params = $excludeStatuses;

        try {
            $rows = DB::fetchAll(
                "SELECT pp.id, pp.planned_qty, pp.status, pp.plan_date, pp.updated_at, pp.plan_type,
                        pp.machine_id, pp.product_id, pp.sequence_no, pp.runtime,
                        p.parts_name, p.parts_number, m.machine_no, m.machine_name
                 FROM production_plans pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 LEFT JOIN machines m ON m.id = pp.machine_id
                 WHERE LOWER(COALESCE(pp.status,'draft')) NOT IN ({$placeholders})
                   AND LOWER(COALESCE(pp.approval_status,'draft')) NOT IN ('approved')
                 ORDER BY pp.id DESC
                 LIMIT {$limit}",
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::normalizeProductionPlanRows($rows);
    }

    public static function fetchQCEntries(array $options = []): array
    {
        if (!self::tableExists('qc_entries')) {
            return [];
        }

        $limit = (int)($options['limit'] ?? 40);
        $excludeStatuses = $options['exclude_statuses'] ?? ['completed', 'closed', 'cancelled', 'approved'];

        $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
        $params = $excludeStatuses;

        try {
            $rows = DB::fetchAll(
                "SELECT q.id, q.checked_qty, q.pass_qty, q.fail_qty, q.status, q.qc_type, q.updated_at,
                        q.product_id, q.remarks,
                        p.parts_name, p.parts_number
                 FROM qc_entries q
                 LEFT JOIN products p ON p.id = q.product_id
                 WHERE LOWER(COALESCE(q.status,'draft')) NOT IN ({$placeholders})
                   AND LOWER(COALESCE(q.approval_status,'draft')) NOT IN ('approved')
                 ORDER BY q.id DESC
                 LIMIT {$limit}",
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::normalizeQCEntryRows($rows);
    }

    public static function fetchDispatchEntries(array $options = []): array
    {
        if (!self::tableExists('dispatch_entries')) {
            return [];
        }

        $limit = (int)($options['limit'] ?? 40);
        $excludeStatuses = $options['exclude_statuses'] ?? ['dispatched', 'completed', 'cancelled'];

        $placeholders = implode(',', array_fill(0, count($excludeStatuses), '?'));
        $params = $excludeStatuses;

        try {
            $rows = DB::fetchAll(
                "SELECT d.id, d.dispatchable_qty, d.dispatch_status, d.dispatch_date, d.updated_at, d.destination,
                        d.product_id, d.dispatch_type,
                        p.parts_name, p.parts_number
                 FROM dispatch_entries d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE LOWER(COALESCE(d.dispatch_status,'draft')) NOT IN ({$placeholders})
                   AND LOWER(COALESCE(d.approval_status,'draft')) NOT IN ('approved')
                 ORDER BY d.id DESC
                 LIMIT {$limit}",
                $params
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::normalizeDispatchEntryRows($rows);
    }

    private static function normalizeDailyOrderRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'entity_type' => self::ENTITY_DAILY_ORDER,
                'entity_id' => (int)($row['id'] ?? 0),
                'id' => (int)($row['id'] ?? 0),
                'status' => (string)($row['status'] ?? 'Open'),
                'required_date' => (string)($row['required_date'] ?? ''),
                'order_date' => (string)($row['order_date'] ?? ''),
                'customer_name' => (string)($row['customer_name'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'qty' => (float)($row['qty'] ?? 0),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'detail_url' => '/daily-orders/360?id=' . (int)($row['id'] ?? 0),
            ];
        }
        return $items;
    }

    private static function normalizeProductionEntryRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'entity_type' => self::ENTITY_PRODUCTION_ENTRY,
                'entity_id' => (int)($row['id'] ?? 0),
                'id' => (int)($row['id'] ?? 0),
                'status' => (string)($row['status'] ?? 'Draft'),
                'production_date' => (string)($row['production_date'] ?? ''),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'product_id' => (int)($row['product_id'] ?? 0),
                'shift' => (string)($row['shift'] ?? 'Day'),
                'produced_qty' => (float)($row['qty_produced'] ?? 0),
                'good_qty' => (float)($row['good_qty'] ?? 0),
                'rejected_qty' => (float)($row['rejected_qty'] ?? 0),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'detail_url' => '/production-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        return $items;
    }

    private static function normalizeProductionPlanRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'entity_type' => self::ENTITY_PRODUCTION_PLAN,
                'entity_id' => (int)($row['id'] ?? 0),
                'id' => (int)($row['id'] ?? 0),
                'status' => (string)($row['status'] ?? 'Planned'),
                'plan_date' => (string)($row['plan_date'] ?? ''),
                'machine_id' => (int)($row['machine_id'] ?? 0),
                'product_id' => (int)($row['product_id'] ?? 0),
                'planned_qty' => (float)($row['planned_qty'] ?? 0),
                'sequence_no' => (int)($row['sequence_no'] ?? 1),
                'runtime' => $row['runtime'] !== null ? (float)$row['runtime'] : null,
                'plan_type' => (string)($row['plan_type'] ?? 'Manual'),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'detail_url' => '/production-plans/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        return $items;
    }

    private static function normalizeQCEntryRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'entity_type' => self::ENTITY_QC_ENTRY,
                'entity_id' => (int)($row['id'] ?? 0),
                'id' => (int)($row['id'] ?? 0),
                'status' => (string)($row['status'] ?? 'Draft'),
                'qc_type' => (string)($row['qc_type'] ?? 'Final'),
                'product_id' => (int)($row['product_id'] ?? 0),
                'checked_qty' => (float)($row['checked_qty'] ?? 0),
                'pass_qty' => (float)($row['pass_qty'] ?? 0),
                'fail_qty' => (float)($row['fail_qty'] ?? 0),
                'remarks' => (string)($row['remarks'] ?? ''),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'detail_url' => '/qc-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        return $items;
    }

    private static function normalizeDispatchEntryRows(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'entity_type' => self::ENTITY_DISPATCH_ENTRY,
                'entity_id' => (int)($row['id'] ?? 0),
                'id' => (int)($row['id'] ?? 0),
                'status' => (string)($row['dispatch_status'] ?? 'Draft'),
                'dispatch_date' => (string)($row['dispatch_date'] ?? ''),
                'product_id' => (int)($row['product_id'] ?? 0),
                'dispatchable_qty' => (float)($row['dispatchable_qty'] ?? 0),
                'destination' => (string)($row['destination'] ?? ''),
                'dispatch_type' => (string)($row['dispatch_type'] ?? 'Regular'),
                'updated_at' => (string)($row['updated_at'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'detail_url' => '/dispatch-entries/edit?id=' . (int)($row['id'] ?? 0),
            ];
        }
        return $items;
    }

    public static function fetchAllMyWorkEntities(array $options = []): array
    {
        $dailyOrders = self::fetchDailyOrders($options);
        $productionEntries = self::fetchProductionEntries($options);
        $productionPlans = self::fetchProductionPlans($options);
        $qcEntries = self::fetchQCEntries($options);
        $dispatchEntries = self::fetchDispatchEntries($options);

        return array_merge($dailyOrders, $productionEntries, $productionPlans, $qcEntries, $dispatchEntries);
    }
}
