<?php
declare(strict_types=1);

namespace Plugins\ProductionEntries;

use App\Core\EntityContext;

class ProductionEntryHooks
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
        if (!isset($data['production_date']) || $data['production_date'] === '') {
            $data['production_date'] = date('Y-m-d');
        }
        if (!isset($data['shift']) || $data['shift'] === '') {
            $data['shift'] = 'Day';
        }
        if (!isset($data['status']) || $data['status'] === '') {
            $data['status'] = 'Draft';
        }
        if (!isset($data['produced_qty'])) {
            $data['produced_qty'] = 0;
        }
        if (!isset($data['rejected_qty'])) {
            $data['rejected_qty'] = 0;
        }
        
        self::$auditLog[] = [
            'stage' => 'before_create',
            'data' => $data,
            'user_id' => $context->userId,
        ];
    }

    public static function afterCreate(array &$data, ?array $original, EntityContext $context): void
    {
        if (isset($data['produced_qty']) && isset($data['rejected_qty'])) {
            $data['good_qty'] = max(0, (float)$data['produced_qty'] - (float)$data['rejected_qty']);
        }
        
        self::$auditLog[] = [
            'stage' => 'after_create',
            'data' => $data,
            'user_id' => $context->userId,
        ];
    }

    public static function beforeUpdate(array &$data, ?array $original, EntityContext $context): void
    {
        if (isset($data['produced_qty']) && isset($data['rejected_qty'])) {
            $data['good_qty'] = max(0, (float)$data['produced_qty'] - (float)$data['rejected_qty']);
        }
        
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
