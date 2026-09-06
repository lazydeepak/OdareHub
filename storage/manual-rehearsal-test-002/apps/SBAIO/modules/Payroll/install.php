<?php
declare(strict_types=1);

use App\Core\DB;

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_payroll_runs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        period_key VARCHAR(20) NOT NULL,
        period_start DATE NOT NULL,
        period_end DATE NOT NULL,
        period_year INT NULL,
        period_month INT NULL,
        period_month_label VARCHAR(40) NULL,
        payslip_variant VARCHAR(40) NOT NULL DEFAULT 'mixed',
        run_status VARCHAR(40) NOT NULL DEFAULT 'draft',
        approval_status VARCHAR(40) NOT NULL DEFAULT 'draft',
        locked_at DATETIME NULL,
        generated_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_sbaio_payroll_runs_period (period_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

DB::query(
    "CREATE TABLE IF NOT EXISTS sbaio_payroll_records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payroll_run_id INT NULL,
        staff_id INT NULL,
        legacy_name_ref VARCHAR(220) NULL,
        salary_type VARCHAR(60) NULL,
        salary_rate DECIMAL(12,2) NULL,
        salary_reference_amount DECIMAL(12,2) NULL,
        payslip_variant VARCHAR(40) NOT NULL DEFAULT 'standard',
        worked_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        workday_count INT NOT NULL DEFAULT 0,
        worked_minutes INT NOT NULL DEFAULT 0,
        worked_hours DECIMAL(12,2) NOT NULL DEFAULT 0,
        leave_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        holiday_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        holiday_work_days DECIMAL(8,2) NOT NULL DEFAULT 0,
        overtime_minutes INT NOT NULL DEFAULT 0,
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
        dependents_count INT NULL,
        base_salary_amount DECIMAL(12,2) NULL,
        hourly_pay_amount DECIMAL(12,2) NULL,
        gross_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        net_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        payslip_working_days_label VARCHAR(120) NULL,
        payslip_working_time_label VARCHAR(120) NULL,
        calc_notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_sbaio_payroll_records_run (payroll_run_id),
        KEY idx_sbaio_payroll_records_staff (staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

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

require __DIR__ . '/update.php';
