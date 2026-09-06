<?php
declare(strict_types=1);

namespace Plugins\Ledger\Controllers;

use App\Core\DB;
use App\Core\View;
use Plugins\Ledger\LedgerMaintenance;
use Plugins\Coverage\Services\CoverageService;

require_once dirname(__DIR__) . '/LedgerMaintenance.php';

final class LedgerController
{
    public static function index(View $view): void
    {
        LedgerMaintenance::ensureSchema();

        $productId = (int)($_GET['product_id'] ?? 0);
        $sourceModule = trim((string)($_GET['source_module'] ?? ''));
        $sourceId = (int)($_GET['source_id'] ?? 0);
        if (!in_array($sourceModule, ['ProductionEntries', 'DispatchEntries', 'QCEntries', 'QRCode', 'Ledger'], true)) {
            $sourceModule = '';
        }

        $summarySql = 'SELECT p.id, p.parts_name, p.parts_number, COALESCE(SUM(s.qty_delta), 0) AS balance, MAX(s.created_at) AS last_movement
            FROM products p
            LEFT JOIN stock_ledger_entries s ON s.product_id = p.id
            WHERE p.is_active = 1';
        $summaryParams = [];
        if ($productId > 0) {
            $summarySql .= ' AND p.id = ?';
            $summaryParams[] = $productId;
        }
        $summarySql .= ' GROUP BY p.id, p.parts_name, p.parts_number ORDER BY p.parts_name ASC LIMIT 500';

        $entrySql = 'SELECT s.*, p.parts_name, p.parts_number
            FROM stock_ledger_entries s
            INNER JOIN products p ON p.id = s.product_id
            WHERE 1=1';
        $entryParams = [];
        if ($productId > 0) {
            $entrySql .= ' AND s.product_id = ?';
            $entryParams[] = $productId;
        }
        if ($sourceModule !== '') {
            $entrySql .= ' AND s.source_module = ?';
            $entryParams[] = $sourceModule;
        }
        if ($sourceId > 0) {
            $entrySql .= ' AND s.source_id = ?';
            $entryParams[] = $sourceId;
        }
        $entrySql .= ' ORDER BY s.created_at DESC, s.id DESC LIMIT 200';

        $view->render('Ledger::index.php', [
            'pageTitle' => 'Stock Ledger',
            'summaryRows' => DB::fetchAll($summarySql, $summaryParams),
            'rows' => DB::fetchAll($entrySql, $entryParams),
            'products' => self::products(),
            'product_id' => $productId,
            'source_module' => $sourceModule,
            'source_id' => $sourceId,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function addForm(View $view): void
    {
        LedgerMaintenance::ensureSchema();

        $old = $_SESSION['ledger_old'] ?? [];
        if ((int)($old['product_id'] ?? 0) <= 0 && (int)($_GET['product_id'] ?? 0) > 0) {
            $old['product_id'] = (int)$_GET['product_id'];
        }
        $old['movement_type'] = (string)($old['movement_type'] ?? 'ADJUST');
        unset($_SESSION['ledger_old']);

        $view->render('Ledger::add.php', [
            'pageTitle' => 'Add Stock Ledger Entry',
            'products' => self::products(),
            'old' => $old,
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function reconcile(View $view): void
    {
        LedgerMaintenance::ensureSchema();
        $mismatchesOnly = (int)($_GET['mismatches_only'] ?? 0) === 1;

        $rows = DB::fetchAll(
            "SELECT
                p.id,
                p.parts_name,
                p.parts_number,
                COALESCE(ledger.balance, 0) AS ledger_balance,
                COALESCE(prod.production_in, 0) AS production_in,
                COALESCE(dispatch.dispatch_out, 0) AS dispatch_out,
                COALESCE(qc.qc_out, 0) AS qc_out,
                COALESCE(qr.qr_delta, 0) AS qr_delta,
                (
                    COALESCE(prod.production_in, 0)
                    - COALESCE(dispatch.dispatch_out, 0)
                    - COALESCE(qc.qc_out, 0)
                    + COALESCE(qr.qr_delta, 0)
                ) AS expected_balance,
                (
                    COALESCE(ledger.balance, 0) - (
                        COALESCE(prod.production_in, 0)
                        - COALESCE(dispatch.dispatch_out, 0)
                        - COALESCE(qc.qc_out, 0)
                        + COALESCE(qr.qr_delta, 0)
                    )
                ) AS variance
            FROM products p
            LEFT JOIN (
                SELECT product_id, SUM(qty_delta) AS balance
                FROM stock_ledger_entries
                GROUP BY product_id
            ) ledger ON ledger.product_id = p.id
            LEFT JOIN (
                SELECT product_id, SUM(good_qty) AS production_in
                FROM production_entries
                GROUP BY product_id
            ) prod ON prod.product_id = p.id
            LEFT JOIN (
                SELECT product_id, SUM(dispatchable_qty) AS dispatch_out
                FROM dispatch_entries
                GROUP BY product_id
            ) dispatch ON dispatch.product_id = p.id
            LEFT JOIN (
                SELECT product_id, SUM(fail_qty) AS qc_out
                FROM qc_entries
                GROUP BY product_id
            ) qc ON qc.product_id = p.id
            LEFT JOIN (
                SELECT product_id,
                    SUM(CASE
                        WHEN movement_type = 'OUT' THEN -ABS(qty)
                        WHEN movement_type = 'IN' THEN ABS(qty)
                        ELSE qty
                    END) AS qr_delta
                FROM qr_stock_updates
                GROUP BY product_id
            ) qr ON qr.product_id = p.id
            WHERE p.is_active = 1
            ORDER BY ABS(
                COALESCE(ledger.balance, 0) - (
                    COALESCE(prod.production_in, 0)
                    - COALESCE(dispatch.dispatch_out, 0)
                    - COALESCE(qc.qc_out, 0)
                    + COALESCE(qr.qr_delta, 0)
                )
            ) DESC, p.parts_name ASC"
        );

        $view->render('Ledger::reconcile.php', [
            'pageTitle' => 'Stock Reconciliation',
            'rows' => $mismatchesOnly
                ? array_values(array_filter($rows, static fn (array $row): bool => abs((float)($row['variance'] ?? 0)) > 0.0001))
                : $rows,
            'mismatches_only' => $mismatchesOnly,
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
        ]);
    }

    public static function repairProduct(int $productId): void
    {
        if ($productId <= 0) {
            self::flash('err', 'Invalid product id.');
            return;
        }

        LedgerMaintenance::rebuildForProduct($productId);
        self::recalculateCoverage([$productId]);
        self::flash('ok', 'Product ledger repaired from source documents.');
    }

    public static function create(array $input): void
    {
        LedgerMaintenance::ensureSchema();

        $data = [
            'product_id' => (int)($input['product_id'] ?? 0),
            'movement_type' => trim((string)($input['movement_type'] ?? 'ADJUST')),
            'qty' => (float)($input['qty'] ?? 0),
            'reference_no' => trim((string)($input['reference_no'] ?? '')),
            'notes' => trim((string)($input['notes'] ?? '')),
        ];
        $_SESSION['ledger_old'] = $data;

        if ($data['product_id'] <= 0 || !in_array($data['movement_type'], ['IN', 'OUT', 'ADJUST', 'PRODUCTION_IN', 'PRODUCTION_OUT', 'DISPATCH_OUT'], true)) {
            self::flash('err', 'Product and valid movement type are required.');
            header('Location: /ledger/add');
            exit;
        }

        $delta = self::movementDelta($data['movement_type'], $data['qty']);
        if ($delta == 0.0) {
            self::flash('err', 'Quantity must be non-zero. For ADJUST, positive or negative values are allowed.');
            header('Location: /ledger/add?product_id=' . $data['product_id']);
            exit;
        }

        self::postEntry(
            $data['product_id'],
            $data['movement_type'],
            $delta,
            $data['reference_no'],
            'Ledger',
            null,
            $data['notes']
        );

        self::recalculateCoverage([$data['product_id']]);

        unset($_SESSION['ledger_old']);
        self::flash('ok', 'Ledger entry posted.');
    }

    public static function rebuild(): void
    {
        $counts = LedgerMaintenance::rebuildFromSources(true);
        CoverageService::recalculateAllOpenOrders();
        self::flash(
            'ok',
            sprintf(
                'Ledger rebuilt. Re-synced %d production, %d dispatch, %d QC, and %d QR entries. Removed %d older synced rows first.',
                $counts['production'],
                $counts['dispatch'],
                $counts['qc'],
                $counts['qr'],
                $counts['deleted']
            )
        );
    }

    public static function postEntry(int $productId, string $movementType, float $qtyDelta, string $referenceNo = '', ?string $sourceModule = null, ?int $sourceId = null, string $notes = ''): void
    {
        $db = DB::conn();
        $db->begin_transaction();
        try {
            $currentBalanceRow = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
            $currentBalance = (float)($currentBalanceRow['balance'] ?? 0);
            $balanceAfter = $currentBalance + $qtyDelta;

            DB::query(
                'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                [$productId, $movementType, $qtyDelta, $balanceAfter, $referenceNo, $sourceModule, $sourceId, $notes]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    private static function movementDelta(string $movementType, float $qty): float
    {
        if (in_array($movementType, ['OUT', 'PRODUCTION_OUT', 'DISPATCH_OUT'], true)) {
            return -abs($qty);
        }
        if (in_array($movementType, ['IN', 'PRODUCTION_IN'], true)) {
            return abs($qty);
        }
        return $qty;
    }

    private static function products(): array
    {
        return DB::fetchAll('SELECT id, parts_name, parts_number FROM products WHERE is_active = 1 ORDER BY parts_name ASC LIMIT 1000');
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['ledger_flash_' . $key] = $msg;
    }

    private static function pullFlash(string $key): string
    {
        $sessionKey = 'ledger_flash_' . $key;
        $value = (string)($_SESSION[$sessionKey] ?? '');
        unset($_SESSION[$sessionKey]);
        return $value;
    }

    private static function recalculateCoverage(array $productIds): void
    {
        try {
            CoverageService::recalculateForProducts($productIds);
        } catch (\Throwable $e) {
            self::flash('err', 'Coverage refresh failed: ' . $e->getMessage());
        }
    }
}
