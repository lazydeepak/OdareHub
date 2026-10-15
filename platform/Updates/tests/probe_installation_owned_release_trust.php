<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\DisposableTrustProvisioner;
use Platform\Updates\InstallationOwnedTrustStore;
use Platform\Updates\OfflineReleaseArtifactVerifier;
use Platform\Updates\ReleaseStatementCanonicalizer;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$root = rtrim(sys_get_temp_dir(), '/') . '/odarehub-trust-' . bin2hex(random_bytes(8));
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

$keyPair = sodium_crypto_sign_seed_keypair(str_repeat("\x31", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$secretKey = sodium_crypto_sign_secretkey($keyPair);
$publicKey = sodium_crypto_sign_publickey($keyPair);
$fingerprint = hash('sha256', $publicKey);
$record = [
    'key_id' => 'fixture-trust-073',
    'algorithm' => 'ed25519',
    'public_key' => $publicKey,
    'fingerprint' => $fingerprint,
    'status' => 'active',
    'not_before' => '2026-01-01T00:00:00Z',
    'not_after' => null,
];
$store = new InstallationOwnedTrustStore($root, [APP_ROOT]);
$provisioner = new DisposableTrustProvisioner($store);

$canonicalizer = new ReleaseStatementCanonicalizer();
$releaseRoot = $root . '/release';
$releaseDirectory = $releaseRoot . '/releases';
$packagePath = $releaseDirectory . '/susankhya-os-1.2.3-fixture-oh073.zip';
$metadataPath = $releaseDirectory . '/susankhya-os-1.2.3-fixture-oh073.json';
$buildId = 'fixture:oh073:001';
$createdAt = '2026-10-15T00:00:00Z';

$makePackage = static function (string $path, string $build, string $created) use ($remove): void {
    @mkdir(dirname($path), 0700, true);
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('fixture package could not be created');
    }
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $rootName) {
        $zip->addFromString($rootName . '/runtime.txt', $rootName);
    }
    $zip->addFromString('release_public_key.txt', 'package-supplied-key-is-not-trust');
    $zip->addFromString('composer.json', json_encode(['require' => []], JSON_THROW_ON_ERROR));
    $zip->addFromString('composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []], JSON_THROW_ON_ERROR));
    $zip->addFromString('release_metadata.json', json_encode([
        'schema_version' => 'susankhya.release.v1', 'product_id' => 'susankhya-os', 'product_name' => 'Susankhya ERP',
        'release_version' => '1.2.3', 'build_id' => $build, 'channel' => 'local', 'lane' => 'app', 'created_at' => $created,
        'package' => ['filename' => basename($path), 'size_bytes' => 0, 'sha256' => null], 'compatibility' => [],
        'readiness' => ['status' => 'passed', 'evidence' => []],
    ], JSON_THROW_ON_ERROR));
    $zip->close();
};
$makeMetadata = static function (string $path, string $package): array {
    $metadata = [
        'schema_version' => 'susankhya.release.v1', 'product_id' => 'susankhya-os', 'product_name' => 'Susankhya ERP',
        'release_version' => '1.2.3', 'build_id' => 'fixture:oh073:001', 'channel' => 'local', 'lane' => 'app',
        'created_at' => '2026-10-15T00:00:00Z',
        'package' => ['filename' => basename($package), 'size_bytes' => filesize($package), 'sha256' => hash_file('sha256', $package)],
        'compatibility' => [], 'readiness' => ['status' => 'passed', 'evidence' => []],
        'migration' => ['warnings' => [], 'requires_backup' => false], 'assets' => ['regeneration_required' => false],
        'release_notes' => '', 'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
    ];
    file_put_contents($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    return $metadata;
};

try {
    $check($store->read()['state'] === InstallationOwnedTrustStore::TRUST_MISSING, 'missing trust record fails closed');
    $check($provisioner->enroll($record, $fingerprint, false)['state'] === InstallationOwnedTrustStore::TRUST_CONFLICT, 'enrollment requires explicit operator approval');
    $check($provisioner->enroll($record, str_repeat('0', 64), true)['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'incorrect independent fingerprint is rejected');
    $candidateReplacement = $record;
    $candidateReplacement['public_key'] = sodium_crypto_sign_publickey(sodium_crypto_sign_seed_keypair(str_repeat("\x32", SODIUM_CRYPTO_SIGN_SEEDBYTES)));
    $check($provisioner->enroll($candidateReplacement, $fingerprint, true)['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'candidate-supplied replacement key is rejected');
    $check((new DisposableTrustProvisioner(new InstallationOwnedTrustStore(APP_ROOT . '/.trust-fixture', [APP_ROOT])))->enroll($record, $fingerprint, true)['state'] === InstallationOwnedTrustStore::TRUST_UNSAFE_PATH, 'disposable enrollment cannot target the repository');
    $enrolled = $provisioner->enroll($record, $fingerprint, true);
    $check($enrolled['ok'] === true && $enrolled['state'] === 'trust_enrolled_disposable', 'valid disposable trust enrollment succeeds');
    $trust = $store->read();
    $check($trust['ok'] === true && $trust['trust_source'] === InstallationOwnedTrustStore::TRUST_SOURCE, 'reader reports installation-owned source');
    $check(($trust['records'][0]['public_key'] ?? null) === $publicKey, 'reader returns verifier-compatible raw public key');
    $check(($trust['authority']['release_authority'] ?? true) === false && ($trust['authority']['apply_eligible'] ?? true) === false, 'trust read does not grant release or apply authority');
    $check($provisioner->enroll($record, $fingerprint, true)['state'] === InstallationOwnedTrustStore::TRUST_CONFLICT, 'unauthorized trust overwrite is rejected');

    $malformedRoot = $root . '-malformed';
    @mkdir($malformedRoot, 0700, true);
    file_put_contents($malformedRoot . '/trust.json', '{"storage_record_version":"wrong"}\n');
    $malformedStore = new InstallationOwnedTrustStore($malformedRoot, [APP_ROOT]);
    $check($malformedStore->read()['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'malformed trust envelope is rejected');
    file_put_contents($malformedRoot . '/trust.json', json_encode([
        'storage_record_version' => InstallationOwnedTrustStore::STORAGE_VERSION,
        'trust_source' => InstallationOwnedTrustStore::TRUST_SOURCE,
        'records' => [[
            'key_id' => $record['key_id'], 'algorithm' => 'ed25519', 'public_key_base64' => 'not-base64-%%%=', 'fingerprint' => $fingerprint,
            'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => null,
        ]],
    ]) . "\n");
    $check($malformedStore->read()['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'malformed public-key encoding is rejected');
    $remove($malformedRoot);

    $duplicateRoot = $root . '-duplicate';
    @mkdir($duplicateRoot, 0700, true);
    $duplicate = ['storage_record_version' => InstallationOwnedTrustStore::STORAGE_VERSION, 'trust_source' => InstallationOwnedTrustStore::TRUST_SOURCE, 'records' => array_fill(0, 2, [
        'key_id' => $record['key_id'], 'algorithm' => 'ed25519', 'public_key_base64' => base64_encode($publicKey), 'fingerprint' => $fingerprint,
        'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => null,
    ])];
    file_put_contents($duplicateRoot . '/trust.json', json_encode($duplicate) . "\n");
    $check((new InstallationOwnedTrustStore($duplicateRoot, [APP_ROOT]))->read()['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'duplicate key IDs are rejected');
    $remove($duplicateRoot);

    $duplicateFingerprintRoot = $root . '-duplicate-fingerprint';
    @mkdir($duplicateFingerprintRoot, 0700, true);
    $duplicateFingerprint = ['storage_record_version' => InstallationOwnedTrustStore::STORAGE_VERSION, 'trust_source' => InstallationOwnedTrustStore::TRUST_SOURCE, 'records' => [
        ['key_id' => 'fixture-trust-a', 'algorithm' => 'ed25519', 'public_key_base64' => base64_encode($publicKey), 'fingerprint' => $fingerprint, 'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => null],
        ['key_id' => 'fixture-trust-b', 'algorithm' => 'ed25519', 'public_key_base64' => base64_encode($publicKey), 'fingerprint' => $fingerprint, 'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => null],
    ]];
    file_put_contents($duplicateFingerprintRoot . '/trust.json', json_encode($duplicateFingerprint) . "\n");
    $check((new InstallationOwnedTrustStore($duplicateFingerprintRoot, [APP_ROOT]))->read()['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'duplicate key fingerprints are rejected');
    $remove($duplicateFingerprintRoot);

    $contradictoryRoot = $root . '-contradictory';
    @mkdir($contradictoryRoot, 0700, true);
    $contradictory = ['storage_record_version' => InstallationOwnedTrustStore::STORAGE_VERSION, 'trust_source' => InstallationOwnedTrustStore::TRUST_SOURCE, 'records' => [[
        'key_id' => $record['key_id'], 'algorithm' => 'ed25519', 'public_key_base64' => base64_encode($publicKey), 'fingerprint' => $fingerprint,
        'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => '2025-01-01T00:00:00Z',
    ]]];
    file_put_contents($contradictoryRoot . '/trust.json', json_encode($contradictory) . "\n");
    $check((new InstallationOwnedTrustStore($contradictoryRoot, [APP_ROOT]))->read()['state'] === InstallationOwnedTrustStore::TRUST_MALFORMED, 'contradictory lifecycle interval is rejected');
    $remove($contradictoryRoot);

    $tamperStore = new InstallationOwnedTrustStore($root, [APP_ROOT], static function (string $path): void {
        file_put_contents($path, file_get_contents($path) . "\n");
    });
    $check($tamperStore->read()['state'] === InstallationOwnedTrustStore::TRUST_MUTATED, 'trust-store mutation during read is blocked');

    $symlinkRoot = $root . '-symlink';
    $symlinkTarget = $root . '-symlink-target';
    @mkdir($symlinkRoot, 0700, true);
    @mkdir($symlinkTarget, 0700, true);
    @symlink($symlinkTarget, $symlinkRoot . '/escape');
    $check((new InstallationOwnedTrustStore($symlinkRoot . '/escape', [APP_ROOT]))->read()['state'] === InstallationOwnedTrustStore::TRUST_UNSAFE_PATH, 'symlink trust path is rejected');
    $remove($symlinkRoot);
    $remove($symlinkTarget);

    $makePackage($packagePath, $buildId, $createdAt);
    $metadata = $makeMetadata($metadataPath, $packagePath);
    $statement = $canonicalizer->canonicalize([
        'product_id' => 'susankhya-os', 'release_version' => '1.2.3', 'build_id' => $buildId, 'lane' => 'app',
        'package_sha256' => $metadata['package']['sha256'], 'package_bytes' => (string)$metadata['package']['size_bytes'],
        'metadata_sha256' => hash_file('sha256', $metadataPath), 'minimum_supported_schema' => '1.0.0', 'created_at' => $createdAt,
    ]);
    $signature = sodium_crypto_sign_detached($statement, $secretKey);
    $artifactVerifier = new OfflineReleaseArtifactVerifier($canonicalizer);
    $valid = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, $record['key_id'], $trust['records'], '2026-10-16T00:00:00Z', [
        'release_version' => '1.2.3', 'build_id' => $buildId, 'metadata' => 'releases/' . basename($metadataPath), 'package' => 'releases/' . basename($packagePath),
        'sha256' => hash_file('sha256', $packagePath), 'size_bytes' => filesize($packagePath),
    ]);
    $check(($valid['ok'] ?? false) === true && ($valid['state'] ?? null) === 'verified', 'signed exact ZIP and sidecar verify with enrolled trust');
    $check(($valid['inspection']['ok'] ?? false) === true, 'package-provided key material is ignored by strict artifact verification');

    $unknownKey = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, 'package-supplied-key', $trust['records'], '2026-10-16T00:00:00Z');
    $check(($unknownKey['state'] ?? null) === 'untrusted_key', 'candidate/package signing key cannot replace enrolled trust');
    $badSignature = $artifactVerifier->verify($packagePath, $metadataPath, $statement, str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES), $record['key_id'], $trust['records'], '2026-10-16T00:00:00Z');
    $check(($badSignature['state'] ?? null) === 'invalid_signature', 'invalid signature remains blocked');
    $revoked = $record;
    $revoked['status'] = 'revoked';
    $revokedRoot = $root . '-revoked';
    $revokedStore = new InstallationOwnedTrustStore($revokedRoot, [APP_ROOT]);
    $revokedProvisioner = new DisposableTrustProvisioner($revokedStore);
    $revokedProvisioner->enroll($revoked, $fingerprint, true);
    $revokedRead = $revokedStore->read();
    $revokedResult = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, $record['key_id'], $revokedRead['records'], '2026-10-16T00:00:00Z');
    $check(($revokedResult['state'] ?? null) === 'revoked_key', 'revoked enrolled key is blocked');
    $remove($revokedRoot);

    $expired = $record;
    $expired['not_after'] = '2026-01-02T00:00:00Z';
    $expiredRoot = $root . '-expired';
    $expiredStore = new InstallationOwnedTrustStore($expiredRoot, [APP_ROOT]);
    (new DisposableTrustProvisioner($expiredStore))->enroll($expired, $fingerprint, true);
    $expiredResult = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, $record['key_id'], $expiredStore->read()['records'], '2026-10-16T00:00:00Z');
    $check(($expiredResult['state'] ?? null) === 'expired_or_not_yet_valid', 'expired enrolled key is blocked');
    $remove($expiredRoot);

    $notYetValid = $record;
    $notYetValid['not_before'] = '2027-01-01T00:00:00Z';
    $notYetRoot = $root . '-not-yet-valid';
    $notYetStore = new InstallationOwnedTrustStore($notYetRoot, [APP_ROOT]);
    (new DisposableTrustProvisioner($notYetStore))->enroll($notYetValid, $fingerprint, true);
    $notYetResult = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, $record['key_id'], $notYetStore->read()['records'], '2026-10-16T00:00:00Z');
    $check(($notYetResult['state'] ?? null) === 'expired_or_not_yet_valid', 'not-yet-valid enrolled key is blocked');
    $remove($notYetRoot);

    file_put_contents($metadataPath, file_get_contents($metadataPath) . "tamper");
    $tamperedSidecar = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, $record['key_id'], $trust['records'], '2026-10-16T00:00:00Z');
    $check(($tamperedSidecar['ok'] ?? true) === false, 'tampered sidecar is blocked before authenticity acceptance');

    file_put_contents($packagePath, file_get_contents($packagePath) . 'tamper');
    $tampered = $artifactVerifier->verify($packagePath, $metadataPath, $statement, $signature, $record['key_id'], $trust['records'], '2026-10-16T00:00:00Z');
    $check(($tampered['ok'] ?? true) === false, 'tampered ZIP is blocked before authenticity acceptance');
    $check((new InstallationOwnedTrustStore($root . '/../trust-escape', [APP_ROOT]))->read()['state'] === InstallationOwnedTrustStore::TRUST_UNSAFE_PATH, 'trust traversal path is rejected');
    $check(($valid['ok'] ?? false) === true, 'preview authenticity remains diagnostic-only and does not alter verified result authority');
} finally {
    $remove($root);
    $remove($root . '-malformed');
    $remove($root . '-duplicate');
    $remove($root . '-duplicate-fingerprint');
    $remove($root . '-contradictory');
    $remove($root . '-symlink');
    $remove($root . '-symlink-target');
    $remove($root . '-revoked');
    $remove($root . '-expired');
    $remove($root . '-not-yet-valid');
}

echo "Installation-owned release trust probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
