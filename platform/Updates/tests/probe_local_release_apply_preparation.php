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

$tmp = sys_get_temp_dir() . '/susankhya-local-apply-prepare-probe-' . bin2hex(random_bytes(6));
mkdir($tmp . '/source/app', 0700, true);
mkdir($tmp . '/source/storage', 0700, true);
file_put_contents($tmp . '/source/app/runtime.php', '<?php return true;');
file_put_contents($tmp . '/source/composer.json', '{}');
file_put_contents($tmp . '/source/storage/db_config.php', 'secret');
$payloadDirs = [];

try {
    $channelDir = $tmp . '/channel';
    $build = (new LocalReleaseChannelBuilderService())->build(
        $tmp . '/source',
        $channelDir,
        '1.0.0',
        'git:apply-prepare-probe',
        ['app', 'composer.json', 'storage'],
        ['storage']
    );
    apply_prepare_assert($build['ok'] === true, 'local channel fixture builds');

    $service = new LocalReleaseApplyPreparationService();
    $stagingDir = $tmp . '/staging';
    mkdir($stagingDir, 0700, true);
    $prepared = $service->prepare(
        $channelDir,
        $stagingDir,
        ['product_version' => '0.5.0'],
        ['verified' => true, 'rehearsed' => true]
    );

    apply_prepare_assert($prepared['ok'] === true, 'apply preparation succeeds with eligible preview');
    if (is_string($prepared['payload_dir'] ?? null)) {
        $payloadDirs[] = (string)$prepared['payload_dir'];
    }
    apply_prepare_assert($prepared['final_state'] === 'prepared_without_live_mutation', 'apply preparation records non-live final state');
    apply_prepare_assert(is_file($stagingDir . '/apply-plan.json'), 'apply plan evidence is written to staging');
    apply_prepare_assert(is_string($prepared['payload_dir'] ?? null) && !str_starts_with((string)$prepared['payload_dir'], APP_ROOT . '/'), 'release payload is extracted outside repository root');
    apply_prepare_assert(is_file((string)$prepared['payload_dir'] . '/app/runtime.php'), 'release payload is extracted to isolated payload directory');
    apply_prepare_assert(!file_exists((string)$prepared['payload_dir'] . '/storage/db_config.php'), 'preserved storage config is not staged from package');
    apply_prepare_assert($prepared['live_mutation_performed'] === false, 'apply preparation reports no live mutation');

    $nonEmpty = $tmp . '/non-empty-staging';
    mkdir($nonEmpty, 0700, true);
    file_put_contents($nonEmpty . '/existing.txt', 'occupied');
    $nonEmptyResult = $service->prepare(
        $channelDir,
        $nonEmpty,
        ['product_version' => '0.5.0'],
        ['verified' => true, 'rehearsed' => true]
    );
    apply_prepare_assert($nonEmptyResult['ok'] === false, 'non-empty staging directory is rejected');

    $withoutRecovery = $tmp . '/without-recovery';
    mkdir($withoutRecovery, 0700, true);
    $blocked = $service->prepare(
        $channelDir,
        $withoutRecovery,
        ['product_version' => '0.5.0'],
        ['verified' => true, 'rehearsed' => false]
    );
    apply_prepare_assert($blocked['ok'] === false, 'apply preparation requires rehearsed recovery evidence');

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
        ['product_version' => '0.5.0'],
        ['verified' => true, 'rehearsed' => true]
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
