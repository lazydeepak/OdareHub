<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;
use Apps\Platform\Services\OrganizationMembershipContext;

/**
 * Products-owned organization-scoped read seam.
 *
 * Callers must already hold an opaque, membership-validated context. This
 * service does not resolve request organization IDs and does not offer an
 * unrestricted Product read fallback.
 */
final class ProductReadService
{
    /**
     * @return array<string,mixed>|null
     */
    public static function findById(
        int $productId,
        OrganizationMembershipContext $context
    ): ?array {
        if ($productId <= 0) {
            return null;
        }

        return DB::fetchOne(
            'SELECT * FROM products
             WHERE id=? AND organization_id=?
             LIMIT 1',
            [$productId, $context->organizationId()]
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findByPartNumber(
        string $partNumber,
        OrganizationMembershipContext $context
    ): ?array {
        $partNumber = trim($partNumber);
        if ($partNumber === '') {
            return null;
        }

        return DB::fetchOne(
            'SELECT * FROM products
             WHERE parts_number=? AND organization_id=?
             LIMIT 1',
            [$partNumber, $context->organizationId()]
        );
    }

    /**
     * Resolve the QR identity while keeping both supplied identifiers
     * consistent when both are present.
     *
     * @return array<string,mixed>|null
     */
    public static function findByQrIdentity(
        int $productId,
        string $partNumber,
        OrganizationMembershipContext $context
    ): ?array {
        $partNumber = trim($partNumber);
        if ($productId > 0 && $partNumber !== '') {
            $product = self::findById($productId, $context);
            return $product !== null
                && (string)($product['parts_number'] ?? '') === $partNumber
                ? $product
                : null;
        }

        if ($productId > 0) {
            return self::findById($productId, $context);
        }

        return self::findByPartNumber($partNumber, $context);
    }
}
