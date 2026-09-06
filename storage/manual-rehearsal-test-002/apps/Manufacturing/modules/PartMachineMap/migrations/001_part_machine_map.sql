CREATE TABLE IF NOT EXISTS part_machine_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    machine_id INT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_product_machine_active (product_id, machine_id, is_active),
    KEY idx_pmm_product (product_id),
    KEY idx_pmm_machine (machine_id),
    KEY idx_pmm_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
