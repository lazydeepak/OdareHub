<?php
declare(strict_types=1);

/**
 * Isolated proof for Procurement GET read safety.
 *
 * This probe reads source and exercises only the service's injected metadata
 * reader. It does not bootstrap the application, connect to a database, run
 * SQL, initialize schema, or render through the live application.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$routesPath = $root . '/apps/Procurement/routes.php';
$viewPaths = [
    'dashboard' => $root . '/apps/Procurement/Views/dashboard.php',
    'requests' => $root . '/apps/Procurement/Views/requests.php',
    'orders' => $root . '/apps/Procurement/Views/orders.php',
    'receipts' => $root . '/apps/Procurement/Views/receipts.php',
];
$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$views = array_map(static fn(string $path): string => (string)file_get_contents($path), $viewPaths);
require_once $servicePath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$requiredTables = [
    'procurement_suppliers',
    'procurement_requests',
    'procurement_purchase_orders',
    'procurement_purchase_order_lines',
    'procurement_receipts',
];
$readyReader = static fn(string $table, array $columns): bool => true;
$missingReader = static fn(string $table, array $columns): bool|string => $table === 'procurement_purchase_orders' ? 'missing' : true;
$incompatibleReader = static fn(string $table, array $columns): bool => $table !== 'procurement_receipts';
$noAuditReader = static fn(string $table, array $columns): bool => $table !== 'audit_activity_log';
$ready = ProcurementOverviewService::readReadiness($readyReader);
$missing = ProcurementOverviewService::readReadiness($missingReader);
$incompatible = ProcurementOverviewService::readReadiness($incompatibleReader);
$noAudit = ProcurementOverviewService::readReadiness($noAuditReader);

$check($ready['available'] === true && $ready['audit_available'] === true, 'ready metadata reports essential and optional storage available');
$check($missing['available'] === false && $missing['audit_available'] === true, 'missing essential storage fails closed without claiming readiness');
$check($incompatible['available'] === false && $incompatible['audit_available'] === true, 'incompatible essential storage fails closed');
$check($ready['missing_tables'] === [] && $ready['incompatible_tables'] === [], 'ready metadata has no unavailable storage findings');
$check($missing['missing_tables'] === ['procurement_purchase_orders'], 'missing required storage is identified without SQL writes');
$check($incompatible['incompatible_tables'] === ['procurement_receipts'], 'incompatible required table is identified');
$check($noAudit['available'] === true && $noAudit['audit_available'] === false, 'missing optional audit storage does not block business reads');

foreach (['/apps/procurement', '/apps/procurement/requests', '/apps/procurement/orders', '/apps/procurement/receipts'] as $path) {
    $routeStart = strpos($routes, "\$router->get('{$path}'");
    $routeEnd = $routeStart === false ? false : strpos($routes, "});", $routeStart);
    $routeBody = $routeStart !== false && $routeEnd !== false ? substr($routes, $routeStart, $routeEnd - $routeStart) : '';
    $check($routeBody !== '', "GET route {$path} exists");
    $check(!str_contains($routeBody, 'ProcurementOverviewService::ensureSchema()'), "GET route {$path} does not initialize schema");
    $check(str_contains($routeBody, 'ProcurementDashboardController::'), "GET route {$path} delegates to Procurement controller");
}

foreach (['dashboard', 'requests', 'orders', 'receipts'] as $method) {
    $methodStart = strpos($controller, "public static function {$method}(");
    $methodEnd = $methodStart === false ? false : strpos($controller, "\n    public static function ", $methodStart + 10);
    $body = $methodStart !== false
        ? substr($controller, $methodStart, $methodEnd === false ? null : $methodEnd - $methodStart)
        : '';
    $check(str_contains($body, 'ProcurementOverviewService::readReadiness()'), "{$method} checks non-mutating read readiness");
    $check(str_contains($body, "if (!\$readiness['available'])"), "{$method} fails closed before business queries");
    $check(str_contains($body, "'readiness' => \$readiness"), "{$method} passes readiness state to the view");
    $check(!str_contains($body, 'ProcurementOverviewService::ensureSchema()'), "{$method} has no schema initialization");
}

$check(str_contains($service, "private static function tableHasColumns"), 'Procurement owns read-only table shape inspection');
$check(str_contains($service, 'information_schema.columns'), 'shape inspection uses metadata only');
$check(str_contains($service, 'CREATE TABLE IF NOT EXISTS'), 'mutation/lifecycle schema authority remains present for non-GET callers');
$check(str_contains($service, "if (!self::tableHasColumns('audit_activity_log'"), 'recent transition reader checks optional audit storage before querying');
$check(str_contains($service, 'private static function latestTransitionMap'), 'transitive transition enrichment path is present');
$transitionStart = strpos($service, 'private static function latestTransitionMap');
$transitionEnd = $transitionStart === false ? false : strpos($service, "\n    /**", $transitionStart + 10);
$transitionBody = $transitionStart !== false ? substr($service, $transitionStart, $transitionEnd === false ? null : $transitionEnd - $transitionStart) : '';
$check(!str_contains($transitionBody, 'AuditLogService::ensureSchema()'), 'transition enrichment does not initialize audit schema');
$recentStart = strpos($service, 'public static function recentTransitions');
$recentEnd = $recentStart === false ? false : strpos($service, "\n    /**", $recentStart + 10);
$recentBody = $recentStart !== false ? substr($service, $recentStart, $recentEnd === false ? null : $recentEnd - $recentStart) : '';
$check(!str_contains($recentBody, 'AuditLogService::ensureSchema()'), 'recent activity reader does not initialize audit schema');

foreach ($views as $name => $view) {
    $check(str_contains($view, "!(\$readiness['available'] ?? false)"), "{$name} view has explicit unavailable presentation path");
    $check(str_contains($view, "_read_unavailable.php"), "{$name} view delegates missing-storage presentation");
}
$partial = (string)file_get_contents($root . '/apps/Procurement/Views/_read_unavailable.php');
$check(str_contains($partial, 'proc.read.unavailable_title'), 'unavailable state is localized');
$check(str_contains($partial, 'proc.read.no_changes'), 'unavailable state states that no storage mutation occurred');
$check(str_contains($views['dashboard'], 'proc.read.audit_unavailable'), 'dashboard distinguishes unavailable audit history from an empty history');

foreach (['latestRequests', 'latestOrders', 'latestReceipts', 'summary'] as $reader) {
    $check(str_contains($controller, "ProcurementOverviewService::{$reader}(") || str_contains($service, "public static function {$reader}"), "established {$reader} read path remains present");
}
$check(str_contains($controller, "'requests' => ProcurementOverviewService::eligibleRequestOptions(120)"), 'eligible request selection remains unchanged');
$check(str_contains($controller, "'suppliers' => ProcurementOverviewService::eligibleSupplierOptions(120)"), 'eligible supplier selection remains unchanged');
$check(str_contains($routes, "\$router->post('/apps/procurement/orders/create'"), 'POST mutation routes remain registered');
$check(str_contains($routes, "ProcurementOverviewService::ensureSchema();"), 'POST provisioning authority remains present');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
