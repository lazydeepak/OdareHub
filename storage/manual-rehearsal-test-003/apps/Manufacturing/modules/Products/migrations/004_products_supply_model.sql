ALTER TABLE products
    ADD COLUMN supply_mode VARCHAR(40) NOT NULL DEFAULT 'in_house' AFTER is_active,
    ADD COLUMN fulfillment_mode VARCHAR(40) NOT NULL DEFAULT 'via_ipm' AFTER supply_mode,
    ADD COLUMN requires_ipm_qc TINYINT(1) NOT NULL DEFAULT 1 AFTER fulfillment_mode,
    ADD COLUMN default_supplier VARCHAR(190) NULL AFTER requires_ipm_qc,
    ADD COLUMN stocked_at_ipm TINYINT(1) NOT NULL DEFAULT 1 AFTER default_supplier,
    ADD COLUMN default_procurement_lead_days DECIMAL(10,2) NULL AFTER stocked_at_ipm,
    ADD COLUMN default_supply_note TEXT NULL AFTER default_procurement_lead_days,
    ADD KEY idx_products_supply_mode (supply_mode),
    ADD KEY idx_products_fulfillment_mode (fulfillment_mode);
