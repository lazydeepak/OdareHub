<?php
declare(strict_types=1);

namespace Plugins\QCEntries;

use App\Core\EntityContext;

class QCEntryHooks
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
        if (!isset($data['status'])) {
            $data['status'] = 'Draft';
        }
        if (!isset($data['qc_type'])) {
            $data['qc_type'] = 'Final';
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
