<?php
declare(strict_types=1);

namespace Tests;

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../app/Core/MyWorkInspector.php';
require_once __DIR__ . '/../app/Core/EntityRegistry.php';
require_once __DIR__ . '/../app/Core/EntityPolicyResolver.php';
require_once __DIR__ . '/../app/Core/EntitySlaEngine.php';
require_once __DIR__ . '/../app/Core/ManufacturingSlaConfig.php';
require_once __DIR__ . '/../app/Core/EntityContext.php';
require_once __DIR__ . '/../app/Core/MyWorkEntityQuery.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/QCEntries/QCEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DispatchEntries/DispatchEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\MyWorkInspector;
use App\Core\EntityRegistry;
use App\Core\EntityContext;
use App\Core\MyWorkEntityQuery;
use App\Core\DB;

final class MyWorkInspectorTest extends TestCase
{
    private const QC_FIXTURE_REMARKS = '__phpunit_my_work_inspector__';

    /** @var array<int> */
    private array $createdQcEntryIds = [];

    protected function setUp(): void
    {
        EntityRegistry::reset();
        \Plugins\QCEntries\register_qc_entry_entity();
        \Plugins\DispatchEntries\register_dispatch_entry_entity();
        \Plugins\ProductionPlans\register_production_plan_entity();
        \Plugins\DailyOrders\register_daily_orders_entity();
        \Plugins\ProductionEntries\register_production_entry_entity();
    }

    protected function tearDown(): void
    {
        foreach (array_unique($this->createdQcEntryIds) as $id) {
            try {
                DB::query(
                    'DELETE FROM qc_entries WHERE id = ? AND remarks = ?',
                    [(int)$id, self::QC_FIXTURE_REMARKS]
                );
            } catch (\Throwable) {
                // Best-effort cleanup for local database-backed tests.
            }
        }

        $this->createdQcEntryIds = [];
    }

    public function testExplainItemReturnsExpectedStructure(): void
    {
        $item = [
            'entity_type' => 'qc_entry',
            'id' => 1,
            'status' => 'Open',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('entity_type', $result);
        $this->assertArrayHasKey('entity_key', $result);
        $this->assertArrayHasKey('entity_id', $result);
        $this->assertArrayHasKey('included', $result);
        $this->assertArrayHasKey('inclusion_reason', $result);
        $this->assertArrayHasKey('can_view', $result);
        $this->assertArrayHasKey('visibility_reason', $result);
        $this->assertArrayHasKey('bucket', $result);
        $this->assertArrayHasKey('has_sla', $result);
        $this->assertArrayHasKey('sla_state', $result);
        $this->assertArrayHasKey('section', $result);
        $this->assertArrayHasKey('stage_label', $result);
    }

    public function testExplainItemWithCompletedStatusIsExcluded(): void
    {
        $item = [
            'entity_type' => 'qc_entry',
            'id' => 1,
            'status' => 'Completed',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertFalse($result['included']);
        $this->assertEquals('filtered_by_status', $result['inclusion_reason']);
    }

    public function testExplainItemWithActiveStatusIsIncluded(): void
    {
        $item = [
            'entity_type' => 'daily_order',
            'id' => 1,
            'status' => 'Open',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertTrue($result['included']);
        $this->assertEquals('active_scope', $result['inclusion_reason']);
    }

    public function testExplainItemMapsEntityTypeToEntityKey(): void
    {
        $item = [
            'entity_type' => 'production_plan',
            'id' => 1,
            'status' => 'Planned',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertEquals('production_plan', $result['entity_type']);
        $this->assertEquals('ProductionPlan', $result['entity_key']);
    }

    public function testExplainItemsReturnsArrayOfExplainedItems(): void
    {
        $items = [
            ['entity_type' => 'qc_entry', 'id' => 1, 'status' => 'Open', 'updated_at' => date('Y-m-d H:i:s')],
            ['entity_type' => 'dispatch_entry', 'id' => 2, 'status' => 'Ready', 'updated_at' => date('Y-m-d H:i:s')],
        ];

        $ctx = EntityContext::admin();
        $results = MyWorkInspector::explainItems($items, $ctx);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        $this->assertEquals('qc_entry', $results[0]['entity_type']);
        $this->assertEquals('dispatch_entry', $results[1]['entity_type']);
    }

    public function testGetSupportedEntityTypesReturnsAllSupported(): void
    {
        $types = MyWorkInspector::getSupportedEntityTypes();

        $this->assertIsArray($types);
        $this->assertContains('daily_order', $types);
        $this->assertContains('production_entry', $types);
        $this->assertContains('production_plan', $types);
        $this->assertContains('qc_entry', $types);
        $this->assertContains('dispatch_entry', $types);
        $this->assertContains('assembly_plan', $types);
        $this->assertContains('assembly_entry', $types);
    }

    public function testGetEntityTypeLabelReturnsCorrectLabels(): void
    {
        $this->assertEquals('Daily Order', MyWorkInspector::getEntityTypeLabel('daily_order'));
        $this->assertEquals('Production Entry', MyWorkInspector::getEntityTypeLabel('production_entry'));
        $this->assertEquals('Production Plan', MyWorkInspector::getEntityTypeLabel('production_plan'));
        $this->assertEquals('QC Entry', MyWorkInspector::getEntityTypeLabel('qc_entry'));
        $this->assertEquals('Dispatch Entry', MyWorkInspector::getEntityTypeLabel('dispatch_entry'));
        $this->assertEquals('Assembly Plan', MyWorkInspector::getEntityTypeLabel('assembly_plan'));
        $this->assertEquals('Assembly Entry', MyWorkInspector::getEntityTypeLabel('assembly_entry'));
    }

    public function testEntityTypeToEntityKeyReturnsCorrectMappings(): void
    {
        $this->assertEquals('DailyOrder', MyWorkInspector::entityTypeToEntityKey('daily_order'));
        $this->assertEquals('ProductionEntry', MyWorkInspector::entityTypeToEntityKey('production_entry'));
        $this->assertEquals('ProductionPlan', MyWorkInspector::entityTypeToEntityKey('production_plan'));
        $this->assertEquals('QCEntry', MyWorkInspector::entityTypeToEntityKey('qc_entry'));
        $this->assertEquals('DispatchEntry', MyWorkInspector::entityTypeToEntityKey('dispatch_entry'));
        $this->assertEquals('AssemblyPlan', MyWorkInspector::entityTypeToEntityKey('assembly_plan'));
        $this->assertEquals('AssemblyEntry', MyWorkInspector::entityTypeToEntityKey('assembly_entry'));
        $this->assertEquals('', MyWorkInspector::entityTypeToEntityKey('unknown'));
    }

    public function testWorkAreaForEntityReturnsCorrectAreas(): void
    {
        $this->assertEquals('order', MyWorkInspector::workAreaForEntity('daily_order'));
        $this->assertEquals('production', MyWorkInspector::workAreaForEntity('production_entry'));
        $this->assertEquals('production', MyWorkInspector::workAreaForEntity('production_plan'));
        $this->assertEquals('qc', MyWorkInspector::workAreaForEntity('qc_entry'));
        $this->assertEquals('dispatch', MyWorkInspector::workAreaForEntity('dispatch_entry'));
        $this->assertEquals('assembly', MyWorkInspector::workAreaForEntity('assembly_plan'));
        $this->assertEquals('assembly', MyWorkInspector::workAreaForEntity('assembly_entry'));
    }

    public function testDetermineBucketByAreasReturnsPrimaryForMatchingArea(): void
    {
        $bucket = MyWorkInspector::determineBucketByAreas('production', 'production', []);
        $this->assertEquals(MyWorkInspector::BUCKET_PRIMARY_WORK, $bucket);
    }

    public function testDetermineBucketByAreasReturnsCrossFunctionalForCrossArea(): void
    {
        $bucket = MyWorkInspector::determineBucketByAreas('qc', 'production', ['qc', 'dispatch']);
        $this->assertEquals(MyWorkInspector::BUCKET_CROSS_FUNCTIONAL, $bucket);
    }

    public function testDetermineBucketByAreasReturnsVisibilityForOtherArea(): void
    {
        $bucket = MyWorkInspector::determineBucketByAreas('qc', 'production', []);
        $this->assertEquals(MyWorkInspector::BUCKET_VISIBILITY, $bucket);
    }

    public function testExplainItemWithDispatchEntryUsesDispatchStatus(): void
    {
        $item = [
            'entity_type' => 'dispatch_entry',
            'id' => 1,
            'dispatch_status' => 'Ready',
            'status' => 'Draft',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertEquals('Ready', $result['status']);
        $this->assertTrue($result['has_sla']);
    }

    public function testExplainItemWithOptionsOverridesDefaults(): void
    {
        $item = [
            'entity_type' => 'qc_entry',
            'id' => 1,
            'status' => 'Open',
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx, [
            'primary_area' => 'qc',
            'cross_areas' => ['production'],
        ]);

        $this->assertEquals('primary_work', $result['bucket']);
    }

    public function testExplainItemCalculatesAgeHours(): void
    {
        $item = [
            'entity_type' => 'qc_entry',
            'id' => 1,
            'status' => 'Open',
            'updated_at' => date('Y-m-d H:i:s', strtotime('-5 hours')),
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertGreaterThanOrEqual(4.9, $result['age_hours']);
        $this->assertLessThanOrEqual(5.1, $result['age_hours']);
    }

    public function testExplainItemHandlesMissingUpdatedAt(): void
    {
        $item = [
            'entity_type' => 'qc_entry',
            'id' => 1,
            'status' => 'Open',
        ];

        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainItem($item, $ctx);

        $this->assertEquals(0.0, $result['age_hours']);
    }

    public function testExplainSingleRecordReturnsUnsupportedForUnknownEntity(): void
    {
        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainSingleRecord('unknown_entity', 1, $ctx);

        $this->assertFalse($result['found']);
        $this->assertNull($result['record']);
        $this->assertEquals('unsupported_entity', $result['error']);
        $this->assertStringContainsString('not supported', $result['error_message']);
    }

    public function testExplainSingleRecordReturnsNotFoundForMissingRecord(): void
    {
        $ctx = EntityContext::admin();
        $result = MyWorkInspector::explainSingleRecord('qc_entry', 99999999, $ctx);

        $this->assertFalse($result['found']);
        $this->assertNull($result['record']);
        $this->assertEquals('not_found', $result['error']);
        $this->assertStringContainsString('not found', $result['error_message']);
    }

    public function testExplainSingleRecordIncludesExplanationFields(): void
    {
        $ctx = EntityContext::admin();
        $id = $this->qcEntryFixtureId();

        $result = MyWorkInspector::explainSingleRecord('qc_entry', $id, $ctx);

        $this->assertTrue($result['found']);
        $this->assertNotNull($result['record']);
        $this->assertNull($result['error']);
        $this->assertArrayHasKey('explanation', $result);
        $this->assertArrayHasKey('included', $result['explanation']);
        $this->assertArrayHasKey('inclusion_reason', $result['explanation']);
        $this->assertArrayHasKey('can_view', $result['explanation']);
        $this->assertArrayHasKey('bucket', $result['explanation']);
        $this->assertArrayHasKey('sla_state', $result['explanation']);
        $this->assertArrayHasKey('section', $result['explanation']);
    }

    public function testFetchRecordsByIdsReturnsEmptyForEmptyInput(): void
    {
        $result = MyWorkInspector::fetchRecordsByIds('qc_entry', []);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFetchRecordsByIdsFiltersInvalidIds(): void
    {
        $result = MyWorkInspector::fetchRecordsByIds('qc_entry', [0, -1, 'abc']);
        $this->assertIsArray($result);
    }

    public function testExplainSingleRecordUsesProvidedOptions(): void
    {
        $ctx = EntityContext::admin();
        $id = $this->qcEntryFixtureId();

        $result = MyWorkInspector::explainSingleRecord('qc_entry', $id, $ctx, [
            'primary_area' => 'qc',
            'cross_areas' => ['production'],
        ]);

        $this->assertTrue($result['found']);
        $this->assertEquals('qc', $result['primary_area']);
        $this->assertEquals(['production'], $result['cross_areas']);
        $this->assertEquals('primary_work', $result['explanation']['bucket']);
    }

    private function qcEntryFixtureId(): int
    {
        $rows = MyWorkEntityQuery::fetchQCEntries(['limit' => 1]);
        $id = (int)($rows[0]['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        if (!$this->qcEntriesTableExists()) {
            $this->markTestSkipped('QC entries table is not available in test DB');
        }

        try {
            DB::query(
                'INSERT INTO qc_entries (product_id, qc_type, checked_qty, pass_qty, fail_qty, status, approval_status, remarks, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW())',
                [0, 'Final', 10.0, 9.0, 1.0, 'Open', 'Draft', self::QC_FIXTURE_REMARKS]
            );

            $id = (int)DB::conn()->insert_id;
        } catch (\Throwable $e) {
            $this->markTestSkipped('Unable to create QC entry fixture: ' . $e->getMessage());
        }

        if ($id <= 0) {
            $this->markTestSkipped('Unable to create valid QC entry fixture');
        }

        $this->createdQcEntryIds[] = $id;
        return $id;
    }

    private function qcEntriesTableExists(): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                ['qc_entries']
            ) !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
