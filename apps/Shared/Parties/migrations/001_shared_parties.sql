CREATE TABLE IF NOT EXISTS shared_parties (
    party_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(255) NULL UNIQUE,
    name VARCHAR(500) NOT NULL,
    type ENUM('person','organization') NOT NULL DEFAULT 'person',
    status ENUM('draft','active','deprecated','archived') NOT NULL DEFAULT 'draft',
    category_ref VARCHAR(120) NULL,
    metadata_ref VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_shared_parties_status (status),
    INDEX idx_shared_parties_reference (reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
