<?php
declare(strict_types=1);

use App\Core\DB;

$leaveColumns = [
    'legacy_name_ref' => "ALTER TABLE sbaio_leave_requests ADD COLUMN legacy_name_ref VARCHAR(220) NULL AFTER staff_id",
    'leave_code' => "ALTER TABLE sbaio_leave_requests ADD COLUMN leave_code VARCHAR(60) NULL AFTER legacy_name_ref",
    'partial_day_unit' => "ALTER TABLE sbaio_leave_requests ADD COLUMN partial_day_unit VARCHAR(20) NULL AFTER end_date",
];

foreach ($leaveColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_leave_requests', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}

$holidayColumns = [
    'holiday_code' => "ALTER TABLE sbaio_holiday_calendar ADD COLUMN holiday_code VARCHAR(60) NULL AFTER holiday_type",
    'is_closed_day' => "ALTER TABLE sbaio_holiday_calendar ADD COLUMN is_closed_day TINYINT(1) NOT NULL DEFAULT 0 AFTER holiday_code",
    'notes' => "ALTER TABLE sbaio_holiday_calendar ADD COLUMN notes TEXT NULL AFTER is_closed_day",
];

foreach ($holidayColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_holiday_calendar', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}
