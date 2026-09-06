ALTER TABLE products
	ADD COLUMN qc_time_per_item DECIMAL(10,2) NULL DEFAULT NULL AFTER cycle_time,
	ADD KEY idx_products_qc_time (qc_time_per_item);
