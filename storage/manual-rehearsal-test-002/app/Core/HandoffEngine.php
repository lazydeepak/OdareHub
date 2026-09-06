<?php
declare(strict_types=1);

namespace App\Core;

final class HandoffEngine
{
    public const STATE_PENDING_ASSIGNMENT = 'pending_assignment';
    public const STATE_ASSIGNED = 'assigned';
    public const STATE_IN_PROGRESS = 'in_progress';
    public const STATE_WAITING_UPSTREAM = 'waiting_upstream';
    public const STATE_WAITING_DOWNSTREAM = 'waiting_downstream';
    public const STATE_BLOCKED = 'blocked';
    public const STATE_COMPLETED = 'completed';
    public const STATE_ESCALATED = 'escalated';

    public const SLA_ON_TRACK = 'on_track';
    public const SLA_AT_RISK = 'at_risk';
    public const SLA_OVERDUE = 'overdue';
    public const SLA_BREACHED = 'breached';

    /**
     * @var array<string,array<string,mixed>>
     */
    private const STAGE_DEFINITIONS = [
        'daily_order:demand_ready' => [
            'label' => 'Demand Ready',
            'default_owner_role' => 'Machine Leader',
            'next_stage' => 'production_planning',
            'next_stage_label' => 'Production Planning',
            'next_owner_role' => 'Machine Leader',
            'next_owner_profile' => 'manufacturing_planning',
            'warn' => 8,
            'breach' => 24,
        ],
        'daily_order:order_risk' => [
            'label' => 'Order Risk',
            'default_owner_role' => 'Machine Leader',
            'next_stage' => 'production_planning',
            'next_stage_label' => 'Production Planning',
            'next_owner_role' => 'Machine Leader',
            'next_owner_profile' => 'manufacturing_planning',
            'warn' => 12,
            'breach' => 24,
        ],
        'production_plan:production_planning' => [
            'label' => 'Production Planning',
            'default_owner_role' => 'Machine Leader',
            'next_stage' => 'production_execution',
            'next_stage_label' => 'Production Execution',
            'next_owner_role' => 'Machine Leader',
            'next_owner_profile' => 'production_operations',
            'warn' => 4,
            'breach' => 12,
        ],
        'production_entry:production_to_qc' => [
            'label' => 'Waiting QC',
            'default_owner_role' => 'QC Leader',
            'next_stage' => 'qc_execution',
            'next_stage_label' => 'QC Execution',
            'next_owner_role' => 'QC Leader',
            'next_owner_profile' => 'qc_operations',
            'warn' => 4,
            'breach' => 12,
        ],
        'assembly_plan:assembly_execution' => [
            'label' => 'Assembly Execution',
            'default_owner_role' => 'Assembly Leader',
            'next_stage' => 'qc_execution',
            'next_stage_label' => 'QC Execution',
            'next_owner_role' => 'QC Leader',
            'next_owner_profile' => 'qc_operations',
            'warn' => 4,
            'breach' => 12,
        ],
        'qc_entry:qc_to_dispatch' => [
            'label' => 'QC Release',
            'default_owner_role' => 'Dispatch Leader',
            'next_stage' => 'dispatch_preparation',
            'next_stage_label' => 'Dispatch Preparation',
            'next_owner_role' => 'Dispatch Leader',
            'next_owner_profile' => 'dispatch_operations',
            'warn' => 2,
            'breach' => 8,
        ],
        'dispatch_entry:dispatch_to_completion' => [
            'label' => 'Dispatch Completion',
            'default_owner_role' => 'Dispatch Leader',
            'next_stage' => 'completed',
            'next_stage_label' => 'Completed',
            'next_owner_role' => 'Dispatch Leader',
            'next_owner_profile' => 'dispatch_operations',
            'warn' => 4,
            'breach' => 12,
        ],
    ];

    private static bool $schemaReady = false;

    /** @var null|callable(int,string):array<string,mixed> */
    private static $assemblyStageContextResolver = null;

    /** @var null|callable(int,string):float */
    private static $assemblyCompletedQtyResolver = null;

    /**
     * Register app-owned assembly resolvers.
     *
     * Core stays business-domain agnostic and invokes these only when present.
     *
     * @param null|callable(int,string):array<string,mixed> $stageContextResolver
     * @param null|callable(int,string):float $completedQtyResolver
     */
    public static function setAssemblyResolvers($stageContextResolver, $completedQtyResolver): void
    {
        self::$assemblyStageContextResolver = is_callable($stageContextResolver) ? $stageContextResolver : null;
        self::$assemblyCompletedQtyResolver = is_callable($completedQtyResolver) ? $completedQtyResolver : null;
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

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
                owner_authority_role VARCHAR(40) NULL,
                owner_profile_key VARCHAR(120) NULL,
                ownership_state VARCHAR(40) NULL,
                next_stage VARCHAR(80) NULL,
                next_owner_role VARCHAR(80) NULL,
                next_owner_profile VARCHAR(120) NULL,
                entered_stage_at DATETIME NULL,
                ready_since DATETIME NULL,
                waiting_since DATETIME NULL,
                in_progress_since DATETIME NULL,
                blocked_since DATETIME NULL,
                released_since DATETIME NULL,
                completed_at DATETIME NULL,
                escalation_level VARCHAR(10) NULL,
                escalation_state VARCHAR(80) NULL,
                escalated_at DATETIME NULL,
                sla_warning_hours INT NULL,
                sla_breach_hours INT NULL,
                sla_state VARCHAR(20) NULL,
                notes TEXT NULL,
                updated_at DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_handoff_target (entity_type, entity_id, stage),
                KEY idx_handoff_stage (stage),
                KEY idx_handoff_owner_role (owner_role),
                KEY idx_handoff_escalated_at (escalated_at),
                KEY idx_handoff_state (ownership_state),
                KEY idx_handoff_sla_state (sla_state)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::addTrackingColumnIfMissing('owner_authority_role', "ALTER TABLE handoff_tracking ADD COLUMN owner_authority_role VARCHAR(40) NULL AFTER owner_assigned_at");
        self::addTrackingColumnIfMissing('owner_profile_key', "ALTER TABLE handoff_tracking ADD COLUMN owner_profile_key VARCHAR(120) NULL AFTER owner_authority_role");
        self::addTrackingColumnIfMissing('ownership_state', "ALTER TABLE handoff_tracking ADD COLUMN ownership_state VARCHAR(40) NULL AFTER owner_profile_key");
        self::addTrackingColumnIfMissing('next_stage', "ALTER TABLE handoff_tracking ADD COLUMN next_stage VARCHAR(80) NULL AFTER ownership_state");
        self::addTrackingColumnIfMissing('next_owner_role', "ALTER TABLE handoff_tracking ADD COLUMN next_owner_role VARCHAR(80) NULL AFTER next_stage");
        self::addTrackingColumnIfMissing('next_owner_profile', "ALTER TABLE handoff_tracking ADD COLUMN next_owner_profile VARCHAR(120) NULL AFTER next_owner_role");
        self::addTrackingColumnIfMissing('waiting_since', "ALTER TABLE handoff_tracking ADD COLUMN waiting_since DATETIME NULL AFTER ready_since");
        self::addTrackingColumnIfMissing('in_progress_since', "ALTER TABLE handoff_tracking ADD COLUMN in_progress_since DATETIME NULL AFTER waiting_since");
        self::addTrackingColumnIfMissing('completed_at', "ALTER TABLE handoff_tracking ADD COLUMN completed_at DATETIME NULL AFTER released_since");
        self::addTrackingColumnIfMissing('sla_warning_hours', "ALTER TABLE handoff_tracking ADD COLUMN sla_warning_hours INT NULL AFTER escalated_at");
        self::addTrackingColumnIfMissing('sla_breach_hours', "ALTER TABLE handoff_tracking ADD COLUMN sla_breach_hours INT NULL AFTER sla_warning_hours");
        self::addTrackingColumnIfMissing('sla_state', "ALTER TABLE handoff_tracking ADD COLUMN sla_state VARCHAR(20) NULL AFTER sla_breach_hours");

        self::$schemaReady = true;
    }

    public static function syncDailyOrder(int $dailyOrderId): void
    {
        self::ensureSchema();

        $row = DB::fetchOne('SELECT * FROM daily_orders WHERE id = ? LIMIT 1', [$dailyOrderId]);
        if (!$row) {
            self::releaseTracking('daily_order', $dailyOrderId, 'demand_ready');
            return;
        }

        $status = strtolower(trim((string)($row['status'] ?? 'open')));
        if (in_array($status, ['closed', 'completed', 'cancelled', 'canceled'], true)) {
            self::releaseTracking('daily_order', $dailyOrderId, 'demand_ready');
            return;
        }

        $enteredAt = self::normalizeTimestamp((string)($row['created_at'] ?? ''), (string)($row['order_date'] ?? ''), true);
        $waitingSince = $enteredAt;
        $state = ((float)($row['shortage_qty'] ?? 0) > 0 || (float)($row['coverage_pct'] ?? 100) < 100)
            ? self::STATE_WAITING_UPSTREAM
            : self::STATE_IN_PROGRESS;

        self::upsertTracking(
            'daily_order',
            $dailyOrderId,
            'demand_ready',
            [
                'entered_stage_at' => $enteredAt,
                'waiting_since' => $waitingSince,
                'in_progress_since' => $state === self::STATE_IN_PROGRESS ? $enteredAt : null,
                'ownership_state' => $state,
                'notes' => 'Demand intake is active for this order.',
            ]
        );
    }

    public static function syncProductionPlan(int $planId): void
    {
        self::ensureSchema();

        $row = DB::fetchOne('SELECT * FROM production_plans WHERE id = ? LIMIT 1', [$planId]);
        if (!$row) {
            self::releaseTracking('production_plan', $planId, 'production_planning');
            return;
        }

        $status = strtolower(trim((string)($row['status'] ?? 'draft')));
        if (in_array($status, ['completed', 'cancelled', 'canceled'], true)) {
            self::releaseTracking('production_plan', $planId, 'production_planning');
            return;
        }

        $approvalStatus = strtolower(trim((string)($row['approval_status'] ?? 'draft')));
        $enteredAt = self::normalizeTimestamp((string)($row['created_at'] ?? ''), (string)($row['plan_date'] ?? ''), true);
        $waitingSince = null;
        $inProgressSince = $enteredAt;
        $state = self::STATE_IN_PROGRESS;
        $nextOwnerRole = 'Machine Leader';
        $nextOwnerProfile = 'production_operations';
        $notes = 'Production planning is active.';

        if ($approvalStatus === 'pending approval') {
            $state = self::STATE_WAITING_DOWNSTREAM;
            $waitingSince = self::normalizeTimestamp((string)($row['updated_at'] ?? ''), (string)($row['plan_date'] ?? ''), true);
            $inProgressSince = null;
            $nextOwnerRole = 'App Admin';
            $nextOwnerProfile = 'manufacturing_approval';
            $notes = 'Production plan is waiting for governance approval.';
        } elseif (in_array($approvalStatus, ['rejected', 'reopened'], true)) {
            $state = self::STATE_WAITING_UPSTREAM;
            $waitingSince = self::normalizeTimestamp((string)($row['updated_at'] ?? ''), (string)($row['plan_date'] ?? ''), true);
            $notes = 'Production plan needs planning rework.';
        }

        self::upsertTracking(
            'production_plan',
            $planId,
            'production_planning',
            [
                'entered_stage_at' => $enteredAt,
                'waiting_since' => $waitingSince,
                'in_progress_since' => $inProgressSince,
                'ownership_state' => $state,
                'next_owner_role' => $nextOwnerRole,
                'next_owner_profile' => $nextOwnerProfile,
                'notes' => $notes,
            ]
        );
    }

    public static function syncAssemblyPlan(int $assemblyPlanId): void
    {
        self::ensureSchema();

        $row = DB::fetchOne('SELECT * FROM mfg_part_demands WHERE id = ? AND demand_type = ? LIMIT 1', [$assemblyPlanId, 'assembly']);
        if (!$row) {
            self::releaseTracking('assembly_plan', $assemblyPlanId, 'assembly_execution');
            return;
        }

        $effectiveQty = self::effectiveDemandQty($row);
        $completedQty = self::assemblyCompletedQty((int)($row['product_id'] ?? 0), (string)($row['demand_date'] ?? ''));
        if ($effectiveQty <= 0 || $completedQty >= $effectiveQty) {
            self::releaseTracking('assembly_plan', $assemblyPlanId, 'assembly_execution');
            return;
        }

        $stageContext = self::assemblyStageContext((int)($row['product_id'] ?? 0), (string)($row['demand_date'] ?? ''));
        $enteredAt = self::normalizeTimestamp('', (string)($row['demand_date'] ?? ''), true);
        $state = self::STATE_IN_PROGRESS;
        $waitingSince = null;
        $inProgressSince = $enteredAt;
        $notes = 'Assembly execution is active.';

        if (!empty($stageContext['block_reasons'])) {
            $state = self::STATE_BLOCKED;
            $waitingSince = $enteredAt;
            $inProgressSince = null;
            $notes = 'Assembly is blocked by upstream/downstream stage constraints.';
        } elseif (!empty($stageContext['waiting_upstream'])) {
            $state = self::STATE_WAITING_UPSTREAM;
            $waitingSince = $enteredAt;
            $inProgressSince = null;
            $notes = 'Assembly is waiting for upstream release.';
        } elseif ($completedQty > 0) {
            $state = self::STATE_WAITING_DOWNSTREAM;
            $waitingSince = self::normalizeTimestamp((string)($row['updated_at'] ?? ''), (string)($row['demand_date'] ?? ''), true);
            $inProgressSince = null;
            $notes = 'Assembly output is waiting for downstream QC release.';
        }

        self::upsertTracking(
            'assembly_plan',
            $assemblyPlanId,
            'assembly_execution',
            [
                'entered_stage_at' => $enteredAt,
                'waiting_since' => $waitingSince,
                'in_progress_since' => $inProgressSince,
                'ownership_state' => $state,
                'notes' => $notes,
            ]
        );
    }

    public static function summaryForEntity(string $entityType, int $entityId): array
    {
        self::ensureSchema();
        $rows = DB::fetchAll(
            'SELECT * FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND released_since IS NULL ORDER BY updated_at DESC, id DESC',
            [$entityType, $entityId]
        );

        return self::buildSummary($rows);
    }

    /**
     * @param array<int,array<string,mixed>> $refs
     */
    public static function summaryForChain(array $refs): array
    {
        self::ensureSchema();

        if ($refs === []) {
            return ['current' => null, 'active' => [], 'metrics' => self::emptyMetrics()];
        }

        $clauses = [];
        $params = [];
        foreach ($refs as $ref) {
            $entityType = trim((string)($ref['entity_type'] ?? ''));
            $entityId = (int)($ref['entity_id'] ?? 0);
            if ($entityType === '' || $entityId <= 0) {
                continue;
            }
            $clauses[] = '(entity_type = ? AND entity_id = ?)';
            $params[] = $entityType;
            $params[] = $entityId;
        }

        if ($clauses === []) {
            return ['current' => null, 'active' => [], 'metrics' => self::emptyMetrics()];
        }

        $rows = DB::fetchAll(
            'SELECT * FROM handoff_tracking WHERE released_since IS NULL AND (' . implode(' OR ', $clauses) . ') ORDER BY updated_at DESC, id DESC',
            $params
        );

        return self::buildSummary($rows);
    }

    public static function workboardForOwner(string $ownerRole, int $limit = 12): array
    {
        self::ensureSchema();

        $rows = DB::fetchAll(
            'SELECT * FROM handoff_tracking WHERE released_since IS NULL ORDER BY updated_at DESC, id DESC LIMIT 300'
        );
        $summaries = self::summariesForRows($rows);

        $ownerRole = strtolower(trim($ownerRole));
        $filtered = array_values(array_filter($summaries, static function (array $row) use ($ownerRole): bool {
            return strtolower(trim((string)($row['owner_role'] ?? ''))) === $ownerRole;
        }));

        $waiting = array_values(array_filter($filtered, static function (array $row): bool {
            return in_array((string)($row['ownership_state'] ?? ''), [
                self::STATE_PENDING_ASSIGNMENT,
                self::STATE_ASSIGNED,
                self::STATE_WAITING_UPSTREAM,
                self::STATE_WAITING_DOWNSTREAM,
            ], true);
        }));
        $owned = array_values(array_filter($filtered, static function (array $row): bool {
            return !empty($row['owner_display']) || (int)($row['owner_user_id'] ?? 0) > 0;
        }));
        $overdue = array_values(array_filter($filtered, static function (array $row): bool {
            return in_array((string)($row['sla_state'] ?? ''), [self::SLA_OVERDUE, self::SLA_BREACHED], true);
        }));
        $blocked = array_values(array_filter($filtered, static function (array $row): bool {
            return in_array((string)($row['ownership_state'] ?? ''), [self::STATE_BLOCKED, self::STATE_ESCALATED], true);
        }));

        return [
            'items_waiting_for_me' => array_slice($waiting, 0, $limit),
            'items_i_own' => array_slice($owned, 0, $limit),
            'items_overdue' => array_slice($overdue, 0, $limit),
            'blocked_items' => array_slice($blocked, 0, $limit),
            'kpi' => [
                'waiting_for_me' => count($waiting),
                'owned_by_me' => count($owned),
                'overdue' => count($overdue),
                'blocked' => count($blocked),
            ],
        ];
    }

    public static function activeOverview(int $limit = 100): array
    {
        self::ensureSchema();
        $rows = DB::fetchAll('SELECT * FROM handoff_tracking WHERE released_since IS NULL ORDER BY updated_at DESC, id DESC LIMIT 400');
        $summaries = self::summariesForRows($rows);

        usort($summaries, static function (array $a, array $b): int {
            $rank = [self::SLA_BREACHED => 0, self::SLA_OVERDUE => 1, self::SLA_AT_RISK => 2, self::SLA_ON_TRACK => 3];
            $aRank = $rank[(string)($a['sla_state'] ?? self::SLA_ON_TRACK)] ?? 3;
            $bRank = $rank[(string)($b['sla_state'] ?? self::SLA_ON_TRACK)] ?? 3;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            return ((float)($b['age_hours'] ?? 0.0)) <=> ((float)($a['age_hours'] ?? 0.0));
        });

        return array_slice($summaries, 0, $limit);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function buildSummary(array $rows): array
    {
        $summaries = self::summariesForRows($rows);
        return [
            'current' => $summaries[0] ?? null,
            'active' => $summaries,
            'metrics' => self::metricsForSummaries($summaries),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function summariesForRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $trackingIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows), static fn(int $id): bool => $id > 0));
        $auditMap = self::lastAuditByTrackingIds($trackingIds);
        $sourceMeta = self::sourceMetadataForRows($rows);
        $out = [];

        foreach ($rows as $row) {
            $row = self::applyDefaults($row);
            $trackingId = (int)($row['id'] ?? 0);
            $sourceKey = self::sourceKey((string)($row['entity_type'] ?? ''), (int)($row['entity_id'] ?? 0));
            $source = $sourceMeta[$sourceKey] ?? [];
            $summary = self::toSummary($row, $source, $auditMap[$trackingId] ?? []);
            if ((string)($summary['ownership_state'] ?? '') === self::STATE_COMPLETED) {
                continue;
            }
            $out[] = $summary;
        }

        usort($out, static function (array $a, array $b): int {
            $rank = [self::SLA_BREACHED => 0, self::SLA_OVERDUE => 1, self::SLA_AT_RISK => 2, self::SLA_ON_TRACK => 3];
            $aRank = $rank[(string)($a['sla_state'] ?? self::SLA_ON_TRACK)] ?? 3;
            $bRank = $rank[(string)($b['sla_state'] ?? self::SLA_ON_TRACK)] ?? 3;
            if ($aRank !== $bRank) {
                return $aRank <=> $bRank;
            }
            return ((float)($b['age_hours'] ?? 0.0)) <=> ((float)($a['age_hours'] ?? 0.0));
        });

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $source
     * @param array<string,mixed> $audit
     * @return array<string,mixed>
     */
    private static function toSummary(array $row, array $source, array $audit): array
    {
        $state = self::normalizeOwnershipState((string)($row['ownership_state'] ?? ''), $row);
        $cfg = self::stageDefinition((string)($row['entity_type'] ?? ''), (string)($row['stage'] ?? ''));
        $ownerRole = trim((string)($row['owner_role'] ?? ''));
        if ($ownerRole === '') {
            $ownerRole = (string)($cfg['default_owner_role'] ?? 'Unassigned / System issue');
        }
        $now = date('Y-m-d H:i:s');
        $since = self::currentSinceAt($row, $state, $now);
        $ageHours = self::hoursBetween($since, $now);
        $sla = self::evaluateSla($row, $cfg, $ageHours);
        $nextStage = trim((string)($row['next_stage'] ?? ''));
        if ($nextStage === '') {
            $nextStage = (string)($cfg['next_stage'] ?? '');
        }

        return [
            'tracking_id' => (int)($row['id'] ?? 0),
            'entity_type' => (string)($row['entity_type'] ?? ''),
            'entity_id' => (int)($row['entity_id'] ?? 0),
            'item_ref' => (string)($source['item_ref'] ?? self::fallbackItemRef((string)($row['entity_type'] ?? ''), (int)($row['entity_id'] ?? 0))),
            'parts_name' => (string)($source['parts_name'] ?? ''),
            'parts_number' => (string)($source['parts_number'] ?? ''),
            'qty' => (float)($source['qty'] ?? 0.0),
            'detail_url' => (string)($source['detail_url'] ?? self::fallbackDetailUrl((string)($row['entity_type'] ?? ''), (int)($row['entity_id'] ?? 0))),
            'record_status' => (string)($source['status'] ?? ''),
            'stage' => (string)($row['stage'] ?? ''),
            'stage_label' => (string)($cfg['label'] ?? (string)($row['stage'] ?? '')),
            'ownership_state' => $state,
            'ownership_state_label' => self::stateLabel($state),
            'owner_role' => $ownerRole,
            'owner_display' => (string)($row['owner_display'] ?? ''),
            'owner_user_id' => (int)($row['owner_user_id'] ?? 0),
            'owner_authority_role' => (string)($row['owner_authority_role'] ?? ''),
            'owner_profile_key' => (string)($row['owner_profile_key'] ?? ''),
            'current_owner_label' => self::ownerLabel($ownerRole, (string)($row['owner_display'] ?? '')),
            'next_stage' => $nextStage,
            'next_stage_label' => (string)($row['next_stage'] ?: ($cfg['next_stage_label'] ?? $nextStage)),
            'next_owner_role' => (string)($row['next_owner_role'] ?: ($cfg['next_owner_role'] ?? '')),
            'next_owner_profile' => (string)($row['next_owner_profile'] ?: ($cfg['next_owner_profile'] ?? '')),
            'entered_stage_at' => (string)($row['entered_stage_at'] ?? ''),
            'waiting_since' => (string)($row['waiting_since'] ?? ''),
            'in_progress_since' => (string)($row['in_progress_since'] ?? ''),
            'blocked_since' => (string)($row['blocked_since'] ?? ''),
            'released_since' => (string)($row['released_since'] ?? ''),
            'current_since_at' => $since,
            'age_hours' => $ageHours,
            'waiting_hours' => self::hoursBetween((string)($row['waiting_since'] ?? ''), $now),
            'in_progress_hours' => self::hoursBetween((string)($row['in_progress_since'] ?? ''), $now),
            'sla_state' => $sla['state'],
            'sla_label' => $sla['label'],
            'sla_level' => $sla['level'],
            'sla_warning_hours' => $sla['warn'],
            'sla_breach_hours' => $sla['breach'],
            'escalation_level' => (string)($row['escalation_level'] ?? ''),
            'escalation_state' => (string)($row['escalation_state'] ?? ''),
            'escalation_indicator' => trim((string)($row['escalation_state'] ?? '')) !== '' ? (string)($row['escalation_state'] ?? '') : 'Not escalated',
            'last_handoff_action' => (string)($audit['action_name'] ?? ''),
            'last_handoff_at' => (string)($audit['created_at'] ?? ''),
        ];
    }

    /**
     * @param array<string,mixed> $updates
     */
    private static function upsertTracking(string $entityType, int $entityId, string $stage, array $updates): void
    {
        $cfg = self::stageDefinition($entityType, $stage);
        $existing = DB::fetchOne('SELECT * FROM handoff_tracking WHERE entity_type = ? AND entity_id = ? AND stage = ? LIMIT 1', [$entityType, $entityId, $stage]) ?: [];

        $ownerRole = trim((string)($existing['owner_role'] ?? ''));
        if ($ownerRole === '') {
            $ownerRole = (string)($cfg['default_owner_role'] ?? 'Unassigned / System issue');
        }

        $ownerUserId = (int)($existing['owner_user_id'] ?? 0);
        $ownerContext = self::ownerContext($ownerUserId);
        $waitingSince = self::normalizeTimestamp((string)($updates['waiting_since'] ?? ''), '', false);
        $inProgressSince = self::normalizeTimestamp((string)($updates['in_progress_since'] ?? ''), '', false);
        $blockedSince = self::normalizeTimestamp((string)($updates['blocked_since'] ?? ''), '', false);
        $releasedSince = self::normalizeTimestamp((string)($updates['released_since'] ?? ''), '', false);
        $enteredAt = self::normalizeTimestamp((string)($updates['entered_stage_at'] ?? ''), '', false);
        $state = trim((string)($updates['ownership_state'] ?? ''));
        if ($state === '') {
            $state = self::normalizeOwnershipState((string)($existing['ownership_state'] ?? ''), [
                'waiting_since' => $waitingSince,
                'in_progress_since' => $inProgressSince,
                'blocked_since' => $blockedSince,
                'released_since' => $releasedSince,
                'owner_role' => $ownerRole,
                'owner_user_id' => $ownerUserId,
                'owner_display' => (string)($existing['owner_display'] ?? ''),
                'escalated_at' => (string)($existing['escalated_at'] ?? ''),
            ]);
        }

        $sla = self::evaluateSla(
            [
                'sla_warning_hours' => (int)($existing['sla_warning_hours'] ?? 0),
                'sla_breach_hours' => (int)($existing['sla_breach_hours'] ?? 0),
                'escalated_at' => (string)($existing['escalated_at'] ?? ''),
            ],
            $cfg,
            self::hoursBetween(self::currentSinceAt([
                'waiting_since' => $waitingSince,
                'in_progress_since' => $inProgressSince,
                'blocked_since' => $blockedSince,
                'released_since' => $releasedSince,
                'entered_stage_at' => $enteredAt,
                'updated_at' => (string)($existing['updated_at'] ?? date('Y-m-d H:i:s')),
                'created_at' => (string)($existing['created_at'] ?? date('Y-m-d H:i:s')),
            ], $state, date('Y-m-d H:i:s')), date('Y-m-d H:i:s'))
        );

        DB::query(
            "INSERT INTO handoff_tracking
                (entity_type, entity_id, stage, owner_role, owner_user_id, owner_display, owner_assigned_at, owner_authority_role, owner_profile_key, ownership_state, next_stage, next_owner_role, next_owner_profile, entered_stage_at, ready_since, waiting_since, in_progress_since, blocked_since, released_since, completed_at, sla_warning_hours, sla_breach_hours, sla_state, notes, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
                owner_role = CASE WHEN COALESCE(NULLIF(owner_role, ''), '') = '' THEN VALUES(owner_role) ELSE owner_role END,
                owner_user_id = CASE WHEN COALESCE(owner_user_id, 0) = 0 THEN VALUES(owner_user_id) ELSE owner_user_id END,
                owner_display = CASE WHEN COALESCE(NULLIF(owner_display, ''), '') = '' THEN VALUES(owner_display) ELSE owner_display END,
                owner_assigned_at = CASE WHEN owner_assigned_at IS NULL THEN VALUES(owner_assigned_at) ELSE owner_assigned_at END,
                owner_authority_role = CASE WHEN COALESCE(NULLIF(owner_authority_role, ''), '') = '' THEN VALUES(owner_authority_role) ELSE owner_authority_role END,
                owner_profile_key = CASE WHEN COALESCE(NULLIF(owner_profile_key, ''), '') = '' THEN VALUES(owner_profile_key) ELSE owner_profile_key END,
                ownership_state = VALUES(ownership_state),
                next_stage = VALUES(next_stage),
                next_owner_role = VALUES(next_owner_role),
                next_owner_profile = VALUES(next_owner_profile),
                entered_stage_at = COALESCE(entered_stage_at, VALUES(entered_stage_at)),
                waiting_since = VALUES(waiting_since),
                in_progress_since = VALUES(in_progress_since),
                blocked_since = VALUES(blocked_since),
                released_since = VALUES(released_since),
                completed_at = VALUES(completed_at),
                sla_warning_hours = VALUES(sla_warning_hours),
                sla_breach_hours = VALUES(sla_breach_hours),
                sla_state = VALUES(sla_state),
                notes = VALUES(notes),
                updated_at = VALUES(updated_at)",
            [
                $entityType,
                $entityId,
                $stage,
                $ownerRole,
                $ownerUserId > 0 ? $ownerUserId : null,
                trim((string)($existing['owner_display'] ?? '')) !== '' ? (string)$existing['owner_display'] : null,
                trim((string)($existing['owner_assigned_at'] ?? '')) !== '' ? (string)$existing['owner_assigned_at'] : null,
                $ownerContext['authority_role'] !== '' ? $ownerContext['authority_role'] : null,
                $ownerContext['profile_key'] !== '' ? $ownerContext['profile_key'] : null,
                $state,
                (string)($updates['next_stage'] ?? ($cfg['next_stage'] ?? '')),
                (string)($updates['next_owner_role'] ?? ($cfg['next_owner_role'] ?? '')),
                (string)($updates['next_owner_profile'] ?? ($cfg['next_owner_profile'] ?? '')),
                $enteredAt !== '' ? $enteredAt : null,
                $waitingSince !== '' ? $waitingSince : null,
                $waitingSince !== '' ? $waitingSince : null,
                $inProgressSince !== '' ? $inProgressSince : null,
                $blockedSince !== '' ? $blockedSince : null,
                $releasedSince !== '' ? $releasedSince : null,
                $releasedSince !== '' ? $releasedSince : null,
                (int)($cfg['warn'] ?? 4),
                (int)($cfg['breach'] ?? 12),
                $sla['state'],
                (string)($updates['notes'] ?? ''),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    private static function releaseTracking(string $entityType, int $entityId, string $stage): void
    {
        if ($entityId <= 0) {
            return;
        }

        self::ensureSchema();
        DB::query(
            'UPDATE handoff_tracking SET released_since = COALESCE(released_since, NOW()), completed_at = COALESCE(completed_at, NOW()), ownership_state = ?, sla_state = ?, updated_at = NOW() WHERE entity_type = ? AND entity_id = ? AND stage = ?',
            [self::STATE_COMPLETED, self::SLA_ON_TRACK, $entityType, $entityId, $stage]
        );
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function evaluateSla(array $row, array $cfg, float $ageHours): array
    {
        $warn = (int)($row['sla_warning_hours'] ?? ($cfg['warn'] ?? 4));
        $breach = (int)($row['sla_breach_hours'] ?? ($cfg['breach'] ?? 12));
        $state = self::SLA_ON_TRACK;
        if ($ageHours >= ($breach * 2) || trim((string)($row['escalated_at'] ?? '')) !== '') {
            $state = self::SLA_BREACHED;
        } elseif ($ageHours >= $breach) {
            $state = self::SLA_OVERDUE;
        } elseif ($ageHours >= $warn) {
            $state = self::SLA_AT_RISK;
        }

        return [
            'state' => $state,
            'level' => $state === self::SLA_ON_TRACK ? 'ok' : ($state === self::SLA_AT_RISK ? 'warning' : 'breach'),
            'label' => self::slaLabel($state),
            'warn' => $warn,
            'breach' => $breach,
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function currentSinceAt(array $row, string $state, string $fallback): string
    {
        $candidates = [];
        if (in_array($state, [self::STATE_BLOCKED, self::STATE_ESCALATED], true)) {
            $candidates[] = trim((string)($row['blocked_since'] ?? ''));
        }
        if (in_array($state, [self::STATE_WAITING_UPSTREAM, self::STATE_WAITING_DOWNSTREAM, self::STATE_ASSIGNED, self::STATE_PENDING_ASSIGNMENT], true)) {
            $candidates[] = trim((string)($row['waiting_since'] ?? ''));
            $candidates[] = trim((string)($row['ready_since'] ?? ''));
        }
        if ($state === self::STATE_IN_PROGRESS) {
            $candidates[] = trim((string)($row['in_progress_since'] ?? ''));
        }
        $candidates[] = trim((string)($row['entered_stage_at'] ?? ''));
        $candidates[] = trim((string)($row['updated_at'] ?? ''));
        $candidates[] = trim((string)($row['created_at'] ?? ''));

        foreach ($candidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function normalizeOwnershipState(string $state, array $row): string
    {
        $state = strtolower(trim($state));
        if ($state !== '') {
            return $state;
        }
        if (trim((string)($row['released_since'] ?? '')) !== '' || trim((string)($row['completed_at'] ?? '')) !== '') {
            return self::STATE_COMPLETED;
        }
        if (trim((string)($row['escalated_at'] ?? '')) !== '') {
            return self::STATE_ESCALATED;
        }
        if (trim((string)($row['blocked_since'] ?? '')) !== '') {
            return self::STATE_BLOCKED;
        }
        if (trim((string)($row['waiting_since'] ?? '')) !== '' || trim((string)($row['ready_since'] ?? '')) !== '') {
            return self::STATE_WAITING_DOWNSTREAM;
        }
        if (trim((string)($row['in_progress_since'] ?? '')) !== '') {
            return self::STATE_IN_PROGRESS;
        }
        if ((int)($row['owner_user_id'] ?? 0) > 0 || trim((string)($row['owner_display'] ?? '')) !== '') {
            return self::STATE_ASSIGNED;
        }
        if (trim((string)($row['owner_role'] ?? '')) !== '') {
            return self::STATE_ASSIGNED;
        }
        return self::STATE_PENDING_ASSIGNMENT;
    }

    /**
     * @param array<int,int> $trackingIds
     * @return array<int,array<string,mixed>>
     */
    private static function lastAuditByTrackingIds(array $trackingIds): array
    {
        if ($trackingIds === []) {
            return [];
        }
        $holders = implode(',', array_fill(0, count($trackingIds), '?'));
        $rows = DB::fetchAll(
            'SELECT entity_id, action_name, created_at FROM audit_activity_log WHERE entity_type = ? AND entity_id IN (' . $holders . ') ORDER BY created_at DESC, id DESC',
            array_merge(['handoff_tracking'], $trackingIds)
        );
        $map = [];
        foreach ($rows as $row) {
            $entityId = (int)($row['entity_id'] ?? 0);
            if ($entityId <= 0 || isset($map[$entityId])) {
                continue;
            }
            $map[$entityId] = $row;
        }
        return $map;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function sourceMetadataForRows(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $entityType = (string)($row['entity_type'] ?? '');
            $entityId = (int)($row['entity_id'] ?? 0);
            if ($entityType === '' || $entityId <= 0) {
                continue;
            }
            $grouped[$entityType][] = $entityId;
        }

        $meta = [];
        foreach ($grouped as $entityType => $ids) {
            $ids = array_values(array_unique(array_map('intval', $ids)));
            if ($ids === []) {
                continue;
            }
            $holders = implode(',', array_fill(0, count($ids), '?'));
            if ($entityType === 'daily_order') {
                if (!self::tableExists('daily_orders')) {
                    continue;
                }
                $rows = DB::fetchAll(
                    'SELECT d.id, d.qty, d.status, p.parts_name, p.parts_number FROM daily_orders d LEFT JOIN products p ON p.id = d.product_id WHERE d.id IN (' . $holders . ')',
                    $ids
                );
                foreach ($rows as $row) {
                    $meta[self::sourceKey('daily_order', (int)$row['id'])] = [
                        'item_ref' => 'Daily Order #' . (int)$row['id'],
                        'parts_name' => (string)($row['parts_name'] ?? ''),
                        'parts_number' => (string)($row['parts_number'] ?? ''),
                        'qty' => (float)($row['qty'] ?? 0),
                        'status' => (string)($row['status'] ?? ''),
                        'detail_url' => '/daily-orders/360?id=' . (int)$row['id'],
                    ];
                }
            } elseif ($entityType === 'production_plan') {
                if (!self::tableExists('production_plans')) {
                    continue;
                }
                $rows = DB::fetchAll(
                    'SELECT pp.id, pp.planned_qty, pp.status, p.parts_name, p.parts_number FROM production_plans pp LEFT JOIN products p ON p.id = pp.product_id WHERE pp.id IN (' . $holders . ')',
                    $ids
                );
                foreach ($rows as $row) {
                    $meta[self::sourceKey('production_plan', (int)$row['id'])] = [
                        'item_ref' => 'Production Plan #' . (int)$row['id'],
                        'parts_name' => (string)($row['parts_name'] ?? ''),
                        'parts_number' => (string)($row['parts_number'] ?? ''),
                        'qty' => (float)($row['planned_qty'] ?? 0),
                        'status' => (string)($row['status'] ?? ''),
                        'detail_url' => '/production-plans/edit?id=' . (int)$row['id'],
                    ];
                }
            } elseif ($entityType === 'production_entry') {
                if (!self::tableExists('production_entries')) {
                    continue;
                }
                $rows = DB::fetchAll(
                    'SELECT pe.id, pe.good_qty, pe.status, p.parts_name, p.parts_number FROM production_entries pe LEFT JOIN products p ON p.id = pe.product_id WHERE pe.id IN (' . $holders . ')',
                    $ids
                );
                foreach ($rows as $row) {
                    $meta[self::sourceKey('production_entry', (int)$row['id'])] = [
                        'item_ref' => 'Production Entry #' . (int)$row['id'],
                        'parts_name' => (string)($row['parts_name'] ?? ''),
                        'parts_number' => (string)($row['parts_number'] ?? ''),
                        'qty' => (float)($row['good_qty'] ?? 0),
                        'status' => (string)($row['status'] ?? ''),
                        'detail_url' => '/production-entries/edit?id=' . (int)$row['id'],
                    ];
                }
            } elseif ($entityType === 'assembly_plan') {
                if (!self::tableExists('mfg_part_demands')) {
                    continue;
                }
                $rows = DB::fetchAll(
                    'SELECT d.id, COALESCE(d.approved_qty, d.adjusted_qty, d.system_qty) AS effective_qty, d.status, p.parts_name, p.parts_number FROM mfg_part_demands d LEFT JOIN products p ON p.id = d.product_id WHERE d.id IN (' . $holders . ') AND d.demand_type = ?',
                    array_merge($ids, ['assembly'])
                );
                foreach ($rows as $row) {
                    $meta[self::sourceKey('assembly_plan', (int)$row['id'])] = [
                        'item_ref' => 'Assembly Plan #' . (int)$row['id'],
                        'parts_name' => (string)($row['parts_name'] ?? ''),
                        'parts_number' => (string)($row['parts_number'] ?? ''),
                        'qty' => (float)($row['effective_qty'] ?? 0),
                        'status' => (string)($row['status'] ?? ''),
                        'detail_url' => '/manufacturing/assembly-plans/detail?id=' . (int)$row['id'],
                    ];
                }
            } elseif ($entityType === 'qc_entry') {
                if (!self::tableExists('qc_entries')) {
                    continue;
                }
                $rows = DB::fetchAll(
                    'SELECT q.id, q.pass_qty, q.status, p.parts_name, p.parts_number FROM qc_entries q LEFT JOIN products p ON p.id = q.product_id WHERE q.id IN (' . $holders . ')',
                    $ids
                );
                foreach ($rows as $row) {
                    $meta[self::sourceKey('qc_entry', (int)$row['id'])] = [
                        'item_ref' => 'QC Entry #' . (int)$row['id'],
                        'parts_name' => (string)($row['parts_name'] ?? ''),
                        'parts_number' => (string)($row['parts_number'] ?? ''),
                        'qty' => (float)($row['pass_qty'] ?? 0),
                        'status' => (string)($row['status'] ?? ''),
                        'detail_url' => '/qc-entries/edit?id=' . (int)$row['id'],
                    ];
                }
            } elseif ($entityType === 'dispatch_entry') {
                if (!self::tableExists('dispatch_entries')) {
                    continue;
                }
                $rows = DB::fetchAll(
                    'SELECT d.id, d.dispatchable_qty, d.dispatch_status, p.parts_name, p.parts_number FROM dispatch_entries d LEFT JOIN products p ON p.id = d.product_id WHERE d.id IN (' . $holders . ')',
                    $ids
                );
                foreach ($rows as $row) {
                    $meta[self::sourceKey('dispatch_entry', (int)$row['id'])] = [
                        'item_ref' => 'Dispatch Entry #' . (int)$row['id'],
                        'parts_name' => (string)($row['parts_name'] ?? ''),
                        'parts_number' => (string)($row['parts_number'] ?? ''),
                        'qty' => (float)($row['dispatchable_qty'] ?? 0),
                        'status' => (string)($row['dispatch_status'] ?? ''),
                        'detail_url' => '/dispatch-entries/edit?id=' . (int)$row['id'],
                    ];
                }
            }
        }

        return $meta;
    }

    /**
     * @return array<string,mixed>
     */
    private static function stageDefinition(string $entityType, string $stage): array
    {
        return self::STAGE_DEFINITIONS[$entityType . ':' . $stage] ?? [
            'label' => ucfirst(str_replace('_', ' ', $stage)),
            'default_owner_role' => 'Unassigned / System issue',
            'next_stage' => '',
            'next_stage_label' => '',
            'next_owner_role' => '',
            'next_owner_profile' => '',
            'warn' => 4,
            'breach' => 12,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function applyDefaults(array $row): array
    {
        $cfg = self::stageDefinition((string)($row['entity_type'] ?? ''), (string)($row['stage'] ?? ''));
        if (trim((string)($row['next_stage'] ?? '')) === '') {
            $row['next_stage'] = (string)($cfg['next_stage'] ?? '');
        }
        if (trim((string)($row['next_owner_role'] ?? '')) === '') {
            $row['next_owner_role'] = (string)($cfg['next_owner_role'] ?? '');
        }
        if (trim((string)($row['next_owner_profile'] ?? '')) === '') {
            $row['next_owner_profile'] = (string)($cfg['next_owner_profile'] ?? '');
        }
        if ((int)($row['sla_warning_hours'] ?? 0) <= 0) {
            $row['sla_warning_hours'] = (int)($cfg['warn'] ?? 4);
        }
        if ((int)($row['sla_breach_hours'] ?? 0) <= 0) {
            $row['sla_breach_hours'] = (int)($cfg['breach'] ?? 12);
        }
        return $row;
    }

    private static function tableExists(string $table): bool
    {
        try {
            return DB::fetchOne(
                'SELECT 1 AS present FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            ) !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string,string>
     */
    private static function ownerContext(int $userId): array
    {
        if ($userId <= 0) {
            return ['authority_role' => '', 'profile_key' => ''];
        }
        $user = DB::fetchOne('SELECT authority_role FROM users WHERE id = ? LIMIT 1', [$userId]) ?: [];
        $profileKey = '';
        if (class_exists('Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
            $assign = DB::fetchOne('SELECT access_profiles FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1', [$userId]) ?: [];
            $profiles = array_values(array_filter(array_map('trim', explode(',', (string)($assign['access_profiles'] ?? '')))));
            $profileKey = $profiles[0] ?? '';
        }
        return [
            'authority_role' => (string)($user['authority_role'] ?? ''),
            'profile_key' => $profileKey,
        ];
    }

    private static function assemblyStageContext(int $productId, string $date): array
    {
        if ($productId <= 0 || $date === '') {
            return [];
        }
        if (!is_callable(self::$assemblyStageContextResolver)) {
            return [];
        }

        try {
            $resolved = call_user_func(self::$assemblyStageContextResolver, $productId, $date);
            return is_array($resolved) ? $resolved : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function assemblyCompletedQty(int $productId, string $date): float
    {
        if ($productId <= 0 || $date === '') {
            return 0.0;
        }
        if (!is_callable(self::$assemblyCompletedQtyResolver)) {
            return 0.0;
        }

        try {
            return (float)call_user_func(self::$assemblyCompletedQtyResolver, $productId, $date);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function effectiveDemandQty(array $row): float
    {
        if (isset($row['approved_qty']) && $row['approved_qty'] !== null && (string)$row['approved_qty'] !== '') {
            return round((float)$row['approved_qty'], 2);
        }
        if (isset($row['adjusted_qty']) && $row['adjusted_qty'] !== null && (string)$row['adjusted_qty'] !== '') {
            return round((float)$row['adjusted_qty'], 2);
        }
        return round((float)($row['system_qty'] ?? 0), 2);
    }

    private static function normalizeTimestamp(string $primary, string $fallbackDate, bool $dateOnlyFallback): string
    {
        $primary = trim($primary);
        if ($primary !== '') {
            return $primary;
        }
        $fallbackDate = trim($fallbackDate);
        if ($fallbackDate !== '') {
            return $dateOnlyFallback ? ($fallbackDate . ' 00:00:00') : $fallbackDate;
        }
        return '';
    }

    private static function hoursBetween(string $from, string $to): float
    {
        if ($from === '' || $to === '') {
            return 0.0;
        }
        try {
            $start = new \DateTime($from);
            $end = new \DateTime($to);
        } catch (\Throwable $e) {
            return 0.0;
        }
        $seconds = $end->getTimestamp() - $start->getTimestamp();
        return $seconds > 0 ? round($seconds / 3600, 2) : 0.0;
    }

    private static function ownerLabel(string $ownerRole, string $ownerDisplay): string
    {
        $ownerDisplay = trim($ownerDisplay);
        if ($ownerDisplay !== '') {
            return $ownerRole . ' / ' . $ownerDisplay;
        }
        return $ownerRole;
    }

    private static function stateLabel(string $state): string
    {
        return match ($state) {
            self::STATE_PENDING_ASSIGNMENT => 'Pending Assignment',
            self::STATE_ASSIGNED => 'Assigned',
            self::STATE_IN_PROGRESS => 'In Progress',
            self::STATE_WAITING_UPSTREAM => 'Waiting Upstream',
            self::STATE_WAITING_DOWNSTREAM => 'Waiting Downstream',
            self::STATE_BLOCKED => 'Blocked',
            self::STATE_COMPLETED => 'Completed',
            self::STATE_ESCALATED => 'Escalated',
            default => ucfirst(str_replace('_', ' ', $state)),
        };
    }

    private static function slaLabel(string $state): string
    {
        return match ($state) {
            self::SLA_ON_TRACK => 'On Track',
            self::SLA_AT_RISK => 'At Risk',
            self::SLA_OVERDUE => 'Overdue',
            self::SLA_BREACHED => 'Breached',
            default => ucfirst(str_replace('_', ' ', $state)),
        };
    }

    /**
     * @param array<int,array<string,mixed>> $summaries
     * @return array<string,int>
     */
    private static function metricsForSummaries(array $summaries): array
    {
        $metrics = self::emptyMetrics();
        foreach ($summaries as $row) {
            $metrics['active']++;
            $state = (string)($row['ownership_state'] ?? '');
            $slaState = (string)($row['sla_state'] ?? '');
            if (in_array($state, [self::STATE_BLOCKED, self::STATE_ESCALATED], true)) {
                $metrics['blocked']++;
            }
            if (in_array($slaState, [self::SLA_OVERDUE, self::SLA_BREACHED], true)) {
                $metrics['overdue']++;
            }
            if ($slaState === self::SLA_BREACHED) {
                $metrics['breached']++;
            }
            if (in_array($state, [self::STATE_WAITING_UPSTREAM, self::STATE_WAITING_DOWNSTREAM], true)) {
                $metrics['waiting']++;
            }
        }
        return $metrics;
    }

    /**
     * @return array<string,int>
     */
    private static function emptyMetrics(): array
    {
        return [
            'active' => 0,
            'waiting' => 0,
            'blocked' => 0,
            'overdue' => 0,
            'breached' => 0,
        ];
    }

    private static function sourceKey(string $entityType, int $entityId): string
    {
        return $entityType . ':' . $entityId;
    }

    private static function fallbackItemRef(string $entityType, int $entityId): string
    {
        $labels = [
            'daily_order'      => 'Daily Order',
            'production_plan'  => 'Production Plan',
            'production_entry' => 'Production Entry',
            'assembly_plan'    => 'Assembly Plan',
            'qc_entry'         => 'QC Entry',
            'dispatch_entry'   => 'Dispatch Entry',
        ];
        $label = $labels[$entityType] ?? ucwords(str_replace('_', ' ', $entityType));
        return $label . ' #' . $entityId;
    }

    private static function fallbackDetailUrl(string $entityType, int $entityId): string
    {
        if ($entityId <= 0) {
            return '#';
        }
        $map = [
            'daily_order'      => '/daily-orders/360?id=',
            'production_plan'  => '/production-plans/edit?id=',
            'production_entry' => '/production-entries/edit?id=',
            'assembly_plan'    => '/manufacturing/assembly-plans/detail?id=',
            'qc_entry'         => '/qc-entries/edit?id=',
            'dispatch_entry'   => '/dispatch-entries/edit?id=',
        ];
        return isset($map[$entityType]) ? $map[$entityType] . $entityId : '#';
    }

    private static function addTrackingColumnIfMissing(string $columnName, string $sql): void
    {
        $exists = DB::fetchOne(
            'SELECT 1 AS present FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            ['handoff_tracking', $columnName]
        );
        if (!$exists) {
            DB::query($sql);
        }
    }
}
