<?php
declare(strict_types=1);

/**
 * Procurement manual purchase-order form contract verification.
 * Verifies that the visible form fields match the createOrder() contract.
 */
$passed = 0;
$failed = 0;

// Read the view source.
$repoRoot = dirname(__DIR__, 3); // from apps/Procurement/Tests -> repo root
$viewPath = $repoRoot . '/apps/Procurement/Views/orders.php';
if (!file_exists($viewPath)) {
    echo "FAIL: orders.php not found at $viewPath\n";
    exit(1);
}
$content = file_get_contents($viewPath);

// 1. The previously broken hidden-empty fields must NOT exist as hidden empty.
$checks = [
    "line_product_id visible input" => (str_contains($content, '<label>') && str_contains($content, 'name="line_product_id"') && !str_contains($content, 'name="line_product_id" value=""') && !str_contains($content, 'type="hidden" name="line_product_id"')) ? true : false,
    "ordered_qty visible input"     => (str_contains($content, '<label>') && str_contains($content, 'name="ordered_qty"')     && !str_contains($content, 'name="ordered_qty" value=""')     && !str_contains($content, 'type="hidden" name="ordered_qty"')) ? true : false,
    "unit_price visible input"     => (str_contains($content, '<label>') && str_contains($content, 'name="unit_price"')     && !str_contains($content, 'name="unit_price" value=""')     && !str_contains($content, 'type="hidden" name="unit_price"')) ? true : false,
    "line_description visible input" => (str_contains($content, '<label>') && str_contains($content, 'name="line_description"') && !str_contains($content, 'type="hidden" name="line_description"')) ? true : false,
    "line_status remains hidden (service contract)" => (str_contains($content, 'type="hidden" name="line_status"')) ? true : false,
];

// Verify exact names used by service createOrder().
$serviceFields = ['line_product_id', 'ordered_qty', 'unit_price', 'line_description', 'line_status'];
$serviceMatched = true;
foreach ($serviceFields as $f) {
    if (!str_contains($content, 'name="' . $f . '"')) {
        echo "FAIL: Service field $f not present in form.\n";
        $serviceMatched = false;
    }
}

if ($serviceMatched) {
    $checks["All service-required input names present"] = true;
} else {
    $checks["All service-required input names present"] = false;
    $serviceMatched = false;
}

// Verify hidden-empty broken state is absent.
$brokenHidden = false;
if (str_contains($content, 'name="line_product_id" value=""') ||
    str_contains($content, 'name="ordered_qty" value=""') ||
    str_contains($content, 'name="unit_price" value=""')) {
    $brokenHidden = true;
}
$checks["Broken hidden-empty line fields removed"] = !$brokenHidden;

// 3. Verify no hidden line fields replaced incorrectly (e.g., renamed).
$nameIntegrity = true;
$expectedNames = ['line_product_id', 'ordered_qty', 'unit_price', 'line_description', 'line_status'];
foreach ($expectedNames as $n) {
    $count = substr_count($content, 'name="' . $n . '"');
    if ($count !== 1) {
        echo "FAIL: Field $n appears $count times (expected exactly 1).\n";
        $nameIntegrity = false;
    }
}
$checks["Each required field appears exactly once"] = $nameIntegrity;

// 4. Verify existing CSRF/action/order-level fields preserved.
$checks["CSRF field preserved"] = str_contains($content, 'name="csrf"');
$checks["PO ref field preserved"] = str_contains($content, 'name="po_ref"');
$checks["Supplier select preserved"] = str_contains($content, 'name="supplier_id"');
$checks["Request select preserved"] = str_contains($content, 'name="request_id"');
$checks["Create action preserved"] = str_contains($content, 'action="/apps/procurement/orders/create"');

foreach ($checks as $label => $ok) {
    if ($ok) {
        echo "PASS: $label\n";
        $passed++;
    } else {
        echo "FAIL: $label\n";
        $failed++;
    }
}

echo "\nTotal: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
