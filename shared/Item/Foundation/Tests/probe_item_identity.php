<?php
declare(strict_types=1);
define('APP_ROOT', dirname(__DIR__, 4));

/**
 * Shared Items Foundation — Minimal Verification Probe
 * References contract: docs/shared-items-foundation/canonical-item-contract.md
 * Independent of Manufacturing / Inventory / Commercial / Session A
 */
require_once APP_ROOT . '/shared/Item/Foundation/Contracts/ItemIdentityContract.php';
require_once APP_ROOT . '/shared/Item/Foundation/Services/ItemIdentityService.php';

use Shared\Item\Foundation\Contracts\ItemIdentityContract;
use Shared\Item\Foundation\Services\ItemIdentityService;

$passed = 0;
$failed = 0;

function assert_true(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  PASS: $label\n";
    } else {
        $failed++;
        echo "  FAIL: $label\n";
    }
}

// Use isolated temp store
$store = '/tmp/shared-items-test-' . uniqid() . '.json';
$service = new ItemIdentityService($store);

// 1. Contract interface present
assert_true(interface_exists(ItemIdentityContract::class), 'ItemIdentityContract interface exists');

// 2. Service implements contract
assert_true($service instanceof ItemIdentityContract, 'ItemIdentityService implements contract');

// 3. Create with canonical fields
$item = $service->create([
    'name' => 'Test Shared Item',
    'code_ref' => 'SI-001',
    'status' => 'active',
    'category_ref' => 'test-category',
]);
assert_true(($item['item_id'] ?? 0) > 0, 'Created with item_id');
assert_true(($item['code_ref'] ?? '') === 'SI-001', 'code_ref preserved');
assert_true(($item['status'] ?? '') === 'active', 'status preserved');
assert_true(isset($item['created_at']) && isset($item['updated_at']), 'audit fields present');

// 4. Fetch by id and code
$fetched = $service->getById($item['item_id']);
assert_true($fetched !== null && ($fetched['name'] ?? '') === 'Test Shared Item', 'getById returns item');
$fetchedCode = $service->getByCode('SI-001');
assert_true($fetchedCode !== null, 'getByCode returns item');

// 5. List works
$list = $service->list();
assert_true(count($list) >= 1, 'list returns at least created item');

// 6. Update permitted fields
$updated = $service->update((int)$item['item_id'], ['name' => 'Updated', 'status' => 'deprecated']);
assert_true(($updated['status'] ?? '') === 'deprecated', 'status updated to deprecated');
assert_true(($updated['name'] ?? '') === 'Updated', 'name updated');

// 7. Deactivate / activate
$deact = $service->deactivate((int)$item['item_id']);
assert_true(($deact['status'] ?? '') === 'archived', 'deactivate -> archived');
$act = $service->activate((int)$item['item_id']);
assert_true(($act['status'] ?? '') === 'active', 'activate -> active');

// 8. Forbidden field rejected (manufacturing-specific excluded)
try {
    $service->create([
        'name' => 'Bad',
        'code_ref' => 'BAD',
        'status' => 'draft',
        'model' => 'X100',
    ]);
    assert_true(false, 'manufacturing-specific field "model" rejected');
} catch (\InvalidArgumentException $e) {
    assert_true(true, 'manufacturing-specific field "model" rejected (exception)');
}

try {
    $service->update((int)$item['item_id'], ['stock_quantity' => 100]);
    assert_true(false, 'inventory field "stock_quantity" rejected on update');
} catch (\InvalidArgumentException $e) {
    assert_true(true, 'inventory field "stock_quantity" rejected on update');
}

// 9. Duplicate code rejected
try {
    $service->create([
        'name' => 'Duplicate',
        'code_ref' => 'SI-001',
        'status' => 'draft',
    ]);
    assert_true(false, 'duplicate code_rejected');
} catch (\InvalidArgumentException $e) {
    assert_true(true, 'duplicate code rejected (expected: unique business reference)');
}

// 10. itemRef deterministic
$ref = $service->itemRef((int)$item['item_id'], 'SI-001');
assert_true(($ref['ref_shape'] ?? '') === 'canonical-item-identity', 'itemRef deterministic shape');
assert_true(($ref['item_id'] ?? 0) === (int)$item['item_id'], 'itemRef id preserved');

// 11. No manufacturing-specific identity duplicated
assert_true(!interface_exists('App\\Manufacturing\\Modules\\Products\\Services\\ProductIdentity'), 'No duplicate Product identity created');

// Cleanup
@unlink($store);

echo "=== SHARED ITEMS FOUNDATION PROBE ===\n";
echo "Passed: $passed\nFailed: $failed\n";
echo ($failed === 0 ? "PASS" : "FAIL ($failed failures)") . "\n";
exit($failed > 0 ? 1 : 0);
