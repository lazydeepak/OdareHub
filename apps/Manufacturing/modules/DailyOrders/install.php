<?php
declare(strict_types=1);

use App\Core\DB;

function daily_orders_add_column_if_missing(string $column, string $definition): void
{
	$escapedColumn = DB::conn()->real_escape_string($column);
	$exists = DB::fetchOne("SHOW COLUMNS FROM daily_orders LIKE '{$escapedColumn}'");
	if ($exists !== null) {
		return;
	}

	DB::query('ALTER TABLE daily_orders ADD COLUMN ' . $column . ' ' . $definition);
}

function daily_orders_add_index_if_missing(string $indexName, string $definition): void
{
	$escapedIndex = DB::conn()->real_escape_string($indexName);
	$exists = DB::fetchOne("SHOW INDEX FROM daily_orders WHERE Key_name = '{$escapedIndex}'");
	if ($exists !== null) {
		return;
	}

	DB::query('ALTER TABLE daily_orders ADD INDEX ' . $indexName . ' ' . $definition);
}

daily_orders_add_column_if_missing('usable_stock_qty', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER planned_supply_qty');
daily_orders_add_column_if_missing('qc_pass_qty', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER usable_stock_qty');
daily_orders_add_column_if_missing('dispatched_qty', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER qc_pass_qty');
daily_orders_add_column_if_missing('usable_supply_qty', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER dispatched_qty');
daily_orders_add_column_if_missing('open_demand_qty', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER usable_supply_qty');
daily_orders_add_column_if_missing('forecast_pressure_qty', 'DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER open_demand_qty');
daily_orders_add_column_if_missing('coverage_last_recalculated_at', 'DATETIME NULL AFTER forecast_pressure_qty');
daily_orders_add_index_if_missing('idx_daily_orders_recalc', '(coverage_last_recalculated_at)');
