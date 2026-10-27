<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\PackageManager;
use SBAIO\Controllers\SbaioExportController;
use SBAIO\Controllers\SbaioImportController;
use SBAIO\Controllers\SbaioRestoreController;
use SBAIO\Controllers\SbaioDashboardController;

require_once __DIR__ . '/Controllers/SbaioDashboardController.php';
require_once __DIR__ . '/Controllers/SbaioExportController.php';
require_once __DIR__ . '/Controllers/SbaioImportController.php';
require_once __DIR__ . '/Controllers/SbaioRestoreController.php';
require_once __DIR__ . '/Services/SbaioLegacyImportService.php';
require_once __DIR__ . '/Services/SbaioBulkIoAdmissionService.php';
require_once __DIR__ . '/bootstrap.php';

$requireBulkIoAdmission = static function (): bool {
    if (SBAIO\Services\SbaioBulkIoAdmissionService::isAvailable('suite_bulk_io')) {
        return true;
    }

    http_response_code(404);
    echo 'Bulk I/O unavailable';
    return false;
};

$router->get('/apps/sbaio', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SbaioDashboardController::index($view);
    return null;
});

$router->get('/apps/sbaio/reports', function () use ($view) {
    Auth::requireAppAccess('sbaio');
    Auth::bootSession();
    SbaioDashboardController::reports($view);
    return null;
});

$router->get('/apps/sbaio/imports', function () use ($view, $requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioImportController::index($view);
    return null;
});

$router->get('/apps/sbaio/exports', function () use ($view, $requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioExportController::index($view);
    return null;
});

$router->get('/apps/sbaio/restores', function () use ($view, $requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioRestoreController::index($view);
    return null;
});

$router->post('/apps/sbaio/imports/preview', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioImportController::previewWorkbook();
    return null;
});

$router->post('/apps/sbaio/imports/commit', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioImportController::commitWorkbook();
    return null;
});

$router->post('/apps/sbaio/exports/suite', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioExportController::exportSuite();
    return null;
});

$router->post('/apps/sbaio/exports/module', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioExportController::exportModule();
    return null;
});

$router->post('/apps/sbaio/restores/preview', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioRestoreController::previewRestore();
    return null;
});

$router->post('/apps/sbaio/restores/commit', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioRestoreController::commitRestore();
    return null;
});

$router->post('/apps/sbaio/restores/cancel', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioRestoreController::cancelPreview();
    return null;
});

$router->get('/apps/sbaio/exports/download', function () use ($requireBulkIoAdmission) {
    Auth::requireAppAccess('sbaio');
    if (!$requireBulkIoAdmission()) {
        return null;
    }
    Auth::bootSession();
    SbaioExportController::download();
    return null;
});

$manifestPath = __DIR__ . '/manifest.json';
$manifest = is_file($manifestPath)
    ? json_decode((string)file_get_contents($manifestPath), true)
    : null;

$modulePlugins = is_array($manifest) && is_array($manifest['legacy_bridge_plugins'] ?? null)
    ? array_values(array_map('strval', (array)$manifest['legacy_bridge_plugins']))
    : [];
$pluginManager = isset($c) ? $c->get('plugins') : null;

foreach ($modulePlugins as $modulePlugin) {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $modulePlugin)) {
        continue;
    }

    // Installed legacy modules are loaded by the module/plugin lifecycle. Do not
    // re-register inactive modules through the bundle-owned compatibility path.
    // Keep the fallback only for local-only modules that are not registered yet.
    if ($pluginManager && method_exists($pluginManager, 'isInstalled') && $pluginManager->isInstalled($modulePlugin)) {
        continue;
    }

    $basePath = PackageManager::pluginSourcePath($modulePlugin);
    if ($basePath === '' || !is_dir($basePath)) {
        continue;
    }

    $viewPath = $basePath . '/Views';
    if (is_dir($viewPath)) {
        $view->addNamespace($modulePlugin, $viewPath);
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
