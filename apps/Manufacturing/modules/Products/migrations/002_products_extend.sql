ALTER TABLE products
	ADD COLUMN model VARCHAR(190) NULL AFTER parts_number,
	ADD COLUMN producer VARCHAR(190) NULL AFTER model,
	ADD COLUMN `lead` VARCHAR(120) NULL AFTER producer,
	ADD COLUMN notes TEXT NULL AFTER `lead`,
	ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER notes,
	ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
	ADD KEY idx_products_active (is_active),
	ADD KEY idx_products_model (model),
	ADD KEY idx_products_producer (producer);
