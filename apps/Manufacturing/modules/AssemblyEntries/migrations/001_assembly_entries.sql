-- AssemblyEntries module: execution records for assembly work completed before QC.
CREATE TABLE IF NOT EXISTS mfg_assembly_entries (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    assembly_plan_id BIGINT NULL,
    product_id INT NOT NULL,
    source_type VARCHAR(40) NULL,
    source_id BIGINT NULL,
    assembly_date DATE NOT NULL,
    planned_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    completed_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    rejected_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('draft','in_progress','completed','approved','blocked','cancelled') NOT NULL DEFAULT 'draft',
    note TEXT NULL,
    completed_by VARCHAR(190) NULL,
    approved_by VARCHAR(190) NULL,
    approved_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mfg_assembly_plan (assembly_plan_id),
    KEY idx_mfg_assembly_product_date (product_id, assembly_date),
    KEY idx_mfg_assembly_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
