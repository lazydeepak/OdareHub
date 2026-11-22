CREATE TABLE IF NOT EXISTS shared_items (
    item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code_ref VARCHAR(255) NOT NULL,
    name VARCHAR(500) NOT NULL,
    status ENUM('draft','active','deprecated','archived') NOT NULL DEFAULT 'draft',
    category_ref VARCHAR(120) NULL,
    metadata_ref VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_shared_items_code_ref (code_ref),
    KEY idx_shared_items_status (status),
    KEY idx_shared_items_category_ref (category_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
