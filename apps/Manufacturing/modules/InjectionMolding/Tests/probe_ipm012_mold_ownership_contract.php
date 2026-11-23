<?php
declare(strict_types=1);

$root = dirname(__DIR__, 5);
$products = $root . '/apps/Manufacturing/modules/Products';
$ipm = $root . '/apps/Manufacturing/modules/InjectionMolding';

$moldMigration = (string)file_get_contents($products . '/migrations/008_part_molds_and_material_yield.sql');
$productsRoutes = (string)file_get_contents($products . '/routes.php');
$productsController = (string)file_get_contents($products . '/Controllers/ProductsController.php');
$part360 = (string)file_get_contents($products . '/Services/Part360Service.php');
$schemaService = (string)file_get_contents($products . '/Services/PartEngineeringSchemaService.php');
$productsControllerPath = $products . '/Controllers/ProductsController.php';
$productsInstall = (string)file_get_contents($products . '/install.php');
$scope = (string)file_get_contents($products . '/Services/ProductOrganizationScope.php');
$read = (string)file_get_contents($products . '/Services/ProductReadService.php');
$revisionMigration = (string)file_get_contents($ipm . '/migrations/001_create_ipm_mold_revisions.sql');
$ipmRoutes = (string)file_get_contents($ipm . '/routes.php');

$runtimeSchemaCallers = [];
$productsIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($products, FilesystemIterator::SKIP_DOTS)
);
foreach ($productsIterator as $fileInfo) {
    if (!$fileInfo->isFile() || $fileInfo->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($fileInfo->getPathname(), strlen($products) + 1);
    if (str_starts_with($relative, 'Tests/') || str_contains($relative, '/Tests/')) {
        continue;
    }
    $source = (string)file_get_contents($fileInfo->getPathname());
    $count = substr_count($source, 'PartEngineeringSchemaService::ensureSchema()');
    if ($count > 0) {
        $runtimeSchemaCallers[$relative] = $count;
    }
}
ksort($runtimeSchemaCallers);

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

$check(str_contains($moldMigration, 'id INT AUTO_INCREMENT PRIMARY KEY'), 'canonical mold identity is INT');
$check(str_contains($moldMigration, 'UNIQUE KEY uniq_part_mold_code (product_id, mold_code)'), 'canonical mold identity is Product-scoped');
$check(!str_contains($moldMigration, 'organization_id'), 'canonical mold currently has no organization ownership column');
$check(str_contains($productsRoutes, "ProductsController::createMold(\$_POST)"), 'mold creation aliases converge on one handler');
$check(str_contains($productsRoutes, 'ProductsController::deleteMold(')
    && str_contains($productsRoutes, "\$_POST['organization_id'] ?? null"),
    'mold deletion aliases converge on one handler with future context forwarding');
$check(substr_count($productsRoutes, "post('/apps/manufacturing/products/molds/add'") === 1 && substr_count($productsRoutes, "post('/products/molds/add'") === 1, 'both mold creation aliases remain registered');
$check(substr_count($productsRoutes, "post('/apps/manufacturing/products/molds/delete'") === 1 && substr_count($productsRoutes, "post('/products/molds/delete'") === 1, 'both mold deletion aliases remain registered');
$check(substr_count($productsRoutes, "Auth::requireCsrf((string)(\$_POST['csrf'] ?? ''))") >= 2, 'mold mutation aliases retain CSRF admission');
$check(str_contains($productsController, 'public static function createMold(array $input): void'), 'ordinary mold creation handler exists');
$check(str_contains($productsController, 'public static function deleteMold(int $moldId, int $productId, mixed $requestedOrganizationId = null): void'), 'ordinary mold deletion handler exists');
$check(str_contains($productsController, 'self::assertCanEditLeadSections(Auth::user(), $productId'), 'ordinary mold mutations use existing lead/admin assignment check');
$check(str_contains($productsController, 'INSERT INTO part_molds'), 'creation currently writes the mold master directly');
$check(str_contains($productsController, 'DELETE FROM part_molds WHERE id=? LIMIT 1'), 'deletion currently lacks owner-scoped predicate');
$check(str_contains($part360, 'private static function molds(int $productId)'), 'Part 360 reads molds by Product');
$check(str_contains($part360, 'WHERE product_id = ?'), 'Part 360 mold read currently lacks organization predicate');
$check(str_contains($schemaService, 'CREATE TABLE IF NOT EXISTS part_molds'), 'schema utility still owns runtime mold DDL');
$check(str_contains($schemaService, 'ALTER TABLE part_molds'), 'schema utility still owns runtime mold alterations');
$expectedRuntimeSchemaCallers = [
    'install.php' => 1,
    'Controllers/ProductsController.php' => 5,
    'Services/Part360Service.php' => 1,
];
ksort($expectedRuntimeSchemaCallers);
$check($runtimeSchemaCallers === $expectedRuntimeSchemaCallers, 'recursive Products PHP scan finds the complete direct caller inventory (7 calls, no unlisted files; Tests fixtures excluded)');
$check(substr_count((string)file_get_contents($productsControllerPath), 'PartEngineeringSchemaService::ensureSchema()') === 5, 'ProductsController has exactly five direct schema-service calls');
$check(str_contains($productsInstall, 'PartEngineeringSchemaService::ensureSchema()'), 'Products install hook remains a schema preparation caller');
$check(str_contains($part360, 'PartEngineeringSchemaService::ensureSchema()'), 'Part 360 remains a runtime schema-service caller');
$check(str_contains($productsRoutes, "ProductsController::createMaterialMap(\$_POST)") && str_contains($productsRoutes, "ProductsController::deleteMaterialMap((int)(\$_POST['map_id'] ?? 0), (int)(\$_POST['product_id'] ?? 0))"), 'neighboring material-map HTTP aliases reach schema-dependent handlers');
$check(str_contains($productsRoutes, "ProductsController::assignActiveMachine(\$_POST)"), 'active-machine HTTP aliases reach a schema-dependent handler');
$check(str_contains($productsController, 'public static function createMaterialMap(array $input): void') && str_contains($productsController, 'public static function deleteMaterialMap(int $mapId, int $productId): void'), 'material-map handler pair is present in the direct caller inventory');
$check(str_contains($productsController, 'public static function assignActiveMachine(array $input): void'), 'active-machine handler is present in the direct caller inventory');
$check(str_contains($productsController, '$payload = Part360Service::build($id);') && str_contains($productsController, '$payload = Part360Service::build($productId);'), 'HTTP controller paths indirectly reach Part360 schema initialization through Part360Service::build');
$check(str_contains($part360, 'return self::buildInternal($productId, true);') && str_contains($part360, 'if ($initializeSchema)'), 'Part360 build call graph reaches the guarded schema initialization branch');
$check(str_contains($moldMigration, 'CREATE TABLE IF NOT EXISTS part_molds'), 'Products migration owns canonical mold table creation');
$check(str_contains((string)file_get_contents($products . '/migrations/009_part_molds_machine_fit_alignment.sql'), 'ALTER TABLE part_molds'), 'Products migration owns mold machine-fit alteration');
$check(str_contains($scope, 'AuthenticatedOrganizationContextResolver'), 'existing Platform organization resolver is reusable');
$check(str_contains($read, 'OrganizationMembershipContext $context'), 'existing Product read seam requires opaque membership context');
$check(str_contains($read, 'organization_id=?'), 'existing Product read seam applies owner predicate');
$check(str_contains($revisionMigration, 'organization_id INT NOT NULL'), 'revision organization ownership remains mandatory');
$check(str_contains($revisionMigration, 'FOREIGN KEY (mold_id) REFERENCES part_molds(id)'), 'revision references canonical mold master');
$check(str_contains($revisionMigration, 'ON DELETE RESTRICT ON UPDATE CASCADE'), 'revision deletion is restrictive');
$check(str_contains($revisionMigration, 'UNIQUE KEY uq_ipm_mold_revision (mold_id, revision_no)'), 'revision identity is unique per canonical mold');
$check(!str_contains($ipmRoutes, '->post(') && !str_contains($ipmRoutes, '->get('), 'InjectionMolding has no runtime mutation or read route');
$check($root === dirname(__DIR__, 5), 'probe uses the established nested-module repository-root convention');

echo "IPM-012 mold ownership contract probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
