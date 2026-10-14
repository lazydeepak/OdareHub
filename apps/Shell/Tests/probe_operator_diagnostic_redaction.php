<?php
declare(strict_types=1);

/**
 * OH-AUTO1-019/OH-AUTO1-038 focused, read-only regression probe.
 *
 * Verifies that operator read-surface failures retain server-side diagnostic
 * logging but expose only stable localization keys to operator views. This
 * probe does not bootstrap the application or connect to the database.
 */

$root = dirname(__DIR__, 3);
$autoload = $root . '/vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}
$passes = 0;
$failures = [];

$assert = static function (bool $condition, string $label) use (&$passes, &$failures): void {
    if ($condition) {
        $passes++;
        echo "PASS {$label}\n";
        return;
    }
    $failures[] = $label;
    echo "FAIL {$label}\n";
};

$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . ltrim($relative, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
};

$composer = $read('apps/Shell/Composers/OperatorSurfaceComposer.php');
$assert($composer !== '', 'operator surface composer is readable');
$assert(!preg_match("/'error'\s*=>\s*\$e->getMessage\(\)/", $composer), 'composer does not return raw exception messages');
$assert(str_contains($composer, "'error' => 'operator.common.data_unavailable'"), 'composer uses stable diagnostic error key');
$assert(substr_count($composer, "error_log('OperatorSurfaceComposer") >= 10, 'composer retains server-side failure logging');

$cache = $read('apps/Shell/Services/OperatorRealtimeCacheManager.php');
$assert($cache !== '', 'realtime cache manager is readable');
$assert(!str_contains($cache, "'error' => \$e->getMessage()"), 'cache status does not return raw exception messages');
$assert(str_contains($cache, "'error' => 'cache_unavailable'"), 'cache status uses stable diagnostic error key');
$assert(!preg_match('/echo .*\\$e->getMessage\(\)/', $cache), 'cache manager does not echo raw exception messages');
$assert(str_contains($cache, 'error_log('), 'cache manager retains server-side failure logging');

$realtime = $read('apps/Shell/Services/OperatorRealtimeService.php');
$assert($realtime !== '', 'realtime service is readable');
$assert(!preg_match('/echo .*\\$e->getMessage\(\)/', $realtime), 'realtime service does not echo raw exception messages');
$assert(substr_count($realtime, 'error_log(') >= 4, 'realtime service retains server-side failure logging');
$assert(str_contains($realtime, 'Auth::isLoggedIn()'), 'realtime service uses authenticated session authority');
$assert(str_contains($realtime, 'hash_equals(Auth::csrfToken(), $token)'), 'realtime service verifies the session CSRF token');
$assert(str_contains($realtime, 'routeAccessDecision('), 'realtime service enforces operator route authorization');
$assert(str_contains($realtime, "getHeader('Cookie')"), 'realtime service receives the browser session cookie');
$assert(str_contains($realtime, 'session_write_close()'), 'realtime service closes validated sessions');
$assert(!str_contains($realtime, 'return !empty($token) && !empty($username)'), 'realtime service has no non-empty-credential fail-open');
$assert(substr_count($realtime, 'isConnectionAuthorized(') >= 3, 'realtime service revalidates before subscription and KPI delivery');

$realtimeReflection = new ReflectionClass(\Apps\Shell\Services\OperatorRealtimeService::class);
$realtimeFixture = $realtimeReflection->newInstanceWithoutConstructor();
$fixtureCase = 'valid';
$fixtureResults = [
    'valid' => true,
    'fabricated_token' => false,
    'identity_mismatch' => false,
    'expired_session' => false,
    'revoked_session' => false,
    'unauthorized_operator' => false,
    'unauthorized_subscription' => false,
    'missing_backend' => false,
];
$fixtureAuthorizer = static function (string $token, string $username, string $view, string $cookie) use (&$fixtureCase, $fixtureResults): bool {
    return ($fixtureResults[$fixtureCase] ?? false)
        && $token === 'valid-token'
        && $username === 'alice'
        && $view === 'dashboard'
        && $cookie === 'PHPSESSID=valid';
};
$authorizerProperty = $realtimeReflection->getProperty('connectionAuthorizer');
$authorizerProperty->setValue($realtimeFixture, $fixtureAuthorizer);
$validationMethod = $realtimeReflection->getMethod('validateConnection');
foreach ($fixtureResults as $case => $expected) {
    $fixtureCase = $case;
    $actual = $validationMethod->invoke($realtimeFixture, 'valid-token', 'alice', 'dashboard', 'PHPSESSID=valid');
    $assert($actual === $expected, 'realtime authentication fixture: ' . $case);
}
$fixtureCase = 'fabricated_token';
$assert($validationMethod->invoke($realtimeFixture, 'forged', 'alice', 'dashboard', 'PHPSESSID=valid') === false, 'fabricated token is rejected by fixture');
$fixtureCase = 'identity_mismatch';
$assert($validationMethod->invoke($realtimeFixture, 'valid-token', 'bob', 'dashboard', 'PHPSESSID=valid') === false, 'identity mismatch is rejected by fixture');
$realValidationFixture = $realtimeReflection->newInstanceWithoutConstructor();
$assert($validationMethod->invoke($realValidationFixture, 'arbitrary', 'alice', 'dashboard', '') === false, 'missing session authority fails closed before database access');

$preferences = $read('apps/Shell/Views/operator/preferences.php');
$assert($preferences !== '', 'operator preferences view is readable');
$assert(!str_contains($preferences, '${error.message}'), 'realtime test does not render browser exception details');

$realtimeClient = $read('public/assets/operator-realtime.js');
$assert($realtimeClient !== '', 'realtime client is readable');
$assert(str_contains($realtimeClient, 'Do not emit the credential-bearing URL'), 'realtime client documents credential-bearing URL redaction');
$assert(!str_contains($realtimeClient, 'Connecting to ${wsUrl}'), 'realtime client does not log the credential-bearing WebSocket URL');

$realtimeBootstrap = $read('bin/operator-realtime-server');
$assert($realtimeBootstrap !== '', 'realtime bootstrap is readable');
$assert(str_contains($realtimeBootstrap, 'new HttpServer($wsServer)'), 'realtime bootstrap handshake wrapper is identified');
$assert(!str_contains($realtimeBootstrap, 'OriginCheck'), 'probe records that no trusted-origin middleware is currently active');
$assert(str_contains($realtimeBootstrap, 'new WsServer($realtimeService)'), 'realtime bootstrap uses the expected Ratchet server');
$assert(str_contains($realtime, "getHeader('Cookie')"), 'realtime authentication still receives session cookies');

$cacheReflection = new ReflectionClass(\Apps\Shell\Services\OperatorRealtimeCacheManager::class);
$cacheManager = $cacheReflection->newInstanceWithoutConstructor();
$throwingRedis = new class {
    public function keys(string $pattern): array
    {
        throw new RuntimeException('redis://internal-host:6379 secret=fixture');
    }
};
$redisProperty = $cacheReflection->getProperty('redis');
$redisProperty->setValue($cacheManager, $throwingRedis);
$availableProperty = $cacheReflection->getProperty('redisAvailable');
$availableProperty->setValue($cacheManager, true);
$safeStats = $cacheManager->getStats();
$assert(($safeStats['error'] ?? null) === 'cache_unavailable', 'cache failure fixture returns stable error key');
$assert(!str_contains((string)($safeStats['error'] ?? ''), 'internal-host'), 'cache failure fixture does not expose internal details');

$adapterFiles = [
    'AssemblyAdapter.php',
    'CoverageAdapter.php',
    'DispatchAdapter.php',
    'MachinesAdapter.php',
    'MaterialsAdapter.php',
    'QcAdapter.php',
];
foreach ($adapterFiles as $file) {
    $source = $read('apps/Manufacturing/Services/OperatorLayerAdapters/' . $file);
    $assert($source !== '', $file . ' is readable');
    $assert(!str_contains($source, "['error'] = \$e->getMessage()"), $file . ' does not return raw exception messages');
    $assert(str_contains($source, "['error'] = 'operator.common.data_unavailable'"), $file . ' returns stable diagnostic error key');
    $assert(str_contains($source, "error_log('"), $file . ' retains server-side failure logging');
}

$viewFiles = [
    'materials.php' => '$matError',
    'notifications.php' => '$ntfError',
    'messages.php' => '$msgError',
    'tasks.php' => '$tdError',
    'fulfillment.php' => '$preparationError',
    'processing.php' => '$processingError',
    'production.php' => '$production[\'error\']',
];
foreach ($viewFiles as $file => $errorVariable) {
    $view = $read('apps/Shell/Views/operator/' . $file);
    $assert($view !== '', $file . ' is readable');
    $assert(str_contains($view, '$this->tr('), $file . ' translates operator failure messages');
}

foreach (['en', 'ja', 'ne'] as $locale) {
    $path = 'apps/Shell/Resources/lang/' . $locale . '.php';
    $payload = $read($path);
    $assert($payload !== '', $path . ' is readable');
    $translations = is_file($root . '/' . $path) ? require $root . '/' . $path : [];
    $assert(is_array($translations), $locale . ' locale returns an array');
    $assert(trim((string)($translations['operator.common.data_unavailable'] ?? '')) !== '', $locale . ' has data-unavailable translation');
}

$total = $passes + count($failures);
echo "SUMMARY {$passes}/{$total} passed\n";
if ($failures !== []) {
    echo "FAILURES:\n";
    foreach ($failures as $failure) {
        echo "- {$failure}\n";
    }
    exit(1);
}

exit(0);
