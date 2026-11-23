<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use Apps\Platform\Services\OrganizationMembershipContext;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;

require_once __DIR__ . '/ProductMoldOwnership.php';

/**
 * Products-owned mold mutation admission boundary.
 *
 * The admission implementation is source-complete but intentionally inactive
 * until Migration 016 and the legacy runtime schema path are qualified against
 * an owner-controlled disposable database. No caller may enable this class by
 * request input or by supplying a raw organization identifier as ownership.
 */
final class ProductMoldMutationAdmission
{
    public static function isRuntimeActivationEnabled(): bool
    {
        return false;
    }

    /**
     * Resolve creation ownership from an explicit membership selection.
     *
     * organization_id is a context selection, not an ownership assertion. The
     * returned ownership value is always derived from the opaque context.
     *
     * @param array<string,mixed> $input
     * @param callable():(?array<string,mixed>)|null $authenticatedUserProvider
     * @return array<string,mixed>
     */
    public static function admitCreation(
        array $input,
        ?callable $authenticatedUserProvider = null,
        ?OrganizationMembershipRepositoryContract $repository = null
    ): array {
        foreach (['mold_organization_id', 'owner_organization_id', 'ownership_organization_id'] as $spoofKey) {
            if (array_key_exists($spoofKey, $input)) {
                return self::denied('client_ownership_not_accepted');
            }
        }

        $selection = ProductMoldOwnership::resolveOrganization(
            $input['organization_id'] ?? null,
            $authenticatedUserProvider,
            $repository
        );
        $context = $selection['context'] ?? null;
        if (($selection['allowed'] ?? false) !== true
            || !$context instanceof OrganizationMembershipContext
        ) {
            return self::denied((string)($selection['reason_code'] ?? 'membership_context_required'));
        }

        return [
            'allowed' => true,
            'status' => 'admitted',
            'reason_code' => 'creation_owner_from_membership_context',
            'context' => $context,
            'organization_id' => $context->organizationId(),
        ];
    }

    /**
     * Resolve and admit an existing mold for deletion.
     *
     * @param array<string,mixed> $mold
     * @param callable():(?array<string,mixed>)|null $authenticatedUserProvider
     * @return array<string,mixed>
     */
    public static function admitDeletion(
        array $mold,
        mixed $requestedOrganizationId,
        ?callable $authenticatedUserProvider = null,
        ?OrganizationMembershipRepositoryContract $repository = null
    ): array {
        $selection = ProductMoldOwnership::resolveOrganization(
            $requestedOrganizationId,
            $authenticatedUserProvider,
            $repository
        );
        $context = $selection['context'] ?? null;
        if (($selection['allowed'] ?? false) !== true
            || !$context instanceof OrganizationMembershipContext
        ) {
            return self::denied((string)($selection['reason_code'] ?? 'membership_context_required'));
        }

        $admission = ProductMoldOwnership::admit($mold, $context);
        if (($admission['allowed'] ?? false) !== true) {
            return $admission;
        }

        return [
            'allowed' => true,
            'status' => 'admitted',
            'reason_code' => 'deletion_owner_matches_membership_context',
            'context' => $context,
            'mold' => $admission['mold'],
        ];
    }

    /**
     * @return array{allowed:false,status:string,reason_code:string,context:null,mold:null}
     */
    private static function denied(string $reason): array
    {
        return [
            'allowed' => false,
            'status' => 'unavailable',
            'reason_code' => $reason,
            'context' => null,
            'mold' => null,
        ];
    }
}
