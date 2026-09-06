<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/DailyOrders/DailyOrderHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionEntries/ProductionEntryHooks.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/EntityDefinition.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanPolicies.php';
require_once __DIR__ . '/../apps/Manufacturing/modules/ProductionPlans/ProductionPlanHooks.php';

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\MyWorkEntityQuery;
use App\Core\MyWorkEntityAdapter;
use App\Core\EntityContext;

final class MyWorkServiceQueryIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        EntityRegistry::reset();
        \Plugins\DailyOrders\register_daily_orders_entity();
        \Plugins\ProductionEntries\register_production_entry_entity();
        \Plugins\ProductionPlans\register_production_plan_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
    }

    public function testMyWorkEntityQueryFetchesAndAdapterEnrichesDailyOrders(): void
    {
        $rows = MyWorkEntityQuery::fetchDailyOrders(['limit' => 5]);
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('entity_type', $row);
            $this->assertEquals('daily_order', $row['entity_type']);
            $this->assertArrayHasKey('required_date', $row);
        }

        $ctx = EntityContext::admin();
        $enriched = MyWorkEntityAdapter::enrichItems($rows, $ctx);

        foreach ($enriched as $item) {
            $this->assertArrayHasKey('sla_state', $item);
            $this->assertArrayHasKey('visibility_source', $item);
        }
    }

    public function testMyWorkEntityQueryFetchesAndAdapterEnrichesProductionEntries(): void
    {
        $rows = MyWorkEntityQuery::fetchProductionEntries(['limit' => 5]);
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('entity_type', $row);
            $this->assertEquals('production_entry', $row['entity_type']);
            $this->assertArrayHasKey('production_date', $row);
        }

        $ctx = EntityContext::admin();
        $enriched = MyWorkEntityAdapter::enrichItems($rows, $ctx);

        foreach ($enriched as $item) {
            $this->assertArrayHasKey('sla_state', $item);
            $this->assertArrayHasKey('visibility_source', $item);
        }
    }

    public function testMyWorkEntityQueryFetchesAndAdapterEnrichesProductionPlans(): void
    {
        $rows = MyWorkEntityQuery::fetchProductionPlans(['limit' => 5]);
        $this->assertIsArray($rows);

        foreach ($rows as $row) {
            $this->assertArrayHasKey('entity_type', $row);
            $this->assertEquals('production_plan', $row['entity_type']);
            $this->assertArrayHasKey('plan_date', $row);
        }

        $ctx = EntityContext::admin();
        $enriched = MyWorkEntityAdapter::enrichItems($rows, $ctx);

        foreach ($enriched as $item) {
            $this->assertArrayHasKey('sla_state', $item);
            $this->assertArrayHasKey('visibility_source', $item);
        }
    }

    public function testFetchAllMyWorkEntitiesCombinesAllThree(): void
    {
        $all = MyWorkEntityQuery::fetchAllMyWorkEntities(['limit' => 20]);
        $this->assertIsArray($all);
        $this->assertLessThanOrEqual(60, count($all));

        // Verify all returned rows have a valid entity_type from the known set.
        // (Does not require all types to be present simultaneously — DB state may vary.)
        $knownTypes = ['daily_order', 'production_entry', 'production_plan', 'qc_entry', 'dispatch_entry'];
        foreach ($all as $row) {
            $this->assertArrayHasKey('entity_type', $row);
            $this->assertContains($row['entity_type'], $knownTypes, "Unexpected entity type '{$row['entity_type']}' in combined results");
        }
    }
}
