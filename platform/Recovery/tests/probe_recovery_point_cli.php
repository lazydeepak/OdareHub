<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

$passed = 0;
$failed = 0;

function cli_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

$output = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' scripts/system/recovery_point.php preflight 2>&1', $output, $code);
$json = json_decode(implode("\n", $output), true);
cli_assert(is_array($json), 'preflight CLI prints JSON');
cli_assert(array_key_exists('ok', $json ?? []), 'preflight CLI JSON includes ok field');

$output = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' scripts/system/recovery_point.php create 2>&1', $output, $code);
cli_assert($code === 2, 'create without target-dir fails safely');
cli_assert(!file_exists(APP_ROOT . '/storage/recovery-points'), 'CLI probe does not create production recovery storage');
cli_assert(!str_contains(implode("\n", $output), 'rehearsal.json'), 'create failure does not write rehearsal output');

$tmp = sys_get_temp_dir() . '/odarehub-recovery-point-cli-probe-' . bin2hex(random_bytes(6));
mkdir($tmp . '/target', 0700, true);
mkdir($tmp . '/isolated', 0700, true);
file_put_contents($tmp . '/target/database.sql.gz', gzencode('-- cli rehearsal'));
$zip = new ZipArchive();
$zip->open($tmp . '/target/filesystem.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);
$zip->addFromString('app/bootstrap.php', '<?php return true;');
$zip->close();
$hash = str_repeat('a', 64);
$metadata = [
    'schema_version' => 'odarehub.recovery-point.v1',
    'recovery_point_id' => 'rp-cli-probe',
    'created_at' => '2026-08-19T00:00:00Z',
    'created_by' => 'probe',
    'reason' => 'manual',
    'installation' => [
        'product_id' => 'odarehub',
        'release_version' => '1.0.0',
        'build_id' => 'git:probe',
        'app_manifest_checksums' => [],
        'module_manifest_checksums' => [],
        'schema_state' => [],
        'migration_state' => [],
    ],
    'database' => [
        'provider' => 'mysqldump',
        'provider_version' => 'probe',
        'identity' => ['host_label' => '127.0.0.1', 'port' => 3306, 'database' => 'source_db'],
        'payload' => [
            'path' => 'database.sql.gz',
            'sha256' => hash_file('sha256', $tmp . '/target/database.sql.gz'),
            'size_bytes' => filesize($tmp . '/target/database.sql.gz'),
        ],
    ],
    'filesystem' => [
        'provider' => 'ziparchive',
        'payload' => [
            'path' => 'filesystem.zip',
            'sha256' => hash_file('sha256', $tmp . '/target/filesystem.zip'),
            'size_bytes' => filesize($tmp . '/target/filesystem.zip'),
        ],
        'preservation_inventory_sha256' => $hash,
    ],
];
file_put_contents($tmp . '/target/recovery-point.json', json_encode($metadata, JSON_PRETTY_PRINT));

$fakeMysqlDir = $tmp . '/bin';
mkdir($fakeMysqlDir, 0700, true);
$fakeMysql = $fakeMysqlDir . '/mysql';
file_put_contents($fakeMysql, "#!/usr/bin/env php\n<?php stream_get_contents(STDIN); exit(0);\n");
chmod($fakeMysql, 0700);

$oldPath = getenv('PATH') ?: '';
putenv('PATH=' . $fakeMysqlDir . PATH_SEPARATOR . $oldPath);
$output = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' scripts/system/recovery_point.php rehearse --target-dir=' . escapeshellarg($tmp . '/target') . ' --isolated-dir=' . escapeshellarg($tmp . '/isolated') . ' --rehearsal-db=cli_rehearsal 2>&1', $output, $code);
putenv('PATH=' . $oldPath);
$rehearsal = json_decode(implode("\n", $output), true);
cli_assert($code === 0, 'rehearse CLI succeeds with fake mysql client');
cli_assert(is_file($tmp . '/target/rehearsal.json'), 'rehearse CLI writes rehearsal evidence');
cli_assert(($rehearsal['filesystem']['entry_count'] ?? 0) === 1, 'rehearse CLI output is compact');
cli_assert(!str_contains(implode("\n", $output), 'sample_entries'), 'rehearse CLI output omits entry list');
cli_rm_tree($tmp);

echo "Recovery point CLI probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);

function cli_rm_tree(string $path): void
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
        cli_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}
