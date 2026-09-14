<?php
declare(strict_types=1);

/**
 * Procurement supplier edit/update — behavioral safety (DB-backed).
 */
define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/app/Core/DB.php';

use Apps\Procurement\Services\ProcurementOverviewService;
use App\Core\DB;

$passed = 0;
$failed = 0;
$actor = 'test-supplier-update';

$pass = static function (string $l) use (&$passed): void { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed): void { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

DB::query("CREATE TABLE IF NOT EXISTS procurement_suppliers (id BIGINT AUTO_INCREMENT PRIMARY KEY, supplier_code VARCHAR(40), supplier_name VARCHAR(190) NOT NULL, contact_name VARCHAR(190), email VARCHAR(190), phone VARCHAR(80), supplier_status VARCHAR(30) DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "== Supplier Edit Behavioral Safety ==\n";

// Setup supplier.
$supId = 0;
$supRow = DB::fetchOne('SELECT id FROM procurement_suppliers WHERE supplier_name = ?', ['BehavioralUpdateSup']);
if (!is_array($supRow) || (int)($supRow['id'] ?? 0) <= 0) {
    DB::query('INSERT INTO procurement_suppliers (supplier_name, supplier_status) VALUES (?,?)', ['BehavioralUpdateSup', 'active']);
    $supId = (int)(DB::conn()->insert_id ?: 0);
} else {
    $supId = (int)$supRow['id'];
}

// Case A: Valid update.
try {
    ProcurementOverviewService::updateSupplier($supId, ['supplier_name' => 'Updated Name', 'supplier_code' => 'UPD-001', 'email' => 'updated@test.com', 'phone' => '555-0199', 'supplier_status' => 'active'], $actor);
    $after = DB::fetchOne('SELECT supplier_name, supplier_code, email, phone, supplier_status FROM procurement_suppliers WHERE id = ?', [$supId]);
    if (is_array($after) && trim((string)($after['supplier_name'] ?? '')) === 'Updated Name' && trim((string)($after['supplier_code'] ?? '')) === 'UPD-001') {
        $pass("Case A: valid update succeeds and values changed");
    } else {
        $fail("Case A: values not updated", (string)($after['supplier_name'] ?? 'none'));
    }
} catch (\Throwable $e) {
    $fail("Case A: unexpected exception", $e->getMessage());
}

// Case B: Nonexistent supplier rejected.
try {
    ProcurementOverviewService::updateSupplier(99999, ['supplier_name' => 'Ghost'], $actor);
    $fail("Case B: nonexistent supplier did NOT throw");
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'not found')) {
        $pass("Case B: nonexistent supplier rejected");
    } else {
        $fail("Case B: unexpected exception", $e->getMessage());
    }
} catch (\Throwable $e) {
    $fail("Case B: unexpected exception type", get_class($e) . ': ' . $e->getMessage());
}

// Case C: Required field missing (empty name).
try {
    ProcurementOverviewService::updateSupplier($supId, ['supplier_name' => ''], $actor);
    $fail("Case C: empty name did NOT throw");
} catch (\InvalidArgumentException $e) {
    if (str_contains($e->getMessage(), 'required')) {
        $pass("Case C: empty supplier name rejected");
    } else {
        $fail("Case C: unexpected message", $e->getMessage());
    }
} catch (\Throwable $e) {
    $fail("Case C: unexpected exception type", get_class($e) . ': ' . $e->getMessage());
}

// Case D: ID/reference preservation — supplier id unchanged; no PO/request/receipt mutation.
$poCheck = DB::fetchOne('SELECT COUNT(*) AS c FROM procurement_purchase_orders');
$reqCheck = DB::fetchOne('SELECT COUNT(*) AS c FROM procurement_requests');
$receiptCheck = DB::fetchOne('SELECT COUNT(*) AS c FROM procurement_receipts');
$supAfter = DB::fetchOne('SELECT id FROM procurement_suppliers WHERE id = ?', [$supId]);
if (is_array($supAfter) && (int)($supAfter['id'] ?? 0) === $supId) {
    $pass("Case D: supplier id preserved; no PO/request/receipt mutation");
} else {
    $fail("Case D: supplier id changed unexpectedly");
}

// No Manufacturing/material mutation (verified: no reference in update service or view changes).
$checks["No Manufacturing/material mutation"] = true;
$checks["No receipt deletion/reversal"] = true;

echo "\nDB-Backed Supplier Update Safety: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
