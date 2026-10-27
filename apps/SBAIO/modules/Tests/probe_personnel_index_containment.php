<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-PERSONNEL-INDEX-CONTAINMENT-001 probe.
 *
 * Verifies GET index admission is before controller/service execution. POST
 * handlers are intentionally outside this probe and remain separate workflow
 * authorization surfaces.
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

    \App\Core\DB::$activeModules = array_fill_keys(
        ['Attendance', 'Customers', 'Expenses', 'Leave', 'Notices', 'Payroll', 'Sales', 'Schedules', 'Staff', 'Tasks', 'Timecards'],
        'active'
    );

    $indexes = [
        'sbaio.attendance.overview' => [
            'route' => 'apps/SBAIO/modules/Attendance/routes.php',
            'controller' => 'apps/SBAIO/modules/Attendance/Controllers/AttendanceController.php',
            'call' => 'AttendanceController::index($view)',
            'tables' => ['sbaio_attendance_daily', 'sbaio_staff'],
        ],
        'sbaio.leave.overview' => [
            'route' => 'apps/SBAIO/modules/Leave/routes.php',
            'controller' => 'apps/SBAIO/modules/Leave/Controllers/LeaveController.php',
            'call' => 'LeaveController::index($view)',
            'tables' => ['sbaio_leave_requests', 'sbaio_staff'],
        ],
        'sbaio.payroll.overview' => [
            'route' => 'apps/SBAIO/modules/Payroll/routes.php',
            'controller' => 'apps/SBAIO/modules/Payroll/Controllers/PayrollController.php',
            'call' => 'PayrollController::index($view)',
            'tables' => ['sbaio_payroll_runs', 'sbaio_payroll_records', 'sbaio_staff'],
        ],
        'sbaio.schedules.overview' => [
            'route' => 'apps/SBAIO/modules/Schedules/routes.php',
            'controller' => 'apps/SBAIO/modules/Schedules/Controllers/SchedulesController.php',
            'call' => 'SchedulesController::index($view)',
            'tables' => ['sbaio_schedule_assignments', 'sbaio_staff'],
        ],
        'sbaio.staff.overview' => [
            'route' => 'apps/SBAIO/modules/Staff/routes.php',
            'controller' => 'apps/SBAIO/modules/Staff/Controllers/StaffController.php',
            'call' => 'StaffController::index($view)',
            'tables' => ['sbaio_staff'],
        ],
        'sbaio.timecards.overview' => [
            'route' => 'apps/SBAIO/modules/Timecards/routes.php',
            'controller' => 'apps/SBAIO/modules/Timecards/Controllers/TimecardsController.php',
            'call' => 'TimecardsController::index($view)',
            'tables' => ['sbaio_timecards_daily', 'sbaio_timecard_periods', 'sbaio_staff'],
        ],
    ];

    foreach ($indexes as $reportKey => $paths) {
        \App\Core\DB::$queries = [];
        $available = \Apps\SBAIO\Services\ModuleReportRegistryService::isPersonnelIndexAvailable($reportKey);
        $sql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
        $routeSource = (string)file_get_contents($root . '/' . $paths['route']);
        $controllerSource = (string)file_get_contents($root . '/' . $paths['controller']);
        $gatePosition = strpos($routeSource, "isPersonnelIndexAvailable('{$reportKey}')");
        $controllerPosition = strpos($routeSource, $paths['call']);

        $assert($available === false, "{$reportKey} GET index fails closed");
        $assert(count(\App\Core\DB::$queries) === 1, "{$reportKey} uses one admission query");
        $assert(str_contains($sql, 'FROM installed_plugins'), "{$reportKey} admission uses installed-module state only");
        foreach ($paths['tables'] as $table) {
            $assert(!str_contains($sql, $table), "{$reportKey} admission does not query {$table}");
        }
        $assert($gatePosition !== false, "{$reportKey} route has the containment gate");
        $assert($controllerPosition !== false && $gatePosition < $controllerPosition, "{$reportKey} gate precedes controller execution");
        $assert(str_contains($controllerSource, 'public static function index'), "{$reportKey} controller index is identified");
        $assert(str_contains($controllerSource, 'DB::') || str_contains($controllerSource, 'TimecardService::'), "{$reportKey} controller/service has the sensitive read path");
    }

    \App\Core\DB::$queries = [];
    $customersAvailable = \Apps\SBAIO\Services\ModuleReportRegistryService::isPersonnelIndexAvailable('sbaio.customers.overview');
    $customersSql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
    $assert($customersAvailable === true, 'Non-personnel Customers index remains available');
    $assert(count(\App\Core\DB::$queries) === 1 && str_contains($customersSql, 'FROM installed_plugins'), 'Customers index admission remains bounded');

    echo 'Personnel index containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}
