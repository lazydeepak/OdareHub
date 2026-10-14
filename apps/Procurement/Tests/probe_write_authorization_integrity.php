<?php
declare(strict_types=1);

/**
 * Static/mock-only proof of Procurement write authorization and identifier
 * ownership boundaries. No application bootstrap, database connection, or SQL
 * execution occurs.
 */

$root = dirname(__DIR__, 3);
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$routesPath = $root . '/apps/Procurement/routes.php';
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$service = (string)file_get_contents($servicePath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$methodBody = static function (string $source, string $method): string {
    $pattern = '/public static function ' . preg_quote($method, '/') . '\\s*\\([^)]*\\):?[^\\{]*\\{(.*?)\\n    \\}/s';
    return preg_match($pattern, $source, $match) === 1 ? (string)$match[1] : '';
};

$selected = [
    'createOrder' => 'ProcurementOverviewService::createOrder(',
    'createOrderFromRequest' => 'ProcurementOverviewService::createOrderFromApprovedRequest(',
    'createReceipt' => 'ProcurementOverviewService::createReceipt(',
];

foreach ($selected as $method => $delegate) {
    $body = $methodBody($controller, $method);
    $check($body !== '', "controller method {$method} exists");
    $check(str_contains($body, "Auth::requireAppAccess('procurement')"), "{$method} re-checks Procurement app access");
    $check(str_contains($body, 'Auth::requireCsrf('), "{$method} requires CSRF");
    $check(str_contains($body, $delegate), "{$method} delegates to the owning service");
    $check(strpos($body, "Auth::requireAppAccess('procurement')") < strpos($body, $delegate), "{$method} authorizes before mutation dispatch");
}

foreach ([
    '/apps/procurement/orders/create' => 'ProcurementDashboardController::createOrder()',
    '/apps/procurement/orders/create-from-request' => 'ProcurementDashboardController::createOrderFromRequest()',
    '/apps/procurement/receipts/create' => 'ProcurementDashboardController::createReceipt()',
] as $route => $handler) {
    $position = strpos($routes, "'$route'");
    $tail = $position === false ? '' : substr($routes, $position, 700);
    $check($position !== false, "route {$route} exists");
    $check(str_contains($tail, "Auth::requireAppAccess('procurement')"), "route {$route} enforces app access");
    $check(str_contains($tail, $handler), "route {$route} dispatches the expected controller");
}

$check(str_contains($service, 'SELECT id, supplier_name, supplier_status'), 'approved-request conversion validates selected supplier identity');
$check(str_contains($service, 'Selected supplier is inactive'), 'approved-request conversion rejects inactive supplier');
$check(preg_match('/WHERE id=\?.*?FOR UPDATE/s', $service) === 1, 'approved-request conversion locks the selected request row');
$check(str_contains($service, "Only approved requests can be converted to PO."), 'conversion rejects non-approved request state');
$check(str_contains($service, 'SELECT id FROM procurement_purchase_orders WHERE request_id=?'), 'conversion checks existing purchase-order ownership by request id');
$check(str_contains($service, 'Purchase order reference does not exist.'), 'receipt creation validates purchase-order identity');
$check(str_contains($service, 'Receipt line reference does not exist.'), 'receipt creation validates purchase-order-line identity');
$check(str_contains($service, 'Receipt line reference does not belong to the selected purchase order.'), 'receipt creation enforces line-to-order ownership');
$check(str_contains($service, 'SELECT id FROM procurement_purchase_orders WHERE id=? LIMIT 1 FOR UPDATE'), 'receipt creation locks the selected purchase order');

$check(!str_contains($service, 'Auth::requireAppAccess('), 'service remains free of duplicated route authorization policy');
$check(!str_contains($service, 'AclPolicy::can('), 'service does not invent a second permission policy');

// Negative-path contract checks: these are source-level proofs of the rejection
// boundary, not claims of database execution.
$check(str_contains($controller, 'Auth::requireCsrf((string)($_POST[\'csrf\'] ?? \'\'))'), 'missing/invalid CSRF is rejected before each selected write');
$check(str_contains($service, 'if ($requestId <= 0)'), 'invalid approved-request identifiers are rejected');
$check(str_contains($service, 'if (!is_array($request))'), 'unknown approved-request identifiers are rejected');
$check(str_contains($service, 'if ($poLineId !== null)'), 'caller-supplied receipt line identifiers are validated');

$runtimeDirectCalls = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/apps/Procurement', FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    if (str_contains($path, '/Tests/') || $path === $servicePath) {
        continue;
    }
    $contents = (string)file_get_contents($path);
    if (str_contains($contents, 'ProcurementOverviewService::createOrder(')
        || str_contains($contents, 'ProcurementOverviewService::createOrderFromApprovedRequest(')
        || str_contains($contents, 'ProcurementOverviewService::createReceipt(')) {
        $runtimeDirectCalls[] = str_replace($root . '/', '', $path);
    }
}
sort($runtimeDirectCalls);
$check($runtimeDirectCalls === ['apps/Procurement/Controllers/ProcurementDashboardController.php'], 'no alternate Procurement runtime caller bypasses the guarded controller');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
