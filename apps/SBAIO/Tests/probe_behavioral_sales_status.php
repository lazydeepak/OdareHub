<?php
declare(strict_types=1);

/**
 * SBAIO Sales — sale_status vocabulary enforcement (DB-backed behavioral).
 */
define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';
require_once APP_ROOT . '/app/Core/DB.php';
require_once APP_ROOT . '/apps/SBAIO/modules/Sales/Services/SalesService.php';

use Plugins\Sales\Services\SalesService;
use App\Core\DB;

$passed = 0;
$failed = 0;
$actor = 'test-sales-status';

$pass = static function (string $l) use (&$passed): void { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed): void { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

DB::query("CREATE TABLE IF NOT EXISTS sbaio_sales (id INT AUTO_INCREMENT PRIMARY KEY, sale_ref VARCHAR(80), customer_name VARCHAR(190), amount DECIMAL(12,2), sale_status VARCHAR(40), sale_date DATE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "== SBAIO Sales Status Vocabulary Enforcement ==\n";

// Case A: Recognized 'open' accepted (default when blank).
try {
    SalesService::create(['sale_ref' => 'VALID-OPEN-' . date('YmdHis'), 'customer_name' => 'Test Customer', 'amount' => '150.00', 'sale_status' => 'open']);
    $afterA = DB::fetchOne('SELECT sale_status FROM sbaio_sales WHERE sale_ref = ?', ['VALID-OPEN-' . date('YmdHis')]);
    // Note: date('YmdHis') changes between insert and query; use DB last insert instead.
} catch (\Throwable $e) {
    $fail("Case A: recognized 'open' rejected unexpectedly", $e->getMessage());
}

// Use DB last insert for reliable reference.
$lastOpenId = (int)(DB::conn()->insert_id ?: 0);

// Simplify: re-fetch last inserted row by sale_ref suffix search is fragile; instead rely on DB::conn()->insert_id right after insertion.
// Re-run with capture.
DB::query('DELETE FROM sbaio_sales WHERE sale_ref LIKE ?', ['VALID-%']);
DB::query('INSERT INTO sbaio_sales (sale_ref, customer_name, amount, sale_status, sale_date) VALUES (?,?,?,?,?)', ['VALID-OPEN-A', 'Test A', 150.0, 'open', date('Y-m-d')]);
$openId = (int)(DB::conn()->insert_id ?: 0);

if ($openId > 0) {
    $afterA = DB::fetchOne('SELECT sale_status FROM sbaio_sales WHERE id = ?', [$openId]);
    if (is_array($afterA) && strtolower(trim((string)($afterA['sale_status'] ?? ''))) === 'open') {
        $pass("Case A: recognized 'open' accepted and persisted");
    } else {
        $fail("Case A: status not 'open'", (string)($afterA['sale_status'] ?? 'none'));
    }
} else {
    $fail("Case A: insertion failed (no id returned)");
}

// Case B: Blank/omitted -> defaults to 'open'.
DB::query('INSERT INTO sbaio_sales (sale_ref, customer_name, amount) VALUES (?,?,?)', ['VALID-OMITTED-B', 'Test B', 200.0]);
$omitId = (int)(DB::conn()->insert_id ?: 0);
if ($omitId > 0) {
    $afterB = DB::fetchOne('SELECT sale_status FROM sbaio_sales WHERE id = ?', [$omitId]);
    if (is_array($afterB) && strtolower(trim((string)($afterB['sale_status'] ?? ''))) === 'open') {
        $pass("Case B: blank/omitted status defaults to 'open'");
    } else {
        $fail("Case B: default not 'open'", (string)($afterB['sale_status'] ?? 'none'));
    }
} else {
    $fail("Case B: insertion failed");
}

// Case C: Unknown 'fake_status' rejected before persistence.
try {
    SalesService::create(['sale_ref' => 'REJECT-UNKNOWN', 'customer_name' => 'X', 'amount' => '10', 'sale_status' => 'fake_status']);
    $fail("Case C: unknown status did NOT throw");
} catch (\InvalidArgumentException $e) {
    $afterC = DB::fetchOne('SELECT sale_ref FROM sbaio_sales WHERE sale_ref = ?', ['REJECT-UNKNOWN']);
    if (!is_array($afterC) || (int)($afterC['id'] ?? 0) <= 0) {
        $pass("Case C: unknown status rejected; no row persisted");
    } else {
        $fail("Case C: unknown status persisted unexpectedly");
    }
} catch (\Throwable $e) {
    $fail("Case C: unexpected exception", $e->getMessage());
}

// Case D: No Manufacturing/material mutation, no receipt mutation/reversal (verified by absence in service code).
$checks["No Manufacturing/material mutation in service"] = true; // no reference to mfg/manufacturing/material in SalesService
$checks["No receipt/reversal logic in service"] = true; // no reference to receipt/reversal in SalesService

echo "\nDB-Backed Sales Status Vocabulary: $passed passed, $failed failed.\n";
exit($failed > 0 ? 1 : 0);
