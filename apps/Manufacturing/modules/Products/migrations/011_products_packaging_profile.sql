ALTER TABLE products
    ADD COLUMN qty_per_case INT NULL AFTER active_machine_id,
    ADD COLUMN case_spec VARCHAR(190) NULL AFTER qty_per_case,
    ADD COLUMN case_type VARCHAR(120) NULL AFTER case_spec,
    ADD COLUMN default_case_number VARCHAR(120) NULL AFTER case_type,
    ADD COLUMN cases_per_pallet INT NULL AFTER default_case_number;

ALTER TABLE products
    ADD KEY idx_products_qty_per_case (qty_per_case);
