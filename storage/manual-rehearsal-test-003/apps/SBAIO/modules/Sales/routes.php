<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Sales\Controllers\SalesController;

require_once __DIR__ . '/Controllers/SalesController.php';

$router->get('/apps/sbaio/sales', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SalesController::index($view);
    return null;
});

$router->post('/apps/sbaio/sales/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SalesController::create();
    return null;
});

$router->get('/apps/sbaio/sales/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.sales.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Sales::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/sales/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.sales.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Sales::export.php', ['report' => $report]);
    return null;
});

