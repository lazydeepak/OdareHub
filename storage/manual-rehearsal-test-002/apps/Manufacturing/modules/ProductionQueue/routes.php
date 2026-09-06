<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\ProductionQueue\Controllers\QueueBoardService;

require_once __DIR__ . '/Controllers/QueueBoardService.php';

$router->get('/production-queue/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.production_queue.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('ProductionQueue::report.php', ['report' => $report]);
    return null;
});

$router->get('/production-queue/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.production_queue.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('ProductionQueue::export.php', ['report' => $report]);
    return null;
});

