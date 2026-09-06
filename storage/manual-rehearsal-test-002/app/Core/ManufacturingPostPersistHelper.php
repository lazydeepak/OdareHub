<?php
declare(strict_types=1);

namespace App\Core;

class ManufacturingPostPersistHelper
{
    public static function afterEntryCreated(int $entryId, string $entryType, int $productId, float $qty, string $notes = '', ?string $user = null): void
    {
        self::syncLedgerForEntry($entryId, $entryType, $productId, $qty, $notes);
        self::recalculateCoverage([$productId]);
        self::syncHandoff($entryId, $entryType);
        self::logAuditEntry($entryId, $entryType, AuditLogService::ACTION_CREATED, $user, [
            'app' => 'manufacturing',
            'module' => strtolower($entryType),
            'note' => $entryType . ' created.',
        ]);
    }

    public static function afterEntryUpdated(int $entryId, string $entryType, array $existing, array $after, int $oldProductId, int $newProductId, ?string $user = null): void
    {
        self::recalculateCoverage([$oldProductId, $newProductId]);
        self::syncHandoff($entryId, $entryType);
        self::logAuditEntry($entryId, $entryType, AuditLogService::ACTION_UPDATED, $user, [
            'app' => 'manufacturing',
            'module' => strtolower($entryType),
            'old_state' => (string)($existing['status'] ?? ''),
            'new_state' => (string)($after['status'] ?? ''),
            'note' => $entryType . ' updated.',
        ]);
    }

    public static function afterEntryDeleted(int $entryId, string $entryType, int $productId, ?string $user = null): void
    {
        self::recalculateCoverage([$productId]);
        self::syncHandoff($entryId, $entryType);
        self::logAuditEntry($entryId, $entryType, AuditLogService::ACTION_DELETED, $user, [
            'app' => 'manufacturing',
            'module' => strtolower($entryType),
            'note' => $entryType . ' deleted.',
        ]);
    }

    public static function syncLedgerForEntry(int $entryId, string $entryType, int $productId, float $qty, string $notes = ''): void
    {
        if (!self::hasLedgerTable() || $productId <= 0) {
            return;
        }

        $currentBalanceRow = DB::fetchOne('SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?', [$productId]);
        $currentBalance = (float)($currentBalanceRow['balance'] ?? 0);
        $balanceAfter = $currentBalance + $qty;

        DB::query(
            'INSERT INTO stock_ledger_entries (product_id, movement_type, qty_delta, balance_after, reference_no, source_module, source_id, notes) VALUES (?,?,?,?,?,?,?,?)',
            [$productId, 'PRODUCTION_IN', $qty, $balanceAfter, strtoupper($entryType) . '-' . $entryId, $entryType, $entryId, $notes]
        );

        self::recalculateBalancesForProduct($productId);
    }

    public static function deleteLedgerForEntry(int $entryId, string $entryType): void
    {
        if (!self::hasLedgerTable()) {
            return;
        }

        DB::query('DELETE FROM stock_ledger_entries WHERE source_module=? AND source_id=?', [$entryType, $entryId]);
    }

    public static function recalculateBalancesForProduct(int $productId): void
    {
        if (!self::hasLedgerTable() || $productId <= 0) {
            return;
        }

        $rows = DB::fetchAll(
            'SELECT id, qty_delta FROM stock_ledger_entries WHERE product_id=? ORDER BY created_at ASC, id ASC',
            [$productId]
        );

        $balance = 0.0;
        foreach ($rows as $ledgerRow) {
            $balance += (float)($ledgerRow['qty_delta'] ?? 0);
            DB::query('UPDATE stock_ledger_entries SET balance_after=? WHERE id=?', [$balance, (int)$ledgerRow['id']]);
        }
    }

    public static function recalculateCoverage(array $productIds): void
    {
        if (empty($productIds)) {
            return;
        }

        try {
            $productIds = array_map('intval', $productIds);
            $productIds = array_filter($productIds, static fn(int $id): bool => $id > 0);
            if (empty($productIds)) {
                return;
            }

            if (class_exists(\Plugins\Coverage\Services\CoverageService::class)) {
                \Plugins\Coverage\Services\CoverageService::recalculateForProducts($productIds);
            }
        } catch (\Throwable $e) {
        }
    }

    public static function syncHandoff(int $entryId, string $entryType): void
    {
        try {
            if ($entryType === 'ProductionEntries' && class_exists(\Plugins\Base\Services\HandoffTrackingService::class)) {
                \Plugins\Base\Services\HandoffTrackingService::syncProductionEntry($entryId);
            } elseif ($entryType === 'QCEntries' && class_exists(\Plugins\Base\Services\HandoffTrackingService::class)) {
                \Plugins\Base\Services\HandoffTrackingService::syncQcEntry($entryId);
            }
        } catch (\Throwable $e) {
        }
    }

    private static function logAuditEntry(int $id, string $module, string $action, ?string $user, array $extra): void
    {
        try {
            if (!class_exists(AuditLogService::class)) {
                return;
            }

            AuditLogService::ensureSchema();
            AuditLogService::logEvent($module, $id, AuditLogService::EVENT_LIFECYCLE, $action, $user, $extra);
        } catch (\Throwable $e) {
        }
    }

    public static function hasLedgerTable(): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE 'stock_ledger_entries'") !== null;
    }

    public static function afterPlanCreated(int $planId, int $productId): void
    {
        self::recalculateCoverage([$productId]);
    }

    public static function afterPlanUpdated(int $planId, int $oldProductId, int $newProductId): void
    {
        self::recalculateCoverage([$oldProductId, $newProductId]);
    }

    public static function afterPlanDeleted(int $planId, int $productId): void
    {
        self::recalculateCoverage([$productId]);
    }
}
