<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryService.php';
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
use Plugins\QCEntries\QCEntryService;
use Plugins\QCEntries\QCEntryHooks;

final class QCEntryApprovalActionIntegrationTest extends TestCase
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

        QCEntryService::setStore(new \App\Core\EntityStore(
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
        QCEntryHooks::reset();
        ManufacturingGateway::reset();
        QCEntryService::reset();
    }

    public function testActionToStatusMapping(): void
    {
        $this->assertEquals('Open', QCEntryService::actionToStatus('submit'));
        $this->assertEquals('Approved', QCEntryService::actionToStatus('approve'));
        $this->assertEquals('Rejected', QCEntryService::actionToStatus('reject'));
        $this->assertEquals('Open', QCEntryService::actionToStatus('reopen'));
        $this->assertEquals('Cancelled', QCEntryService::actionToStatus('cancel'));
        $this->assertNull(QCEntryService::actionToStatus('invalid_action'));
    }

    public function testQuickStatusUpdateThroughService(): void
    {
        $context = EntityContext::admin();

        $id = QCEntryService::create([
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Draft', $row['status']);

        QCEntryService::quickStatusUpdate($id, 'Open', $context);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Open', $row['status']);
        $this->assertEquals('Pending', $row['approval_status']);
    }

    public function testServiceWorkflowValidationEnforced(): void
    {
        $context = EntityContext::admin();

        $id = QCEntryService::create([
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Invalid workflow transition from 'Draft' to 'Approved'");
        QCEntryService::quickStatusUpdate($id, 'Approved', $context);
    }

    public function testServiceHooksFireOnTransition(): void
    {
        $context = EntityContext::admin();

        $id = QCEntryService::create([
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $log = QCEntryHooks::getAuditLog();
        $this->assertCount(2, $log);

        QCEntryService::quickStatusUpdate($id, 'Open', $context);

        $log = QCEntryHooks::getAuditLog();
        $this->assertCount(4, $log);
        $this->assertEquals('before_update', $log[2]['stage']);
        $this->assertEquals('after_update', $log[3]['stage']);
    }

    public function testServicePolicyBlocksApprovedRow(): void
    {
        $context = EntityContext::admin();

        $id = QCEntryService::create([
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        QCEntryService::quickStatusUpdate($id, 'Open', $context);
        QCEntryService::quickStatusUpdate($id, 'Submitted', $context);
        QCEntryService::quickStatusUpdate($id, 'Approved', $context);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Edit not allowed for this row");
        QCEntryService::quickStatusUpdate($id, 'Cancelled', $context);
    }

    public function testControllerActionFlow(): void
    {
        $user = ['id' => 1, 'authority_role' => 'admin', 'acl_roles' => ['admin']];
        $context = EntityContext::fromUser($user);

        $id = QCEntryService::create([
            'product_id' => 1,
            'checked_qty' => 100,
        ], $context);

        $newStatus = QCEntryService::actionToStatus('submit');
        $this->assertNotNull($newStatus);

        $result = QCEntryService::quickStatusUpdate($id, $newStatus, $context);
        $this->assertTrue($result);

        $row = $this->gateway->read('QCEntry', $id);
        $this->assertEquals('Open', $row['status']);
    }
}
