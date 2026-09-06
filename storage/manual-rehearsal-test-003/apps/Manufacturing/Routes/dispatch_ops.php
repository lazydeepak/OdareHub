<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\DispatchOpsController;

$renderDispatchOps = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/dispatch-ops');
    DispatchOpsController::index($view);
    return null;
};
$router->get('/apps/manufacturing/dispatch-ops', $renderDispatchOps);
$router->get('/manufacturing/dispatch-ops', function() {
    header('Location: /apps/manufacturing/dispatch-ops', true, 302);
    exit;
});

$router->get('/manufacturing/dispatch-queue', function() {
    header('Location: /apps/manufacturing/dispatch-ops', true, 302);
    exit;
});

$renderDispatchPreparation = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/dispatch-ops/preparation');
    DispatchOpsController::preparationForm($view);
    return null;
};
$router->get('/apps/manufacturing/dispatch-ops/preparation', $renderDispatchPreparation);
$router->get('/manufacturing/dispatch-ops/preparation', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/dispatch-ops/preparation' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$markDispatchReady = function() {
    Auth::requireRouteAccess('/manufacturing/dispatch-ops', 'POST');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchOpsController::markReady($_POST);
    header('Location: /apps/manufacturing/dispatch-ops');
    exit;
};
$router->post('/apps/manufacturing/dispatch-ops/ready', $markDispatchReady);
$router->post('/manufacturing/dispatch-ops/ready', $markDispatchReady);

$prepareDispatch = function() {
    Auth::requireRouteAccess('/manufacturing/dispatch-ops', 'POST');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchOpsController::prepare($_POST);
    header('Location: /apps/manufacturing/dispatch-ops');
    exit;
};
$router->post('/apps/manufacturing/dispatch-ops/prepare', $prepareDispatch);
$router->post('/manufacturing/dispatch-ops/prepare', $prepareDispatch);

$saveDispatchPreparation = function() {
    Auth::requireRouteAccess('/manufacturing/dispatch-ops/preparation', 'POST');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchOpsController::savePreparation($_POST);
    $redirect = '/apps/manufacturing/dispatch-ops/preparation';
    $params = [];
    $dispatchDate = trim((string)($_POST['dispatch_date'] ?? ''));
    $productId = (int)($_POST['product_id'] ?? 0);
    $dailyOrderId = (int)($_POST['daily_order_id'] ?? 0);
    if ($dispatchDate !== '') {
        $params['dispatch_date'] = $dispatchDate;
    }
    if ($productId > 0) {
        $params['product_id'] = (string)$productId;
    }
    if ($dailyOrderId > 0) {
        $params['daily_order_id'] = (string)$dailyOrderId;
    }
    if ($params !== []) {
        $redirect .= '?' . http_build_query($params);
    }
    header('Location: ' . $redirect);
    exit;
};
$router->post('/apps/manufacturing/dispatch-ops/preparation', $saveDispatchPreparation);
$router->post('/manufacturing/dispatch-ops/preparation', $saveDispatchPreparation);

$completeDispatch = function() {
    Auth::requireRouteAccess('/manufacturing/dispatch-ops', 'POST');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchOpsController::complete($_POST);
    header('Location: /apps/manufacturing/dispatch-ops');
    exit;
};
$router->post('/apps/manufacturing/dispatch-ops/complete', $completeDispatch);
$router->post('/manufacturing/dispatch-ops/complete', $completeDispatch);
