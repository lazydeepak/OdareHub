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
mkdir($tmp . '/source/storage', 0700, true);
file_put_contents($tmp . '/source/app/runtime.php', '<?php return true;');
file_put_contents($tmp . '/source/composer.lock', '{}');
file_put_contents($tmp . '/source/storage/db_config.php', 'secret');

try {
    $channelDir = $tmp . '/channel';
    $result = (new LocalReleaseChannelBuilderService())->build(
        $tmp . '/source',
        $channelDir,
        '1.0.0',
        'git:builder-probe',
        ['app', 'composer.lock', 'storage'],
        ['storage']
    );

    builder_assert($result['ok'] === true, 'local channel builder succeeds');
    builder_assert(is_file($channelDir . '/channel.json'), 'channel.json is created');
    builder_assert(is_file((string)$result['metadata_path']), 'release metadata is created');
    builder_assert(is_file((string)$result['package_path']), 'release package is created');

    $preview = (new LocalReleaseChannelPreviewService())->preview(
        $channelDir,
        ['product_version' => '0.5.0'],
        ['verified' => true, 'rehearsed' => true]
    );
    builder_assert($preview['ok'] === true, 'generated local channel passes preview');
    builder_assert($preview['apply_eligible'] === true, 'generated local channel is apply-eligible with recovery evidence');

    $zip = new ZipArchive();
    $zip->open((string)$result['package_path']);
    builder_assert($zip->locateName('app/runtime.php') !== false, 'release package includes app runtime');
    builder_assert($zip->locateName('composer.lock') !== false, 'release package includes composer lock');
    builder_assert($zip->locateName('storage/db_config.php') === false, 'release package excludes storage config');
    $zip->close();
} finally {
    builder_rm_tree($tmp);
}

echo "Local release channel builder probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
