<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\Auth;
use App\Core\DB;
use Plugins\Supply\Services\SupplyModel;

final class DemandEngineService
{
    private const DEMAND_TYPES = ['production', 'qc', 'assembly'];

    public static function ensureSchema(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS mfg_part_demands (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            demand_date DATE NOT NULL,
            demand_type ENUM('production','qc','assembly') NOT NULL,
            system_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
            adjusted_qty DECIMAL(14,2) NULL,
            approved_qty DECIMAL(14,2) NULL,
            adjustment_note TEXT NULL,
            adjusted_by VARCHAR(190) NULL,
            approved_by VARCHAR(190) NULL,
            approved_at DATETIME NULL,
            source_summary_json JSON NULL,
            status ENUM('calculated','adjusted','approved') NOT NULL DEFAULT 'calculated',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_mfg_demand_key (product_id, demand_date, demand_type),
            KEY idx_mfg_demands_date (demand_date),
            KEY idx_mfg_demands_type (demand_type),
            KEY idx_mfg_demands_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        self::ensureProductOperationalColumns();
        self::ensureDispatchOperationalColumns();
    }

    public static function recalculateDemands(array $productIds = [], ?string $fromDate = null, ?string $toDate = null): int
    {
        self::ensureSchema();

        $sources = self::sourceDemandRows($productIds, $fromDate, $toDate);
        if (!$sources) {
            return 0;
        }

        $products = self::productOperationalMap(array_values(array_unique(array_map(static fn(array $row): int => (int)$row['product_id'], $sources))));
        $updated = 0;

        foreach ($sources as $src) {
            $productId = (int)$src['product_id'];
            $demandDate = (string)$src['demand_date'];
            $preQty = round((float)$src['pre_qty'], 2);
            $dailyQty = round((float)$src['daily_qty'], 2);
            $grossDemandQty = round(max(0.0, $preQty + $dailyQty), 2);

            $productOps = $products[$productId] ?? [
                'supply_mode' => SupplyModel::SUPPLY_IN_HOUSE,
                'fulfillment_mode' => 'company_to_destination',
                'requires_ipm_qc' => 0,
                'requires_assembly' => 0,
                'requires_processing' => 1,
                'dispatch_mode' => '',
                'dispatch_as_is' => 0,
                'essential_stock_qty' => null,
            ];

            $availableStockQty = round(max(0.0, (float)($src['available_stock_qty'] ?? 0.0)), 2);
            $plannedStockQty = round(max(0.0, (float)($src['planned_stock_qty'] ?? 0.0)), 2);
            $essentialStockQty = round(max(0.0, (float)($productOps['essential_stock_qty'] ?? 0.0)), 2);
            $bufferRecoveryNeed = round(max(0.0, $essentialStockQty - ($availableStockQty + $plannedStockQty)), 2);

            $decision = DemandRouteDecisionService::decide(
                [
                    'supply_mode' => (string)($productOps['supply_mode'] ?? SupplyModel::SUPPLY_IN_HOUSE),
                    'fulfillment_mode' => (string)($productOps['fulfillment_mode'] ?? 'company_to_destination'),
                    'requires_ipm_qc' => (int)($productOps['requires_ipm_qc'] ?? 0),
                    'requires_assembly' => (int)($productOps['requires_assembly'] ?? 0),
                    'requires_processing' => (int)($productOps['requires_processing'] ?? 1),
                    'dispatch_mode' => (string)($productOps['dispatch_mode'] ?? ''),
                    'dispatch_as_is' => (int)($productOps['dispatch_as_is'] ?? 0),
                ],
                [
                    'daily_order_qty' => $grossDemandQty,
                    'buffer_recovery_need' => $bufferRecoveryNeed,
                    'available_stock_qty' => $availableStockQty,
                    'planned_stock_qty' => $plannedStockQty,
                ]
            );

            $productionQty = (float)($decision['net_execution_qty'] ?? 0.0);
            $qcQty = ((bool)($decision['needs_qc'] ?? false)) ? $productionQty : 0.0;
            $assemblyQty = ((bool)($decision['needs_assembly'] ?? false)) ? $productionQty : 0.0;

            $sourceSummary = [
                'pre_orders_qty' => $preQty,
                'daily_orders_qty' => $dailyQty,
                'gross_demand_qty' => $grossDemandQty,
                'available_stock_qty' => $availableStockQty,
                'planned_stock_qty' => $plannedStockQty,
                'buffer_recovery_need' => $bufferRecoveryNeed,
                'decision' => $decision,
            ];

            self::upsertDemand($productId, $demandDate, 'production', $productionQty, $sourceSummary);
            self::upsertDemand($productId, $demandDate, 'qc', $qcQty, $sourceSummary);
            self::upsertDemand($productId, $demandDate, 'assembly', $assemblyQty, $sourceSummary);
            $updated += 3;
        }

        return $updated;
    }

    public static function adjustDemand(int $id, float $adjustedQty, string $note): void
    {
        self::ensureSchema();
        $row = DB::fetchOne('SELECT * FROM mfg_part_demands WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            throw new \RuntimeException('Demand record not found.');
        }

        $adjustedQty = round(max(0.0, $adjustedQty), 2);
        $status = ((float)($row['approved_qty'] ?? 0) > 0 || (string)($row['status'] ?? '') === 'approved') ? 'approved' : 'adjusted';

        DB::query(
            'UPDATE mfg_part_demands SET adjusted_qty=?, adjustment_note=?, adjusted_by=?, status=?, updated_at=NOW() WHERE id=?',
            [
                $adjustedQty,
                trim($note),
                self::currentUserLabel(),
                $status,
                $id,
            ]
        );
    }

    public static function approveDemand(int $id, ?float $approvedQty = null): void
    {
        self::ensureSchema();
        $row = DB::fetchOne('SELECT * FROM mfg_part_demands WHERE id=? LIMIT 1', [$id]);
        if (!$row) {
            throw new \RuntimeException('Demand record not found.');
        }

        $fallback = (float)($row['adjusted_qty'] ?? $row['system_qty'] ?? 0);
        $final = $approvedQty !== null ? round(max(0.0, $approvedQty), 2) : round(max(0.0, $fallback), 2);

        DB::query(
            'UPDATE mfg_part_demands SET approved_qty=?, approved_by=?, approved_at=NOW(), status=?, updated_at=NOW() WHERE id=?',
            [$final, self::currentUserLabel(), 'approved', $id]
        );
    }

    public static function listDemands(string $fromDate = '', string $toDate = '', int $productId = 0, string $demandType = '', string $status = ''): array
    {
        self::ensureSchema();
        $sql = "SELECT d.*, p.parts_name, p.parts_number
                FROM mfg_part_demands d
                INNER JOIN products p ON p.id=d.product_id
                WHERE 1=1";
        $params = [];

        if ($fromDate !== '') {
            $sql .= ' AND d.demand_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate !== '') {
            $sql .= ' AND d.demand_date <= ?';
            $params[] = $toDate;
        }
        if ($productId > 0) {
            $sql .= ' AND d.product_id = ?';
            $params[] = $productId;
        }
        if (in_array($demandType, self::DEMAND_TYPES, true)) {
            $sql .= ' AND d.demand_type = ?';
            $params[] = $demandType;
        }
        if (in_array($status, ['calculated', 'adjusted', 'approved'], true)) {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY d.demand_date DESC, p.parts_name ASC, d.demand_type ASC LIMIT 1200';
        return DB::fetchAll($sql, $params);
    }

    public static function previewRouteDecisions(?string $targetDate = null, int $limit = 50, array $productIds = []): array
    {
        self::ensureSchema();

        $productIds = array_values(array_filter(array_map(static fn($v): int => (int)$v, $productIds), static fn(int $v): bool => $v > 0));
        $rows = self::sourceDemandRows($productIds, $targetDate, $targetDate);
        if (!$rows) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            $left = ((string)$a['demand_date']) . '-' . str_pad((string)((int)$a['product_id']), 10, '0', STR_PAD_LEFT);
            $right = ((string)$b['demand_date']) . '-' . str_pad((string)((int)$b['product_id']), 10, '0', STR_PAD_LEFT);
            return strcmp($left, $right);
        });

        $limit = max(1, min(200, $limit));
        $rows = array_slice($rows, 0, $limit);

        $productIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['product_id'], $rows)));
        $products = self::productOperationalMap($productIds);

        $out = [];
        foreach ($rows as $src) {
            $productId = (int)$src['product_id'];
            $preQty = round((float)$src['pre_qty'], 2);
            $dailyQty = round((float)$src['daily_qty'], 2);
            $availableStockQty = round(max(0.0, (float)($src['available_stock_qty'] ?? 0.0)), 2);
            $plannedStockQty = round(max(0.0, (float)($src['planned_stock_qty'] ?? 0.0)), 2);
            $grossDemandQty = round(max(0.0, $preQty + $dailyQty), 2);

            $productOps = $products[$productId] ?? [
                'supply_mode' => SupplyModel::SUPPLY_IN_HOUSE,
                'fulfillment_mode' => 'company_to_destination',
                'requires_ipm_qc' => 0,
                'requires_assembly' => 0,
                'requires_processing' => 1,
                'dispatch_mode' => '',
                'dispatch_as_is' => 0,
                'essential_stock_qty' => null,
            ];

            $essentialStockQty = round(max(0.0, (float)($productOps['essential_stock_qty'] ?? 0.0)), 2);
            $bufferRecoveryNeed = round(max(0.0, $essentialStockQty - ($availableStockQty + $plannedStockQty)), 2);

            $decision = DemandRouteDecisionService::decide(
                [
                    'supply_mode' => (string)($productOps['supply_mode'] ?? SupplyModel::SUPPLY_IN_HOUSE),
                    'fulfillment_mode' => (string)($productOps['fulfillment_mode'] ?? 'company_to_destination'),
                    'requires_ipm_qc' => (int)($productOps['requires_ipm_qc'] ?? 0),
                    'requires_assembly' => (int)($productOps['requires_assembly'] ?? 0),
                    'requires_processing' => (int)($productOps['requires_processing'] ?? 1),
                    'dispatch_mode' => (string)($productOps['dispatch_mode'] ?? ''),
                    'dispatch_as_is' => (int)($productOps['dispatch_as_is'] ?? 0),
                ],
                [
                    'daily_order_qty' => $grossDemandQty,
                    'buffer_recovery_need' => $bufferRecoveryNeed,
                    'available_stock_qty' => $availableStockQty,
                    'planned_stock_qty' => $plannedStockQty,
                ]
            );

            $out[] = [
                'product_id' => $productId,
                'demand_date' => (string)$src['demand_date'],
                'part_profile' => [
                    'supply_mode' => (string)($productOps['supply_mode'] ?? SupplyModel::SUPPLY_IN_HOUSE),
                    'fulfillment_mode' => (string)($productOps['fulfillment_mode'] ?? 'company_to_destination'),
                    'requires_ipm_qc' => (int)($productOps['requires_ipm_qc'] ?? 0),
                    'requires_assembly' => (int)($productOps['requires_assembly'] ?? 0),
                    'requires_processing' => (int)($productOps['requires_processing'] ?? 1),
                    'dispatch_mode' => (string)($productOps['dispatch_mode'] ?? ''),
                    'dispatch_as_is' => (int)($productOps['dispatch_as_is'] ?? 0),
                ],
                'inputs' => [
                    'pre_orders_qty' => $preQty,
                    'daily_orders_qty' => $dailyQty,
                    'gross_demand_qty' => $grossDemandQty,
                    'available_stock_qty' => $availableStockQty,
                    'planned_stock_qty' => $plannedStockQty,
                    'essential_stock_qty' => $essentialStockQty,
                    'buffer_recovery_need' => $bufferRecoveryNeed,
                ],
                'decision' => $decision,
            ];
        }

        return $out;
    }

    public static function sourceDemandRows(array $productIds = [], ?string $fromDate = null, ?string $toDate = null): array
    {
        $dailyOrderColumns = self::tableColumns('daily_orders');
        $availableStockExpr = isset($dailyOrderColumns['usable_stock_qty']) ? 'COALESCE(d.usable_stock_qty, 0)' : '0.00';
        $plannedStockExpr = isset($dailyOrderColumns['planned_supply_qty']) ? 'COALESCE(d.planned_supply_qty, 0)' : '0.00';

        $params = [];
        $where = '';

        if ($productIds) {
            $holders = implode(',', array_fill(0, count($productIds), '?'));
            $where .= " AND src.product_id IN ({$holders})";
            foreach ($productIds as $id) {
                $params[] = (int)$id;
            }
        }

        if (($fromDate ?? '') !== '') {
            $where .= ' AND src.demand_date >= ?';
            $params[] = $fromDate;
        }
        if (($toDate ?? '') !== '') {
            $where .= ' AND src.demand_date <= ?';
            $params[] = $toDate;
        }

        $sql = "SELECT
                    src.product_id,
                    src.demand_date,
                    ROUND(SUM(src.pre_qty), 2) AS pre_qty,
                    ROUND(SUM(src.daily_qty), 2) AS daily_qty,
                    ROUND(SUM(src.available_stock_qty), 2) AS available_stock_qty,
                    ROUND(SUM(src.planned_stock_qty), 2) AS planned_stock_qty
                FROM (
                    SELECT
                        p.product_id,
                        COALESCE(p.required_date, CURRENT_DATE()) AS demand_date,
                        ROUND(COALESCE(NULLIF(p.balance_qty, 0), p.planned_qty, 0), 2) AS pre_qty,
                        0.00 AS daily_qty,
                        0.00 AS available_stock_qty,
                        0.00 AS planned_stock_qty
                    FROM pre_orders p
                    WHERE p.product_id IS NOT NULL

                    UNION ALL

                    SELECT
                        d.product_id,
                        COALESCE(d.required_date, d.order_date, CURRENT_DATE()) AS demand_date,
                        0.00 AS pre_qty,
                        ROUND(COALESCE(d.qty, 0), 2) AS daily_qty,
                        {$availableStockExpr} AS available_stock_qty,
                        {$plannedStockExpr} AS planned_stock_qty
                    FROM daily_orders d
                    WHERE d.product_id IS NOT NULL
                      AND LOWER(COALESCE(d.status, 'open')) NOT IN ('closed','completed','cancelled','canceled')
                ) src
                WHERE 1=1 {$where}
                GROUP BY src.product_id, src.demand_date";

        return DB::fetchAll($sql, $params);
    }

    private static function upsertDemand(int $productId, string $demandDate, string $demandType, float $systemQty, array $sourceSummary): void
    {
        $existing = DB::fetchOne(
            'SELECT * FROM mfg_part_demands WHERE product_id=? AND demand_date=? AND demand_type=? LIMIT 1',
            [$productId, $demandDate, $demandType]
        );

        $summaryJson = json_encode($sourceSummary, JSON_UNESCAPED_SLASHES);
        if ($summaryJson === false) {
            $summaryJson = '{}';
        }

        if (!$existing) {
            DB::query(
                'INSERT INTO mfg_part_demands (product_id, demand_date, demand_type, system_qty, adjusted_qty, approved_qty, source_summary_json, status) VALUES (?,?,?,?,?,?,?,?)',
                [$productId, $demandDate, $demandType, $systemQty, $systemQty, null, $summaryJson, 'calculated']
            );
            return;
        }

        $existingAdjusted = isset($existing['adjusted_qty']) ? (float)$existing['adjusted_qty'] : null;
        $existingApproved = isset($existing['approved_qty']) ? (float)$existing['approved_qty'] : null;
        $nextAdjusted = $existingAdjusted ?? $systemQty;
        $nextStatus = 'calculated';

        if ((string)($existing['status'] ?? '') === 'approved' || $existingApproved !== null) {
            $nextStatus = 'approved';
        } elseif ($existingAdjusted !== null && round($existingAdjusted, 2) !== round($systemQty, 2)) {
            $nextStatus = 'adjusted';
        }

        DB::query(
            'UPDATE mfg_part_demands SET system_qty=?, adjusted_qty=?, source_summary_json=?, status=?, updated_at=NOW() WHERE id=?',
            [$systemQty, $nextAdjusted, $summaryJson, $nextStatus, (int)$existing['id']]
        );
    }

    private static function productOperationalMap(array $productIds): array
    {
        if (!$productIds) {
            return [];
        }

        $holders = implode(',', array_fill(0, count($productIds), '?'));
        $rows = DB::fetchAll(
            "SELECT id, supply_mode, fulfillment_mode, requires_ipm_qc, requires_assembly, requires_processing, dispatch_mode, dispatch_as_is, essential_stock_qty FROM products WHERE id IN ({$holders})",
            $productIds
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row;
        }
        return $map;
    }
    /**
     * @return array<string,bool>
     */
    private static function tableColumns(string $table): array
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
        if ($safeTable === '') {
            return [];
        }

        $rows = DB::fetchAll('SHOW COLUMNS FROM ' . $safeTable);
        $out = [];
        foreach ($rows as $row) {
            $name = strtolower(trim((string)($row['Field'] ?? '')));
            if ($name !== '') {
                $out[$name] = true;
            }
        }
        return $out;
    }

    private static function ensureProductOperationalColumns(): void
    {
        // DEPRECATED FIELDS (kept for backward compatibility during transition phase)
        // These columns are superseded by the canonical fields in Products plugin:
        // - production_source → use supply_mode instead
        // - requires_qc → use requires_ipm_qc instead
        // - delivery_flow (in products table) → use fulfillment_mode instead
        // 
        // All new code should read from canonical fields. These legacy columns
        // may be safely dropped after all code references have been migrated.
        self::addColumnIfMissing('products', 'production_source', "ALTER TABLE products ADD COLUMN production_source ENUM('in_house','third_party') NOT NULL DEFAULT 'in_house'");
        self::addColumnIfMissing('products', 'requires_qc', "ALTER TABLE products ADD COLUMN requires_qc TINYINT(1) NOT NULL DEFAULT 0");
        self::addColumnIfMissing('products', 'requires_assembly', "ALTER TABLE products ADD COLUMN requires_assembly TINYINT(1) NOT NULL DEFAULT 0");
        self::addColumnIfMissing('products', 'requires_processing', "ALTER TABLE products ADD COLUMN requires_processing TINYINT(1) NOT NULL DEFAULT 1");
        self::addColumnIfMissing('products', 'dispatch_mode', "ALTER TABLE products ADD COLUMN dispatch_mode ENUM('internal','direct_supplier','hybrid') NULL");
        self::addColumnIfMissing('products', 'dispatch_as_is', "ALTER TABLE products ADD COLUMN dispatch_as_is TINYINT(1) NOT NULL DEFAULT 0");
        self::addColumnIfMissing('products', 'delivery_flow', "ALTER TABLE products ADD COLUMN delivery_flow ENUM('company_to_destination','third_party_to_destination','third_party_to_company_to_destination') NOT NULL DEFAULT 'company_to_destination'");
        self::addColumnIfMissing('products', 'activity_type', "ALTER TABLE products ADD COLUMN activity_type ENUM('active','passive') NOT NULL DEFAULT 'active'");
    }

    private static function ensureDispatchOperationalColumns(): void
    {
        self::addColumnIfMissing('dispatch_entries', 'cases_count', "ALTER TABLE dispatch_entries ADD COLUMN cases_count INT NOT NULL DEFAULT 0");
        self::addColumnIfMissing('dispatch_entries', 'pallets_count', "ALTER TABLE dispatch_entries ADD COLUMN pallets_count INT NOT NULL DEFAULT 0");
        self::addColumnIfMissing('dispatch_entries', 'eta_load_at', "ALTER TABLE dispatch_entries ADD COLUMN eta_load_at DATETIME NULL");
        self::addColumnIfMissing('dispatch_entries', 'driver_name', "ALTER TABLE dispatch_entries ADD COLUMN driver_name VARCHAR(190) NULL");
        self::addColumnIfMissing('dispatch_entries', 'truck_no', "ALTER TABLE dispatch_entries ADD COLUMN truck_no VARCHAR(80) NULL");
        self::addColumnIfMissing('dispatch_entries', 'carrier_name', "ALTER TABLE dispatch_entries ADD COLUMN carrier_name VARCHAR(190) NULL");
        self::addColumnIfMissing('dispatch_entries', 'load_reference', "ALTER TABLE dispatch_entries ADD COLUMN load_reference VARCHAR(120) NULL");
        self::addColumnIfMissing('dispatch_entries', 'dispatch_mode', "ALTER TABLE dispatch_entries ADD COLUMN dispatch_mode ENUM('in_house_dispatch','third_party_dispatch','company_origin_dispatch','third_party_direct_dispatch','third_party_to_company_then_destination') NOT NULL DEFAULT 'company_origin_dispatch'");
        self::addColumnIfMissing('dispatch_entries', 'delivery_flow', "ALTER TABLE dispatch_entries ADD COLUMN delivery_flow ENUM('company_to_destination','third_party_to_destination','third_party_to_company_to_destination') NOT NULL DEFAULT 'company_to_destination'");
        self::addColumnIfMissing('dispatch_entries', 'source_type', "ALTER TABLE dispatch_entries ADD COLUMN source_type ENUM('in_house','third_party') NOT NULL DEFAULT 'in_house'");
        self::addColumnIfMissing('dispatch_entries', 'prepared_by', "ALTER TABLE dispatch_entries ADD COLUMN prepared_by VARCHAR(190) NULL");
        self::addColumnIfMissing('dispatch_entries', 'prepared_at', "ALTER TABLE dispatch_entries ADD COLUMN prepared_at DATETIME NULL");
        self::addColumnIfMissing('dispatch_entries', 'dispatch_completed_by', "ALTER TABLE dispatch_entries ADD COLUMN dispatch_completed_by VARCHAR(190) NULL");
        self::addColumnIfMissing('dispatch_entries', 'dispatch_completed_at', "ALTER TABLE dispatch_entries ADD COLUMN dispatch_completed_at DATETIME NULL");
        self::addColumnIfMissing('dispatch_entries', 'completion_status', "ALTER TABLE dispatch_entries ADD COLUMN completion_status ENUM('draft','ready','prepared','completed') NOT NULL DEFAULT 'draft'");
        self::addColumnIfMissing('dispatch_entries', 'third_party_reference', "ALTER TABLE dispatch_entries ADD COLUMN third_party_reference VARCHAR(190) NULL");
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
