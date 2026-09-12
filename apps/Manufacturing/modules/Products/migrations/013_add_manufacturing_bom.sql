-- Manufacturing-owned BOM / Recipe foundation (Session A — Shared Items adoption)
-- References canonical Shared Items identity via item_ref; no Shared Inventory/Sales/Cost fields.
CREATE TABLE IF NOT EXISTS manufacturing_bom (
    id INT AUTO_INCREMENT PRIMARY KEY,
    finished_item_ref INT NOT NULL,
    version VARCHAR(50) NOT NULL DEFAULT '1.0',
    revision INT NOT NULL DEFAULT 1,
    status ENUM('draft', 'released', 'superseded', 'archived') NOT NULL DEFAULT 'draft',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_manufacturing_bom_reference (finished_item_ref, version, revision),
    KEY idx_manufacturing_bom_finished (finished_item_ref),
    KEY idx_manufacturing_bom_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS manufacturing_bom_line (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bom_id INT NOT NULL,
    component_item_ref INT NOT NULL,
    quantity DECIMAL(15, 4) NOT NULL DEFAULT 1,
    unit VARCHAR(50) NOT NULL DEFAULT 'each',
    sequence INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bom_id) REFERENCES manufacturing_bom(id) ON DELETE CASCADE,
    KEY idx_bom_line_bom (bom_id),
    KEY idx_bom_line_component (component_item_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
