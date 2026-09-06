<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/EntityDefinition.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use Plugins\DailyOrders\register_daily_orders_entity;
use Plugins\DailyOrders\DailyOrderHooks;

final class DailyOrderIntegrationTest extends TestCase
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
        DailyOrderHooks::reset();

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Plugins\DailyOrders\register_daily_orders_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        DailyOrderHooks::reset();
    }

    public function testDailyOrderDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('DailyOrder');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testDailyOrderCreateAppliesDefaults(): void
    {
        $store = new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );

        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', [
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
        ], $context);

        $row = $this->gateway->read('DailyOrder', $id);
        $this->assertNotNull($row);
        $this->assertEquals('2026-04-10', $row['order_date']);
        $this->assertEquals('Test Customer', $row['customer_name']);
        $this->assertEquals(1, $row['product_id']);
        $this->assertEquals(0, $row['qty']);
        $this->assertEquals('Open', $row['status']);
        $this->assertEquals('Low', $row['coverage_status']);
    }

    public function testDailyOrderWorkflowValidTransition(): void
    {
        $store = new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );

        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', [
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
        ], $context);

        $original = $this->gateway->read('DailyOrder', $id);
        $store->update('DailyOrder', $id, ['status' => 'InProgress'], $context, $original);

        $updated = $this->gateway->read('DailyOrder', $id);
        $this->assertEquals('InProgress', $updated['status']);
    }

    public function testDailyOrderPolicyBlocksCompletedRow(): void
    {
        $store = new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );

        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', [
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
        ], $context);

        $original = $this->gateway->read('DailyOrder', $id);
        $store->update('DailyOrder', $id, ['status' => 'InProgress'], $context, $original);

        $original = $this->gateway->read('DailyOrder', $id);
        $store->update('DailyOrder', $id, ['status' => 'Fulfilled'], $context, $original);

        $fulfilled = $this->gateway->read('DailyOrder', $id);
        $fulfilled['status'] = 'Fulfilled';

        $this->expectException(\RuntimeException::class);
        $store->update('DailyOrder', $id, ['notes' => 'Updated'], $context, $fulfilled);
    }

    public function testDailyOrderHooksAreExecuted(): void
    {
        $store = new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );

        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', [
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
        ], $context);

        $log = DailyOrderHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testDailyOrderInvalidWorkflowTransition(): void
    {
        $store = new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );

        $context = EntityContext::admin();
        $id = $store->create('DailyOrder', [
            'order_date' => '2026-04-10',
            'customer_name' => 'Test Customer',
            'product_id' => 1,
        ], $context);

        $original = $this->gateway->read('DailyOrder', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('DailyOrder', $id, ['status' => 'Fulfilled'], $context, $original);
    }
}
