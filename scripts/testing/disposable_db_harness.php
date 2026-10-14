<?php
declare(strict_types=1);

/**
 * OH-AUTO1-022 disposable database safety harness.
 *
 * This slice is deliberately non-executing. It validates the proof packet a
 * future DB runner may inspect. This command is mock/proof validation only:
 * it can never authorize a connection, SQL execution, fixture load, or
 * teardown. Missing, ambiguous, production-like, or manually supplied proof
 * fails closed.
 */

$root = dirname(__DIR__, 2);
$fixturePath = $root . '/scripts/testing/disposable_db_fixture.sql';
$proofPath = getenv('OH_TEST_DB_PROOF_FILE') ?: '';
$requiredEnv = [
    'OH_TEST_DB_HOST',
    'OH_TEST_DB_PORT',
    'OH_TEST_DB_NAME',
    'OH_TEST_DB_USER',
    'OH_TEST_DB_ISOLATION_TOKEN',
    'OH_TEST_DB_PROVISIONER',
    'OH_TEST_DB_CREDENTIAL_SOURCE',
    'OH_TEST_DB_ATTESTATION_MODE',
    'OH_TEST_DB_RESOURCE_ID',
];

$errors = [];
$warnings = [];

$env = static function (string $key): string {
    $value = getenv($key);
    return $value === false ? '' : trim((string)$value);
};

foreach ($requiredEnv as $key) {
    if ($env($key) === '') {
        $errors[] = "missing required proof variable {$key}";
    }
}

$host = $env('OH_TEST_DB_HOST');
$port = $env('OH_TEST_DB_PORT');
$name = $env('OH_TEST_DB_NAME');
$user = $env('OH_TEST_DB_USER');
$token = $env('OH_TEST_DB_ISOLATION_TOKEN');
$provisioner = $env('OH_TEST_DB_PROVISIONER');
$credentialSource = $env('OH_TEST_DB_CREDENTIAL_SOURCE');
$attestationMode = $env('OH_TEST_DB_ATTESTATION_MODE');
$resourceId = $env('OH_TEST_DB_RESOURCE_ID');

if ($host !== '' && !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    $errors[] = 'database host is not an explicitly allowlisted loopback host';
}
if ($port !== '' && (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535)) {
    $errors[] = 'database port is invalid';
}
if ($name !== '' && preg_match('/(?:^|[_-])(erp|odarehub|susankhya|production|prod|live)(?:$|[_-])/i', $name)) {
    $errors[] = 'database name is production-like or installed-system-like';
}
if ($user !== '' && in_array(strtolower($user), ['root', 'admin', 'odarehub', 'susankhya'], true)) {
    $errors[] = 'database user is not a test-only identity';
}
if ($token !== '' && !preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
    $errors[] = 'isolation token is not a high-entropy hexadecimal proof token';
}
if ($provisioner !== '' && !in_array($provisioner, ['docker-internal-network', 'ephemeral-vm', 'approved-local-test-instance'], true)) {
    $errors[] = 'provisioner is not an approved isolated mechanism';
}
if ($credentialSource !== '' && !in_array($credentialSource, ['container-secret', 'ephemeral-provisioner-secret', 'approved-secret-store'], true)) {
    $errors[] = 'credential source is not an approved test-only source';
}
if ($attestationMode !== 'mock') {
    $errors[] = 'only mock attestation input is accepted by this non-executing validator';
}
if ($resourceId !== '' && !preg_match('/^oh-auto-022-[a-z0-9][a-z0-9._-]{7,127}$/', $resourceId)) {
    $errors[] = 'resource identity is not a harness-owned test identity';
}

foreach (['ERP_DB_HOST' => $host, 'ERP_DB_NAME' => $name, 'ERP_DB_USER' => $user] as $installedKey => $testValue) {
    $installedValue = $env($installedKey);
    if ($installedValue !== '' && $testValue !== '' && hash_equals($installedValue, $testValue)) {
        $errors[] = "test target reuses installed configuration variable {$installedKey}";
    }
}

if (!is_file($fixturePath)) {
    $errors[] = 'known-schema fixture is missing';
} else {
    $fixture = (string)file_get_contents($fixturePath);
    if (!preg_match('/CREATE TABLE[^;]+oh_test_parents/i', $fixture)
        || !preg_match('/CREATE TABLE[^;]+oh_test_children/i', $fixture)
        || !preg_match('/FOREIGN KEY/i', $fixture)) {
        $errors[] = 'known-schema fixture lacks parent, child, or foreign-key contract';
    }
    if (preg_match('/\b(?:DROP|TRUNCATE|ALTER)\b/i', $fixture)) {
        $errors[] = 'known-schema fixture contains destructive DDL';
    }
}

if ($proofPath === '') {
    $errors[] = 'OH_TEST_DB_PROOF_FILE is not supplied';
} elseif (!is_file($proofPath)) {
    $errors[] = 'isolation proof file does not exist';
} else {
    $proof = json_decode((string)file_get_contents($proofPath), true);
    if (!is_array($proof)) {
        $errors[] = 'isolation proof file is not valid JSON';
    } else {
        foreach ([
            'disposable' => true,
            'installed_database_access' => false,
            'production_network_access' => false,
            'production_storage_mount' => false,
            'teardown_owned_only' => true,
            'owned_by_harness' => true,
        ] as $key => $expected) {
            if (($proof[$key] ?? null) !== $expected) {
                $errors[] = "isolation proof {$key} is not {$expected}";
            }
        }
        if ($name !== '' && ($proof['database_name'] ?? '') !== $name) {
            $errors[] = 'proof database identity does not match OH_TEST_DB_NAME';
        }
        if ($host !== '' && ($proof['host'] ?? '') !== $host) {
            $errors[] = 'proof host identity does not match OH_TEST_DB_HOST';
        }
        if ($credentialSource !== '' && ($proof['credential_source'] ?? '') !== $credentialSource) {
            $errors[] = 'proof credential source does not match OH_TEST_DB_CREDENTIAL_SOURCE';
        }
        if ($resourceId !== '' && ($proof['resource_id'] ?? '') !== $resourceId) {
            $errors[] = 'proof resource identity does not match OH_TEST_DB_RESOURCE_ID';
        }
        if (isset($proof['execution_authorized']) && $proof['execution_authorized'] === true) {
            $errors[] = 'synthetic proof cannot assert execution authorization';
        }
        if (isset($proof['teardown_resource_id']) && $proof['teardown_resource_id'] !== ($proof['resource_id'] ?? null)) {
            $errors[] = 'teardown resource identity escapes the proof resource';
        }
    }
}

$result = [
    'status' => $errors === [] ? 'MOCK_PROOF_VALIDATED_BUT_EXECUTION_BLOCKED' : 'BLOCKED',
    'proof_kind' => 'synthetic_input_only',
    'execution_authorized' => false,
    'teardown_authorized' => false,
    'execution_performed' => false,
    'sql_executed' => false,
    'installed_database_touched' => false,
    'fixture' => 'scripts/testing/disposable_db_fixture.sql',
    'required_proof' => [
        'loopback_host_allowlist' => ['127.0.0.1', 'localhost', '::1'],
        'production_like_names_rejected' => true,
        'test_only_user_required' => true,
        'explicit_disposable_proof_required' => true,
    ],
    'errors' => $errors,
    'warnings' => $warnings,
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
// Even a structurally valid synthetic proof exits blocked. A future runner
// must obtain independent local/container attestation in a separate process.
exit(2);
