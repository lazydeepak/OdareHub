<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';
require_once __DIR__ . '/../app/Core/ManufacturingGateway.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use App\Core\ManufacturingGateway;
use Plugins\ProductionPlans\ProductionPlanHooks;

final class ProductionPlanTransitionIntegrationTest extends TestCase
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
        ManufacturingGateway::reset();

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
        ManufacturingGateway::reset();
    }

    private function createStore(): \App\Core\EntityStore
    {
        return new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        );
    }

    public function testProductionPlanWorkflowTransitionsAreValidated(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Draft', $row['status']);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'Planned'], $context, $original);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Planned', $row['status']);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'InProgress'], $context, $original);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('InProgress', $row['status']);
    }

    public function testProductionPlanInvalidWorkflowTransitionThrows(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $original = $this->gateway->read('ProductionPlan', $id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid workflow transition from 'Planned' to 'Completed'");
        $store->update('ProductionPlan', $id, ['status' => 'Completed'], $context, $original);
    }

    public function testProductionPlanHooksFireOnStatusTransition(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        $log = ProductionPlanHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'Planned'], $context, $original);

        $log = ProductionPlanHooks::getAuditLog();
        $this->assertCount(4, $log);
        $this->assertEquals('before_update', $log[2]['stage']);
        $this->assertEquals('after_update', $log[3]['stage']);
    }

    public function testProductionPlanFullLifecycleWorkflow(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('ProductionPlan', [
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'Planned'], $context, $original);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'InProgress'], $context, $original);

        $original = $this->gateway->read('ProductionPlan', $id);
        $store->update('ProductionPlan', $id, ['status' => 'Completed'], $context, $original);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Completed', $row['status']);
    }
}
