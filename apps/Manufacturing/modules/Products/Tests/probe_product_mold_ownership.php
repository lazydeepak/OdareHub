<?php
declare(strict_types=1);

/**
 * Database-free IPM-013 ownership schema and admission probe.
 *
 * This verifies preparatory source contracts only. It does not execute the
 * migration, read a mold row, or activate mold/revision authorization.
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
$selection = \Plugins\Products\Services\ProductMoldOwnership::resolveOrganization(41, $user, $repo);
$context = $selection['context'] ?? null;
$check(($selection['allowed'] ?? false) === true, 'explicit selection delegates to existing membership admission');
$check($context instanceof \Apps\Platform\Services\OrganizationMembershipContext, 'admission returns opaque membership context');
$check($context instanceof \Apps\Platform\Services\OrganizationMembershipContext && $context->organizationId() === 41, 'validated context carries selected organization');

$mold = ['id' => 7, 'product_id' => 3, 'organization_id' => 41, 'creator_id' => 999, 'assigned_to' => 1000];
$allowed = \Plugins\Products\Services\ProductMoldOwnership::admit($mold, $context);
$check(($allowed['allowed'] ?? false) === true, 'same-organization mold is admitted');
$check(($allowed['mold']['id'] ?? null) === 7, 'admitted result preserves the canonical mold row');
$check(($allowed['context'] ?? null) === $context, 'admitted result preserves the validated context');

$legacy = \Plugins\Products\Services\ProductMoldOwnership::admit(
    ['id' => 8, 'product_id' => 3, 'organization_id' => null, 'creator_id' => 9001],
    $context
);
$check(($legacy['allowed'] ?? true) === false, 'NULL-owned legacy mold is denied');
$check(($legacy['reason_code'] ?? '') === 'legacy_mold_owner_unresolved', 'legacy denial is explicit');
$check(($legacy['mold'] ?? null) === null, 'legacy denial does not disclose the mold row');

$mismatch = \Plugins\Products\Services\ProductMoldOwnership::admit(
    ['id' => 9, 'product_id' => 3, 'organization_id' => 42],
    $context
);
$check(($mismatch['allowed'] ?? true) === false, 'cross-organization mold is denied');
$check(($mismatch['reason_code'] ?? '') === 'mold_owner_mismatch', 'cross-organization denial is explicit');
$check(($mismatch['mold'] ?? null) === null, 'cross-organization denial does not disclose the mold row');

foreach ([
    null,
    '',
    '0',
    0,
    true,
    [],
    ['organization_id' => 41],
] as $ownerValue) {
    $result = \Plugins\Products\Services\ProductMoldOwnership::admit(
        ['id' => 10, 'product_id' => 3, 'organization_id' => $ownerValue],
        $context
    );
    $check(($result['allowed'] ?? true) === false, 'malformed mold ownership fails closed');
    $check(($result['mold'] ?? null) === null, 'malformed ownership remains non-disclosing');
}

$creatorFallback = \Plugins\Products\Services\ProductMoldOwnership::admit(
    ['id' => 11, 'product_id' => 3, 'creator_id' => 9001, 'assigned_to' => 9001],
    $context
);
$check(($creatorFallback['allowed'] ?? true) === false, 'creator/assignment fields are not ownership fallback');

$missingContext = \Plugins\Products\Services\ProductMoldOwnership::admit($mold, null);
$check(($missingContext['allowed'] ?? true) === false, 'missing membership context fails closed');
$check(($missingContext['reason_code'] ?? '') === 'membership_context_required', 'missing context denial is explicit');

$migration = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/migrations/016_part_molds_organization_ownership.sql');
$check(str_contains($migration, 'organization_id INT NULL'), 'mold ownership column is nullable');
$check(str_contains($migration, 'KEY idx_part_molds_organization'), 'mold ownership index is present');
$check(str_contains($migration, 'FOREIGN KEY (organization_id) REFERENCES org_companies(id)'), 'mold ownership FK targets organizations');
$check(str_contains($migration, 'ON DELETE RESTRICT ON UPDATE CASCADE'), 'mold ownership deletion semantics are restrictive');
$check(!preg_match('/UPDATE\\s+part_molds.*organization_id/is', $migration), 'mold migration has no ownership backfill');
$check(!str_contains($migration, 'CREATE TABLE'), 'mold ownership migration does not duplicate the mold master');

$service = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/ProductMoldOwnership.php');
$controller = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Controllers/ProductsController.php');
$routes = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/routes.php');
$part360 = (string)file_get_contents($root . '/apps/Manufacturing/modules/Products/Services/Part360Service.php');
$check(str_contains($service, 'ProductOrganizationScope::resolve'), 'ownership component reuses Products organization admission');
$check(str_contains($service, 'OrganizationMembershipContext $context'), 'mold admission requires opaque membership context');
$check(!str_contains($service, 'organization_id = ?'), 'ownership component has no raw organization authorization query');
$check(!str_contains($service, 'Auth::user'), 'ownership component does not fall back to session identity');
$check(!str_contains($service, 'DB::'), 'ownership component is database-independent');
$check(!str_contains($controller, 'ProductMoldOwnership::') && !str_contains($routes, 'ProductMoldOwnership::') && !str_contains($part360, 'ProductMoldOwnership::'), 'ownership admission remains unused by runtime handlers');

$ipmMigration = (string)file_get_contents($root . '/apps/Manufacturing/modules/InjectionMolding/migrations/001_create_ipm_mold_revisions.sql');
$check(str_contains($ipmMigration, 'organization_id INT NOT NULL'), 'revision organization remains mandatory');
$check(str_contains($ipmMigration, 'FOREIGN KEY (mold_id) REFERENCES part_molds(id)'), 'revisions retain canonical mold reference');
$check(str_contains($ipmMigration, 'ON DELETE RESTRICT ON UPDATE CASCADE'), 'revision deletion remains restrictive');

$ipmRoutes = (string)file_get_contents($root . '/apps/Manufacturing/modules/InjectionMolding/routes.php');
$check(!str_contains($ipmRoutes, '->post(') && !str_contains($ipmRoutes, '->get('), 'revision activation remains route-free');

echo "Product mold ownership foundation probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
