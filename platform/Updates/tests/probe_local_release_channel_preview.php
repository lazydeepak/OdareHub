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
    file_put_contents($package, 'disposable package bytes');
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
            'php' => ['minimum' => '8.1.0'],
            'php_extensions' => ['json'],
        ],
        'migration' => ['warnings' => [], 'requires_backup' => true],
        'apply_eligibility' => ['requires_preview' => true, 'requires_operator_approval' => true],
    ], $overrides['metadata'] ?? []);
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

$tmp = sys_get_temp_dir() . '/susankhya-local-channel-preview-probe-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$productionChannelExistsBefore = file_exists(APP_ROOT . '/storage/update-channels');

try {
    $service = new LocalReleaseChannelPreviewService();
    $channel = $tmp . '/channel';
    write_preview_channel($channel);

    $preview = $service->preview($channel, ['product_version' => '0.5.0']);
    preview_assert($preview['ok'] === true, 'valid local channel preview passes package checks');
    preview_assert($preview['apply_eligible'] === false, 'valid preview remains apply-ineligible without recovery evidence');
    preview_assert(in_array('Verified and rehearsed recovery evidence is required before apply.', $preview['apply_blockers'], true), 'preview reports recovery evidence apply blocker');

    $eligible = $service->preview($channel, ['product_version' => '0.5.0'], ['verified' => true, 'rehearsed' => true]);
    preview_assert($eligible['ok'] === true && $eligible['apply_eligible'] === true, 'verified recovery evidence makes preview apply-eligible');

    $badHashChannel = $tmp . '/bad-hash';
    write_preview_channel($badHashChannel, ['metadata' => ['package' => ['sha256' => str_repeat('b', 64)]]]);
    $badHash = $service->preview($badHashChannel, ['product_version' => '0.5.0'], ['verified' => true, 'rehearsed' => true]);
    preview_assert($badHash['ok'] === false, 'package checksum mismatch fails preview');

    $oldInstallChannel = $tmp . '/old-install';
    write_preview_channel($oldInstallChannel);
    $old = $service->preview($oldInstallChannel, ['product_version' => '0.4.9']);
    preview_assert($old['ok'] === false, 'installed version below minimum fails preview');

    $unsafePathChannel = $tmp . '/unsafe-path';
    write_preview_channel($unsafePathChannel, ['channel' => ['lanes' => ['app' => ['current' => ['metadata' => '../escape.json']]]]]);
    $unsafe = $service->preview($unsafePathChannel, ['product_version' => '0.5.0']);
    preview_assert($unsafe['ok'] === false, 'unsafe channel metadata path fails preview');

    preview_assert(file_exists(APP_ROOT . '/storage/update-channels') === $productionChannelExistsBefore, 'preview does not change production update channel storage existence');
} finally {
    preview_rm_tree($tmp);
}

echo "Local release channel preview probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
