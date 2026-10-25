<?php
declare(strict_types=1);

/**
 * Read-only Procurement PO-to-receipt handoff contract probe.
 * No application bootstrap, database, SQL, or mutation is performed.
 */

$root = dirname(__DIR__, 3);
if (!defined('APP_NAME')) {
    define('APP_NAME', 'OdareHub OS');
}
$controller = (string)file_get_contents($root . '/apps/Procurement/Controllers/ProcurementDashboardController.php');
$orders = (string)file_get_contents($root . '/apps/Procurement/Views/orders.php');
$receipts = (string)file_get_contents($root . '/apps/Procurement/Views/receipts.php');

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$check(str_contains($orders, "['issued', 'partial']"), 'receipt handoff is limited to active receiving PO states');
$check(str_contains($orders, '/apps/procurement/receipts?po_id='), 'PO row links to the existing receipt route');
$check(str_contains($orders, "t('proc.receipts.btn_create')"), 'handoff label reuses the existing localized receipt action');
$check(str_contains($receipts, '$selectedPoId'), 'receipt view consumes the selected PO projection');
$check(str_contains($receipts, ' selected'), 'receipt form preselects the requested PO');
$check(str_contains($controller, "'selected_po_id' => self::positiveQueryId(\$_GET['po_id'] ?? null)"), 'controller derives selection from a bounded GET id');
$check(str_contains($controller, 'private static function positiveQueryId'), 'query id normalizer is controller-owned');
$check(str_contains($controller, "preg_match('/^[1-9][0-9]*$/"), 'query id rejects malformed values');
$check(str_contains($controller, 'PHP_INT_MAX'), 'query id rejects integer overflow');
$check(str_contains($receipts, 'name="po_id"'), 'existing receipt POST field remains authoritative');
$check(str_contains($receipts, 'method="post" action="/apps/procurement/receipts/create"'), 'receipt creation route remains unchanged');
$check(str_contains($orders, 'e(t(\'proc.receipts.btn_create\'))'), 'handoff output is escaped');
$check(!str_contains($orders, 'method="post" action="/apps/procurement/receipts/create"'), 'PO list adds no duplicate receipt mutation form');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
