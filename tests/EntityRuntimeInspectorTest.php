<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../app/Core/EntityRuntimeInspector.php';
require_once __DIR__ . '/../app/Core/EntityRegistry.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanService.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryService.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryService.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRuntimeInspector;
use App\Core\EntityRegistry;

final class EntityRuntimeInspectorTest extends TestCase
{
    protected function setUp(): void
    {
        EntityRegistry::reset();
        \Plugins\QCEntries\register_qc_entry_entity();
        \Plugins\DispatchEntries\register_dispatch_entry_entity();
        \Plugins\ProductionPlans\register_production_plan_entity();
    }

    public function testInspectReturnsArray(): void
    {
        $result = EntityRuntimeInspector::inspect();
        $this->assertIsArray($result);
    }

    public function testInspectContainsRegisteredEntities(): void
    {
        $result = EntityRuntimeInspector::inspect();

        $this->assertArrayHasKey('QCEntry', $result);
        $this->assertArrayHasKey('DispatchEntry', $result);
        $this->assertArrayHasKey('ProductionPlan', $result);
    }

    public function testInspectExposesWorkflowStatesAndTransitions(): void
    {
        $result = EntityRuntimeInspector::inspect();

        $qcEntry = $result['QCEntry'] ?? null;
        $this->assertNotNull($qcEntry);
        $this->assertArrayHasKey('workflow', $qcEntry);
        $this->assertArrayHasKey('states', $qcEntry['workflow']);
        $this->assertArrayHasKey('transitions', $qcEntry['workflow']);

        $this->assertIsArray($qcEntry['workflow']['states']);
        $this->assertIsArray($qcEntry['workflow']['transitions']);
    }

    public function testInspectExposesSlaPresenceInfo(): void
    {
        $result = EntityRuntimeInspector::inspect();

        $qcEntry = $result['QCEntry'] ?? null;
        $this->assertNotNull($qcEntry);
        $this->assertArrayHasKey('sla_config', $qcEntry);
        $this->assertEquals('ENTITY_QC_ENTRY', $qcEntry['sla_config']);

        $dispatchEntry = $result['DispatchEntry'] ?? null;
        $this->assertNotNull($dispatchEntry);
        $this->assertEquals('ENTITY_DISPATCH_ENTRY', $dispatchEntry['sla_config']);
    }

    public function testInspectExposesDispatchActionClassification(): void
    {
        $result = EntityRuntimeInspector::inspect();

        $dispatchEntry = $result['DispatchEntry'] ?? null;
        $this->assertNotNull($dispatchEntry);
        $this->assertArrayHasKey('action_classification', $dispatchEntry);
        $this->assertIsArray($dispatchEntry['action_classification']);

        $classification = $dispatchEntry['action_classification'];
        $this->assertArrayHasKey('entity_lifecycle', $classification);
        $this->assertArrayHasKey('governance_only', $classification);
        $this->assertArrayHasKey('operational', $classification);

        $this->assertContains('submit', $classification['entity_lifecycle']);
        $this->assertContains('approve', $classification['entity_lifecycle']);
        $this->assertContains('reject', $classification['governance_only']);
        $this->assertContains('handoff', $classification['operational']);
    }

    public function testInspectExposesHasServiceFlag(): void
    {
        $result = EntityRuntimeInspector::inspect();

        $qcEntry = $result['QCEntry'] ?? null;
        $this->assertNotNull($qcEntry);
        $this->assertArrayHasKey('has_service', $qcEntry);
        $this->assertTrue($qcEntry['has_service']);
    }

    public function testDetectModuleReturnsCorrectModuleName(): void
    {
        $this->assertEquals('QCEntries', EntityRuntimeInspector::detectModule('QCEntry'));
        $this->assertEquals('DispatchEntries', EntityRuntimeInspector::detectModule('DispatchEntry'));
        $this->assertEquals('ProductionPlans', EntityRuntimeInspector::detectModule('ProductionPlan'));
        $this->assertEquals('Unknown', EntityRuntimeInspector::detectModule('UnknownEntity'));
    }

    public function testExtractWorkflowInfoReturnsExpectedStructure(): void
    {
        $definition = [
            'workflow' => [
                'field' => 'status',
                'states' => ['Open' => ['type' => 'normal']],
                'transitions' => ['submit' => ['from' => 'Open', 'to' => 'Submitted']],
            ],
        ];

        $result = EntityRuntimeInspector::extractWorkflowInfo($definition);

        $this->assertEquals('status', $result['field']);
        $this->assertCount(1, $result['states']);
        $this->assertCount(1, $result['transitions']);
    }

    public function testExtractFieldsReturnsStatusFields(): void
    {
        $definition = [
            'fields' => [
                'id' => [],
                'status' => [],
                'approval_status' => [],
                'qty' => [],
            ],
        ];

        $result = EntityRuntimeInspector::extractFields($definition);

        $this->assertEquals(4, $result['count']);
        $this->assertContains('status', $result['status_fields']);
        $this->assertContains('approval_status', $result['status_fields']);
    }

    public function testExtractHooksReturnsHookTypes(): void
    {
        $definition = [
            'hooks' => [
                'before_create' => [],
                'after_update' => [],
            ],
        ];

        $result = EntityRuntimeInspector::extractHooks($definition);

        $this->assertEquals(2, $result['count']);
        $this->assertContains('before_create', $result['types']);
        $this->assertContains('after_update', $result['types']);
    }

    public function testClassifyActionsReturnsNullForNonDispatchEntry(): void
    {
        $result = EntityRuntimeInspector::classifyActions('QCEntry');
        $this->assertNull($result);
    }

    public function testClassifyActionsReturnsClassificationForDispatchEntry(): void
    {
        $result = EntityRuntimeInspector::classifyActions('DispatchEntry');
        $this->assertIsArray($result);
        $this->assertArrayHasKey('entity_lifecycle', $result);
        $this->assertArrayHasKey('governance_only', $result);
        $this->assertArrayHasKey('operational', $result);
    }

    public function testDetectSlaConfigReturnsCorrectKey(): void
    {
        $this->assertEquals('ENTITY_QC_ENTRY', EntityRuntimeInspector::detectSlaConfig('QCEntry'));
        $this->assertEquals('ENTITY_DISPATCH_ENTRY', EntityRuntimeInspector::detectSlaConfig('DispatchEntry'));
        $this->assertNull(EntityRuntimeInspector::detectSlaConfig('UnknownEntity'));
    }

    public function testHasEntityServiceReturnsTrueForKnownEntities(): void
    {
        $this->assertTrue(EntityRuntimeInspector::hasEntityService('QCEntry'));
        $this->assertTrue(EntityRuntimeInspector::hasEntityService('DispatchEntry'));
    }

    public function testDryRunTransitionReturnsExpectedStructure(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('QCEntry', 'Open', 'Submitted');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('entity_key', $result);
        $this->assertArrayHasKey('workflow_field', $result);
        $this->assertArrayHasKey('current_state', $result);
        $this->assertArrayHasKey('target_state', $result);
        $this->assertArrayHasKey('allowed_next_states', $result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('reason', $result);
    }

    public function testDryRunTransitionReturnsCorrectEntityKey(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('DispatchEntry', 'Ready', 'Dispatched');

        $this->assertEquals('DispatchEntry', $result['entity_key']);
    }

    public function testDryRunTransitionReturnsAllowedNextStates(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('QCEntry', 'Open', 'Submitted');

        $this->assertIsArray($result['allowed_next_states']);
    }

    public function testDryRunTransitionWithActionExposesCategory(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('DispatchEntry', 'Ready', 'Dispatched', 'submit');

        $this->assertEquals('submit', $result['action']);
        $this->assertNotNull($result['action_category']);
        $this->assertEquals('entity_lifecycle', $result['action_category']);
    }

    public function testDryRunTransitionWithNonDispatchActionReturnsNullCategory(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('QCEntry', 'Open', 'Submitted', 'submit');

        $this->assertNull($result['action_category']);
    }

    public function testDryRunTransitionUnknownEntityReturnsAllowedFalse(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('UnknownEntity', 'Open', 'Closed');

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('Unknown entity key', $result['reason']);
    }

    public function testGetRegisteredEntityKeysReturnsArray(): void
    {
        $keys = EntityRuntimeInspector::getRegisteredEntityKeys();

        $this->assertIsArray($keys);
        $this->assertContains('QCEntry', $keys);
        $this->assertContains('DispatchEntry', $keys);
        $this->assertContains('ProductionPlan', $keys);
    }

    public function testDryRunTransitionReturnsAllStates(): void
    {
        $result = EntityRuntimeInspector::dryRunTransition('QCEntry', 'Open', 'Submitted');

        $this->assertIsArray($result['all_states']);
        $this->assertNotEmpty($result['all_states']);
    }

    public function testInspectActionReturnsExpectedStructure(): void
    {
        $result = EntityRuntimeInspector::inspectAction('DispatchEntry', 'submit');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('entity_key', $result);
        $this->assertArrayHasKey('action', $result);
        $this->assertArrayHasKey('action_recognized', $result);
        $this->assertArrayHasKey('mapped_status', $result);
        $this->assertArrayHasKey('workflow_field', $result);
        $this->assertArrayHasKey('reason', $result);
    }

    public function testInspectActionRecognizesDispatchSubmit(): void
    {
        $result = EntityRuntimeInspector::inspectAction('DispatchEntry', 'submit');

        $this->assertTrue($result['action_recognized']);
        $this->assertEquals('Ready', $result['mapped_status']);
        $this->assertEquals('entity_lifecycle', $result['action_category']);
    }

    public function testInspectActionRecognizesQCApprove(): void
    {
        $result = EntityRuntimeInspector::inspectAction('QCEntry', 'approve');

        $this->assertTrue($result['action_recognized']);
        $this->assertEquals('Approved', $result['mapped_status']);
    }

    public function testInspectActionRecognizesProductionPlanSubmit(): void
    {
        $result = EntityRuntimeInspector::inspectAction('ProductionPlan', 'submit');

        $this->assertTrue($result['action_recognized']);
        $this->assertEquals('Planned', $result['mapped_status']);
    }

    public function testInspectActionReturnsFalseForUnrecognizedAction(): void
    {
        $result = EntityRuntimeInspector::inspectAction('DispatchEntry', 'unknown_action');

        $this->assertFalse($result['action_recognized']);
        $this->assertNull($result['mapped_status']);
        $this->assertStringContainsString('not mapped', $result['reason']);
    }

    public function testInspectActionWithCurrentStateChecksWorkflow(): void
    {
        $result = EntityRuntimeInspector::inspectAction('QCEntry', 'approve', 'Open');

        $this->assertEquals('Open', $result['current_state']);
        $this->assertIsArray($result['allowed_next_states']);
        $this->assertArrayHasKey('allowed_from_current', $result);
    }

    public function testGetMappedStatusReturnsStatusForDispatchEntry(): void
    {
        $this->assertEquals('Ready', EntityRuntimeInspector::getMappedStatus('DispatchEntry', 'submit'));
        $this->assertEquals('Dispatched', EntityRuntimeInspector::getMappedStatus('DispatchEntry', 'finalize'));
        $this->assertEquals('Hold', EntityRuntimeInspector::getMappedStatus('DispatchEntry', 'hold'));
        $this->assertEquals('Cancelled', EntityRuntimeInspector::getMappedStatus('DispatchEntry', 'cancel'));
    }

    public function testGetMappedStatusReturnsNullForUnrecognizedAction(): void
    {
        $this->assertNull(EntityRuntimeInspector::getMappedStatus('DispatchEntry', 'unknown'));
        $this->assertNull(EntityRuntimeInspector::getMappedStatus('QCEntry', 'foobar'));
    }

    public function testGetActionCategoryReturnsCategoryForDispatchEntry(): void
    {
        $this->assertEquals('entity_lifecycle', EntityRuntimeInspector::getActionCategory('DispatchEntry', 'submit'));
        $this->assertEquals('governance_only', EntityRuntimeInspector::getActionCategory('DispatchEntry', 'reject'));
        $this->assertEquals('operational', EntityRuntimeInspector::getActionCategory('DispatchEntry', 'handoff'));
    }

    public function testGetActionCategoryReturnsNullForNonDispatchEntry(): void
    {
        $this->assertNull(EntityRuntimeInspector::getActionCategory('QCEntry', 'approve'));
        $this->assertNull(EntityRuntimeInspector::getActionCategory('UnknownEntity', 'submit'));
    }

    public function testInspectRecordReturnsUnsupportedForUnknownEntity(): void
    {
        $ctx = \App\Core\EntityContext::admin();
        $result = EntityRuntimeInspector::inspectRecord('UnknownEntity', 1, $ctx);

        $this->assertFalse($result['found']);
        $this->assertNull($result['record']);
        $this->assertEquals('unsupported_entity', $result['error']);
        $this->assertStringContainsString('not supported', $result['error_message']);
    }

    public function testInspectRecordReturnsNotFoundForMissingRecord(): void
    {
        $ctx = \App\Core\EntityContext::admin();
        $result = EntityRuntimeInspector::inspectRecord('QCEntry', 99999999, $ctx);

        $this->assertFalse($result['found']);
        $this->assertNull($result['record']);
        $this->assertEquals('not_found', $result['error']);
    }

    public function testInspectRecordIncludesRequiredFields(): void
    {
        $ctx = \App\Core\EntityContext::admin();
        $result = EntityRuntimeInspector::inspectRecord('UnknownEntity', 1, $ctx);

        $this->assertFalse($result['found']);
        $this->assertEquals('unsupported_entity', $result['error']);
    }

    public function testGetSupportedEntityKeysReturnsAllSupported(): void
    {
        $keys = EntityRuntimeInspector::getSupportedEntityKeys();

        $this->assertIsArray($keys);
        $this->assertContains('QCEntry', $keys);
        $this->assertContains('DispatchEntry', $keys);
        $this->assertContains('ProductionPlan', $keys);
    }

    public function testGetEntityKeyLabelReturnsCorrectLabels(): void
    {
        $this->assertEquals('QC Entry', EntityRuntimeInspector::getEntityKeyLabel('QCEntry'));
        $this->assertEquals('Dispatch Entry', EntityRuntimeInspector::getEntityKeyLabel('DispatchEntry'));
        $this->assertEquals('Production Plan', EntityRuntimeInspector::getEntityKeyLabel('ProductionPlan'));
    }

    public function testNormalizeEntityKeyHandlesBothFormats(): void
    {
        $ctx = \App\Core\EntityContext::admin();
        $result1 = EntityRuntimeInspector::inspectRecord('qc_entry', 99999999, $ctx);
        $result2 = EntityRuntimeInspector::inspectRecord('QCEntry', 99999999, $ctx);

        $this->assertFalse($result1['found']);
        $this->assertFalse($result2['found']);
        $this->assertEquals('not_found', $result1['error']);
        $this->assertEquals('not_found', $result2['error']);
    }
}
