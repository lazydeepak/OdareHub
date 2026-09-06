<?php
declare(strict_types=1);

namespace Plugins\ProductionPlans;

use App\Core\EntityContext;

class ProductionPlanPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['status'] ?? 'Planned';
        if (in_array($status, ['Completed', 'Closed', 'Cancelled'], true)) {
            return false;
        }
        return true;
    }
}
