<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\ProductionPlansController;
use Apps\Manufacturing\Services\QueueBoardService;

$renderProductionPlans = function() use ($view) {
    Auth::requireRouteAccess('/production-plans');
    ProductionPlansController::index($view);
    return null;
};
$router->get('/apps/manufacturing/production-plans', $renderProductionPlans);
$router->get('/production-plans', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderProductionPlanAdd = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionPlansController::addForm($view);
    return null;
};
$router->get('/apps/manufacturing/production-plans/add', $renderProductionPlanAdd);
$router->get('/production-plans/add', function() {
    header('Location: /apps/manufacturing/production-plans/add', true, 302);
    exit;
});

$startProductionPlanDraft = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionPlansController::createDraft($_POST);
    return null;
};
$router->post('/apps/manufacturing/production-plans/start-draft', $startProductionPlanDraft);
$router->post('/production-plans/start-draft', $startProductionPlanDraft);

$createProductionPlan = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionPlansController::create($_POST);
    header('Location: /apps/manufacturing/production-plans');
    exit;
};
$router->post('/apps/manufacturing/production-plans/add', $createProductionPlan);
$router->post('/production-plans/add', $createProductionPlan);

$renderProductionPlanEdit = function() use ($view) {
    Auth::requireRouteAccess('/production-plans/edit?id=' . (int)($_GET['id'] ?? 0));
    ProductionPlansController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
};
$router->get('/apps/manufacturing/production-plans/edit', $renderProductionPlanEdit);
$router->get('/production-plans/edit', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/edit' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$updateProductionPlan = function() {
    Auth::requireRouteAccess('/production-plans', 'POST');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionPlansController::update($_POST);
    header('Location: /apps/manufacturing/production-plans');
    exit;
};
$router->post('/apps/manufacturing/production-plans/edit', $updateProductionPlan);
$router->post('/production-plans/edit', $updateProductionPlan);

$productionPlanApprovalAction = function() {
    acl_require_any([
        'workflow.production_plan.submit',
        'workflow.production_plan.approve',
        'workflow.production_plan.reopen',
    ], '/production-plans');

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionPlansController::approvalAction($_POST);
    return null;
};
$router->post('/apps/manufacturing/production-plans/approval-action', $productionPlanApprovalAction);
$router->post('/production-plans/approval-action', $productionPlanApprovalAction);

$deleteProductionPlan = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    ProductionPlansController::delete((int)($_POST['id'] ?? 0));
    header('Location: /apps/manufacturing/production-plans');
    exit;
};
$router->post('/apps/manufacturing/production-plans/delete', $deleteProductionPlan);
$router->post('/production-plans/delete', $deleteProductionPlan);

$printProductionPlanPdf = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionPlansController::printPdf((int)($_GET['id'] ?? 0), false);
};
$router->get('/apps/manufacturing/production-plans/print-pdf', $printProductionPlanPdf);
$router->get('/production-plans/print-pdf', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/print-pdf' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$downloadProductionPlanPdf = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    ProductionPlansController::printPdf((int)($_GET['id'] ?? 0), true);
};
$router->get('/apps/manufacturing/production-plans/download-pdf', $downloadProductionPlanPdf);
$router->get('/production-plans/download-pdf', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/download-pdf' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderProductionPlansNextTwoWeeks = function() use ($view) {
    Auth::requireRouteAccess('/production-plans/print-next-two-weeks');
    ProductionPlansController::printNextTwoWeeks($view);
    return null;
};
$router->get('/apps/manufacturing/production-plans/print-next-two-weeks', $renderProductionPlansNextTwoWeeks);
$router->get('/production-plans/print-next-two-weeks', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/print-next-two-weeks' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$printProductionPlansNextTwoWeeksPdf = function() {
    Auth::requireRouteAccess('/production-plans/print-next-two-weeks-pdf');
    ProductionPlansController::printNextTwoWeeksPdf();
};
$router->get('/apps/manufacturing/production-plans/print-next-two-weeks-pdf', $printProductionPlansNextTwoWeeksPdf);
$router->get('/production-plans/print-next-two-weeks-pdf', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/print-next-two-weeks-pdf' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});

$renderProductionPlansQueue = function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();

    $queueActive = ((string)(\App\Core\DB::fetchOne("SELECT status FROM core_apps WHERE app_key='manufacturing' LIMIT 1")['status'] ?? '')) === 'enabled';
    if ($queueActive) {
        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: /apps/manufacturing/production-queue' . ($qs !== '' ? ('?' . $qs) : ''));
        exit;
    }

    QueueBoardService::render($view, 'manufacturing::production_plans/queue.php');
    return null;
};
$router->get('/apps/manufacturing/production-plans/queue', $renderProductionPlansQueue);
$router->get('/production-plans/queue', function() {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $target = '/apps/manufacturing/production-plans/queue' . ($query !== '' ? ('?' . $query) : '');
    header('Location: ' . $target, true, 302);
    exit;
});
