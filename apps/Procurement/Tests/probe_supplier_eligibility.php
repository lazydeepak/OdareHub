<?php
declare(strict_types=1);

/**
 * Isolated supplier-eligibility proof for new purchase-order paths.
 *
 * Uses an injected in-memory supplier reader and source ordering checks only.
 * It does not bootstrap the application, connect to a database, execute SQL,
 * or invoke a write-capable order method.
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

$suppliers = [
    1 => ['id' => 1, 'supplier_name' => 'Active Supplier', 'supplier_status' => 'active'],
    2 => ['id' => 2, 'supplier_name' => 'Inactive Supplier', 'supplier_status' => 'inactive'],
    3 => ['id' => 3, 'supplier_name' => 'Disabled Supplier', 'supplier_status' => 'disabled'],
    4 => ['id' => 4, 'supplier_name' => 'Blocked Supplier', 'supplier_status' => 'blocked'],
];
$reads = [];
$reader = static function (int $id) use (&$reads, $suppliers): array|false {
    $reads[] = $id;
    return $suppliers[$id] ?? false;
};

ProcurementOverviewService::assertSupplierEligible(1, $reader);
$check($reads === [1], 'active supplier is accepted through the existing lifecycle rule');

$expectRejected = static function (int $id, string $label) use ($reader, $check): void {
    try {
        ProcurementOverviewService::assertSupplierEligible($id, $reader);
        $check(false, $label . ' is rejected');
    } catch (\RuntimeException $e) {
        $check(true, $label . ' is rejected');
        $check(str_contains($e->getMessage(), 'Selected supplier'), $label . ' rejection remains supplier-specific');
    }
};
$expectRejected(2, 'inactive supplier');
$expectRejected(3, 'disabled supplier');
$expectRejected(4, 'blocked supplier');
$expectRejected(99, 'missing supplier');

$automaticReads = 0;
ProcurementOverviewService::assertSupplierEligible(null, static function (int $id) use (&$automaticReads): array {
    $automaticReads++;
    return ['id' => $id, 'supplier_status' => 'blocked'];
});
$check($automaticReads === 0, 'omitted supplier preserves automatic-supplier semantics without a lookup');

$source = (string)file_get_contents($servicePath);
$createStart = strpos($source, 'public static function createOrder(array $input, string $actor): void');
$receiptStart = strpos($source, 'public static function createReceipt(', (int)$createStart);
$createBody = $createStart !== false && $receiptStart !== false
    ? substr($source, $createStart, $receiptStart - $createStart)
    : '';
$conversionStart = strpos($source, 'public static function createOrderFromApprovedRequest(');
$receiptsListStart = strpos($source, 'public static function latestReceipts(', (int)$conversionStart);
$conversionBody = $conversionStart !== false && $receiptsListStart !== false
    ? substr($source, $conversionStart, $receiptsListStart - $conversionStart)
    : '';

$check($createBody !== '', 'manual order method is present');
$check(str_contains($createBody, 'self::assertSupplierEligible('), 'manual order invokes the common supplier rule');
$check(strpos($createBody, 'self::assertSupplierEligible(') < strpos($createBody, 'AuditLogService::ensureSchema()'), 'manual eligibility rejection precedes audit schema access');
$check(strpos($createBody, 'self::assertSupplierEligible(') < strpos($createBody, 'DB::conn()'), 'manual eligibility rejection precedes transaction opening');
$check(strpos($createBody, 'self::assertSupplierEligible(') < strpos($createBody, 'INSERT INTO procurement_purchase_orders'), 'manual eligibility rejection precedes purchase-order insertion');
$check(strpos($createBody, 'self::assertSupplierEligible(') < strpos($createBody, 'self::recordAudit('), 'manual eligibility rejection precedes creation audit');
$check($conversionBody !== '', 'approved-request conversion method is present');
$check(str_contains($conversionBody, 'self::assertSupplierEligible('), 'approved-request conversion uses the common supplier rule');
$check(strpos($conversionBody, 'self::assertSupplierEligible(') < strpos($conversionBody, 'AuditLogService::ensureSchema()'), 'conversion eligibility rejection remains before audit schema access');
$check(strpos($conversionBody, 'self::assertSupplierEligible(') < strpos($conversionBody, 'INSERT INTO procurement_purchase_orders'), 'conversion eligibility rejection precedes purchase-order insertion');
$check(strpos($conversionBody, 'self::assertSupplierEligible(') < strpos($conversionBody, 'self::setRequestStatus('), 'conversion eligibility rejection precedes linked-request closure');
$check(strpos($conversionBody, 'self::assertSupplierEligible(') < strpos($conversionBody, 'self::recordAudit('), 'conversion eligibility rejection precedes creation audit');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
