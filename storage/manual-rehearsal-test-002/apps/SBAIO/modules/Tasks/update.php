<?php
declare(strict_types=1);

use App\Core\DB;

$columns = [
    'task_type' => "ALTER TABLE sbaio_tasks ADD COLUMN task_type VARCHAR(80) NULL AFTER owner_name",
    'task_bucket' => "ALTER TABLE sbaio_tasks ADD COLUMN task_bucket VARCHAR(80) NULL AFTER task_type",
    'related_staff_id' => "ALTER TABLE sbaio_tasks ADD COLUMN related_staff_id INT NULL AFTER task_bucket",
    'related_date' => "ALTER TABLE sbaio_tasks ADD COLUMN related_date DATE NULL AFTER related_staff_id",
];

foreach ($columns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_tasks', $column]
    );
    if ($exists !== null) {
        continue;
    }
    DB::query($sql);
}
