<?php

declare(strict_types=1);

namespace Apps\Manufacturing\Modules\Bom;

use App\Core\EntityContext;

class BomHooks
{
    private static array $auditLog = [];

    public static function reset(): void
    {
        self::$auditLog = [];
    }

    /** @return array<int,array<string,mixed>> */
    public static function getAuditLog(): array
    {
        return self::$auditLog;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $original
     */
    public static function beforeCreate(array &$data, ?array $original, EntityContext $context): void
    {
        if (!isset($data['status'])) {
            $data['status'] = 'draft';
        }
        self::$auditLog[] = ['stage' => 'before_create', 'user_id' => $context->userId];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $original
     */
    public static function afterCreate(array &$data, ?array $original, EntityContext $context): void
    {
        self::$auditLog[] = ['stage' => 'after_create', 'data' => $data, 'user_id' => $context->userId];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $original
     */
    public static function beforeUpdate(array &$data, ?array $original, EntityContext $context): void
    {
        self::$auditLog[] = [
            'stage' => 'before_update',
            'data' => $data,
            'original' => $original,
            'user_id' => $context->userId,
        ];
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $original
     */
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