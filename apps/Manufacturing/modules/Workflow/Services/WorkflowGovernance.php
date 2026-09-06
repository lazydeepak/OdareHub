<?php
declare(strict_types=1);

namespace Plugins\Workflow\Services;

use App\Core\Auth;
use App\Core\DB;

final class WorkflowGovernance
{
    private static bool $schemaEnsured = false;

    /**
     * @var array<string,string>
     */
    private const TABLES = [
        WorkflowPolicy::MODULE_PRODUCTION_PLAN => 'production_plans',
        WorkflowPolicy::MODULE_QC_ENTRY => 'qc_entries',
        WorkflowPolicy::MODULE_DISPATCH_ENTRY => 'dispatch_entries',
    ];

    public static function ensureSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        foreach (self::TABLES as $table) {
            if (!self::tableExists($table)) {
                continue;
            }

            self::addColumnIfMissing($table, 'approval_status', "VARCHAR(40) NOT NULL DEFAULT 'Draft' AFTER updated_at");
            self::addColumnIfMissing($table, 'approved_by', 'INT NULL AFTER approval_status');
            self::addColumnIfMissing($table, 'approved_at', 'DATETIME NULL AFTER approved_by');
            self::addColumnIfMissing($table, 'approval_note', 'TEXT NULL AFTER approved_at');
            self::addColumnIfMissing($table, 'locked_by', 'INT NULL AFTER approval_note');
            self::addColumnIfMissing($table, 'locked_at', 'DATETIME NULL AFTER locked_by');
            self::addColumnIfMissing($table, 'reopened_by', 'INT NULL AFTER locked_at');
            self::addColumnIfMissing($table, 'reopened_at', 'DATETIME NULL AFTER reopened_by');
            self::addColumnIfMissing($table, 'reopen_reason', 'TEXT NULL AFTER reopened_at');
            self::addColumnIfMissing($table, 'override_reason', 'TEXT NULL AFTER reopen_reason');
            self::addIndexIfMissing($table, 'idx_' . $table . '_approval_status', '(approval_status)');
            self::addIndexIfMissing($table, 'idx_' . $table . '_locked_at', '(locked_at)');
        }

        DB::query(
            'CREATE TABLE IF NOT EXISTS workflow_approval_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                module_name VARCHAR(60) NOT NULL,
                record_id INT NOT NULL,
                action_name VARCHAR(60) NOT NULL,
                previous_approval_status VARCHAR(40) NULL,
                new_approval_status VARCHAR(40) NULL,
                previous_locked_state TINYINT(1) NOT NULL DEFAULT 0,
                new_locked_state TINYINT(1) NOT NULL DEFAULT 0,
                reason_text TEXT NULL,
                note_text TEXT NULL,
                acted_by INT NULL,
                acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_workflow_events_record (module_name, record_id),
                INDEX idx_workflow_events_action (action_name),
                INDEX idx_workflow_events_acted_at (acted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::$schemaEnsured = true;
    }

    public static function tableForModule(string $module): string
    {
        return self::TABLES[$module] ?? '';
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function fetchRecord(string $module, int $id): ?array
    {
        self::ensureSchema();

        if ($id <= 0) {
            return null;
        }

        $table = self::tableForModule($module);
        if ($table === '' || !self::tableExists($table)) {
            return null;
        }

        return DB::fetchOne('SELECT * FROM ' . $table . ' WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * @param array<string,mixed> $fields
     */
    public static function updateRecord(string $module, int $id, array $fields): void
    {
        self::ensureSchema();

        if ($id <= 0 || $fields === []) {
            return;
        }

        $table = self::tableForModule($module);
        if ($table === '') {
            throw new \RuntimeException('Unsupported governance module.');
        }

        $set = [];
        $params = [];
        foreach ($fields as $name => $value) {
            $column = trim((string)$name);
            if ($column === '') {
                continue;
            }
            $set[] = $column . ' = ?';
            $params[] = $value;
        }

        if ($set === []) {
            return;
        }

        $set[] = 'updated_at = NOW()';
        $params[] = $id;

        DB::query('UPDATE ' . $table . ' SET ' . implode(', ', $set) . ' WHERE id = ? LIMIT 1', $params);
    }

    /**
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     */
    public static function recordEvent(string $module, int $recordId, string $action, array $before, array $after, ?array $user = null, string $reason = '', string $note = ''): void
    {
        self::ensureSchema();

        if ($recordId <= 0) {
            return;
        }

        DB::query(
            'INSERT INTO workflow_approval_events
             (module_name, record_id, action_name, previous_approval_status, new_approval_status, previous_locked_state, new_locked_state, reason_text, note_text, acted_by, acted_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)',
            [
                $module,
                $recordId,
                trim($action),
                WorkflowPolicy::normalizeApprovalStatus((string)($before['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT),
                WorkflowPolicy::normalizeApprovalStatus((string)($after['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT),
                WorkflowPolicy::isLocked($module, $before) ? 1 : 0,
                WorkflowPolicy::isLocked($module, $after) ? 1 : 0,
                trim($reason),
                trim($note),
                self::actorId($user),
                date('Y-m-d H:i:s'),
            ]
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function withDefaults(array $row): array
    {
        $row['approval_status'] = WorkflowPolicy::normalizeApprovalStatus((string)($row['approval_status'] ?? ''), WorkflowPolicy::APPROVAL_DRAFT);
        foreach (['approved_by', 'approved_at', 'approval_note', 'locked_by', 'locked_at', 'reopened_by', 'reopened_at', 'reopen_reason', 'override_reason'] as $field) {
            if (!array_key_exists($field, $row)) {
                $row[$field] = null;
            }
        }
        return $row;
    }

    public static function actorId(?array $user = null): ?int
    {
        $id = (int)($user['id'] ?? 0);
        if ($id > 0) {
            return $id;
        }

        $auth = Auth::user();
        $authId = (int)($auth['id'] ?? 0);
        return $authId > 0 ? $authId : null;
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
