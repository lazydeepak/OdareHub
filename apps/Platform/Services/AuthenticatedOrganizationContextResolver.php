<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;

require_once __DIR__ . '/../modules/Organization/Contracts/OrganizationMembershipRepositoryContract.php';

/**
 * Resolves organization context for the already-authenticated Core user.
 *
 * This is a Platform boundary, not an Auth replacement or an ACL grant. Core
 * remains authoritative for the session and users.id; Platform only reads that
 * identity, loads explicit membership evidence, and delegates validation to
 * OrganizationMembershipResolver.
 */
final class AuthenticatedOrganizationContextResolver
{
    /**
     * @var callable():(?array<string,mixed>)
     */
    private $authenticatedUserProvider;

    /**
     * @param callable():(?array<string,mixed>)|null $authenticatedUserProvider
     */
    public function __construct(
        private readonly OrganizationMembershipRepositoryContract $repository,
        ?callable $authenticatedUserProvider = null
    ) {
        $this->authenticatedUserProvider = $authenticatedUserProvider
            ?? static fn (): ?array => \App\Core\Auth::user();
    }

    /**
     * Resolve only an explicitly selected organization for the authenticated user.
     *
     * @return array{
     *     allowed:bool,
     *     status:string,
     *     reason_code:string,
     *     context:OrganizationMembershipContext|null,
     *     eligible_organization_ids:array<int,int>
     * }
     */
    public function resolve(
        ?int $requestedOrganizationId,
        ?DateTimeInterface $at = null
    ): array {
        try {
            $user = ($this->authenticatedUserProvider)();
        } catch (\Throwable) {
            return self::denied('authentication_unavailable', 'authentication_unavailable');
        }

        if (!is_array($user)) {
            return self::denied('unauthenticated', 'unauthenticated');
        }

        $userId = self::strictPositiveId($user['id'] ?? null);
        if ($userId === null) {
            return self::denied('invalid_authenticated_identity', 'invalid_authenticated_identity');
        }

        if ($requestedOrganizationId === null || $requestedOrganizationId <= 0) {
            return self::denied('organization_selection_required', 'organization_selection_required');
        }

        $observedAt = $at ?? new DateTimeImmutable('now');
        try {
            $evidence = $this->repository->listEvidenceForUser($userId, $observedAt);
        } catch (\Throwable) {
            return self::denied('membership_evidence_unavailable', 'membership_evidence_unavailable');
        }

        if (!is_array($evidence)) {
            return self::denied('malformed_evidence', 'malformed_evidence');
        }

        $resolved = OrganizationMembershipResolver::resolve(
            $userId,
            $evidence,
            $requestedOrganizationId,
            $observedAt
        );

        $context = $resolved['context'] ?? null;
        if ($resolved['allowed'] !== true
            || !$context instanceof OrganizationMembershipContext
            || $context->userId() !== $userId
            || $context->organizationId() !== $requestedOrganizationId
        ) {
            return $resolved['allowed'] === true
                ? self::denied('context_integrity_failure', 'context_integrity_failure')
                : $resolved;
        }

        return $resolved;
    }

    /**
     * @return array{
     *     allowed:false,
     *     status:string,
     *     reason_code:string,
     *     context:null,
     *     eligible_organization_ids:array<int,int>
     * }
     */
    private static function denied(string $status, string $reasonCode): array
    {
        return [
            'allowed' => false,
            'status' => $status,
            'reason_code' => $reasonCode,
            'context' => null,
            'eligible_organization_ids' => [],
        ];
    }

    private static function strictPositiveId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || !preg_match('/^[1-9][0-9]*$/', $value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int)$id;
    }
}
