<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\SBAIO\Services\ModuleReportRegistryService;
require_once APP_ROOT . "/apps/SBAIO/Services/ModuleReportRegistryService.php";
use Plugins\Tasks\Controllers\TasksController;

require_once __DIR__ . '/Controllers/TasksController.php';

$router->get('/apps/sbaio/tasks', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    TasksController::index($view);
    return null;
});

$router->post('/apps/sbaio/tasks/create', function () {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    TasksController::create();
    return null;
});

$router->get('/apps/sbaio/tasks/report', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('sbaio.tasks.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo 'Report unavailable';
        return null;
    }
    $view->render('Tasks::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/sbaio/tasks/export', function() use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    $reportKey = 'sbaio.tasks.overview';
    if (strtolower(trim((string)($_GET['format'] ?? ''))) === 'csv') {
        if (!ModuleReportRegistryService::streamCsvForReport($reportKey)) {
            http_response_code(404);
            echo 'Export unavailable';
        }
        return null;
    }
    $report = ModuleReportRegistryService::findActiveReport($reportKey);
    $view->render('Tasks::export.php', ['report' => $report]);
    return null;
});

