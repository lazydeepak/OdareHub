<?php
declare(strict_types=1);

use App\Core\DB;

$runColumns = [
    'period_year' => "ALTER TABLE sbaio_payroll_runs ADD COLUMN period_year INT NULL AFTER period_end",
    'period_month' => "ALTER TABLE sbaio_payroll_runs ADD COLUMN period_month INT NULL AFTER period_year",
    'period_month_label' => "ALTER TABLE sbaio_payroll_runs ADD COLUMN period_month_label VARCHAR(40) NULL AFTER period_month",
    'payslip_variant' => "ALTER TABLE sbaio_payroll_runs ADD COLUMN payslip_variant VARCHAR(40) NOT NULL DEFAULT 'mixed' AFTER period_month_label",
];

foreach ($runColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_payroll_runs', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}

$recordColumns = [
    'legacy_name_ref' => "ALTER TABLE sbaio_payroll_records ADD COLUMN legacy_name_ref VARCHAR(220) NULL AFTER staff_id",
    'salary_reference_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN salary_reference_amount DECIMAL(12,2) NULL AFTER salary_rate",
    'payslip_variant' => "ALTER TABLE sbaio_payroll_records ADD COLUMN payslip_variant VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER salary_reference_amount",
    'workday_count' => "ALTER TABLE sbaio_payroll_records ADD COLUMN workday_count INT NOT NULL DEFAULT 0 AFTER worked_days",
    'worked_hours' => "ALTER TABLE sbaio_payroll_records ADD COLUMN worked_hours DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER worked_minutes",
    'holiday_days' => "ALTER TABLE sbaio_payroll_records ADD COLUMN holiday_days DECIMAL(8,2) NOT NULL DEFAULT 0 AFTER leave_days",
    'overtime_minutes' => "ALTER TABLE sbaio_payroll_records ADD COLUMN overtime_minutes INT NOT NULL DEFAULT 0 AFTER holiday_work_days",
    'health_insurance_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN health_insurance_amount DECIMAL(12,2) NULL AFTER overtime_minutes",
    'pension_insurance_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN pension_insurance_amount DECIMAL(12,2) NULL AFTER health_insurance_amount",
    'employment_insurance_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN employment_insurance_amount DECIMAL(12,2) NULL AFTER pension_insurance_amount",
    'social_insurance_total' => "ALTER TABLE sbaio_payroll_records ADD COLUMN social_insurance_total DECIMAL(12,2) NULL AFTER employment_insurance_amount",
    'taxable_income_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN taxable_income_amount DECIMAL(12,2) NULL AFTER social_insurance_total",
    'income_tax_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN income_tax_amount DECIMAL(12,2) NULL AFTER taxable_income_amount",
    'basic_insurance_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN basic_insurance_amount DECIMAL(12,2) NULL AFTER income_tax_amount",
    'specified_insurance_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN specified_insurance_amount DECIMAL(12,2) NULL AFTER basic_insurance_amount",
    'long_term_care_insurance_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN long_term_care_insurance_amount DECIMAL(12,2) NULL AFTER specified_insurance_amount",
    'fixed_tax_reduction_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN fixed_tax_reduction_amount DECIMAL(12,2) NULL AFTER long_term_care_insurance_amount",
    'total_deductions_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN total_deductions_amount DECIMAL(12,2) NULL AFTER fixed_tax_reduction_amount",
    'cash_payment_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN cash_payment_amount DECIMAL(12,2) NULL AFTER total_deductions_amount",
    'dependents_count' => "ALTER TABLE sbaio_payroll_records ADD COLUMN dependents_count INT NULL AFTER cash_payment_amount",
    'base_salary_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN base_salary_amount DECIMAL(12,2) NULL AFTER overtime_minutes",
    'hourly_pay_amount' => "ALTER TABLE sbaio_payroll_records ADD COLUMN hourly_pay_amount DECIMAL(12,2) NULL AFTER base_salary_amount",
    'payslip_working_days_label' => "ALTER TABLE sbaio_payroll_records ADD COLUMN payslip_working_days_label VARCHAR(120) NULL AFTER net_amount",
    'payslip_working_time_label' => "ALTER TABLE sbaio_payroll_records ADD COLUMN payslip_working_time_label VARCHAR(120) NULL AFTER payslip_working_days_label",
];

foreach ($recordColumns as $column => $sql) {
    $exists = DB::fetchOne(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        ['sbaio_payroll_records', $column]
    );
    if ($exists === null) {
        DB::query($sql);
    }
}

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_salary_monthly_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        salary_type VARCHAR(60) NULL,
        period_year INT NOT NULL,
        period_month INT NOT NULL,
        reference_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        source_label VARCHAR(80) NOT NULL DEFAULT 'legacy_salary_sheet',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_salary_monthly_rates_staff_period (staff_id, period_year, period_month),
        KEY idx_sbaio_salary_monthly_rates_name_period (legacy_name_ref(120), period_year, period_month)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_payroll_fixed_profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        basic_salary_amount DECIMAL(12,2) NULL,
        taxable_earnings_amount DECIMAL(12,2) NULL,
        social_ins_subject_amount DECIMAL(12,2) NULL,
        actual_gross_pay_amount DECIMAL(12,2) NULL,
        gross_pay_amount DECIMAL(12,2) NULL,
        health_insurance_amount DECIMAL(12,2) NULL,
        pension_insurance_amount DECIMAL(12,2) NULL,
        employment_insurance_amount DECIMAL(12,2) NULL,
        social_insurance_total DECIMAL(12,2) NULL,
        taxable_income_amount DECIMAL(12,2) NULL,
        income_tax_amount DECIMAL(12,2) NULL,
        basic_insurance_amount DECIMAL(12,2) NULL,
        specified_insurance_amount DECIMAL(12,2) NULL,
        long_term_care_insurance_amount DECIMAL(12,2) NULL,
        fixed_tax_reduction_amount DECIMAL(12,2) NULL,
        total_deductions_amount DECIMAL(12,2) NULL,
        cash_payment_amount DECIMAL(12,2) NULL,
        net_pay_amount DECIMAL(12,2) NULL,
        dependents_count INT NULL,
        source_label VARCHAR(80) NOT NULL DEFAULT 'legacy_fulltime_rates',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_payroll_fixed_profiles_staff (staff_id),
        KEY idx_sbaio_payroll_fixed_profiles_name (legacy_name_ref(120))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);
