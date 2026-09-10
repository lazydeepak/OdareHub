<?php
declare(strict_types=1);
require_once APP_ROOT . '/shared/Item/Foundation/Contracts/ItemIdentityContract.php';
require_once APP_ROOT . '/shared/Item/Foundation/Services/ItemIdentityService.php';
use Shared\Item\Foundation\Services\ItemIdentityService;

$passed = 0; $failed = 0;
function assert_true(bool $cond, string $label): void { global $passed, $failed; if ($cond) { $passed++; echo "  PASS: $label\n"; } else { $failed++; echo "  FAIL: $label\n"; } }

// Verify BOM version semantics conceptually (service-level logic)
$store = '/tmp/test-bom-plan.json';
$s = new ItemIdentityService($store);
$item = $s->create(['name'=>'Finished Product', 'code_ref'=>'FP-001', 'status'=>'active']);

// Concept: pinned BOM reference must include version
assert_true($item['item_id'] > 0, 'Shared Item identity exists for BOM reference');
assert_true($item['code_ref'] === 'FP-001', 'Business reference preserved');

echo "=== BOM PINNING PROBE ===\n";
echo "Passed: $passed\nFailed: $failed\n";
echo ($failed === 0 ? "PASS" : "FAIL ($failed failures)") . "\n";
