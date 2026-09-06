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

use PHPUnit\Framework\TestCase;
use App\Core\EntityRegistry;
use App\Core\EntityContext;
use App\Core\MyWorkEntityAdapter;

final class MyWorkEntityAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        EntityRegistry::reset();
        \Plugins\DailyOrders\register_daily_orders_entity();
        \Plugins\ProductionEntries\register_production_entry_entity();
    }

    protected function tearDown(): void
    {
        EntityRegistry::reset();
    }

    public function testDailyOrderSlaConfigIsValid(): void
    {
        $config = MyWorkEntityAdapter::getDailyOrderSlaConfig();
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('status', $config['status_field']);
        $this->assertEquals('required_date', $config['deadline_field']);
        $this->assertArrayHasKey('rules', $config);
        $this->assertArrayHasKey('breach_labels', $config);
    }

    public function testProductionEntrySlaConfigIsValid(): void
    {
        $config = MyWorkEntityAdapter::getProductionEntrySlaConfig();
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('status', $config['status_field']);
        $this->assertEquals('production_date', $config['deadline_field']);
        $this->assertArrayHasKey('rules', $config);
        $this->assertArrayHasKey('breach_labels', $config);
    }

    public function testEnrichDailyOrderItemAddsSlaFields(): void
    {
        $context = EntityContext::admin();
        $item = [
            'id' => 1,
            'entity_type' => 'daily_order',
            'customer_name' => 'Test',
            'product_id' => 1,
            'status' => 'Open',
            'required_date' => '2026-04-05',
        ];

        $enriched = MyWorkEntityAdapter::enrichDailyOrderItem($item, $context);

        $this->assertArrayHasKey('sla_state', $enriched);
        $this->assertArrayHasKey('sla_label', $enriched);
        $this->assertArrayHasKey('sla_deadline', $enriched);
        $this->assertArrayHasKey('sla_escalated', $enriched);
        $this->assertArrayHasKey('can_view', $enriched);
        $this->assertArrayHasKey('visibility_source', $enriched);
        $this->assertEquals('entity_runtime', $enriched['visibility_source']);
    }

    public function testEnrichProductionEntryItemAddsSlaFields(): void
    {
        $context = EntityContext::admin();
        $item = [
            'id' => 1,
            'entity_type' => 'production_entry',
            'machine_id' => 1,
            'product_id' => 1,
            'status' => 'Draft',
            'production_date' => '2026-04-05',
        ];

        $enriched = MyWorkEntityAdapter::enrichProductionEntryItem($item, $context);

        $this->assertArrayHasKey('sla_state', $enriched);
        $this->assertArrayHasKey('sla_label', $enriched);
        $this->assertArrayHasKey('sla_deadline', $enriched);
        $this->assertArrayHasKey('sla_escalated', $enriched);
        $this->assertArrayHasKey('can_view', $enriched);
        $this->assertEquals('entity_runtime', $enriched['visibility_source']);
    }

    public function testEnrichItemsFiltersByEntityType(): void
    {
        $context = EntityContext::admin();
        $items = [
            ['id' => 1, 'entity_type' => 'daily_order', 'status' => 'Open', 'required_date' => '2026-04-05'],
            ['id' => 2, 'entity_type' => 'production_entry', 'status' => 'Draft', 'production_date' => '2026-04-05'],
            ['id' => 3, 'entity_type' => 'unknown', 'status' => 'Active'],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $context);

        $this->assertEquals('entity_runtime', $enriched[0]['visibility_source']);
        $this->assertEquals('entity_runtime', $enriched[1]['visibility_source']);
        $this->assertArrayNotHasKey('visibility_source', $enriched[2]);
    }

    public function testIsSlaBreachedReturnsCorrectly(): void
    {
        $breachedItem = ['sla_state' => 'breached'];
        $okItem = ['sla_state' => 'ok'];
        
        $this->assertTrue(MyWorkEntityAdapter::isSlaBreached($breachedItem));
        $this->assertFalse(MyWorkEntityAdapter::isSlaBreached($okItem));
    }

    public function testIsSlaEscalatedReturnsCorrectly(): void
    {
        $escalatedItem = ['sla_escalated' => true];
        $normalItem = ['sla_escalated' => false];
        
        $this->assertTrue(MyWorkEntityAdapter::isSlaEscalated($escalatedItem));
        $this->assertFalse(MyWorkEntityAdapter::isSlaEscalated($normalItem));
    }

    public function testIsVisibleReturnsCorrectly(): void
    {
        $visibleItem = ['can_view' => true, 'visibility_source' => 'entity_runtime'];
        $hiddenItem = ['can_view' => false, 'visibility_source' => 'entity_runtime'];
        $legacyItem = ['can_view' => true];
        
        $this->assertTrue(MyWorkEntityAdapter::isVisible($visibleItem));
        $this->assertFalse(MyWorkEntityAdapter::isVisible($hiddenItem));
        $this->assertTrue(MyWorkEntityAdapter::isVisible($legacyItem));
    }

    public function testFilterBreachedItems(): void
    {
        $items = [
            ['id' => 1, 'sla_state' => 'breached'],
            ['id' => 2, 'sla_state' => 'ok'],
            ['id' => 3, 'sla_state' => 'breached'],
            ['id' => 4, 'sla_state' => 'due_soon'],
        ];

        $breached = MyWorkEntityAdapter::filterBreachedItems($items);
        
        $this->assertCount(2, $breached);
        $this->assertEquals(1, $breached[0]['id']);
        $this->assertEquals(3, $breached[1]['id']);
    }

    public function testFilterVisibleItems(): void
    {
        $items = [
            ['id' => 1, 'can_view' => true, 'visibility_source' => 'entity_runtime'],
            ['id' => 2, 'can_view' => false, 'visibility_source' => 'entity_runtime'],
            ['id' => 3, 'can_view' => true],
        ];

        $visible = MyWorkEntityAdapter::filterVisibleItems($items);
        
        $this->assertCount(2, $visible);
        $this->assertEquals(1, $visible[0]['id']);
        $this->assertEquals(3, $visible[1]['id']);
    }

    public function testGetSlaUrgencyRankReturnsCorrectOrder(): void
    {
        $escalated = ['sla_state' => 'breached', 'sla_escalated' => true];
        $breached = ['sla_state' => 'breached', 'sla_escalated' => false];
        $dueSoon = ['sla_state' => 'due_soon', 'sla_escalated' => false];
        $ok = ['sla_state' => 'ok', 'sla_escalated' => false];

        $this->assertEquals(0, MyWorkEntityAdapter::getSlaUrgencyRank($escalated));
        $this->assertEquals(1, MyWorkEntityAdapter::getSlaUrgencyRank($breached));
        $this->assertEquals(2, MyWorkEntityAdapter::getSlaUrgencyRank($dueSoon));
        $this->assertEquals(9, MyWorkEntityAdapter::getSlaUrgencyRank($ok));
    }
}
