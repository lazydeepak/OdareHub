<?php
declare(strict_types=1);

/**
 * Database-free IPM-015 mutation-admission probe.
 *
 * The admission implementation is exercised with in-memory membership
 * evidence. Handler activation is verified as explicitly disabled so this
 * probe cannot execute the migration, schema setup, or a mold mutation.
 */
$root = dirname(__DIR__, 5);
define('APP_ROOT', $root);

require_once $root . '/apps/Platform/Services/OrganizationMembershipResolver.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once $root . '/apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductOrganizationScope.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductMoldOwnership.php';
require_once $root . '/apps/Manufacturing/modules/Products/Services/ProductMoldMutationAdmission.php';

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

$check(
    \Plugins\Products\Services\ProductMoldMutationAdmission::isRuntimeActivationEnabled() === false,
    'runtime mold activation remains explicitly disabled'
);

$creation = \Plugins\Products\Services\ProductMoldMutationAdmission::admitCreation(
    ['organization_id' => 41, 'product_id' => 3, 'mold_code' => 'M-1'],
    $user,
    $repo
);
$check(($creation['allowed'] ?? false) === true, 'creation admits validated membership selection');
$check(($creation['organization_id'] ?? null) === 41, 'creation ownership is derived from opaque context');
$check(($creation['context'] ?? null) instanceof \Apps\Platform\Services\OrganizationMembershipContext, 'creation returns validated context');

foreach ([
    ['organization_id' => 41, 'mold_organization_id' => 42],
    ['organization_id' => 41, 'owner_organization_id' => 42],
    ['organization_id' => 41, 'ownership_organization_id' => 42],
] as $spoofed) {
    $result = \Plugins\Products\Services\ProductMoldMutationAdmission::admitCreation($spoofed, $user, $repo);
    $check(($result['allowed'] ?? true) === false, 'client ownership field is rejected');
    $check(($result['reason_code'] ?? '') === 'client_ownership_not_accepted', 'ownership spoof denial is explicit');
}

foreach ([null, '', '0', 0, true, [], '41.0'] as $selection) {
    $result = \Plugins\Products\Services\ProductMoldMutationAdmission::admitCreation(
        ['organization_id' => $selection],
        $user,
        $repo
    );
    $check(($result['allowed'] ?? true) === false, 'missing or malformed creation context fails closed');
}

$owned = ['id' => 7, 'product_id' => 3, 'organization_id' => 41];
$deletion = \Plugins\Products\Services\ProductMoldMutationAdmission::admitDeletion($owned, 41, $user, $repo);
$check(($deletion['allowed'] ?? false) === true, 'deletion admits an owner-matching mold');
$check(($deletion['mold']['id'] ?? null) === 7, 'admitted deletion retains canonical mold identity');

foreach ([
    [null, 'NULL-owned mold is denied'],
    [42, 'cross-organization mold is denied'],
    [41, 'missing-context mold is denied'],
] as [$owner, $label]) {
    $row = ['id' => 8, 'product_id' => 3, 'organization_id' => $owner];
    $requested = $label === 'missing-context mold is denied' ? null : 41;
    if ($owner === 42) {
        $row['organization_id'] = 42;
    }
    $result = \Plugins\Products\Services\ProductMoldMutationAdmission::admitDeletion($row, $requested, $user, $repo);
    $check(($result['allowed'] ?? true) === false, $label);
    $check(($result['mold'] ?? null) === null, 'denied deletion does not disclose mold details');
}

$products = $root . '/apps/Manufacturing/modules/Products';
$controller = (string)file_get_contents($products . '/Controllers/ProductsController.php');
$routes = (string)file_get_contents($products . '/routes.php');
$migration = (string)file_get_contents($products . '/migrations/016_part_molds_organization_ownership.sql');
$check(str_contains($controller, 'ProductMoldMutationAdmission::isRuntimeActivationEnabled()'), 'both handlers use the explicit activation gate');
$check(substr_count($controller, 'ProductMoldMutationAdmission::isRuntimeActivationEnabled()') === 2, 'creation and deletion each have an activation gate');
$check(str_contains($controller, 'ProductMoldMutationAdmission::admitCreation($input)'), 'creation handler has authoritative admission integration');
$check(str_contains($controller, 'ProductMoldMutationAdmission::admitDeletion('), 'deletion handler has authoritative admission integration');
$check(str_contains($controller, 'organization_id, '), 'active creation path persists derived ownership');
$check(str_contains($controller, 'organization_id=? LIMIT 1'), 'active deletion read selects ownership before mutation');
$check(str_contains($controller, 'AND organization_id=? LIMIT 1'), 'active deletion mutation is owner-scoped');
$check(substr_count($routes, "post('/apps/manufacturing/products/molds/add'") === 1
    && substr_count($routes, "post('/products/molds/add'") === 1,
    'canonical and compatibility creation aliases remain equivalent');
$check(substr_count($routes, "post('/apps/manufacturing/products/molds/delete'") === 1
    && substr_count($routes, "post('/products/molds/delete'") === 1,
    'canonical and compatibility deletion aliases remain equivalent');
$check(substr_count($routes, '$_POST[\'organization_id\'] ?? null') >= 1, 'deletion aliases forward one organization selection');
$check(str_contains($migration, 'organization_id INT NULL'), 'activation remains dependent on nullable Migration 016');

echo "IPM-015 mold mutation admission probe: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
