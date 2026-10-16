<?php
declare(strict_types=1);

/**
 * Isolated Procurement receipt purchase-order identity proof.
 *
 * Uses the service's injected purchase-order reader only; it does not load the
 * application, connect to a database, execute SQL, or provision schema.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
require_once $servicePath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$readerCalls = [];
$reader = static function (int $id) use (&$readerCalls): array {
    $readerCalls[] = $id;
    return $id === 42 ? ['id' => 42] : [];
};

$check(
    ProcurementOverviewService::assertPurchaseOrderExists(42, $reader)['id'] === 42,
    'valid purchase-order identity is accepted'
);
$check($readerCalls === [42], 'purchase-order reader receives the exact identity');

foreach ([null, '', 'abc', '1abc', 1.0, '999999999999999999999999'] as $value) {
    try {
        ProcurementOverviewService::normalizeReceiptPurchaseOrderId($value);
        $check(false, 'malformed PO identity is rejected');
    } catch (InvalidArgumentException) {
        $check(true, 'malformed PO identity is rejected');
    }
}

$check(
    ProcurementOverviewService::normalizeReceiptPurchaseOrderId('42') === 42,
    'HTTP-form decimal PO identity is normalized exactly'
);

foreach ([
    0 => 'zero PO identity is rejected',
    -1 => 'negative PO identity is rejected',
] as $id => $label) {
    try {
        ProcurementOverviewService::assertPurchaseOrderExists($id, $reader);
        $check(false, $label);
    } catch (InvalidArgumentException) {
        $check(true, $label);
    }
}

try {
    ProcurementOverviewService::assertPurchaseOrderExists(41, $reader);
    $check(false, 'nonexistent purchase order is rejected');
} catch (InvalidArgumentException) {
    $check(true, 'nonexistent purchase order is rejected');
}

$source = (string)file_get_contents($servicePath);
$createReceipt = substr($source, strpos($source, 'public static function createReceipt('));
$check(str_contains($createReceipt, 'normalizeReceiptPurchaseOrderId'), 'receipt requires strict PO identity normalization');
$check(str_contains($createReceipt, 'self::assertPurchaseOrderExists($poId)'), 'receipt validates and locks PO before insert');
$check(str_contains($createReceipt, 'INSERT INTO procurement_receipts'), 'receipt insertion remains present');
$check(str_contains($createReceipt, '$poLineId = self::toPositiveInt'), 'receipt line remains optional');
$check(str_contains($createReceipt, 'Receipt line reference does not belong'), 'cross-order receipt line rejection remains');
$check(str_contains($createReceipt, 'Received quantity must be greater than zero.'), 'positive quantity policy remains');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
