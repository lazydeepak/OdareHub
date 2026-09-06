<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\PackageManager;
use Plugins\ProductionPlans\Controllers\ProductionPlansController;
use Plugins\ProductionQueue\Controllers\QueueBoardService;
use Apps\Manufacturing\Services\ModuleReportRegistryService;

require_once __DIR__ . '/Controllers/ProductionPlansController.php';
$queueBoardService = rtrim(PackageManager::pluginSourcePath('ProductionQueue'), '/') . '/Controllers/QueueBoardService.php';
if (is_file($queueBoardService)) {
    require_once $queueBoardService;
}

$renderProductionPlansReport = function() use ($view) {
    Auth::requireRouteAccess('/production-plans');
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.production_plans.next_two_weeks');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('ProductionPlans::report.php', ['report' => $report]);
    return null;
};
$router->get('/apps/manufacturing/production-plans/report', $renderProductionPlansReport);
$router->get('/production-plans/report', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/report' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderProductionPlansExport = function() use ($view) {
    Auth::requireRouteAccess('/production-plans');
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.production_plans.next_two_weeks');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('ProductionPlans::export.php', ['report' => $report]);
    return null;
};
$router->get('/apps/manufacturing/production-plans/export', $renderProductionPlansExport);
$router->get('/production-plans/export', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/export' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});
