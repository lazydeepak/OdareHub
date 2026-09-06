<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\PartMachineMap\Controllers\PartMachineMapController;

require_once __DIR__ . '/Controllers/PartMachineMapController.php';

$router->get('/part-machine-map', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PartMachineMapController::index($view);
    return null;
});

$router->get('/part-machine-map/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PartMachineMapController::addForm($view);
    return null;
});

$router->post('/part-machine-map/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PartMachineMapController::create($_POST);
    header('Location: /part-machine-map');
    exit;
});

$router->get('/part-machine-map/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    PartMachineMapController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/part-machine-map/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PartMachineMapController::update($_POST);
    header('Location: /part-machine-map');
    exit;
});

$router->post('/part-machine-map/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    PartMachineMapController::delete((int)($_POST['id'] ?? 0));
    header('Location: /part-machine-map');
    exit;
});

$router->get('/part-machine-map/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.part_machine_map.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('PartMachineMap::report.php', ['report' => $report]);
    return null;
});

$router->get('/part-machine-map/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.part_machine_map.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('PartMachineMap::export.php', ['report' => $report]);
    return null;
});

