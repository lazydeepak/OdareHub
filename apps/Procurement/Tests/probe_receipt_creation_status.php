<?php
declare(strict_types=1);

/**
 * OH-AUTO2-128 isolated receipt-creation status proof.
 *
 * This probe invokes only the private pure status normalizer through
 * reflection and inspects source ordering. It does not bootstrap the app,
 * connect to a database, execute SQL, provision schema, or invoke a mutation.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$viewPath = $root . '/apps/Procurement/Views/receipts.php';
$concurrencyPath = $root . '/apps/Procurement/Tests/probe_procurement_concurrency.php';

require_once $servicePath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$normalize = new ReflectionMethod(ProcurementOverviewService::class, 'normalizeReceiptStatus');
$normalizeReceiptStatus = static function ($value, bool $defaultReceived = false, string $error = 'Unsupported receipt status transition.') use ($normalize): string {
    return (string)$normalize->invoke(null, $value, $defaultReceived, $error);
};

foreach ([null, '', '   '] as $value) {
    $check(
        $normalizeReceiptStatus($value, true, 'Unsupported receipt status.') === 'received',
        'missing or blank creation status defaults to received'
    );
}

foreach (['received', 'posted', 'cancelled'] as $status) {
    $check(
        $normalizeReceiptStatus($status, true, 'Unsupported receipt status.') === $status,
        "supported creation status {$status} is accepted"
    );
}

$check(
    $normalizeReceiptStatus('  PoStEd  ', true, 'Unsupported receipt status.') === 'posted',
    'mixed-case and surrounding whitespace normalize to posted'
);
$check(
    $normalizeReceiptStatus("\tCANCELLED\n", true, 'Unsupported receipt status.') === 'cancelled',
    'mixed-case and surrounding whitespace normalize to cancelled'
);

foreach (['draft', 'unknown', 'received-now', ['posted']] as $value) {
    try {
        $normalizeReceiptStatus($value, true, 'Unsupported receipt status.');
        $check(false, 'unsupported creation status is rejected before mutation');
    } catch (InvalidArgumentException $e) {
        $check($e->getMessage() === 'Unsupported receipt status.', 'unsupported creation status is rejected before mutation');
    }
}

try {
    $normalizeReceiptStatus('', false);
    $check(false, 'setReceiptStatus blank status remains rejected');
} catch (InvalidArgumentException $e) {
    $check($e->getMessage() === 'Unsupported receipt status transition.', 'setReceiptStatus blank status remains rejected');
}

$source = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$view = (string)file_get_contents($viewPath);
$concurrency = (string)file_get_contents($concurrencyPath);
$createStart = strpos($source, 'public static function createReceipt(');
$createEnd = strpos($source, 'public static function ', $createStart + 1);
$create = substr($source, $createStart, $createEnd === false ? null : $createEnd - $createStart);
$transitionStart = strpos($source, 'public static function setReceiptStatus(');
$transitionEnd = strpos($source, 'private static function ', $transitionStart + 1);
$transition = substr($source, $transitionStart, $transitionEnd === false ? null : $transitionEnd - $transitionStart);

$statusCall = strpos($create, 'self::normalizeReceiptStatus(');
$auditEnsure = strpos($create, 'AuditLogService::ensureSchema()');
$begin = strpos($create, '$db->begin_transaction()');
$insert = strpos($create, 'INSERT INTO procurement_receipts');
$audit = strpos($create, "'new_state' => \$status");
$recompute = strpos($create, "if (\$status === 'posted')");

$check($create !== '', 'createReceipt method is available');
$check($transition !== '', 'setReceiptStatus method is available');
$check($statusCall !== false, 'createReceipt uses shared status normalization');
$check($statusCall !== false && $statusCall < $auditEnsure && $auditEnsure < $begin, 'status validation precedes audit initialization and transaction');
$check(substr_count($create, '$input[\'receipt_status\']') === 1, 'raw status input is read only for canonicalization');
$check(str_contains($create, "true,\n            'Unsupported receipt status.'"), 'creation enables received default and creation-specific rejection');
$check(str_contains($create, '$status,') && str_contains($create, "'new_state' => \$status"), 'stored status and audit new_state use canonical status');
$check($insert !== false && $audit !== false && $insert < $audit, 'receipt insertion precedes audit with canonical status');
$check($audit !== false && $recompute !== false && $audit < $recompute, 'audit precedes posted-status recomputation');
$check(str_contains($create, "if (\$status === 'posted')") && str_contains($create, 'recomputeOrderStatusFromReceipts($poId, $actor)'), 'posted recomputation uses canonical status');
$check(str_contains($transition, 'self::normalizeReceiptStatus($status)'), 'status transitions use the same normalizer');
$check(!str_contains($transition, "\$allowed = ['received', 'posted', 'cancelled']"), 'status vocabulary is not duplicated in transition logic');
$check(str_contains($create, '$poLineId = self::toPositiveInt'), 'optional receipt-line behavior remains intact');
$check(str_contains($create, 'self::assertPurchaseOrderExists($poId)'), 'valid PO identity enforcement remains intact');
$check(str_contains($create, '$db->commit()') && str_contains($create, '$db->rollback()'), 'receipt transaction commit/rollback remains intact');
$check(str_contains($controller, "Auth::requireCsrf((string)(\$_POST['csrf'] ?? ''))"), 'controller CSRF ordering remains intact');
$check(str_contains($view, 'name="receipt_status" value="received"'), 'creation form defaults to received');
$check(str_contains($concurrency, "'receipt_status' => 'posted'"), 'existing internal posted receipt creation remains intact');
$check(str_contains($create, 'AuditLogService::ensureSchema()') && str_contains($create, 'begin_transaction()'), 'audit initialization remains before transaction for creation');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
