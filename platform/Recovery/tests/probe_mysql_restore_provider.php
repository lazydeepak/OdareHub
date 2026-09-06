<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\MySqlRestoreProvider;

$passed = 0;
$failed = 0;

function mysql_restore_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function mysql_restore_rm_tree(string $path): void
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
        mysql_restore_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/susankhya-mysql-restore-provider-probe-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);

try {
    $fakeMysql = $tmp . '/fake-mysql.php';
    file_put_contents($fakeMysql, <<<'PHP'
#!/usr/bin/env php
<?php
$stdin = stream_get_contents(STDIN);
$record = [
    'argv' => $argv,
    'stdin_has_dump' => str_contains($stdin, 'restore provider probe'),
];
file_put_contents(dirname(__FILE__) . '/fake-mysql-records.jsonl', json_encode($record) . "\n", FILE_APPEND);
exit(0);
PHP);
    chmod($fakeMysql, 0700);

    $dump = $tmp . '/database.sql.gz';
    file_put_contents($dump, gzencode('-- restore provider probe'));

    $provider = new MySqlRestoreProvider($fakeMysql);
    $result = $provider->rehearse(
        $dump,
        ['host' => '127.0.0.1', 'user' => 'root', 'pass' => 'do-not-leak', 'port' => 3306],
        'probe_rehearsal',
        ['database' => 'source_db']
    );

    mysql_restore_assert($result['ok'] === true, 'fake mysql restore provider succeeds');
    mysql_restore_assert(!str_contains(json_encode($result, JSON_THROW_ON_ERROR), 'do-not-leak'), 'restore result does not expose password');
    $records = file($tmp . '/fake-mysql-records.jsonl', FILE_IGNORE_NEW_LINES) ?: [];
    mysql_restore_assert(count($records) === 2, 'restore provider invokes setup and restore commands');
    $last = json_decode((string)end($records), true);
    mysql_restore_assert(($last['stdin_has_dump'] ?? false) === true, 'restore command receives dump through stdin');
    mysql_restore_assert(count(glob($tmp . '/mysql-restore-defaults-*') ?: []) === 0, 'temporary restore defaults file is removed');

    $sameDb = $provider->rehearse($dump, [], 'source_db', ['database' => 'source_db']);
    mysql_restore_assert($sameDb['ok'] === false, 'restore provider refuses source database as rehearsal target');

    mysql_restore_assert(!file_exists(APP_ROOT . '/storage/recovery-points'), 'restore provider probe does not create production recovery storage');
} finally {
    mysql_restore_rm_tree($tmp);
}

echo "MySQL restore provider probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
