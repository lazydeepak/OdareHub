CREATE TABLE IF NOT EXISTS user_dashboard_assignments (
    user_id INT PRIMARY KEY,
    dashboard_type VARCHAR(80) NOT NULL,
    default_app VARCHAR(120) NULL,
    default_landing_page VARCHAR(255) NULL,
    updated_by VARCHAR(190) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_dashboard_type (dashboard_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_operational_scopes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    machine_id INT NULL,
    part_id INT NULL,
    task_type VARCHAR(80) NULL,
    department_code VARCHAR(80) NULL,
    branch_code VARCHAR(80) NULL,
    ownership_role VARCHAR(80) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_by VARCHAR(190) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_scope_user_id (user_id),
    KEY idx_scope_machine_id (machine_id),
    KEY idx_scope_part_id (part_id),
    KEY idx_scope_task_type (task_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_module_visibility (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    module_key VARCHAR(120) NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_by VARCHAR(190) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_module (user_id, module_key),
    KEY idx_user_module_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
