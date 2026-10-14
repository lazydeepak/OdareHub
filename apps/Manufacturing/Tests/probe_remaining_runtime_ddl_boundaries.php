<?php
declare(strict_types=1);

/**
 * Static/mock-only proof for the remaining Manufacturing runtime DDL review.
 * No application bootstrap, database connection, or SQL execution occurs.
 */

$root = dirname(__DIR__, 3);
$stagePath = $root . '/apps/Manufacturing/Services/StageTransitionService.php';
$stageMigration = $root . '/apps/Manufacturing/migrations/002_mfg_stage_transitions.sql';
$assemblyMigration = $root . '/apps/Manufacturing/migrations/003_mfg_assembly_execution.sql';
$demandMigration = $root . '/apps/Manufacturing/migrations/001_mfg_operational_model.sql';
$bootstrapPath = $root . '/apps/Manufacturing/bootstrap.php';
$queuePath = $root . '/apps/Manufacturing/Services/QueueBoardService.php';
$dispatchController = $root . '/apps/Manufacturing/Controllers/DispatchOpsController.php';
$demandExecutionPath = $root . '/apps/Manufacturing/Services/DemandExecutionService.php';
$executionPath = $root . '/apps/Manufacturing/Services/DemandWorkExecutionGeneratorService.php';
$dispatchPath = $root . '/apps/Manufacturing/Services/DispatchOpsService.php';
$manufacturingSql = [];
$sqlRoot = $root . '/apps/Manufacturing';
if (is_dir($sqlRoot)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sqlRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'sql') {
            $manufacturingSql[] = $file->getPathname();
        }
    }
}

$stage = (string)file_get_contents($stagePath);
$stageStart = strpos($stage, 'public static function computeForDate(');
$releaseStart = strpos($stage, 'public static function releaseStage(');
$overrideStart = strpos($stage, 'public static function overrideStageStatus(');
$privateStart = strpos($stage, 'private static function ');
$computeBody = ($stageStart !== false && $releaseStart !== false)
    ? substr($stage, $stageStart, $releaseStart - $stageStart)
    : '';
$releaseBody = ($releaseStart !== false && $overrideStart !== false)
    ? substr($stage, $releaseStart, $overrideStart - $releaseStart)
    : '';
$overrideBody = ($overrideStart !== false && $privateStart !== false)
    ? substr($stage, $overrideStart, $privateStart - $overrideStart)
    : '';
$stageHelperStart = strpos($stage, 'public static function ensureSchema(');
$stageHelperBody = $stageHelperStart !== false ? substr($stage, $stageHelperStart) : '';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$check($computeBody !== '' && $releaseBody !== '' && $overrideBody !== '', 'stage runtime methods are discoverable');
$check(!str_contains($computeBody, 'ensureSchema('), 'computeForDate does not invoke runtime schema ensure');
$check(!str_contains($releaseBody, 'ensureSchema('), 'releaseStage does not invoke runtime schema ensure');
$check(!str_contains($overrideBody, 'ensureSchema('), 'overrideStageStatus does not invoke runtime schema ensure');
$check(str_contains($stageHelperBody, 'CREATE TABLE IF NOT EXISTS mfg_stage_readiness'), 'stage preparation helper retains table DDL');
$check(str_contains($stageHelperBody, 'ALTER TABLE mfg_stage_readiness'), 'stage preparation helper retains compatibility ALTER');
$check(str_contains((string)file_get_contents($stageMigration), 'mfg_stage_readiness'), 'stage readiness is migration-owned');
$check(str_contains((string)file_get_contents($assemblyMigration), 'mfg_assembly_entries'), 'assembly entries are migration-owned');
$check(str_contains((string)file_get_contents($demandMigration), 'mfg_part_demands'), 'shared demand table is migration-owned');
$check(str_contains((string)file_get_contents($bootstrapPath), 'StageTransitionService::computeForDate('), 'Manufacturing bootstrap reaches stage computation');
$check(str_contains((string)file_get_contents($queuePath), 'StageTransitionService::computeForDate('), 'queue service reaches stage computation');
$check(str_contains((string)file_get_contents($dispatchController), 'StageTransitionService::computeForDate('), 'dispatch controller reaches stage computation');
$demandExecution = (string)file_get_contents($demandExecutionPath);
$productionMapStart = strpos($demandExecution, 'public static function productionDemandMapByDate(');
$qcRowsStart = strpos($demandExecution, 'public static function qcDemandRows(');
$assemblyRowsStart = strpos($demandExecution, 'public static function assemblyDemandRows(');
$productionMapBody = ($productionMapStart !== false && $qcRowsStart !== false)
    ? substr($demandExecution, $productionMapStart, $qcRowsStart - $productionMapStart)
    : '';
$qcRowsBody = ($qcRowsStart !== false && $assemblyRowsStart !== false)
    ? substr($demandExecution, $qcRowsStart, $assemblyRowsStart - $qcRowsStart)
    : '';
$assemblyRowsBody = $assemblyRowsStart !== false ? substr($demandExecution, $assemblyRowsStart) : '';
$check(!str_contains($productionMapBody, 'ensureSchema('), 'production demand map does not invoke runtime schema ensure');
$check(!str_contains($qcRowsBody, 'ensureSchema('), 'QC demand rows do not invoke runtime schema ensure');
$check(!str_contains($assemblyRowsBody, 'ensureSchema('), 'assembly demand rows do not invoke runtime schema ensure');

$processing = (string)file_get_contents($executionPath);
$dispatch = (string)file_get_contents($dispatchPath);
$sqlText = '';
foreach ($manufacturingSql as $path) {
    $sqlText .= (string)file_get_contents($path);
}
$check(str_contains($processing, 'self::ensureSchema();'), 'processing generator runtime DDL remains explicitly identified');
$check(!str_contains($sqlText, 'mfg_order_processing_entries'), 'processing generator tables lack migration ownership');
$check(str_contains($dispatch, 'self::ensureSchema();'), 'dispatch service runtime DDL remains explicitly identified');
$check(!str_contains($sqlText, 'dispatch_preparation_logs'), 'dispatch preparation table lacks migration ownership');
$check(!str_contains($sqlText, 'mfg_dispatch_bundle_headers'), 'dispatch bundle tables lack migration ownership');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
