<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/vendor/autoload.php';

use Platform\Recovery\FilesystemArchiveProvider;
use Platform\Recovery\RestoreRehearsalService;

$passed = 0;
$failed = 0;

function rehearsal_assert(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }

    $failed++;
    fwrite(STDERR, "FAIL: {$label}\n");
}

function rehearsal_rm_tree(string $path): void
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
        rehearsal_rm_tree($path . '/' . $item);
    }
    @rmdir($path);
}

function rehearsal_metadata(string $dbPayload, string $fsPayload): array
{
    $hash = str_repeat('a', 64);

    return [
        'schema_version' => 'susankhya.recovery-point.v1',
        'recovery_point_id' => 'rp-rehearsal-probe',
        'created_at' => '2026-08-19T00:00:00Z',
        'created_by' => 'probe',
        'reason' => 'manual',
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
            'provider_version' => 'probe',
            'identity' => ['host_label' => 'isolated', 'port' => 3306, 'database' => 'probe'],
            'payload' => [
                'path' => basename($dbPayload),
                'sha256' => hash_file('sha256', $dbPayload) ?: $hash,
                'size_bytes' => filesize($dbPayload) ?: 0,
            ],
        ],
        'filesystem' => [
            'provider' => 'ziparchive',
            'payload' => [
                'path' => basename($fsPayload),
                'sha256' => hash_file('sha256', $fsPayload) ?: $hash,
                'size_bytes' => filesize($fsPayload) ?: 0,
            ],
            'preservation_inventory_sha256' => $hash,
        ],
    ];
}

$tmp = sys_get_temp_dir() . '/susankhya-restore-rehearsal-probe-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);

try {
    $recoveryPoint = $tmp . '/recovery-point';
    $fixtureRoot = $tmp . '/fixture-root';
    $isolatedTarget = $tmp . '/isolated-target';
    mkdir($recoveryPoint, 0700, true);
    mkdir($fixtureRoot . '/app', 0700, true);
    mkdir($isolatedTarget, 0700, true);
    file_put_contents($fixtureRoot . '/app/bootstrap.php', '<?php return true;');

    $dbPayload = $recoveryPoint . '/database.sql.gz';
    file_put_contents($dbPayload, gzencode('-- rehearsal db'));
    $fsPayload = $recoveryPoint . '/filesystem.zip';
    (new FilesystemArchiveProvider())->createArchive($fixtureRoot, $fsPayload, ['app']);
    $metadata = rehearsal_metadata($dbPayload, $fsPayload);

    $service = new RestoreRehearsalService(static function (string $dumpPath, array $identity): array {
        $content = gzdecode((string)file_get_contents($dumpPath));
        return [
            'ok' => $content !== false && str_contains($content, 'rehearsal db') && ($identity['database'] ?? '') === 'probe',
            'errors' => [],
        ];
    });

    $result = $service->rehearse($metadata, $recoveryPoint, $isolatedTarget);
    rehearsal_assert($result['ok'] === true, 'restore rehearsal succeeds with disposable payloads');
    rehearsal_assert(($result['final_state'] ?? '') === 'restored', 'successful rehearsal reports restored final state');
    rehearsal_assert(is_file($isolatedTarget . '/app/bootstrap.php'), 'filesystem archive extracts to isolated target');
    rehearsal_assert(($result['filesystem']['entry_count'] ?? 0) === 1, 'filesystem rehearsal reports entry count');
    rehearsal_assert(is_string($result['filesystem']['entries_sha256'] ?? null), 'filesystem rehearsal reports entries checksum');
    rehearsal_assert(!file_exists(APP_ROOT . '/storage/recovery-points'), 'restore rehearsal does not create production recovery storage');

    $badMetadata = $metadata;
    $badMetadata['database']['payload']['sha256'] = str_repeat('b', 64);
    $bad = $service->rehearse($badMetadata, $recoveryPoint, $isolatedTarget);
    rehearsal_assert($bad['ok'] === false, 'checksum mismatch fails rehearsal');
    rehearsal_assert(($bad['final_state'] ?? '') === 'failed_without_mutation', 'checksum mismatch is non-mutating failure');

    $unsafeZip = $recoveryPoint . '/unsafe.zip';
    $zip = new ZipArchive();
    $zip->open($unsafeZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('../escape.txt', 'bad');
    $zip->close();
    $unsafeMetadata = rehearsal_metadata($dbPayload, $unsafeZip);
    $unsafe = $service->rehearse($unsafeMetadata, $recoveryPoint, $isolatedTarget);
    rehearsal_assert($unsafe['ok'] === false, 'unsafe archive path fails rehearsal');

    $dbFailService = new RestoreRehearsalService(static fn(): array => ['ok' => false, 'errors' => ['fake db fail']]);
    $dbFail = $dbFailService->rehearse($metadata, $recoveryPoint, $isolatedTarget);
    rehearsal_assert($dbFail['ok'] === false, 'database rehearsal callback failure blocks restore');
} finally {
    rehearsal_rm_tree($tmp);
}

echo "Restore rehearsal probe: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
