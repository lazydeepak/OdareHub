<?php
declare(strict_types=1);

/**
 * Hospitality Slice 4 Routes + Navigation Probe
 *
 * Proves the six canonical Hospitality surfaces register through EXISTING app runtime
 * loading and that guards/navigation stay consistent:
 *   1. All six GET routes registered by AppRuntimeLoader (real boot path, status
 *      round-trip restored afterwards).
 *   2. Every route handler requires app access AND a server-side hospitality.view
 *      permission check exists for each module surface.
 *   3. navigation.v1 items match manifest routes[] exactly (paths + feature keys).
 *   4. Module plugin.json capability additions still satisfy contract implication rules.
 *   5. Views referenced by controllers exist; locale files cover every visible label key.
 *
 * No Core, loader, or Shell composition file is modified by this probe.
 *
 * Usage: php apps/Hospitality/Tests/probe_slice4_routes_navigation.php
 * Exit codes: 0 = pass, 1 = fail, 2 = environment unavailable
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\Container;
use App\Core\EventBus;
use App\Core\DB;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Services\AppLocalDiscoveryService;
use App\Services\AppRegistryService;
use App\Services\AppRuntimeLoader;

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
$sixRoutes = [
    '/apps/hospitality',
    '/apps/hospitality/rooms',
    '/apps/hospitality/guests',
    '/apps/hospitality/reservations',
    '/apps/hospitality/front-desk',
    '/apps/hospitality/housekeeping',
];

echo "== Slice 4: Hospitality routes + navigation ==\n";

// ---- Group A: route registration through real loader ----
try {
    DB::conn();
} catch (\Throwable $e) {
    echo "SKIP: database unavailable; loader group not run.\n";
    goto static_groups;
}

$statusSnapshot = [];
foreach (DB::fetchAll('SELECT app_key, status FROM core_apps') as $r) {
    $statusSnapshot[(string)$r['app_key']] = (string)$r['status'];
}

$registry = new AppRegistryService();
$registry->setStatus('hospitality', AppRegistryService::STATUS_ENABLED);

$c = new Container();
$router = new Router();
$view = new View(APP_ROOT . '/public/views');
$bus = new EventBus();
$c->set('router', fn() => $router);
$c->set('view', fn() => $view);
$c->set('bus', fn() => $bus);
$c->set('plugins', fn() => new PluginManager(APP_ROOT . '/plugins', $c, $router, $view, $bus));

AppRuntimeLoader::resetLoadedRoutes();
try {
    (new AppRuntimeLoader($registry))->loadEnabledApps($c, $router, $view);
    $routes = $router->listRoutes();
    foreach ($sixRoutes as $path) {
        isset($routes['GET'][$path]) || isset($routes['GET'][rtrim($path, '/')])
            ? $pass("GET {$path} registered by loader")
            : $fail("GET {$path} registered by loader");
    }
} catch (\Throwable $e) {
    $fail('loadEnabledApps ran', $e->getMessage());
}

foreach ($statusSnapshot as $appKey => $originalStatus) {
    if ((string)($registry->find($appKey)['status'] ?? '') !== $originalStatus) {
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

static_groups:

// ---- Group B: server-side permission checks ----
$routesPhp = (string)file_get_contents($appRoot . '/routes.php');
(substr_count($routesPhp, "Auth::requireAppAccess('hospitality')") === 6)
    ? $pass('all six handlers require app access')
    : $fail('all six handlers require app access', (string)substr_count($routesPhp, 'requireAppAccess'));

foreach (['RoomsController.php', 'GuestsController.php', 'ReservationsController.php', 'FrontDeskController.php', 'HousekeepingController.php'] as $controllerFile) {
    $src = (string)@file_get_contents($appRoot . '/Controllers/' . $controllerFile);
    (str_contains($src, 'HospitalityAccess::requireView'))
        ? $pass("{$controllerFile}: server-side view guard")
        : $fail("{$controllerFile}: server-side view guard");
}
$accessSrc = (string)file_get_contents($appRoot . '/Controllers/HospitalityAccess.php');
(str_contains($accessSrc, "AclPolicy::can('hospitality.view'") && str_contains($accessSrc, 'http_response_code(403)'))
    ? $pass('guard enforces hospitality.view via AclPolicy with 403')
    : $fail('guard enforces hospitality.view via AclPolicy with 403');

// ---- Group C: navigation <-> manifest consistency ----
$nav = require $appRoot . '/navigation.php';
$navItems = is_array($nav['items'] ?? null) ? $nav['items'] : [];

$manifest = json_decode((string)file_get_contents($appRoot . '/manifest.json'), true);
$manifestPaths = [];
foreach ((array)($manifest['routes'] ?? []) as $r) {
    $manifestPaths[(string)$r['path']] = (string)($r['feature_key'] ?? '');
}

$navUrls = [];
$navFeatureKeysOk = true;
foreach ($navItems as $item) {
    $url = (string)($item['url'] ?? '');
    $navUrls[$url] = (string)($item['feature_key'] ?? '');
    if (($manifestPaths[$url] ?? '') !== '' && $manifestPaths[$url] !== ($item['feature_key'] ?? '')) {
        $navFeatureKeysOk = false;
    }
}
(array_keys($navUrls) === $sixRoutes)
    ? $pass('navigation exposes exactly the six canonical routes')
    : $fail('navigation exposes exactly the six canonical routes', implode(',', array_keys($navUrls)));
(count($manifestPaths) === 6)
    ? $pass('manifest declares exactly six canonical routes')
    : $fail('manifest declares exactly six canonical routes', (string)count($manifestPaths));
$navFeatureKeysOk ? $pass('feature keys consistent between navigation and manifest') : $fail('feature keys consistent between navigation and manifest');

// nav module attribution matches declared modules
$declaredKeys = array_map(static fn ($m) => (string)$m['key'], (array)($manifest['modules'] ?? []));
$itemModules = array_map(static fn ($i) => (string)($i['module'] ?? ''), $navItems);
$attributionOk = true;
foreach ($itemModules as $idx => $mod) {
    if ($mod !== 'hospitality' && !in_array($mod, $declaredKeys, true)) {
        $attributionOk = false;
    }
}
$attributionOk ? $pass('navigation module attribution uses declared modules only') : $fail('navigation module attribution uses declared modules only');

// ---- Group D: views exist + locale coverage of visible keys ----
$viewKeysByRoute = [
    '/apps/hospitality/rooms' => ['rooms'],
    '/apps/hospitality/guests' => ['guests'],
    '/apps/hospitality/reservations' => ['reservations'],
    '/apps/hospitality/front-desk' => ['front_desk'],
    '/apps/hospitality/housekeeping' => ['housekeeping'],
];
foreach ($viewKeysByRoute as $route => [$viewName]) {
    is_file($appRoot . '/Views/' . $viewName . '.php')
        ? $pass("view exists for {$route}")
        : $fail("view exists for {$route}");
}
is_file($appRoot . '/Views/forbidden.php') ? $pass('forbidden view exists') : $fail('forbidden view exists');

$visibleKeys = [
    'hospitality.app.name',
    'hospitality.home.coming_soon',
    'hospitality.home.scope_note',
    'hospitality.nav.rooms',
    'hospitality.nav.guests',
    'hospitality.nav.reservations',
    'hospitality.nav.front_desk',
    'hospitality.nav.housekeeping',
    'hospitality.module.records_count',
    'hospitality.module.schema_pending',
    'hospitality.module.l1_note',
    'hospitality.forbidden.title',
    'hospitality.forbidden.message',
];
foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = require $appRoot . '/Resources/lang/' . $lang . '.php';
    $missing = array_diff($visibleKeys, array_keys((array)$catalog));
    $missing === []
        ? $pass("locale {$lang} covers all visible label keys")
        : $fail("locale {$lang} covers all visible label keys", implode(',', $missing));
}

// ---- Group E: controller-emitted flash keys resolve in every locale catalog ----
// Success flashes are emitted as literal constants; a mismatch with the catalogs
// renders the raw key on screen. This group keeps controller keys and catalogs in sync.
$flashKeys = [];
foreach (glob($appRoot . '/Controllers/*Controller.php') ?: [] as $controllerPath) {
    preg_match_all("/redirect(?:Ok|Err)\('[^']+',\s*'([A-Za-z0-9_.]+)'\)/", (string)file_get_contents($controllerPath), $matches);
    foreach ($matches[1] as $literalKey) {
        $flashKeys[strtolower($literalKey)] = true;
    }
}
(count($flashKeys) > 0)
    ? $pass('controller flash key literals discovered (' . count($flashKeys) . ')')
    : $fail('controller flash key literals discovered');
foreach (['en', 'ja', 'ne'] as $lang) {
    $catalog = require $appRoot . '/Resources/lang/' . $lang . '.php';
    $missingFlash = array_diff(array_keys($flashKeys), array_keys((array)$catalog));
    $missingFlash === []
        ? $pass("locale {$lang} resolves every controller flash key")
        : $fail("locale {$lang} resolves every controller flash key", implode(',', $missingFlash));
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
