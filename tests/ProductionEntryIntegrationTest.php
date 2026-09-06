<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryService.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityStore;
use App\Core\EntityContext;
use Plugins\ProductionEntries\ProductionEntryHooks;
use Plugins\ProductionEntries\ProductionEntryService;

final class ProductionEntryIntegrationTest extends TestCase
{
    private GatewayStub $gateway;
    private EntityHookRunner $hookRunner;
    private EntityPolicyResolver $policyResolver;
    private EntitySlaEngine $slaEngine;
    private EntityWorkflowGuard $workflowGuard;
    private EntityDefinitionValidator $validator;

    protected function setUp(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        ProductionEntryHooks::reset();
        
        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();
        
        \Plugins\ProductionEntries\register_production_entry_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        ProductionEntryHooks::reset();
    }

    private function createStore(): EntityStore
    {
        return new EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
    }

    public function testProductionEntryDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('ProductionEntry');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testProductionEntryCreateAppliesDefaultsAndHooks(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();
        
        $id = $store->create('ProductionEntry', [
            'production_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 5,
            'produced_qty' => 100,
            'rejected_qty' => 5,
        ], $context);

        $row = $this->gateway->read('ProductionEntry', $id);
        $this->assertNotNull($row);
        $this->assertEquals('2026-04-10', $row['production_date']);
        $this->assertEquals('Day', $row['shift']);
        $this->assertEquals('Draft', $row['status']);
        $this->assertEquals(95, $row['good_qty']);
    }

    public function testProductionEntryWorkflowValidTransition(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();
        
        $id = $store->create('ProductionEntry', [
            'production_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 5,
            'produced_qty' => 100,
            'rejected_qty' => 5,
        ], $context);

        $original = $this->gateway->read('ProductionEntry', $id);
        $store->update('ProductionEntry', $id, ['status' => 'Open'], $context, $original);

        $updated = $this->gateway->read('ProductionEntry', $id);
        $this->assertEquals('Open', $updated['status']);
    }

    public function testProductionEntryPolicyBlocksPostedRow(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();
        
        $id = $store->create('ProductionEntry', [
            'production_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 5,
            'produced_qty' => 100,
            'rejected_qty' => 5,
        ], $context);

        $original = $this->gateway->read('ProductionEntry', $id);
        $store->update('ProductionEntry', $id, ['status' => 'Open'], $context, $original);
        
        $original = $this->gateway->read('ProductionEntry', $id);
        $store->update('ProductionEntry', $id, ['status' => 'Posted'], $context, $original);

        $posted = $this->gateway->read('ProductionEntry', $id);
        $posted['status'] = 'Posted';

        $this->expectException(\RuntimeException::class);
        $store->update('ProductionEntry', $id, ['notes' => 'Attempt to edit'], $context, $posted);
    }

    public function testProductionEntryInvalidWorkflowTransition(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();
        
        $id = $store->create('ProductionEntry', [
            'production_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 5,
            'produced_qty' => 100,
            'rejected_qty' => 5,
        ], $context);

        $original = $this->gateway->read('ProductionEntry', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('ProductionEntry', $id, ['status' => 'Posted'], $context, $original);
    }

    public function testProductionEntryHooksAreExecuted(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();
        
        ProductionEntryHooks::reset();
        
        $store->create('ProductionEntry', [
            'production_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 5,
            'produced_qty' => 100,
            'rejected_qty' => 5,
        ], $context);

        $log = ProductionEntryHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testProductionEntryServiceCreatePassesThroughEntityStore(): void
    {
        $storage = [];
        $calls = [];
        $mockStore = new class($storage, $calls) {
            private array $storage;
            private array $calls;
            
            public function __construct(array &$storage, array &$calls)
            {
                $this->storage = &$storage;
                $this->calls = &$calls;
            }
            
            public function create(string $entityKey, array $data, $context): int
            {
                $this->calls[] = ['method' => 'create', 'entity' => $entityKey, 'data' => $data];
                $id = count($this->storage) + 1;
                $this->storage[$id] = array_merge(['id' => $id], $data);
                return $id;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
                $this->calls[] = ['method' => 'update', 'entity' => $entityKey, 'id' => $id, 'data' => $data];
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                $this->calls[] = ['method' => 'delete', 'entity' => $entityKey, 'id' => $id];
                return true;
            }
            
            public function find(string $entityKey, $id, $context): ?array
            {
                return $this->storage[$id] ?? null;
            }
            
            public function &getStorage(): array
            {
                return $this->storage;
            }
            
            public function &getCalls(): array
            {
                return $this->calls;
            }
        };
        
        ProductionEntryService::setStore($mockStore);
        ProductionEntryService::resetStore();
        ProductionEntryService::setStore($mockStore);
        
        $context = EntityContext::admin();
        
        $id = ProductionEntryService::create([
            'production_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 5,
            'produced_qty' => 100,
            'rejected_qty' => 5,
        ], $context);
        
        $this->assertEquals(1, $id);
        $this->assertCount(1, $mockStore->getCalls());
        $this->assertEquals('create', $mockStore->getCalls()[0]['method']);
        $this->assertEquals('ProductionEntry', $mockStore->getCalls()[0]['entity']);
        
        $storage = &$mockStore->getStorage();
        $this->assertEquals('2026-04-10', $storage[1]['production_date']);
        $this->assertEquals(100, $storage[1]['produced_qty']);
        
        ProductionEntryService::resetStore();
    }

    public function testProductionEntryServiceUpdatePassesThroughEntityStore(): void
    {
        $storage = [1 => ['id' => 1, 'production_date' => '2026-04-10', 'machine_id' => 1, 'product_id' => 5, 'produced_qty' => 100, 'rejected_qty' => 5, 'good_qty' => 95]];
        $calls = [];
        $mockStore = new class($storage, $calls) {
            private array $storage;
            private array $calls;
            
            public function __construct(array &$storage, array &$calls)
            {
                $this->storage = &$storage;
                $this->calls = &$calls;
            }
            
            public function create(string $entityKey, array $data, $context): int
            {
                $id = count($this->storage) + 1;
                $this->storage[$id] = array_merge(['id' => $id], $data);
                return $id;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
                $this->calls[] = ['method' => 'update', 'entity' => $entityKey, 'id' => $id, 'data' => $data];
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                return true;
            }
            
            public function find(string $entityKey, $id, $context): ?array
            {
                return $this->storage[$id] ?? null;
            }
            
            public function &getCalls(): array
            {
                return $this->calls;
            }
        };
        
        ProductionEntryService::setStore($mockStore);
        ProductionEntryService::resetStore();
        ProductionEntryService::setStore($mockStore);
        
        $context = EntityContext::admin();
        $original = ['production_date' => '2026-04-10', 'machine_id' => 1, 'product_id' => 5, 'produced_qty' => 100, 'rejected_qty' => 5];
        
        $result = ProductionEntryService::update(1, [
            'status' => 'Open',
            'notes' => 'Updated via service',
        ], $context, $original);
        
        $this->assertTrue($result);
        $this->assertCount(1, $mockStore->getCalls());
        $this->assertEquals('update', $mockStore->getCalls()[0]['method']);
        $this->assertEquals('ProductionEntry', $mockStore->getCalls()[0]['entity']);
        $this->assertEquals('Open', $mockStore->getCalls()[0]['data']['status']);
        
        ProductionEntryService::resetStore();
    }
}
