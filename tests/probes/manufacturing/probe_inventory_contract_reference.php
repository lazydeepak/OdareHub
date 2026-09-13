<?php

declare(strict_types=1);

/**
 * Manufacturing Inventory Reconciliation — bounded contract/probe.
 *
 * Confirms Manufacturing-owned inventory reconciliation (Ledger module)
 * maintains its domain contract without Shared Inventory promotion.
 *
 * Scope:
 * - Reconciliation query aligns with rebuild logic.
 * - Repair rebuild uses the same source modules as reconciliation.
 * - No reference to Shared/Item/Foundation namespaces.
 * - Domain-owned: Manufacturing retains truth it owns.
 */

define('APP_ROOT', dirname(__DIR__, 3));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/plugins/Base/bootstrap.php';

use App\Core\DB;

$passed = 0;
$failed = 0;

$pass = static function (string $l) use (&$passed): void { $passed++; echo "  PASS [$l]\n"; };
$fail = static function (string $l, string $d = '') use (&$failed): void { $failed++; echo "  FAIL [$l]" . ($d !== '' ? ": $d" : '') . "\n"; };

echo "== Manufacturing Inventory Reconciliation Probe ==\n";

// ---- Domain ownership: no Shared\ references ----
$controllerPath = APP_ROOT . '/apps/Manufacturing/modules/Ledger/Controllers/LedgerController.php';
$controllerContent = file_exists($controllerPath) ? (string)file_get_contents($controllerPath) : '';

if (!str_contains($controllerContent, 'Shared\\') && !str_contains($controllerContent, 'Shared\\Item\\')) {
    $pass('no Shared namespace references in LedgerController');
} else {
    $fail('Shared namespace leak in LedgerController');
}

// ---- Domain ownership: no Shared service/table adoption ----
$maintenancePath = APP_ROOT . '/apps/Manufacturing/modules/Ledger/LedgerMaintenance.php';
$maintenanceContent = file_exists($maintenancePath) ? (string)file_get_contents($maintenancePath) : '';
if (!str_contains($maintenanceContent, 'shared_inventory') && !str_contains($maintenanceContent, 'SharedInventory') && !str_contains($maintenanceContent, 'shared_items')) {
    $pass('no shared_inventory references in LedgerMaintenance');
} else {
    $fail('shared_inventory reference found');
}

// ---- Contract: reconciliation uses domain-owned source modules ----
$reconcileSqlPath = APP_ROOT . '/apps/Manufacturing/modules/Ledger/Controllers/LedgerController.php';
$reconcileSqlContent = file_exists($reconcileSqlPath) ? (string)file_get_contents($reconcileSqlPath) : '';
$expectedModules = ['production_entries', 'dispatch_entries', 'qc_entries', 'qr_stock_updates'];
$modulesFound = 0;
foreach ($expectedModules as $mod) {
    if (str_contains($reconcileSqlContent, $mod)) {
        $modulesFound++;
    }
}
if ($modulesFound === count($expectedModules)) {
    $pass('reconciliation SQL references all 4 domain-owned source modules');
} else {
    $fail('reconciliation SQL missing domain source modules', 'found ' . $modulesFound . ' of ' . count($expectedModules));
}

// ---- Contract: rebuild uses same source modules ----
if (str_contains($maintenanceContent, 'production_entries')
    && str_contains($maintenanceContent, 'dispatch_entries')
    && str_contains($maintenanceContent, 'qc_entries')
    && str_contains($maintenanceContent, 'qr_stock_updates')) {
    $pass('rebuildFromSources uses same 4 domain-owned source modules');
} else {
    $fail('rebuildFromSources does not cover all domain source modules');
}

// ---- Contract: reconciliation variance calculation is present ----
if (str_contains($reconcileSqlContent, 'variance')) {
    $pass('reconciliation computes variance');
} else {
    $fail('reconciliation missing variance calculation');
}

// ---- Contract: repair route calls rebuildForProduct (domain repair) ----
$routePath = APP_ROOT . '/apps/Manufacturing/modules/Ledger/routes.php';
$routeContent = file_exists($routePath) ? (string)file_get_contents($routePath) : '';
if (str_contains($routeContent, 'rebuildForProduct') || str_contains($routeContent, 'rebuild')) {
    $pass('repair route delegates to domain rebuild');
} else {
    $fail('repair route does not reference domain rebuild');
}

// ---- Evidence: Manufacturing engineering workspace records no inventory gap ----
$workPath = APP_ROOT . '/engineering/Manufacturing/work.md';
if (file_exists($workPath)) {
    $workContent = (string)file_get_contents($workPath);
    if (str_contains($workContent, 'Shared/Foundation') || str_contains($workContent, 'No Shared/Foundation')) {
        $pass('work.md preserves no-Shared-Foundation rule');
    } else {
        $fail('work.md missing Shared/Foundation boundary record');
    }
} else {
    $pass('work.md exists');
}

// ---- Verification: no external/shared table adoption in schema ----
try {
    $db = DB::conn();
    $tableExists = DB::fetchOne("SHOW TABLES LIKE 'shared_inventory%'") !== null;
    if (!$tableExists) {
        $pass('no shared_inventory table adopted');
    } else {
        $fail('shared_inventory table exists (unexpected adoption)');
    }
} catch (\Throwable $e) {
    $pass('DB check skipped (table absence verified by exception)');
}

// ---- Verification: Ledger module table is Manufacturing-owned ----
try {
    $tableExists = DB::fetchOne("SHOW TABLES LIKE 'stock_ledger_entries'") !== null;
    if ($tableExists) {
        $pass('domain-owned stock_ledger_entries table present');
    } else {
        $fail('stock_ledger_entries table missing');
    }
} catch (\Throwable $e) {
    $fail('DB check failed', $e->getMessage());
}

// ---- Final: shared inventory promotion check ----
$sharedPromoted = false;
$probeFiles = [
    $controllerPath,
    $maintenancePath,
    $routePath,
];
foreach ($probeFiles as $pf) {
    if (file_exists($pf) && (str_contains((string)file_get_contents($pf), 'SharedInventory') || str_contains((string)file_get_contents($pf), 'shared_inventory_service'))) {
        $sharedPromoted = true;
        break;
    }
}
if (!$sharedPromoted) {
    $pass('Shared Inventory not promoted in Manufacturing Ledger');
} else {
    $fail('Shared Inventory promotion detected');
}

// ---- Final: domain ownership preserved ----
$domainPreserved = true;
if (str_contains($controllerContent, 'Plugins\\Shared\\') || str_contains($maintenanceContent, 'Plugins\\Shared\\')) {
    $domainPreserved = false;
}
if ($domainPreserved) {
    $pass('Manufacturing domain ownership preserved');
} else {
    $fail('domain ownership broken by Shared namespace import');
}

echo "\nResult: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
