<?php
declare(strict_types=1);

namespace Plugins\Workflow\Services;

use App\Core\AclPolicy;
use App\Core\AuditLogService;
use App\Core\DB;

final class WorkflowTransitionEngine
{
    private static bool $schemaEnsured = false;

    /**
     * @return array<string,mixed>
     */
    public static function transition(string $entity, int $recordId, string $action, ?array $user = null, string $reason = '', string $note = ''): array
    {
        self::ensureSchema();

        $record = self::fetchRecord($entity, $recordId);
        if ($record === null) {
            return [
                'ok' => false,
                'code' => 'not_found',
                'message' => 'Workflow record was not found.',
            ];
        }

        $validation = self::validateTransition($entity, $record, $action, $user, $reason, $note);
        if (!(bool)($validation['ok'] ?? false)) {
            return $validation;
        }

        $toState = (string)($validation['to_state'] ?? '');
        if ($toState === '') {
            return [
                'ok' => false,
                'code' => 'invalid_rule',
                'message' => 'Workflow transition did not resolve a target state.',
            ];
        }

        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition)) {
            return [
                'ok' => false,
                'code' => 'entity_not_supported',
                'message' => 'Workflow entity is not supported.',
            ];
        }

        $stateColumn = (string)($definition['state_column'] ?? 'workflow_state');
        $fromState = self::currentState($entity, $record);

        $fields = [
            $stateColumn => $toState,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $compatibility = self::compatibilityStateFields($entity, $action, $toState, $record, $user, $reason, $note);
        foreach ($compatibility as $column => $value) {
            $fields[$column] = $value;
        }

        self::updateRecord($entity, $recordId, $fields);

        $after = self::fetchRecord($entity, $recordId) ?? $record;
        self::recordTransitionEvent($entity, $recordId, $action, $fromState, $toState, $user, $reason, $note, (bool)($definition['audit_required'] ?? true));
        self::recordAuditEvent($entity, $recordId, $action, $fromState, $toState, $user, $reason, $note, $record, $after, $validation);

        if (class_exists('\\Plugins\\Base\\Services\\NotificationService')) {
            try {
                \Plugins\Base\Services\NotificationService::handleWorkflowTransition(
                    $entity,
                    $recordId,
                    $action,
                    $fromState,
                    $toState,
                    $user,
                    $after
                );
            } catch (\Throwable $e) {
                // Never block workflow transitions if notification emission fails.
            }
        }

        if (in_array($entity, [WorkflowRegistry::ENTITY_PRODUCTION_PLAN, WorkflowRegistry::ENTITY_QC_ENTRY, WorkflowRegistry::ENTITY_DISPATCH_ENTRY], true)) {
            WorkflowGovernance::recordEvent($entity, $recordId, $action, $record, $after, $user, $reason, $note);
        }

        return [
            'ok' => true,
            'code' => 'ok',
            'message' => 'Workflow transition applied.',
            'entity' => $entity,
            'record_id' => $recordId,
            'action' => $action,
            'from_state' => $fromState,
            'to_state' => $toState,
            'record' => $after,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function validateTransition(string $entity, array $record, string $action, ?array $user = null, string $reason = '', string $note = ''): array
    {
        self::ensureSchema();

        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition)) {
            return ['ok' => false, 'code' => 'entity_not_supported', 'message' => 'Workflow entity is not supported.'];
        }

        $action = self::normalizeAction($action);
        $rule = self::transitionRule($definition, $action);
        if ($rule === null) {
            return ['ok' => false, 'code' => 'action_not_supported', 'message' => 'Workflow action is not supported for this entity.'];
        }

        $fromState = self::currentState($entity, $record);
        $allowedFrom = array_values(array_filter(array_map([self::class, 'normalizeState'], (array)($rule['from'] ?? [])), static fn(string $v): bool => $v !== ''));
        if (!in_array($fromState, $allowedFrom, true)) {
            return [
                'ok' => false,
                'code' => 'invalid_state_transition',
                'message' => 'Workflow action is not allowed from the current state.',
                'current_state' => $fromState,
                'allowed_from' => $allowedFrom,
            ];
        }

        $permission = trim((string)($rule['permission'] ?? ''));
        if ($permission !== '' && !AclPolicy::can($permission, $user)) {
            return ['ok' => false, 'code' => 'permission_denied', 'message' => 'Required permission is missing for this workflow action.', 'required_permission' => $permission];
        }

        $reason = trim($reason);
        $note = trim($note);
        if ((bool)($rule['reason_required'] ?? false) && $reason === '') {
            return ['ok' => false, 'code' => 'reason_required', 'message' => 'A reason is required for this workflow action.'];
        }
        if ((bool)($rule['note_required'] ?? false) && $note === '') {
            return ['ok' => false, 'code' => 'note_required', 'message' => 'A note is required for this workflow action.'];
        }

        $accessResult = self::validateAssignmentAndScope($definition, $record, $user);
        if (!(bool)($accessResult['ok'] ?? false)) {
            return $accessResult;
        }

        return [
            'ok' => true,
            'code' => 'ok',
            'message' => 'Workflow action is valid.',
            'current_state' => $fromState,
            'to_state' => self::normalizeState((string)($rule['to'] ?? '')),
            'rule' => $rule,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function allowedActions(string $entity, array $record, ?array $user = null): array
    {
        self::ensureSchema();

        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition)) {
            return [];
        }

        $out = [];
        foreach (array_keys((array)($definition['transitions'] ?? [])) as $action) {
            $validation = self::validateTransition($entity, $record, (string)$action, $user);
            if (!(bool)($validation['ok'] ?? false)) {
                continue;
            }

            $rule = is_array($validation['rule'] ?? null) ? $validation['rule'] : [];
            $out[] = [
                'action' => (string)$action,
                'label' => ucfirst(str_replace('_', ' ', (string)$action)),
                'to_state' => (string)($validation['to_state'] ?? ''),
                'requires_reason' => (bool)($rule['reason_required'] ?? false),
                'requires_note' => (bool)($rule['note_required'] ?? false),
                'required_permission' => (string)($rule['permission'] ?? ''),
                'approval_required' => (bool)($rule['approval_required'] ?? false),
                'notifications' => (bool)($rule['notifications'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function fetchRecord(string $entity, int $recordId): ?array
    {
        self::ensureSchema();

        if ($recordId <= 0) {
            return null;
        }

        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition)) {
            return null;
        }

        $table = (string)($definition['table'] ?? '');
        $idColumn = (string)($definition['id_column'] ?? 'id');
        if ($table === '') {
            return null;
        }

        $sql = 'SELECT * FROM ' . $table . ' WHERE ' . $idColumn . ' = ?';
        $params = [$recordId];

        $recordWhere = trim((string)($definition['record_where_sql'] ?? ''));
        if ($recordWhere !== '') {
            $sql .= ' AND ' . $recordWhere;
        }

        $sql .= ' LIMIT 1';
        return DB::fetchOne($sql, $params);
    }

    public static function currentState(string $entity, array $record): string
    {
        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition)) {
            return 'draft';
        }

        $stateColumn = (string)($definition['state_column'] ?? 'workflow_state');
        $state = self::normalizeState((string)($record[$stateColumn] ?? ''));
        if ($state !== '') {
            return $state;
        }

        return self::deriveStateFromLegacyColumns($entity, $record);
    }

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        WorkflowGovernance::ensureSchema();

        foreach (WorkflowRegistry::entities() as $entity) {
            $definition = WorkflowRegistry::entity($entity);
            if (!is_array($definition)) {
                continue;
            }

            $table = (string)($definition['table'] ?? '');
            $stateColumn = (string)($definition['state_column'] ?? 'workflow_state');
            if ($table === '' || !self::tableExists($table)) {
                continue;
            }

            self::addColumnIfMissing($table, $stateColumn, "VARCHAR(40) NULL AFTER updated_at");
            self::addIndexIfMissing($table, 'idx_' . $table . '_workflow_state', '(' . $stateColumn . ')');
        }

        DB::query(
            'CREATE TABLE IF NOT EXISTS workflow_transition_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entity_name VARCHAR(80) NOT NULL,
                record_id INT NOT NULL,
                action_name VARCHAR(80) NOT NULL,
                from_state VARCHAR(40) NULL,
                to_state VARCHAR(40) NULL,
                reason_text TEXT NULL,
                note_text TEXT NULL,
                permission_key VARCHAR(190) NULL,
                app_key VARCHAR(80) NULL,
                module_key VARCHAR(80) NULL,
                approval_required TINYINT(1) NOT NULL DEFAULT 0,
                notifications_enabled TINYINT(1) NOT NULL DEFAULT 0,
                acted_by INT NULL,
                acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_workflow_transition_entity_record (entity_name, record_id),
                INDEX idx_workflow_transition_action (action_name),
                INDEX idx_workflow_transition_acted_at (acted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$schemaEnsured = true;
    }

    /**
     * @return array<string,mixed>
     */
    private static function validateAssignmentAndScope(array $definition, array $record, ?array $user): array
    {
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($authorityRole === 'platform_admin') {
            return ['ok' => true];
        }

        $accessPolicy = platform_user_access_policy_contract();
        $module = trim((string)($definition['module'] ?? ''));
        if ($module !== '' && !$accessPolicy->canAccessModule($user, $module)) {
            return ['ok' => false, 'code' => 'module_not_allowed', 'message' => 'Workflow module is not assigned to the current user.'];
        }

        $ctx = platform_user_context_contract()->resolveUserContext($user);
        $requiredApp = strtolower(trim((string)($definition['app'] ?? '')));
        $assignedApps = array_map('strval', (array)($ctx['assigned_apps'] ?? []));
        if ($requiredApp !== '' && !in_array($requiredApp, $assignedApps, true) && $authorityRole !== 'platform_admin') {
            return ['ok' => false, 'code' => 'app_not_assigned', 'message' => 'Workflow app assignment is missing for the current user.'];
        }

        $scope = is_array($ctx['scope'] ?? null) ? (array)$ctx['scope'] : [];
        $machineIds = array_values(array_filter(array_map('intval', (array)($scope['machine_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        $recordMachine = (int)($record['machine_id'] ?? 0);
        $recordProduct = (int)($record['product_id'] ?? 0);

        if (!empty($machineIds) && $recordMachine > 0 && !in_array($recordMachine, $machineIds, true)) {
            return ['ok' => false, 'code' => 'scope_machine_denied', 'message' => 'Workflow transition is outside assigned machine scope.'];
        }
        if (!empty($partIds) && $recordProduct > 0 && !in_array($recordProduct, $partIds, true)) {
            return ['ok' => false, 'code' => 'scope_part_denied', 'message' => 'Workflow transition is outside assigned part scope.'];
        }

        return ['ok' => true];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function transitionRule(array $definition, string $action): ?array
    {
        $transitions = (array)($definition['transitions'] ?? []);
        return isset($transitions[$action]) && is_array($transitions[$action]) ? $transitions[$action] : null;
    }

    private static function updateRecord(string $entity, int $recordId, array $fields): void
    {
        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition) || $recordId <= 0 || $fields === []) {
            return;
        }

        $table = (string)($definition['table'] ?? '');
        $idColumn = (string)($definition['id_column'] ?? 'id');
        if ($table === '') {
            return;
        }

        $set = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $name = trim((string)$column);
            if ($name === '') {
                continue;
            }
            $set[] = $name . ' = ?';
            $params[] = $value;
        }

        if ($set === []) {
            return;
        }

        $params[] = $recordId;
        DB::query('UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE ' . $idColumn . ' = ? LIMIT 1', $params);
    }

    private static function recordTransitionEvent(string $entity, int $recordId, string $action, string $fromState, string $toState, ?array $user, string $reason, string $note, bool $auditRequired): void
    {
        if (!$auditRequired || $recordId <= 0) {
            return;
        }

        $definition = WorkflowRegistry::entity($entity);
        $rule = is_array($definition) ? self::transitionRule($definition, $action) : null;

        DB::query(
            'INSERT INTO workflow_transition_events
             (entity_name, record_id, action_name, from_state, to_state, reason_text, note_text, permission_key, app_key, module_key, approval_required, notifications_enabled, acted_by, acted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                $entity,
                $recordId,
                $action,
                $fromState,
                $toState,
                trim($reason),
                trim($note),
                (string)($rule['permission'] ?? ''),
                (string)($definition['app'] ?? ''),
                (string)($definition['module'] ?? ''),
                (bool)($rule['approval_required'] ?? false) ? 1 : 0,
                (bool)($rule['notifications'] ?? false) ? 1 : 0,
                WorkflowGovernance::actorId($user),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function compatibilityStateFields(string $entity, string $action, string $toState, array $record, ?array $user, string $reason, string $note): array
    {
        $actorId = WorkflowGovernance::actorId($user);
        $now = date('Y-m-d H:i:s');
        $fields = [];

        if (in_array($entity, [WorkflowRegistry::ENTITY_PRODUCTION_PLAN, WorkflowRegistry::ENTITY_QC_ENTRY, WorkflowRegistry::ENTITY_DISPATCH_ENTRY], true)) {
            $fields['approval_status'] = match ($toState) {
                'submitted' => WorkflowPolicy::APPROVAL_PENDING,
                'approved', 'finalized' => WorkflowPolicy::APPROVAL_APPROVED,
                'rejected' => WorkflowPolicy::APPROVAL_REJECTED,
                'reopened' => WorkflowPolicy::APPROVAL_REOPENED,
                default => WorkflowPolicy::APPROVAL_DRAFT,
            };

            if ($action === WorkflowRegistry::ACTION_APPROVE || $action === WorkflowRegistry::ACTION_FINALIZE) {
                $fields['approved_by'] = $actorId;
                $fields['approved_at'] = $now;
                $fields['approval_note'] = trim($note);
                $fields['locked_by'] = $actorId;
                $fields['locked_at'] = $now;
            }
            if ($action === WorkflowRegistry::ACTION_REJECT) {
                $fields['approval_note'] = trim($note) !== '' ? trim($note) : trim($reason);
                $fields['locked_by'] = null;
                $fields['locked_at'] = null;
            }
            if ($action === WorkflowRegistry::ACTION_REOPEN) {
                $fields['reopened_by'] = $actorId;
                $fields['reopened_at'] = $now;
                $fields['reopen_reason'] = trim($reason);
                $fields['locked_by'] = null;
                $fields['locked_at'] = null;
            }
            if ($action === WorkflowRegistry::ACTION_SUBMIT) {
                $fields['approval_note'] = trim($note);
            }
        }

        if ($entity === WorkflowRegistry::ENTITY_DISPATCH_ENTRY) {
            $fields['dispatch_status'] = match ($toState) {
                'draft' => 'Draft',
                'submitted' => 'Ready',
                'approved' => 'Ready',
                'hold' => 'Hold',
                'finalized' => 'Dispatched',
                'cancelled' => 'Cancelled',
                default => (string)($record['dispatch_status'] ?? 'Ready'),
            };

            if ($action === WorkflowRegistry::ACTION_HOLD) {
                $fields['status_reason'] = trim($reason);
                $fields['blocked_at'] = $now;
            }
            if ($action === WorkflowRegistry::ACTION_RESUME) {
                $fields['status_reason'] = 'Resumed';
                $fields['blocked_at'] = null;
            }
            if ($action === WorkflowRegistry::ACTION_FINALIZE) {
                $fields['dispatched_at'] = $now;
            }
            $fields['last_transition_at'] = $now;
            $fields['status_updated_by'] = $actorId;
        }

        if ($entity === WorkflowRegistry::ENTITY_ASSEMBLY_PLAN) {
            $fields['status'] = match ($toState) {
                'approved', 'finalized' => 'approved',
                'rejected' => 'adjusted',
                'submitted' => 'adjusted',
                'cancelled' => 'adjusted',
                default => (string)($record['status'] ?? 'calculated'),
            };
            if ($action === WorkflowRegistry::ACTION_APPROVE || $action === WorkflowRegistry::ACTION_FINALIZE) {
                $fields['approved_qty'] = isset($record['approved_qty']) && (float)$record['approved_qty'] > 0
                    ? (float)$record['approved_qty']
                    : (isset($record['adjusted_qty']) ? (float)$record['adjusted_qty'] : (float)($record['system_qty'] ?? 0));
            }
            if (trim($note) !== '') {
                $fields['adjustment_note'] = trim($note);
            } elseif (trim($reason) !== '') {
                $fields['adjustment_note'] = trim($reason);
            }
        }

        return $fields;
    }

    private static function deriveStateFromLegacyColumns(string $entity, array $record): string
    {
        if (in_array($entity, [WorkflowRegistry::ENTITY_PRODUCTION_PLAN, WorkflowRegistry::ENTITY_QC_ENTRY, WorkflowRegistry::ENTITY_DISPATCH_ENTRY], true)) {
            $approval = WorkflowPolicy::normalizeApprovalStatus((string)($record['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT);
            $approvalState = match ($approval) {
                WorkflowPolicy::APPROVAL_PENDING => 'submitted',
                WorkflowPolicy::APPROVAL_APPROVED => 'approved',
                WorkflowPolicy::APPROVAL_REJECTED => 'rejected',
                WorkflowPolicy::APPROVAL_REOPENED => 'reopened',
                default => 'draft',
            };

            if ($entity === WorkflowRegistry::ENTITY_DISPATCH_ENTRY) {
                $dispatchStatus = strtolower(trim((string)($record['dispatch_status'] ?? '')));
                if (in_array($dispatchStatus, ['hold', 'blocked'], true)) {
                    return 'hold';
                }
                if (in_array($dispatchStatus, ['dispatched', 'completed', 'closed'], true)) {
                    return 'finalized';
                }
                if (in_array($dispatchStatus, ['cancelled', 'canceled'], true)) {
                    return 'cancelled';
                }
            }

            return $approvalState;
        }

        if ($entity === WorkflowRegistry::ENTITY_ASSEMBLY_PLAN) {
            $status = strtolower(trim((string)($record['status'] ?? 'calculated')));
            if ($status === 'approved') {
                return 'approved';
            }
            if ($status === 'adjusted') {
                return 'submitted';
            }
            return 'draft';
        }

        return 'draft';
    }

    private static function normalizeAction(string $action): string
    {
        $action = strtolower(trim($action));
        return match ($action) {
            'unlock_override' => WorkflowRegistry::ACTION_REOPEN,
            default => $action,
        };
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param array<string,mixed> $validation
     */
    private static function recordAuditEvent(string $entity, int $recordId, string $action, string $fromState, string $toState, ?array $user, string $reason, string $note, array $before, array $after, array $validation): void
    {
        $definition = WorkflowRegistry::entity($entity);
        if (!is_array($definition)) {
            return;
        }

        $taxonomyAction = match (strtolower(trim($action))) {
            WorkflowRegistry::ACTION_SUBMIT => AuditLogService::ACTION_SUBMITTED,
            WorkflowRegistry::ACTION_APPROVE => AuditLogService::ACTION_APPROVED,
            WorkflowRegistry::ACTION_REJECT => AuditLogService::ACTION_REJECTED,
            WorkflowRegistry::ACTION_REOPEN => AuditLogService::ACTION_REOPENED,
            WorkflowRegistry::ACTION_HOLD => AuditLogService::ACTION_HELD,
            WorkflowRegistry::ACTION_RESUME => AuditLogService::ACTION_RESUMED,
            WorkflowRegistry::ACTION_FINALIZE => AuditLogService::ACTION_FINALIZED,
            WorkflowRegistry::ACTION_CANCEL => AuditLogService::ACTION_CANCELLED,
            default => strtolower(trim($action)),
        };

        $diffFields = match ($entity) {
            WorkflowRegistry::ENTITY_PRODUCTION_PLAN => ['workflow_state', 'approval_status', 'status', 'planned_qty', 'machine_id', 'product_id', 'notes', 'added_by'],
            WorkflowRegistry::ENTITY_ASSEMBLY_PLAN => ['workflow_state', 'status', 'system_qty', 'adjusted_qty', 'approved_qty', 'adjustment_note'],
            WorkflowRegistry::ENTITY_QC_ENTRY => ['workflow_state', 'approval_status', 'status', 'checked_qty', 'pass_qty', 'fail_qty', 'remarks'],
            WorkflowRegistry::ENTITY_DISPATCH_ENTRY => ['workflow_state', 'approval_status', 'dispatch_status', 'dispatchable_qty', 'destination', 'status_reason', 'status_note', 'remarks'],
            default => ['workflow_state'],
        };

        AuditLogService::logEvent(
            $entity,
            $recordId,
            AuditLogService::EVENT_WORKFLOW,
            $taxonomyAction,
            $user,
            [
                'app' => (string)($definition['app'] ?? ''),
                'module' => (string)($definition['module'] ?? ''),
                'old_state' => $fromState,
                'new_state' => $toState,
                'reason' => $reason,
                'note' => $note,
                'diff' => AuditLogService::diffImportantFields($before, $after, $diffFields),
                'metadata' => [
                    'workflow_action' => $action,
                    'validation' => [
                        'code' => (string)($validation['code'] ?? ''),
                        'message' => (string)($validation['message'] ?? ''),
                    ],
                ],
            ]
        );
    }

    private static function normalizeState(string $state): string
    {
        $state = strtolower(trim($state));
        $state = str_replace(' ', '_', $state);
        return match ($state) {
            'pending_approval', 'pending', 'submitted_for_approval' => 'submitted',
            're_opened' => 'reopened',
            'inprogress' => 'in_progress',
            default => $state,
        };
    }

    private static function tableExists(string $table): bool
    {
        $escaped = DB::conn()->real_escape_string($table);
        return DB::fetchOne("SHOW TABLES LIKE '{$escaped}'") !== null;
    }

    private static function addColumnIfMissing(string $table, string $column, string $definition): void
    {
        $escapedTable = DB::conn()->real_escape_string($table);
        $escapedColumn = DB::conn()->real_escape_string($column);
        $exists = DB::fetchOne("SHOW COLUMNS FROM {$escapedTable} LIKE '{$escapedColumn}'");
        if ($exists !== null) {
            return;
        }

        DB::query('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }

    private static function addIndexIfMissing(string $table, string $index, string $definition): void
    {
        $escapedTable = DB::conn()->real_escape_string($table);
        $escapedIndex = DB::conn()->real_escape_string($index);
        $exists = DB::fetchOne("SHOW INDEX FROM {$escapedTable} WHERE Key_name = '{$escapedIndex}'");
        if ($exists !== null) {
            return;
        }

        DB::query('ALTER TABLE ' . $table . ' ADD INDEX ' . $index . ' ' . $definition);
    }
}
