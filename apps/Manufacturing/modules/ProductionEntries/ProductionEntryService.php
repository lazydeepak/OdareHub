<?php
declare(strict_types=1);

namespace Plugins\ProductionEntries;

use App\Core\EntityStore;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;

class ProductionEntryService
{
    private static ?object $store = null;

    public static function getStore(): object
    {
        if (self::$store === null) {
            self::$store = new EntityStore(
                new EntityRegistry(),
                new DBProductionEntryGateway(),
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
        register_production_entry_entity();
        return self::getStore()->create('ProductionEntry', $data, $context);
    }

    public static function update(int $id, array $data, EntityContext $context, ?array $original = null): bool
    {
        register_production_entry_entity();
        return self::getStore()->update('ProductionEntry', $id, $data, $context, $original);
    }

    public static function delete(int $id, EntityContext $context): bool
    {
        register_production_entry_entity();
        return self::getStore()->delete('ProductionEntry', $id, $context);
    }

    public static function find(int $id, EntityContext $context): ?array
    {
        register_production_entry_entity();
        return self::getStore()->find('ProductionEntry', $id, $context);
    }
}

class DBProductionEntryGateway
{
    public function create(string $entityKey, array $row): int
    {
        $columns = implode(', ', array_keys($row));
        $placeholders = implode(', ', array_fill(0, count($row), '?'));
        $values = array_values($row);
        
        \App\Core\DB::query(
            "INSERT INTO production_entries ({$columns}) VALUES ({$placeholders})",
            $values
        );
        
        return (int)\App\Core\DB::conn()->insert_id;
    }

    public function read(string $entityKey, int $id): ?array
    {
        return \App\Core\DB::fetchOne('SELECT * FROM production_entries WHERE id=? LIMIT 1', [$id]);
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
        
        $sql = 'UPDATE production_entries SET ' . implode(', ', $sets) . ' WHERE id=?';
        \App\Core\DB::query($sql, $values);
        
        return \App\Core\DB::conn()->affected_rows > 0;
    }

    public function delete(string $entityKey, int $id): bool
    {
        \App\Core\DB::query('DELETE FROM production_entries WHERE id=? LIMIT 1', [$id]);
        return \App\Core\DB::conn()->affected_rows > 0;
    }
}
