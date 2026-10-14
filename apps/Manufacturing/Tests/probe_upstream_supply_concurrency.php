<?php
declare(strict_types=1);

/**
 * OH-AUTO1-032 isolated concurrency probe.
 *
 * Independent PHP workers invoke the public generation entry point against a
 * fresh, explicitly attested disposable MySQL instance. Existing composite
 * uniqueness constraints are the only duplicate-prevention contract tested.
 */

ob_start();
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
$env = static function (string $key): string {
    $value = getenv($key);
    return $value === false ? '' : trim((string)$value);
};
foreach ($required as $key) {
    if ($env($key) === '') {
        fwrite(STDERR, "Missing explicit test input: {$key}\n");
        exit(2);
    }
}

$repo = $env('OH_MFG_REPO');
$appRoot = $env('OH_MFG_APP_ROOT');
require_once $repo . '/scripts/testing/DisposableDbTestConfigurationGuard.php';
$configuration = DisposableDbTestConfigurationGuard::authorize([
    'repo' => $repo,
    'app_root' => $appRoot,
    'resource_root' => $env('OH_MFG_DB_RESOURCE_ROOT'),
    'host' => '127.0.0.1',
    'port' => $env('OH_MFG_DB_PORT'),
    'name' => $env('OH_MFG_DB_NAME'),
    'user' => $env('OH_MFG_DB_USER'),
]);
require_once $repo . '/scripts/testing/DisposableDbExecutionAuthorizer.php';
$authorization = DisposableDbExecutionAuthorizer::authorize(
    (int)$env('OH_MFG_DB_PID'),
    $env('OH_MFG_DB_SOCKET'),
    $env('OH_MFG_DB_DATADIR'),
    $env('OH_MFG_DB_RESOURCE_ROOT'),
    (int)$env('OH_MFG_DB_PORT')
);
if (!$authorization['authorized']) {
    fwrite(STDERR, "Disposable database authorization failed.\n");
    exit(2);
}
DisposableDbTestConfigurationGuard::assertUnchanged($configuration);

define('APP_ROOT', $appRoot);
require $repo . '/vendor/autoload.php';
require_once $repo . '/apps/Manufacturing/modules/Products/Services/PartExecutionRouteResolver.php';
require_once $repo . '/apps/Manufacturing/modules/Supply/Services/SupplyModel.php';

use App\Core\DB;
use Apps\Manufacturing\Services\UpstreamSupplyGenerationService;

$row = static function (string $sql, array $params = []): array {
    $value = DB::fetchOne($sql, $params);
    return is_array($value) ? $value : [];
};
$count = static function (string $table, string $where = '', array $params = []) use ($row): int {
    return (int)($row('SELECT COUNT(*) AS c FROM ' . $table . ($where === '' ? '' : ' WHERE ' . $where), $params)['c'] ?? 0);
};
$runPair = static function (array $firstArgs, array $secondArgs): array {
    $children = [];
    foreach ([$firstArgs, $secondArgs] as $args) {
        $command = implode(' ', array_map('escapeshellarg', array_merge([PHP_BINARY, __FILE__], $args)));
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start concurrent Manufacturing worker.');
        }
        $children[] = [$process, $pipes];
    }
    $results = [];
    foreach ($children as [$process, $pipes]) {
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $results[] = ['code' => proc_close($process), 'output' => trim((string)$stdout . "\n" . (string)$stderr)];
    }
    return $results;
};
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};
$passed = 0;
$failed = 0;

if (($argv[1] ?? '') === '--generate') {
    try {
        $result = UpstreamSupplyGenerationService::generate((string)$argv[3], 100, [(int)$argv[2]], false);
        echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_SLASHES) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_SLASHES) . "\n";
        exit(1);
    }
}

UpstreamSupplyGenerationService::ensureSchema();
$migrationFiles = [
    '/apps/Manufacturing/modules/Products/migrations/001_products.sql',
    '/apps/Manufacturing/modules/Products/migrations/002_products_extend.sql',
    '/apps/Manufacturing/modules/Products/migrations/004_products_supply_model.sql',
    '/apps/Manufacturing/modules/Products/migrations/006_part_execution_planning_fields.sql',
    '/apps/Manufacturing/modules/PreOrders/migrations/001_pre_orders.sql',
    '/apps/Manufacturing/modules/DailyOrders/migrations/001_daily_orders.sql',
];
foreach ($migrationFiles as $relativePath) {
    foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents($repo . $relativePath)))) as $statement) {
        DB::query($statement);
    }
}

$suffix = strtoupper(bin2hex(random_bytes(4)));
$date = '2030-03-15';
$products = [];
foreach ([['in_house', 'company_to_destination'], ['third_party', 'third_party_to_company_to_destination']] as [$supplyMode, $fulfillmentMode]) {
    DB::query(
        'INSERT INTO products (parts_name, parts_number, supply_mode, fulfillment_mode, requires_ipm_qc, requires_assembly, requires_processing, dispatch_mode, dispatch_as_is, activity_type)
         VALUES (?,?,?,?,?,?,?,?,?,?)',
        ['OH032 ' . $supplyMode . ' ' . $suffix, 'OH032-' . strtoupper($supplyMode) . '-' . $suffix, $supplyMode, $fulfillmentMode, 0, 0, 1, 'internal', 0, 'active']
    );
    $products[$supplyMode] = (int)DB::conn()->insert_id;
}
$check(count($products) === 2, 'in-house and third-party fixture products created');
foreach ($products as $supplyMode => $productId) {
    DB::query(
        'INSERT INTO daily_orders (order_date, required_date, customer_name, product_id, qty, status)
         VALUES (?,?,?,?,?,?)',
        [$date, $date, 'OH032 ' . $supplyMode, $productId, 10, 'Open']
    );
}

$productionTrigger = 'oh032_production_pause_' . strtolower($suffix);
DB::query("CREATE TRIGGER {$productionTrigger} BEFORE INSERT ON mfg_production_plan_candidates FOR EACH ROW BEGIN DO SLEEP(2); END");
$productionWorkers = $runPair(
    ['--generate', (string)$products['in_house'], $date],
    ['--generate', (string)$products['in_house'], $date]
);
DB::query("DROP TRIGGER {$productionTrigger}");
$productionTargets = $count('mfg_production_plan_candidates', 'product_id=?', [$products['in_house']]);
$productionLinks = $count('mfg_upstream_generation_links', 'work_type=? AND product_id=?', ['production', $products['in_house']]);
$productionSuccesses = count(array_filter($productionWorkers, static fn(array $item): bool => $item['code'] === 0));
if ($productionSuccesses !== 2) {
    echo 'DIAGNOSTIC production_workers=' . json_encode($productionWorkers, JSON_UNESCAPED_SLASHES) . "\n";
}
$check($productionSuccesses === 2, 'concurrent in-house generation calls both complete');
$check($productionTargets === 1, 'concurrent in-house generation leaves one target');
$check($productionLinks === 1, 'concurrent in-house generation leaves one link');

$procurementTrigger = 'oh032_procurement_pause_' . strtolower($suffix);
DB::query("CREATE TRIGGER {$procurementTrigger} BEFORE INSERT ON mfg_procurement_demands FOR EACH ROW BEGIN DO SLEEP(2); END");
$procurementWorkers = $runPair(
    ['--generate', (string)$products['third_party'], $date],
    ['--generate', (string)$products['third_party'], $date]
);
DB::query("DROP TRIGGER {$procurementTrigger}");
$procurementTargets = $count('mfg_procurement_demands', 'product_id=?', [$products['third_party']]);
$procurementLinks = $count('mfg_upstream_generation_links', 'work_type=? AND product_id=?', ['procurement', $products['third_party']]);
$procurementSuccesses = count(array_filter($procurementWorkers, static fn(array $item): bool => $item['code'] === 0));
if ($procurementSuccesses !== 2) {
    echo 'DIAGNOSTIC procurement_workers=' . json_encode($procurementWorkers, JSON_UNESCAPED_SLASHES) . "\n";
}
$check($procurementSuccesses === 2, 'concurrent third-party generation calls both complete');
$check($procurementTargets === 1, 'concurrent third-party generation leaves one target');
$check($procurementLinks === 1, 'concurrent third-party generation leaves one link');

$retry = UpstreamSupplyGenerationService::generate($date, 100, [$products['in_house'], $products['third_party']], false);
$check(($retry['generation_summary']['production_updated'] ?? 0) === 1, 'post-race in-house retry updates existing target');
$check(($retry['generation_summary']['procurement_updated'] ?? 0) === 1, 'post-race third-party retry updates existing target');
$check($productionTargets === $count('mfg_production_plan_candidates', 'product_id=?', [$products['in_house']]), 'in-house retry does not duplicate target');
$check($procurementTargets === $count('mfg_procurement_demands', 'product_id=?', [$products['third_party']]), 'third-party retry does not duplicate target');

printf("RESULTS passed=%d failed=%d\n", $passed, $failed);
$output = ob_get_clean();
echo $output;
exit($failed === 0 ? 0 : 1);
