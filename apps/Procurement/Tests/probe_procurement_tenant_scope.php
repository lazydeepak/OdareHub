<?php
declare(strict_types=1);

/** Database-free OH-AUTO4-PROC-003 tenant-scope contract probe. */
$root = dirname(__DIR__, 3);
define('APP_ROOT', $root);
require_once $root . '/apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipResolver.php';
require_once $root . '/apps/Platform/Services/AuthenticatedOrganizationContextResolver.php';
require_once $root . '/apps/Procurement/Services/ProcurementOrganizationScope.php';

use Apps\Platform\Services\OrganizationMembershipResolver;
use Apps\Procurement\Services\ProcurementOrganizationScope;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$evidence = static function (int $userId, int $organizationId, string $status = 'active'): array {
    return [[
        'membership_key' => 'm-' . $userId . '-' . $organizationId,
        'user_id' => $userId,
        'organization_id' => $organizationId,
        'is_active' => $status === 'active',
        'is_revoked' => $status === 'revoked',
        'organization_is_active' => true,
        'valid_from' => null,
        'valid_until' => null,
    ]];
};

$repository = static function (array $rows): OrganizationMembershipRepositoryContract {
    return new class($rows) implements OrganizationMembershipRepositoryContract {
        public function __construct(private array $rows) {}
        public function listEvidenceForUser(int $userId, DateTimeInterface $observedAt): array { return $this->rows; }
        public function findById(int $membershipId): ?array { return null; }
        public function create(array $command): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
        public function activate(int $membershipId, int $actorUserId, DateTimeInterface $observedAt): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
        public function revoke(int $membershipId, int $actorUserId, DateTimeInterface $observedAt, string $reason): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
        public function replaceActive(int $userId, int $organizationId, array $command, int $actorUserId, DateTimeInterface $observedAt): array { return ['ok' => false, 'state' => 'unsupported', 'record' => null]; }
    };
};

$sameTenant = ProcurementOrganizationScope::resolve('11', static fn (): array => ['id' => 7], $repository($evidence(7, 11)));
$check(($sameTenant['allowed'] ?? false) === true && $sameTenant['context']->organizationId() === 11, 'same-tenant explicit membership resolves');

$crossTenant = ProcurementOrganizationScope::resolve(12, static fn (): array => ['id' => 7], $repository($evidence(7, 11)));
$check(($crossTenant['allowed'] ?? true) === false && ($crossTenant['reason_code'] ?? '') === 'organization_not_member', 'cross-tenant organization is denied');

$missing = ProcurementOrganizationScope::resolve(null, static fn (): array => ['id' => 7], $repository($evidence(7, 11)));
$check(($missing['allowed'] ?? true) === false && ($missing['reason_code'] ?? '') === 'invalid_organization_selection', 'missing organization selection fails closed');

$revoked = ProcurementOrganizationScope::resolve(11, static fn (): array => ['id' => 7], $repository($evidence(7, 11, 'revoked')));
$check(($revoked['allowed'] ?? true) === false && ($revoked['reason_code'] ?? '') === 'organization_not_member', 'revoked membership is denied');

$ambiguous = OrganizationMembershipResolver::resolve(7, array_merge($evidence(7, 11), $evidence(7, 12)), null);
$check(($ambiguous['allowed'] ?? true) === false && ($ambiguous['reason_code'] ?? '') === 'organization_selection_required', 'ambiguous identity requires explicit organization selection');

$unauthenticated = ProcurementOrganizationScope::resolve(11, static fn (): ?array => null, $repository($evidence(7, 11)));
$check(($unauthenticated['allowed'] ?? true) === false && ($unauthenticated['reason_code'] ?? '') === 'unauthenticated', 'unauthenticated actor is denied');

$service = (string)file_get_contents($root . '/apps/Procurement/Services/ProcurementOverviewService.php');
$controller = (string)file_get_contents($root . '/apps/Procurement/Controllers/ProcurementDashboardController.php');
$migration = (string)file_get_contents($root . '/apps/Procurement/migrations/20261031_000001_add_request_organization_ownership.sql');
$operatorView = (string)file_get_contents($root . '/apps/Procurement/Views/operator/requests.php');

$check(str_contains($migration, 'organization_id INT NULL'), 'migration keeps legacy ownership nullable');
$check(str_contains($migration, 'FOREIGN KEY (organization_id) REFERENCES org_companies(id)'), 'migration binds request ownership to organizations');
$check(!preg_match('/\bUPDATE\s+procurement_requests\b/i', $migration), 'migration performs no ownership backfill');
$check(str_contains($service, "'organization_id'") && str_contains($service, 'r.organization_id = ?'), 'readiness and operator SQL require organization ownership');
$check(str_contains($service, "\$where = ['r.created_by = ?']") && str_contains($service, "\$where[] = 'r.organization_id = ?'"), 'creator and organization predicates are both mandatory');
$check(str_contains($service, 'organization_id, request_ref') && str_contains($service, '$organizationId,'), 'manual and intake inserts persist the verified organization');
$check(str_contains($controller, 'ProcurementOrganizationScope::resolve') && str_contains($controller, 'requireOrganizationId'), 'creation controller resolves Platform organization context');
$check(str_contains($operatorView, 'organization_id') && str_contains($operatorView, 'ProcurementOrganizationScope::resolve'), 'operator read surface requires explicit authenticated organization context');
$check(str_contains($service, 'organizationId === null || $organizationId <= 0'), 'missing ownership cannot enter request creation');

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
