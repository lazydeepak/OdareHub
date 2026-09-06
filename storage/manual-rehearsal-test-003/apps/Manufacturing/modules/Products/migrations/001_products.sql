CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    parts_name VARCHAR(255) NOT NULL,
    parts_number VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_parts_number (parts_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
