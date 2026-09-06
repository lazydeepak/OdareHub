<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\DemandExecutionController;
use Apps\Manufacturing\Controllers\ProcessingOperationController;
use Apps\Manufacturing\Controllers\ProductionOperationController;

$router->get('/apps/manufacturing', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DemandExecutionController::dashboard($view);
    return null;
});

$router->get('/apps/manufacturing/production-operation', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionOperationController::index($view);
    return null;
});

$router->get('/apps/manufacturing/processing-operation', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProcessingOperationController::index($view);
    return null;
});

$router->post('/apps/manufacturing/processing-operation/assembly-update', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProcessingOperationController::updateAssembly($_POST);
    $redirect = (string)($_POST['redirect'] ?? '/apps/manufacturing/processing-operation');
    header('Location: ' . (str_starts_with($redirect, '/') ? $redirect : '/apps/manufacturing/processing-operation'));
    exit;
});

$router->post('/apps/manufacturing/processing-operation/qc-update', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProcessingOperationController::updateQc($_POST);
    $redirect = (string)($_POST['redirect'] ?? '/apps/manufacturing/processing-operation');
    header('Location: ' . (str_starts_with($redirect, '/') ? $redirect : '/apps/manufacturing/processing-operation'));
    exit;
});

$router->get('/apps/manufacturing/qc-queue', function() use ($view) {
    // Keep ACL bound to existing route key while the permission map is migrated.
    Auth::requireRouteAccess('/manufacturing/qc-demand-queue');
    DemandExecutionController::qcQueue($view);
    return null;
});

$router->get('/manufacturing/qc-queue', function() {
    $query = http_build_query($_GET);
    header('Location: /apps/manufacturing/qc-queue' . ($query !== '' ? ('?' . $query) : ''), true, 302);
    exit;
});

$router->get('/manufacturing/qc-demand-queue', function() {
    header('Location: /manufacturing/qc-queue', true, 302);
    exit;
});

$legacyAssemblyRedirectHeaders = static function(string $sunset = 'Fri, 31 Jul 2026 23:59:59 GMT'): void {
    header('Deprecation: true');
    header('Sunset: ' . $sunset);
    header('Link: </apps/manufacturing/assembly-queue>; rel="successor-version"');
};

// Legacy compat redirects for assembly queue: only register when AssemblyEntries module is active.
if (!isset($c) || $c->get('plugins')->status('AssemblyEntries') === 'active') {
    $router->get('/manufacturing/assembly-queue', function() use ($legacyAssemblyRedirectHeaders) {
        $legacyAssemblyRedirectHeaders();
        $query = http_build_query($_GET);
        header('Location: /apps/manufacturing/assembly-queue' . ($query !== '' ? ('?' . $query) : ''), true, 302);
        exit;
    });

    $router->get('/manufacturing/assembly-demand-queue', function() use ($legacyAssemblyRedirectHeaders) {
        $legacyAssemblyRedirectHeaders();
        header('Location: /apps/manufacturing/assembly-queue', true, 302);
        exit;
    });
}

$router->get('/manufacturing/execution-dashboard', function() {
    // Canonical execution dashboard lives at /apps/manufacturing
    header('Location: /apps/manufacturing', true, 302);
    exit;
});
