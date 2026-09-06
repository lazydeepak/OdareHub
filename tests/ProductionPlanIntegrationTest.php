<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use Plugins\ProductionPlans\register_production_plan_entity;
use Plugins\ProductionPlans\ProductionPlanHooks;

final class ProductionPlanIntegrationTest extends TestCase
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
        ProductionPlanHooks::reset();

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Plugins\ProductionPlans\register_production_plan_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        ProductionPlanHooks::reset();
    }

    public function testProductionPlanDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('ProductionPlan');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testProductionPlanCreateAppliesDefaults(): void
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
        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertNotNull($row);
        $this->assertEquals('2026-04-10', $row['plan_date']);
        $this->assertEquals(1, $row['machine_id']);
        $this->assertEquals(1, $row['product_id']);
        $this->assertEquals(100, $row['planned_qty']);
        $this->assertEquals('Planned', $row['status']);
        $this->assertEquals(1, $row['sequence_no']);
    }

    public function testProductionPlanWorkflowValidTransition(): void
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
        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'InProgress'], $context, $original);

        $updated = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('InProgress', $updated['status']);
    }

    public function testProductionPlanPolicyBlocksCompletedRow(): void
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
        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'InProgress'], $context, $original);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'Completed'], $context, $original);

        $completed = $this->gateway->read('ProductionPlan', $id);
        $completed['status'] = 'Completed';

        $this->expectException(\RuntimeException::class);
        $store->update('ProductionPlan', $id, ['notes' => 'Updated'], $context, $completed);
    }

    public function testProductionPlanHooksAreExecuted(): void
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
        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $log = ProductionPlanHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testProductionPlanInvalidWorkflowTransition(): void
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
        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $original = $this->gateway->read('ProductionPlan', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('ProductionPlan', $id, ['status' => 'Completed'], $context, $original);
    }
}
