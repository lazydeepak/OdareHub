<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Timecards\Controllers\TimecardsController;

require_once __DIR__ . '/Controllers/TimecardsController.php';
require_once __DIR__ . '/Services/TimecardGenerationService.php';
require_once __DIR__ . '/Services/TimecardService.php';

$router->get('/apps/sbaio/timecards', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    TimecardsController::index($view);
    return null;
});

$router->post('/apps/sbaio/timecards/generate', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    TimecardsController::generate();
    return null;
});

$router->post('/apps/sbaio/timecards/approve', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    TimecardsController::approvePeriod();
    return null;
});

$router->get('/apps/sbaio/timecards/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.timecards.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Timecards::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/timecards/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.timecards.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Timecards::export.php', ['report' => $report]);
    return null;
});

