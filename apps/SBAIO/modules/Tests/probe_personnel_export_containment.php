<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-PERSONNEL-EXPORT-CONTAINMENT-001 probe.
 *
 * Uses in-memory doubles only. It verifies that unresolved personnel/payroll
 * exports fail before their source query while established non-personnel
 * exports retain their explicit CSV behavior. No application bootstrap or
 * configured database is loaded.
 */

namespace App\Core {
    final class DB
    {
        public static array $queries = [];
        public static array $activeModules = [];

        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$queries[] = ['sql' => $sql, 'params' => $params];
            if (str_contains($sql, 'FROM installed_plugins')) {
                return array_map(
                    static fn (string $name): array => ['name' => $name, 'status' => self::$activeModules[$name] ?? 'active'],
                    array_keys(self::$activeModules)
                );
            }

            return [];
        }
    }
}

namespace App\Services {
    final class ExportHistoryService
    {
        public function recordSuccess(...$arguments): void
        {
        }

        public function recordFailure(...$arguments): void
        {
        }
    }
}

namespace {
    $root = dirname(__DIR__, 4);
    define('APP_ROOT', $root);
    require_once $root . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

    $assertions = 0;
    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL: {$message}\n");
        }
    };

    $activeModules = ['Attendance', 'Customers', 'Expenses', 'Leave', 'Notices', 'Payroll', 'Sales', 'Schedules', 'Staff', 'Tasks', 'Timecards'];
    \App\Core\DB::$activeModules = array_fill_keys($activeModules, 'active');

    $blocked = [
        'sbaio.attendance.overview' => 'sbaio_attendance_daily',
        'sbaio.leave.overview' => 'sbaio_leave_requests',
        'sbaio.payroll.overview' => 'sbaio_payroll_records',
        'sbaio.schedules.overview' => 'sbaio_schedule_assignments',
        'sbaio.staff.overview' => 'sbaio_staff',
        'sbaio.timecards.overview' => 'sbaio_timecards_daily',
    ];

    foreach ($blocked as $reportKey => $table) {
        \App\Core\DB::$queries = [];
        $result = \Apps\SBAIO\Services\ModuleReportRegistryService::streamCsvForReport($reportKey);
        $sql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
        $assert($result === false, "{$reportKey} fails closed");
        $assert(count(\App\Core\DB::$queries) === 1 && str_contains($sql, 'FROM installed_plugins'), "{$reportKey} performs admission only");
        $assert(!str_contains($sql, $table), "{$reportKey} does not query {$table}");
    }

    \App\Core\DB::$activeModules['Tasks'] = 'inactive';
    \App\Core\DB::$queries = [];
    $inactiveResult = \Apps\SBAIO\Services\ModuleReportRegistryService::streamCsvForReport('sbaio.tasks.overview');
    $inactiveSql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
    $assert($inactiveResult === false, 'inactive Task report fails closed');
    $assert(!str_contains($inactiveSql, 'FROM sbaio_tasks'), 'inactive Task report is prevented before source query');

    \App\Core\DB::$activeModules['Tasks'] = 'active';
    \App\Core\DB::$queries = [];
    ob_start();
    $taskResult = \Apps\SBAIO\Services\ModuleReportRegistryService::streamCsvForReport('sbaio.tasks.overview');
    ob_end_clean();
    $taskSql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
    $assert($taskResult === true, 'Task CSV remains available under its existing contract');
    $assert(str_contains($taskSql, 'SELECT id, task_title, owner_name, task_type, task_bucket, related_date, task_status, priority, due_date, created_at, updated_at FROM sbaio_tasks'), 'Task CSV retains its explicit projection');
    $assert(!str_contains($taskSql, 'SELECT *'), 'Task CSV does not use unrestricted projection');

    echo 'Personnel export containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}
