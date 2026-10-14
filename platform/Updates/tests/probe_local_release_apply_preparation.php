<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\LocalReleaseApplyPreparationService;
use Platform\Updates\LocalReleaseChannelBuilderService;

$passed = 0;
$failed = 0;

function apply_prepare_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function apply_installed_state(): array
{
    return [
        'product_id' => 'susankhya-os',
        'product_version' => '0.5.0',
        'platform' => ['product_id' => 'susankhya-os', 'version' => '0.5.0', 'build_id' => 'git:installed'],
        'apps' => [],
        'modules' => [],
        'schema' => ['version' => '1.0.0'],
        'migrations' => ['pending' => [], 'failed' => [], 'order' => []],
    ];
}

function apply_prepare_rm_tree(string $path): void
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
        apply_prepare_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

function write_apply_recovery(string $root): string
{
    mkdir($root, 0700, true);
    $database = $root . '/database.sql.gz';
    $filesystem = $root . '/filesystem.zip';
    file_put_contents($database, gzencode('-- apply preparation recovery database'));
    file_put_contents($filesystem, 'apply preparation recovery filesystem');
    $metadata = [
        'schema_version' => 'susankhya.recovery-point.v1',
        'recovery_point_id' => 'rp-apply-preparation',
        'created_at' => '2026-08-19T00:00:00Z',
        'created_by' => 'probe',
        'reason' => 'update_apply',
        'installation' => [
            'product_id' => 'susankhya-os',
            'release_version' => '0.5.0',
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
            'preservation_inventory_sha256' => str_repeat('a', 64),
        ],
    ];
    file_put_contents($root . '/recovery-point.json', json_encode($metadata));
    file_put_contents($root . '/rehearsal.json', json_encode([
        'schema_version' => 'susankhya.rehearsal.v1',
        'recovery_point_id' => 'rp-apply-preparation',
        'ok' => true,
        'final_state' => 'restored',
        'database' => ['ok' => true],
        'filesystem' => ['ok' => true],
    ]));
    return $root;
}

$tmp = sys_get_temp_dir() . '/susankhya-local-apply-prepare-probe-' . bin2hex(random_bytes(6));
mkdir($tmp . '/source/app', 0700, true);
foreach (['apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $root) {
    mkdir($tmp . '/source/' . $root, 0700, true);
    file_put_contents($tmp . '/source/' . $root . '/probe.txt', $root);
}
mkdir($tmp . '/source/storage', 0700, true);
file_put_contents($tmp . '/source/app/runtime.php', '<?php return true;');
file_put_contents($tmp . '/source/composer.json', json_encode(['require' => []]));
file_put_contents($tmp . '/source/composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));
file_put_contents($tmp . '/source/storage/db_config.php', 'secret');
$payloadDirs = [];

try {
    $channelDir = $tmp . '/channel';
    $build = (new LocalReleaseChannelBuilderService())->build(
        $tmp . '/source',
        $channelDir,
        '1.0.0',
        'git:apply-prepare-probe',
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock', 'storage'],
        ['storage']
    );
    apply_prepare_assert($build['ok'] === true, 'local channel fixture builds');

    $service = new LocalReleaseApplyPreparationService();
    $recoveryDir = write_apply_recovery($tmp . '/recovery');
    $stagingDir = $tmp . '/staging';
    mkdir($stagingDir, 0700, true);
    $prepared = $service->prepare(
        $channelDir,
        $stagingDir,
        apply_installed_state(),
        $recoveryDir
    );

    apply_prepare_assert($prepared['ok'] === false, 'apply preparation remains blocked by unauthenticated readiness evidence');
    apply_prepare_assert(!file_exists($stagingDir . '/apply-plan.json'), 'blocked readiness produces no apply plan');

    $nonEmpty = $tmp . '/non-empty-staging';
    mkdir($nonEmpty, 0700, true);
    file_put_contents($nonEmpty . '/existing.txt', 'occupied');
    $nonEmptyResult = $service->prepare(
        $channelDir,
        $nonEmpty,
        apply_installed_state(),
        $recoveryDir
    );
    apply_prepare_assert($nonEmptyResult['ok'] === false, 'non-empty staging directory is rejected');

    $withoutRecovery = $tmp . '/without-recovery';
    mkdir($withoutRecovery, 0700, true);
    $blocked = $service->prepare(
        $channelDir,
        $withoutRecovery,
        apply_installed_state(),
        $tmp . '/missing-recovery'
    );
    apply_prepare_assert($blocked['ok'] === false, 'apply preparation requires rehearsed recovery evidence');

    $withoutCompatibilityEvidence = $tmp . '/without-compatibility-evidence';
    mkdir($withoutCompatibilityEvidence, 0700, true);
    $compatibilityBlocked = $service->prepare(
        $channelDir,
        $withoutCompatibilityEvidence,
        ['product_version' => '0.5.0'],
        $recoveryDir
    );
    apply_prepare_assert($compatibilityBlocked['ok'] === false, 'apply preparation cannot bypass missing installed compatibility evidence');

    $unsafeChannel = $tmp . '/unsafe-channel';
    mkdir($unsafeChannel . '/releases', 0700, true);
    $unsafePackage = $unsafeChannel . '/releases/unsafe.zip';
    $zip = new ZipArchive();
    $zip->open($unsafePackage, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('storage/db_config.php', 'secret');
    $zip->close();
    $sha = hash_file('sha256', $unsafePackage) ?: '';
    $size = filesize($unsafePackage) ?: 0;
    file_put_contents($unsafeChannel . '/releases/unsafe.json', json_encode([
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.0.0',
        'build_id' => 'git:unsafe',
        'channel' => 'local',
        'lane' => 'app',
        'created_at' => '2026-08-19T00:00:00Z',
        'package' => ['filename' => 'unsafe.zip', 'sha256' => $sha, 'size_bytes' => $size],
        'compatibility' => ['minimum_product_version' => '0.5.0', 'maximum_product_version' => null, 'php' => ['minimum' => '8.1.0'], 'php_extensions' => ['json', 'zip']],
        'migration' => ['warnings' => [], 'requires_backup' => true],
        'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
    ], JSON_PRETTY_PRINT));
    file_put_contents($unsafeChannel . '/channel.json', json_encode([
        'schema_version' => 'susankhya.local-channel.v1',
        'channel' => 'local',
        'product_id' => 'susankhya-os',
        'generated_at' => '2026-08-19T00:00:00Z',
        'lanes' => [
            'app' => ['current' => ['release_version' => '1.0.0', 'build_id' => 'git:unsafe', 'metadata' => 'releases/unsafe.json', 'package' => 'releases/unsafe.zip', 'sha256' => $sha, 'size_bytes' => $size]],
            'runtime' => ['current' => null],
            'support' => ['current' => null],
        ],
    ], JSON_PRETTY_PRINT));

    $unsafeStaging = $tmp . '/unsafe-staging';
    mkdir($unsafeStaging, 0700, true);
    $unsafe = $service->prepare(
        $unsafeChannel,
        $unsafeStaging,
        apply_installed_state(),
        $recoveryDir
    );
    apply_prepare_assert($unsafe['ok'] === false, 'blocked payload paths are rejected before extraction');
} finally {
    foreach ($payloadDirs as $payloadDir) {
        apply_prepare_rm_tree($payloadDir);
    }
    apply_prepare_rm_tree($tmp);
}

echo "Local release apply preparation probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
