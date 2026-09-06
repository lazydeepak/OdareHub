ALTER TABLE products
	ADD COLUMN cycle_time DECIMAL(10,2) NULL AFTER `lead`,
	ADD KEY idx_products_cycle_time (cycle_time);
