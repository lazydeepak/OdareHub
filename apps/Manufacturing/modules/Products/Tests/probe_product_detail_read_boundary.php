<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
$routesPath = $root . '/apps/Manufacturing/modules/Products/routes.php';
$controllerPath = $root . '/apps/Manufacturing/modules/Products/Controllers/ProductsController.php';
$servicePath = $root . '/apps/Manufacturing/modules/Products/Services/Part360Service.php';
$readPath = $root . '/apps/Manufacturing/modules/Products/Services/ProductReadService.php';

$routes = (string)file_get_contents($routesPath);
$controller = (string)file_get_contents($controllerPath);
$service = (string)file_get_contents($servicePath);
$read = (string)file_get_contents($readPath);
$detailRoutes = $routes;
if (preg_match('/\$renderProduct360 = function\\(\\).*?\\$router->get\\(\'\\/products\\/360\'.*?\\n\\}\\);/s', $routes, $matches) === 1) {
    $detailRoutes = $matches[0];
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

$check(str_contains($detailRoutes, '$router->get(\'/apps/manufacturing/products/360\', $renderProduct360);'), 'canonical detail route exists');
$check(str_contains($detailRoutes, '$target = \'/apps/manufacturing/products/360\''), 'legacy detail alias redirects to canonical route');
$check(str_contains($routes, 'ProductsController::part360($view, (int)($_GET[\'id\'] ?? 0))'), 'canonical route invokes the existing detail handler');
$check(str_contains($detailRoutes, "acl_require('products.360.view'"), 'detail route retains existing ACL admission');
$check(!str_contains($detailRoutes, "organization_id'] ?? null"), 'detail route does not currently establish organization selection');
$check(str_contains($controller, '$payload = Part360Service::build($id);'), 'detail handler still uses the unrestricted Part360 entry point');
$check(!str_contains($controller, 'ProductOrganizationScope::resolve($_GET'), 'detail handler does not currently resolve organization membership');
$check(str_contains($service, 'WHERE p.id = ?'), 'Part360 primary Product lookup is by ID only');
$check(!str_contains($service, 'p.organization_id = ?'), 'Part360 primary lookup has no organization predicate');
$check(str_contains($service, 'private static function materialOptions()'), 'detail payload includes a global material selector query');
$check(str_contains($service, 'FROM materials'), 'detail payload reads material catalog data');
$check(str_contains($service, 'FROM machines'), 'detail payload reads machine catalog data');
$check(str_contains($service, 'FROM users u'), 'detail payload reads user responsibility data');
$check(str_contains($service, 'private static function responsibility(int $productId)'), 'responsibility query has no organization context parameter');
$check(str_contains($service, 'private static function moldFitSummary(array $molds, array $partMaterials)'), 'machine-fit secondary query has no organization context parameter');
$check(!str_contains($service, 'OrganizationMembershipContext'), 'Part360 has no validated organization-context contract');
$check(!str_contains($service, 'buildForOrganization('), 'no partially activated scoped detail entry point exists');
$check(str_contains($service, 'WHERE product_id = ?'), 'some secondary rows are product-linked but not independently organization-owned');
$check(str_contains($read, 'OrganizationMembershipContext $context'), 'reusable Product seam remains context-only');
$check(str_contains($read, 'organization_id=?'), 'reusable Product seam retains SQL ownership filtering');

echo "Product detail read boundary blocker probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
