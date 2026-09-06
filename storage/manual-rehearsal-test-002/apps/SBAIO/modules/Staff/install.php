<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_staff (
        id INT AUTO_INCREMENT PRIMARY KEY,
        legacy_sn VARCHAR(40) NULL,
        employee_code VARCHAR(60) NULL,
        full_name VARCHAR(190) NOT NULL,
        name_ref VARCHAR(220) NULL,
        email VARCHAR(190) NULL,
        role_label VARCHAR(120) NULL,
        staff_type VARCHAR(80) NULL,
        salary_type VARCHAR(60) NULL,
        salary_rate DECIMAL(12,2) NULL,
        helper_classification VARCHAR(80) NULL,
        nationality VARCHAR(80) NULL,
        gender VARCHAR(40) NULL,
        date_of_birth DATE NULL,
        employment_status VARCHAR(40) NOT NULL DEFAULT 'active',
        department_name VARCHAR(120) NULL,
        branch_name VARCHAR(120) NULL,
        hire_date DATE NULL,
        join_date DATE NULL,
        exit_date DATE NULL,
        legacy_job_details VARCHAR(190) NULL,
        residence_card_front VARCHAR(255) NULL,
        residence_card_back VARCHAR(255) NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
