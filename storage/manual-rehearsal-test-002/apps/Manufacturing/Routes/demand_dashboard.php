<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\DemandDashboardController;

$router->get('/apps/manufacturing/demand-dashboard', function () use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DemandDashboardController::index($view);
    return null;
});
$router->get('/manufacturing/demand-dashboard', function () {
    header('Location: /apps/manufacturing/demand-dashboard', true, 302);
    exit;
});
