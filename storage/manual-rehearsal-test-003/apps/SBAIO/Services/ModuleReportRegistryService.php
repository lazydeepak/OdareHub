<?php
declare(strict_types=1);

namespace Apps\SBAIO\Services;

use App\Core\DB;
use App\Services\ExportHistoryService;

final class ModuleReportRegistryService
{
    /**
     * @var array<string,array{table:string,order_by:string,direction:string,filename_prefix:string}>
     */
    private const CSV_REPORT_SOURCES = [
        'sbaio.attendance.overview' => ['table' => 'sbaio_attendance_daily', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-attendance'],
        'sbaio.customers.overview' => ['table' => 'sbaio_customers', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-customers'],
        'sbaio.expenses.overview' => ['table' => 'sbaio_expenses', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-expenses'],
        'sbaio.leave.overview' => ['table' => 'sbaio_leave_requests', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-leave'],
        'sbaio.notices.overview' => ['table' => 'sbaio_notices', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-notices'],
        'sbaio.payroll.overview' => ['table' => 'sbaio_payroll_records', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-payroll'],
        'sbaio.sales.overview' => ['table' => 'sbaio_sales', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-sales'],
        'sbaio.schedules.overview' => ['table' => 'sbaio_schedule_assignments', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-schedules'],
        'sbaio.staff.overview' => ['table' => 'sbaio_staff', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-staff'],
        'sbaio.tasks.overview' => ['table' => 'sbaio_tasks', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-tasks'],
        'sbaio.timecards.overview' => ['table' => 'sbaio_timecards_daily', 'order_by' => 'id', 'direction' => 'DESC', 'filename_prefix' => 'sbaio-timecards'],
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

        if (!preg_match('/^[a-z0-9_]+$/i', $table) || !preg_match('/^[a-z0-9_]+$/i', $orderBy)) {
            self::recordCsvExportFailure($reportKey, 'CSV source validation failed.');
            return false;
        }

        try {
            $rows = DB::fetchAll(sprintf(
                'SELECT * FROM %s ORDER BY %s %s LIMIT 5000',
                $table,
                $orderBy,
                $direction
            ));

            $headers = [];
            if (!empty($rows) && is_array($rows[0])) {
                $headers = array_keys($rows[0]);
            } else {
                $columns = DB::fetchAll(sprintf('SHOW COLUMNS FROM %s', $table));
                foreach ($columns as $column) {
                    $name = trim((string)($column['Field'] ?? ''));
                    if ($name !== '') {
                        $headers[] = $name;
                    }
                }
            }

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
