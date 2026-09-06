<?php
declare(strict_types=1);

namespace Plugins\DispatchEntries\Services;

use App\Core\Auth;
use App\Core\DB;
use Plugins\Supply\Services\SupplyModel;

final class DispatchWorkflow
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_READY = 'Ready';
    public const STATUS_PARTIAL = 'Partial';
    public const STATUS_HOLD = 'Hold';
    public const STATUS_BLOCKED = 'Blocked';
    public const STATUS_DISPATCHED = 'Dispatched';
    public const STATUS_CANCELLED = 'Cancelled';

    /**
     * @var array<int,string>
     */
    private const RELEASED_STATUSES = [
        self::STATUS_PARTIAL,
        self::STATUS_DISPATCHED,
        'Completed',
        'Closed',
        'Delivered',
    ];

    /**
     * @var array<string,array<int,string>>
     */
    private const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_READY, self::STATUS_HOLD, self::STATUS_BLOCKED, self::STATUS_CANCELLED],
        self::STATUS_READY => [self::STATUS_PARTIAL, self::STATUS_DISPATCHED, self::STATUS_HOLD, self::STATUS_BLOCKED, self::STATUS_CANCELLED],
        self::STATUS_PARTIAL => [self::STATUS_DISPATCHED, self::STATUS_HOLD, self::STATUS_BLOCKED, self::STATUS_CANCELLED],
        self::STATUS_HOLD => [self::STATUS_READY, self::STATUS_BLOCKED, self::STATUS_CANCELLED],
        self::STATUS_BLOCKED => [self::STATUS_READY, self::STATUS_HOLD, self::STATUS_CANCELLED],
        self::STATUS_DISPATCHED => [],
        self::STATUS_CANCELLED => [],
    ];

    private static ?array $dispatchColumns = null;
    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        self::addColumnIfMissing('status_reason', 'VARCHAR(190) NULL AFTER remarks');
        self::addColumnIfMissing('status_note', 'TEXT NULL AFTER status_reason');
        self::addColumnIfMissing('released_at', 'DATETIME NULL AFTER status_note');
        self::addColumnIfMissing('blocked_at', 'DATETIME NULL AFTER released_at');
        self::addColumnIfMissing('dispatched_at', 'DATETIME NULL AFTER blocked_at');
        self::addColumnIfMissing('last_transition_at', 'DATETIME NULL AFTER dispatched_at');
        self::addColumnIfMissing('status_updated_by', 'INT NULL AFTER last_transition_at');
        self::addIndexIfMissing('idx_dispatch_entries_status', '(dispatch_status)');
        self::addIndexIfMissing('idx_dispatch_entries_daily_order_status', '(daily_order_id, dispatch_status)');
        self::addIndexIfMissing('idx_dispatch_entries_qc_status', '(qc_entry_id, dispatch_status)');

        DB::query(
            'CREATE TABLE IF NOT EXISTS dispatch_entry_transitions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dispatch_entry_id INT NOT NULL,
                from_status VARCHAR(40) NULL,
                to_status VARCHAR(40) NOT NULL,
                transition_reason VARCHAR(190) NULL,
                transition_note TEXT NULL,
                performed_by INT NULL,
                performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_dispatch_entry_transition_entry (dispatch_entry_id),
                INDEX idx_dispatch_entry_transition_status (to_status),
                INDEX idx_dispatch_entry_transition_performed_at (performed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$schemaEnsured = true;
        self::$dispatchColumns = null;
    }

    /**
     * @return array<string,string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => self::STATUS_DRAFT,
            self::STATUS_READY => self::STATUS_READY,
            self::STATUS_HOLD => self::STATUS_HOLD,
            self::STATUS_BLOCKED => self::STATUS_BLOCKED,
            self::STATUS_PARTIAL => self::STATUS_PARTIAL,
            self::STATUS_DISPATCHED => self::STATUS_DISPATCHED,
            self::STATUS_CANCELLED => self::STATUS_CANCELLED,
        ];
    }

    public static function normalizeStatus(string $status, string $fallback = self::STATUS_READY): string
    {
        $normalized = strtolower(trim($status));
        return match ($normalized) {
            'draft' => self::STATUS_DRAFT,
            'ready' => self::STATUS_READY,
            'partial' => self::STATUS_PARTIAL,
            'hold' => self::STATUS_HOLD,
            'blocked' => self::STATUS_BLOCKED,
            'dispatched', 'completed', 'closed', 'delivered' => self::STATUS_DISPATCHED,
            'cancelled', 'canceled' => self::STATUS_CANCELLED,
            default => $fallback,
        };
    }

    public static function countsAsReleased(string $status): bool
    {
        return in_array(self::normalizeStatus($status, ''), self::RELEASED_STATUSES, true);
    }

    public static function requiresReason(string $status): bool
    {
        $status = self::normalizeStatus($status, '');
        return in_array($status, [self::STATUS_HOLD, self::STATUS_BLOCKED, self::STATUS_CANCELLED], true);
    }

    /**
     * @param array<string,mixed>|null $existingRow
     * @param array<string,mixed>|null $currentUser
     * @return array<string,mixed>
     */
    public static function prepareForSave(array $data, ?array $existingRow = null, ?array $currentUser = null): array
    {
        self::ensureSchema();

        $status = self::normalizeStatus((string)($data['dispatch_status'] ?? ''), self::STATUS_READY);
        $reason = self::sanitizeReason((string)($data['status_reason'] ?? ''));
        $note = self::sanitizeNote((string)($data['status_note'] ?? ''));
        $product = self::loadProduct((int)($data['product_id'] ?? 0));

        if (!$product) {
            throw new \RuntimeException('Dispatch product was not found.');
        }

        self::validateStatusPayload($status, $reason);

        $currentStatus = $existingRow ? self::normalizeStatus((string)($existingRow['dispatch_status'] ?? ''), self::STATUS_READY) : null;
        if ($currentStatus !== null && $currentStatus !== $status && !self::canTransition($currentStatus, $status)) {
            throw new \RuntimeException('Invalid dispatch lifecycle transition from ' . $currentStatus . ' to ' . $status . '.');
        }

        $finalStatus = $status;
        if (self::countsAsReleased($status)) {
            self::validateRelease($data, $existingRow, $product);
            $finalStatus = self::resolveReleasedStatus($data, $existingRow);
        }

        $transition = self::buildTransition($currentStatus, $finalStatus, $reason, $note, $currentUser, $existingRow === null);
        $timestamps = self::buildTimestamps($finalStatus, $existingRow, $transition !== null);

        return [
            'dispatch_status' => $finalStatus,
            'status_reason' => $reason,
            'status_note' => $note,
            'status_updated_by' => self::actorId($currentUser),
            'released_at' => $timestamps['released_at'],
            'blocked_at' => $timestamps['blocked_at'],
            'dispatched_at' => $timestamps['dispatched_at'],
            'last_transition_at' => $timestamps['last_transition_at'],
            'transition' => $transition,
            'product' => $product,
        ];
    }

    /**
     * @param array<string,mixed>|null $currentUser
     * @return array<string,mixed>
     */
    public static function transition(int $entryId, string $requestedStatus, string $reason, string $note, ?array $currentUser = null): array
    {
        self::ensureSchema();

        $row = self::fetchDispatchEntry($entryId);
        if (!$row) {
            throw new \RuntimeException('Dispatch entry not found.');
        }

        $currentStatus = self::normalizeStatus((string)($row['dispatch_status'] ?? ''), self::STATUS_READY);
        $requestedStatus = self::normalizeStatus($requestedStatus, $currentStatus);
        if ($requestedStatus === $currentStatus) {
            throw new \RuntimeException('Dispatch entry is already in ' . $currentStatus . '.');
        }
        if (!self::canTransition($currentStatus, $requestedStatus)) {
            throw new \RuntimeException('Invalid dispatch lifecycle transition from ' . $currentStatus . ' to ' . $requestedStatus . '.');
        }

        $prepared = self::prepareForSave([
            'dispatch_date' => (string)($row['dispatch_date'] ?? ''),
            'daily_order_id' => (int)($row['daily_order_id'] ?? 0),
            'production_plan_id' => (int)($row['production_plan_id'] ?? 0),
            'production_entry_id' => (int)($row['production_entry_id'] ?? 0),
            'qc_entry_id' => (int)($row['qc_entry_id'] ?? 0),
            'product_id' => (int)($row['product_id'] ?? 0),
            'dispatchable_qty' => (float)($row['dispatchable_qty'] ?? 0),
            'destination' => (string)($row['destination'] ?? ''),
            'dispatch_type' => (string)($row['dispatch_type'] ?? ''),
            'dispatch_status' => $requestedStatus,
            'status_reason' => $reason,
            'status_note' => $note,
        ], $row, $currentUser);

        return array_merge($prepared, [
            'entry' => $row,
        ]);
    }

    /**
     * Validates a status transition without persisting changes.
     * Returns the resolved status and prepared data for entity runtime to persist.
     * @param array<string,mixed>|null $currentUser
     * @return array<string,mixed>
     */
    public static function validateTransitionForEntity(int $entryId, string $requestedStatus, string $reason, string $note, ?array $currentUser = null): array
    {
        self::ensureSchema();

        $row = self::fetchDispatchEntry($entryId);
        if (!$row) {
            throw new \RuntimeException('Dispatch entry not found.');
        }

        $currentStatus = self::normalizeStatus((string)($row['dispatch_status'] ?? ''), self::STATUS_READY);
        $requestedStatus = self::normalizeStatus($requestedStatus, $currentStatus);
        if ($requestedStatus === $currentStatus) {
            throw new \RuntimeException('Dispatch entry is already in ' . $currentStatus . '.');
        }
        if (!self::canTransition($currentStatus, $requestedStatus)) {
            throw new \RuntimeException('Invalid dispatch lifecycle transition from ' . $currentStatus . ' to ' . $requestedStatus . '.');
        }

        $prepared = self::prepareForSave([
            'dispatch_date' => (string)($row['dispatch_date'] ?? ''),
            'daily_order_id' => (int)($row['daily_order_id'] ?? 0),
            'production_plan_id' => (int)($row['production_plan_id'] ?? 0),
            'production_entry_id' => (int)($row['production_entry_id'] ?? 0),
            'qc_entry_id' => (int)($row['qc_entry_id'] ?? 0),
            'product_id' => (int)($row['product_id'] ?? 0),
            'dispatchable_qty' => (float)($row['dispatchable_qty'] ?? 0),
            'destination' => (string)($row['destination'] ?? ''),
            'dispatch_type' => (string)($row['dispatch_type'] ?? ''),
            'dispatch_status' => $requestedStatus,
            'status_reason' => $reason,
            'status_note' => $note,
        ], $row, $currentUser);

        return [
            'ok' => true,
            'dispatch_status' => $prepared['dispatch_status'],
            'status_reason' => $prepared['status_reason'],
            'status_note' => $prepared['status_note'],
            'released_at' => $prepared['released_at'],
            'blocked_at' => $prepared['blocked_at'],
            'dispatched_at' => $prepared['dispatched_at'],
            'last_transition_at' => $prepared['last_transition_at'],
            'status_updated_by' => $prepared['status_updated_by'],
            'product' => $prepared['product'],
            'entry' => $row,
            'transition' => $prepared['transition'],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recentTransitions(int $entryId, int $limit = 12): array
    {
        self::ensureSchema();

        if ($entryId <= 0) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        return DB::fetchAll(
            'SELECT t.*, u.email AS actor_email
             FROM dispatch_entry_transitions t
             LEFT JOIN users u ON u.id = t.performed_by
             WHERE t.dispatch_entry_id = ?
             ORDER BY t.performed_at DESC, t.id DESC
             LIMIT ' . $limit,
            [$entryId]
        );
    }

    /**
     * @param array<string,mixed>|null $currentUser
     * @param array<string,mixed>|null $existingRow
     * @param array<string,mixed>|null $transition
     */
    public static function recordTransition(int $entryId, ?array $transition): void
    {
        self::ensureSchema();

        if ($entryId <= 0 || !$transition) {
            return;
        }

        DB::query(
            'INSERT INTO dispatch_entry_transitions (dispatch_entry_id, from_status, to_status, transition_reason, transition_note, performed_by, performed_at) VALUES (?,?,?,?,?,?,?)',
            [
                $entryId,
                $transition['from_status'],
                $transition['to_status'],
                $transition['transition_reason'],
                $transition['transition_note'],
                $transition['performed_by'],
                $transition['performed_at'],
            ]
        );
    }

    /**
     * @return array<string,bool>
     */
    public static function dispatchColumns(): array
    {
        self::ensureSchema();

        if (is_array(self::$dispatchColumns)) {
            return self::$dispatchColumns;
        }

        self::$dispatchColumns = [];
        foreach (DB::fetchAll('SHOW COLUMNS FROM dispatch_entries') as $column) {
            $name = (string)($column['Field'] ?? '');
            if ($name !== '') {
                self::$dispatchColumns[$name] = true;
            }
        }

        return self::$dispatchColumns;
    }

    public static function canUseLedger(array $product): bool
    {
        $fulfillmentMode = SupplyModel::normalizeFulfillmentMode((string)($product['fulfillment_mode'] ?? ''));
        $stockedAtIpm = (int)($product['stocked_at_ipm'] ?? 1) === 1;
        if ($fulfillmentMode === SupplyModel::FULFILL_DIRECT_SUPPLIER) {
            return false;
        }
        return $stockedAtIpm;
    }

    /**
     * @param array<string,mixed>|null $existingRow
     */
    public static function releasedStockAllowance(int $productId, ?array $existingRow = null): float
    {
        $balanceRow = DB::fetchOne(
            'SELECT COALESCE(SUM(qty_delta), 0) AS balance FROM stock_ledger_entries WHERE product_id = ?',
            [$productId]
        );
        $available = (float)($balanceRow['balance'] ?? 0);

        if ($existingRow
            && (int)($existingRow['product_id'] ?? 0) === $productId
            && self::countsAsReleased((string)($existingRow['dispatch_status'] ?? ''))
        ) {
            $available += abs((float)($existingRow['dispatchable_qty'] ?? 0));
        }

        return max(0.0, round($available, 2));
    }

    private static function canTransition(string $fromStatus, string $toStatus): bool
    {
        if ($fromStatus === $toStatus) {
            return true;
        }

        return in_array($toStatus, self::TRANSITIONS[$fromStatus] ?? [], true);
    }

    private static function sanitizeReason(string $reason): string
    {
        return mb_substr(trim($reason), 0, 190);
    }

    private static function sanitizeNote(string $note): string
    {
        return trim($note);
    }

    private static function validateStatusPayload(string $status, string $reason): void
    {
        if (!array_key_exists($status, self::TRANSITIONS)) {
            throw new \RuntimeException('Invalid dispatch status.');
        }

        if (self::requiresReason($status) && $reason === '') {
            throw new \RuntimeException('A workflow reason is required for ' . $status . ' dispatches.');
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $existingRow
     * @param array<string,mixed> $product
     */
    private static function validateRelease(array $data, ?array $existingRow, array $product): void
    {
        $qty = round(max(0.0, (float)($data['dispatchable_qty'] ?? 0)), 2);
        if ($qty <= 0.0) {
            throw new \RuntimeException('Dispatchable quantity must be greater than zero.');
        }

        $dailyOrderId = (int)($data['daily_order_id'] ?? 0);
        if ($dailyOrderId > 0) {
            $outstanding = self::outstandingOrderQty($dailyOrderId, $existingRow ? (int)($existingRow['id'] ?? 0) : 0);
            if ($qty - $outstanding > 0.0001) {
                throw new \RuntimeException('Dispatch quantity exceeds the outstanding daily order balance.');
            }
        }

        $requiresIpmQc = SupplyModel::normalizeRequiresIpmQc($product['requires_ipm_qc'] ?? 1);
        $fulfillmentMode = SupplyModel::normalizeFulfillmentMode((string)($product['fulfillment_mode'] ?? ''));
        $qcEntryId = (int)($data['qc_entry_id'] ?? 0);
        if ($requiresIpmQc && $fulfillmentMode !== SupplyModel::FULFILL_DIRECT_SUPPLIER && $qcEntryId <= 0) {
            throw new \RuntimeException('This product requires an IPM QC entry before dispatch release.');
        }
        if ($qcEntryId > 0) {
            $releasableQty = self::releasableQcQty($qcEntryId, $existingRow ? (int)($existingRow['id'] ?? 0) : 0);
            if ($qty - $releasableQty > 0.0001) {
                throw new \RuntimeException('Dispatch quantity exceeds releasable QC quantity.');
            }
        }

        if (self::hasStageReadinessTable()) {
            $dispatchDate = trim((string)($data['dispatch_date'] ?? ''));
            if ($dispatchDate === '') {
                $dispatchDate = date('Y-m-d');
            }

            $stageAllowance = self::dispatchStageReleaseAllowance((int)($data['product_id'] ?? 0), $dispatchDate, $existingRow);
            if ($qty - $stageAllowance > 0.0001) {
                throw new \RuntimeException('Dispatch quantity exceeds released dispatch-stage allowance. Complete QC/assembly release or dispatch a partial-ready quantity.');
            }
        }

        if (self::canUseLedger($product) && self::hasLedgerTable()) {
            $productId = (int)($data['product_id'] ?? 0);
            $available = self::releasedStockAllowance($productId, $existingRow);
            if ($qty - $available > 0.0001) {
                throw new \RuntimeException('Insufficient stock available for dispatch release.');
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $existingRow
     */
    private static function resolveReleasedStatus(array $data, ?array $existingRow): string
    {
        $dailyOrderId = (int)($data['daily_order_id'] ?? 0);
        if ($dailyOrderId <= 0) {
            return self::STATUS_DISPATCHED;
        }

        $qty = round(max(0.0, (float)($data['dispatchable_qty'] ?? 0)), 2);
        $outstanding = self::outstandingOrderQty($dailyOrderId, $existingRow ? (int)($existingRow['id'] ?? 0) : 0);
        $remainingAfter = round(max(0.0, $outstanding - $qty), 2);
        return $remainingAfter > 0.0001 ? self::STATUS_PARTIAL : self::STATUS_DISPATCHED;
    }

    /**
     * @param array<string,mixed>|null $existingRow
     * @return array<string,mixed>|null
     */
    private static function buildTransition(?string $fromStatus, string $toStatus, string $reason, string $note, ?array $currentUser, bool $isCreate): ?array
    {
        $fromStatus = $fromStatus !== null ? self::normalizeStatus($fromStatus, self::STATUS_READY) : null;
        if (!$isCreate && $fromStatus === $toStatus) {
            return null;
        }

        return [
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'transition_reason' => $reason !== '' ? $reason : ($isCreate ? 'Created' : null),
            'transition_note' => $note !== '' ? $note : null,
            'performed_by' => self::actorId($currentUser),
            'performed_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * @param array<string,mixed>|null $existingRow
     * @return array<string,mixed>
     */
    private static function buildTimestamps(string $finalStatus, ?array $existingRow, bool $statusChanged): array
    {
        $releasedAt = $existingRow['released_at'] ?? null;
        $blockedAt = $existingRow['blocked_at'] ?? null;
        $dispatchedAt = $existingRow['dispatched_at'] ?? null;
        $lastTransitionAt = $existingRow['last_transition_at'] ?? null;
        $now = date('Y-m-d H:i:s');

        if ($statusChanged) {
            $lastTransitionAt = $now;
        }

        if (self::countsAsReleased($finalStatus)) {
            $releasedAt = $releasedAt ?: $now;
            if ($finalStatus === self::STATUS_DISPATCHED) {
                $dispatchedAt = $now;
            }
            $blockedAt = null;
        } elseif (in_array($finalStatus, [self::STATUS_HOLD, self::STATUS_BLOCKED], true)) {
            $blockedAt = $now;
        } else {
            $blockedAt = null;
        }

        if ($finalStatus === self::STATUS_CANCELLED) {
            $blockedAt = $blockedAt ?: $now;
        }

        return [
            'released_at' => $releasedAt,
            'blocked_at' => $blockedAt,
            'dispatched_at' => $dispatchedAt,
            'last_transition_at' => $lastTransitionAt,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadProduct(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        return DB::fetchOne(
            'SELECT id, parts_name, parts_number, supply_mode, fulfillment_mode, requires_ipm_qc, stocked_at_ipm
             FROM products
             WHERE id = ?
             LIMIT 1',
            [$productId]
        );
    }

    private static function outstandingOrderQty(int $dailyOrderId, int $excludeEntryId = 0): float
    {
        $order = DB::fetchOne('SELECT qty FROM daily_orders WHERE id = ? LIMIT 1', [$dailyOrderId]);
        if (!$order) {
            throw new \RuntimeException('Linked daily order was not found.');
        }

        $params = [$dailyOrderId];
        $sql = 'SELECT COALESCE(SUM(dispatchable_qty), 0) AS total
                FROM dispatch_entries
                WHERE daily_order_id = ?
                  AND ' . self::releasedStatusSql('dispatch_status');
        if ($excludeEntryId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeEntryId;
        }

        $released = DB::fetchOne($sql, $params);
        $releasedQty = (float)($released['total'] ?? 0);
        return round(max(0.0, (float)($order['qty'] ?? 0) - $releasedQty), 2);
    }

    private static function releasableQcQty(int $qcEntryId, int $excludeEntryId = 0): float
    {
        $qc = DB::fetchOne('SELECT pass_qty FROM qc_entries WHERE id = ? LIMIT 1', [$qcEntryId]);
        if (!$qc) {
            throw new \RuntimeException('Linked QC entry was not found.');
        }

        $params = [$qcEntryId];
        $sql = 'SELECT COALESCE(SUM(dispatchable_qty), 0) AS total
                FROM dispatch_entries
                WHERE qc_entry_id = ?
                  AND ' . self::releasedStatusSql('dispatch_status');
        if ($excludeEntryId > 0) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeEntryId;
        }

        $released = DB::fetchOne($sql, $params);
        $releasedQty = (float)($released['total'] ?? 0);
        return round(max(0.0, (float)($qc['pass_qty'] ?? 0) - $releasedQty), 2);
    }

    private static function releasedStatusSql(string $field): string
    {
        $quoted = array_map(static fn (string $status): string => "'" . strtolower($status) . "'", self::RELEASED_STATUSES);
        return 'LOWER(COALESCE(' . $field . ", '')) IN (" . implode(',', $quoted) . ')';
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function fetchDispatchEntry(int $entryId): ?array
    {
        if ($entryId <= 0) {
            return null;
        }

        return DB::fetchOne('SELECT * FROM dispatch_entries WHERE id = ? LIMIT 1', [$entryId]);
    }

    private static function actorId(?array $currentUser): ?int
    {
        $userId = (int)($currentUser['id'] ?? 0);
        if ($userId > 0) {
            return $userId;
        }

        $authUser = Auth::user();
        $authId = (int)($authUser['id'] ?? 0);
        return $authId > 0 ? $authId : null;
    }

    private static function addColumnIfMissing(string $column, string $definition): void
    {
        $escapedColumn = DB::conn()->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM dispatch_entries LIKE '{$escapedColumn}'");
        if ($exists !== null) {
            return;
        }

        DB::query('ALTER TABLE dispatch_entries ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function addIndexIfMissing(string $indexName, string $definition): void
    {
        $escapedIndex = DB::conn()->real_escape_string($indexName);
        $exists = DB::fetchOne("SHOW INDEX FROM dispatch_entries WHERE Key_name = '{$escapedIndex}'");
        if ($exists !== null) {
            return;
        }

        DB::query('ALTER TABLE dispatch_entries ADD INDEX ' . $indexName . ' ' . $definition);
    }

    private static function hasLedgerTable(): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE 'stock_ledger_entries'") !== null;
    }

    private static function hasStageReadinessTable(): bool
    {
        return DB::fetchOne("SHOW TABLES LIKE 'mfg_stage_readiness'") !== null;
    }

    /**
     * @param array<string,mixed>|null $existingRow
     */
    private static function dispatchStageReleaseAllowance(int $productId, string $dispatchDate, ?array $existingRow = null): float
    {
        $stageRow = DB::fetchOne(
            "SELECT override_status, blocked_reason, COALESCE(released_qty,0) AS released_qty
             FROM mfg_stage_readiness
             WHERE product_id = ? AND ref_date = ? AND stage = 'dispatch'
             LIMIT 1",
            [$productId, $dispatchDate]
        );

        if (!$stageRow) {
            throw new \RuntimeException('Dispatch stage is not released for this product/date. Complete QC/assembly release first.');
        }

        $override = strtolower(trim((string)($stageRow['override_status'] ?? '')));
        if ($override === 'blocked') {
            $reason = trim((string)($stageRow['blocked_reason'] ?? ''));
            throw new \RuntimeException(
                'Dispatch stage is blocked' . ($reason !== '' ? ': ' . $reason : '.')
            );
        }

        $releasedQty = round(max(0.0, (float)($stageRow['released_qty'] ?? 0)), 2);
        if ($override !== 'released' && $releasedQty <= 0.0) {
            throw new \RuntimeException('Dispatch stage has no released quantity available.');
        }

        if ($existingRow
            && (int)($existingRow['product_id'] ?? 0) === $productId
            && ((string)($existingRow['dispatch_date'] ?? '') === $dispatchDate)
            && self::countsAsReleased((string)($existingRow['dispatch_status'] ?? ''))
        ) {
            $releasedQty += round(max(0.0, (float)($existingRow['dispatchable_qty'] ?? 0)), 2);
        }

        return $releasedQty;
    }
}
