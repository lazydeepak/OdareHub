<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\DailyOrdersController;

$renderDailyOrders = function() use ($view) {
    acl_require('daily_orders.index.view', '/daily-orders');
    DailyOrdersController::index($view);
    return null;
};
$router->get('/apps/manufacturing/daily-orders', $renderDailyOrders);
$router->get('/daily-orders', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/daily-orders' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderDailyOrder360 = function() use ($view) {
    acl_require('daily_orders.360.view', '/daily-orders/360?id=' . (int)($_GET['id'] ?? 0));

    DailyOrdersController::order360($view, (int)($_GET['id'] ?? 0));
    return null;
};
$router->get('/apps/manufacturing/daily-orders/360', $renderDailyOrder360);
$router->get('/daily-orders/360', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/daily-orders/360' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderDailyOrderAdd = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DailyOrdersController::addForm($view);
    return null;
};
$router->get('/apps/manufacturing/daily-orders/add', $renderDailyOrderAdd);
$router->get('/daily-orders/add', function() {
    header('Location: /apps/manufacturing/daily-orders/add', true, 302);
    exit;
});

$renderDailyOrderImport = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DailyOrdersController::importForm($view);
    return null;
};
$router->get('/apps/manufacturing/daily-orders/import', $renderDailyOrderImport);
$router->get('/daily-orders/import', function() {
    header('Location: /apps/manufacturing/daily-orders/import', true, 302);
    exit;
});

$importDailyOrders = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DailyOrdersController::importUpload($_FILES['import_file'] ?? null);
    header('Location: /apps/manufacturing/daily-orders');
    exit;
};
$router->post('/apps/manufacturing/daily-orders/import', $importDailyOrders);
$router->post('/daily-orders/import', $importDailyOrders);

$createDailyOrder = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DailyOrdersController::create($_POST);
    header('Location: /apps/manufacturing/daily-orders');
    exit;
};
$router->post('/apps/manufacturing/daily-orders/add', $createDailyOrder);
$router->post('/daily-orders/add', $createDailyOrder);

$renderDailyOrderEdit = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DailyOrdersController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
};
$router->get('/apps/manufacturing/daily-orders/edit', $renderDailyOrderEdit);
$router->get('/daily-orders/edit', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/daily-orders/edit' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$updateDailyOrder = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DailyOrdersController::update($_POST);
    header('Location: /apps/manufacturing/daily-orders');
    exit;
};
$router->post('/apps/manufacturing/daily-orders/edit', $updateDailyOrder);
$router->post('/daily-orders/edit', $updateDailyOrder);

$deleteDailyOrder = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DailyOrdersController::delete((int)($_POST['id'] ?? 0));
    header('Location: /apps/manufacturing/daily-orders');
    exit;
};
$router->post('/apps/manufacturing/daily-orders/delete', $deleteDailyOrder);
$router->post('/daily-orders/delete', $deleteDailyOrder);
