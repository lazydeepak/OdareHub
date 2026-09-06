<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\RecoveryPointMetadataValidator;
use Platform\Recovery\RecoveryProviderPreflightService;

$passed = 0;
$failed = 0;

function recovery_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function recovery_metadata(): array
{
    $hash = str_repeat('a', 64);

    return [
        'schema_version' => 'susankhya.recovery-point.v1',
        'recovery_point_id' => 'rp-probe-001',
        'created_at' => '2026-08-19T00:00:00Z',
        'created_by' => 'probe',
        'reason' => 'update_apply',
        'installation' => [
            'product_id' => 'susankhya-os',
            'release_version' => '1.0.0',
            'build_id' => 'git:probe',
            'app_manifest_checksums' => [],
            'module_manifest_checksums' => [],
            'schema_state' => [],
            'migration_state' => [],
        ],
        'database' => [
            'provider' => 'mysqldump',
            'provider_version' => '8.0',
            'identity' => ['host_label' => 'local', 'port' => 3306, 'database' => 'erp', 'charset' => 'utf8mb4'],
            'payload' => ['path' => 'database.sql.gz', 'sha256' => $hash, 'size_bytes' => 10],
        ],
        'filesystem' => [
            'provider' => 'ziparchive',
            'payload' => ['path' => 'filesystem.zip', 'sha256' => $hash, 'size_bytes' => 20],
            'preservation_inventory_sha256' => $hash,
        ],
    ];
}

$valid = RecoveryPointMetadataValidator::validate(recovery_metadata());
recovery_assert($valid['ok'] === true, 'valid metadata passes');

$missing = recovery_metadata();
unset($missing['installation']['build_id']);
recovery_assert(RecoveryPointMetadataValidator::validate($missing)['ok'] === false, 'missing required field fails');

$uppercaseHash = recovery_metadata();
$uppercaseHash['database']['payload']['sha256'] = strtoupper(str_repeat('a', 64));
recovery_assert(RecoveryPointMetadataValidator::validate($uppercaseHash)['ok'] === false, 'uppercase checksum fails');

$negativeSize = recovery_metadata();
$negativeSize['filesystem']['payload']['size_bytes'] = -1;
recovery_assert(RecoveryPointMetadataValidator::validate($negativeSize)['ok'] === false, 'negative size fails');

$secretKey = recovery_metadata();
$secretKey['database']['password'] = 'do-not-store';
recovery_assert(RecoveryPointMetadataValidator::validate($secretKey)['ok'] === false, 'secret-looking key fails');

$secretValue = recovery_metadata();
$secretValue['database']['identity']['connection'] = 'mysql://user:password@localhost/erp';
recovery_assert(RecoveryPointMetadataValidator::validate($secretValue)['ok'] === false, 'secret-looking value fails');

$candidatePath = APP_ROOT . '/storage/recovery-points';
recovery_assert(!file_exists($candidatePath), 'recovery storage does not exist before preflight');

$service = new RecoveryProviderPreflightService(
    static fn(): ?array => null,
    ['PATH' => ''],
    APP_ROOT
);
$preflight = $service->inspect();
recovery_assert(($preflight['database']['resolved'] ?? true) === false, 'example config is not treated as real configuration');
recovery_assert(($preflight['dump_executable']['available'] ?? true) === false, 'missing dump executable is reported without crash');
recovery_assert(in_array('MySQL/MariaDB dump executable is unavailable.', $preflight['blockers'] ?? [], true), 'missing dump executable is a blocker');
recovery_assert(!file_exists($candidatePath), 'preflight does not create recovery storage');

$redactedService = new RecoveryProviderPreflightService(
    static fn(): array => [
        'host' => 'db.local',
        'name' => 'erp_local',
        'port' => 3306,
        'charset' => 'utf8mb4',
        'pass' => 'never-leak-this',
        '_source' => 'env',
    ],
    ['PATH' => ''],
    APP_ROOT
);
$redacted = $redactedService->inspect();
$encoded = json_encode($redacted, JSON_THROW_ON_ERROR);
recovery_assert(!str_contains($encoded, 'never-leak-this'), 'preflight redacts configured password');
recovery_assert(($redacted['database']['identity']['database'] ?? '') === 'erp_local', 'preflight reports non-secret database identity');

echo "Recovery provider preflight probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);