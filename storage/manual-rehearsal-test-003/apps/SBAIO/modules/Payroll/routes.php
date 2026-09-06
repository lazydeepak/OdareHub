<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Payroll\Controllers\PayrollController;

require_once __DIR__ . '/Controllers/PayrollController.php';
require_once __DIR__ . '/Services/PayrollParityValidationService.php';
require_once __DIR__ . '/Services/PayrollScaffoldService.php';

$router->get('/apps/sbaio/payroll', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    PayrollController::index($view);
    return null;
});

$router->post('/apps/sbaio/payroll/create-run', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    PayrollController::createRun();
    return null;
});

$router->get('/apps/sbaio/payroll/parity', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    PayrollController::parity($view);
    return null;
});

$router->get('/apps/sbaio/payroll/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.payroll.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Payroll::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/payroll/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.payroll.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Payroll::export.php', ['report' => $report]);
    return null;
});

