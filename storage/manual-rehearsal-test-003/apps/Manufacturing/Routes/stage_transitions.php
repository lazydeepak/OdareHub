<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\StageTransitionController;

$router->get('/apps/manufacturing/stage-board', function () use ($view) {
    StageTransitionController::boardAction($view);
});

$router->get('/manufacturing/stage-board', function () {
    $query = http_build_query($_GET);
    header('Location: /apps/manufacturing/stage-board' . ($query !== '' ? ('?' . $query) : ''), true, 302);
    exit;
});

$router->post('/apps/manufacturing/stage-release', function () {
    StageTransitionController::releaseAction();
});

$router->post('/manufacturing/stage-release', function () {
    StageTransitionController::releaseAction();
});

$router->post('/apps/manufacturing/stage-override', function () {
    StageTransitionController::overrideAction();
});

$router->post('/manufacturing/stage-override', function () {
    StageTransitionController::overrideAction();
});
