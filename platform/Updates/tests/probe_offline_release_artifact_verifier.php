<?php
declare(strict_types=1);

/** OH-AUTO1-065 disposable exact-artifact verification probe. */

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$tmp = rtrim(sys_get_temp_dir(), '/') . '/oh-auto1-065-' . bin2hex(random_bytes(6));
$channel = $tmp . '/channel';
$releaseDirectory = $channel . '/releases';
mkdir($releaseDirectory, 0700, true);
$packagePath = $releaseDirectory . '/susankhya-os-1.2.3-fixture-oh065.zip';
$metadataPath = $releaseDirectory . '/susankhya-os-1.2.3-fixture-oh065.json';
$createdAt = '2026-10-15T00:00:00Z';
$buildId = 'fixture:oh065:001';
$channelRecord = [];

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        $removeTree($entry->getPathname());
    }
    rmdir($path);
};

$makePackage = static function (string $path, string $buildId, string $createdAt): void {
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('fixture ZIP could not be opened');
    }
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $root) {
        $zip->addFromString($root . '/runtime.txt', $root);
    }
    $zip->addFromString('composer.json', json_encode(['require' => []], JSON_THROW_ON_ERROR));
    $zip->addFromString('composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []], JSON_THROW_ON_ERROR));
    $zip->addFromString('release_metadata.json', json_encode([
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.2.3',
        'build_id' => $buildId,
        'channel' => 'local',
        'lane' => 'app',
        'created_at' => $createdAt,
        'package' => ['filename' => basename($path), 'size_bytes' => 0, 'sha256' => null],
        'compatibility' => [],
        'readiness' => ['status' => 'passed', 'evidence' => []],
    ], JSON_THROW_ON_ERROR));
    $zip->close();
};

$makeMetadata = static function (string $path, string $packagePath, string $buildId, string $createdAt): array {
    $metadata = [
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.2.3',
        'build_id' => $buildId,
        'channel' => 'local',
        'lane' => 'app',
        'created_at' => $createdAt,
        'package' => [
            'filename' => basename($packagePath),
            'size_bytes' => filesize($packagePath),
            'sha256' => hash_file('sha256', $packagePath),
        ],
        'compatibility' => [],
        'readiness' => ['status' => 'passed', 'evidence' => []],
        'migration' => ['warnings' => [], 'requires_backup' => false],
        'assets' => ['regeneration_required' => false],
        'release_notes' => '',
        'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
    ];
    file_put_contents($path, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
    return $metadata;
};

$canonicalizer = new Platform\Updates\ReleaseStatementCanonicalizer();
$keyPair = sodium_crypto_sign_seed_keypair(str_repeat("\x22", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$secretKey = sodium_crypto_sign_secretkey($keyPair);
$publicKey = sodium_crypto_sign_publickey($keyPair);
$trustRecord = [
    'key_id' => 'fixture-oh065-key',
    'algorithm' => 'ed25519',
    'public_key' => $publicKey,
    'fingerprint' => hash('sha256', $publicKey),
    'status' => 'active',
    'not_before' => '2026-01-01T00:00:00Z',
    'not_after' => null,
];

try {
    $makePackage($packagePath, $buildId, $createdAt);
    $metadata = $makeMetadata($metadataPath, $packagePath, $buildId, $createdAt);
    $statement = $canonicalizer->canonicalize([
        'product_id' => 'susankhya-os',
        'release_version' => '1.2.3',
        'build_id' => $buildId,
        'lane' => 'app',
        'package_sha256' => $metadata['package']['sha256'],
        'package_bytes' => (string)$metadata['package']['size_bytes'],
        'metadata_sha256' => hash_file('sha256', $metadataPath),
        'minimum_supported_schema' => '1.0.0',
        'created_at' => $createdAt,
    ]);
    $signature = sodium_crypto_sign_detached($statement, $secretKey);
    $channelRecord = [
        'release_version' => '1.2.3',
        'build_id' => $buildId,
        'metadata' => 'releases/' . basename($metadataPath),
        'package' => 'releases/' . basename($packagePath),
        'sha256' => hash_file('sha256', $packagePath),
        'size_bytes' => filesize($packagePath),
    ];
    $verify = static function (string $package, string $sidecar, string $statementBytes = '', ?string $sig = null, ?array $records = null, ?array $channel = null) use ($canonicalizer, $trustRecord, &$statement, &$signature, &$channelRecord): array {
        $statementBytes = $statementBytes !== '' ? $statementBytes : $statement;
        $sig ??= $signature;
        $records ??= [$trustRecord];
        $channel ??= $channelRecord;
        return (new Platform\Updates\OfflineReleaseArtifactVerifier($canonicalizer))->verify($package, $sidecar, $statementBytes, $sig, 'fixture-oh065-key', $records, '2026-10-16T00:00:00Z', $channel);
    };

    $valid = $verify($packagePath, $metadataPath);
    $check(($valid['ok'] ?? false) === true && ($valid['state'] ?? null) === 'verified', 'valid signature binds exact ZIP and sidecar bytes');
    $check(($valid['artifact']['package_sha256'] ?? null) === hash_file('sha256', $packagePath), 'reported package hash matches exact bytes');
    $check(($valid['artifact']['metadata_sha256'] ?? null) === hash_file('sha256', $metadataPath), 'reported metadata hash matches exact sidecar bytes');
    $check(($valid['inspection']['ok'] ?? false) === true, 'existing strict package inspection passes');
    $wrongChannel = $channelRecord;
    $wrongChannel['build_id'] = 'fixture:oh065:wrong-channel';
    $check(($verify($packagePath, $metadataPath, '', null, null, $wrongChannel)['ok'] ?? true) === false, 'contradictory channel identity is rejected');

    file_put_contents($packagePath, file_get_contents($packagePath) . 'x');
    $packageChanged = $verify($packagePath, $metadataPath);
    $check(($packageChanged['ok'] ?? true) === false, 'modified package is rejected');
    $makePackage($packagePath, $buildId, $createdAt);
    $makeMetadata($metadataPath, $packagePath, $buildId, $createdAt);
    $channelRecord['sha256'] = hash_file('sha256', $packagePath);
    $channelRecord['size_bytes'] = filesize($packagePath);
    $statement = $canonicalizer->canonicalize([
        'product_id' => 'susankhya-os', 'release_version' => '1.2.3', 'build_id' => $buildId, 'lane' => 'app',
        'package_sha256' => hash_file('sha256', $packagePath), 'package_bytes' => (string)filesize($packagePath),
        'metadata_sha256' => hash_file('sha256', $metadataPath), 'minimum_supported_schema' => '1.0.0', 'created_at' => $createdAt,
    ]);
    $signature = sodium_crypto_sign_detached($statement, $secretKey);

    $metadataChanged = json_decode((string)file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
    $metadataChanged['build_id'] = 'fixture:oh065:changed';
    file_put_contents($metadataPath, json_encode($metadataChanged, JSON_PRETTY_PRINT) . "\n");
    $check(($verify($packagePath, $metadataPath)['ok'] ?? true) === false, 'modified sidecar is rejected');
    $makeMetadata($metadataPath, $packagePath, $buildId, $createdAt);

    $sizeMismatch = json_decode((string)file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
    $sizeMismatch['package']['size_bytes']++;
    file_put_contents($metadataPath, json_encode($sizeMismatch, JSON_PRETTY_PRINT) . "\n");
    $check(($verify($packagePath, $metadataPath)['ok'] ?? true) === false, 'package size mismatch is rejected');
    $makeMetadata($metadataPath, $packagePath, $buildId, $createdAt);

    $wrongBuildStatement = $canonicalizer->canonicalize([
        'product_id' => 'susankhya-os', 'release_version' => '1.2.3', 'build_id' => 'fixture:oh065:wrong', 'lane' => 'app',
        'package_sha256' => hash_file('sha256', $packagePath), 'package_bytes' => (string)filesize($packagePath),
        'metadata_sha256' => hash_file('sha256', $metadataPath), 'minimum_supported_schema' => '1.0.0', 'created_at' => $createdAt,
    ]);
    $check(($verify($packagePath, $metadataPath, $wrongBuildStatement)['ok'] ?? true) === false, 'wrong signed build ID is rejected');
    $check(($verify($packagePath, $metadataPath, $statement, str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES))['state'] ?? null) === 'invalid_signature', 'invalid signature is reported');
    $check(($verify($packagePath, $metadataPath, $statement, $signature, [array_replace($trustRecord, ['key_id' => 'other-key'])])['state'] ?? null) === 'untrusted_key', 'unknown key is reported');
    $check(($verify($packagePath, $metadataPath, $statement, $signature, [array_replace($trustRecord, ['status' => 'revoked'])])['state'] ?? null) === 'revoked_key', 'revoked key is reported');
    $check(($verify($packagePath, $metadataPath, $statement, $signature, [])['state'] ?? null) === 'untrusted_key', 'missing trust records are blocked');

    $check(($verify($packagePath . '/../' . basename($packagePath), $metadataPath)['ok'] ?? true) === false, 'path traversal is rejected');
    $outside = $tmp . '/outside.zip';
    copy($packagePath, $outside);
    $symlink = $releaseDirectory . '/escape.zip';
    if (function_exists('symlink') && @symlink($outside, $symlink)) {
        $check(($verify($symlink, $metadataPath)['ok'] ?? true) === false, 'symlink artifact path is rejected');
    } else {
        $check(true, 'symlink fixture unavailable is treated as non-applicable');
    }

    $mutation = new Platform\Updates\OfflineReleaseArtifactVerifier($canonicalizer, null, static function () use ($metadataPath): void {
        file_put_contents($metadataPath, file_get_contents($metadataPath) . "\n");
    });
    $mutated = $mutation->verify($packagePath, $metadataPath, $statement, $signature, 'fixture-oh065-key', [$trustRecord], '2026-10-16T00:00:00Z');
    $check(($mutated['ok'] ?? true) === false && (($mutated['diagnostics'][0]['code'] ?? '') === 'RAV-ARTIFACT-MUTATED'), 'artifact mutation during verification fails closed');

    $artifact = ['release_version' => '1.2.3', 'build_id' => $buildId, 'package_sha256' => hash_file('sha256', $packagePath), 'package_size_bytes' => filesize($packagePath)];
    $readiness = ['status' => 'passed', 'authenticity' => 'authorized-verifier', 'evidence' => []];
    foreach (['package_contract', 'architecture_gates', 'deployment_readiness'] as $id) {
        $readiness['evidence'][] = ['id' => $id, 'status' => 'passed', 'evidence' => ['fixture' => true], 'artifact' => $artifact];
    }
    $spoof = (new Platform\Updates\ReleaseReadinessEvidenceService())->validate($readiness, $artifact);
    $check(($spoof['ok'] ?? false) === true && ($spoof['eligible'] ?? false) === true, 'readiness authenticity string alone reproduces eligible spoof');
    $check(($valid['ok'] ?? false) === true && ($spoof['eligible'] ?? false) === true && ($valid['state'] ?? null) === 'verified', 'artifact verifier remains separate from readiness eligibility');
} catch (Throwable $exception) {
    $check(false, 'probe completed without exception: ' . $exception->getMessage());
} finally {
    $removeTree($tmp);
}

echo "Offline release artifact verifier probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
