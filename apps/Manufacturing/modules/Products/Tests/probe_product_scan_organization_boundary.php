<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
$controllerPath = $root . '/apps/Platform/modules/QRCode/Controllers/QRCodeController.php';
$routesPath = $root . '/apps/Platform/modules/QRCode/routes.php';
$viewPath = $root . '/apps/Platform/modules/QRCode/Views/scan_result.php';
$servicePath = $root . '/apps/Manufacturing/modules/Products/Services/ProductScanAdmissionService.php';
$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$view = (string)file_get_contents($viewPath);
$scanMethod = '';
if (preg_match('/public static function scanProduct\(.*?\n    \}\n\n    public static function stockIndex/s', $controller, $matches) === 1) {
    $scanMethod = $matches[0];
}

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL: {$label}\n";
};

$check(str_contains($routes, '$router->get(\'/qr/product/scan\''), 'scan route remains GET and public');
$check(str_contains($routes, 'Auth::bootSession();'), 'scan route boots existing session infrastructure');
$check(str_contains($routes, "['organization_id'] ?? null"), 'route forwards organization selection as resolver input');
$check(!str_contains($routes, '(int)($_GET[\'organization_id\']'), 'route does not coerce organization input as authorization');
$check(str_contains($routes, '$rawProductId = $_GET[\'product_id\'] ?? null;'), 'route captures Product identity before normalization');
$check(str_contains($routes, 'is_int($rawProductId) || is_string($rawProductId)'), 'malformed Product identity is normalized without array coercion');
$check(str_contains($routes, 'is_string($rawPartNumber) ? $rawPartNumber : \'\''), 'malformed part number is normalized without disclosure');
$check(str_contains($controller, 'ProductScanAdmissionService::resolve('), 'scan controller uses Products-owned admission');
$check($scanMethod !== '' && !str_contains($scanMethod, 'findProduct('), 'scan controller does not use unscoped findProduct');
$check(str_contains($controller, "'error' => 'QR scan is unavailable.'"), 'missing and denied scans use generic error');
$check(substr_count($controller, "'error' => 'QR scan is unavailable.'") >= 2, 'table and lookup failures share generic error');
$check($scanMethod !== '' && !str_contains($scanMethod, 'header('), 'scan failures do not redirect or vary by authorization');
$check(str_contains($service, 'ProductOrganizationScope::resolve($requestedOrganizationId)'), 'admission delegates to existing organization resolver');
$check(str_contains($service, 'OrganizationMembershipContext'), 'admission requires opaque membership context');
$check(str_contains($service, 'organization_id=?'), 'Product lookup applies organization ownership predicate');
$check(str_contains($service, 'parts_number=? AND organization_id=?'), 'part-number lookup is owner-scoped');
$check(str_contains($service, 'ProductOrganizationScope::resolve'), 'anonymous or invalid context fails before Product SQL');
$check(!preg_match('/SELECT \* FROM products\s+WHERE id=\? LIMIT 1/s', $service), 'no unscoped Product identity lookup remains in scan service');
$check(str_contains($service, 'mixed $requestedOrganizationId'), 'raw organization input is treated only as resolver input');
$check(str_contains($controller, "'scanUrl' =>"), 'Product label scan URL contract remains present');
$check(str_contains($view, "'/login?redirect='"), 'existing authenticated action continuation remains');
$check(str_contains($view, "['label' => 'Production Update'"), 'existing QR action consumers remain');
$check(str_contains($controller, "'QRCode::scan_result.php'"), 'existing scan result view remains the response surface');
$check(str_contains($view, '$product'), 'scan result preserves Product payload contract for admitted callers');
$check(str_contains($routes, '$router->get(\'/qr/product/label\''), 'Product label route remains registered');
$check(str_contains($routes, "Auth::requireAdmin();"), 'existing QR admin routes retain their admin gate');
$check(str_contains($routes, '$router->get(\'/qr/stock\''), 'QR stock route remains registered');
$check(str_contains($routes, '$router->post(\'/qr/stock/add\''), 'QR stock mutation route remains registered');
$check(str_contains($routes, '$router->get(\'/qr/report\''), 'QR report route remains registered');
$check(str_contains($routes, '$router->get(\'/qr/export\''), 'QR export route remains registered');
$check(!str_contains($scanMethod, 'DB::'), 'scan controller performs no secondary Product query');
$check(substr_count($service, 'SELECT * FROM products') === 3, 'scan service has only bounded Product lookup branches');
$check(substr_count($service, 'organization_id=?') === 3, 'every scan Product lookup includes ownership filtering');

echo "Product QR organization boundary probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
