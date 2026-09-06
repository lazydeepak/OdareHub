<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/Core/DB.php';

use App\Core\DB;

$closedOrderStatuses = ['closed', 'completed', 'cancelled', 'canceled'];
$validPlanStatuses = ['planned', 'scheduled', 'inprogress', 'in progress', 'released', 'confirmed', 'approved', 'ready'];
$invalidQcStatuses = ['cancelled', 'canceled', 'void', 'rejected'];
$validDispatchedStatuses = ['dispatched', 'completed', 'closed', 'delivered'];

$ordersByProduct = [];
$openRows = DB::fetchAll(
    "SELECT id, product_id, qty, order_date, required_date, coverage_pct, coverage_status, shortage_qty, notes
     FROM daily_orders
     WHERE LOWER(COALESCE(status, '')) NOT IN (?,?,?,?)
     ORDER BY product_id ASC, order_date ASC, required_date ASC, id ASC",
    $closedOrderStatuses
);

foreach ($openRows as $row) {
    $pid = (int)($row['product_id'] ?? 0);
    if ($pid <= 0) {
        continue;
    }
    $ordersByProduct[$pid][] = $row;
}

$validationRows = [];
foreach ($ordersByProduct as $productId => $rows) {
    $stockRow = DB::fetchOne(
        'SELECT COALESCE(SUM(qty_delta), 0) AS qty FROM stock_ledger_entries WHERE product_id = ?',
        [$productId]
    );
    $stockQty = round(max(0.0, (float)($stockRow['qty'] ?? 0.0)), 2);

    $planRow = DB::fetchOne(
        "SELECT COALESCE(SUM(planned_qty), 0) AS qty FROM production_plans WHERE product_id = ? AND LOWER(COALESCE(status, '')) IN (" . implode(',', array_fill(0, count($validPlanStatuses), '?')) . ')',
        array_merge([$productId], $validPlanStatuses)
    );
    $planQty = round(max(0.0, (float)($planRow['qty'] ?? 0.0)), 2);

    $qcRow = DB::fetchOne(
        "SELECT COALESCE(SUM(pass_qty), 0) AS qty FROM qc_entries WHERE product_id = ? AND LOWER(COALESCE(status, '')) NOT IN (" . implode(',', array_fill(0, count($invalidQcStatuses), '?')) . ')',
        array_merge([$productId], $invalidQcStatuses)
    );
    $qcQty = round(max(0.0, (float)($qcRow['qty'] ?? 0.0)), 2);

    $dispatchRow = DB::fetchOne(
        "SELECT COALESCE(SUM(dispatchable_qty), 0) AS qty FROM dispatch_entries WHERE product_id = ? AND LOWER(COALESCE(dispatch_status, '')) IN (" . implode(',', array_fill(0, count($validDispatchedStatuses), '?')) . ')',
        array_merge([$productId], $validDispatchedStatuses)
    );
    $dispatchedQty = round(max(0.0, (float)($dispatchRow['qty'] ?? 0.0)), 2);

    $usableSupplyQty = round(max(0.0, $stockQty + $planQty + $qcQty - $dispatchedQty), 2);

    $allocated = 0.0;
    foreach ($rows as $row) {
        $demand = round(max(0.0, (float)($row['qty'] ?? 0.0)), 2);
        $availableBefore = round(max(0.0, $usableSupplyQty - $allocated), 2);
        $covered = round(min($demand, $availableBefore), 2);
        $shortage = round(max(0.0, $demand - $covered), 2);
        $pct = $demand > 0 ? round(($covered / $demand) * 100, 2) : 0.0;
        $status = $pct >= 100.0 ? 'Full' : ($pct > 0.0 ? 'Partial' : 'Low');

        $actualPct = round((float)($row['coverage_pct'] ?? 0.0), 2);
        $actualShortage = round((float)($row['shortage_qty'] ?? 0.0), 2);
        $actualStatus = (string)($row['coverage_status'] ?? '');

        $validationRows[] = [
            'order_id' => (int)($row['id'] ?? 0),
            'product_id' => $productId,
            'notes' => (string)($row['notes'] ?? ''),
            'expected' => [
                'stock_qty' => $stockQty,
                'plan_qty' => $planQty,
                'qc_qty' => $qcQty,
                'dispatched_qty' => $dispatchedQty,
                'usable_supply_qty' => $usableSupplyQty,
                'covered_qty' => $covered,
                'shortage_qty' => $shortage,
                'coverage_pct' => $pct,
                'coverage_status' => $status,
            ],
            'actual' => [
                'coverage_pct' => $actualPct,
                'shortage_qty' => $actualShortage,
                'coverage_status' => $actualStatus,
            ],
            'match' => [
                'coverage_pct' => abs($actualPct - $pct) <= 0.01,
                'shortage_qty' => abs($actualShortage - $shortage) <= 0.01,
                'coverage_status' => strtolower($actualStatus) === strtolower($status),
            ],
        ];

        $allocated = round($allocated + $covered, 2);
    }
}

$mismatches = array_values(array_filter($validationRows, static function (array $r): bool {
    $m = (array)($r['match'] ?? []);
    return empty($m['coverage_pct']) || empty($m['shortage_qty']) || empty($m['coverage_status']);
}));

$scenarioExamples = [
    'full' => DB::fetchOne("SELECT id, product_id, notes, coverage_pct, coverage_status, shortage_qty FROM daily_orders WHERE LOWER(COALESCE(coverage_status,''))='full' ORDER BY id ASC LIMIT 1"),
    'partial' => DB::fetchOne("SELECT id, product_id, notes, coverage_pct, coverage_status, shortage_qty FROM daily_orders WHERE LOWER(COALESCE(coverage_status,''))='partial' ORDER BY id ASC LIMIT 1"),
    'low' => DB::fetchOne("SELECT id, product_id, notes, coverage_pct, coverage_status, shortage_qty FROM daily_orders WHERE LOWER(COALESCE(coverage_status,''))='low' ORDER BY id ASC LIMIT 1"),
    'planned_only' => DB::fetchOne("SELECT d.id, d.product_id, d.notes, d.coverage_pct, d.coverage_status, d.shortage_qty FROM daily_orders d WHERE EXISTS (SELECT 1 FROM production_plans pp WHERE pp.product_id=d.product_id) AND NOT EXISTS (SELECT 1 FROM production_entries pe WHERE pe.product_id=d.product_id) ORDER BY d.id ASC LIMIT 1"),
    'qc_ready_dispatch_ready' => DB::fetchOne("SELECT d.id, d.product_id, d.notes, d.coverage_pct, d.coverage_status, d.shortage_qty FROM daily_orders d WHERE EXISTS (SELECT 1 FROM qc_entries q WHERE q.product_id=d.product_id AND q.pass_qty>0) AND EXISTS (SELECT 1 FROM dispatch_entries x WHERE x.product_id=d.product_id AND x.dispatch_status='Ready') ORDER BY d.id ASC LIMIT 1"),
];

echo json_encode([
    'audited_open_orders' => count($validationRows),
    'mismatch_count' => count($mismatches),
    'mismatches' => $mismatches,
    'scenario_examples' => $scenarioExamples,
], JSON_PRETTY_PRINT), PHP_EOL;
