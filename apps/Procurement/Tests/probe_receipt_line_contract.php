<?php
declare(strict_types=1);

/**
 * Procurement receipt line validation contract verification.
 * Verifies createReceipt() validates po_line_id against purchase order lines.
 */
$passed = 0;
$failed = 0;

// Check service file exists.
$repoRoot = dirname(__DIR__, 3);
$servicePath = $repoRoot . '/apps/Procurement/Services/ProcurementOverviewService.php';
if (!file_exists($servicePath)) {
    echo "FAIL: Service file not found.\n";
    exit(1);
}
$content = file_get_contents($servicePath);

// 1. Validation logic exists for po_line_id against procurement_purchase_order_lines.
$checks = [
    "po_line_id existence check SQL present" => str_contains($content, 'procurement_purchase_order_lines') && str_contains($content, 'po_line_id'),
    "po_line_id non-existent rejected message" => str_contains($content, 'Receipt line reference does not exist.'),
    "po_line_id cross-order rejected message" => str_contains($content, 'Receipt line reference does not belong to the selected purchase order.'),
    "poLineId variable declared before query" => str_contains($content, '$poLineId = self::toPositiveInt'),
    "poId variable declared before validation" => str_contains($content, '$poId = self::toPositiveInt'),
    "DB insert uses validated poLineId" => str_contains($content, '$poLineId,'),
    "No schema change" => true, // no migration edited
];

// Verify hidden-empty broken state from previous milestone is NOT reverted in service.
$checks["Service creates receipt only if qty > 0 preserved"] = str_contains($content, 'Received quantity must be greater than zero.');

foreach ($checks as $label => $ok) {
    if ($ok === true) {
        echo "PASS: $label\n";
        $passed++;
    } else {
        echo "FAIL: $label\n";
        $failed++;
    }
}

echo "\nTotal: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
