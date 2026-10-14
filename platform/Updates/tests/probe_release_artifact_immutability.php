<?php
declare(strict_types=1);

/** OH-AUTO1-063 disposable release-artifact immutability probe. */

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$tmp = realpath(sys_get_temp_dir()) . '/oh-auto1-063-' . bin2hex(random_bytes(6));
$source = $tmp . '/source';

$makeSource = static function (string $root, string $payload): void {
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $directory) {
        if (!is_dir($root . '/' . $directory)) {
            mkdir($root . '/' . $directory, 0700, true);
        }
        file_put_contents($root . '/' . $directory . '/runtime.txt', $directory);
    }
    if (!is_dir($root . '/storage')) {
        mkdir($root . '/storage', 0700, true);
    }
    file_put_contents($root . '/app/runtime.php', '<?php return ' . var_export($payload, true) . ';');
    file_put_contents($root . '/vendor/autoload.php', '<?php /* fixture loader */');
    file_put_contents($root . '/composer.json', json_encode(['require' => []]));
    file_put_contents($root . '/composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));
};

$build = static function (string $sourceRoot, string $channel, string $buildId, array $extraRoots = []) use ($makeSource): array {
    $roots = ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock'];
    foreach ($extraRoots as $extraRoot) {
        $roots[] = $extraRoot;
    }
    return (new Platform\Updates\LocalReleaseChannelBuilderService())->build(
        $sourceRoot,
        $channel,
        '1.0.0',
        $buildId,
        $roots,
        ['storage']
    );
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!file_exists($path)) {
        return;
    }
    if (is_file($path)) {
        unlink($path);
        return;
    }
    foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $entry) {
        $removeTree($entry->getPathname());
    }
    rmdir($path);
};

mkdir($source, 0700, true);
$makeSource($source, 'first');

try {
    $channel = $tmp . '/channel';
    $first = $build($source, $channel, 'git:fixed');
    $check(($first['ok'] ?? false) === true, 'first release build succeeds');
    $package = (string)($first['package_path'] ?? '');
    $metadata = (string)($first['metadata_path'] ?? '');
    $channelPath = $channel . '/channel.json';
    $before = [
        'package' => hash_file('sha256', $package),
        'metadata' => hash_file('sha256', $metadata),
        'channel' => hash_file('sha256', $channelPath),
    ];

    file_put_contents($source . '/app/runtime.php', '<?php return \'changed\';');
    $replacement = $build($source, $channel, 'git:fixed');
    $check(($replacement['ok'] ?? true) === false, 'same identity replacement is refused');
    $check($before['package'] === hash_file('sha256', $package), 'existing package bytes are preserved');
    $check($before['metadata'] === hash_file('sha256', $metadata), 'existing sidecar bytes are preserved');
    $check($before['channel'] === hash_file('sha256', $channelPath), 'existing channel metadata is preserved');

    $repeat = $build($source, $channel, 'git:fixed');
    $check(($repeat['ok'] ?? true) === false, 'repeated identity build is refused');

    $collisionChannel = $tmp . '/normalized-collision';
    $makeSource($tmp . '/collision-source', 'collision-a');
    $firstCollision = $build($tmp . '/collision-source', $collisionChannel, 'build/a');
    $check(($firstCollision['ok'] ?? false) === true, 'normalized-collision first build succeeds');
    $makeSource($tmp . '/collision-source', 'collision-b');
    $secondCollision = $build($tmp . '/collision-source', $collisionChannel, 'build-a');
    $check(($secondCollision['ok'] ?? true) === false, 'normalized build-ID collision is refused');

    $sidecarOnlyChannel = $tmp . '/sidecar-only';
    mkdir($sidecarOnlyChannel . '/releases', 0700, true);
    $sidecarOnly = $sidecarOnlyChannel . '/releases/susankhya-os-1.0.0-git-sidecar.json';
    file_put_contents($sidecarOnly, 'stale-sidecar');
    $sidecarResult = $build($source, $sidecarOnlyChannel, 'git:sidecar');
    $check(($sidecarResult['ok'] ?? true) === false, 'sidecar-only artifact record is refused');
    $check(file_get_contents($sidecarOnly) === 'stale-sidecar', 'incomplete sidecar record is preserved');

    $failedSource = $tmp . '/failed-source';
    $makeSource($failedSource, 'failed');
    file_put_contents($failedSource . '/release_metadata.json', '{}');
    $failedChannel = $tmp . '/failed-channel';
    $failedBuild = $build($failedSource, $failedChannel, 'git:failed', ['release_metadata.json']);
    $check(($failedBuild['ok'] ?? true) === false, 'inspection failure is refused');
    $check(!is_file($failedChannel . '/channel.json'), 'failed build does not create channel metadata');
    $check(!is_dir($failedChannel . '/releases') || count(scandir($failedChannel . '/releases')) === 2, 'failed build removes temporary release artifacts');
} catch (Throwable $e) {
    $check(false, 'probe completed without exception: ' . $e->getMessage());
} finally {
    $removeTree($tmp);
}

echo "Release artifact immutability probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
