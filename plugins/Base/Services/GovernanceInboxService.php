<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\DB;
use Plugins\Workflow\Services\WorkflowGovernance;
use Plugins\Workflow\Services\WorkflowPolicy;

final class GovernanceInboxService
{
    private const MODULE_LABELS = [
        WorkflowPolicy::MODULE_PRODUCTION_PLAN => 'Production Plans',
        WorkflowPolicy::MODULE_QC_ENTRY => 'QC Entries',
        WorkflowPolicy::MODULE_DISPATCH_ENTRY => 'Dispatch Entries',
    ];

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function build(array $input, ?array $user = null): array
    {
        WorkflowGovernance::ensureSchema();
        $runtime = function_exists('platform_user_runtime_facade') ? platform_user_runtime_facade() : null;

        if (!$runtime || !$runtime->isAppEnabled('manufacturing')) {
            return [
                'filters' => [
                    'module' => 'all',
                    'bucket' => 'all',
                    'q' => trim((string)($input['q'] ?? '')),
                ],
                'module_options' => ['all' => 'All Modules'],
                'bucket_options' => [
                    'all' => 'All Queue Items',
                    'pending' => 'Pending Approval',
                    'rework' => 'Reopened / Rework',
                    'locked' => 'Locked / Finalized',
                ],
                'summary' => ['all' => 0, 'pending' => 0, 'urgent' => 0, 'rework' => 0, 'locked' => 0, 'recent_activity' => 0],
                'pending_items' => [],
                'pending_urgent' => [],
                'pending_recent' => [],
                'rework_items' => [],
                'locked_items' => [],
                'queue_items' => [],
                'recent_activity' => [],
                'role' => ['raw' => '', 'slug' => '', 'oversight' => false],
                'host_regions' => HostSurfaceRegistryService::build('approval_inbox', ['user' => $user, 'input' => $input, 'summary' => []]),
            ];
        }

        $rawRole = strtolower(trim((string)($user['role'] ?? '')));
        $roleSlug = WorkflowPolicy::roleSlug($user);

        $visibleModules = self::visibleModules($user, $rawRole, $roleSlug);
        $visibleModules = $runtime->filterModulesForUser($user, $visibleModules);
        $context = $runtime->resolveUserContext($user);
        $scope = (array)($context['scope'] ?? []);
        $selectedModule = self::normalizeModule((string)($input['module'] ?? 'all'), $visibleModules);
        $selectedBucket = self::normalizeBucket((string)($input['bucket'] ?? 'all'));
        $search = trim((string)($input['q'] ?? ''));

        $items = self::fetchItems($selectedModule, $visibleModules, $search, $scope);

        $pending = [];
        $rework = [];
        $locked = [];

        foreach ($items as $item) {
            $bucket = (string)($item['bucket'] ?? '');
            if ($bucket === 'pending') {
                $pending[] = $item;
            } elseif ($bucket === 'rework') {
                $rework[] = $item;
            } elseif ($bucket === 'locked') {
                $locked[] = $item;
            }
        }

        usort($pending, static fn(array $a, array $b): int => strcmp((string)($a['updated_at'] ?? ''), (string)($b['updated_at'] ?? '')) * -1);
        usort($rework, static fn(array $a, array $b): int => strcmp((string)($a['updated_at'] ?? ''), (string)($b['updated_at'] ?? '')) * -1);
        usort($locked, static fn(array $a, array $b): int => strcmp((string)($a['updated_at'] ?? ''), (string)($b['updated_at'] ?? '')) * -1);

        $visibleQueue = match ($selectedBucket) {
            'pending' => $pending,
            'rework' => $rework,
            'locked' => $locked,
            default => $items,
        };

        $activity = self::fetchRecentActivity($selectedModule, $visibleModules, $search, $scope);

        $pendingUrgent = array_values(array_filter($pending, static fn(array $i): bool => ($i['urgency_tier'] ?? 'normal') === 'urgent'));
        $pendingRecent = array_values(array_filter($pending, static fn(array $i): bool => ($i['urgency_tier'] ?? 'normal') !== 'urgent'));

        return [
            'filters' => [
                'module' => $selectedModule,
                'bucket' => $selectedBucket,
                'q' => $search,
            ],
            'module_options' => self::moduleOptions($visibleModules),
            'bucket_options' => [
                'all' => 'All Queue Items',
                'pending' => 'Pending Approval',
                'rework' => 'Reopened / Rework',
                'locked' => 'Locked / Finalized',
            ],
            'summary' => [
                'all' => count($items),
                'pending' => count($pending),
                'urgent' => count($pendingUrgent),
                'rework' => count($rework),
                'locked' => count($locked),
                'recent_activity' => count($activity),
            ],
            'pending_items' => array_slice($pending, 0, 150),
            'pending_urgent' => array_slice($pendingUrgent, 0, 100),
            'pending_recent' => array_slice($pendingRecent, 0, 100),
            'rework_items' => array_slice($rework, 0, 150),
            'locked_items' => array_slice($locked, 0, 150),
            'queue_items' => array_slice($visibleQueue, 0, 180),
            'recent_activity' => array_slice($activity, 0, 100),
            'role' => [
                'raw' => $rawRole,
                'slug' => $roleSlug,
                'oversight' => self::isOversightRole($rawRole, $roleSlug),
            ],
            'host_regions' => HostSurfaceRegistryService::build('approval_inbox', [
                'user' => $user,
                'input' => $input,
                'summary' => [
                    'all' => count($items),
                    'pending' => count($pending),
                    'urgent' => count($pendingUrgent),
                    'rework' => count($rework),
                    'locked' => count($locked),
                ],
            ]),
        ];
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<int,string>
     */
    private static function visibleModules(?array $user, string $rawRole, string $roleSlug): array
    {
        if (self::isOversightRole($rawRole, $roleSlug)) {
            return array_keys(self::MODULE_LABELS);
        }

        $visible = [];
        foreach (array_keys(self::MODULE_LABELS) as $module) {
            if (
                WorkflowPolicy::canSubmit($module, $user)
                || WorkflowPolicy::canApprove($module, $user)
                || WorkflowPolicy::canReopen($module, $user)
                || WorkflowPolicy::canOverrideLock($module, $user)
                || WorkflowPolicy::canFinalize($module, $user)
            ) {
                $visible[] = $module;
            }
        }

        return $visible;
    }

    private static function isOversightRole(string $rawRole, string $roleSlug): bool
    {
        if (in_array($roleSlug, ['admin', 'gm'], true)) {
            return true;
        }

        return in_array($rawRole, ['manager', 'supervisor'], true);
    }

    /**
     * @param array<int,string> $visibleModules
     */
    private static function normalizeModule(string $module, array $visibleModules): string
    {
        $module = strtolower(trim($module));
        if ($module === '' || $module === 'all') {
            return 'all';
        }

        foreach ($visibleModules as $candidate) {
            if ($module === strtolower($candidate)) {
                return $candidate;
            }
        }

        return 'all';
    }

    private static function normalizeBucket(string $bucket): string
    {
        $bucket = strtolower(trim($bucket));
        if (in_array($bucket, ['pending', 'rework', 'locked', 'all'], true)) {
            return $bucket;
        }
        return 'all';
    }

    /**
     * @param array<int,string> $visibleModules
     * @return array<string,string>
     */
    private static function moduleOptions(array $visibleModules): array
    {
        $options = ['all' => 'All Modules'];
        foreach ($visibleModules as $module) {
            $options[$module] = (string)(self::MODULE_LABELS[$module] ?? $module);
        }
        return $options;
    }

    /**
     * @param array<int,string> $visibleModules
     * @return array<int,array<string,mixed>>
     */
    private static function fetchItems(string $selectedModule, array $visibleModules, string $search, array $scope): array
    {
        $items = [];

        if (in_array(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $visibleModules, true) && ($selectedModule === 'all' || $selectedModule === WorkflowPolicy::MODULE_PRODUCTION_PLAN)) {
            foreach (self::fetchProductionPlans($search, $scope) as $row) {
                $items[] = self::buildItem(WorkflowPolicy::MODULE_PRODUCTION_PLAN, $row);
            }
        }

        if (in_array(WorkflowPolicy::MODULE_QC_ENTRY, $visibleModules, true) && ($selectedModule === 'all' || $selectedModule === WorkflowPolicy::MODULE_QC_ENTRY)) {
            foreach (self::fetchQcEntries($search, $scope) as $row) {
                $items[] = self::buildItem(WorkflowPolicy::MODULE_QC_ENTRY, $row);
            }
        }

        if (in_array(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $visibleModules, true) && ($selectedModule === 'all' || $selectedModule === WorkflowPolicy::MODULE_DISPATCH_ENTRY)) {
            foreach (self::fetchDispatchEntries($search, $scope) as $row) {
                $items[] = self::buildItem(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $row);
            }
        }

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchProductionPlans(string $search, array $scope): array
    {
        $params = [];
        $sql =
            "SELECT
                pp.*,
                p.parts_name,
                p.parts_number,
                m.machine_no,
                m.machine_name,
                u_added.email AS submitted_by_email
             FROM production_plans pp
             LEFT JOIN products p ON p.id = pp.product_id
             LEFT JOIN machines m ON m.id = pp.machine_id
             LEFT JOIN users u_added ON u_added.id = pp.added_by
             WHERE pp.approval_status IN ('Pending Approval', 'Reopened', 'Rejected', 'Approved')";

        [$scopeSql, $scopeParams] = self::partScopeSql($scope, 'pp.product_id');
        $sql .= $scopeSql;
        foreach ($scopeParams as $sp) {
            $params[] = $sp;
        }

        if ($search !== '') {
            $sql .= ' AND (CAST(pp.id AS CHAR) LIKE ? OR COALESCE(p.parts_name, \"\") LIKE ? OR COALESCE(p.parts_number, \"\") LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= ' ORDER BY pp.updated_at DESC, pp.id DESC LIMIT 240';
        return DB::fetchAll($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchQcEntries(string $search, array $scope): array
    {
        $params = [];
        $sql =
            "SELECT
                q.*,
                p.parts_name,
                p.parts_number,
                NULL AS submitted_by_email
             FROM qc_entries q
             LEFT JOIN products p ON p.id = q.product_id
             WHERE q.approval_status IN ('Pending Approval', 'Reopened', 'Rejected', 'Approved')";

        [$scopeSql, $scopeParams] = self::partScopeSql($scope, 'q.product_id');
        $sql .= $scopeSql;
        foreach ($scopeParams as $sp) {
            $params[] = $sp;
        }

        if ($search !== '') {
            $sql .= ' AND (CAST(q.id AS CHAR) LIKE ? OR COALESCE(p.parts_name, \"\") LIKE ? OR COALESCE(p.parts_number, \"\") LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= ' ORDER BY q.updated_at DESC, q.id DESC LIMIT 240';
        return DB::fetchAll($sql, $params);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDispatchEntries(string $search, array $scope): array
    {
        $params = [];
        $sql =
            "SELECT
                d.*,
                p.parts_name,
                p.parts_number,
                u_prep.email AS submitted_by_email
             FROM dispatch_entries d
             LEFT JOIN products p ON p.id = d.product_id
             LEFT JOIN users u_prep ON u_prep.id = d.prepared_by
             WHERE d.approval_status IN ('Pending Approval', 'Reopened', 'Rejected', 'Approved')
                OR LOWER(COALESCE(d.dispatch_status, '')) = 'dispatched'";

        [$scopeSql, $scopeParams] = self::partScopeSql($scope, 'd.product_id');
        $sql .= $scopeSql;
        foreach ($scopeParams as $sp) {
            $params[] = $sp;
        }

        if ($search !== '') {
            $sql .= ' AND (CAST(d.id AS CHAR) LIKE ? OR COALESCE(p.parts_name, \"\") LIKE ? OR COALESCE(p.parts_number, \"\") LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= ' ORDER BY d.updated_at DESC, d.id DESC LIMIT 240';
        return DB::fetchAll($sql, $params);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function buildItem(string $module, array $row): array
    {
        $id = (int)($row['id'] ?? 0);
        $row = WorkflowGovernance::withDefaults($row);
        $approvalStatus = WorkflowPolicy::normalizeApprovalStatus((string)($row['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT);
        $isLocked = WorkflowPolicy::isLocked($module, $row);

        $bucket = 'pending';
        if (in_array($approvalStatus, [WorkflowPolicy::APPROVAL_REOPENED, WorkflowPolicy::APPROVAL_REJECTED], true)) {
            $bucket = 'rework';
        } elseif ($isLocked) {
            $bucket = 'locked';
        }

        $moduleLabel = (string)(self::MODULE_LABELS[$module] ?? $module);
        $editUrl = self::editUrl($module, $id);
        $dateText = self::itemDate($module, $row);
        $qtyText = self::itemQty($module, $row);

        $updatedAt = (string)($row['updated_at'] ?? '');
        $ageHours = 0.0;
        if ($updatedAt !== '') {
            try {
                $dt = new \DateTimeImmutable($updatedAt);
                $ageHours = max(0.0, (float)(time() - $dt->getTimestamp()) / 3600.0);
            } catch (\Throwable) {
                $ageHours = 0.0;
            }
        }
        $previouslyRejected = trim((string)($row['reopen_reason'] ?? '')) !== '';
        $urgencyTier = ($ageHours > 48.0 || $previouslyRejected) ? 'urgent' : 'normal';

        return [
            'module' => $module,
            'module_label' => $moduleLabel,
            'id' => $id,
            'ref' => strtoupper(str_replace('_', '-', $module)) . '-' . $id,
            'approval_status' => $approvalStatus,
            'locked' => $isLocked,
            'bucket' => $bucket,
            'part_name' => (string)($row['parts_name'] ?? '-'),
            'part_number' => (string)($row['parts_number'] ?? ''),
            'date_text' => $dateText,
            'qty_text' => $qtyText,
            'status_text' => self::statusText($module, $row),
            'workflow_state' => (string)($row['workflow_state'] ?? ''),
            'approval_note' => (string)($row['approval_note'] ?? ''),
            'reopen_reason' => (string)($row['reopen_reason'] ?? ''),
            'override_reason' => (string)($row['override_reason'] ?? ''),
            'updated_at' => $updatedAt,
            'age_hours' => $ageHours,
            'previously_rejected' => $previouslyRejected,
            'urgency_tier' => $urgencyTier,
            'submitted_by' => (string)($row['submitted_by_email'] ?? ''),
            'edit_url' => $editUrl,
            'actions' => self::itemActions($module, $row, $editUrl),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function itemDate(string $module, array $row): string
    {
        if ($module === WorkflowPolicy::MODULE_PRODUCTION_PLAN) {
            return 'Plan ' . (string)($row['plan_date'] ?? '-');
        }
        if ($module === WorkflowPolicy::MODULE_QC_ENTRY) {
            return 'Updated ' . (string)($row['updated_at'] ?? $row['created_at'] ?? '-');
        }
        return 'Dispatch ' . (string)($row['dispatch_date'] ?? '-');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function itemQty(string $module, array $row): string
    {
        if ($module === WorkflowPolicy::MODULE_PRODUCTION_PLAN) {
            return 'Planned ' . number_format((float)($row['planned_qty'] ?? 0), 2, '.', ',');
        }
        if ($module === WorkflowPolicy::MODULE_QC_ENTRY) {
            return 'Checked ' . number_format((float)($row['checked_qty'] ?? 0), 2, '.', ',')
                . ' | Pass ' . number_format((float)($row['pass_qty'] ?? 0), 2, '.', ',');
        }
        return 'Dispatchable ' . number_format((float)($row['dispatchable_qty'] ?? 0), 2, '.', ',');
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function statusText(string $module, array $row): string
    {
        if ($module === WorkflowPolicy::MODULE_DISPATCH_ENTRY) {
            return 'Lifecycle: ' . (string)($row['dispatch_status'] ?? '-');
        }
        return 'Status: ' . (string)($row['status'] ?? '-');
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,array<string,mixed>>
     */
    private static function itemActions(string $module, array $row, string $editUrl): array
    {
        $actions = [
            [
                'key' => 'open',
                'label' => 'Open',
                'endpoint' => $editUrl,
                'method' => 'link',
            ],
        ];

        $currentUser = \App\Core\Auth::user();

        foreach (['submit', 'approve', 'reject', 'reopen', 'unlock_override'] as $action) {
            if (!WorkflowPolicy::canTransitionApproval($module, $row, $action, $currentUser)) {
                continue;
            }

            $actions[] = [
                'key' => $action,
                'label' => self::actionLabel($action),
                'action_hint' => self::actionHint($action),
                'endpoint' => self::approvalEndpoint($module),
                'method' => 'post',
                'requires_reason' => in_array($action, ['reject', 'reopen', 'unlock_override'], true),
                'supports_note' => in_array($action, ['submit', 'approve', 'reject', 'reopen'], true),
                'post' => [
                    'id' => (int)($row['id'] ?? 0),
                    'action' => $action,
                ],
            ];
        }

        if ($module === WorkflowPolicy::MODULE_DISPATCH_ENTRY) {
            $dispatchStatus = strtolower(trim((string)($row['dispatch_status'] ?? '')));
            $approvalStatus = WorkflowPolicy::normalizeApprovalStatus((string)($row['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT);
            if (
                $dispatchStatus !== 'dispatched'
                && $approvalStatus === WorkflowPolicy::APPROVAL_APPROVED
                && WorkflowPolicy::canFinalize(WorkflowPolicy::MODULE_DISPATCH_ENTRY, $currentUser)
            ) {
                $actions[] = [
                    'key' => 'finalize',
                    'label' => 'Finalize',
                    'endpoint' => '/dispatch-entries/transition',
                    'method' => 'post',
                    'requires_reason' => false,
                    'supports_note' => false,
                    'post' => [
                        'id' => (int)($row['id'] ?? 0),
                        'target_status' => 'Dispatched',
                        'reason' => 'Finalized from governance queue',
                    ],
                ];
            }
        }

        return $actions;
    }

    private static function actionLabel(string $action): string
    {
        return match ($action) {
            'submit' => 'Submit',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'reopen' => 'Reopen',
            'unlock_override' => 'Unlock Override',
            default => ucfirst($action),
        };
    }

    private static function actionHint(string $action): string
    {
        return match ($action) {
            'submit'           => 'Sends this record for governance approval.',
            'approve'          => 'Approves this record. It will be locked for execution.',
            'reject'           => 'Sends back for revision. Submitter must rework and resubmit.',
            'reopen'           => 'Unlocks this record and returns it for editing.',
            'unlock_override'  => 'Force-unlocks this record, bypassing the approval chain.',
            'finalize'         => 'Confirms dispatch. Record will be marked as Dispatched.',
            default            => '',
        };
    }

    private static function approvalEndpoint(string $module): string
    {
        return match ($module) {
            WorkflowPolicy::MODULE_PRODUCTION_PLAN => '/apps/manufacturing/production-plans/approval-action',
            WorkflowPolicy::MODULE_QC_ENTRY => '/qc-entries/approval-action',
            WorkflowPolicy::MODULE_DISPATCH_ENTRY => '/dispatch-entries/approval-action',
            default => '#',
        };
    }

    private static function editUrl(string $module, int $id): string
    {
        return match ($module) {
            WorkflowPolicy::MODULE_PRODUCTION_PLAN => '/apps/manufacturing/production-plans/edit?id=' . $id,
            WorkflowPolicy::MODULE_QC_ENTRY => '/qc-entries/edit?id=' . $id,
            WorkflowPolicy::MODULE_DISPATCH_ENTRY => '/dispatch-entries/edit?id=' . $id,
            default => '/'
        };
    }

    /**
     * @param array<int,string> $visibleModules
     * @return array<int,array<string,mixed>>
     */
    private static function fetchRecentActivity(string $selectedModule, array $visibleModules, string $search, array $scope): array
    {
        if (empty($visibleModules)) {
            return [];
        }

        $params = [];
        $sql =
            "SELECT
                e.id,
                e.module_name,
                e.record_id,
                e.action_name,
                e.previous_approval_status,
                e.new_approval_status,
                e.previous_locked_state,
                e.new_locked_state,
                e.reason_text,
                e.note_text,
                e.acted_at,
                u.email AS actor_email
             FROM workflow_approval_events e
             LEFT JOIN users u ON u.id = e.acted_by
             WHERE 1=1";

        if ($selectedModule !== 'all') {
            $sql .= ' AND e.module_name = ?';
            $params[] = $selectedModule;
        } else {
            $in = implode(',', array_fill(0, count($visibleModules), '?'));
            $sql .= " AND e.module_name IN ({$in})";
            foreach ($visibleModules as $module) {
                $params[] = $module;
            }
        }

        if ($search !== '') {
            $sql .= ' AND (CAST(e.record_id AS CHAR) LIKE ? OR COALESCE(e.note_text, "") LIKE ? OR COALESCE(e.reason_text, "") LIKE ?)';
            $q = '%' . $search . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql .= ' ORDER BY e.acted_at DESC, e.id DESC LIMIT 120';

        $rows = DB::fetchAll($sql, $params);
        $activity = [];
        foreach ($rows as $row) {
            if (!self::activityWithinPartScope($row, $scope)) {
                continue;
            }
            $module = (string)($row['module_name'] ?? '');
            $recordId = (int)($row['record_id'] ?? 0);
            $activity[] = [
                'id' => (int)($row['id'] ?? 0),
                'module' => $module,
                'module_label' => (string)(self::MODULE_LABELS[$module] ?? $module),
                'record_id' => $recordId,
                'action_name' => (string)($row['action_name'] ?? ''),
                'from_approval' => (string)($row['previous_approval_status'] ?? ''),
                'to_approval' => (string)($row['new_approval_status'] ?? ''),
                'from_locked' => (int)($row['previous_locked_state'] ?? 0) === 1,
                'to_locked' => (int)($row['new_locked_state'] ?? 0) === 1,
                'reason_text' => (string)($row['reason_text'] ?? ''),
                'note_text' => (string)($row['note_text'] ?? ''),
                'actor_email' => (string)($row['actor_email'] ?? 'System'),
                'acted_at' => (string)($row['acted_at'] ?? ''),
                'open_url' => self::editUrl($module, $recordId),
            ];
        }

        return $activity;
    }

    /**
     * @return array{0:string,1:array<int,int>}
     */
    private static function partScopeSql(array $scope, string $qualifiedColumn): array
    {
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        if (!$partIds) {
            return ['', []];
        }

        $holders = implode(',', array_fill(0, count($partIds), '?'));
        return [" AND {$qualifiedColumn} IN ({$holders})", $partIds];
    }

    private static function activityWithinPartScope(array $row, array $scope): bool
    {
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        if (!$partIds) {
            return true;
        }

        $module = (string)($row['module_name'] ?? '');
        $recordId = (int)($row['record_id'] ?? 0);
        if ($recordId <= 0) {
            return false;
        }

        if ($module === WorkflowPolicy::MODULE_PRODUCTION_PLAN) {
            $r = DB::fetchOne('SELECT product_id FROM production_plans WHERE id=? LIMIT 1', [$recordId]);
            return in_array((int)($r['product_id'] ?? 0), $partIds, true);
        }
        if ($module === WorkflowPolicy::MODULE_QC_ENTRY) {
            $r = DB::fetchOne('SELECT product_id FROM qc_entries WHERE id=? LIMIT 1', [$recordId]);
            return in_array((int)($r['product_id'] ?? 0), $partIds, true);
        }
        if ($module === WorkflowPolicy::MODULE_DISPATCH_ENTRY) {
            $r = DB::fetchOne('SELECT product_id FROM dispatch_entries WHERE id=? LIMIT 1', [$recordId]);
            return in_array((int)($r['product_id'] ?? 0), $partIds, true);
        }

        return true;
    }
}
