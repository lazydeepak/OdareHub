<?php
declare(strict_types=1);

namespace Apps\SBAIO\Services;

use App\Core\DB;
use App\Services\ExportHistoryService;

final class ModuleReportRegistryService
{
    /**
     * @var array<string,array{table:string,order_by:string,direction:string,filename_prefix:string,columns?:list<string>}>
     */
    private const CSV_REPORT_SOURCES = [
        'sbaio.attendance.overview' => [
            'table' => 'sbaio_attendance_daily', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-attendance',
            'columns' => ['id', 'staff_id', 'attendance_date', 'work_start_at', 'work_end_at', 'break_start_at', 'break_end_at', 'day_marker', 'source_label', 'is_manual_correction', 'correction_note', 'created_at', 'updated_at'],
        ],
        'sbaio.customers.overview' => [
            'table' => 'sbaio_customers', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-customers',
            'columns' => ['id', 'customer_name', 'contact_name', 'email', 'phone', 'status', 'created_at', 'updated_at'],
        ],
        'sbaio.expenses.overview' => [
            'table' => 'sbaio_expenses', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-expenses',
            'columns' => ['id', 'expense_ref', 'category_name', 'amount', 'expense_status', 'expense_date', 'created_at', 'updated_at'],
        ],
        'sbaio.leave.overview' => [
            'table' => 'sbaio_leave_requests', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-leave',
            'columns' => ['id', 'staff_id', 'legacy_name_ref', 'leave_code', 'leave_type', 'start_date', 'end_date', 'partial_day_unit', 'leave_status', 'notes', 'approved_by', 'approved_at', 'created_at', 'updated_at'],
        ],
        'sbaio.notices.overview' => [
            'table' => 'sbaio_notices',
            'order_by' => 'id',
            'direction' => 'DESC',
            'filename_prefix' => 'sbaio-notices',
            'columns' => ['id', 'title', 'notice_type', 'target_period', 'requires_ack', 'notice_level', 'is_active', 'created_at', 'updated_at'],
        ],
        'sbaio.payroll.overview' => [
            'table' => 'sbaio_payroll_records', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-payroll',
            'columns' => ['id', 'payroll_run_id', 'staff_id', 'legacy_name_ref', 'salary_type', 'salary_rate', 'salary_reference_amount', 'payslip_variant', 'worked_days', 'workday_count', 'worked_minutes', 'worked_hours', 'leave_days', 'holiday_days', 'holiday_work_days', 'overtime_minutes', 'health_insurance_amount', 'pension_insurance_amount', 'employment_insurance_amount', 'social_insurance_total', 'taxable_income_amount', 'income_tax_amount', 'basic_insurance_amount', 'specified_insurance_amount', 'long_term_care_insurance_amount', 'fixed_tax_reduction_amount', 'total_deductions_amount', 'cash_payment_amount', 'dependents_count', 'base_salary_amount', 'hourly_pay_amount', 'gross_amount', 'net_amount', 'payslip_working_days_label', 'payslip_working_time_label', 'calc_notes', 'created_at', 'updated_at'],
        ],
        'sbaio.sales.overview' => [
            'table' => 'sbaio_sales', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-sales',
            'columns' => ['id', 'sale_ref', 'customer_name', 'amount', 'sale_status', 'sale_date', 'created_at', 'updated_at'],
        ],
        'sbaio.schedules.overview' => [
            'table' => 'sbaio_schedule_assignments', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-schedules',
            'columns' => ['id', 'staff_id', 'legacy_name_ref', 'schedule_date', 'template_id', 'expected_start_time', 'expected_end_time', 'break_start_time', 'break_end_time', 'expected_break_minutes', 'day_of_week', 'range_start_date', 'range_end_date', 'job_label', 'scheduled_minutes', 'assignment_status', 'created_at', 'updated_at'],
        ],
        'sbaio.staff.overview' => [
            'table' => 'sbaio_staff', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-staff',
            'columns' => ['id', 'legacy_sn', 'employee_code', 'full_name', 'name_ref', 'email', 'role_label', 'staff_type', 'salary_type', 'salary_rate', 'helper_classification', 'nationality', 'gender', 'date_of_birth', 'employment_status', 'department_name', 'branch_name', 'hire_date', 'join_date', 'exit_date', 'legacy_job_details', 'residence_card_front', 'residence_card_back', 'status', 'created_at', 'updated_at'],
        ],
        'sbaio.tasks.overview' => [
            'table' => 'sbaio_tasks',
            'order_by' => 'id',
            'direction' => 'DESC',
            'filename_prefix' => 'sbaio-tasks',
            'columns' => ['id', 'task_title', 'owner_name', 'task_type', 'task_bucket', 'related_date', 'task_status', 'priority', 'due_date', 'created_at', 'updated_at'],
        ],
        'sbaio.timecards.overview' => [
            'table' => 'sbaio_timecards_daily', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-timecards',
            'columns' => ['id', 'staff_id', 'legacy_name_ref', 'work_date', 'attendance_row_id', 'scheduled_start_at', 'scheduled_end_at', 'actual_start_at', 'actual_end_at', 'actual_break_start_at', 'actual_break_end_at', 'break_minutes', 'worked_minutes', 'scheduled_minutes', 'overtime_minutes', 'day_status', 'attendance_marker', 'exception_status', 'salary_type', 'salary_rate', 'daily_salary_amount', 'month_label', 'weekday_label', 'helper_flag', 'leave_type', 'holiday_name', 'approval_status', 'generated_at', 'approved_at', 'created_at', 'updated_at'],
        ],
    ];

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function activeReports(): array
    {
        $manifests = glob(APP_ROOT . '/apps/SBAIO/modules/*/plugin.json') ?: [];
        sort($manifests);

        $statusMap = self::statusMap();
        $reports = [];

        foreach ($manifests as $manifestPath) {
            $moduleDir = dirname($manifestPath);
            $moduleFolder = basename($moduleDir);
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            $moduleName = trim((string)($manifest['name'] ?? $moduleFolder));
            if ($moduleName === '' || strtolower((string)($statusMap[$moduleName] ?? 'inactive')) !== 'active') {
                continue;
            }

            foreach ((array)($manifest['reports'] ?? []) as $report) {
                if (!is_array($report)) {
                    continue;
                }

                $lifecycle = strtolower(trim((string)($report['lifecycle'] ?? 'active_only')));
                if ($lifecycle !== '' && $lifecycle !== 'active_only') {
                    continue;
                }

                $reports[] = [
                    'module' => $moduleName,
                    'report_key' => trim((string)($report['report_key'] ?? '')),
                    'title' => trim((string)($report['title'] ?? '')),
                    'owner' => trim((string)($report['owner'] ?? 'module')),
                    'scope' => trim((string)($report['scope'] ?? '')),
                    'permission' => trim((string)($report['permission'] ?? '')),
                    'view' => trim((string)($report['view'] ?? '')),
                    'export_view' => trim((string)($report['export_view'] ?? '')),
                    'lifecycle' => $lifecycle,
                    'module_dir' => $moduleDir,
                ];
            }
        }

        return $reports;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findActiveReport(string $reportKey): ?array
    {
        $reportKey = trim($reportKey);
        if ($reportKey === '') {
            return null;
        }

        foreach (self::activeReports() as $report) {
            if (trim((string)($report['report_key'] ?? '')) === $reportKey) {
                return $report;
            }
        }

        return null;
    }

    public static function streamCsvForReport(string $reportKey): bool
    {
        $reportKey = trim($reportKey);
        if (!is_array(self::findActiveReport($reportKey))) {
            self::recordCsvExportFailure($reportKey !== '' ? $reportKey : 'unknown', 'CSV report is not active.');
            return false;
        }

        $source = self::CSV_REPORT_SOURCES[$reportKey] ?? null;
        if (!is_array($source)) {
            self::recordCsvExportFailure($reportKey !== '' ? $reportKey : 'unknown', 'CSV source mapping is not defined.');
            return false;
        }

        $table = (string)($source['table'] ?? '');
        $orderBy = (string)($source['order_by'] ?? 'id');
        $direction = strtoupper((string)($source['direction'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $filePrefix = trim((string)($source['filename_prefix'] ?? 'export'));
        $filename = $filePrefix . '-' . date('Ymd-His') . '.csv';
        $columns = array_values(array_filter(
            (array)($source['columns'] ?? []),
            static fn (mixed $column): bool => is_string($column) && preg_match('/^[a-z0-9_]+$/i', $column) === 1
        ));

        if ($columns === []) {
            self::recordCsvExportFailure($reportKey, 'CSV projection is not defined.');
            return false;
        }

        if (!preg_match('/^[a-z0-9_]+$/i', $table) || !preg_match('/^[a-z0-9_]+$/i', $orderBy)) {
            self::recordCsvExportFailure($reportKey, 'CSV source validation failed.');
            return false;
        }

        try {
            $select = implode(', ', $columns);
            $rows = DB::fetchAll(sprintf(
                'SELECT %s FROM %s ORDER BY %s %s LIMIT 5000',
                $select,
                $table,
                $orderBy,
                $direction
            ));

            $headers = [];
            $headers = $columns;

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $out = fopen('php://output', 'wb');
            if ($out === false) {
                self::recordCsvExportFailure($reportKey, 'Unable to open output stream.');
                return false;
            }

            self::recordCsvExportSuccess($reportKey, $filename, $table, count($rows));

            // UTF-8 BOM for Excel compatibility.
            fwrite($out, "\xEF\xBB\xBF");
            if (!empty($headers)) {
                fputcsv($out, $headers);
            }

            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $value = $row[$header] ?? '';
                    if (is_bool($value)) {
                        $line[] = $value ? '1' : '0';
                    } elseif ($value === null) {
                        $line[] = '';
                    } elseif (is_scalar($value)) {
                        $line[] = (string)$value;
                    } else {
                        $line[] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
                fputcsv($out, $line);
            }

            fclose($out);
            return true;
        } catch (\Throwable $e) {
            self::recordCsvExportFailure($reportKey, $e->getMessage());
            return false;
        }
    }

    private static function exportHistory(): ExportHistoryService
    {
        static $history = null;
        if (!$history instanceof ExportHistoryService) {
            $history = new ExportHistoryService();
        }
        return $history;
    }

    private static function recordCsvExportSuccess(string $reportKey, string $filename, string $table, int $rowCount): void
    {
        try {
            self::exportHistory()->recordSuccess(
                'module',
                $reportKey,
                'sbaio',
                'csv',
                $filename,
                'stream://' . $filename,
                [
                    'report_key' => $reportKey,
                    'table' => $table,
                    'rows' => max(0, $rowCount),
                ]
            );
        } catch (\Throwable $e) {
            // Do not break export streaming when audit logging fails.
        }
    }

    private static function recordCsvExportFailure(string $reportKey, string $errorText): void
    {
        try {
            self::exportHistory()->recordFailure('module', $reportKey, 'sbaio', 'csv', trim($errorText) !== '' ? $errorText : 'CSV export failed.');
        } catch (\Throwable $e) {
            // Do not break export flows when audit logging fails.
        }
    }

    /**
     * @return array<string,string>
     */
    private static function statusMap(): array
    {
        $rows = DB::fetchAll('SELECT name, status FROM installed_plugins');
        $map = [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $map[$name] = trim((string)($row['status'] ?? 'inactive'));
        }

        return $map;
    }
}
