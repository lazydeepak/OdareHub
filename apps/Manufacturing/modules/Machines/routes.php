<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\Machines\Controllers\MachinesController;

require_once __DIR__ . '/Controllers/MachinesController.php';

// Legacy route - redirect to canonical workboard route for backward compatibility
$router->get('/machines/leader', function() use ($view) {
    header('Location: /manufacturing/production-workboard', true, 302);
    return null;
});

$router->get('/machines', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    MachinesController::index($view);
    return null;
});

$router->get('/machines/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    MachinesController::addForm($view);
    return null;
});

$router->get('/machines/detail', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    MachinesController::detail($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/machines/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MachinesController::create($_POST);
    header('Location: /machines');
    exit;
});

$router->get('/machines/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    MachinesController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/machines/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MachinesController::update($_POST);
    header('Location: /machines');
    exit;
});

$router->post('/machines/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MachinesController::delete((int)($_POST['id'] ?? 0));
    header('Location: /machines');
    exit;
});

$router->get('/machines/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.machines.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('Machines::report.php', ['report' => $report]);
    return null;
});

$router->get('/machines/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.machines.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('Machines::export.php', ['report' => $report]);
    return null;
});

