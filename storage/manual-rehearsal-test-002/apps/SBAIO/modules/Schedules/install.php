<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_schedule_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        template_name VARCHAR(120) NOT NULL,
        shift_code VARCHAR(40) NULL,
        day_of_week VARCHAR(20) NULL,
        range_start_date DATE NULL,
        range_end_date DATE NULL,
        expected_start_time TIME NULL,
        expected_end_time TIME NULL,
        break_start_time TIME NULL,
        break_end_time TIME NULL,
        expected_break_minutes INT NOT NULL DEFAULT 0,
        job_label VARCHAR(120) NULL,
        scheduled_minutes INT NOT NULL DEFAULT 0,
        legacy_name_ref VARCHAR(220) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_schedule_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        schedule_date DATE NOT NULL,
        template_id INT NULL,
        expected_start_time TIME NULL,
        expected_end_time TIME NULL,
        break_start_time TIME NULL,
        break_end_time TIME NULL,
        expected_break_minutes INT NOT NULL DEFAULT 0,
        day_of_week VARCHAR(20) NULL,
        range_start_date DATE NULL,
        range_end_date DATE NULL,
        job_label VARCHAR(120) NULL,
        scheduled_minutes INT NOT NULL DEFAULT 0,
        assignment_status VARCHAR(40) NOT NULL DEFAULT 'planned',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_schedule_assignment_staff_date (staff_id, schedule_date),
        KEY idx_sbaio_schedule_assignments_date (schedule_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
