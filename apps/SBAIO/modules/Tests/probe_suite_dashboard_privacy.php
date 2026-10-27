<?php
declare(strict_types=1);

/**
 * Database-free OH-AUTO3-SBAIO-SUITE-DASHBOARD-PRIVACY-001 probe.
 *
 * Verifies central dashboard suppression and consolidated-report admission
 * without bootstrapping the application, connecting to a database, or
 * rendering/executing either surface.
 */

$root = dirname(__DIR__, 4);
$routes = (string)file_get_contents($root . '/apps/SBAIO/routes.php');
$controller = (string)file_get_contents($root . '/apps/SBAIO/Controllers/SbaioDashboardController.php');
$dashboardView = (string)file_get_contents($root . '/apps/SBAIO/Views/dashboard.php');
$reportsView = (string)file_get_contents($root . '/apps/SBAIO/Views/reports.php');
$admissionPath = $root . '/apps/SBAIO/Services/SbaioDashboardPrivacyAdmissionService.php';
$admission = (string)file_get_contents($admissionPath);

$assertions = 0;
$failures = 0;
$assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL: {$message}\n");
    }
};

require_once $admissionPath;
$assert(\SBAIO\Services\SbaioDashboardPrivacyAdmissionService::isPersonnelDataAvailable() === false, 'personnel dashboard admission fails closed');
$assert(str_contains($admission, 'return false;'), 'dashboard admission has no implicit authorization fallback');
$assert(str_contains($routes, 'require_once __DIR__ . \'/Services/SbaioDashboardPrivacyAdmissionService.php\''), 'dashboard privacy service is explicitly loaded');

$reportsOffset = strpos($routes, "\$router->get('/apps/sbaio/reports'");
$reportsBlock = $reportsOffset === false ? '' : substr($routes, $reportsOffset, (int)(strpos($routes, "\n});", $reportsOffset) - $reportsOffset));
$reportGate = strpos($reportsBlock, '$requireConsolidatedReportsPrivacy()');
$reportBoot = strpos($reportsBlock, 'Auth::bootSession()');
$reportDispatch = strpos($reportsBlock, 'SbaioDashboardController::reports($view)');
$assert($reportGate !== false, 'consolidated reports route uses privacy admission');
$assert($reportGate !== false && ($reportBoot === false || $reportGate < $reportBoot), 'consolidated reports gate precedes session boot');
$assert($reportGate !== false && $reportDispatch !== false && $reportGate < $reportDispatch, 'consolidated reports gate precedes controller dispatch');
$assert(str_contains($routes, "echo 'Consolidated personnel reports unavailable';"), 'consolidated reports fail with controlled unavailable response');

$dashboardMethodOffset = strpos($controller, 'public static function index');
$reportsMethodOffset = strpos($controller, 'public static function reports');
$dashboardMethod = $dashboardMethodOffset === false ? '' : substr($controller, $dashboardMethodOffset, (int)($reportsMethodOffset - $dashboardMethodOffset));
$assert(str_contains($dashboardMethod, 'isPersonnelDataAvailable()'), 'dashboard resolves the explicit personnel privacy admission');
$assert(substr_count($dashboardMethod, '$personnelDataAvailable ? self::count(') >= 10, 'sensitive dashboard counts are conditional');
$assert(substr_count($dashboardMethod, '$personnelDataAvailable ? self::countWhere(') === 4, 'sensitive dashboard status counts are conditional');
$assert(str_contains($dashboardMethod, 'if (!$personnelDataAvailable)'), 'dashboard removes sensitive sections when unavailable');
$assert(str_contains($dashboardMethod, "['sbaio_staff', 'sbaio_schedules', 'sbaio_attendance', 'sbaio_leave', 'sbaio_timecards', 'sbaio_payroll']"), 'personnel and payroll module cards are suppressed');
$assert(str_contains($dashboardMethod, '$heroStats = $personnelDataAvailable ?'), 'sensitive hero statistics are suppressed');
$assert(str_contains($dashboardMethod, "'sbaio_customers'") && str_contains($dashboardMethod, "'sbaio_sales'"), 'business dashboard counts remain available');

$sensitiveTables = [
    'sbaio_staff', 'sbaio_attendance_daily', 'sbaio_schedule_assignments',
    'sbaio_timecard_periods', 'sbaio_payroll_runs', 'sbaio_payroll_records',
    'sbaio_salary_monthly_rates', 'sbaio_payroll_fixed_profiles',
    'sbaio_leave_requests', 'sbaio_holiday_calendar',
];
foreach ($sensitiveTables as $table) {
    $assert(str_contains($dashboardMethod, "'{$table}'"), "dashboard sensitive table {$table} remains explicitly audited");
}

$assert(!str_contains($dashboardView, 'sbaio_staff') && !str_contains($dashboardView, 'sbaio_payroll_records'), 'dashboard view performs no source-table access');
$assert(!str_contains($reportsView, 'DB::') && !str_contains($reportsView, 'sbaio_attendance_daily') && !str_contains($reportsView, 'sbaio_payroll_records'), 'consolidated report view performs no SQL');
$assert(str_contains($dashboardMethod, "'sbaio_customers'") && str_contains($dashboardMethod, "'sbaio_tasks'"), 'non-personnel business functionality remains in dashboard controller');
$assert(!str_contains($admission, 'Auth::') && !str_contains($admission, 'DB::') && !str_contains($admission, 'organization'), 'privacy admission invents no auth, SQL, or organization policy');

echo 'SBAIO suite dashboard privacy probe: ' . $assertions . ' assertions, ' . $failures . " failures\n";
exit($failures === 0 ? 0 : 1);
