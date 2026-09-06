<?php

declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/Stubs.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;

final class EntityStoreTest extends TestCase
{
    private EntityRegistry $registry;
    private EntityHookRunner $hookRunner;
    private EntityPolicyResolver $policyResolver;
    private EntitySlaEngine $slaEngine;
    private EntityWorkflowGuard $workflowGuard;
    private EntityDefinitionValidator $validator;

    protected function setUp(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        TestHooks::reset();
        $this->registry = new EntityRegistry();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        TestHooks::reset();
    }

    private function registerDailyOrder(): void
    {
        EntityRegistry::register('DailyOrder', [
            'fields' => [
                'id' => ['type' => 'int', 'primary' => true],
                'order_date' => ['type' => 'date', 'required' => true, 'default' => 'today'],
                'customer_id' => ['type' => 'int', 'required' => true],
                'status' => ['type' => 'string', 'default' => 'draft'],
                'hidden_field' => ['type' => 'string', 'hidden' => true],
                'readonly_field' => ['type' => 'string', 'readonly' => true],
                'total_amount' => ['type' => 'decimal'],
            ],
            'workflow' => [
                'states' => ['draft', 'confirmed', 'shipped', 'completed', 'cancelled'],
                'transitions' => [
                    'draft' => ['confirmed', 'cancelled'],
                    'confirmed' => ['shipped', 'cancelled'],
                    'shipped' => ['completed'],
                    'completed' => [],
                    'cancelled' => [],
                ],
                'field' => 'status',
            ],
            'hooks' => [
                'before_create' => [[TestHooks::class, 'beforeCreateDefaults']],
                'after_create' => [[TestHooks::class, 'afterCreateSetTotal']],
                'before_update' => [[TestHooks::class, 'beforeUpdateMutate']],
                'after_update' => [[TestHooks::class, 'afterUpdateLog']],
                'before_delete' => [[TestHooks::class, 'beforeDeleteCheck']],
            ],
            'permissions' => [
                'rules' => [
                    'can_edit_row' => [[TestPolicy::class, 'canEditRow']],
                ],
            ],
        ]);
    }

    public function testCreateAppliesDefaultsAndRunsHooksInOrder(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);

        $row = $gateway->read('DailyOrder', $id);
        $this->assertNotNull($row);
        $this->assertEquals('today', $row['order_date']);
        $this->assertEquals('draft', $row['status']);
        $this->assertEquals(123, $row['customer_id']);
        $this->assertEquals(100.0, $row['total_amount']);
    }

    public function testUpdateRunsHooksInOrder(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        $original = $gateway->read('DailyOrder', $id);

        $store->update('DailyOrder', $id, ['status' => 'confirmed'], $context, $original);

        $updated = $gateway->read('DailyOrder', $id);
        $this->assertEquals('confirmed-mutated', $updated['status']);
    }

    public function testReadonlyFieldIsRejected(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        $original = $gateway->read('DailyOrder', $id);

        $this->expectException(\InvalidArgumentException::class);
        $store->update('DailyOrder', $id, ['readonly_field' => 'hacked'], $context, $original);
    }

    public function testHiddenFieldIsRejected(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();

        $this->expectException(\InvalidArgumentException::class);
        $store->create('DailyOrder', ['customer_id' => 123, 'hidden_field' => 'secret'], $context);
    }

    public function testRequiredFieldEnforcementConsistentWithDefaults(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();

        $id = $store->create('DailyOrder', ['customer_id' => 999], $context);
        $row = $gateway->read('DailyOrder', $id);
        $this->assertEquals('today', $row['order_date']);
    }

    public function testValidWorkflowTransitionPasses(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        $original = $gateway->read('DailyOrder', $id);

        $store->update('DailyOrder', $id, ['status' => 'confirmed'], $context, $original);

        $updated = $gateway->read('DailyOrder', $id);
        $this->assertEquals('confirmed-mutated', $updated['status']);
    }

    public function testInvalidWorkflowTransitionThrowsUsingRealInvalidChange(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        $original = $gateway->read('DailyOrder', $id);

        $store->update('DailyOrder', $id, ['status' => 'confirmed'], $context, $original);

        $this->expectException(\RuntimeException::class);
        $store->update('DailyOrder', $id, ['status' => 'completed'], $context, $original);
    }

    public function testCanEditRowFalseBlocksUpdate(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        
        $completedRow = $gateway->read('DailyOrder', $id);
        $completedRow['status'] = 'completed';
        $gateway->update('DailyOrder', $id, ['status' => 'completed']);

        $original = $gateway->read('DailyOrder', $id);
        $this->expectException(\RuntimeException::class);
        $store->update('DailyOrder', $id, ['total_amount' => 999], $context, $original);
    }

    public function testUnknownEntityThrows(): void
    {
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();

        $this->expectException(\InvalidArgumentException::class);
        $store->create('NonExistentEntity', [], $context);
    }

    public function testHookMutationsArePersistedThroughGateway(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);

        $row = $gateway->read('DailyOrder', $id);
        $this->assertEquals(100.0, $row['total_amount']);
    }

    public function testUpdateHooksReceiveOriginalRow(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        $original = $gateway->read('DailyOrder', $id);

        TestHooks::reset();
        $store->update('DailyOrder', $id, ['status' => 'confirmed'], $context, $original);

        $receivedOriginal = TestHooks::getLastOriginalRow();
        $this->assertNotNull($receivedOriginal);
        $this->assertEquals('draft', $receivedOriginal['status']);
    }

    public function testDeleteRunsHooksAndRemovesRow(): void
    {
        $this->registerDailyOrder();
        $gateway = new GatewayStub();
        $store = new \App\Core\EntityStore(
            $this->registry,
            $gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', ['customer_id' => 123], $context);
        $this->assertNotNull($gateway->read('DailyOrder', $id));

        TestHooks::reset();
        $store->delete('DailyOrder', $id, $context);

        $this->assertNull($gateway->read('DailyOrder', $id));
        $this->assertTrue(TestHooks::wasBeforeDeleteCalled());
    }
}
