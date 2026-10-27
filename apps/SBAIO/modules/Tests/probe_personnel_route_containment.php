<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-PAYROLL-PARITY-CONTAINMENT-001 probe.
 *
 * Audits the complete six-module personnel route inventory and proves the
 * Payroll parity gate stops before its sensitive validation service. No
 * application bootstrap or configured database is loaded.
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

    $modules = [
        'Attendance' => ['key' => 'sbaio.attendance.overview', 'posts' => 1, 'controller' => 'AttendanceController::index($view)'],
        'Leave' => ['key' => 'sbaio.leave.overview', 'posts' => 2, 'controller' => 'LeaveController::index($view)'],
        'Payroll' => ['key' => 'sbaio.payroll.overview', 'posts' => 1, 'controller' => 'PayrollController::index($view)'],
        'Schedules' => ['key' => 'sbaio.schedules.overview', 'posts' => 2, 'controller' => 'SchedulesController::index($view)'],
        'Staff' => ['key' => 'sbaio.staff.overview', 'posts' => 1, 'controller' => 'StaffController::index($view)'],
        'Timecards' => ['key' => 'sbaio.timecards.overview', 'posts' => 2, 'controller' => 'TimecardsController::index($view)'],
    ];

    foreach ($modules as $module => $definition) {
        $routePath = $root . '/apps/SBAIO/modules/' . $module . '/routes.php';
        $source = (string)file_get_contents($routePath);
        $routeModule = strtolower($module);
        $indexGate = strpos($source, "isPersonnelIndexAvailable('{$definition['key']}')");
        $indexCall = strpos($source, $definition['controller']);

        $assert(str_contains($source, "findActiveHtmlReport('{$definition['key']}')"), "{$module} report route remains contained");
        $assert(str_contains($source, 'streamCsvForReport($reportKey)'), "{$module} CSV route remains contained");
        $assert(substr_count($source, "isPersonnelMutationAvailable('{$definition['key']}')") === $definition['posts'], "{$module} mutation route count is complete");
        $assert($indexGate !== false, "{$module} index has containment gate");
        $assert($indexCall !== false && $indexGate < $indexCall, "{$module} index gate precedes controller");
        $assert(str_contains($source, "/apps/sbaio/{$routeModule}/report"), "{$module} report route is inventoried");
        $assert(str_contains($source, "/apps/sbaio/{$routeModule}/export"), "{$module} export route is inventoried");
    }

    $payrollRoute = (string)file_get_contents($root . '/apps/SBAIO/modules/Payroll/routes.php');
    $parityGate = strpos($payrollRoute, "isPersonnelParityAvailable('sbaio.payroll.overview')");
    $parityCall = strpos($payrollRoute, 'PayrollController::parity($view)');
    $parityBoot = strpos($payrollRoute, 'Auth::bootSession()', $parityGate === false ? 0 : $parityGate);
    $parityService = (string)file_get_contents($root . '/apps/SBAIO/modules/Payroll/Services/PayrollParityValidationService.php');

    \App\Core\DB::$queries = [];
    $parityAvailable = \Apps\SBAIO\Services\ModuleReportRegistryService::isPersonnelParityAvailable('sbaio.payroll.overview');
    $paritySql = implode("\n", array_map(static fn (array $query): string => $query['sql'], \App\Core\DB::$queries));

    $assert($parityAvailable === false, 'Payroll parity fails closed');
    $assert(count(\App\Core\DB::$queries) === 1, 'Payroll parity performs one admission query');
    $assert(str_contains($paritySql, 'FROM installed_plugins'), 'Payroll parity admission uses installed-module state only');
    foreach (['sbaio_salary_monthly_rates', 'sbaio_payroll_records', 'sbaio_payroll_runs', 'sbaio_staff', 'sbaio_payroll_fixed_profiles'] as $table) {
        $assert(!str_contains($paritySql, $table), "Payroll parity admission does not query {$table}");
    }
    $assert($parityGate !== false, 'Payroll parity route has containment gate');
    $assert($parityCall !== false && $parityGate < $parityCall, 'Payroll parity gate precedes controller');
    $assert($parityBoot !== false && $parityGate < $parityBoot, 'Payroll parity gate precedes session boot');
    $assert(str_contains($parityService, 'sbaio_salary_monthly_rates'), 'Parity service salary-source disclosure is identified');
    $assert(str_contains($parityService, 'sbaio_payroll_fixed_profiles'), 'Parity service payroll-profile disclosure is identified');
    $assert(str_contains($parityService, "'employee'"), 'Parity service employee disclosure is identified');

    echo 'Personnel route containment probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
    exit($failures === 0 ? 0 : 1);
}
