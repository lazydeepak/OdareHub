<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-PERSONNEL-HTML-REPORT-CONTAINMENT-001 probe.
 *
 * The registry double records only admission SQL. No application bootstrap,
 * configured database, report view, or installed database is loaded.
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
                    static fn (string $name): array => [
                        'name' => $name,
                        'status' => self::$activeModules[$name] ?? 'active',
                    ],
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

    $moduleNames = ['Attendance', 'Customers', 'Expenses', 'Leave', 'Notices', 'Payroll', 'Sales', 'Schedules', 'Staff', 'Tasks', 'Timecards'];
    \App\Core\DB::$activeModules = array_fill_keys($moduleNames, 'active');

    $reports = [
        'sbaio.attendance.overview' => [
            'route' => 'apps/SBAIO/modules/Attendance/routes.php',
            'view' => 'apps/SBAIO/modules/Attendance/Views/report.php',
            'table' => 'sbaio_attendance_daily',
        ],
        'sbaio.leave.overview' => [
            'route' => 'apps/SBAIO/modules/Leave/routes.php',
            'view' => 'apps/SBAIO/modules/Leave/Views/report.php',
            'table' => 'sbaio_leave_requests',
        ],
        'sbaio.payroll.overview' => [
            'route' => 'apps/SBAIO/modules/Payroll/routes.php',
            'view' => 'apps/SBAIO/modules/Payroll/Views/report.php',
            'table' => 'sbaio_payroll_records',
        ],
        'sbaio.schedules.overview' => [
            'route' => 'apps/SBAIO/modules/Schedules/routes.php',
            'view' => 'apps/SBAIO/modules/Schedules/Views/report.php',
            'table' => 'sbaio_schedule_assignments',
        ],
        'sbaio.staff.overview' => [
            'route' => 'apps/SBAIO/modules/Staff/routes.php',
            'view' => 'apps/SBAIO/modules/Staff/Views/report.php',
            'table' => 'sbaio_staff',
        ],
        'sbaio.timecards.overview' => [
            'route' => 'apps/SBAIO/modules/Timecards/routes.php',
            'view' => 'apps/SBAIO/modules/Timecards/Views/report.php',
            'table' => 'sbaio_timecards_daily',
        ],
    ];

    foreach ($reports as $reportKey => $paths) {
        \App\Core\DB::$queries = [];
        $report = \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveHtmlReport($reportKey);
        $sql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
        $routeSource = (string)file_get_contents($root . '/' . $paths['route']);
        $viewSource = (string)file_get_contents($root . '/' . $paths['view']);

        $assert($report === null, "{$reportKey} HTML report is contained");
        $assert(count(\App\Core\DB::$queries) === 1, "{$reportKey} performs one admission query only");
        $assert(str_contains($sql, 'FROM installed_plugins'), "{$reportKey} admission query is the only SQL");
        $assert(!str_contains($sql, $paths['table']), "{$reportKey} never queries {$paths['table']}");
        $assert(str_contains($routeSource, 'findActiveHtmlReport'), "{$reportKey} route uses HTML containment");
        $assert(!str_contains($routeSource, "findActiveReport('{$reportKey}')"), "{$reportKey} route has no uncontained report lookup");
        $assert(str_contains($viewSource, 'findActiveHtmlReport'), "{$reportKey} view fallback uses HTML containment");
        $assert(!str_contains($viewSource, "findActiveReport('{$reportKey}')"), "{$reportKey} view has no uncontained report fallback");
    }

    \App\Core\DB::$queries = [];
    $customers = \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveHtmlReport('sbaio.customers.overview');
    $customersSql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
    $assert(is_array($customers), 'Customers HTML report remains available');
    $assert(count(\App\Core\DB::$queries) === 1 && str_contains($customersSql, 'FROM installed_plugins'), 'Customers HTML admission remains bounded');

    echo 'Personnel HTML report containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}
