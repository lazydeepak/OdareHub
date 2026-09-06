<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Procurement\Controllers\ProcurementDashboardController;
use Apps\Procurement\Services\ProcurementOverviewService;

require_once __DIR__ . '/Controllers/ProcurementDashboardController.php';
require_once __DIR__ . '/Services/ProcurementOverviewService.php';
require_once __DIR__ . '/bootstrap.php';

$view->addNamespace('procurement', __DIR__ . '/Views');

$router->get('/apps/procurement', function () use ($view) {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::dashboard($view);
    return null;
});

$router->get('/apps/procurement/requests', function () use ($view) {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::requests($view);
    return null;
});

$router->get('/apps/procurement/orders', function () use ($view) {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::orders($view);
    return null;
});

$router->get('/apps/procurement/receipts', function () use ($view) {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::receipts($view);
    return null;
});

$router->post('/apps/procurement/suppliers/create', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::createSupplier();
    return null;
});

$router->post('/apps/procurement/requests/create', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::createRequest();
    return null;
});

$router->post('/apps/procurement/requests/intake/manufacturing', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::intakeManufacturingRequest();
    return null;
});

$router->post('/apps/procurement/requests/approve', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::approveRequest();
    return null;
});

$router->post('/apps/procurement/orders/create', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::createOrder();
    return null;
});

$router->post('/apps/procurement/orders/create-from-request', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::createOrderFromRequest();
    return null;
});

$router->post('/apps/procurement/orders/issue', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::issueOrder();
    return null;
});

$router->post('/apps/procurement/receipts/create', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::createReceipt();
    return null;
});

$router->post('/apps/procurement/receipts/post', function () {
    Auth::requireAppAccess('procurement');
    Auth::bootSession();
    ProcurementOverviewService::ensureSchema();
    ProcurementDashboardController::postReceipt();
    return null;
});
