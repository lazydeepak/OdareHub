<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Module\Products\Services;

use Shared\Item\Foundation\Contracts\ItemIdentityContract;
use Shared\Item\Foundation\Services\ItemIdentityService;

/**
 * Manufacturing Product → Shared Items adoption adapter.
 * Read-only resolution and reference validation only.
 * No Manufacturing identity taken over; no inventory/stock extension.
 * References contract: docs/shared-items-foundation/canonical-item-contract.md
 */
class SharedItemAdapter
{
    private ?ItemIdentityService $itemService = null;

    public function __construct(?ItemIdentityService $itemService = null)
    {
        $this->itemService = $itemService;
    }

    /**
     * Resolve Shared Item by item_ref (item_id + optional code_ref validation).
     * @return array<string,mixed>|null
     */
    public function resolveByItemId(int $item_id): ?array
    {
        if ($this->itemService === null) {
            return null;
        }
        try {
            return $this->itemService->getById($item_id);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Validate item_ref matches both id and code_ref (mismatch = reject).
     */
    public function validateRef(int $item_id, string $code_ref): bool
    {
        $item = $this->resolveByItemId($item_id);
        if ($item === null) {
            throw new \InvalidArgumentException("Shared Item not found: $item_id");
        }
        if (($item['code_ref'] ?? '') !== $code_ref) {
            throw new \InvalidArgumentException("Shared Item code_ref mismatch: expected $code_ref");
        }
        return true;
    }

    /**
     * Build deterministic reference for Manufacturing Product.
     */
    public function buildItemRef(int $item_id, string $code_ref): array
    {
        $item = $this->resolveByItemId($item_id);
        if ($item === null) {
            throw new \InvalidArgumentException("Shared Item not found for reference: $item_id / $code_ref");
        }
        if (($item['code_ref'] ?? '') !== $code_ref) {
            throw new \InvalidArgumentException("Shared Item code_ref mismatch: expected $code_ref, got " . ($item['code_ref'] ?? 'NONE'));
        }
        return [
            'item_ref' => [
                'item_id' => $item_id,
                'code_ref' => $code_ref,
                'ref_shape' => 'canonical-item-identity',
            ],
            'item_status' => $item['status'] ?? 'unknown',
            'shared_owner' => 'Shared Items',
        ];
    }
}
