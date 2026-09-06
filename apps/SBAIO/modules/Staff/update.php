<?php
declare(strict_types=1);

use App\Core\DB;

$columns = [
    'legacy_sn' => "ALTER TABLE sbaio_staff ADD COLUMN legacy_sn VARCHAR(40) NULL AFTER id",
    'employee_code' => "ALTER TABLE sbaio_staff ADD COLUMN employee_code VARCHAR(60) NULL AFTER id",
    'name_ref' => "ALTER TABLE sbaio_staff ADD COLUMN name_ref VARCHAR(220) NULL AFTER full_name",
    'staff_type' => "ALTER TABLE sbaio_staff ADD COLUMN staff_type VARCHAR(80) NULL AFTER role_label",
    'salary_type' => "ALTER TABLE sbaio_staff ADD COLUMN salary_type VARCHAR(60) NULL AFTER staff_type",
    'salary_rate' => "ALTER TABLE sbaio_staff ADD COLUMN salary_rate DECIMAL(12,2) NULL AFTER salary_type",
    'helper_classification' => "ALTER TABLE sbaio_staff ADD COLUMN helper_classification VARCHAR(80) NULL AFTER salary_rate",
    'nationality' => "ALTER TABLE sbaio_staff ADD COLUMN nationality VARCHAR(80) NULL AFTER helper_classification",
    'gender' => "ALTER TABLE sbaio_staff ADD COLUMN gender VARCHAR(40) NULL AFTER nationality",
    'date_of_birth' => "ALTER TABLE sbaio_staff ADD COLUMN date_of_birth DATE NULL AFTER gender",
    'employment_status' => "ALTER TABLE sbaio_staff ADD COLUMN employment_status VARCHAR(40) NOT NULL DEFAULT 'active' AFTER helper_classification",
    'department_name' => "ALTER TABLE sbaio_staff ADD COLUMN department_name VARCHAR(120) NULL AFTER employment_status",
    'branch_name' => "ALTER TABLE sbaio_staff ADD COLUMN branch_name VARCHAR(120) NULL AFTER department_name",
    'hire_date' => "ALTER TABLE sbaio_staff ADD COLUMN hire_date DATE NULL AFTER branch_name",
    'join_date' => "ALTER TABLE sbaio_staff ADD COLUMN join_date DATE NULL AFTER hire_date",
    'exit_date' => "ALTER TABLE sbaio_staff ADD COLUMN exit_date DATE NULL AFTER join_date",
    'legacy_job_details' => "ALTER TABLE sbaio_staff ADD COLUMN legacy_job_details VARCHAR(190) NULL AFTER exit_date",
    'residence_card_front' => "ALTER TABLE sbaio_staff ADD COLUMN residence_card_front VARCHAR(255) NULL AFTER legacy_job_details",
    'residence_card_back' => "ALTER TABLE sbaio_staff ADD COLUMN residence_card_back VARCHAR(255) NULL AFTER residence_card_front",
];

foreach ($columns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_staff', $column]
    );
    if ($exists !== null) {
        continue;
    }
    DB::query($sql);
}
