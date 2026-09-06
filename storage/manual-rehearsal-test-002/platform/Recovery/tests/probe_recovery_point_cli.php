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

echo "Recovery point CLI probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
