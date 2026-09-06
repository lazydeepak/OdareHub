<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\Coverage\Controllers\CoverageController;

require_once __DIR__ . '/Controllers/CoverageController.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$router->get('/apps/manufacturing/coverage', function () use ($view) {
    Auth::requireRouteAccess('/manufacturing/coverage');
    CoverageController::index($view);
    return null;
});
$router->get('/manufacturing/coverage', function () {
    header('Location: /apps/manufacturing/coverage', true, 302);
    exit;
});

$router->get('/apps/manufacturing/coverage/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('Coverage::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/manufacturing/coverage/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('Coverage::export.php', ['report' => $report]);
    return null;
});

