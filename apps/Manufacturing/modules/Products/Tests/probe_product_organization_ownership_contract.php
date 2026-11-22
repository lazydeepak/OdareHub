<?php
declare(strict_types=1);

/**
 * Database-free IPM-006 contract and creation-path probe.
 *
 * This verifies the additive boundary and all ordinary Product creation
 * callers without claiming database/runtime acceptance.
 */
$root = dirname(__DIR__, 5);
define('APP_ROOT', $root);

require_once $root . '/apps/Platform/Services/OrganizationMembershipResolver.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once $root . '/apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductOrganizationScope.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductCreationService.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$label}\n";
    } else {
        $failed++;
        echo "FAIL: {$label}\n";
    }
};

$repo = new class implements \Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract {
    public function listEvidenceForUser(int $userId, \DateTimeInterface $observedAt): array
    {
        return [[
            'user_id' => $userId,
            'organization_id' => 41,
            'status' => 'active',
            'is_active' => true,
            'is_revoked' => false,
            'organization_is_active' => true,
            'valid_from' => null,
            'valid_until' => null,
        ]];
    }
    public function findById(int $membershipId): ?array { return null; }
    public function create(array $command): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
    public function activate(int $membershipId, int $actorUserId, \DateTimeInterface $observedAt): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
    public function revoke(int $membershipId, int $actorUserId, \DateTimeInterface $observedAt, string $reason): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
    public function replaceActive(int $userId, int $organizationId, array $command, int $actorUserId, \DateTimeInterface $observedAt): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
};

$user = static fn (): array => ['id' => 9001];
$allowed = \Plugins\Products\Services\ProductOrganizationScope::resolve(41, $user, $repo);
$check(($allowed['allowed'] ?? false) === true, 'explicit active membership resolves through Platform context');
$context = $allowed['context'] ?? null;
$check($context instanceof \Apps\Platform\Services\OrganizationMembershipContext && $context->organizationId() === 41, 'admission returns opaque organization context');

$deniedSpoof = \Plugins\Products\Services\ProductOrganizationScope::resolve(42, $user, $repo);
$check(($deniedSpoof['allowed'] ?? true) === false, 'organization spoofing is denied by membership evidence');
$deniedMissing = \Plugins\Products\Services\ProductOrganizationScope::resolve(null, $user, $repo);
$check(($deniedMissing['allowed'] ?? true) === false, 'missing organization selection fails closed');
$deniedMalformed = \Plugins\Products\Services\ProductOrganizationScope::resolve(['organization_id' => 41], $user, $repo);
$check(($deniedMalformed['allowed'] ?? true) === false, 'array-shaped organization selection fails closed');

$migration = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/migrations/015_product_organization_ownership.sql');
$check(str_contains($migration, 'organization_id INT NULL'), 'Product ownership remains nullable for legacy rows');
$check(str_contains($migration, 'KEY idx_products_organization'), 'Product organization index exists');
$check(str_contains($migration, 'FOREIGN KEY (organization_id) REFERENCES org_companies(id)'), 'Product organization FK is database-enforced');
$check(!preg_match('/UPDATE\\s+products.*organization_id/is', $migration), 'ownership migration has no legacy backfill');

$controller = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Controllers/ProductsController.php');
$legacyService = (string)file_get_contents($root . '/apps/Manufacturing/Services/ManufacturingLegacyImportService.php');
$legacyController = (string)file_get_contents($root . '/apps/Manufacturing/Controllers/ManufacturingImportController.php');
$routes = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/routes.php');
$creationService = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/ProductCreationService.php');

$check(str_contains($controller, 'ProductOrganizationScope::resolve($input[\'organization_id\'] ?? null)'), 'Products form creation resolves explicit organization');
$check(str_contains($controller, 'ProductCreationService::create($columns, $organization[\'context\'])'), 'Products form persists through verified context service');
$check(str_contains($controller, 'ProductOrganizationScope::resolve($requestedOrganizationId)'), 'Products CSV import resolves explicit organization');
$check(str_contains($controller, 'ProductCreationService::upsertOwned($columns, $organization[\'context\'])'), 'Products CSV import uses owner-aware upsert');
$check(str_contains($legacyService, 'ProductOrganizationScope::resolve($requestedOrganizationId)'), 'legacy import product preview/commit resolves organization');
$check(str_contains($legacyService, 'ProductCreationService::upsertOwned($row, $context)'), 'legacy import product commit uses owner-aware upsert');
$check(str_contains($legacyController, '$_POST[\'organization_id\'] ?? null'), 'legacy import controller forwards selected organization');
$check(str_contains($legacyController, '$payload[\'preview\'][\'organization_id\'] ?? null'), 'legacy import reuses only the stored selected organization for revalidation');
$check(str_contains($routes, '$_POST[\'organization_id\'] ?? null'), 'Products import route forwards organization selection');
$check(str_contains($creationService, 'OrganizationMembershipContext $context'), 'persistence service accepts opaque context, not raw organization ID');
$check(str_contains($creationService, '$conn->begin_transaction()') && str_contains($creationService, '$conn->rollback()'), 'single Product creation rolls back on failure');
$check(str_contains($creationService, 'Product ownership conflict'), 'owned upsert rejects NULL or cross-organization existing Products');
$check(!str_contains($creationService, 'organization_id = ?'), 'persistence service does not accept a raw organization proof parameter');

$seed = (string)file_get_contents($root . '/apps/Platform/Services/DemoDataSeedService.php');
$platformRoutes = (string)file_get_contents($root . '/apps/Platform/routes.php');
$check(str_contains($seed, 'INSERT INTO products'), 'Platform seed Product insert is inventoried');
$check(str_contains($platformRoutes, 'AdminToolsAccessService::requireAdminToolsAccess()'), 'seed entry points require privileged admin-tools authority');
$check(!str_contains($seed, 'ProductOrganizationScope::resolve'), 'privileged seed path remains explicitly outside ordinary admission');

$testInsertCount = 0;
foreach ([
    $root . '/apps/Manufacturing/Tests/probe_upstream_supply_transaction.php',
    $root . '/apps/Manufacturing/Tests/probe_upstream_supply_concurrency.php',
    $root . '/apps/Manufacturing/modules/Bom/Tests/probe_bom_contract.php',
    $root . '/apps/Manufacturing/modules/Products/Tests/probe_product_item_mapping_database.php',
] as $path) {
    $source = (string)file_get_contents($path);
    $testInsertCount += substr_count($source, 'INSERT INTO products');
}
$check($testInsertCount > 0, 'database-only fixtures are identified separately from reachable creation');

echo "Product organization ownership contract probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
