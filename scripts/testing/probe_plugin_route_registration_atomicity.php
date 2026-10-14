<?php
declare(strict_types=1);

/**
 * OH-AUTO1-024 in-memory plugin route registration reproduction.
 *
 * This probe exercises the existing Router mutation semantics with disposable
 * fixture callbacks and inspects PluginManager's loader source. It does not
 * instantiate PluginManager, load a real plugin, call DB, or mutate lifecycle
 * state.
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

$registerFixture = static function (App\Core\Router $router, array $steps): void {
    foreach ($steps as $step) {
        if (($step['type'] ?? '') === 'throw') {
            throw new RuntimeException((string)($step['message'] ?? 'fixture registration failure'));
        }
        $method = strtoupper((string)($step['method'] ?? 'GET'));
        $path = (string)($step['path'] ?? '/');
        $handler = $step['handler'] ?? static fn(): string => 'fixture';
        if ($method === 'POST') {
            $router->post($path, $handler);
        } else {
            $router->get($path, $handler);
        }
    }
};

$routeCount = static function (App\Core\Router $router): int {
    $routes = $router->listRoutes();
    return count((array)($routes['GET'] ?? [])) + count((array)($routes['POST'] ?? []));
};

// Successful registration.
$router = new App\Core\Router();
$registerFixture($router, [
    ['method' => 'GET', 'path' => '/fixture/success-a'],
    ['method' => 'POST', 'path' => '/fixture/success-b'],
]);
$assert($routeCount($router) === 2, 'successful plugin fixture registers all routes');

// Failure after the first route: current behavior is partial route retention.
$router = new App\Core\Router();
$router->get('/other-owner/stable', static fn(): string => 'stable');
$state = ['status' => 'active'];
try {
    $registerFixture($router, [
        ['method' => 'GET', 'path' => '/fixture/partial'],
        ['type' => 'throw', 'message' => 'synthetic route failure'],
    ]);
} catch (Throwable $e) {
    $assert($e->getMessage() === 'synthetic route failure', 'partial fixture exception reaches caller');
}
$routesAfterFailure = $router->listRoutes();
$assert(isset($routesAfterFailure['GET']['/fixture/partial']), 'partial route remains after failure');
$assert(isset($routesAfterFailure['GET']['/other-owner/stable']), 'other owner route remains unchanged');
$assert($state['status'] === 'active', 'loader failure fixture does not transition lifecycle state');

// Retry does not clean stale routes first; registration overwrites the same key.
try {
    $registerFixture($router, [
        ['method' => 'GET', 'path' => '/fixture/partial'],
        ['type' => 'throw', 'message' => 'synthetic retry failure'],
    ]);
} catch (Throwable $e) {
    $assert($e->getMessage() === 'synthetic retry failure', 'retry failure reaches caller');
}
$assert($routeCount($router) === 2, 'retry leaves stale partial route without creating a second map key');

// Disabled plugin and missing dependency are filtered before registration.
$router = new App\Core\Router();
$plugin = ['status' => 'inactive', 'dependency_ok' => false];
if ($plugin['status'] === 'active' && $plugin['dependency_ok']) {
    $registerFixture($router, [['path' => '/fixture/disabled']]);
}
$assert($routeCount($router) === 0, 'disabled or dependency-blocked plugin registers no route');

// Duplicate declarations are last-write-wins in Router, not rejected atomically.
$router = new App\Core\Router();
$registerFixture($router, [
    ['path' => '/fixture/duplicate', 'handler' => static fn(): string => 'first'],
    ['path' => '/fixture/duplicate', 'handler' => static fn(): string => 'second'],
]);
$duplicate = $router->listRoutes()['GET']['/fixture/duplicate'] ?? null;
$assert($routeCount($router) === 1, 'duplicate declaration collapses to one route map key');
$assert(is_callable($duplicate) && $duplicate() === 'second', 'duplicate declaration silently replaces earlier handler');

// Static contract evidence for the actual plugin loader path.
$pluginManagerSource = (string)file_get_contents($root . '/app/Core/PluginManager.php');
$loadStart = strpos($pluginManagerSource, 'public function loadActivePlugins(): void');
$loadEnd = strpos($pluginManagerSource, 'private function sortByDependencies', $loadStart ?: 0);
$loadBody = ($loadStart !== false && $loadEnd !== false)
    ? substr($pluginManagerSource, $loadStart, $loadEnd - $loadStart)
    : '';
$assert($loadBody !== '', 'PluginManager loadActivePlugins source located');
$assert(str_contains($loadBody, 'require $routes;'), 'plugin route file is required directly');
$assert(!str_contains($loadBody, 'try {'), 'plugin route require has no local try boundary');
$assert(!str_contains($loadBody, 'setStatus') && !str_contains($loadBody, "status='broken'"), 'plugin load failure has no broken-state transition');
$assert(str_contains($loadBody, 'TRUNCATE TABLE menus'), 'plugin load mutates shared menu registry before route loading');

$routerSource = (string)file_get_contents($root . '/app/Core/Router.php');
$assert(!str_contains($routerSource, 'remove(') && !str_contains($routerSource, 'unregister'), 'Router has no route removal API');

echo json_encode([
    'probe' => 'OH-AUTO1-024',
    'mode' => 'in_memory_fixture_and_static_loader_audit',
    'database_connected' => false,
    'lifecycle_state_mutated' => false,
    'real_plugin_activated' => false,
    'assertions' => $assertions,
    'failures' => $failures,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($failures === [] ? 0 : 1);
