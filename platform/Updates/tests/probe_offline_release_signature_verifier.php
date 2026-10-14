<?php
declare(strict_types=1);

/** OH-AUTO1-064 pure offline canonical statement/signature probe. */

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$canonicalizer = new Platform\Updates\ReleaseStatementCanonicalizer();
$verifier = new Platform\Updates\OfflineReleaseSignatureVerifier($canonicalizer);
$fields = [
    'product_id' => 'susankhya-os',
    'release_version' => '1.2.3',
    'build_id' => 'fixture:oh064:001',
    'lane' => 'app',
    'package_sha256' => str_repeat('a', 64),
    'package_bytes' => '12345',
    'metadata_sha256' => str_repeat('b', 64),
    'minimum_supported_schema' => '1.0.0',
    'created_at' => '2026-10-15T00:00:00Z',
];
$statement = $canonicalizer->canonicalize($fields);
$keyPair = sodium_crypto_sign_seed_keypair(str_repeat("\x11", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$secretKey = sodium_crypto_sign_secretkey($keyPair);
$publicKey = sodium_crypto_sign_publickey($keyPair);
$signature = sodium_crypto_sign_detached($statement, $secretKey);
$keyRecord = static function (string $status = 'active', string $notBefore = '2026-01-01T00:00:00Z', ?string $notAfter = null) use ($publicKey): array {
    return [
        'key_id' => 'fixture-key-1',
        'algorithm' => 'ed25519',
        'public_key' => $publicKey,
        'fingerprint' => hash('sha256', $publicKey),
        'status' => $status,
        'not_before' => $notBefore,
        'not_after' => $notAfter,
    ];
};
$binding = [
    'package_sha256' => $fields['package_sha256'],
    'package_bytes' => $fields['package_bytes'],
    'metadata_sha256' => $fields['metadata_sha256'],
];

$check($canonicalizer->parse($statement)['ok'] === true, 'canonical statement parses');
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z', $binding)['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFIED, 'valid Ed25519 signature verifies');
$check(($verifier->verify($statement . 'x', $signature, 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::STATEMENT_MISMATCH, 'modified statement bytes are rejected as noncanonical');

$changedPackageFields = $fields;
$changedPackageFields['package_sha256'] = str_repeat('c', 64);
$changedPackageStatement = $canonicalizer->canonicalize($changedPackageFields);
$check(($verifier->verify($changedPackageStatement, $signature, 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z', $binding)['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::STATEMENT_MISMATCH, 'modified package checksum binding is rejected');

$changedMetadataFields = $fields;
$changedMetadataFields['metadata_sha256'] = str_repeat('d', 64);
$check(($verifier->verify($canonicalizer->canonicalize($changedMetadataFields), $signature, 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z', $binding)['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::STATEMENT_MISMATCH, 'modified metadata checksum binding is rejected');
$check(($verifier->verify($statement, str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES), 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::INVALID_SIGNATURE, 'invalid signature is rejected');
$check(($verifier->verify($statement, $signature, 'unknown-key', [$keyRecord()], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::UNTRUSTED_KEY, 'unknown key is rejected');
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$keyRecord('revoked')], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::REVOKED_KEY, 'revoked key is rejected');
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$keyRecord('active', '2027-01-01T00:00:00Z')], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::EXPIRED_OR_NOT_YET_VALID, 'not-yet-valid key is rejected');
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$keyRecord('active', '2026-01-01T00:00:00Z', '2026-10-01T00:00:00Z')], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::EXPIRED_OR_NOT_YET_VALID, 'expired key is rejected');
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$keyRecord(), $keyRecord()], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'duplicate trust key is rejected fail-closed');
$malformedTrust = $keyRecord();
$malformedTrust['algorithm'] = 'rsa';
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$malformedTrust], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'malformed trust record is rejected fail-closed');
$wrongFingerprint = $keyRecord();
$wrongFingerprint['fingerprint'] = str_repeat('0', 64);
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$wrongFingerprint], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'trust fingerprint mismatch is rejected');
$wrongMessage = $canonicalizer->canonicalize(array_replace($fields, ['build_id' => 'fixture:oh064:002']));
$check(($verifier->verify($wrongMessage, $signature, 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::INVALID_SIGNATURE, 'signature over wrong canonical message is rejected');

$reordered = str_replace("product_id=susankhya-os\nrelease_version=1.2.3\n", "release_version=1.2.3\nproduct_id=susankhya-os\n", $statement);
$check(($canonicalizer->parse($reordered)['ok'] ?? true) === false, 'noncanonical field ordering is rejected');
$missing = preg_replace('/^lane=.*\n/m', '', $statement);
$check(($canonicalizer->parse((string)$missing)['ok'] ?? true) === false, 'missing statement field is rejected');
$duplicate = str_replace("lane=app\n", "lane=app\nlane=app\n", $statement);
$check(($canonicalizer->parse($duplicate)['ok'] ?? true) === false, 'duplicated statement field is rejected');
$check(($canonicalizer->parse(str_replace("build_id=fixture:oh064:001", "build_id=fixture=inject", $statement))['ok'] ?? true) === false, 'delimiter injection is rejected');
$check(($canonicalizer->parse("\xFF" . $statement)['ok'] ?? true) === false, 'invalid UTF-8 is rejected');

$wrongProduct = str_replace('product_id=susankhya-os', 'product_id=other-product', $statement);
$check(($canonicalizer->parse($wrongProduct)['ok'] ?? true) === false, 'wrong product identity is rejected');
$wrongLane = str_replace('lane=app', 'lane=runtime', $statement);
$check(($canonicalizer->parse($wrongLane)['ok'] ?? true) === false, 'wrong release lane is rejected');
$check(($verifier->verify($statement, null, null, [], '2026-10-16T00:00:00Z')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::UNSIGNED, 'unsigned statement is blocked');
$check(($verifier->verify($statement, $signature, 'fixture-key-1', [$keyRecord()], 'not-a-time')['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'invalid verification time is blocked');
$unavailable = (new Platform\Updates\OfflineReleaseSignatureVerifier($canonicalizer, false))->verify($statement, $signature, 'fixture-key-1', [$keyRecord()], '2026-10-16T00:00:00Z');
$check(($unavailable['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'missing cryptographic capability is blocked');

echo "Offline release signature verifier probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
