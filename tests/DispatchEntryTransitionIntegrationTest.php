<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryService.php';
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
use Plugins\DispatchEntries\DispatchEntryService;
use Plugins\DispatchEntries\DispatchEntryHooks;

final class DispatchEntryTransitionIntegrationTest extends TestCase
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
        DispatchEntryHooks::reset();
        ManufacturingGateway::reset();

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Plugins\DispatchEntries\register_dispatch_entry_entity();

        DispatchEntryService::setStore(new \App\Core\EntityStore(
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
        DispatchEntryHooks::reset();
        ManufacturingGateway::reset();
        DispatchEntryService::reset();
    }

    public function testActionToStatusMapping(): void
    {
        $this->assertEquals('Ready', DispatchEntryService::actionToStatus('submit'));
        $this->assertEquals('Ready', DispatchEntryService::actionToStatus('approve'));
        $this->assertEquals('Dispatched', DispatchEntryService::actionToStatus('finalize'));
        $this->assertEquals('Hold', DispatchEntryService::actionToStatus('hold'));
        $this->assertEquals('Cancelled', DispatchEntryService::actionToStatus('cancel'));
        $this->assertEquals('Ready', DispatchEntryService::actionToStatus('reopen'));
        $this->assertNull(DispatchEntryService::actionToStatus('reject'));
        $this->assertNull(DispatchEntryService::actionToStatus('handoff'));
        $this->assertNull(DispatchEntryService::actionToStatus('invalid_action'));
    }

    public function testActionClassification(): void
    {
        $this->assertTrue(DispatchEntryService::isEntityLifecycleAction('submit'));
        $this->assertTrue(DispatchEntryService::isEntityLifecycleAction('finalize'));
        $this->assertTrue(DispatchEntryService::isEntityLifecycleAction('hold'));
        $this->assertTrue(DispatchEntryService::isEntityLifecycleAction('reopen'));

        $this->assertTrue(DispatchEntryService::isGovernanceOnlyAction('reject'));
        $this->assertFalse(DispatchEntryService::isGovernanceOnlyAction('submit'));

        $this->assertTrue(DispatchEntryService::isOperationalAction('handoff'));
        $this->assertFalse(DispatchEntryService::isOperationalAction('submit'));
    }

    public function testRejectGovernanceAction(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        DispatchEntryService::rejectDispatch($id, $context, 'Test rejection reason', 'Test note');

        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Rejected', $row['approval_status']);
        $this->assertEquals('Test rejection reason', $row['status_reason']);
        $this->assertEquals('Test note', $row['status_note']);
    }

    public function testDispatchEntryWorkflowTransitionsAreValidated(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Draft', $row['dispatch_status']);

        DispatchEntryService::transitionStatus($id, 'Ready', $context);

        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Ready', $row['dispatch_status']);
    }

    public function testDispatchEntryInvalidWorkflowTransitionThrows(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid workflow transition from 'Draft' to 'Dispatched'");
        DispatchEntryService::transitionStatus($id, 'Dispatched', $context);
    }

    public function testDispatchEntryPolicyBlocksDispatchedRow(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        DispatchEntryService::transitionStatus($id, 'Ready', $context);
        DispatchEntryService::transitionStatus($id, 'Hold', $context);
        DispatchEntryService::transitionStatus($id, 'Ready', $context);
        DispatchEntryService::transitionStatus($id, 'Dispatched', $context);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Edit not allowed for this row");
        DispatchEntryService::transitionStatus($id, 'Ready', $context);
    }

    public function testDispatchEntryHooksFireOnStatusTransition(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        $log = DispatchEntryHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);

        DispatchEntryService::transitionStatus($id, 'Ready', $context);

        $log = DispatchEntryHooks::getAuditLog();
        $this->assertCount(4, $log);
        $this->assertEquals('before_update', $log[2]['stage']);
        $this->assertEquals('after_update', $log[3]['stage']);
    }

    public function testFullLifecycleThroughService(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        DispatchEntryService::transitionStatus($id, 'Ready', $context);
        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Ready', $row['dispatch_status']);

        DispatchEntryService::transitionStatus($id, 'Hold', $context);
        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Hold', $row['dispatch_status']);

        DispatchEntryService::transitionStatus($id, 'Ready', $context);
        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Ready', $row['dispatch_status']);
    }

    public function testCancelledCannotTransition(): void
    {
        $context = EntityContext::admin();

        $id = DispatchEntryService::create([
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 50,
        ], $context);

        DispatchEntryService::transitionStatus($id, 'Cancelled', $context);
        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Cancelled', $row['dispatch_status']);

        $this->expectException(\RuntimeException::class);
        DispatchEntryService::transitionStatus($id, 'Ready', $context);
    }

    public function testReopenActionThroughActionToStatusMapping(): void
    {
        $this->assertEquals('Ready', DispatchEntryService::actionToStatus('reopen'));
    }

    public function testRejectAndHandoffRemainLegacy(): void
    {
        $this->assertNull(DispatchEntryService::actionToStatus('reject'));
        $this->assertNull(DispatchEntryService::actionToStatus('handoff'));
    }
}
