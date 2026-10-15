<?php
declare(strict_types=1);

/**
 * OH-AUTO1-078 disposable execution rehearsal.
 *
 * This is the first test entry point allowed to mutate anything: every
 * mutation target is created under one runner-owned temporary root, and a
 * separately started --no-defaults MySQL process is required. It refuses to
 * use the repository or the installed ServBay database.
 */

$repository = dirname(__DIR__, 3);
require_once $repository . '/vendor/autoload.php';
require_once $repository . '/scripts/testing/IsolatedSourceRootTestRunner.php';
require_once $repository . '/scripts/testing/DisposableDbExecutionAuthorizer.php';

use App\Core\DB;
use App\Services\AppMigrationService;
use Platform\Health\PostUpdateHealthVerifier;
use Platform\Recovery\FilesystemArchiveProvider;
use Platform\Recovery\MySqlDumpProvider;
use Platform\Recovery\RecoveryPointService;
use Platform\Recovery\RestoreRehearsalService;
use Platform\Updates\DisposableAdministratorApprovalAuthority;
use Platform\Updates\DisposableReleaseTransactionJournal;
use Platform\Updates\DisposableReleaseTransactionService;
use Platform\Updates\DisposableTrustProvisioner;
use Platform\Updates\InstallationIdentityStore;
use Platform\Updates\InstallationOwnedTrustStore;
use Platform\Updates\InstalledPlatformIdentityVerifier;
use Platform\Updates\InstalledSystemEvidenceProvider;
use Platform\Updates\LocalReleaseChannelBuilderService;
use Platform\Updates\OfflineReleaseArtifactVerifier;
use Platform\Updates\ReleaseMigrationPlanValidator;
use Platform\Updates\ReleaseStatementCanonicalizer;
use Platform\Updates\TrustedReleaseCoordinatorPreflightService;
use Platform\Updates\TrustedReleasePreflightEvidence;

$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

/** @param array<int,string> $command */
$run = static function (array $command, ?string $cwd = null): array {
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('unable to start command: ' . implode(' ', $command));
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        throw new RuntimeException('command failed (' . $exit . '): ' . implode(' ', $command) . "\n" . $stderr);
    }
    return ['stdout' => (string)$stdout, 'stderr' => (string)$stderr];
};

/** @param array<int,string> $command @return array{exit_code:int,stdout:string,stderr:string} */
$runWithInput = static function (array $command, string $input, ?string $cwd = null): array {
    $pipes = [];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if (!is_resource($process)) throw new RuntimeException('unable to start input command: ' . implode(' ', $command));
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return ['exit_code' => proc_close($process), 'stdout' => (string)$stdout, 'stderr' => (string)$stderr];
};

$rm = static function (string $path) use (&$rm): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $rm($path . '/' . $entry);
    @rmdir($path);
};

$copyTree = static function (string $source, string $target) use (&$copyTree): void {
    if (is_link($source)) throw new RuntimeException('symlink in restore source: ' . $source);
    if (is_dir($source)) {
        if (!is_dir($target) && !mkdir($target, 0700, true)) throw new RuntimeException('cannot create restore directory: ' . $target);
        foreach (scandir($source) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $copyTree($source . '/' . $entry, $target . '/' . $entry);
        return;
    }
    if (!is_file($source) || file_put_contents($target, (string)file_get_contents($source)) === false) throw new RuntimeException('restore copy failed: ' . $source);
};

$temporaryRoot = realpath(sys_get_temp_dir());
if ($temporaryRoot === false) {
    fwrite(STDERR, "OH-AUTO1-078 BLOCKED/FAILED: system temporary root is unavailable\n");
    exit(2);
}
$workspace = rtrim($temporaryRoot, '/') . '/o78-' . bin2hex(random_bytes(5));
$sourceWorkspace = null;
$mysqld = null;
$root = $workspace . '/resources';
$install = $root . '/installation';
$database = 'oh078_' . bin2hex(random_bytes(5));
$dbUser = 'oh078u_' . bin2hex(random_bytes(4));
$dbPass = bin2hex(random_bytes(16));
$portProbe = stream_socket_server('tcp://127.0.0.1:0', $portError, $portMessage);
if (!is_resource($portProbe)) {
    fwrite(STDERR, "OH-AUTO1-079 BLOCKED/FAILED: disposable port allocation failed\n");
    exit(2);
}
$portName = stream_socket_get_name($portProbe, false);
fclose($portProbe);
$dbPort = (int)substr(strrchr((string)$portName, ':'), 1);
if ($dbPort < 1) {
    fwrite(STDERR, "OH-AUTO1-079 BLOCKED/FAILED: disposable port allocation returned no port\n");
    exit(2);
}
$socket = $root . '/mysql.sock';
$datadir = $root . '/mysql-data';
$pidFile = $root . '/mysql.pid';
$dbLog = $root . '/mysql.log';
$environment = 'disposable-release-rehearsal-v1';

$executeMysql = null;
    $signingSecret = null;
$signingPublic = null;
$trustFingerprint = null;

try {
    if (realpath($repository) === false || str_starts_with(realpath($repository) ?: '', realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('repository root was unexpectedly redirected into temporary storage');
    }
    if (!mkdir($root, 0700, true)) throw new RuntimeException('runner root could not be created');

    $preparedPath = rtrim($temporaryRoot, '/') . '/o78s-' . bin2hex(random_bytes(5));
    $prepared = IsolatedSourceRootTestRunner::prepare($repository, $preparedPath);
    $sourceWorkspace = $prepared['workspace'];
    $sourceRoot = $prepared['source_root'];
    $assert(($prepared['manifest']['database_connected'] ?? true) === false, 'source-root preparation records no database connection');
    $assert(realpath($sourceRoot) !== false && !str_starts_with(realpath($sourceRoot), realpath($repository) . DIRECTORY_SEPARATOR), 'source root is outside repository');

    if (!is_dir($sourceRoot . '/apps/Platform/Resources') && !mkdir($sourceRoot . '/apps/Platform/Resources', 0700, true)) throw new RuntimeException('fixture resources directory could not be created');
    file_put_contents($sourceRoot . '/apps/Platform/Resources/oh078-release-marker.txt', "release-v1\n");
    if (!is_dir($sourceRoot . '/apps/Platform/migrations') && !mkdir($sourceRoot . '/apps/Platform/migrations', 0700, true)) throw new RuntimeException('fixture migrations directory could not be created');
    // OH-AUTO1-044 intentionally stages application/platform sources only.
    // Supply a tiny runner-owned public/resources/bin surface so the release
    // package contract is exercised without copying installed delivery files.
    mkdir($sourceRoot . '/public', 0700, true);
    mkdir($sourceRoot . '/resources', 0700, true);
    mkdir($sourceRoot . '/bin', 0700, true);
    file_put_contents($sourceRoot . '/public/index.php', "<?php\n// disposable OH-AUTO1-078 public fixture\n");
    file_put_contents($sourceRoot . '/resources/oh078-runtime.txt', "runtime-v1\n");
    file_put_contents($sourceRoot . '/bin/oh078-runtime', "#!/bin/sh\nexit 0\n");

    $builder = new LocalReleaseChannelBuilderService();
    $include = ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock'];
    $initialChannel = $workspace . '/initial-channel';
    $initialBuild = $builder->build($sourceRoot, $initialChannel, '1.0.0', 'fixture:oh078-initial', $include);
    if (($initialBuild['ok'] ?? false) !== true) throw new RuntimeException('initial package build failed');
    $candidateMigration = "CREATE TABLE IF NOT EXISTS oh078_release_marker (id INT NOT NULL PRIMARY KEY, value VARCHAR(80) NOT NULL);\n";
    file_put_contents($sourceRoot . '/apps/Platform/migrations/oh078_001_release.sql', $candidateMigration);
    file_put_contents($sourceRoot . '/apps/Platform/Resources/oh078-release-marker.txt', "release-v2\n");
    $candidateChannel = $workspace . '/candidate-channel';
    $candidateBuild = $builder->build($sourceRoot, $candidateChannel, '2.0.0', 'fixture:oh078-candidate', $include);
    if (($candidateBuild['ok'] ?? false) !== true) throw new RuntimeException('candidate package build failed');

    $candidateMetadataPath = $candidateBuild['metadata_path'];
    $candidateMetadata = json_decode((string)file_get_contents($candidateMetadataPath), true, 512, JSON_THROW_ON_ERROR);
    $candidatePackage = $candidateBuild['package_path'];
    $sourcePath = 'apps/Platform/migrations/oh078_001_release.sql';
    $step = [
        'position' => 1, 'app_key' => 'Platform', 'version' => '2.0.0',
        'migration_name' => 'oh078_001_release.sql', 'checksum' => hash('sha256', $candidateMigration), 'requires' => [],
    ];
    $plan = [
        'plan_version' => ReleaseMigrationPlanValidator::PLAN_VERSION,
        'plan_id' => 'fixture:oh078-candidate', 'source' => ReleaseMigrationPlanValidator::SOURCE,
        'validation_state' => 'validated', 'steps' => [$step],
    ];
    $plan['plan_fingerprint'] = ReleaseMigrationPlanValidator::fingerprint($plan['plan_version'], $plan['plan_id'], $plan['steps']);
    $inventory = [
        'contract_version' => ReleaseMigrationPlanValidator::PLAN_VERSION,
        'plan_id' => $plan['plan_id'], 'target_schema' => 'schema:2.0.0',
        'plan_fingerprint' => $plan['plan_fingerprint'], 'steps' => [$step],
        'source_entries' => [['identity' => 'Platform:oh078_001_release.sql', 'source_path' => $sourcePath, 'sha256' => $step['checksum']]],
    ];
    $candidateMetadata['compatibility']['schema']['target'] = 'schema:2.0.0';
    $candidateMetadata['migration_inventory'] = $inventory;
    file_put_contents($candidateMetadataPath, json_encode($candidateMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $zip = new ZipArchive();
    if ($zip->open($initialBuild['package_path']) !== true || !$zip->extractTo($install)) throw new RuntimeException('initial package extraction failed');
    $zip->close();
    mkdir($install . '/storage', 0700, true);
    $config = ['host' => '127.0.0.1', 'port' => $dbPort, 'name' => $database, 'user' => $dbUser, 'pass' => $dbPass, 'timezone' => 'UTC', 'charset' => 'utf8mb4'];
    file_put_contents($install . '/storage/db_config.php', '<?php return ' . var_export($config, true) . ';' . "\n");
    if (realpath($install) === false || str_starts_with(realpath($install), realpath($repository) . DIRECTORY_SEPARATOR)) throw new RuntimeException('installation root is not isolated');

    $run(['/opt/homebrew/bin/mysqld', '--no-defaults', '--initialize-insecure', '--datadir=' . $datadir, '--basedir=/opt/homebrew/opt/mysql']);
    $mysqld = proc_open(['/opt/homebrew/bin/mysqld', '--no-defaults', '--datadir=' . $datadir, '--socket=' . $socket, '--pid-file=' . $pidFile, '--log-error=' . $dbLog, '--bind-address=127.0.0.1', '--port=' . $dbPort, '--mysqlx=0', '--user=' . get_current_user()], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($mysqld)) throw new RuntimeException('disposable MySQL could not start');
    fclose($pipes[0]); fclose($pipes[1]); fclose($pipes[2]);
    for ($i = 0; $i < 30 && !is_file($socket); $i++) usleep(100000);
    if (!file_exists($socket)) throw new RuntimeException('disposable MySQL socket did not appear: ' . (is_file($dbLog) ? (string)file_get_contents($dbLog) : 'no MySQL log'));
    $executeMysql = static function (string $sql, ?string $db = null) use ($run, $socket, $dbUser, $dbPass): void {
        $command = ['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '--user=' . $dbUser, '--password=' . $dbPass];
        if ($db !== null) $command[] = $db;
        $command[] = '--execute=' . $sql;
        $run($command);
    };
    $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', '--execute=CREATE DATABASE `' . $database . '`; CREATE USER \'' . $dbUser . '\'@\'localhost\' IDENTIFIED BY \'' . $dbPass . '\'; GRANT ALL ON `' . $database . '`.* TO \'' . $dbUser . '\'@\'localhost\'; GRANT RELOAD, FLUSH_TABLES ON *.* TO \'' . $dbUser . '\'@\'localhost\'; FLUSH PRIVILEGES;']);
    $authorizer = DisposableDbExecutionAuthorizer::authorize((int)trim((string)file_get_contents($pidFile)), $socket, $datadir, $root, $dbPort);
    $assert($authorizer['authorized'] === true, 'independent MySQL process/socket/datadir attestation passes');
    if ($authorizer['authorized'] !== true) throw new RuntimeException('disposable database attestation failed before mutation: ' . json_encode($authorizer));

    define('APP_ROOT', $install);
    // The coordinator is already loaded from the repository test harness. Do
    // not load the package's Composer bootstrap into this PHP process: both
    // Composer loaders use the same generated class name. Runtime files are
    // still activated only under the isolated APP_ROOT below.
    $executeMysql('CREATE TABLE core_settings (setting_key VARCHAR(190) PRIMARY KEY, setting_value TEXT NOT NULL); CREATE TABLE installed_plugins (name VARCHAR(190), version VARCHAR(50), status VARCHAR(30), requires_json TEXT);', $database);
    $service = new AppMigrationService();
    $service->ensureCoreTables();
    DB::query('INSERT INTO core_settings (setting_key, setting_value) VALUES (?, ?), (?, ?)', ['product_id', 'susankhya-os', 'platform.product_id', 'susankhya-os']);
    DB::query('INSERT INTO core_apps (app_key, app_name, version, app_type, status, install_path, manifest_json) VALUES (?, ?, ?, ?, ?, ?, ?)', ['Platform', 'Platform', '1.0.0', 'app', 'installed', $install . '/apps/Platform', json_encode(['build_id' => 'fixture:oh078-initial'])]);
    $baselineSql = 'baseline:oh078:v1';
    DB::query('INSERT INTO core_app_migrations (app_key, version, migration_name, checksum, status) VALUES (?, ?, ?, ?, ?)', ['Platform', '1.0.0', 'baseline.sql', hash('sha256', $baselineSql), 'applied']);
    $snapshot = json_encode(['schema_version' => '1.0.0', 'identity' => 'schema:1.0.0'], JSON_UNESCAPED_SLASHES);
    DB::query('INSERT INTO core_schema_snapshots (app_key, app_version, snapshot_json, checksum) VALUES (?, ?, ?, ?)', ['Platform', '1.0.0', $snapshot, hash('sha256', $snapshot)]);
    $baselineTableRows = DB::fetchAll('SHOW TABLES');
    $baselineTables = array_values(array_map(static fn(array $row): string => (string)reset($row), $baselineTableRows));
    sort($baselineTables);
    $baselineDatabaseExpectation = [
        'tables' => $baselineTables,
        'product_id' => 'susankhya-os',
        'app_version' => '1.0.0',
        'migration_name' => 'baseline.sql',
        'migration_checksum' => hash('sha256', $baselineSql),
        'schema_identity' => 'schema:1.0.0',
    ];

    $initialStatementFields = ['product_id' => 'susankhya-os', 'release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial', 'lane' => 'app', 'package_sha256' => $initialBuild['package_sha256'], 'package_bytes' => (string)$initialBuild['package_size_bytes'], 'metadata_sha256' => hash_file('sha256', $initialBuild['metadata_path']), 'minimum_supported_schema' => '1.0.0', 'created_at' => gmdate('Y-m-d\TH:i:s\Z')];
    $canonicalizer = new ReleaseStatementCanonicalizer();
    $initialStatement = $canonicalizer->canonicalize($initialStatementFields);
    $keyPair = sodium_crypto_sign_keypair(); $signingSecret = sodium_crypto_sign_secretkey($keyPair); $signingPublic = sodium_crypto_sign_publickey($keyPair); $trustFingerprint = hash('sha256', $signingPublic);
    $trustRecord = ['key_id' => 'fixture-oh078', 'algorithm' => 'ed25519', 'public_key' => $signingPublic, 'fingerprint' => $trustFingerprint, 'status' => 'active', 'not_before' => '2026-01-01T00:00:00Z', 'not_after' => null];
    $trustStore = new InstallationOwnedTrustStore($root . '/trust', [$repository]);
    mkdir($root . '/trust', 0700, true);
    $trustStore->provisionDisposable($trustRecord, $trustFingerprint, true, DisposableTrustProvisioner::INDEPENDENT_EXPECTED_FINGERPRINT);
    $trustStorageFingerprint = (string)($trustStore->read()['fingerprint'] ?? '');
    $initialIdentity = ['installation_id' => 'fixture-oh078-installation', 'product_id' => 'susankhya-os', 'release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial', 'lane' => 'app', 'package_sha256' => $initialBuild['package_sha256'], 'release_statement_fingerprint' => hash('sha256', $initialStatement), 'signing_key_id' => 'fixture-oh078', 'installed_at' => gmdate('Y-m-d\TH:i:s\Z'), 'schema_state_identity' => 'schema:1.0.0', 'record_version' => InstalledPlatformIdentityVerifier::RECORD_VERSION];
    $identityStore = new InstallationIdentityStore($root . '/identity', [$repository]);
    mkdir($root . '/identity', 0700, true);
    $identityObservation = ['package' => ['sha256' => $initialIdentity['package_sha256']], 'signed_release' => ['product_id' => 'susankhya-os', 'release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial', 'lane' => 'app', 'statement_fingerprint' => $initialIdentity['release_statement_fingerprint'], 'signing_key_id' => 'fixture-oh078'], 'schema' => ['identity' => 'schema:1.0.0'], 'application_registry' => ['build_id' => 'fixture:oh078-initial', 'version' => '1.0.0']];
    $identityStore->create($initialIdentity, $identityObservation);
    $initialIdentityFingerprint = (string)($identityStore->read()['fingerprint'] ?? '');

    $state = static function () use ($install): array {
        $apps = DB::fetchAll('SELECT app_key, app_name, version, app_type, status, install_path, manifest_json FROM core_apps ORDER BY id ASC');
        $rows = DB::fetchAll('SELECT id, app_key, version, migration_name, checksum, status FROM core_app_migrations ORDER BY id ASC');
        $schemaRows = DB::fetchAll('SELECT app_key, app_version, snapshot_json, checksum FROM core_schema_snapshots ORDER BY id ASC');
        $snapshot = json_decode((string)($schemaRows[count($schemaRows) - 1]['snapshot_json'] ?? '{}'), true);
        $migrations = ['pending' => [], 'failed' => [], 'order' => [], 'evidence' => ['identities_verified' => true, 'ownership_verified' => true, 'checksums_verified' => true, 'ordering_verified' => true, 'errors' => []]];
        foreach ($rows as $row) { $key = $row['app_key'] . ':' . $row['migration_name']; $migrations['order'][] = $key; if ($row['status'] === 'failed') $migrations['failed'][] = $key; }
        $platformManifest = json_decode((string)($apps[0]['manifest_json'] ?? '{}'), true);
        return ['product_id' => 'susankhya-os', 'product_version' => (string)($apps[0]['version'] ?? ''), 'platform' => ['product_id' => 'susankhya-os', 'version' => (string)($apps[0]['version'] ?? ''), 'build_id' => (string)($platformManifest['build_id'] ?? 'fixture:oh078-initial'), 'status' => 'installed'], 'apps' => array_map(static fn(array $row): array => ['id' => $row['app_key'], 'version' => $row['version'], 'status' => $row['status'], 'requires' => []], $apps), 'modules' => [], 'schema' => ['version' => (string)($snapshot['schema_version'] ?? ''), 'identity' => (string)($snapshot['identity'] ?? '')], 'migrations' => $migrations];
    };
    $installedState = $state();
    $recoveryDir = $root . '/recovery-before-update'; mkdir($recoveryDir, 0700, true);
    $recovery = new RecoveryPointService(new Platform\Recovery\RecoveryProviderPreflightService(), new MySqlDumpProvider('/opt/homebrew/bin/mysqldump'), new FilesystemArchiveProvider());
    $recoveryResult = $recovery->createRecoveryPoint($config, $install, $recoveryDir, 'oh-auto1-078-runner', 'update_apply', ['product_id' => 'susankhya-os', 'release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial', 'schema_state' => ['identity' => 'schema:1.0.0'], 'migration_state' => $installedState['migrations']], ['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock'], []);
    if (($recoveryResult['ok'] ?? false) !== true) throw new RuntimeException('initial recovery point failed: ' . json_encode($recoveryResult));
    $restoreFiles = $root . '/recovery-files'; mkdir($restoreFiles, 0700, true);
    $restoreDbName = $database . '_restore';
    $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', '--execute=CREATE DATABASE `' . $restoreDbName . '`; GRANT ALL ON `' . $restoreDbName . '`.* TO \'' . $dbUser . '\'@\'localhost\'; FLUSH PRIVILEGES;']);
    $restore = new RestoreRehearsalService(static function (string $dumpPath, array $identity) use ($run, $runWithInput, $socket, $dbUser, $dbPass, $restoreDbName, $baselineDatabaseExpectation): array {
        if (!is_file($dumpPath) || filesize($dumpPath) === 0) return ['ok' => false, 'verified' => false, 'errors' => ['database dump is missing or empty']];
        $compressed = file_get_contents($dumpPath);
        $sql = is_string($compressed) ? gzdecode($compressed) : false;
        if (!is_string($sql) || $sql === '') return ['ok' => false, 'verified' => false, 'errors' => ['database dump could not be decompressed']];
        $sql = preg_replace('/^.*(?:GTID_PURGED|SQL_LOG_BIN).*?(?:;|\R)$/mi', '', $sql) ?? $sql;
        $reset = $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', '--execute=DROP DATABASE IF EXISTS `' . $restoreDbName . '`; CREATE DATABASE `' . $restoreDbName . '`; GRANT ALL ON `' . $restoreDbName . '`.* TO \'' . $dbUser . '\'@\'localhost\'; FLUSH PRIVILEGES;']);
        $import = $runWithInput(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '--user=' . $dbUser, '--password=' . $dbPass, $restoreDbName], $sql);
        if ($import['exit_code'] !== 0) return ['ok' => false, 'verified' => false, 'errors' => ['database restore import failed'], 'stderr' => $import['stderr']];
        $query = static function (string $sql) use ($run, $socket, $dbUser, $dbPass, $restoreDbName): string {
            return $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '--user=' . $dbUser, '--password=' . $dbPass, $restoreDbName, '--batch', '--skip-column-names', '--execute=' . $sql])['stdout'];
        };
        $tables = array_values(array_filter(array_map('trim', preg_split('/\R/', $query('SHOW TABLES;')) ?: [])));
        sort($tables);
        $expectedTables = $baselineDatabaseExpectation['tables'];
        sort($expectedTables);
        $productId = trim($query("SELECT setting_value FROM core_settings WHERE setting_key='product_id';"));
        $appVersion = trim($query("SELECT version FROM core_apps WHERE app_key='Platform';"));
        $migration = trim($query("SELECT migration_name, checksum FROM core_app_migrations WHERE app_key='Platform' AND status='applied' ORDER BY id ASC;"));
        $schemaIdentity = trim($query("SELECT JSON_UNQUOTE(JSON_EXTRACT(snapshot_json, '$.identity')) FROM core_schema_snapshots WHERE app_key='Platform' ORDER BY id DESC LIMIT 1;"));
        $expectedMigration = $baselineDatabaseExpectation['migration_name'] . "\t" . $baselineDatabaseExpectation['migration_checksum'];
        $verified = $tables === $expectedTables && $productId === $baselineDatabaseExpectation['product_id'] && $appVersion === $baselineDatabaseExpectation['app_version'] && $migration === $expectedMigration && $schemaIdentity === $baselineDatabaseExpectation['schema_identity'];
        return ['ok' => $verified, 'verified' => $verified, 'database' => $restoreDbName, 'identity' => $identity, 'observations' => compact('tables', 'productId', 'appVersion', 'migration', 'schemaIdentity'), 'reset_exit' => $reset['stdout'], 'import_exit' => $import['exit_code'], 'errors' => $verified ? [] : ['restored database observations differ from baseline']];
    });
    $rehearsal = $restore->rehearse($recoveryResult['metadata'], $recoveryDir, $restoreFiles);
    if (($rehearsal['ok'] ?? false) !== true || ($rehearsal['database']['verified'] ?? false) !== true) throw new RuntimeException('pre-update restore rehearsal did not verify the restored database: ' . json_encode($rehearsal));
    file_put_contents($recoveryDir . '/rehearsal.json', json_encode(['schema_version' => 'susankhya.rehearsal.v1', 'recovery_point_id' => $recoveryResult['recovery_point_id'], 'ok' => true, 'final_state' => 'restored', 'database' => ['ok' => true, 'verified' => true, 'observations' => $rehearsal['database']['observations']], 'filesystem' => ['ok' => ($rehearsal['filesystem']['ok'] ?? false), 'verified' => true, 'content_sha256' => $rehearsal['filesystem']['content_sha256'] ?? null]], JSON_PRETTY_PRINT));

    $provider = new InstalledSystemEvidenceProvider(static function (): array {
        $rows = DB::fetchAll('SELECT app_key, app_name, version, app_type, status, install_path, manifest_json FROM core_apps ORDER BY id ASC');
        foreach ($rows as &$row) {
            $manifest = json_decode((string)($row['manifest_json'] ?? '{}'), true);
            $row['build_id'] = is_array($manifest) ? ($manifest['build_id'] ?? '') : '';
        }
        unset($row);
        return $rows;
    }, static fn(): array => [], static fn(): array => DB::fetchAll('SELECT setting_key, setting_value FROM core_settings ORDER BY setting_key ASC'), static fn(): array => DB::fetchAll('SELECT id, app_key, version, migration_name, checksum, status FROM core_app_migrations ORDER BY id ASC'), static fn(): array => DB::fetchAll('SELECT app_key, app_version, snapshot_json, checksum FROM core_schema_snapshots ORDER BY id ASC'));
    $coordinator = new TrustedReleaseCoordinatorPreflightService($trustStore, $identityStore, new OfflineReleaseArtifactVerifier(), new InstalledPlatformIdentityVerifier(), null, $provider);
    $candidateMetadata['package']['sha256'] = hash_file('sha256', $candidatePackage); $candidateMetadata['package']['size_bytes'] = filesize($candidatePackage); file_put_contents($candidateMetadataPath, json_encode($candidateMetadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    $candidateStatementFields = ['product_id' => 'susankhya-os', 'release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate', 'lane' => 'app', 'package_sha256' => $candidateMetadata['package']['sha256'], 'package_bytes' => (string)$candidateMetadata['package']['size_bytes'], 'metadata_sha256' => hash_file('sha256', $candidateMetadataPath), 'minimum_supported_schema' => '1.0.0', 'created_at' => gmdate('Y-m-d\TH:i:s\Z')];
    $candidateStatement = $canonicalizer->canonicalize($candidateStatementFields); $candidateSignature = sodium_crypto_sign_detached($candidateStatement, $signingSecret);
    $identityObservations = $identityObservation;
    $migrationBinding = ['plan' => $plan, 'source_entries' => $inventory['source_entries'], 'release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate', 'package_sha256' => $candidateMetadata['package']['sha256'], 'statement_fingerprint' => hash('sha256', $candidateStatement), 'target_schema' => 'schema:2.0.0'];
    $preflightRaw = $coordinator->preflight($candidatePackage, $candidateMetadataPath, $candidateStatement, $candidateSignature, 'fixture-oh078', gmdate('Y-m-d\TH:i:s\Z'), $identityObservations, $installedState, $migrationBinding, $recoveryDir);
    $assert(($preflightRaw['state'] ?? null) === TrustedReleaseCoordinatorPreflightService::PREFLIGHT_VERIFIED, 'fresh coordinator preflight verifies signed candidate');
    if (($preflightRaw['state'] ?? null) !== TrustedReleaseCoordinatorPreflightService::PREFLIGHT_VERIFIED) throw new RuntimeException('coordinator preflight blocked: ' . json_encode($preflightRaw));
    $issueFreshEvidence = static function (TrustedReleasePreflightEvidence $candidate) use ($coordinator, $candidatePackage, $candidateMetadataPath, $candidateStatement, $candidateSignature, $identityObservations, $installedState, $migrationBinding, $recoveryDir): TrustedReleasePreflightEvidence {
        $fresh = $coordinator->preflight($candidatePackage, $candidateMetadataPath, $candidateStatement, $candidateSignature, 'fixture-oh078', gmdate('Y-m-d\TH:i:s\Z'), $identityObservations, $installedState, $migrationBinding, $recoveryDir);
        if (($fresh['state'] ?? null) !== TrustedReleaseCoordinatorPreflightService::PREFLIGHT_VERIFIED) throw new RuntimeException('fresh coordinator revalidation was blocked');
        $freshEvidence = TrustedReleasePreflightEvidence::fromCoordinatorResult($fresh);
        if (!hash_equals($freshEvidence->fingerprint(), $candidate->fingerprint())) throw new RuntimeException('caller evidence differs from fresh coordinator evidence');
        return $freshEvidence;
    };
    $forgedValues = $preflightRaw;
    $forgedValues['diagnostics'][] = ['gate' => 'forged-caller-observation', 'ok' => true];
    $forgedEvidence = TrustedReleasePreflightEvidence::fromCoordinatorResult($forgedValues);
    $forgedRejected = false;
    try { $issueFreshEvidence($forgedEvidence); } catch (Throwable) { $forgedRejected = true; }
    $assert($forgedRejected, 'forged public evidence wrapper cannot cross fresh coordinator boundary');
    $evidence = $issueFreshEvidence(TrustedReleasePreflightEvidence::fromCoordinatorResult($preflightRaw));
    $journal = new DisposableReleaseTransactionJournal($root . '/transaction-journal'); $transactions = new DisposableReleaseTransactionService($journal); $approvals = new DisposableAdministratorApprovalAuthority($journal);
    $transaction = $transactions->prepare($evidence, $environment); $transactionId = $transaction['record']['transaction_id']; $challenge = $approvals->issue($transactionId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC'))); $approvals->approve($transactionId, 'simulated:oh078-admin', $challenge['token'], $evidence, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $expectedHealth = ['release_identity' => ['release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate'], 'target_schema' => ['identity' => 'schema:2.0.0'], 'migration_ledger' => ['plan_fingerprint' => $plan['plan_fingerprint'], 'rows' => ['Platform:baseline.sql', 'Platform:oh078_001_release.sql'], 'checksums' => ['Platform:baseline.sql' => hash('sha256', $baselineSql), 'Platform:oh078_001_release.sql' => $step['checksum']]], 'bootstrap' => ['available' => true], 'application_discovery' => ['applications' => ['Platform']], 'critical_connectivity' => ['reachable' => true], 'configuration_trust' => ['identity_fingerprint' => $preflightRaw['installation_identity_fingerprint'], 'trust_fingerprint' => ($trustStore->read()['fingerprint'] ?? '')], 'protected_paths' => ['integrity' => hash_file('sha256', $install . '/storage/db_config.php')], 'unresolved_state' => ['unresolved_migrations' => false, 'unresolved_recovery' => false], 'health_binding' => $transaction['record']['health_binding']];
    $transactions->bindHealthExpectations($transactionId, $expectedHealth, $environment);
    $transactions->markExecuting($transactionId, $evidence, $environment);
    $migrationResult = (new AppMigrationService())->executePlannedStep('Platform', '2.0.0', $sourceRoot . '/apps/Platform/migrations', 'oh078_001_release.sql', $step['checksum'], 1, ReleaseMigrationPlanValidator::validate($plan)['evidence']);
    $assert(($migrationResult['state'] ?? null) === 'migration_applied', 'exact candidate migration executes in isolated database');
    // Activate only the package-owned fixture files that differ. This avoids
    // duplicating the complete Composer tree in the temporary filesystem;
    // every byte still comes from the authenticated candidate ZIP.
    $candidateZip = new ZipArchive();
    if ($candidateZip->open($candidatePackage) !== true) throw new RuntimeException('candidate package could not be reopened for activation');
    foreach (['apps/Platform/Resources/oh078-release-marker.txt', 'apps/Platform/migrations/oh078_001_release.sql'] as $entry) {
        $bytes = $candidateZip->getFromName($entry);
        if (!is_string($bytes)) { $candidateZip->close(); throw new RuntimeException('candidate package entry is missing: ' . $entry); }
        $target = $install . '/' . $entry;
        if (file_put_contents($target, $bytes) === false) { $candidateZip->close(); throw new RuntimeException('candidate activation write failed: ' . $entry); }
    }
    $candidateZip->close();
    DB::query('UPDATE core_apps SET version=?, install_path=? WHERE app_key=?', ['2.0.0', $install . '/apps/Platform', 'Platform']); $snapshot2 = json_encode(['schema_version' => '2.0.0', 'identity' => 'schema:2.0.0'], JSON_UNESCAPED_SLASHES); DB::query('INSERT INTO core_schema_snapshots (app_key, app_version, snapshot_json, checksum) VALUES (?, ?, ?, ?)', ['Platform', '2.0.0', $snapshot2, hash('sha256', $snapshot2)]); file_put_contents($install . '/storage/runtime-release.json', json_encode(['release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate']));
    $healthRoot = $root . '/health'; mkdir($healthRoot, 0700, true);
    $sources = [
        'release_identity' => static fn(): array => json_decode((string)file_get_contents(APP_ROOT . '/storage/runtime-release.json'), true, 512, JSON_THROW_ON_ERROR),
        'target_schema' => static function (): array {
            $row = DB::fetchOne('SELECT snapshot_json FROM core_schema_snapshots ORDER BY id DESC LIMIT 1');
            $snapshot = json_decode((string)($row['snapshot_json'] ?? ''), true, 512, JSON_THROW_ON_ERROR);
            return ['identity' => (string)($snapshot['identity'] ?? ''), 'version' => (string)($snapshot['schema_version'] ?? '')];
        },
        'migration_ledger' => static function (): array {
            $rows = DB::fetchAll('SELECT app_key, migration_name, checksum, status FROM core_app_migrations ORDER BY id ASC');
            $applied = array_values(array_filter($rows, static fn(array $row): bool => ($row['status'] ?? null) === 'applied'));
            return ['plan_fingerprint' => 'runtime-ledger-observation', 'rows' => array_map(static fn(array $row): string => $row['app_key'] . ':' . $row['migration_name'], $applied), 'checksums' => array_reduce($applied, static function (array $carry, array $row): array { $carry[$row['app_key'] . ':' . $row['migration_name']] = $row['checksum']; return $carry; }, [])];
        },
        'bootstrap' => static fn(): array => ['available' => is_file(APP_ROOT . '/vendor/autoload.php') && class_exists('App\\Core\\DB')],
        'application_discovery' => static function (): array {
            $rows = DB::fetchAll('SELECT app_key, install_path, status FROM core_apps ORDER BY id ASC');
            $applications = [];
            foreach ($rows as $row) if (($row['status'] ?? null) === 'installed' && is_dir((string)$row['install_path'])) $applications[] = (string)$row['app_key'];
            return ['applications' => $applications];
        },
        'critical_connectivity' => static fn(): array => ['reachable' => (int)(DB::fetchOne('SELECT 1 AS ok')['ok'] ?? 0) === 1],
        'configuration_trust' => static fn(): array => ['identity_fingerprint' => (string)($identityStore->read()['fingerprint'] ?? ''), 'trust_fingerprint' => (string)($trustStore->read()['fingerprint'] ?? '')],
        'protected_paths' => static fn(): array => ['integrity' => hash_file('sha256', APP_ROOT . '/storage/db_config.php') ?: ''],
        'unresolved_state' => static fn(): array => ['unresolved_migrations' => (int)(DB::fetchOne("SELECT COUNT(*) AS count FROM core_app_migrations WHERE status IN ('failed', 'pending')")['count'] ?? 0) > 0, 'unresolved_recovery' => is_file($root . '/recovery-before-update/rehearsal.json') ? !((json_decode((string)file_get_contents($root . '/recovery-before-update/rehearsal.json'), true)['ok'] ?? false) === true) : true],
    ];
    $binding = $transaction['record']['health_binding'];
    $assert($expectedHealth['release_identity']['release_version'] === $binding['candidate_release_version'], 'health release version matches binding');
    $assert($expectedHealth['release_identity']['build_id'] === $binding['candidate_build_id'], 'health build id matches binding');
    $assert($expectedHealth['target_schema']['identity'] === $binding['target_schema_identity'], 'health target schema matches binding');
    $assert($expectedHealth['configuration_trust']['identity_fingerprint'] === $binding['installation_identity_fingerprint'], 'health identity fingerprint matches binding');
    $assert($expectedHealth['configuration_trust']['trust_fingerprint'] === $binding['trust_store_fingerprint'], 'health trust fingerprint matches binding');
    $assert($expectedHealth['migration_ledger']['plan_fingerprint'] === $binding['migration_plan_fingerprint'], 'health plan fingerprint matches binding');
    foreach (['candidate_release_version', 'candidate_build_id', 'target_schema_identity', 'installation_identity_fingerprint', 'trust_store_fingerprint', 'migration_plan_fingerprint'] as $bindingField) {
        $assert(($expectedHealth['health_binding'][$bindingField] ?? null) === ($binding[$bindingField] ?? null), 'health binding field is transaction-bound: ' . $bindingField);
    }
    $health = (new PostUpdateHealthVerifier($sources))->verify($transactionId, $evidence->fingerprint(), $expectedHealth); $assert($health->status() === 'verified', 'post-update health executes against updated isolated state'); if ($health->status() !== 'verified') throw new RuntimeException('post-update health failed: ' . json_encode($health->toArray())); $transactions->markVerifying($transactionId, $evidence, $environment);
    $candidateIdentity = $initialIdentity; $candidateIdentity['release_version'] = '2.0.0'; $candidateIdentity['build_id'] = 'fixture:oh078-candidate'; $candidateIdentity['package_sha256'] = $candidateMetadata['package']['sha256']; $candidateIdentity['release_statement_fingerprint'] = hash('sha256', $candidateStatement); $candidateIdentity['schema_state_identity'] = 'schema:2.0.0'; $candidateObservation = ['package' => ['sha256' => $candidateIdentity['package_sha256']], 'signed_release' => ['product_id' => 'susankhya-os', 'release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate', 'lane' => 'app', 'statement_fingerprint' => $candidateIdentity['release_statement_fingerprint'], 'signing_key_id' => 'fixture-oh078'], 'schema' => ['identity' => 'schema:2.0.0'], 'application_registry' => ['build_id' => 'fixture:oh078-candidate', 'version' => '2.0.0']];

    // A second, separately journaled rehearsal injects a post-mutation
    // failure. It is classified as recovery-required, then the actual
    // runner-owned database dump and filesystem archive restore the target.
    $failureJournal = new DisposableReleaseTransactionJournal($root . '/failure-journal');
    $failureTransactions = new DisposableReleaseTransactionService($failureJournal);
    $failureApprovals = new DisposableAdministratorApprovalAuthority($failureJournal);
    $failedTransaction = $failureTransactions->prepare($evidence, $environment);
    $failedId = $failedTransaction['record']['transaction_id'];
    $failedChallenge = $failureApprovals->issue($failedId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC')));
    $failureApprovals->approve($failedId, 'simulated:oh078-admin', $failedChallenge['token'], $evidence, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $failureTransactions->bindHealthExpectations($failedId, $expectedHealth, $environment);
    $failureTransactions->markExecuting($failedId, $evidence, $environment);
    file_put_contents($install . '/apps/Platform/Resources/oh078-release-marker.txt', "broken-after-mutation\n");
    DB::query('INSERT INTO oh078_release_marker (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=?', [1, 'broken-after-mutation', 'broken-after-mutation']);
    $failedHealth = (new PostUpdateHealthVerifier($sources))->verify($failedId, $evidence->fingerprint(), $expectedHealth);
    if ($failedHealth->status() !== 'verified') throw new RuntimeException('failure-injection health did not verify before identity finalization test: ' . json_encode($failedHealth->toArray()));
    $failureTransactions->markVerifying($failedId, $evidence, $environment);
    $failingIdentityStore = new InstallationIdentityStore($root . '/identity', [$repository], static function (string $stage): void {
        if ($stage === 'before_finalize') throw new RuntimeException('injected identity finalization failure');
    });
    $identityFailureCaught = false;
    try {
        $failureTransactions->complete($failedId, $failedHealth, $environment, static function () use ($failingIdentityStore, $candidateIdentity, $candidateObservation, $identityStore): array {
            return $failingIdentityStore->replace($candidateIdentity, $candidateObservation, (string)($identityStore->read()['fingerprint'] ?? ''));
        });
    } catch (Throwable) {
        $identityFailureCaught = true;
    }
    $assert($identityFailureCaught, 'identity finalization failure is surfaced');
    $assert(($failureTransactions->read($failedId)['state'] ?? null) === 'recovery_required', 'identity finalization failure leaves recovery-required state');

    $restoreMain = new RestoreRehearsalService(static function (string $dumpPath, array $identity) use ($run, $runWithInput, $socket, $database, $dbUser, $dbPass, $baselineDatabaseExpectation): array {
        $compressed = file_get_contents($dumpPath);
        $sql = is_string($compressed) ? gzdecode($compressed) : false;
        if (!is_string($sql)) return ['ok' => false, 'errors' => ['database dump could not be decompressed']];
        // The disposable server has its own GTID history; replaying a dump's
        // global GTID_PURGED marker would falsely collide with that history.
        $sql = preg_replace('/^.*(?:GTID_PURGED|SQL_LOG_BIN).*?(?:;|\R)$/mi', '', $sql) ?? $sql;
        $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', '--execute=DROP DATABASE `' . $database . '`; CREATE DATABASE `' . $database . '`; GRANT ALL ON `' . $database . '`.* TO \'' . $dbUser . '\'@\'localhost\'; FLUSH PRIVILEGES;']);
        $import = $runWithInput(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', $database], $sql);
        if ($import['exit_code'] !== 0) return ['ok' => false, 'verified' => false, 'errors' => ['main database restore import failed'], 'stderr' => $import['stderr']];
        $query = static function (string $statement) use ($run, $socket, $database): string {
            return $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', $database, '--batch', '--skip-column-names', '--execute=' . $statement])['stdout'];
        };
        $tables = array_values(array_filter(array_map('trim', preg_split('/\R/', $query('SHOW TABLES;')) ?: [])));
        sort($tables);
        $expectedTables = $baselineDatabaseExpectation['tables'];
        sort($expectedTables);
        $verified = $tables === $expectedTables;
        return ['ok' => $verified, 'verified' => $verified, 'database' => $database, 'identity' => $identity, 'observations' => ['tables' => $tables], 'command_exit' => $import['exit_code'], 'stderr' => $import['stderr'], 'errors' => $verified ? [] : ['restored main database tables differ from baseline']];
    });
    $failureRecoveryFiles = $root . '/failure-recovery-files'; mkdir($failureRecoveryFiles, 0700, true);
    $recovered = $restoreMain->rehearse($recoveryResult['metadata'], $recoveryDir, $failureRecoveryFiles);
    if (($recovered['ok'] ?? false) !== true) throw new RuntimeException('failure recovery rehearsal failed: ' . json_encode($recovered));
    foreach (['app', 'apps', 'platform', 'plugins', 'public', 'resources', 'vendor', 'bin', 'composer.json', 'composer.lock'] as $replaceablePath) {
        $target = $install . '/' . $replaceablePath;
        $source = $failureRecoveryFiles . '/' . $replaceablePath;
        if (is_dir($target) || is_file($target)) $rm($target);
        if (is_dir($source) || is_file($source)) $copyTree($source, $target);
    }
    $restoredMarker = $install . '/apps/Platform/Resources/oh078-release-marker.txt';
    if (!is_file($restoredMarker)) throw new RuntimeException('complete filesystem recovery marker is missing');
    file_put_contents($install . '/storage/runtime-release.json', json_encode(['release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial']));
    $interruptedIdentity = $identityStore->read();
    $assert(($interruptedIdentity['state'] ?? null) === InstallationIdentityStore::IDENTITY_INTERRUPTED_WRITE, 'failed identity write leaves explicit interrupted evidence');
    foreach (glob($root . '/identity/.identity.tmp-*') ?: [] as $temporaryIdentity) @unlink($temporaryIdentity);
    $currentIdentityFingerprint = (string)($identityStore->read()['fingerprint'] ?? '');
    $identityRestore = $identityStore->replace($initialIdentity, $identityObservation, $currentIdentityFingerprint);
    $restoredDb = $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '--user=' . $dbUser, '--password=' . $dbPass, $database, '--batch', '--skip-column-names', '--execute=SHOW TABLES LIKE \'oh078_release_marker\';']);
    $assert(trim($restoredDb['stdout']) === '', 'recovery restores database before injected mutation');
    $assert(trim((string)file_get_contents($install . '/apps/Platform/Resources/oh078-release-marker.txt')) === 'release-v1', 'recovery restores filesystem before injected mutation');
    $assert(!is_file($install . '/apps/Platform/migrations/oh078_001_release.sql'), 'recovery removes candidate-only filesystem content');
    $assert(is_file($install . '/storage/db_config.php') && $trustStore->read()['fingerprint'] === $trustStorageFingerprint, 'recovery preserves protected configuration and trust');
    $assert(($identityStore->read()['identity']['build_id'] ?? '') === 'fixture:oh078-initial', 'recovery restores installation identity');
    if (($identityStore->read()['identity']['build_id'] ?? '') !== 'fixture:oh078-initial') throw new RuntimeException('identity restore failed: ' . json_encode($identityRestore) . ' state=' . json_encode($identityStore->read()));
    $assert(($failureTransactions->read($failedId)['state'] ?? null) === 'recovery_required', 'failed transaction remains explicitly recovery-required');
    $restoreHealthBinding = ['candidate_release_version' => '1.0.0', 'candidate_build_id' => 'fixture:oh078-initial', 'candidate_package_sha256' => $initialBuild['package_sha256'], 'installation_identity_fingerprint' => $initialIdentityFingerprint, 'trust_store_fingerprint' => $trustStorageFingerprint, 'migration_plan_fingerprint' => 'restore-baseline', 'target_schema_identity' => 'schema:1.0.0', 'recovery_point_identity' => $recoveryResult['recovery_point_id']];
    $restoreHealthExpected = ['release_identity' => ['release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial'], 'target_schema' => ['identity' => 'schema:1.0.0'], 'migration_ledger' => ['plan_fingerprint' => 'restore-baseline', 'rows' => ['Platform:baseline.sql'], 'checksums' => ['Platform:baseline.sql' => hash('sha256', $baselineSql)]], 'bootstrap' => ['available' => true], 'application_discovery' => ['applications' => ['Platform']], 'critical_connectivity' => ['reachable' => true], 'configuration_trust' => ['identity_fingerprint' => $initialIdentityFingerprint, 'trust_fingerprint' => $trustStorageFingerprint], 'protected_paths' => ['integrity' => hash_file('sha256', $install . '/storage/db_config.php')], 'unresolved_state' => ['unresolved_migrations' => false, 'unresolved_recovery' => false], 'health_binding' => $restoreHealthBinding];
    $restoredHealth = (new PostUpdateHealthVerifier($sources))->verify('oh078-restore-health', 'recovery:' . $recoveryResult['recovery_point_id'], $restoreHealthExpected);
    $assert($restoredHealth->status() === 'verified', 'post-restore health verifies actual baseline state');
    $rerunMigration = (new AppMigrationService())->executePlannedStep('Platform', '2.0.0', $sourceRoot . '/apps/Platform/migrations', 'oh078_001_release.sql', $step['checksum'], 1, ReleaseMigrationPlanValidator::validate($plan)['evidence']);
    $assert(($rerunMigration['state'] ?? null) === 'migration_applied', 'candidate migration is re-executed after verified restore');
    $candidateZip = new ZipArchive();
    if ($candidateZip->open($candidatePackage) !== true) throw new RuntimeException('candidate package could not be reopened after restore');
    foreach (['apps/Platform/Resources/oh078-release-marker.txt', 'apps/Platform/migrations/oh078_001_release.sql'] as $entry) {
        $bytes = $candidateZip->getFromName($entry);
        if (!is_string($bytes) || file_put_contents($install . '/' . $entry, $bytes) === false) { $candidateZip->close(); throw new RuntimeException('candidate reactivation failed: ' . $entry); }
    }
    $candidateZip->close();
    DB::query('UPDATE core_apps SET version=? WHERE app_key=?', ['2.0.0', 'Platform']);
    DB::query('INSERT INTO core_schema_snapshots (app_key, app_version, snapshot_json, checksum) VALUES (?, ?, ?, ?)', ['Platform', '2.0.0', $snapshot2, hash('sha256', $snapshot2)]);
    file_put_contents($install . '/storage/runtime-release.json', json_encode(['release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate']));
    $healthAfterRestore = (new PostUpdateHealthVerifier($sources))->verify($transactionId, $evidence->fingerprint(), $expectedHealth);
    $assert($healthAfterRestore->status() === 'verified', 'post-restore candidate reapplication has verified health');
    $candidateIdentityStore = new InstallationIdentityStore($root . '/identity', [$repository]);
    $transactions->complete($transactionId, $healthAfterRestore, $environment, static function () use ($candidateIdentityStore, $candidateIdentity, $candidateObservation, $identityStore): array {
        $before = $identityStore->read();
        $result = $candidateIdentityStore->replace($candidateIdentity, $candidateObservation, (string)($before['fingerprint'] ?? ''));
        $after = $candidateIdentityStore->read();
        return ['verified' => ($result['ok'] ?? false) === true && ($after['identity']['build_id'] ?? null) === 'fixture:oh078-candidate', 'identity' => $after];
    });
    $assert(($identityStore->read()['identity']['build_id'] ?? '') === 'fixture:oh078-candidate', 'installation identity commits only after verified health');
    $assert(is_file($install . '/apps/Platform/Resources/oh078-release-marker.txt') && trim((string)file_get_contents($install . '/apps/Platform/Resources/oh078-release-marker.txt')) === 'release-v2', 'candidate activation contents are present');
    $assert($trustStore->read()['fingerprint'] === $trustStorageFingerprint, 'installation-owned trust remains unchanged');
    echo json_encode(['transaction_id' => $transactionId, 'initial_package_sha256' => $initialBuild['package_sha256'], 'candidate_package_sha256' => $candidateMetadata['package']['sha256'], 'candidate_signature_verified' => true, 'migration_state' => $migrationResult['state'], 'health_status' => $health->status(), 'identity_build_id' => $identityStore->read()['identity']['build_id'], 'trust_fingerprint' => $trustFingerprint, 'isolated_root' => $root], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "OH-AUTO1-078 BLOCKED/FAILED: {$exception->getMessage()}\n");
    exit(2);
} finally {
    if (is_resource($mysqld)) {
        $processStatus = proc_get_status($mysqld);
        @proc_terminate($mysqld);
        for ($attempt = 0; $attempt < 20; $attempt++) {
            usleep(50000);
            $processStatus = proc_get_status($mysqld);
            if (($processStatus['running'] ?? false) !== true) break;
        }
        if (($processStatus['running'] ?? false) === true && function_exists('posix_kill') && isset($processStatus['pid'])) {
            @posix_kill((int)$processStatus['pid'], 9);
        }
        @proc_close($mysqld);
    }
    if ($sourceWorkspace !== null && is_dir($sourceWorkspace)) { try { IsolatedSourceRootTestRunner::cleanup($sourceWorkspace); } catch (Throwable) {} }
    if (is_dir($workspace)) $rm($workspace);
}

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
