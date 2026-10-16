<?php
declare(strict_types=1);

/**
 * OH-AUTO2-107 isolated /me admission proof.
 *
 * This probe uses fake registry rows, fake Platform policy, and temporary
 * provider/manifest files. It does not load application bootstrap or connect
 * to a database.
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

namespace OHAuto2107Fixture {
    final class Policy
    {
        public bool $enabled = true;
        public bool $routesAllowed = true;

        public function isAppEnabled(string $appKey): bool
        {
            return $this->enabled && $appKey === 'fixture_app';
        }

        public function canAccessModule(?array $user, string $moduleKey): bool
        {
            return true;
        }

        public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array
        {
            return ['allowed' => $this->routesAllowed, 'reason' => $this->routesAllowed ? 'fixture_allow' : 'fixture_deny'];
        }
    }
}

namespace {
    $root = sys_get_temp_dir() . '/oh-auto2-107-provider-' . bin2hex(random_bytes(5));
    mkdir($root . '/providers', 0700, true);
    $loadedMarker = $root . '/approved.loaded';
    $invokedMarker = $root . '/approved.invoked';
    $unknownMarker = $root . '/unknown.loaded';
    $escapeFile = $root . '/escape.php';

    $policy = new \OHAuto2107Fixture\Policy();
    function platform_user_access_policy_contract(): \OHAuto2107Fixture\Policy
    {
        global $policy;
        return $policy;
    }

    file_put_contents($root . '/providers/ApprovedProvider.php', <<<'PHP'
<?php
namespace {
    file_put_contents(__DIR__ . '/../approved.loaded', '1');
}
namespace OHAuto2107Fixture {
final class ApprovedProvider
{
    public static function contribute(array $request): array
    {
        file_put_contents(__DIR__ . '/../approved.invoked', '1');
        return [[
            'widget_key' => 'approved',
            'label' => 'Approved',
            'url' => '/apps/fixture/approved',
            'weight' => 1,
        ]];
    }
}
}
PHP);

    file_put_contents($root . '/providers/UnknownProvider.php', <<<'PHP'
<?php
namespace {
    file_put_contents(__DIR__ . '/../unknown.loaded', '1');
}
namespace OHAuto2107Fixture {
final class UnknownProvider
{
    public static function contribute(array $request): array
    {
        return [['widget_key' => 'unknown']];
    }
}
}
PHP);
    file_put_contents($escapeFile, "<?php file_put_contents('" . addslashes($root . '/escape.loaded') . "', '1');");

    file_put_contents($root . '/manifest.json', json_encode([
        'id' => 'fixture_app',
        'app_key' => 'fixture_app',
        'host_provider_capabilities' => [
            [
                'key' => 'fixture.me.approved',
                'owner_app' => 'fixture_app',
                'provider' => 'OHAuto2107Fixture\\ApprovedProvider::contribute',
                'provider_file' => 'providers/ApprovedProvider.php',
                'surface' => 'me',
                'region' => 'summary_cards',
                'required_modules' => [],
                'required_app_authority' => 'existing_app_assignment',
                'effect' => 'read_only_navigation',
                'record_scope' => 'route_authority_only',
                'record_authorization' => 'route_authority',
                'output' => 'navigation_items',
                'failure_behavior' => 'suppress',
                'review' => ['static' => 'approved', 'isolated' => 'approved'],
                'enabled' => true,
            ],
            [
                'key' => 'fixture.me.disabled',
                'owner_app' => 'fixture_app',
                'provider' => 'OHAuto2107Fixture\\ApprovedProvider::contribute',
                'provider_file' => 'providers/ApprovedProvider.php',
                'surface' => 'me',
                'region' => 'header_actions',
                'required_modules' => [],
                'required_app_authority' => 'existing_app_assignment',
                'effect' => 'read_only_navigation',
                'record_scope' => 'route_authority_only',
                'record_authorization' => 'route_authority',
                'output' => 'navigation_items',
                'failure_behavior' => 'suppress',
                'review' => ['static' => 'approved', 'isolated' => 'approved'],
                'enabled' => false,
            ],
        ],
    ], JSON_THROW_ON_ERROR));

    define('APP_ROOT', $root);
    require_once dirname(__DIR__) . '/../Platform/Services/HostProviderAdmissionService.php';
    require_once dirname(__DIR__, 3) . '/plugins/Base/Services/HostSurfaceRegistryService.php';

    $context = [
        'user' => ['id' => 7, 'authority_role' => 'platform_admin'],
        'context' => [
            'authority_role' => 'platform_admin',
            'active_assigned_apps' => ['fixture_app'],
        ],
    ];
    $provider = 'OHAuto2107Fixture\\ApprovedProvider::contribute';
    $baseRow = static fn(string $hookKey, string $payloadProvider = ''): array => [
        'app_key' => 'fixture_app',
        'hook_key' => $hookKey,
        'install_path' => $root,
        'payload_json' => json_encode([
            'surface' => 'me',
            'region' => 'summary_cards',
            'provider' => $payloadProvider !== '' ? $payloadProvider : $provider,
            'provider_file' => 'providers/ApprovedProvider.php',
        ], JSON_THROW_ON_ERROR),
    ];

    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    \App\Core\DB::$hooks = [$baseRow('fixture.me.approved')];
    $regions = \Plugins\Base\Services\HostSurfaceRegistryService::build('me', $context);
    $assert(($regions['summary_cards'][0]['widget_key'] ?? '') === 'approved', 'approved provider executes');
    $assert(is_file($loadedMarker), 'approved provider file loads');
    $assert(is_file($invokedMarker), 'approved provider callback invokes');

    @unlink($unknownMarker);
    \App\Core\DB::$hooks = [[
        'app_key' => 'fixture_app',
        'hook_key' => 'fixture.me.unknown',
        'install_path' => $root,
        'payload_json' => json_encode([
            'surface' => 'me',
            'region' => 'summary_cards',
            'provider' => 'OHAuto2107Fixture\\UnknownProvider::contribute',
            'provider_file' => 'providers/UnknownProvider.php',
        ], JSON_THROW_ON_ERROR),
    ]];
    $unknown = \Plugins\Base\Services\HostSurfaceRegistryService::build('me', $context);
    $assert($unknown === [], 'unknown callback is rejected');
    $assert(!is_file($unknownMarker), 'unknown callback is rejected before file loading');

    $escapeDescriptor = [
        'key' => 'fixture.me.escape',
        'owner_app' => 'fixture_app',
        'provider' => $provider,
        'provider_file' => '../escape.php',
        'surface' => 'me',
        'region' => 'summary_cards',
        'required_modules' => [],
        'required_app_authority' => 'existing_app_assignment',
        'effect' => 'read_only_navigation',
        'record_scope' => 'route_authority_only',
        'record_authorization' => 'route_authority',
        'output' => 'navigation_items',
        'failure_behavior' => 'suppress',
        'review' => ['static' => 'approved', 'isolated' => 'approved'],
        'enabled' => true,
    ];
    $admission = \Apps\Platform\Services\HostProviderAdmissionService::admit(
        ['app_key' => 'fixture_app', 'hook_key' => 'fixture.me.escape', 'install_path' => $root],
        [
            'surface' => 'me',
            'region' => 'summary_cards',
            'provider' => $provider,
            'provider_file' => '../escape.php',
        ],
        'me',
        $context
    );
    $assert(($admission['allowed'] ?? true) === false, 'path escape is rejected');

    $mismatch = \Apps\Platform\Services\HostProviderAdmissionService::admit(
        ['app_key' => 'fixture_app', 'hook_key' => 'fixture.me.approved', 'install_path' => $root],
        [
            'surface' => 'me',
            'region' => 'summary_cards',
            'provider' => 'OHAuto2107Fixture\\UnknownProvider::contribute',
            'provider_file' => 'providers/ApprovedProvider.php',
        ],
        'me',
        $context
    );
    $assert(($mismatch['allowed'] ?? true) === false, 'class/method mismatch is rejected');

    \App\Core\DB::$hooks = [$baseRow('fixture.me.disabled')];
    $disabled = \Plugins\Base\Services\HostSurfaceRegistryService::build('me', $context);
    $assert($disabled === [], 'disabled provider is fail-closed');

    $routePolicy = $policy;
    $routePolicy->routesAllowed = false;
    \App\Core\DB::$hooks = [$baseRow('fixture.me.approved')];
    $filtered = \Plugins\Base\Services\HostSurfaceRegistryService::build('me', $context);
    $assert(($filtered['summary_cards'][0]['url'] ?? '') === '', 'existing route filtering remains effective');

    foreach ([$root . '/providers/ApprovedProvider.php', $root . '/providers/UnknownProvider.php', $root . '/manifest.json', $escapeFile, $loadedMarker, $invokedMarker, $unknownMarker, $root . '/escape.loaded'] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    if (is_dir($root . '/providers')) {
        rmdir($root . '/providers');
    }
    if (is_dir($root)) {
        rmdir($root);
    }

    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        fwrite(STDERR, "{$assertions} assertions, " . count($failures) . " failures\n");
        exit(1);
    }

    echo "OH-AUTO2-107 host provider admission: {$assertions}/{$assertions} assertions passed\n";
}
