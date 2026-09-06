<?php
declare(strict_types=1);

namespace Plugins\ProductionPlans;

use App\Core\EntityContext;

class ProductionPlanHooks
{
    private static array $auditLog = [];

    public static function reset(): void
    {
        self::$auditLog = [];
    }

    public static function getAuditLog(): array
    {
        return self::$auditLog;
    }

    public static function beforeCreate(array &$data, ?array $original, EntityContext $context): void
    {
        if (!isset($data['plan_date'])) {
            $data['plan_date'] = date('Y-m-d');
        }
        if (!isset($data['status'])) {
            $data['status'] = 'Planned';
        }
        if (!isset($data['sequence_no'])) {
            $data['sequence_no'] = 1;
        }
        self::$auditLog[] = [
            'stage' => 'before_create',
            'data' => $data,
            'user_id' => $context->userId,
        ];
    }

    public static function afterCreate(array &$data, ?array $original, EntityContext $context): void
    {
        self::$auditLog[] = [
            'stage' => 'after_create',
            'data' => $data,
            'user_id' => $context->userId,
        ];
    }

    public static function beforeUpdate(array &$data, ?array $original, EntityContext $context): void
    {
        self::$auditLog[] = [
            'stage' => 'before_update',
            'data' => $data,
            'original' => $original,
            'user_id' => $context->userId,
        ];
    }

    public static function afterUpdate(array &$data, ?array $original, EntityContext $context): void
    {
        self::$auditLog[] = [
            'stage' => 'after_update',
            'data' => $data,
            'original' => $original,
            'user_id' => $context->userId,
        ];
    }
}
