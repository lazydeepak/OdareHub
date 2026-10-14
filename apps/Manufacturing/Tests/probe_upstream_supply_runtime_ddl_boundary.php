<?php
declare(strict_types=1);

/**
 * Static/mock-only proof that upstream generation does not perform schema DDL
 * during ordinary runtime calls. No application bootstrap, DB connection, or
 * SQL execution is performed.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Manufacturing/Services/UpstreamSupplyGenerationService.php';
$demandPath = $root . '/apps/Manufacturing/Services/DemandEngineService.php';
$migrationPath = $root . '/apps/Manufacturing/migrations/005_mfg_upstream_supply_tables.sql';
$demandMigrationPath = $root . '/apps/Manufacturing/migrations/001_mfg_operational_model.sql';
$controllerPath = $root . '/apps/Manufacturing/Controllers/DemandController.php';
$service = (string)file_get_contents($servicePath);
$demand = (string)file_get_contents($demandPath);
$migration = (string)file_get_contents($migrationPath);
$demandMigration = (string)file_get_contents($demandMigrationPath);
$controller = (string)file_get_contents($controllerPath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$generateStart = strpos($service, 'public static function generate(');
$ensureStart = strpos($service, 'public static function ensureSchema(');
$previewStart = strpos($demand, 'public static function previewRouteDecisions(');
$sourceRowsStart = strpos($demand, 'public static function sourceDemandRows(');
$generateBody = ($generateStart !== false && $ensureStart !== false)
    ? substr($service, $generateStart, $ensureStart - $generateStart)
    : '';
$previewBody = ($previewStart !== false && $sourceRowsStart !== false)
    ? substr($demand, $previewStart, $sourceRowsStart - $previewStart)
    : '';
$ensureBody = $ensureStart !== false ? substr($service, $ensureStart) : '';

$check($generateBody !== '', 'upstream generate method is discoverable');
$check(!str_contains($generateBody, 'ensureSchema('), 'generate does not invoke runtime schema ensure');
$check(!str_contains($previewBody, 'ensureSchema('), 'route preview does not invoke runtime schema ensure');
$check(substr_count($ensureBody, 'CREATE TABLE IF NOT EXISTS') === 3, 'upstream schema helper retains three explicit table definitions');
$check(str_contains($ensureBody, 'mfg_production_plan_candidates'), 'explicit helper retains production target DDL');
$check(str_contains($ensureBody, 'mfg_procurement_demands'), 'explicit helper retains procurement target DDL');
$check(str_contains($ensureBody, 'mfg_upstream_generation_links'), 'explicit helper retains link target DDL');
$check(str_contains($migration, 'mfg_production_plan_candidates')
    && str_contains($migration, 'mfg_procurement_demands')
    && str_contains($migration, 'mfg_upstream_generation_links'), 'migration owns all upstream tables');
$check(str_contains($demandMigration, 'mfg_part_demands'), 'migration owns demand table used by route decisions');
$check(str_contains($service, 'DemandWorkBucketService::previewBuckets('), 'generate retains bucket computation');
$check(str_contains($controller, 'UpstreamSupplyGenerationService::generate('), 'controller retains upstream generation caller');
$check(str_contains($controller, 'DemandWorkExecutionGeneratorService::generate('), 'controller retains execution generator caller');
$check(!str_contains($generateBody, 'ALTER TABLE'), 'generate body contains no ALTER TABLE');
$check(!str_contains($previewBody, 'ALTER TABLE'), 'preview body contains no ALTER TABLE');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
