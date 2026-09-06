<?php
declare(strict_types=1);

use App\Core\DB;

$columns = [
    'notice_type' => "ALTER TABLE sbaio_notices ADD COLUMN notice_type VARCHAR(80) NULL AFTER body",
    'target_period' => "ALTER TABLE sbaio_notices ADD COLUMN target_period VARCHAR(40) NULL AFTER notice_type",
    'related_staff_id' => "ALTER TABLE sbaio_notices ADD COLUMN related_staff_id INT NULL AFTER target_period",
    'requires_ack' => "ALTER TABLE sbaio_notices ADD COLUMN requires_ack TINYINT(1) NOT NULL DEFAULT 0 AFTER related_staff_id",
];

foreach ($columns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_notices', $column]
    );
    if ($exists !== null) {
        continue;
    }
    DB::query($sql);
}
