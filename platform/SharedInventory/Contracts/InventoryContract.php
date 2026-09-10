<?php
declare(strict_types=1);

namespace Platform\SharedInventory\Contracts;

/**
 * Shared Inventory Foundation — Canonical Contract (Session C)
 *
 * Defines the universal inventory domain: location, movement, balance, source,
 * idempotency, reversal, item reference — independent of any domain app.
 *
 * References:
 * - Session A Shared Items: abstract item_ref (item_id + code_ref)
 * - Session C audit: Manufacturing-specific stock_ledger_entries must NOT become
 *   universal inventory mechanism directly; adapter/service contracts only.
 * - Session D governance: Shared App / Suite Extension / dependency direction.
 *
 * No Manufacturing business logic imported.
 * No Product/Material identity created.
 * No session C runtime execution without authorization.
 */
interface InventoryContract
{
    /**
     * Record an inventory movement through adapter/service contract.
     * Must not import Manufacturing/Procurement/Sales business logic.
     * Must use universal item_ref (Session A abstract); must not assume
     * Manufacturing products table.
     */
    public function recordMovement(
        string $warehouseRef,
        ?string $locationRef,
        int $itemId,
        ?string $codeRef,
        string $movementType,
        float $qtyDelta,
        ?string $sourceDomain,
        ?string $sourceTransactionRef,
        ?string $referenceNo,
        ?string $notes,
        bool $checkIdempotency = true
    ): array;

    /**
     * Retrieve universal movements by item and/or warehouse.
     */
    public function getMovements(
        ?int $itemId,
        ?string $codeRef,
        ?string $warehouseRef,
        ?string $sourceDomain
    ): array;

    /**
     * Derive universal balance from ordered universal movement sequence.
     */
    public function deriveBalance(
        ?int $itemId,
        ?string $codeRef,
        ?string $warehouseRef
    ): float;

    /**
     * Apply a universal correction/reversal through adapter contract.
     */
    public function applyAdjustment(
        int $itemId,
        ?string $codeRef,
        string $warehouseRef,
        float $beforeQty,
        float $afterQty,
        ?string $reasonRef,
        ?string $authorRef,
        ?string $notes
    ): array;

    /**
     * Verify item reference is valid through Shared Items adapter.
     */
    public function validateItemRef(int $itemId, ?string $codeRef): bool;
}
