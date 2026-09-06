<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyPlans;

use App\Core\EntityContext;

class AssemblyPlanPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['status'] ?? 'calculated';
        if (in_array($status, ['completed', 'cancelled'], true)) {
            return false;
        }
        return true;
    }
}
