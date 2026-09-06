<?php
declare(strict_types=1);

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Stubs.php';

use PHPUnit\Framework\TestCase;
use App\Core\MyWorkEntityQuery;

final class MyWorkEntityQueryTest extends TestCase
{
    public function testEntityConstantsAreDefined(): void
    {
        $this->assertEquals('daily_order', MyWorkEntityQuery::ENTITY_DAILY_ORDER);
        $this->assertEquals('production_entry', MyWorkEntityQuery::ENTITY_PRODUCTION_ENTRY);
        $this->assertEquals('production_plan', MyWorkEntityQuery::ENTITY_PRODUCTION_PLAN);
    }

    public function testFetchDailyOrdersReturnsArray(): void
    {
        $result = MyWorkEntityQuery::fetchDailyOrders(['limit' => 10]);
        $this->assertIsArray($result);
    }

    public function testFetchProductionEntriesReturnsArray(): void
    {
        $result = MyWorkEntityQuery::fetchProductionEntries(['limit' => 10]);
        $this->assertIsArray($result);
    }

    public function testFetchProductionPlansReturnsArray(): void
    {
        $result = MyWorkEntityQuery::fetchProductionPlans(['limit' => 10]);
        $this->assertIsArray($result);
    }

    public function testFetchAllMyWorkEntitiesReturnsArray(): void
    {
        $result = MyWorkEntityQuery::fetchAllMyWorkEntities(['limit' => 10]);
        $this->assertIsArray($result);
    }

    public function testOptionsArePassed(): void
    {
        $dailyOrders = MyWorkEntityQuery::fetchDailyOrders(['limit' => 5]);
        $this->assertLessThanOrEqual(5, count($dailyOrders));
    }
}
