<?php
declare(strict_types=1);

/**
 * Static regression probe for the Procurement order/receipt service boundary.
 *
 * This intentionally does not bootstrap DB or invoke mutation methods. It
 * verifies that receipt-line ownership validation lives in createReceipt(),
 * while createOrder() retains only purchase-order-line creation behavior.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$source = file_get_contents($servicePath);

if (!is_string($source) || $source === '') {
    fwrite(STDERR, "FAIL: Procurement service source unavailable\n");
    exit(1);
}

$methods = [];
foreach (['createOrder', 'createReceipt'] as $method) {
    $start = strpos($source, 'public static function ' . $method . '(');
    if ($start === false) {
        fwrite(STDERR, "FAIL: missing {$method} method\n");
        exit(1);
    }

    $next = strpos($source, 'public static function ', $start + 1);
    $methods[$method] = substr($source, $start, $next === false ? null : $next - $start);
}

$checks = [
    'createOrder method exists' => $methods['createOrder'] !== '',
    'createReceipt method exists' => $methods['createReceipt'] !== '',
    'createOrder has PO-line insert' => str_contains($methods['createOrder'], 'INSERT INTO procurement_purchase_order_lines'),
    'createOrder does not validate receipt line ownership' => !str_contains($methods['createOrder'], 'Receipt line reference does not exist.'),
    'createOrder does not read receipt po_id' => !str_contains($methods['createOrder'], "\$input['po_id']"),
    'createReceipt validates receipt line existence' => str_contains($methods['createReceipt'], 'Receipt line reference does not exist.'),
    'createReceipt validates receipt line ownership' => str_contains($methods['createReceipt'], 'Receipt line reference does not belong to the selected purchase order.'),
    'createReceipt writes validated poLineId' => str_contains($methods['createReceipt'], '$poLineId,'),
    'no schema mutation in probe' => true,
];

$passed = 0;
$failed = 0;
foreach ($checks as $label => $ok) {
    if ($ok) {
        $passed++;
        echo "PASS: {$label}\n";
    } else {
        $failed++;
        echo "FAIL: {$label}\n";
    }
}

echo "\nResults: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
