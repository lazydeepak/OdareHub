<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-OPERATOR-WIDGET-PRIVACY-001 probe.
 *
 * Verifies each SBAIO operator widget provider fails closed before sensitive
 * count/detail SQL and that Shell remains a generic discovery/rendering layer.
 * No application bootstrap, operator session, or database is loaded.
 */

$root = dirname(__DIR__, 4);
$servicePath = $root . '/apps/SBAIO/Services/SbaioOperatorWidgetPrivacyService.php';
$service = (string)file_get_contents($servicePath);

$providers = [
    'Attendance' => $root . '/apps/SBAIO/modules/Attendance/Services/AttendanceWidgetRegistry.php',
    'Timecards' => $root . '/apps/SBAIO/modules/Timecards/Services/TimecardWidgetRegistry.php',
    'Leave' => $root . '/apps/SBAIO/modules/Leave/Services/LeaveWidgetRegistry.php',
    'Payroll' => $root . '/apps/SBAIO/modules/Payroll/Services/PayrollWidgetRegistry.php',
];

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

require_once $servicePath;
$assert(\SBAIO\Services\SbaioOperatorWidgetPrivacyService::isAvailable('summary_cards') === false, 'operator widget admission fails closed');
$assert(\SBAIO\Services\SbaioOperatorWidgetPrivacyService::isAvailable('watchlist_items') === false, 'watchlist admission fails closed');
$assert(str_contains($service, 'return false;'), 'widget admission has no implicit authorization fallback');

foreach ($providers as $name => $path) {
    $source = (string)file_get_contents($path);
    $assert(is_file($path), "{$name} widget registry exists");
    $assert(str_contains($source, 'SbaioOperatorWidgetPrivacyService.php'), "{$name} registry loads the privacy boundary");
    $assert(str_contains($source, 'SbaioOperatorWidgetPrivacyService::isAvailable($region)'), "{$name} registry gates contribution before dispatch");
    $assert(str_contains($source, 'return [];'), "{$name} registry has a fail-closed empty result");

    require_once $path;
    $class = match ($name) {
        'Attendance' => \Plugins\Attendance\Services\AttendanceWidgetRegistry::class,
        'Timecards' => \Plugins\Timecards\Services\TimecardWidgetRegistry::class,
        'Leave' => \Plugins\Leave\Services\LeaveWidgetRegistry::class,
        'Payroll' => \Plugins\Payroll\Services\PayrollWidgetRegistry::class,
    };
    $assert($class::contribute('summary_cards') === [], "{$name} summary widgets are unavailable");
    $assert($class::contribute('watchlist_items') === [], "{$name} watchlist widgets are unavailable");
}

$operatorWidgetService = (string)file_get_contents($root . '/apps/Shell/Services/OperatorLayerWidgetService.php');
$hostContributionService = (string)file_get_contents($root . '/apps/SBAIO/Services/HostSurfaceContributionService.php');
$assert(str_contains($operatorWidgetService, 'AttendanceWidgetRegistry'), 'Shell discovery retains the existing SBAIO provider catalog');
$assert(str_contains($operatorWidgetService, 'normalizeOperatorWidgetCollection'), 'Shell retains generic widget normalization');
$assert(str_contains($operatorWidgetService, 'renderSingleWidget'), 'Shell retains generic widget rendering');
$assert(str_contains($hostContributionService, 'return [];'), 'SBAIO host summary/monitoring contribution remains fail closed');
$assert(!str_contains($service, 'Auth::') && !str_contains($service, 'DB::') && !str_contains($service, 'organization'), 'widget privacy service adds no auth, SQL, or organization policy');

$sensitiveTables = [
    'sbaio_attendance_daily', 'sbaio_staff', 'sbaio_timecard_periods',
    'sbaio_leave_requests', 'sbaio_payroll_runs',
];
foreach ($providers as $name => $path) {
    $source = (string)file_get_contents($path);
    foreach ($sensitiveTables as $table) {
        if (str_contains($source, $table)) {
            $gate = strpos($source, 'SbaioOperatorWidgetPrivacyService::isAvailable($region)');
            $query = strpos($source, $table);
            $assert($gate !== false && $query !== false && $gate < $query, "{$name} sensitive {$table} use follows the privacy gate");
        }
    }
}

echo 'SBAIO operator widget privacy probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
