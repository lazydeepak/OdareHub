<?php
declare(strict_types=1);

/**
 * OH-AUTO2-107 actual approved-provider loading proof.
 *
 * Reads version-controlled manifests and invokes only the six approved
 * navigation providers through the real Base registry with fake DB/policy
 * implementations. No application bootstrap or database connection occurs.
 */

namespace App\Core {
    final class DB
    {
        /** @var array<int,array<string,mixed>> */
        public static array $hooks = [];

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            return ['present' => 1];
        }

        /** @return array<int,array<string,mixed>> */
        public static function fetchAll(string $sql, array $params = []): array
        {
            return self::$hooks;
        }
    }
}

namespace {
    define('APP_ROOT', dirname(__DIR__, 3));

    function t(string $key, array $replace = []): string
    {
        return $key;
    }

    final class ActualProviderPolicy
    {
        public function isAppEnabled(string $appKey): bool
        {
            return in_array(strtolower($appKey), ['manufacturing', 'sbaio', 'studio'], true);
        }

        public function canAccessModule(?array $user, string $moduleKey): bool
        {
            return true;
        }

        public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array
        {
            return ['allowed' => true];
        }
    }

    $actualProviderPolicy = new ActualProviderPolicy();
    function platform_user_access_policy_contract(): ActualProviderPolicy
    {
        global $actualProviderPolicy;
        return $actualProviderPolicy;
    }

    $root = APP_ROOT;
    $rows = [];
    $approved = [];
    foreach (['Manufacturing' => 'manufacturing', 'SBAIO' => 'sbaio', 'Studio' => 'studio'] as $directory => $appKey) {
        $manifestPath = $root . '/apps/' . $directory . '/manifest.json';
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        foreach ((array)($manifest['host_provider_capabilities'] ?? []) as $descriptor) {
            if (!is_array($descriptor)) {
                continue;
            }
            $approved[] = $appKey . ':' . (string)($descriptor['key'] ?? '');
            $rows[] = [
                'app_key' => $appKey,
                'hook_key' => (string)$descriptor['key'],
                'install_path' => $root . '/apps/' . $directory,
                'payload_json' => json_encode([
                    'surface' => $descriptor['surface'],
                    'region' => $descriptor['region'],
                    'provider' => $descriptor['provider'],
                    'provider_file' => $descriptor['provider_file'],
                ], JSON_THROW_ON_ERROR),
            ];
        }
    }

    \App\Core\DB::$hooks = $rows;
    require_once $root . '/plugins/Base/Services/HostSurfaceRegistryService.php';

    $regions = \Plugins\Base\Services\HostSurfaceRegistryService::build('me', [
        'user' => ['id' => 7, 'authority_role' => 'platform_admin'],
        'context' => [
            'authority_role' => 'platform_admin',
            'active_assigned_apps' => ['manufacturing', 'sbaio', 'studio'],
        ],
    ]);

    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $assert(count($approved) === 7, 'six navigation plus one extracted aggregate descriptor exist');
    $headerApps = array_values(array_unique(array_map(
        static fn(array $item): string => (string)($item['app_key'] ?? ''),
        array_values(array_filter((array)($regions['header_actions'] ?? []), 'is_array'))
    )));
    $quickLinkApps = array_values(array_unique(array_map(
        static fn(array $item): string => (string)($item['app_key'] ?? ''),
        array_values(array_filter((array)($regions['quick_links'] ?? []), 'is_array'))
    )));
    $assert(count($headerApps) === 3, 'three approved header providers execute');
    $assert(count($quickLinkApps) === 3, 'three approved quick-link providers execute');
    $assert($headerApps === ['manufacturing', 'sbaio', 'studio'], 'existing assigned-app provider ordering is preserved');
    $assert(($regions['header_actions'][0]['app_key'] ?? '') !== '', 'approved output retains provider ownership');
    $assert(($regions['quick_links'][0]['region'] ?? '') === 'quick_links', 'approved output retains region identity');

    \App\Core\DB::$hooks = [[
        'app_key' => 'fixture_legacy',
        'hook_key' => 'fixture.approval',
        'install_path' => $root . '/apps/Manufacturing',
        'payload_json' => json_encode([
            'surface' => 'approval_inbox',
            'region' => 'header_actions',
            'provider' => 'Apps\\Manufacturing\\Services\\HostSurfaceContributionService::contribute',
            'provider_file' => 'Services/HostSurfaceContributionService.php',
        ], JSON_THROW_ON_ERROR),
    ]];
    $otherSurface = \Plugins\Base\Services\HostSurfaceRegistryService::build('approval_inbox', [
        'user' => ['id' => 7, 'authority_role' => 'platform_admin'],
        'context' => ['authority_role' => 'platform_admin', 'active_assigned_apps' => []],
    ]);
    $assert(($otherSurface['header_actions'][0]['widget_key'] ?? '') !== '', 'non-/me host surfaces retain existing execution path');

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        fwrite(STDERR, "{$assertions} assertions, " . count($failures) . " failures\n");
        exit(1);
    }

    echo "OH-AUTO2-107 approved provider inventory: {$assertions}/{$assertions} assertions passed\n";
}
