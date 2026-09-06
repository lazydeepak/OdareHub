<?php
declare(strict_types=1);

namespace Plugins\DailyOrders;

use App\Core\EntityContext;

class DailyOrderPolicies
{
    public static function canEdit(array $row, EntityContext $context): bool
    {
        $status = $row['status'] ?? 'Open';
        if ($status === 'Fulfilled' || $status === 'Cancelled') {
            return false;
        }
        return true;
    }
}
