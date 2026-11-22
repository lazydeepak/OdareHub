<?php
declare(strict_types=1);

/**
 * Governed disposable-database-only IPM-006 ownership probe.
 *
 * Exit 0 = pass, 1 = implementation failure, 2 = missing/invalid disposable
 * authorization. No installed or development database is an accepted target.
 */
$repositoryRoot = trim((string)(getenv('OH_IPM_REPO') ?: dirname(__DIR__, 5)));
require_once $repositoryRoot . '/scripts/testing/DisposableDbTestEntryPointGuard.php';

try {
    $context = DisposableDbTestEntryPointGuard::authorize('OH_IPM', $repositoryRoot);
} catch (Throwable $e) {
    echo "INCONCLUSIVE: disposable Product ownership authorization unavailable: {$e->getMessage()}\n";
    exit(2);
}

define('APP_ROOT', $context['app_root']);
require_once APP_ROOT . '/vendor/autoload.php';

use App\Core\DB;
use App\Services\AppMigrationService;
use App\Services\AppSchemaSyncService;
use Apps\Platform\Services\OrganizationMembershipContext;
use Plugins\Products\Services\ProductCreationService;

require_once APP_ROOT . '/apps/Platform/Services/OrganizationMembershipContext.php';
require_once APP_ROOT . '/apps/Manufacturing/modules/Products/Services/ProductCreationService.php';

$passed = 0;
$failed = 0;
$pass = static function (string $label) use (&$passed): void {
    $passed++;
    echo "PASS: {$label}\n";
};
$fail = static function (string $label, string $detail) use (&$failed): void {
    $failed++;
    echo "FAIL: {$label}: {$detail}\n";
};
$check = static function (bool $condition, string $label) use ($pass, $fail): void {
    $condition ? $pass($label) : $fail($label, 'assertion failed');
};
$tableExists = static function (string $table): bool {
    $row = DB::fetchOne(
        'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',
        [$table]
    );
    return (int)($row['c'] ?? 0) === 1;
};

try {
    $migrationService = new AppMigrationService();
    $migrationService->ensureCoreTables();

    AppSchemaSyncService::runAdditiveSql((string)file_get_contents(
        APP_ROOT . '/apps/Platform/modules/Organization/migrations/001_create_organization_tables.sql'
    ));
    AppSchemaSyncService::runAdditiveSql((string)file_get_contents(
        APP_ROOT . '/apps/Shared/migrations/20261123_000001_create_shared_items.sql'
    ));

    $productDir = APP_ROOT . '/apps/Manufacturing/modules/Products/migrations';
    $applied = $migrationService->runMigrations('manufacturing.products', 'ipm-006', $productDir);
    $check(in_array('015_product_organization_ownership.sql', $applied, true), 'Products ownership migration applies through the migration ledger');
    $replay = $migrationService->runMigrations('manufacturing.products', 'ipm-006', $productDir);
    $check($replay === [], 'Products migration replay is ledger-idempotent');
    $check($tableExists('products') && $tableExists('org_companies'), 'Product and organization tables exist');

    $columns = DB::fetchOne(
        "SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable
         FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name='products' AND column_name='organization_id'"
    );
    $check(is_array($columns) && $columns['is_nullable'] === 'YES', 'organization_id is nullable for legacy Products');
    $check(is_array($columns) && str_contains((string)$columns['column_type'], 'int'), 'organization_id preserves integer organization identity');
    $index = DB::fetchOne(
        "SELECT COUNT(*) AS c FROM information_schema.statistics
         WHERE table_schema=DATABASE() AND table_name='products' AND index_name='idx_products_organization'"
    );
    $check((int)($index['c'] ?? 0) > 0, 'organization_id index exists');
    $foreignKey = DB::fetchOne(
        "SELECT DELETE_RULE FROM information_schema.referential_constraints
         WHERE constraint_schema=DATABASE() AND table_name='products' AND constraint_name='fk_products_organization'"
    );
    $check(is_array($foreignKey) && $foreignKey['DELETE_RULE'] === 'RESTRICT', 'organization FK uses restrictive deletion');

    $suffix = bin2hex(random_bytes(4));
    DB::query(
        'INSERT INTO org_companies (company_code, company_name, is_active) VALUES (?, ?, 1)',
        ['IPM006-' . $suffix, 'IPM006 Organization']
    );
    $organizationId = (int)DB::conn()->insert_id;
    $contextEvidence = new OrganizationMembershipContext(9001, $organizationId, 'ipm006-test-evidence');

    DB::query(
        'INSERT INTO products (parts_name, parts_number) VALUES (?, ?)',
        ['Legacy Product ' . $suffix, 'IPM006-LEG-' . $suffix]
    );
    $legacyId = (int)DB::conn()->insert_id;
    $legacy = DB::fetchOne('SELECT organization_id FROM products WHERE id=? LIMIT 1', [$legacyId]);
    $check(is_array($legacy) && $legacy['organization_id'] === null, 'historical direct Product remains NULL-owned');

    $createdId = ProductCreationService::create([
        'parts_name' => 'Owned Product ' . $suffix,
        'parts_number' => 'IPM006-OWN-' . $suffix,
        'model' => 'IPM006',
    ], $contextEvidence);
    $created = DB::fetchOne('SELECT organization_id FROM products WHERE id=? LIMIT 1', [$createdId]);
    $check(is_array($created) && (int)$created['organization_id'] === $organizationId, 'service-mediated Product persists verified organization atomically');

    $beforeFailed = DB::fetchOne('SELECT COUNT(*) AS c FROM products')['c'] ?? 0;
    $badContext = new OrganizationMembershipContext(9001, 999999999, 'invalid-test-evidence');
    $atomicFailure = false;
    try {
        ProductCreationService::create([
            'parts_name' => 'Rejected Product ' . $suffix,
            'parts_number' => 'IPM006-REJECT-' . $suffix,
        ], $badContext);
    } catch (Throwable) {
        $atomicFailure = true;
    }
    $afterFailed = DB::fetchOne('SELECT COUNT(*) AS c FROM products')['c'] ?? 0;
    $check($atomicFailure && (int)$beforeFailed === (int)$afterFailed, 'invalid organization FK failure leaves no Product row');

    DB::query(
        'INSERT INTO products (organization_id, parts_name, parts_number) VALUES (?, ?, ?)',
        [$organizationId, 'Same Tenant Product ' . $suffix, 'IPM006-SAME-' . $suffix]
    );
    $sameId = (int)DB::conn()->insert_id;
    DB::conn()->begin_transaction();
    ProductCreationService::upsertOwned([
        'parts_name' => 'Same Tenant Updated ' . $suffix,
        'parts_number' => 'IPM006-SAME-' . $suffix,
    ], $contextEvidence);
    DB::conn()->commit();
    $same = DB::fetchOne('SELECT parts_name, organization_id FROM products WHERE id=? LIMIT 1', [$sameId]);
    $check(is_array($same) && str_contains((string)$same['parts_name'], 'Updated') && (int)$same['organization_id'] === $organizationId, 'same-organization import update preserves ownership');

    DB::conn()->begin_transaction();
    $batchRolledBack = false;
    try {
        ProductCreationService::upsertOwned([
            'parts_name' => 'Batch Product ' . $suffix,
            'parts_number' => 'IPM006-BATCH-' . $suffix,
        ], $contextEvidence);
        ProductCreationService::upsertOwned([
            'parts_name' => 'Legacy Conflict ' . $suffix,
            'parts_number' => 'IPM006-LEG-' . $suffix,
        ], $contextEvidence);
        DB::conn()->commit();
    } catch (Throwable) {
        DB::conn()->rollback();
        $batchRolledBack = true;
    }
    $batchRow = DB::fetchOne(
        'SELECT id FROM products WHERE parts_number=? LIMIT 1',
        ['IPM006-BATCH-' . $suffix]
    );
    $legacyAfter = DB::fetchOne(
        'SELECT parts_name, organization_id FROM products WHERE id=? LIMIT 1',
        [$legacyId]
    );
    $check($batchRolledBack && $batchRow === null, 'batch authorization failure rolls back earlier Product rows');
    $check(is_array($legacyAfter) && $legacyAfter['organization_id'] === null, 'batch conflict does not claim legacy NULL ownership');

    $mappingTenantColumn = DB::fetchOne(
        "SELECT COUNT(*) AS c FROM information_schema.columns
         WHERE table_schema=DATABASE() AND table_name='manufacturing_product_item_map' AND column_name='organization_id'"
    );
    $check((int)($mappingTenantColumn['c'] ?? 0) === 0, 'IPM-004 mapping remains without runtime tenant activation');
} catch (Throwable $e) {
    $fail('IPM-006 disposable Product ownership probe', $e->getMessage());
}

echo json_encode(['passed' => $passed, 'failed' => $failed], JSON_THROW_ON_ERROR) . "\n";
exit($failed === 0 ? 0 : 1);
