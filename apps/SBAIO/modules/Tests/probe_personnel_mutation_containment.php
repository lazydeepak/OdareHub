<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-PERSONNEL-MUTATION-CONTAINMENT-001 probe.
 *
 * Confirms every personnel POST route fails before CSRF dispatch, controller,
 * service, SQL, or transaction entry. No application bootstrap or configured
 * database is loaded.
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

    $mutations = [
        'Staff create' => [
            'key' => 'sbaio.staff.overview',
            'route' => 'apps/SBAIO/modules/Staff/routes.php',
            'path' => "/apps/sbaio/staff/create",
            'call' => 'StaffController::create()',
            'source' => 'apps/SBAIO/modules/Staff/Controllers/StaffController.php',
            'marker' => 'INSERT INTO sbaio_staff',
            'table' => 'sbaio_staff',
        ],
        'Payroll run creation' => [
            'key' => 'sbaio.payroll.overview',
            'route' => 'apps/SBAIO/modules/Payroll/routes.php',
            'path' => "/apps/sbaio/payroll/create-run",
            'call' => 'PayrollController::createRun()',
            'source' => 'apps/SBAIO/modules/Payroll/Services/PayrollScaffoldService.php',
            'marker' => 'INSERT INTO sbaio_payroll_runs',
            'table' => 'sbaio_payroll_runs',
        ],
        'Leave creation' => [
            'key' => 'sbaio.leave.overview',
            'route' => 'apps/SBAIO/modules/Leave/routes.php',
            'path' => "/apps/sbaio/leave/create",
            'call' => 'LeaveController::createLeave()',
            'source' => 'apps/SBAIO/modules/Leave/Controllers/LeaveController.php',
            'marker' => 'INSERT INTO sbaio_leave_requests',
            'table' => 'sbaio_leave_requests',
        ],
        'Leave holiday mutation' => [
            'key' => 'sbaio.leave.overview',
            'route' => 'apps/SBAIO/modules/Leave/routes.php',
            'path' => "/apps/sbaio/leave/holiday",
            'call' => 'LeaveController::createHoliday()',
            'source' => 'apps/SBAIO/modules/Leave/Controllers/LeaveController.php',
            'marker' => 'INSERT INTO sbaio_holiday_calendar',
            'table' => 'sbaio_holiday_calendar',
        ],
        'Attendance save' => [
            'key' => 'sbaio.attendance.overview',
            'route' => 'apps/SBAIO/modules/Attendance/routes.php',
            'path' => "/apps/sbaio/attendance/save",
            'call' => 'AttendanceController::saveEntry()',
            'source' => 'apps/SBAIO/modules/Attendance/Controllers/AttendanceController.php',
            'marker' => 'INSERT INTO sbaio_attendance_daily',
            'table' => 'sbaio_attendance_daily',
        ],
        'Timecard generation' => [
            'key' => 'sbaio.timecards.overview',
            'route' => 'apps/SBAIO/modules/Timecards/routes.php',
            'path' => "/apps/sbaio/timecards/generate",
            'call' => 'TimecardsController::generate()',
            'source' => 'apps/SBAIO/modules/Timecards/Services/TimecardGenerationService.php',
            'marker' => 'INSERT INTO sbaio_timecards_daily',
            'table' => 'sbaio_timecards_daily',
        ],
        'Timecard approval' => [
            'key' => 'sbaio.timecards.overview',
            'route' => 'apps/SBAIO/modules/Timecards/routes.php',
            'path' => "/apps/sbaio/timecards/approve",
            'call' => 'TimecardsController::approvePeriod()',
            'source' => 'apps/SBAIO/modules/Timecards/Controllers/TimecardsController.php',
            'marker' => "UPDATE sbaio_timecards_daily",
            'table' => 'sbaio_timecards_daily',
        ],
        'Schedule template mutation' => [
            'key' => 'sbaio.schedules.overview',
            'route' => 'apps/SBAIO/modules/Schedules/routes.php',
            'path' => "/apps/sbaio/schedules/templates",
            'call' => 'SchedulesController::createTemplate()',
            'source' => 'apps/SBAIO/modules/Schedules/Controllers/SchedulesController.php',
            'marker' => 'INSERT INTO sbaio_schedule_templates',
            'table' => 'sbaio_schedule_templates',
        ],
        'Schedule assignment mutation' => [
            'key' => 'sbaio.schedules.overview',
            'route' => 'apps/SBAIO/modules/Schedules/routes.php',
            'path' => "/apps/sbaio/schedules/assign",
            'call' => 'SchedulesController::assignRange()',
            'source' => 'apps/SBAIO/modules/Schedules/Controllers/SchedulesController.php',
            'marker' => 'INSERT INTO sbaio_schedule_assignments',
            'table' => 'sbaio_schedule_assignments',
        ],
    ];

    foreach ($mutations as $name => $mutation) {
        \App\Core\DB::$queries = [];
        $available = \Apps\SBAIO\Services\ModuleReportRegistryService::isPersonnelMutationAvailable($mutation['key']);
        $sql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
        $routeSource = (string)file_get_contents($root . '/' . $mutation['route']);
        $source = (string)file_get_contents($root . '/' . $mutation['source']);
        $postToken = '$router->post(\'' . $mutation['path'] . '\'';
        $postPosition = strpos($routeSource, $postToken);
        $block = $postPosition === false ? '' : substr($routeSource, $postPosition, (int)(strpos($routeSource, "\n});", $postPosition) - $postPosition));
        $gatePosition = strpos($block, "isPersonnelMutationAvailable('{$mutation['key']}')");
        $bootPosition = strpos($block, 'Auth::bootSession()');
        $callPosition = strpos($block, $mutation['call']);

        $assert($available === false, "{$name} fails closed");
        $assert(count(\App\Core\DB::$queries) === 1, "{$name} performs one admission query");
        $assert(str_contains($sql, 'FROM installed_plugins'), "{$name} admission uses installed-module state only");
        $assert(!str_contains($sql, $mutation['table']), "{$name} admission does not query {$mutation['table']}");
        $assert($gatePosition !== false, "{$name} has a mutation gate");
        $assert($bootPosition === false || $gatePosition < $bootPosition, "{$name} gate precedes session/controller setup");
        $assert($callPosition !== false && $gatePosition < $callPosition, "{$name} gate precedes controller dispatch");
        $assert(str_contains($source, $mutation['marker']), "{$name} mutation source is identified");
    }

    \App\Core\DB::$queries = [];
    $customersAvailable = \Apps\SBAIO\Services\ModuleReportRegistryService::isPersonnelMutationAvailable('sbaio.customers.overview');
    $customersSql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));
    $assert($customersAvailable === true, 'Non-personnel Customers mutation admission remains available');
    $assert(count(\App\Core\DB::$queries) === 1 && str_contains($customersSql, 'FROM installed_plugins'), 'Customers mutation admission remains bounded');

    echo 'Personnel mutation containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}
