ALTER TABLE daily_orders
    ADD COLUMN usable_stock_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER planned_supply_qty,
    ADD COLUMN qc_pass_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER usable_stock_qty,
    ADD COLUMN dispatched_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER qc_pass_qty,
    ADD COLUMN usable_supply_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER dispatched_qty,
    ADD COLUMN open_demand_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER usable_supply_qty,
    ADD COLUMN forecast_pressure_qty DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER open_demand_qty,
    ADD COLUMN coverage_last_recalculated_at DATETIME NULL AFTER forecast_pressure_qty;

ALTER TABLE daily_orders
    ADD KEY idx_daily_orders_recalc (coverage_last_recalculated_at);
