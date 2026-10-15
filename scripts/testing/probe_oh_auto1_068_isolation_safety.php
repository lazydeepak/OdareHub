<?php
declare(strict_types=1);

/** OH-AUTO1-068 configuration and entry-point safety probe; no DB or SQL. */

require_once __DIR__ . '/DisposableDbTestConfigurationGuard.php';
require_once __DIR__ . '/DisposableDbTestEntryPointGuard.php';
require_once __DIR__ . '/DisposableDbExecutionAuthorizer.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$root = sys_get_temp_dir() . '/oh-auto1-068-safety-' . bin2hex(random_bytes(6));
$appRoot = $root . '/app-root';
$storage = $appRoot . '/storage';
$configPath = $storage . '/db_config.php';
mkdir($storage, 0700, true);
$repo = dirname(__DIR__, 2);
$identity = [
    'host' => '127.0.0.1',
    'port' => 43068,
    'name' => 'oh_test_068_safety',
    'user' => 'oh068_runner',
    'pass' => 'synthetic-only',
];
$writeConfig = static function (array $config) use ($configPath): void {
    file_put_contents($configPath, "<?php\nreturn " . var_export($config, true) . ";\n");
};
$input = static function () use ($repo, $appRoot, $root, $identity): array {
    return [
        'repo' => $repo,
        'app_root' => $appRoot,
        'resource_root' => $root,
        'host' => $identity['host'],
        'port' => $identity['port'],
        'name' => $identity['name'],
        'user' => $identity['user'],
    ];
};

try {
    $writeConfig($identity);
    putenv('ERP_DB_HOST=127.0.0.1');
    putenv('ERP_DB_PORT=43069');
    putenv('ERP_DB_NAME=oh_synthetic_ambient');
    putenv('ERP_DB_USER=oh_synthetic_ambient_user');
    putenv('ERP_DB_PASS=synthetic-ambient');
    define('APP_ROOT', $appRoot);
    require_once $repo . '/app/Core/helpers.php';
    $resolved = app_db_config();
    $check(is_array($resolved) && ($resolved['_source'] ?? null) === 'file', 'repository precedence defect reproduced with synthetic values only');
    $check(is_array($resolved) && ($resolved['port'] ?? null) === 43068 && ($resolved['name'] ?? null) === 'oh_test_068_safety', 'file identity wins over conflicting ambient values');
    foreach (['ERP_DB_HOST', 'ERP_DB_PORT', 'ERP_DB_NAME', 'ERP_DB_USER', 'ERP_DB_PASS'] as $key) {
        putenv($key);
    }

    $snapshot = DisposableDbTestConfigurationGuard::authorize($input());
    $check(isset($snapshot['config_hash']), 'temporary configuration identity is authorized');
    DisposableDbTestConfigurationGuard::assertUnchanged($snapshot);
    $check(true, 'unchanged temporary configuration remains authorized');

    $writeConfig(array_replace($identity, ['name' => 'oh_test_068_changed']));
    try {
        DisposableDbTestConfigurationGuard::assertUnchanged($snapshot);
        $check(false, 'configuration mutation is rejected');
    } catch (Throwable) {
        $check(true, 'configuration mutation is rejected');
    }
    $writeConfig($identity);

    try {
        DisposableDbTestConfigurationGuard::authorize(array_replace($input(), ['app_root' => $repo]));
        $check(false, 'repository APP_ROOT is rejected');
    } catch (Throwable) {
        $check(true, 'repository APP_ROOT is rejected');
    }

    unlink($configPath);
    try {
        DisposableDbTestConfigurationGuard::authorize($input());
        $check(false, 'missing temporary configuration is rejected');
    } catch (Throwable) {
        $check(true, 'missing temporary configuration is rejected');
    }
    $writeConfig($identity);

    putenv('ERP_DB_NAME=contradictory_ambient');
    try {
        DisposableDbTestConfigurationGuard::authorize($input());
        $check(false, 'contradictory ambient identity is rejected');
    } catch (Throwable) {
        $check(true, 'contradictory ambient identity is rejected');
    }
    putenv('ERP_DB_NAME');

    $incompleteRoot = $root . '/incomplete';
    mkdir($incompleteRoot . '/storage', 0700, true);
    file_put_contents($incompleteRoot . '/storage/db_config.php', "<?php return " . var_export($identity, true) . ";\n");
    putenv('OH068_REPO=' . $repo);
    putenv('OH068_APP_ROOT=' . $incompleteRoot);
    putenv('OH068_DB_RESOURCE_ROOT=' . $root);
    putenv('OH068_DB_PORT=43068');
    putenv('OH068_DB_NAME=' . $identity['name']);
    putenv('OH068_DB_USER=' . $identity['user']);
    putenv('OH068_DB_PID=999999');
    putenv('OH068_DB_SOCKET=' . $root . '/missing.sock');
    putenv('OH068_DB_DATADIR=' . $root . '/missing-data');
    try {
        DisposableDbTestEntryPointGuard::authorize('OH068', $repo);
        $check(false, 'incomplete temporary APP_ROOT is rejected before DB loading');
    } catch (Throwable) {
        $check(true, 'incomplete temporary APP_ROOT is rejected before DB loading');
    }

    $blocked = DisposableDbExecutionAuthorizer::authorize(999999, $root . '/missing.sock', $root . '/missing-data', $root);
    $check($blocked['authorized'] === false, 'unattested process/socket/datadir tuple is rejected');
    $installed = DisposableDbExecutionAuthorizer::authorize(999999, $root . '/missing.sock', '/Applications/ServBay/db/mysql/9.4', $root);
    $check($installed['authorized'] === false, 'installed datadir identity is rejected');
} finally {
    foreach (['ERP_DB_HOST', 'ERP_DB_PORT', 'ERP_DB_NAME', 'ERP_DB_USER', 'ERP_DB_PASS', 'OH068_REPO', 'OH068_APP_ROOT', 'OH068_DB_RESOURCE_ROOT', 'OH068_DB_PORT', 'OH068_DB_NAME', 'OH068_DB_USER', 'OH068_DB_PID', 'OH068_DB_SOCKET', 'OH068_DB_DATADIR'] as $key) {
        putenv($key);
    }
    $remove = static function (string $path) use (&$remove): void {
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        if (!is_dir($path)) { return; }
        foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) { $remove($entry->getPathname()); }
        @rmdir($path);
    };
    $remove($root);
}

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
