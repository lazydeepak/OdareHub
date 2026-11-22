<?php
declare(strict_types=1);

/**
 * Disposable-database-only IPM-004 Product -> Shared Item mapping probe.
 *
 * Exit 0 = pass, 1 = implementation failure, 2 = missing/invalid disposable
 * authorization. No installed or development database is an accepted target.
 */
$repositoryRoot = trim((string)(getenv('OH_IPM_REPO') ?: dirname(__DIR__, 5)));
require_once $repositoryRoot . '/scripts/testing/DisposableDbTestEntryPointGuard.php';

try {
    $context = DisposableDbTestEntryPointGuard::authorize('OH_IPM', $repositoryRoot);
} catch (Throwable $e) {
    echo "INCONCLUSIVE: disposable Product mapping authorization unavailable: {$e->getMessage()}\n";
    exit(2);
}

define('APP_ROOT', $context['app_root']);
require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;
use App\Services\AppMigrationService;
use Shared\Item\Foundation\Services\DatabaseItemIdentityService;

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "PASS: {$label}\n";
};
$fail = static function (string $label, string $detail = '') use (&$failed): void {
    $failed++;
    echo "FAIL: {$label}" . ($detail !== '' ? ": {$detail}" : '') . "\n";
};
$check = static function (bool $condition, string $label, string $detail = '') use ($pass, $fail): void {
    $condition ? $pass($label) : $fail($label, $detail);
};
$tableExists = static function (string $table): bool {
    $row = DB::fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [$table]
    );
    return is_array($row) && (int)($row['c'] ?? 0) > 0;
};
$apply = static function (string $path): void {
    $sql = trim((string)file_get_contents($path));
    if ($sql === '') {
        throw new RuntimeException('mapping migration is empty');
    }
    DB::query($sql);
};
$constraint = static function (string $name): ?array {
    return DB::fetchOne(
        'SELECT CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE
         FROM information_schema.REFERENTIAL_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME=? LIMIT 1',
        [$name]
    );
};

if (getenv('OH_IPM_MAPPING_CONCURRENT') === '1') {
    try {
        DB::query(
            'INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)',
            [(int)getenv('OH_IPM_MAPPING_PRODUCT_ID'), (int)getenv('OH_IPM_MAPPING_ITEM_ID')]
        );
        echo json_encode(['ok' => true], JSON_THROW_ON_ERROR) . "\n";
        exit(0);
    } catch (Throwable $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR) . "\n";
        exit(1);
    }
}

$sharedMigration = APP_ROOT . '/apps/Shared/migrations/20261123_000001_create_shared_items.sql';
$productMigration = APP_ROOT . '/apps/Manufacturing/modules/Products/migrations/001_products.sql';
$mappingMigration = APP_ROOT . '/apps/Manufacturing/modules/Products/migrations/014_product_item_map.sql';

try {
    if ($tableExists('manufacturing_product_item_map') || $tableExists('products') || $tableExists('shared_items')) {
        echo "INCONCLUSIVE: disposable database already contains IPM-004 fixture tables; refusing to alter it.\n";
        exit(2);
    }

    $migrationService = new AppMigrationService();
    $migrationService->ensureCoreTables();
    $sharedApplied = $migrationService->runMigrations(
        'shared',
        '0.1.0',
        dirname($sharedMigration)
    );
    $check($sharedApplied === [basename($sharedMigration)], 'Shared migration applies before Products mapping');
    DB::query((string)file_get_contents($productMigration));
    $apply($mappingMigration);
    $apply($mappingMigration);
    $check($tableExists('manufacturing_product_item_map'), 'mapping migration applies and replays idempotently');

    $columns = DB::fetchAll(
        "SELECT COLUMN_NAME AS column_name, DATA_TYPE AS data_type, COLUMN_TYPE AS column_type
         FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name='manufacturing_product_item_map'
         ORDER BY ordinal_position"
    );
    $columnMap = [];
    foreach ($columns as $column) {
        $columnMap[(string)$column['column_name']] = $column;
    }
    $check(($columnMap['product_id']['column_type'] ?? '') === 'int', 'product_id column retains INT shape');
    $check(($columnMap['item_id']['column_type'] ?? '') === 'bigint unsigned', 'item_id column retains BIGINT UNSIGNED shape');

    $productFk = $constraint('fk_product_item_map_product');
    $itemFk = $constraint('fk_product_item_map_item');
    $check(is_array($productFk) && $productFk['DELETE_RULE'] === 'RESTRICT', 'Product FK uses RESTRICT');
    $check(is_array($itemFk) && $itemFk['DELETE_RULE'] === 'RESTRICT', 'Shared Item FK uses RESTRICT');

    $suffix = bin2hex(random_bytes(4));
    DB::query('INSERT INTO products (parts_name, parts_number) VALUES (?, ?)', ['Mapped Product ' . $suffix, 'MAP-' . $suffix]);
    $productId = (int)DB::conn()->insert_id;
    DB::query('INSERT INTO products (parts_name, parts_number) VALUES (?, ?)', ['Mapped Variant ' . $suffix, 'MAP-V-' . $suffix]);
    $secondProductId = (int)DB::conn()->insert_id;
    $items = new DatabaseItemIdentityService();
    $item = $items->create(['code_ref' => 'MAP-ITEM-' . $suffix, 'name' => 'Mapped Shared Item']);
    $itemId = (int)$item['item_id'];
    $productRows = DB::fetchAll(
        'SELECT id FROM products WHERE id IN (?, ?)',
        [$productId, $secondProductId]
    );
    $check(count($productRows) === 2, 'mapped Product records exist before relation creation');
    $sharedItemRow = $items->getById($itemId);
    $check(is_array($sharedItemRow) && in_array($sharedItemRow['status'] ?? null, ['draft', 'active'], true), 'mapped Shared Item is lifecycle-eligible');
    DB::query('INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)', [$productId, $itemId]);
    $mapping = DB::fetchOne(
        'SELECT product_id, item_id FROM manufacturing_product_item_map WHERE product_id=? LIMIT 1',
        [$productId]
    );
    $check(is_array($mapping) && (int)$mapping['item_id'] === $itemId, 'mapping creation and lookup preserve both identities');

    $duplicateProductRejected = false;
    try {
        DB::query('INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)', [$productId, $itemId + 1]);
    } catch (Throwable) {
        $duplicateProductRejected = true;
    }
    $check($duplicateProductRejected, 'duplicate Product mapping is rejected');

    DB::query('INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)', [$secondProductId, $itemId]);
    $sharedRepresentationCount = DB::fetchOne(
        'SELECT COUNT(*) AS c FROM manufacturing_product_item_map WHERE item_id=?',
        [$itemId]
    );
    $check((int)($sharedRepresentationCount['c'] ?? 0) === 2, 'one canonical Item supports multiple Product representations');
    $reverseRows = DB::fetchAll(
        'SELECT product_id FROM manufacturing_product_item_map WHERE item_id=? ORDER BY product_id ASC',
        [$itemId]
    );
    $check(count($reverseRows) === 2 && (int)$reverseRows[0]['product_id'] === $productId, 'reverse lookup returns a Product collection');

    $secondItem = $items->create(['code_ref' => 'MAP-ITEM-2-' . $suffix, 'name' => 'Second Shared Item']);
    DB::query('INSERT INTO products (parts_name, parts_number) VALUES (?, ?)', ['Concurrent Product ' . $suffix, 'MAP-C-' . $suffix]);
    $concurrentProductId = (int)DB::conn()->insert_id;
    $concurrentItem = $items->create(['code_ref' => 'MAP-ITEM-C-' . $suffix, 'name' => 'Concurrent Shared Item']);
    $children = [];
    $env = getenv();
    $env['OH_IPM_MAPPING_CONCURRENT'] = '1';
    $env['OH_IPM_MAPPING_PRODUCT_ID'] = (string)$concurrentProductId;
    $env['OH_IPM_MAPPING_ITEM_ID'] = (string)$concurrentItem['item_id'];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, null, $env);
        if (!is_resource($process)) {
            throw new RuntimeException('could not start mapping concurrency child');
        }
        $children[] = [$process, $pipes];
    }
    $results = [];
    foreach ($children as [$process, $pipes]) {
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        $results[] = ['json' => json_decode(trim((string)$stdout), true), 'exit' => $exit, 'stderr' => $stderr];
    }
    $successes = count(array_filter($results, static fn(array $result): bool => ($result['json']['ok'] ?? false) === true));
    $check($successes === 1, 'concurrent duplicate Product mapping has exactly one winner');

    $missingProductRejected = false;
    try {
        DB::query('INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)', [$productId + 9999, (int)$secondItem['item_id']]);
    } catch (Throwable) {
        $missingProductRejected = true;
    }
    $check($missingProductRejected, 'missing Product reference is rejected by FK');

    $missingItemRejected = false;
    try {
        DB::query('INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)', [$productId + 1, $itemId + 9999]);
    } catch (Throwable) {
        $missingItemRejected = true;
    }
    $check($missingItemRejected, 'missing Shared Item reference is rejected by FK');

    DB::query('INSERT INTO products (parts_name, parts_number) VALUES (?, ?)', ['Rollback Product ' . $suffix, 'MAP-R-' . $suffix]);
    $rollbackProductId = (int)DB::conn()->insert_id;
    $rollback = false;
    $db = DB::conn();
    $db->begin_transaction();
    try {
        DB::query('INSERT INTO manufacturing_product_item_map (product_id, item_id) VALUES (?, ?)', [$rollbackProductId, (int)$secondItem['item_id']]);
        throw new RuntimeException('probe rollback');
    } catch (Throwable) {
        $db->rollback();
        $rollback = DB::fetchOne(
            'SELECT id FROM manufacturing_product_item_map WHERE product_id=? LIMIT 1',
            [$rollbackProductId]
        ) === null;
    }
    $check($rollback, 'transaction rollback leaves no partial mapping');

    $constrainedDelete = false;
    try {
        DB::query('DELETE FROM products WHERE id=?', [$productId]);
    } catch (Throwable) {
        $constrainedDelete = true;
    }
    $check($constrainedDelete, 'mapped Product cannot be deleted through RESTRICT FK');

    $tenantColumn = DB::fetchOne(
        "SELECT COUNT(*) AS c
         FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name='manufacturing_product_item_map'
         AND column_name='organization_id'"
    );
    $check((int)($tenantColumn['c'] ?? 0) === 0, 'mapping has no inferred tenant column');
} catch (Throwable $e) {
    $fail('database Product mapping probe completed', $e->getMessage());
}

echo json_encode(['passed' => $passed, 'failed' => $failed], JSON_THROW_ON_ERROR) . "\n";
exit($failed === 0 ? 0 : 1);
