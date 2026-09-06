<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Modules\AssemblyPlans\Controllers\AssemblyPlansController;
use Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanService;

require_once __DIR__ . '/Controllers/AssemblyPlansController.php';

$assemblyPlansIndex = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/assembly-plans');
    AssemblyPlanService::requireAvailability();
    AssemblyPlansController::index($view);
    return null;
};

$assemblyPlansDetail = function() use ($view) {
    $id = (int)($_GET['id'] ?? 0);
    Auth::requireRouteAccess('/manufacturing/assembly-plans/detail?id=' . $id);
    AssemblyPlanService::requireAvailability();
    AssemblyPlansController::detail($view, $id);
    return null;
};

$assemblyPlansReport = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/assembly-plans');
    AssemblyPlanService::requireAvailability();
    $view->render('AssemblyPlans::report.php', [
        'pageTitle' => 'Assembly Plan Report',
    ]);
    return null;
};

$assemblyPlansExport = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/assembly-plans');
    AssemblyPlanService::requireAvailability();
    $view->render('AssemblyPlans::export.php', [
        'pageTitle' => 'Assembly Plan Export',
    ]);
    return null;
};

$assemblyPlansUpdate = function() {
    Auth::requireRouteAccess('/manufacturing/assembly-plans', 'POST');
    AssemblyPlanService::requireAvailability();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);
    AssemblyPlansController::update($_POST);
    header('Location: /apps/manufacturing/assembly-plans/detail?id=' . $id);
    exit;
};

$assemblyPlansApprove = function() {
    Auth::requireAppAccess('manufacturing');
    AssemblyPlanService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $id = (int)($_POST['id'] ?? 0);
    AssemblyPlansController::approve($_POST);
    header('Location: /apps/manufacturing/assembly-plans/detail?id=' . $id);
    exit;
};

$assemblyPlansExecutionLog = function() {
    Auth::requireRouteAccess('/manufacturing/assembly-plans', 'POST');
    AssemblyPlanService::requireAvailability();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $planId = (int)($_POST['assembly_plan_id'] ?? 0);
    AssemblyPlansController::addExecution($_POST);
    header('Location: /apps/manufacturing/assembly-plans/detail?id=' . $planId);
    exit;
};

$assemblyPlansExecutionApprove = function() {
    Auth::requireAppAccess('manufacturing');
    AssemblyPlanService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $planId = (int)($_POST['assembly_plan_id'] ?? 0);
    AssemblyPlansController::approveExecution($_POST);
    header('Location: /apps/manufacturing/assembly-plans/detail?id=' . $planId);
    exit;
};

$assemblyPlansTransition = function() {
    Auth::requireRouteAccess('/manufacturing/assembly-plans', 'POST');
    AssemblyPlanService::requireAvailability();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    $planId = (int)($_POST['id'] ?? 0);
    AssemblyPlansController::transition($_POST);
    header('Location: /apps/manufacturing/assembly-plans/detail?id=' . $planId);
    exit;
};

$router->get('/apps/manufacturing/assembly-plans', $assemblyPlansIndex);
$router->get('/apps/manufacturing/assembly-workbench', function() {
    header('Location: /apps/manufacturing/assembly-plans?only_open=1', true, 302);
    exit;
});
$router->get('/apps/manufacturing/assembly-plans/report', $assemblyPlansReport);
$router->get('/apps/manufacturing/assembly-plans/export', $assemblyPlansExport);
$router->get('/apps/manufacturing/assembly-plans/detail', $assemblyPlansDetail);
$router->post('/apps/manufacturing/assembly-plans/update', $assemblyPlansUpdate);
$router->post('/apps/manufacturing/assembly-plans/approve', $assemblyPlansApprove);
$router->post('/apps/manufacturing/assembly-plans/execution-log', $assemblyPlansExecutionLog);
$router->post('/apps/manufacturing/assembly-plans/execution-approve', $assemblyPlansExecutionApprove);
$router->post('/apps/manufacturing/assembly-plans/transition', $assemblyPlansTransition);
