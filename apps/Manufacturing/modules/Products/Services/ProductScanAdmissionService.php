<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;
use Apps\Platform\Services\OrganizationMembershipContext;

require_once __DIR__ . '/ProductOrganizationScope.php';

/**
 * Products-owned read admission for the bounded QR scan consumer.
 *
 * Anonymous QR identification remains reachable, but it never performs a
 * Product lookup. Detail data requires an authenticated, membership-verified
 * organization selection. The request organization value is only resolver
 * input; it is never authorization evidence by itself.
 */
final class ProductScanAdmissionService
{
    /**
     * @param mixed $requestedOrganizationId
     * @return array<string,mixed>|null
     */
    public static function resolve(
        int $productId,
        string $partNumber,
        mixed $requestedOrganizationId
    ): ?array {
        $organization = ProductOrganizationScope::resolve($requestedOrganizationId);
        $context = $organization['context'] ?? null;
        if (($organization['allowed'] ?? false) !== true
            || !$context instanceof OrganizationMembershipContext
        ) {
            return null;
        }

        $organizationId = $context->organizationId();
        $partNumber = trim($partNumber);
        if ($productId > 0) {
            if ($partNumber !== '') {
                return DB::fetchOne(
                    'SELECT * FROM products
                     WHERE id=? AND parts_number=? AND organization_id=?
                     LIMIT 1',
                    [$productId, $partNumber, $organizationId]
                );
            }

            return DB::fetchOne(
                'SELECT * FROM products
                 WHERE id=? AND organization_id=?
                 LIMIT 1',
                [$productId, $organizationId]
            );
        }

        if ($partNumber === '') {
            return null;
        }

        return DB::fetchOne(
            'SELECT * FROM products
             WHERE parts_number=? AND organization_id=?
             LIMIT 1',
            [$partNumber, $organizationId]
        );
    }
}
