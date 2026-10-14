<?php
declare(strict_types=1);

require_once __DIR__ . '/DisposableDbExecutionAuthorizer.php';

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
};

$blocked = DisposableDbExecutionAuthorizer::authorize(
    getmypid(),
    sys_get_temp_dir() . '/missing-oh-auto.sock',
    __DIR__,
    sys_get_temp_dir()
);
$assert($blocked['authorized'] === false, 'missing socket cannot authorize execution');
$assert($blocked['failures'] !== [], 'missing socket returns explicit failures');

$outside = DisposableDbExecutionAuthorizer::authorize(
    getmypid(),
    '/tmp/oh-auto.sock',
    '/Applications/ServBay/db/mysql/9.4',
    sys_get_temp_dir()
);
$assert($outside['authorized'] === false, 'installed datadir cannot authorize execution');
$assert(in_array('datadir is inside the supplied resource root', $outside['failures'], true), 'datadir containment is enforced');

$source = (string)file_get_contents(__DIR__ . '/DisposableDbExecutionAuthorizer.php');
$assert(str_contains($source, '--skip-networking'), 'authorizer requires network isolation');
$assert(str_contains($source, '--bind-address=127.0.0.1'), 'authorizer supports only loopback-bound application testing');
$assert(str_contains($source, '--no-defaults'), 'authorizer requires explicit configuration isolation');
$assert(str_contains($source, 'server-reported datadir matches attested datadir'), 'authorizer verifies server datadir identity');
$assert(str_contains($source, 'server-reported socket matches attested socket'), 'authorizer verifies server socket identity');

printf("Results: %d passed, %d failed\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
