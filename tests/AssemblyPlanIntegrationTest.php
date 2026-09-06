<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/AssemblyPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/AssemblyPlans/AssemblyPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/AssemblyPlans/AssemblyPlanHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use Apps\Manufacturing\Modules\AssemblyPlans\register_assembly_plan_entity;
use Apps\Manufacturing\Modules\AssemblyPlans\AssemblyPlanHooks;

final class AssemblyPlanIntegrationTest extends TestCase
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
        AssemblyPlanHooks::reset();

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Apps\Manufacturing\Modules\AssemblyPlans\register_assembly_plan_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        AssemblyPlanHooks::reset();
    }

    public function testAssemblyPlanDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('AssemblyPlan');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testAssemblyPlanCreateAppliesDefaults(): void
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
        $id = $store->create('AssemblyPlan', [
            'product_id' => 1,
            'demand_date' => '2026-04-10',
            'demand_type' => 'assembly',
        ], $context);

        $row = $this->gateway->read('AssemblyPlan', $id);
        $this->assertNotNull($row);
        $this->assertEquals('calculated', $row['status']);
        $this->assertEquals('assembly', $row['demand_type']);
    }

    public function testAssemblyPlanWorkflowValidTransition(): void
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
        $id = $store->create('AssemblyPlan', [
            'product_id' => 1,
            'demand_date' => '2026-04-10',
        ], $context);

        $original = $this->gateway->read('AssemblyPlan', $id);
        $store->update('AssemblyPlan', $id, ['status' => 'adjusted'], $context, $original);

        $updated = $this->gateway->read('AssemblyPlan', $id);
        $this->assertEquals('adjusted', $updated['status']);
    }

    public function testAssemblyPlanPolicyBlocksCompletedRow(): void
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
        $id = $store->create('AssemblyPlan', [
            'product_id' => 1,
            'demand_date' => '2026-04-10',
        ], $context);

        $original = $this->gateway->read('AssemblyPlan', $id);
        $store->update('AssemblyPlan', $id, ['status' => 'adjusted'], $context, $original);

        $original = $this->gateway->read('AssemblyPlan', $id);
        $store->update('AssemblyPlan', $id, ['status' => 'approved'], $context, $original);

        $original = $this->gateway->read('AssemblyPlan', $id);
        $store->update('AssemblyPlan', $id, ['status' => 'released'], $context, $original);

        $original = $this->gateway->read('AssemblyPlan', $id);
        $store->update('AssemblyPlan', $id, ['status' => 'completed'], $context, $original);

        $completed = $this->gateway->read('AssemblyPlan', $id);
        $completed['status'] = 'completed';

        $this->expectException(\RuntimeException::class);
        $store->update('AssemblyPlan', $id, ['adjustment_note' => 'Updated'], $context, $completed);
    }

    public function testAssemblyPlanHooksAreExecuted(): void
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
        $id = $store->create('AssemblyPlan', [
            'product_id' => 1,
            'demand_date' => '2026-04-10',
        ], $context);

        $log = AssemblyPlanHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testAssemblyPlanInvalidWorkflowTransition(): void
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
        $id = $store->create('AssemblyPlan', [
            'product_id' => 1,
            'demand_date' => '2026-04-10',
        ], $context);

        $original = $this->gateway->read('AssemblyPlan', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('AssemblyPlan', $id, ['status' => 'completed'], $context, $original);
    }
}
