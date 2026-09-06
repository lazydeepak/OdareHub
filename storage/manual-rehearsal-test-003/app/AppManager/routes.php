<?php
declare(strict_types=1);

use App\AppManager\Controllers\AppManagerController;
use App\AppManager\Controllers\SearchController;
use App\AppManager\Controllers\SetupController;

$router->get('/api/search', function() {
    SearchController::search();
    return null;
});

$router->post('/api/search', function() {
    SearchController::search();
    return null;
});

$router->get('/admin/app-manager', function() use ($view) {
    AppManagerController::index($view);
    return null;
});

$router->post('/admin/app-manager/upload', function() {
    AppManagerController::upload();
    return null;
});

$router->post('/admin/app-manager/action', function() {
    AppManagerController::action();
    return null;
});

$router->get('/admin/app-manager/export', function() {
    AppManagerController::export();
    return null;
});

$router->get('/admin/app-manager/detail', function() use ($view) {
    AppManagerController::detail($view);
    return null;
});

$router->get('/admin/setup', function() use ($view) {
    SetupController::index($view);
    return null;
});

$router->get('/admin/setup/onboarding', function() use ($view) {
    SetupController::onboardingPage($view);
    return null;
});

$router->get('/admin/setup/core', function() use ($view) {
    SetupController::corePage($view);
    return null;
});

$router->get('/admin/setup/suites', function() use ($view) {
    SetupController::suitesPage($view);
    return null;
});

$router->get('/admin/setup/suites/detail', function() use ($view) {
    SetupController::suiteDetailPage($view);
    return null;
});

$router->get('/admin/setup/modules', function() use ($view) {
    SetupController::modulesPage($view);
    return null;
});

$router->get('/admin/setup/upgrades', function() use ($view) {
    SetupController::upgradesPage($view);
    return null;
});

$router->get('/admin/setup/health', function() use ($view) {
    SetupController::healthPage($view);
    return null;
});

$router->get('/admin/setup/environment', function() use ($view) {
    SetupController::environmentPage($view);
    return null;
});

$router->get('/admin/setup/config', function() use ($view) {
    SetupController::configPage($view);
    return null;
});

$router->get('/admin/setup/release', function() use ($view) {
    SetupController::releasePage($view);
    return null;
});

$router->get('/admin/setup/dependencies', function() use ($view) {
    SetupController::dependenciesPage($view);
    return null;
});

$router->get('/admin/setup/scaffolds', function() use ($view) {
    SetupController::scaffoldsPage($view);
    return null;
});

$router->get('/admin/setup/audit', function() use ($view) {
    SetupController::auditPage($view);
    return null;
});

$router->get('/admin/setup/complete', function() use ($view) {
    SetupController::completionPage($view);
    return null;
});

$router->post('/admin/setup/core', function() {
    SetupController::coreAction();
    return null;
});

$router->post('/admin/setup/profile', function() {
    SetupController::platformProfileAction();
    return null;
});

$router->post('/admin/setup/suite', function() {
    SetupController::suiteAction();
    return null;
});

$router->post('/admin/setup/module', function() {
    SetupController::moduleAction();
    return null;
});

$router->post('/admin/setup/scaffold', function() {
    SetupController::scaffoldAction();
    return null;
});

$router->post('/admin/setup/upgrades/preview', function() {
    SetupController::upgradePreviewAction();
    return null;
});

$router->post('/admin/setup/upgrades/apply', function() {
    SetupController::upgradeApplyAction();
    return null;
});

$router->post('/admin/setup/upgrades/cancel', function() {
    SetupController::upgradeCancelAction();
    return null;
});

$router->post('/admin/setup/environment/snapshot', function() {
    SetupController::environmentSnapshotAction();
    return null;
});

$router->post('/admin/setup/environment/preview', function() {
    SetupController::environmentPreviewAction();
    return null;
});

$router->post('/admin/setup/environment/apply', function() {
    SetupController::environmentApplyAction();
    return null;
});

$router->post('/admin/setup/environment/cancel', function() {
    SetupController::environmentCancelAction();
    return null;
});

$router->post('/admin/setup/config/save', function() {
    SetupController::configSaveAction();
    return null;
});

$router->post('/admin/setup/config/import', function() {
    SetupController::configImportAction();
    return null;
});

$router->post('/admin/setup/release/preview', function() {
    SetupController::releasePreviewAction();
    return null;
});

$router->post('/admin/setup/release/generate', function() {
    SetupController::releaseGenerateAction();
    return null;
});

$router->post('/admin/setup/release/cancel', function() {
    SetupController::releaseCancelAction();
    return null;
});

$router->get('/admin/setup/environment/download', function() {
    SetupController::environmentDownloadAction();
    return null;
});

$router->get('/admin/setup/config/export', function() {
    SetupController::configExportAction();
    return null;
});

$router->get('/admin/setup/release/download', function() {
    SetupController::releaseDownloadAction();
    return null;
});

$router->post('/admin/setup/verify', function() {
    SetupController::verifyAction();
    return null;
});

$router->post('/admin/setup/recover', function() {
    SetupController::recoverAction();
    return null;
});
