<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\LocalReleaseChannelPreviewService;

$passed = 0;
$failed = 0;

function preview_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function preview_installed_state(string $version = '0.5.0'): array
{
    return [
        'product_id' => 'susankhya-os',
        'product_version' => $version,
        'platform' => ['product_id' => 'susankhya-os', 'version' => $version, 'build_id' => 'git:installed'],
        'apps' => [],
        'modules' => [],
        'schema' => ['version' => '1.0.0'],
        'migrations' => ['pending' => [], 'failed' => [], 'order' => []],
    ];
}

function preview_rm_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        preview_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

function write_preview_channel(string $root, array $overrides = []): array
{
    mkdir($root . '/releases', 0700, true);
    $package = $root . '/releases/susankhya-os-1.0.0-build.zip';
    if (($overrides['raw_package'] ?? null) !== null) {
        file_put_contents($package, (string)$overrides['raw_package']);
    } else {
        $zip = new ZipArchive();
        $zip->open($package, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $omit = array_fill_keys((array)($overrides['omit'] ?? []), true);
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $runtimeRoot) {
        if (!isset($omit[$runtimeRoot])) {
            $zip->addFromString($runtimeRoot . '/probe.txt', $runtimeRoot);
        }
    }
    if (!isset($omit['composer.json'])) {
        $zip->addFromString('composer.json', json_encode(['require' => []]));
    }
    if (!isset($omit['composer.lock'])) {
        $zip->addFromString('composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));
    }
    $internalMetadata = [
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.0.0',
        'build_id' => 'git:preview',
        'channel' => 'local',
        'lane' => 'app',
        'created_at' => '2026-08-19T00:00:00Z',
        'package' => ['filename' => basename($package), 'sha256' => null, 'size_bytes' => null],
    ];
    $internalMetadata = array_replace_recursive($internalMetadata, $overrides['internal_metadata'] ?? []);
    if (!isset($omit['release_metadata.json'])) {
        $zip->addFromString('release_metadata.json', json_encode($internalMetadata));
    }
    foreach ((array)($overrides['extra_entries'] ?? []) as $entry => $contents) {
        $zip->addFromString((string)$entry, (string)$contents);
    }
    if (($overrides['symlink_entry'] ?? false) === true && method_exists($zip, 'setExternalAttributesName')) {
        $zip->addFromString('app/symlink', 'target');
        $zip->setExternalAttributesName('app/symlink', ZipArchive::OPSYS_UNIX, (0120000 | 0777) << 16);
    }
    $zip->close();
    }
    $sha = hash_file('sha256', $package) ?: '';
    $size = filesize($package) ?: 0;

    $metadata = array_replace_recursive([
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.0.0',
        'build_id' => 'git:preview',
        'channel' => 'local',
        'lane' => 'app',
        'created_at' => '2026-08-19T00:00:00Z',
        'package' => [
            'filename' => basename($package),
            'sha256' => $sha,
            'size_bytes' => $size,
        ],
        'compatibility' => [
            'minimum_product_version' => '0.5.0',
            'maximum_product_version' => null,
            'core' => ['minimum' => '0.0.0'],
            'apps' => [],
            'modules' => [],
            'schema' => ['minimum' => null, 'target' => null],
            'php' => ['minimum' => '8.1.0'],
            'php_extensions' => ['json'],
        ],
        'migration' => ['warnings' => [], 'requires_backup' => true],
        'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
    ], $overrides['metadata'] ?? []);
    $artifact = [
        'release_version' => '1.0.0',
        'build_id' => 'git:preview',
        'package_sha256' => $sha,
        'package_size_bytes' => $size,
    ];
    $metadata['readiness'] = $overrides['readiness'] ?? [
        'status' => 'passed',
        'authenticity' => 'disposable-fixture-unverified',
        'evidence' => array_map(
            static fn(string $id): array => [
                'id' => $id,
                'status' => 'passed',
                'artifact' => $artifact,
                'evidence' => $id === 'package_contract'
                    ? ['source' => 'disposable-fixture', 'observed' => true]
                    : [
                        'source' => 'disposable-fixture',
                        'executed' => true,
                        'command' => $id === 'architecture_gates'
                            ? 'bash scripts/architecture/run_architecture_gates.sh'
                            : 'bash scripts/system/check_deployment_readiness.sh',
                        'exit_code' => 0,
                        'output_sha256' => str_repeat('a', 64),
                        'source_revision' => 'git:preview',
                        'working_tree_status_sha256' => str_repeat('b', 64),
                    ],
            ],
            ['package_contract', 'architecture_gates', 'deployment_readiness']
        ),
    ];
    file_put_contents($root . '/releases/susankhya-os-1.0.0-build.json', json_encode($metadata, JSON_PRETTY_PRINT));

    $channel = array_replace_recursive([
        'schema_version' => 'susankhya.local-channel.v1',
        'channel' => 'local',
        'product_id' => 'susankhya-os',
        'generated_at' => '2026-08-19T00:00:00Z',
        'lanes' => [
            'app' => [
                'current' => [
                    'release_version' => '1.0.0',
                    'build_id' => 'git:preview',
                    'metadata' => 'releases/susankhya-os-1.0.0-build.json',
                    'package' => 'releases/susankhya-os-1.0.0-build.zip',
                    'sha256' => $sha,
                    'size_bytes' => $size,
                ],
            ],
            'runtime' => ['current' => null],
            'support' => ['current' => null],
        ],
    ], $overrides['channel'] ?? []);
    file_put_contents($root . '/channel.json', json_encode($channel, JSON_PRETTY_PRINT));

    return ['package' => $package, 'sha' => $sha, 'size' => $size];
}

function write_preview_recovery(string $root, string $releaseVersion = '0.5.0'): string
{
    mkdir($root, 0700, true);
    $database = $root . '/database.sql.gz';
    $filesystem = $root . '/filesystem.zip';
    file_put_contents($database, gzencode('-- preview recovery database'));
    file_put_contents($filesystem, 'preview recovery filesystem');
    $hash = str_repeat('a', 64);
    $metadata = [
        'schema_version' => 'susankhya.recovery-point.v1',
        'recovery_point_id' => 'rp-preview-' . bin2hex(random_bytes(4)),
        'created_at' => '2026-08-19T00:00:00Z',
        'created_by' => 'probe',
        'reason' => 'update_apply',
        'installation' => [
            'product_id' => 'susankhya-os',
            'release_version' => $releaseVersion,
            'build_id' => 'git:installed',
            'app_manifest_checksums' => [],
            'module_manifest_checksums' => [],
            'schema_state' => [],
            'migration_state' => [],
        ],
        'database' => [
            'provider' => 'mysqldump',
            'provider_version' => 'probe',
            'identity' => ['host_label' => 'probe', 'port' => 3306, 'database' => 'probe'],
            'payload' => ['path' => basename($database), 'sha256' => hash_file('sha256', $database), 'size_bytes' => filesize($database)],
        ],
        'filesystem' => [
            'provider' => 'ziparchive',
            'payload' => ['path' => basename($filesystem), 'sha256' => hash_file('sha256', $filesystem), 'size_bytes' => filesize($filesystem)],
            'preservation_inventory_sha256' => $hash,
        ],
    ];
    file_put_contents($root . '/recovery-point.json', json_encode($metadata, JSON_PRETTY_PRINT));
    file_put_contents($root . '/rehearsal.json', json_encode([
        'schema_version' => 'susankhya.rehearsal.v1',
        'recovery_point_id' => $metadata['recovery_point_id'],
        'ok' => true,
        'final_state' => 'restored',
        'database' => ['ok' => true],
        'filesystem' => ['ok' => true],
    ], JSON_PRETTY_PRINT));
    return $root;
}

/** @return array<string,mixed> */
function preview_authentication_input(string $root, Platform\Updates\ReleaseStatementCanonicalizer $canonicalizer, string $secretKey, array $trustRecord, ?string $detachedSignature = null, ?string $keyId = null): array
{
    $package = $root . '/releases/susankhya-os-1.0.0-build.zip';
    $metadata = $root . '/releases/susankhya-os-1.0.0-build.json';
    $sidecar = json_decode((string)file_get_contents($metadata), true, 512, JSON_THROW_ON_ERROR);
    $statement = $canonicalizer->canonicalize([
        'product_id' => 'susankhya-os',
        'release_version' => $sidecar['release_version'],
        'build_id' => $sidecar['build_id'],
        'lane' => $sidecar['lane'],
        'package_sha256' => hash_file('sha256', $package),
        'package_bytes' => (string)filesize($package),
        'metadata_sha256' => hash_file('sha256', $metadata),
        'minimum_supported_schema' => '1.0.0',
        'created_at' => $sidecar['created_at'],
    ]);
    return [
        'statement_bytes' => $statement,
        'detached_signature' => $detachedSignature ?? sodium_crypto_sign_detached($statement, $secretKey),
        'key_id' => $keyId ?? $trustRecord['key_id'],
        'trusted_keys' => [$trustRecord],
        'verification_timestamp' => '2026-10-16T00:00:00Z',
    ];
}

function refresh_preview_package_binding(string $root): void
{
    $package = $root . '/releases/susankhya-os-1.0.0-build.zip';
    $metadataPath = $root . '/releases/susankhya-os-1.0.0-build.json';
    $metadata = json_decode((string)file_get_contents($metadataPath), true, 512, JSON_THROW_ON_ERROR);
    $metadata['package']['sha256'] = hash_file('sha256', $package);
    $metadata['package']['size_bytes'] = filesize($package);
    file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT));
    $channelPath = $root . '/channel.json';
    $channel = json_decode((string)file_get_contents($channelPath), true, 512, JSON_THROW_ON_ERROR);
    $channel['lanes']['app']['current']['sha256'] = $metadata['package']['sha256'];
    $channel['lanes']['app']['current']['size_bytes'] = $metadata['package']['size_bytes'];
    file_put_contents($channelPath, json_encode($channel, JSON_PRETTY_PRINT));
}

$tmp = sys_get_temp_dir() . '/susankhya-local-channel-preview-probe-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$productionChannelExistsBefore = file_exists(APP_ROOT . '/storage/update-channels');

try {
    $service = new LocalReleaseChannelPreviewService();
    $channel = $tmp . '/channel';
    write_preview_channel($channel);

    $canonicalizer = new Platform\Updates\ReleaseStatementCanonicalizer();
    $keyPair = sodium_crypto_sign_seed_keypair(str_repeat("\x31", SODIUM_CRYPTO_SIGN_SEEDBYTES));
    $secretKey = sodium_crypto_sign_secretkey($keyPair);
    $publicKey = sodium_crypto_sign_publickey($keyPair);
    $trustRecord = [
        'key_id' => 'preview-fixture-key',
        'algorithm' => 'ed25519',
        'public_key' => $publicKey,
        'fingerprint' => hash('sha256', $publicKey),
        'status' => 'active',
        'not_before' => '2026-01-01T00:00:00Z',
        'not_after' => null,
    ];
    $validAuthenticity = preview_authentication_input($channel, $canonicalizer, $secretKey, $trustRecord);

    $preview = $service->preview($channel, preview_installed_state());
    preview_assert($preview['ok'] === true, 'valid local channel preview passes package checks');
    preview_assert(($preview['authenticity']['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFICATION_UNAVAILABLE, 'preview reports missing authenticity inputs as unavailable');
    preview_assert($preview['apply_eligible'] === false, 'valid preview remains apply-ineligible without recovery evidence');
    preview_assert(in_array('An authoritative recovery-point directory is required before apply.', $preview['apply_blockers'], true), 'preview reports missing recovery evidence blocker');

    $fake = $service->preview($channel, preview_installed_state(), null);
    preview_assert($fake['apply_eligible'] === false, 'missing recovery directory cannot be replaced by booleans');

    $recovery = write_preview_recovery($tmp . '/valid-recovery');
    $eligible = $service->preview($channel, preview_installed_state(), $recovery);
    preview_assert($eligible['ok'] === true && $eligible['apply_eligible'] === false, 'unattested readiness keeps preview apply-ineligible despite recovery evidence');

    $authenticated = $service->preview($channel, preview_installed_state(), null, $validAuthenticity);
    preview_assert(($authenticated['authenticity']['verified'] ?? false) === true, 'valid signed release is reported as verified');
    preview_assert(($authenticated['authenticity']['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::VERIFIED, 'valid signed release reports verified state');
    preview_assert($authenticated['apply_eligible'] === false, 'verified signature alone never grants apply eligibility');

    $unsignedInput = $validAuthenticity;
    $unsignedInput['detached_signature'] = null;
    $unsigned = $service->preview($channel, preview_installed_state(), null, $unsignedInput);
    preview_assert(($unsigned['authenticity']['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::UNSIGNED, 'unsigned release is reported without changing preview compatibility');

    $invalidSignatureInput = $validAuthenticity;
    $invalidSignatureInput['detached_signature'] = str_repeat('x', SODIUM_CRYPTO_SIGN_BYTES);
    $invalidSignature = $service->preview($channel, preview_installed_state(), null, $invalidSignatureInput);
    preview_assert(($invalidSignature['authenticity']['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::INVALID_SIGNATURE, 'invalid signature is reported');

    $unknownInput = $validAuthenticity;
    $unknownInput['key_id'] = 'unknown-preview-key';
    $unknown = $service->preview($channel, preview_installed_state(), null, $unknownInput);
    preview_assert(($unknown['authenticity']['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::UNTRUSTED_KEY, 'unknown signing key is reported');

    $revokedRecord = array_replace($trustRecord, ['status' => 'revoked']);
    $revokedInput = $validAuthenticity;
    $revokedInput['trusted_keys'] = [$revokedRecord];
    $revoked = $service->preview($channel, preview_installed_state(), null, $revokedInput);
    preview_assert(($revoked['authenticity']['state'] ?? null) === Platform\Updates\OfflineReleaseSignatureVerifier::REVOKED_KEY, 'revoked signing key is reported');

    $forgedReadinessChannel = $tmp . '/forged-readiness';
    write_preview_channel($forgedReadinessChannel, ['readiness' => ['status' => 'passed', 'authenticity' => 'authorized-verifier', 'evidence' => []]]);
    $forged = $service->preview($forgedReadinessChannel, preview_installed_state(), null);
    preview_assert(($forged['release']['readiness']['eligible'] ?? true) === false, 'forged readiness authenticity remains ineligible');
    preview_assert(($forged['authenticity']['verified'] ?? true) === false, 'forged readiness metadata is not authenticity evidence');

    $failedReadinessChannel = $tmp . '/failed-readiness';
    write_preview_channel($failedReadinessChannel, ['readiness' => ['status' => 'failed', 'evidence' => []]]);
    $failedReadinessAuth = preview_authentication_input($failedReadinessChannel, $canonicalizer, $secretKey, $trustRecord);
    $failedReadiness = $service->preview($failedReadinessChannel, preview_installed_state(), null, $failedReadinessAuth);
    preview_assert($failedReadiness['ok'] === false && ($failedReadiness['authenticity']['verified'] ?? false) === true, 'valid signature with failed readiness stays blocked');

    $failedCompatibilityChannel = $tmp . '/failed-compatibility';
    write_preview_channel($failedCompatibilityChannel, ['metadata' => ['compatibility' => ['minimum_product_version' => '9.0.0']]]);
    $failedCompatibilityAuth = preview_authentication_input($failedCompatibilityChannel, $canonicalizer, $secretKey, $trustRecord);
    $failedCompatibility = $service->preview($failedCompatibilityChannel, preview_installed_state(), null, $failedCompatibilityAuth);
    preview_assert($failedCompatibility['ok'] === false && ($failedCompatibility['authenticity']['verified'] ?? false) === true, 'valid signature with failed compatibility stays blocked');

    $modifiedPackageChannel = $tmp . '/modified-package';
    write_preview_channel($modifiedPackageChannel);
    $modifiedPackageAuth = preview_authentication_input($modifiedPackageChannel, $canonicalizer, $secretKey, $trustRecord);
    file_put_contents($modifiedPackageChannel . '/releases/susankhya-os-1.0.0-build.zip', 'modified-package');
    refresh_preview_package_binding($modifiedPackageChannel);
    $modifiedPackage = $service->preview($modifiedPackageChannel, preview_installed_state(), null, $modifiedPackageAuth);
    preview_assert(($modifiedPackage['authenticity']['verified'] ?? true) === false, 'modified package is rejected by authenticity binding');

    $modifiedSidecarChannel = $tmp . '/modified-sidecar';
    write_preview_channel($modifiedSidecarChannel);
    $modifiedSidecarAuth = preview_authentication_input($modifiedSidecarChannel, $canonicalizer, $secretKey, $trustRecord);
    $modifiedSidecarPath = $modifiedSidecarChannel . '/releases/susankhya-os-1.0.0-build.json';
    $modifiedSidecar = json_decode((string)file_get_contents($modifiedSidecarPath), true, 512, JSON_THROW_ON_ERROR);
    $modifiedSidecar['build_id'] = 'git:modified-sidecar';
    file_put_contents($modifiedSidecarPath, json_encode($modifiedSidecar, JSON_PRETTY_PRINT));
    $modifiedSidecar = $service->preview($modifiedSidecarChannel, preview_installed_state(), null, $modifiedSidecarAuth);
    preview_assert(($modifiedSidecar['authenticity']['verified'] ?? true) === false, 'modified sidecar is rejected by authenticity binding');

    $emptyReadinessChannel = $tmp . '/empty-readiness';
    write_preview_channel($emptyReadinessChannel, ['readiness' => ['status' => 'passed', 'evidence' => []]]);
    $emptyReadiness = $service->preview($emptyReadinessChannel, preview_installed_state(), $recovery);
    preview_assert($emptyReadiness['ok'] === false, 'preview rejects empty readiness evidence');

    $injectedReadinessChannel = $tmp . '/injected-readiness';
    write_preview_channel($injectedReadinessChannel, ['readiness' => ['status' => 'passed', 'passed' => true, 'evidence' => []]]);
    $injectedReadiness = $service->preview($injectedReadinessChannel, preview_installed_state(), $recovery);
    preview_assert($injectedReadiness['ok'] === false, 'preview rejects caller-injected readiness success');

    $badHashChannel = $tmp . '/bad-hash';
    write_preview_channel($badHashChannel, ['metadata' => ['package' => ['sha256' => str_repeat('b', 64)]]]);
    $badHash = $service->preview($badHashChannel, preview_installed_state(), $recovery);
    preview_assert($badHash['ok'] === false, 'package checksum mismatch fails preview');

    $badSizeChannel = $tmp . '/bad-size';
    write_preview_channel($badSizeChannel, ['metadata' => ['package' => ['size_bytes' => 1]]]);
    $badSize = $service->preview($badSizeChannel, preview_installed_state());
    preview_assert($badSize['ok'] === false, 'package byte-size mismatch fails preview');

    $oldInstallChannel = $tmp . '/old-install';
    write_preview_channel($oldInstallChannel);
    $old = $service->preview($oldInstallChannel, preview_installed_state('0.4.9'));
    preview_assert($old['ok'] === false, 'installed version below minimum fails preview');

    $unsafePathChannel = $tmp . '/unsafe-path';
    write_preview_channel($unsafePathChannel, ['channel' => ['lanes' => ['app' => ['current' => ['metadata' => '../escape.json']]]]]);
    $unsafe = $service->preview($unsafePathChannel, preview_installed_state());
    preview_assert($unsafe['ok'] === false, 'unsafe channel metadata path fails preview');

    $missingLockChannel = $tmp . '/missing-lock';
    write_preview_channel($missingLockChannel, ['omit' => ['composer.lock']]);
    $missingLock = $service->preview($missingLockChannel, preview_installed_state());
    preview_assert($missingLock['ok'] === false, 'missing composer.lock fails preview');

    $missingRootChannel = $tmp . '/missing-root';
    write_preview_channel($missingRootChannel, ['omit' => ['vendor']]);
    $missingRoot = $service->preview($missingRootChannel, preview_installed_state());
    preview_assert($missingRoot['ok'] === false, 'missing mandatory runtime root fails preview');

    $missingInternalChannel = $tmp . '/missing-internal';
    write_preview_channel($missingInternalChannel, ['omit' => ['release_metadata.json']]);
    $missingInternal = $service->preview($missingInternalChannel, preview_installed_state());
    preview_assert($missingInternal['ok'] === false, 'missing internal release metadata fails preview');

    $internalMismatchChannel = $tmp . '/internal-mismatch';
    write_preview_channel($internalMismatchChannel, ['internal_metadata' => ['build_id' => 'git:other']]);
    $internalMismatch = $service->preview($internalMismatchChannel, preview_installed_state());
    preview_assert($internalMismatch['ok'] === false, 'internal/external metadata mismatch fails preview');

    $filenameMismatchChannel = $tmp . '/filename-mismatch';
    write_preview_channel($filenameMismatchChannel, ['metadata' => ['package' => ['filename' => 'other.zip']]]);
    $filenameMismatch = $service->preview($filenameMismatchChannel, preview_installed_state());
    preview_assert($filenameMismatch['ok'] === false, 'external package filename mismatch fails preview');

    $channelFilenameMismatch = $tmp . '/channel-filename-mismatch';
    write_preview_channel($channelFilenameMismatch, ['channel' => ['lanes' => ['app' => ['current' => ['package' => 'releases/other.zip']]]]]);
    $channelFilename = $service->preview($channelFilenameMismatch, preview_installed_state());
    preview_assert($channelFilename['ok'] === false, 'channel package filename mismatch fails preview');

    $corruptZipChannel = $tmp . '/corrupt-zip';
    write_preview_channel($corruptZipChannel, ['raw_package' => 'not a zip']);
    $corruptZip = $service->preview($corruptZipChannel, preview_installed_state());
    preview_assert($corruptZip['ok'] === false, 'corrupt ZIP fails preview');

    $duplicateChannel = $tmp . '/duplicate-entry';
    write_preview_channel($duplicateChannel, ['extra_entries' => ['APP/probe.txt' => 'duplicate']]);
    $duplicate = $service->preview($duplicateChannel, preview_installed_state());
    preview_assert($duplicate['ok'] === false, 'duplicate ZIP entry fails preview');

    $traversalChannel = $tmp . '/traversal-entry';
    write_preview_channel($traversalChannel, ['extra_entries' => ['../escape.txt' => 'unsafe']]);
    $traversal = $service->preview($traversalChannel, preview_installed_state());
    preview_assert($traversal['ok'] === false, 'traversal ZIP entry fails preview');

    $collisionChannel = $tmp . '/collision-entry';
    write_preview_channel($collisionChannel, ['extra_entries' => ['app\\probe.txt' => 'collision']]);
    $collision = $service->preview($collisionChannel, preview_installed_state());
    preview_assert($collision['ok'] === false, 'normalized path collision fails preview');

    $protectedChannel = $tmp . '/protected-entry';
    write_preview_channel($protectedChannel, ['extra_entries' => ['storage/db_config.php' => 'secret']]);
    $protected = $service->preview($protectedChannel, preview_installed_state());
    preview_assert($protected['ok'] === false, 'protected installation path fails preview');

    $symlinkChannel = $tmp . '/symlink-entry';
    write_preview_channel($symlinkChannel, ['symlink_entry' => true]);
    $symlink = $service->preview($symlinkChannel, preview_installed_state());
    preview_assert($symlink['ok'] === false, 'unsafe ZIP entry type fails preview');

    $corruptRecovery = write_preview_recovery($tmp . '/corrupt-recovery');
    file_put_contents($corruptRecovery . '/recovery-point.json', '{bad json');
    $corrupt = $service->preview($channel, preview_installed_state(), $corruptRecovery);
    preview_assert($corrupt['ok'] === false, 'corrupt recovery metadata fails preview');

    $missingPayloadRecovery = write_preview_recovery($tmp . '/missing-payload-recovery');
    unlink($missingPayloadRecovery . '/database.sql.gz');
    $missingPayload = $service->preview($channel, preview_installed_state(), $missingPayloadRecovery);
    preview_assert($missingPayload['ok'] === false, 'missing recovery payload fails preview');

    $checksumRecovery = write_preview_recovery($tmp . '/checksum-recovery');
    file_put_contents($checksumRecovery . '/database.sql.gz', 'corrupted recovery payload');
    $checksumMismatch = $service->preview($channel, preview_installed_state(), $checksumRecovery);
    preview_assert($checksumMismatch['ok'] === false, 'recovery payload checksum mismatch fails preview');

    $missingRehearsalRecovery = write_preview_recovery($tmp . '/missing-rehearsal-recovery');
    unlink($missingRehearsalRecovery . '/rehearsal.json');
    $missingRehearsal = $service->preview($channel, preview_installed_state(), $missingRehearsalRecovery);
    preview_assert($missingRehearsal['ok'] === false, 'missing rehearsal evidence fails preview');

    $badRehearsalRecovery = write_preview_recovery($tmp . '/bad-rehearsal-recovery');
    $badRehearsal = json_decode((string)file_get_contents($badRehearsalRecovery . '/rehearsal.json'), true);
    $badRehearsal['recovery_point_id'] = 'rp-other';
    file_put_contents($badRehearsalRecovery . '/rehearsal.json', json_encode($badRehearsal));
    $rehearsalMismatch = $service->preview($channel, preview_installed_state(), $badRehearsalRecovery);
    preview_assert($rehearsalMismatch['ok'] === false, 'rehearsal identity mismatch fails preview');

    $identityMismatchRecovery = write_preview_recovery($tmp . '/identity-mismatch-recovery', '0.4.9');
    $identityMismatch = $service->preview($channel, preview_installed_state(), $identityMismatchRecovery);
    preview_assert($identityMismatch['ok'] === false, 'installed release identity mismatch fails preview');

    preview_assert(file_exists(APP_ROOT . '/storage/update-channels') === $productionChannelExistsBefore, 'preview does not change production update channel storage existence');
} finally {
    preview_rm_tree($tmp);
}

echo "Local release channel preview probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
