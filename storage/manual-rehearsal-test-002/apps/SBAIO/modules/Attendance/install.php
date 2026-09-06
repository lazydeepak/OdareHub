<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_attendance_daily (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        attendance_date DATE NOT NULL,
        work_start_at DATETIME NULL,
        work_end_at DATETIME NULL,
        break_start_at DATETIME NULL,
        break_end_at DATETIME NULL,
        day_marker VARCHAR(40) NOT NULL DEFAULT 'workday',
        source_label VARCHAR(80) NOT NULL DEFAULT 'manual',
        is_manual_correction TINYINT(1) NOT NULL DEFAULT 0,
        correction_note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_attendance_staff_date (staff_id, attendance_date),
        KEY idx_sbaio_attendance_date (attendance_date),
        KEY idx_sbaio_attendance_marker (day_marker)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
