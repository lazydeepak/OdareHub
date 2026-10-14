<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\RecoveryPointService;
use Platform\Updates\LocalReleaseApplyPreparationService;
use Platform\Updates\LocalReleaseChannelBuilderService;
use Platform\Updates\LocalReleaseChannelPreviewService;
use Platform\Updates\ReleasePackageInspectionService;

$passed = 0;
$failed = 0;

function integration_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function integration_rm_tree(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    foreach (scandir($path) ?: [] as $item) {
        if ($item !== '.' && $item !== '..') {
            integration_rm_tree($path . '/' . $item);
        }
    }
    @rmdir($path);
}

function integration_copy_tree(string $source, string $target): void
{
    if (is_dir($source)) {
        mkdir($target, 0700, true);
        foreach (scandir($source) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                integration_copy_tree($source . '/' . $item, $target . '/' . $item);
            }
        }
        return;
    }
    copy($source, $target);
}

function integration_read_json(string $path): array
{
    $value = json_decode((string)file_get_contents($path), true);
    return is_array($value) ? $value : [];
}

function integration_write_json(string $path, array $value): void
{
    file_put_contents($path, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

function integration_installed_state(): array
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

function integration_source(string $root): void
{
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $directory) {
        mkdir($root . '/' . $directory, 0700, true);
        file_put_contents($root . '/' . $directory . '/probe.txt', $directory);
    }
    file_put_contents($root . '/composer.json', json_encode(['require' => []]));
    file_put_contents($root . '/composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));
}

function integration_recovery(string $root, string $releaseVersion = '0.5.0', bool $rehearsalSuccess = true): string
{
    mkdir($root, 0700, true);
    $database = $root . '/database.sql.gz';
    $filesystem = $root . '/filesystem.zip';
    file_put_contents($database, gzencode('-- integration database'));
    file_put_contents($filesystem, 'integration filesystem');
    $recoveryId = 'rp-integration-' . bin2hex(random_bytes(4));
    $metadata = [
        'schema_version' => 'susankhya.recovery-point.v1',
        'recovery_point_id' => $recoveryId,
        'created_at' => '2026-10-14T00:00:00Z',
        'created_by' => 'offline-integration-probe',
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
            'preservation_inventory_sha256' => str_repeat('a', 64),
        ],
    ];
    integration_write_json($root . '/recovery-point.json', $metadata);
    integration_write_json($root . '/rehearsal.json', [
        'schema_version' => 'susankhya.rehearsal.v1',
        'recovery_point_id' => $recoveryId,
        'ok' => $rehearsalSuccess,
        'final_state' => $rehearsalSuccess ? 'restored' : 'failed',
        'database' => ['ok' => $rehearsalSuccess],
        'filesystem' => ['ok' => $rehearsalSuccess],
    ]);
    return $root;
}

function integration_mutate_metadata(string $channel, callable $mutator): void
{
    $path = $channel . '/releases/susankhya-os-1.0.0-git-integration.json';
    $metadata = integration_read_json($path);
    $mutator($metadata);
    integration_write_json($path, $metadata);
}

$tmp = sys_get_temp_dir() . '/susankhya-offline-integration-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$source = $tmp . '/source';
$baseChannel = $tmp . '/base-channel';
integration_source($source);

try {
    $builder = new LocalReleaseChannelBuilderService();
    $build = $builder->build(
        $source,
        $baseChannel,
        '1.0.0',
        'git:integration',
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock']
    );
    integration_assert(($build['ok'] ?? false) === true, 'release builder creates the offline integration package');

    $metadata = integration_read_json((string)$build['metadata_path']);
    $packageInspection = (new ReleasePackageInspectionService())->inspect((string)$build['package_path'], $metadata);
    integration_assert(($packageInspection['ok'] ?? false) === true, 'built package passes shared package inspection');

    $installed = integration_installed_state();
    $recovery = integration_recovery($tmp . '/recovery-valid');
    $previewService = new LocalReleaseChannelPreviewService();
    $previewWithoutRecovery = $previewService->preview($baseChannel, $installed);
    integration_assert(($previewWithoutRecovery['ok'] ?? false) === true, 'structurally valid release preview passes');
    integration_assert(($previewWithoutRecovery['apply_eligible'] ?? true) === false, 'missing recovery evidence blocks apply eligibility');

    $preview = $previewService->preview($baseChannel, $installed, $recovery);
    integration_assert(($preview['ok'] ?? false) === true, 'compatible installed system and valid recovery preserve preview validity');
    integration_assert(($preview['apply_eligible'] ?? true) === false, 'unattested readiness keeps complete positive control blocked');
    integration_assert(($preview['release']['release_version'] ?? null) === $metadata['release_version'], 'release version identity survives preview');
    integration_assert(($preview['release']['build_id'] ?? null) === $metadata['build_id'], 'build identity survives preview');
    integration_assert(($preview['release']['package_sha256'] ?? null) === $metadata['package']['sha256'], 'package checksum identity survives preview');

    $staging = $tmp . '/staging';
    mkdir($staging, 0700, true);
    $prepared = (new LocalReleaseApplyPreparationService())->prepare($baseChannel, $staging, $installed, $recovery);
    integration_assert(($prepared['ok'] ?? true) === false, 'apply preparation cannot bypass blocked readiness');
    integration_assert(!file_exists($staging . '/apply-plan.json'), 'blocked integration produces no apply plan');

    $cases = [
        'missing-readiness-evidence' => static function (string $channel): void {
            integration_mutate_metadata($channel, static function (array &$metadata): void {
                $metadata['readiness'] = ['status' => 'passed', 'evidence' => []];
            });
        },
        'incompatible-installed-schema' => static function (string $channel): void {
            integration_mutate_metadata($channel, static function (array &$metadata): void {
                $metadata['compatibility']['schema']['minimum'] = '2.0.0';
            });
        },
        'mismatched-recovery-identity' => static function (string $channel): void {},
        'corrupted-package-bytes' => static function (string $channel): void {
            file_put_contents($channel . '/releases/susankhya-os-1.0.0-git-integration.zip', 'corrupted package bytes');
        },
        'mismatched-channel-metadata' => static function (string $channel): void {
            $path = $channel . '/channel.json';
            $channelData = integration_read_json($path);
            $channelData['lanes']['app']['current']['build_id'] = 'git:other';
            integration_write_json($path, $channelData);
        },
        'missing-required-module-dependency' => static function (string $channel): void {
            integration_mutate_metadata($channel, static function (array &$metadata): void {
                $metadata['compatibility']['modules'] = [[
                    'id' => 'required-module',
                    'minimum' => '1.0.0',
                    'required' => true,
                    'requires' => ['required-dependency'],
                ]];
            });
        },
        'missing-mandatory-release-file' => static function (string $channel): void {
            $zipPath = $channel . '/releases/susankhya-os-1.0.0-git-integration.zip';
            $zip = new ZipArchive();
            $zip->open($zipPath);
            $zip->deleteName('vendor/probe.txt');
            $zip->close();
        },
        'fabricated-readiness-success' => static function (string $channel): void {
            integration_mutate_metadata($channel, static function (array &$metadata): void {
                $metadata['readiness'] = ['status' => 'passed', 'passed' => true, 'evidence' => []];
            });
        },
        'fabricated-rehearsal-success' => static function (string $channel): void {},
        'cross-release-evidence-reuse' => static function (string $channel): void {},
    ];

    foreach ($cases as $name => $mutator) {
        $channel = $tmp . '/case-' . $name;
        integration_copy_tree($baseChannel, $channel);
        $mutator($channel);
        $caseRecovery = $recovery;
        if (in_array($name, ['mismatched-recovery-identity', 'fabricated-rehearsal-success', 'cross-release-evidence-reuse'], true)) {
            $caseRecovery = $tmp . '/recovery-' . $name;
            integration_recovery($caseRecovery, in_array($name, ['mismatched-recovery-identity', 'cross-release-evidence-reuse'], true) ? '0.4.0' : '0.5.0', true);
            if ($name === 'fabricated-rehearsal-success') {
                $rehearsal = integration_read_json($caseRecovery . '/rehearsal.json');
                $rehearsal['final_state'] = 'failed';
                integration_write_json($caseRecovery . '/rehearsal.json', $rehearsal);
            }
        }
        $casePreview = $previewService->preview($channel, $installed, $caseRecovery);
        integration_assert(($casePreview['ok'] ?? true) === false, $name . ' fails closed at integrated preview');
    }

    $wrongInstalledRelease = integration_installed_state();
    $wrongInstalledRelease['product_version'] = '0.4.0';
    $wrongInstalledRelease['platform']['version'] = '0.4.0';
    $wrongReleasePreview = $previewService->preview($baseChannel, $wrongInstalledRelease, $recovery);
    integration_assert(($wrongReleasePreview['ok'] ?? true) === false, 'wrong installed release fails compatibility preflight');

    $bypassStaging = $tmp . '/bypass-staging';
    mkdir($bypassStaging, 0700, true);
    $bypass = (new LocalReleaseApplyPreparationService())->prepare($baseChannel, $bypassStaging, $installed, $recovery);
    integration_assert(($bypass['ok'] ?? true) === false, 'apply-preparation bypass attempt fails closed');
} finally {
    integration_rm_tree($tmp);
}

echo "Offline release/recovery integration probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
