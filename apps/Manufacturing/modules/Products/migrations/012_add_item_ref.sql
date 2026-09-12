ALTER TABLE products
    ADD COLUMN item_ref INT NULL DEFAULT NULL,
    ADD KEY idx_products_item_ref (item_ref);
