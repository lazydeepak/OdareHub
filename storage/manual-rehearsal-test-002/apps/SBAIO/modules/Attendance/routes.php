<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Attendance\Controllers\AttendanceController;

require_once __DIR__ . '/Controllers/AttendanceController.php';

$router->get('/apps/sbaio/attendance', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    AttendanceController::index($view);
    return null;
});

$router->post('/apps/sbaio/attendance/save', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    AttendanceController::saveEntry();
    return null;
});

$router->get('/apps/sbaio/attendance/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.attendance.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Attendance::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/attendance/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.attendance.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Attendance::export.php', ['report' => $report]);
    return null;
});

