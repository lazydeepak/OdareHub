<?php
declare(strict_types=1);

/**
 * Database-free IPM-014 mold admission and activation-boundary probe.
 *
 * This proves the safe admission foundation and records why the existing
 * handlers remain inactive until explicit context and database qualification
 * are available. It never executes SQL or the Products migration.
 */
$root = dirname(__DIR__, 5);
define('APP_ROOT', $root);

require_once $root . '/apps/Platform/Services/OrganizationMembershipResolver.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once $root . '/apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductOrganizationScope.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductMoldOwnership.php';

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
    public function create(array $command): array { return ['ok' => false]; }
    public function activate(int $membershipId, int $actorUserId, \DateTimeInterface $observedAt): array { return ['ok' => false]; }
    public function revoke(int $membershipId, int $actorUserId, \DateTimeInterface $observedAt, string $reason): array { return ['ok' => false]; }
    public function replaceActive(int $userId, int $organizationId, array $command, int $actorUserId, \DateTimeInterface $observedAt): array { return ['ok' => false]; }
};

$user = static fn (): array => ['id' => 9001];
$resolve = \Plugins\Products\Services\ProductMoldOwnership::resolveOrganization(...);

$valid = $resolve(41, $user, $repo);
$context = $valid['context'] ?? null;
$check(($valid['allowed'] ?? false) === true, 'creation context admits only an active membership selection');
$check($context instanceof \Apps\Platform\Services\OrganizationMembershipContext, 'creation receives opaque membership context');
$check($context instanceof \Apps\Platform\Services\OrganizationMembershipContext && $context->organizationId() === 41, 'creation ownership value comes from validated context');

foreach ([null, '', '0', 0, true, [], '41.0'] as $selection) {
    $result = $resolve($selection, $user, $repo);
    $check(($result['allowed'] ?? true) === false, 'missing or spoofed organization selection fails closed');
    $check(($result['context'] ?? null) === null, 'rejected organization selection yields no context');
}

$owned = ['id' => 7, 'product_id' => 3, 'organization_id' => 41];
$admitted = \Plugins\Products\Services\ProductMoldOwnership::admit($owned, $context);
$check(($admitted['allowed'] ?? false) === true, 'deletion admission accepts an owner-matching mold');
$check(($admitted['mold']['id'] ?? null) === 7, 'admitted deletion retains the canonical mold identity');

$legacy = \Plugins\Products\Services\ProductMoldOwnership::admit(
    ['id' => 8, 'product_id' => 3, 'organization_id' => null],
    $context
);
$check(($legacy['allowed'] ?? true) === false, 'deletion denies legacy NULL-owned mold');
$check(($legacy['mold'] ?? null) === null, 'legacy NULL-owned denial discloses no mold row');

$crossOrganization = \Plugins\Products\Services\ProductMoldOwnership::admit(
    ['id' => 9, 'product_id' => 3, 'organization_id' => 42],
    $context
);
$check(($crossOrganization['allowed'] ?? true) === false, 'deletion denies cross-organization mold');
$check(($crossOrganization['mold'] ?? null) === null, 'cross-organization denial discloses no mold row');

$products = $root . '/apps/Manufacturing/modules/Products';
$controller = (string)file_get_contents($products . '/Controllers/ProductsController.php');
$routes = (string)file_get_contents($products . '/routes.php');
$part360 = (string)file_get_contents($products . '/Services/Part360Service.php');
$schema = (string)file_get_contents($products . '/Services/PartEngineeringSchemaService.php');
$migration = (string)file_get_contents($products . '/migrations/016_part_molds_organization_ownership.sql');

$check(substr_count($routes, "post('/apps/manufacturing/products/molds/add'") === 1
    && substr_count($routes, "post('/products/molds/add'") === 1,
    'canonical and compatibility creation aliases remain covered');
$check(substr_count($routes, "post('/apps/manufacturing/products/molds/delete'") === 1
    && substr_count($routes, "post('/products/molds/delete'") === 1,
    'canonical and compatibility deletion aliases remain covered');
$check(substr_count($routes, "Auth::requireCsrf((string)(\$_POST['csrf'] ?? ''))") >= 2, 'both mutation aliases retain CSRF admission');
$check(str_contains($controller, 'self::assertCanEditLeadSections(Auth::user(), $productId'), 'creation retains lead/admin assignment admission');
$check(str_contains($controller, 'self::assertCanEditLeadSections(Auth::user(), $productId'), 'deletion retains lead/admin assignment admission');
$check(!str_contains($controller, 'ProductMoldOwnership::'), 'runtime handlers remain inactive without complete context coverage');
$check(!str_contains($routes, 'ProductMoldOwnership::'), 'compatibility aliases do not bypass inactive admission');
$check(!str_contains($part360, 'ProductMoldOwnership::'), 'Part 360 remains outside the mold admission slice');
$check(str_contains($controller, 'PartEngineeringSchemaService::ensureSchema()'), 'existing runtime schema compatibility call remains unchanged');
$check(str_contains($schema, 'CREATE TABLE IF NOT EXISTS part_molds'), 'runtime schema compatibility authority remains documented');
$check(str_contains($migration, 'organization_id INT NULL'), 'activation depends on nullable ownership schema');
$check(str_contains($migration, 'FOREIGN KEY (organization_id) REFERENCES org_companies(id)'), 'activation depends on organization FK integrity');
$check(!preg_match('/UPDATE\s+part_molds.*organization_id/is', $migration), 'activation introduces no ownership backfill');

$ipmRoutes = (string)file_get_contents($root . '/apps/Manufacturing/modules/InjectionMolding/routes.php');
$check(!str_contains($ipmRoutes, '->post(') && !str_contains($ipmRoutes, '->get('), 'revision workflows remain inactive');

echo "IPM-014 mold admission boundary probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
