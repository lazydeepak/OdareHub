<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\DB;

final class OperatorWorkboardService
{
    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function build(array $input, ?array $user): array
    {
        UserDashboardAssignmentService::ensureSchema();

        $effectiveUser = is_array($user) ? $user : [];
        $ctx = UserDashboardAssignmentService::resolveUserContext($effectiveUser);
        $scope = (array)($ctx['scope'] ?? []);
        $allowedModules = UserDashboardAssignmentService::enabledModulesForUser($effectiveUser);
        $dutyCodes = array_values((array)($ctx['duty_codes'] ?? []));

        $queue = self::buildQueue($scope, $allowedModules);
        $summary = self::buildSummary($queue);
        $handoffs = self::buildHandoffs($effectiveUser, $scope, $allowedModules, $dutyCodes);
        $quickActions = self::buildQuickActions($effectiveUser, $allowedModules);
        $alerts = self::buildAlerts($effectiveUser);

        return [
            'dashboard_type' => (string)($ctx['dashboard_type'] ?? 'operator'),
            'summary' => $summary,
            'queue' => $queue,
            'handoffs' => $handoffs,
            'quick_actions' => $quickActions,
            'alerts' => $alerts,
            'scope' => [
                'machine_ids' => (array)($scope['machine_ids'] ?? []),
                'part_ids' => (array)($scope['part_ids'] ?? []),
                'department_code' => (string)($scope['department_code'] ?? ''),
                'branch_code' => (string)($scope['branch_code'] ?? ''),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<int,string> $allowedModules
     * @return array<int,array<string,mixed>>
     */
    private static function buildQueue(array $scope, array $allowedModules): array
    {
        $rows = [];

        if (self::moduleAllowed($allowedModules, 'production')) {
            $rows = array_merge($rows, self::fetchProductionQueue($scope));
        }
        if (self::moduleAllowed($allowedModules, 'assembly')) {
            $rows = array_merge($rows, self::fetchAssemblyQueue($scope));
        }
        if (self::moduleAllowed($allowedModules, 'qc')) {
            $rows = array_merge($rows, self::fetchQcQueue($scope));
        }
        if (self::moduleAllowed($allowedModules, 'dispatch')) {
            $rows = array_merge($rows, self::fetchDispatchQueue($scope));
        }

        usort($rows, static function (array $a, array $b): int {
            $aTs = (int)($a['due_ts'] ?? PHP_INT_MAX);
            $bTs = (int)($b['due_ts'] ?? PHP_INT_MAX);
            if ($aTs !== $bTs) {
                return $aTs <=> $bTs;
            }
            return strcmp((string)($a['task_ref'] ?? ''), (string)($b['task_ref'] ?? ''));
        });

        return array_slice($rows, 0, 220);
    }

    /**
     * @param array<int,array<string,mixed>> $queue
     * @return array<string,int>
     */
    private static function buildSummary(array $queue): array
    {
        $pending = 0;
        $inProgress = 0;
        $completedToday = 0;
        $blocked = 0;

        $today = date('Y-m-d');
        foreach ($queue as $item) {
            $bucket = (string)($item['status_bucket'] ?? 'pending');
            if ($bucket === 'blocked') {
                $blocked++;
            } elseif ($bucket === 'in_progress') {
                $inProgress++;
            } elseif ($bucket === 'completed') {
                $completedDate = substr((string)($item['status_date'] ?? ''), 0, 10);
                if ($completedDate === $today) {
                    $completedToday++;
                }
            } else {
                $pending++;
            }
        }

        return [
            'pending' => $pending,
            'in_progress' => $inProgress,
            'completed_today' => $completedToday,
            'blocked' => $blocked,
        ];
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<int,string> $allowedModules
     * @param array<int,string> $dutyCodes
     * @param array<string,mixed>|null $user
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function buildHandoffs(?array $user, array $scope, array $allowedModules, array $dutyCodes): array
    {
        self::ensureHandoffTable();
        if (!self::tableExists('handoff_tracking')) {
            return ['incoming' => [], 'outgoing' => [], 'blocked' => []];
        }

        $roles = self::operatorRoleTargets($allowedModules, $dutyCodes);
        $clauses = [];
        $params = [];

        $userId = (int)($user['id'] ?? 0);
        if ($userId > 0) {
            $clauses[] = 'ht.owner_user_id = ?';
            $params[] = $userId;
        }

        if (!empty($roles)) {
            $holders = implode(',', array_fill(0, count($roles), '?'));
            $clauses[] = "ht.owner_role IN ({$holders})";
            foreach ($roles as $r) {
                $params[] = $r;
            }
        }

        if (empty($clauses)) {
            return ['incoming' => [], 'outgoing' => [], 'blocked' => []];
        }

        $rows = DB::fetchAll(
            "SELECT ht.entity_type, ht.entity_id, ht.stage, ht.owner_role, ht.ready_since, ht.blocked_since, ht.released_since, ht.updated_at,
                    p.parts_name, p.parts_number, p.id AS product_id
             FROM handoff_tracking ht
             LEFT JOIN production_entries pe ON ht.entity_type='production_entry' AND pe.id=ht.entity_id
             LEFT JOIN qc_entries qe ON ht.entity_type='qc_entry' AND qe.id=ht.entity_id
             LEFT JOIN dispatch_entries de ON ht.entity_type='dispatch_entry' AND de.id=ht.entity_id
             LEFT JOIN daily_orders o ON ht.entity_type='daily_order' AND o.id=ht.entity_id
             LEFT JOIN products p ON p.id = COALESCE(pe.product_id, qe.product_id, de.product_id, o.product_id)
             WHERE ht.released_since IS NULL AND (" . implode(' OR ', $clauses) . ")
             ORDER BY ht.updated_at DESC
             LIMIT 160",
            $params
        );

        $incoming = [];
        $outgoing = [];
        $blocked = [];

        foreach ($rows as $row) {
            if (!self::rowMatchesScope($row, $scope)) {
                continue;
            }

            $item = [
                'task_ref' => (string)($row['entity_type'] ?? '') . '-' . (int)($row['entity_id'] ?? 0),
                'part' => trim((string)($row['parts_name'] ?? '-')),
                'part_number' => trim((string)($row['parts_number'] ?? '')),
                'stage' => trim((string)($row['stage'] ?? '')),
                'owner_role' => trim((string)($row['owner_role'] ?? '')),
                'updated_at' => (string)($row['updated_at'] ?? ''),
            ];

            $readySince = trim((string)($row['ready_since'] ?? ''));
            $blockedSince = trim((string)($row['blocked_since'] ?? ''));
            if ($blockedSince !== '') {
                $blocked[] = $item;
            } elseif ($readySince !== '') {
                $incoming[] = $item;
            } else {
                $outgoing[] = $item;
            }
        }

        return [
            'incoming' => array_slice($incoming, 0, 20),
            'outgoing' => array_slice($outgoing, 0, 20),
            'blocked' => array_slice($blocked, 0, 20),
        ];
    }

    /**
     * @param array<string,mixed>|null $user
     * @param array<int,string> $allowedModules
     * @return array<int,array<string,string>>
     */
    private static function buildQuickActions(?array $user, array $allowedModules): array
    {
        $actions = [
            ['label' => 'Open Assigned Work', 'url' => '/'],
            ['label' => 'Report Issue', 'url' => '/apps/manufacturing/handoffs'],
        ];

        if (self::moduleAllowed($allowedModules, 'production')) {
            $actions[] = ['label' => 'Start Task', 'url' => '/production-entries/add'];
            $actions[] = ['label' => 'Pause / Hold', 'url' => '/manufacturing/production-queue'];
        }
        if (self::moduleAllowed($allowedModules, 'qc')) {
            $actions[] = ['label' => 'Mark Complete', 'url' => '/qc-entries'];
        }
        if (self::moduleAllowed($allowedModules, 'dispatch')) {
            $actions[] = ['label' => 'Mark Complete', 'url' => '/dispatch-entries'];
        }

        $filtered = [];
        foreach ($actions as $action) {
            $decision = UserDashboardAssignmentService::routeAccessDecision($user, (string)($action['url'] ?? '/'), 'GET');
            if ((bool)($decision['allowed'] ?? false)) {
                $filtered[] = $action;
            }
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<int,array<string,mixed>>
     */
    private static function buildAlerts(?array $user): array
    {
        $rows = NotificationService::listForUser($user, NotificationService::STATUS_NEW);
        $out = [];
        foreach (array_slice($rows, 0, 20) as $row) {
            $out[] = [
                'title' => (string)($row['title'] ?? ''),
                'message' => (string)($row['message'] ?? ''),
                'severity' => strtolower(trim((string)($row['severity'] ?? 'info'))),
                'created_at' => (string)($row['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchProductionQueue(array $scope): array
    {
        if (!self::tableExists('production_plans')) {
            return [];
        }

        $filters = UserDashboardAssignmentService::scopeFiltersForTable(
            $scope,
            'production_plans',
            ['product_id' => 'part_ids', 'machine_id' => 'machine_ids']
        );

        try {
            $rows = DB::fetchAll(
                "SELECT pp.id, pp.product_id, pp.plan_date, pp.planned_qty, pp.status, pp.updated_at,
                        p.parts_name, p.parts_number
                 FROM production_plans pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 WHERE LOWER(COALESCE(pp.status,'')) NOT IN ('completed','closed')" . $filters['sql'] . "
                 ORDER BY COALESCE(pp.plan_date, CURDATE()) ASC, pp.id DESC
                 LIMIT 80",
                $filters['params']
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapQueueRows($rows, 'Production', 'production', static function (array $r): array {
            $due = (string)($r['plan_date'] ?? '');
            $status = (string)($r['status'] ?? 'Open');
            return [
                'task_ref' => 'PP-' . (int)($r['id'] ?? 0),
                'part' => (string)($r['parts_name'] ?? '-'),
                'part_number' => (string)($r['parts_number'] ?? ''),
                'stage' => 'Production',
                'qty' => (float)($r['planned_qty'] ?? 0),
                'due' => $due,
                'status' => $status,
                'status_bucket' => self::statusBucket($status),
                'status_date' => (string)($r['updated_at'] ?? ''),
                'action_label' => 'Open Task',
                'action_url' => '/apps/manufacturing/production-plans/edit?id=' . (int)($r['id'] ?? 0),
            ];
        });
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchAssemblyQueue(array $scope): array
    {
        if (!self::tableExists('mfg_assembly_entries')) {
            return [];
        }

        $filters = UserDashboardAssignmentService::scopeFiltersForTable(
            $scope,
            'mfg_assembly_entries',
            ['product_id' => 'part_ids']
        );

        try {
            $rows = DB::fetchAll(
                "SELECT a.id, a.product_id, a.planned_qty, a.completed_qty, a.status, a.updated_at,
                        p.parts_name, p.parts_number
                 FROM mfg_assembly_entries a
                 LEFT JOIN products p ON p.id = a.product_id
                 WHERE LOWER(COALESCE(a.status,'')) NOT IN ('completed','closed','approved')" . $filters['sql'] . "
                 ORDER BY a.id DESC
                 LIMIT 80",
                $filters['params']
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapQueueRows($rows, 'Assembly', 'assembly', static function (array $r): array {
            $status = (string)($r['status'] ?? 'Open');
            $qty = max(0.0, (float)($r['planned_qty'] ?? 0) - (float)($r['completed_qty'] ?? 0));
            return [
                'task_ref' => 'ASM-' . (int)($r['id'] ?? 0),
                'part' => (string)($r['parts_name'] ?? '-'),
                'part_number' => (string)($r['parts_number'] ?? ''),
                'stage' => 'Assembly',
                'qty' => $qty,
                'due' => '',
                'status' => $status,
                'status_bucket' => self::statusBucket($status),
                'status_date' => (string)($r['updated_at'] ?? ''),
                'action_label' => 'Open Task',
                'action_url' => '/manufacturing/assembly-plans',
            ];
        });
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchQcQueue(array $scope): array
    {
        if (!self::tableExists('qc_entries')) {
            return [];
        }

        $filters = UserDashboardAssignmentService::scopeFiltersForTable(
            $scope,
            'qc_entries',
            ['product_id' => 'part_ids']
        );

        try {
            $rows = DB::fetchAll(
                "SELECT q.id, q.product_id, q.checked_qty, q.status, q.updated_at,
                        p.parts_name, p.parts_number
                 FROM qc_entries q
                 LEFT JOIN products p ON p.id = q.product_id
                 WHERE LOWER(COALESCE(q.status,'')) NOT IN ('completed','closed','approved')" . $filters['sql'] . "
                 ORDER BY q.id DESC
                 LIMIT 80",
                $filters['params']
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapQueueRows($rows, 'QC', 'qc', static function (array $r): array {
            $status = (string)($r['status'] ?? 'Open');
            return [
                'task_ref' => 'QC-' . (int)($r['id'] ?? 0),
                'part' => (string)($r['parts_name'] ?? '-'),
                'part_number' => (string)($r['parts_number'] ?? ''),
                'stage' => 'QC',
                'qty' => (float)($r['checked_qty'] ?? 0),
                'due' => '',
                'status' => $status,
                'status_bucket' => self::statusBucket($status),
                'status_date' => (string)($r['updated_at'] ?? ''),
                'action_label' => 'Open Task',
                'action_url' => '/qc-entries/edit?id=' . (int)($r['id'] ?? 0),
            ];
        });
    }

    /**
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDispatchQueue(array $scope): array
    {
        if (!self::tableExists('dispatch_entries')) {
            return [];
        }

        $filters = UserDashboardAssignmentService::scopeFiltersForTable(
            $scope,
            'dispatch_entries',
            ['product_id' => 'part_ids']
        );

        try {
            $rows = DB::fetchAll(
                "SELECT d.id, d.product_id, d.dispatchable_qty, d.dispatch_date, d.dispatch_status, d.updated_at,
                        p.parts_name, p.parts_number
                 FROM dispatch_entries d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE LOWER(COALESCE(d.dispatch_status,'')) NOT IN ('completed','closed','dispatched')" . $filters['sql'] . "
                 ORDER BY COALESCE(d.dispatch_date, CURDATE()) ASC, d.id DESC
                 LIMIT 80",
                $filters['params']
            );
        } catch (\Throwable $e) {
            return [];
        }

        return self::mapQueueRows($rows, 'Dispatch', 'dispatch', static function (array $r): array {
            $status = (string)($r['dispatch_status'] ?? 'Draft');
            return [
                'task_ref' => 'DSP-' . (int)($r['id'] ?? 0),
                'part' => (string)($r['parts_name'] ?? '-'),
                'part_number' => (string)($r['parts_number'] ?? ''),
                'stage' => 'Dispatch',
                'qty' => (float)($r['dispatchable_qty'] ?? 0),
                'due' => (string)($r['dispatch_date'] ?? ''),
                'status' => $status,
                'status_bucket' => self::statusBucket($status),
                'status_date' => (string)($r['updated_at'] ?? ''),
                'action_label' => 'Open Task',
                'action_url' => '/dispatch-entries/edit?id=' . (int)($r['id'] ?? 0),
            ];
        });
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param callable(array<string,mixed>):array<string,mixed> $mapper
     * @return array<int,array<string,mixed>>
     */
    private static function mapQueueRows(array $rows, string $stage, string $module, callable $mapper): array
    {
        $out = [];
        foreach ($rows as $row) {
            $mapped = $mapper($row);
            $due = trim((string)($mapped['due'] ?? ''));
            $out[] = [
                'task_ref' => (string)($mapped['task_ref'] ?? ''),
                'part' => (string)($mapped['part'] ?? '-'),
                'part_number' => (string)($mapped['part_number'] ?? ''),
                'stage' => (string)($mapped['stage'] ?? $stage),
                'qty' => (float)($mapped['qty'] ?? 0),
                'due' => $due,
                'due_ts' => $due !== '' ? (strtotime($due) ?: PHP_INT_MAX) : PHP_INT_MAX,
                'status' => (string)($mapped['status'] ?? 'Open'),
                'status_bucket' => (string)($mapped['status_bucket'] ?? 'pending'),
                'status_date' => (string)($mapped['status_date'] ?? ''),
                'action_label' => (string)($mapped['action_label'] ?? 'Open'),
                'action_url' => (string)($mapped['action_url'] ?? '/'),
                'module' => $module,
            ];
        }

        return $out;
    }

    private static function statusBucket(string $status): string
    {
        $s = strtolower(trim($status));
        if ($s === '') {
            return 'pending';
        }

        if (str_contains($s, 'block') || str_contains($s, 'hold') || str_contains($s, 'pause') || str_contains($s, 'wait')) {
            return 'blocked';
        }
        if (str_contains($s, 'progress') || str_contains($s, 'wip') || str_contains($s, 'start') || str_contains($s, 'ready') || str_contains($s, 'prepared')) {
            return 'in_progress';
        }
        if (str_contains($s, 'complete') || str_contains($s, 'close') || str_contains($s, 'approve') || str_contains($s, 'dispatch')) {
            return 'completed';
        }

        return 'pending';
    }

    /**
     * @param array<int,string> $allowedModules
     */
    private static function moduleAllowed(array $allowedModules, string $module): bool
    {
        if (empty($allowedModules)) {
            return true;
        }
        return in_array($module, $allowedModules, true);
    }

    private static function tableExists(string $table): bool
    {
        try {
            $safe = DB::conn()->real_escape_string($table);
            return DB::fetchOne("SHOW TABLES LIKE '{$safe}'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function ensureHandoffTable(): void
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

    /**
     * @param array<string,mixed> $row
     * @param array<string,mixed> $scope
     */
    private static function rowMatchesScope(array $row, array $scope): bool
    {
        $parts = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        if (empty($parts)) {
            return true;
        }

        $productId = (int)($row['product_id'] ?? 0);
        return $productId > 0 && in_array($productId, $parts, true);
    }

    /**
     * @param array<int,string> $allowedModules
     * @param array<int,string> $dutyCodes
     * @return array<int,string>
     */
    private static function operatorRoleTargets(array $allowedModules, array $dutyCodes): array
    {
        $targets = [];

        if (self::moduleAllowed($allowedModules, 'production') || self::moduleAllowed($allowedModules, 'assembly')) {
            $targets[] = 'Machine Leader';
        }
        if (self::moduleAllowed($allowedModules, 'qc') || in_array('qc_release', $dutyCodes, true)) {
            $targets[] = 'QC Leader';
        }
        if (self::moduleAllowed($allowedModules, 'dispatch') || in_array('dispatch_release', $dutyCodes, true)) {
            $targets[] = 'Dispatch Leader';
        }

        if (empty($targets)) {
            $targets[] = 'Machine Leader';
        }

        return array_values(array_unique($targets));
    }
}
