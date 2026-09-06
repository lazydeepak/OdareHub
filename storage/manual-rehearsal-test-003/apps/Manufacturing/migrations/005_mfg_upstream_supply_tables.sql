-- Manufacturing app: upstream planning/procurement generation tables.

CREATE TABLE IF NOT EXISTS mfg_production_plan_candidates (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    demand_date DATE NOT NULL,
    planned_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    route_family VARCHAR(80) NULL,
    execution_path_label VARCHAR(190) NULL,
    source_demand_reference VARCHAR(120) NOT NULL,
    source_summary_json JSON NULL,
    status ENUM('draft','reviewed','approved','cancelled') NOT NULL DEFAULT 'draft',
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_production_candidate (product_id, demand_date, source_demand_reference),
    KEY idx_production_candidate_date (demand_date),
    KEY idx_production_candidate_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mfg_procurement_demands (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    required_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    required_date DATE NOT NULL,
    fulfillment_mode VARCHAR(40) NOT NULL,
    dispatch_mode VARCHAR(40) NULL,
    route_family VARCHAR(80) NULL,
    execution_path_label VARCHAR(190) NULL,
    source_demand_reference VARCHAR(120) NOT NULL,
    source_summary_json JSON NULL,
    status ENUM('draft','reviewed','approved','cancelled') NOT NULL DEFAULT 'draft',
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_procurement_demand (product_id, required_date, source_demand_reference),
    KEY idx_procurement_date (required_date),
    KEY idx_procurement_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS mfg_upstream_generation_links (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    work_type ENUM('production','procurement') NOT NULL,
    product_id INT NOT NULL,
    demand_date DATE NOT NULL,
    source_key VARCHAR(120) NOT NULL,
    route_family VARCHAR(80) NULL,
    execution_path_label VARCHAR(190) NULL,
    target_table VARCHAR(80) NOT NULL,
    target_id BIGINT NOT NULL,
    demanded_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    source_summary_json JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_upstream_link (work_type, product_id, demand_date, source_key),
    KEY idx_upstream_target (target_table, target_id),
    KEY idx_upstream_date (demand_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
