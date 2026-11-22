<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use Apps\Platform\Services\OrganizationMembershipContext;
use Plugins\Organization\Contracts\OrganizationMembershipRepositoryContract;

require_once __DIR__ . '/../../../../../apps/Platform/Services/OrganizationMembershipContext.php';
require_once __DIR__ . '/ProductOrganizationScope.php';

/**
 * Database-free mold ownership admission foundation.
 *
 * Selection admission delegates to the existing Platform membership resolver.
 * Mold admission accepts only the opaque context produced by that resolver and
 * never treats a raw organization ID, Product relationship, creator, or
 * assignment as ownership evidence.
 */
final class ProductMoldOwnership
{
    /**
     * Resolve an explicit organization selection through the existing
     * Products-to-Platform context boundary.
     *
     * @param mixed $requestedOrganizationId
     * @param callable():(?array<string,mixed>)|null $authenticatedUserProvider
     * @return array<string,mixed>
     */
    public static function resolveOrganization(
        mixed $requestedOrganizationId,
        ?callable $authenticatedUserProvider = null,
        ?OrganizationMembershipRepositoryContract $repository = null
    ): array {
        return ProductOrganizationScope::resolve(
            $requestedOrganizationId,
            $authenticatedUserProvider,
            $repository
        );
    }

    /**
     * Admit one mold row for an already membership-validated context.
     *
     * Denial never returns the supplied mold row. A NULL organization is a
     * preserved legacy state and is not converted into ownership evidence.
     *
     * @param array<string,mixed> $mold
     * @return array{
     *     allowed:bool,
     *     status:string,
     *     reason_code:string,
     *     context:OrganizationMembershipContext|null,
     *     mold:array<string,mixed>|null
     * }
     */
    public static function admit(
        array $mold,
        ?OrganizationMembershipContext $context
    ): array {
        if (!$context instanceof OrganizationMembershipContext
            || $context->userId() <= 0
            || $context->organizationId() <= 0
            || trim($context->evidenceFingerprint()) === ''
        ) {
            return self::denied('membership_context_required');
        }

        if (!array_key_exists('organization_id', $mold)
            || $mold['organization_id'] === null
        ) {
            return self::denied('legacy_mold_owner_unresolved');
        }

        $moldOrganizationId = self::positiveId($mold['organization_id']);
        if ($moldOrganizationId === null) {
            return self::denied('malformed_mold_owner');
        }

        if ($moldOrganizationId !== $context->organizationId()) {
            return self::denied('mold_owner_mismatch');
        }

        return [
            'allowed' => true,
            'status' => 'admitted',
            'reason_code' => 'mold_owner_match',
            'context' => $context,
            'mold' => $mold,
        ];
    }

    /**
     * @return array{
     *     allowed:false,
     *     status:string,
     *     reason_code:string,
     *     context:null,
     *     mold:null
     * }
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
}
