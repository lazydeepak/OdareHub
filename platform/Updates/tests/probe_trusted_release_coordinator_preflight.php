<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\RecoveryPointMetadataValidator;
use Platform\Updates\DisposableTrustProvisioner;
use Platform\Updates\InstallationIdentityStore;
use Platform\Updates\InstallationOwnedTrustStore;
use Platform\Updates\InstalledPlatformIdentityVerifier;
use Platform\Updates\OfflineReleaseArtifactVerifier;
use Platform\Updates\ReleaseMigrationPlanValidator;
use Platform\Updates\ReleaseStatementCanonicalizer;
use Platform\Updates\TrustedReleaseCoordinatorPreflightService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$root = rtrim(sys_get_temp_dir(), '/') . '/odarehub-coordinator-' . bin2hex(random_bytes(8));
$remove = static function (string $path) use (&$remove): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') { $remove($path . '/' . $entry); }
        }
        @rmdir($path);
    }
};

try {
    $keyPair = sodium_crypto_sign_seed_keypair(str_repeat("\x41", SODIUM_CRYPTO_SIGN_SEEDBYTES));
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);
    $trustRecord = [
        'key_id' => 'fixture-coordinator-key', 'algorithm' => 'ed25519',
        'public_key' => $publicKey, 'fingerprint' => hash('sha256', $publicKey),
        'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => null,
    ];
    $trustStore = new InstallationOwnedTrustStore($root . '/trust', [APP_ROOT]);
    (new DisposableTrustProvisioner($trustStore))->enroll($trustRecord, $trustRecord['fingerprint'], true);

    $releaseDirectory = $root . '/release';
    $packagePath = $releaseDirectory . '/susankhya-os-2.0.0-coordinator.zip';
    $metadataPath = $releaseDirectory . '/susankhya-os-2.0.0-coordinator.json';
    @mkdir($releaseDirectory, 0700, true);
    $zip = new \ZipArchive();
    if ($zip->open($packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('fixture ZIP could not be created');
    }
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $name) {
        $zip->addFromString($name . '/runtime.txt', $name);
    }
    $zip->addFromString('composer.json', json_encode(['require' => []], JSON_THROW_ON_ERROR));
    $zip->addFromString('composer.lock', json_encode(['content-hash' => str_repeat('b', 32), 'packages' => []], JSON_THROW_ON_ERROR));
    $zip->addFromString('release_metadata.json', json_encode(['schema_version' => 'susankhya.release.v1', 'product_id' => 'susankhya-os', 'product_name' => 'Susankhya ERP', 'release_version' => '2.0.0', 'build_id' => 'fixture:coordinator:001', 'channel' => 'local', 'lane' => 'app', 'created_at' => '2026-10-15T00:00:00Z', 'package' => ['filename' => basename($packagePath), 'size_bytes' => 0, 'sha256' => null], 'compatibility' => []], JSON_THROW_ON_ERROR));
    $zip->close();

    $compatibility = [
        'minimum_product_version' => '1.0.0', 'core' => ['minimum' => '1.0.0'],
        'apps' => [], 'modules' => [], 'schema' => ['minimum' => '1.0.0', 'target' => '2.0.0'],
        'php' => ['minimum' => PHP_VERSION], 'php_extensions' => [],
    ];
    $metadata = [
        'schema_version' => 'susankhya.release.v1', 'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP', 'release_version' => '2.0.0',
        'build_id' => 'fixture:coordinator:001', 'channel' => 'local', 'lane' => 'app',
        'created_at' => '2026-10-15T00:00:00Z',
        'package' => ['filename' => basename($packagePath), 'size_bytes' => filesize($packagePath), 'sha256' => hash_file('sha256', $packagePath)],
        'compatibility' => $compatibility, 'migration' => ['warnings' => [], 'requires_backup' => true],
        'readiness' => ['status' => 'passed', 'evidence' => []], 'assets' => ['regeneration_required' => false],
        'release_notes' => '', 'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
    ];
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

    $canonicalizer = new ReleaseStatementCanonicalizer();
    $statement = $canonicalizer->canonicalize([
        'product_id' => 'susankhya-os', 'release_version' => '2.0.0', 'build_id' => 'fixture:coordinator:001', 'lane' => 'app',
        'package_sha256' => $metadata['package']['sha256'], 'package_bytes' => (string)$metadata['package']['size_bytes'],
        'metadata_sha256' => hash_file('sha256', $metadataPath), 'minimum_supported_schema' => '1.0.0', 'created_at' => $metadata['created_at'],
    ]);
    $signature = sodium_crypto_sign_detached($statement, $secretKey);
    $statementFingerprint = hash('sha256', $statement);

    $installedState = [
        'product_id' => 'susankhya-os', 'product_version' => '1.2.3',
        'platform' => ['product_id' => 'susankhya-os', 'version' => '1.2.3', 'build_id' => 'fixture:installed:001'],
        'apps' => [], 'modules' => [], 'schema' => ['version' => '1.0.0'],
        'migrations' => ['pending' => [], 'failed' => [], 'evidence' => ['identities_verified' => true, 'ownership_verified' => true, 'checksums_verified' => true, 'ordering_verified' => true, 'target_schema_verified' => true, 'target_schema' => '2.0.0']],
    ];
    $identity = [
        'installation_id' => 'installation-fixture-001', 'product_id' => 'susankhya-os', 'release_version' => '1.2.3',
        'build_id' => 'fixture:installed:001', 'lane' => 'app', 'package_sha256' => $metadata['package']['sha256'],
        'release_statement_fingerprint' => str_repeat('c', 64), 'signing_key_id' => $trustRecord['key_id'],
        'installed_at' => '2026-10-15T00:00:00Z', 'schema_state_identity' => 'schema:1.0.0',
        'record_version' => InstalledPlatformIdentityVerifier::RECORD_VERSION,
    ];
    // The current identity intentionally describes the installed release. Its
    // comparison observations are independent of the candidate statement.
    $identityObservations = [
        'package' => ['sha256' => $identity['package_sha256']],
        'signed_release' => ['product_id' => 'susankhya-os', 'release_version' => '1.2.3', 'build_id' => 'fixture:installed:001', 'lane' => 'app', 'statement_fingerprint' => $identity['release_statement_fingerprint'], 'signing_key_id' => $trustRecord['key_id']],
        'schema' => ['identity' => 'schema:1.0.0'],
    ];
    $identityStore = new InstallationIdentityStore($root . '/identity', [APP_ROOT]);
    $identityStore->create($identity, $identityObservations);

    $steps = [['position' => 1, 'app_key' => 'Platform', 'version' => '2.0.0', 'migration_name' => '001_target.sql', 'checksum' => str_repeat('d', 64), 'requires' => []]];
    $plan = ['plan_version' => ReleaseMigrationPlanValidator::PLAN_VERSION, 'plan_id' => 'fixture-plan-001', 'source' => ReleaseMigrationPlanValidator::SOURCE, 'validation_state' => 'validated', 'steps' => $steps, 'plan_fingerprint' => ReleaseMigrationPlanValidator::fingerprint(ReleaseMigrationPlanValidator::PLAN_VERSION, 'fixture-plan-001', $steps)];
    $migrationBinding = ['plan' => $plan, 'release_version' => '2.0.0', 'build_id' => 'fixture:coordinator:001', 'package_sha256' => $metadata['package']['sha256'], 'statement_fingerprint' => $statementFingerprint, 'target_schema' => '2.0.0'];

    $recovery = $root . '/recovery';
    @mkdir($recovery, 0700, true);
    file_put_contents($recovery . '/database.sql', 'disposable database payload');
    file_put_contents($recovery . '/filesystem.zip', 'disposable filesystem payload');
    $recoveryMetadata = ['schema_version' => 'susankhya.recovery-point.v1', 'recovery_point_id' => 'rp-fixture-001', 'created_by' => 'fixture', 'created_at' => '2026-10-15T00:00:00Z', 'reason' => 'update_apply', 'installation' => ['product_id' => 'susankhya-os', 'release_version' => '1.2.3', 'build_id' => 'fixture:installed:001', 'app_manifest_checksums' => [], 'module_manifest_checksums' => [], 'schema_state' => [], 'migration_state' => []], 'database' => ['provider' => 'fixture', 'provider_version' => '1', 'identity' => ['host_label' => 'disposable', 'database' => 'fixture', 'port' => 3307], 'payload' => ['path' => 'database.sql', 'sha256' => hash_file('sha256', $recovery . '/database.sql'), 'size_bytes' => filesize($recovery . '/database.sql')]], 'filesystem' => ['provider' => 'fixture', 'payload' => ['path' => 'filesystem.zip', 'sha256' => hash_file('sha256', $recovery . '/filesystem.zip'), 'size_bytes' => filesize($recovery . '/filesystem.zip')], 'preservation_inventory_sha256' => str_repeat('e', 64)]];
    file_put_contents($recovery . '/recovery-point.json', json_encode($recoveryMetadata, JSON_THROW_ON_ERROR));
    file_put_contents($recovery . '/rehearsal.json', json_encode(['schema_version' => 'susankhya.rehearsal.v1', 'recovery_point_id' => 'rp-fixture-001', 'ok' => true, 'final_state' => 'restored', 'database' => ['ok' => true], 'filesystem' => ['ok' => true]], JSON_THROW_ON_ERROR));
    $coordinator = new TrustedReleaseCoordinatorPreflightService($trustStore, $identityStore);

    $valid = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery);
    $check(($valid['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_VERIFIED, 'valid authenticated candidate produces non-executing preflight');
    foreach (['release_authority_granted', 'update_eligible', 'apply_eligible', 'execution_authorized', 'live_mutation_performed'] as $field) {
        $check(($valid[$field] ?? true) === false, 'eligibility remains false: ' . $field);
    }
    $check(($valid['execution_prohibited'] ?? false) === true && count((array)($valid['missing_authorization_controls'] ?? [])) === 3, 'approval, health, and execution gates remain explicit');
    $check(($valid['candidate_package_sha256'] ?? '') === $metadata['package']['sha256'] && ($valid['target_schema_identity'] ?? '') === '2.0.0', 'bound record contains independently derived package and schema evidence');

    $unknownTrust = new InstallationOwnedTrustStore($root . '/unknown-trust', [APP_ROOT]);
    $blockedUnknown = (new TrustedReleaseCoordinatorPreflightService($unknownTrust, $identityStore))->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery);
    $check(($blockedUnknown['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'missing trust blocks');

    $revokedRecord = $trustRecord;
    $revokedRecord['status'] = 'revoked';
    $revokedStore = new InstallationOwnedTrustStore($root . '/revoked-trust', [APP_ROOT]);
    (new DisposableTrustProvisioner($revokedStore))->enroll($revokedRecord, $revokedRecord['fingerprint'], true);
    $blockedRevoked = (new TrustedReleaseCoordinatorPreflightService($revokedStore, $identityStore))->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery);
    $check(($blockedRevoked['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'revoked trust blocks');

    $badSignature = $coordinator->preflight($packagePath, $metadataPath, $statement, str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES), $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery);
    $check(($badSignature['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'invalid signature blocks');

    $contradictoryIdentity = $identityObservations;
    $contradictoryIdentity['schema']['identity'] = 'schema:contradictory';
    $blockedIdentity = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $contradictoryIdentity, $installedState, $migrationBinding, $recovery);
    $check(($blockedIdentity['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'contradictory installed identity blocks');

    $forgedPlan = $migrationBinding;
    $forgedPlan['plan']['plan_fingerprint'] = str_repeat('f', 64);
    $blockedPlan = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $forgedPlan, $recovery);
    $check(($blockedPlan['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'forged migration-plan evidence blocks');

    $failedState = $installedState;
    $failedState['migrations']['failed'] = ['Platform:001_target.sql'];
    $blockedFailed = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $failedState, $migrationBinding, $recovery);
    $check(($blockedFailed['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'failed migration history blocks');

    $blockedRecovery = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, null);
    $check(($blockedRecovery['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'missing recovery evidence blocks');

    $tamperedPath = $releaseDirectory . '/tampered.zip';
    copy($packagePath, $tamperedPath);
    file_put_contents($tamperedPath, 'tampered', FILE_APPEND);
    $blockedTamper = $coordinator->preflight($tamperedPath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery);
    $check(($blockedTamper['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'tampered ZIP blocks');

    $tamperedMetadataPath = $releaseDirectory . '/tampered.json';
    $tamperedMetadata = $metadata;
    $tamperedMetadata['build_id'] = 'fixture:tampered:999';
    file_put_contents($tamperedMetadataPath, json_encode($tamperedMetadata, JSON_THROW_ON_ERROR));
    $blockedSidecar = $coordinator->preflight($packagePath, $tamperedMetadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery);
    $check(($blockedSidecar['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'tampered sidecar blocks');

    $unknownSchema = $migrationBinding;
    $unknownSchema['target_schema'] = 'schema-unknown';
    $blockedSchema = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $unknownSchema, $recovery);
    $check(($blockedSchema['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'unknown target schema blocks');

    $downgradeState = $installedState;
    $downgradeState['product_version'] = '3.0.0';
    $blockedDowngrade = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $downgradeState, $migrationBinding, $recovery);
    $check(($blockedDowngrade['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'incompatible downgrade blocks');

    $forgedApproval = ['approved' => true, 'health' => 'passed'];
    $blockedForgedApproval = $coordinator->preflight($packagePath, $metadataPath, $statement, $signature, $trustRecord['key_id'], '2026-10-16T00:00:00Z', $identityObservations, $installedState, $migrationBinding, $recovery, $forgedApproval);
    $check(($blockedForgedApproval['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_BLOCKED, 'forged approval and health claims are not accepted');
} finally {
    $remove($root);
}

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
