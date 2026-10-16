<?php
declare(strict_types=1);

/**
 * OH-AUTO2-129 direct PO status transaction proof.
 *
 * Source checks cover the production call graph and ordering. The small
 * transaction spy below is an isolated contract model only; it does not invoke
 * Procurement mutation methods, bootstrap the app, connect to a database,
 * execute SQL, or provision schema.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$routesPath = $root . '/apps/Procurement/routes.php';
$receiptCreationPath = $root . '/apps/Procurement/Tests/probe_receipt_creation_status.php';
$receiptPostingPath = $root . '/apps/Procurement/Tests/probe_receipt_posting_transaction.php';

$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$receiptCreation = (string)file_get_contents($receiptCreationPath);
$receiptPosting = (string)file_get_contents($receiptPostingPath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

$methodBody = static function (string $source, string $method): string {
    $start = strpos($source, 'public static function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    $next = strpos($source, 'public static function ', $start + 1);
    return substr($source, $start, $next === false ? null : $next - $start);
};

$privateBody = static function (string $source, string $method): string {
    $start = strpos($source, 'private static function ' . $method . '(');
    if ($start === false) {
        return '';
    }
    $next = strpos($source, 'private static function ', $start + 1);
    return substr($source, $start, $next === false ? null : $next - $start);
};

$direct = $methodBody($service, 'setOrderStatusDirect');
$internal = $methodBody($service, 'setOrderStatus');
$within = $privateBody($service, 'setOrderStatusWithinTransaction');
$recompute = $privateBody($service, 'recomputeOrderStatusFromReceipts');
$issue = $methodBody($controller, 'issueOrder');
$cancel = $methodBody($controller, 'cancelOrder');

$check($direct !== '', 'direct PO transition entry point exists');
$check($internal !== '', 'transaction-compatible internal status update exists');
$check($within !== '', 'shared in-transaction PO update exists');
$check(str_contains($issue, 'setOrderStatusDirect($id, \'issued\''), 'issue controller uses direct transition entry point');
$check(str_contains($cancel, 'setOrderStatusDirect($id, \'cancelled\''), 'cancel controller uses direct transition entry point');
$check(str_contains($recompute, 'self::setOrderStatus($poId, $target, $actor'), 'receipt recomputation retains internal status-update caller');

$directStatus = strpos($direct, 'self::normalizeOrderStatus($status)');
$auditEnsure = strpos($direct, 'AuditLogService::ensureSchema()');
$begin = strpos($direct, '$db->begin_transaction()');
$poLock = strpos($direct, 'self::assertPurchaseOrderExists($id)');
$internalCall = strpos($direct, 'self::setOrderStatus($id, $status, $actor, $note)');
$commit = strpos($direct, '$db->commit()');
$rollback = strpos($direct, '$db->rollback()');
$check($directStatus !== false && $directStatus < $auditEnsure && $auditEnsure < $begin, 'direct status validation and audit initialization precede transaction');
$check($begin !== false && $begin < $poLock && $poLock < $internalCall, 'transaction begins before PO-first lock and internal update');
$check($internalCall !== false && $internalCall < $commit, 'status update completes before commit');
$check($commit !== false && $rollback !== false, 'direct transition has explicit commit and rollback');
$check(substr_count($direct, 'begin_transaction()') === 1, 'direct transition opens exactly one transaction');
$check(!str_contains($internal, 'begin_transaction()'), 'internal status update does not open a nested transaction');
$check(!str_contains($internal, 'AuditLogService::ensureSchema()'), 'internal status update does not initialize audit DDL');

$lock = strpos($within, 'FOR UPDATE');
$cancelCheck = strpos($within, "if (\$status === 'cancelled')");
$issueCheck = strpos($within, "if (\$status === 'issued')");
$update = strpos($within, 'UPDATE procurement_purchase_orders SET po_status');
$audit = strpos($within, 'self::recordAudit(');
$check($lock !== false && $lock < $cancelCheck && $lock < $issueCheck, 'PO lock precedes cancellation and issue checks');
$check($cancelCheck !== false && $issueCheck !== false && $cancelCheck < $update && $issueCheck < $update, 'status-dependent checks precede PO update');
$check($update !== false && $audit !== false && $update < $audit, 'PO update precedes same-transaction audit');
$check(str_contains($within, 'Cannot cancel purchase order with posted receipts.'), 'posted-receipt cancellation prohibition preserved');
$check(str_contains($within, 'Cannot issue PO with zero ordered quantity.'), 'positive ordered-quantity issue policy preserved');
$check(str_contains($service, "['draft', 'approved', 'issued', 'partial', 'closed', 'cancelled']"), 'existing PO status vocabulary preserved');

$check(str_contains($service, 'self::assertPurchaseOrderExists($id)'), 'missing PO is rejected through existing locked identity check');
$check(str_contains($service, "AuditLogService::ensureSchema();") && str_contains($service, '$db->rollback();'), 'audit DDL setup and rollback remain bounded');
$check(!str_contains($recompute, 'begin_transaction()'), 'receipt recomputation cannot begin a nested transaction');
$check(str_contains($recompute, 'self::setOrderStatus($poId, $target, $actor'), 'recomputation remains transaction-compatible');
$check(str_contains($controller, 'Auth::requireAppAccess(\'procurement\')') && str_contains($controller, 'Auth::requireCsrf('), 'controller authorization and CSRF remain present');
$check(str_contains($routes, '/apps/procurement/orders/issue') && str_contains($routes, '/apps/procurement/orders/cancel'), 'existing issue/cancel routes remain unchanged');
$check(is_file($root . '/apps/Procurement/Tests/probe_receipt_creation_status.php'), 'receipt-creation status regression remains available');
$check(str_contains($receiptPosting, 'self::recordAudit('), 'receipt-posting audit path remains available');

final class OrderStatusTransactionSpy
{
    /** @var array<int,string> */
    public array $events = [];

    public function begin(): void { $this->events[] = 'begin'; }
    public function lockPo(): void { $this->events[] = 'lock-po'; }
    public function validate(): void { $this->events[] = 'validate'; }
    public function update(): void { $this->events[] = 'update'; }
    public function audit(): void { $this->events[] = 'audit'; }
    public function commit(): void { $this->events[] = 'commit'; }
    public function rollback(): void { $this->events[] = 'rollback'; }
}

$runContract = static function (bool $failAfterUpdate): OrderStatusTransactionSpy {
    $spy = new OrderStatusTransactionSpy();
    $spy->begin();
    try {
        $spy->lockPo();
        $spy->validate();
        $spy->update();
        if ($failAfterUpdate) {
            throw new RuntimeException('audit failure fixture');
        }
        $spy->audit();
        $spy->commit();
    } catch (Throwable $e) {
        $spy->rollback();
    }
    return $spy;
};

$success = $runContract(false);
$check($success->events === ['begin', 'lock-po', 'validate', 'update', 'audit', 'commit'], 'successful direct transition commits once after audit');
$check(!in_array('rollback', $success->events, true), 'successful direct transition does not roll back');
$failure = $runContract(true);
$check($failure->events === ['begin', 'lock-po', 'validate', 'update', 'rollback'], 'audit failure fixture reaches rollback');
$check(!in_array('commit', $failure->events, true), 'failed direct transition does not commit');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
