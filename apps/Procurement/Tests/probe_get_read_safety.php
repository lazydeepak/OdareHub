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

$seenColumns = [];
$readyReader = static function (string $table, array $columns) use (&$seenColumns): bool {
    $seenColumns[$table] = $columns;
    return true;
};
$missingReader = static fn(string $table, array $columns): bool|string => $table === 'procurement_purchase_orders' ? 'missing' : true;
$incompatibleReader = static fn(string $table, array $columns): bool => $table !== 'procurement_receipts';
$noAuditReader = static fn(string $table, array $columns): bool => $table !== 'audit_activity_log';
$noManufacturingReader = static fn(string $table, array $columns): bool => $table !== 'mfg_procurement_demands';
$missingAuditAppReader = static fn(string $table, array $columns): bool => !($table === 'audit_activity_log' && in_array('app_key', $columns, true));
$missingAuditActorReader = static fn(string $table, array $columns): bool => !($table === 'audit_activity_log' && in_array('actor_display_name', $columns, true));
$missingSelectedColumnReader = static fn(string $table, array $columns): bool => !($table === 'procurement_requests' && in_array('request_status', $columns, true));
$missingJoinColumnReader = static fn(string $table, array $columns): bool => !($table === 'procurement_purchase_orders' && in_array('supplier_id', $columns, true));
$ready = ProcurementOverviewService::readReadiness($readyReader);
$missing = ProcurementOverviewService::readReadiness($missingReader);
$incompatible = ProcurementOverviewService::readReadiness($incompatibleReader);
$noAudit = ProcurementOverviewService::readReadiness($noAuditReader);
$noManufacturing = ProcurementOverviewService::readReadiness($noManufacturingReader);
$missingAuditApp = ProcurementOverviewService::readReadiness($missingAuditAppReader);
$missingAuditActor = ProcurementOverviewService::readReadiness($missingAuditActorReader);
$missingSelectedColumn = ProcurementOverviewService::readReadiness($missingSelectedColumnReader);
$missingJoinColumn = ProcurementOverviewService::readReadiness($missingJoinColumnReader);

$check($ready['available'] === true && $ready['audit_available'] === true, 'ready metadata reports essential and optional storage available');
$check($missing['available'] === false && $missing['audit_available'] === true, 'missing essential storage fails closed without claiming readiness');
$check($incompatible['available'] === false && $incompatible['audit_available'] === true, 'incompatible essential storage fails closed');
$check($ready['missing_tables'] === [] && $ready['incompatible_tables'] === [], 'ready metadata has no unavailable storage findings');
$check($missing['missing_tables'] === ['procurement_purchase_orders'], 'missing required storage is identified without SQL writes');
$check($incompatible['incompatible_tables'] === ['procurement_receipts'], 'incompatible required table is identified');
$check($noAudit['available'] === true && $noAudit['audit_available'] === false, 'missing optional audit storage does not block business reads');
$check($noManufacturing['optional_sources']['manufacturing_demands'] === false, 'missing optional Manufacturing storage is classified separately');
$check($missingAuditApp['available'] === true && $missingAuditApp['audit_available'] === false, 'missing audit app filter column marks audit unavailable');
$check($missingAuditActor['available'] === true && $missingAuditActor['audit_available'] === false, 'missing audit actor column marks audit unavailable');
$check($missingSelectedColumn['available'] === false, 'missing essential selected/filter column is rejected');
$check($missingJoinColumn['available'] === false, 'missing essential join column is rejected');
$check(in_array('request_status', $seenColumns['procurement_requests'] ?? [], true), 'request status filter dependency is declared');
$check(in_array('supplier_id', $seenColumns['procurement_purchase_orders'] ?? [], true), 'order supplier join dependency is declared');
$check(in_array('app_key', $seenColumns['audit_activity_log'] ?? [], true), 'audit app filter dependency is declared');
$check(in_array('old_state', $seenColumns['audit_activity_log'] ?? [], true), 'audit old-state selected dependency is declared');
$check(in_array('actor_display_name', $seenColumns['audit_activity_log'] ?? [], true), 'audit actor display dependency is declared');
$check(in_array('execution_path_label', $seenColumns['mfg_procurement_demands'] ?? [], true), 'optional Manufacturing dependency is declared');

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
$check(str_contains($views['requests'], 'proc.read.manufacturing_unavailable'), 'requests view distinguishes unavailable Manufacturing intake from an empty queue');
$check(str_contains($controller, "optional_sources']['manufacturing_demands"), 'controller skips optional Manufacturing query when readiness is false');

foreach (['latestRequests', 'latestOrders', 'latestReceipts', 'summary'] as $reader) {
    $check(str_contains($controller, "ProcurementOverviewService::{$reader}(") || str_contains($service, "public static function {$reader}"), "established {$reader} read path remains present");
}
$check(str_contains($controller, "'requests' => ProcurementOverviewService::eligibleRequestOptions(120)"), 'eligible request selection remains unchanged');
$check(str_contains($controller, "'suppliers' => ProcurementOverviewService::eligibleSupplierOptions(120)"), 'eligible supplier selection remains unchanged');
$check(str_contains($routes, "\$router->post('/apps/procurement/orders/create'"), 'POST mutation routes remain registered');
$check(str_contains($routes, "ProcurementOverviewService::ensureSchema();"), 'POST provisioning authority remains present');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
