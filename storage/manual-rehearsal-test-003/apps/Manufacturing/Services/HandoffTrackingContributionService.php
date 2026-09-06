<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\AuditLogService;
use App\Core\DB;
use App\Core\HandoffEngine;
use Plugins\Base\Services\NotificationService;

if (defined('APP_ROOT')) {
    $notificationServicePath = APP_ROOT . '/plugins/Base/Services/NotificationService.php';
    if (is_file($notificationServicePath)) {
        require_once $notificationServicePath;
    }
}

final class HandoffTrackingContributionService
{
    public const STAGE_PROD_TO_QC = 'production_to_qc';
    public const STAGE_QC_TO_DISPATCH = 'qc_to_dispatch';
    public const STAGE_DISPATCH_TO_COMPLETION = 'dispatch_to_completion';
    public const STAGE_ORDER_RISK = 'order_risk';

    /**
     * @var array<int,string>
     */
    private const INVALID_QC_STATUS = ['cancelled', 'canceled'];

    /**
     * @var array<int,string>
     */
    private const ACTIVE_DISPATCH_STATUS = ['ready', 'hold', 'blocked'];

    /**
     * @var array<int,string>
     */
    private const RELEASED_DISPATCH_STATUS = ['partial', 'dispatched', 'completed', 'closed', 'delivered'];

    public static function syncProductionEntry(int $productionEntryId): void
    {
        HandoffEngine::ensureSchema();
        self::ensureTrackingTable();

        if ($productionEntryId <= 0) {
            return;
        }

        $row = DB::fetchOne(
            'SELECT id, production_date, status, good_qty FROM production_entries WHERE id = ? LIMIT 1',
            [$productionEntryId]
        );

        if (!$row) {
            self::deleteStageRow('production_entry', $productionEntryId, self::STAGE_PROD_TO_QC);
            return;
        }

        $goodQty = (float)($row['good_qty'] ?? 0);
        $status = strtolower(trim((string)($row['status'] ?? '')));
        if ($goodQty <= 0 || in_array($status, self::INVALID_QC_STATUS, true)) {
            self::markReleased('production_entry', $productionEntryId, self::STAGE_PROD_TO_QC);
            return;
        }

        $enteredAt = trim((string)($row['production_date'] ?? ''));
        if ($enteredAt === '') {
            $enteredAt = date('Y-m-d');
        }
        $enteredAt .= ' 00:00:00';

        self::upsertStage(
            'production_entry',
            $productionEntryId,
            self::STAGE_PROD_TO_QC,
            'QC Leader',
            [
                'entered_stage_at' => $enteredAt,
                'ready_since' => date('Y-m-d H:i:s'),
                'blocked_since' => null,
                'released_since' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public static function syncQcEntry(int $qcEntryId): void
    {
        HandoffEngine::ensureSchema();
        self::ensureTrackingTable();

        if ($qcEntryId <= 0) {
            return;
        }

        $row = DB::fetchOne(
            'SELECT id, pass_qty, status, created_at, updated_at FROM qc_entries WHERE id = ? LIMIT 1',
            [$qcEntryId]
        );

        if (!$row) {
            self::deleteStageRow('qc_entry', $qcEntryId, self::STAGE_QC_TO_DISPATCH);
            return;
        }

        $status = strtolower(trim((string)($row['status'] ?? '')));
        $passQty = (float)($row['pass_qty'] ?? 0);
        $dispatchedRow = DB::fetchOne(
            "SELECT COALESCE(SUM(dispatchable_qty), 0) AS total
             FROM dispatch_entries
             WHERE qc_entry_id = ?
               AND LOWER(COALESCE(dispatch_status, '')) IN ('partial', 'dispatched', 'completed', 'closed', 'delivered')",
            [$qcEntryId]
        );
        $dispatchedQty = (float)($dispatchedRow['total'] ?? 0);
        $releasableQty = max(0.0, $passQty - $dispatchedQty);

        if ($releasableQty <= 0.0 || in_array($status, self::INVALID_QC_STATUS, true)) {
            self::markReleased('qc_entry', $qcEntryId, self::STAGE_QC_TO_DISPATCH);
            return;
        }

        $enteredAt = trim((string)($row['updated_at'] ?? ''));
        if ($enteredAt === '') {
            $enteredAt = trim((string)($row['created_at'] ?? ''));
        }
        if ($enteredAt === '') {
            $enteredAt = date('Y-m-d H:i:s');
        }

        self::upsertStage(
            'qc_entry',
            $qcEntryId,
            self::STAGE_QC_TO_DISPATCH,
            'Dispatch Leader',
            [
                'entered_stage_at' => $enteredAt,
                'ready_since' => $enteredAt,
                'blocked_since' => null,
                'released_since' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public static function syncDispatchEntry(int $dispatchEntryId, ?int $linkedQcEntryId = null): void
    {
        HandoffEngine::ensureSchema();
        self::ensureTrackingTable();

        if ($dispatchEntryId <= 0) {
            return;
        }

        $row = DB::fetchOne(
            'SELECT id, dispatch_date, dispatch_status, qc_entry_id FROM dispatch_entries WHERE id = ? LIMIT 1',
            [$dispatchEntryId]
        );

        if (!$row) {
            self::deleteStageRow('dispatch_entry', $dispatchEntryId, self::STAGE_DISPATCH_TO_COMPLETION);
            if (($linkedQcEntryId ?? 0) > 0) {
                self::syncQcEntry((int)$linkedQcEntryId);
            }
            return;
        }

        $status = strtolower(trim((string)($row['dispatch_status'] ?? '')));
        $isKnownStatus = in_array($status, array_merge(self::ACTIVE_DISPATCH_STATUS, self::RELEASED_DISPATCH_STATUS), true);

        $enteredAt = trim((string)($row['dispatch_date'] ?? ''));
        if ($enteredAt === '') {
            $enteredAt = date('Y-m-d');
        }
        $enteredAt .= ' 00:00:00';

        $updates = [
            'entered_stage_at' => $enteredAt,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!$isKnownStatus) {
            $updates['blocked_since'] = date('Y-m-d H:i:s');
            $updates['ready_since'] = null;
            $updates['released_since'] = null;
            self::upsertStage(
                'dispatch_entry',
                $dispatchEntryId,
                self::STAGE_DISPATCH_TO_COMPLETION,
                'Unassigned / System issue',
                $updates
            );
        } elseif (in_array($status, self::RELEASED_DISPATCH_STATUS, true)) {
            $updates['released_since'] = date('Y-m-d H:i:s');
            $updates['ready_since'] = null;
            $updates['blocked_since'] = null;
            self::upsertStage(
                'dispatch_entry',
                $dispatchEntryId,
                self::STAGE_DISPATCH_TO_COMPLETION,
                'Dispatch Leader',
                $updates
            );
        } elseif (in_array($status, ['hold', 'blocked'], true)) {
            $updates['blocked_since'] = date('Y-m-d H:i:s');
            $updates['ready_since'] = null;
            $updates['released_since'] = null;
            self::upsertStage(
                'dispatch_entry',
                $dispatchEntryId,
                self::STAGE_DISPATCH_TO_COMPLETION,
                'Dispatch Leader',
                $updates
            );
        } else {
            $updates['ready_since'] = date('Y-m-d H:i:s');
            $updates['blocked_since'] = null;
            $updates['released_since'] = null;
            self::upsertStage(
                'dispatch_entry',
                $dispatchEntryId,
                self::STAGE_DISPATCH_TO_COMPLETION,
                'Dispatch Leader',
                $updates
            );
        }

        $qcEntryId = (int)($row['qc_entry_id'] ?? 0);
        if ($qcEntryId > 0) {
            self::syncQcEntry($qcEntryId);
        }
        if (($linkedQcEntryId ?? 0) > 0 && (int)$linkedQcEntryId !== $qcEntryId) {
            self::syncQcEntry((int)$linkedQcEntryId);
        }
    }

    /**
     * @param array<int,int> $productIds
     */
    public static function syncDailyOrderRiskForProducts(array $productIds): void
    {
        HandoffEngine::ensureSchema();
        self::ensureTrackingTable();

        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $v): bool => $v > 0)));
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $openStatus = ['closed', 'completed', 'cancelled', 'canceled'];
        $openSql = "LOWER(COALESCE(o.status, '')) NOT IN (?,?,?,?)";

        $params = array_merge($ids, $openStatus);
        $orders = DB::fetchAll(
            "SELECT
                o.id,
                o.shortage_qty,
                o.coverage_pct,
                COALESCE(hf.pending_qc_qty, 0) AS pending_qc_qty,
                COALESCE(hf.releasable_qty, 0) AS releasable_qty,
                COALESCE(hf.blocked_qty, 0) AS blocked_qty
             FROM daily_orders o
             LEFT JOIN (
                SELECT
                    x.daily_order_id,
                    SUM(x.pending_qc_qty) AS pending_qc_qty,
                    SUM(x.releasable_qty) AS releasable_qty,
                    SUM(x.blocked_qty) AS blocked_qty
                FROM (
                    SELECT
                        peo.daily_order_id,
                        GREATEST(pe.good_qty - COALESCE(qc.checked_qty, 0), 0) AS pending_qc_qty,
                        0 AS releasable_qty,
                        0 AS blocked_qty
                    FROM production_entries pe
                    INNER JOIN (
                        SELECT product_id, MIN(id) AS daily_order_id
                        FROM daily_orders
                        WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                        GROUP BY product_id
                    ) peo ON peo.product_id = pe.product_id
                    LEFT JOIN (
                        SELECT product_id, DATE(created_at) AS qc_date, SUM(checked_qty) AS checked_qty
                        FROM qc_entries
                        GROUP BY product_id, DATE(created_at)
                    ) qc ON qc.product_id = pe.product_id AND qc.qc_date = pe.production_date
                    WHERE pe.good_qty > COALESCE(qc.checked_qty, 0)

                    UNION ALL

                    SELECT
                        q.daily_order_id,
                        0 AS pending_qc_qty,
                        GREATEST(q.pass_qty - COALESCE(qd.dispatched_qty, 0), 0) AS releasable_qty,
                        0 AS blocked_qty
                    FROM qc_entries q
                    LEFT JOIN (
                        SELECT qc_entry_id, SUM(dispatchable_qty) AS dispatched_qty
                        FROM dispatch_entries
                        GROUP BY qc_entry_id
                    ) qd ON qd.qc_entry_id = q.id
                    WHERE q.pass_qty > COALESCE(qd.dispatched_qty, 0)

                    UNION ALL

                    SELECT
                        d.daily_order_id,
                        0 AS pending_qc_qty,
                        0 AS releasable_qty,
                        d.dispatchable_qty AS blocked_qty
                    FROM dispatch_entries d
                    WHERE LOWER(COALESCE(d.dispatch_status, '')) IN ('hold', 'blocked')
                ) x
                WHERE x.daily_order_id IS NOT NULL
                GROUP BY x.daily_order_id
             ) hf ON hf.daily_order_id = o.id
             WHERE o.product_id IN ({$placeholders})
               AND {$openSql}",
            $params
        );

        $riskOrderIds = [];
        foreach ($orders as $order) {
            $orderId = (int)($order['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            $shortageQty = (float)($order['shortage_qty'] ?? 0);
            $coveragePct = (float)($order['coverage_pct'] ?? 100);
            $pendingQc = (float)($order['pending_qc_qty'] ?? 0);
            $releasable = (float)($order['releasable_qty'] ?? 0);
            $blocked = (float)($order['blocked_qty'] ?? 0);

            $isRisk = $shortageQty > 0 || $coveragePct < 80 || $pendingQc > 0 || $releasable > 0 || $blocked > 0;
            if (!$isRisk) {
                self::markReleased('daily_order', $orderId, self::STAGE_ORDER_RISK);
                continue;
            }

            $defaultOwner = 'Unassigned / System issue';
            if ($blocked > 0 || $releasable > 0) {
                $defaultOwner = 'Dispatch Leader';
            } elseif ($pendingQc > 0) {
                $defaultOwner = 'QC Leader';
            } elseif ($shortageQty > 0 || $coveragePct < 80) {
                $defaultOwner = 'Machine Leader';
            }

            self::upsertStage(
                'daily_order',
                $orderId,
                self::STAGE_ORDER_RISK,
                $defaultOwner,
                [
                    'entered_stage_at' => date('Y-m-d H:i:s'),
                    'ready_since' => date('Y-m-d H:i:s'),
                    'released_since' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
            $riskOrderIds[$orderId] = true;
        }

        $productPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $releaseSql =
            "UPDATE handoff_tracking ht
             INNER JOIN daily_orders o ON o.id = ht.entity_id
             SET ht.released_since = COALESCE(ht.released_since, NOW()), ht.updated_at = NOW()
             WHERE ht.entity_type = 'daily_order'
               AND ht.stage = ?
               AND o.product_id IN ({$productPlaceholders})";

        $releaseParams = array_merge([self::STAGE_ORDER_RISK], $ids);
        if (!empty($riskOrderIds)) {
            $riskPlaceholders = implode(',', array_fill(0, count($riskOrderIds), '?'));
            $releaseSql .= " AND ht.entity_id NOT IN ({$riskPlaceholders})";
            $releaseParams = array_merge($releaseParams, array_keys($riskOrderIds));
        }

        DB::query($releaseSql, $releaseParams);
    }

    private static function markReleased(string $entityType, int $entityId, string $stage): void
    {
        $before = DB::fetchOne(
            'SELECT * FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND stage = ? LIMIT 1',
            [$entityType, $entityId, $stage]
        );

        DB::query(
            "UPDATE handoff_tracking
             SET released_since = COALESCE(released_since, NOW()), updated_at = NOW()
             WHERE entity_type = ? AND entity_id = ? AND stage = ?",
            [$entityType, $entityId, $stage]
        );

        $after = DB::fetchOne(
            'SELECT * FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND stage = ? LIMIT 1',
            [$entityType, $entityId, $stage]
        );

        $beforeReleased = trim((string)($before['released_since'] ?? ''));
        $afterReleased = trim((string)($after['released_since'] ?? ''));
        if ($beforeReleased === '' && $afterReleased !== '') {
            self::logHandoffTrackingEvent(
                $after,
                AuditLogService::ACTION_HANDOFF_COMPLETED,
                'Handoff stage released/completed by tracking sync.',
                [
                    'tracked_entity_type' => $entityType,
                    'tracked_entity_id' => $entityId,
                    'stage' => $stage,
                ]
            );
        }
    }

    private static function deleteStageRow(string $entityType, int $entityId, string $stage): void
    {
        DB::query(
            'DELETE FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND stage = ? LIMIT 1',
            [$entityType, $entityId, $stage]
        );
    }

    /**
     * @param array<string,mixed> $updates
     */
    private static function upsertStage(string $entityType, int $entityId, string $stage, string $defaultOwnerRole, array $updates): void
    {
        $before = DB::fetchOne(
            'SELECT * FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND stage = ? LIMIT 1',
            [$entityType, $entityId, $stage]
        );

        $enteredStageAtProvided = array_key_exists('entered_stage_at', $updates);
        $readySinceProvided = array_key_exists('ready_since', $updates);
        $blockedSinceProvided = array_key_exists('blocked_since', $updates);
        $releasedSinceProvided = array_key_exists('released_since', $updates);

        $enteredStageAt = $updates['entered_stage_at'] ?? null;
        $readySince = $updates['ready_since'] ?? null;
        $blockedSince = $updates['blocked_since'] ?? null;
        $releasedSince = $updates['released_since'] ?? null;
        $updatedAt = $updates['updated_at'] ?? date('Y-m-d H:i:s');

        DB::query(
            "INSERT INTO handoff_tracking
                (entity_type, entity_id, stage, owner_role, entered_stage_at, ready_since, blocked_since, released_since, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                owner_role = CASE
                    WHEN owner_role IS NULL OR owner_role = '' THEN VALUES(owner_role)
                    ELSE owner_role
                END,
                entered_stage_at = CASE
                    WHEN ? = 0 THEN entered_stage_at
                    WHEN entered_stage_at IS NULL THEN VALUES(entered_stage_at)
                    ELSE entered_stage_at
                END,
                ready_since = CASE
                    WHEN ? = 0 THEN ready_since
                    WHEN VALUES(ready_since) IS NULL THEN NULL
                    ELSE COALESCE(ready_since, VALUES(ready_since))
                END,
                blocked_since = CASE
                    WHEN ? = 0 THEN blocked_since
                    WHEN VALUES(blocked_since) IS NULL THEN NULL
                    ELSE COALESCE(blocked_since, VALUES(blocked_since))
                END,
                released_since = CASE
                    WHEN ? = 0 THEN released_since
                    ELSE VALUES(released_since)
                END,
                updated_at = VALUES(updated_at)",
            [
                $entityType,
                $entityId,
                $stage,
                $defaultOwnerRole,
                $enteredStageAt,
                $readySince,
                $blockedSince,
                $releasedSince,
                $updatedAt,
                $enteredStageAtProvided ? 1 : 0,
                $readySinceProvided ? 1 : 0,
                $blockedSinceProvided ? 1 : 0,
                $releasedSinceProvided ? 1 : 0,
            ]
        );

        $after = DB::fetchOne(
            'SELECT * FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND stage = ? LIMIT 1',
            [$entityType, $entityId, $stage]
        );

        if (class_exists(NotificationService::class)) {
            NotificationService::handleTrackingTransition(
                $entityType,
                $entityId,
                $stage,
                is_array($before) ? $before : null,
                is_array($after) ? $after : null,
                $defaultOwnerRole
            );
        }

        $beforeBlocked = trim((string)($before['blocked_since'] ?? ''));
        $afterBlocked = trim((string)($after['blocked_since'] ?? ''));
        $beforeReleased = trim((string)($before['released_since'] ?? ''));
        $afterReleased = trim((string)($after['released_since'] ?? ''));

        if (!$before && $after) {
            self::logHandoffTrackingEvent(
                $after,
                AuditLogService::ACTION_HANDOFF_CREATED,
                'Handoff stage tracking created.',
                [
                    'tracked_entity_type' => $entityType,
                    'tracked_entity_id' => $entityId,
                    'stage' => $stage,
                    'owner_role' => (string)($after['owner_role'] ?? $defaultOwnerRole),
                ]
            );
        }

        if ($beforeBlocked === '' && $afterBlocked !== '') {
            self::logHandoffTrackingEvent(
                $after,
                AuditLogService::ACTION_HANDOFF_BLOCKED,
                'Handoff stage entered blocked/escalated state.',
                [
                    'tracked_entity_type' => $entityType,
                    'tracked_entity_id' => $entityId,
                    'stage' => $stage,
                    'owner_role' => (string)($after['owner_role'] ?? $defaultOwnerRole),
                ]
            );
        }

        if ($beforeReleased === '' && $afterReleased !== '') {
            self::logHandoffTrackingEvent(
                $after,
                AuditLogService::ACTION_HANDOFF_COMPLETED,
                'Handoff stage released/completed by tracking sync.',
                [
                    'tracked_entity_type' => $entityType,
                    'tracked_entity_id' => $entityId,
                    'stage' => $stage,
                    'owner_role' => (string)($after['owner_role'] ?? $defaultOwnerRole),
                ]
            );
        }
    }

    /**
     * @param array<string,mixed>|null $row
     * @param array<string,mixed> $metadata
     */
    private static function logHandoffTrackingEvent(?array $row, string $action, string $note, array $metadata = []): void
    {
        $trackingId = (int)($row['id'] ?? 0);
        if ($trackingId <= 0) {
            return;
        }

        AuditLogService::logEvent(
            'handoff_tracking',
            $trackingId,
            AuditLogService::EVENT_SYSTEM,
            $action,
            null,
            [
                'app' => 'manufacturing',
                'module' => 'ops',
                'note' => $note,
                'metadata' => $metadata,
            ]
        );
    }

    private static function ensureTrackingTable(): void
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS handoff_tracking (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(40) NOT NULL,
                entity_id INT NOT NULL,
                stage VARCHAR(50) NOT NULL,
                owner_role VARCHAR(80) NULL,
                owner_user_id INT NULL,
                owner_display VARCHAR(190) NULL,
                owner_assigned_at DATETIME NULL,
                entered_stage_at DATETIME NULL,
                ready_since DATETIME NULL,
                blocked_since DATETIME NULL,
                released_since DATETIME NULL,
                escalation_level VARCHAR(10) NULL,
                escalation_state VARCHAR(80) NULL,
                escalated_at DATETIME NULL,
                notes TEXT NULL,
                updated_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_handoff_target (entity_type, entity_id, stage),
                KEY idx_handoff_stage (stage),
                KEY idx_handoff_owner_role (owner_role),
                KEY idx_handoff_escalated_at (escalated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
}
