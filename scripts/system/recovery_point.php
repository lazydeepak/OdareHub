<?php
declare(strict_types=1);

use Platform\Recovery\FilesystemArchiveProvider;
use Platform\Recovery\MySqlDumpProvider;
use Platform\Recovery\MySqlRestoreProvider;
use Platform\Recovery\RecoveryPointService;
use Platform\Recovery\RecoveryProviderPreflightService;
use Platform\Recovery\RestoreRehearsalService;
use Platform\Updates\LocalReleaseApplyPreparationService;
use Platform\Updates\LocalReleaseChannelBuilderService;
use Platform\Updates\LocalReleaseChannelPreviewService;

$root = dirname(__DIR__, 2);
chdir($root);
define('APP_ROOT', $root);

require_once $root . '/vendor/autoload.php';
require_once $root . '/app/Core/helpers.php';

$args = $argv;
array_shift($args);
$command = array_shift($args) ?? 'help';
$options = parse_options($args);

if (in_array($command, ['help', '--help', '-h'], true)) {
    print_help();
    exit(0);
}

$preflight = new RecoveryProviderPreflightService(null, null, $root);
$inspection = $preflight->inspect();
$dumpPath = (string)($inspection['dump_executable']['path'] ?? '');
$service = new RecoveryPointService(
    $preflight,
    new MySqlDumpProvider($dumpPath),
    new FilesystemArchiveProvider()
);

if ($command === 'preflight') {
    print_json($service->preflight());
    exit(0);
}

if ($command === 'build-channel') {
    $channelDir = (string)($options['channel-dir'] ?? 'storage/update-channels/local');
    $releaseVersion = (string)($options['release-version'] ?? APP_VERSION);
    $buildId = git_head();
    $result = (new LocalReleaseChannelBuilderService())->build(
        $root,
        $channelDir,
        $releaseVersion,
        $buildId,
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'composer.json', 'composer.lock', 'bin'],
        ['storage', 'packages', '.git', 'tests', 'node_modules']
    );
    print_json($result);
    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

if ($command === 'preview-channel') {
    $channelDir = (string)($options['channel-dir'] ?? 'storage/update-channels/local');
    $recoveryDir = (string)($options['recovery-dir'] ?? '');
    $evidence = null;
    if ($recoveryDir !== '') {
        $evidence = [
            'verified' => is_file(rtrim($recoveryDir, '/') . '/recovery-point.json'),
            'rehearsed' => (bool)((json_decode((string)@file_get_contents(rtrim($recoveryDir, '/') . '/rehearsal.json'), true)['ok'] ?? false)),
        ];
    }
    $result = (new LocalReleaseChannelPreviewService())->preview($channelDir, ['product_version' => APP_VERSION], $evidence);
    print_json($result);
    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

if ($command === 'prepare-apply') {
    $channelDir = (string)($options['channel-dir'] ?? 'storage/update-channels/local');
    $recoveryDir = (string)($options['recovery-dir'] ?? '');
    $stagingDir = (string)($options['staging-dir'] ?? '');
    if ($recoveryDir === '' || $stagingDir === '') {
        fail_cli('prepare-apply requires --recovery-dir and --staging-dir');
    }
    if (!is_dir($stagingDir)) {
        fail_cli('apply staging directory must already exist');
    }

    $evidence = recovery_evidence($recoveryDir);
    $result = (new LocalReleaseApplyPreparationService())->prepare(
        $channelDir,
        $stagingDir,
        ['product_version' => APP_VERSION],
        $evidence
    );
    print_json($result);
    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

if ($command === 'verify') {
    $target = (string)($options['target-dir'] ?? '');
    if ($target === '') {
        fail_cli('verify requires --target-dir');
    }
    $result = $service->verify($target);
    print_json($result);
    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

if ($command === 'rehearse') {
    $target = (string)($options['target-dir'] ?? '');
    $isolatedDir = (string)($options['isolated-dir'] ?? '');
    $rehearsalDb = (string)($options['rehearsal-db'] ?? '');
    if ($target === '' || $isolatedDir === '' || $rehearsalDb === '') {
        fail_cli('rehearse requires --target-dir, --isolated-dir, and --rehearsal-db');
    }
    if (!is_dir($isolatedDir)) {
        fail_cli('isolated rehearsal directory must already exist');
    }

    $metadataPath = rtrim($target, '/') . '/recovery-point.json';
    $metadata = is_file($metadataPath) ? json_decode((string)file_get_contents($metadataPath), true) : null;
    if (!is_array($metadata)) {
        fail_cli('recovery metadata is unavailable or invalid');
    }
    $config = app_db_config();
    if (!is_array($config)) {
        fail_cli('database configuration is unavailable');
    }
    $mysqlPath = find_executable(['mysql', 'mariadb', 'mysql.exe', 'mariadb.exe']);
    if ($mysqlPath === null) {
        fail_cli('mysql client executable is unavailable');
    }

    $restoreProvider = new MySqlRestoreProvider($mysqlPath);
    $rehearsal = new RestoreRehearsalService(
        static fn(string $dumpPath, array $identity): array => $restoreProvider->rehearse($dumpPath, $config, $rehearsalDb, $identity)
    );
    $result = $rehearsal->rehearse($metadata, $target, $isolatedDir);
    $evidence = rehearsal_evidence($result, $metadata, $target, $isolatedDir, $rehearsalDb);
    if (!write_rehearsal_evidence($target, $evidence)) {
        fail_cli('unable to write rehearsal evidence');
    }
    print_json(compact_rehearsal_result($evidence));
    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

if ($command === 'create') {
    $target = (string)($options['target-dir'] ?? '');
    if ($target === '') {
        fail_cli('create requires --target-dir');
    }
    if (!is_dir($target)) {
        fail_cli('create target directory must already exist');
    }
    if (($inspection['ok'] ?? false) !== true) {
        print_json(['ok' => false, 'errors' => ['preflight must pass before create'], 'preflight' => $inspection]);
        exit(1);
    }

    $config = app_db_config();
    if (!is_array($config)) {
        fail_cli('database configuration is unavailable');
    }

    $result = $service->createRecoveryPoint(
        $config,
        $root,
        $target,
        (string)($options['created-by'] ?? (get_current_user() ?: 'system')),
        (string)($options['reason'] ?? 'manual'),
        [
            'release_version' => APP_VERSION,
            'build_id' => git_head(),
        ],
        ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'composer.json', 'composer.lock', 'bin'],
        ['storage', 'packages', '.git']
    );
    print_json($result);
    exit(($result['ok'] ?? false) === true ? 0 : 1);
}

fail_cli('unknown command: ' . $command);

/** @param array<int,string> $args @return array<string,string|bool> */
function parse_options(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if (!str_starts_with($arg, '--')) {
            continue;
        }
        $arg = substr($arg, 2);
        if (str_contains($arg, '=')) {
            [$key, $value] = explode('=', $arg, 2);
            $options[$key] = $value;
            continue;
        }
        $options[$arg] = true;
    }
    return $options;
}

function print_help(): void
{
    echo "Susankhya recovery point tool\n\n";
    echo "Usage:\n";
    echo "  php scripts/system/recovery_point.php preflight\n";
    echo "  php scripts/system/recovery_point.php create --target-dir=/explicit/writable/dir [--reason=manual] [--created-by=name]\n";
    echo "  php scripts/system/recovery_point.php verify --target-dir=/existing/recovery-point/dir\n";
    echo "  php scripts/system/recovery_point.php rehearse --target-dir=/existing/recovery-point/dir --isolated-dir=/empty/dir --rehearsal-db=susankhya_rehearsal\n";
    echo "  php scripts/system/recovery_point.php build-channel [--channel-dir=storage/update-channels/local] [--release-version=0.5.0]\n";
    echo "  php scripts/system/recovery_point.php preview-channel [--channel-dir=storage/update-channels/local] [--recovery-dir=/path/to/recovery-point]\n";
    echo "  php scripts/system/recovery_point.php prepare-apply --channel-dir=storage/update-channels/local --recovery-dir=/path/to/recovery-point --staging-dir=/empty/staging/dir\n";
}

/** @param array<string,mixed> $data */
function print_json(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

function fail_cli(string $message): void
{
    fwrite(STDERR, $message . "\n");
    exit(2);
}

/** @return array<string,bool> */
function recovery_evidence(string $recoveryDir): array
{
    $recoveryDir = rtrim($recoveryDir, '/');
    return [
        'verified' => is_file($recoveryDir . '/recovery-point.json'),
        'rehearsed' => (bool)((json_decode((string)@file_get_contents($recoveryDir . '/rehearsal.json'), true)['ok'] ?? false)),
    ];
}

function git_head(): string
{
    $head = trim((string)@file_get_contents('.git/HEAD'));
    if ($head === '') {
        return 'unknown';
    }
    if (str_starts_with($head, 'ref: ')) {
        $ref = trim(substr($head, 5));
        $hash = trim((string)@file_get_contents('.git/' . $ref));
        return $hash !== '' ? 'git:' . $hash : 'unknown';
    }
    return 'git:' . $head;
}

/**
 * @param array<string,mixed> $result
 * @param array<string,mixed> $metadata
 * @return array<string,mixed>
 */
function rehearsal_evidence(array $result, array $metadata, string $target, string $isolatedDir, string $rehearsalDb): array
{
    return [
        'schema_version' => 'susankhya.rehearsal.v1',
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'recovery_point_id' => (string)($metadata['recovery_point_id'] ?? ''),
        'recovery_point_dir' => $target,
        'isolated_dir' => $isolatedDir,
        'rehearsal_database' => $rehearsalDb,
        'ok' => (bool)($result['ok'] ?? false),
        'final_state' => (string)($result['final_state'] ?? 'failed_without_mutation'),
        'errors' => $result['errors'] ?? [],
        'warnings' => $result['warnings'] ?? [],
        'database' => [
            'ok' => (bool)($result['database']['ok'] ?? false),
            'provider' => (string)($result['database']['provider'] ?? ''),
            'rehearsal_database' => (string)($result['database']['rehearsal_database'] ?? $rehearsalDb),
        ],
        'filesystem' => [
            'ok' => (bool)($result['filesystem']['ok'] ?? false),
            'entry_count' => (int)($result['filesystem']['entry_count'] ?? 0),
            'entries_sha256' => (string)($result['filesystem']['entries_sha256'] ?? ''),
            'sample_entries' => $result['filesystem']['sample_entries'] ?? [],
        ],
    ];
}

/** @param array<string,mixed> $evidence */
function write_rehearsal_evidence(string $target, array $evidence): bool
{
    $path = rtrim($target, '/') . '/rehearsal.json';
    $encoded = json_encode($evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return $encoded !== false && file_put_contents($path, $encoded . "\n") !== false;
}

/**
 * @param array<string,mixed> $evidence
 * @return array<string,mixed>
 */
function compact_rehearsal_result(array $evidence): array
{
    return [
        'ok' => $evidence['ok'] ?? false,
        'final_state' => $evidence['final_state'] ?? '',
        'errors' => $evidence['errors'] ?? [],
        'warnings' => $evidence['warnings'] ?? [],
        'recovery_point_id' => $evidence['recovery_point_id'] ?? '',
        'rehearsal_evidence_path' => rtrim((string)($evidence['recovery_point_dir'] ?? ''), '/') . '/rehearsal.json',
        'database' => $evidence['database'] ?? [],
        'filesystem' => [
            'ok' => (bool)($evidence['filesystem']['ok'] ?? false),
            'entry_count' => (int)($evidence['filesystem']['entry_count'] ?? 0),
            'entries_sha256' => (string)($evidence['filesystem']['entries_sha256'] ?? ''),
        ],
    ];
}

/** @param array<int,string> $candidates */
function find_executable(array $candidates): ?string
{
    $path = getenv('PATH') ?: '';
    $separator = str_contains($path, ';') ? ';' : PATH_SEPARATOR;
    foreach (array_filter(array_map('trim', explode($separator, $path))) as $directory) {
        foreach ($candidates as $candidate) {
            $absolute = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate;
            if (is_file($absolute) && is_executable($absolute)) {
                return $absolute;
            }
        }
    }
    return null;
}
