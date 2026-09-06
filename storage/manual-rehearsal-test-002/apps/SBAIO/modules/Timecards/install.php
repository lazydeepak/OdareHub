<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_timecards_daily (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        work_date DATE NOT NULL,
        attendance_row_id INT NULL,
        scheduled_start_at DATETIME NULL,
        scheduled_end_at DATETIME NULL,
        actual_start_at DATETIME NULL,
        actual_end_at DATETIME NULL,
        actual_break_start_at DATETIME NULL,
        actual_break_end_at DATETIME NULL,
        break_minutes INT NOT NULL DEFAULT 0,
        worked_minutes INT NOT NULL DEFAULT 0,
        scheduled_minutes INT NOT NULL DEFAULT 0,
        overtime_minutes INT NOT NULL DEFAULT 0,
        day_status VARCHAR(40) NOT NULL DEFAULT 'workday',
        attendance_marker VARCHAR(40) NULL,
        exception_status VARCHAR(40) NOT NULL DEFAULT 'normal',
        salary_type VARCHAR(60) NULL,
        salary_rate DECIMAL(12,2) NULL,
        daily_salary_amount DECIMAL(12,2) NULL,
        month_label VARCHAR(40) NULL,
        weekday_label VARCHAR(40) NULL,
        helper_flag TINYINT(1) NOT NULL DEFAULT 0,
        leave_type VARCHAR(60) NULL,
        holiday_name VARCHAR(190) NULL,
        approval_status VARCHAR(40) NOT NULL DEFAULT 'draft',
        generated_at DATETIME NULL,
        approved_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_timecards_staff_date (staff_id, work_date),
        KEY idx_sbaio_timecards_work_date (work_date),
        KEY idx_sbaio_timecards_approval (approval_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_timecard_periods (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        period_key VARCHAR(20) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        total_days INT NOT NULL DEFAULT 0,
        total_worked_minutes INT NOT NULL DEFAULT 0,
        total_worked_hours DECIMAL(12,2) NOT NULL DEFAULT 0,
        overtime_minutes INT NOT NULL DEFAULT 0,
        workday_count INT NOT NULL DEFAULT 0,
        leave_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        holiday_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        holiday_work_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        exception_count INT NOT NULL DEFAULT 0,
        salary_type VARCHAR(60) NULL,
        salary_rate DECIMAL(12,2) NULL,
        approval_status VARCHAR(40) NOT NULL DEFAULT 'draft',
        locked_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_timecard_periods_staff_period (staff_id, period_key),
        KEY idx_sbaio_timecard_periods_period (period_start, period_end)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

require __DIR__ . '/update.php';
