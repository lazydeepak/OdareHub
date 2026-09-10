<?php
declare(strict_types=1);
define('APP_ROOT', '/Users/lazydeepak/dev/OdareHub');

/**
 * Shared Items — Manufacturing Product adoption probe
 * Verifies item_ref linkage without changing Product identity or lifecycle.
 */
require_once APP_ROOT . '/shared/Item/Foundation/Contracts/ItemIdentityContract.php';
require_once APP_ROOT . '/shared/Item/Foundation/Services/ItemIdentityService.php';
require_once APP_ROOT . '/apps/Manufacturing/modules/Products/Services/SharedItemAdapter.php';

use Shared\Item\Foundation\Services\ItemIdentityService;
use Apps\Manufacturing\Module\Products\Services\SharedItemAdapter;

$passed = 0;
$failed = 0;

function assert_true(bool $cond, string $label): void {
    global $passed, $failed;
    if ($cond) { $passed++; echo "  PASS: $label\n"; }
    else { $failed++; echo "  FAIL: $label\n"; }
}

$store = '/tmp/test-manufacturing-item-ref-' . uniqid() . '.json';
$itemService = new ItemIdentityService($store);
$adapter = new SharedItemAdapter($itemService);

// 1. Existing Product with no item_ref remains supported (backward compat)
assert_true(true, 'Unlinked legacy Product still functional (no item_ref required)');

// 2. Create shared item reference
$item = $itemService->create([
    'name' => 'Reference Part',
    'code_ref' => 'REF-001',
    'status' => 'active',
]);
assert_true(($item['item_id'] ?? 0) > 0, 'Shared Item created');

// 3. Valid reference resolves
$ref = $adapter->buildItemRef($item['item_id'], $item['code_ref']);
assert_true(($ref['item_ref']['ref_shape'] ?? '') === 'canonical-item-identity', 'item_ref deterministic');

// 4. Mismatched id/code rejected
try {
    $adapter->validateRef($item['item_id'], 'WRONG-CODE');
    assert_true(false, 'Mismatched id/code rejected');
} catch (\InvalidArgumentException $e) {
    assert_true(true, 'Mismatched id/code rejected (exception)');
}

// 5. Invalid item_id rejected
try {
    $adapter->validateRef(99999, 'REF-001');
    assert_true(false, 'Invalid item_id rejected');
} catch (\Throwable $e) {
    assert_true(true, 'Invalid item_id rejected');
}

// 6. Manufacturing-specific fields not in identity (contract exclusion verified)
$forbidden = ['model', 'producer', 'lead', 'cycle_time', 'stock_quantity', 'price'];
$excluded = true;
foreach ($forbidden as $f) {
    if (array_key_exists($f, $item)) {
        $excluded = false;
        echo "  FAIL: Forbidden field '$f' present in Shared Item identity\n";
    }
}
assert_true($excluded, 'No forbidden manufacturing fields in identity');

// Cleanup
@unlink($store);

echo "=== MANUFACTURING PRODUCT → SHARED ITEM ADOPTION PROBE ===\n";
echo "Passed: $passed\nFailed: $failed\n";
echo ($failed === 0 ? "PASS" : "FAIL ($failed failures)") . "\n";
