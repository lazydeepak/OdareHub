<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\LocalReleaseChannelBuilderService;
use Platform\Updates\LocalReleaseChannelPreviewService;

$passed = 0;
$failed = 0;

function builder_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function builder_installed_state(): array
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

function builder_rm_tree(string $path): void
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
        builder_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

$tmp = sys_get_temp_dir() . '/susankhya-local-channel-builder-probe-' . bin2hex(random_bytes(6));
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

try {
    $incompleteSource = $tmp . '/incomplete-source';
    mkdir($incompleteSource . '/app', 0700, true);
    file_put_contents($incompleteSource . '/app/runtime.php', '<?php return true;');
    $incomplete = (new LocalReleaseChannelBuilderService())->build(
        $incompleteSource,
        $tmp . '/incomplete-channel',
        '1.0.0',
        'git:incomplete-probe',
        ['app']
    );
    builder_assert($incomplete['ok'] === false, 'builder rejects incomplete runtime package source');

    $channelDir = $tmp . '/channel';
    $result = (new LocalReleaseChannelBuilderService())->build(
        $tmp . '/source',
        $channelDir,
        '1.0.0',
        'git:builder-probe',
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock', 'storage'],
        ['storage']
    );

    builder_assert($result['ok'] === true, 'local channel builder succeeds');
    builder_assert(is_file($channelDir . '/channel.json'), 'channel.json is created');
    builder_assert(is_file((string)$result['metadata_path']), 'release metadata is created');
    builder_assert(is_file((string)$result['package_path']), 'release package is created');
    $builtMetadata = json_decode((string)file_get_contents((string)$result['metadata_path']), true);
    builder_assert(($builtMetadata['readiness']['status'] ?? null) === 'blocked', 'builder does not claim unsupported readiness passed');
    builder_assert(count((array)($builtMetadata['readiness']['evidence'] ?? [])) === 3, 'builder records explicit readiness check results');

    $preview = (new LocalReleaseChannelPreviewService())->preview(
        $channelDir,
        builder_installed_state()
    );
    builder_assert($preview['ok'] === true, 'generated local channel passes preview');
    builder_assert($preview['apply_eligible'] === false, 'generated local channel remains ineligible without authoritative recovery evidence');

    $zip = new ZipArchive();
    $zip->open((string)$result['package_path']);
    builder_assert($zip->locateName('app/runtime.php') !== false, 'release package includes app runtime');
    builder_assert($zip->locateName('composer.lock') !== false, 'release package includes composer lock');
    builder_assert($zip->locateName('release_metadata.json') !== false, 'release package includes internal release metadata');
    builder_assert($zip->locateName('storage/db_config.php') === false, 'release package excludes storage config');
    $zip->close();
} finally {
    builder_rm_tree($tmp);
}

echo "Local release channel builder probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
