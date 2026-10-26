<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Customers\Controllers\CustomersController;

require_once __DIR__ . '/Controllers/CustomersController.php';

$router->get('/apps/sbaio/customers', function () use ($view) {
    CustomersController::requireAdministrator();
    Auth::bootSession();
    CustomersController::index($view);
    return null;
});

$router->post('/apps/sbaio/customers/create', function () {
    CustomersController::requireAdministrator();
    Auth::bootSession();
    CustomersController::create();
    return null;
});

$router->get('/apps/sbaio/customers/report', function() use ($view) {
    CustomersController::requireAdministrator();
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.customers.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    CustomersController::report($view, $report);
    return null;
});

$router->get('/apps/sbaio/customers/export', function() use ($view) {
    CustomersController::requireAdministrator();
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
