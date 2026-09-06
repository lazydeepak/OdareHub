<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Expenses\Controllers\ExpensesController;

require_once __DIR__ . '/Controllers/ExpensesController.php';

$router->get('/apps/sbaio/expenses', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    ExpensesController::index($view);
    return null;
});

$router->post('/apps/sbaio/expenses/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    ExpensesController::create();
    return null;
});

$router->get('/apps/sbaio/expenses/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.expenses.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Expenses::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/expenses/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.expenses.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Expenses::export.php', ['report' => $report]);
    return null;
});

