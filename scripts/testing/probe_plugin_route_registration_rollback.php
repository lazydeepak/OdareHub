<?php
declare(strict_types=1);

/**
 * LIFECYCLE-003 authorized Core regression probe.
 *
 * Exercises Router's scoped route snapshot/rollback contract with in-memory
 * callbacks. It does not instantiate PluginManager, activate plugins, or use
 * a database; the loader source is checked separately for the scoped boundary.
 */

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$assertions = 0;
$failures = [];
$assert = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};
$handler = static fn (): string => 'stable';
$routeCount = static function (App\Core\Router $router): int {
    $routes = $router->listRoutes();
    return count((array)($routes['GET'] ?? [])) + count((array)($routes['POST'] ?? []));
};
$register = static function (App\Core\Router $router, array $routes, bool $fail = false): void {
    foreach ($routes as $route) {
        if (($route['method'] ?? 'GET') === 'POST') {
            $router->post($route['path'], $route['handler'] ?? static fn (): string => 'plugin');
        } else {
            $router->get($route['path'], $route['handler'] ?? static fn (): string => 'plugin');
        }
    }
    if ($fail) {
        throw new RuntimeException('synthetic plugin initialization failure');
    }
};

$router = new App\Core\Router();
$router->get('/pre-existing', $handler);
$router->post('/unrelated', $handler);
$snapshot = $router->beginRouteRegistrationScope();
$register($router, [
    ['method' => 'GET', 'path' => '/plugin/get-a'],
    ['method' => 'POST', 'path' => '/plugin/post-a'],
]);
$router->commitRouteRegistrationScope($snapshot);
$assert($routeCount($router) === 4, 'successful registration commits GET and POST routes');
$assert(isset($router->listRoutes()['GET']['/plugin/get-a']), 'successful GET route remains');

$snapshot = $router->beginRouteRegistrationScope();
try {
    $register($router, [
        ['method' => 'GET', 'path' => '/plugin/failing-get-a'],
        ['method' => 'GET', 'path' => '/plugin/failing-get-b'],
        ['method' => 'POST', 'path' => '/plugin/failing-post'],
    ], true);
} catch (Throwable $e) {
    $assert($e->getMessage() === 'synthetic plugin initialization failure', 'initialization failure is rethrown');
    $router->rollbackRouteRegistrationScope($snapshot);
}
$routes = $router->listRoutes();
$assert(!isset($routes['GET']['/plugin/failing-get-a']), 'failed plugin GET route is removed');
$assert(!isset($routes['GET']['/plugin/failing-get-b']), 'failed plugin second GET route is removed');
$assert(!isset($routes['POST']['/plugin/failing-post']), 'failed plugin POST route is removed');
$assert(isset($routes['GET']['/pre-existing']) && isset($routes['POST']['/unrelated']), 'pre-existing routes survive rollback');
$assert(isset($routes['GET']['/plugin/get-a']), 'successful plugin route survives unrelated rollback');

$snapshot = $router->beginRouteRegistrationScope();
try {
    $register($router, [['method' => 'GET', 'path' => '/plugin/retry']], true);
} catch (Throwable $e) {
    $router->rollbackRouteRegistrationScope($snapshot);
}
$assert(!isset($router->listRoutes()['GET']['/plugin/retry']), 'retry begins without stale failed route');
$snapshot = $router->beginRouteRegistrationScope();
$register($router, [['method' => 'GET', 'path' => '/plugin/retry']], false);
$router->commitRouteRegistrationScope($snapshot);
$assert(isset($router->listRoutes()['GET']['/plugin/retry']), 'retry succeeds after rollback');

$routerSource = (string)file_get_contents($root . '/app/Core/Router.php');
$managerSource = (string)file_get_contents($root . '/app/Core/PluginManager.php');
$assert(str_contains($routerSource, 'beginRouteRegistrationScope'), 'Router exposes scoped registration start');
$assert(str_contains($routerSource, 'rollbackRouteRegistrationScope'), 'Router exposes scoped rollback');
$assert(str_contains($managerSource, 'beginRouteRegistrationScope'), 'PluginManager starts a scope per plugin');
$assert(str_contains($managerSource, 'rollbackRouteRegistrationScope'), 'PluginManager rolls back failed plugin routes');
$assert(str_contains($managerSource, 'throw $e;'), 'PluginManager does not swallow initialization failures');

echo json_encode([
    'probe' => 'LIFECYCLE-003-regression',
    'mode' => 'in_memory_router_fixture_and_static_loader_audit',
    'database_connected' => false,
    'database_mutated' => false,
    'plugin_activated' => false,
    'assertions' => $assertions,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($failures === [] ? 0 : 1);
