<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;
use Apps\Manufacturing\Services\StageTransitionService;

final class DispatchOpsService
{
    private const VALID_COMPLETION_STATUSES = ['draft', 'ready', 'prepared', 'completed'];
    private const VALID_SOURCE_TYPES = ['in_house', 'third_party'];
    private const VALID_DELIVERY_FLOWS = [
        'company_to_destination',
        'third_party_to_destination',
        'third_party_to_company_to_destination',
    ];
    private const VALID_DISPATCH_MODES = [
        'in_house_dispatch',
        'third_party_dispatch',
        'company_origin_dispatch',
        'third_party_direct_dispatch',
        'third_party_to_company_then_destination',
    ];

    public static function ensureSchema(): void
    {
        DemandEngineService::ensureSchema();

        DB::query(
            "CREATE TABLE IF NOT EXISTS dispatch_preparation_logs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                dispatch_entry_id INT NULL,
                daily_order_id INT NOT NULL,
                product_id INT NOT NULL,
                dispatch_date DATE NOT NULL,
                prepared_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                cases_count INT NOT NULL DEFAULT 0,
                pallets_count INT NOT NULL DEFAULT 0,
                note TEXT NULL,
                prepared_by VARCHAR(190) NULL,
                prepared_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_dispatch_prep_order_date (daily_order_id, dispatch_date),
                KEY idx_dispatch_prep_entry (dispatch_entry_id),
                KEY idx_dispatch_prep_product_date (product_id, dispatch_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS mfg_dispatch_bundle_headers (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bundle_code VARCHAR(80) NULL,
                pallet_code VARCHAR(80) NOT NULL,
                destination VARCHAR(190) NOT NULL,
                eta_load_at DATETIME NULL,
                driver_name VARCHAR(190) NULL,
                truck_no VARCHAR(80) NULL,
                carrier_name VARCHAR(190) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'draft',
                notes TEXT NULL,
                created_by VARCHAR(190) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_dispatch_bundle_dest_eta (destination, eta_load_at),
                KEY idx_dispatch_bundle_pallet (pallet_code),
                KEY idx_dispatch_bundle_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS mfg_dispatch_bundle_lines (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bundle_id BIGINT NOT NULL,
                dispatch_entry_id INT NULL,
                daily_order_id INT NULL,
                product_id INT NOT NULL,
                dispatch_date DATE NOT NULL,
                qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_dispatch_bundle_line_bundle (bundle_id),
                KEY idx_dispatch_bundle_line_product_date (product_id, dispatch_date),
                KEY idx_dispatch_bundle_line_entry (dispatch_entry_id),
                KEY idx_dispatch_bundle_line_order (daily_order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::addColumnIfMissing('mfg_dispatch_bundle_headers', 'carrier_name', "ALTER TABLE mfg_dispatch_bundle_headers ADD COLUMN carrier_name VARCHAR(190) NULL AFTER truck_no");
        self::addColumnIfMissing('mfg_dispatch_bundle_lines', 'dispatch_entry_id', "ALTER TABLE mfg_dispatch_bundle_lines ADD COLUMN dispatch_entry_id INT NULL AFTER bundle_id");
        self::addColumnIfMissing('mfg_dispatch_bundle_lines', 'daily_order_id', "ALTER TABLE mfg_dispatch_bundle_lines ADD COLUMN daily_order_id INT NULL AFTER dispatch_entry_id");
        self::addColumnIfMissing('mfg_dispatch_bundle_lines', 'updated_at', "ALTER TABLE mfg_dispatch_bundle_lines ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    }

    private static function addColumnIfMissing(string $table, string $column, string $alterSql): void
    {
        $db = DB::conn();
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        if ($safeTable === '') {
            throw new \InvalidArgumentException('Invalid table name for schema check.');
        }

        $safeColumn = $db->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'");
        if ($exists) {
            return;
        }

        DB::query($alterSql);
    }

    public static function listRows(string $fromDate = '', string $toDate = '', string $completionStatus = '', int $productId = 0): array
    {
        self::ensureSchema();

        $sql = "SELECT d.*, p.parts_name, p.parts_number,
                    p.supply_mode AS product_supply_mode,
                    p.fulfillment_mode AS product_fulfillment_mode
                FROM dispatch_entries d
                INNER JOIN products p ON p.id=d.product_id
                WHERE 1=1";
        $params = [];

        if ($fromDate !== '') {
            $sql .= ' AND d.dispatch_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND d.dispatch_date <= ?';
            $params[] = $toDate;
        }
        if ($productId > 0) {
            $sql .= ' AND d.product_id = ?';
            $params[] = $productId;
        }
        if (in_array($completionStatus, self::VALID_COMPLETION_STATUSES, true)) {
            $sql .= ' AND d.completion_status = ?';
            $params[] = $completionStatus;
        }

        $sql .= ' ORDER BY d.dispatch_date DESC, d.id DESC LIMIT 600';
        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $row = self::withCompatibilityDefaults($row);
        }
        unset($row);

        return $rows;
    }

    public static function preparationFormData(string $dispatchDate, int $productId = 0, int $dailyOrderId = 0): array
    {
        self::ensureSchema();

        $products = DB::fetchAll('SELECT id, parts_name, parts_number FROM products ORDER BY parts_name ASC LIMIT 1200');
        $orders = self::ordersForPreparation($dispatchDate, $productId);

        if ($dailyOrderId <= 0 && !empty($orders)) {
            $dailyOrderId = (int)($orders[0]['id'] ?? 0);
            if ($productId <= 0) {
                $productId = (int)($orders[0]['product_id'] ?? 0);
            }
        }

        $selectedOrder = null;
        foreach ($orders as $order) {
            if ((int)($order['id'] ?? 0) === $dailyOrderId) {
                $selectedOrder = $order;
                break;
            }
        }

        if ($selectedOrder === null && $dailyOrderId > 0) {
            $selectedOrder = self::singlePreparationOrder($dailyOrderId, $dispatchDate);
            if ($selectedOrder && $productId <= 0) {
                $productId = (int)($selectedOrder['product_id'] ?? 0);
            }
        }

        $existingEntry = null;
        if ($selectedOrder) {
            $existingEntry = self::existingDispatchEntry((int)$selectedOrder['id'], $dispatchDate);
        }

        $latestLog = null;
        if ($selectedOrder) {
            $latestLog = DB::fetchOne(
                'SELECT * FROM dispatch_preparation_logs WHERE daily_order_id=? AND dispatch_date=? ORDER BY prepared_at DESC, id DESC LIMIT 1',
                [(int)$selectedOrder['id'], $dispatchDate]
            );
        }

        $form = [
            'dispatch_entry_id' => (int)($existingEntry['id'] ?? 0),
            'dispatch_date' => $dispatchDate,
            'product_id' => (int)($selectedOrder['product_id'] ?? $productId),
            'daily_order_id' => (int)($selectedOrder['id'] ?? $dailyOrderId),
            'dispatchable_qty' => $existingEntry !== null
                ? round((float)($existingEntry['dispatchable_qty'] ?? 0), 2)
                : round((float)($selectedOrder['outstanding_qty'] ?? 0), 2),
            'cases_count' => (int)($existingEntry['cases_count'] ?? 0),
            'pallets_count' => (int)($existingEntry['pallets_count'] ?? 0),
            'destination' => (string)($existingEntry['destination'] ?? ($selectedOrder['customer_name'] ?? '')),
            'note' => (string)($latestLog['note'] ?? ''),
        ];

        $history = $selectedOrder
            ? DB::fetchAll(
                'SELECT * FROM dispatch_preparation_logs WHERE daily_order_id=? AND dispatch_date=? ORDER BY prepared_at DESC, id DESC LIMIT 50',
                [(int)$selectedOrder['id'], $dispatchDate]
            )
            : [];

        return [
            'products' => $products,
            'orders' => $orders,
            'selected_order' => $selectedOrder,
            'form' => $form,
            'history' => $history,
        ];
    }

    public static function markReady(int $id): void
    {
        self::ensureSchema();
        $row = self::requireRow($id);
        $current = self::withCompatibilityDefaults($row);
        if ((string)$current['completion_status'] === 'completed') {
            throw new \RuntimeException('Dispatch is already completed.');
        }

        self::assertDispatchWorkflowGate(
            (int)($current['product_id'] ?? 0),
            (string)($current['dispatch_date'] ?? ''),
            (float)($current['dispatchable_qty'] ?? 0.0),
            (int)($current['id'] ?? 0)
        );

        DB::query(
            'UPDATE dispatch_entries SET completion_status=?, updated_at=NOW() WHERE id=?',
            ['ready', $id]
        );
    }

    public static function prepare(int $id, array $input): void
    {
        self::ensureSchema();
        $row = self::requireRow($id);
        $current = self::withCompatibilityDefaults($row);
        if ((string)$current['completion_status'] === 'completed') {
            throw new \RuntimeException('Completed dispatch cannot be prepared again.');
        }

        self::assertDispatchWorkflowGate(
            (int)($current['product_id'] ?? 0),
            (string)($current['dispatch_date'] ?? ''),
            (float)($current['dispatchable_qty'] ?? 0.0),
            (int)($current['id'] ?? 0)
        );

        $cases = max(0, (int)($input['cases_count'] ?? 0));
        $pallets = max(0, (int)($input['pallets_count'] ?? 0));
        $dispatchMode = (string)($input['dispatch_mode'] ?? $current['dispatch_mode']);
        $deliveryFlow = (string)($input['delivery_flow'] ?? $current['delivery_flow']);
        $sourceType = (string)($input['source_type'] ?? $current['source_type']);
        $thirdPartyRef = trim((string)($input['third_party_reference'] ?? ''));

        if (!in_array($dispatchMode, self::VALID_DISPATCH_MODES, true)) {
            throw new \RuntimeException('Invalid dispatch mode.');
        }
        if (!in_array($deliveryFlow, self::VALID_DELIVERY_FLOWS, true)) {
            throw new \RuntimeException('Invalid delivery flow.');
        }
        if (!in_array($sourceType, self::VALID_SOURCE_TYPES, true)) {
            throw new \RuntimeException('Invalid source type.');
        }

        DB::query(
            'UPDATE dispatch_entries
             SET cases_count=?, pallets_count=?, dispatch_mode=?, delivery_flow=?, source_type=?, third_party_reference=?, prepared_by=?, prepared_at=NOW(), completion_status=?, updated_at=NOW()
             WHERE id=?',
            [
                $cases,
                $pallets,
                $dispatchMode,
                $deliveryFlow,
                $sourceType,
                $thirdPartyRef !== '' ? $thirdPartyRef : null,
                self::currentUserLabel(),
                'prepared',
                $id,
            ]
        );
    }

    public static function complete(int $id): void
    {
        self::ensureSchema();
        $row = self::requireRow($id);
        $current = self::withCompatibilityDefaults($row);
        if ((string)$current['completion_status'] === 'completed') {
            return;
        }

        self::assertDispatchWorkflowGate(
            (int)($current['product_id'] ?? 0),
            (string)($current['dispatch_date'] ?? ''),
            (float)($current['dispatchable_qty'] ?? 0.0),
            (int)($current['id'] ?? 0)
        );

        DB::query(
            'UPDATE dispatch_entries
             SET completion_status=?, dispatch_completed_by=?, dispatch_completed_at=NOW(), dispatch_status=?, updated_at=NOW()
             WHERE id=?',
            ['completed', self::currentUserLabel(), 'Dispatched', $id]
        );
    }

    public static function savePreparation(array $input): array
    {
        self::ensureSchema();

        $dispatchDate = trim((string)($input['dispatch_date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dispatchDate)) {
            throw new \RuntimeException('Invalid dispatch date.');
        }

        $dispatchEntryId = (int)($input['dispatch_entry_id'] ?? 0);
        $dailyOrderId = (int)($input['daily_order_id'] ?? 0);
        $productId = (int)($input['product_id'] ?? 0);
        $preparedQty = round(max(0.0, (float)($input['dispatchable_qty'] ?? 0)), 2);
        $casesCount = max(0, (int)($input['cases_count'] ?? 0));
        $palletsCount = max(0, (int)($input['pallets_count'] ?? 0));
        $destination = trim((string)($input['destination'] ?? ''));
        $note = trim((string)($input['note'] ?? ''));

        if ($dailyOrderId <= 0 || $productId <= 0) {
            throw new \RuntimeException('Select a valid order/part to prepare.');
        }
        if ($preparedQty <= 0.0) {
            throw new \RuntimeException('Prepared quantity must be greater than zero.');
        }

        $order = self::singlePreparationOrder($dailyOrderId, $dispatchDate);
        if (!$order) {
            throw new \RuntimeException('Matching order not found for the selected date.');
        }

        if ((int)($order['product_id'] ?? 0) !== $productId) {
            throw new \RuntimeException('Selected order does not match the selected part.');
        }

        $allowedQty = round((float)($order['outstanding_qty'] ?? 0), 2);
        $existing = null;
        if ($dispatchEntryId > 0) {
            $existing = self::requireRow($dispatchEntryId);
        } else {
            $existing = self::existingDispatchEntry($dailyOrderId, $dispatchDate);
            $dispatchEntryId = (int)($existing['id'] ?? 0);
        }

        if ($existing !== null) {
            $allowedQty = round($allowedQty + (float)($existing['dispatchable_qty'] ?? 0), 2);
        }

        if ($preparedQty - $allowedQty > 0.0001) {
            throw new \RuntimeException('Prepared quantity exceeds the remaining order quantity for that day.');
        }

        self::assertDispatchWorkflowGate($productId, $dispatchDate, $preparedQty, $dispatchEntryId);

        $dispatchType = 'Order Preparation';
        $sourceType = 'in_house';
        $deliveryFlow = 'company_to_destination';
        $dispatchMode = 'company_origin_dispatch';
        $remarks = $note;
        $preparedBy = self::currentUserLabel();

        if ($existing !== null) {
            $current = self::withCompatibilityDefaults($existing);
            $sourceType = (string)($current['source_type'] ?? $sourceType);
            $deliveryFlow = (string)($current['delivery_flow'] ?? $deliveryFlow);
            $dispatchMode = (string)($current['dispatch_mode'] ?? $dispatchMode);
            $dispatchType = (string)($current['dispatch_type'] ?? $dispatchType);
            DB::query(
                'UPDATE dispatch_entries
                 SET dispatch_date=?, daily_order_id=?, product_id=?, dispatchable_qty=?, destination=?, remarks=?, cases_count=?, pallets_count=?, prepared_by=?, prepared_at=NOW(), completion_status=?, dispatch_status=?, dispatch_type=?, source_type=?, delivery_flow=?, dispatch_mode=?, updated_at=NOW()
                 WHERE id=?',
                [
                    $dispatchDate,
                    $dailyOrderId,
                    $productId,
                    $preparedQty,
                    $destination !== '' ? $destination : (string)($order['customer_name'] ?? ''),
                    $remarks !== '' ? $remarks : null,
                    $casesCount,
                    $palletsCount,
                    $preparedBy,
                    'prepared',
                    'Ready',
                    $dispatchType !== '' ? $dispatchType : 'Order Preparation',
                    $sourceType,
                    $deliveryFlow,
                    $dispatchMode,
                    $dispatchEntryId,
                ]
            );
            $action = 'updated';
        } else {
            DB::query(
                'INSERT INTO dispatch_entries (dispatch_date, daily_order_id, production_plan_id, production_entry_id, qc_entry_id, product_id, dispatchable_qty, destination, dispatch_type, dispatch_status, remarks, cases_count, pallets_count, source_type, delivery_flow, dispatch_mode, prepared_by, prepared_at, completion_status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)',
                [
                    $dispatchDate,
                    $dailyOrderId,
                    null,
                    null,
                    null,
                    $productId,
                    $preparedQty,
                    $destination !== '' ? $destination : (string)($order['customer_name'] ?? ''),
                    $dispatchType,
                    'Ready',
                    $remarks !== '' ? $remarks : null,
                    $casesCount,
                    $palletsCount,
                    $sourceType,
                    $deliveryFlow,
                    $dispatchMode,
                    $preparedBy,
                    'prepared',
                ]
            );
            $dispatchEntryId = (int)DB::conn()->insert_id;
            $action = 'created';
        }

        DB::query(
            'INSERT INTO dispatch_preparation_logs (dispatch_entry_id, daily_order_id, product_id, dispatch_date, prepared_qty, cases_count, pallets_count, note, prepared_by, prepared_at)
             VALUES (?,?,?,?,?,?,?,?,?,NOW())',
            [
                $dispatchEntryId > 0 ? $dispatchEntryId : null,
                $dailyOrderId,
                $productId,
                $dispatchDate,
                $preparedQty,
                $casesCount,
                $palletsCount,
                $note !== '' ? $note : null,
                $preparedBy,
            ]
        );

        return [
            'dispatch_entry_id' => $dispatchEntryId,
            'action' => $action,
        ];
    }

    public static function dispatchWorkflowHints(int $productId, string $dispatchDate, int $dispatchEntryId = 0): array
    {
        $snapshot = self::dispatchSnapshot($productId, $dispatchDate);
        if ($snapshot === null) {
            return [
                'allowed' => false,
                'message' => 'No workflow snapshot available for this product/date.',
                'releasable_qty' => 0.0,
                'dispatch_status' => 'pending',
                'upstream_stage' => null,
            ];
        }

        $dispatch = (array)($snapshot['stages']['dispatch'] ?? []);
        $dispatchStatus = (string)($dispatch['status'] ?? 'pending');
        $upstreamStage = self::dispatchUpstreamStage((array)($snapshot['stage_path'] ?? []));
        $upstreamStatus = $upstreamStage !== null
            ? (string)($snapshot['stages'][$upstreamStage]['status'] ?? 'pending')
            : 'pending';
        $baseReleasable = self::dispatchBaseReleasableQty($snapshot, $upstreamStage);
        $existingAllocated = self::existingAllocatedQty($productId, $dispatchDate, $dispatchEntryId);
        $available = round(max(0.0, $baseReleasable - $existingAllocated), 2);

        $blockedReason = trim((string)($dispatch['blocked_reason'] ?? ''));
        if ($blockedReason === '') {
            $blockedReason = trim((string)implode('; ', (array)($snapshot['block_reasons'] ?? [])));
        }

        $allowedStatuses = ['eligible', 'released', 'in_progress', 'complete'];
        if (!in_array($dispatchStatus, $allowedStatuses, true)) {
            $msg = $blockedReason !== ''
                ? $blockedReason
                : 'Dispatch not allowed while dispatch stage is ' . $dispatchStatus . '.';

            return [
                'allowed' => false,
                'message' => $msg,
                'releasable_qty' => $available,
                'dispatch_status' => $dispatchStatus,
                'upstream_stage' => $upstreamStage,
                'upstream_status' => $upstreamStatus,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'releasable_qty' => $available,
            'dispatch_status' => $dispatchStatus,
            'upstream_stage' => $upstreamStage,
            'upstream_status' => $upstreamStatus,
        ];
    }

    public static function withCompatibilityDefaults(array $row): array
    {
        $mapped = $row;
        $legacyDispatchType = strtolower(trim((string)($row['dispatch_type'] ?? '')));

        $mapped['source_type'] = (string)($row['source_type'] ?? '');
        if ($mapped['source_type'] === '') {
            $mapped['source_type'] = str_contains($legacyDispatchType, 'third')
                ? 'third_party'
                : (string)($row['product_supply_mode'] ?? 'in_house');
        }

        $mapped['delivery_flow'] = (string)($row['delivery_flow'] ?? '');
        if ($mapped['delivery_flow'] === '') {
            $mapped['delivery_flow'] = (string)($row['product_fulfillment_mode'] ?? 'company_to_destination');
        }

        $mapped['dispatch_mode'] = (string)($row['dispatch_mode'] ?? '');
        if ($mapped['dispatch_mode'] === '') {
            $mapped['dispatch_mode'] = self::inferDispatchMode($mapped['source_type'], $mapped['delivery_flow']);
        }

        $mapped['completion_status'] = (string)($row['completion_status'] ?? '');
        if ($mapped['completion_status'] === '') {
            $dispatchStatus = strtolower(trim((string)($row['dispatch_status'] ?? '')));
            $mapped['completion_status'] = $dispatchStatus === 'dispatched' ? 'completed' : 'draft';
        }

        $mapped['cases_count'] = (int)($row['cases_count'] ?? 0);
        $mapped['pallets_count'] = (int)($row['pallets_count'] ?? 0);

        return $mapped;
    }

    private static function requireRow(int $id): array
    {
        if ($id <= 0) {
            throw new \RuntimeException('Invalid dispatch record id.');
        }
        $row = DB::fetchOne('SELECT * FROM dispatch_entries WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            throw new \RuntimeException('Dispatch entry not found.');
        }
        return $row;
    }

    private static function ordersForPreparation(string $dispatchDate, int $productId = 0): array
    {
        $sql = "SELECT d.id,
                       d.order_date,
                       d.required_date,
                       d.customer_name,
                       d.product_id,
                       d.qty,
                       d.dispatch_deadline,
                       p.parts_name,
                       p.parts_number,
                       ROUND(GREATEST(COALESCE(d.qty,0) - COALESCE(x.prepared_qty,0), 0), 2) AS outstanding_qty
                FROM daily_orders d
                INNER JOIN products p ON p.id = d.product_id
                LEFT JOIN (
                    SELECT daily_order_id,
                           SUM(CASE WHEN LOWER(COALESCE(dispatch_status,'')) NOT IN ('cancelled','canceled') THEN dispatchable_qty ELSE 0 END) AS prepared_qty
                    FROM dispatch_entries
                    GROUP BY daily_order_id
                ) x ON x.daily_order_id = d.id
                WHERE LOWER(COALESCE(d.status, 'open')) NOT IN ('closed','completed','cancelled','canceled')
                  AND COALESCE(d.required_date, DATE(d.dispatch_deadline), d.order_date) = ?";
        $params = [$dispatchDate];

        if ($productId > 0) {
            $sql .= ' AND d.product_id = ?';
            $params[] = $productId;
        }

        $sql .= ' ORDER BY p.parts_name ASC, d.id DESC LIMIT 300';
        $rows = DB::fetchAll($sql, $params);

        return array_values(array_filter($rows, static fn(array $row): bool => (float)($row['outstanding_qty'] ?? 0) > 0));
    }

    private static function singlePreparationOrder(int $dailyOrderId, string $dispatchDate): ?array
    {
        $rows = DB::fetchAll(
            "SELECT d.id,
                    d.order_date,
                    d.required_date,
                    d.customer_name,
                    d.product_id,
                    d.qty,
                    d.dispatch_deadline,
                    p.parts_name,
                    p.parts_number,
                    ROUND(GREATEST(COALESCE(d.qty,0) - COALESCE(x.prepared_qty,0), 0), 2) AS outstanding_qty
             FROM daily_orders d
             INNER JOIN products p ON p.id = d.product_id
             LEFT JOIN (
                 SELECT daily_order_id,
                        SUM(CASE WHEN LOWER(COALESCE(dispatch_status,'')) NOT IN ('cancelled','canceled') THEN dispatchable_qty ELSE 0 END) AS prepared_qty
                 FROM dispatch_entries
                 GROUP BY daily_order_id
             ) x ON x.daily_order_id = d.id
             WHERE d.id = ?
               AND COALESCE(d.required_date, DATE(d.dispatch_deadline), d.order_date) = ?
             LIMIT 1",
            [$dailyOrderId, $dispatchDate]
        );

        return $rows[0] ?? null;
    }

    private static function existingDispatchEntry(int $dailyOrderId, string $dispatchDate): ?array
    {
        return DB::fetchOne(
            'SELECT * FROM dispatch_entries WHERE daily_order_id=? AND dispatch_date=? ORDER BY id DESC LIMIT 1',
            [$dailyOrderId, $dispatchDate]
        );
    }

    private static function inferDispatchMode(string $sourceType, string $deliveryFlow): string
    {
        if ($sourceType === 'third_party' && $deliveryFlow === 'third_party_to_destination') {
            return 'third_party_direct_dispatch';
        }
        if ($sourceType === 'third_party' && $deliveryFlow === 'third_party_to_company_to_destination') {
            return 'third_party_to_company_then_destination';
        }
        if ($sourceType === 'third_party') {
            return 'third_party_dispatch';
        }
        return 'company_origin_dispatch';
    }

    private static function assertDispatchWorkflowGate(int $productId, string $dispatchDate, float $requestedQty, int $dispatchEntryId = 0): void
    {
        $hints = self::dispatchWorkflowHints($productId, $dispatchDate, $dispatchEntryId);
        if (!(bool)($hints['allowed'] ?? false)) {
            throw new \RuntimeException((string)($hints['message'] ?? 'Dispatch stage is not releasable.'));
        }

        $available = (float)($hints['releasable_qty'] ?? 0.0);
        if ($requestedQty - $available > 0.0001) {
            throw new \RuntimeException('Requested qty exceeds dispatch releasable qty (' . $available . ').');
        }
    }

    private static function dispatchSnapshot(int $productId, string $dispatchDate): ?array
    {
        if ($productId <= 0 || $dispatchDate === '') {
            return null;
        }

        $byDate = StageTransitionService::computeForDate($dispatchDate);
        $snapshot = $byDate[$productId] ?? null;
        return is_array($snapshot) ? $snapshot : null;
    }

    private static function dispatchUpstreamStage(array $stagePath): ?string
    {
        $idx = array_search('dispatch', $stagePath, true);
        if (!is_int($idx) || $idx <= 0) {
            return null;
        }
        return (string)$stagePath[$idx - 1];
    }

    private static function dispatchBaseReleasableQty(array $snapshot, ?string $upstreamStage): float
    {
        $demand = 0.0;
        $dispatch = (array)($snapshot['stages']['dispatch'] ?? []);
        $demand = max($demand, (float)($dispatch['demand'] ?? 0.0));

        $upstream = $upstreamStage !== null ? (array)($snapshot['stages'][$upstreamStage] ?? []) : [];
        $base = $demand;
        if ($upstreamStage === 'production') {
            $base = (float)($upstream['qty'] ?? 0.0);
        } elseif ($upstreamStage === 'qc') {
            $base = (float)($upstream['pass_qty'] ?? 0.0);
        } elseif ($upstreamStage === 'assembly') {
            $base = (float)($upstream['assembled_qty'] ?? 0.0);
        }
        if ($demand > 0.0) {
            $base = min($base, $demand);
        }

        $explicitRelease = (float)($dispatch['released_qty'] ?? 0.0);
        if ($explicitRelease > 0.0) {
            $base = min($base, $explicitRelease);
        }

        return round(max(0.0, $base), 2);
    }

    private static function existingAllocatedQty(int $productId, string $dispatchDate, int $excludeDispatchEntryId = 0): float
    {
        $sql = "SELECT ROUND(COALESCE(SUM(dispatchable_qty),0),2) AS qty
                FROM dispatch_entries
                WHERE product_id=?
                  AND dispatch_date=?
                  AND LOWER(COALESCE(dispatch_status,'')) NOT IN ('cancelled','canceled')";
        $params = [$productId, $dispatchDate];
        if ($excludeDispatchEntryId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeDispatchEntryId;
        }

        $row = DB::fetchOne($sql, $params);
        return round((float)($row['qty'] ?? 0.0), 2);
    }

    private static function currentUserLabel(): string
    {
        $u = Auth::user();
        $email = trim((string)($u['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }
        return 'system';
    }
}
