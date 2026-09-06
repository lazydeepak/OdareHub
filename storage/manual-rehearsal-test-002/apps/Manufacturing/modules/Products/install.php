<?php
declare(strict_types=1);

use App\Core\DB;

require_once __DIR__ . '/Services/PartEngineeringSchemaService.php';

function products_add_column_if_missing(string $column, string $definition): void
{
	$escapedColumn = DB::conn()->real_escape_string($column);
	$exists = DB::fetchOne("SHOW COLUMNS FROM products LIKE '{$escapedColumn}'");
	if ($exists !== null) {
		return;
	}

	DB::query('ALTER TABLE products ADD COLUMN ' . $column . ' ' . $definition);
}

function products_add_index_if_missing(string $indexName, string $definition): void
{
	$escapedIndex = DB::conn()->real_escape_string($indexName);
	$exists = DB::fetchOne("SHOW INDEX FROM products WHERE Key_name = '{$escapedIndex}'");
	if ($exists !== null) {
		return;
	}

	DB::query('ALTER TABLE products ADD INDEX ' . $indexName . ' ' . $definition);
}

products_add_column_if_missing('model', 'VARCHAR(190) NULL AFTER parts_number');
products_add_column_if_missing('producer', 'VARCHAR(190) NULL AFTER model');
products_add_column_if_missing('lead', '`lead` VARCHAR(120) NULL AFTER producer');
products_add_column_if_missing('notes', 'TEXT NULL AFTER `lead`');
products_add_column_if_missing('is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER notes');
products_add_column_if_missing('updated_at', 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');
products_add_column_if_missing('supply_mode', "VARCHAR(40) NOT NULL DEFAULT 'in_house' AFTER is_active");
products_add_column_if_missing('fulfillment_mode', "VARCHAR(40) NOT NULL DEFAULT 'via_ipm' AFTER supply_mode");
products_add_column_if_missing('requires_ipm_qc', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER fulfillment_mode');
products_add_column_if_missing('default_supplier', 'VARCHAR(190) NULL AFTER requires_ipm_qc');
products_add_column_if_missing('stocked_at_ipm', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER default_supplier');
products_add_column_if_missing('default_procurement_lead_days', 'DECIMAL(10,2) NULL AFTER stocked_at_ipm');
products_add_column_if_missing('default_supply_note', 'TEXT NULL AFTER default_procurement_lead_days');

// Part Execution Routing Fields (Phase 1)
products_add_column_if_missing('requires_assembly', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER default_supply_note');
products_add_column_if_missing('requires_processing', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER requires_assembly');
products_add_column_if_missing('dispatch_mode', "ENUM('internal','direct_supplier','hybrid') NULL AFTER requires_processing");
products_add_column_if_missing('dispatch_as_is', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER dispatch_mode');
products_add_column_if_missing('activity_type', "ENUM('active','passive') NOT NULL DEFAULT 'active' AFTER dispatch_as_is");
products_add_column_if_missing('essential_stock_qty', 'DECIMAL(12,2) NULL AFTER activity_type');
products_add_column_if_missing('planning_window_days', 'INT NULL AFTER essential_stock_qty');
products_add_column_if_missing('max_buffer_qty', 'DECIMAL(12,2) NULL AFTER planning_window_days');
products_add_column_if_missing('safety_stock_qty', 'DECIMAL(12,2) NULL AFTER max_buffer_qty');
products_add_column_if_missing('active_machine_id', 'INT NULL AFTER max_buffer_qty');
products_add_column_if_missing('qty_per_case', 'INT NULL AFTER active_machine_id');
products_add_column_if_missing('case_spec', 'VARCHAR(190) NULL AFTER qty_per_case');
products_add_column_if_missing('case_type', 'VARCHAR(120) NULL AFTER case_spec');
products_add_column_if_missing('default_case_number', 'VARCHAR(120) NULL AFTER case_type');
products_add_column_if_missing('cases_per_pallet', 'INT NULL AFTER default_case_number');

products_add_index_if_missing('idx_products_active', '(is_active)');
products_add_index_if_missing('idx_products_model', '(model)');
products_add_index_if_missing('idx_products_producer', '(producer)');
products_add_index_if_missing('idx_products_supply_mode', '(supply_mode)');
products_add_index_if_missing('idx_products_fulfillment_mode', '(fulfillment_mode)');
products_add_index_if_missing('idx_products_requires_assembly', '(requires_assembly)');
products_add_index_if_missing('idx_products_requires_processing', '(requires_processing)');
products_add_index_if_missing('idx_products_dispatch_mode', '(dispatch_mode)');
products_add_index_if_missing('idx_products_activity_type', '(activity_type)');
products_add_index_if_missing('idx_products_essential_stock_qty', '(essential_stock_qty)');
products_add_index_if_missing('idx_products_planning_window_days', '(planning_window_days)');
products_add_index_if_missing('idx_products_max_buffer_qty', '(max_buffer_qty)');
products_add_index_if_missing('idx_products_safety_stock_qty', '(safety_stock_qty)');
products_add_index_if_missing('idx_products_active_machine_id', '(active_machine_id)');
products_add_index_if_missing('idx_products_qty_per_case', '(qty_per_case)');

DB::query('UPDATE products SET safety_stock_qty = COALESCE(safety_stock_qty, max_buffer_qty, 0) WHERE safety_stock_qty IS NULL');
DB::query('UPDATE products SET max_buffer_qty = COALESCE(max_buffer_qty, safety_stock_qty, 0) WHERE max_buffer_qty IS NULL');

\Plugins\Products\Services\PartEngineeringSchemaService::ensureSchema();
