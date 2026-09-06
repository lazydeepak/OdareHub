CREATE TABLE IF NOT EXISTS part_molds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    mold_code VARCHAR(120) NOT NULL,
    mold_name VARCHAR(190) NOT NULL,
    cavity_count INT NOT NULL DEFAULT 1,
    is_family_mold TINYINT(1) NOT NULL DEFAULT 0,
    tool_weight_kg DECIMAL(12,2) NULL,
    mold_width_mm DECIMAL(12,2) NULL,
    mold_height_mm DECIMAL(12,2) NULL,
    mold_thickness_mm DECIMAL(12,2) NULL,
    min_clamp_ton_required DECIMAL(12,2) NULL,
    min_shot_g_required DECIMAL(12,2) NULL,
    runner_type VARCHAR(80) NULL,
    cycle_time_sec DECIMAL(12,2) NULL,
    preferred_machine_group VARCHAR(50) NULL,
    preferred_machine_type VARCHAR(80) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_part_mold_code (product_id, mold_code),
    KEY idx_part_molds_product (product_id),
    KEY idx_part_molds_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- part_material_map yield/snapshot columns are included in MaterialSchemaService
-- directly, so they exist regardless of module install order.
-- No further schema changes needed in this file.
