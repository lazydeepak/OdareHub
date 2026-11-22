<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use Apps\Platform\Services\OrganizationMembershipContext;

require_once __DIR__ . '/ProductOrganizationScope.php';
require_once __DIR__ . '/ProductReadService.php';

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

        return ProductReadService::findByQrIdentity($productId, $partNumber, $context);
    }
}
