<?php
// Admin route registration for Architecture Health Board
use App\Admin\ArchitectureHealthController;

$router->get('/admin/architecture-health', function () use ($view) {
    ArchitectureHealthController::show($view);
    return null;
});
