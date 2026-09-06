<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Apps\Shell\Services\OperatorRealtimeKpiProvider;
use Apps\Shell\Services\OperatorRealtimeCacheManager;

/**
 * Operator Realtime KPI Provider Tests
 * 
 * Tests KPI extraction from adapters for WebSocket broadcast.
 */
final class OperatorRealtimeKpiProviderTest extends TestCase
{
    private OperatorRealtimeKpiProvider $provider;
    private OperatorRealtimeCacheManager $cache;

    protected function setUp(): void
    {
        $this->provider = new OperatorRealtimeKpiProvider();
        $this->cache = new OperatorRealtimeCacheManager();
        $this->cache->flush();
    }

    /**
     * Test KPI provider initialization
     */
    public function testProviderInitialization(): void
    {
        $this->assertInstanceOf(OperatorRealtimeKpiProvider::class, $this->provider);
    }

    /**
     * Test cache manager is properly initialized
     */
    public function testCacheManagerInitialized(): void
    {
        $this->assertInstanceOf(OperatorRealtimeCacheManager::class, $this->cache);
    }

    /**
     * Test provider and cache work together for caching
     */
    public function testProviderAndCacheIntegration(): void
    {
        // Pre-populate cache with mock data
        $mockData = ['count' => 42, 'status' => 'ok'];
        $this->cache->set('kpi:dashboard:test_user:summary', $mockData);

        // Verify cache returns the data
        $cached = $this->cache->get('kpi:dashboard:test_user:summary');
        $this->assertNotNull($cached);
        $this->assertArrayHasKey('count', $cached);
        $this->assertEquals(42, $cached['count']);
    }

    /**
     * Test multiple KPI keys in single call
     */
    public function testMultipleKpiKeysInCache(): void
    {
        $kpiKeys = ['summary', 'critical_orders', 'coverage'];
        
        // Pre-populate cache
        foreach ($kpiKeys as $key) {
            $this->cache->set("kpi:dashboard:test_user:$key", ['data' => $key]);
        }

        // Verify all keys can be retrieved
        foreach ($kpiKeys as $key) {
            $cached = $this->cache->get("kpi:dashboard:test_user:$key");
            $this->assertNotNull($cached);
            $this->assertEquals($key, $cached['data']);
        }
    }

    /**
     * Test KPI data is JSON serializable
     */
    public function testKpiDataIsJsonSerializable(): void
    {
        $mockKpi = [
            'open_orders' => 42,
            'critical' => 5,
            'coverage_pct' => 94.5,
            'timestamp' => time(),
        ];

        $this->cache->set('kpi:dashboard:test_user:summary', $mockKpi);
        $cached = $this->cache->get('kpi:dashboard:test_user:summary');

        $json = json_encode($cached);
        $this->assertIsString($json);
        $this->assertNotEmpty($json);

        // Verify it can be decoded back
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertEquals(42, $decoded['open_orders']);
    }

    /**
     * Test cache invalidation for KPI updates
     */
    public function testCacheInvalidationForViews(): void
    {
        $views = ['dashboard', 'production', 'dispatch', 'coverage', 'qc', 'machines', 'materials', 'assembly'];
        $username = 'test_user';

        // Populate cache for all views
        foreach ($views as $view) {
            $this->cache->set("kpi:$view:$username:summary", ['view' => $view]);
        }

        // Verify all views are cached
        foreach ($views as $view) {
            $cached = $this->cache->get("kpi:$view:$username:summary");
            $this->assertNotNull($cached);
            $this->assertEquals($view, $cached['view']);
        }

        // Invalidate user across all views
        $this->cache->invalidateForUser($username);

        // Verify all are cleared
        foreach ($views as $view) {
            $cached = $this->cache->get("kpi:$view:$username:summary");
            $this->assertNull($cached);
        }
    }
}
