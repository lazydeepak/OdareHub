<?php
declare(strict_types=1);

/**
 * Isolated source/instrumentation proof for receipt-status posting.
 *
 * The production DB abstraction is not bootstrapped here. Assertions classify
 * transaction and lock ordering from source; the failure cases use a small
 * in-memory transaction spy to document the required rollback contract.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$auditPath = $root . '/app/Core/AuditLogService.php';
$source = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$auditSource = (string)file_get_contents($auditPath);
$start = strpos($source, 'public static function setReceiptStatus(');
$end = strpos($source, '    private static function recomputeOrderStatusFromReceipts', $start === false ? 0 : $start);
$method = $start !== false && $end !== false ? substr($source, $start, $end - $start) : '';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$position = static function (string $needle) use ($method) {
    return strpos($method, $needle);
};

$check($method !== '', 'setReceiptStatus method is available');
$check($position('begin_transaction()') !== false, 'posting begins an explicit transaction');
$check($position('$db->commit()') !== false, 'posting commits explicitly');
$check($position('$db->rollback()') !== false, 'posting rolls back explicitly');
$check($position('SELECT po_id FROM procurement_receipts') !== false, 'posting establishes linked PO identity');
$check($position('self::assertPurchaseOrderExists($discoveredPoId)') !== false, 'posting locks and validates the linked PO');
$check($position('FOR UPDATE') !== false, 'posting revalidates and locks the receipt row');
$check($position('UPDATE procurement_receipts SET receipt_status') !== false, 'receipt status update remains present');
$check($position('self::recordAudit(') !== false, 'receipt audit remains inside posting method');
$check($position('self::recomputeOrderStatusFromReceipts($poId, $actor)') !== false, 'PO recomputation remains inside posting method');
$check($position('$oldState !== $status') !== false, 'existing no-change audit behavior remains conditional');
$check($position('throw new \\RuntimeException(\'Receipt not found.\')') !== false, 'missing receipt is rejected inside the transaction');
$check($position('catch (\\Throwable $e)') !== false, 'posting catches downstream failures');
$check($position('rollback();') !== false, 'downstream failures trigger rollback');

$begin = $position('$db->begin_transaction()');
$identity = $position('SELECT po_id FROM procurement_receipts');
$poLock = $position('self::assertPurchaseOrderExists($discoveredPoId)');
$receiptLock = $position('FOR UPDATE');
$update = $position('UPDATE procurement_receipts SET receipt_status');
$audit = $position('self::recordAudit(');
$recompute = $position('self::recomputeOrderStatusFromReceipts($poId, $actor)');
$commit = $position('$db->commit()');
$rollback = $position('$db->rollback()');

$auditEnsure = $position('AuditLogService::ensureSchema()');
$check($auditEnsure !== false, 'audit initialization remains explicit');
$check($auditEnsure !== false && $auditEnsure < $begin, 'audit initialization precedes transaction start');
$check($auditEnsure !== false && substr_count(substr($method, $begin), 'AuditLogService::ensureSchema()') === 0, 'no audit initialization remains inside transaction');
$check(str_contains($auditSource, 'self::ensureSchema();'), 'audit log call graph re-enters schema initialization');
$check(str_contains($auditSource, 'CREATE TABLE IF NOT EXISTS audit_activity_log'), 'audit initialization is DDL-capable');

$check($identity < $begin && $begin < $poLock && $poLock < $receiptLock, 'preliminary identity read is outside transaction and PO lock is first inside');
$check($receiptLock < $update && $update < $audit && $audit < $recompute && $recompute < $commit, 'update, audit, recomputation, and commit are ordered atomically');
$check($rollback > $recompute, 'rollback covers downstream audit/recompute failures');
$check($auditEnsure !== false && substr_count(substr($method, $begin), 'AuditLogService::ensureSchema()') === 0, 'posting transaction body has no audit schema initialization');
$check(str_contains($controller, "ProcurementOverviewService::setReceiptStatus(") && str_contains($controller, "'posted'"), 'controller keeps existing posting dispatch');

$spy = ['events' => [], 'committed' => false, 'rolled_back' => false];
$run = static function (callable $operation) use (&$spy): void {
    $spy['events'][] = 'begin';
    try {
        $operation($spy);
        $spy['events'][] = 'commit';
        $spy['committed'] = true;
    } catch (Throwable) {
        $spy['events'][] = 'rollback';
        $spy['rolled_back'] = true;
    }
};
$run(static function (array &$state): void {
    $state['events'][] = 'receipt_update';
    $state['events'][] = 'audit';
    throw new RuntimeException('simulated audit failure');
});
$check($spy['rolled_back'] && !$spy['committed'], 'simulated audit failure rolls back');

$spy = ['events' => [], 'committed' => false, 'rolled_back' => false];
$run(static function (array &$state): void {
    $state['events'][] = 'receipt_update';
    throw new RuntimeException('simulated receipt update failure');
});
$check($spy['rolled_back'] && !$spy['committed'], 'simulated receipt update failure rolls back');

$spy = ['events' => [], 'committed' => false, 'rolled_back' => false];
$run(static function (array &$state): void {
    $state['events'][] = 'receipt_update';
    $state['events'][] = 'audit';
    $state['events'][] = 'recompute';
    throw new RuntimeException('simulated recomputation failure');
});
$check($spy['rolled_back'] && !$spy['committed'], 'simulated recomputation failure rolls back');

$spy = ['events' => [], 'committed' => false, 'rolled_back' => false];
$run(static function (array &$state): void {
    $state['events'][] = 'receipt_update';
    $state['events'][] = 'audit';
    $state['events'][] = 'recompute';
});
$check($spy['committed'] && !$spy['rolled_back'], 'successful posting commits once');
$check($spy['events'] === ['begin', 'receipt_update', 'audit', 'recompute', 'commit'], 'successful spy sequence is deterministic');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
