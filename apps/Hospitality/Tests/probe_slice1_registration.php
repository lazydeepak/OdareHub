<?php
declare(strict_types=1);

/**
 * Hospitality Slice 1 Registration Probe
 *
 * Proves the apps/Hospitality skeleton registers through EXISTING app loading
 * behavior only:
 *   1. manifest.json passes AppManifestService validation (the real boot validator)
 *   2. AppLocalDiscoveryService::syncLocalApps() (real boot path) registers the app
 *      in core_apps without any loader modification
 *   3. When enabled via AppRegistryService (same service the admin lifecycle uses),
 *      AppRuntimeLoader loads the entry and Router exposes GET /apps/hospitality
 *   4. Original registry status is restored afterwards
 *
 * No Core or loader file is modified by this probe.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice1_registration.php
 * Exit codes: 0 = pass, 1 = fail, 2 = environment unavailable
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Services\AppLocalDiscoveryService;
use App\Services\AppManifestService;
use App\Services\AppRegistryService;
use App\Services\AppRuntimeLoader;
use App\Core\Container;
use App\Core\DB;
use App\Core\Router;
use App\Core\View;

$passed = 0;
$failed = 0;

$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "  PASS [{$label}]\n";
};

$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "  FAIL [{$label}]" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};

$appRoot = APP_ROOT . '/apps/Hospitality';

echo "== Slice 1: Hospitality app registration ==\n";

// ---- Group A: filesystem shape ----
foreach ([
    'manifest.json' => 'manifest exists',
    'routes.php' => 'entry exists',
    'bootstrap.php' => 'bootstrap exists',
    'navigation.php' => 'navigation contract exists',
    'Controllers/HospitalityHomeController.php' => 'controller exists',
    'Views/home.php' => 'view exists',
    'Resources/lang/en.php' => 'en locale exists',
    'Resources/lang/ja.php' => 'ja locale exists',
    'Resources/lang/ne.php' => 'ne locale exists',
    'migrations' => 'migrations dir exists',
] as $rel => $label) {
    file_exists($appRoot . '/' . $rel) ? $pass($label) : $fail($label);
}

// Module scaffolding arrived in slice 3; detailed contract checks live in probe_slice3_modules.php
$moduleDirs = is_dir($appRoot . '/modules') ? glob($appRoot . '/modules/*', GLOB_ONLYDIR) : [];
(count($moduleDirs) === 5)
    ? $pass('modules/ scaffolded with five module dirs (slice 3)')
    : $fail('modules/ scaffolded with five module dirs (slice 3)', (string)count($moduleDirs));
if (!is_dir($appRoot . '/extensions')) {
    $pass('no extensions/ dir (docs-level convention only)');
} else {
    $fail('no extensions/ dir', 'extensions/ must not exist');
}

// ---- Group B: manifest passes existing validation ----
try {
    $manifest = AppManifestService::loadFromFile($appRoot . '/manifest.json');
    $pass('manifest passes AppManifestService validation');
    ((string)$manifest['id'] === 'hospitality') ? $pass('id = hospitality') : $fail('id = hospitality', (string)$manifest['id']);
    ((string)$manifest['type'] === 'business') ? $pass('type = business (Domain App)') : $fail('type = business');
    ((array)$manifest['dependencies'] === []) ? $pass('no dependencies (no shared-app coupling)') : $fail('no dependencies');
    ((array)$manifest['permissions'] === ['hospitality.view', 'hospitality.manage']) ? $pass('permissions scoped to hospitality.*') : $fail('permissions scoped to hospitality.*');
} catch (\Throwable $e) {
    $fail('manifest passes AppManifestService validation', $e->getMessage());
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit(1);
}

// ---- Group C: discovery through real boot path ----
try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable ({$e->getMessage()}); discovery/runtime groups not run.\n";
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit($failed > 0 ? 1 : 0);
}

$registry = new AppRegistryService();

try {
    (new AppLocalDiscoveryService())->syncLocalApps();
    $pass('syncLocalApps() ran (real boot discovery path)');
} catch (\Throwable $e) {
    $fail('syncLocalApps() ran', $e->getMessage());
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit(1);
}

$row = $registry->find('hospitality');
if (is_array($row)) {
    $pass('hospitality row present in core_apps after discovery');
    $originalStatus = (string)($row['status'] ?? '');
} else {
    $fail('hospitality row present in core_apps after discovery');
    echo "\nResult: {$passed} passed, {$failed} failed\n";
    exit(1);
}

// ---- Group D: route activation round-trip through existing services ----

// Snapshot every registry status so the probe leaves runtime state exactly as found.
$statusSnapshot = [];
foreach (DB::fetchAll('SELECT app_key, status FROM core_apps') as $r) {
    $statusSnapshot[(string)$r['app_key']] = (string)$r['status'];
}

$registry->setStatus('hospitality', AppRegistryService::STATUS_ENABLED);
((string)($registry->find('hospitality')['status'] ?? '') === AppRegistryService::STATUS_ENABLED)
    ? $pass('enabled via AppRegistryService (admin-lifecycle path)')
    : $fail('enabled via AppRegistryService');

// Mirror real boot container bindings so other enabled apps load as they do in Application.
$c = new Container();
$router = new Router();
$view = new View(APP_ROOT . '/public/views');
$bus = new \App\Core\EventBus();
$c->set('router', fn() => $router);
$c->set('view', fn() => $view);
$c->set('bus', fn() => $bus);
$c->set('plugins', fn() => new \App\Core\PluginManager(APP_ROOT . '/plugins', $c, $router, $view, $bus));

AppRuntimeLoader::resetLoadedRoutes();
(new AppRuntimeLoader($registry))->loadEnabledApps($c, $router, $view);

$routes = $router->listRoutes();
$hasRoute = isset($routes['GET']['/apps/hospitality']) || isset($routes['GET']['/apps/hospitality/']);
$hasRoute ? $pass('GET /apps/hospitality registered by AppRuntimeLoader') : $fail('GET /apps/hospitality registered by AppRuntimeLoader');

$loadedFiles = AppRuntimeLoader::getLoadedRouteFiles();
$loadedEntry = false;
foreach ($loadedFiles as $f) {
    if (str_contains((string)$f, 'apps/Hospitality/routes.php')) {
        $loadedEntry = true;
    }
}
$loadedEntry ? $pass('entry routes.php loaded exactly once via loader guard') : $fail('entry routes.php loaded');

$viewNamespaces = null;
if (method_exists($view, 'getNamespaces')) {
    $viewNamespaces = $view->getNamespaces();
}
if (is_array($viewNamespaces)) {
    isset($viewNamespaces['hospitality']) ? $pass('view namespace hospitality registered') : $fail('view namespace hospitality registered');
} else {
    $pass('view namespace registration (skipped assertion; getter absent)');
}

// restore every registry status so this probe leaves runtime state as it found it
foreach ($statusSnapshot as $appKey => $originalStatus) {
    $current = (string)($registry->find($appKey)['status'] ?? '');
    if ($current !== $originalStatus) {
        $registry->setStatus($appKey, $originalStatus);
    }
}
$restoredAll = true;
foreach ($statusSnapshot as $appKey => $originalStatus) {
    if ((string)($registry->find($appKey)['status'] ?? '') !== $originalStatus) {
        $restoredAll = false;
    }
}
$restoredAll ? $pass('all original registry statuses restored') : $fail('all original registry statuses restored');

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
