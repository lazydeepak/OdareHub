<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\QCPlans\Controllers\QCPlansController;

require_once __DIR__ . '/Controllers/QCPlansController.php';

$router->get('/qc-plans', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCPlansController::index($view);
    return null;
});

$router->get('/qc-plans/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCPlansController::addForm($view);
    return null;
});

$router->post('/qc-plans/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCPlansController::create($_POST);
    header('Location: /qc-plans');
    exit;
});

$router->get('/qc-plans/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCPlansController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/qc-plans/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCPlansController::update($_POST);
    header('Location: /qc-plans');
    exit;
});

$router->post('/qc-plans/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCPlansController::delete((int)($_POST['id'] ?? 0));
    header('Location: /qc-plans');
    exit;
});

$router->get('/qc-plans/print-pdf', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCPlansController::printRangePdf($_GET);
    return null;
});

$router->get('/qc-plans/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();

    $report = ModuleReportRegistryService::findActiveReport('manufacturing.qc_plans.range');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }

    $view->render('QCPlans::report.php', ['report' => $report]);
    return null;
});

$router->get('/qc-plans/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();

    $report = ModuleReportRegistryService::findActiveReport('manufacturing.qc_plans.range');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }

    $view->render('QCPlans::export.php', ['report' => $report]);
    return null;
});
