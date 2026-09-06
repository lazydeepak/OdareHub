CREATE TABLE IF NOT EXISTS machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_no VARCHAR(100) NOT NULL,
    machine_name VARCHAR(190) NOT NULL,
    section VARCHAR(120) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Available',
    capacity_per_hour DECIMAL(12,2) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_machine_no (machine_no),
    KEY idx_machines_status (status),
    KEY idx_machines_section (section),
    KEY idx_machines_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
