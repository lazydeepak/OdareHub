<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\Auth;

final class RateLimitService
{
    /**
     * Attempt to perform action within rate limit.
     * Returns true if action is allowed (within limit), false if rate limit exceeded.
     *
     * @param string $key Unique key identifying the rate limit bucket
     * @param int $limit Maximum attempts allowed within the window
     * @param int $windowSeconds Time window in seconds
     * @return bool True if action is allowed, false if rate limited
     */
    public static function attemptWithinLimit(string $key, int $limit, int $windowSeconds): bool
    {
        Auth::bootSession();

        $now = time();
        $bucketKey = 'rate_limit_' . $key;
        $bucket = $_SESSION[$bucketKey] ?? ['count' => 0, 'since' => $now];
        $since = (int)($bucket['since'] ?? $now);
        $count = (int)($bucket['count'] ?? 0);

        if (($now - $since) > $windowSeconds) {
            $since = $now;
            $count = 0;
        }

        if ($count >= $limit) {
            $_SESSION[$bucketKey] = ['count' => $count, 'since' => $since];
            return false;
        }

        $_SESSION[$bucketKey] = ['count' => $count + 1, 'since' => $since];
        return true;
    }
}
