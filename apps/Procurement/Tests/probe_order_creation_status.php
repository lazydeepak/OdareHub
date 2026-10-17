<?php
declare(strict_types=1);

/**
 * OH-AUTO2-131 isolated manual PO creation-status proof.
 *
 * Pure normalization is invoked through reflection and the creation method is
 * checked for source ordering. No application bootstrap, database connection,
 * SQL execution, schema provisioning, or mutation method is invoked.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$viewPath = $root . '/apps/Procurement/Views/orders.php';
$requestProofPath = $root . '/apps/Procurement/Tests/probe_request_status_transaction.php';
$poProofPath = $root . '/apps/Procurement/Tests/probe_order_status_transaction.php';

require_once $servicePath;

use Apps\Procurement\Services\ProcurementOverviewService;

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$normalize = new ReflectionMethod(ProcurementOverviewService::class, 'normalizeOrderCreationStatus');
$normalizeOrderCreationStatus = static function ($value) use ($normalize): string {
    return (string)$normalize->invoke(null, $value);
};

foreach ([null, '', '   '] as $value) {
    $check($normalizeOrderCreationStatus($value) === 'draft', 'missing or blank creation status defaults to draft');
}

foreach (['draft', 'approved', 'issued', 'partial', 'closed', 'cancelled'] as $status) {
    $check($normalizeOrderCreationStatus($status) === $status, "supported creation status {$status} is accepted");
}

$check($normalizeOrderCreationStatus('  PaRtIaL  ') === 'partial', 'mixed-case and surrounding whitespace normalize to partial');
$check($normalizeOrderCreationStatus("\tCANCELLED\n") === 'cancelled', 'mixed-case and surrounding whitespace normalize to cancelled');

foreach (['unknown', 'posted', 'draft-now', ['closed'], 123] as $value) {
    try {
        $normalizeOrderCreationStatus($value);
        $check(false, 'unsupported or malformed creation status is rejected');
    } catch (InvalidArgumentException $e) {
        $check($e->getMessage() === 'Unsupported order status transition.', 'unsupported or malformed creation status is rejected');
    }
}

$source = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$view = (string)file_get_contents($viewPath);
$createStart = strpos($source, 'public static function createOrder(array $input, string $actor): void');
$createEnd = strpos($source, 'public static function assertManualOrderRequestEligible(', (int)$createStart);
$create = $createStart !== false && $createEnd !== false ? substr($source, $createStart, $createEnd - $createStart) : '';
$approvedStart = strpos($source, 'public static function createOrderFromApprovedRequest(');
$approvedEnd = strpos($source, 'public static function intakeFromManufacturingDemand(', (int)$approvedStart);
$approved = $approvedStart !== false && $approvedEnd !== false ? substr($source, $approvedStart, $approvedEnd - $approvedStart) : '';

$statusCall = strpos($create, 'self::normalizeOrderCreationStatus(');
$auditEnsure = strpos($create, 'AuditLogService::ensureSchema()');
$begin = strpos($create, '$db->begin_transaction()');
$insert = strpos($create, 'INSERT INTO procurement_purchase_orders');
$audit = strpos($create, "'new_state' => \$poStatus");
$commit = strpos($create, '$db->commit()');
$rollback = strpos($create, '$db->rollback()');

$check($create !== '', 'createOrder method is available');
$check($statusCall !== false, 'createOrder uses creation status normalization');
$check($statusCall !== false && $statusCall < $auditEnsure && $auditEnsure < $begin, 'status validation precedes audit initialization and transaction');
$check(substr_count($create, '$input[\'po_status\']') === 1, 'raw PO status input is read only for canonicalization');
$check($insert !== false && $audit !== false && $insert < $audit, 'PO insertion and audit both use canonical status');
$check($audit !== false && str_contains($create, "'new_state' => \$poStatus"), 'creation audit new_state uses canonical status');
$check($commit !== false && $rollback !== false, 'manual PO transaction commit/rollback remains intact');
$check(str_contains($create, '$poStatus,') || str_contains($create, '$poStatus'), 'manual PO insert receives canonical status');
$check(str_contains($create, 'self::assertSupplierEligible('), 'supplier eligibility remains before transaction');
$check(str_contains($create, 'self::assertManualOrderRequestEligible($requestId)'), 'request linkage remains intact');
$check(str_contains($create, 'self::setRequestStatus($requestId, \'closed\''), 'internal request closure remains transaction-compatible');

$check(str_contains($approved, "'draft'"), 'approved-request conversion explicitly creates draft POs');
$check(str_contains($approved, 'self::setRequestStatus($requestId, \'closed\''), 'approved-request conversion remains unchanged');
$check(str_contains($source, 'private static function normalizeOrderStatus(string $status)'), 'existing PO transition normalizer remains present');
$check(str_contains($source, 'self::normalizeOrderStatus($status)'), 'direct/internal PO transitions retain shared normalizer');
$check(str_contains($controller, "Auth::requireAppAccess('procurement')") && str_contains($controller, 'Auth::requireCsrf('), 'controller authorization and CSRF remain intact');
$check(str_contains($controller, 'ProcurementOverviewService::createOrder($_POST'), 'manual controller dispatch remains unchanged');
$check(str_contains($view, 'name="po_status" value="draft"'), 'manual PO form still submits draft');
$check(is_file($requestProofPath), 'request transition regression remains available');
$check(is_file($poProofPath), 'direct PO transition regression remains available');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
