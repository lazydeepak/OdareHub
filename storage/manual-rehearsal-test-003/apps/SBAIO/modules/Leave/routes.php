<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Leave\Controllers\LeaveController;

require_once __DIR__ . '/Controllers/LeaveController.php';

$router->get('/apps/sbaio/leave', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    LeaveController::index($view);
    return null;
});

$router->post('/apps/sbaio/leave/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    LeaveController::createLeave();
    return null;
});

$router->post('/apps/sbaio/leave/holiday', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    LeaveController::createHoliday();
    return null;
});

$router->get('/apps/sbaio/leave/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.leave.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Leave::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/leave/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.leave.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Leave::export.php', ['report' => $report]);
    return null;
});

