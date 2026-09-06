CREATE TABLE IF NOT EXISTS stock_ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('IN','OUT','ADJUST','PRODUCTION_IN','PRODUCTION_OUT','DISPATCH_OUT') NOT NULL DEFAULT 'ADJUST',
    qty_delta DECIMAL(14,2) NOT NULL DEFAULT 0,
    balance_after DECIMAL(14,2) NOT NULL DEFAULT 0,
    reference_no VARCHAR(120) NULL,
    source_module VARCHAR(80) NULL,
    source_id INT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_stock_ledger_product (product_id),
    KEY idx_stock_ledger_movement (movement_type),
    KEY idx_stock_ledger_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
