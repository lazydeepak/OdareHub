<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\DB;
use App\Core\Auth;

final class RoleInboxService
{
    public const ROLE_MACHINE = 'Machine Leader';
    public const ROLE_QC = 'QC Leader';
    public const ROLE_DISPATCH = 'Dispatch Leader';

    private const STAGE_PROD_TO_QC = 'production_to_qc';
    private const STAGE_QC_TO_DISPATCH = 'qc_to_dispatch';
    private const STAGE_DISPATCH_TO_COMPLETION = 'dispatch_to_completion';
    private const STAGE_ORDER_RISK = 'order_risk';

    private const NEW_READY_WINDOW_HOURS = 2;
    private const MAX_ITEMS = 220;

    /**
     * @var array<string,array{warn:int,breach:int,label:string}>
     */
    private const SLA_HOURS = [
        self::STAGE_PROD_TO_QC => ['warn' => 4, 'breach' => 12, 'label' => 'Waiting QC'],
        self::STAGE_QC_TO_DISPATCH => ['warn' => 2, 'breach' => 8, 'label' => 'QC Release'],
        self::STAGE_DISPATCH_TO_COMPLETION => ['warn' => 4, 'breach' => 12, 'label' => 'Dispatch Hold'],
        self::STAGE_ORDER_RISK => ['warn' => 12, 'breach' => 24, 'label' => 'Order Risk'],
    ];

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function build(array $input, ?array $user = null): array
    {
        self::ensureTrackingTable();
        $runtime = function_exists('platform_user_runtime_facade') ? platform_user_runtime_facade() : null;

        if (!$runtime || !$runtime->isAppEnabled('manufacturing')) {
            return [
                'now' => date('Y-m-d H:i:s'),
                'role' => self::ROLE_MACHINE,
                'role_options' => self::roleOptions(),
                'owner_options' => self::ownerOptions(),
                'sla_policy' => self::SLA_HOURS,
                'kpi' => ['assigned' => 0, 'critical' => 0, 'warning' => 0, 'newly_ready' => 0, 'blocked' => 0, 'escalated' => 0],
                'assigned_items' => [],
                'critical_items' => [],
                'warning_items' => [],
                'newly_ready_items' => [],
                'blocked_items' => [],
                'escalated_items' => [],
                'host_regions' => HostSurfaceRegistryService::build('role_inbox', ['user' => $user, 'input' => $input, 'kpi' => []]),
            ];
        }

        $effectiveUser = is_array($user) ? $user : Auth::user();
        $context = $runtime->resolveUserContext($effectiveUser);
        $scope = (array)($context['scope'] ?? []);

        $selectedRole = self::resolveSelectedRole($input, $effectiveUser, $context);
        $activeRows = self::fetchActiveTrackingRows();
        $details = self::fetchEntityDetails($activeRows);

        $now = date('Y-m-d H:i:s');
        $items = [];
        foreach ($activeRows as $row) {
            $item = self::buildInboxItem($row, $details, $selectedRole, $now, $scope);
            if ($item === null) {
                continue;
            }
            $items[] = $item;
        }

        usort($items, static function (array $a, array $b): int {
            $rankCmp = ((int)$a['urgency_rank']) <=> ((int)$b['urgency_rank']);
            if ($rankCmp !== 0) {
                return $rankCmp;
            }
            $ageCmp = ((float)$b['age_hours']) <=> ((float)$a['age_hours']);
            if ($ageCmp !== 0) {
                return $ageCmp;
            }
            return ((int)$b['entity_id']) <=> ((int)$a['entity_id']);
        });

        $critical = array_values(array_filter($items, static fn(array $r): bool => (string)$r['sla_level'] === 'breach'));
        $warning = array_values(array_filter($items, static fn(array $r): bool => (string)$r['sla_level'] === 'warning'));
        $newlyReady = array_values(array_filter($items, static fn(array $r): bool => (bool)$r['is_newly_ready']));
        $blocked = array_values(array_filter($items, static fn(array $r): bool => (bool)$r['is_blocked']));
        $escalated = array_values(array_filter($items, static fn(array $r): bool => (bool)$r['is_escalated']));

        return [
            'now' => $now,
            'role' => $selectedRole,
            'role_options' => self::roleOptions(),
            'owner_options' => self::ownerOptions(),
            'sla_policy' => self::SLA_HOURS,
            'kpi' => [
                'assigned' => count($items),
                'critical' => count($critical),
                'warning' => count($warning),
                'newly_ready' => count($newlyReady),
                'blocked' => count($blocked),
                'escalated' => count($escalated),
            ],
            'assigned_items' => array_slice($items, 0, 120),
            'critical_items' => array_slice($critical, 0, 80),
            'warning_items' => array_slice($warning, 0, 80),
            'newly_ready_items' => array_slice($newlyReady, 0, 80),
            'blocked_items' => array_slice($blocked, 0, 80),
            'escalated_items' => array_slice($escalated, 0, 80),
            'host_regions' => HostSurfaceRegistryService::build('role_inbox', [
                'user' => $effectiveUser,
                'input' => $input,
                'role' => $selectedRole,
                'kpi' => [
                    'assigned' => count($items),
                    'critical' => count($critical),
                    'warning' => count($warning),
                    'newly_ready' => count($newlyReady),
                    'blocked' => count($blocked),
                    'escalated' => count($escalated),
                ],
            ]),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $user
     */
    private static function resolveSelectedRole(array $input, ?array $user, array $context): string
    {
        $dashboardType = (string)($context['dashboard_type'] ?? '');
        $forced = self::forcedInboxRoleForDashboardType($dashboardType);
        if ($forced !== null) {
            return $forced;
        }

        $requested = self::normalizeRole((string)($input['role'] ?? ''));
        if ($requested !== null) {
            return $requested;
        }

        $userRole = self::normalizeRole((string)($user['role'] ?? ''));
        if ($userRole !== null) {
            return $userRole;
        }

        return self::ROLE_MACHINE;
    }

    private static function forcedInboxRoleForDashboardType(string $dashboardType): ?string
    {
        return match (strtolower(trim($dashboardType))) {
            'production_leader', 'assembly_leader' => self::ROLE_MACHINE,
            'qc_leader' => self::ROLE_QC,
            'dispatch_leader' => self::ROLE_DISPATCH,
            default => null,
        };
    }

    private static function normalizeRole(string $role): ?string
    {
        $v = strtolower(trim($role));
        if (in_array($v, ['machine leader', 'machineleader', 'machine_leader', 'machine'], true)) {
            return self::ROLE_MACHINE;
        }
        if (in_array($v, ['qc leader', 'qcleader', 'qc_leader', 'qc'], true)) {
            return self::ROLE_QC;
        }
        if (in_array($v, ['dispatch leader', 'dispatchleader', 'dispatch_leader', 'dispatch'], true)) {
            return self::ROLE_DISPATCH;
        }
        return null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function fetchActiveTrackingRows(): array
    {
        $stages = [
            self::STAGE_PROD_TO_QC,
            self::STAGE_QC_TO_DISPATCH,
            self::STAGE_DISPATCH_TO_COMPLETION,
            self::STAGE_ORDER_RISK,
        ];

        $placeholders = implode(',', array_fill(0, count($stages), '?'));
        $rows = DB::fetchAll(
            "SELECT
                id,
                entity_type,
                entity_id,
                stage,
                owner_role,
                owner_user_id,
                owner_display,
                owner_assigned_at,
                entered_stage_at,
                ready_since,
                blocked_since,
                released_since,
                escalation_level,
                escalation_state,
                escalated_at,
                updated_at,
                created_at
             FROM handoff_tracking
             WHERE stage IN ({$placeholders})
               AND released_since IS NULL
             ORDER BY updated_at DESC, id DESC
             LIMIT " . self::MAX_ITEMS,
            $stages
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function fetchEntityDetails(array $rows): array
    {
        $idBuckets = [
            'production_entry' => [],
            'qc_entry' => [],
            'dispatch_entry' => [],
            'daily_order' => [],
        ];

        foreach ($rows as $row) {
            $entityType = (string)($row['entity_type'] ?? '');
            $entityId = (int)($row['entity_id'] ?? 0);
            if ($entityId <= 0 || !array_key_exists($entityType, $idBuckets)) {
                continue;
            }
            $idBuckets[$entityType][$entityId] = true;
        }

        return [
            'production_entry' => self::fetchProductionDetails(array_keys($idBuckets['production_entry'])),
            'qc_entry' => self::fetchQcDetails(array_keys($idBuckets['qc_entry'])),
            'dispatch_entry' => self::fetchDispatchDetails(array_keys($idBuckets['dispatch_entry'])),
            'daily_order' => self::fetchDailyOrderDetails(array_keys($idBuckets['daily_order'])),
        ];
    }

    /**
     * @param array<int,int|string> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function fetchProductionDetails(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT
                pe.id,
                pe.product_id,
                pe.production_date,
                pe.status,
                pe.good_qty,
                pe.notes,
                p.parts_name,
                p.parts_number
             FROM production_entries pe
             LEFT JOIN products p ON p.id = pe.product_id
             WHERE pe.id IN ({$placeholders})",
            array_values(array_map('intval', $ids))
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['id'] ?? 0)] = $row;
        }
        return $map;
    }

    /**
     * @param array<int,int|string> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function fetchQcDetails(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT
                q.id,
                q.product_id,
                q.status,
                q.checked_qty,
                q.pass_qty,
                q.fail_qty,
                q.remarks,
                q.created_at,
                q.updated_at,
                p.parts_name,
                p.parts_number
             FROM qc_entries q
             LEFT JOIN products p ON p.id = q.product_id
             WHERE q.id IN ({$placeholders})",
            array_values(array_map('intval', $ids))
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['id'] ?? 0)] = $row;
        }
        return $map;
    }

    /**
     * @param array<int,int|string> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDispatchDetails(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT
                d.id,
                d.product_id,
                d.qc_entry_id,
                d.daily_order_id,
                d.dispatch_date,
                d.dispatch_status,
                d.dispatchable_qty,
                d.destination,
                d.remarks,
                p.parts_name,
                p.parts_number
             FROM dispatch_entries d
             LEFT JOIN products p ON p.id = d.product_id
             WHERE d.id IN ({$placeholders})",
            array_values(array_map('intval', $ids))
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['id'] ?? 0)] = $row;
        }
        return $map;
    }

    /**
     * @param array<int,int|string> $ids
     * @return array<int,array<string,mixed>>
     */
    private static function fetchDailyOrderDetails(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::fetchAll(
            "SELECT
                o.id,
                o.product_id,
                o.customer_name,
                o.required_date,
                o.qty,
                o.status,
                o.coverage_pct,
                o.coverage_status,
                o.shortage_qty,
                o.notes,
                p.parts_name,
                p.parts_number
             FROM daily_orders o
             LEFT JOIN products p ON p.id = o.product_id
             WHERE o.id IN ({$placeholders})",
            array_values(array_map('intval', $ids))
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)($row['id'] ?? 0)] = $row;
        }
        return $map;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<string,array<int,array<string,mixed>>> $details
     * @return array<string,mixed>|null
     */
    private static function buildInboxItem(array $row, array $details, string $selectedRole, string $now, array $scope): ?array
    {
        $entityType = (string)($row['entity_type'] ?? '');
        $entityId = (int)($row['entity_id'] ?? 0);
        $stage = (string)($row['stage'] ?? '');
        if ($entityId <= 0 || $entityType === '' || $stage === '') {
            return null;
        }

        $detail = (array)($details[$entityType][$entityId] ?? []);
        if (!self::isWithinScope($detail, $scope)) {
            return null;
        }
        $effectiveOwner = self::effectiveOwnerRole($row, $stage);
        if ($effectiveOwner !== $selectedRole) {
            return null;
        }

        $stageSinceAt = self::stageSinceAt($row);
        $ageHours = self::hoursDiff($stageSinceAt, $now);
        $sla = self::evaluateSla($stage, $ageHours, self::hoursToDue($detail));

        $readySince = trim((string)($row['ready_since'] ?? ''));
        $blockedSince = trim((string)($row['blocked_since'] ?? ''));
        $isNewlyReady = $readySince !== ''
            && $blockedSince === ''
            && self::hoursDiff($readySince, $now) <= self::NEW_READY_WINDOW_HOURS;
        $isBlocked = $blockedSince !== '';
        $escalationLevel = trim((string)($row['escalation_level'] ?? ''));
        $escalationState = trim((string)($row['escalation_state'] ?? ''));
        $escalatedAt = trim((string)($row['escalated_at'] ?? ''));
        $isEscalated = $escalatedAt !== '' || $escalationLevel !== '' || $escalationState !== '';

        $actions = self::buildActions($entityType, $entityId, $stage, $selectedRole, $detail);

        $urgencyRank = 4;
        if ((string)$sla['level'] === 'breach') {
            $urgencyRank = 0;
        } elseif ((string)$sla['level'] === 'warning') {
            $urgencyRank = 1;
        } elseif ($isBlocked) {
            $urgencyRank = 2;
        } elseif ($isNewlyReady) {
            $urgencyRank = 3;
        }

        $ownerDisplay = trim((string)($row['owner_display'] ?? ''));
        $ownerRole = trim((string)($row['owner_role'] ?? ''));

        return [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'stage_key' => $stage,
            'stage_label' => (string)((self::SLA_HOURS[$stage]['label'] ?? 'Work Item')),
            'item_ref' => $entityType . '-' . $entityId,
            'part_name' => (string)($detail['parts_name'] ?? '-'),
            'part_number' => (string)($detail['parts_number'] ?? ''),
            'qty' => self::itemQty($entityType, $detail),
            'status' => self::itemStatus($entityType, $detail),
            'stage_since_at' => $stageSinceAt,
            'age_hours' => $ageHours,
            'sla_level' => (string)$sla['level'],
            'sla_label' => (string)$sla['label'],
            'owner_role' => $effectiveOwner,
            'owner_label' => $ownerDisplay !== '' ? ($effectiveOwner . ' / ' . $ownerDisplay) : $effectiveOwner,
            'owner_explicit' => $ownerRole !== '',
            'is_newly_ready' => $isNewlyReady,
            'is_blocked' => $isBlocked,
            'is_escalated' => $isEscalated,
            'escalation_state_text' => $escalationState !== '' ? $escalationState : ($isEscalated ? ('Escalated ' . $escalationLevel) : 'Not escalated'),
            'urgency_rank' => $urgencyRank,
            'open_url' => $actions['open_url'],
            'open_label' => $actions['open_label'],
            'complete_url' => $actions['complete_url'],
            'complete_label' => $actions['complete_label'],
            'complete_method' => $actions['complete_method'],
            'complete_post' => $actions['complete_post'],
            'board_url' => $actions['board_url'],
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function effectiveOwnerRole(array $row, string $stage): string
    {
        $ownerRole = trim((string)($row['owner_role'] ?? ''));
        if ($ownerRole !== '') {
            return $ownerRole;
        }

        if ($stage === self::STAGE_PROD_TO_QC) {
            return self::ROLE_QC;
        }
        if ($stage === self::STAGE_QC_TO_DISPATCH || $stage === self::STAGE_DISPATCH_TO_COMPLETION) {
            return self::ROLE_DISPATCH;
        }

        return 'Unassigned / System issue';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function stageSinceAt(array $row): string
    {
        $candidates = [
            trim((string)($row['blocked_since'] ?? '')),
            trim((string)($row['ready_since'] ?? '')),
            trim((string)($row['entered_stage_at'] ?? '')),
            trim((string)($row['updated_at'] ?? '')),
            trim((string)($row['created_at'] ?? '')),
        ];

        foreach ($candidates as $ts) {
            if ($ts !== '') {
                return $ts;
            }
        }

        return date('Y-m-d H:i:s');
    }

    /**
     * @param array<string,mixed> $detail
     */
    private static function hoursToDue(array $detail): ?int
    {
        $required = trim((string)($detail['required_date'] ?? ''));
        if ($required === '') {
            return null;
        }

        try {
            $due = new \DateTime($required . ' 23:59:59');
            $now = new \DateTime(date('Y-m-d H:i:s'));
            $seconds = $due->getTimestamp() - $now->getTimestamp();
            return (int)floor($seconds / 3600);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $detail
     */
    private static function itemQty(string $entityType, array $detail): float
    {
        if ($entityType === 'production_entry') {
            return (float)($detail['good_qty'] ?? 0);
        }
        if ($entityType === 'qc_entry') {
            return (float)($detail['pass_qty'] ?? 0);
        }
        if ($entityType === 'dispatch_entry') {
            return (float)($detail['dispatchable_qty'] ?? 0);
        }
        return (float)($detail['shortage_qty'] ?? $detail['qty'] ?? 0);
    }

    /**
     * @param array<string,mixed> $detail
     */
    private static function itemStatus(string $entityType, array $detail): string
    {
        if ($entityType === 'production_entry') {
            return trim((string)($detail['status'] ?? ''));
        }
        if ($entityType === 'qc_entry') {
            return trim((string)($detail['status'] ?? ''));
        }
        if ($entityType === 'dispatch_entry') {
            return trim((string)($detail['dispatch_status'] ?? ''));
        }
        return trim((string)($detail['coverage_status'] ?? $detail['status'] ?? ''));
    }

    private static function isWithinScope(array $detail, array $scope): bool
    {
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        if ($partIds) {
            $productId = (int)($detail['product_id'] ?? 0);
            if ($productId <= 0 || !in_array($productId, $partIds, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $detail
     * @return array<string,mixed>
     */
    private static function buildActions(string $entityType, int $entityId, string $stage, string $role, array $detail): array
    {
        $boardUrl = '/apps/manufacturing/handoffs';
        $openUrl = '/apps/manufacturing/handoffs';
        $openLabel = 'Open Context';

        if ($entityType === 'production_entry') {
            $openUrl = '/production-entries/edit?id=' . $entityId;
            $openLabel = 'Open Production Entry';
        } elseif ($entityType === 'qc_entry') {
            $openUrl = '/qc-entries/edit?id=' . $entityId;
            $openLabel = 'Open QC Entry';
        } elseif ($entityType === 'dispatch_entry') {
            $openUrl = '/dispatch-entries/edit?id=' . $entityId;
            $openLabel = 'Open Dispatch Entry';
        } elseif ($entityType === 'daily_order') {
            $openUrl = '/apps/manufacturing/daily-orders/360?id=' . $entityId;
            $openLabel = 'Open Order 360';
        }

        $completeMethod = 'get';
        $completeUrl = '/apps/manufacturing/handoffs';
        $completeLabel = 'Open Handoff Board';
        $completePost = [];

        if ($stage === self::STAGE_PROD_TO_QC) {
            $completeUrl = '/qc-entries/add?production_entry_id=' . $entityId
                . '&product_id=' . (int)($detail['product_id'] ?? 0)
                . '&status=Open';
            $completeLabel = 'Complete: Create QC Entry';
        } elseif ($stage === self::STAGE_QC_TO_DISPATCH) {
            $completeUrl = '/dispatch-entries/add?qc_entry_id=' . $entityId
                . '&product_id=' . (int)($detail['product_id'] ?? 0)
                . '&dispatch_status=Ready';
            $completeLabel = 'Complete: Create Dispatch Entry';
        } elseif ($stage === self::STAGE_DISPATCH_TO_COMPLETION) {
            $completeMethod = 'post';
            $completeUrl = '/dispatch-entries/quick-status';
            $completeLabel = 'Complete: Mark Dispatched';
            $completePost = [
                'id' => $entityId,
                'status' => 'Dispatched',
                'redirect' => '/me?role=' . urlencode($role),
            ];
        } elseif ($stage === self::STAGE_ORDER_RISK) {
            if ($role === self::ROLE_MACHINE) {
                $completeUrl = '/manufacturing/production-workboard';
                $completeLabel = 'Complete: Plan/Run Machine';
            } elseif ($role === self::ROLE_QC) {
                $completeUrl = '/manufacturing/qc-workboard';
                $completeLabel = 'Complete: Clear QC Backlog';
            } else {
                $completeUrl = '/manufacturing/dispatch-ops';
                $completeLabel = 'Complete: Clear Dispatch Block';
            }
        }

        return [
            'board_url' => $boardUrl,
            'open_url' => $openUrl,
            'open_label' => $openLabel,
            'complete_url' => $completeUrl,
            'complete_label' => $completeLabel,
            'complete_method' => $completeMethod,
            'complete_post' => $completePost,
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function ownerOptions(): array
    {
        return [
            self::ROLE_MACHINE => displayRole(self::ROLE_MACHINE),
            self::ROLE_QC => displayRole(self::ROLE_QC),
            self::ROLE_DISPATCH => displayRole(self::ROLE_DISPATCH),
            'Unassigned / System issue' => 'Unassigned / System issue',
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function roleOptions(): array
    {
        return [
            'machine' => self::ROLE_MACHINE,
            'qc' => self::ROLE_QC,
            'dispatch' => self::ROLE_DISPATCH,
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

        $seconds = $to->getTimestamp() - $from->getTimestamp();
        if ($seconds <= 0) {
            return 0.0;
        }

        return round($seconds / 3600, 2);
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
