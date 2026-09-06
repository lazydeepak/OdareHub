-- ACL plugin runtime-owned tables must be migration-owned for live upgrades.

CREATE TABLE IF NOT EXISTS acl_role_permission_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    perm_key VARCHAR(150) NOT NULL,
    state ENUM('allow','deny') NOT NULL,
    reason_text VARCHAR(255) NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_acl_override (role, perm_key),
    KEY idx_acl_override_role (role),
    KEY idx_acl_override_perm (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS acl_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_user_id INT NULL,
    actor_email VARCHAR(190) NULL,
    actor_role VARCHAR(80) NULL,
    target_role VARCHAR(80) NOT NULL,
    perm_key VARCHAR(150) NOT NULL,
    previous_state VARCHAR(40) NOT NULL,
    new_state VARCHAR(40) NOT NULL,
    reason_text VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_acl_audit_created (created_at),
    KEY idx_acl_audit_role (target_role),
    KEY idx_acl_audit_perm (perm_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
