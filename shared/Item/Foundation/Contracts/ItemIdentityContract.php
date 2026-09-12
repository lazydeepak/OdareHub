<?php
declare(strict_types=1);

namespace Shared\Item\Foundation\Contracts;

/**
 * Shared Items Foundation — Canonical Item Identity Contract
 * Reference: docs/shared-items-foundation/canonical-item-contract.md
 * Purpose: smallest shared identity; references Manufacturing Product via item_ref
 * Not a consumer interface; not a suite extension; not a database schema
 * 
 * All identity fields are per approved contract section 3.1 / exclusions 3.2.
 */
interface ItemIdentityContract
{
    /**
     * Canonical identity retrieval by internal id.
     * @return array<string,mixed>|null
     */
    public function getById(int $item_id): ?array;

    /**
     * Retrieval by business reference / SKU / part-number reference.
     * Manufacturing Product → item_ref references this.
     * @return array<string,mixed>|null
     */
    public function getByCode(string $code_ref): ?array;

    /**
     * List with optional filter (status, category_ref range).
     * @return array<int,array<string,mixed>>
     */
    public function list(?array $filters = null): array;

    /**
     * Create item with validated canonical fields only.
     * Rejects manufacturing-specific fields (model, producer, lead, cycle_time, etc.).
     * @param array<string,mixed> $fields
     * @return array<string,mixed>
     */
    public function create(array $fields): array;

    /**
     * Update permitted canonical fields only.
     * @return array<string,mixed>
     */
    public function update(int $item_id, array $fields): array;

    /**
     * Deactivate (status -> deprecated/archived per contract lifecycle).
     * @return array<string,mixed>
     */
    public function deactivate(int $item_id): array;

    /**
     * Reactivate if previously deprecated.
     * @return array<string,mixed>
     */
    public function activate(int $item_id): array;

    /**
     * Reference shape for Manufacturing Product -> Shared Item.
     * Deterministic: item_id (int) + code_ref (string) + name (string).
     * @param int $item_id
     * @param string $code_ref
     * @return array<string,mixed>
     */
    public function itemRef(int $item_id, string $code_ref): array;
}
