<?php
declare(strict_types=1);

namespace Plugins\Ledger;

use App\Core\DB;

final class LedgerMaintenance
{
    public static function ensureSchema(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS stock_ledger_entries (
	id INT AUTO_INCREMENT PRIMARY KEY,
	product_id INT NOT NULL,
	movement_type ENUM('IN','OUT','ADJUST','PRODUCTION_IN','PRODUCTION_OUT','DISPATCH_OUT') NOT NULL DEFAULT 'ADJUST',
	qty_delta DECIMAL(14,2) NOT NULL DEFAULT 0,
	balance_after DECIMAL(14,2) NOT NULL DEFAULT 0,
	reference_no VARCHAR(120) NULL,
	source_module VARCHAR(80) NULL,
	source_id INT NULL,
	notes TEXT NULL,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	UNIQUE KEY uniq_source (source_module, source_id, movement_type),
	KEY idx_stock_ledger_product (product_id),
	KEY idx_stock_ledger_movement (movement_type),
	KEY idx_stock_ledger_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public static function rebuildFromSources(bool $resetSyncedEntries = false): array
    {
        self::ensureSchema();

        $counts = [
            'production' => 0,
            'dispatch' => 0,
            'qc' => 0,
            'qr' => 0,
            'deleted' => 0,
        ];

        if ($resetSyncedEntries) {
            $deleted = DB::query("DELETE FROM stock_ledger_entries WHERE source_module IN ('ProductionEntries', 'DispatchEntries', 'QCEntries', 'QRCode')");
            if ($deleted !== false) {
                $counts['deleted'] = (int)DB::conn()->affected_rows;
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'production_entries'") !== null) {
            $entries = DB::fetchAll("SELECT id, product_id, good_qty, notes FROM production_entries ORDER BY id ASC");
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $productId = (int)($entry['product_id'] ?? 0);
                $goodQty = (float)($entry['good_qty'] ?? 0);
                if ($entryId <= 0 || $productId <= 0 || $goodQty <= 0) {
                    continue;
                }
                $exists = DB::fetchOne('SELECT id FROM stock_ledger_entries WHERE source_module=? AND source_id=? AND movement_type=? LIMIT 1', ['ProductionEntries', $entryId, 'PRODUCTION_IN']);
                if ($exists) {
                    continue;
                }
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, 'PRODUCTION_IN', $goodQty, 0, 'PE-' . $entryId, 'ProductionEntries', $entryId, (string)($entry['notes'] ?? '')]
                );
                $counts['production']++;
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'dispatch_entries'") !== null) {
            $entries = DB::fetchAll("SELECT id, product_id, dispatchable_qty, remarks FROM dispatch_entries ORDER BY id ASC");
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $productId = (int)($entry['product_id'] ?? 0);
                $dispatchQty = (float)($entry['dispatchable_qty'] ?? 0);
                if ($entryId <= 0 || $productId <= 0 || $dispatchQty <= 0) {
                    continue;
                }
                $exists = DB::fetchOne('SELECT id FROM stock_ledger_entries WHERE source_module=? AND source_id=? AND movement_type=? LIMIT 1', ['DispatchEntries', $entryId, 'DISPATCH_OUT']);
                if ($exists) {
                    continue;
                }
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, 'DISPATCH_OUT', -abs($dispatchQty), 0, 'DE-' . $entryId, 'DispatchEntries', $entryId, (string)($entry['remarks'] ?? '')]
                );
                $counts['dispatch']++;
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'qr_stock_updates'") !== null) {
            $entries = DB::fetchAll("SELECT id, product_id, movement_type, qty, reference_no, notes FROM qr_stock_updates ORDER BY id ASC");
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $productId = (int)($entry['product_id'] ?? 0);
                $movementType = (string)($entry['movement_type'] ?? 'ADJUST');
                $qty = (float)($entry['qty'] ?? 0);
                if ($entryId <= 0 || $productId <= 0 || $qty <= 0) {
                    continue;
                }
                $exists = DB::fetchOne('SELECT id FROM stock_ledger_entries WHERE source_module=? AND source_id=? AND movement_type=? LIMIT 1', ['QRCode', $entryId, $movementType]);
                if ($exists) {
                    continue;
                }
                $delta = $movementType === 'OUT' ? -abs($qty) : ($movementType === 'IN' ? abs($qty) : $qty);
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, $movementType, $delta, 0, (string)($entry['reference_no'] ?? ''), 'QRCode', $entryId, (string)($entry['notes'] ?? '')]
                );
                $counts['qr']++;
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'qc_entries'") !== null) {
            $entries = DB::fetchAll("SELECT id, product_id, fail_qty, remarks FROM qc_entries ORDER BY id ASC");
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $productId = (int)($entry['product_id'] ?? 0);
                $failQty = (float)($entry['fail_qty'] ?? 0);
                if ($entryId <= 0 || $productId <= 0 || $failQty <= 0) {
                    continue;
                }
                $exists = DB::fetchOne('SELECT id FROM stock_ledger_entries WHERE source_module=? AND source_id=? AND movement_type=? LIMIT 1', ['QCEntries', $entryId, 'PRODUCTION_OUT']);
                if ($exists) {
                    continue;
                }
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, 'PRODUCTION_OUT', -abs($failQty), 0, 'QC-' . $entryId, 'QCEntries', $entryId, (string)($entry['remarks'] ?? '')]
                );
                $counts['qc']++;
            }
        }

        self::recalculateBalances();

        return $counts;
    }

    public static function recalculateBalances(): void
    {
        self::ensureSchema();

        $productRows = DB::fetchAll('SELECT DISTINCT product_id FROM stock_ledger_entries ORDER BY product_id ASC');
        foreach ($productRows as $productRow) {
            $productId = (int)($productRow['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $balance = 0.0;
            $ledgerRows = DB::fetchAll('SELECT id, qty_delta FROM stock_ledger_entries WHERE product_id=? ORDER BY created_at ASC, id ASC', [$productId]);
            foreach ($ledgerRows as $ledgerRow) {
                $balance += (float)($ledgerRow['qty_delta'] ?? 0);
                DB::query('UPDATE stock_ledger_entries SET balance_after=? WHERE id=?', [$balance, (int)$ledgerRow['id']]);
            }
        }
    }

    public static function rebuildForProduct(int $productId): void
    {
        self::ensureSchema();

        if ($productId <= 0) {
            return;
        }

        DB::query(
            "DELETE FROM stock_ledger_entries WHERE product_id=? AND source_module IN ('ProductionEntries', 'DispatchEntries', 'QCEntries', 'QRCode')",
            [$productId]
        );

        if (DB::fetchOne("SHOW TABLES LIKE 'production_entries'") !== null) {
            $entries = DB::fetchAll("SELECT id, good_qty, notes FROM production_entries WHERE product_id=? ORDER BY id ASC", [$productId]);
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $goodQty = (float)($entry['good_qty'] ?? 0);
                if ($entryId <= 0 || $goodQty <= 0) {
                    continue;
                }
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, 'PRODUCTION_IN', $goodQty, 0, 'PE-' . $entryId, 'ProductionEntries', $entryId, (string)($entry['notes'] ?? '')]
                );
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'dispatch_entries'") !== null) {
            $entries = DB::fetchAll("SELECT id, dispatchable_qty, remarks FROM dispatch_entries WHERE product_id=? ORDER BY id ASC", [$productId]);
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $dispatchQty = (float)($entry['dispatchable_qty'] ?? 0);
                if ($entryId <= 0 || $dispatchQty <= 0) {
                    continue;
                }
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, 'DISPATCH_OUT', -abs($dispatchQty), 0, 'DE-' . $entryId, 'DispatchEntries', $entryId, (string)($entry['remarks'] ?? '')]
                );
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'qr_stock_updates'") !== null) {
            $entries = DB::fetchAll("SELECT id, movement_type, qty, reference_no, notes FROM qr_stock_updates WHERE product_id=? ORDER BY id ASC", [$productId]);
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $movementType = (string)($entry['movement_type'] ?? 'ADJUST');
                $qty = (float)($entry['qty'] ?? 0);
                if ($entryId <= 0 || $qty == 0.0) {
                    continue;
                }
                $delta = $movementType === 'OUT' ? -abs($qty) : ($movementType === 'IN' ? abs($qty) : $qty);
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, $movementType, $delta, 0, (string)($entry['reference_no'] ?? ''), 'QRCode', $entryId, (string)($entry['notes'] ?? '')]
                );
            }
        }

        if (DB::fetchOne("SHOW TABLES LIKE 'qc_entries'") !== null) {
            $entries = DB::fetchAll("SELECT id, fail_qty, remarks FROM qc_entries WHERE product_id=? ORDER BY id ASC", [$productId]);
            foreach ($entries as $entry) {
                $entryId = (int)($entry['id'] ?? 0);
                $failQty = (float)($entry['fail_qty'] ?? 0);
                if ($entryId <= 0 || $failQty <= 0) {
                    continue;
                }
                DB::query(
                    'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
                    [$productId, 'PRODUCTION_OUT', -abs($failQty), 0, 'QC-' . $entryId, 'QCEntries', $entryId, (string)($entry['remarks'] ?? '')]
                );
            }
        }

        self::recalculateBalancesForProduct($productId);
    }

    public static function recalculateBalancesForProduct(int $productId): void
    {
        self::ensureSchema();

        if ($productId <= 0) {
            return;
        }

        $balance = 0.0;
        $ledgerRows = DB::fetchAll('SELECT id, qty_delta FROM stock_ledger_entries WHERE product_id=? ORDER BY created_at ASC, id ASC', [$productId]);
        foreach ($ledgerRows as $ledgerRow) {
            $balance += (float)($ledgerRow['qty_delta'] ?? 0);
            DB::query('UPDATE stock_ledger_entries SET balance_after=? WHERE id=?', [$balance, (int)$ledgerRow['id']]);
        }
    }
}