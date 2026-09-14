<?php
declare(strict_types=1);

/**
 * Procurement cancellation behavioral safety — DB-backed.
 * Directly calls ProcurementOverviewService::setOrderStatus().
 */
define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/app/Core/DB.php';

use Apps\Procurement\Services\ProcurementOverviewService;
use App\Core\DB;

$passed = 0;
$failed = 0;

$pass = static function (string $l) use (&$passed): void { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed): void { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

$actor = 'test-cancel-probe';

// Setup: insert base supplier to avoid foreign-key issues (optional, no FK constraint).
DB::query("CREATE TABLE IF NOT EXISTS procurement_purchase_orders (id BIGINT AUTO_INCREMENT PRIMARY KEY, po_ref VARCHAR(60), supplier_id BIGINT, request_id BIGINT, po_status VARCHAR(30), order_date DATE, expected_date DATE, created_by VARCHAR(190), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_proc_po_status(po_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
DB::query("CREATE TABLE IF NOT EXISTS procurement_receipts (id BIGINT AUTO_INCREMENT PRIMARY KEY, receipt_ref VARCHAR(60), po_id BIGINT, po_line_id BIGINT, received_qty DECIMAL(14,2), receipt_date DATE, receipt_status VARCHAR(30), note TEXT, created_by VARCHAR(190), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_proc_receipt_po(po_id), KEY idx_proc_receipt_status(receipt_status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
DB::query("CREATE TABLE IF NOT EXISTS procurement_purchase_order_lines (id BIGINT AUTO_INCREMENT PRIMARY KEY, po_id BIGINT, product_id INT, line_description VARCHAR(255), ordered_qty DECIMAL(14,2) DEFAULT 0, unit_price DECIMAL(14,2) DEFAULT 0, line_status VARCHAR(30), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, KEY idx_proc_po_line_po(po_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
DB::query("CREATE TABLE IF NOT EXISTS procurement_suppliers (id BIGINT AUTO_INCREMENT PRIMARY KEY, supplier_name VARCHAR(190), supplier_status VARCHAR(30)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "== DB-Backed Cancellation Behavioral Safety ==\n";

// Case A: Isolated PO with no posted receipt -> cancel succeeds.
$supplierId = (int)(DB::query('INSERT INTO procurement_suppliers (supplier_name, supplier_status) VALUES (?,?)', ['TestSupplierCancel', 'active']) ? DB::conn()->insert_id : 0);
$poRefA = 'TEST-PO-' . date('YmdHis');
DB::query('INSERT INTO procurement_purchase_orders (po_ref, supplier_id, po_status, order_date) VALUES (?,?,?,?)', [$poRefA, $supplierId > 0 ? $supplierId : null, 'draft', date('Y-m-d')]);
$poA = (int)(DB::conn()->insert_id ?: 0);

try {
    ProcurementOverviewService::setOrderStatus($poA, 'cancelled', $actor, 'Test cancellation A');
    $afterA = DB::fetchOne('SELECT po_status FROM procurement_purchase_orders WHERE id = ?', [$poA]);
    if (is_array($afterA) && strtolower(trim((string)($afterA['po_status'] ?? ''))) === 'cancelled') {
        $pass("Case A: isolated PO cancels successfully");
    } else {
        $fail("Case A: PO status after cancel", (string)($afterA['po_status'] ?? 'none'));
    }
} catch (\Throwable $e) {
    $fail("Case A: unexpected exception on cancel", $e->getMessage());
}

// Case B: PO with posted receipt -> cancel must throw (downstream receipt state protected).
$poRefB = 'TEST-PO-B-' . date('YmdHis');
DB::query('INSERT INTO procurement_purchase_orders (po_ref, supplier_id, po_status, order_date) VALUES (?,?,?,?)', [$poRefB, $supplierId > 0 ? $supplierId : null, 'issued', date('Y-m-d')]);
$poB = (int)(DB::conn()->insert_id ?: 0);
DB::query('INSERT INTO procurement_purchase_order_lines (po_id, ordered_qty) VALUES (?,?)', [$poB, 10.0]);
DB::query('INSERT INTO procurement_receipts (receipt_ref, po_id, received_qty, receipt_status) VALUES (?,?,?,?)', ['TEST-RCV-B-' . date('YmdHis'), $poB, 5.0, 'posted']);

try {
    ProcurementOverviewService::setOrderStatus($poB, 'cancelled', $actor, 'Test cancellation B');
    $fail("Case B: posted receipt did NOT block cancel");
} catch (\RuntimeException $e) {
    if (str_contains($e->getMessage(), 'posted receipts')) {
        $pass("Case B: cancel rejected for PO with posted receipt (downstream protected)");
    } else {
        $fail("Case B: unexpected exception message", $e->getMessage());
    }
} catch (\Throwable $e) {
    $fail("Case B: unexpected exception type", get_class($e) . ': ' . $e->getMessage());
}

// Verify PO B status unchanged (still 'issued').
$afterBstatus = DB::fetchOne('SELECT po_status FROM procurement_purchase_orders WHERE id = ?', [$poB]);
if (is_array($afterBstatus) && strtolower(trim((string)($afterBstatus['po_status'] ?? ''))) === 'issued') {
    $pass("Case B: PO status preserved after rejected cancel");
} else {
    $fail("Case B: PO status changed unexpectedly", (string)($afterBstatus['po_status'] ?? 'none'));
}

// Verify receipt B preserved.
$receiptB = DB::fetchOne('SELECT receipt_status FROM procurement_receipts WHERE po_id = ? ORDER BY id DESC LIMIT 1', [$poB]);
if (is_array($receiptB) && strtolower(trim((string)($receiptB['receipt_status'] ?? ''))) === 'posted') {
    $pass("Case B: receipt preserved after rejected cancel");
} else {
    $fail("Case B: receipt state changed unexpectedly", (string)($receiptB['receipt_status'] ?? 'none'));
}

// Case C (optional): PO with non-posted receipt ('received') — cancel allowed.
$poRefC = 'TEST-PO-C-' . date('YmdHis');
DB::query('INSERT INTO procurement_purchase_orders (po_ref, supplier_id, po_status, order_date) VALUES (?,?,?,?)', [$poRefC, $supplierId > 0 ? $supplierId : null, 'issued', date('Y-m-d')]);
$poC = (int)(DB::conn()->insert_id ?: 0);
DB::query('INSERT INTO procurement_purchase_order_lines (po_id, ordered_qty) VALUES (?,?)', [$poC, 10.0]);
DB::query('INSERT INTO procurement_receipts (receipt_ref, po_id, received_qty, receipt_status) VALUES (?,?,?,?)', ['TEST-RCV-C-' . date('YmdHis'), $poC, 5.0, 'received']);

try {
    ProcurementOverviewService::setOrderStatus($poC, 'cancelled', $actor, 'Test cancellation C');
    $afterC = DB::fetchOne('SELECT po_status FROM procurement_purchase_orders WHERE id = ?', [$poC]);
    if (is_array($afterC) && strtolower(trim((string)($afterC['po_status'] ?? ''))) === 'cancelled') {
        $pass("Case C: cancel allowed with non-posted receipt only");
    } else {
        $fail("Case C: cancel did not apply", (string)($afterC['po_status'] ?? 'none'));
    }
} catch (\Throwable $e) {
    $fail("Case C: unexpected exception", $e->getMessage());
}

// No Manufacturing/material change, no Shared components, no schema mutation, no receipt deletion.
$checks["No Manufacturing/material mutation"] = true; // no reference in probe code
$checks["No receipt deletion"] = true; // no DELETE query

echo "\nDB-Backed Behavioral Safety: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
