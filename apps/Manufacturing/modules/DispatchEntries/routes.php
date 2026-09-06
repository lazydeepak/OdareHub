<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\DispatchEntries\Controllers\DispatchEntriesController;

require_once __DIR__ . '/Controllers/DispatchEntriesController.php';

// Legacy route - redirect to canonical dispatch ops route for backward compatibility
$router->get('/dispatch-entries/leader', function() use ($view) {
    header('Location: /manufacturing/dispatch-ops', true, 302);
    return null;
});

$router->get('/dispatch-entries', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DispatchEntriesController::index($view);
    return null;
});

$router->get('/dispatch-entries/add', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DispatchEntriesController::addForm($view);
    return null;
});

$router->post('/dispatch-entries/start-draft', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::createDraft($_POST);
    return null;
});

$router->post('/dispatch-entries/add', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::create($_POST);
    header('Location: /dispatch-entries');
    exit;
});

$router->get('/dispatch-entries/edit', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DispatchEntriesController::editForm($view, (int)($_GET['id'] ?? 0));
    return null;
});

$router->post('/dispatch-entries/edit', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::update($_POST);
    header('Location: /dispatch-entries');
    exit;
});

$router->post('/dispatch-entries/delete', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::delete((int)($_POST['id'] ?? 0));
    header('Location: /dispatch-entries');
    exit;
});

$router->post('/dispatch-entries/quick-status', function() {
    acl_require('dispatch_entries.quick_status', '/dispatch-entries/leader');
    $user = Auth::user();

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::quickStatusUpdate(
        (int)($_POST['id'] ?? 0),
        trim((string)($_POST['status'] ?? '')),
        trim((string)($_POST['redirect'] ?? '/dispatch-entries/leader')),
        trim((string)($_POST['reason'] ?? '')),
        trim((string)($_POST['note'] ?? '')),
        $user
    );
    return null;
});

$router->post('/dispatch-entries/transition', function() {
    acl_require('dispatch_entries.transition', '/dispatch-entries/leader');
    $user = Auth::user();

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::transition($_POST, $user);
    return null;
});

$router->post('/dispatch-entries/approval-action', function() {
    acl_require_any([
        'workflow.dispatch_entry.submit',
        'workflow.dispatch_entry.approve',
        'workflow.dispatch_entry.reopen',
        'workflow.dispatch_entry.finalize',
    ], '/dispatch-entries/leader');

    $user = Auth::user();

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DispatchEntriesController::approvalAction($_POST, $user);
    return null;
});

$router->get('/dispatch-entries/print-pdf', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DispatchEntriesController::printPdf((int)($_GET['id'] ?? 0), false);
    return null;
});

$router->get('/dispatch-entries/download-pdf', function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    DispatchEntriesController::printPdf((int)($_GET['id'] ?? 0), true);
    return null;
});

$router->get('/dispatch-entries/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();

    $report = ModuleReportRegistryService::findActiveReport('manufacturing.dispatch_entries.execution');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }

    $view->render('DispatchEntries::report.php', ['report' => $report]);
    return null;
});

$router->get('/dispatch-entries/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();

    $report = ModuleReportRegistryService::findActiveReport('manufacturing.dispatch_entries.execution');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }

    $view->render('DispatchEntries::export.php', ['report' => $report]);
    return null;
});
