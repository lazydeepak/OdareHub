<?php

declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Modules\Bom\BomService;
use Apps\Manufacturing\Modules\Bom\Controllers\BomController;

require_once __DIR__ . '/Controllers/BomController.php';

$bomIndex = function () use ($view) {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    BomController::index($view);
    return null;
};

$bomDetail = function () use ($view) {
    $id = (int)($_GET['id'] ?? 0);
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    BomController::detail($view, $id);
    return null;
};

$bomForm = function () use ($view) {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    BomController::form($view);
    return null;
};

$bomCreate = function () {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BomController::create($_POST);
    exit;
};

$bomUpdateHeader = function () {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BomController::updateHeader($_POST);
    exit;
};

$bomStatus = function () {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BomController::transition($_POST);
    exit;
};

$bomLineAdd = function () {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BomController::addLine($_POST);
    exit;
};

$bomLineUpdate = function () {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BomController::updateLine($_POST);
    exit;
};

$bomLineDelete = function () {
    Auth::requireAppAccess('manufacturing');
    BomService::requireAvailability();
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    BomController::deleteLine($_POST);
    exit;
};

$router->get('/apps/manufacturing/bom', $bomIndex);
$router->get('/apps/manufacturing/bom/detail', $bomDetail);
$router->get('/apps/manufacturing/bom/add', $bomForm);
$router->post('/apps/manufacturing/bom/add', $bomCreate);
$router->post('/apps/manufacturing/bom/update', $bomUpdate);
$router->post('/apps/manufacturing/bom/status', $bomStatus);
$router->post('/apps/manufacturing/bom/lines/add', $bomLineAdd);
$router->post('/apps/manufacturing/bom/lines/update', $bomLineUpdate);
$router->post('/apps/manufacturing/bom/lines/delete', $bomLineDelete);