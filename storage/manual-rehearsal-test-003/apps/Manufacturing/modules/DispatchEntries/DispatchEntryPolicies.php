<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries;

use App\Core\EntityContext;

class DispatchEntryPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['dispatch_status'] ?? 'Draft';
        if (in_array($status, ['Dispatched', 'Cancelled'], true)) {
            return false;
        }
        return true;
    }
}
