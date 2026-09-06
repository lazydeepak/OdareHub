<?php
declare(strict_types=1);

use App\Core\DB;

$templateColumns = [
    'day_of_week' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN day_of_week VARCHAR(20) NULL AFTER shift_code",
    'range_start_date' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN range_start_date DATE NULL AFTER day_of_week",
    'range_end_date' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN range_end_date DATE NULL AFTER range_start_date",
    'break_start_time' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN break_start_time TIME NULL AFTER expected_end_time",
    'break_end_time' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN break_end_time TIME NULL AFTER break_start_time",
    'job_label' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN job_label VARCHAR(120) NULL AFTER expected_break_minutes",
    'scheduled_minutes' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN scheduled_minutes INT NOT NULL DEFAULT 0 AFTER job_label",
    'legacy_name_ref' => "ALTER TABLE sbaio_schedule_templates ADD COLUMN legacy_name_ref VARCHAR(220) NULL AFTER scheduled_minutes",
];

foreach ($templateColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_schedule_templates', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}

$assignmentColumns = [
    'legacy_name_ref' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN legacy_name_ref VARCHAR(220) NULL AFTER staff_id",
    'break_start_time' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN break_start_time TIME NULL AFTER expected_end_time",
    'break_end_time' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN break_end_time TIME NULL AFTER break_start_time",
    'day_of_week' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN day_of_week VARCHAR(20) NULL AFTER expected_break_minutes",
    'range_start_date' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN range_start_date DATE NULL AFTER day_of_week",
    'range_end_date' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN range_end_date DATE NULL AFTER range_start_date",
    'job_label' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN job_label VARCHAR(120) NULL AFTER range_end_date",
    'scheduled_minutes' => "ALTER TABLE sbaio_schedule_assignments ADD COLUMN scheduled_minutes INT NOT NULL DEFAULT 0 AFTER job_label",
];

foreach ($assignmentColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_schedule_assignments', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}
