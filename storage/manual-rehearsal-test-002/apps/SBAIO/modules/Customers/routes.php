<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Customers\Controllers\CustomersController;

require_once __DIR__ . '/Controllers/CustomersController.php';

$router->get('/apps/sbaio/customers', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    CustomersController::index($view);
    return null;
});

$router->post('/apps/sbaio/customers/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    CustomersController::create();
    return null;
});

$router->get('/apps/sbaio/customers/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.customers.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Customers::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/customers/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.customers.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Customers::export.php', ['report' => $report]);
    return null;
});

