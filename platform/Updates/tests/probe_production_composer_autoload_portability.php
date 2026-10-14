<?php
declare(strict_types=1);

/**
 * OH-AUTO1-062 actual-source Composer portability probe.
 *
 * The source is staged in a runner-owned temporary root. Composer is invoked
 * only by the release builder inside its disposable preparation copy; this
 * probe never loads application bootstrap or creates database authority.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/scripts/testing/IsolatedSourceRootTestRunner.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$workspace = realpath(sys_get_temp_dir()) . '/oh-auto1-062-probe-' . bin2hex(random_bytes(6));
$prepared = null;

$copyRoot = static function (string $source, string $destination): void {
    if (!is_dir($destination)) {
        mkdir($destination, 0700, true);
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    $sourcePrefix = rtrim($source, '/') . '/';
    foreach ($iterator as $entry) {
        if ($entry->isLink()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr((string)$entry->getPathname(), strlen($sourcePrefix)));
        $target = rtrim($destination, '/') . '/' . $relative;
        if ($entry->isDir()) {
            if (!is_dir($target)) {
                mkdir($target, 0700, true);
            }
            continue;
        }
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        copy((string)$entry->getPathname(), $target);
    }
};

$autoloadPathsAreComplete = static function (string $root) use ($check): bool {
    $composerRoot = $root . '/vendor/composer';
    $missing = [];
    foreach (['autoload_psr4.php', 'autoload_classmap.php', 'autoload_files.php'] as $file) {
        $path = $composerRoot . '/' . $file;
        if (!is_file($path)) {
            $missing[] = $file;
        }
    }
    $check($missing === [], 'production Composer metadata files exist');
    if ($missing !== []) {
        return false;
    }

    $psr4 = require $composerRoot . '/autoload_psr4.php';
    $check(!isset($psr4['Tests\\']), 'autoload-dev root Tests mapping is absent');
    $classmap = require $composerRoot . '/autoload_classmap.php';
    $files = require $composerRoot . '/autoload_files.php';
    $serialized = strtolower((string)json_encode([$psr4, $classmap, $files]));
    $check(!str_contains($serialized, 'phpunit') && !str_contains($serialized, 'code-coverage'), 'development PHPUnit and coverage targets are absent from autoload metadata');
    $complete = true;
    $walk = static function (mixed $value) use (&$walk, &$complete, &$missing, $root): void {
        if (is_array($value)) {
            foreach ($value as $item) {
                $walk($item);
            }
            return;
        }
        if (!is_string($value) || !str_starts_with($value, '/')) {
            return;
        }
        $realRoot = realpath($root);
        $realValue = realpath($value);
        if ($realRoot === false || $realValue === false || ($realValue !== $realRoot && !str_starts_with($realValue, $realRoot . '/'))) {
            $complete = false;
            $missing[] = $value;
        }
    };
    foreach ([
        $psr4,
        $classmap,
        $files,
    ] as $map) {
        $walk($map);
    }
    $check($complete, 'all production autoload targets exist inside the package root');
    return $complete;
};

try {
    $prepared = IsolatedSourceRootTestRunner::prepare(APP_ROOT, $workspace);
    $sourceRoot = $prepared['source_root'];
    foreach (['public', 'resources', 'bin'] as $root) {
        $copyRoot(APP_ROOT . '/' . $root, $sourceRoot . '/' . $root);
    }

    $manifest = $prepared['manifest'];
    $result = (new Platform\Updates\LocalReleaseChannelBuilderService())->build(
        $sourceRoot,
        $workspace . '/channel',
        '1.0.0',
        'git:oh-auto1-062',
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock'],
        ['storage']
    );

    $check(($result['ok'] ?? false) === true, 'actual source production package builds');
    $package = (string)($result['package_path'] ?? '');
    $metadataPath = (string)($result['metadata_path'] ?? '');
    $check(is_file($package), 'disposable package exists');
    $check(is_file($metadataPath), 'disposable sidecar exists');

    if (is_file($package)) {
        $zip = new ZipArchive();
        $check($zip->open($package) === true, 'disposable package opens');
        $extracted = $workspace . '/extracted';
        mkdir($extracted, 0700, true);
        $check($zip->extractTo($extracted) === true, 'disposable package extracts');
        $zip->close();
        $autoloadPathsAreComplete($extracted);
        $check(is_file($extracted . '/vendor/dompdf/dompdf/src/Dompdf.php'), 'runtime dependency dompdf is retained');
        $check(is_file($extracted . '/vendor/autoload.php'), 'runtime Composer loader is retained');
    }

    if (is_file($metadataPath)) {
        $metadata = json_decode((string)file_get_contents($metadataPath), true);
        $channel = json_decode((string)file_get_contents($workspace . '/channel/channel.json'), true);
        $sha = hash_file('sha256', $package);
        $size = filesize($package);
        $check($sha === ($metadata['package']['sha256'] ?? null), 'sidecar checksum matches package');
        $check($size === ($metadata['package']['size_bytes'] ?? null), 'sidecar size matches package');
        $check($sha === ($channel['lanes']['app']['current']['sha256'] ?? null), 'channel checksum matches package');
        $check($size === ($channel['lanes']['app']['current']['size_bytes'] ?? null), 'channel size matches package');
    }

    IsolatedSourceRootTestRunner::assertManifestUnchanged($sourceRoot, $manifest);
    $check(true, 'runner source manifest remains unchanged');
} catch (Throwable $e) {
    $check(false, 'probe completed without exception: ' . $e->getMessage());
} finally {
    if ($prepared !== null) {
        IsolatedSourceRootTestRunner::cleanup($prepared['workspace']);
    }
}

echo "Production Composer portability probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
