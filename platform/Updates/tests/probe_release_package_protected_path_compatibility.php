<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Updates\LocalReleaseChannelBuilderService;
use Platform\Updates\ReleasePackageInspectionService;

$passed = 0;
$failed = 0;

function rel004_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function rel004_rm_tree(string $path): void
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
            rel004_rm_tree($path . '/' . $item);
        }
    }
    @rmdir($path);
}

/** @param array<int,string> $extraEntries */
function rel004_write_zip(string $path, array $extraEntries = [], bool $symlink = false): void
{
    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $root) {
        $zip->addFromString($root . '/runtime.php', '<?php return true;');
    }
    $zip->addFromString('vendor/autoload.php', '<?php /* runtime autoload */');
    $zip->addFromString('composer.json', json_encode(['require' => []]));
    $zip->addFromString('composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));
    $metadata = [
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.0.0',
        'build_id' => 'fixture:rel004',
        'channel' => 'local',
        'lane' => 'app',
        'created_at' => '2026-10-15T00:00:00Z',
        'package' => ['filename' => basename($path), 'sha256' => null, 'size_bytes' => null],
    ];
    $zip->addFromString('release_metadata.json', json_encode($metadata));
    foreach ($extraEntries as $entry) {
        $zip->addFromString($entry, 'fixture');
    }
    if ($symlink && method_exists($zip, 'setExternalAttributesName')) {
        $zip->addFromString('app/link', 'target');
        $zip->setExternalAttributesName('app/link', ZipArchive::OPSYS_UNIX, (0120000 | 0777) << 16);
    }
    $zip->close();
}

$tmp = sys_get_temp_dir() . '/odarehub-rel004-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);

try {
    $source = $tmp . '/source';
    mkdir($source . '/app', 0700, true);
    foreach (['apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin'] as $root) {
        mkdir($source . '/' . $root, 0700, true);
        file_put_contents($source . '/' . $root . '/runtime.txt', $root);
    }
    file_put_contents($source . '/app/runtime.php', '<?php return true;');
    file_put_contents($source . '/vendor/autoload.php', '<?php /* runtime autoload */');
    mkdir($source . '/vendor/bin', 0700, true);
    file_put_contents($source . '/vendor/bin/.phpunit.result.cache', '{}');
    mkdir($source . '/vendor/ratchet/rfc6455', 0700, true);
    file_put_contents($source . '/vendor/ratchet/rfc6455/phpunit.xml.dist', '<phpunit/>');
    file_put_contents($source . '/composer.json', json_encode(['require' => []]));
    file_put_contents($source . '/composer.lock', json_encode(['content-hash' => str_repeat('a', 32), 'packages' => []]));

    $channel = $tmp . '/built-channel';
    $built = (new LocalReleaseChannelBuilderService())->build(
        $source,
        $channel,
        '1.0.0',
        'fixture:rel004',
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock']
    );
    rel004_assert(($built['ok'] ?? false) === true, 'builder excludes vendor phpunit metadata and produces a valid package');
    $zip = new ZipArchive();
    $zip->open((string)($built['package_path'] ?? ''));
    rel004_assert($zip->locateName('vendor/autoload.php') !== false, 'vendor runtime autoload remains included');
    rel004_assert($zip->locateName('vendor/ratchet/rfc6455/phpunit.xml.dist') === false, 'vendor phpunit metadata is excluded');
    rel004_assert($zip->locateName('vendor/bin/.phpunit.result.cache') === false, 'vendor development cache is excluded');
    $zip->close();

    $raw = $tmp . '/raw-vendor-metadata.zip';
    rel004_write_zip($raw, ['vendor/ratchet/rfc6455/phpunit.xml.dist']);
    $rawInspection = (new ReleasePackageInspectionService())->inspect($raw, []);
    rel004_assert(($rawInspection['ok'] ?? true) === false, 'raw package with vendor phpunit metadata remains rejected');
    rel004_assert(str_contains((string)($rawInspection['errors'][0] ?? ''), 'protected installation path'), 'raw rejection identifies protected path');

    $valid = $tmp . '/valid.zip';
    rel004_write_zip($valid);
    $validInspection = (new ReleasePackageInspectionService())->inspect($valid, [
        'schema_version' => 'susankhya.release.v1',
        'product_id' => 'susankhya-os',
        'product_name' => 'Susankhya ERP',
        'release_version' => '1.0.0',
        'build_id' => 'fixture:rel004',
        'channel' => 'local',
        'lane' => 'app',
        'package' => ['filename' => basename($valid)],
    ]);
    rel004_assert(($validInspection['ok'] ?? false) === true, 'vendor runtime fixture passes inspection');

    $rootPhpunit = $tmp . '/root-phpunit.zip';
    rel004_write_zip($rootPhpunit, ['phpunit.xml.dist']);
    rel004_assert(!(new ReleasePackageInspectionService())->inspect($rootPhpunit, [])['ok'], 'root phpunit configuration remains rejected');

    $credentials = $tmp . '/credentials.zip';
    rel004_write_zip($credentials, ['storage/db_config.php']);
    rel004_assert(!(new ReleasePackageInspectionService())->inspect($credentials, [])['ok'], 'credential-like config remains rejected');

    $traversal = $tmp . '/traversal.zip';
    rel004_write_zip($traversal, ['../escape.php']);
    rel004_assert(!(new ReleasePackageInspectionService())->inspect($traversal, [])['ok'], 'path traversal remains rejected');

    $symlink = $tmp . '/symlink.zip';
    rel004_write_zip($symlink, [], true);
    rel004_assert(!(new ReleasePackageInspectionService())->inspect($symlink, [])['ok'], 'ZIP symlink remains rejected');

    $missingVendor = $tmp . '/missing-vendor.zip';
    rel004_write_zip($missingVendor);
    $missingZip = new ZipArchive();
    $missingZip->open($missingVendor);
    $missingZip->deleteName('vendor/runtime.php');
    $missingZip->deleteName('vendor/autoload.php');
    $missingZip->close();
    rel004_assert(!(new ReleasePackageInspectionService())->inspect($missingVendor, [])['ok'], 'missing vendor runtime fails inspection');

    $corruptComposer = $tmp . '/corrupt-composer.zip';
    rel004_write_zip($corruptComposer);
    $corruptZip = new ZipArchive();
    $corruptZip->open($corruptComposer);
    $corruptZip->deleteName('composer.lock');
    $corruptZip->addFromString('composer.lock', '{not-json');
    $corruptZip->close();
    rel004_assert(!(new ReleasePackageInspectionService())->inspect($corruptComposer, [])['ok'], 'corrupt Composer metadata fails inspection');

    $metadata = json_decode((string)file_get_contents((string)$built['metadata_path']), true);
    rel004_assert(is_array($metadata) && ($metadata['package']['sha256'] ?? '') !== '', 'builder records package checksum');
    rel004_assert(is_array($metadata) && (int)($metadata['package']['size_bytes'] ?? 0) > 0, 'builder records package byte size');
} finally {
    rel004_rm_tree($tmp);
}

echo "REL-004 protected-path compatibility probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
