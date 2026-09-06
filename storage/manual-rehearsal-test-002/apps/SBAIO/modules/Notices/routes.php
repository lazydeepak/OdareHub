<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Notices\Controllers\NoticesController;

require_once __DIR__ . '/Controllers/NoticesController.php';

$router->get('/apps/sbaio/notices', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    NoticesController::index($view);
    return null;
});

$router->post('/apps/sbaio/notices/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    NoticesController::create();
    return null;
});

$router->get('/apps/sbaio/notices/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.notices.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Notices::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/notices/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.notices.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Notices::export.php', ['report' => $report]);
    return null;
});

