<?php
declare(strict_types=1);

use App\Core\PackageManager;

require_once __DIR__ . '/Services/QueueBoardService.php';
require_once __DIR__ . '/Services/Order360Service.php';
require_once __DIR__ . '/Services/DemandRouteDecisionService.php';
require_once __DIR__ . '/Services/DemandWorkBucketService.php';
require_once __DIR__ . '/Services/DemandWorkExecutionGeneratorService.php';
require_once __DIR__ . '/Services/UpstreamSupplyGenerationService.php';
require_once __DIR__ . '/Services/DemandEngineService.php';
require_once __DIR__ . '/Services/DispatchOpsService.php';
require_once __DIR__ . '/Services/DemandExecutionService.php';
require_once __DIR__ . '/Services/AdminRouteRegistryService.php';
require_once __DIR__ . '/Services/StageTransitionService.php';
require_once __DIR__ . '/Services/AssemblyPlanService.php';
require_once __DIR__ . '/Controllers/ProductionPlansController.php';
require_once __DIR__ . '/Controllers/DailyOrdersController.php';
require_once __DIR__ . '/Controllers/ManufacturingExportController.php';
require_once __DIR__ . '/Controllers/ManufacturingImportController.php';
require_once __DIR__ . '/Controllers/ManufacturingRestoreController.php';
require_once __DIR__ . '/Controllers/DemandController.php';
require_once __DIR__ . '/Controllers/DispatchOpsController.php';
require_once __DIR__ . '/Controllers/DemandExecutionController.php';
require_once __DIR__ . '/Controllers/StageTransitionController.php';
require_once __DIR__ . '/Controllers/AssemblyPlansController.php';
require_once __DIR__ . '/Controllers/DemandDashboardController.php';
require_once __DIR__ . '/Controllers/HandoffBoardController.php';
require_once __DIR__ . '/Controllers/ProcessingOperationController.php';
require_once __DIR__ . '/Controllers/ProductionOperationController.php';
require_once __DIR__ . '/Services/HandoffBoardService.php';
require_once __DIR__ . '/Services/ManufacturingLegacyImportService.php';
require_once __DIR__ . '/Services/ProcessingOperationService.php';
require_once __DIR__ . '/Services/ProductionOperationService.php';
require_once __DIR__ . '/Routes/production_queue.php';
require_once __DIR__ . '/Routes/production_plans.php';
require_once __DIR__ . '/Routes/daily_orders.php';
require_once __DIR__ . '/Routes/demands.php';
require_once __DIR__ . '/Routes/dispatch_ops.php';
require_once __DIR__ . '/Routes/demand_dashboard.php';
require_once __DIR__ . '/Routes/execution.php';
require_once __DIR__ . '/Routes/workboards.php';
require_once __DIR__ . '/Routes/role_dashboards.php';
require_once __DIR__ . '/Routes/stage_transitions.php';
require_once __DIR__ . '/Routes/assembly_plans.php';
require_once __DIR__ . '/Routes/handoffs.php';
require_once __DIR__ . '/Routes/compat.php';
require_once __DIR__ . '/bootstrap.php';

$router->get('/apps/manufacturing/imports', function () use ($view) {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingImportController::index($view);
    return null;
});

$router->get('/apps/manufacturing/exports', function () use ($view) {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingExportController::index($view);
    return null;
});

$router->get('/apps/manufacturing/restores', function () use ($view) {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingRestoreController::index($view);
    return null;
});

$router->post('/apps/manufacturing/imports/preview', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingImportController::previewImport();
    return null;
});

$router->post('/apps/manufacturing/imports/commit', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingImportController::commitImport();
    return null;
});

$router->post('/apps/manufacturing/exports/suite', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingExportController::exportSuite();
    return null;
});

$router->post('/apps/manufacturing/exports/module', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingExportController::exportModule();
    return null;
});

$router->post('/apps/manufacturing/restores/preview', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingRestoreController::previewRestore();
    return null;
});

$router->post('/apps/manufacturing/restores/commit', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingRestoreController::commitRestore();
    return null;
});

$router->post('/apps/manufacturing/restores/cancel', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingRestoreController::cancelPreview();
    return null;
});

$router->get('/apps/manufacturing/exports/download', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingExportController::download();
    return null;
});

$router->get('/apps/manufacturing/imports/template', function () {
    \App\Core\Auth::requireAppAccess('manufacturing');
    \App\Core\Auth::bootSession();
    \Apps\Manufacturing\Controllers\ManufacturingImportController::downloadTemplate();
    return null;
});

$manifestPath = __DIR__ . '/manifest.json';
$manifest = is_file($manifestPath)
    ? json_decode((string)file_get_contents($manifestPath), true)
    : null;

$legacyPlugins = is_array($manifest) && is_array($manifest['legacy_bridge_plugins'] ?? null)
    ? array_values(array_map('strval', (array)$manifest['legacy_bridge_plugins']))
    : [];
$pluginManager = isset($c) ? $c->get('plugins') : null;

foreach ($legacyPlugins as $legacyPlugin) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $legacyPlugin)) {
        continue;
    }

    // Installed legacy modules are loaded through the module/plugin lifecycle already.
    // Only use this bundle-owned fallback loader for local-only modules that are not
    // yet registered in installed_plugins.
    if ($pluginManager && method_exists($pluginManager, 'isInstalled') && $pluginManager->isInstalled($legacyPlugin)) {
        continue;
    }

    $basePath = PackageManager::pluginSourcePath($legacyPlugin);
    if ($basePath === '' || !is_dir($basePath)) {
        continue;
    }

    // Register view namespace so plugin views can render via PluginName::view.php
    $viewPath = $basePath . '/Views';
    if (is_dir($viewPath)) {
        $view->addNamespace($legacyPlugin, $viewPath);
    }

    $bootstrap = $basePath . '/bootstrap.php';
    if (is_file($bootstrap)) {
        require_once $bootstrap;
    }

    $routesFile = $basePath . '/routes.php';
    if (is_file($routesFile)) {
        require_once $routesFile;
    }
}
