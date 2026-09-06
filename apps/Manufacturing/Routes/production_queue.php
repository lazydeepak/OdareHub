<?php
declare(strict_types=1);

use App\Core\Auth;
use Apps\Manufacturing\Services\QueueBoardService;

$router->get('/apps/manufacturing/production-queue', function() use ($view) {
    Auth::requireAppAccess('manufacturing');
    Auth::bootSession();
    QueueBoardService::render($view);
    return null;
});

// Legacy aliases — redirect to canonical /apps/manufacturing/production-queue.
$router->get('/manufacturing/production-queue', function() {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /apps/manufacturing/production-queue' . ($qs !== '' ? '?' . $qs : ''), true, 301);
    exit;
});

$router->get('/production-queue', function() {
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: /apps/manufacturing/production-queue' . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
});
