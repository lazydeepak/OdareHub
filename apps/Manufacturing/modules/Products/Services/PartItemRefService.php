<?php

declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;

final class PartItemRefService
{
    public static function columnExists(): bool
    {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'item_ref'"
        );
        return (int)($row['c'] ?? 0) > 0;
    }

    /**
     * @return array{ok:bool,error:?string}
     */
    public static function validateItemRef(?int $itemRef, int $excludeProductId = 0): array
    {
        if ($itemRef === null) {
            return ['ok' => true, 'error' => null];
        }
        if ($itemRef <= 0) {
            return ['ok' => false, 'error' => 'bom.error.invalid_item_ref'];
        }
        $dup = DB::fetchOne(
            'SELECT id FROM products WHERE item_ref = ? AND id <> ? LIMIT 1',
            [$itemRef, $excludeProductId]
        );
        if ($dup) {
            return ['ok' => false, 'error' => 'product.item_ref.duplicate'];
        }
        return ['ok' => true, 'error' => null];
    }

    public static function assign(int $productId, ?int $itemRef, array $actor): void
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Invalid product id.');
        }
        $check = self::validateItemRef($itemRef, $productId);
        if (!$check['ok']) {
            throw new \InvalidArgumentException((string)($check['error'] ?? 'Invalid item reference.'));
        }
        DB::query(
            'UPDATE products SET item_ref = ? WHERE id = ? LIMIT 1',
            [$itemRef, $productId]
        );
    }

    public static function clear(int $productId, array $actor): void
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Invalid product id.');
        }
        DB::query('UPDATE products SET item_ref = NULL WHERE id = ? LIMIT 1', [$productId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function referenceableProducts(): array
    {
        if (!self::columnExists()) {
            return [];
        }
        return DB::fetchAll(
            'SELECT id, parts_name, parts_number, item_ref FROM products WHERE item_ref IS NOT NULL ORDER BY parts_name ASC LIMIT 1200',
            []
        );
    }
}