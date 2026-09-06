<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_leave_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        leave_code VARCHAR(60) NULL,
        leave_type VARCHAR(60) NOT NULL DEFAULT 'leave',
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        partial_day_unit VARCHAR(20) NULL,
        leave_status VARCHAR(40) NOT NULL DEFAULT 'draft',
        notes TEXT NULL,
        approved_by VARCHAR(190) NULL,
        approved_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_sbaio_leave_requests_dates (start_date, end_date),
        KEY idx_sbaio_leave_requests_status (leave_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_holiday_calendar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        holiday_date DATE NOT NULL,
        holiday_name VARCHAR(190) NOT NULL,
        holiday_type VARCHAR(60) NOT NULL DEFAULT 'public_holiday',
        holiday_code VARCHAR(60) NULL,
        is_closed_day TINYINT(1) NOT NULL DEFAULT 0,
        notes TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_holiday_date (holiday_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
