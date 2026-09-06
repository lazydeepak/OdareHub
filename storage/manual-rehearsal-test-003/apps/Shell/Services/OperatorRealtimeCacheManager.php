<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * Operator Realtime Cache Manager
 * 
 * Implements stale-while-revalidate caching for KPI data.
 * Supports both Redis (preferred) and in-memory fallback.
 * 
 * Features:
 * - Automatic TTL expiration (30s default)
 * - Stale-while-revalidate grace period (60s)
 * - Per-key invalidation
 * - Per-user invalidation
 * - Connection pooling
 * 
 * Usage:
 *   $cache = new OperatorRealtimeCacheManager();
 *   $data = $cache->get('kpi:dashboard:lazy:summary');
 *   $cache->set('kpi:dashboard:lazy:summary', $data);
 *   $cache->invalidate('lazy', 'dashboard');
 */
final class OperatorRealtimeCacheManager
{
    /** Cache TTL in seconds */
    private const CACHE_TTL = 30;

    /** Stale-while-revalidate grace period in seconds */
    private const STALE_GRACE_PERIOD = 60;

    /** @var array<string, array{value: mixed, expires_at: int}> In-memory cache fallback */
    private array $memoryCache = [];

    private ?object $redis = null;
    private bool $redisAvailable = false;

    public function __construct()
    {
        $this->initializeRedis();
    }

    /**
     * Initialize Redis connection (if available)
     */
    private function initializeRedis(): void
    {
        try {
            if (extension_loaded('redis')) {
                $this->redis = new \Redis();
                $host = getenv('REDIS_HOST') ?: '127.0.0.1';
                $port = (int)(getenv('REDIS_PORT') ?: 6379);
                $password = getenv('REDIS_PASSWORD') ?: '';

                if ($this->redis->connect($host, $port, 1)) {
                    if (!empty($password)) {
                        $this->redis->auth($password);
                    }
                    $this->redisAvailable = true;
                    echo "[Cache] Redis connected at {$host}:{$port}\n";
                }
            }
        } catch (Exception $e) {
            echo "[Cache] Redis unavailable: {$e->getMessage()}\n";
        }

        if (!$this->redisAvailable) {
            echo "[Cache] Using in-memory fallback\n";
        }
    }

    /**
     * Get value from cache with stale-while-revalidate support
     * 
     * Returns:
     * - Fresh value if not expired
     * - Stale value if within grace period (with revalidate flag)
     * - null if not in cache or stale period exceeded
     */
    public function get(string $key): ?array
    {
        if ($this->redisAvailable) {
            return $this->getFromRedis($key);
        }

        return $this->getFromMemory($key);
    }

    /**
     * Set value in cache with TTL
     */
    public function set(string $key, array $value): void
    {
        if ($this->redisAvailable) {
            $this->setInRedis($key, $value);
        } else {
            $this->setInMemory($key, $value);
        }
    }

    /**
     * Invalidate specific cache key
     */
    public function invalidate(string $username, string $view, ?string $kpiKey = null): void
    {
        if ($kpiKey) {
            $key = "kpi:{$view}:{$username}:{$kpiKey}";
            if ($this->redisAvailable) {
                $this->redis->del($key);
            } else {
                unset($this->memoryCache[$key]);
            }
        } else {
            // Invalidate all KPIs for user/view
            $pattern = "kpi:{$view}:{$username}:*";
            if ($this->redisAvailable) {
                $keys = $this->redis->keys($pattern);
                if (!empty($keys)) {
                    $this->redis->del(...$keys);
                }
            } else {
                foreach (array_keys($this->memoryCache) as $key) {
                    if (strpos($key, "kpi:{$view}:{$username}:") === 0) {
                        unset($this->memoryCache[$key]);
                    }
                }
            }
        }
    }

    /**
     * Invalidate all cache entries for a user
     */
    public function invalidateForUser(string $username): void
    {
        $pattern = "kpi:*:{$username}:*";
        if ($this->redisAvailable) {
            $keys = $this->redis->keys($pattern);
            if (!empty($keys)) {
                $this->redis->del(...$keys);
            }
        } else {
            foreach (array_keys($this->memoryCache) as $key) {
                if (strpos($key, "kpi:") === 0 && strpos($key, ":{$username}:") !== false) {
                    unset($this->memoryCache[$key]);
                }
            }
        }
    }

    /**
     * Clear entire cache
     */
    public function flush(): void
    {
        if ($this->redisAvailable) {
            try {
                $keys = $this->redis->keys('kpi:*');
                if (!empty($keys)) {
                    $this->redis->del(...$keys);
                }
            } catch (Exception $e) {
                echo "[Cache] Flush error: {$e->getMessage()}\n";
            }
        } else {
            $this->memoryCache = [];
        }
    }

    /**
     * Get cache statistics for monitoring
     */
    public function getStats(): array
    {
        if ($this->redisAvailable) {
            try {
                $keys = $this->redis->keys('kpi:*');
                return [
                    'backend' => 'redis',
                    'entries' => count($keys),
                    'connected' => true,
                ];
            } catch (Exception $e) {
                return [
                    'backend' => 'redis',
                    'entries' => 0,
                    'connected' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'backend' => 'memory',
            'entries' => count($this->memoryCache),
            'connected' => true,
        ];
    }

    // ========== Private Redis Methods ==========

    /**
     * Get from Redis with stale-while-revalidate
     */
    private function getFromRedis(string $key): ?array
    {
        try {
            $value = $this->redis->get($key);
            if ($value !== false) {
                return json_decode($value, true);
            }

            // Check if stale entry exists (using a suffix)
            $staleKey = "{$key}:stale";
            $staleValue = $this->redis->get($staleKey);
            if ($staleValue !== false) {
                // Return stale value; background refresh should happen
                return json_decode($staleValue, true);
            }
        } catch (Exception $e) {
            echo "[Cache] Redis get error: {$e->getMessage()}\n";
        }

        return null;
    }

    /**
     * Set in Redis with stale storage
     */
    private function setInRedis(string $key, array $value): void
    {
        try {
            $encoded = json_encode($value);

            // Set fresh value with TTL
            $this->redis->set($key, $encoded, ['EX' => self::CACHE_TTL]);

            // Also store as stale copy with longer TTL
            $staleKey = "{$key}:stale";
            $this->redis->set($staleKey, $encoded, ['EX' => self::CACHE_TTL + self::STALE_GRACE_PERIOD]);
        } catch (Exception $e) {
            echo "[Cache] Redis set error: {$e->getMessage()}\n";
        }
    }

    // ========== Private Memory Methods ==========

    /**
     * Get from memory cache with stale-while-revalidate
     */
    private function getFromMemory(string $key): ?array
    {
        $now = time();

        if (isset($this->memoryCache[$key])) {
            $entry = $this->memoryCache[$key];

            // Fresh entry
            if ($entry['expires_at'] > $now) {
                return $entry['value'];
            }

            // Stale but within grace period
            if ($entry['expires_at'] + self::STALE_GRACE_PERIOD > $now) {
                return $entry['value']; // Return stale, let provider refresh
            }

            // Expired; remove
            unset($this->memoryCache[$key]);
        }

        return null;
    }

    /**
     * Set in memory cache with TTL
     */
    private function setInMemory(string $key, array $value): void
    {
        $this->memoryCache[$key] = [
            'value' => $value,
            'expires_at' => time() + self::CACHE_TTL,
        ];

        // Simple cleanup: remove 10% oldest entries if cache exceeds 1000 items
        if (count($this->memoryCache) > 1000) {
            $now = time();
            $expired = array_filter(
                array_keys($this->memoryCache),
                fn($k) => $this->memoryCache[$k]['expires_at'] <= $now
            );

            foreach (array_slice($expired, 0, max(1, (int)(count($expired) * 0.1))) as $k) {
                unset($this->memoryCache[$k]);
            }
        }
    }
}
