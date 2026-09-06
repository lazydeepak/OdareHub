<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\FilesystemArchiveProvider;
use Platform\Recovery\MySqlDumpProvider;
use Platform\Recovery\RecoveryPointService;
use Platform\Recovery\RecoveryProviderPreflightService;

$passed = 0;
$failed = 0;

function point_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function point_rm_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        point_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/susankhya-recovery-point-service-probe-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);

try {
    $fakeDump = $tmp . '/fake-mysqldump.php';
    file_put_contents($fakeDump, <<<'PHP'
#!/usr/bin/env php
<?php
$resultFile = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--result-file=')) {
        $resultFile = substr($arg, strlen('--result-file='));
    }
}
if ($resultFile === null) {
    exit(2);
}
file_put_contents($resultFile, "-- service probe dump\n");
exit(0);
PHP);
    chmod($fakeDump, 0700);

    $source = $tmp . '/source';
    $target = $tmp . '/target';
    mkdir($source . '/app', 0700, true);
    mkdir($target, 0700, true);
    file_put_contents($source . '/app/runtime.php', '<?php return true;');
    file_put_contents($source . '/composer.lock', '{}');

    $service = new RecoveryPointService(
        new RecoveryProviderPreflightService(static fn(): array => [
            'host' => '127.0.0.1',
            'name' => 'probe',
            'user' => 'probe',
            'pass' => 'do-not-leak',
            '_source' => 'probe',
        ], ['PATH' => dirname($fakeDump)], APP_ROOT),
        new MySqlDumpProvider($fakeDump),
        new FilesystemArchiveProvider()
    );

    $preflight = $service->preflight();
    point_assert(($preflight['database']['resolved'] ?? false) === true, 'orchestrator exposes preflight result');

    $create = $service->createRecoveryPoint(
        ['host' => '127.0.0.1', 'name' => 'probe', 'user' => 'probe', 'pass' => 'do-not-leak'],
        $source,
        $target,
        'probe',
        'manual',
        ['release_version' => '1.0.0', 'build_id' => 'git:probe'],
        ['app', 'composer.lock']
    );
    point_assert($create['ok'] === true, 'orchestrator creates disposable recovery point');
    point_assert(is_file($target . '/recovery-point.json'), 'orchestrator writes metadata in explicit target');
    point_assert(is_file($target . '/database.sql.gz'), 'orchestrator writes database payload in explicit target');
    point_assert(is_file($target . '/filesystem.zip'), 'orchestrator writes filesystem payload in explicit target');
    point_assert(!str_contains(json_encode($create, JSON_THROW_ON_ERROR), 'do-not-leak'), 'orchestrator result does not expose password');

    $verify = $service->verify($target);
    point_assert($verify['ok'] === true, 'orchestrator verifies created recovery point');

    file_put_contents($target . '/database.sql.gz', 'corrupt');
    $broken = $service->verify($target);
    point_assert($broken['ok'] === false, 'orchestrator detects corrupted payload');
    point_assert(!file_exists(APP_ROOT . '/storage/recovery-points'), 'orchestrator probe does not create production recovery storage');
} finally {
    point_rm_tree($tmp);
}

echo "Recovery point service probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
