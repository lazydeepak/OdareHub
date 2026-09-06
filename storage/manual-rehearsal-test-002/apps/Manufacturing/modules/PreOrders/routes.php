<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\PreOrders\Controllers\PreOrdersController;

require_once __DIR__ . '/Controllers/PreOrdersController.php';

$router->get('/pre-orders', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PreOrdersController::index($view);
    return null;
});

$router->get('/pre-orders/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PreOrdersController::addForm($view);
    return null;
});

$router->get('/pre-orders/import', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PreOrdersController::importForm($view);
    return null;
});

$router->post('/pre-orders/import', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PreOrdersController::importUpload($_FILES['import_file'] ?? null);
    header('Location: /pre-orders');
    exit;
});

$router->post('/pre-orders/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PreOrdersController::create($_POST);
    header('Location: /pre-orders');
    exit;
});

$router->get('/pre-orders/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PreOrdersController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/pre-orders/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PreOrdersController::update($_POST);
    header('Location: /pre-orders');
    exit;
});

$router->post('/pre-orders/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PreOrdersController::delete((int)($_POST['id'] ?? 0));
    header('Location: /pre-orders');
    exit;
});

$router->get('/pre-orders/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.pre_orders.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('PreOrders::report.php', ['report' => $report]);
    return null;
});

$router->get('/pre-orders/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.pre_orders.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('PreOrders::export.php', ['report' => $report]);
    return null;
});

