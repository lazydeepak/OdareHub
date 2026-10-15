<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\InstalledPlatformIdentityVerifier;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$hash = str_repeat('a', 64);
$statementFingerprint = str_repeat('b', 64);
$identity = [
    'installation_id' => 'install:fixture-001',
    'product_id' => 'susankhya-os',
    'release_version' => '1.2.3',
    'build_id' => 'build:fixture-001',
    'lane' => 'app',
    'package_sha256' => $hash,
    'release_statement_fingerprint' => $statementFingerprint,
    'signing_key_id' => 'release-key:2026-01',
    'installed_at' => '2026-10-15T12:00:00Z',
    'schema_state_identity' => 'schema:1.2.0',
    'record_version' => InstalledPlatformIdentityVerifier::RECORD_VERSION,
];
$observations = [
    'package' => ['sha256' => $hash],
    'signed_release' => [
        'product_id' => 'susankhya-os',
        'release_version' => '1.2.3',
        'build_id' => 'build:fixture-001',
        'lane' => 'app',
        'statement_fingerprint' => $statementFingerprint,
        'signing_key_id' => 'release-key:2026-01',
    ],
    'schema' => ['identity' => 'schema:1.2.0'],
    'application_registry' => ['build_id' => 'build:fixture-001', 'version' => '1.2.3'],
];
$verify = static fn(array $records, array $evidence): array => (new InstalledPlatformIdentityVerifier())->verify($records, $evidence);

$valid = $verify([$identity], $observations);
$assert($valid['ok'] === true && $valid['state'] === 'verified', 'valid authoritative identity verifies');
$assert(($valid['identity']['build_id'] ?? '') === 'build:fixture-001', 'verified identity preserves immutable build ID');
$assert(($valid['authoritative_source'] ?? '') === 'caller_supplied_installation_identity', 'authority is explicitly installation-owned evidence');
$assert($valid['update_eligible'] === false && $valid['apply_eligible'] === false, 'verification does not enable update eligibility');
$assert(($valid['comparison_evidence']['application_registry_is_authority'] ?? true) === false, 'application registry is comparison-only');

$missing = $verify([], $observations);
$assert($missing['state'] === 'identity_missing', 'missing identity fails with identity_missing');
$duplicate = $verify([$identity, $identity], $observations);
$assert($duplicate['state'] === 'identity_ambiguous', 'duplicate current records fail with identity_ambiguous');

$missingBuild = $identity;
unset($missingBuild['build_id']);
$missingBuildResult = $verify([$missingBuild], $observations);
$assert($missingBuildResult['state'] === 'identity_missing', 'missing build ID fails with identity_missing');

$wrongProduct = $identity;
$wrongProduct['product_id'] = 'other-product';
$assert($verify([$wrongProduct], $observations)['state'] === 'identity_release_mismatch', 'contradictory product identity fails closed');

$wrongPackage = $observations;
$wrongPackage['package']['sha256'] = str_repeat('c', 64);
$assert($verify([$identity], $wrongPackage)['state'] === 'identity_payload_mismatch', 'package checksum mismatch fails closed');

$wrongSignedBuild = $observations;
$wrongSignedBuild['signed_release']['build_id'] = 'build:other';
$assert($verify([$identity], $wrongSignedBuild)['state'] === 'identity_release_mismatch', 'signed release build mismatch fails closed');

$missingSchema = $observations;
$missingSchema['schema'] = [];
$assert($verify([$identity], $missingSchema)['state'] === 'identity_schema_unknown', 'missing schema identity fails closed');

$unsupportedVersion = $identity;
$unsupportedVersion['record_version'] = 'odarehub.installation-identity.v0';
$assert($verify([$unsupportedVersion], $observations)['state'] === 'identity_unavailable', 'unsupported record version fails closed');

$badTimestamp = $identity;
$badTimestamp['installed_at'] = '2026-10-15 12:00:00';
$assert($verify([$badTimestamp], $observations)['state'] === 'identity_unavailable', 'malformed installation timestamp fails closed');

$badObservation = $observations;
$badObservation['signed_release']['statement_fingerprint'] = 'not-a-fingerprint';
$assert($verify([$identity], $badObservation)['state'] === 'identity_unavailable', 'malformed signed observation fails closed');

$registryFallback = $observations;
$registryFallback['application_registry']['build_id'] = 'build:registry-only';
$assert($verify([$identity], $registryFallback)['state'] === 'identity_release_mismatch', 'contradictory registry evidence is not silently reconciled');

$registryOnly = $verify([], [
    'application_registry' => ['build_id' => 'build:registry-only', 'version' => '1.2.3'],
]);
$assert($registryOnly['state'] === 'identity_missing', 'application registry cannot provide authoritative fallback');
$assert($registryOnly['update_eligible'] === false, 'fallback attempt cannot enable eligibility');

$unknownField = $identity;
$unknownField['generated_at'] = '2026-10-15T12:00:00Z';
$assert($verify([$unknownField], $observations)['state'] === 'identity_unavailable', 'malformed extra identity field fails closed');

$schemaMismatch = $observations;
$schemaMismatch['schema']['identity'] = 'schema:other';
$assert($verify([$identity], $schemaMismatch)['state'] === 'identity_schema_unknown', 'contradictory schema observation fails closed');

echo "Installed platform identity verifier: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
