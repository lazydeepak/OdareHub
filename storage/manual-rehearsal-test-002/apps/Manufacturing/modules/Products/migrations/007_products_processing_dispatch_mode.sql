ALTER TABLE products
    ADD COLUMN requires_processing TINYINT(1) NOT NULL DEFAULT 1 AFTER requires_assembly;

ALTER TABLE products
    ADD COLUMN dispatch_mode ENUM('internal','direct_supplier','hybrid') NULL AFTER requires_processing;

CREATE INDEX idx_products_requires_processing ON products (requires_processing);

CREATE INDEX idx_products_dispatch_mode ON products (dispatch_mode);
