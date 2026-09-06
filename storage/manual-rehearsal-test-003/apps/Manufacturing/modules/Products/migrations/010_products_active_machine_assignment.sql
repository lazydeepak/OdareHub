ALTER TABLE products
    ADD COLUMN active_machine_id INT NULL AFTER max_buffer_qty;

ALTER TABLE products
    ADD KEY idx_products_active_machine_id (active_machine_id);
