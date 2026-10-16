<?php
declare(strict_types=1);

/**
 * OH-AUTO2-103 focused behavioral proof.
 *
 * This probe uses a fake registry database and temporary provider files. It
 * proves the Shell composition boundary returns before host registry discovery
 * for every non-admitted preflight state, while an explicitly admitted
 * unrestricted composition still invokes the existing registry path.
 */

namespace App\Core {
    final class DB
    {
        /** @var array<int,array<string,mixed>> */
        public static array $hooks = [];

        public static function fetchOne(string $sql, array $params = []): ?array
        {
            return ['exists' => 1];
        }

        /** @return array<int,array<string,mixed>> */
        public static function fetchAll(string $sql, array $params = []): array
        {
            return self::$hooks;
        }
    }
}

namespace {
    final class HostContainmentPolicy
    {
        public function isAppEnabled(string $appKey): bool { return $appKey === 'safe_app'; }
        public function canAccessModule(?array $user, string $moduleKey): bool { return true; }
        public function routeAccessDecision(?array $user, string $path, string $method = 'GET'): array { return ['allowed' => true]; }
    }

    $hostContainmentPolicy = new HostContainmentPolicy();
    function platform_user_access_policy_contract(): HostContainmentPolicy
    {
        global $hostContainmentPolicy;
        return $hostContainmentPolicy;
    }

    $root = sys_get_temp_dir() . '/oh-auto2-103-host-containment-' . bin2hex(random_bytes(5));
    mkdir($root . '/providers', 0700, true);
    $safeMarker = $root . '/safe.invoked';
    $failingMarker = $root . '/failing.invoked';

    $cleanup = static function () use ($root, $safeMarker, $failingMarker): void {
        foreach ([$root . '/providers/SafeProvider.php', $root . '/providers/FailingProvider.php', $root . '/manifest.json', $safeMarker, $failingMarker] as $path) {
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
    };

    file_put_contents($root . '/providers/SafeProvider.php', <<<'PHP'
<?php
namespace FixtureOHAuto2103;
final class SafeProvider
{
    public static function contribute(array $request): array
    {
        file_put_contents(__DIR__ . '/../safe.invoked', '1');
        return [['widget_key' => 'fixture_safe', 'label' => 'safe']];
    }
}
PHP);

    file_put_contents($root . '/manifest.json', json_encode([
        'id' => 'safe_app',
        'app_key' => 'safe_app',
        'host_provider_capabilities' => [
            [
                'key' => 'safe.me',
                'owner_app' => 'safe_app',
                'provider' => 'FixtureOHAuto2103\\SafeProvider::contribute',
                'provider_file' => 'providers/SafeProvider.php',
                'surface' => 'me',
                'region' => 'summary_cards',
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
                'key' => 'failing.me',
                'owner_app' => 'safe_app',
                'provider' => 'FixtureOHAuto2103\\FailingProvider::contribute',
                'provider_file' => 'providers/FailingProvider.php',
                'surface' => 'me',
                'region' => 'summary_cards',
                'required_app_authority' => 'existing_app_assignment',
                'effect' => 'read_only_navigation',
                'record_scope' => 'route_authority_only',
                'record_authorization' => 'route_authority',
                'output' => 'navigation_items',
                'failure_behavior' => 'suppress',
                'review' => ['static' => 'approved', 'isolated' => 'approved'],
                'enabled' => true,
            ],
        ],
    ], JSON_THROW_ON_ERROR));
    file_put_contents($root . '/providers/FailingProvider.php', <<<'PHP'
<?php
namespace FixtureOHAuto2103;
final class FailingProvider
{
    public static function contribute(array $request): array
    {
        file_put_contents(__DIR__ . '/../failing.invoked', '1');
        throw new \RuntimeException('fixture provider failure');
    }
}
PHP);

    define('APP_ROOT', $root);
    require_once dirname(__DIR__, 3) . '/plugins/Base/Services/' . 'HostSurface' . 'RegistryService.php';
    require_once dirname(__DIR__) . '/Composers/AdminSurfaceComposer.php';

    \App\Core\DB::$hooks = [
        [
            'app_key' => 'safe_app',
            'hook_key' => 'safe.me',
            'payload_json' => json_encode([
                'surface' => 'me',
                'region' => 'summary_cards',
                'provider' => 'FixtureOHAuto2103\\SafeProvider::contribute',
                'provider_file' => 'providers/SafeProvider.php',
            ], JSON_THROW_ON_ERROR),
            'install_path' => $root,
        ],
        [
            'app_key' => 'safe_app',
            'hook_key' => 'failing.me',
            'payload_json' => json_encode([
                'surface' => 'me',
                'region' => 'summary_cards',
                'provider' => 'FixtureOHAuto2103\\FailingProvider::contribute',
                'provider_file' => 'providers/FailingProvider.php',
            ], JSON_THROW_ON_ERROR),
            'install_path' => $root,
        ],
    ];

    $assertions = 0;
    $failures = [];
    $assert = static function (bool $condition, string $message) use (&$assertions, &$failures): void {
        $assertions++;
        if (!$condition) {
            $failures[] = $message;
        }
    };

    $composer = static function (bool $allowed, string $state) use ($assert): array {
        $reflection = new \ReflectionClass(\Apps\Shell\Composers\AdminSurfaceComposer::class);
        $instance = $reflection->newInstanceWithoutConstructor();
        foreach ([
            'user' => ['id' => 7, 'authority_role' => 'platform_admin'],
            'ctx' => ['_scope_preflight_state' => $state, 'authority_role' => 'platform_admin', 'active_assigned_apps' => ['safe_app']],
            'query' => [],
            'hostProvidersAllowed' => $allowed,
        ] as $property => $value) {
            $field = $reflection->getProperty($property);
            $field->setValue($instance, $value);
        }
        $method = $reflection->getMethod('safeHostRegions');
        return (array)$method->invoke($instance, []);
    };

    foreach (['restricted', 'malformed', 'unavailable', ''] as $state) {
        $regions = $composer(false, $state);
        $assert($regions === [], $state === '' ? 'missing preflight suppresses host regions' : $state . ' preflight suppresses host regions');
        $assert(!is_file($safeMarker), $state === '' ? 'missing preflight does not load safe provider' : $state . ' preflight does not load safe provider');
        $assert(!is_file($failingMarker), $state === '' ? 'missing preflight does not invoke failing provider' : $state . ' preflight does not invoke failing provider');
    }

    $regions = $composer(true, 'unrestricted');
    $assert(($regions['summary_cards'][0]['widget_key'] ?? '') === 'fixture_safe', 'unrestricted host output is preserved');
    $assert(is_file($safeMarker), 'unrestricted path invokes permitted provider');
    $assert(is_file($failingMarker), 'permitted provider exception path remains isolated and invoked');

    $cleanup();
    if ($failures !== []) {
        foreach ($failures as $failure) {
            fwrite(STDERR, "FAIL: {$failure}\n");
        }
        fwrite(STDERR, "{$assertions} assertions, " . count($failures) . " failures\n");
        exit(1);
    }

    echo "OH-AUTO2-103 host containment: {$assertions}/{$assertions} assertions passed\n";
}
