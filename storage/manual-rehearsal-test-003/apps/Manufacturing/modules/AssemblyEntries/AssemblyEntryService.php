<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Modules\AssemblyEntries;

use App\Core\EntityContext;
use App\Core\EntityRegistry;
use App\Core\EntityStore;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;

class AssemblyEntryService
{
    private static ?EntityStore $store = null;
    private static ?EntityHookRunner $hookRunner = null;
    private static ?EntityPolicyResolver $policyResolver = null;
    private static ?EntitySlaEngine $slaEngine = null;
    private static ?EntityWorkflowGuard $workflowGuard = null;
    private static ?EntityDefinitionValidator $validator = null;

    public static function setStore(object $store): void
    {
        self::$store = $store instanceof EntityStore ? $store : null;
    }

    public static function getStore(): EntityStore
    {
        if (self::$store === null) {
            self::ensureStore();
        }
        return self::$store;
    }

    private static function ensureStore(): void
    {
        if (self::$hookRunner === null) {
            self::$hookRunner = new EntityHookRunner();
        }
        if (self::$policyResolver === null) {
            self::$policyResolver = new EntityPolicyResolver();
        }
        if (self::$slaEngine === null) {
            self::$slaEngine = new EntitySlaEngine();
        }
        if (self::$workflowGuard === null) {
            self::$workflowGuard = new EntityWorkflowGuard();
        }
        if (self::$validator === null) {
            self::$validator = new EntityDefinitionValidator();
        }

        self::$store = new EntityStore(
            new EntityRegistry(),
            null,
            self::$hookRunner,
            self::$policyResolver,
            self::$slaEngine,
            self::$workflowGuard,
            self::$validator
        );
    }

    public static function reset(): void
    {
        self::$store = null;
        self::$hookRunner = null;
        self::$policyResolver = null;
        self::$slaEngine = null;
        self::$workflowGuard = null;
        self::$validator = null;
    }

    public static function create(array $data, EntityContext $context): int
    {
        return self::getStore()->create('AssemblyEntry', $data, $context);
    }

    public static function update(int $id, array $data, EntityContext $context, ?array $original = null): bool
    {
        return self::getStore()->update('AssemblyEntry', $id, $data, $context, $original);
    }

    public static function delete(int $id, EntityContext $context): bool
    {
        return self::getStore()->delete('AssemblyEntry', $id, $context);
    }

    public static function find(int $id): ?array
    {
        return self::getStore()->find('AssemblyEntry', $id);
    }

    public static function canEdit(array $row, EntityContext $context): bool
    {
        return self::getStore()->canEdit('AssemblyEntry', $row, $context);
    }

    public static function canView(array $row, EntityContext $context): bool
    {
        return self::getStore()->canView('AssemblyEntry', $row, $context);
    }
}
