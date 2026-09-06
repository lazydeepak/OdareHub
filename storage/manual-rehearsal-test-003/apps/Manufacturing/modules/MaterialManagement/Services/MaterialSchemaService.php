<?php
declare(strict_types=1);

namespace Plugins\MaterialManagement\Services;

use App\Core\DB;

final class MaterialSchemaService
{
    private static bool $ready = false;

    private const DEFAULT_CURRENCY = 'USD';

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }

        DB::query(
            "CREATE TABLE IF NOT EXISTS materials (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_number VARCHAR(100) NOT NULL,
                material_name VARCHAR(255) NOT NULL,
                material_type VARCHAR(40) NOT NULL DEFAULT '材料',
                material_subtype VARCHAR(80) NULL,
                vendor_id INT NULL,
                vendor_name VARCHAR(190) NULL,
                uom VARCHAR(40) NOT NULL DEFAULT 'kg',
                pack_size DECIMAL(14,4) NULL,
                material_grade VARCHAR(120) NULL,
                color VARCHAR(80) NULL,
                lead_time_days INT NOT NULL DEFAULT 7,
                minimum_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                reorder_point_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                maximum_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                storage_capacity_qty DECIMAL(14,2) NULL,
                standard_cost DECIMAL(14,4) NULL,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                material_code VARCHAR(100) NULL,
                unit VARCHAR(40) NULL,
                supplier_name VARCHAR(190) NULL,
                supplier_ref VARCHAR(120) NULL,
                safety_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                max_storage_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                storage_location VARCHAR(120) NULL,
                standard_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
                reorder_policy VARCHAR(40) NULL,
                min_order_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                order_lot_size DECIMAL(14,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_material_number (material_number),
                UNIQUE KEY uniq_material_code (material_code),
                KEY idx_materials_type (material_type),
                KEY idx_materials_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS part_material_map (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                material_id INT NOT NULL,
                qty_per_part DECIMAL(14,4) NOT NULL DEFAULT 0,
                uom VARCHAR(40) NOT NULL DEFAULT 'kg',
                usage_qty DECIMAL(14,4) NOT NULL DEFAULT 0,
                usage_unit VARCHAR(40) NULL,
                yield_parts_per_kg DECIMAL(14,4) NULL,
                material_code_snapshot VARCHAR(100) NULL,
                material_name_snapshot VARCHAR(255) NULL,
                material_type_snapshot VARCHAR(80) NULL,
                vendor_name_snapshot VARCHAR(190) NULL,
                vendor_code_snapshot VARCHAR(120) NULL,
                scrap_pct DECIMAL(8,2) NOT NULL DEFAULT 0,
                is_primary TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                effective_from DATE NULL,
                effective_to DATE NULL,
                sequence_no INT NOT NULL DEFAULT 1,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_product_material_pair (product_id, material_id),
                UNIQUE KEY uniq_product_material_window (product_id, material_id, effective_from, effective_to),
                KEY idx_part_material_product (product_id),
                KEY idx_part_material_material (material_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS material_ledger (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_id INT NOT NULL,
                movement_type VARCHAR(40) NOT NULL DEFAULT 'ADJUST',
                qty_delta DECIMAL(14,2) NOT NULL DEFAULT 0,
                reserved_delta DECIMAL(14,2) NOT NULL DEFAULT 0,
                ledger_reference VARCHAR(120) NULL,
                reference_type VARCHAR(80) NULL,
                reference_id INT NULL,
                notes TEXT NULL,
                updated_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_material_ledger_material (material_id),
                KEY idx_material_ledger_reference (reference_type, reference_id),
                KEY idx_material_ledger_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS material_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_id INT NOT NULL,
                order_reference VARCHAR(120) NULL,
                supplier_name VARCHAR(190) NULL,
                supplier_ref VARCHAR(120) NULL,
                planned_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                expected_delivery_date DATE NOT NULL,
                received_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                status VARCHAR(40) NOT NULL DEFAULT 'planned',
                unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0,
                ordered_at DATETIME NULL,
                confirmed_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_material_orders_material (material_id),
                KEY idx_material_orders_status (status),
                KEY idx_material_orders_expected (expected_delivery_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS material_capacity (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_id INT NULL,
                location_code VARCHAR(120) NOT NULL,
                slot_label VARCHAR(120) NULL,
                max_capacity_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_material_capacity_slot (material_id, location_code),
                KEY idx_material_capacity_location (location_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        DB::query(
            "CREATE TABLE IF NOT EXISTS material_reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                material_id INT NOT NULL,
                reservation_reference VARCHAR(120) NULL,
                demand_source_type VARCHAR(80) NULL,
                demand_source_id INT NULL,
                reserved_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                fulfilled_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
                required_by_date DATE NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'active',
                notes TEXT NULL,
                created_by INT NULL,
                released_by INT NULL,
                released_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_material_reservation_material (material_id),
                KEY idx_material_reservation_source (demand_source_type, demand_source_id),
                KEY idx_material_reservation_status (status),
                KEY idx_material_reservation_required (required_by_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::addColumnIfMissing('materials', 'material_number', "ALTER TABLE materials ADD COLUMN material_number VARCHAR(100) NULL AFTER id");
        self::addColumnIfMissing('materials', 'material_subtype', "ALTER TABLE materials ADD COLUMN material_subtype VARCHAR(80) NULL AFTER material_type");
        self::addColumnIfMissing('materials', 'vendor_id', "ALTER TABLE materials ADD COLUMN vendor_id INT NULL AFTER material_subtype");
        self::addColumnIfMissing('materials', 'vendor_name', "ALTER TABLE materials ADD COLUMN vendor_name VARCHAR(190) NULL AFTER vendor_id");
        self::addColumnIfMissing('materials', 'uom', "ALTER TABLE materials ADD COLUMN uom VARCHAR(40) NULL AFTER vendor_name");
        self::addColumnIfMissing('materials', 'pack_size', "ALTER TABLE materials ADD COLUMN pack_size DECIMAL(14,4) NULL AFTER uom");
        self::addColumnIfMissing('materials', 'material_grade', "ALTER TABLE materials ADD COLUMN material_grade VARCHAR(120) NULL AFTER pack_size");
        self::addColumnIfMissing('materials', 'color', "ALTER TABLE materials ADD COLUMN color VARCHAR(80) NULL AFTER material_grade");
        self::addColumnIfMissing('materials', 'minimum_stock_qty', "ALTER TABLE materials ADD COLUMN minimum_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER lead_time_days");
        self::addColumnIfMissing('materials', 'reorder_point_qty', "ALTER TABLE materials ADD COLUMN reorder_point_qty DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER minimum_stock_qty");
        self::addColumnIfMissing('materials', 'maximum_stock_qty', "ALTER TABLE materials ADD COLUMN maximum_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER reorder_point_qty");
        self::addColumnIfMissing('materials', 'storage_capacity_qty', "ALTER TABLE materials ADD COLUMN storage_capacity_qty DECIMAL(14,2) NULL AFTER maximum_stock_qty");
        self::addColumnIfMissing('materials', 'standard_cost', "ALTER TABLE materials ADD COLUMN standard_cost DECIMAL(14,4) NULL AFTER storage_capacity_qty");
        self::addColumnIfMissing('materials', 'currency', "ALTER TABLE materials ADD COLUMN currency VARCHAR(10) NOT NULL DEFAULT '" . self::DEFAULT_CURRENCY . "' AFTER standard_cost");
        self::addColumnIfMissing('materials', 'material_code', "ALTER TABLE materials ADD COLUMN material_code VARCHAR(100) NULL AFTER currency");
        self::addColumnIfMissing('materials', 'unit', "ALTER TABLE materials ADD COLUMN unit VARCHAR(40) NULL AFTER material_code");
        self::addColumnIfMissing('materials', 'supplier_name', "ALTER TABLE materials ADD COLUMN supplier_name VARCHAR(190) NULL AFTER unit");
        self::addColumnIfMissing('materials', 'storage_location', "ALTER TABLE materials ADD COLUMN storage_location VARCHAR(120) NULL AFTER max_storage_qty");
        self::addColumnIfMissing('materials', 'standard_unit_cost', "ALTER TABLE materials ADD COLUMN standard_unit_cost DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER storage_location");
        self::addColumnIfMissing('materials', 'reorder_policy', "ALTER TABLE materials ADD COLUMN reorder_policy VARCHAR(40) NULL AFTER standard_unit_cost");
        self::addColumnIfMissing('materials', 'min_order_qty', "ALTER TABLE materials ADD COLUMN min_order_qty DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER reorder_policy");
        self::addColumnIfMissing('materials', 'order_lot_size', "ALTER TABLE materials ADD COLUMN order_lot_size DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER min_order_qty");
        self::addColumnIfMissing('materials', 'supplier_ref', "ALTER TABLE materials ADD COLUMN supplier_ref VARCHAR(120) NULL AFTER supplier_name");
        self::addColumnIfMissing('materials', 'safety_stock_qty', "ALTER TABLE materials ADD COLUMN safety_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER supplier_ref");
        self::addColumnIfMissing('materials', 'max_storage_qty', "ALTER TABLE materials ADD COLUMN max_storage_qty DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER safety_stock_qty");

        self::addColumnIfMissing('part_material_map', 'qty_per_part', "ALTER TABLE part_material_map ADD COLUMN qty_per_part DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER material_id");
        self::addColumnIfMissing('part_material_map', 'uom', "ALTER TABLE part_material_map ADD COLUMN uom VARCHAR(40) NULL AFTER qty_per_part");
        self::addColumnIfMissing('part_material_map', 'usage_unit', "ALTER TABLE part_material_map ADD COLUMN usage_unit VARCHAR(40) NULL AFTER usage_qty");
        self::addColumnIfMissing('part_material_map', 'is_primary', "ALTER TABLE part_material_map ADD COLUMN is_primary TINYINT(1) NOT NULL DEFAULT 1 AFTER scrap_pct");
        self::addColumnIfMissing('part_material_map', 'sequence_no', "ALTER TABLE part_material_map ADD COLUMN sequence_no INT NOT NULL DEFAULT 1 AFTER effective_to");
        self::addColumnIfMissing('part_material_map', 'is_active', "ALTER TABLE part_material_map ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sequence_no");
        self::addColumnIfMissing('part_material_map', 'notes', "ALTER TABLE part_material_map ADD COLUMN notes TEXT NULL AFTER is_active");

        self::addColumnIfMissing('material_ledger', 'ledger_reference', "ALTER TABLE material_ledger ADD COLUMN ledger_reference VARCHAR(120) NULL AFTER reserved_delta");

        self::addColumnIfMissing('material_orders', 'order_reference', "ALTER TABLE material_orders ADD COLUMN order_reference VARCHAR(120) NULL AFTER material_id");
        self::addColumnIfMissing('material_orders', 'supplier_ref', "ALTER TABLE material_orders ADD COLUMN supplier_ref VARCHAR(120) NULL AFTER supplier_name");
        self::addColumnIfMissing('material_orders', 'ordered_at', "ALTER TABLE material_orders ADD COLUMN ordered_at DATETIME NULL AFTER unit_cost");
        self::addColumnIfMissing('material_orders', 'confirmed_at', "ALTER TABLE material_orders ADD COLUMN confirmed_at DATETIME NULL AFTER ordered_at");
        self::addColumnIfMissing('material_orders', 'cancelled_at', "ALTER TABLE material_orders ADD COLUMN cancelled_at DATETIME NULL AFTER confirmed_at");

        self::addColumnIfMissing('material_capacity', 'slot_label', "ALTER TABLE material_capacity ADD COLUMN slot_label VARCHAR(120) NULL AFTER location_code");
        self::addColumnIfMissing('material_capacity', 'is_active', "ALTER TABLE material_capacity ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER max_capacity_qty");

        self::syncCanonicalMaterialColumns();
        self::syncCanonicalPartMaterialColumns();

        self::addIndexIfMissing('materials', 'uniq_material_number', "ALTER TABLE materials ADD UNIQUE INDEX uniq_material_number (material_number)");
        self::addIndexIfMissing('materials', 'idx_materials_storage_location', "ALTER TABLE materials ADD INDEX idx_materials_storage_location (storage_location)");
        self::addIndexIfMissing('materials', 'idx_materials_number', "ALTER TABLE materials ADD INDEX idx_materials_number (material_number)");
        self::addIndexIfMissing('material_ledger', 'idx_material_ledger_ledger_reference', "ALTER TABLE material_ledger ADD INDEX idx_material_ledger_ledger_reference (ledger_reference)");
        self::addIndexIfMissing('material_orders', 'idx_material_orders_reference', "ALTER TABLE material_orders ADD INDEX idx_material_orders_reference (order_reference)");
        self::addIndexIfMissing('material_reservations', 'idx_material_reservation_reference', "ALTER TABLE material_reservations ADD INDEX idx_material_reservation_reference (reservation_reference)");
        self::addIndexIfMissing('part_material_map', 'uniq_product_material_pair', "ALTER TABLE part_material_map ADD UNIQUE INDEX uniq_product_material_pair (product_id, material_id)");

        self::$ready = true;
    }

    private static function syncCanonicalMaterialColumns(): void
    {
        try {
            DB::query(
                "UPDATE materials
                 SET
                    material_number = COALESCE(NULLIF(material_number, ''), NULLIF(material_code, '')),
                    material_code = COALESCE(NULLIF(material_code, ''), NULLIF(material_number, '')),
                    uom = COALESCE(NULLIF(uom, ''), NULLIF(unit, ''), 'kg'),
                    unit = COALESCE(NULLIF(unit, ''), NULLIF(uom, ''), 'kg'),
                    vendor_name = COALESCE(NULLIF(vendor_name, ''), NULLIF(supplier_name, '')),
                    supplier_name = COALESCE(NULLIF(supplier_name, ''), NULLIF(vendor_name, '')),
                    minimum_stock_qty = COALESCE(minimum_stock_qty, safety_stock_qty, 0),
                    reorder_point_qty = CASE
                        WHEN COALESCE(reorder_point_qty, 0) > 0 THEN reorder_point_qty
                        ELSE COALESCE(safety_stock_qty, minimum_stock_qty, 0)
                    END,
                    maximum_stock_qty = CASE
                        WHEN COALESCE(maximum_stock_qty, 0) > 0 THEN maximum_stock_qty
                        ELSE COALESCE(max_storage_qty, storage_capacity_qty, 0)
                    END,
                    storage_capacity_qty = CASE
                        WHEN storage_capacity_qty IS NOT NULL AND storage_capacity_qty > 0 THEN storage_capacity_qty
                        ELSE NULLIF(max_storage_qty, 0)
                    END,
                    safety_stock_qty = COALESCE(safety_stock_qty, minimum_stock_qty, 0),
                    max_storage_qty = COALESCE(max_storage_qty, maximum_stock_qty, COALESCE(storage_capacity_qty, 0), 0),
                    standard_cost = COALESCE(standard_cost, standard_unit_cost),
                    standard_unit_cost = COALESCE(standard_unit_cost, standard_cost, 0),
                    currency = COALESCE(NULLIF(currency, ''), '" . self::DEFAULT_CURRENCY . "'),
                    lead_time_days = CASE
                        WHEN COALESCE(lead_time_days, 0) > 0 THEN lead_time_days
                        ELSE 7
                    END"
            );
        } catch (\Throwable $e) {
            // Keep schema setup resilient on partially initialized environments.
        }
    }

    private static function syncCanonicalPartMaterialColumns(): void
    {
        try {
            DB::query(
                "UPDATE part_material_map
                 SET
                    qty_per_part = COALESCE(NULLIF(qty_per_part, 0), usage_qty, 0),
                    usage_qty = COALESCE(NULLIF(usage_qty, 0), qty_per_part, 0),
                    uom = COALESCE(NULLIF(uom, ''), NULLIF(usage_unit, ''), 'kg'),
                    usage_unit = COALESCE(NULLIF(usage_unit, ''), NULLIF(uom, ''), 'kg'),
                    is_primary = CASE
                        WHEN is_primary IS NOT NULL THEN is_primary
                        WHEN COALESCE(sequence_no, 1) <= 1 THEN 1
                        ELSE 0
                    END"
            );
        } catch (\Throwable $e) {
            // Keep schema setup resilient on partially initialized environments.
        }
    }

    private static function addColumnIfMissing(string $table, string $column, string $alterSql): void
    {
        try {
            $safeTable = DB::conn()->real_escape_string($table);
            $safeColumn = DB::conn()->real_escape_string($column);
            $exists = DB::fetchOne("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'") !== null;
            if (!$exists) {
                DB::query($alterSql);
            }
        } catch (\Throwable $e) {
            // Keep schema setup resilient on partially initialized environments.
        }
    }

    private static function addIndexIfMissing(string $table, string $index, string $alterSql): void
    {
        try {
            $safeTable = DB::conn()->real_escape_string($table);
            $safeIndex = DB::conn()->real_escape_string($index);
            $exists = DB::fetchOne("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'") !== null;
            if (!$exists) {
                DB::query($alterSql);
            }
        } catch (\Throwable $e) {
            // Keep schema setup resilient on partially initialized environments.
        }
    }
}
