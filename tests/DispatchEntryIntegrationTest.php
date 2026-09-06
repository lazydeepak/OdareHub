<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use Plugins\DispatchEntries\register_dispatch_entry_entity;
use Plugins\DispatchEntries\DispatchEntryHooks;

final class DispatchEntryIntegrationTest extends TestCase
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

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Plugins\DispatchEntries\register_dispatch_entry_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        DispatchEntryHooks::reset();
    }

    public function testDispatchEntryDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('DispatchEntry');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testDispatchEntryCreateAppliesDefaults(): void
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
        $id = $store->create('DispatchEntry', [
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 100,
            'destination' => 'Test Customer',
        ], $context);

        $row = $this->gateway->read('DispatchEntry', $id);
        $this->assertNotNull($row);
        $this->assertEquals('2026-04-10', $row['dispatch_date']);
        $this->assertEquals(1, $row['product_id']);
        $this->assertEquals(100, $row['dispatchable_qty']);
        $this->assertEquals('Draft', $row['dispatch_status']);
        $this->assertEquals('Regular', $row['dispatch_type']);
    }

    public function testDispatchEntryWorkflowValidTransition(): void
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
        $id = $store->create('DispatchEntry', [
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 100,
        ], $context);

        $original = $this->gateway->read('DispatchEntry', $id);
        $store->update('DispatchEntry', $id, ['dispatch_status' => 'Ready'], $context, $original);

        $updated = $this->gateway->read('DispatchEntry', $id);
        $this->assertEquals('Ready', $updated['dispatch_status']);
    }

    public function testDispatchEntryPolicyBlocksDispatchedRow(): void
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
        $id = $store->create('DispatchEntry', [
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 100,
        ], $context);

        $original = $this->gateway->read('DispatchEntry', $id);
        $store->update('DispatchEntry', $id, ['dispatch_status' => 'Ready'], $context, $original);

        $original = $this->gateway->read('DispatchEntry', $id);
        $store->update('DispatchEntry', $id, ['dispatch_status' => 'Dispatched'], $context, $original);

        $dispatched = $this->gateway->read('DispatchEntry', $id);
        $dispatched['dispatch_status'] = 'Dispatched';

        $this->expectException(\RuntimeException::class);
        $store->update('DispatchEntry', $id, ['remarks' => 'Updated'], $context, $dispatched);
    }

    public function testDispatchEntryHooksAreExecuted(): void
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
        $id = $store->create('DispatchEntry', [
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 100,
        ], $context);

        $log = DispatchEntryHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testDispatchEntryInvalidWorkflowTransition(): void
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
        $id = $store->create('DispatchEntry', [
            'dispatch_date' => '2026-04-10',
            'product_id' => 1,
            'dispatchable_qty' => 100,
        ], $context);

        $original = $this->gateway->read('DispatchEntry', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('DispatchEntry', $id, ['dispatch_status' => 'Dispatched'], $context, $original);
    }
}
