<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;

final class PartEngineeringSchemaService
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }

        DB::query(
            "CREATE TABLE IF NOT EXISTS part_molds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                mold_code VARCHAR(120) NOT NULL,
                mold_name VARCHAR(190) NOT NULL,
                cavity_count INT NOT NULL DEFAULT 1,
                is_family_mold TINYINT(1) NOT NULL DEFAULT 0,
                tool_weight_kg DECIMAL(12,2) NULL,
                mold_width_mm DECIMAL(12,2) NULL,
                mold_height_mm DECIMAL(12,2) NULL,
                mold_thickness_mm DECIMAL(12,2) NULL,
                min_clamp_ton_required DECIMAL(12,2) NULL,
                min_shot_g_required DECIMAL(12,2) NULL,
                runner_type VARCHAR(80) NULL,
                cycle_time_sec DECIMAL(12,2) NULL,
                preferred_machine_group VARCHAR(50) NULL,
                preferred_machine_type VARCHAR(80) NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_part_mold_code (product_id, mold_code),
                KEY idx_part_molds_product (product_id),
                KEY idx_part_molds_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::addColumnIfMissing('part_molds', 'tool_weight_kg', "ALTER TABLE part_molds ADD COLUMN tool_weight_kg DECIMAL(12,2) NULL AFTER is_family_mold");
        self::addColumnIfMissing('part_molds', 'mold_width_mm', "ALTER TABLE part_molds ADD COLUMN mold_width_mm DECIMAL(12,2) NULL AFTER tool_weight_kg");
        self::addColumnIfMissing('part_molds', 'mold_height_mm', "ALTER TABLE part_molds ADD COLUMN mold_height_mm DECIMAL(12,2) NULL AFTER mold_width_mm");
        self::addColumnIfMissing('part_molds', 'mold_thickness_mm', "ALTER TABLE part_molds ADD COLUMN mold_thickness_mm DECIMAL(12,2) NULL AFTER mold_height_mm");
        self::addColumnIfMissing('part_molds', 'min_clamp_ton_required', "ALTER TABLE part_molds ADD COLUMN min_clamp_ton_required DECIMAL(12,2) NULL AFTER mold_thickness_mm");
        self::addColumnIfMissing('part_molds', 'min_shot_g_required', "ALTER TABLE part_molds ADD COLUMN min_shot_g_required DECIMAL(12,2) NULL AFTER min_clamp_ton_required");
        self::addColumnIfMissing('part_molds', 'runner_type', "ALTER TABLE part_molds ADD COLUMN runner_type VARCHAR(80) NULL AFTER min_shot_g_required");
        self::addColumnIfMissing('part_molds', 'cycle_time_sec', "ALTER TABLE part_molds ADD COLUMN cycle_time_sec DECIMAL(12,2) NULL AFTER runner_type");
        self::addColumnIfMissing('part_molds', 'preferred_machine_group', "ALTER TABLE part_molds ADD COLUMN preferred_machine_group VARCHAR(50) NULL AFTER cycle_time_sec");
        self::addColumnIfMissing('part_molds', 'preferred_machine_type', "ALTER TABLE part_molds ADD COLUMN preferred_machine_type VARCHAR(80) NULL AFTER preferred_machine_group");
        self::addColumnIfMissing('part_molds', 'status', "ALTER TABLE part_molds ADD COLUMN status VARCHAR(50) NOT NULL DEFAULT 'active' AFTER preferred_machine_type");
        self::addColumnIfMissing('part_molds', 'notes', "ALTER TABLE part_molds ADD COLUMN notes TEXT NULL AFTER status");
        self::addIndexIfMissing('part_molds', 'idx_part_molds_product', '(product_id)');
        self::addIndexIfMissing('part_molds', 'idx_part_molds_status', '(status)');

        self::addColumnIfMissing('part_material_map', 'yield_parts_per_kg', "ALTER TABLE part_material_map ADD COLUMN yield_parts_per_kg DECIMAL(14,4) NULL AFTER usage_unit");
        self::addColumnIfMissing('part_material_map', 'material_code_snapshot', "ALTER TABLE part_material_map ADD COLUMN material_code_snapshot VARCHAR(100) NULL AFTER yield_parts_per_kg");
        self::addColumnIfMissing('part_material_map', 'material_name_snapshot', "ALTER TABLE part_material_map ADD COLUMN material_name_snapshot VARCHAR(255) NULL AFTER material_code_snapshot");
        self::addColumnIfMissing('part_material_map', 'material_type_snapshot', "ALTER TABLE part_material_map ADD COLUMN material_type_snapshot VARCHAR(80) NULL AFTER material_name_snapshot");
        self::addColumnIfMissing('part_material_map', 'vendor_name_snapshot', "ALTER TABLE part_material_map ADD COLUMN vendor_name_snapshot VARCHAR(190) NULL AFTER material_type_snapshot");
        self::addColumnIfMissing('part_material_map', 'vendor_code_snapshot', "ALTER TABLE part_material_map ADD COLUMN vendor_code_snapshot VARCHAR(120) NULL AFTER vendor_name_snapshot");
        self::addColumnIfMissing('part_material_map', 'notes', "ALTER TABLE part_material_map ADD COLUMN notes TEXT NULL AFTER is_active");

        self::$ready = true;
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
            // Keep runtime schema setup resilient on partially migrated environments.
        }
    }

    private static function addIndexIfMissing(string $table, string $index, string $definition): void
    {
        try {
            $safeTable = DB::conn()->real_escape_string($table);
            $safeIndex = DB::conn()->real_escape_string($index);
            $exists = DB::fetchOne("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'") !== null;
            if (!$exists) {
                DB::query("ALTER TABLE `{$safeTable}` ADD INDEX {$index} {$definition}");
            }
        } catch (\Throwable $e) {
            // Keep runtime schema setup resilient on partially migrated environments.
        }
    }
}
