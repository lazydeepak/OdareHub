<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\AuditLogService;
use App\Core\Auth;
use App\Core\DB;
use App\Core\HandoffEngine;
use App\Core\PackageManager;
use App\Services\AppLegacyBridgeService;
use Plugins\Base\Services\NotificationService;
use Plugins\DispatchEntries\Services\DispatchLeaderDashboardService;
use Plugins\Machines\Services\MachineLeaderDashboardService;
use Plugins\QCEntries\Services\QcLeaderDashboardService;

if (!class_exists(MachineLeaderDashboardService::class)) {
    require_once rtrim(PackageManager::pluginSourcePath('Machines'), '/') . '/Services/MachineLeaderDashboardService.php';
}
if (!class_exists(QcLeaderDashboardService::class)) {
    require_once rtrim(PackageManager::pluginSourcePath('QCEntries'), '/') . '/Services/QcLeaderDashboardService.php';
}
if (!class_exists(DispatchLeaderDashboardService::class)) {
    require_once rtrim(PackageManager::pluginSourcePath('DispatchEntries'), '/') . '/Services/DispatchLeaderDashboardService.php';
}
require_once APP_ROOT . '/plugins/Base/Services/NotificationService.php';

final class HandoffBoardService
{
    private const STAGE_PROD_TO_QC = 'production_to_qc';
    private const STAGE_QC_TO_DISPATCH = 'qc_to_dispatch';
    private const STAGE_DISPATCH_TO_COMPLETION = 'dispatch_to_completion';
    private const STAGE_ORDER_RISK = 'order_risk';

    /**
     * @var array<string,array{warn:int,breach:int,label:string}>
     */
    private const SLA_HOURS = [
        'production_planning' => ['warn' => 4, 'breach' => 12, 'label' => 'Production Planning'],
        self::STAGE_PROD_TO_QC => ['warn' => 4, 'breach' => 12, 'label' => 'Waiting QC'],
        'assembly_execution' => ['warn' => 4, 'breach' => 12, 'label' => 'Assembly Execution'],
        self::STAGE_QC_TO_DISPATCH => ['warn' => 2, 'breach' => 8, 'label' => 'QC Release'],
        self::STAGE_DISPATCH_TO_COMPLETION => ['warn' => 4, 'breach' => 12, 'label' => 'Dispatch Hold'],
        self::STAGE_ORDER_RISK => ['warn' => 12, 'breach' => 24, 'label' => 'Order Risk'],
    ];

    public static function canonicalUrl(): string
    {
        return '/apps/manufacturing/handoffs';
    }

    public static function legacyUrl(): string
    {
        return '/ops/handoff-board';
    }

    public static function isAvailable(): bool
    {
        return AppLegacyBridgeService::manufacturingAppEnabled();
    }

    public static function canonicalUrlWithQueryFromLegacy(string $legacyUrl): string
    {
        $query = parse_url($legacyUrl, PHP_URL_QUERY);
        $fragment = parse_url($legacyUrl, PHP_URL_FRAGMENT);

        $target = self::canonicalUrl();
        if (is_string($query) && $query !== '') {
            $target .= '?' . $query;
        }
        if (is_string($fragment) && $fragment !== '') {
            $target .= '#' . $fragment;
        }

        return $target;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function build(array $input, ?array $user = null): array
    {
        self::ensureTrackingTable();

        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $date = self::normalizeDate((string)($input['date'] ?? $today), $today);

        $waitingForQc = self::fetchWaitingForQc($date, $now);
        $qcPassedNotReleased = self::fetchQcPassedNotReleased($date, $now);
        $dispatchHoldBlocked = self::fetchDispatchHoldBlocked($date, $now);
        $agingWork = self::buildAgingWork($waitingForQc, $qcPassedNotReleased, $dispatchHoldBlocked);
        $highRisk = self::fetchHighRiskOrders($date, $now);

        $roleBottlenecks = self::buildRoleBottlenecks(
            $waitingForQc,
            $qcPassedNotReleased,
            $dispatchHoldBlocked,
            $highRisk
        );

        $kpi = [
            'waiting_for_qc' => count($waitingForQc),
            'qc_passed_not_released' => count($qcPassedNotReleased),
            'dispatch_hold_blocked' => count($dispatchHoldBlocked),
            'aging_handoffs' => count($agingWork),
            'high_risk_orders' => count($highRisk),
            'sla_warning_items' => self::countBySlaLevel($agingWork, 'warning'),
            'sla_breach_items' => self::countBySlaLevel($agingWork, 'breach'),
            'explicit_owner_items' => self::countExplicitOwners($waitingForQc, $qcPassedNotReleased, $dispatchHoldBlocked),
        ];

        // Transform role labels for display in final output
        self::transformRowsForDisplay($waitingForQc);
        self::transformRowsForDisplay($qcPassedNotReleased);
        self::transformRowsForDisplay($dispatchHoldBlocked);
        self::transformRowsForDisplay($highRisk);

        return [
            'today' => $today,
            'date' => $date,
            'kpi' => $kpi,
            'sla_policy' => self::SLA_HOURS,
            'owner_options' => self::ownerOptions(),
            'waiting_for_qc' => array_slice($waitingForQc, 0, 100),
            'qc_passed_not_released' => array_slice($qcPassedNotReleased, 0, 100),
            'dispatch_hold_blocked' => array_slice($dispatchHoldBlocked, 0, 100),
            'aging_handoffs' => array_slice($agingWork, 0, 100),
            'bottlenecks_by_role' => $roleBottlenecks,
            'high_risk_orders' => array_slice($highRisk, 0, 100),
            'role_snapshots' => self::buildRoleSnapshots($date, $user),
            'ownership_overview' => array_slice(HandoffEngine::activeOverview(120), 0, 40),
        ];
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function assignOwner(array $input): void
    {
        self::ensureTrackingTable();

        $entityType = trim((string)($input['entity_type'] ?? ''));
        $entityId = (int)($input['entity_id'] ?? 0);
        $stage = trim((string)($input['stage'] ?? ''));

        if (!self::isValidTrackingTarget($entityType, $entityId, $stage)) {
            return;
        }

        $ownerRole = trim((string)($input['owner_role'] ?? ''));
        $ownerDisplay = trim((string)($input['owner_display'] ?? ''));
        $ownerUserId = (int)($input['owner_user_id'] ?? 0);
        if ($ownerRole === 'Unassigned / System issue') {
            $ownerRole = 'Unassigned / System issue';
            $ownerDisplay = '';
            $ownerUserId = 0;
        }

        $before = DB::fetchOne(
            'SELECT owner_role, owner_user_id FROM handoff_tracking WHERE entity_type=? AND entity_id=? AND stage=? LIMIT 1',
            [$entityType, $entityId, $stage]
        );

        DB::query(
            "INSERT INTO handoff_tracking
                (entity_type, entity_id, stage, owner_role, owner_user_id, owner_display, owner_assigned_at, entered_stage_at, updated_at)
             VALUES (?,?,?,?,?,?,NOW(),COALESCE(?, NOW()),NOW())
             ON DUPLICATE KEY UPDATE
                owner_role=VALUES(owner_role),
                owner_user_id=VALUES(owner_user_id),
                owner_display=VALUES(owner_display),
                owner_assigned_at=NOW(),
                updated_at=NOW()",
            [
                $entityType,
                $entityId,
                $stage,
                $ownerRole !== '' ? $ownerRole : null,
                $ownerUserId > 0 ? $ownerUserId : null,
                $ownerDisplay !== '' ? $ownerDisplay : null,
                trim((string)($input['entered_stage_at'] ?? '')) !== '' ? (string)$input['entered_stage_at'] : null,
            ]
        );

        $tracking = DB::fetchOne(
            'SELECT id, owner_role, owner_user_id, owner_display FROM handoff_tracking WHERE entity_type=? AND entity_id=? AND stage=? LIMIT 1',
            [$entityType, $entityId, $stage]
        );
        $beforeRole = trim((string)($before['owner_role'] ?? ''));
        $beforeUserId = (int)($before['owner_user_id'] ?? 0);
        $afterRole = $ownerRole;
        $afterUserId = $ownerUserId;

        $trackingId = (int)($tracking['id'] ?? 0);
        if ($trackingId > 0 && ($beforeRole !== $afterRole || $beforeUserId !== $afterUserId)) {
            AuditLogService::logEvent(
                'handoff_tracking',
                $trackingId,
                AuditLogService::EVENT_ASSIGNMENT,
                AuditLogService::ACTION_HANDOFF_ACCEPTED,
                Auth::user(),
                [
                    'app' => 'manufacturing',
                    'module' => 'ops',
                    'note' => 'Handoff owner assigned/updated from board.',
                    'metadata' => [
                        'tracked_entity_type' => $entityType,
                        'tracked_entity_id' => $entityId,
                        'stage' => $stage,
                        'before_owner_role' => $beforeRole,
                        'after_owner_role' => $afterRole,
                        'before_owner_user_id' => $beforeUserId,
                        'after_owner_user_id' => $afterUserId,
                        'owner_display' => (string)($tracking['owner_display'] ?? ''),
                    ],
                ]
            );
        }

        if (class_exists(NotificationService::class) && ($beforeRole !== $afterRole || $beforeUserId !== $afterUserId) && $afterRole !== '') {
            NotificationService::notifyAssigned(
                $entityType,
                $entityId,
                $stage,
                $afterRole,
                $afterUserId
            );
        }
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function escalate(array $input): void
    {
        self::ensureTrackingTable();

        $entityType = trim((string)($input['entity_type'] ?? ''));
        $entityId = (int)($input['entity_id'] ?? 0);
        $stage = trim((string)($input['stage'] ?? ''));
        if (!self::isValidTrackingTarget($entityType, $entityId, $stage)) {
            return;
        }

        $level = strtoupper(trim((string)($input['escalation_level'] ?? 'L1')));
        if (!in_array($level, ['L1', 'L2', 'L3'], true)) {
            $level = 'L1';
        }

        $state = trim((string)($input['escalation_state'] ?? 'Escalated'));
        if ($state === '') {
            $state = 'Escalated';
        }

        DB::query(
            "INSERT INTO handoff_tracking
                (entity_type, entity_id, stage, escalation_level, escalation_state, escalated_at, blocked_since, entered_stage_at, updated_at)
             VALUES (?,?,?,?,?,NOW(),NOW(),NOW(),NOW())
             ON DUPLICATE KEY UPDATE
                escalation_level=VALUES(escalation_level),
                escalation_state=VALUES(escalation_state),
                escalated_at=NOW(),
                blocked_since=COALESCE(blocked_since, NOW()),
                updated_at=NOW()",
            [$entityType, $entityId, $stage, $level, $state]
        );

        $tracking = DB::fetchOne(
            'SELECT id, owner_role, owner_user_id FROM handoff_tracking WHERE entity_type=? AND entity_id=? AND stage=? LIMIT 1',
            [$entityType, $entityId, $stage]
        );
        $trackingId = (int)($tracking['id'] ?? 0);
        if ($trackingId > 0) {
            AuditLogService::logEvent(
                'handoff_tracking',
                $trackingId,
                AuditLogService::EVENT_WORKFLOW,
                AuditLogService::ACTION_HANDOFF_BLOCKED,
                Auth::user(),
                [
                    'app' => 'manufacturing',
                    'module' => 'ops',
                    'note' => 'Handoff escalated from board.',
                    'metadata' => [
                        'tracked_entity_type' => $entityType,
                        'tracked_entity_id' => $entityId,
                        'stage' => $stage,
                        'escalation_level' => $level,
                        'escalation_state' => $state,
                    ],
                ]
            );
        }

        $row = DB::fetchOne(
            'SELECT owner_role, owner_user_id FROM handoff_tracking WHERE entity_type=? AND entity_id=? AND stage=? LIMIT 1',
            [$entityType, $entityId, $stage]
        );
        $recipientRole = trim((string)($row['owner_role'] ?? ''));
        if ($recipientRole === '') {
            $recipientRole = self::defaultOwnerForStage($stage);
        }
        if (class_exists(NotificationService::class) && $recipientRole !== '') {
            NotificationService::notifyEscalated(
                $entityType,
                $entityId,
                $stage,
                $recipientRole,
                (int)($row['owner_user_id'] ?? 0),
                $level,
                $state
            );
        }
    }

    private static function defaultOwnerForStage(string $stage): string
    {
        if ($stage === self::STAGE_PROD_TO_QC) {
            return 'QC';
        }
        if ($stage === self::STAGE_QC_TO_DISPATCH || $stage === self::STAGE_DISPATCH_TO_COMPLETION) {
            return 'Dispatch';
        }
        if ($stage === self::STAGE_ORDER_RISK) {
            return 'Production';
        }
        return '';
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

    private static function isValidTrackingTarget(string $entityType, int $entityId, string $stage): bool
    {
        if ($entityId <= 0) {
            return false;
        }

        $allowedEntityTypes = ['production_plan', 'production_entry', 'assembly_plan', 'qc_entry', 'dispatch_entry', 'daily_order'];
        if (!in_array($entityType, $allowedEntityTypes, true)) {
            return false;
        }

        return isset(self::SLA_HOURS[$stage]);
    }

    private static function normalizeDate(string $date, string $fallback): string
    {
        if ($date === '') {
            return $fallback;
        }
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        if (!$dt || $dt->format('Y-m-d') !== $date) {
            return $fallback;
        }
        return $date;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchWaitingForQc(string $date, string $now): array
    {
        $rows = DB::fetchAll(
            "SELECT
                pe.id AS production_entry_id,
                pe.production_date,
                pe.product_id,
                p.parts_name,
                p.parts_number,
                pe.good_qty,
                COALESCE(qe.checked_qty, 0) AS checked_qty,
                GREATEST(pe.good_qty - COALESCE(qe.checked_qty, 0), 0) AS pending_qc_qty,
                COALESCE(ht.owner_role, '') AS owner_role,
                COALESCE(ht.owner_display, '') AS owner_display,
                COALESCE(ht.owner_user_id, 0) AS owner_user_id,
                ht.entered_stage_at,
                ht.blocked_since,
                ht.ready_since,
                ht.released_since,
                ht.escalation_level,
                ht.escalation_state,
                ht.escalated_at,
                COALESCE(ord.daily_order_id, 0) AS daily_order_id,
                COALESCE(ord.shortage_qty, 0) AS shortage_qty,
                COALESCE(ord.coverage_pct, 100) AS coverage_pct
            FROM production_entries pe
            INNER JOIN products p ON p.id = pe.product_id
            LEFT JOIN (
                SELECT
                    product_id,
                    DATE(created_at) AS qc_date,
                    SUM(checked_qty) AS checked_qty
                FROM qc_entries
                GROUP BY product_id, DATE(created_at)
            ) qe ON qe.product_id = pe.product_id
               AND qe.qc_date = pe.production_date
            LEFT JOIN handoff_tracking ht
               ON ht.entity_type='production_entry'
              AND ht.entity_id=pe.id
              AND ht.stage='" . self::STAGE_PROD_TO_QC . "'
            LEFT JOIN (
                SELECT
                    product_id,
                    MIN(id) AS daily_order_id,
                    SUM(COALESCE(shortage_qty, 0)) AS shortage_qty,
                    MIN(COALESCE(coverage_pct, 100)) AS coverage_pct
                FROM daily_orders
                WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                GROUP BY product_id
            ) ord ON ord.product_id = pe.product_id
            WHERE pe.good_qty > COALESCE(qe.checked_qty, 0)
            ORDER BY pe.production_date ASC, pending_qc_qty DESC, pe.id ASC
            LIMIT 250"
        );

        foreach ($rows as &$row) {
            $fallbackOwner = 'QC Leader';
            $derivedStageSince = (string)($row['production_date'] ?? $date) . ' 00:00:00';
            self::decorateTrackingRow($row, self::STAGE_PROD_TO_QC, $fallbackOwner, $derivedStageSince, $now);
            $row['handoff_stage'] = 'Production -> QC';
            $row['entity_type'] = 'production_entry';
            $row['entity_id'] = (int)$row['production_entry_id'];
            $row['stage_key'] = self::STAGE_PROD_TO_QC;
            $row['next_action'] = '/qc-entries/add?production_entry_id=' . (int)$row['production_entry_id']
                . '&product_id=' . (int)$row['product_id']
                . '&daily_order_id=' . (int)$row['daily_order_id']
                . '&status=Open';
            $row['action_label'] = 'Open QC Entry';
            $row['escalate_action'] = '/manufacturing/production-workboard';
            $row['escalate_label'] = 'Open Machine Board';
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchQcPassedNotReleased(string $date, string $now): array
    {
        $rows = DB::fetchAll(
            "SELECT
                q.id AS qc_entry_id,
                q.product_id,
                q.daily_order_id,
                p.parts_name,
                p.parts_number,
                q.pass_qty,
                COALESCE(disp.dispatched_qty, 0) AS dispatched_qty,
                GREATEST(q.pass_qty - COALESCE(disp.dispatched_qty, 0), 0) AS releasable_qty,
                DATE(COALESCE(q.updated_at, q.created_at)) AS qc_updated_date,
                COALESCE(ht.owner_role, '') AS owner_role,
                COALESCE(ht.owner_display, '') AS owner_display,
                COALESCE(ht.owner_user_id, 0) AS owner_user_id,
                ht.entered_stage_at,
                ht.blocked_since,
                ht.ready_since,
                ht.released_since,
                ht.escalation_level,
                ht.escalation_state,
                ht.escalated_at,
                COALESCE(o.shortage_qty, 0) AS shortage_qty,
                COALESCE(o.coverage_pct, 100) AS coverage_pct
            FROM qc_entries q
            INNER JOIN products p ON p.id = q.product_id
            LEFT JOIN (
                SELECT qc_entry_id, SUM(dispatchable_qty) AS dispatched_qty
                FROM dispatch_entries
                GROUP BY qc_entry_id
            ) disp ON disp.qc_entry_id = q.id
            LEFT JOIN handoff_tracking ht
               ON ht.entity_type='qc_entry'
              AND ht.entity_id=q.id
              AND ht.stage='" . self::STAGE_QC_TO_DISPATCH . "'
            LEFT JOIN daily_orders o ON o.id = q.daily_order_id
            WHERE q.pass_qty > COALESCE(disp.dispatched_qty, 0)
              AND LOWER(COALESCE(q.status, '')) NOT IN ('cancelled', 'canceled')
            ORDER BY releasable_qty DESC, q.id DESC
            LIMIT 250"
        );

        foreach ($rows as &$row) {
            $fallbackOwner = 'Dispatch Leader';
            $derivedStageSince = (string)($row['qc_updated_date'] ?? $date) . ' 00:00:00';
            self::decorateTrackingRow($row, self::STAGE_QC_TO_DISPATCH, $fallbackOwner, $derivedStageSince, $now);
            $row['handoff_stage'] = 'QC -> Dispatch';
            $row['entity_type'] = 'qc_entry';
            $row['entity_id'] = (int)$row['qc_entry_id'];
            $row['stage_key'] = self::STAGE_QC_TO_DISPATCH;
            $row['next_action'] = '/dispatch-entries/add?dispatch_date=' . rawurlencode($date)
                . '&qc_entry_id=' . (int)$row['qc_entry_id']
                . '&product_id=' . (int)$row['product_id']
                . '&daily_order_id=' . (int)$row['daily_order_id']
                . '&dispatch_status=Ready'
                . '&dispatchable_qty=' . rawurlencode((string)$row['releasable_qty']);
            $row['action_label'] = 'Release to Dispatch';
            $row['escalate_action'] = '/qc-entries/edit?id=' . (int)$row['qc_entry_id'];
            $row['escalate_label'] = 'Open QC Entry';
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDispatchHoldBlocked(string $date, string $now): array
    {
        $rows = DB::fetchAll(
            "SELECT
                d.id AS dispatch_entry_id,
                d.dispatch_date,
                d.daily_order_id,
                d.product_id,
                p.parts_name,
                p.parts_number,
                d.dispatch_status,
                d.dispatchable_qty,
                d.remarks,
                COALESCE(ht.owner_role, '') AS owner_role,
                COALESCE(ht.owner_display, '') AS owner_display,
                COALESCE(ht.owner_user_id, 0) AS owner_user_id,
                ht.entered_stage_at,
                ht.blocked_since,
                ht.ready_since,
                ht.released_since,
                ht.escalation_level,
                ht.escalation_state,
                ht.escalated_at,
                COALESCE(o.qty, 0) AS order_qty,
                COALESCE(o.shortage_qty, 0) AS shortage_qty,
                COALESCE(o.coverage_pct, 100) AS coverage_pct,
                COALESCE(o.dispatch_deadline, NULL) AS dispatch_deadline
            FROM dispatch_entries d
            INNER JOIN products p ON p.id = d.product_id
            LEFT JOIN handoff_tracking ht
               ON ht.entity_type='dispatch_entry'
              AND ht.entity_id=d.id
              AND ht.stage='" . self::STAGE_DISPATCH_TO_COMPLETION . "'
            LEFT JOIN daily_orders o ON o.id = d.daily_order_id
            WHERE LOWER(COALESCE(d.dispatch_status, '')) IN ('hold', 'blocked')
            ORDER BY d.dispatch_date ASC, d.id DESC
            LIMIT 250"
        );

        foreach ($rows as &$row) {
            $shortage = (float)($row['shortage_qty'] ?? 0);
            $status = strtolower((string)($row['dispatch_status'] ?? ''));
            $fallbackOwner = $shortage > 0 ? 'Machine Leader' : 'Dispatch Leader';
            $derivedStageSince = (string)($row['dispatch_date'] ?? $date) . ' 00:00:00';
            self::decorateTrackingRow($row, self::STAGE_DISPATCH_TO_COMPLETION, $fallbackOwner, $derivedStageSince, $now);

            if ($row['owner_explicit'] !== true) {
                if ($shortage > 0) {
                    $row['owner_reason'] = 'Blocked by shortage risk';
                } elseif ($status === 'blocked') {
                    $row['owner_reason'] = 'Dispatch flow blocked';
                } else {
                    $row['owner_reason'] = 'Dispatch hold requires release decision';
                }
            }

            $row['handoff_stage'] = 'Dispatch -> Order Completion';
            $row['entity_type'] = 'dispatch_entry';
            $row['entity_id'] = (int)$row['dispatch_entry_id'];
            $row['stage_key'] = self::STAGE_DISPATCH_TO_COMPLETION;
            $row['next_action'] = '/dispatch-entries/edit?id=' . (int)$row['dispatch_entry_id'];
            $row['action_label'] = 'Open Dispatch Entry';
            $row['escalate_action'] = $shortage > 0
                ? '/manufacturing/production-workboard'
                : '/apps/manufacturing/dispatch-ops';
            $row['escalate_label'] = $shortage > 0 ? 'Open Machine Board' : 'Open Dispatch Ops';
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function decorateTrackingRow(array &$row, string $stage, string $fallbackOwner, string $derivedStageSince, string $now): void
    {
        $ownerRole = trim((string)($row['owner_role'] ?? ''));
        $ownerDisplay = trim((string)($row['owner_display'] ?? ''));
        $ownerUserId = (int)($row['owner_user_id'] ?? 0);

        $resolvedOwner = $ownerDisplay !== '' ? $ownerDisplay : ($ownerRole !== '' ? $ownerRole : $fallbackOwner);
        $row['owner'] = $resolvedOwner;
        $row['owner_explicit'] = ($ownerRole !== '' || $ownerDisplay !== '' || $ownerUserId > 0);

        if ($row['owner_explicit']) {
            $row['owner_reason'] = 'Explicit assignment';
        } else {
            $row['owner_reason'] = 'Inferred from stage logic';
        }

        $enteredStageAt = trim((string)($row['entered_stage_at'] ?? ''));
        if ($enteredStageAt === '') {
            $enteredStageAt = $derivedStageSince;
        }
        $row['stage_since_at'] = $enteredStageAt;

        $ageHours = self::hoursDiff($enteredStageAt, $now);
        $row['age_hours'] = $ageHours;
        $row['age_days'] = (int)floor($ageHours / 24);

        $row['sla'] = self::evaluateSla($stage, $ageHours);
        $row['sla_level'] = (string)($row['sla']['level'] ?? 'ok');
        $row['sla_label'] = (string)($row['sla']['label'] ?? 'Within SLA');

        $escalatedAt = trim((string)($row['escalated_at'] ?? ''));
        if ($escalatedAt !== '') {
            $level = trim((string)($row['escalation_level'] ?? 'L1'));
            $state = trim((string)($row['escalation_state'] ?? 'Escalated'));
            $row['escalation_state_text'] = $state . ' ' . $level . ' since ' . $escalatedAt;
        } else {
            $row['escalation_state_text'] = 'Not escalated';
        }
    }

    /**
     * @param array<int,array<string,mixed>> $waitingForQc
     * @param array<int,array<string,mixed>> $qcPassedNotReleased
     * @param array<int,array<string,mixed>> $dispatchHoldBlocked
     * @return array<int,array<string,mixed>>
     */
    private static function buildAgingWork(array $waitingForQc, array $qcPassedNotReleased, array $dispatchHoldBlocked): array
    {
        $rows = [];

        foreach ($waitingForQc as $row) {
            $rows[] = [
                'stage' => 'Production -> QC',
                'owner' => displayRole((string)($row['owner'] ?? 'QC Leader')),
                'owner_explicit' => (bool)($row['owner_explicit'] ?? false),
                'item_ref' => 'PE-' . (int)($row['production_entry_id'] ?? 0),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'age_hours' => self::toFloat($row['age_hours'] ?? 0),
                'age_days' => (int)($row['age_days'] ?? 0),
                'qty' => self::toFloat($row['pending_qc_qty'] ?? 0),
                'next_action' => (string)($row['next_action'] ?? '/manufacturing/qc-workboard'),
                'action_label' => (string)($row['action_label'] ?? 'Open'),
                'sla_level' => (string)($row['sla_level'] ?? 'ok'),
                'sla_label' => (string)($row['sla_label'] ?? 'Within SLA'),
                'escalation_state_text' => (string)($row['escalation_state_text'] ?? 'Not escalated'),
                'entity_type' => 'production_entry',
                'entity_id' => (int)($row['production_entry_id'] ?? 0),
                'stage_key' => self::STAGE_PROD_TO_QC,
            ];
        }

        foreach ($qcPassedNotReleased as $row) {
            $rows[] = [
                'stage' => 'QC -> Dispatch',
                'owner' => displayRole((string)($row['owner'] ?? 'Dispatch Leader')),
                'owner_explicit' => (bool)($row['owner_explicit'] ?? false),
                'item_ref' => 'QC-' . (int)($row['qc_entry_id'] ?? 0),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'age_hours' => self::toFloat($row['age_hours'] ?? 0),
                'age_days' => (int)($row['age_days'] ?? 0),
                'qty' => self::toFloat($row['releasable_qty'] ?? 0),
                'next_action' => (string)($row['next_action'] ?? '/apps/manufacturing/dispatch-ops'),
                'action_label' => (string)($row['action_label'] ?? 'Open'),
                'sla_level' => (string)($row['sla_level'] ?? 'ok'),
                'sla_label' => (string)($row['sla_label'] ?? 'Within SLA'),
                'escalation_state_text' => (string)($row['escalation_state_text'] ?? 'Not escalated'),
                'entity_type' => 'qc_entry',
                'entity_id' => (int)($row['qc_entry_id'] ?? 0),
                'stage_key' => self::STAGE_QC_TO_DISPATCH,
            ];
        }

        foreach ($dispatchHoldBlocked as $row) {
            $rows[] = [
                'stage' => 'Dispatch -> Completion',
                'owner' => displayRole((string)($row['owner'] ?? 'Dispatch Leader')),
                'owner_explicit' => (bool)($row['owner_explicit'] ?? false),
                'item_ref' => 'DE-' . (int)($row['dispatch_entry_id'] ?? 0),
                'parts_number' => (string)($row['parts_number'] ?? ''),
                'parts_name' => (string)($row['parts_name'] ?? ''),
                'age_hours' => self::toFloat($row['age_hours'] ?? 0),
                'age_days' => (int)($row['age_days'] ?? 0),
                'qty' => self::toFloat($row['dispatchable_qty'] ?? 0),
                'next_action' => (string)($row['next_action'] ?? '/apps/manufacturing/dispatch-ops'),
                'action_label' => (string)($row['action_label'] ?? 'Open'),
                'sla_level' => (string)($row['sla_level'] ?? 'ok'),
                'sla_label' => (string)($row['sla_label'] ?? 'Within SLA'),
                'escalation_state_text' => (string)($row['escalation_state_text'] ?? 'Not escalated'),
                'entity_type' => 'dispatch_entry',
                'entity_id' => (int)($row['dispatch_entry_id'] ?? 0),
                'stage_key' => self::STAGE_DISPATCH_TO_COMPLETION,
            ];
        }

        usort($rows, static function(array $a, array $b): int {
            $severityRank = ['breach' => 3, 'warning' => 2, 'ok' => 1];
            $ar = $severityRank[(string)($a['sla_level'] ?? 'ok')] ?? 0;
            $br = $severityRank[(string)($b['sla_level'] ?? 'ok')] ?? 0;
            if ($ar !== $br) {
                return $br <=> $ar;
            }
            $ageCmp = self::toFloat($b['age_hours'] ?? 0) <=> self::toFloat($a['age_hours'] ?? 0);
            if ($ageCmp !== 0) {
                return $ageCmp;
            }
            return self::toFloat($b['qty'] ?? 0) <=> self::toFloat($a['qty'] ?? 0);
        });

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchHighRiskOrders(string $date, string $now): array
    {
        $rows = DB::fetchAll(
            "SELECT
                o.id AS daily_order_id,
                o.product_id,
                p.parts_name,
                p.parts_number,
                o.qty AS demand_qty,
                COALESCE(o.shortage_qty, 0) AS shortage_qty,
                COALESCE(o.coverage_pct, 100) AS coverage_pct,
                GREATEST(COALESCE(o.qty, 0) - COALESCE(disp.dispatched_qty, 0), 0) AS outstanding_dispatch_qty,
                COALESCE(hf.pending_qc_qty, 0) AS pending_qc_qty,
                COALESCE(hf.releasable_qty, 0) AS releasable_dispatch_qty,
                COALESCE(hf.blocked_qty, 0) AS blocked_dispatch_qty,
                COALESCE(ht.owner_role, '') AS owner_role,
                COALESCE(ht.owner_display, '') AS owner_display,
                COALESCE(ht.owner_user_id, 0) AS owner_user_id,
                ht.entered_stage_at,
                ht.escalation_level,
                ht.escalation_state,
                ht.escalated_at,
                TIMESTAMPDIFF(HOUR, NOW(), COALESCE(o.dispatch_deadline, CONCAT(COALESCE(o.required_date, ?), ' 23:59:59'))) AS hours_to_due
            FROM daily_orders o
            INNER JOIN products p ON p.id = o.product_id
            LEFT JOIN (
                SELECT
                    daily_order_id,
                    SUM(CASE WHEN LOWER(COALESCE(dispatch_status, '')) IN ('partial', 'dispatched', 'completed', 'closed', 'delivered') THEN dispatchable_qty ELSE 0 END) AS dispatched_qty
                FROM dispatch_entries
                GROUP BY daily_order_id
            ) disp ON disp.daily_order_id = o.id
            LEFT JOIN handoff_tracking ht
               ON ht.entity_type='daily_order'
              AND ht.entity_id=o.id
              AND ht.stage='" . self::STAGE_ORDER_RISK . "'
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
            WHERE LOWER(COALESCE(o.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
              AND (
                    COALESCE(o.shortage_qty, 0) > 0
                 OR COALESCE(o.coverage_pct, 100) < 80
                 OR COALESCE(hf.pending_qc_qty, 0) > 0
                 OR COALESCE(hf.releasable_qty, 0) > 0
                 OR COALESCE(hf.blocked_qty, 0) > 0
              )
            ORDER BY shortage_qty DESC, outstanding_dispatch_qty DESC, o.id DESC
            LIMIT 250",
            [$date]
        );

        foreach ($rows as &$row) {
            $blockedQty = self::toFloat($row['blocked_dispatch_qty'] ?? 0);
            $releasableQty = self::toFloat($row['releasable_dispatch_qty'] ?? 0);
            $pendingQcQty = self::toFloat($row['pending_qc_qty'] ?? 0);
            $shortageQty = self::toFloat($row['shortage_qty'] ?? 0);

            $ownerRole = trim((string)($row['owner_role'] ?? ''));
            $ownerDisplay = trim((string)($row['owner_display'] ?? ''));
            $ownerUserId = (int)($row['owner_user_id'] ?? 0);
            $explicitOwner = ($ownerRole !== '' || $ownerDisplay !== '' || $ownerUserId > 0);

            if ($explicitOwner) {
                $row['owner'] = $ownerDisplay !== '' ? $ownerDisplay : $ownerRole;
                $row['owner_reason'] = 'Explicit assignment';
                $row['owner_explicit'] = true;
            } elseif ($blockedQty > 0 || $releasableQty > 0) {
                $row['owner'] = 'Dispatch Leader';
                $row['owner_reason'] = $blockedQty > 0 ? 'Dispatch blocked/hold' : 'QC passed but not released';
                $row['owner_explicit'] = false;
            } elseif ($pendingQcQty > 0) {
                $row['owner'] = 'QC Leader';
                $row['owner_reason'] = 'Produced quantity waiting QC';
                $row['owner_explicit'] = false;
            } elseif ($shortageQty > 0) {
                $row['owner'] = 'Machine Leader';
                $row['owner_reason'] = 'Shortage and low coverage';
                $row['owner_explicit'] = false;
            } else {
                $row['owner'] = 'Unassigned / System issue';
                $row['owner_reason'] = 'Manual triage required';
                $row['owner_explicit'] = false;
            }

            $row['next_action'] = self::ownerAction((string)$row['owner']);
            $row['action_label'] = 'Open Owner Board';
            $row['entity_type'] = 'daily_order';
            $row['entity_id'] = (int)($row['daily_order_id'] ?? 0);
            $row['stage_key'] = self::STAGE_ORDER_RISK;

            $enteredAt = trim((string)($row['entered_stage_at'] ?? ''));
            if ($enteredAt === '') {
                $enteredAt = $date . ' 00:00:00';
            }
            $ageHours = self::hoursDiff($enteredAt, $now);
            $row['age_hours'] = $ageHours;
            $row['sla'] = self::evaluateSla(self::STAGE_ORDER_RISK, $ageHours, (int)($row['hours_to_due'] ?? 9999));
            $row['sla_level'] = (string)($row['sla']['level'] ?? 'ok');
            $row['sla_label'] = (string)($row['sla']['label'] ?? 'Within SLA');

            $escalatedAt = trim((string)($row['escalated_at'] ?? ''));
            if ($escalatedAt !== '') {
                $row['escalation_state_text'] = trim((string)($row['escalation_state'] ?? 'Escalated'))
                    . ' ' . trim((string)($row['escalation_level'] ?? 'L1'))
                    . ' since ' . $escalatedAt;
            } else {
                $row['escalation_state_text'] = 'Not escalated';
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $waitingForQc
     * @param array<int,array<string,mixed>> $qcPassedNotReleased
     * @param array<int,array<string,mixed>> $dispatchHoldBlocked
     * @param array<int,array<string,mixed>> $highRisk
     * @return array<int,array<string,mixed>>
     */
    private static function buildRoleBottlenecks(
        array $waitingForQc,
        array $qcPassedNotReleased,
        array $dispatchHoldBlocked,
        array $highRisk
    ): array {
        $roles = [
            'Machine Leader' => ['items' => 0, 'qty' => 0.0, 'high_risk' => 0, 'explicit' => 0],
            'QC Leader' => ['items' => 0, 'qty' => 0.0, 'high_risk' => 0, 'explicit' => 0],
            'Dispatch Leader' => ['items' => 0, 'qty' => 0.0, 'high_risk' => 0, 'explicit' => 0],
            'Unassigned / System issue' => ['items' => 0, 'qty' => 0.0, 'high_risk' => 0, 'explicit' => 0],
        ];

        foreach ([$waitingForQc, $qcPassedNotReleased, $dispatchHoldBlocked] as $bucket) {
            foreach ($bucket as $row) {
                $owner = (string)($row['owner'] ?? 'Unassigned / System issue');
                if (!isset($roles[$owner])) {
                    $owner = 'Unassigned / System issue';
                }
                $qty = self::toFloat($row['pending_qc_qty'] ?? ($row['releasable_qty'] ?? ($row['dispatchable_qty'] ?? 0)));
                $roles[$owner]['items']++;
                $roles[$owner]['qty'] += $qty;
                if ((bool)($row['owner_explicit'] ?? false)) {
                    $roles[$owner]['explicit']++;
                }
            }
        }

        foreach ($highRisk as $row) {
            if ((string)($row['sla_level'] ?? 'ok') !== 'breach') {
                continue;
            }
            $owner = (string)($row['owner'] ?? 'Unassigned / System issue');
            if (!isset($roles[$owner])) {
                $owner = 'Unassigned / System issue';
            }
            $roles[$owner]['high_risk']++;
        }

        $result = [];
        foreach ($roles as $owner => $metrics) {
            $result[] = [
                'owner' => displayRole($owner),
                'blocked_items' => (int)$metrics['items'],
                'blocked_qty' => round((float)$metrics['qty'], 2),
                'high_risk_orders' => (int)$metrics['high_risk'],
                'explicit_owned_items' => (int)$metrics['explicit'],
                'next_action' => self::ownerAction($owner),
            ];
        }

        usort($result, static function(array $a, array $b): int {
            if ((int)$a['blocked_items'] !== (int)$b['blocked_items']) {
                return ((int)$b['blocked_items']) <=> ((int)$a['blocked_items']);
            }
            return ((int)$b['high_risk_orders']) <=> ((int)$a['high_risk_orders']);
        });

        return $result;
    }

    private static function transformRowsForDisplay(array &$rows): void
    {
        foreach ($rows as &$row) {
            if (isset($row['owner']) && is_string($row['owner'])) {
                $row['owner'] = displayRole($row['owner']);
            }
        }
        unset($row);
    }

    private static function ownerAction(string $owner): string
    {
        if ($owner === 'Machine Leader') {
            return '/machines/leader';
        }
        if ($owner === 'QC Leader') {
            return '/qc-entries/leader';
        }
        if ($owner === 'Dispatch Leader') {
            return '/dispatch-entries/leader';
        }
        return '/apps/manufacturing/daily-orders';
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    private static function buildRoleSnapshots(string $date, ?array $user): array
    {
        $machine = [];
        $qc = [];
        $dispatch = [];

        try {
            $machine = MachineLeaderDashboardService::build(['date' => $date], $user);
        } catch (\Throwable $e) {
            $machine = [];
        }

        try {
            $qc = QcLeaderDashboardService::build(['date' => $date], $user);
        } catch (\Throwable $e) {
            $qc = [];
        }

        try {
            $dispatch = DispatchLeaderDashboardService::build(['date' => $date], $user);
        } catch (\Throwable $e) {
            $dispatch = [];
        }

        return [
            'machine' => [
                'run_now' => (int)($machine['kpi']['run_now_jobs'] ?? 0),
                'waiting_qc' => (int)($machine['kpi']['waiting_qc_jobs'] ?? 0),
                'delayed' => (int)($machine['kpi']['delayed_jobs'] ?? 0),
            ],
            'assembly' => [
                'waiting_for_me' => (int)(HandoffEngine::workboardForOwner('Assembly Leader')['kpi']['waiting_for_me'] ?? 0),
                'overdue' => (int)(HandoffEngine::workboardForOwner('Assembly Leader')['kpi']['overdue'] ?? 0),
                'blocked' => (int)(HandoffEngine::workboardForOwner('Assembly Leader')['kpi']['blocked'] ?? 0),
            ],
            'qc' => [
                'run_now' => (int)($qc['kpi']['run_now'] ?? 0),
                'pending_qc' => (int)($qc['kpi']['pending_qc'] ?? 0),
                'ready_dispatch' => (int)($qc['kpi']['ready_dispatch'] ?? 0),
            ],
            'dispatch' => [
                'ready_now' => (int)($dispatch['kpi']['ready_now'] ?? 0),
                'blocked_hold' => (int)($dispatch['kpi']['blocked_hold'] ?? 0),
                'aging' => (int)($dispatch['kpi']['aging_overdue'] ?? 0),
            ],
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function ownerOptions(): array
    {
        return [
            'Machine Leader' => displayRole('Machine Leader'),
            'Assembly Leader' => displayRole('Assembly Leader'),
            'QC Leader' => displayRole('QC Leader'),
            'Dispatch Leader' => displayRole('Dispatch Leader'),
            'App Admin' => 'App Admin',
            'Unassigned / System issue' => 'Unassigned / System issue',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function evaluateSla(string $stage, float $ageHours, ?int $hoursToDue = null): array
    {
        $policy = self::SLA_HOURS[$stage] ?? ['warn' => 4, 'breach' => 12, 'label' => 'Stage'];
        $warn = (int)$policy['warn'];
        $breach = (int)$policy['breach'];

        $level = 'ok';
        if ($ageHours >= $breach) {
            $level = 'breach';
        } elseif ($ageHours >= $warn) {
            $level = 'warning';
        }

        if ($stage === self::STAGE_ORDER_RISK && $hoursToDue !== null) {
            if ($hoursToDue <= 2) {
                $level = 'breach';
            } elseif ($hoursToDue <= 12 && $level === 'ok') {
                $level = 'warning';
            }
        }

        $label = 'Within SLA';
        if ($level === 'warning') {
            $label = 'SLA Warning';
        } elseif ($level === 'breach') {
            $label = 'SLA Breach';
        }

        return [
            'policy' => $policy,
            'warn_at_hours' => $warn,
            'breach_at_hours' => $breach,
            'level' => $level,
            'label' => $label,
        ];
    }

    private static function hoursDiff(string $fromTs, string $toTs): float
    {
        try {
            $from = new \DateTime($fromTs);
            $to = new \DateTime($toTs);
        } catch (\Throwable $e) {
            return 0.0;
        }

        $sec = max(0, $to->getTimestamp() - $from->getTimestamp());
        return round($sec / 3600, 2);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function countBySlaLevel(array $rows, string $level): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if ((string)($row['sla_level'] ?? '') === $level) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * @param array<int,array<string,mixed>> $waitingForQc
     * @param array<int,array<string,mixed>> $qcPassedNotReleased
     * @param array<int,array<string,mixed>> $dispatchHoldBlocked
     */
    private static function countExplicitOwners(array $waitingForQc, array $qcPassedNotReleased, array $dispatchHoldBlocked): int
    {
        $count = 0;
        foreach ([$waitingForQc, $qcPassedNotReleased, $dispatchHoldBlocked] as $bucket) {
            foreach ($bucket as $row) {
                if ((bool)($row['owner_explicit'] ?? false)) {
                    $count++;
                }
            }
        }
        return $count;
    }

    /**
     * @param mixed $value
     */
    private static function toFloat($value): float
    {
        return round((float)$value, 2);
    }
}
