<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-CSV-ADMISSION-001 probe.
 *
 * Verifies the existing SBAIO app/report admission chain and the explicit
 * CSV projections for all module-owned report sources. It reads source and
 * manifest files only; it never bootstraps the application or connects to a
 * configured database.
 */

$root = dirname(__DIR__, 4);
$sbaioRoot = $root . '/apps/SBAIO';
$registryPath = $sbaioRoot . '/Services/ModuleReportRegistryService.php';
$registry = file_get_contents($registryPath) ?: '';

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

$sources = [
    'attendance' => ['key' => 'sbaio.attendance.overview', 'table' => 'sbaio_attendance_daily', 'columns' => ['id', 'staff_id', 'attendance_date', 'work_start_at', 'work_end_at', 'break_start_at', 'break_end_at', 'day_marker', 'source_label', 'is_manual_correction', 'correction_note', 'created_at', 'updated_at']],
    'customers' => ['key' => 'sbaio.customers.overview', 'table' => 'sbaio_customers', 'columns' => ['id', 'customer_name', 'contact_name', 'email', 'phone', 'status', 'created_at', 'updated_at']],
    'expenses' => ['key' => 'sbaio.expenses.overview', 'table' => 'sbaio_expenses', 'columns' => ['id', 'expense_ref', 'category_name', 'amount', 'expense_status', 'expense_date', 'created_at', 'updated_at']],
    'leave' => ['key' => 'sbaio.leave.overview', 'table' => 'sbaio_leave_requests', 'columns' => ['id', 'staff_id', 'legacy_name_ref', 'leave_code', 'leave_type', 'start_date', 'end_date', 'partial_day_unit', 'leave_status', 'notes', 'approved_by', 'approved_at', 'created_at', 'updated_at']],
    'notices' => ['key' => 'sbaio.notices.overview', 'table' => 'sbaio_notices', 'columns' => ['id', 'title', 'notice_type', 'target_period', 'requires_ack', 'notice_level', 'is_active', 'created_at', 'updated_at']],
    'payroll' => ['key' => 'sbaio.payroll.overview', 'table' => 'sbaio_payroll_records', 'columns' => ['id', 'payroll_run_id', 'staff_id', 'legacy_name_ref', 'salary_type', 'salary_rate', 'salary_reference_amount', 'payslip_variant', 'worked_days', 'workday_count', 'worked_minutes', 'worked_hours', 'leave_days', 'holiday_days', 'holiday_work_days', 'overtime_minutes', 'health_insurance_amount', 'pension_insurance_amount', 'employment_insurance_amount', 'social_insurance_total', 'taxable_income_amount', 'income_tax_amount', 'basic_insurance_amount', 'specified_insurance_amount', 'long_term_care_insurance_amount', 'fixed_tax_reduction_amount', 'total_deductions_amount', 'cash_payment_amount', 'dependents_count', 'base_salary_amount', 'hourly_pay_amount', 'gross_amount', 'net_amount', 'payslip_working_days_label', 'payslip_working_time_label', 'calc_notes', 'created_at', 'updated_at']],
    'sales' => ['key' => 'sbaio.sales.overview', 'table' => 'sbaio_sales', 'columns' => ['id', 'sale_ref', 'customer_name', 'amount', 'sale_status', 'sale_date', 'created_at', 'updated_at']],
    'schedules' => ['key' => 'sbaio.schedules.overview', 'table' => 'sbaio_schedule_assignments', 'columns' => ['id', 'staff_id', 'legacy_name_ref', 'schedule_date', 'template_id', 'expected_start_time', 'expected_end_time', 'break_start_time', 'break_end_time', 'expected_break_minutes', 'day_of_week', 'range_start_date', 'range_end_date', 'job_label', 'scheduled_minutes', 'assignment_status', 'created_at', 'updated_at']],
    'staff' => ['key' => 'sbaio.staff.overview', 'table' => 'sbaio_staff', 'columns' => ['id', 'legacy_sn', 'employee_code', 'full_name', 'name_ref', 'email', 'role_label', 'staff_type', 'salary_type', 'salary_rate', 'helper_classification', 'nationality', 'gender', 'date_of_birth', 'employment_status', 'department_name', 'branch_name', 'hire_date', 'join_date', 'exit_date', 'legacy_job_details', 'residence_card_front', 'residence_card_back', 'status', 'created_at', 'updated_at']],
    'tasks' => ['key' => 'sbaio.tasks.overview', 'table' => 'sbaio_tasks', 'columns' => ['id', 'task_title', 'owner_name', 'task_type', 'task_bucket', 'related_date', 'task_status', 'priority', 'due_date', 'created_at', 'updated_at']],
    'timecards' => ['key' => 'sbaio.timecards.overview', 'table' => 'sbaio_timecards_daily', 'columns' => ['id', 'staff_id', 'legacy_name_ref', 'work_date', 'attendance_row_id', 'scheduled_start_at', 'scheduled_end_at', 'actual_start_at', 'actual_end_at', 'actual_break_start_at', 'actual_break_end_at', 'break_minutes', 'worked_minutes', 'scheduled_minutes', 'overtime_minutes', 'day_status', 'attendance_marker', 'exception_status', 'salary_type', 'salary_rate', 'daily_salary_amount', 'month_label', 'weekday_label', 'helper_flag', 'leave_type', 'holiday_name', 'approval_status', 'generated_at', 'approved_at', 'created_at', 'updated_at']],
];

$assert(count($sources) === 11, 'all eleven SBAIO report sources are covered');
$assert(str_contains($registry, 'self::findActiveReport($reportKey)') && strpos($registry, 'self::findActiveReport($reportKey)') < strpos($registry, 'CSV_REPORT_SOURCES[$reportKey]'), 'active report admission precedes CSV source lookup');
$assert(str_contains($registry, 'CSV projection is not defined.') && !str_contains($registry, "SELECT *") && !str_contains($registry, 'SHOW COLUMNS'), 'CSV registry has no unrestricted or schema-discovery fallback');
$assert(str_contains($registry, '$select = implode(", ", $columns);') || str_contains($registry, '$select = implode(\', \', $columns);'), 'CSV SQL is assembled only from validated explicit columns');

foreach ($sources as $module => $source) {
    $assert(str_contains($registry, "'{$source['key']}'") && str_contains($registry, "'table' => '{$source['table']}'"), "{$module} report source is registered");
    foreach ($source['columns'] as $column) {
        $assert(str_contains($registry, "'{$column}'"), "{$module} projection declares {$column}");
    }

    $routePath = $sbaioRoot . '/modules/' . ucfirst($module) . '/routes.php';
    $route = file_get_contents($routePath) ?: '';
    $exportMarker = "'/apps/sbaio/{$module}/export'";
    $exportOffset = strpos($route, $exportMarker);
    $exportRoute = $exportOffset === false ? '' : substr($route, $exportOffset);
    $authOffset = strpos($exportRoute, "Auth::requireAppAccess('sbaio')");
    $streamOffset = strpos($exportRoute, 'streamCsvForReport($reportKey)');
    $reportOffset = strpos($exportRoute, 'findActiveReport($reportKey)');
    $assert($exportOffset !== false, "{$module} CSV route exists");
    $hasExistingAuthorization = $authOffset !== false || strpos($exportRoute, '::requireAdministrator()') !== false;
    $authOffset = $authOffset === false ? strpos($exportRoute, '::requireAdministrator()') : $authOffset;
    $assert($hasExistingAuthorization && $streamOffset !== false && $authOffset < $streamOffset, "{$module} route authorizes before CSV dispatch");
    $assert($reportOffset !== false && $streamOffset < $reportOffset, "{$module} non-CSV report lookup remains after CSV branch");

    $manifest = json_decode((string)file_get_contents($sbaioRoot . '/modules/' . ucfirst($module) . '/plugin.json'), true);
    $report = is_array($manifest) ? ($manifest['reports'][0] ?? []) : [];
    $assert(($report['report_key'] ?? null) === $source['key'] && ($report['permission'] ?? null) === 'app.sbaio.access' && ($report['lifecycle'] ?? null) === 'active_only', "{$module} manifest preserves existing report admission contract");
}

$assert(!str_contains($registry, "'body'") && !str_contains($registry, "'related_staff_id'"), 'Task and Notice privacy exclusions remain absent from CSV projections');

echo 'CSV admission/projection probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
