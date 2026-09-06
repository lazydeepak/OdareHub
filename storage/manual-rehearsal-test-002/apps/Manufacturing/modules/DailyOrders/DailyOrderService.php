<?php
declare(strict_types=1);

namespace Plugins\DailyOrders;

use App\Core\EntityStore;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;

class DailyOrderService
{
    private static ?object $store = null;

    public static function getStore(): object
    {
        if (self::$store === null) {
            self::$store = new EntityStore(
                new EntityRegistry(),
                new DBDailyOrderGateway(),
                new EntityHookRunner(),
                new EntityPolicyResolver(),
                new EntitySlaEngine(),
                new EntityWorkflowGuard(),
                new EntityDefinitionValidator()
            );
        }
        return self::$store;
    }

    public static function setStore(object $store): void
    {
        self::$store = $store;
    }

    public static function resetStore(): void
    {
        self::$store = null;
    }

    public static function create(array $data, EntityContext $context): int
    {
        register_daily_orders_entity();
        return self::getStore()->create('DailyOrder', $data, $context);
    }

    public static function update(int $id, array $data, EntityContext $context, ?array $original = null): bool
    {
        register_daily_orders_entity();
        return self::getStore()->update('DailyOrder', $id, $data, $context, $original);
    }

    public static function delete(int $id, EntityContext $context): bool
    {
        register_daily_orders_entity();
        return self::getStore()->delete('DailyOrder', $id, $context);
    }

    public static function find(int $id, EntityContext $context): ?array
    {
        register_daily_orders_entity();
        return self::getStore()->find('DailyOrder', $id, $context);
    }
}

class DBDailyOrderGateway
{
    public function create(string $entityKey, array $row): int
    {
        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $values = array_values($row);
        
        \App\Core\DB::query(
            "INSERT INTO daily_orders ({$columns}) VALUES ({$placeholders})",
            $values
        );
        
        return (int)\App\Core\DB::conn()->insert_id;
    }

    public function read(string $entityKey, int $id): ?array
    {
        return \App\Core\DB::fetchOne('SELECT * FROM daily_orders WHERE id=? LIMIT 1', [$id]);
    }

    public function update(string $entityKey, int $id, array $row): bool
    {
        if (empty($row)) {
            return true;
        }
        
        $sets = [];
        $values = [];
        foreach ($row as $col => $val) {
            $sets[] = "{$col}=?";
            $values[] = $val;
        }
        $values[] = $id;
        
        $sql = 'UPDATE daily_orders SET ' . implode(', ', $sets) . ' WHERE id=?';
        \App\Core\DB::query($sql, $values);
        
        return \App\Core\DB::conn()->affected_rows > 0;
    }

    public function delete(string $entityKey, int $id): bool
    {
        \App\Core\DB::query('DELETE FROM daily_orders WHERE id=? LIMIT 1', [$id]);
        return \App\Core\DB::conn()->affected_rows > 0;
    }
}
