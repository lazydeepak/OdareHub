<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

use App\Core\AuditLogService;
use App\Core\DB;

final class ProcurementOverviewService
{
    /**
     * Read-only storage contract for Procurement GET surfaces.
     *
     * @param null|callable(string,array<int,string>):bool|string $schemaReader
     * @return array{available:bool,audit_available:bool,missing_tables:array<int,string>,incompatible_tables:array<int,string>,optional_sources:array{manufacturing_demands:bool}}
     */
    public static function readReadiness(?callable $schemaReader = null): array
    {
        $schemaReader ??= static fn(string $table, array $columns): string => self::tableShape($table, $columns);
        $required = [
            'procurement_suppliers' => ['id', 'supplier_name', 'supplier_status'],
            'procurement_requests' => [
                'id', 'request_ref', 'product_id', 'requested_qty', 'needed_date',
                'source_app', 'source_ref_type', 'source_ref_id', 'request_status', 'created_at',
            ],
            'procurement_purchase_orders' => [
                'id', 'po_ref', 'supplier_id', 'request_id', 'po_status',
                'order_date', 'expected_date', 'created_at',
            ],
            'procurement_purchase_order_lines' => ['id', 'po_id', 'ordered_qty'],
            'procurement_receipts' => [
                'id', 'receipt_ref', 'po_id', 'po_line_id', 'received_qty',
                'receipt_date', 'receipt_status', 'created_at',
            ],
        ];
        $missing = [];
        $incompatible = [];
        foreach ($required as $table => $columns) {
            try {
                $shape = $schemaReader($table, $columns);
                if ($shape === 'missing') {
                    $missing[] = $table;
                } elseif ($shape !== true && $shape !== 'ready') {
                    $incompatible[] = $table;
                }
            } catch (\Throwable) {
                $incompatible[] = $table;
            }
        }

        $auditAvailable = false;
        try {
            $auditShape = $schemaReader('audit_activity_log', [
                'id', 'entity_type', 'entity_id', 'action_name', 'old_state', 'new_state',
                'note_text', 'actor_email', 'actor_display_name', 'app_key', 'created_at',
            ]);
            $auditAvailable = $auditShape === true || $auditShape === 'ready';
        } catch (\Throwable) {
            $auditAvailable = false;
        }

        $manufacturingDemandsAvailable = false;
        try {
            $manufacturingDemandsShape = $schemaReader('mfg_procurement_demands', [
                'id', 'product_id', 'required_qty', 'required_date', 'status',
                'route_family', 'execution_path_label',
            ]);
            $manufacturingDemandsAvailable = $manufacturingDemandsShape === true || $manufacturingDemandsShape === 'ready';
        } catch (\Throwable) {
            $manufacturingDemandsAvailable = false;
        }

        return [
            'available' => $missing === [] && $incompatible === [],
            'audit_available' => $auditAvailable,
            'missing_tables' => $missing,
            'incompatible_tables' => array_values(array_unique($incompatible)),
            'optional_sources' => [
                'manufacturing_demands' => $manufacturingDemandsAvailable,
            ],
        ];
    }

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
     * Requests eligible for a new manual purchase-order linkage.
     *
     * Historical/request-workflow listings continue to use latestRequests().
     * This source excludes non-approved requests and requests already linked
     * to a purchase order before ordering and applying the result limit.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function eligibleRequestOptions(int $limit = 120): array
    {
        if (!self::tableExists('procurement_requests') || !self::tableExists('procurement_purchase_orders')) {
            return [];
        }

        return DB::fetchAll(
            "SELECT r.id, r.request_ref, r.product_id, r.requested_qty, r.needed_date, r.request_status, r.created_at
             FROM procurement_requests r
             LEFT JOIN procurement_purchase_orders po ON po.request_id = r.id
             WHERE LOWER(COALESCE(r.request_status, 'draft')) = 'approved'
               AND po.id IS NULL
             ORDER BY r.id DESC
             LIMIT " . max(1, (int)$limit)
        );
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
        self::assertSupplierEligible($supplierId);

        AuditLogService::ensureSchema();
        $db = DB::conn();
        $db->begin_transaction();
        try {
            // Serialize conversion attempts for the same request before the
            // existing-PO check. The request table has no request_id unique
            // constraint on purchase orders, so the row lock is the
            // application-owned idempotency boundary.
            $request = DB::fetchOne(
                'SELECT id, request_ref, product_id, requested_qty, needed_date, request_status
                 FROM procurement_requests
                 WHERE id=?
                 LIMIT 1
                 FOR UPDATE',
                [$requestId]
            );
            if (!is_array($request)) {
                throw new \RuntimeException('Request not found.');
            }

            $existingPo = DB::fetchOne(
                'SELECT id FROM procurement_purchase_orders WHERE request_id=? LIMIT 1',
                [$requestId]
            );
            if (is_array($existingPo) && (int)($existingPo['id'] ?? 0) > 0) {
                $db->commit();
                return ['po_id' => (int)$existingPo['id'], 'already_exists' => true];
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

            $poId = (int)($db->insert_id ?: 0);
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

            $db->commit();
            return ['po_id' => $poId, 'already_exists' => false];
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
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
        if (!self::tableHasColumns('audit_activity_log', [
            'id', 'entity_type', 'entity_id', 'action_name', 'old_state', 'new_state',
            'note_text', 'actor_email', 'actor_display_name', 'app_key', 'created_at',
        ])) {
            return [];
        }

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
        if (!self::tableHasColumns('mfg_procurement_demands', [
            'id', 'product_id', 'required_qty', 'required_date', 'status',
            'route_family', 'execution_path_label',
        ]) || !self::tableHasColumns('procurement_requests', [
            'id', 'request_ref', 'source_app', 'source_ref_type', 'source_ref_id',
        ])) {
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
     * Supplier options for new purchase-order creation only.
     *
     * Supplier administration continues to use supplierOptions(), which must
     * retain inactive suppliers so they can be reviewed or reactivated.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function eligibleSupplierOptions(int $limit = 120): array
    {
        if (!self::tableExists('procurement_suppliers')) {
            return [];
        }

        return DB::fetchAll(
            "SELECT id, supplier_name
             FROM procurement_suppliers
             WHERE LOWER(COALESCE(supplier_status, 'active')) NOT IN ('inactive', 'disabled', 'blocked')
             ORDER BY supplier_name ASC
             LIMIT " . max(1, (int)$limit)
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

    public static function updateSupplier(int $id, array $input, string $actor): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid supplier id.');
        }

        $before = DB::fetchOne('SELECT id, supplier_name FROM procurement_suppliers WHERE id = ? LIMIT 1', [$id]);
        if (!is_array($before) || (int)($before['id'] ?? 0) <= 0) {
            throw new \RuntimeException('Supplier not found.');
        }

        $name = trim((string)($input['supplier_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Supplier name is required.');
        }

        DB::query(
            'UPDATE procurement_suppliers SET supplier_code = ?, supplier_name = ?, contact_name = ?, email = ?, phone = ?, supplier_status = ?, updated_at = NOW() WHERE id = ? LIMIT 1',
            [
                self::nullIfBlank((string)($input['supplier_code'] ?? '')),
                $name,
                self::nullIfBlank((string)($input['contact_name'] ?? '')),
                self::nullIfBlank((string)($input['email'] ?? '')),
                self::nullIfBlank((string)($input['phone'] ?? '')),
                self::nullIfBlank((string)($input['supplier_status'] ?? 'active')) ?? 'active',
                $id,
            ]
        );

        self::recordAudit(
            'procurement_supplier',
            $id,
            AuditLogService::EVENT_WORKFLOW,
            AuditLogService::ACTION_UPDATED,
            $actor,
            [
                'app' => 'procurement',
                'module' => 'suppliers',
                'old_state' => (string)($before['supplier_name'] ?? ''),
                'new_state' => $name,
                'note' => 'Supplier updated.',
                'metadata' => [
                    'supplier_name' => $name,
                    'supplier_code' => (string)($input['supplier_code'] ?? ''),
                ],
            ]
        );
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
        // Validate and canonicalize before supplier/audit setup, transaction
        // opening, or any order/request mutation. Manual creation keeps the
        // established blank/missing default of draft while accepting the
        // existing PO status vocabulary for internal callers.
        $poStatus = self::normalizeOrderCreationStatus($input['po_status'] ?? null);

        $poRef = trim((string)($input['po_ref'] ?? ''));
        if ($poRef === '') {
            $poRef = self::nextRef('PO');
        }

        $requestId = self::toPositiveInt($input['request_id'] ?? null);

        // Validate the selected supplier before schema access, transaction
        // opening, or any order/request/audit mutation. A null supplier keeps
        // the established automatic/omitted supplier behavior.
        self::assertSupplierEligible(self::toPositiveInt($input['supplier_id'] ?? null));
        AuditLogService::ensureSchema();
        $db = DB::conn();
        $db->begin_transaction();
        try {
            self::assertManualOrderRequestEligible($requestId);
            DB::query(
                'INSERT INTO procurement_purchase_orders (po_ref, supplier_id, request_id, po_status, order_date, expected_date, created_by)
                 VALUES (?,?,?,?,?,?,?)',
                [
                    $poRef,
                    self::toPositiveInt($input['supplier_id'] ?? null),
                    $requestId,
                    $poStatus,
                    self::nullIfBlank((string)($input['order_date'] ?? '')),
                    self::nullIfBlank((string)($input['expected_date'] ?? '')),
                    self::nullIfBlank($actor),
                ]
            );

            $poId = (int)($db->insert_id ?: 0);
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
                        'new_state' => $poStatus,
                        'note' => 'Purchase order created.',
                        'metadata' => [
                            'request_id' => $requestId,
                            'supplier_id' => self::toPositiveInt($input['supplier_id'] ?? null),
                        ],
                    ]
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Validate and lock a manually linked request inside the active order
     * transaction. The optional readers exist only for isolated verification.
     *
     * @param null|callable(int):array<string,mixed>|false|null $requestReader
     * @param null|callable(int):array<string,mixed>|false|null $existingPoReader
     * @return null|array<string,mixed>
     */
    public static function assertManualOrderRequestEligible(
        ?int $requestId,
        ?callable $requestReader = null,
        ?callable $existingPoReader = null
    ): ?array {
        if ($requestId === null || $requestId <= 0) {
            return null;
        }

        $readRequest = $requestReader ?? static fn(int $id) => DB::fetchOne(
            'SELECT id, request_ref, request_status
             FROM procurement_requests
             WHERE id=?
             LIMIT 1
             FOR UPDATE',
            [$id]
        );
        $request = $readRequest($requestId);
        if (!is_array($request)) {
            throw new \RuntimeException('Request not found.');
        }

        $requestRef = trim((string)($request['request_ref'] ?? ''));
        if ($requestRef === '') {
            $requestRef = '#' . (string)$requestId;
        }
        $status = strtolower(trim((string)($request['request_status'] ?? 'draft')));
        if ($status !== 'approved') {
            throw new \RuntimeException('Request ' . $requestRef . ' is currently "' . $status . '". Only approved requests can be linked to a PO.');
        }

        $readExistingPo = $existingPoReader ?? static fn(int $id) => DB::fetchOne(
            'SELECT id FROM procurement_purchase_orders WHERE request_id=? LIMIT 1',
            [$id]
        );
        $existingPo = $readExistingPo($requestId);
        if (is_array($existingPo) && (int)($existingPo['id'] ?? 0) > 0) {
            throw new \RuntimeException('Request ' . $requestRef . ' already has a purchase order.');
        }

        return $request;
    }

    /**
     * Enforce the existing supplier lifecycle rule for new order creation.
     *
     * The optional reader exists only for isolated non-database verification;
     * production uses the existing DB authority. Null preserves the established
     * automatic-supplier path.
     *
     * @param null|callable(int):array<string,mixed>|false|null $supplierReader
     */
    public static function assertSupplierEligible(?int $supplierId, ?callable $supplierReader = null): void
    {
        if ($supplierId === null || $supplierId <= 0) {
            return;
        }

        $reader = $supplierReader ?? static fn(int $id) => DB::fetchOne(
            'SELECT id, supplier_name, supplier_status
             FROM procurement_suppliers
             WHERE id=?
             LIMIT 1',
            [$id]
        );
        $supplier = $reader($supplierId);
        if (!is_array($supplier)) {
            throw new \RuntimeException('Selected supplier was not found. Pick a valid supplier or use Auto supplier.');
        }

        $supplierStatus = strtolower(trim((string)($supplier['supplier_status'] ?? 'active')));
        if (in_array($supplierStatus, ['inactive', 'disabled', 'blocked'], true)) {
            throw new \RuntimeException('Selected supplier is inactive. Choose an active supplier.');
        }
    }

    /**
     * Validate and lock the purchase order used by a new receipt.
     *
     * The injectable reader is limited to isolated contract verification. The
     * production reader retains the row lock required by receipt/status writes.
     *
     * @param null|callable(int):array<string,mixed>|false|null $purchaseOrderReader
     * @return array<string,mixed>
     */
    public static function assertPurchaseOrderExists(int $poId, ?callable $purchaseOrderReader = null): array
    {
        if ($poId <= 0) {
            throw new \InvalidArgumentException('Purchase order reference is required.');
        }

        $reader = $purchaseOrderReader ?? static fn(int $id) => DB::fetchOne(
            'SELECT id FROM procurement_purchase_orders WHERE id=? LIMIT 1 FOR UPDATE',
            [$id]
        );
        $purchaseOrder = $reader($poId);
        if (!is_array($purchaseOrder) || (int)($purchaseOrder['id'] ?? 0) !== $poId) {
            throw new \InvalidArgumentException('Purchase order reference does not exist.');
        }

        return $purchaseOrder;
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function createReceipt(array $input, string $actor): void
    {
        // Validate and canonicalize before audit initialization, transaction
        // opening, or any business mutation. Creation intentionally permits
        // the same receipt vocabulary as setReceiptStatus(), including
        // internally supplied posted/cancelled receipts.
        $status = self::normalizeReceiptStatus(
            $input['receipt_status'] ?? null,
            true,
            'Unsupported receipt status.'
        );

        $qty = round(max(0.0, (float)($input['received_qty'] ?? 0)), 2);
        if ($qty <= 0) {
            throw new \InvalidArgumentException('Received quantity must be greater than zero.');
        }

        $poId = self::normalizeReceiptPurchaseOrderId($input['po_id'] ?? null);

        $receiptRef = trim((string)($input['receipt_ref'] ?? ''));
        if ($receiptRef === '') {
            $receiptRef = self::nextRef('RCV');
        }

        $poLineId = self::toPositiveInt($input['po_line_id'] ?? null);

        AuditLogService::ensureSchema();
        $db = DB::conn();
        $db->begin_transaction();
        try {
            // Serialize receipt insertion and aggregate order-status
            // recomputation for one purchase order. Without this lock two
            // posted receipts can each calculate status from a stale snapshot.
            self::assertPurchaseOrderExists($poId);

            if ($poLineId !== null) {
                $line = DB::fetchOne(
                    'SELECT id, po_id FROM procurement_purchase_order_lines WHERE id = ? LIMIT 1',
                    [$poLineId]
                );
                if (!is_array($line) || (int)($line['id'] ?? 0) <= 0) {
                    throw new \InvalidArgumentException('Receipt line reference does not exist.');
                }
                $linePoId = self::toPositiveInt($line['po_id'] ?? null);
                if ($linePoId !== $poId) {
                    throw new \InvalidArgumentException('Receipt line reference does not belong to the selected purchase order.');
                }
            }

            DB::query(
                'INSERT INTO procurement_receipts (receipt_ref, po_id, po_line_id, received_qty, receipt_date, receipt_status, note, created_by)
                 VALUES (?,?,?,?,?,?,?,?)',
                [
                    $receiptRef,
                    $poId,
                    $poLineId,
                    $qty,
                    self::nullIfBlank((string)($input['receipt_date'] ?? '')),
                    $status,
                    self::nullIfBlank((string)($input['note'] ?? '')),
                    self::nullIfBlank($actor),
                ]
            );

            $receiptId = (int)($db->insert_id ?: 0);
            if ($receiptId > 0) {
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
                    self::recomputeOrderStatusFromReceipts($poId, $actor);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Direct, controller-owned request status transition boundary.
     *
     * PO creation already owns a transaction and calls setRequestStatus(), so
     * direct request actions use this separate transaction-owning entry point.
     */
    public static function setRequestStatusDirect(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid request id.');
        }

        $status = self::normalizeRequestStatus($status);
        // AuditLogService::logEvent() can initialize DDL on its first call;
        // complete that before the request transaction begins.
        AuditLogService::ensureSchema();

        $db = DB::conn();
        $db->begin_transaction();
        try {
            // The internal update's first business read locks the request row
            // before cancellation/linkage checks or any mutation.
            self::setRequestStatus($id, $status, $actor, $note);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Transaction-compatible request update used by PO creation.
     * The caller owns the surrounding transaction and request-row lock.
     */
    public static function setRequestStatus(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid request id.');
        }

        self::setRequestStatusWithinTransaction($id, self::normalizeRequestStatus($status), $actor, $note);
    }

    private static function setRequestStatusWithinTransaction(int $id, string $status, string $actor, string $note): void
    {
        $before = DB::fetchOne('SELECT request_status FROM procurement_requests WHERE id=? LIMIT 1 FOR UPDATE', [$id]);
        if (!is_array($before)) {
            throw new \RuntimeException('Request not found.');
        }

        if ($status === 'cancelled') {
            $linkedPo = DB::fetchOne('SELECT id FROM procurement_purchase_orders WHERE request_id = ? LIMIT 1', [$id]);
            if (is_array($linkedPo) && (int)($linkedPo['id'] ?? 0) > 0) {
                throw new \RuntimeException('Cannot cancel procurement request with an existing purchase order.');
            }
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

    /**
     * Direct, controller-owned PO status transition boundary.
     *
     * Receipt-driven recomputation must not call this method because it already
     * owns the surrounding transaction. That path uses setOrderStatus(), which
     * remains a transaction-compatible internal update.
     */
    public static function setOrderStatusDirect(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid order id.');
        }

        $status = self::normalizeOrderStatus($status);
        // AuditLogService::logEvent() can initialize DDL on its first call;
        // complete that before the business transaction begins.
        AuditLogService::ensureSchema();

        $db = DB::conn();
        $db->begin_transaction();
        try {
            // The PO lock is the first business read and serializes this
            // direct transition with receipt creation/posting.
            self::assertPurchaseOrderExists($id);
            self::setOrderStatus($id, $status, $actor, $note);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Transaction-compatible PO status update used by receipt recomputation.
     * The caller owns the surrounding transaction and PO-first lock.
     */
    public static function setOrderStatus(int $id, string $status, string $actor, string $note = ''): void
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new \InvalidArgumentException('Invalid order id.');
        }

        self::setOrderStatusWithinTransaction($id, self::normalizeOrderStatus($status), $actor, $note);
    }

    private static function setOrderStatusWithinTransaction(int $id, string $status, string $actor, string $note): void
    {
        $before = DB::fetchOne('SELECT po_status FROM procurement_purchase_orders WHERE id=? LIMIT 1 FOR UPDATE', [$id]);
        if (!is_array($before)) {
            throw new \RuntimeException('Order not found.');
        }

        if ($status === 'cancelled') {
            $postedReceipts = DB::fetchOne("SELECT COUNT(*) AS c FROM procurement_receipts WHERE po_id = ? AND LOWER(COALESCE(receipt_status, '')) = 'posted'", [$id]);
            if (is_array($postedReceipts) && (int)($postedReceipts['c'] ?? 0) > 0) {
                throw new \RuntimeException('Cannot cancel purchase order with posted receipts.');
            }
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

        $status = self::normalizeReceiptStatus($status);

        // AuditLogService::logEvent() re-enters ensureSchema(). Complete that
        // DDL-capable initialization before opening the business transaction
        // so audit initialization cannot implicitly commit receipt changes.
        AuditLogService::ensureSchema();

        // Discover the PO outside the transaction. Once the transaction
        // begins, its first database operation is the PO locking read rather
        // than a non-locking consistent read that could establish a stale
        // snapshot under MySQL REPEATABLE READ.
        $identity = DB::fetchOne(
            'SELECT po_id FROM procurement_receipts WHERE id=? LIMIT 1',
            [$id]
        );
        if (!is_array($identity)) {
            throw new \RuntimeException('Receipt not found.');
        }
        $discoveredPoId = self::normalizeReceiptPurchaseOrderId($identity['po_id'] ?? null);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            // Lock the discovered PO before locking the receipt row. This
            // matches createReceipt()'s PO-first ordering and avoids a
            // receipt-row/PO-row lock inversion.
            self::assertPurchaseOrderExists($discoveredPoId);

            // Revalidate both receipt identity and PO ownership while the PO
            // lock is held, immediately before any mutation.
            $before = DB::fetchOne(
                'SELECT receipt_status, po_id FROM procurement_receipts WHERE id=? LIMIT 1 FOR UPDATE',
                [$id]
            );
            if (!is_array($before)) {
                throw new \RuntimeException('Receipt not found.');
            }
            $lockedPoId = self::normalizeReceiptPurchaseOrderId($before['po_id'] ?? null);
            if ($lockedPoId !== $discoveredPoId) {
                throw new \RuntimeException('Receipt purchase order changed during posting.');
            }
            $poId = $lockedPoId;

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
                        'metadata' => ['po_id' => $poId],
                    ]
                );
            }

            self::recomputeOrderStatusFromReceipts($poId, $actor);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
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
        if (!self::tableHasColumns('audit_activity_log', [
            'id', 'entity_type', 'entity_id', 'action_name', 'new_state',
            'note_text', 'actor_email', 'actor_display_name', 'created_at',
        ])) {
            return [];
        }

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

    /**
     * Read-only table shape inspection. This deliberately uses metadata only;
     * schema creation remains owned by authorized lifecycle/mutation paths.
     *
     * @param array<int,string> $columns
     */
    private static function tableHasColumns(string $table, array $columns): bool
    {
        return self::tableShape($table, $columns) === 'ready';
    }

    /**
     * @param array<int,string> $columns
     * @return 'missing'|'incompatible'|'ready'
     */
    private static function tableShape(string $table, array $columns): string
    {
        if (!self::tableExists($table)) {
            return 'missing';
        }

        $safeColumns = array_values(array_filter(array_map(
            static fn(string $column): string => preg_replace('/[^a-zA-Z0-9_]/', '', $column) ?? '',
            $columns
        )));
        if ($safeColumns === []) {
            return 'ready';
        }

        $placeholders = implode(',', array_fill(0, count($safeColumns), '?'));
        $row = DB::fetchOne(
            'SELECT COUNT(*) AS c
             FROM information_schema.columns
             WHERE table_schema = DATABASE() AND table_name = ?
               AND column_name IN (' . $placeholders . ')
             LIMIT 1',
            array_merge([$table], $safeColumns)
        );

        return (int)($row['c'] ?? 0) === count($safeColumns) ? 'ready' : 'incompatible';
    }

    private static function nullIfBlank(string $value): ?string
    {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Normalize the existing receipt-status vocabulary for both creation and
     * transitions. Creation defaults missing/blank form values to received;
     * transitions preserve their historical rejection of blank values.
     *
     * @param mixed $value
     */
    private static function normalizeReceiptStatus(
        $value,
        bool $defaultReceived = false,
        string $errorMessage = 'Unsupported receipt status transition.'
    ): string {
        if ($value === null) {
            if ($defaultReceived) {
                return 'received';
            }
            throw new \InvalidArgumentException($errorMessage);
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException($errorMessage);
        }

        $status = strtolower(trim($value));
        if ($status === '' && $defaultReceived) {
            return 'received';
        }

        if (!in_array($status, ['received', 'posted', 'cancelled'], true)) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $status;
    }

    private static function normalizeOrderStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['draft', 'approved', 'issued', 'partial', 'closed', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Unsupported order status transition.');
        }

        return $status;
    }

    /**
     * Creation-specific wrapper around the established PO status vocabulary.
     * Missing/blank form values retain the historical draft default without
     * weakening the strict normalizer used by status transitions.
     *
     * @param mixed $value
     */
    private static function normalizeOrderCreationStatus($value): string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return 'draft';
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException('Unsupported order status transition.');
        }

        return self::normalizeOrderStatus($value);
    }

    private static function normalizeRequestStatus(string $status): string
    {
        $status = strtolower(trim($status));
        if (!in_array($status, ['draft', 'approved', 'closed', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Unsupported request status transition.');
        }

        return $status;
    }

    /**
     * @param mixed $value
     */
    private static function toPositiveInt($value): ?int
    {
        $n = (int)$value;
        return $n > 0 ? $n : null;
    }

    /**
     * Receipt purchase orders are required and must use an exact positive
     * integer identity. This is intentionally stricter than the legacy
     * optional-ID normalization used by unrelated Procurement inputs.
     *
     * @param mixed $value
     */
    public static function normalizeReceiptPurchaseOrderId($value): int
    {
        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/\A[0-9]+\z/', $value) === 1) {
            $normalized = ltrim($value, '0');
            $maximum = (string)PHP_INT_MAX;
            if ($normalized !== ''
                && (strlen($normalized) < strlen($maximum)
                    || (strlen($normalized) === strlen($maximum) && strcmp($normalized, $maximum) <= 0))) {
                return (int)$normalized;
            }
        }

        throw new \InvalidArgumentException('Purchase order reference is required.');
    }

    private static function nextRef(string $prefix): string
    {
        return strtoupper($prefix) . '-' . date('Ymd-His') . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
    }
}
