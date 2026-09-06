<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\MaterialManagement\Controllers\MaterialManagementController;
use Plugins\MaterialManagement\Services\MaterialAccessService;

require_once __DIR__ . '/Controllers/MaterialManagementController.php';

$router->get('/apps/manufacturing/materials', function () use ($view) {
    MaterialAccessService::requireAnyAccess('/apps/manufacturing/materials');
    MaterialManagementController::dashboard($view);
    return null;
});

$router->get('/apps/manufacturing/materials/master', function () use ($view) {
    MaterialAccessService::requireSection('master', '/apps/manufacturing/materials/master');
    MaterialManagementController::master($view);
    return null;
});

$router->post('/apps/manufacturing/materials/master', function () {
    MaterialAccessService::requireSection('master', '/apps/manufacturing/materials/master');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    try {
        MaterialManagementController::createMaterial($_POST);
        header('Location: /apps/manufacturing/materials/master');
        exit;
    } catch (\Throwable $e) {
        MaterialManagementController::fail($e, '/apps/manufacturing/materials/master');
    }
});

$router->get('/apps/manufacturing/materials/mapping', function () use ($view) {
    MaterialAccessService::requireSection('mapping', '/apps/manufacturing/materials/mapping');
    MaterialManagementController::mapping($view);
    return null;
});

$router->post('/apps/manufacturing/materials/mapping', function () {
    MaterialAccessService::requireSection('mapping', '/apps/manufacturing/materials/mapping');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    try {
        MaterialManagementController::createMapping($_POST);
        header('Location: /apps/manufacturing/materials/mapping');
        exit;
    } catch (\Throwable $e) {
        MaterialManagementController::fail($e, '/apps/manufacturing/materials/mapping');
    }
});

$router->get('/apps/manufacturing/materials/stock', function () use ($view) {
    MaterialAccessService::requireSection('stock', '/apps/manufacturing/materials/stock');
    MaterialManagementController::stock($view);
    return null;
});

$router->get('/apps/manufacturing/materials/receipt', function () use ($view) {
    MaterialAccessService::requireSection('receipt', '/apps/manufacturing/materials/receipt');
    MaterialManagementController::receipt($view);
    return null;
});

$router->post('/apps/manufacturing/materials/receipt', function () {
    MaterialAccessService::requireSection('receipt', '/apps/manufacturing/materials/receipt');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    try {
        MaterialManagementController::recordReceipt($_POST);
        $mode = strtolower(trim((string)($_POST['receipt_mode'] ?? '')));
        $target = '/apps/manufacturing/materials/receipt';
        if (in_array($mode, ['resin', 'functional'], true)) {
            $target .= '?mode=' . $mode;
        }
        header('Location: ' . $target);
        exit;
    } catch (\Throwable $e) {
        $mode = strtolower(trim((string)($_POST['receipt_mode'] ?? '')));
        $target = '/apps/manufacturing/materials/receipt';
        if (in_array($mode, ['resin', 'functional'], true)) {
            $target .= '?mode=' . $mode;
        }
        MaterialManagementController::fail($e, $target);
    }
});

$router->post('/apps/manufacturing/materials/stock', function () {
    MaterialAccessService::requireSection('stock', '/apps/manufacturing/materials/stock');
    if (!MaterialAccessService::canManage()) {
        http_response_code(403);
        echo t('common.access_denied');
        exit;
    }
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    try {
        MaterialManagementController::postStockAdjustment($_POST);
        header('Location: /apps/manufacturing/materials/stock');
        exit;
    } catch (\Throwable $e) {
        MaterialManagementController::fail($e, '/apps/manufacturing/materials/stock');
    }
});

$router->get('/apps/manufacturing/materials/coverage', function () use ($view) {
    MaterialAccessService::requireSection('coverage', '/apps/manufacturing/materials/coverage');
    MaterialManagementController::coverage($view);
    return null;
});

$router->get('/apps/manufacturing/materials/part-status', function () use ($view) {
    MaterialAccessService::requireAnyAccess('/apps/manufacturing/materials/part-status');
    MaterialManagementController::partStatus($view);
    return null;
});

$router->get('/apps/manufacturing/materials/detail', function () use ($view) {
    MaterialAccessService::requireAnyAccess('/apps/manufacturing/materials/detail');
    $materialId = (int)($_GET['id'] ?? 0);
    if ($materialId <= 0) {
        http_response_code(404);
        echo t('common.not_found');
        return null;
    }
    MaterialManagementController::detail($view, $materialId);
    return null;
});

$router->get('/apps/manufacturing/materials/planning', function () use ($view) {
    MaterialAccessService::requireSection('planning', '/apps/manufacturing/materials/planning');
    MaterialManagementController::planning($view);
    return null;
});

$router->get('/apps/manufacturing/materials/orders', function () use ($view) {
    MaterialAccessService::requireSection('orders', '/apps/manufacturing/materials/orders');
    MaterialManagementController::orders($view);
    return null;
});

$router->post('/apps/manufacturing/materials/orders', function () {
    MaterialAccessService::requireSection('orders', '/apps/manufacturing/materials/orders');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    try {
        MaterialManagementController::createOrder($_POST);
        header('Location: /apps/manufacturing/materials/orders');
        exit;
    } catch (\Throwable $e) {
        MaterialManagementController::fail($e, '/apps/manufacturing/materials/orders');
    }
});

$router->get('/apps/manufacturing/materials/capacity', function () use ($view) {
    MaterialAccessService::requireSection('capacity', '/apps/manufacturing/materials/capacity');
    MaterialManagementController::capacity($view);
    return null;
});

$router->post('/apps/manufacturing/materials/capacity', function () {
    MaterialAccessService::requireSection('capacity', '/apps/manufacturing/materials/capacity');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    try {
        MaterialManagementController::createCapacity($_POST);
        header('Location: /apps/manufacturing/materials/capacity');
        exit;
    } catch (\Throwable $e) {
        MaterialManagementController::fail($e, '/apps/manufacturing/materials/capacity');
    }
});

$router->get('/apps/manufacturing/materials/cost', function () use ($view) {
    MaterialAccessService::requireSection('cost', '/apps/manufacturing/materials/cost');
    MaterialManagementController::cost($view);
    return null;
});

$router->get('/apps/manufacturing/materials/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.material_management.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('MaterialManagement::report.php', ['report' => $report]);
    return null;
});

$router->get('/apps/manufacturing/materials/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.material_management.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('MaterialManagement::export.php', ['report' => $report]);
    return null;
});

