<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Modules\AssemblyEntries\Controllers\AssemblyEntriesController;

require_once __DIR__ . '/Controllers/AssemblyEntriesController.php';

$assemblyQueueHandler = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/assembly-demand-queue');
    AssemblyEntriesController::queue($view);
    return null;
};

$assemblyQueueReport = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/assembly-demand-queue');
    $view->render('AssemblyEntries::report.php', [
        'pageTitle' => 'Assembly Execution Report',
    ]);
    return null;
};

$assemblyQueueExport = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/assembly-demand-queue');
    $view->render('AssemblyEntries::export.php', [
        'pageTitle' => 'Assembly Entry Export',
    ]);
    return null;
};

$router->get('/apps/manufacturing/assembly-queue', $assemblyQueueHandler);
$router->get('/apps/manufacturing/assembly-queue/report', $assemblyQueueReport);
$router->get('/apps/manufacturing/assembly-queue/export', $assemblyQueueExport);
$router->get('/apps/manufacturing/assembly-demand-queue', function() {
    header('Location: /apps/manufacturing/assembly-queue', true, 302);
    exit;
});
