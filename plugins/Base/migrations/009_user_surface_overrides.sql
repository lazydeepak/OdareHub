CREATE TABLE IF NOT EXISTS user_surface_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    override_field VARCHAR(64) NOT NULL,
    raw_value LONGTEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_field (user_id, override_field),
    KEY idx_field (override_field)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
