CREATE TABLE IF NOT EXISTS base_module_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(80) NOT NULL UNIQUE,
    label VARCHAR(150) NOT NULL,
    table_name VARCHAR(120) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS base_field_registry (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_key VARCHAR(80) NOT NULL,
    field_key VARCHAR(120) NOT NULL,
    column_name VARCHAR(120) NOT NULL,
    label VARCHAR(160) NOT NULL,
    data_type VARCHAR(40) NOT NULL,
    max_length INT NULL,
    precision_value INT NULL,
    scale_value INT NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 0,
    default_value VARCHAR(255) NULL,
    options_text TEXT NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 100,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    last_synced_at DATETIME NULL,
    created_by VARCHAR(190) NULL,
    updated_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_base_field_module_column (module_key, column_name),
    UNIQUE KEY uniq_base_field_module_field_key (module_key, field_key),
    KEY idx_base_field_module (module_key),
    KEY idx_base_field_status (status),
    KEY idx_base_field_visible (is_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS base_schema_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_type VARCHAR(60) NOT NULL,
    module_key VARCHAR(80) NOT NULL,
    field_id INT NULL,
    payload_json JSON NULL,
    changed_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_base_audit_module (module_key),
    KEY idx_base_audit_action (action_type),
    KEY idx_base_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
