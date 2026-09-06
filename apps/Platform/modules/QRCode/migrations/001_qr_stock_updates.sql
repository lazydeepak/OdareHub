CREATE TABLE IF NOT EXISTS qr_stock_updates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('IN','OUT','ADJUST') NOT NULL DEFAULT 'ADJUST',
    qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    reference_no VARCHAR(120) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_qr_stock_updates_product (product_id),
    KEY idx_qr_stock_updates_type (movement_type),
    KEY idx_qr_stock_updates_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
