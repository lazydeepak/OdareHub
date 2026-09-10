<?php
declare(strict_types=1);

namespace Platform\SharedInventory\Contracts;

/**
 * Shared Items adapter contract — references Session A canonical Item identity
 * (item_id integer, code_ref string) without importing Manufacturing Products
 * or inventing Product/Material identity.
 */
interface SharedItemAdapterContract
{
    public function resolveItemId(int $itemId): ?array;
    public function resolveCodeRef(string $codeRef): ?array;
    public function exists(int $itemId, ?string $codeRef): bool;
    public function isReachable(): bool;
}
