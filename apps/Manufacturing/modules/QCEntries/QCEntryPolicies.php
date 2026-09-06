<?php
declare(strict_types=1);

namespace Plugins\QCEntries;

use App\Core\EntityContext;

class QCEntryPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['status'] ?? 'Draft';
        if (in_array($status, ['Approved', 'Cancelled'], true)) {
            return false;
        }
        return true;
    }
}
