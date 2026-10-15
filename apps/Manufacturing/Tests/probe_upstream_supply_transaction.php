<?php
declare(strict_types=1);

ob_start();

/**
 * OH-AUTO1-030 isolated behavioral probe.
 *
 * This probe requires an explicitly provisioned temporary database and an
 * independently attested MySQL process. It never discovers or reads the
 * repository's installed database configuration. The caller supplies a
 * temporary APP_ROOT containing only the test DB config.
 */

$required = [
    'OH_MFG_REPO',
    'OH_MFG_APP_ROOT',
    'OH_MFG_DB_PID',
    'OH_MFG_DB_SOCKET',
    'OH_MFG_DB_DATADIR',
    'OH_MFG_DB_RESOURCE_ROOT',
    'OH_MFG_DB_PORT',
    'OH_MFG_DB_NAME',
    'OH_MFG_DB_USER',
];

$value = static function (string $key): string {
    $raw = getenv($key);
    return $raw === false ? '' : trim((string)$raw);
};

foreach ($required as $key) {
    if ($value($key) === '') {
        fwrite(STDERR, "Missing explicit test input: {$key}\n");
        exit(2);
    }
}

$repo = $value('OH_MFG_REPO');
$appRoot = $value('OH_MFG_APP_ROOT');
if (!is_dir($repo) || !is_dir($appRoot)) {
    fwrite(STDERR, "Repository or temporary APP_ROOT is unavailable.\n");
    exit(2);
}

require_once $repo . '/scripts/testing/DisposableDbTestConfigurationGuard.php';
$configuration = DisposableDbTestConfigurationGuard::authorize([
    'repo' => $repo,
    'app_root' => $appRoot,
    'resource_root' => $value('OH_MFG_DB_RESOURCE_ROOT'),
    'host' => '127.0.0.1',
    'port' => $value('OH_MFG_DB_PORT'),
    'name' => $value('OH_MFG_DB_NAME'),
    'user' => $value('OH_MFG_DB_USER'),
]);
require_once $repo . '/scripts/testing/DisposableDbExecutionAuthorizer.php';
$authorization = DisposableDbExecutionAuthorizer::authorize(
    (int)$value('OH_MFG_DB_PID'),
    $value('OH_MFG_DB_SOCKET'),
    $value('OH_MFG_DB_DATADIR'),
    $value('OH_MFG_DB_RESOURCE_ROOT'),
    (int)$value('OH_MFG_DB_PORT')
);
if (!$authorization['authorized']) {
    fwrite(STDERR, "Disposable database authorization failed: " . json_encode($authorization, JSON_UNESCAPED_SLASHES) . "\n");
    exit(2);
}
DisposableDbTestConfigurationGuard::assertUnchanged($configuration);

define('APP_ROOT', $appRoot);
require $appRoot . '/vendor/autoload.php';
require_once $appRoot . '/apps/Manufacturing/modules/Products/Services/PartExecutionRouteResolver.php';
require_once $appRoot . '/apps/Manufacturing/modules/Supply/Services/SupplyModel.php';

use App\Core\DB;
use Apps\Manufacturing\Services\UpstreamSupplyGenerationService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};
$row = static function (string $sql, array $params = []): array {
    $result = DB::fetchOne($sql, $params);
    return is_array($result) ? $result : [];
};
$count = static function (string $table, string $where = '', array $params = []) use ($row): int {
    $suffix = $where === '' ? '' : ' WHERE ' . $where;
    return (int)($row('SELECT COUNT(*) AS c FROM ' . $table . $suffix, $params)['c'] ?? 0);
};

$migrationFiles = [
    '/apps/Manufacturing/modules/Products/migrations/001_products.sql',
    '/apps/Manufacturing/modules/Products/migrations/002_products_extend.sql',
    '/apps/Manufacturing/modules/Products/migrations/004_products_supply_model.sql',
    '/apps/Manufacturing/modules/Products/migrations/006_part_execution_planning_fields.sql',
    '/apps/Manufacturing/modules/PreOrders/migrations/001_pre_orders.sql',
    '/apps/Manufacturing/modules/DailyOrders/migrations/001_daily_orders.sql',
    '/apps/Manufacturing/modules/DispatchEntries/migrations/001_dispatch_entries.sql',
];
foreach ($migrationFiles as $relativePath) {
    $sql = (string)@file_get_contents($appRoot . $relativePath);
    $check($sql !== '', 'authoritative fixture loaded: ' . ltrim($relativePath, '/'));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        DB::query($statement);
    }
}

// UpstreamSupplyGenerationService::ensureSchema() owns these three tables.
UpstreamSupplyGenerationService::ensureSchema();

$suffix = strtoupper(bin2hex(random_bytes(4)));
$date = '2030-01-15';
$inHouseProduct = 0;
$thirdPartyProduct = 0;
DB::query(
    'INSERT INTO products (parts_name, parts_number, supply_mode, fulfillment_mode, requires_ipm_qc, requires_assembly, requires_processing, dispatch_mode, dispatch_as_is, activity_type)
     VALUES (?,?,?,?,?,?,?,?,?,?)',
    ['OH MFG In-house ' . $suffix, 'OH-MFG-I-' . $suffix, 'in_house', 'company_to_destination', 0, 0, 1, 'internal', 0, 'active']
);
$inHouseProduct = (int)DB::conn()->insert_id;
DB::query(
    'INSERT INTO products (parts_name, parts_number, supply_mode, fulfillment_mode, requires_ipm_qc, requires_assembly, requires_processing, dispatch_mode, dispatch_as_is, activity_type)
     VALUES (?,?,?,?,?,?,?,?,?,?)',
    ['OH MFG Third-party ' . $suffix, 'OH-MFG-T-' . $suffix, 'third_party', 'third_party_to_company_to_destination', 0, 0, 1, 'internal', 0, 'active']
);
$thirdPartyProduct = (int)DB::conn()->insert_id;
$check($inHouseProduct > 0 && $thirdPartyProduct > 0, 'fixture products created');

foreach ([[$inHouseProduct, 'in-house'], [$thirdPartyProduct, 'third-party']] as [$productId, $label]) {
    DB::query(
        'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, status)
         VALUES (?,?,?,?,?,?)',
        [$date, $date, 'OH MFG ' . $label . ' customer', $productId, 5, 'Open']
    );
}

$result = UpstreamSupplyGenerationService::generate($date, 100, [$inHouseProduct, $thirdPartyProduct], false);
$rows = (array)($result['rows'] ?? []);
$check(count($rows) === 2, 'public generate entry point processes both source rows');
$check($count('mfg_production_plan_candidates', 'product_id=?', [$inHouseProduct]) === 1, 'in-house production target committed');
$check($count('mfg_upstream_generation_links', 'work_type=? AND product_id=?', ['production', $inHouseProduct]) === 1, 'in-house production link committed');
$check($count('mfg_procurement_demands', 'product_id=?', [$thirdPartyProduct]) === 1, 'third-party procurement target committed');
$check($count('mfg_upstream_generation_links', 'work_type=? AND product_id=?', ['procurement', $thirdPartyProduct]) === 1, 'third-party procurement link committed');

$repeat = UpstreamSupplyGenerationService::generate($date, 100, [$inHouseProduct, $thirdPartyProduct], false);
$check((int)(($repeat['generation_summary']['production_updated'] ?? 0)) === 1, 'repeat in-house generation updates existing target');
$check((int)(($repeat['generation_summary']['procurement_updated'] ?? 0)) === 1, 'repeat third-party generation updates existing target');
$check($count('mfg_production_plan_candidates', 'product_id=?', [$inHouseProduct]) === 1, 'repeat does not duplicate production target');
$check($count('mfg_procurement_demands', 'product_id=?', [$thirdPartyProduct]) === 1, 'repeat does not duplicate procurement target');

$missing = UpstreamSupplyGenerationService::generate($date, 100, [999999999], false);
$check((array)($missing['rows'] ?? []) === [], 'missing product dependency produces no generated row');

$failureProduct = 0;
DB::query(
    'INSERT INTO products (parts_name, parts_number, supply_mode, fulfillment_mode, requires_ipm_qc, requires_assembly, requires_processing, dispatch_mode, dispatch_as_is, activity_type)
     VALUES (?,?,?,?,?,?,?,?,?,?)',
    ['OH MFG Failure ' . $suffix, 'OH-MFG-F-' . $suffix, 'in_house', 'company_to_destination', 0, 0, 1, 'internal', 0, 'active']
);
$failureProduct = (int)DB::conn()->insert_id;
DB::query(
    'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, status)
     VALUES (?,?,?,?,?,?)',
    [$date, $date, 'OH MFG failure customer', $failureProduct, 3, 'Open']
);
$trigger = 'oh030_fail_upstream_link_' . strtolower($suffix);
DB::query("CREATE TRIGGER {$trigger} BEFORE INSERT ON mfg_upstream_generation_links FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='OH030 controlled link failure'");
try {
    UpstreamSupplyGenerationService::generate($date, 100, [$failureProduct], false);
    $check(false, 'controlled link failure surfaced');
} catch (\Throwable $e) {
    $check(str_contains($e->getMessage(), 'OH030 controlled link failure'), 'controlled link failure surfaced');
}
$check($count('mfg_production_plan_candidates', 'product_id=?', [$failureProduct]) === 0, 'controlled link failure rolls back production target');
$check($count('mfg_upstream_generation_links', 'product_id=?', [$failureProduct]) === 0, 'controlled link failure leaves no orphan link');
DB::query("DROP TRIGGER {$trigger}");

printf("RESULTS passed=%d failed=%d\n", $passed, $failed);
$output = ob_get_clean();
echo $output;
exit($failed === 0 ? 0 : 1);
