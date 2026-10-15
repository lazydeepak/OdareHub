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

$rm = static function (string $path) use (&$rm): void {
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $entry) if ($entry !== '.' && $entry !== '..') $rm($path . '/' . $entry);
    @rmdir($path);
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
    $config = ['host' => '127.0.0.1', 'port' => 3307, 'name' => $database, 'user' => $dbUser, 'pass' => $dbPass, 'timezone' => 'UTC', 'charset' => 'utf8mb4'];
    file_put_contents($install . '/storage/db_config.php', '<?php return ' . var_export($config, true) . ';' . "\n");
    if (realpath($install) === false || str_starts_with(realpath($install), realpath($repository) . DIRECTORY_SEPARATOR)) throw new RuntimeException('installation root is not isolated');

    $run(['/opt/homebrew/bin/mysqld', '--no-defaults', '--initialize-insecure', '--datadir=' . $datadir, '--basedir=/opt/homebrew/opt/mysql']);
    $mysqld = proc_open(['/opt/homebrew/bin/mysqld', '--no-defaults', '--datadir=' . $datadir, '--socket=' . $socket, '--pid-file=' . $pidFile, '--log-error=' . $dbLog, '--bind-address=127.0.0.1', '--port=3307', '--user=' . get_current_user()], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
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
    $authorizer = DisposableDbExecutionAuthorizer::authorize((int)trim((string)file_get_contents($pidFile)), $socket, $datadir, $root, 3307);
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
    $restore = new RestoreRehearsalService(static function (string $dumpPath, array $identity) use ($run, $socket, $dbUser, $dbPass, $restoreDbName): array { $tmp = tempnam(sys_get_temp_dir(), 'oh078-defaults-'); file_put_contents($tmp, "[client]\nprotocol=SOCKET\nsocket={$socket}\nuser={$dbUser}\npassword={$dbPass}\n"); $result = $run(['/opt/homebrew/bin/mysql', '--defaults-extra-file=' . $tmp, $restoreDbName], null); @unlink($tmp); return ['ok' => true, 'database' => $restoreDbName, 'identity' => $identity, 'command_exit' => 0]; });
    $rehearsal = $restore->rehearse($recoveryResult['metadata'], $recoveryDir, $restoreFiles);
    file_put_contents($recoveryDir . '/rehearsal.json', json_encode(['schema_version' => 'susankhya.rehearsal.v1', 'recovery_point_id' => $recoveryResult['recovery_point_id'], 'ok' => ($rehearsal['ok'] ?? false), 'final_state' => ($rehearsal['final_state'] ?? 'failed'), 'database' => ['ok' => ($rehearsal['database']['ok'] ?? false) || (($rehearsal['database']['command_exit'] ?? 1) === 0)], 'filesystem' => ['ok' => ($rehearsal['filesystem']['ok'] ?? false)]], JSON_PRETTY_PRINT));

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
    $evidence = TrustedReleasePreflightEvidence::fromCoordinatorResult($preflightRaw);
    $journal = new DisposableReleaseTransactionJournal($root . '/transaction-journal'); $transactions = new DisposableReleaseTransactionService($journal); $approvals = new DisposableAdministratorApprovalAuthority($journal);
    $transaction = $transactions->prepare($evidence, $environment); $transactionId = $transaction['record']['transaction_id']; $challenge = $approvals->issue($transactionId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC'))); $approvals->approve($transactionId, 'simulated:oh078-admin', $challenge['token'], $evidence, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $expectedHealth = ['release_identity' => ['release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate'], 'target_schema' => ['identity' => 'schema:2.0.0'], 'migration_ledger' => ['plan_fingerprint' => $plan['plan_fingerprint'], 'rows' => ['Platform:oh078_001_release.sql'], 'checksums' => ['Platform:oh078_001_release.sql' => $step['checksum']]], 'bootstrap' => ['available' => true], 'application_discovery' => ['applications' => ['Platform']], 'critical_connectivity' => ['reachable' => true], 'configuration_trust' => ['identity_fingerprint' => $preflightRaw['installation_identity_fingerprint'], 'trust_fingerprint' => ($trustStore->read()['fingerprint'] ?? '')], 'protected_paths' => ['integrity' => hash_file('sha256', $install . '/storage/db_config.php')], 'unresolved_state' => ['unresolved_migrations' => false, 'unresolved_recovery' => false], 'health_binding' => $transaction['record']['health_binding']];
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
    $healthRoot = $root . '/health'; mkdir($healthRoot, 0700, true); $sources = ['release_identity' => static fn(): array => json_decode((string)file_get_contents(APP_ROOT . '/storage/runtime-release.json'), true, 512, JSON_THROW_ON_ERROR), 'target_schema' => static fn(): array => ['identity' => 'schema:2.0.0'], 'migration_ledger' => static fn(): array => ['plan_fingerprint' => $plan['plan_fingerprint'], 'rows' => ['Platform:oh078_001_release.sql'], 'checksums' => ['Platform:oh078_001_release.sql' => $step['checksum']]], 'bootstrap' => static fn(): array => ['available' => is_file(APP_ROOT . '/vendor/autoload.php')], 'application_discovery' => static fn(): array => ['applications' => ['Platform']], 'critical_connectivity' => static fn(): array => ['reachable' => DB::fetchOne('SELECT 1 AS ok')['ok'] == 1], 'configuration_trust' => static fn(): array => ['identity_fingerprint' => $preflightRaw['installation_identity_fingerprint'], 'trust_fingerprint' => ($trustStore->read()['fingerprint'] ?? '')], 'protected_paths' => static fn(): array => ['integrity' => hash_file('sha256', APP_ROOT . '/storage/db_config.php')], 'unresolved_state' => static fn(): array => ['unresolved_migrations' => false, 'unresolved_recovery' => false]];
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
    $health = (new PostUpdateHealthVerifier($sources))->verify($transactionId, $evidence->fingerprint(), $expectedHealth); $assert($health->status() === 'verified', 'post-update health executes against updated isolated state'); if ($health->status() !== 'verified') throw new RuntimeException('post-update health failed: ' . json_encode($health->toArray())); $transactions->markVerifying($transactionId, $evidence, $environment); $transactions->complete($transactionId, $health, $environment);
    $candidateIdentity = $initialIdentity; $candidateIdentity['release_version'] = '2.0.0'; $candidateIdentity['build_id'] = 'fixture:oh078-candidate'; $candidateIdentity['package_sha256'] = $candidateMetadata['package']['sha256']; $candidateIdentity['release_statement_fingerprint'] = hash('sha256', $candidateStatement); $candidateIdentity['schema_state_identity'] = 'schema:2.0.0'; $candidateObservation = ['package' => ['sha256' => $candidateIdentity['package_sha256']], 'signed_release' => ['product_id' => 'susankhya-os', 'release_version' => '2.0.0', 'build_id' => 'fixture:oh078-candidate', 'lane' => 'app', 'statement_fingerprint' => $candidateIdentity['release_statement_fingerprint'], 'signing_key_id' => 'fixture-oh078'], 'schema' => ['identity' => 'schema:2.0.0'], 'application_registry' => ['build_id' => 'fixture:oh078-candidate', 'version' => '2.0.0']]; $identityStore->replace($candidateIdentity, $candidateObservation, $initialIdentity ? ($identityStore->read()['fingerprint'] ?? '') : '');
    $assert(($identityStore->read()['identity']['build_id'] ?? '') === 'fixture:oh078-candidate', 'installation identity commits only after verified health');

    $assert(is_file($install . '/apps/Platform/Resources/oh078-release-marker.txt') && trim((string)file_get_contents($install . '/apps/Platform/Resources/oh078-release-marker.txt')) === 'release-v2', 'candidate activation contents are present');
    $assert($trustStore->read()['fingerprint'] === $trustStorageFingerprint, 'installation-owned trust remains unchanged');

    // A second, separately journaled rehearsal injects a post-mutation
    // failure. It is classified as recovery-required, then the actual
    // runner-owned database dump and filesystem archive restore the target.
    $failedTransaction = $transactions->prepare($evidence, $environment);
    $failedId = $failedTransaction['record']['transaction_id'];
    $failedChallenge = $approvals->issue($failedId, new DateTimeImmutable('+10 minutes', new DateTimeZone('UTC')));
    $approvals->approve($failedId, 'simulated:oh078-admin', $failedChallenge['token'], $evidence, new DateTimeImmutable('now', new DateTimeZone('UTC')));
    $transactions->bindHealthExpectations($failedId, $expectedHealth, $environment);
    $transactions->markExecuting($failedId, $evidence, $environment);
    file_put_contents($install . '/apps/Platform/Resources/oh078-release-marker.txt', "broken-after-mutation\n");
    DB::query('INSERT INTO oh078_release_marker (id, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=?', [1, 'broken-after-mutation', 'broken-after-mutation']);
    $transactions->markRecoveryRequired($failedId, 'injected post-update health failure', $environment);

    $restoreMain = new RestoreRehearsalService(static function (string $dumpPath, array $identity) use ($run, $socket, $database, $dbUser, $dbPass, $root): array {
        $compressed = file_get_contents($dumpPath);
        $sql = is_string($compressed) ? gzdecode($compressed) : false;
        if (!is_string($sql)) return ['ok' => false, 'errors' => ['database dump could not be decompressed']];
        // The disposable server has its own GTID history; replaying a dump's
        // global GTID_PURGED marker would falsely collide with that history.
        $sql = preg_replace('/SET @@GLOBAL\.GTID_PURGED=.*?;\s*/s', '', $sql) ?? $sql;
        $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', '--execute=DROP DATABASE `' . $database . '`; CREATE DATABASE `' . $database . '`; GRANT ALL ON `' . $database . '`.* TO \'' . $dbUser . '\'@\'localhost\'; FLUSH PRIVILEGES;']);
        $result = $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '-uroot', $database, '--execute=' . $sql]);
        return ['ok' => true, 'database' => $database, 'identity' => $identity, 'command_exit' => 0, 'stderr' => $result['stderr']];
    });
    $failureRecoveryFiles = $root . '/failure-recovery-files'; mkdir($failureRecoveryFiles, 0700, true);
    $recovered = $restoreMain->rehearse($recoveryResult['metadata'], $recoveryDir, $failureRecoveryFiles);
    if (($recovered['ok'] ?? false) !== true) throw new RuntimeException('failure recovery rehearsal failed: ' . json_encode($recovered));
    $restoredMarker = $failureRecoveryFiles . '/apps/Platform/Resources/oh078-release-marker.txt';
    if (!is_file($restoredMarker) || !copy($restoredMarker, $install . '/apps/Platform/Resources/oh078-release-marker.txt')) throw new RuntimeException('filesystem recovery marker restore failed');
    file_put_contents($install . '/storage/runtime-release.json', json_encode(['release_version' => '1.0.0', 'build_id' => 'fixture:oh078-initial']));
    $currentIdentityFingerprint = (string)($identityStore->read()['fingerprint'] ?? '');
    $identityStore->replace($initialIdentity, $identityObservation, $currentIdentityFingerprint);
    $restoredDb = $run(['/opt/homebrew/bin/mysql', '--no-defaults', '--protocol=SOCKET', '--socket=' . $socket, '--user=' . $dbUser, '--password=' . $dbPass, $database, '--batch', '--skip-column-names', '--execute=SHOW TABLES LIKE \'oh078_release_marker\';']);
    $assert(trim($restoredDb['stdout']) === '', 'recovery restores database before injected mutation');
    $assert(trim((string)file_get_contents($install . '/apps/Platform/Resources/oh078-release-marker.txt')) === 'release-v1', 'recovery restores filesystem before injected mutation');
    $assert(($identityStore->read()['identity']['build_id'] ?? '') === 'fixture:oh078-initial', 'recovery restores installation identity');
    $assert(($transactions->read($failedId)['state'] ?? null) === 'recovery_required', 'failed transaction remains explicitly recovery-required');
    echo json_encode(['transaction_id' => $transactionId, 'initial_package_sha256' => $initialBuild['package_sha256'], 'candidate_package_sha256' => $candidateMetadata['package']['sha256'], 'candidate_signature_verified' => true, 'migration_state' => $migrationResult['state'], 'health_status' => $health->status(), 'identity_build_id' => $identityStore->read()['identity']['build_id'], 'trust_fingerprint' => $trustFingerprint, 'isolated_root' => $root], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "OH-AUTO1-078 BLOCKED/FAILED: {$exception->getMessage()}\n");
    exit(2);
} finally {
    if (is_resource($mysqld)) { @proc_terminate($mysqld); @proc_close($mysqld); }
    if ($sourceWorkspace !== null && is_dir($sourceWorkspace)) { try { IsolatedSourceRootTestRunner::cleanup($sourceWorkspace); } catch (Throwable) {} }
    if (is_dir($workspace)) $rm($workspace);
}

echo "RESULT: {$passed}/" . ($passed + $failed) . " assertions passed\n";
exit($failed === 0 ? 0 : 1);
