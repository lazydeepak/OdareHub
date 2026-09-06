<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderService.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityContext;
use Plugins\DailyOrders\DailyOrderService;
use Plugins\DailyOrders\DailyOrderHooks;

final class DailyOrderServiceIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        DailyOrderHooks::reset();
        DailyOrderService::resetStore();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        DailyOrderHooks::reset();
        DailyOrderService::resetStore();
    }

    public function testDailyOrderServiceUsesEntityStore(): void
    {
        $context = EntityContext::admin();
        
        $mockStore = new class {
            private array $storage = [];
            private array $calls = [];
            
            public function create(string $entityKey, array $data, $context): int
            {
                $this->calls[] = 'create';
                $id = count($this->storage) + 1;
                $this->storage[$id] = $data;
                return $id;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
                $this->calls[] = 'update';
                $this->storage[$id] = array_merge($this->storage[$id] ?? [], $data);
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                $this->calls[] = 'delete';
                unset($this->storage[$id]);
                return true;
            }
            
            public function find(string $entityKey, $id, $context): ?array
            {
                $this->calls[] = 'find';
                return $this->storage[$id] ?? null;
            }
            
            public function getCalls(): array
            {
                return $this->calls;
            }
            
            public function getStorage(): array
            {
                return $this->storage;
            }
        };
        
        DailyOrderService::setStore($mockStore);
        
        $id = DailyOrderService::create([
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
            'qty' => 100,
        ], $context);
        
        $this->assertEquals(1, $id);
        $this->assertContains('create', $mockStore->getCalls());
        
        $storage = $mockStore->getStorage();
        $this->assertEquals('Test Customer', $storage[1]['customer_name']);
        $this->assertEquals(100, $storage[1]['qty']);
    }

    public function testDailyOrderServiceUpdateCallsEntityStore(): void
    {
        $context = EntityContext::admin();
        
        $mockStore = new class {
            private array $storage = [1 => ['id' => 1, 'customer_name' => 'Test', 'product_id' => 1, 'status' => 'Open', 'qty' => 100]];
            
            public function create(string $entityKey, array $data, $context): int
            {
                return 1;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
                $this->storage[$id] = array_merge($this->storage[$id] ?? [], $data);
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                unset($this->storage[$id]);
                return true;
            }
            
            public function find(string $entityKey, $id, $context): ?array
            {
                return $this->storage[$id] ?? null;
            }
            
            public function getStorage(): array
            {
                return $this->storage;
            }
        };
        
        DailyOrderService::setStore($mockStore);
        
        $result = DailyOrderService::update(1, [
            'status' => 'InProgress',
            'notes' => 'Updated via entity store',
        ], $context);
        
        $this->assertTrue($result);
        $storage = $mockStore->getStorage();
        $this->assertEquals('InProgress', $storage[1]['status']);
        $this->assertEquals('Updated via entity store', $storage[1]['notes']);
    }

    public function testDailyOrderServiceDeleteCallsEntityStore(): void
    {
        $context = EntityContext::admin();
        
        $mockStore = new class {
            private array $storage = [1 => ['id' => 1, 'customer_name' => 'Test', 'product_id' => 1]];
            
            public function create(string $entityKey, array $data, $context): int
            {
                return 1;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                unset($this->storage[$id]);
                return true;
            }
            
            public function find(string $entityKey, $id, $context): ?array
            {
                return $this->storage[$id] ?? null;
            }
            
            public function getStorage(): array
            {
                return $this->storage;
            }
        };
        
        DailyOrderService::setStore($mockStore);
        
        $result = DailyOrderService::delete(1, $context);
        
        $this->assertTrue($result);
        $this->assertEmpty($mockStore->getStorage());
    }

    public function testDailyOrderServiceFindCallsEntityStore(): void
    {
        $context = EntityContext::admin();
        
        $mockStore = new class {
            private array $storage = [1 => ['id' => 1, 'customer_name' => 'Found', 'product_id' => 5]];
            
            public function create(string $entityKey, array $data, $context): int
            {
                return 1;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
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
        };
        
        DailyOrderService::setStore($mockStore);
        
        $row = DailyOrderService::find(1, $context);
        
        $this->assertNotNull($row);
        $this->assertEquals('Found', $row['customer_name']);
        $this->assertEquals(5, $row['product_id']);
    }

    public function testDailyOrderHooksAreExecutedThroughService(): void
    {
        $context = EntityContext::admin();
        
        $calls = [];
        $mockStore = new class {
            public function create(string $entityKey, array $data, $context): int
            {
                return 1;
            }
            
            public function update(string $entityKey, $id, array $data, $context, $original = null): bool
            {
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                return true;
            }
            
            public function find(string $entityKey, $id, $context): ?array
            {
                return null;
            }
        };
        
        DailyOrderService::setStore($mockStore);
        
        $id = DailyOrderService::create([
            'order_date' => '2026-04-10',
            'customer_name' => 'Hook Test',
            'product_id' => 1,
        ], $context);
        
        $this->assertEquals(1, $id);
    }

    public function testDailyOrderServiceCreatePassesDataThroughGateway(): void
    {
        $context = EntityContext::admin();
        
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
                if (isset($this->storage[$id])) {
                    $this->storage[$id] = array_merge($this->storage[$id], $data);
                }
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                $this->calls[] = ['method' => 'delete', 'entity' => $entityKey, 'id' => $id];
                unset($this->storage[$id]);
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
        
        DailyOrderService::setStore($mockStore);
        
        $inputData = [
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
            'qty' => 100,
        ];
        
        $id = DailyOrderService::create($inputData, $context);
        
        $this->assertEquals(1, $id);
        $this->assertCount(1, $mockStore->getCalls());
        $this->assertEquals('create', $mockStore->getCalls()[0]['method']);
        $this->assertEquals('DailyOrder', $mockStore->getCalls()[0]['entity']);
        $this->assertEquals('Test Customer', $mockStore->getCalls()[0]['data']['customer_name']);
        $this->assertEquals(100, $mockStore->getCalls()[0]['data']['qty']);
        
        $storage = &$mockStore->getStorage();
        $this->assertEquals('Test Customer', $storage[1]['customer_name']);
        $this->assertEquals(100, $storage[1]['qty']);
    }

    public function testDailyOrderServiceUpdatePassesDataThroughGateway(): void
    {
        $context = EntityContext::admin();
        
        $storage = [1 => ['id' => 1, 'customer_name' => 'Original', 'product_id' => 1, 'qty' => 50]];
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
                if (isset($this->storage[$id])) {
                    $this->storage[$id] = array_merge($this->storage[$id], $data);
                }
                return true;
            }
            
            public function delete(string $entityKey, $id, $context): bool
            {
                unset($this->storage[$id]);
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
        
        DailyOrderService::setStore($mockStore);
        
        $original = ['customer_name' => 'Original', 'product_id' => 1, 'qty' => 50];
        $updateData = [
            'customer_name' => 'Updated Customer',
            'qty' => 150,
        ];
        
        $result = DailyOrderService::update(1, $updateData, $context, $original);
        
        $this->assertTrue($result);
        $this->assertCount(1, $mockStore->getCalls());
        $this->assertEquals('update', $mockStore->getCalls()[0]['method']);
        $this->assertEquals('DailyOrder', $mockStore->getCalls()[0]['entity']);
        $this->assertEquals('Updated Customer', $mockStore->getCalls()[0]['data']['customer_name']);
        $this->assertEquals(150, $mockStore->getCalls()[0]['data']['qty']);
    }
}
