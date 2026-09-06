<?php
declare(strict_types=1);

namespace App\Core;

final class AuditLogService
{
    public const EVENT_LIFECYCLE = 'lifecycle';
    public const EVENT_WORKFLOW = 'workflow';
    public const EVENT_ASSIGNMENT = 'assignment';
    public const EVENT_SCOPE = 'scope';
    public const EVENT_SYSTEM = 'system';

    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_SUBMITTED = 'submitted';
    public const ACTION_APPROVED = 'approved';
    public const ACTION_REJECTED = 'rejected';
    public const ACTION_REOPENED = 'reopened';
    public const ACTION_HELD = 'held';
    public const ACTION_RESUMED = 'resumed';
    public const ACTION_FINALIZED = 'finalized';
    public const ACTION_CANCELLED = 'cancelled';
    public const ACTION_ASSIGNED = 'assigned';
    public const ACTION_SCOPE_CHANGED = 'scope_changed';
    public const ACTION_RECALCULATED = 'recalculated';
    public const ACTION_DEMAND_REGENERATED = 'demand_regenerated';
    public const ACTION_HANDOFF_CREATED = 'handoff_created';
    public const ACTION_HANDOFF_ACCEPTED = 'handoff_accepted';
    public const ACTION_HANDOFF_BLOCKED = 'handoff_blocked';
    public const ACTION_HANDOFF_COMPLETED = 'handoff_completed';
    public const ACTION_PRODUCTION_OUTPUT_SUBMITTED = 'production_output_submitted';
    public const ACTION_STOCK_ADJUSTMENT_POSTED = 'stock_adjustment_posted';

    private static bool $schemaEnsured = false;

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        DB::query(
            'CREATE TABLE IF NOT EXISTS audit_activity_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entity_type VARCHAR(80) NOT NULL,
                entity_id INT NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                action_name VARCHAR(80) NOT NULL,
                actor_user_id INT NULL,
                actor_email VARCHAR(190) NULL,
                actor_display_name VARCHAR(190) NULL,
                app_key VARCHAR(80) NULL,
                module_key VARCHAR(80) NULL,
                old_state VARCHAR(80) NULL,
                new_state VARCHAR(80) NULL,
                reason_text TEXT NULL,
                note_text TEXT NULL,
                diff_json LONGTEXT NULL,
                metadata_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_audit_entity (entity_type, entity_id, created_at),
                INDEX idx_audit_event_type (event_type, created_at),
                INDEX idx_audit_action (action_name, created_at),
                INDEX idx_audit_actor (actor_user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$schemaEnsured = true;
    }

    /**
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public static function logEvent(string $entityType, int $entityId, string $eventType, string $action, ?array $actor = null, array $options = []): array
    {
        self::ensureSchema();

        if ($entityId <= 0 || trim($entityType) === '' || trim($eventType) === '' || trim($action) === '') {
            return ['ok' => false, 'message' => 'Invalid audit payload.'];
        }

        $actorId = (int)($actor['id'] ?? 0);
        $actorEmail = trim((string)($actor['email'] ?? ''));
        $actorDisplay = trim((string)($actor['display_name'] ?? ($actor['name'] ?? ($actor['username'] ?? ''))));

        DB::query(
            'INSERT INTO audit_activity_log
             (entity_type, entity_id, event_type, action_name, actor_user_id, actor_email, actor_display_name, app_key, module_key, old_state, new_state, reason_text, note_text, diff_json, metadata_json, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [
                strtolower(trim($entityType)),
                $entityId,
                strtolower(trim($eventType)),
                strtolower(trim($action)),
                $actorId > 0 ? $actorId : null,
                $actorEmail !== '' ? $actorEmail : null,
                $actorDisplay !== '' ? $actorDisplay : null,
                self::nullIfEmpty((string)($options['app'] ?? '')),
                self::nullIfEmpty((string)($options['module'] ?? '')),
                self::nullIfEmpty((string)($options['old_state'] ?? '')),
                self::nullIfEmpty((string)($options['new_state'] ?? '')),
                self::nullIfEmpty((string)($options['reason'] ?? '')),
                self::nullIfEmpty((string)($options['note'] ?? '')),
                self::encodeJson($options['diff'] ?? null),
                self::encodeJson($options['metadata'] ?? null),
                date('Y-m-d H:i:s'),
            ]
        );

        return ['ok' => true, 'id' => (int)DB::conn()->insert_id];
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param array<int,string> $fields
     * @return array<int,array<string,mixed>>
     */
    public static function diffImportantFields(array $before, array $after, array $fields): array
    {
        $diff = [];
        foreach ($fields as $field) {
            $key = trim($field);
            if ($key === '') {
                continue;
            }

            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;
            if (self::scalarCompare($old, $new)) {
                continue;
            }

            $diff[] = [
                'field' => $key,
                'old' => $old,
                'new' => $new,
            ];
        }
        return $diff;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function timeline(string $entityType, int $entityId, int $limit = 60): array
    {
        self::ensureSchema();
        if ($entityId <= 0) {
            return [];
        }

        $limit = max(1, min(300, $limit));
        $rows = DB::fetchAll(
            'SELECT id, entity_type, entity_id, event_type, action_name, actor_user_id, actor_email, actor_display_name, app_key, module_key, old_state, new_state, reason_text, note_text, diff_json, metadata_json, created_at
             FROM audit_activity_log
             WHERE entity_type = ? AND entity_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
            [strtolower(trim($entityType)), $entityId]
        );

        foreach ($rows as &$row) {
            $row['diff'] = self::decodeJson((string)($row['diff_json'] ?? ''));
            $row['metadata'] = self::decodeJson((string)($row['metadata_json'] ?? ''));
            $actor = trim((string)($row['actor_display_name'] ?? ''));
            if ($actor === '') {
                $actor = trim((string)($row['actor_email'] ?? ''));
            }
            if ($actor === '') {
                $actor = 'System';
            }
            $row['actor_label'] = $actor;
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<int,int> $entityIds
     * @return array<int,array<string,mixed>>
     */
    public static function latestByEntityIds(string $entityType, array $entityIds): array
    {
        self::ensureSchema();

        $ids = array_values(array_unique(array_filter(array_map('intval', $entityIds), static fn(int $v): bool => $v > 0)));
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([strtolower(trim($entityType))], $ids);

        $rows = DB::fetchAll(
            'SELECT a.entity_id, a.action_name, a.event_type, a.created_at, a.actor_email, a.actor_display_name, a.new_state
             FROM audit_activity_log a
             INNER JOIN (
                SELECT entity_id, MAX(id) AS max_id
                FROM audit_activity_log
                WHERE entity_type = ? AND entity_id IN (' . $placeholders . ')
                GROUP BY entity_id
             ) x ON x.max_id = a.id',
            $params
        );

        $byId = [];
        foreach ($rows as $row) {
            $id = (int)($row['entity_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $actor = trim((string)($row['actor_display_name'] ?? ''));
            if ($actor === '') {
                $actor = trim((string)($row['actor_email'] ?? ''));
            }
            if ($actor === '') {
                $actor = 'System';
            }

            $byId[$id] = [
                'action' => (string)($row['action_name'] ?? ''),
                'event_type' => (string)($row['event_type'] ?? ''),
                'at' => (string)($row['created_at'] ?? ''),
                'actor' => $actor,
                'new_state' => (string)($row['new_state'] ?? ''),
            ];
        }

        return $byId;
    }

    /**
     * @param array<string,mixed> $filters
     * @param array<string,mixed> $scope
     * @return array<int,array<string,mixed>>
     */
    public static function explorer(array $filters, array $scope = [], int $limit = 300): array
    {
        self::ensureSchema();

        $authorityRole = strtolower(trim((string)($scope['authority_role'] ?? 'app_user')));
        if (!in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        $entityType = strtolower(trim((string)($filters['entity_type'] ?? '')));
        if ($entityType !== '') {
            $where[] = 'entity_type = ?';
            $params[] = $entityType;
        }

        $entityId = (int)($filters['entity_id'] ?? 0);
        if ($entityId > 0) {
            $where[] = 'entity_id = ?';
            $params[] = $entityId;
        }

        $appKey = strtolower(trim((string)($filters['app_key'] ?? '')));
        if ($appKey !== '') {
            $where[] = "LOWER(COALESCE(app_key, '')) = ?";
            $params[] = $appKey;
        }

        $moduleKey = strtolower(trim((string)($filters['module_key'] ?? '')));
        if ($moduleKey !== '') {
            $where[] = "LOWER(COALESCE(module_key, '')) = ?";
            $params[] = $moduleKey;
        }

        $eventType = strtolower(trim((string)($filters['event_type'] ?? '')));
        if ($eventType !== '') {
            $where[] = 'event_type = ?';
            $params[] = $eventType;
        }

        $actionName = strtolower(trim((string)($filters['action_name'] ?? '')));
        if ($actionName !== '') {
            $where[] = 'action_name = ?';
            $params[] = $actionName;
        }

        $actor = strtolower(trim((string)($filters['actor'] ?? '')));
        if ($actor !== '') {
            $where[] = "(LOWER(COALESCE(actor_email, '')) LIKE ? OR LOWER(COALESCE(actor_display_name, '')) LIKE ?)";
            $like = '%' . $actor . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $from = trim((string)($filters['from_date'] ?? ''));
        if ($from !== '') {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $from;
        }

        $to = trim((string)($filters['to_date'] ?? ''));
        if ($to !== '') {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $to;
        }

        if ($authorityRole === 'app_admin') {
            $assignedApps = array_values(array_filter(array_map('strval', (array)($scope['assigned_apps'] ?? [])), static fn(string $v): bool => trim($v) !== ''));
            if ($assignedApps !== []) {
                $holders = implode(',', array_fill(0, count($assignedApps), '?'));
                $where[] = "LOWER(COALESCE(app_key, '')) IN (" . $holders . ')';
                foreach ($assignedApps as $assignedApp) {
                    $params[] = strtolower(trim($assignedApp));
                }
            }

            $moduleVisibility = array_values(array_filter(array_map('strval', (array)($scope['module_visibility'] ?? [])), static fn(string $v): bool => trim($v) !== ''));
            if ($moduleVisibility !== []) {
                $holders = implode(',', array_fill(0, count($moduleVisibility), '?'));
                $where[] = "(module_key IS NULL OR module_key = '' OR LOWER(module_key) IN (" . $holders . '))';
                foreach ($moduleVisibility as $module) {
                    $params[] = strtolower(trim($module));
                }
            }
        }

        $limit = max(1, min(500, $limit));
        $rows = DB::fetchAll(
            'SELECT id, entity_type, entity_id, event_type, action_name, actor_user_id, actor_email, actor_display_name, app_key, module_key, old_state, new_state, reason_text, note_text, diff_json, metadata_json, created_at
             FROM audit_activity_log
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
            $params
        );

        foreach ($rows as &$row) {
            $row['diff'] = self::decodeJson((string)($row['diff_json'] ?? ''));
            $row['metadata'] = self::decodeJson((string)($row['metadata_json'] ?? ''));
            $actorLabel = trim((string)($row['actor_display_name'] ?? ''));
            if ($actorLabel === '') {
                $actorLabel = trim((string)($row['actor_email'] ?? ''));
            }
            if ($actorLabel === '') {
                $actorLabel = 'System';
            }
            $row['actor_label'] = $actorLabel;
            $row['diff_summary'] = self::compactDiffSummary(is_array($row['diff'] ?? null) ? (array)$row['diff'] : []);
        }
        unset($row);

        return $rows;
    }

    private static function scalarCompare(mixed $left, mixed $right): bool
    {
        if ($left === null && $right === null) {
            return true;
        }

        return (string)$left === (string)$right;
    }

    private static function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) && $value === []) {
            return null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }

    private static function decodeJson(string $value): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    /**
     * @param array<int,array<string,mixed>> $diff
     */
    private static function compactDiffSummary(array $diff): string
    {
        if ($diff === []) {
            return '-';
        }

        $parts = [];
        foreach (array_slice($diff, 0, 4) as $item) {
            $field = trim((string)($item['field'] ?? ''));
            if ($field === '') {
                continue;
            }
            $parts[] = $field . ': ' . (string)($item['old'] ?? '-') . ' -> ' . (string)($item['new'] ?? '-');
        }

        if ($parts === []) {
            return '-';
        }

        if (count($diff) > 4) {
            $parts[] = '+' . (count($diff) - 4) . ' more';
        }

        return implode(' | ', $parts);
    }

    private static function nullIfEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
