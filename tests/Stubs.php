<?php

namespace Tests;

use App\Core\EntityContext;

final class TestPolicy
{
    public static function canEditRow(array $row, EntityContext $context): bool
    {
        return ($row['status'] ?? '') !== 'completed';
    }
}

final class TestHooks
{
    private static ?array $lastOriginalRow = null;
    private static bool $beforeDeleteCalled = false;

    public static function reset(): void
    {
        self::$lastOriginalRow = null;
        self::$beforeDeleteCalled = false;
    }

    public static function getLastOriginalRow(): ?array
    {
        return self::$lastOriginalRow;
    }

    public static function wasBeforeDeleteCalled(): bool
    {
        return self::$beforeDeleteCalled;
    }

    public static function beforeCreateDefaults(array &$data, ?array $original, EntityContext $context): void
    {
        $data['order_date'] ??= 'today';
        $data['status'] ??= 'draft';
    }

    public static function afterCreateSetTotal(array &$data, ?array $original, EntityContext $context): void
    {
        $data['total_amount'] = 100.0;
    }

    public static function beforeUpdateMutate(array &$data, ?array $original, EntityContext $context): void
    {
        self::$lastOriginalRow = $original;
        if (isset($data['status']) && $data['status'] === 'confirmed') {
            $data['status'] = 'confirmed-mutated';
        }
    }

    public static function afterUpdateLog(array $data, ?array $original, EntityContext $context): void
    {
    }

    public static function beforeDeleteCheck(array $data, ?array $original, EntityContext $context): void
    {
        self::$beforeDeleteCalled = true;
    }
}

final class GatewayStub
{
    private static array $storage = [];
    private static array $calls = [];

    public function create(string $entityKey, array $row): int
    {
        $id = count(self::$storage) + 1;
        self::$storage[$id] = $row;
        self::$calls[] = ['method' => 'create', 'entity' => $entityKey, 'row' => $row, 'id' => $id];
        return $id;
    }

    public function read(string $entityKey, int $id): ?array
    {
        self::$calls[] = ['method' => 'read', 'entity' => $entityKey, 'id' => $id];
        return self::$storage[$id] ?? null;
    }

    public function update(string $entityKey, int $id, array $row): bool
    {
        if (!isset(self::$storage[$id])) {
            return false;
        }
        self::$storage[$id] = array_merge(self::$storage[$id], $row);
        self::$calls[] = ['method' => 'update', 'entity' => $entityKey, 'id' => $id, 'row' => $row];
        return true;
    }

    public function delete(string $entityKey, int $id): bool
    {
        if (!isset(self::$storage[$id])) {
            return false;
        }
        unset(self::$storage[$id]);
        self::$calls[] = ['method' => 'delete', 'entity' => $entityKey, 'id' => $id];
        return true;
    }

    public function getLastCall(): ?array
    {
        return self::$calls ? end(self::$calls) : null;
    }

    public static function resetStatic(): void
    {
        self::$storage = [];
        self::$calls = [];
    }
}
