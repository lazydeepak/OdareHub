<?php
declare(strict_types=1);

/**
 * OH-AUTO1-074 disposable-only exact-step migration proof.
 *
 * This entry point refuses to bootstrap until the existing isolated DB guard
 * and live process authorizer have both attested the temporary environment.
 */
$repository = getenv('OH_EXACT_REPO') ?: '';
$appRoot = getenv('OH_EXACT_APP_ROOT') ?: '';
require_once $repository . '/scripts/testing/DisposableDbTestEntryPointGuard.php';
$attestation = DisposableDbTestEntryPointGuard::authorize('OH_EXACT', $repository);
define('APP_ROOT', $attestation['app_root']);
require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;
use App\Services\AppMigrationService;
use Platform\Updates\ReleaseMigrationPlanValidator;

$resourceRoot = (string)(getenv('OH_EXACT_DB_RESOURCE_ROOT') ?: '');
$passed = 0;
$failed = 0;
$assert = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};
$remove = static function (string $path) use (&$remove): void {
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    if (is_dir($path)) {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $remove($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }
};
$plan = static function (array $steps): array {
    $planId = 'fixture:oh-auto1-074';
    $planVersion = ReleaseMigrationPlanValidator::PLAN_VERSION;
    return [
        'plan_version' => $planVersion,
        'plan_id' => $planId,
        'source' => ReleaseMigrationPlanValidator::SOURCE,
        'validation_state' => 'validated',
        'steps' => $steps,
        'plan_fingerprint' => ReleaseMigrationPlanValidator::fingerprint($planVersion, $planId, $steps),
    ];
};
$step = static function (int $position, string $owner, string $version, string $name, string $sql, array $requires = []): array {
    return [
        'position' => $position,
        'app_key' => $owner,
        'version' => $version,
        'migration_name' => $name,
        'checksum' => hash('sha256', $sql),
        'requires' => $requires,
    ];
};

$root = $resourceRoot . '/oh-auto1-074-migrations';
$ownerA = $root . '/owner-a';
$ownerB = $root . '/owner-b';
$remove($root);
mkdir($ownerA, 0770, true);
mkdir($ownerB, 0770, true);
$sqlA1 = 'CREATE TABLE oh074_a001 (id INT NOT NULL PRIMARY KEY)';
$sqlB1 = 'CREATE TABLE oh074_b001 (id INT NOT NULL PRIMARY KEY)';
$sqlA2 = 'CREATE TABLE oh074_a002 (id INT NOT NULL PRIMARY KEY)';
$sqlFail = 'DROP TABLE oh074_a001';
file_put_contents($ownerA . '/001_a.sql', $sqlA1);
file_put_contents($ownerB . '/001_b.sql', $sqlB1);
file_put_contents($ownerA . '/002_a.sql', $sqlA2);
file_put_contents($ownerA . '/003_fail.sql', $sqlFail);

try {
    $service = new AppMigrationService();
    $service->ensureCoreTables();
    DB::query("INSERT INTO core_apps (app_key, app_name, version, app_type, status, install_path, manifest_json) VALUES ('owner-a','Fixture A','1.0.0','app','installed','/fixture','{}'),('owner-b','Fixture B','1.0.0','app','installed','/fixture','{}')");

    $a1 = $step(1, 'owner-a', '1.0.0', '001_a.sql', $sqlA1);
    $b1 = $step(2, 'owner-b', '1.0.0', '001_b.sql', $sqlB1, ['owner-a:001_a.sql']);
    $a2 = $step(3, 'owner-a', '1.0.0', '002_a.sql', $sqlA2, ['owner-b:001_b.sql']);
    $ordered = $plan([$a1, $b1, $a2]);
    $validated = ReleaseMigrationPlanValidator::validate($ordered);
    $assert(($validated['ok'] ?? false) === true, 'A/B/A fixture plan validates');
    $evidence = $validated['evidence'] ?? null;

    $result = $service->executePlannedStep('owner-a', '1.0.0', $ownerA, '001_a.sql', $a1['checksum'], 1, $evidence);
    $assert(($result['state'] ?? '') === 'migration_applied', 'A001 executes as the sole selected step');
    $assert(is_array(DB::fetchOne("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='oh074_a001'")), 'A001 table exists');
    $assert(!is_array(DB::fetchOne("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='oh074_a002'")), 'A002 neighboring file was not executed');

    $result = $service->executePlannedStep('owner-b', '1.0.0', $ownerB, '001_b.sql', $b1['checksum'], 2, $evidence);
    $assert(($result['state'] ?? '') === 'migration_applied', 'B001 executes after A001 under plan evidence');
    $result = $service->executePlannedStep('owner-a', '1.0.0', $ownerA, '002_a.sql', $a2['checksum'], 3, $evidence);
    $assert(($result['state'] ?? '') === 'migration_applied', 'A002 executes after cross-owner B001');

    $again = $service->executePlannedStep('owner-a', '1.0.0', $ownerA, '001_a.sql', $a1['checksum'], 1, $evidence);
    $assert(($again['state'] ?? '') === 'migration_already_applied' && ($again['sql_executed'] ?? true) === false, 'identical applied step is idempotent without SQL');
    $mismatch = $service->executePlannedStep('owner-a', '1.0.0', $ownerA, '001_a.sql', str_repeat('a', 64), 1, $evidence);
    $assert(($mismatch['state'] ?? '') === 'migration_plan_position_contradiction', 'caller checksum mismatch cannot override plan evidence');

    $failStep = $step(1, 'owner-a', '1.0.0', '003_fail.sql', $sqlFail);
    $failEvidenceResult = ReleaseMigrationPlanValidator::validate($plan([$failStep]));
    $failResult = $service->executePlannedStep('owner-a', '1.0.0', $ownerA, '003_fail.sql', $failStep['checksum'], 1, $failEvidenceResult['evidence'] ?? null);
    $assert(($failResult['state'] ?? '') === 'migration_recovery_required', 'failing SQL requires recovery and does not claim rollback');
    $failedRow = DB::fetchOne("SELECT status FROM core_app_migrations WHERE app_key='owner-a' AND migration_name='003_fail.sql'");
    $assert(($failedRow['status'] ?? '') === 'failed', 'failed ledger evidence is preserved');
    $retry = $service->executePlannedStep('owner-a', '1.0.0', $ownerA, '003_fail.sql', $failStep['checksum'], 1, $failEvidenceResult['evidence'] ?? null);
    $assert(($retry['state'] ?? '') === 'migration_recovery_required', 'failed step cannot be automatically retried');

    $missingSql = 'CREATE TABLE oh074_missing (id INT NOT NULL PRIMARY KEY)';
    file_put_contents($ownerB . '/002_missing.sql', $missingSql);
    $missingPrerequisite = $step(1, 'owner-a', '1.0.0', '004_dependency.sql', 'CREATE TABLE oh074_dependency (id INT NOT NULL PRIMARY KEY)');
    $missingDependency = $step(2, 'owner-b', '1.0.0', '002_missing.sql', $missingSql, ['owner-a:004_dependency.sql']);
    $missingPlan = ReleaseMigrationPlanValidator::validate($plan([$missingPrerequisite, $missingDependency]));
    $missing = $service->executePlannedStep('owner-b', '1.0.0', $ownerB, '002_missing.sql', $missingDependency['checksum'], 2, $missingPlan['evidence'] ?? null);
    $assert(($missing['state'] ?? '') === 'migration_dependency_missing', 'missing dependency blocks execution');

    $unknownSql = 'CREATE TABLE oh074_unknown (id INT NOT NULL PRIMARY KEY)';
    $unknownDir = $root . '/unknown';
    mkdir($unknownDir, 0770, true);
    file_put_contents($unknownDir . '/001_unknown.sql', $unknownSql);
    $unknownStep = $step(1, 'unknown-owner', '1.0.0', '001_unknown.sql', $unknownSql);
    $unknownPlan = ReleaseMigrationPlanValidator::validate($plan([$unknownStep]));
    $unknown = $service->executePlannedStep('unknown-owner', '1.0.0', $unknownDir, '001_unknown.sql', $unknownStep['checksum'], 1, $unknownPlan['evidence'] ?? null);
    $assert(($unknown['state'] ?? '') === 'migration_owner_unrecognized', 'unrecognized owner is rejected');
} finally {
    $remove($root);
}

echo "RESULT assertions=" . ($passed + $failed) . " passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
