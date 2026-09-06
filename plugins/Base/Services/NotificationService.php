<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\DB;

if (defined('APP_ROOT')) {
    $escalationServicePath = APP_ROOT . '/plugins/Base/Services/EscalationService.php';
    if (is_file($escalationServicePath)) {
        require_once $escalationServicePath;
    }
}

final class NotificationService
{
    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_DISMISSED = 'dismissed';

    public const SEVERITY_INFO = 'info';
    public const SEVERITY_ACTION_REQUIRED = 'action_required';
    public const SEVERITY_APPROVAL_REQUIRED = 'approval_required';
    public const SEVERITY_WARNING = 'warning';
    public const SEVERITY_CRITICAL = 'critical';

    public const EVENT_ASSIGNED = 'item_assigned';
    public const EVENT_NEWLY_READY = 'item_newly_ready';
    public const EVENT_SLA_WARNING = 'sla_at_risk';
    public const EVENT_SLA_OVERDUE = 'sla_overdue';
    public const EVENT_SLA_BREACH = 'sla_breach';
    public const EVENT_ESCALATED = 'item_escalated';
    public const EVENT_WORKFLOW_SUBMITTED = 'workflow_submitted';
    public const EVENT_WORKFLOW_APPROVED = 'workflow_approved';
    public const EVENT_WORKFLOW_REJECTED = 'workflow_rejected';
    public const EVENT_WORKFLOW_REOPENED = 'workflow_reopened';
    public const EVENT_WORKFLOW_BLOCKED = 'workflow_blocked';
    public const EVENT_WORKFLOW_UNBLOCKED = 'workflow_unblocked';
    public const EVENT_MISSING_HANDOFF_COMPLETION = 'missing_handoff_completion';
    public const EVENT_WORKFLOW_STATE_INCONSISTENT = 'workflow_state_inconsistent';
    public const EVENT_ADMIN_MESSAGE = 'admin_message';
    public const EVENT_ADMIN_GUIDE = 'admin_guide';

    private const STAGE_PROD_TO_QC = 'production_to_qc';
    private const STAGE_QC_TO_DISPATCH = 'qc_to_dispatch';
    private const STAGE_DISPATCH_TO_COMPLETION = 'dispatch_to_completion';
    private const STAGE_ORDER_RISK = 'order_risk';

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
     * @param array<string,mixed>|null $before
     * @param array<string,mixed>|null $after
     */
    public static function handleTrackingTransition(
        string $entityType,
        int $entityId,
        string $stage,
        ?array $before,
        ?array $after,
        string $fallbackOwnerRole
    ): void {
        self::ensureTable();

        if ($entityId <= 0 || $stage === '' || $entityType === '' || $after === null) {
            return;
        }

        $releasedSince = trim((string)($after['released_since'] ?? ''));
        if ($releasedSince !== '') {
            return;
        }

        $recipientRole = self::effectiveOwnerRole($after, $stage, $fallbackOwnerRole);
        if ($recipientRole === '') {
            return;
        }

        $recipientUserId = (int)($after['owner_user_id'] ?? 0);

        $beforeOwner = trim((string)($before['owner_role'] ?? ''));
        $afterOwner = trim((string)($after['owner_role'] ?? ''));
        $beforeUser = (int)($before['owner_user_id'] ?? 0);
        if (($before === null || $beforeOwner !== $afterOwner || $beforeUser !== $recipientUserId) && $afterOwner !== '') {
            self::emit(
                self::EVENT_ASSIGNED,
                self::SEVERITY_INFO,
                $recipientRole,
                $recipientUserId,
                $entityType,
                $entityId,
                $stage,
                'New item assigned',
                self::stageLabel($stage) . ' item assigned to ' . $recipientRole . '.',
                self::dedupe(self::EVENT_ASSIGNED, $entityType, $entityId, $stage, $recipientRole)
            );
        }

        $beforeReady = trim((string)($before['ready_since'] ?? ''));
        $afterReady = trim((string)($after['ready_since'] ?? ''));
        $afterBlocked = trim((string)($after['blocked_since'] ?? ''));
        if ($afterReady !== '' && $afterBlocked === '' && ($before === null || $beforeReady === '')) {
            self::emit(
                self::EVENT_NEWLY_READY,
                self::SEVERITY_INFO,
                $recipientRole,
                $recipientUserId,
                $entityType,
                $entityId,
                $stage,
                'Item newly ready',
                self::stageLabel($stage) . ' is newly ready for action.',
                self::dedupe(self::EVENT_NEWLY_READY, $entityType, $entityId, $stage, $recipientRole)
            );
        }

        $now = date('Y-m-d H:i:s');
        $beforeLevel = self::slaLevelFromRow($stage, $before, $now);
        $afterLevel = self::slaLevelFromRow($stage, $after, $now);

        $actionUrl = self::actionUrlForEntity($entityType, $entityId);

        if ($beforeLevel !== 'breached' && $afterLevel === 'breached') {
            self::emit(
                self::EVENT_SLA_BREACH,
                self::SEVERITY_CRITICAL,
                $recipientRole,
                $recipientUserId,
                $entityType,
                $entityId,
                $stage,
                'SLA breach reached',
                self::stageLabel($stage) . ' has breached SLA.',
                self::dedupe(self::EVENT_SLA_BREACH, $entityType, $entityId, $stage, $recipientRole),
                $actionUrl
            );
        } elseif ($beforeLevel !== 'overdue' && $afterLevel === 'overdue') {
            self::emit(
                self::EVENT_SLA_OVERDUE,
                self::SEVERITY_ACTION_REQUIRED,
                $recipientRole,
                $recipientUserId,
                $entityType,
                $entityId,
                $stage,
                'SLA overdue',
                self::stageLabel($stage) . ' is overdue and needs immediate action.',
                self::dedupe(self::EVENT_SLA_OVERDUE, $entityType, $entityId, $stage, $recipientRole),
                $actionUrl
            );
        } elseif ($beforeLevel === 'ok' && $afterLevel === 'at_risk') {
            self::emit(
                self::EVENT_SLA_WARNING,
                self::SEVERITY_WARNING,
                $recipientRole,
                $recipientUserId,
                $entityType,
                $entityId,
                $stage,
                'SLA warning reached',
                self::stageLabel($stage) . ' is approaching SLA breach.',
                self::dedupe(self::EVENT_SLA_WARNING, $entityType, $entityId, $stage, $recipientRole),
                $actionUrl
            );
        }
    }

    public static function notifyEscalated(
        string $entityType,
        int $entityId,
        string $stage,
        string $recipientRole,
        int $recipientUserId = 0,
        string $level = 'L1',
        string $state = 'Escalated'
    ): void {
        self::ensureTable();

        if ($entityId <= 0 || $recipientRole === '') {
            return;
        }

        $title = 'Item escalated (' . strtoupper($level) . ')';
        $message = self::stageLabel($stage) . ' was escalated: ' . $state . '.';
        $token = strtoupper($level) . '|' . strtolower(trim($state));
        self::emit(
            self::EVENT_ESCALATED,
            self::SEVERITY_CRITICAL,
            $recipientRole,
            $recipientUserId,
            $entityType,
            $entityId,
            $stage,
            $title,
            $message,
            self::dedupe(self::EVENT_ESCALATED, $entityType, $entityId, $stage, $recipientRole, $token)
        );
    }

    public static function notifyAssigned(
        string $entityType,
        int $entityId,
        string $stage,
        string $recipientRole,
        int $recipientUserId = 0
    ): void {
        self::ensureTable();

        if ($entityId <= 0 || $recipientRole === '') {
            return;
        }

        self::emit(
            self::EVENT_ASSIGNED,
            self::SEVERITY_INFO,
            $recipientRole,
            $recipientUserId,
            $entityType,
            $entityId,
            $stage,
            'New item assigned',
            self::stageLabel($stage) . ' item assigned to ' . $recipientRole . '.',
            self::dedupe(self::EVENT_ASSIGNED, $entityType, $entityId, $stage, $recipientRole)
        );
    }

    /**
     * @param array<string,mixed>|null $user
     * @param array<string,mixed> $record
     */
    public static function handleWorkflowTransition(
        string $entityType,
        int $entityId,
        string $action,
        string $fromState,
        string $toState,
        ?array $user,
        array $record
    ): void {
        self::ensureTable();

        if ($entityId <= 0 || $entityType === '') {
            return;
        }

        $action = strtolower(trim($action));
        $entityLabel = ucwords(str_replace('_', ' ', $entityType));
        $actionUrl = self::actionUrlForEntity($entityType, $entityId);

        $recipientRole = self::workflowOwnerRoleForEntity($entityType);
        $addedBy = (int)($record['added_by'] ?? 0);

        if ($action === 'submit') {
            self::emit(
                self::EVENT_WORKFLOW_SUBMITTED,
                self::SEVERITY_APPROVAL_REQUIRED,
                'App Admin',
                0,
                $entityType,
                $entityId,
                $toState,
                $entityLabel . ' submitted for approval',
                $entityLabel . ' #' . $entityId . ' was submitted and is awaiting approval.',
                self::dedupe(self::EVENT_WORKFLOW_SUBMITTED, $entityType, $entityId, $toState, 'App Admin'),
                $actionUrl
            );
            return;
        }

        if ($action === 'approve') {
            self::emit(
                self::EVENT_WORKFLOW_APPROVED,
                self::SEVERITY_INFO,
                $recipientRole,
                $addedBy,
                $entityType,
                $entityId,
                $toState,
                $entityLabel . ' approved',
                $entityLabel . ' #' . $entityId . ' was approved.',
                self::dedupe(self::EVENT_WORKFLOW_APPROVED, $entityType, $entityId, $toState, $recipientRole),
                $actionUrl
            );
            return;
        }

        if ($action === 'reject') {
            self::emit(
                self::EVENT_WORKFLOW_REJECTED,
                self::SEVERITY_ACTION_REQUIRED,
                $recipientRole,
                $addedBy,
                $entityType,
                $entityId,
                $toState,
                $entityLabel . ' rejected',
                $entityLabel . ' #' . $entityId . ' was rejected and needs rework.',
                self::dedupe(self::EVENT_WORKFLOW_REJECTED, $entityType, $entityId, $toState, $recipientRole),
                $actionUrl
            );
            return;
        }

        if ($action === 'reopen') {
            self::emit(
                self::EVENT_WORKFLOW_REOPENED,
                self::SEVERITY_ACTION_REQUIRED,
                $recipientRole,
                $addedBy,
                $entityType,
                $entityId,
                $toState,
                $entityLabel . ' reopened',
                $entityLabel . ' #' . $entityId . ' was reopened and requires attention.',
                self::dedupe(self::EVENT_WORKFLOW_REOPENED, $entityType, $entityId, $toState, $recipientRole),
                $actionUrl
            );
            return;
        }

        if ($action === 'hold') {
            self::emit(
                self::EVENT_WORKFLOW_BLOCKED,
                self::SEVERITY_WARNING,
                $recipientRole,
                $addedBy,
                $entityType,
                $entityId,
                $toState,
                $entityLabel . ' blocked',
                $entityLabel . ' #' . $entityId . ' is now blocked/on hold.',
                self::dedupe(self::EVENT_WORKFLOW_BLOCKED, $entityType, $entityId, $toState, $recipientRole),
                $actionUrl
            );
            return;
        }

        if ($action === 'resume') {
            self::emit(
                self::EVENT_WORKFLOW_UNBLOCKED,
                self::SEVERITY_INFO,
                $recipientRole,
                $addedBy,
                $entityType,
                $entityId,
                $toState,
                $entityLabel . ' unblocked',
                $entityLabel . ' #' . $entityId . ' is active again.',
                self::dedupe(self::EVENT_WORKFLOW_UNBLOCKED, $entityType, $entityId, $toState, $recipientRole),
                $actionUrl
            );
        }
    }

    /**
     * @param array<string,mixed> $payload
     */
    public static function create(array $payload): void
    {
        self::ensureTable();

        $entityType = trim((string)($payload['entity_type'] ?? ''));
        $entityId = (int)($payload['entity_id'] ?? 0);
        $eventType = trim((string)($payload['event_type'] ?? ''));
        $severity = trim((string)($payload['severity'] ?? self::SEVERITY_INFO));
        $title = trim((string)($payload['title'] ?? 'Notification'));
        $message = trim((string)($payload['message'] ?? ''));
        $recipientRole = self::canonicalRole((string)($payload['recipient_role'] ?? ''));
        $recipientUserId = (int)($payload['recipient_user_id'] ?? 0);
        $stage = trim((string)($payload['stage'] ?? ''));
        $actionUrl = trim((string)($payload['action_url'] ?? ''));

        if ($entityType === '' || $entityId <= 0 || $eventType === '') {
            return;
        }

        if (!in_array($severity, [
            self::SEVERITY_INFO,
            self::SEVERITY_ACTION_REQUIRED,
            self::SEVERITY_APPROVAL_REQUIRED,
            self::SEVERITY_WARNING,
            self::SEVERITY_CRITICAL,
        ], true)) {
            $severity = self::SEVERITY_INFO;
        }

        $dedupeKey = trim((string)($payload['dedupe_key'] ?? ''));
        if ($dedupeKey === '') {
            $dedupeKey = self::dedupe($eventType, $entityType, $entityId, $stage, $recipientRole, (string)($payload['extra_token'] ?? ''));
        }

        self::emit(
            $eventType,
            $severity,
            $recipientRole,
            $recipientUserId,
            $entityType,
            $entityId,
            $stage,
            $title,
            $message,
            $dedupeKey,
            $actionUrl
        );
    }

    /**
     * @param array<string,mixed>|null $user
     */
    public static function unreadCountForUser(?array $user): int
    {
        self::ensureTable();
        self::runEscalationSweep();

        $role = self::canonicalRole((string)($user['role'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        if ($role === '' && $userId <= 0) {
            return 0;
        }

        if ($role !== '' && $userId > 0) {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM workflow_notifications
                 WHERE status = ?
                   AND (recipient_role = ? OR recipient_user_id = ? OR user_id = ?)",
                [self::STATUS_NEW, $role, $userId, $userId]
            );
            return (int)($row['c'] ?? 0);
        }

        if ($role !== '') {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c FROM workflow_notifications WHERE status = ? AND recipient_role = ?',
                [self::STATUS_NEW, $role]
            );
            return (int)($row['c'] ?? 0);
        }

        $row = DB::fetchOne(
            'SELECT COUNT(*) AS c FROM workflow_notifications WHERE status = ? AND (recipient_user_id = ? OR user_id = ?)',
            [self::STATUS_NEW, $userId, $userId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<int,array<string,mixed>>
     */
    public static function listForUser(?array $user, string $status = ''): array
    {
        self::ensureTable();
        self::runEscalationSweep();

        $role = self::canonicalRole((string)($user['role'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        if ($role === '' && $userId <= 0) {
            return [];
        }

        $where = [];
        $params = [];

        if ($role !== '' && $userId > 0) {
            $where[] = '(recipient_role = ? OR recipient_user_id = ? OR user_id = ?)';
            $params[] = $role;
            $params[] = $userId;
            $params[] = $userId;
        } elseif ($role !== '') {
            $where[] = 'recipient_role = ?';
            $params[] = $role;
        } else {
            $where[] = '(recipient_user_id = ? OR user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($status !== '' && in_array($status, [self::STATUS_NEW, self::STATUS_READ, self::STATUS_DISMISSED], true)) {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $sql =
            'SELECT id, recipient_role, recipient_user_id, user_id, severity, status, event_type, title, message, entity_type, entity_id, stage, action_url, read_at, dismissed_at, created_at, updated_at
             FROM workflow_notifications
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at DESC, id DESC
             LIMIT 240';

        $rows = DB::fetchAll($sql, $params);
        foreach ($rows as &$row) {
            if (isset($row['recipient_role'])) {
                $row['recipient_role'] = displayRole((string)$row['recipient_role']);
            }
        }
        unset($row);
        return $rows;
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<int,array<string,mixed>>
     */
    public static function recentForUser(?array $user, int $limit = 6, bool $unreadOnly = false): array
    {
        self::ensureTable();
        self::runEscalationSweep();

        $role = self::canonicalRole((string)($user['role'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        if ($role === '' && $userId <= 0) {
            return [];
        }

        $where = [];
        $params = [];

        if ($role !== '' && $userId > 0) {
            $where[] = '(recipient_role = ? OR recipient_user_id = ? OR user_id = ?)';
            $params[] = $role;
            $params[] = $userId;
            $params[] = $userId;
        } elseif ($role !== '') {
            $where[] = 'recipient_role = ?';
            $params[] = $role;
        } else {
            $where[] = '(recipient_user_id = ? OR user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($unreadOnly) {
            $where[] = 'status = ?';
            $params[] = self::STATUS_NEW;
        }

        $limit = max(1, min(25, $limit));
        $rows = DB::fetchAll(
            'SELECT id, severity, status, event_type, title, message, entity_type, entity_id, stage, action_url, created_at
             FROM workflow_notifications
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
            $params
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function signalGroup(array $row): string
    {
        $event = strtolower(trim((string)($row['event_type'] ?? '')));
        $severity = strtolower(trim((string)($row['severity'] ?? self::SEVERITY_INFO)));

        if ($severity === self::SEVERITY_APPROVAL_REQUIRED || $event === self::EVENT_WORKFLOW_SUBMITTED) {
            return 'approval_signals';
        }

        if (in_array($event, [
            self::EVENT_SLA_WARNING,
            self::EVENT_SLA_OVERDUE,
            self::EVENT_SLA_BREACH,
            self::EVENT_ESCALATED,
            self::EVENT_MISSING_HANDOFF_COMPLETION,
            self::EVENT_WORKFLOW_STATE_INCONSISTENT,
            self::EVENT_WORKFLOW_BLOCKED,
        ], true)) {
            return 'escalations';
        }

        if ($severity === self::SEVERITY_ACTION_REQUIRED || in_array($event, [
            self::EVENT_ASSIGNED,
            self::EVENT_NEWLY_READY,
            self::EVENT_WORKFLOW_REJECTED,
            self::EVENT_WORKFLOW_REOPENED,
        ], true)) {
            return 'action_required';
        }

        return 'system';
    }

    public static function eventLabel(string $eventType): string
    {
        return match (strtolower(trim($eventType))) {
            self::EVENT_ASSIGNED => 'Assigned to You',
            self::EVENT_NEWLY_READY => 'Now Ready',
            self::EVENT_SLA_WARNING => 'SLA At Risk',
            self::EVENT_SLA_OVERDUE => 'SLA Overdue',
            self::EVENT_SLA_BREACH => 'SLA Breached',
            self::EVENT_ESCALATED => 'Escalated',
            self::EVENT_WORKFLOW_SUBMITTED => 'Approval Requested',
            self::EVENT_WORKFLOW_APPROVED => 'Approved',
            self::EVENT_WORKFLOW_REJECTED => 'Rejected',
            self::EVENT_WORKFLOW_REOPENED => 'Reopened',
            self::EVENT_WORKFLOW_BLOCKED => 'Blocked',
            self::EVENT_WORKFLOW_UNBLOCKED => 'Unblocked',
            self::EVENT_MISSING_HANDOFF_COMPLETION => 'Handoff Incomplete',
            self::EVENT_WORKFLOW_STATE_INCONSISTENT => 'Workflow Inconsistent',
            self::EVENT_ADMIN_MESSAGE => 'Admin Message',
            self::EVENT_ADMIN_GUIDE => 'Operational Guide',
            default => 'Update',
        };
    }

    public static function severityLabel(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            self::SEVERITY_ACTION_REQUIRED => 'Action Required',
            self::SEVERITY_APPROVAL_REQUIRED => 'Approval Required',
            self::SEVERITY_WARNING => 'Warning',
            self::SEVERITY_CRITICAL => 'Urgent',
            default => 'Info',
        };
    }

    /**
     * @param array<string,mixed> $row
     * @return array{primary_url:string,primary_label:string,secondary_url:string,secondary_label:string}
     */
    public static function recommendedActions(array $row): array
    {
        $group = self::signalGroup($row);
        $event = strtolower(trim((string)($row['event_type'] ?? '')));
        $detailUrl = trim((string)($row['action_url'] ?? ''));

        if ($group === 'approval_signals') {
            return [
                'primary_url' => '/ops/approval-inbox',
                'primary_label' => 'Open Approval Inbox',
                'secondary_url' => $detailUrl,
                'secondary_label' => 'Open Record',
            ];
        }

        if ($group === 'action_required') {
            return [
                'primary_url' => '/',
                'primary_label' => 'Open Home',
                'secondary_url' => $detailUrl,
                'secondary_label' => 'Open Record',
            ];
        }

        if ($group === 'escalations') {
            $primaryUrl = in_array($event, [self::EVENT_ESCALATED, self::EVENT_SLA_WARNING, self::EVENT_SLA_OVERDUE, self::EVENT_SLA_BREACH], true)
                ? '/apps/manufacturing/handoffs'
                : '/';
            $primaryLabel = $primaryUrl === '/apps/manufacturing/handoffs' ? 'Open Handoff Board' : 'Open Home';

            return [
                'primary_url' => $primaryUrl,
                'primary_label' => $primaryLabel,
                'secondary_url' => $detailUrl,
                'secondary_label' => 'Open Record',
            ];
        }

        return [
            'primary_url' => $detailUrl !== '' ? $detailUrl : '/ops/notifications',
            'primary_label' => $detailUrl !== '' ? 'Open Detail' : 'Open Notifications',
            'secondary_url' => '/ops/notifications',
            'secondary_label' => 'Open Activity',
        ];
    }

    /**
     * @param array<string,mixed>|null $user
     * @return array<string,int>
     */
    public static function summaryForUser(?array $user): array
    {
        self::ensureTable();
        self::runEscalationSweep();

        $role = self::canonicalRole((string)($user['role'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        if ($role === '' && $userId <= 0) {
            return ['total' => 0, 'unread' => 0, 'critical' => 0, 'warning' => 0, 'action_required' => 0, 'approval_required' => 0];
        }

        $where = [];
        $params = [];

        if ($role !== '' && $userId > 0) {
            $where[] = '(recipient_role = ? OR recipient_user_id = ? OR user_id = ?)';
            $params[] = $role;
            $params[] = $userId;
            $params[] = $userId;
        } elseif ($role !== '') {
            $where[] = 'recipient_role = ?';
            $params[] = $role;
        } else {
            $where[] = '(recipient_user_id = ? OR user_id = ?)';
            $params[] = $userId;
            $params[] = $userId;
        }

        $row = DB::fetchOne(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = "new" THEN 1 ELSE 0 END) AS unread,
                    SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) AS critical,
                    SUM(CASE WHEN severity = "warning" THEN 1 ELSE 0 END) AS warning,
                    SUM(CASE WHEN severity = "action_required" THEN 1 ELSE 0 END) AS action_required,
                    SUM(CASE WHEN severity = "approval_required" THEN 1 ELSE 0 END) AS approval_required
             FROM workflow_notifications
             WHERE ' . implode(' AND ', $where),
            $params
        ) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'unread' => (int)($row['unread'] ?? 0),
            'critical' => (int)($row['critical'] ?? 0),
            'warning' => (int)($row['warning'] ?? 0),
            'action_required' => (int)($row['action_required'] ?? 0),
            'approval_required' => (int)($row['approval_required'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed>|null $user
     */
    public static function markRead(int $notificationId, ?array $user): void
    {
        self::updateStatus($notificationId, $user, self::STATUS_READ);
    }

    /**
     * @param array<string,mixed>|null $user
     */
    public static function dismiss(int $notificationId, ?array $user): void
    {
        self::updateStatus($notificationId, $user, self::STATUS_DISMISSED);
    }

    /**
     * @param array<string,mixed>|null $user
     */
    public static function markAllRead(?array $user): void
    {
        self::ensureTable();

        $role = self::canonicalRole((string)($user['role'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        if ($role === '' && $userId <= 0) {
            return;
        }

        if ($role !== '' && $userId > 0) {
            DB::query(
                "UPDATE workflow_notifications
                 SET status = ?, read_at = NOW(), updated_at = NOW()
                 WHERE status = ?
                   AND (recipient_role = ? OR recipient_user_id = ? OR user_id = ?)",
                [self::STATUS_READ, self::STATUS_NEW, $role, $userId, $userId]
            );
            return;
        }

        if ($role !== '') {
            DB::query(
                "UPDATE workflow_notifications
                 SET status = ?, read_at = NOW(), updated_at = NOW()
                 WHERE status = ? AND recipient_role = ?",
                [self::STATUS_READ, self::STATUS_NEW, $role]
            );
            return;
        }

        DB::query(
            "UPDATE workflow_notifications
             SET status = ?, read_at = NOW(), updated_at = NOW()
             WHERE status = ? AND (recipient_user_id = ? OR user_id = ?)",
            [self::STATUS_READ, self::STATUS_NEW, $userId, $userId]
        );
    }

    public static function sendAdminCommunication(
        string $kind,
        string $title,
        string $message,
        string $severity,
        int $targetUserId = 0,
        string $targetRole = ''
    ): int {
        self::ensureTable();

        $title = trim($title);
        $message = trim($message);
        if ($title === '' || $message === '') {
            return 0;
        }

        $severity = in_array($severity, [self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_CRITICAL], true)
            ? $severity
            : self::SEVERITY_INFO;

        $eventType = strtolower(trim($kind)) === 'guide' ? self::EVENT_ADMIN_GUIDE : self::EVENT_ADMIN_MESSAGE;
        $recipientRole = self::canonicalRole($targetRole);
        $createdAt = date('Y-m-d H:i:s');

        if ($targetUserId > 0) {
            self::insertOneOffNotification('', $targetUserId, $severity, $eventType, $title, $message, $createdAt);
            return 1;
        }

        if ($recipientRole !== '') {
            self::insertOneOffNotification($recipientRole, 0, $severity, $eventType, $title, $message, $createdAt);
            return 1;
        }

        $users = DB::fetchAll('SELECT id FROM users ORDER BY id ASC');
        $sent = 0;
        foreach ($users as $row) {
            $uid = (int)($row['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            self::insertOneOffNotification('', $uid, $severity, $eventType, $title, $message, $createdAt);
            $sent++;
        }

        return $sent;
    }

    private static function insertOneOffNotification(
        string $recipientRole,
        int $recipientUserId,
        string $severity,
        string $eventType,
        string $title,
        string $message,
        string $createdAt
    ): void {
        $dedupeKey = hash('sha256', implode('|', [
            'admin_communication',
            $eventType,
            strtolower(trim($recipientRole)),
            (string)$recipientUserId,
            $title,
            $message,
            microtime(true),
            random_int(1000, 99999999),
        ]));

        DB::query(
            "INSERT INTO workflow_notifications
                (recipient_role, recipient_user_id, user_id, severity, status, event_type, title, message, entity_type, entity_id, stage, action_url, delivery_status, sent_at, dedupe_key, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $recipientRole !== '' ? $recipientRole : null,
                $recipientUserId > 0 ? $recipientUserId : null,
                $recipientUserId > 0 ? $recipientUserId : null,
                $severity,
                self::STATUS_NEW,
                $eventType,
                substr($title, 0, 190),
                substr($message, 0, 6000),
                'admin_communication',
                0,
                'admin_center',
                '/ops/notifications',
                'sent',
                $createdAt,
                $dedupeKey,
                $createdAt,
                $createdAt,
            ]
        );
    }

    private static function updateStatus(int $notificationId, ?array $user, string $status): void
    {
        self::ensureTable();

        if ($notificationId <= 0) {
            return;
        }

        $role = self::canonicalRole((string)($user['role'] ?? ''));
        $userId = (int)($user['id'] ?? 0);
        if ($role === '' && $userId <= 0) {
            return;
        }

        $statusFieldSql = $status === self::STATUS_DISMISSED ? 'dismissed_at = NOW()' : 'read_at = NOW()';

        if ($role !== '' && $userId > 0) {
            DB::query(
                "UPDATE workflow_notifications
                 SET status = ?, {$statusFieldSql}, updated_at = NOW()
                 WHERE id = ?
                   AND (recipient_role = ? OR recipient_user_id = ? OR user_id = ?)",
                [$status, $notificationId, $role, $userId, $userId]
            );
            return;
        }

        if ($role !== '') {
            DB::query(
                "UPDATE workflow_notifications
                 SET status = ?, {$statusFieldSql}, updated_at = NOW()
                 WHERE id = ? AND recipient_role = ?",
                [$status, $notificationId, $role]
            );
            return;
        }

        DB::query(
            "UPDATE workflow_notifications
             SET status = ?, {$statusFieldSql}, updated_at = NOW()
             WHERE id = ? AND (recipient_user_id = ? OR user_id = ?)",
            [$status, $notificationId, $userId, $userId]
        );
    }

    private static function emit(
        string $eventType,
        string $severity,
        string $recipientRole,
        int $recipientUserId,
        string $entityType,
        int $entityId,
        string $stage,
        string $title,
        string $message,
        string $dedupeKey,
        string $actionUrl = ''
    ): void {
        if ($recipientRole === '' && $recipientUserId <= 0) {
            return;
        }

        DB::query(
            "INSERT INTO workflow_notifications
                (recipient_role, recipient_user_id, user_id, severity, status, event_type, title, message, entity_type, entity_id, stage, action_url, delivery_status, sent_at, dedupe_key, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE updated_at = updated_at",
            [
                $recipientRole !== '' ? $recipientRole : null,
                $recipientUserId > 0 ? $recipientUserId : null,
                $recipientUserId > 0 ? $recipientUserId : null,
                $severity,
                self::STATUS_NEW,
                $eventType,
                $title,
                $message,
                $entityType,
                $entityId,
                $stage,
                $actionUrl !== '' ? $actionUrl : self::actionUrlForEntity($entityType, $entityId),
                'sent',
                date('Y-m-d H:i:s'),
                $dedupeKey,
                date('Y-m-d H:i:s'),
                date('Y-m-d H:i:s'),
            ]
        );

        // Email notification for critical/warning events directed at a specific user (Phase 5).
        if ($recipientUserId > 0
            && in_array($severity, [self::SEVERITY_CRITICAL, self::SEVERITY_WARNING], true)
        ) {
            self::maybySendAlertEmail($recipientUserId, $severity, $title, $message,
                $actionUrl !== '' ? $actionUrl : self::actionUrlForEntity($entityType, $entityId));
        }
    }

    /**
     * Attempt to send an email alert to a specific user. Non-fatal — logs on failure.
     */
    private static function maybySendAlertEmail(
        int $userId,
        string $severity,
        string $title,
        string $message,
        string $actionUrl
    ): void {
        try {
            $row = DB::fetchOne('SELECT email FROM users WHERE id = ? AND account_status = ? LIMIT 1',
                [$userId, 'active']);
            $email = trim((string)($row['email'] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $severityLabel = $severity === self::SEVERITY_CRITICAL ? 'URGENT' : 'Warning';
            $appName = function_exists('app_display_name') ? app_display_name() : 'IPM';
            $subject = '[' . $severityLabel . '] ' . $title . ' — ' . $appName;

            $safeTitle   = htmlspecialchars($title);
            $safeMsg     = htmlspecialchars($message);
            $safeAction  = htmlspecialchars($actionUrl);
            $safeApp     = htmlspecialchars($appName);
            $safeSev     = htmlspecialchars(ucfirst($severity));

            $severityColor = $severity === self::SEVERITY_CRITICAL ? 'var(--color-danger-text)' : 'var(--color-warning-text)';
            $html = '<html><body class="u-style-1c7e4eb7ef">'
                . '<h2 style="color:' . $severityColor . '">'
                . $safeSev . ': ' . $safeTitle . '</h2>'
                . '<p class="u-style-da25a2e553">' . nl2br($safeMsg) . '</p>'
                . ($actionUrl !== '' ? '<p><a class="u-style-494bf9cd61" href="' . $safeAction . '">View in ' . $safeApp . '</a></p>' : '')
                . '<hr class="u-style-5eafa68ae8">'
                . '<p class="u-style-026fe6426e">This is an automated alert from ' . $safeApp . '. Do not reply.</p>'
                . '</body></html>';

            $text = $severityLabel . ': ' . $title . "\n\n" . $message
                . ($actionUrl !== '' ? "\n\nView: " . $actionUrl : '')
                . "\n\n-- Automated alert from " . $appName;

            (new \App\Services\MailService())->sendRaw($email, $subject, $html, $text);
        } catch (\Throwable $e) {
            error_log('NotificationService::maybySendAlertEmail: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string,mixed>|null $row
     */
    private static function slaLevelFromRow(string $stage, ?array $row, string $now): string
    {
        if ($row === null) {
            return 'ok';
        }

        $released = trim((string)($row['released_since'] ?? ''));
        if ($released !== '') {
            return 'ok';
        }

        $policy = self::SLA_HOURS[$stage] ?? ['warn' => 4, 'breach' => 12, 'label' => 'Stage'];
        $from = self::stageSinceAt($row);
        $age = self::hoursDiff($from, $now);
        $escalatedAt = trim((string)($row['escalated_at'] ?? ''));

        if ($escalatedAt !== '' || $age >= ((float)$policy['breach'] * 2.0)) {
            return 'breached';
        }
        if ($age >= (float)$policy['breach']) {
            return 'overdue';
        }
        if ($age >= (float)$policy['warn']) {
            return 'at_risk';
        }
        return 'ok';
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
     * @param array<string,mixed>|null $row
     */
    private static function effectiveOwnerRole(?array $row, string $stage, string $fallback): string
    {
        $owner = trim((string)($row['owner_role'] ?? ''));
        if ($owner !== '') {
            return self::canonicalRole($owner);
        }

        $fallbackRole = self::canonicalRole($fallback);
        if ($fallbackRole !== '') {
            return $fallbackRole;
        }

        if ($stage === self::STAGE_PROD_TO_QC) {
            return 'QC';
        }
        if ($stage === self::STAGE_QC_TO_DISPATCH || $stage === self::STAGE_DISPATCH_TO_COMPLETION) {
            return 'Dispatch';
        }
        return 'Production';
    }

    private static function canonicalRole(string $role): string
    {
        $v = strtolower(trim($role));
        if (in_array($v, ['machine leader', 'machineleader', 'machine_leader', 'machine'], true)) {
            return 'Production';
        }
        if (in_array($v, ['qc leader', 'qcleader', 'qc_leader', 'qc'], true)) {
            return 'QC';
        }
        if (in_array($v, ['dispatch leader', 'dispatchleader', 'dispatch_leader', 'dispatch'], true)) {
            return 'Dispatch';
        }
        if ($v === 'admin') {
            return 'Admin';
        }
        if ($v === 'manager') {
            return 'Manager';
        }
        if ($v === 'supervisor') {
            return 'Supervisor';
        }
        return trim($role);
    }

    private static function workflowOwnerRoleForEntity(string $entityType): string
    {
        return match ($entityType) {
            'production_plan', 'assembly_plan', 'production_entry', 'daily_order' => 'Production',
            'qc_entry' => 'QC',
            'dispatch_entry' => 'Dispatch',
            default => 'App Admin',
        };
    }

    private static function actionUrlForEntity(string $entityType, int $entityId): string
    {
        if ($entityId <= 0) {
            return '/ops/notifications';
        }

        return match ($entityType) {
            'daily_order' => '/apps/manufacturing/daily-orders/360?id=' . $entityId,
            'production_plan' => '/apps/manufacturing/production-plans/edit?id=' . $entityId,
            'production_entry' => '/production-entries/edit?id=' . $entityId,
            'assembly_plan' => '/manufacturing/assembly-plans/detail?id=' . $entityId,
            'qc_entry' => '/qc-entries/edit?id=' . $entityId,
            'dispatch_entry' => '/dispatch-entries/edit?id=' . $entityId,
            default => '/ops/notifications',
        };
    }

    private static function runEscalationSweep(): void
    {
        static $ran = false;
        if ($ran) {
            return;
        }
        $ran = true;

        if (!class_exists(EscalationService::class)) {
            return;
        }

        try {
            EscalationService::runSweep();
        } catch (\Throwable $e) {
            // Keep notifications page resilient even if sweep fails.
        }
    }

    private static function stageLabel(string $stage): string
    {
        return (string)(self::SLA_HOURS[$stage]['label'] ?? $stage);
    }

    private static function dedupe(
        string $eventType,
        string $entityType,
        int $entityId,
        string $stage,
        string $recipientRole,
        string $extraToken = ''
    ): string {
        $token = implode('|', [
            $eventType,
            $entityType,
            (string)$entityId,
            $stage,
            strtolower(trim($recipientRole)),
            strtolower(trim($extraToken)),
        ]);
        return hash('sha256', $token);
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

    private static function ensureTable(): void
    {
        DB::query(
            "CREATE TABLE IF NOT EXISTS workflow_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                recipient_role VARCHAR(80) NULL,
                recipient_user_id INT NULL,
                user_id INT NULL,
                severity VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'new',
                event_type VARCHAR(50) NOT NULL,
                title VARCHAR(190) NOT NULL,
                message TEXT NULL,
                entity_type VARCHAR(40) NOT NULL,
                entity_id INT NOT NULL,
                stage VARCHAR(50) NOT NULL,
                action_url VARCHAR(255) NULL,
                delivery_status VARCHAR(20) NOT NULL DEFAULT 'sent',
                sent_at DATETIME NULL,
                dedupe_key VARCHAR(64) NOT NULL,
                read_at DATETIME NULL,
                dismissed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_workflow_notification_dedupe (dedupe_key),
                KEY idx_workflow_notification_target (recipient_role, recipient_user_id, status),
                KEY idx_workflow_notification_entity (entity_type, entity_id, stage),
                KEY idx_workflow_notification_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        self::addColumnIfMissing('user_id', 'ALTER TABLE workflow_notifications ADD COLUMN user_id INT NULL AFTER recipient_user_id');
        self::addColumnIfMissing('action_url', 'ALTER TABLE workflow_notifications ADD COLUMN action_url VARCHAR(255) NULL AFTER stage');
        self::addColumnIfMissing('delivery_status', 'ALTER TABLE workflow_notifications ADD COLUMN delivery_status VARCHAR(20) NOT NULL DEFAULT "sent" AFTER action_url');
        self::addColumnIfMissing('sent_at', 'ALTER TABLE workflow_notifications ADD COLUMN sent_at DATETIME NULL AFTER delivery_status');
        self::addIndexIfMissing('idx_workflow_notification_user', 'CREATE INDEX idx_workflow_notification_user ON workflow_notifications (user_id, status, created_at)');
    }

    private static function addColumnIfMissing(string $column, string $ddl): void
    {
        $exists = DB::fetchOne(
            'SELECT 1 AS present FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            ['workflow_notifications', $column]
        );
        if ($exists !== null) {
            return;
        }
        DB::query($ddl);
    }

    private static function addIndexIfMissing(string $indexName, string $ddl): void
    {
        $exists = DB::fetchOne(
            'SELECT 1 AS present FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            ['workflow_notifications', $indexName]
        );
        if ($exists !== null) {
            return;
        }
        DB::query($ddl);
    }
}
