<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\FilesystemArchiveProvider;
use Platform\Recovery\MySqlDumpProvider;

$passed = 0;
$failed = 0;

function backup_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function backup_rm_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path) ?: [];
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        backup_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/susankhya-recovery-provider-probe-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);

try {
    $fakeDump = $tmp . '/fake-mysqldump.php';
    file_put_contents($fakeDump, <<<'PHP'
#!/usr/bin/env php
<?php
$resultFile = null;
$defaultsFile = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--result-file=')) {
        $resultFile = substr($arg, strlen('--result-file='));
    }
    if (str_starts_with($arg, '--defaults-extra-file=')) {
        $defaultsFile = substr($arg, strlen('--defaults-extra-file='));
    }
}
if ($resultFile === null || $defaultsFile === null || !is_file($defaultsFile)) {
    fwrite(STDERR, "missing result/defaults never-leak-this\n");
    exit(2);
}
file_put_contents($resultFile, "-- disposable probe dump\nCREATE TABLE probe (id INT);\n");
exit(0);
PHP);
    chmod($fakeDump, 0700);

    $dumpTarget = $tmp . '/dump-target';
    mkdir($dumpTarget, 0700);
    $dump = (new MySqlDumpProvider($fakeDump))->createDump([
        'host' => '127.0.0.1',
        'name' => 'probe_db',
        'user' => 'probe_user',
        'pass' => 'never-leak-this',
        'port' => 3306,
        'charset' => 'utf8mb4',
    ], $dumpTarget);

    backup_assert($dump['ok'] === true, 'fake mysqldump provider succeeds');
    backup_assert(is_file($dumpTarget . '/database.sql.gz'), 'database dump payload is created in disposable target');
    backup_assert(($dump['payload']['sha256'] ?? '') === hash_file('sha256', $dumpTarget . '/database.sql.gz'), 'database dump checksum matches payload');
    backup_assert(($dump['identity']['database'] ?? '') === 'probe_db', 'database identity reports database name');
    backup_assert(!str_contains(json_encode($dump, JSON_THROW_ON_ERROR), 'never-leak-this'), 'database dump result does not expose password');
    backup_assert(count(glob($dumpTarget . '/mysql-defaults-*') ?: []) === 0, 'temporary defaults file is removed');
    backup_assert(gzdecode((string)file_get_contents($dumpTarget . '/database.sql.gz')) !== false, 'database dump is gzip-readable');
    $invalidPayload = (new MySqlDumpProvider($fakeDump))->createDump(['name' => 'probe_db'], $dumpTarget, '../escape.sql.gz');
    backup_assert($invalidPayload['ok'] === false, 'database dump rejects pathy payload name');

    $fixtureRoot = $tmp . '/fixture-root';
    mkdir($fixtureRoot . '/app', 0700, true);
    mkdir($fixtureRoot . '/storage/app_files', 0700, true);
    mkdir($fixtureRoot . '/storage/cache', 0700, true);
    file_put_contents($fixtureRoot . '/app/runtime.php', '<?php echo "ok";');
    file_put_contents($fixtureRoot . '/composer.lock', '{"packages":[]}');
    file_put_contents($fixtureRoot . '/storage/app_files/customer.txt', 'customer');
    file_put_contents($fixtureRoot . '/storage/cache/temp.txt', 'cache');
    @symlink($fixtureRoot . '/storage/app_files/customer.txt', $fixtureRoot . '/storage/app_files/customer-link.txt');

    $archiveTarget = $tmp . '/filesystem.zip';
    $archive = (new FilesystemArchiveProvider())->createArchive(
        $fixtureRoot,
        $archiveTarget,
        ['app', 'composer.lock', 'storage'],
        ['storage/cache']
    );

    backup_assert($archive['ok'] === true, 'filesystem archive provider succeeds');
    backup_assert(is_file($archiveTarget), 'filesystem archive is created in disposable target');
    backup_assert(($archive['payload']['sha256'] ?? '') === hash_file('sha256', $archiveTarget), 'filesystem archive checksum matches payload');
    backup_assert(is_string($archive['preservation_inventory_sha256'] ?? null) && strlen((string)$archive['preservation_inventory_sha256']) === 64, 'preservation inventory checksum is recorded');

    $zip = new ZipArchive();
    backup_assert($zip->open($archiveTarget) === true, 'filesystem archive opens');
    backup_assert($zip->locateName('app/runtime.php') !== false, 'archive includes runtime file');
    backup_assert($zip->locateName('composer.lock') !== false, 'archive includes root file');
    backup_assert($zip->locateName('storage/app_files/customer.txt') !== false, 'archive includes customer file');
    backup_assert($zip->locateName('storage/cache/temp.txt') === false, 'archive excludes declared reproducible cache');
    backup_assert($zip->locateName('storage/app_files/customer-link.txt') === false, 'archive excludes symlink file');
    $zip->close();

    backup_assert(!file_exists(APP_ROOT . '/storage/recovery-points'), 'providers do not create production recovery storage');
} finally {
    backup_rm_tree($tmp);
}

echo "Recovery backup provider probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
