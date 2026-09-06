<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Controllers\HandoffBoardController;
use Apps\Manufacturing\Services\HandoffBoardService;

$router->get('/apps/manufacturing/handoffs', function () use ($view) {
    Auth::bootSession();
    Auth::requireRouteAccess('/apps/manufacturing/handoffs');

    if (!HandoffBoardService::isAvailable()) {
        header('Location: /me', true, 302);
        exit;
    }

    HandoffBoardController::index($view, Auth::user());
    return null;
});

$router->post('/apps/manufacturing/handoffs/assign-owner', function () {
    Auth::bootSession();
    Auth::requireRouteAccess('/apps/manufacturing/handoffs');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    if (!HandoffBoardService::isAvailable()) {
        header('Location: /me', true, 302);
        exit;
    }

    HandoffBoardController::assignOwner($_POST);
    return null;
});

$router->post('/apps/manufacturing/handoffs/escalate', function () {
    Auth::bootSession();
    Auth::requireRouteAccess('/apps/manufacturing/handoffs');
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    if (!HandoffBoardService::isAvailable()) {
        header('Location: /me', true, 302);
        exit;
    }

    HandoffBoardController::escalate($_POST);
    return null;
});
