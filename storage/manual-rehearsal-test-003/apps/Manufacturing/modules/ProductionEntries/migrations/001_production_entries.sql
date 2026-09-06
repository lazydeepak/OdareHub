CREATE TABLE IF NOT EXISTS production_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_date DATE NOT NULL,
    shift VARCHAR(30) NOT NULL DEFAULT 'Day',
    machine_id INT NOT NULL,
    product_id INT NOT NULL,
    produced_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    rejected_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    good_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'Draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_pe_date (production_date),
    KEY idx_pe_machine (machine_id),
    KEY idx_pe_product (product_id),
    KEY idx_pe_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
