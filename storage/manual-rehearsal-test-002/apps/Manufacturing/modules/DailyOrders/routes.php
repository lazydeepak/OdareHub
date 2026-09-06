<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\DailyOrders\Controllers\DailyOrdersController;

require_once __DIR__ . '/Controllers/DailyOrdersController.php';
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$renderDailyOrdersReport = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.daily_orders.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('DailyOrders::report.php', ['report' => $report]);
    return null;
};
$router->get('/apps/manufacturing/daily-orders/report', $renderDailyOrdersReport);
$router->get('/daily-orders/report', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/daily-orders/report' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderDailyOrdersExport = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.daily_orders.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('DailyOrders::export.php', ['report' => $report]);
    return null;
};
$router->get('/apps/manufacturing/daily-orders/export', $renderDailyOrdersExport);
$router->get('/daily-orders/export', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/daily-orders/export' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

