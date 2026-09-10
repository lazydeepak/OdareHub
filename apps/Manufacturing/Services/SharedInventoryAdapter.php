<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

/**
 * Manufacturing-owned adapter/port for Shared Inventory adoption.
 * References universal inventory contract (Platform\SharedInventory\Contracts\InventoryContract)
 * through adapter/service boundary — does NOT import Manufacturing business logic.
 * Does NOT alter Manufacturing stock_ledger_entries.
 * Does NOT claim universal item master.
 * Does NOT create duplicate Product/Material identity.
 * Graceful when Shared Inventory is unavailable/unpromoted.
 *
 * Per Session D contract: adaptation only; no restructuring.
 */
final class SharedInventoryAdapter
{
    private ?\Platform\SharedInventory\Contracts\InventoryContract $inventory = null;
    private ?\Platform\SharedInventory\Adapters\SharedItemAdapter $itemAdapter = null;

    public function __construct(?\Platform\SharedInventory\Contracts\InventoryContract $inventory = null, ?\Platform\SharedInventory\Adapters\SharedItemAdapter $itemAdapter = null)
    {
        $this->inventory = $inventory;
        $this->itemAdapter = $itemAdapter ?? new \Platform\SharedInventory\Adapters\SharedItemAdapter();
    }

    /**
     * Resolve a universal item reference through Session A adapter.
     */
    public function resolveItem(string $itemIdOrCode): ?array
    {
        $itemId = is_numeric($itemIdOrCode) ? (int)$itemIdOrCode : 0;
        $codeRef = is_string($itemIdOrCode) && $itemId <= 0 ? $itemIdOrCode : null;
        if ($itemId > 0) {
            $resolved = $this->itemAdapter->resolveItemId($itemId);
            return $resolved !== null ? array_merge($resolved, ['referenced_by' => 'manufacturing-adapter']) : null;
        }
        if ($codeRef !== null && strlen($codeRef) <= 255) {
            $resolved = $this->itemAdapter->resolveCodeRef($codeRef);
            return $resolved !== null ? array_merge($resolved, ['referenced_by' => 'manufacturing-adapter']) : null;
        }
        return null;
    }

    /**
     * Read-only balance check for a given item reference — adapter/service only.
     */
    public function getItemBalance(?int $itemId, ?string $codeRef, ?string $warehouseRef = null): ?float
    {
        if ($this->inventory === null) {
            return null;
        }
        if (($itemId !== null && $itemId <= 0) || ($codeRef !== null && strlen($codeRef) > 255)) {
            return null;
        }
        return $this->inventory->deriveBalance($itemId, $codeRef, $warehouseRef);
    }

    /**
     * Validate whether a requested inventory movement could reference a valid item.
     */
    public function validateMovementRequest(array $request): array
    {
        $itemId = $request['item_id'] ?? 0;
        $codeRef = $request['code_ref'] ?? null;
        $warehouseRef = $request['warehouse_ref'] ?? null;

        if ($itemId <= 0 && (empty($codeRef) || strlen($codeRef) > 255)) {
            return ['valid' => false, 'reason' => 'invalid_item_ref', 'manufacturing_adapter' => true];
        }

        $itemIdToCheck = $itemId > 0 ? $itemId : 0;
        $codeToCheck = $codeRef ?? null;
        $resolved = $this->resolveItem(is_numeric((string)$itemId) && $itemId > 0 ? $itemId : ($codeRef ?? ''));
        if ($itemId > 0 || ($codeRef !== null && strlen($codeRef) > 0)) {
            return ['valid' => true, 'item_ref' => $itemId > 0 ? $itemId : ($codeRef ?? ''), 'manufacturing_adapter' => true];
        }
        return ['valid' => false, 'reason' => 'missing_item_ref', 'manufacturing_adapter' => true];
    }

    /**
     * Confirm inventory adapter is reachable without importing Manufacturing business logic.
     */
    public function isAdapterReachable(): bool
    {
        return $this->inventory !== null && $this->inventory->isReachable();
    }
}
