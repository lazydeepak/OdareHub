<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/apps/Platform/modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once $root . '/apps/Platform/Services/OrganizationMembershipResolver.php';
require_once $root . '/apps/Platform/Services/AuthenticatedOrganizationContextResolver.php';

use Apps\Platform\Services\AuthenticatedOrganizationContextResolver;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;

class ProbeOrganizationMembershipRepository implements OrganizationMembershipRepositoryContract
{
    /** @var array<int,array<string,mixed>> */
    private array $evidence;

    /** @var array<int,int> */
    public array $requestedUserIds = [];

    /** @param array<int,array<string,mixed>> $evidence */
    public function __construct(array $evidence)
    {
        $this->evidence = $evidence;
    }

    public function listEvidenceForUser(int $userId, \DateTimeInterface $observedAt): array
    {
        $this->requestedUserIds[] = $userId;
        return array_values(array_filter(
            $this->evidence,
            static fn (array $row): bool => ($row['user_id'] ?? null) === $userId
        ));
    }

    public function findById(int $membershipId): ?array
    {
        return null;
    }

    public function create(array $command): array
    {
        return ['ok' => false, 'state' => 'unsupported', 'record' => null];
    }

    public function activate(int $membershipId, int $actorUserId, \DateTimeInterface $observedAt): array
    {
        return ['ok' => false, 'state' => 'unsupported', 'record' => null];
    }

    public function revoke(int $membershipId, int $actorUserId, \DateTimeInterface $observedAt, string $reason): array
    {
        return ['ok' => false, 'state' => 'unsupported', 'record' => null];
    }

    public function replaceActive(
        int $userId,
        int $organizationId,
        array $command,
        int $actorUserId,
        \DateTimeInterface $observedAt
    ): array {
        return ['ok' => false, 'state' => 'unsupported', 'record' => null];
    }
}

final class ThrowingOrganizationMembershipRepository extends ProbeOrganizationMembershipRepository
{
    public function listEvidenceForUser(int $userId, \DateTimeInterface $observedAt): array
    {
        throw new RuntimeException('repository unavailable');
    }
}

$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    $checks++;
    if (!$ok) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$active = static function (int $userId, int $organizationId, array $extra = []): array {
    return array_merge([
        'user_id' => $userId,
        'organization_id' => $organizationId,
        'is_active' => true,
        'is_revoked' => false,
        'organization_is_active' => true,
    ], $extra);
};
$at = new \DateTimeImmutable('2026-10-26T12:00:00+00:00');

$repo = new ProbeOrganizationMembershipRepository([
    $active(7, 11),
    $active(7, 12),
    $active(8, 11),
]);
$resolver = new AuthenticatedOrganizationContextResolver($repo, static fn (): array => ['id' => 7]);

$selected = $resolver->resolve(12, $at);
$assert($selected['allowed'] === true, 'authenticated user resolves selected active organization');
$assert($selected['context']?->userId() === 7, 'context uses exact authenticated users.id');
$assert($selected['context']?->organizationId() === 12, 'context uses explicit selected organization');
$assert($selected['context']?->evidenceFingerprint() !== '', 'context retains membership evidence fingerprint');
$assert($repo->requestedUserIds === [7], 'repository receives only the authenticated users.id');

$singleMembershipResolver = new AuthenticatedOrganizationContextResolver(
    new ProbeOrganizationMembershipRepository([$active(7, 11)]),
    static fn (): array => ['id' => 7]
);
$assert(
    $singleMembershipResolver->resolve(null, $at)['reason_code'] === 'organization_selection_required',
    'single membership still requires explicit selection'
);

$assert(
    $resolver->resolve(null, $at)['reason_code'] === 'organization_selection_required',
    'missing selection is denied before membership lookup'
);
$assert(
    $resolver->resolve(13, $at)['reason_code'] === 'organization_not_member',
    'organization outside authenticated membership set is denied'
);
$duplicateResolver = new AuthenticatedOrganizationContextResolver(
    new ProbeOrganizationMembershipRepository([$active(7, 11), $active(7, 11)]),
    static fn (): array => ['id' => 7]
);
$assert(
    $duplicateResolver->resolve(11, $at)['reason_code'] === 'duplicate_active_membership',
    'duplicate active membership is denied'
);

$otherUserResolver = new AuthenticatedOrganizationContextResolver(
    $repo,
    static fn (): array => ['id' => 8]
);
$otherUser = $otherUserResolver->resolve(12, $at);
$assert($otherUser['reason_code'] === 'organization_not_member', 'cross-user organization selection is denied');

$assert(
    (new AuthenticatedOrganizationContextResolver($repo, static fn (): ?array => null))->resolve(11, $at)['reason_code'] === 'unauthenticated',
    'missing authenticated user is denied'
);
$assert(
    (new AuthenticatedOrganizationContextResolver($repo, static fn (): array => ['id' => 0]))->resolve(11, $at)['reason_code'] === 'invalid_authenticated_identity',
    'invalid authenticated identity is denied'
);

$inactiveRepo = new ProbeOrganizationMembershipRepository([$active(7, 11, ['organization_is_active' => false])]);
$assert(
    (new AuthenticatedOrganizationContextResolver($inactiveRepo, static fn (): array => ['id' => 7]))->resolve(11, $at)['reason_code'] === 'organization_inactive',
    'inactive organization is denied'
);
$revokedRepo = new ProbeOrganizationMembershipRepository([$active(7, 11, ['is_revoked' => true])]);
$assert(
    (new AuthenticatedOrganizationContextResolver($revokedRepo, static fn (): array => ['id' => 7]))->resolve(11, $at)['reason_code'] === 'organization_not_member',
    'revoked membership is denied'
);
$assert(
    (new AuthenticatedOrganizationContextResolver(new ThrowingOrganizationMembershipRepository([]), static fn (): array => ['id' => 7]))->resolve(11, $at)['reason_code'] === 'membership_evidence_unavailable',
    'repository failure is denied'
);

$authFailure = new AuthenticatedOrganizationContextResolver(
    $repo,
    static function (): ?array {
        throw new RuntimeException('auth unavailable');
    }
);
$assert($authFailure->resolve(11, $at)['reason_code'] === 'authentication_unavailable', 'authentication failure is denied');

echo "Authenticated organization context probe: {$checks}/{$checks} assertions passed\n";
