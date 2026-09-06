<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/AssemblyEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/AssemblyEntries/AssemblyEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/AssemblyEntries/AssemblyEntryHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityHookRunner;
use App\Core\EntityPolicyResolver;
use App\Core\EntitySlaEngine;
use App\Core\EntityWorkflowGuard;
use App\Core\EntityDefinitionValidator;
use App\Core\EntityContext;
use Apps\Manufacturing\Modules\AssemblyEntries\register_assembly_entry_entity;
use Apps\Manufacturing\Modules\AssemblyEntries\AssemblyEntryHooks;

final class AssemblyEntryIntegrationTest extends TestCase
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
        AssemblyEntryHooks::reset();

        $this->gateway = new GatewayStub();
        $this->hookRunner = new EntityHookRunner();
        $this->policyResolver = new EntityPolicyResolver();
        $this->slaEngine = new EntitySlaEngine();
        $this->workflowGuard = new EntityWorkflowGuard();
        $this->validator = new EntityDefinitionValidator();

        \Apps\Manufacturing\Modules\AssemblyEntries\register_assembly_entry_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
        GatewayStub::resetStatic();
        AssemblyEntryHooks::reset();
    }

    public function testAssemblyEntryDefinitionCanBeRegistered(): void
    {
        $definition = EntityRegistry::get('AssemblyEntry');
        $this->assertNotNull($definition);
        $this->assertArrayHasKey('fields', $definition);
        $this->assertArrayHasKey('workflow', $definition);
        $this->assertArrayHasKey('permissions', $definition);
        $this->assertArrayHasKey('hooks', $definition);
    }

    public function testAssemblyEntryCreateAppliesDefaults(): void
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
        $id = $store->create('AssemblyEntry', [
            'assembly_plan_id' => 1,
            'product_id' => 1,
            'assembly_date' => '2026-04-10',
        ], $context);

        $row = $this->gateway->read('AssemblyEntry', $id);
        $this->assertNotNull($row);
        $this->assertEquals('draft', $row['status']);
    }

    public function testAssemblyEntryWorkflowValidTransition(): void
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
        $id = $store->create('AssemblyEntry', [
            'assembly_plan_id' => 1,
            'product_id' => 1,
            'assembly_date' => '2026-04-10',
        ], $context);

        $original = $this->gateway->read('AssemblyEntry', $id);
        $store->update('AssemblyEntry', $id, ['status' => 'in_progress'], $context, $original);

        $updated = $this->gateway->read('AssemblyEntry', $id);
        $this->assertEquals('in_progress', $updated['status']);
    }

    public function testAssemblyEntryPolicyBlocksCompletedRow(): void
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
        $id = $store->create('AssemblyEntry', [
            'assembly_plan_id' => 1,
            'product_id' => 1,
            'assembly_date' => '2026-04-10',
        ], $context);

        $original = $this->gateway->read('AssemblyEntry', $id);
        $store->update('AssemblyEntry', $id, ['status' => 'in_progress'], $context, $original);

        $original = $this->gateway->read('AssemblyEntry', $id);
        $store->update('AssemblyEntry', $id, ['status' => 'completed'], $context, $original);

        $original = $this->gateway->read('AssemblyEntry', $id);
        $store->update('AssemblyEntry', $id, ['status' => 'approved'], $context, $original);

        $approved = $this->gateway->read('AssemblyEntry', $id);
        $approved['status'] = 'approved';

        $this->expectException(\RuntimeException::class);
        $store->update('AssemblyEntry', $id, ['notes' => 'Updated'], $context, $approved);
    }

    public function testAssemblyEntryHooksAreExecuted(): void
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
        $id = $store->create('AssemblyEntry', [
            'assembly_plan_id' => 1,
            'product_id' => 1,
            'assembly_date' => '2026-04-10',
        ], $context);

        $log = AssemblyEntryHooks::getAuditLog();
        $this->assertCount(2, $log);
        $this->assertEquals('before_create', $log[0]['stage']);
        $this->assertEquals('after_create', $log[1]['stage']);
    }

    public function testAssemblyEntryInvalidWorkflowTransition(): void
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
        $id = $store->create('AssemblyEntry', [
            'assembly_plan_id' => 1,
            'product_id' => 1,
            'assembly_date' => '2026-04-10',
        ], $context);

        $original = $this->gateway->read('AssemblyEntry', $id);

        $this->expectException(\RuntimeException::class);
        $store->update('AssemblyEntry', $id, ['status' => 'completed'], $context, $original);
    }
}
