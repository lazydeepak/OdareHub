<?php
declare(strict_types=1);

namespace Plugins\ProductionEntries;

use App\Core\EntityContext;

class ProductionEntryPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['status'] ?? 'Draft';
        if ($status === 'Posted' || $status === 'Cancelled') {
            return false;
        }
        return true;
    }
}
