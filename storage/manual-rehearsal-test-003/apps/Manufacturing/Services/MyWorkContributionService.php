<?php
declare(strict_types=1);

namespace Apps\Manufacturing\Services;

use App\Core\DB;
use App\Core\HandoffEngine;
use App\Core\MyWorkEntityAdapter;
use App\Core\MyWorkEntityQuery;
use App\Core\EntityContext;
use Plugins\Base\Services\NotificationService;
use Plugins\Base\Services\UserDashboardAssignmentService;
use Plugins\Workflow\Services\WorkflowGovernance;
use Plugins\Workflow\Services\WorkflowPolicy;

/**
 * MyWorkService — unified operational inbox aggregator.
 *
 * Produces a structured queue split into four operational sections:
 *   1. needs_action       — handoff-owned, SLA-urgent, or newly assigned
 *   2. awaiting_approval  — items pending the user's workflow decision
 *   3. overdue_escalated  — SLA breached / escalated items in the user's scope
 *   4. blocked_followup   — blocked / waiting-upstream items requiring intervention
 *
 * Every item carries: item_ref, title, entity_type, entity_id, reason, stage_label,
 * sla_label (if applicable), action_url, section_key, urgency_rank.
 *
 * Design notes:
 * — Pulls from HandoffEngine (ownership/SLA state), GovernanceInboxService (approvals),
 *   and OperatorWorkboardService queue (live operational tasks).
 * — Deduplicates: a record appearing in multiple sources is shown once at its highest
 *   urgency rank.
 * — Respects authority_role, assigned_apps, access_profiles, and workflow permissions.
 * — No second task system: all state is read from existing tables.
 */
final class MyWorkContributionService
{
    // Section keys — used as stable identifiers in the view
    public const SECTION_NEEDS_ACTION    = 'needs_action';
    public const SECTION_AWAITING_APPROVAL = 'awaiting_approval';
    public const SECTION_OVERDUE         = 'overdue_escalated';
    public const SECTION_BLOCKED         = 'blocked_followup';

    // Urgency ranks — lower = higher priority in final sort
    private const RANK_BREACHED   = 0;
    private const RANK_ESCAPED    = 1;   // escalated
    private const RANK_OVERDUE    = 2;
    private const RANK_APPROVAL   = 3;   // waiting for user's action
    private const RANK_BLOCKED    = 4;
    private const RANK_ASSIGNED   = 5;
    private const RANK_NORMAL     = 9;

    /**
     * @param array<string,mixed>|null $user
     * @param array<string,string>     $input  GET params (for future filter use)
     * @return array<string,mixed>
     */
    public static function build(?array $user, array $input = []): array
    {
        $user = is_array($user) ? $user : [];
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? 'app_user')));

        UserDashboardAssignmentService::ensureSchema();
        WorkflowGovernance::ensureSchema();
        HandoffEngine::ensureSchema();

        $ctx   = UserDashboardAssignmentService::resolveUserContext($user);
        $scope = (array)($ctx['scope'] ?? []);
        $allowedModules = UserDashboardAssignmentService::enabledModulesForUser($user);
        $activeAssignedApps = array_values((array)($ctx['active_assigned_apps'] ?? []));
        $manufacturingActive = in_array('manufacturing', $activeAssignedApps, true) && UserDashboardAssignmentService::isAppEnabled('manufacturing');

        // --- aggregate all items ---
        $allItems = [];

        if ($manufacturingActive) {
            // 1. Handoff-engine owned/actionable items
            $allItems = array_merge($allItems, self::collectHandoffItems($user, $scope, $allowedModules));

            // 2. Approval-inbox items (workflow decisions)
            $allItems = array_merge($allItems, self::collectApprovalItems($user, $scope, $allowedModules));

            // 3. Live operational queue items not already covered by handoff rows
            $allItems = array_merge($allItems, self::collectOperationalQueue($user, $scope, $allowedModules));
        }

        // --- deduplicate: keep highest-urgency item per dedup key ---
        $deduped = self::deduplicateItems($allItems);
        $deduped = array_values(array_map(
            static fn(array $item): array => self::decorateItemForDisplay($item),
            $deduped
        ));

        // --- sort globally by urgency, then age descending ---
        usort($deduped, static function (array $a, array $b): int {
            $rank = (int)$a['urgency_rank'] <=> (int)$b['urgency_rank'];
            if ($rank !== 0) {
                return $rank;
            }
            return ((float)($b['age_hours'] ?? 0.0)) <=> ((float)($a['age_hours'] ?? 0.0));
        });

        // --- split into sections ---
        $sections = [
            self::SECTION_NEEDS_ACTION     => [],
            self::SECTION_AWAITING_APPROVAL => [],
            self::SECTION_OVERDUE          => [],
            self::SECTION_BLOCKED          => [],
        ];

        foreach ($deduped as $item) {
            $sections[(string)($item['section_key'] ?? self::SECTION_NEEDS_ACTION)][] = $item;
        }

        $primaryArea = $manufacturingActive ? self::primaryWorkArea($ctx) : '';
        $crossAreas = $manufacturingActive ? self::crossWorkAreas($ctx) : [];
        $groupedSections = [
            'primary_work' => [],
            'cross_functional_work' => [],
            'visibility_monitoring' => [],
        ];

        foreach ($deduped as $item) {
            $area = self::workAreaForItem($item);
            if ($area !== '' && $area === $primaryArea) {
                $item['work_group'] = 'primary_work';
                $groupedSections['primary_work'][] = $item;
                continue;
            }
            if ($area !== '' && in_array($area, $crossAreas, true)) {
                $item['work_group'] = 'cross_functional_work';
                $groupedSections['cross_functional_work'][] = $item;
                continue;
            }
            $item['work_group'] = 'visibility_monitoring';
            $groupedSections['visibility_monitoring'][] = $item;
        }

        $kpi = self::buildKpi($sections, $deduped);

        $notificationSummary = [];
        $recentNotifications = [];
        $unreadNotifications = 0;
        if (class_exists(NotificationService::class)) {
            try {
                $notificationSummary = NotificationService::summaryForUser($user);
                $recentNotifications = NotificationService::recentForUser($user, 8, true);
                $unreadNotifications = (int)($notificationSummary['unread'] ?? 0);
            } catch (\Throwable $e) {
                $notificationSummary = [];
                $recentNotifications = [];
                $unreadNotifications = 0;
            }
        }

        return [
            'user_name'           => self::displayName($user),
            'user_role'           => self::displayRole($user),
            'authority_role'        => $authorityRole,
            'dashboard_type'      => (string)($ctx['dashboard_type'] ?? 'operator'),
            'kpi'                 => $kpi,
            'sections'            => $sections,
            'grouped_sections'    => $groupedSections,
            'all_items'           => array_slice($deduped, 0, 300),
            'has_approvals'       => !empty($sections[self::SECTION_AWAITING_APPROVAL]),
            'has_overdue'         => !empty($sections[self::SECTION_OVERDUE]),
            'has_blocked'         => !empty($sections[self::SECTION_BLOCKED]),
            'role_links'          => $manufacturingActive ? self::buildRoleLinks($user, $allowedModules) : [],
            'cross_functional_access' => (array)($ctx['cross_functional_access'] ?? []),
            'primary_work_area' => $primaryArea,
            'cross_work_areas' => $crossAreas,
            'approval_modules'    => $manufacturingActive ? self::approvalModulesForUser($user) : [],
            'notification_summary' => $notificationSummary,
            'recent_notifications' => $recentNotifications,
            'unread_notifications' => $unreadNotifications,
            'active_assigned_apps' => $activeAssignedApps,
        ];
    }

    // ----------------------------------------------------------------
    // HANDOFF ITEMS
    // ----------------------------------------------------------------

    /**
     * Pulls handoff_tracking rows for items owned by or waiting for this user's role(s).
     * Enriches with HandoffEngine summary data (item_ref, detail_url, SLA, etc.)
     *
     * @param array<string,mixed>        $user
     * @param array<string,mixed>        $scope
     * @param array<int,string>          $allowedModules
     * @return array<int,array<string,mixed>>
     */
    private static function collectHandoffItems(array $user, array $scope, array $allowedModules): array
    {
        if (!self::tableExists('handoff_tracking')) {
            return [];
        }

        $userId   = (int)($user['id'] ?? 0);
        $ownerRoles = self::ownerRolesForUser($user, $allowedModules);

        if ($userId <= 0 && $ownerRoles === []) {
            return [];
        }

        $clauses = [];
        $params  = [];

        if ($userId > 0) {
            $clauses[] = 'owner_user_id = ?';
            $params[]  = $userId;
        }

        if ($ownerRoles !== []) {
            $holders   = implode(',', array_fill(0, count($ownerRoles), '?'));
            $clauses[] = "owner_role IN ({$holders})";
            foreach ($ownerRoles as $r) {
                $params[] = $r;
            }
        }

        $rows = DB::fetchAll(
            'SELECT * FROM handoff_tracking WHERE released_since IS NULL AND (' . implode(' OR ', $clauses) . ') ORDER BY updated_at DESC LIMIT 400',
            $params
        );

        if ($rows === []) {
            return [];
        }

        // Use HandoffEngine's summariesForRows via activeOverview pruned to these IDs.
        // More efficient: call summaryForEntity per unique entity, but for batching
        // we replicate the workboardForOwner logic against our pre-fetched rows.
        // We use the HandoffEngine's public summaryForChain API.
        $refs = [];
        foreach ($rows as $row) {
            $et = trim((string)($row['entity_type'] ?? ''));
            $ei = (int)($row['entity_id'] ?? 0);
            if ($et !== '' && $ei > 0) {
                $refs[] = ['entity_type' => $et, 'entity_id' => $ei];
            }
        }

        if ($refs === []) {
            return [];
        }

        $chainSummary = HandoffEngine::summaryForChain($refs);
        $summaries = (array)($chainSummary['active'] ?? []);

        $items = [];
        foreach ($summaries as $s) {
            $entityType = (string)($s['entity_type'] ?? '');
            $module = self::entityTypeToModule($entityType);
            if ($module !== '' && !in_array($module, $allowedModules, true) && $module !== 'manufacturing') {
                continue;
            }

            $slaState   = (string)($s['sla_state'] ?? 'on_track');
            $ownerState = (string)($s['ownership_state'] ?? '');
            $ageHours   = (float)($s['age_hours'] ?? 0.0);

            [$section, $rank] = self::classifyHandoffItem($slaState, $ownerState);

            $reason = self::handoffReason($slaState, $ownerState, (string)($s['stage_label'] ?? ''));

            $nextOwner = (string)($s['next_owner_role'] ?? '');
            $items[] = [
                'dedup_key'      => $entityType . ':' . (int)($s['entity_id'] ?? 0),
                'source'         => 'handoff',
                'section_key'    => $section,
                'urgency_rank'   => $rank,
                'entity_type'    => $entityType,
                'entity_id'      => (int)($s['entity_id'] ?? 0),
                'item_ref'       => (string)($s['item_ref'] ?? ''),
                'parts_name'     => (string)($s['parts_name'] ?? ''),
                'parts_number'   => (string)($s['parts_number'] ?? ''),
                'stage_label'    => (string)($s['stage_label'] ?? ''),
                'ownership_state'=> $ownerState,
                'owner_label'    => (string)($s['current_owner_label'] ?? ''),
                'next_owner'     => displayRole($nextOwner),
                'sla_state'      => $slaState,
                'sla_label'      => (string)($s['sla_label'] ?? 'On Track'),
                'age_hours'      => $ageHours,
                'reason'         => $reason,
                'action_label'   => t('common.open'),
                'action_url'     => (string)($s['detail_url'] ?? '#'),
            ];
        }

        return $items;
    }

    // ----------------------------------------------------------------
    // APPROVAL ITEMS
    // ----------------------------------------------------------------

    /**
     * Pulls pending-approval records across production_plans, qc_entries, dispatch_entries
     * that this user has permission to action (approve/reject/reopen/finalize).
     *
     * @param array<string,mixed>  $user
     * @param array<string,mixed>  $scope
     * @param array<int,string>    $allowedModules
     * @return array<int,array<string,mixed>>
     */
    private static function collectApprovalItems(array $user, array $scope, array $allowedModules): array
    {
        $items = [];

        $moduleTableMap = [
            WorkflowPolicy::MODULE_PRODUCTION_PLAN => 'production_plans',
            WorkflowPolicy::MODULE_QC_ENTRY        => 'qc_entries',
            WorkflowPolicy::MODULE_DISPATCH_ENTRY  => 'dispatch_entries',
        ];

        $moduleUrlMap = [
            WorkflowPolicy::MODULE_PRODUCTION_PLAN => '/apps/manufacturing/production-plans/edit?id=',
            WorkflowPolicy::MODULE_QC_ENTRY        => '/qc-entries/edit?id=',
            WorkflowPolicy::MODULE_DISPATCH_ENTRY  => '/dispatch-entries/edit?id=',
        ];

        $moduleEntityMap = [
            WorkflowPolicy::MODULE_PRODUCTION_PLAN => 'production_plan',
            WorkflowPolicy::MODULE_QC_ENTRY        => 'qc_entry',
            WorkflowPolicy::MODULE_DISPATCH_ENTRY  => 'dispatch_entry',
        ];

        $moduleLabelMap = [
            WorkflowPolicy::MODULE_PRODUCTION_PLAN => t('nav.production_plans'),
            WorkflowPolicy::MODULE_QC_ENTRY        => t('nav.qc_entries'),
            WorkflowPolicy::MODULE_DISPATCH_ENTRY  => t('nav.dispatch_entries'),
        ];

        foreach ($moduleTableMap as $module => $table) {
            // Only include if user has any approval-level permission for this module
            $canAct = WorkflowPolicy::canApprove($module, $user)
                || WorkflowPolicy::canReopen($module, $user)
                || WorkflowPolicy::canFinalize($module, $user)
                || WorkflowPolicy::canOverrideLock($module, $user);

            if (!$canAct) {
                continue;
            }
            if (!self::tableExists($table)) {
                continue;
            }

            try {
                $rows = DB::fetchAll(
                    "SELECT t.id, t.approval_status, t.updated_at, t.locked_at,
                            p.parts_name, p.parts_number
                     FROM {$table} t
                     LEFT JOIN products p ON p.id = t.product_id
                     WHERE LOWER(COALESCE(t.approval_status,'')) IN ('pending approval','reopened','draft')
                       AND (t.locked_at IS NULL OR t.locked_at = '')
                     ORDER BY t.updated_at ASC
                     LIMIT 100",
                    []
                );
            } catch (\Throwable $e) {
                continue;
            }

            $moduleLabel    = $moduleLabelMap[$module] ?? $module;
            $urlBase        = $moduleUrlMap[$module] ?? '#';
            $entityType     = $moduleEntityMap[$module] ?? $module;

            foreach ($rows as $row) {
                $id             = (int)($row['id'] ?? 0);
                $approvalStatus = WorkflowPolicy::normalizeApprovalStatus((string)($row['approval_status'] ?? ''));
                $updatedAt      = (string)($row['updated_at'] ?? '');
                $ageHours       = self::hoursAgo($updatedAt);

                $reason = match ($approvalStatus) {
                    WorkflowPolicy::APPROVAL_PENDING  => t('ops.my_work.reason.awaiting_approval'),
                    WorkflowPolicy::APPROVAL_REOPENED => t('ops.my_work.reason.reopened_rereview'),
                    default                           => t('ops.my_work.reason.not_submitted'),
                };

                // For draft items, only include if user can submit (they're the creator)
                if ($approvalStatus === WorkflowPolicy::APPROVAL_DRAFT && !WorkflowPolicy::canSubmit($module, $user)) {
                    continue;
                }

                $itemRef = self::buildEntityReference($entityType, $id, $urlBase . $id, $moduleLabel);

                $items[] = [
                    'dedup_key'       => $entityType . ':' . $id,
                    'source'          => 'approval',
                    'section_key'     => self::SECTION_AWAITING_APPROVAL,
                    'urgency_rank'    => $approvalStatus === WorkflowPolicy::APPROVAL_REOPENED ? self::RANK_ESCAPED : self::RANK_APPROVAL,
                    'entity_type'     => $entityType,
                    'entity_id'       => $id,
                    'item_ref'        => $itemRef,
                    'title'           => $itemRef,
                    'parts_name'      => (string)($row['parts_name'] ?? ''),
                    'parts_number'    => (string)($row['parts_number'] ?? ''),
                    'stage_label'     => $approvalStatus,
                    'ownership_state' => '',
                    'owner_label'     => '',
                    'next_owner'      => '',
                    'sla_state'       => 'on_track',
                    'sla_label'       => '',
                    'age_hours'       => $ageHours,
                    'reason'          => $reason,
                    'action_label'    => $approvalStatus === WorkflowPolicy::APPROVAL_PENDING ? t('ops.my_work.action_review_approve') : t('common.open'),
                    'action_url'      => $urlBase . $id,
                ];
            }
        }

        return $items;
    }

    // ----------------------------------------------------------------
    // OPERATIONAL QUEUE
    // ----------------------------------------------------------------

    /**
     * Pulls live operational work items not already surfaced by handoff rows.
     * Uses MyWorkEntityQuery for runtime-backed entities (daily_order, production_entry, production_plan).
     * Falls back to legacy queries for qc_entry and dispatch_entry.
     *
     * @param array<string,mixed>  $user
     * @param array<string,mixed>  $scope
     * @param array<int,string>    $allowedModules
     * @return array<int,array<string,mixed>>
     */
    private static function collectOperationalQueue(array $user, array $scope, array $allowedModules): array
    {
        $items = [];

        $items = array_merge($items, self::fetchRuntimeBackedEntities($allowedModules));

        if (self::moduleAllowed($allowedModules, 'qc') && self::tableExists('qc_entries')) {
            $items = array_merge($items, self::fetchQcEntryQueue($scope));
        }
        if (self::moduleAllowed($allowedModules, 'dispatch') && self::tableExists('dispatch_entries')) {
            $items = array_merge($items, self::fetchDispatchEntryQueue($scope));
        }

        return self::enrichEntityItems($items, $user);
    }

    private static function fetchRuntimeBackedEntities(array $allowedModules): array
    {
        $items = [];
        $options = ['limit' => 40];

        if (self::moduleAllowed($allowedModules, 'daily_orders')) {
            $dailyOrders = MyWorkEntityQuery::fetchDailyOrders($options);
            foreach ($dailyOrders as $row) {
                $items[] = self::mapEntityRowToItem($row, t('nav.daily_orders'));
            }
        }

        if (self::moduleAllowed($allowedModules, 'production')) {
            $productionEntries = MyWorkEntityQuery::fetchProductionEntries($options);
            foreach ($productionEntries as $row) {
                $items[] = self::mapEntityRowToItem($row, t('nav.production_entries'));
            }

            $productionPlans = MyWorkEntityQuery::fetchProductionPlans($options);
            foreach ($productionPlans as $row) {
                $items[] = self::mapEntityRowToItem($row, t('nav.production_plans'));
            }
        }

        if (self::moduleAllowed($allowedModules, 'qc')) {
            $qcEntries = MyWorkEntityQuery::fetchQCEntries($options);
            foreach ($qcEntries as $row) {
                $items[] = self::mapEntityRowToItem($row, t('nav.qc_entries'));
            }
        }

        if (self::moduleAllowed($allowedModules, 'dispatch')) {
            $dispatchEntries = MyWorkEntityQuery::fetchDispatchEntries($options);
            foreach ($dispatchEntries as $row) {
                $items[] = self::mapEntityRowToItem($row, t('nav.dispatch_entries'));
            }
        }

        return $items;
    }

    private static function mapEntityRowToItem(array $row, string $label): array
    {
        $id = (int)($row['id'] ?? 0);
        $entityType = (string)($row['entity_type'] ?? '');
        $status = strtolower(trim((string)($row['status'] ?? 'open')));
        $updatedAt = (string)($row['updated_at'] ?? '');
        $ageHrs = self::hoursAgo($updatedAt);

        $statusBucket = self::statusBucket($status);
        if ($statusBucket === 'completed') {
            $statusBucket = 'active';
        }

        $section = $statusBucket === 'blocked' ? self::SECTION_BLOCKED : self::SECTION_NEEDS_ACTION;
        $detailUrl = (string)($row['detail_url'] ?? '#');
        $itemRef = self::buildEntityReference($entityType, $id, $detailUrl, $label);
        $reasonLabel = self::entityDisplayLabel($entityType, $detailUrl, $label);

        return [
            'dedup_key'       => $entityType . ':' . $id,
            'source'          => 'ops_queue',
            'section_key'     => $section,
            'urgency_rank'    => $statusBucket === 'blocked' ? self::RANK_BLOCKED : self::RANK_NORMAL,
            'entity_type'     => $entityType,
            'entity_id'       => $id,
            'item_ref'        => $itemRef,
            'title'           => $itemRef,
            'parts_name'      => (string)($row['parts_name'] ?? ''),
            'parts_number'    => (string)($row['parts_number'] ?? ''),
            'stage_label'     => ucwords(str_replace('_', ' ', $status)),
            'ownership_state' => '',
            'owner_label'     => '',
            'next_owner'      => '',
            'sla_state'       => 'on_track',
            'sla_label'       => '',
            'age_hours'       => $ageHrs,
            'reason'          => t('ops.my_work.reason.active_scope', ['label' => $reasonLabel]),
            'action_label'    => t('common.open'),
            'action_url'      => $detailUrl,
        ];
    }

    private static function enrichEntityItems(array $items, array $user): array
    {
        $ctx = self::buildEntityContext($user);
        return MyWorkEntityAdapter::enrichItems($items, $ctx);
    }

    private static function buildEntityContext(array $user): EntityContext
    {
        $userId = (int)($user['id'] ?? 0);
        $role = strtolower(trim((string)($user['role'] ?? '')));
        $roles = $role !== '' ? [$role] : [];
        if (($user['authority_role'] ?? '') === 'platform_admin' || ($user['authority_role'] ?? '') === 'app_admin') {
            $roles[] = 'admin';
        }
        return new EntityContext($userId > 0 ? $userId : null, $roles, 'manufacturing');
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchProductionPlanQueue(array $scope): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT pp.id, pp.plan_date, pp.planned_qty, pp.status, pp.approval_status, pp.updated_at,
                        p.parts_name, p.parts_number
                 FROM production_plans pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 WHERE LOWER(COALESCE(pp.status,'draft')) NOT IN ('completed','closed','cancelled')
                   AND LOWER(COALESCE(pp.approval_status,'draft')) NOT IN ('approved')
                 ORDER BY COALESCE(pp.plan_date, CURDATE()) ASC, pp.id DESC
                 LIMIT 60"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapOperationalRows($rows, 'production_plan', t('nav.production_plans'), '/apps/manufacturing/production-plans/edit?id=');
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchQcEntryQueue(array $scope): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT q.id, q.checked_qty, q.status, q.approval_status, q.updated_at,
                        p.parts_name, p.parts_number
                 FROM qc_entries q
                 LEFT JOIN products p ON p.id = q.product_id
                 WHERE LOWER(COALESCE(q.status,'open')) NOT IN ('completed','closed','cancelled')
                   AND LOWER(COALESCE(q.approval_status,'draft')) NOT IN ('approved')
                 ORDER BY q.id DESC
                 LIMIT 60"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapOperationalRows($rows, 'qc_entry', t('nav.qc_entries'), '/qc-entries/edit?id=');
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDispatchEntryQueue(array $scope): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT d.id, d.dispatchable_qty, d.dispatch_date, d.dispatch_status, d.approval_status, d.updated_at,
                        p.parts_name, p.parts_number
                 FROM dispatch_entries d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE LOWER(COALESCE(d.dispatch_status,'draft')) NOT IN ('dispatched','completed','cancelled')
                   AND LOWER(COALESCE(d.approval_status,'draft')) NOT IN ('approved')
                 ORDER BY COALESCE(d.dispatch_date, CURDATE()) ASC, d.id DESC
                 LIMIT 60"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapOperationalRows($rows, 'dispatch_entry', t('nav.dispatch_entries'), '/dispatch-entries/edit?id=');
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDailyOrderQueue(array $scope): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT o.id, o.qty, o.status, o.updated_at, o.required_date,
                        p.parts_name, p.parts_number
                 FROM daily_orders o
                 LEFT JOIN products p ON p.id = o.product_id
                 WHERE LOWER(COALESCE(o.status,'open')) NOT IN ('completed','closed','cancelled','fulfilled')
                 ORDER BY o.id DESC
                 LIMIT 40"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapOperationalRows($rows, 'daily_order', t('nav.daily_orders'), '/apps/manufacturing/daily-orders/360?id=');
    }

    private static function fetchProductionEntryQueue(array $scope): array
    {
        if (!self::tableExists('production_entries')) {
            return [];
        }
        try {
            $rows = DB::fetchAll(
                "SELECT pe.id, pe.qty_produced, pe.status, pe.production_date, pe.updated_at,
                        p.parts_name, p.parts_number
                 FROM production_entries pe
                 LEFT JOIN products p ON p.id = pe.product_id
                 WHERE LOWER(COALESCE(pe.status,'draft')) NOT IN ('completed','closed','cancelled')
                 ORDER BY pe.id DESC
                 LIMIT 40"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapOperationalRows($rows, 'production_entry', t('nav.production_entries'), '/production-entries/edit?id=');
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function mapOperationalRows(array $rows, string $entityType, string $label, string $urlBase): array
    {
        $items = [];
        foreach ($rows as $row) {
            $id      = (int)($row['id'] ?? 0);
            $status  = strtolower(trim((string)($row['status'] ?? ($row['dispatch_status'] ?? 'open'))));
            $ageHrs  = self::hoursAgo((string)($row['updated_at'] ?? ''));

            $statusBucket = self::statusBucket($status);
            if ($statusBucket === 'completed') {
                continue;
            }

            $section = $statusBucket === 'blocked' ? self::SECTION_BLOCKED : self::SECTION_NEEDS_ACTION;

            $detailUrl = $urlBase . $id;
            $itemRef = self::buildEntityReference($entityType, $id, $detailUrl, $label);
            $reasonLabel = self::entityDisplayLabel($entityType, $detailUrl, $label);

            $items[] = [
                'dedup_key'       => $entityType . ':' . $id,
                'source'          => 'ops_queue',
                'section_key'     => $section,
                'urgency_rank'    => $statusBucket === 'blocked' ? self::RANK_BLOCKED : self::RANK_NORMAL,
                'entity_type'     => $entityType,
                'entity_id'       => $id,
                'item_ref'        => $itemRef,
                'title'           => $itemRef,
                'parts_name'      => (string)($row['parts_name'] ?? ''),
                'parts_number'    => (string)($row['parts_number'] ?? ''),
                'stage_label'     => ucwords(str_replace('_', ' ', $status)),
                'ownership_state' => '',
                'owner_label'     => '',
                'next_owner'      => '',
                'sla_state'       => 'on_track',
                'sla_label'       => '',
                'age_hours'       => $ageHrs,
                'reason'          => t('ops.my_work.reason.active_scope', ['label' => $reasonLabel]),
                'action_label'    => t('common.open'),
                'action_url'      => $detailUrl,
            ];
        }

        return $items;
    }

    // ----------------------------------------------------------------
    // DEDUPLICATION
    // ----------------------------------------------------------------

    /**
     * Keep highest-urgency item per dedup_key (entity_type:entity_id).
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private static function deduplicateItems(array $items): array
    {
        $best = [];
        foreach ($items as $item) {
            $key  = (string)($item['dedup_key'] ?? '');
            $rank = (int)($item['urgency_rank'] ?? self::RANK_NORMAL);
            if (!isset($best[$key]) || $rank < (int)($best[$key]['urgency_rank'] ?? PHP_INT_MAX)) {
                $best[$key] = $item;
            }
        }

        return array_values($best);
    }

    private static function decorateItemForDisplay(array $item): array
    {
        $title = self::resolveItemTitle($item);
        if ($title !== '') {
            $item['title'] = $title;
            $item['display_label'] = $title;
        }

        if (trim((string)($item['item_ref'] ?? '')) === '') {
            $item['item_ref'] = $title;
        }

        return $item;
    }

    private static function resolveItemTitle(array $item): string
    {
        $entityType = strtolower(trim((string)($item['entity_type'] ?? '')));
        $entityId = (int)($item['entity_id'] ?? 0);
        $actionUrl = (string)($item['action_url'] ?? '');

        $entityLabel = self::entityDisplayLabel($entityType, $actionUrl, (string)($item['item_ref'] ?? ''));
        if ($entityLabel !== '') {
            return $entityId > 0 ? $entityLabel . ' #' . $entityId : $entityLabel;
        }

        $title = self::normalizeDisplayLabel((string)($item['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $itemRef = self::normalizeDisplayLabel((string)($item['item_ref'] ?? ''));
        if ($itemRef !== '') {
            return $itemRef;
        }

        return self::genericItemTitle((string)($item['section_key'] ?? ''), $entityId);
    }

    private static function buildEntityReference(string $entityType, int $entityId, string $actionUrl = '', string $fallback = ''): string
    {
        $label = self::entityDisplayLabel($entityType, $actionUrl, $fallback);
        if ($label === '') {
            return self::genericItemTitle('', $entityId);
        }

        return $entityId > 0 ? $label . ' #' . $entityId : $label;
    }

    private static function entityDisplayLabel(string $entityType, string $actionUrl = '', string $fallback = ''): string
    {
        $entityType = strtolower(trim($entityType));
        if ($entityType !== '') {
            return match ($entityType) {
                'daily_order' => 'Daily Order',
                'production_entry' => 'Production Entry',
                'production_plan' => 'Production Plan',
                'qc_entry' => 'QC Entry',
                'dispatch_entry' => 'Dispatch Entry',
                'assembly_plan' => 'Assembly Plan',
                'assembly_entry' => 'Assembly Entry',
                'attendance_exception' => 'Attendance Exception',
                'draft_timecard' => 'Draft Timecard',
                'payroll_draft' => 'Payroll Draft',
                default => self::normalizeDisplayLabel(ucwords(str_replace('_', ' ', $entityType))),
            };
        }

        $path = strtolower((string)(parse_url($actionUrl, PHP_URL_PATH) ?: $actionUrl));
        if ($path !== '') {
            return match (true) {
                str_contains($path, '/apps/manufacturing/daily-orders/') || str_contains($path, '/daily-orders/') => 'Daily Order',
                str_contains($path, '/production-entries/') => 'Production Entry',
                str_contains($path, '/apps/manufacturing/production-plans/') || str_contains($path, '/production-plans/') => 'Production Plan',
                str_contains($path, '/qc-entries/') => 'QC Entry',
                str_contains($path, '/dispatch-entries/') => 'Dispatch Entry',
                str_contains($path, '/assembly-plans/') => 'Assembly Plan',
                str_contains($path, '/assembly-entries/') => 'Assembly Entry',
                str_contains($path, '/apps/sbaio/attendance') => 'Attendance Exception',
                str_contains($path, '/apps/sbaio/timecards') => 'Draft Timecard',
                str_contains($path, '/apps/sbaio/payroll') => 'Payroll Draft',
                default => '',
            };
        }

        return self::normalizeDisplayLabel($fallback);
    }

    private static function normalizeDisplayLabel(string $label): string
    {
        $label = trim((string)preg_replace('/\s+/', ' ', $label));
        if ($label === '') {
            return '';
        }

        $normalized = strtolower($label);
        if (in_array($normalized, ['work item', 'item', 'work', 'task'], true)) {
            return '';
        }

        return $label;
    }

    private static function genericItemTitle(string $sectionKey, int $entityId): string
    {
        $base = match ($sectionKey) {
            self::SECTION_AWAITING_APPROVAL => 'Approval Item',
            self::SECTION_OVERDUE => 'Escalated Item',
            self::SECTION_BLOCKED => 'Follow-up Item',
            default => 'Action Item',
        };

        return $entityId > 0 ? $base . ' #' . $entityId : $base;
    }

    // ----------------------------------------------------------------
    // CLASSIFICATION HELPERS
    // ----------------------------------------------------------------

    /**
     * @return array{0:string, 1:int}  [section_key, urgency_rank]
     */
    private static function classifyHandoffItem(string $slaState, string $ownerState): array
    {
        // Escalated — highest urgency, goes to overdue section for visibility
        if ($ownerState === HandoffEngine::STATE_ESCALATED) {
            return [self::SECTION_OVERDUE, self::RANK_ESCAPED];
        }
        // SLA Breached
        if ($slaState === HandoffEngine::SLA_BREACHED) {
            return [self::SECTION_OVERDUE, self::RANK_BREACHED];
        }
        // Overdue SLA
        if ($slaState === HandoffEngine::SLA_OVERDUE) {
            return [self::SECTION_OVERDUE, self::RANK_OVERDUE];
        }
        // Blocked / Waiting upstream
        if (in_array($ownerState, [HandoffEngine::STATE_BLOCKED, HandoffEngine::STATE_WAITING_UPSTREAM], true)) {
            return [self::SECTION_BLOCKED, self::RANK_BLOCKED];
        }
        // Assigned / waiting downstream — needs action
        if (in_array($ownerState, [
            HandoffEngine::STATE_ASSIGNED,
            HandoffEngine::STATE_PENDING_ASSIGNMENT,
            HandoffEngine::STATE_WAITING_DOWNSTREAM,
            HandoffEngine::STATE_IN_PROGRESS,
        ], true)) {
            return [self::SECTION_NEEDS_ACTION, self::RANK_ASSIGNED];
        }

        return [self::SECTION_NEEDS_ACTION, self::RANK_NORMAL];
    }

    private static function handoffReason(string $slaState, string $ownerState, string $stageLabel): string
    {
        if ($ownerState === HandoffEngine::STATE_ESCALATED) {
            return t('ops.my_work.reason.escalated');
        }
        if ($slaState === HandoffEngine::SLA_BREACHED) {
            return t('ops.my_work.reason.sla_breached', ['stage' => $stageLabel]);
        }
        if ($slaState === HandoffEngine::SLA_OVERDUE) {
            return t('ops.my_work.reason.overdue', ['stage' => $stageLabel]);
        }
        if ($ownerState === HandoffEngine::STATE_BLOCKED) {
            return t('ops.my_work.reason.blocked', ['stage' => $stageLabel]);
        }
        if ($ownerState === HandoffEngine::STATE_WAITING_UPSTREAM) {
            return t('ops.my_work.reason.waiting_upstream');
        }
        if ($ownerState === HandoffEngine::STATE_WAITING_DOWNSTREAM) {
            return t('ops.my_work.reason.waiting_downstream');
        }
        if ($ownerState === HandoffEngine::STATE_IN_PROGRESS) {
            return t('ops.my_work.reason.in_progress', ['stage' => $stageLabel]);
        }
        if ($ownerState === HandoffEngine::STATE_ASSIGNED) {
            return t('ops.my_work.reason.assigned', ['stage' => $stageLabel]);
        }
        return t('ops.my_work.reason.active', ['stage' => $stageLabel]);
    }

    // ----------------------------------------------------------------
    // KPI
    // ----------------------------------------------------------------

    /**
     * @param array<string,array<int,array<string,mixed>>> $sections
     * @param array<int,array<string,mixed>>               $all
     * @return array<string,int>
     */
    private static function buildKpi(array $sections, array $all): array
    {
        $breached = 0;
        $today    = date('Y-m-d');

        foreach ($all as $item) {
            if ((string)($item['sla_state'] ?? '') === HandoffEngine::SLA_BREACHED) {
                $breached++;
            }
        }

        return [
            'total'             => count($all),
            'needs_action'      => count($sections[self::SECTION_NEEDS_ACTION] ?? []),
            'awaiting_approval' => count($sections[self::SECTION_AWAITING_APPROVAL] ?? []),
            'overdue_escalated' => count($sections[self::SECTION_OVERDUE] ?? []),
            'blocked'           => count($sections[self::SECTION_BLOCKED] ?? []),
            'sla_breached'      => $breached,
        ];
    }

    // ----------------------------------------------------------------
    // ROLE LINKS (quick navigation for the sidebar/header)
    // ----------------------------------------------------------------

    /**
     * @param array<string,mixed> $ctx
     */
    public static function primaryWorkArea(array $ctx): string
    {
        $profile = strtolower(trim((string)($ctx['operational_role'] ?? '')));
        $slug = str_replace([' ', '-'], '', $profile);
        if (str_contains($slug, 'assembly')) {
            return 'assembly';
        }
        if (str_contains($slug, 'qc')) {
            return 'qc';
        }
        if (str_contains($slug, 'dispatch')) {
            return 'dispatch';
        }
        if (str_contains($slug, 'planner') || str_contains($slug, 'order') || str_contains($slug, 'planning')) {
            return 'order';
        }
        return 'production';
    }

    /**
     * @param array<string,mixed> $ctx
     * @return array<int,string>
     */
    public static function crossWorkAreas(array $ctx): array
    {
        $map = [];
        foreach ((array)($ctx['cross_functional_access'] ?? []) as $bundle => $level) {
            $k = strtolower(trim((string)$bundle));
            if ($k === '') {
                continue;
            }
            if (str_contains($k, 'order')) {
                $map['order'] = 'order';
            } elseif (str_contains($k, 'assembly')) {
                $map['assembly'] = 'assembly';
            } elseif (str_contains($k, 'qc')) {
                $map['qc'] = 'qc';
            } elseif (str_contains($k, 'dispatch')) {
                $map['dispatch'] = 'dispatch';
            } elseif (str_contains($k, 'production')) {
                $map['production'] = 'production';
            }
        }
        return array_values($map);
    }

    /**
     * @param array<string,mixed> $item
     */
    public static function workAreaForItem(array $item): string
    {
        $entityType = strtolower(trim((string)($item['entity_type'] ?? '')));
        return match ($entityType) {
            'daily_order' => 'order',
            'assembly_plan' => 'assembly',
            'qc_entry' => 'qc',
            'dispatch_entry' => 'dispatch',
            'production_plan', 'production_entry' => 'production',
            default => 'production',
        };
    }

    /**
     * @param array<string,mixed> $user
     * @param array<int,string>   $allowedModules
     * @return array<int,array<string,mixed>>
     */
    public static function buildRoleLinks(array $user, array $allowedModules): array
    {
        $links = [];
        $roleSlug = strtolower(trim((string)($user['role'] ?? '')));
        $roleSlug = str_replace([' ', '-'], '', $roleSlug);

        if (in_array($roleSlug, ['productionleader', 'assemblyleader', 'productionoperations'], true)) {
            $links[] = ['key' => 'machine_workboard', 'label' => t('nav.machine_leader'), 'url' => '/manufacturing/production-workboard'];
        }
        if (in_array($roleSlug, ['qcleader', 'qcoperations'], true) || self::moduleAllowed($allowedModules, 'qc')) {
            $links[] = ['key' => 'qc_workboard', 'label' => t('nav.qc_leader'), 'url' => '/manufacturing/qc-workboard'];
        }
        if (in_array($roleSlug, ['dispatchleader', 'dispatchoperations'], true) || self::moduleAllowed($allowedModules, 'dispatch')) {
            $links[] = ['key' => 'dispatch_ops', 'label' => t('nav.dispatch_ops'), 'url' => '/manufacturing/dispatch-ops'];
        }
        if (($user['authority_role'] ?? '') === 'platform_admin' || ($user['authority_role'] ?? '') === 'app_admin') {
            $links[] = ['key' => 'cross_role_handoff', 'label' => t('nav.cross_role_handoff'), 'url' => '/apps/manufacturing/handoffs'];
        }

        $filtered = [];
        foreach ($links as $link) {
            $url = trim((string)($link['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $decision = UserDashboardAssignmentService::routeAccessDecision($user, $url, 'GET');
            if ((bool)($decision['allowed'] ?? false)) {
                $filtered[] = $link;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $user
     * @return array<int,string>
     */
    public static function approvalModulesForUser(array $user): array
    {
        $out = [];
        foreach (WorkflowPolicy::modules() as $module) {
            if (WorkflowPolicy::canApprove($module, $user) || WorkflowPolicy::canReopen($module, $user)) {
                $out[] = $module;
            }
        }
        return $out;
    }

    // ----------------------------------------------------------------
    // SUPPORT HELPERS
    // ----------------------------------------------------------------

    /**
     * @param array<string,mixed> $user
     * @param array<int,string>   $allowedModules
     * @return array<int,string>
     */
    private static function ownerRolesForUser(array $user, array $allowedModules): array
    {
        $role = strtolower(str_replace([' ', '-'], '_', trim((string)($user['role'] ?? ''))));
        $roles = [];

        // Map common role slugs to HandoffEngine owner_role values
        if (str_contains($role, 'production') || str_contains($role, 'machine') || str_contains($role, 'assembly')) {
            $roles[] = 'Machine Leader';
            $roles[] = 'Assembly Leader';
        }
        if (str_contains($role, 'qc')) {
            $roles[] = 'QC Leader';
        }
        if (str_contains($role, 'dispatch')) {
            $roles[] = 'Dispatch Leader';
        }

        // fallback: use allowed modules to infer scope
        if ($roles === []) {
            if (self::moduleAllowed($allowedModules, 'production')) {
                $roles[] = 'Machine Leader';
            }
            if (self::moduleAllowed($allowedModules, 'qc')) {
                $roles[] = 'QC Leader';
            }
            if (self::moduleAllowed($allowedModules, 'dispatch')) {
                $roles[] = 'Dispatch Leader';
            }
        }

        return array_values(array_unique($roles));
    }

    private static function entityTypeToModule(string $entityType): string
    {
        return match ($entityType) {
            'daily_order', 'production_plan', 'production_entry', 'assembly_plan' => 'manufacturing',
            'qc_entry'        => 'qc',
            'dispatch_entry'  => 'dispatch',
            default           => '',
        };
    }

    /**
     * @param array<int,string> $allowedModules
     */
    private static function moduleAllowed(array $allowedModules, string $module): bool
    {
        if (in_array('*', $allowedModules, true) || $allowedModules === []) {
            return true;
        }
        return in_array($module, $allowedModules, true);
    }

    private static function statusBucket(string $status): string
    {
        if (str_contains($status, 'block') || str_contains($status, 'hold') || str_contains($status, 'wait')) {
            return 'blocked';
        }
        if (str_contains($status, 'complete') || str_contains($status, 'close') || str_contains($status, 'dispatch') || str_contains($status, 'approve')) {
            return 'completed';
        }
        return 'active';
    }

    private static function hoursAgo(string $datetime): float
    {
        if ($datetime === '') {
            return 0.0;
        }
        try {
            $ts = new \DateTime($datetime);
            $diff = (new \DateTime())->getTimestamp() - $ts->getTimestamp();
            return $diff > 0 ? round($diff / 3600, 2) : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private static function displayName(array $user): string
    {
        $name = trim((string)($user['full_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($user['name'] ?? ''));
        }
        if ($name === '') {
            $name = trim((string)($user['email'] ?? 'User'));
        }
        return $name ?: 'User';
    }

    private static function displayRole(array $user): string
    {
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($authorityRole === 'platform_admin') {
            return 'Platform Admin';
        }

        $role = trim((string)($user['role'] ?? ''));
        $flat = strtolower(preg_replace('/[^a-z0-9]+/', '', $role) ?? '');
        if (in_array($flat, ['itadmin', 'itadministrator', 'admin', 'sysadmin', 'systemadmin', 'systemadministrator', 'platformadmin'], true)) {
            return 'Platform Admin';
        }

        return $role;
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
}
