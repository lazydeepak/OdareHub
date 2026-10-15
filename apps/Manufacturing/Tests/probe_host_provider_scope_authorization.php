<?php
declare(strict_types=1);

/**
 * OH-AUTO2-105 provider-level authorization proof.
 *
 * This uses an in-memory policy and DB double. It proves provider admission is
 * decided before operational collection, while an authorized Manufacturing
 * summary still produces its established cards. SBAIO personnel/payroll host
 * summaries remain suppressed because no record-level contract is available.
 */

namespace App\Core {
    final class DB
    {
        public static int $fetchCalls = 0;
        public static int $writeCalls = 0;

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            self::$fetchCalls++;
            if (stripos($sql, 'installed_plugins') !== false) {
                return ['status' => 'inactive'];
            }
            if (stripos($sql, 'information_schema.tables') !== false) {
                return ['present' => 1];
            }
            return ['total' => 2, 'cnt' => 2];
        }

        /** @return array<int,array<string,mixed>> */
        public static function fetchAll(string $sql, array $params = []): array
        {
            self::$fetchCalls++;
            return [];
        }

        public static function query(string $sql, array $params = []): void
        {
            self::$writeCalls++;
        }
    }
}

namespace {
    define('APP_ROOT', dirname(__DIR__, 3));

    function t(string $key, array $replace = []): string
    {
        return $key;
    }

    final class HostScopeFakePolicy
    {
        public bool $allowed;
        public function __construct(bool $allowed)
        {
            $this->allowed = $allowed;
        }
        public function isAppEnabled(string $app): bool
        {
            return $this->allowed && in_array($app, ['manufacturing', 'sbaio'], true);
        }
        public function canAccessModule(?array $user, string $module): bool
        {
            return $this->allowed;
        }
        public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array
        {
            return ['allowed' => $this->allowed, 'reason' => $this->allowed ? 'fixture_allow' : 'fixture_deny'];
        }
    }

    $policy = new HostScopeFakePolicy(false);
    function platform_user_access_policy_contract(): HostScopeFakePolicy
    {
        global $policy;
        return $policy;
    }

    require_once dirname(__DIR__) . '/Services/HostSurfaceContributionService.php';
    require_once dirname(__DIR__, 2) . '/SBAIO/Services/HostSurfaceContributionService.php';

    use App\Core\DB;
    use Apps\Manufacturing\Services\HostSurfaceContributionService as ManufacturingHost;
    use Apps\SBAIO\Services\HostSurfaceContributionService as SbaioHost;

    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $request = [
        'surface' => 'me',
        'region' => 'summary_cards',
        'user' => ['id' => 7],
        'context' => ['active_assigned_apps' => ['manufacturing', 'sbaio']],
    ];

    DB::$fetchCalls = 0;
    DB::$writeCalls = 0;
    $manufacturingDenied = ManufacturingHost::contribute($request);
    $assert($manufacturingDenied === [], 'Manufacturing summary is suppressed without policy authority');
    $assert(DB::$fetchCalls === 0, 'Manufacturing denied summary performs no operational query');
    $assert(DB::$writeCalls === 0, 'Manufacturing denied summary performs no write');

    $request['region'] = 'monitoring_sections';
    $manufacturingMonitoringDenied = ManufacturingHost::contribute($request);
    $assert($manufacturingMonitoringDenied === [], 'Manufacturing monitoring is suppressed before provider loading');
    $assert(DB::$fetchCalls === 0, 'Manufacturing denied monitoring performs no query');

    $request['region'] = 'summary_cards';
    $sbaioDenied = SbaioHost::contribute($request);
    $assert($sbaioDenied === [], 'SBAIO personnel summary remains suppressed without record-level authority');
    $assert(DB::$fetchCalls === 0, 'SBAIO suppressed summary performs no query');

    $request['region'] = 'monitoring_sections';
    $sbaioMonitoringDenied = SbaioHost::contribute($request);
    $assert($sbaioMonitoringDenied === [], 'SBAIO personnel watchlist remains suppressed');
    $assert(DB::$fetchCalls === 0, 'SBAIO suppressed watchlist performs no query');

    // Existing policy authority preserves Manufacturing aggregate output.
    $policy->allowed = true;
    $request['region'] = 'summary_cards';
    $manufacturingAllowed = ManufacturingHost::contribute($request);
    $assert(is_array($manufacturingAllowed) && count($manufacturingAllowed) >= 3, 'authorized Manufacturing summary remains available');
    $assert(DB::$fetchCalls > 0, 'authorized Manufacturing summary collects established aggregates');
    $assert(DB::$writeCalls === 0, 'authorized host rendering remains read-only');

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        fwrite(STDERR, "{$assertions} assertions, " . count($failures) . " failures\n");
        exit(1);
    }

    echo "OH-AUTO2-105 host scope authorization: {$assertions}/{$assertions} assertions passed\n";
}
