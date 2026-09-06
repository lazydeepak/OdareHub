<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryHooks.php';
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
use Plugins\QCEntries\QCEntryHooks;

final class QCEntryTransitionIntegrationTest extends TestCase
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
        QCEntryHooks::reset();
        ManufacturingGateway::reset();

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Plugins\QCEntries\register_qc_entry_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        QCEntryHooks::reset();
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

    public function testQCEntryWorkflowTransitionsAreValidated(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
            'pass_qty' => 90,
            'fail_qty' => 10,
        ], $context);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Draft', $row['status']);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Open'], $context, $original);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Open', $row['status']);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Submitted'], $context, $original);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Submitted', $row['status']);
    }

    public function testQCEntryInvalidWorkflowTransitionThrows(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $original = $this->gateway->read('QCEntry', $id);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid workflow transition from 'Draft' to 'Approved'");
        $store->update('QCEntry', $id, ['status' => 'Approved'], $context, $original);
    }

    public function testQCEntryPolicyBlocksApprovedRow(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Open'], $context, $original);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Submitted'], $context, $original);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Approved'], $context, $original);

        $approved = $this->gateway->read('QCEntry', $id);
        $approved['status'] = 'Approved';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Edit not allowed for this row");
        $store->update('QCEntry', $id, ['remarks' => 'Updated after approval'], $context, $approved);
    }

    public function testQCEntryHooksFireOnStatusTransition(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $log = QCEntryHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Open'], $context, $original);

        $log = QCEntryHooks::getAuditLog();
        $this->assertCount(4, $log);
        $this->assertEquals('before_update', $log[2]['stage']);
        $this->assertEquals('after_update', $log[3]['stage']);
    }

    public function testQCEntryRejectedCanTransitionBackToOpen(): void
    {
        $store = $this->createStore();
        $context = EntityContext::admin();

        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Open'], $context, $original);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Submitted'], $context, $original);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Rejected'], $context, $original);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Open'], $context, $original);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Open', $row['status']);
    }
}
