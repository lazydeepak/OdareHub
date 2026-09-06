<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use Plugins\QCEntries\register_qc_entry_entity;
use Plugins\QCEntries\QCEntryHooks;

final class QCEntryIntegrationTest extends TestCase
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
    }

    public function testQCEntryDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('QCEntry');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testQCEntryCreateAppliesDefaults(): void
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
        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
            'qc_type' => 'Final',
        ], $context);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertNotNull($row);
        $this->assertEquals(1, $row['product_id']);
        $this->assertEquals(100, $row['checked_qty']);
        $this->assertEquals('Draft', $row['status']);
        $this->assertEquals('Final', $row['qc_type']);
    }

    public function testQCEntryWorkflowValidTransition(): void
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
        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $original = $this->gateway->read('QCEntry', $id);
        $store->update('QCEntry', $id, ['status' => 'Open'], $context, $original);

        $updated = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Open', $updated['status']);
    }

    public function testQCEntryPolicyBlocksApprovedRow(): void
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
        $store->update('QCEntry', $id, ['remarks' => 'Updated'], $context, $approved);
    }

    public function testQCEntryHooksAreExecuted(): void
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
        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $log = QCEntryHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testQCEntryInvalidWorkflowTransition(): void
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
        $id = $store->create('QCEntry', [
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $original = $this->gateway->read('QCEntry', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('QCEntry', $id, ['status' => 'Approved'], $context, $original);
    }
}
