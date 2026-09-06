<?php
declare(strict_types=1);

namespace App\Core;

class ManufacturingEntityService
{
    protected static ?EntityStore $store = null;
    protected static ?EntityHookRunner $hookRunner = null;
    protected static ?EntityPolicyResolver $policyResolver = null;
    protected static ?EntitySlaEngine $slaEngine = null;
    protected static ?EntityWorkflowGuard $workflowGuard = null;
    protected static ?EntityDefinitionValidator $validator = null;

    protected static function ensureComponents(): void
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
    }

    protected static function getStore(): EntityStore
    {
        if (self::$store === null) {
            self::ensureComponents();
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
        return self::$store;
    }

    public static function reset(): void
    {
        self::$store = null;
    }

    public static function create(string $entityKey, callable $registerEntity, array $data, EntityContext $context): int
    {
        $registerEntity();
        return self::getStore()->create($entityKey, $data, $context);
    }

    public static function update(string $entityKey, callable $registerEntity, int $id, array $data, EntityContext $context, ?array $original = null): bool
    {
        $registerEntity();
        return self::getStore()->update($entityKey, $id, $data, $context, $original);
    }

    public static function delete(string $entityKey, callable $registerEntity, int $id, EntityContext $context): bool
    {
        $registerEntity();
        return self::getStore()->delete($entityKey, $id, $context);
    }

    public static function find(string $entityKey, callable $registerEntity, int $id): ?array
    {
        $registerEntity();
        return self::getStore()->find($entityKey, $id);
    }

    public static function canEdit(string $entityKey, callable $registerEntity, array $row, EntityContext $context): bool
    {
        $registerEntity();
        return self::getStore()->canEdit($entityKey, $row, $context);
    }

    public static function canView(string $entityKey, callable $registerEntity, array $row, EntityContext $context): bool
    {
        $registerEntity();
        return self::getStore()->canView($entityKey, $row, $context);
    }
}
