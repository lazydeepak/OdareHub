<?php
declare(strict_types=1);

/**
 * OH-AUTO2-130 direct request transition proof.
 *
 * Source checks cover the production call graph and ordering. The transaction
 * spy is an isolated contract model only; it does not invoke Procurement
 * mutation methods, bootstrap the app, connect to a database, execute SQL, or
 * provision schema.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$routesPath = $root . '/apps/Procurement/routes.php';
$linkagePath = $root . '/apps/Procurement/Tests/probe_request_linkage_integrity.php';
$orderPath = $root . '/apps/Procurement/Tests/probe_order_status_transaction.php';
$receiptPath = $root . '/apps/Procurement/Tests/probe_receipt_posting_transaction.php';

$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$linkage = (string)file_get_contents($linkagePath);
$order = (string)file_get_contents($orderPath);
$receipt = (string)file_get_contents($receiptPath);

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

$direct = $methodBody($service, 'setRequestStatusDirect');
$internal = $methodBody($service, 'setRequestStatus');
$within = $privateBody($service, 'setRequestStatusWithinTransaction');
$manualOrder = $methodBody($service, 'createOrder');
$approvedOrder = $methodBody($service, 'createOrderFromApprovedRequest');
$manualEligibility = $methodBody($service, 'assertManualOrderRequestEligible');
$approve = $methodBody($controller, 'approveRequest');
$cancel = $methodBody($controller, 'cancelRequest');

$check($direct !== '', 'direct request transition entry point exists');
$check($internal !== '', 'transaction-compatible internal request update exists');
$check($within !== '', 'shared in-transaction request update exists');
$check(str_contains($approve, 'setRequestStatusDirect($id, \'approved\''), 'approval controller uses direct transition entry point');
$check(str_contains($cancel, 'setRequestStatusDirect($id, \'cancelled\''), 'cancellation controller uses direct transition entry point');
$check(str_contains($manualOrder, 'self::setRequestStatus($requestId, \'closed\''), 'manual PO creation retains internal request closure');
$check(str_contains($approvedOrder, 'self::setRequestStatus($requestId, \'closed\''), 'approved-request conversion retains internal request closure');

$status = strpos($direct, 'self::normalizeRequestStatus($status)');
$auditEnsure = strpos($direct, 'AuditLogService::ensureSchema()');
$begin = strpos($direct, '$db->begin_transaction()');
$internalCall = strpos($direct, 'self::setRequestStatus($id, $status, $actor, $note)');
$commit = strpos($direct, '$db->commit()');
$rollback = strpos($direct, '$db->rollback()');
$check($status !== false && $status < $auditEnsure && $auditEnsure < $begin, 'status validation and audit initialization precede transaction');
$check($begin !== false && $begin < $internalCall, 'transaction begins before internal locked update');
$check($internalCall !== false && $internalCall < $commit, 'request update completes before commit');
$check($commit !== false && $rollback !== false, 'direct request transition has commit and rollback');
$check(substr_count($direct, 'begin_transaction()') === 1, 'direct request transition opens exactly one transaction');
$check(!str_contains($internal, 'begin_transaction()'), 'internal request update does not open a nested transaction');
$check(!str_contains($internal, 'AuditLogService::ensureSchema()'), 'internal request update does not initialize audit DDL');

$lock = strpos($within, 'FOR UPDATE');
$cancelCheck = strpos($within, "if (\$status === 'cancelled')");
$update = strpos($within, 'UPDATE procurement_requests SET request_status');
$audit = strpos($within, 'self::recordAudit(');
$check($lock !== false && $lock < $cancelCheck, 'request lock precedes cancellation linkage check');
$check($cancelCheck !== false && $cancelCheck < $update, 'cancellation check precedes request update');
$check($update !== false && $audit !== false && $update < $audit, 'request update precedes same-transaction audit');
$check(str_contains($within, 'Cannot cancel procurement request with an existing purchase order.'), 'linked-PO cancellation prohibition preserved');
$check(str_contains($service, "['draft', 'approved', 'closed', 'cancelled']"), 'existing request status vocabulary preserved');
$check(str_contains($service, 'Request not found.'), 'missing request remains rejected');
$check(str_contains($service, 'Unsupported request status transition.'), 'unsupported request status remains rejected');
$check(str_contains($within, 'if ($oldState !== $status)'), 'same-status audit behavior remains conditional');
$check(str_contains($within, 'AuditLogService::ACTION_APPROVED'), 'approval audit vocabulary remains preserved');
$check(str_contains($within, 'AuditLogService::ACTION_UPDATED'), 'non-approval audit vocabulary remains preserved');

$manualLock = strpos($manualEligibility, 'FOR UPDATE');
$manualClosure = strpos($manualOrder, 'self::setRequestStatus($requestId, \'closed\'');
$approvedLock = strpos($approvedOrder, 'FOR UPDATE');
$approvedClosure = strpos($approvedOrder, 'self::setRequestStatus($requestId, \'closed\'');
$check($manualLock !== false && $manualClosure !== false && $manualLock < $manualClosure, 'manual PO conversion locks request before closure');
$check($approvedLock !== false && $approvedClosure !== false && $approvedLock < $approvedClosure, 'approved-request conversion locks request before closure');
$check(str_contains($manualOrder, 'self::assertManualOrderRequestEligible($requestId)'), 'manual request-link eligibility remains intact');
$check(str_contains($approvedOrder, 'Only approved requests can be converted to PO.'), 'approved-request lifecycle policy remains intact');
$check(str_contains($approve, 'Auth::requireAppAccess(\'procurement\')') && str_contains($approve, 'Auth::requireCsrf('), 'approval authorization and CSRF remain intact');
$check(str_contains($cancel, 'Auth::requireAppAccess(\'procurement\')') && str_contains($cancel, 'Auth::requireCsrf('), 'cancellation authorization and CSRF remain intact');
$check(str_contains($routes, '/apps/procurement/requests/approve') && str_contains($routes, '/apps/procurement/requests/cancel'), 'existing request routes remain unchanged');
$check(is_file($root . '/apps/Procurement/Tests/probe_request_linkage_integrity.php'), 'request-linkage regression remains available');
$check(is_file($root . '/apps/Procurement/Tests/probe_order_status_transaction.php'), 'direct PO transition regression remains available');
$check(str_contains($receipt, 'self::recordAudit('), 'receipt posting regression remains available');

final class RequestStatusTransactionSpy
{
    /** @var array<int,string> */
    public array $events = [];

    public function begin(): void { $this->events[] = 'begin'; }
    public function lockRequest(): void { $this->events[] = 'lock-request'; }
    public function validate(): void { $this->events[] = 'validate'; }
    public function update(): void { $this->events[] = 'update'; }
    public function audit(): void { $this->events[] = 'audit'; }
    public function commit(): void { $this->events[] = 'commit'; }
    public function rollback(): void { $this->events[] = 'rollback'; }
}

$runContract = static function (bool $failAfterUpdate): RequestStatusTransactionSpy {
    $spy = new RequestStatusTransactionSpy();
    $spy->begin();
    try {
        $spy->lockRequest();
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
$check($success->events === ['begin', 'lock-request', 'validate', 'update', 'audit', 'commit'], 'successful request transition commits once after audit');
$check(!in_array('rollback', $success->events, true), 'successful request transition does not roll back');
$failure = $runContract(true);
$check($failure->events === ['begin', 'lock-request', 'validate', 'update', 'rollback'], 'audit failure fixture reaches rollback');
$check(!in_array('commit', $failure->events, true), 'failed request transition does not commit');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
