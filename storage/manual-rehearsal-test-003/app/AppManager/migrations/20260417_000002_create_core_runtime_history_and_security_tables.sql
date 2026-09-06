-- Core runtime history and security tables moved from runtime ensureSchema calls to migration ownership.

CREATE TABLE IF NOT EXISTS core_setup_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    target_type VARCHAR(40) NOT NULL,
    target_key VARCHAR(190) NOT NULL,
    action_key VARCHAR(80) NOT NULL,
    status ENUM('pending','running','installed','configured','verified','failed','rolled_back','partial') NOT NULL DEFAULT 'pending',
    rollback_performed TINYINT(1) NOT NULL DEFAULT 0,
    warnings_json LONGTEXT NULL,
    error_text TEXT NULL,
    manual_attention_text TEXT NULL,
    resume_action VARCHAR(80) NULL,
    retry_action VARCHAR(80) NULL,
    repair_action VARCHAR(80) NULL,
    run_meta_json LONGTEXT NULL,
    started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    created_by VARCHAR(190) NULL,
    INDEX idx_setup_runs_target (target_type, target_key),
    INDEX idx_setup_runs_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS core_setup_steps (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    run_id BIGINT NOT NULL,
    step_key VARCHAR(120) NOT NULL,
    step_label VARCHAR(190) NOT NULL,
    step_order INT NOT NULL DEFAULT 0,
    status ENUM('pending','running','installed','configured','verified','failed','rolled_back','partial') NOT NULL DEFAULT 'pending',
    rollback_performed TINYINT(1) NOT NULL DEFAULT 0,
    result_text TEXT NULL,
    error_text TEXT NULL,
    warnings_json LONGTEXT NULL,
    manual_attention_text TEXT NULL,
    step_meta_json LONGTEXT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    finished_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_setup_steps_run (run_id, step_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS core_import_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    suite_key VARCHAR(80) NOT NULL,
    import_type VARCHAR(120) NOT NULL,
    source_file VARCHAR(255) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'previewed',
    preview_token VARCHAR(80) NULL,
    valid_rows INT NOT NULL DEFAULT 0,
    invalid_rows INT NOT NULL DEFAULT 0,
    skipped_rows INT NOT NULL DEFAULT 0,
    duplicate_rows INT NOT NULL DEFAULT 0,
    imported_rows INT NOT NULL DEFAULT 0,
    failed_rows INT NOT NULL DEFAULT 0,
    warning_text TEXT NULL,
    error_text TEXT NULL,
    summary_json LONGTEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_import_preview_token (preview_token),
    KEY idx_import_suite_created (suite_key, created_at),
    KEY idx_import_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS core_export_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    target_type VARCHAR(40) NOT NULL,
    target_key VARCHAR(120) NOT NULL,
    suite_key VARCHAR(80) NULL,
    export_type VARCHAR(80) NOT NULL,
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(500) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'completed',
    warning_text TEXT NULL,
    error_text TEXT NULL,
    summary_json LONGTEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_export_target (target_type, target_key),
    KEY idx_export_suite (suite_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS core_release_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    package_name VARCHAR(255) NULL,
    package_path VARCHAR(500) NULL,
    scope_key VARCHAR(80) NOT NULL,
    release_version VARCHAR(80) NOT NULL,
    included_suites_json LONGTEXT NULL,
    included_modules_json LONGTEXT NULL,
    notes_text TEXT NULL,
    preview_token VARCHAR(80) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'previewed',
    warning_text TEXT NULL,
    error_text TEXT NULL,
    summary_json LONGTEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_release_preview_token (preview_token),
    KEY idx_release_created (created_at),
    KEY idx_release_scope (scope_key, release_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS core_restore_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    target_type VARCHAR(40) NOT NULL,
    target_key VARCHAR(120) NOT NULL,
    suite_key VARCHAR(80) NULL,
    restore_type VARCHAR(80) NOT NULL,
    source_backup VARCHAR(255) NULL,
    preview_token VARCHAR(80) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'previewed',
    warning_text TEXT NULL,
    error_text TEXT NULL,
    rollback_attempted TINYINT(1) NOT NULL DEFAULT 0,
    summary_json LONGTEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_restore_preview_token (preview_token),
    KEY idx_restore_suite (suite_key, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS core_environment_portability_runs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    operation_type VARCHAR(40) NOT NULL,
    scope_key VARCHAR(80) NOT NULL,
    source_environment VARCHAR(120) NULL,
    target_environment VARCHAR(120) NULL,
    snapshot_file_name VARCHAR(255) NULL,
    snapshot_file_path VARCHAR(500) NULL,
    preview_token VARCHAR(80) NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'created',
    warning_text TEXT NULL,
    error_text TEXT NULL,
    summary_json LONGTEXT NULL,
    created_by VARCHAR(190) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_env_preview_token (preview_token),
    KEY idx_env_runs_created (created_at),
    KEY idx_env_runs_operation (operation_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS identity_security_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(80) NOT NULL,
    user_id INT NULL,
    email_mask VARCHAR(190) NULL,
    email_hash CHAR(64) NULL,
    ip_hash CHAR(64) NULL,
    outcome VARCHAR(40) NOT NULL,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identity_event_type (event_type, created_at),
    INDEX idx_identity_user (user_id, created_at),
    INDEX idx_identity_email_hash (email_hash, created_at),
    INDEX idx_identity_ip_hash (ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_password_reset_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    request_ip_hash CHAR(64) NULL,
    request_user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_password_reset_token_hash (token_hash),
    KEY idx_password_reset_user (user_id, created_at),
    KEY idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_account_setup_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_account_setup_token_hash (token_hash),
    KEY idx_account_setup_user (user_id, created_at),
    KEY idx_account_setup_expires (expires_at),
    CONSTRAINT fk_account_setup_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_passkeys (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    credential_id VARCHAR(255) NOT NULL,
    public_key LONGTEXT NOT NULL,
    device_name VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL,
    UNIQUE KEY uniq_user_passkey_credential (credential_id),
    KEY idx_user_passkeys_user (user_id, created_at),
    CONSTRAINT fk_user_passkeys_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN auth_session_version INT NOT NULL DEFAULT 1 AFTER password_hash;
ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER auth_session_version;
