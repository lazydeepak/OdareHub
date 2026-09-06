<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\ModuleReportRegistryService;
use Plugins\Ledger\Controllers\LedgerController;

require_once __DIR__ . '/Controllers/LedgerController.php';

$router->get('/ledger', function () use ($view) {
	Auth::requireAdmin();
	Auth::bootSession();
	LedgerController::index($view);
	return null;
});

$router->get('/ledger/add', function () use ($view) {
	Auth::requireAdmin();
	Auth::bootSession();
	LedgerController::addForm($view);
	return null;
});

$router->get('/ledger/reconcile', function () use ($view) {
	Auth::requireAdmin();
	Auth::bootSession();
	LedgerController::reconcile($view);
	return null;
});

$router->post('/ledger/reconcile/repair', function () {
	Auth::requireAdmin();
	Auth::bootSession();
	Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
	LedgerController::repairProduct((int)($_POST['product_id'] ?? 0));
	header('Location: /ledger/reconcile?mismatches_only=1');
	exit;
});

$router->post('/ledger/add', function () {
	Auth::requireAdmin();
	Auth::bootSession();
	Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
	LedgerController::create($_POST);
	header('Location: /ledger');
	exit;
});

$router->post('/ledger/rebuild', function () {
	Auth::requireAdmin();
	Auth::bootSession();
	Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
	LedgerController::rebuild();
	header('Location: /ledger');
	exit;
});

$router->get('/ledger/report', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.ledger.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.report_unavailable');
        return null;
    }
    $view->render('Ledger::report.php', ['report' => $report]);
    return null;
});

$router->get('/ledger/export', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    $report = ModuleReportRegistryService::findActiveReport('manufacturing.ledger.overview');
    if (!is_array($report)) {
        http_response_code(404);
        echo t('common.export_unavailable');
        return null;
    }
    $view->render('Ledger::export.php', ['report' => $report]);
    return null;
});

