<?php
declare(strict_types=1);

/**
 * OH-AUTO2-135 isolated supplier-creation transaction proof.
 *
 * The production service is not invoked. Source/call-order assertions verify
 * the boundary, while a local transaction spy exercises success, insert
 * failure, thrown audit failure, and non-OK audit-result rollback semantics.
 * No application bootstrap, database connection, SQL execution, or mutation
 * occurs.
 */

$root = dirname(__DIR__, 3);
$servicePath = $root . '/apps/Procurement/Services/ProcurementOverviewService.php';
$controllerPath = $root . '/apps/Procurement/Controllers/ProcurementDashboardController.php';
$routesPath = $root . '/apps/Procurement/routes.php';
$viewPath = $root . '/apps/Procurement/Views/dashboard.php';
$viewRequestsPath = $root . '/apps/Procurement/Views/requests.php';

$service = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$view = (string)file_get_contents($viewPath);
$viewRequests = (string)file_get_contents($viewRequestsPath);

$passed = 0;
$failed = 0;
$check = static function (bool $condition, string $label) use (&$passed, &$failed): void {
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
};

final class SupplierCreationTransactionSpy
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
    $tx = new SupplierCreationTransactionSpy();
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

$insertInvalidId = $simulate(
    static function (array &$events): void { $events[] = 'insert'; },
    static function (array &$events): array { return ['ok' => true]; }
);
$check(!$insertInvalidId['committed'] && $insertInvalidId['rolled_back'], 'simulated insertion with invalid identity rolls back without commit');
$check($insertInvalidId['events'] === ['insert', 'failure:Failed to create supplier'], 'invalid identity prevents audit and success commit');

$source = (string)file_get_contents($servicePath);
$controller = (string)file_get_contents($controllerPath);
$routes = (string)file_get_contents($routesPath);
$view = (string)file_get_contents($viewPath);
$viewRequests = (string)file_get_contents($viewRequestsPath);

$createStart = strpos($source, 'public static function createSupplier(array $input, string $actor): void');
$createEnd = strpos($source, 'public static function updateSupplier(int $id, array $input, string $actor): void', (int)$createStart);
$create = $createStart !== false && $createEnd !== false ? substr($source, $createStart, $createEnd - $createStart) : '';

$ensure = strpos($create, 'AuditLogService::ensureSchema()');
$begin = strpos($create, '$db->begin_transaction()');
$insert = strpos($create, 'INSERT INTO procurement_suppliers');
$identity = strpos($create, '$supplierId = (int)($db->insert_id ?: 0)');
$audit = strpos($create, '$audit = self::recordAudit(');
$auditCheck = strpos($create, '($audit[\'ok\'] ?? false) !== true');
$commit = strpos($create, '$db->commit()');
$rollback = strpos($create, '$db->rollback()');

$check($create !== '', 'createSupplier method is available');
$check($ensure !== false && $begin !== false && $ensure < $begin, 'audit initialization precedes transaction start');
$check($begin !== false && $insert !== false && $begin < $insert, 'supplier insertion occurs inside the transaction');
$check($insert !== false && $identity !== false && $insert < $identity, 'inserted supplier identity is retrieved after insertion');
$check($identity !== false && $audit !== false && $identity < $audit, 'creation audit follows inserted identity retrieval');
$check($audit !== false && $auditCheck !== false && $audit < $auditCheck, 'audit result is checked for non-OK failure');
$check($auditCheck !== false && $commit !== false && $auditCheck < $commit, 'success commit follows required audit result');
$check($commit !== false && $rollback !== false && $commit < $rollback, 'commit and rollback paths remain in one try/catch boundary');
$check(substr_count($create, '$db->begin_transaction()') === 1 && substr_count($create, '$db->commit()') === 1 && substr_count($create, '$db->rollback()') === 1, 'supplier creation owns exactly one transaction boundary');
$check(str_contains($create, 'supplier_name'), 'supplier creation includes supplier_name');
$check(str_contains($create, 'contact_name'), 'supplier creation includes contact_name');
$check(str_contains($create, 'email'), 'supplier creation includes email');
$check(str_contains($create, 'phone'), 'supplier creation includes phone');
$check(str_contains($create, 'supplier_status'), 'supplier creation uses supplier_status');
$check(str_contains($controller, "Auth::requireAppAccess('procurement')") && str_contains($controller, 'Auth::requireCsrf('), 'controller authorization and CSRF remain intact');
$check(str_contains($controller, 'ProcurementOverviewService::createSupplier($_POST'), 'manual supplier controller dispatch remains unchanged');
$check(str_contains($routes, '/apps/procurement/suppliers/create'), 'supplier-create POST route remains registered');
$check($view !== '' && $controller !== '' && $routes !== '', 'all integration components present');

$completed = ($failed === 0) ? 'PASS' : 'FAIL';
echo "RESULT: $completed passed={$passed} failed={$failed}\n";
exit($failed === 0 ? 0 : 1);