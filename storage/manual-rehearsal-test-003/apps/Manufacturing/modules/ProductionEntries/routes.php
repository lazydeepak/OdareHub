<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\ProductionEntries\Controllers\ProductionEntriesController;

require_once __DIR__ . '/Controllers/ProductionEntriesController.php';

$router->get('/production-entries', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionEntriesController::index($view);
    return null;
});

$router->get('/production-entries/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionEntriesController::addForm($view);
    return null;
});

$router->post('/production-entries/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionEntriesController::create($_POST);
    header('Location: /production-entries');
    exit;
});

$router->get('/production-entries/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionEntriesController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/production-entries/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionEntriesController::update($_POST);
    header('Location: /production-entries');
    exit;
});

$router->post('/production-entries/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionEntriesController::delete((int)($_POST['id'] ?? 0));
    header('Location: /production-entries');
    exit;
});

$router->get('/production-entries/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.production_entries.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('ProductionEntries::report.php', ['report' => $report]);
    return null;
});

$router->get('/production-entries/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.production_entries.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('ProductionEntries::export.php', ['report' => $report]);
    return null;
});

