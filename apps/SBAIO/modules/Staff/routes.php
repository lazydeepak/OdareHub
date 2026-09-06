<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Staff\Controllers\StaffController;

require_once __DIR__ . '/Controllers/StaffController.php';

$router->get('/apps/sbaio/staff', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    StaffController::index($view);
    return null;
});

$router->post('/apps/sbaio/staff/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    StaffController::create();
    return null;
});

$router->get('/apps/sbaio/staff/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.staff.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Staff::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/staff/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.staff.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Staff::export.php', ['report' => $report]);
    return null;
});

