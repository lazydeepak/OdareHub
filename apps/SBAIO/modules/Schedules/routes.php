<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Schedules\Controllers\SchedulesController;

require_once __DIR__ . '/Controllers/SchedulesController.php';

$router->get('/apps/sbaio/schedules', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SchedulesController::index($view);
    return null;
});

$router->post('/apps/sbaio/schedules/templates', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SchedulesController::createTemplate();
    return null;
});

$router->post('/apps/sbaio/schedules/assign', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SchedulesController::assignRange();
    return null;
});

$router->get('/apps/sbaio/schedules/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.schedules.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Schedules::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/schedules/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.schedules.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Schedules::export.php', ['report' => $report]);
    return null;
});

