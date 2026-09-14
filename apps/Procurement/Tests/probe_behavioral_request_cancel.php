<?php
declare(strict_types=1);

/**
 * Procurement request cancellation — behavioral safety (DB-backed).
 * Uses existing DB layer via Base bootstrap (same pattern as Manufacturing probes).
 */
define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/app/Core/DB.php';

use Apps\Procurement\Services\ProcurementOverviewService;
use App\Core\DB;

$passed = 0;
$failed = 0;
$actor = 'test-req-cancel';

$pass = static function (string $l) use (&$passed): void { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed): void { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

DB::query("CREATE TABLE IF NOT EXISTS procurement_requests (id BIGINT AUTO_INCREMENT PRIMARY KEY, request_ref VARCHAR(60), product_id INT, requested_qty DECIMAL(14,2), needed_date DATE, source_app VARCHAR(80), source_ref_type VARCHAR(80), source_ref_id VARCHAR(80), request_status VARCHAR(30) DEFAULT 'draft', note TEXT, created_by VARCHAR(190), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, UNIQUE KEY uniq_proc_request_ref(request_ref), KEY idx_proc_request_status(request_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
DB::query("CREATE TABLE IF NOT EXISTS procurement_purchase_orders (id BIGINT AUTO_INCREMENT PRIMARY KEY, po_ref VARCHAR(60), supplier_id BIGINT, request_id BIGINT, po_status VARCHAR(30) DEFAULT 'draft', order_date DATE, expected_date DATE, created_by VARCHAR(190), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
DB::query("CREATE TABLE IF NOT EXISTS procurement_receipts (id BIGINT AUTO_INCREMENT PRIMARY KEY, receipt_ref VARCHAR(60), po_id BIGINT, po_line_id BIGINT, received_qty DECIMAL(14,2), receipt_date DATE, receipt_status VARCHAR(30), note TEXT, created_by VARCHAR(190), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_proc_receipt_po(po_id), KEY idx_proc_receipt_status(receipt_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helper to create a base supplier.
$supplierId = 0;
$r = DB::query('SELECT id FROM procurement_suppliers WHERE supplier_name = ?', ['BehavioralCancelSup']);
$supRow = DB::fetchOne('SELECT id FROM procurement_suppliers WHERE supplier_name = ?', ['BehavioralCancelSup']);
if (!is_array($supRow) || (int)($supRow['id'] ?? 0) <= 0) {
    DB::query('INSERT INTO procurement_suppliers (supplier_name, supplier_status) VALUES (?,?)', ['BehavioralCancelSup', 'active']);
    $supplierId = (int)(DB::conn()->insert_id ?: 0);
} else {
    $supplierId = (int)$supRow['id'];
}

echo "== Request Cancellation Behavioral Safety ==\n";

$suffix = '-' . substr(md5(random_bytes(2)), 0, 6);
// Case A: Valid cancel of approved request with no PO link.
DB::query('INSERT INTO procurement_requests (request_ref, product_id, requested_qty, request_status, created_by) VALUES (?,?,?,?,?)', ['REQ-CANCEL-A' . $suffix . '-TEST', 1, 5.0, 'approved', $actor]);
$reqA = (int)(DB::conn()->insert_id ?: 0);
try {
    ProcurementOverviewService::setRequestStatus($reqA, 'cancelled', $actor, 'Behavioral cancel A');
    $afterA = DB::fetchOne('SELECT request_status FROM procurement_requests WHERE id = ?', [$reqA]);
    if (is_array($afterA) && strtolower(trim((string)($afterA['request_status'] ?? ''))) === 'cancelled') {
        $pass("Case A: valid cancel succeeds and status = cancelled");
    } else {
        $fail("Case A: request status after cancel", (string)($afterA['request_status'] ?? 'none'));
    }
} catch (\Throwable $e) {
    $fail("Case A: unexpected exception", $e->getMessage());
}

// Case B: Already cancelled -> transition rejected; status preserved.
DB::query('INSERT INTO procurement_requests (request_ref, product_id, requested_qty, request_status, created_by) VALUES (?,?,?,?,?)', ['REQ-CANCEL-B' . $suffix . '-TEST', 1, 5.0, 'cancelled', $actor]);
$reqB = (int)(DB::conn()->insert_id ?: 0);
try {
    ProcurementOverviewService::setRequestStatus($reqB, 'cancelled', $actor, 'Behavioral cancel B');
    $afterB = DB::fetchOne('SELECT request_status FROM procurement_requests WHERE id = ?', [$reqB]);
    if (is_array($afterB) && strtolower(trim((string)($afterB['request_status'] ?? ''))) === 'cancelled') {
        $pass("Case B: already-cancelled preserved (same-state, no mutation)");
    } else {
        $fail("Case B: status changed unexpectedly", (string)($afterB['request_status'] ?? 'none'));
    }
} catch (\Throwable $e) {
    $fail("Case B: unexpected exception", $e->getMessage());
}

// Case C: Request with linked PO -> cancel rejected (downstream protected).
DB::query('INSERT INTO procurement_requests (request_ref, product_id, requested_qty, request_status, created_by) VALUES (?,?,?,?,?)', ['REQ-CANCEL-C' . $suffix . '-TEST', 1, 5.0, 'approved', $actor]);
$reqC = (int)(DB::conn()->insert_id ?: 0);
DB::query('INSERT INTO procurement_purchase_orders (po_ref, supplier_id, request_id, po_status, order_date) VALUES (?,?,?,?,?)', ['PO-CANCEL-C' . $suffix . '-TEST', $supplierId > 0 ? $supplierId : null, $reqC, 'draft', date('Y-m-d')]);
try {
    ProcurementOverviewService::setRequestStatus($reqC, 'cancelled', $actor, 'Behavioral cancel C');
    $fail("Case C: PO-linked cancel did NOT throw");
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'existing purchase order')) {
        $pass("Case C: cancel rejected (downstream PO-linked protected)");
    } else {
        $fail("Case C: unexpected exception message", $e->getMessage());
    }
} catch (\Throwable $e) {
    $fail("Case C: unexpected exception type", get_class($e) . ': ' . $e->getMessage());
}

// Verify request C preserved (not cancelled).
$afterCreq = DB::fetchOne('SELECT request_status FROM procurement_requests WHERE id = ?', [$reqC]);
if (is_array($afterCreq) && strtolower(trim((string)($afterCreq['request_status'] ?? ''))) === 'approved') {
    $pass("Case C: linked request preserved after rejected cancel");
} else {
    $fail("Case C: linked request status changed unexpectedly", (string)($afterCreq['request_status'] ?? 'none'));
}

// Verify linked PO preserved (not mutated by cancel attempt).
$afterCpo = DB::fetchOne('SELECT po_status FROM procurement_purchase_orders WHERE request_id = ?', [$reqC]);
if (is_array($afterCpo) && strtolower(trim((string)($afterCpo['po_status'] ?? ''))) === 'draft') {
    $pass("Case C: linked PO preserved after rejected cancel");
} else {
    $fail("Case C: linked PO changed unexpectedly", (string)($afterCpo['po_status'] ?? 'none'));
}

echo "\nDB-Backed Request Cancellation Safety: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
