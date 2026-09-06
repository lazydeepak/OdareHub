<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\DemandController;

$renderDemands = function() use ($view) {
    Auth::requireRouteAccess('/manufacturing/demands');
    DemandController::index($view);
    return null;
};

$router->get('/apps/manufacturing/demands', $renderDemands);
$router->get('/manufacturing/demands', function() {
    header('Location: /apps/manufacturing/demands', true, 302);
    exit;
});

$recalculateDemands = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DemandController::recalculate($_POST);
    header('Location: /apps/manufacturing/demands');
    exit;
};
$router->post('/apps/manufacturing/demands/recalculate', $recalculateDemands);
$router->post('/manufacturing/demands/recalculate', $recalculateDemands);

$adjustDemands = function() {
    Auth::requireRouteAccess('/manufacturing/demands', 'POST');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DemandController::adjust($_POST);
    header('Location: /apps/manufacturing/demands');
    exit;
};
$router->post('/apps/manufacturing/demands/adjust', $adjustDemands);
$router->post('/manufacturing/demands/adjust', $adjustDemands);

$approveDemands = function() {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    DemandController::approve($_POST);
    header('Location: /apps/manufacturing/demands');
    exit;
};
$router->post('/apps/manufacturing/demands/approve', $approveDemands);
$router->post('/manufacturing/demands/approve', $approveDemands);

$router->get('/manufacturing/demands/debug-decisions', function() {
    Auth::requireRouteAccess('/manufacturing/demands');
    DemandController::debugDecisions();
    return null;
});

$router->get('/manufacturing/demands/debug-work-buckets', function() {
    Auth::requireRouteAccess('/manufacturing/demands');
    DemandController::debugWorkBuckets();
    return null;
});

$router->get('/manufacturing/demands/debug-generate-work-executions', function() {
    Auth::requireRouteAccess('/manufacturing/demands');
    DemandController::debugGenerateWorkExecutions();
    return null;
});

$router->get('/manufacturing/demands/debug-generate-upstream-supply', function() {
    Auth::requireRouteAccess('/manufacturing/demands');
    DemandController::debugGenerateUpstreamSupply();
    return null;
});
