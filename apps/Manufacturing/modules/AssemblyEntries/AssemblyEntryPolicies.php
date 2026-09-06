<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyEntries;

use App\Core\EntityContext;

class AssemblyEntryPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['status'] ?? 'draft';
        if (in_array($status, ['approved', 'cancelled'], true)) {
            return false;
        }
        return true;
    }
}
