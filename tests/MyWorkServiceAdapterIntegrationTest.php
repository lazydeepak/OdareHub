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
use App\Core\MyWorkEntityAdapter;
use App\Core\EntityContext;

final class MyWorkServiceAdapterIntegrationTest extends TestCase
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

    public function testMyWorkEntityAdapterEnrichesDailyOrderItems(): void
    {
        $user = ['id' => 1, 'role' => 'admin', 'authority_role' => 'app_admin'];
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'daily_order',
                'status' => 'Open',
                'required_date' => '2026-04-05',
                'dedup_key' => 'daily_order:1',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $ctx);

        $this->assertCount(1, $enriched);
        $this->assertArrayHasKey('sla_state', $enriched[0]);
        $this->assertArrayHasKey('sla_label', $enriched[0]);
        $this->assertArrayHasKey('sla_deadline', $enriched[0]);
        $this->assertArrayHasKey('can_view', $enriched[0]);
        $this->assertEquals('entity_runtime', $enriched[0]['visibility_source']);
    }

    public function testMyWorkEntityAdapterEnrichesProductionEntryItems(): void
    {
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'production_entry',
                'status' => 'Open',
                'production_date' => '2026-04-05',
                'dedup_key' => 'production_entry:1',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $ctx);

        $this->assertCount(1, $enriched);
        $this->assertArrayHasKey('sla_state', $enriched[0]);
        $this->assertArrayHasKey('sla_label', $enriched[0]);
        $this->assertArrayHasKey('sla_deadline', $enriched[0]);
        $this->assertArrayHasKey('can_view', $enriched[0]);
        $this->assertEquals('entity_runtime', $enriched[0]['visibility_source']);
    }

    public function testMyWorkEntityAdapterPreservesOtherEntityTypes(): void
    {
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'purchase_order',
                'status' => 'Draft',
                'dedup_key' => 'purchase_order:1',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
            [
                'id' => 2,
                'entity_type' => 'daily_order',
                'status' => 'Open',
                'required_date' => '2026-04-05',
                'dedup_key' => 'daily_order:2',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $ctx);

        $this->assertCount(2, $enriched);
        $this->assertArrayNotHasKey('sla_state', $enriched[0]);
        $this->assertArrayHasKey('sla_state', $enriched[1]);
        $this->assertEquals('entity_runtime', $enriched[1]['visibility_source']);
    }

    public function testMyWorkEntityAdapterEnrichesQCEntryItems(): void
    {
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'qc_entry',
                'status' => 'Open',
                'updated_at' => '2026-04-05 10:00:00',
                'dedup_key' => 'qc_entry:1',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $ctx);

        $this->assertCount(1, $enriched);
        $this->assertArrayHasKey('sla_state', $enriched[0]);
        $this->assertArrayHasKey('sla_label', $enriched[0]);
        $this->assertArrayHasKey('can_view', $enriched[0]);
        $this->assertEquals('entity_runtime', $enriched[0]['visibility_source']);
    }

    public function testMyWorkEntityAdapterEnrichesProductionPlanItems(): void
    {
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'production_plan',
                'status' => 'Planned',
                'plan_date' => '2026-04-05',
                'dedup_key' => 'production_plan:1',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $ctx);

        $this->assertCount(1, $enriched);
        $this->assertArrayHasKey('sla_state', $enriched[0]);
        $this->assertArrayHasKey('sla_label', $enriched[0]);
        $this->assertArrayHasKey('sla_deadline', $enriched[0]);
        $this->assertArrayHasKey('can_view', $enriched[0]);
        $this->assertEquals('entity_runtime', $enriched[0]['visibility_source']);
    }

    public function testFilterBreachedItemsWorksOnEnrichedData(): void
    {
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'daily_order',
                'status' => 'Open',
                'required_date' => '2020-01-01',
                'dedup_key' => 'daily_order:1',
                'section_key' => 'overdue_escalated',
                'urgency_rank' => 0,
            ],
            [
                'id' => 2,
                'entity_type' => 'daily_order',
                'status' => 'Open',
                'required_date' => '2026-12-31',
                'dedup_key' => 'daily_order:2',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $enriched = MyWorkEntityAdapter::enrichItems($items, $ctx);
        $breached = MyWorkEntityAdapter::filterBreachedItems($enriched);

        $this->assertCount(1, $breached);
        $this->assertEquals(1, $breached[0]['id']);
        $this->assertEquals('breached', $breached[0]['sla_state']);
    }

    public function testFilterVisibleItemsWorksOnEnrichedData(): void
    {
        $ctx = EntityContext::admin();

        $items = [
            [
                'id' => 1,
                'entity_type' => 'daily_order',
                'status' => 'Open',
                'required_date' => '2026-04-05',
                'can_view' => true,
                'visibility_source' => 'entity_runtime',
                'dedup_key' => 'daily_order:1',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
            [
                'id' => 2,
                'entity_type' => 'daily_order',
                'status' => 'Open',
                'required_date' => '2026-04-05',
                'can_view' => false,
                'visibility_source' => 'entity_runtime',
                'dedup_key' => 'daily_order:2',
                'section_key' => 'needs_action',
                'urgency_rank' => 9,
            ],
        ];

        $visible = MyWorkEntityAdapter::filterVisibleItems($items);

        $this->assertCount(1, $visible);
        $this->assertEquals(1, $visible[0]['id']);
    }
}
