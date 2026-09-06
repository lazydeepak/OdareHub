<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanService.php';
require_once __DIR__ . '/../app/Core/ManufacturingGateway.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityContext;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\ManufacturingGateway;
use Plugins\ProductionPlans\ProductionPlanService;
use Plugins\ProductionPlans\ProductionPlanHooks;

final class ProductionPlanApprovalActionIntegrationTest extends TestCase
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

        ProductionPlanService::setStore(new \App\Core\EntityStore(
            new EntityRegistry(),
            $this->gateway,
            $this->hookRunner,
            $this->policyResolver,
            $this->slaEngine,
            $this->workflowGuard,
            $this->validator
        ));
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        ProductionPlanHooks::reset();
        ManufacturingGateway::reset();
        ProductionPlanService::reset();
    }

    public function testActionToStatusMapping(): void
    {
        $this->assertEquals('Planned', ProductionPlanService::actionToStatus('submit'));
        $this->assertEquals('InProgress', ProductionPlanService::actionToStatus('approve'));
        $this->assertEquals('Completed', ProductionPlanService::actionToStatus('finalize'));
        $this->assertEquals('Cancelled', ProductionPlanService::actionToStatus('cancel'));
        $this->assertNull(ProductionPlanService::actionToStatus('invalid_action'));
    }

    public function testQuickStatusUpdateThroughService(): void
    {
        $context = EntityContext::admin();

        $id = ProductionPlanService::create([
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Draft', $row['status']);

        ProductionPlanService::quickStatusUpdate($id, 'Planned', $context);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Planned', $row['status']);
    }

    public function testServiceWorkflowValidationEnforced(): void
    {
        $context = EntityContext::admin();

        $id = ProductionPlanService::create([
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
        ], $context);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid workflow transition from 'Planned' to 'Completed'");
        ProductionPlanService::quickStatusUpdate($id, 'Completed', $context);
    }

    public function testServiceHooksFireOnTransition(): void
    {
        $context = EntityContext::admin();

        $id = ProductionPlanService::create([
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        $log = ProductionPlanHooks::getAuditLog();
        $this->assertCount(2, $log);

        ProductionPlanService::quickStatusUpdate($id, 'Planned', $context);

        $log = ProductionPlanHooks::getAuditLog();
        $this->assertCount(4, $log);
        $this->assertEquals('before_update', $log[2]['stage']);
        $this->assertEquals('after_update', $log[3]['stage']);
    }

    public function testFullLifecycleThroughService(): void
    {
        $context = EntityContext::admin();

        $id = ProductionPlanService::create([
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        ProductionPlanService::quickStatusUpdate($id, 'Planned', $context);
        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Planned', $row['status']);

        ProductionPlanService::quickStatusUpdate($id, 'InProgress', $context);
        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('InProgress', $row['status']);

        ProductionPlanService::quickStatusUpdate($id, 'Completed', $context);
        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Completed', $row['status']);
    }

    public function testControllerActionFlow(): void
    {
        $user = ['id' => 1, 'authority_role' => 'admin', 'acl_roles' => ['admin']];
        $context = EntityContext::fromUser($user);

        $id = ProductionPlanService::create([
            'plan_date' => '2026-04-10',
            'machine_id' => 1,
            'product_id' => 1,
            'planned_qty' => 100,
            'status' => 'Draft',
        ], $context);

        $submitStatus = ProductionPlanService::actionToStatus('submit');
        $this->assertNotNull($submitStatus);
        $this->assertEquals('Planned', $submitStatus);

        $result = ProductionPlanService::quickStatusUpdate($id, $submitStatus, $context);
        $this->assertTrue($result);

        $row = $this->gateway->read('ProductionPlan', $id);
        $this->assertEquals('Planned', $row['status']);
    }
}
