<?php
declare(strict_types=1);

/**
 * OH-AUTO2-133 isolated request-creation transaction proof.
 *
 * The production service is not invoked. Source/call-order assertions verify
 * the boundary, while a local transaction spy exercises success, insert
 * failure, thrown audit failure, and non-OK audit-result rollback semantics.
 * No application bootstrap, database connection, SQL execution, or schema
 * provisioning occurs.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$routesPath = $root . '/apps/Procurement/routes.php';
$viewPath = $root . '/apps/Procurement/Views/requests.php';
$statusProofPath = $root . '/apps/Procurement/Tests/probe_request_creation_status.php';
$requestTransitionProofPath = $root . '/apps/Procurement/Tests/probe_request_status_transaction.php';
$linkageProofPath = $root . '/apps/Procurement/Tests/probe_request_linkage_integrity.php';

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

final class RequestCreationTransactionSpy
{
    public int $beginCount = 0;
    public int $commitCount = 0;
    public int $rollbackCount = 0;
    public bool $active = false;

    public function begin(): void
    {
        $this->beginCount++;
        $this->active = true;
    }

    public function commit(): void
    {
        if (!$this->active) {
            throw new RuntimeException('commit without transaction');
        }
        $this->commitCount++;
        $this->active = false;
    }

    public function rollback(): void
    {
        if (!$this->active) {
            throw new RuntimeException('rollback without transaction');
        }
        $this->rollbackCount++;
        $this->active = false;
    }
}

/** @return array{committed:bool,rolled_back:bool,events:list<string>} */
$simulate = static function (callable $insert, callable $audit): array {
    $tx = new RequestCreationTransactionSpy();
    $events = [];
    $tx->begin();
    try {
        $insert($events);
        $auditResult = $audit($events);
        if (($auditResult['ok'] ?? false) !== true) {
            throw new RuntimeException((string)($auditResult['message'] ?? 'audit failed'));
        }
        $tx->commit();
    } catch (Throwable $e) {
        $events[] = 'failure:' . $e->getMessage();
        $tx->rollback();
    }

    return [
        'committed' => $tx->commitCount === 1,
        'rolled_back' => $tx->rollbackCount === 1,
        'events' => $events,
    ];
};

$success = $simulate(
    static function (array &$events): void { $events[] = 'insert'; },
    static function (array &$events): array { $events[] = 'audit'; return ['ok' => true]; }
);
$check($success['committed'] && !$success['rolled_back'], 'successful insert and audit commit once');
$check($success['events'] === ['insert', 'audit'], 'successful operation orders insert before audit');

$insertFailure = $simulate(
    static function (array &$events): void { $events[] = 'insert'; throw new RuntimeException('insert failed'); },
    static function (array &$events): array { $events[] = 'audit'; return ['ok' => true]; }
);
$check(!$insertFailure['committed'] && $insertFailure['rolled_back'], 'simulated insertion failure rolls back without commit');
$check($insertFailure['events'] === ['insert', 'failure:insert failed'], 'insertion failure prevents audit and success commit');

$auditThrow = $simulate(
    static function (array &$events): void { $events[] = 'insert'; },
    static function (array &$events): array { $events[] = 'audit'; throw new RuntimeException('audit threw'); }
);
$check(!$auditThrow['committed'] && $auditThrow['rolled_back'], 'simulated thrown audit failure rolls back without commit');
$check($auditThrow['events'] === ['insert', 'audit', 'failure:audit threw'], 'thrown audit failure occurs after insert and prevents commit');

$auditFalse = $simulate(
    static function (array &$events): void { $events[] = 'insert'; },
    static function (array &$events): array { $events[] = 'audit'; return ['ok' => false, 'message' => 'audit rejected']; }
);
$check(!$auditFalse['committed'] && $auditFalse['rolled_back'], 'non-OK audit result rolls back without commit');
$check($auditFalse['events'] === ['insert', 'audit', 'failure:audit rejected'], 'non-OK audit result prevents success commit');

$source = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$view = (string)file_get_contents($viewPath);
$auditCore = (string)file_get_contents($root . '/app/Core/AuditLogService.php');
$createStart = strpos($source, 'public static function createRequest(array $input, string $actor): void');
$createEnd = strpos($source, 'public static function intakeFromManufacturingDemand(', (int)$createStart);
$create = $createStart !== false && $createEnd !== false ? substr($source, $createStart, $createEnd - $createStart) : '';
$intakeStart = strpos($source, 'public static function intakeFromManufacturingDemand(');
$intakeEnd = strpos($source, 'public static function createOrder(array $input, string $actor): void', (int)$intakeStart);
$intake = $intakeStart !== false && $intakeEnd !== false ? substr($source, $intakeStart, $intakeEnd - $intakeStart) : '';

$status = strpos($create, 'self::normalizeRequestCreationStatus(');
$qty = strpos($create, 'Requested quantity must be greater than zero.');
$ref = strpos($create, "self::nextRef('PR')");
$ensure = strpos($create, 'AuditLogService::ensureSchema()');
$begin = strpos($create, '$db->begin_transaction()');
$insert = strpos($create, 'INSERT INTO procurement_requests');
$identity = strpos($create, '$requestId = (int)($db->insert_id ?: 0)');
$audit = strpos($create, '$audit = self::recordAudit(');
$auditCheck = strpos($create, '($audit[\'ok\'] ?? false) !== true');
$commit = strpos($create, '$db->commit()');
$rollback = strpos($create, '$db->rollback()');

$check($create !== '', 'createRequest method is available');
$check($status !== false && $qty !== false && $status < $qty, 'status normalization precedes quantity validation');
$check($status !== false && $ref !== false && $status < $ref, 'status normalization precedes reference generation');
$check($ensure !== false && $begin !== false && $ensure < $begin, 'audit initialization precedes transaction start');
$check($begin !== false && $insert !== false && $begin < $insert, 'request insertion occurs inside the transaction');
$check($insert !== false && $identity !== false && $insert < $identity, 'inserted request identity is retrieved after insertion');
$check($identity !== false && $audit !== false && $identity < $audit, 'creation audit follows inserted identity retrieval');
$check($audit !== false && $auditCheck !== false && $audit < $auditCheck, 'audit result is checked for non-OK failure');
$check($auditCheck !== false && $commit !== false && $auditCheck < $commit, 'success commit follows required audit result');
$check($commit !== false && $rollback !== false && $commit < $rollback, 'commit and rollback paths remain in one try/catch boundary');
$check(substr_count($create, '$db->begin_transaction()') === 1 && substr_count($create, '$db->commit()') === 1 && substr_count($create, '$db->rollback()') === 1, 'request creation owns exactly one transaction boundary');
$check(str_contains($create, '$requestStatus,'), 'canonical request status remains the insert value');
$check(str_contains($create, "'new_state' => \$requestStatus"), 'canonical request status remains the audit state');
$check(str_contains($create, 'source_app') && str_contains($create, 'source_ref_type') && str_contains($create, 'source_ref_id'), 'request provenance fields remain unchanged');
$check(str_contains($create, 'requested_qty') && str_contains($create, 'needed_date') && str_contains($create, 'note'), 'request optional and quantity fields remain unchanged');

$check(str_contains($auditCore, 'public static function ensureSchema(): void'), 'Core audit schema initializer exists');
$logStart = strpos($auditCore, 'public static function logEvent(');
$logEnd = strpos($auditCore, 'public static function diffImportantFields(', (int)$logStart);
$log = $logStart !== false && $logEnd !== false ? substr($auditCore, $logStart, $logEnd - $logStart) : '';
$check(strpos($log, 'self::ensureSchema()') !== false, 'audit logging re-enters schema initialization');
$check(str_contains($log, "'ok' => false") && str_contains($log, "'ok' => true"), 'audit API exposes non-throwing success/failure results');

$check(str_contains($controller, "Auth::requireAppAccess('procurement')") && str_contains($controller, 'Auth::requireCsrf('), 'controller authorization and CSRF remain intact');
$check(str_contains($controller, 'ProcurementOverviewService::createRequest($_POST'), 'manual request controller dispatch remains unchanged');
$check(str_contains($routes, '/apps/procurement/requests/create'), 'request-create POST route remains registered');
$check(str_contains($view, 'name="request_status" value="draft"'), 'manual request form still submits draft');
$check(str_contains($intake, "'draft',") && str_contains($intake, "'new_state' => 'draft'"), 'Manufacturing-demand intake remains explicit draft');
$check(!str_contains($intake, '$db->begin_transaction()'), 'Manufacturing-demand intake path is not modified with this transaction boundary');
$check(is_file($statusProofPath), 'request status regression remains available');
$check(is_file($requestTransitionProofPath), 'direct request transition regression remains available');
$check(is_file($linkageProofPath), 'request linkage regression remains available');

echo "RESULT passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);
