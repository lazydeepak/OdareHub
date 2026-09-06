<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\QCEntries\Controllers\QCEntriesController;

require_once __DIR__ . '/Controllers/QCEntriesController.php';

// Legacy route - redirect to canonical workboard route for backward compatibility
$router->get('/qc-entries/leader', function() use ($view) {
    header('Location: /manufacturing/qc-workboard', true, 302);
    return null;
});

$router->get('/qc-entries', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCEntriesController::index($view);
    return null;
});

$router->get('/qc-entries/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCEntriesController::addForm($view);
    return null;
});

$router->post('/qc-entries/start-draft', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCEntriesController::createDraft($_POST);
    return null;
});

$router->post('/qc-entries/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCEntriesController::create($_POST);
    header('Location: /qc-entries');
    exit;
});

$router->get('/qc-entries/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QCEntriesController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/qc-entries/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCEntriesController::update($_POST);
    header('Location: /qc-entries');
    exit;
});

$router->post('/qc-entries/approval-action', function() {
    acl_require_any([
        'workflow.qc_entry.submit',
        'workflow.qc_entry.approve',
        'workflow.qc_entry.reopen',
    ], '/qc-entries/leader');

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCEntriesController::approvalAction($_POST);
    return null;
});

$router->post('/qc-entries/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    QCEntriesController::delete((int)($_POST['id'] ?? 0));
    header('Location: /qc-entries');
    exit;
});

$router->get('/qc-entries/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.qcentries.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('QCEntries::report.php', ['report' => $report]);
    return null;
});

$router->get('/qc-entries/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.qcentries.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('QCEntries::export.php', ['report' => $report]);
    return null;
});

