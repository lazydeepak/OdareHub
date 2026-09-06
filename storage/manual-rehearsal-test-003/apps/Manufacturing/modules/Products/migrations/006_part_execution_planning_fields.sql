ALTER TABLE products
    ADD COLUMN requires_assembly TINYINT(1) NOT NULL DEFAULT 0 AFTER default_supply_note;

ALTER TABLE products
    ADD COLUMN requires_processing TINYINT(1) NOT NULL DEFAULT 1 AFTER requires_assembly;

ALTER TABLE products
    ADD COLUMN dispatch_mode ENUM('internal','direct_supplier','hybrid') NULL AFTER requires_processing;

ALTER TABLE products
    ADD COLUMN dispatch_as_is TINYINT(1) NOT NULL DEFAULT 0 AFTER dispatch_mode;

ALTER TABLE products
    ADD COLUMN activity_type ENUM('active','passive') NOT NULL DEFAULT 'active' AFTER dispatch_as_is;

ALTER TABLE products
    ADD COLUMN essential_stock_qty DECIMAL(12,2) NULL AFTER activity_type;

ALTER TABLE products
    ADD COLUMN planning_window_days INT NULL AFTER essential_stock_qty;

ALTER TABLE products
    ADD COLUMN max_buffer_qty DECIMAL(12,2) NULL AFTER planning_window_days;

ALTER TABLE products
    ADD KEY idx_products_requires_assembly (requires_assembly);

ALTER TABLE products
    ADD KEY idx_products_activity_type (activity_type);

ALTER TABLE products
    ADD KEY idx_products_essential_stock_qty (essential_stock_qty);

ALTER TABLE products
    ADD KEY idx_products_planning_window_days (planning_window_days);

ALTER TABLE products
    ADD KEY idx_products_max_buffer_qty (max_buffer_qty);
