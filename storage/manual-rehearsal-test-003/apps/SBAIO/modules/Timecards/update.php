<?php
declare(strict_types=1);

use App\Core\DB;

$dailyColumns = [
    'legacy_name_ref' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN legacy_name_ref VARCHAR(220) NULL AFTER staff_id",
    'actual_break_start_at' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN actual_break_start_at DATETIME NULL AFTER actual_end_at",
    'actual_break_end_at' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN actual_break_end_at DATETIME NULL AFTER actual_break_start_at",
    'scheduled_minutes' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN scheduled_minutes INT NOT NULL DEFAULT 0 AFTER worked_minutes",
    'overtime_minutes' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN overtime_minutes INT NOT NULL DEFAULT 0 AFTER scheduled_minutes",
    'attendance_marker' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN attendance_marker VARCHAR(40) NULL AFTER day_status",
    'salary_type' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN salary_type VARCHAR(60) NULL AFTER exception_status",
    'salary_rate' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN salary_rate DECIMAL(12,2) NULL AFTER salary_type",
    'daily_salary_amount' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN daily_salary_amount DECIMAL(12,2) NULL AFTER salary_rate",
    'month_label' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN month_label VARCHAR(40) NULL AFTER daily_salary_amount",
    'weekday_label' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN weekday_label VARCHAR(40) NULL AFTER month_label",
    'helper_flag' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN helper_flag TINYINT(1) NOT NULL DEFAULT 0 AFTER weekday_label",
    'leave_type' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN leave_type VARCHAR(60) NULL AFTER helper_flag",
    'holiday_name' => "ALTER TABLE sbaio_timecards_daily ADD COLUMN holiday_name VARCHAR(190) NULL AFTER leave_type",
];

foreach ($dailyColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_timecards_daily', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}

$periodColumns = [
    'legacy_name_ref' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN legacy_name_ref VARCHAR(220) NULL AFTER staff_id",
    'total_worked_hours' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN total_worked_hours DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER total_worked_minutes",
    'overtime_minutes' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN overtime_minutes INT NOT NULL DEFAULT 0 AFTER total_worked_hours",
    'workday_count' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN workday_count INT NOT NULL DEFAULT 0 AFTER total_worked_hours",
    'holiday_days' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN holiday_days DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER leave_days",
    'salary_type' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN salary_type VARCHAR(60) NULL AFTER exception_count",
    'salary_rate' => "ALTER TABLE sbaio_timecard_periods ADD COLUMN salary_rate DECIMAL(12,2) NULL AFTER salary_type",
];

foreach ($periodColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_timecard_periods', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}
