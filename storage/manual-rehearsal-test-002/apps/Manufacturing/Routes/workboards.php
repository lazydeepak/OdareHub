<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\PackageManager;
use Plugins\Machines\Controllers\MachinesController;
use Plugins\QCEntries\Controllers\QCEntriesController;
use Plugins\DispatchEntries\Controllers\DispatchEntriesController;

if (!class_exists(MachinesController::class)) {
    require_once rtrim(PackageManager::pluginSourcePath('Machines'), '/') . '/Controllers/MachinesController.php';
}
if (!class_exists(QCEntriesController::class)) {
    require_once rtrim(PackageManager::pluginSourcePath('QCEntries'), '/') . '/Controllers/QCEntriesController.php';
}
if (!class_exists(DispatchEntriesController::class)) {
    require_once rtrim(PackageManager::pluginSourcePath('DispatchEntries'), '/') . '/Controllers/DispatchEntriesController.php';
}

// Canonical manufacturing workboard routes (clean functional naming)

// Production execution workboard - where production leaders manage workflow transitions
$router->get('/apps/manufacturing/production-workboard', function() use ($view) {
    acl_require('machines.leader.view', '/manufacturing/production-workboard');
    $user = Auth::user();
    MachinesController::leaderDashboard($view, $user);
    return null;
});
$router->get('/manufacturing/production-workboard', function() {
    header('Location: /apps/manufacturing/production-workboard', true, 302);
    exit;
});

// QC execution workboard - where QC leaders manage workflow transitions
$router->get('/apps/manufacturing/qc-workboard', function() use ($view) {
    acl_require('qc_entries.leader.view', '/manufacturing/qc-workboard');
    $user = Auth::user();
    QCEntriesController::leaderDashboard($view, $user);
    return null;
});
$router->get('/manufacturing/qc-workboard', function() {
    header('Location: /apps/manufacturing/qc-workboard', true, 302);
    exit;
});

// Dispatch execution workboard - where dispatch leaders manage workflow transitions
$router->get('/apps/manufacturing/dispatch-workboard', function() use ($view) {
    acl_require('dispatch_entries.leader.view', '/manufacturing/dispatch-workboard');
    $user = Auth::user();
    DispatchEntriesController::leaderDashboard($view, $user);
    return null;
});
$router->get('/manufacturing/dispatch-workboard', function() {
    header('Location: /apps/manufacturing/dispatch-workboard', true, 302);
    exit;
});

// Assembly execution workboard currently maps to the assembly queue surface.
$router->get('/apps/manufacturing/assembly-workboard', function() {
    header('Location: /apps/manufacturing/assembly-queue', true, 302);
    exit;
});

$router->get('/manufacturing/assembly-workboard', function() {
    header('Location: /apps/manufacturing/assembly-queue', true, 302);
    exit;
});
