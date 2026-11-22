<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
$scope = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/ProductOrganizationScope.php');
$scan = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/ProductScanAdmissionService.php');
$read = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/ProductReadService.php');
$controller = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Controllers/ProductsController.php');
$routes = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/routes.php');
$part360 = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/Part360Service.php');
$materials = (string)file_get_contents($root . '/apps/Manufacturing/modules/MaterialManagement/Services/MaterialSchemaService.php');
$machines = (string)file_get_contents($root . '/apps/Manufacturing/modules/Machines/migrations/001_machines.sql');
$molds = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/migrations/008_part_molds_and_material_yield.sql');
$ipmBoundaryPath = $root . '/docs/architecture/ipm/ipm-module-boundary.md';
$ipmBoundary = (string)file_get_contents($ipmBoundaryPath);
$part360Handler = $controller;
if (preg_match('/public static function part360\\(.*?\\n    \\}\\n/s', $controller, $matches) === 1) {
    $part360Handler = $matches[0];
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

$check(str_contains($scope, 'AuthenticatedOrganizationContextResolver'), 'Products context adapter delegates to Platform resolver');
$check(str_contains($scope, 'requestedOrganizationId'), 'context admission requires explicit selection input');
$check(str_contains($scope, 'return self::denied(\'invalid_organization_selection\')'), 'malformed or missing selection fails closed');
$check(str_contains($scan, 'ProductOrganizationScope::resolve($requestedOrganizationId)'), 'QR uses the same Products context adapter');
$check(str_contains($scan, 'ProductReadService::findByQrIdentity'), 'QR performs Product read only after context admission');
$check(str_contains($read, 'OrganizationMembershipContext $context'), 'Product read seam accepts opaque membership context');
$check(str_contains($read, 'organization_id=?'), 'Product read seam applies owner predicate');
$check(str_contains($controller, '$payload = Part360Service::build($id);'), 'Part 360 remains on the unscoped runtime entry point');
$check(!str_contains($part360Handler, 'ProductOrganizationScope::resolve'), 'Part 360 has not acquired a duplicate or partial resolver');
$check(str_contains($routes, '$query = $_SERVER[\'QUERY_STRING\'] ?? \'\';'), 'legacy detail alias preserves query context');
$check(str_contains($part360, 'WHERE p.id = ?'), 'Part 360 primary lookup is currently ID-only');
$check(str_contains($part360, 'private static function materialOptions()'), 'Part 360 includes a separate material selector projection');
$check(str_contains($part360, 'FROM materials'), 'material selector reads the global material catalog');
$check(str_contains($part360, 'FROM machines'), 'machine fit and usage read machine master data');
$check(str_contains($part360, 'FROM users u'), 'responsibility projection reads user identities');
$check(str_contains($part360, 'FIND_IN_SET(\'manufacturing\''), 'available responsibility users use app assignment, not organization membership');
$check(!str_contains($materials, 'organization_id'), 'material schema has no authoritative organization column');
$check(!str_contains($machines, 'organization_id'), 'machine schema has no authoritative organization column');
$check(!str_contains($molds, 'organization_id'), 'Product mold schema has no authoritative organization column');
$check(str_contains($molds, 'product_id INT NOT NULL'), 'mold records relate directly to Product identity');
$check(str_contains($ipmBoundary, 'Every `ipm_*` table carries `organization_id INT NOT NULL`'), 'future IPM-owned records have an explicit tenant contract');
$check(str_contains($ipmBoundary, 'ownership stays with Products'), 'future IPM boundary does not duplicate the mold master');
$check(!is_dir($root . '/apps/Manufacturing/modules/InjectionMolding'), 'InjectionMolding runtime module is not yet scaffolded');
$check(str_contains($ipmBoundary, 'Part360 integration'), 'future IPM integration is additive and separately sequenced');

echo "IPM-010 Part 360 contract probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
