<?php
declare(strict_types=1);

/**
 * OH-AUTO1-023 mock-only regression probe.
 *
 * This probe invokes the validator in subprocesses. It never starts a
 * database, opens a socket, executes fixture SQL, or performs teardown.
 */

$root = dirname(__DIR__, 2);
$harness = $root . '/scripts/testing/disposable_db_harness.php';
$tmp = sys_get_temp_dir() . '/oh-auto-023-' . bin2hex(random_bytes(6));
if (!mkdir($tmp, 0700, true)) {
    fwrite(STDERR, "cannot create temporary probe directory\n");
    exit(2);
}

$baseProof = [
    'disposable' => true,
    'installed_database_access' => false,
    'production_network_access' => false,
    'production_storage_mount' => false,
    'teardown_owned_only' => true,
    'owned_by_harness' => true,
    'host' => '127.0.0.1',
    'database_name' => 'oh_auto_022_ephemeral_7f3a',
    'credential_source' => 'container-secret',
    'resource_id' => 'oh-auto-022-unique-resource-id',
];

$baseEnv = getenv();
foreach (['ERP_DB_HOST', 'ERP_DB_NAME', 'ERP_DB_USER', 'ERP_DB_PASS'] as $key) {
    unset($baseEnv[$key]);
}
$baseEnv += [
    'OH_TEST_DB_HOST' => '127.0.0.1',
    'OH_TEST_DB_PORT' => '43306',
    'OH_TEST_DB_NAME' => 'oh_auto_022_ephemeral_7f3a',
    'OH_TEST_DB_USER' => 'oh_test_runner',
    'OH_TEST_DB_ISOLATION_TOKEN' => '0123456789abcdef0123456789abcdef',
    'OH_TEST_DB_PROVISIONER' => 'docker-internal-network',
    'OH_TEST_DB_CREDENTIAL_SOURCE' => 'container-secret',
    'OH_TEST_DB_ATTESTATION_MODE' => 'mock',
    'OH_TEST_DB_RESOURCE_ID' => 'oh-auto-022-unique-resource-id',
];

$assertions = 0;
$failures = [];
$run = static function (array $proof, array $envOverrides = []) use ($harness, $tmp, $baseEnv): array {
    $path = $tmp . '/proof-' . bin2hex(random_bytes(4)) . '.json';
    file_put_contents($path, json_encode($proof, JSON_THROW_ON_ERROR));
    $env = array_merge($baseEnv, ['OH_TEST_DB_PROOF_FILE' => $path], $envOverrides);
    $pipes = [];
    $process = proc_open([PHP_BINARY, $harness], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $tmp, $env);
    if (!is_resource($process)) {
        throw new RuntimeException('could not invoke harness');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    @unlink($path);
    $decoded = json_decode($stdout, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('harness did not return JSON: ' . $stderr . $stdout);
    }
    $decoded['_exit'] = $exit;
    return $decoded;
};

$check = static function (bool $condition, string $label) use (&$assertions, &$failures): void {
    $assertions++;
    if (!$condition) {
        $failures[] = $label;
    }
};

try {
    $valid = $run($baseProof);
    $check($valid['_exit'] === 2, 'valid synthetic proof remains exit 2');
    $check(($valid['status'] ?? '') === 'MOCK_PROOF_VALIDATED_BUT_EXECUTION_BLOCKED', 'valid proof is mock-only blocked');
    $check(($valid['execution_authorized'] ?? true) === false, 'valid proof cannot authorize execution');
    $check(($valid['teardown_authorized'] ?? true) === false, 'valid proof cannot authorize teardown');

    $forged = $baseProof;
    $forged['execution_authorized'] = true;
    $result = $run($forged);
    $check($result['_exit'] === 2, 'forged execution claim remains blocked');
    $check(in_array('synthetic proof cannot assert execution authorization', $result['errors'] ?? [], true), 'forged execution claim rejected');

    $result = $run($baseProof, ['OH_TEST_DB_HOST' => '10.0.0.8']);
    $check($result['_exit'] === 2, 'hostile non-loopback host blocked');
    $check(in_array('database host is not an explicitly allowlisted loopback host', $result['errors'] ?? [], true), 'hostile host diagnostic present');

    $production = $baseProof;
    $production['database_name'] = 'erp_local';
    $result = $run($production, ['OH_TEST_DB_NAME' => 'erp_local']);
    $check(in_array('database name is production-like or installed-system-like', $result['errors'] ?? [], true), 'production-like name blocked');

    $unowned = $baseProof;
    unset($unowned['owned_by_harness']);
    $result = $run($unowned);
    $check(in_array('isolation proof owned_by_harness is not 1', $result['errors'] ?? [], true), 'missing resource ownership blocked');

    $result = $run($baseProof, ['OH_TEST_DB_CREDENTIAL_SOURCE' => 'installed-config']);
    $check(in_array('credential source is not an approved test-only source', $result['errors'] ?? [], true), 'installed credential source blocked');

    $result = $run($baseProof, ['ERP_DB_HOST' => '127.0.0.1']);
    $check(in_array('test target reuses installed configuration variable ERP_DB_HOST', $result['errors'] ?? [], true), 'installed environment configuration reuse blocked');

    $escaped = $baseProof;
    $escaped['teardown_resource_id'] = 'other-resource';
    $result = $run($escaped);
    $check(in_array('teardown resource identity escapes the proof resource', $result['errors'] ?? [], true), 'teardown escape blocked');

    $reused = $baseProof;
    $reused['resource_id'] = 'oh-auto-022-reused-resource';
    $result = $run($reused);
    $check(in_array('proof resource identity does not match OH_TEST_DB_RESOURCE_ID', $result['errors'] ?? [], true), 'reused or mismatched resource identity blocked');
} catch (Throwable $e) {
    $failures[] = 'probe exception: ' . $e->getMessage();
}

@rmdir($tmp);
echo json_encode([
    'assertions' => $assertions,
    'failures' => $failures,
    'sql_executed' => false,
    'database_connected' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit($failures === [] ? 0 : 1);
