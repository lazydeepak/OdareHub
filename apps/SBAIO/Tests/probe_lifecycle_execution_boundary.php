<?php
declare(strict_types=1);

/**
 * SBAIO lifecycle execution-boundary probe.
 *
 * Read-only proof that the bundle route fallback does not register routes for
 * installed inactive legacy modules, while suite-owned SBAIO routes remain
 * available through the enabled app entry. This probe never changes lifecycle
 * state, database rows, schema, menus, or runtime artifacts.
 *
 * Usage: php apps/SBAIO/Tests/probe_lifecycle_execution_boundary.php
 * Exit codes: 0 = pass, 1 = assertion failure, 2 = environment unavailable.
 */

require_once dirname(__DIR__, 3) . '/scripts/testing/DisposableDbTestEntryPointGuard.php';
$ohTestContext = DisposableDbTestEntryPointGuard::authorize('OH_SBAIO', dirname(__DIR__, 3));
define('APP_ROOT', $ohTestContext['app_root']);

require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\Container;
use App\Core\EventBus;
use App\Core\PluginManager;
use App\Core\Router;
use App\Core\View;
use App\Core\DB;

$pass = 0;
$fail = 0;

$check = static function (bool $condition, string $label) use (&$pass, &$fail): void {
    if ($condition) {
        $pass++;
        echo "PASS: {$label}\n";
        return;
    }

    $fail++;
    echo "FAIL: {$label}\n";
};

try {
    $c = new Container();
    $router = new Router();
    $view = new View(APP_ROOT . '/public/views');
    $bus = new EventBus();
    $plugins = new PluginManager(APP_ROOT . '/plugins', $c, $router, $view, $bus);

    $c->set('plugins', static fn (): PluginManager => $plugins);
    $c->set('router', static fn (): Router => $router);
    $c->set('view', static fn (): View => $view);
    $c->set('bus', static fn (): EventBus => $bus);

    $app = DB::fetchOne('SELECT status FROM core_apps WHERE app_key=? LIMIT 1', ['sbaio']);
    if (!is_array($app)) {
        fwrite(STDERR, "Environment unavailable: SBAIO app registry row missing\n");
        exit(2);
    }

    $check((string)($app['status'] ?? '') === 'enabled', 'SBAIO app is enabled for route fixture');

    require APP_ROOT . '/apps/SBAIO/routes.php';
    $routes = $router->listRoutes();
    $getRoutes = (array)($routes['GET'] ?? []);
    $postRoutes = (array)($routes['POST'] ?? []);

    $check(isset($getRoutes['/apps/sbaio']), 'suite-owned SBAIO route remains registered');
    $check(isset($getRoutes['/apps/sbaio/reports']), 'suite-owned SBAIO reports route remains registered');

    $manifest = json_decode(
        (string)file_get_contents(APP_ROOT . '/apps/SBAIO/manifest.json'),
        true
    );
    $legacyModules = is_array($manifest) ? (array)($manifest['legacy_bridge_plugins'] ?? []) : [];
    $inactiveInstalled = [];
    foreach ($legacyModules as $moduleName) {
        $moduleName = trim((string)$moduleName);
        if ($moduleName === '') {
            continue;
        }

        $row = DB::fetchOne('SELECT status FROM installed_plugins WHERE name=? LIMIT 1', [$moduleName]);
        if (is_array($row) && strtolower(trim((string)($row['status'] ?? ''))) !== 'active') {
            $inactiveInstalled[] = $moduleName;
        }
    }

    $check($inactiveInstalled !== [], 'inactive installed legacy-module fixture is present');

    foreach ($inactiveInstalled as $moduleName) {
        $routeSegment = strtolower((string)preg_replace('/(?<!^)[A-Z]/', '-$0', $moduleName));
        $routePath = '/apps/sbaio/' . $routeSegment;
        $check(
            !isset($getRoutes[$routePath]) && !isset($postRoutes[$routePath]),
            "inactive module route absent: {$moduleName} ({$routePath})"
        );
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Environment unavailable: ' . $e->getMessage() . PHP_EOL);
    exit(2);
}

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
