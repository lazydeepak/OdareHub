<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Apps\Shell\Services\OperatorRealtimeCacheManager;

/**
 * Operator Realtime Cache Manager Tests
 * 
 * Tests stale-while-revalidate caching behavior and invalidation.
 */
final class OperatorRealtimeCacheManagerTest extends TestCase
{
    private OperatorRealtimeCacheManager $cache;

    protected function setUp(): void
    {
        $this->cache = new OperatorRealtimeCacheManager();
        $this->cache->flush(); // Clear any previous test data
    }

    /**
     * Test setting and getting cache value
     */
    public function testSetAndGet(): void
    {
        $key = 'test:key:1';
        $value = ['count' => 42, 'label' => 'test'];

        $this->cache->set($key, $value);
        $cached = $this->cache->get($key);

        $this->assertNotNull($cached);
        $this->assertEquals(42, $cached['count']);
        $this->assertEquals('test', $cached['label']);
    }

    /**
     * Test cache miss returns null
     */
    public function testCacheMiss(): void
    {
        $this->assertNull($this->cache->get('nonexistent:key'));
    }

    /**
     * Test invalidate specific key
     */
    public function testInvalidateSpecificKey(): void
    {
        $this->cache->set('kpi:dashboard:user1:summary', ['count' => 10]);
        $this->cache->set('kpi:dashboard:user1:critical', ['count' => 5]);

        $this->assertNotNull($this->cache->get('kpi:dashboard:user1:summary'));
        $this->assertNotNull($this->cache->get('kpi:dashboard:user1:critical'));

        $this->cache->invalidate('user1', 'dashboard', 'summary');

        $this->assertNull($this->cache->get('kpi:dashboard:user1:summary'));
        $this->assertNotNull($this->cache->get('kpi:dashboard:user1:critical'));
    }

    /**
     * Test invalidate all KPIs for user/view
     */
    public function testInvalidateAllForUserView(): void
    {
        $this->cache->set('kpi:dashboard:user1:summary', ['count' => 10]);
        $this->cache->set('kpi:dashboard:user1:critical', ['count' => 5]);
        $this->cache->set('kpi:production:user1:summary', ['count' => 20]);

        $this->cache->invalidate('user1', 'dashboard');

        $this->assertNull($this->cache->get('kpi:dashboard:user1:summary'));
        $this->assertNull($this->cache->get('kpi:dashboard:user1:critical'));
        $this->assertNotNull($this->cache->get('kpi:production:user1:summary'));
    }

    /**
     * Test invalidate all for user across all views
     */
    public function testInvalidateForUser(): void
    {
        $this->cache->set('kpi:dashboard:user1:summary', ['count' => 10]);
        $this->cache->set('kpi:production:user1:summary', ['count' => 20]);
        $this->cache->set('kpi:dashboard:user2:summary', ['count' => 30]);

        $this->cache->invalidateForUser('user1');

        $this->assertNull($this->cache->get('kpi:dashboard:user1:summary'));
        $this->assertNull($this->cache->get('kpi:production:user1:summary'));
        $this->assertNotNull($this->cache->get('kpi:dashboard:user2:summary'));
    }

    /**
     * Test flush clears entire cache
     */
    public function testFlush(): void
    {
        $this->cache->set('kpi:dashboard:user1:summary', ['count' => 10]);
        $this->cache->set('kpi:dashboard:user2:summary', ['count' => 20]);

        $this->cache->flush();

        $this->assertNull($this->cache->get('kpi:dashboard:user1:summary'));
        $this->assertNull($this->cache->get('kpi:dashboard:user2:summary'));
    }

    /**
     * Test cache stats
     */
    public function testStats(): void
    {
        $this->cache->set('kpi:dashboard:user1:summary', ['count' => 10]);
        $this->cache->set('kpi:dashboard:user2:summary', ['count' => 20]);

        $stats = $this->cache->getStats();

        $this->assertArrayHasKey('backend', $stats);
        $this->assertArrayHasKey('entries', $stats);
        $this->assertArrayHasKey('connected', $stats);
        $this->assertGreaterThan(0, $stats['entries']);
    }

    /**
     * Test multiple values don't interfere
     */
    public function testMultipleValuesIsolation(): void
    {
        $this->cache->set('key1', ['id' => 1, 'value' => 'first']);
        $this->cache->set('key2', ['id' => 2, 'value' => 'second']);
        $this->cache->set('key3', ['id' => 3, 'value' => 'third']);

        $v1 = $this->cache->get('key1');
        $v2 = $this->cache->get('key2');
        $v3 = $this->cache->get('key3');

        $this->assertEquals(1, $v1['id']);
        $this->assertEquals(2, $v2['id']);
        $this->assertEquals(3, $v3['id']);
    }

    /**
     * Test different data types in cache
     */
    public function testDifferentDataTypes(): void
    {
        $testData = [
            'string_val' => 'hello',
            'int_val' => 42,
            'float_val' => 3.14,
            'bool_val' => true,
            'array_val' => [1, 2, 3],
            'nested_array' => [
                'level1' => ['level2' => 'value'],
            ],
        ];

        $this->cache->set('complex', $testData);
        $cached = $this->cache->get('complex');

        $this->assertEquals('hello', $cached['string_val']);
        $this->assertEquals(42, $cached['int_val']);
        $this->assertEqualsWithDelta(3.14, $cached['float_val'], 0.01);
        $this->assertTrue($cached['bool_val']);
        $this->assertEquals([1, 2, 3], $cached['array_val']);
        $this->assertEquals('value', $cached['nested_array']['level1']['level2']);
    }
}
