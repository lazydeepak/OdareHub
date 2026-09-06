-- Manufacturing-owned demand table only.
-- Child-module tables such as products and dispatch_entries are owned by
-- their respective module migrations/install hooks and must not be altered
-- from the bundle install path.
CREATE TABLE IF NOT EXISTS mfg_part_demands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    demand_date DATE NOT NULL,
    demand_type ENUM('production','qc','assembly') NOT NULL,
    system_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    adjusted_qty DECIMAL(14,2) NULL,
    approved_qty DECIMAL(14,2) NULL,
    adjustment_note TEXT NULL,
    adjusted_by VARCHAR(190) NULL,
    approved_by VARCHAR(190) NULL,
    approved_at DATETIME NULL,
    source_summary_json JSON NULL,
    status ENUM('calculated','adjusted','approved') NOT NULL DEFAULT 'calculated',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mfg_demand_key (product_id, demand_date, demand_type),
    KEY idx_mfg_demands_date (demand_date),
    KEY idx_mfg_demands_type (demand_type),
    KEY idx_mfg_demands_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
