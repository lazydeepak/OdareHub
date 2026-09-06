<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

use App\Core\AuditLogService;
use App\Core\DB;

final class ProcurementOverviewService
{
    public static function ensureSchema(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS procurement_suppliers (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            supplier_code VARCHAR(40) NULL,
            supplier_name VARCHAR(190) NOT NULL,
            contact_name VARCHAR(190) NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(80) NULL,
            supplier_status VARCHAR(30) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_proc_supplier_status (supplier_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS procurement_requests (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            request_ref VARCHAR(60) NOT NULL,
            product_id INT NULL,
            requested_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            needed_date DATE NULL,
            source_app VARCHAR(80) NULL,
            source_ref_type VARCHAR(80) NULL,
            source_ref_id VARCHAR(80) NULL,
            request_status VARCHAR(30) NOT NULL DEFAULT 'draft',
            note TEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_proc_request_ref (request_ref),
            KEY idx_proc_request_status (request_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS procurement_purchase_orders (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            po_ref VARCHAR(60) NOT NULL,
            supplier_id BIGINT NULL,
            request_id BIGINT NULL,
            po_status VARCHAR(30) NOT NULL DEFAULT 'draft',
            order_date DATE NULL,
            expected_date DATE NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_proc_po_ref (po_ref),
            KEY idx_proc_po_status (po_status),
            KEY idx_proc_po_supplier (supplier_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS procurement_purchase_order_lines (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            po_id BIGINT NOT NULL,
            product_id INT NULL,
            line_description VARCHAR(255) NULL,
            ordered_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            unit_price DECIMAL(14,2) NOT NULL DEFAULT 0,
            line_status VARCHAR(30) NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_proc_po_line_po (po_id),
            KEY idx_proc_po_line_status (line_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        DB::query("CREATE TABLE IF NOT EXISTS procurement_receipts (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            receipt_ref VARCHAR(60) NOT NULL,
            po_id BIGINT NULL,
            po_line_id BIGINT NULL,
            received_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            receipt_date DATE NULL,
            receipt_status VARCHAR(30) NOT NULL DEFAULT 'received',
            note TEXT NULL,
            created_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_proc_receipt_ref (receipt_ref),
            KEY idx_proc_receipt_status (receipt_status),
            KEY idx_proc_receipt_po (po_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * @return array<string,int>
     */
    public static function summary(): array
    {
        return [
            'suppliers' => self::safeCount('procurement_suppliers'),
            'requests' => self::safeCount('procurement_requests'),
            'orders' => self::safeCount('procurement_purchase_orders'),
            'receipts' => self::safeCount('procurement_receipts'),
            'pending_requests' => self::safeCountWhere('procurement_requests', "LOWER(COALESCE(request_status,'draft')) IN ('draft','approved')"),
            'open_orders' => self::safeCountWhere('procurement_purchase_orders', "LOWER(COALESCE(po_status,'draft')) IN ('draft','approved','issued','partial')"),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function latestRequests(int $limit = 20): array
    {
        if (!self::tableExists('procurement_requests')) {
            return [];
        }

        $rows = DB::fetchAll(
            'SELECT id, request_ref, product_id, requested_qty, needed_date, source_app, source_ref_type, source_ref_id, request_status, created_at
             FROM procurement_requests
                         ORDER BY id DESC
             LIMIT ' . max(1, (int)$limit)
        );

        return self::attachTransitions($rows, 'procurement_request');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function latestOrders(int $limit = 20): array
    {
        if (!self::tableExists('procurement_purchase_orders')) {
            return [];
        }

        $rows = DB::fetchAll(
            'SELECT po.id,
                    po.po_ref,
                    po.supplier_id,
                    po.request_id,
                    po.po_status,
                    po.order_date,
                    po.expected_date,
                    po.created_at,
                    COALESCE(s.supplier_name, \'\') AS supplier_name,
                    COALESCE(r.request_ref, \'\') AS request_ref
             FROM procurement_purchase_orders po
             LEFT JOIN procurement_suppliers s ON s.id = po.supplier_id
             LEFT JOIN procurement_requests r ON r.id = po.request_id
                         ORDER BY po.id DESC
             LIMIT ' . max(1, (int)$limit)
        );

        return self::attachTransitions($rows, 'procurement_order');
    }

    /**
     * @return array{po_id:int,already_exists:bool}
     */
    public static function createOrderFromApprovedRequest(int $requestId, ?int $supplierId, string $actor): array
    {
        $requestId = (int)$requestId;
        if ($requestId <= 0) {
            throw new \InvalidArgumentException('Invalid request id.');
        }

        $supplierId = $supplierId !== null && $supplierId > 0 ? (int)$supplierId : null;
        if ($supplierId !== null) {
            $supplier = DB::fetchOne(
                'SELECT id, supplier_name, supplier_status
                 FROM procurement_suppliers
                 WHERE id=?
                 LIMIT 1',
                [$supplierId]
            );
            if (!is_array($supplier)) {
                throw new \RuntimeException('Selected supplier was not found. Pick a valid supplier or use Auto supplier.');
            }

            $supplierStatus = strtolower(trim((string)($supplier['supplier_status'] ?? 'active')));
            if (in_array($supplierStatus, ['inactive', 'disabled', 'blocked'], true)) {
                throw new \RuntimeException('Selected supplier is inactive. Choose an active supplier.');
            }
        }

        $existingPo = DB::fetchOne(
            'SELECT id FROM procurement_purchase_orders WHERE request_id=? LIMIT 1',
            [$requestId]
        );
        if (is_array($existingPo) && (int)($existingPo['id'] ?? 0) > 0) {
            return ['po_id' => (int)$existingPo['id'], 'already_exists' => true];
        }

        $request = DB::fetchOne(
            'SELECT id, request_ref, product_id, requested_qty, needed_date, request_status
             FROM procurement_requests
             WHERE id=?
             LIMIT 1',
            [$requestId]
        );
        if (!is_array($request)) {
            throw new \RuntimeException('Request not found.');
        }

        $requestRef = trim((string)($request['request_ref'] ?? ''));
        if ($requestRef === '') {
            $requestRef = '#' . (string)$requestId;
        }

        $status = strtolower(trim((string)($request['request_status'] ?? 'draft')));
        if ($status !== 'approved') {
            throw new \RuntimeException('Request ' . $requestRef . ' is currently "' . $status . '". Only approved requests can be converted to PO.');
        }

        $poRef = self::nextRef('PO');
        $requestedQty = round(max(0.0, (float)($request['requested_qty'] ?? 0.0)), 2);
        if ($requestedQty <= 0.0) {
            throw new \RuntimeException('Request ' . $requestRef . ' has zero quantity. Update quantity before creating a PO.');
        }

        DB::query(
            'INSERT INTO procurement_purchase_orders (po_ref, supplier_id, request_id, po_status, order_date, expected_date, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $poRef,
                $supplierId,
                $requestId,
                'draft',
                date('Y-m-d'),
                self::nullIfBlank((string)($request['needed_date'] ?? '')),
                self::nullIfBlank($actor),
            ]
        );

        $poId = (int)(DB::conn()->insert_id ?: 0);
        if ($poId <= 0) {
            throw new \RuntimeException('Failed to create PO from request.');
        }

        DB::query(
            'INSERT INTO procurement_purchase_order_lines (po_id, product_id, line_description, ordered_qty, unit_price, line_status)
             VALUES (?,?,?,?,?,?)',
            [
                $poId,
                self::toPositiveInt($request['product_id'] ?? null),
                self::nullIfBlank('Auto from request ' . (string)($request['request_ref'] ?? ('#' . $requestId))),
                $requestedQty,
                0,
                'open',
            ]
        );

        self::setRequestStatus($requestId, 'closed', $actor, 'Auto closed after PO creation from approved request.');

        self::recordAudit(
            'procurement_order',
            $poId,
            AuditLogService::EVENT_WORKFLOW,
            AuditLogService::ACTION_CREATED,
            $actor,
            [
                'app' => 'procurement',
                'module' => 'orders',
                'old_state' => 'new',
                'new_state' => 'draft',
                'note' => 'PO created from approved request.',
                'metadata' => [
                    'request_id' => $requestId,
                    'supplier_id' => $supplierId,
                ],
            ]
        );

        return ['po_id' => $poId, 'already_exists' => false];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function latestReceipts(int $limit = 20): array
    {
        if (!self::tableExists('procurement_receipts')) {
            return [];
        }

        $rows = DB::fetchAll(
            'SELECT rc.id,
                    rc.receipt_ref,
                    rc.po_id,
                    rc.po_line_id,
                    rc.received_qty,
                    rc.receipt_date,
                    rc.receipt_status,
                    rc.created_at,
                    COALESCE(po.po_ref, \'\') AS po_ref,
                    COALESCE(s.supplier_name, \'\') AS supplier_name
             FROM procurement_receipts rc
             LEFT JOIN procurement_purchase_orders po ON po.id = rc.po_id
             LEFT JOIN procurement_suppliers s ON s.id = po.supplier_id
               ORDER BY rc.id DESC
             LIMIT ' . max(1, (int)$limit)
        );

        return self::attachTransitions($rows, 'procurement_receipt');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recentTransitions(int $limit = 40): array
    {
        AuditLogService::ensureSchema();

        $rows = DB::fetchAll(
            'SELECT id, entity_type, entity_id, action_name, old_state, new_state, note_text, actor_email, actor_display_name, created_at
             FROM audit_activity_log
             WHERE LOWER(COALESCE(app_key,\'\')) = ?
             ORDER BY id DESC
             LIMIT ' . max(1, min(200, (int)$limit)),
            ['procurement']
        );

        foreach ($rows as &$row) {
            $actor = trim((string)($row['actor_display_name'] ?? ''));
            if ($actor === '') {
                $actor = trim((string)($row['actor_email'] ?? ''));
            }
            $row['actor_label'] = $actor !== '' ? $actor : 'System';
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function latestManufacturingDemands(int $limit = 20): array
    {
        if (!self::tableExists('mfg_procurement_demands')) {
            return [];
        }

        return DB::fetchAll(
            "SELECT d.id,
                    d.product_id,
                    d.required_qty,
                    d.required_date,
                    d.status,
                    d.route_family,
                    d.execution_path_label,
                    r.id AS linked_request_id,
                    r.request_ref AS linked_request_ref
             FROM mfg_procurement_demands d
             LEFT JOIN procurement_requests r
               ON r.source_app = 'manufacturing'
              AND r.source_ref_type = 'mfg_procurement_demands'
              AND r.source_ref_id = CAST(d.id AS CHAR)
             ORDER BY d.id DESC
             LIMIT " . max(1, (int)$limit)
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function supplierOptions(int $limit = 120): array
    {
        if (!self::tableExists('procurement_suppliers')) {
            return [];
        }

        return DB::fetchAll(
            'SELECT id, supplier_name
             FROM procurement_suppliers
             ORDER BY supplier_name ASC
             LIMIT ' . max(1, (int)$limit)
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function createSupplier(array $input, string $actor): void
    {
        $name = trim((string)($input['supplier_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Supplier name is required.');
        }

        DB::query(
            'INSERT INTO procurement_suppliers (supplier_code, supplier_name, contact_name, email, phone, supplier_status)
             VALUES (?,?,?,?,?,?)',
            [
                self::nullIfBlank((string)($input['supplier_code'] ?? '')),
                $name,
                self::nullIfBlank((string)($input['contact_name'] ?? '')),
                self::nullIfBlank((string)($input['email'] ?? '')),
                self::nullIfBlank((string)($input['phone'] ?? '')),
                self::nullIfBlank((string)($input['supplier_status'] ?? 'active')) ?? 'active',
            ]
        );

        $supplierId = (int)(DB::conn()->insert_id ?: 0);
        if ($supplierId > 0) {
            self::recordAudit(
                'procurement_supplier',
                $supplierId,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_CREATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'suppliers',
                    'new_state' => (string)($input['supplier_status'] ?? 'active'),
                    'note' => 'Supplier created.',
                    'metadata' => [
                        'supplier_name' => $name,
                    ],
                ]
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function createRequest(array $input, string $actor): void
    {
        $qty = round(max(0.0, (float)($input['requested_qty'] ?? 0)), 2);
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Requested quantity must be greater than zero.');
        }

        $requestRef = trim((string)($input['request_ref'] ?? ''));
        if ($requestRef === '') {
            $requestRef = self::nextRef('PR');
        }

        DB::query(
            'INSERT INTO procurement_requests (request_ref, product_id, requested_qty, needed_date, source_app, source_ref_type, source_ref_id, request_status, note, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                $requestRef,
                self::toPositiveInt($input['product_id'] ?? null),
                $qty,
                self::nullIfBlank((string)($input['needed_date'] ?? '')),
                self::nullIfBlank((string)($input['source_app'] ?? '')),
                self::nullIfBlank((string)($input['source_ref_type'] ?? '')),
                self::nullIfBlank((string)($input['source_ref_id'] ?? '')),
                self::nullIfBlank((string)($input['request_status'] ?? 'draft')) ?? 'draft',
                self::nullIfBlank((string)($input['note'] ?? '')),
                self::nullIfBlank($actor),
            ]
        );

        $requestId = (int)(DB::conn()->insert_id ?: 0);
        if ($requestId > 0) {
            self::recordAudit(
                'procurement_request',
                $requestId,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_CREATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'requests',
                    'new_state' => (string)($input['request_status'] ?? 'draft'),
                    'note' => 'Procurement request created.',
                    'metadata' => [
                        'request_ref' => $requestRef,
                        'source_app' => (string)($input['source_app'] ?? ''),
                    ],
                ]
            );
        }
    }

    /**
     * @return array{request_id:int,already_exists:bool}
     */
    public static function intakeFromManufacturingDemand(int $demandId, string $actor): array
    {
        if ($demandId <= 0) {
            throw new \InvalidArgumentException('Invalid manufacturing demand id.');
        }
        if (!self::tableExists('mfg_procurement_demands')) {
            throw new \RuntimeException('Manufacturing procurement demand table is not available.');
        }

        $existing = DB::fetchOne(
            "SELECT id
             FROM procurement_requests
             WHERE source_app='manufacturing'
               AND source_ref_type='mfg_procurement_demands'
               AND source_ref_id=?
             LIMIT 1",
            [(string)$demandId]
        );
        if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
            return ['request_id' => (int)$existing['id'], 'already_exists' => true];
        }

        $row = DB::fetchOne(
            'SELECT id, product_id, required_qty, required_date, route_family, execution_path_label, status
             FROM mfg_procurement_demands
             WHERE id=?
             LIMIT 1',
            [$demandId]
        );
        if (!is_array($row)) {
            throw new \RuntimeException('Manufacturing demand not found.');
        }

        $status = strtolower(trim((string)($row['status'] ?? 'draft')));
        if (in_array($status, ['cancelled'], true)) {
            throw new \RuntimeException('Manufacturing demand is cancelled and cannot be imported.');
        }

        DB::query(
            'INSERT INTO procurement_requests (request_ref, product_id, requested_qty, needed_date, source_app, source_ref_type, source_ref_id, request_status, note, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?)',
            [
                self::nextRef('PR'),
                self::toPositiveInt($row['product_id'] ?? null),
                round(max(0.0, (float)($row['required_qty'] ?? 0.0)), 2),
                self::nullIfBlank((string)($row['required_date'] ?? '')),
                'manufacturing',
                'mfg_procurement_demands',
                (string)$demandId,
                'draft',
                self::nullIfBlank('Imported from manufacturing demand #' . $demandId . ' [' . (string)($row['route_family'] ?? '') . '] ' . (string)($row['execution_path_label'] ?? '')),
                self::nullIfBlank($actor),
            ]
        );

        $requestId = (int)(DB::conn()->insert_id ?: 0);
        if ($requestId > 0) {
            self::recordAudit(
                'procurement_request',
                $requestId,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_CREATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'requests',
                    'new_state' => 'draft',
                    'note' => 'Request imported from manufacturing demand.',
                    'metadata' => [
                        'source_app' => 'manufacturing',
                        'source_ref_type' => 'mfg_procurement_demands',
                        'source_ref_id' => (string)$demandId,
                    ],
                ]
            );
        }

        return ['request_id' => $requestId, 'already_exists' => false];
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function createOrder(array $input, string $actor): void
    {
        $poRef = trim((string)($input['po_ref'] ?? ''));
        if ($poRef === '') {
            $poRef = self::nextRef('PO');
        }

        DB::query(
            'INSERT INTO procurement_purchase_orders (po_ref, supplier_id, request_id, po_status, order_date, expected_date, created_by)
             VALUES (?,?,?,?,?,?,?)',
            [
                $poRef,
                self::toPositiveInt($input['supplier_id'] ?? null),
                self::toPositiveInt($input['request_id'] ?? null),
                self::nullIfBlank((string)($input['po_status'] ?? 'draft')) ?? 'draft',
                self::nullIfBlank((string)($input['order_date'] ?? '')),
                self::nullIfBlank((string)($input['expected_date'] ?? '')),
                self::nullIfBlank($actor),
            ]
        );

        $poId = (int)(DB::conn()->insert_id ?: 0);
        $requestId = self::toPositiveInt($input['request_id'] ?? null);
        $orderedQty = round(max(0.0, (float)($input['ordered_qty'] ?? 0)), 2);
        if ($poId > 0 && $orderedQty > 0) {
            DB::query(
                'INSERT INTO procurement_purchase_order_lines (po_id, product_id, line_description, ordered_qty, unit_price, line_status)
                 VALUES (?,?,?,?,?,?)',
                [
                    $poId,
                    self::toPositiveInt($input['line_product_id'] ?? ($input['product_id'] ?? null)),
                    self::nullIfBlank((string)($input['line_description'] ?? '')),
                    $orderedQty,
                    round(max(0.0, (float)($input['unit_price'] ?? 0)), 2),
                    self::nullIfBlank((string)($input['line_status'] ?? 'open')) ?? 'open',
                ]
            );
        }

        if ($poId > 0) {
            if ($requestId !== null) {
                self::setRequestStatus($requestId, 'closed', $actor, 'Auto closed after PO creation.');
            }

            self::recordAudit(
                'procurement_order',
                $poId,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_CREATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'orders',
                    'new_state' => (string)($input['po_status'] ?? 'draft'),
                    'note' => 'Purchase order created.',
                    'metadata' => [
                        'request_id' => $requestId,
                        'supplier_id' => self::toPositiveInt($input['supplier_id'] ?? null),
                    ],
                ]
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function createReceipt(array $input, string $actor): void
    {
        $qty = round(max(0.0, (float)($input['received_qty'] ?? 0)), 2);
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Received quantity must be greater than zero.');
        }

        $receiptRef = trim((string)($input['receipt_ref'] ?? ''));
        if ($receiptRef === '') {
            $receiptRef = self::nextRef('RCV');
        }

        DB::query(
            'INSERT INTO procurement_receipts (receipt_ref, po_id, po_line_id, received_qty, receipt_date, receipt_status, note, created_by)
             VALUES (?,?,?,?,?,?,?,?)',
            [
                $receiptRef,
                self::toPositiveInt($input['po_id'] ?? null),
                self::toPositiveInt($input['po_line_id'] ?? null),
                $qty,
                self::nullIfBlank((string)($input['receipt_date'] ?? '')),
                self::nullIfBlank((string)($input['receipt_status'] ?? 'received')) ?? 'received',
                self::nullIfBlank((string)($input['note'] ?? '')),
                self::nullIfBlank($actor),
            ]
        );

        $receiptId = (int)(DB::conn()->insert_id ?: 0);
        if ($receiptId > 0) {
            $status = strtolower(trim((string)($input['receipt_status'] ?? 'received')));
            self::recordAudit(
                'procurement_receipt',
                $receiptId,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_CREATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'receipts',
                    'new_state' => $status,
                    'note' => 'Receipt created.',
                    'metadata' => [
                        'po_id' => self::toPositiveInt($input['po_id'] ?? null),
                        'received_qty' => $qty,
                    ],
                ]
            );

            if ($status === 'posted') {
                $poId = self::toPositiveInt($input['po_id'] ?? null);
                if ($poId !== null) {
                    self::recomputeOrderStatusFromReceipts($poId, $actor);
                }
            }
        }
    }

    public static function setRequestStatus(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid request id.');
        }

        $before = DB::fetchOne('SELECT request_status FROM procurement_requests WHERE id=? LIMIT 1', [$id]);
        if (!is_array($before)) {
            throw new \RuntimeException('Request not found.');
        }

        $allowed = ['draft', 'approved', 'closed', 'cancelled'];
        $status = strtolower(trim($status));
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported request status transition.');
        }

        DB::query(
            'UPDATE procurement_requests SET request_status=?, updated_at=NOW(), created_by=COALESCE(created_by, ?) WHERE id=? LIMIT 1',
            [$status, self::nullIfBlank($actor), $id]
        );

        $oldState = strtolower(trim((string)($before['request_status'] ?? '')));
        if ($oldState !== $status) {
            $action = $status === 'approved' ? AuditLogService::ACTION_APPROVED : AuditLogService::ACTION_UPDATED;
            self::recordAudit(
                'procurement_request',
                $id,
                AuditLogService::EVENT_WORKFLOW,
                $action,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'requests',
                    'old_state' => $oldState,
                    'new_state' => $status,
                    'note' => $note !== '' ? $note : 'Request status updated.',
                ]
            );
        }
    }

    public static function setOrderStatus(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid order id.');
        }

        $before = DB::fetchOne('SELECT po_status FROM procurement_purchase_orders WHERE id=? LIMIT 1', [$id]);
        if (!is_array($before)) {
            throw new \RuntimeException('Order not found.');
        }

        $allowed = ['draft', 'approved', 'issued', 'partial', 'closed', 'cancelled'];
        $status = strtolower(trim($status));
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported order status transition.');
        }

        if ($status === 'issued') {
            $ordered = (float)(DB::fetchOne('SELECT COALESCE(SUM(ordered_qty),0) AS q FROM procurement_purchase_order_lines WHERE po_id=?', [$id])['q'] ?? 0.0);
            if ($ordered <= 0.0) {
                throw new \RuntimeException('Cannot issue PO with zero ordered quantity. Add at least one line with quantity first.');
            }
        }

        DB::query(
            'UPDATE procurement_purchase_orders SET po_status=?, updated_at=NOW(), created_by=COALESCE(created_by, ?) WHERE id=? LIMIT 1',
            [$status, self::nullIfBlank($actor), $id]
        );

        $oldState = strtolower(trim((string)($before['po_status'] ?? '')));
        if ($oldState !== $status) {
            self::recordAudit(
                'procurement_order',
                $id,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_UPDATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'orders',
                    'old_state' => $oldState,
                    'new_state' => $status,
                    'note' => $note !== '' ? $note : 'PO status updated.',
                ]
            );
        }
    }

    public static function setReceiptStatus(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid receipt id.');
        }

        $before = DB::fetchOne('SELECT receipt_status, po_id FROM procurement_receipts WHERE id=? LIMIT 1', [$id]);
        if (!is_array($before)) {
            throw new \RuntimeException('Receipt not found.');
        }

        $allowed = ['received', 'posted', 'cancelled'];
        $status = strtolower(trim($status));
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported receipt status transition.');
        }

        DB::query(
            'UPDATE procurement_receipts SET receipt_status=?, updated_at=NOW(), created_by=COALESCE(created_by, ?) WHERE id=? LIMIT 1',
            [$status, self::nullIfBlank($actor), $id]
        );

        $oldState = strtolower(trim((string)($before['receipt_status'] ?? '')));
        if ($oldState !== $status) {
            self::recordAudit(
                'procurement_receipt',
                $id,
                AuditLogService::EVENT_WORKFLOW,
                $status === 'posted' ? AuditLogService::ACTION_STOCK_ADJUSTMENT_POSTED : AuditLogService::ACTION_UPDATED,
                $actor,
                [
                    'app' => 'procurement',
                    'module' => 'receipts',
                    'old_state' => $oldState,
                    'new_state' => $status,
                    'note' => $note !== '' ? $note : 'Receipt status updated.',
                    'metadata' => ['po_id' => self::toPositiveInt($before['po_id'] ?? null)],
                ]
            );
        }

        $poId = self::toPositiveInt($before['po_id'] ?? null);
        if ($poId !== null) {
            self::recomputeOrderStatusFromReceipts($poId, $actor);
        }
    }

    private static function recomputeOrderStatusFromReceipts(int $poId, string $actor): void
    {
        $po = DB::fetchOne('SELECT id, po_status FROM procurement_purchase_orders WHERE id=? LIMIT 1', [$poId]);
        if (!is_array($po)) {
            return;
        }

        $current = strtolower(trim((string)($po['po_status'] ?? 'draft')));
        if ($current === 'cancelled') {
            return;
        }

        $ordered = (float)(DB::fetchOne('SELECT COALESCE(SUM(ordered_qty),0) AS q FROM procurement_purchase_order_lines WHERE po_id=?', [$poId])['q'] ?? 0.0);
        $posted = (float)(DB::fetchOne("SELECT COALESCE(SUM(received_qty),0) AS q FROM procurement_receipts WHERE po_id=? AND LOWER(COALESCE(receipt_status,''))='posted'", [$poId])['q'] ?? 0.0);

        $target = $current;
        if ($ordered > 0) {
            if ($posted <= 0.0) {
                $target = 'issued';
            } elseif ($posted + 0.0001 < $ordered) {
                $target = 'partial';
            } else {
                $target = 'closed';
            }
        }

        if ($target !== $current) {
            self::setOrderStatus($poId, $target, $actor, 'Auto updated from posted receipt quantity.');
        }
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function attachTransitions(array $rows, string $entityType): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return $rows;
        }

        $map = self::latestTransitionMap($entityType, $ids);
        foreach ($rows as &$row) {
            $id = (int)($row['id'] ?? 0);
            $row['transition'] = $map[$id] ?? null;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,int> $entityIds
     * @return array<int,array<string,mixed>>
     */
    private static function latestTransitionMap(string $entityType, array $entityIds): array
    {
        AuditLogService::ensureSchema();

        $ids = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn(int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([strtolower(trim($entityType))], $ids);
        $rows = DB::fetchAll(
            'SELECT a.entity_id, a.action_name, a.new_state, a.note_text, a.created_at, a.actor_email, a.actor_display_name
             FROM audit_activity_log a
             INNER JOIN (
                SELECT entity_id, MAX(id) AS max_id
                FROM audit_activity_log
                WHERE entity_type = ? AND entity_id IN (' . $placeholders . ')
                GROUP BY entity_id
             ) x ON x.max_id = a.id',
            $params
        );

        $out = [];
        foreach ($rows as $row) {
            $id = (int)($row['entity_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $actor = trim((string)($row['actor_display_name'] ?? ''));
            if ($actor === '') {
                $actor = trim((string)($row['actor_email'] ?? ''));
            }

            $out[$id] = [
                'action' => (string)($row['action_name'] ?? ''),
                'state' => (string)($row['new_state'] ?? ''),
                'note' => (string)($row['note_text'] ?? ''),
                'at' => (string)($row['created_at'] ?? ''),
                'actor' => $actor !== '' ? $actor : 'System',
            ];
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $options
     */
    private static function recordAudit(string $entityType, int $entityId, string $eventType, string $action, string $actorEmail, array $options = []): void
    {
        if ($entityId <= 0) {
            return;
        }

        AuditLogService::logEvent(
            $entityType,
            $entityId,
            $eventType,
            $action,
            ['email' => $actorEmail],
            $options
        );
    }

    private static function safeCount(string $table): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }

        return (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM {$table}")['c'] ?? 0);
    }

    private static function safeCountWhere(string $table, string $where): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }

        return (int)(DB::fetchOne("SELECT COUNT(*) AS c FROM {$table} WHERE {$where}")['c'] ?? 0);
    }

    private static function tableExists(string $table): bool
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($safe === '') {
            return false;
        }

        $row = DB::fetchOne(
            'SELECT COUNT(*) AS c
             FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ?
             LIMIT 1',
            [$safe]
        );
        return (int)($row['c'] ?? 0) > 0;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param mixed $value
     */
    private static function toPositiveInt($value): ?int
    {
        $n = (int)$value;
        return $n > 0 ? $n : null;
    }

    private static function nextRef(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }
}
