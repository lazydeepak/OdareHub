<?php
declare(strict_types=1);

namespace Apps\Procurement\Services;

use Apps\Platform\Services\AuthenticatedOrganizationContextResolver;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;
use Plugins\Organization\Services\OrganizationMembershipRepository;

require_once APP_ROOT . '/apps/Platform/Services/AuthenticatedOrganizationContextResolver.php';
require_once APP_ROOT . '/apps/Platform/Services/OrganizationMembershipResolver.php';
require_once APP_ROOT . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once APP_ROOT . '/apps/Platform/modules/Organization/Services/OrganizationMembershipRepository.php';

/** Procurement-owned adapter over the existing fail-closed Platform context. */
final class ProcurementOrganizationScope
{
    /**
     * @param mixed $requestedOrganizationId
     * @param callable():(?array<string,mixed>)|null $authenticatedUserProvider
     * @return array<string,mixed>
     */
    public static function resolve(
        mixed $requestedOrganizationId,
        ?callable $authenticatedUserProvider = null,
        ?OrganizationMembershipRepositoryContract $repository = null
    ): array {
        $organizationId = self::positiveId($requestedOrganizationId);
        if ($organizationId === null) {
            return self::denied('invalid_organization_selection');
        }

        try {
            $resolver = new AuthenticatedOrganizationContextResolver(
                $repository ?? new OrganizationMembershipRepository(),
                $authenticatedUserProvider
            );
            return $resolver->resolve($organizationId);
        } catch (\Throwable) {
            return self::denied('organization_context_unavailable');
        }
    }

    private static function positiveId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }
        if (!is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int)$id;
    }

    /** @return array{allowed:false,status:string,reason_code:string,context:null,eligible_organization_ids:array<int,int>} */
    private static function denied(string $reason): array
    {
        return ['allowed' => false, 'status' => $reason, 'reason_code' => $reason, 'context' => null, 'eligible_organization_ids' => []];
    }
}
