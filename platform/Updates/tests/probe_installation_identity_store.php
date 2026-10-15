<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\InstallationIdentityStore;
use Platform\Updates\InstalledPlatformIdentityVerifier;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$hash = str_repeat('a', 64);
$statementFingerprint = str_repeat('b', 64);
$identity = static function (string $build, string $version = '1.2.3') use ($hash, $statementFingerprint): array {
    return [
        'installation_id' => 'install:fixture-001',
        'product_id' => 'susankhya-os',
        'release_version' => $version,
        'build_id' => $build,
        'lane' => 'app',
        'package_sha256' => $hash,
        'release_statement_fingerprint' => $statementFingerprint,
        'signing_key_id' => 'release-key:2026-01',
        'installed_at' => '2026-10-15T12:00:00Z',
        'schema_state_identity' => 'schema:1.2.0',
        'record_version' => InstalledPlatformIdentityVerifier::RECORD_VERSION,
    ];
};
$observations = static function (string $build, string $version = '1.2.3') use ($hash, $statementFingerprint): array {
    return [
        'package' => ['sha256' => $hash],
        'signed_release' => [
            'product_id' => 'susankhya-os',
            'release_version' => $version,
            'build_id' => $build,
            'lane' => 'app',
            'statement_fingerprint' => $statementFingerprint,
            'signing_key_id' => 'release-key:2026-01',
        ],
        'schema' => ['identity' => 'schema:1.2.0'],
    ];
};

$root = sys_get_temp_dir() . '/odarehub-identity-store-' . bin2hex(random_bytes(8));
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $remove($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }
};
$store = new InstallationIdentityStore($root);
$first = $identity('build:fixture-001');
$firstEvidence = $observations('build:fixture-001');

try {
    $missing = $store->read();
    $assert($missing['state'] === InstallationIdentityStore::IDENTITY_MISSING, 'empty runner-owned store reports missing identity');

    $created = $store->create($first, $firstEvidence);
    $assert($created['ok'] === true && $created['state'] === 'identity_created', 'initial identity creation succeeds');
    $read = $store->read();
    $assert($read['ok'] === true && ($read['identity']['build_id'] ?? '') === 'build:fixture-001', 'created identity reads back exactly');
    $assert(($created['authority']['update_eligible'] ?? true) === false && ($created['authority']['apply_eligible'] ?? true) === false, 'creation grants no update or apply authority');

    $implicit = $store->create($first, $firstEvidence);
    $assert($implicit['state'] === InstallationIdentityStore::IDENTITY_CONFLICT, 'initial creation refuses implicit overwrite');

    $second = $identity('build:fixture-002', '1.2.4');
    $secondEvidence = $observations('build:fixture-002', '1.2.4');
    $replaced = $store->replace($second, $secondEvidence, (string)$read['fingerprint']);
    $assert($replaced['ok'] === true && $replaced['state'] === 'identity_replaced', 'controlled replacement succeeds');
    $afterReplace = $store->read();
    $assert(($afterReplace['identity']['installation_id'] ?? '') === 'install:fixture-001', 'installation ID remains stable across replacement');
    $assert(($afterReplace['identity']['build_id'] ?? '') === 'build:fixture-002', 'replacement installs the new build identity');
    $assert(is_array($afterReplace['predecessor'] ?? null), 'replacement exposes predecessor evidence');
    $assert(is_file($root . '/predecessors/' . $read['fingerprint'] . '.json'), 'predecessor record is preserved immutably');

    $differentInstallation = $second;
    $differentInstallation['installation_id'] = 'install:fixture-002';
    $differentInstallationResult = $store->replace($differentInstallation, $secondEvidence, (string)$afterReplace['fingerprint']);
    $assert($differentInstallationResult['state'] === InstallationIdentityStore::IDENTITY_INSTALLATION_MISMATCH, 'replacement rejects changed installation ID before writing');
    $assert(($store->read()['identity']['installation_id'] ?? '') === 'install:fixture-001', 'installation ID mismatch preserves authoritative bytes');

    $stale = $store->replace($first, $firstEvidence, (string)$read['fingerprint']);
    $assert($stale['state'] === InstallationIdentityStore::IDENTITY_CONFLICT, 'stale expected fingerprint rejects conflicting replacement');

    $malformed = $second;
    unset($malformed['build_id']);
    $malformedResult = $store->replace($malformed, $secondEvidence, (string)$afterReplace['fingerprint']);
    $assert($malformedResult['state'] === InstalledPlatformIdentityVerifier::IDENTITY_MISSING, 'malformed replacement is rejected before write');
    $assert(($store->read()['identity']['build_id'] ?? '') === 'build:fixture-002', 'malformed replacement preserves current identity');

    $concurrent = null;
    $lockStore = new InstallationIdentityStore($root, [], static function (string $stage) use (&$concurrent, $root, $second, $secondEvidence, $afterReplace): void {
        if ($stage === 'after_temp_write') {
            $concurrent = (new InstallationIdentityStore($root))->replace($second, $secondEvidence, (string)$afterReplace['fingerprint']);
        }
    });
    $lockResult = $lockStore->replace($second, $secondEvidence, (string)$afterReplace['fingerprint']);
    $assert(($concurrent['state'] ?? null) === InstallationIdentityStore::IDENTITY_LOCK_UNAVAILABLE, 'concurrent writer is rejected while lock is held');
    $assert($lockResult['state'] === 'identity_replaced', 'original writer completes after concurrent rejection');

    $faultRoot = $root . '-fault';
    $faultStore = new InstallationIdentityStore($faultRoot, [], static function (string $stage): void {
        if ($stage === 'before_finalize') {
            throw new RuntimeException('injected pre-finalization failure');
        }
    });
    $faultResult = $faultStore->create($first, $firstEvidence);
    $assert($faultResult['state'] === InstallationIdentityStore::IDENTITY_WRITE_FAILED, 'failure before finalization is reported');
    $interrupted = $faultStore->read();
    $assert($interrupted['state'] === InstallationIdentityStore::IDENTITY_INTERRUPTED_WRITE, 'leftover temporary write is classified as interrupted');
    $assert($faultStore->create($first, $firstEvidence)['state'] === InstallationIdentityStore::IDENTITY_INTERRUPTED_WRITE, 'interrupted state is not automatically repaired');
    $remove($faultRoot);

    $preserveRoot = $root . '-preserve';
    $preserveStore = new InstallationIdentityStore($preserveRoot);
    $preserveStore->create($first, $firstEvidence);
    $preserveBefore = $preserveStore->read();
    $preserveFaultStore = new InstallationIdentityStore($preserveRoot, [], static function (string $stage): void {
        if ($stage === 'before_finalize') {
            throw new RuntimeException('injected replacement failure');
        }
    });
    $preserveResult = $preserveFaultStore->replace($second, $secondEvidence, (string)$preserveBefore['fingerprint']);
    $assert($preserveResult['state'] === InstallationIdentityStore::IDENTITY_WRITE_FAILED, 'replacement failure before finalization is reported');
    foreach (glob($preserveRoot . '/.identity.tmp-*') ?: [] as $temporary) {
        @unlink($temporary);
    }
    $assert(($preserveStore->read()['identity']['build_id'] ?? '') === 'build:fixture-001', 'replacement failure preserves the original identity');
    $remove($preserveRoot);

    $malformedRoot = $root . '-malformed';
    @mkdir($malformedRoot, 0700, true);
    $malformedBytes = "{\"identity\":\"not-a-record\"}\n";
    file_put_contents($malformedRoot . '/identity.json', $malformedBytes);
    $malformedStore = new InstallationIdentityStore($malformedRoot);
    $malformedExistingResult = $malformedStore->create($first, $firstEvidence);
    $assert($malformedExistingResult['state'] === InstallationIdentityStore::IDENTITY_UNAVAILABLE, 'create blocks malformed existing identity state');
    $assert(file_get_contents($malformedRoot . '/identity.json') === $malformedBytes, 'malformed existing identity bytes remain unchanged');
    $remove($malformedRoot);

    $postFinalizeRoot = $root . '-post-finalize';
    $postFinalizeStore = new InstallationIdentityStore($postFinalizeRoot, [], static function (string $stage): void {
        if ($stage === 'after_finalize') {
            throw new RuntimeException('injected post-finalization interruption');
        }
    });
    $postFinalizeResult = $postFinalizeStore->create($first, $firstEvidence);
    $assert($postFinalizeResult['state'] === InstallationIdentityStore::IDENTITY_CANDIDATE_COMMITTED, 'post-finalization exception reconciles committed candidate truthfully');
    $assert(($postFinalizeStore->read()['identity']['build_id'] ?? '') === 'build:fixture-001', 'post-finalization candidate remains authoritative after reconciliation');
    $remove($postFinalizeRoot);

    $rollbackRoot = $root . '-rollback';
    $rollbackStore = new InstallationIdentityStore($rollbackRoot);
    $rollbackCreate = $rollbackStore->create($first, $firstEvidence);
    $rollbackBefore = $rollbackStore->read();
    $tamperStore = new InstallationIdentityStore($rollbackRoot, [], static function (string $stage, string $path): void {
        if ($stage === 'after_finalize') {
            file_put_contents($path . '/identity.json', '{"malformed":true}\n');
        }
    });
    $rollbackResult = $tamperStore->replace($second, $secondEvidence, (string)$rollbackBefore['fingerprint']);
    $assert($rollbackResult['state'] === InstallationIdentityStore::IDENTITY_READBACK_MISMATCH, 'read-back mismatch fails closed');
    $assert(($rollbackStore->read()['identity']['build_id'] ?? '') === 'build:fixture-001', 'read-back failure restores original identity');
    $remove($rollbackRoot);

    $indeterminateRoot = $root . '-indeterminate';
    $indeterminateStore = new InstallationIdentityStore($indeterminateRoot);
    $indeterminateStore->create($first, $firstEvidence);
    $indeterminateBefore = $indeterminateStore->read();
    $indeterminateFaultStore = new InstallationIdentityStore($indeterminateRoot, [], static function (string $stage, string $path): void {
        if ($stage === 'after_finalize') {
            file_put_contents($path . '/identity.json', '{"malformed":true}' . "\n");
        }
        if ($stage === 'before_rollback') {
            throw new RuntimeException('injected rollback failure');
        }
    });
    $indeterminateResult = $indeterminateFaultStore->replace($second, $secondEvidence, (string)$indeterminateBefore['fingerprint']);
    $assert($indeterminateResult['state'] === InstallationIdentityStore::IDENTITY_INDETERMINATE, 'rollback failure is classified as indeterminate, never successful');
    $assert(is_file($indeterminateRoot . '/predecessors/' . $indeterminateBefore['fingerprint'] . '.json'), 'predecessor evidence survives indeterminate recovery');
    $remove($indeterminateRoot);

    $unsafeTraversal = new InstallationIdentityStore($root . '/../escape');
    $assert($unsafeTraversal->read()['state'] === InstallationIdentityStore::IDENTITY_PATH_UNSAFE, 'traversal destination is rejected');

    $symlinkRoot = $root . '-symlink';
    @mkdir($symlinkRoot, 0700, true);
    $symlinkTarget = $root . '-symlink-target';
    @mkdir($symlinkTarget, 0700, true);
    @symlink($symlinkTarget, $symlinkRoot . '/escape');
    $symlinkStore = new InstallationIdentityStore($symlinkRoot . '/escape');
    $assert($symlinkStore->read()['state'] === InstallationIdentityStore::IDENTITY_PATH_UNSAFE, 'symlink escape destination is rejected');
    $remove($symlinkRoot);
    $remove($symlinkTarget);

    $repoStore = new InstallationIdentityStore(APP_ROOT . '/.identity-fixture');
    $assert($repoStore->read()['state'] === InstallationIdentityStore::IDENTITY_PATH_UNSAFE, 'repository destination is rejected');
} finally {
    $remove($root);
    $remove($root . '-fault');
    $remove($root . '-rollback');
    $remove($root . '-malformed');
    $remove($root . '-post-finalize');
    $remove($root . '-indeterminate');
}

echo "Installation identity store: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
