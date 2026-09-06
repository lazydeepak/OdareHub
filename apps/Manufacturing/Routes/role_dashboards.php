<?php
declare(strict_types=1);

use Plugins\Base\Controllers\RoleDashboardsController;

$roleDashboardsControllerPath = APP_ROOT . '/plugins/Base/Controllers/RoleDashboardsController.php';
if (!class_exists(RoleDashboardsController::class) && is_file($roleDashboardsControllerPath)) {
    require_once $roleDashboardsControllerPath;
}

$legacyDashboardRedirectHeaders = static function(string $sunset = 'Fri, 31 Jul 2026 23:59:59 GMT'): void {
    header('Deprecation: true');
    header('Sunset: ' . $sunset);
};

$router->get('/apps/manufacturing/production-dashboard', function () use ($view) {
    RoleDashboardsController::render($view, 'production_leader');
    return null;
});

$router->get('/apps/manufacturing/assembly-dashboard', function () use ($view) {
    RoleDashboardsController::render($view, 'assembly_leader');
    return null;
});

$router->get('/apps/manufacturing/qc-dashboard', function () use ($view) {
    RoleDashboardsController::render($view, 'qc_leader');
    return null;
});

$router->get('/apps/manufacturing/dispatch-dashboard', function () use ($view) {
    RoleDashboardsController::render($view, 'dispatch_leader');
    return null;
});
