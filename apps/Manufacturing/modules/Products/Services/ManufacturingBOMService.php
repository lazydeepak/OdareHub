<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Module\Products\Services;

use Shared\Item\Foundation\Contracts\ItemIdentityContract;
use Apps\Manufacturing\Module\Products\Services\SharedItemAdapter;

/**
 * Manufacturing BOM / Recipe — Manufacturing-owned extension of Shared Item.
 * References finished item_ref and component item_ref through Shared Items contract.
 * No Inventory, no pricing, no procurement data.
 */
class ManufacturingBOMService
{
    private ?ItemIdentityService $itemService;

    public function __construct(?ItemIdentityService $itemService = null)
    {
        $this->itemService = $itemService;
    }

    public function resolveBomForFinishedItem(int $item_id, ?string $version = null): ?array
    {
        // Manufacturing-owned lookup — does not access Shared Item persistence directly
        // Reference only via item_id; actual BOM data lives in manufacturing_bom
        return null; // Placeholder: owning session implements DB query
    }
}
