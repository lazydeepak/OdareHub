<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

/**
 * OperatorTaskService
 *
 * CRUD and status management for operator tasks.
 *
 * Table: operator_tasks
 *   id, title, description, assigned_to (user_id), assigned_by (user_id),
 *   priority (low|medium|high|urgent), status (open|in_progress|done|cancelled),
 *   due_date (DATE), due_shift (VARCHAR), completed_at, cancelled_at,
 *   created_at, updated_at
 *
 * Admin creates/edits tasks and assigns them to operators.
 * Operators can advance status: open → in_progress → done.
 * Admins can cancel any task.
 */
final class OperatorTaskService
{
    // ── Constants ──────────────────────────────────────────────────────────

    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_CANCELLED   = 'cancelled';

    public const PRIORITY_LOW    = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH   = 'high';
    public const PRIORITY_URGENT = 'urgent';

    // ── Read ───────────────────────────────────────────────────────────────

    /**
     * Return tasks assigned to a user, newest-first.
     * Optionally filtered to active only (open + in_progress).
     *
     * @return array{tasks: array<int,array<string,mixed>>, open_count: int, error: string}
     */
    public static function getForUser(int $userId, bool $activeOnly = false): array
    {
        $result = ['tasks' => [], 'open_count' => 0, 'error' => ''];

        try {
            $whereExtra = $activeOnly
                ? " AND t.status IN ('open','in_progress')"
                : '';

            $rows = DB::fetchAll(
                "SELECT t.id, t.title, t.description,
                        t.assigned_to, t.assigned_by,
                        t.priority, t.status,
                        t.due_date, t.due_shift,
                        t.completed_at, t.cancelled_at,
                        t.created_at, t.updated_at,
                        u.display_name AS assigned_by_name
                   FROM operator_tasks t
                   LEFT JOIN users u ON u.id = t.assigned_by
                  WHERE t.assigned_to = ?" . $whereExtra . "
                  ORDER BY
                    FIELD(t.status,'open','in_progress','done','cancelled'),
                    FIELD(t.priority,'urgent','high','medium','low'),
                    t.due_date ASC,
                    t.created_at DESC",
                [$userId]
            );

            $open = 0;
            foreach ($rows as &$row) {
                $row['is_overdue'] = self::isOverdue($row);
                $row['priority_label'] = self::priorityLabel((string)$row['priority']);
                $row['status_label']   = self::statusLabel((string)$row['status']);
                $row['description_safe'] = nl2br(htmlspecialchars((string)($row['description'] ?? '')));
                if ($row['status'] === self::STATUS_OPEN || $row['status'] === self::STATUS_IN_PROGRESS) {
                    $open++;
                }
            }
            unset($row);

            $result['tasks']      = $rows;
            $result['open_count'] = $open;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::getForUser: ' . $e->getMessage());
            $result['error'] = 'Unable to load tasks.';
        }

        return $result;
    }

    /**
     * Return all tasks (for admin listing), optionally filtered by assignee.
     *
     * @return array{tasks: array<int,array<string,mixed>>, error: string}
     */
    public static function getAll(?int $assignedToUserId = null, string $statusFilter = ''): array
    {
        $result = ['tasks' => [], 'error' => ''];

        try {
            $where  = ['1=1'];
            $params = [];

            if ($assignedToUserId !== null) {
                $where[]  = 't.assigned_to = ?';
                $params[] = $assignedToUserId;
            }

            if ($statusFilter !== '' && in_array($statusFilter, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
                $where[]  = 't.status = ?';
                $params[] = $statusFilter;
            }

            $rows = DB::fetchAll(
                "SELECT t.id, t.title, t.description,
                        t.assigned_to, t.assigned_by,
                        t.priority, t.status,
                        t.due_date, t.due_shift,
                        t.completed_at, t.cancelled_at,
                        t.created_at, t.updated_at,
                        ua.display_name AS assigned_to_name,
                        ub.display_name AS assigned_by_name
                   FROM operator_tasks t
                   LEFT JOIN users ua ON ua.id = t.assigned_to
                   LEFT JOIN users ub ON ub.id = t.assigned_by
                  WHERE " . implode(' AND ', $where) . "
                  ORDER BY
                    FIELD(t.status,'open','in_progress','done','cancelled'),
                    FIELD(t.priority,'urgent','high','medium','low'),
                    t.due_date ASC,
                    t.created_at DESC",
                $params
            );

            foreach ($rows as &$row) {
                $row['is_overdue']       = self::isOverdue($row);
                $row['priority_label']   = self::priorityLabel((string)$row['priority']);
                $row['status_label']     = self::statusLabel((string)$row['status']);
                $row['description_safe'] = nl2br(htmlspecialchars((string)($row['description'] ?? '')));
            }
            unset($row);

            $result['tasks'] = $rows;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::getAll: ' . $e->getMessage());
            $result['error'] = 'Unable to load tasks.';
        }

        return $result;
    }

    /**
     * Fetch a single task by ID. Returns null if not found.
     *
     * @return array<string,mixed>|null
     */
    public static function getById(int $id): ?array
    {
        try {
            $row = DB::fetchOne(
                "SELECT t.*, ua.display_name AS assigned_to_name, ub.display_name AS assigned_by_name
                   FROM operator_tasks t
                   LEFT JOIN users ua ON ua.id = t.assigned_to
                   LEFT JOIN users ub ON ub.id = t.assigned_by
                  WHERE t.id = ?",
                [$id]
            );
            if ($row === null || $row === false) {
                return null;
            }
            $row = (array)$row;
            $row['is_overdue']     = self::isOverdue($row);
            $row['priority_label'] = self::priorityLabel((string)($row['priority'] ?? ''));
            $row['status_label']   = self::statusLabel((string)($row['status'] ?? ''));
            return $row;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::getById: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Count open tasks for a user (open + in_progress).
     * Used for sidebar badge. Returns 0 on DB error.
     */
    public static function countOpen(int $userId): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS cnt FROM operator_tasks
                  WHERE assigned_to = ? AND status IN ('open','in_progress')",
                [$userId]
            );
            return (int)(is_array($row) ? ($row['cnt'] ?? 0) : 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    // ── Write ──────────────────────────────────────────────────────────────

    /**
     * Create a new task. Returns ['ok' => true, 'id' => int] or ['ok' => false, 'error' => string].
     *
     * @param array<string,mixed> $data
     * @return array{ok: bool, id: int, error: string}
     */
    public static function create(array $data): array
    {
        $result = ['ok' => false, 'id' => 0, 'error' => ''];

        $title      = trim((string)($data['title'] ?? ''));
        $desc       = trim((string)($data['description'] ?? ''));
        $assignedTo = (int)($data['assigned_to'] ?? 0);
        $assignedBy = (int)($data['assigned_by'] ?? 0);
        $priority   = self::sanitizePriority((string)($data['priority'] ?? 'medium'));
        $dueDate    = self::sanitizeDate((string)($data['due_date'] ?? ''));
        $dueShift   = substr(trim((string)($data['due_shift'] ?? '')), 0, 60);

        if ($title === '') {
            $result['error'] = 'Task title is required.';
            return $result;
        }
        if ($assignedTo <= 0 || $assignedBy <= 0) {
            $result['error'] = 'Invalid user assignment.';
            return $result;
        }

        try {
            DB::query(
                "INSERT INTO operator_tasks
                    (title, description, assigned_to, assigned_by, priority, status, due_date, due_shift, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, 'open', ?, ?, NOW(), NOW())",
                [$title, $desc !== '' ? $desc : null, $assignedTo, $assignedBy, $priority, $dueDate !== '' ? $dueDate : null, $dueShift !== '' ? $dueShift : null]
            );
            $result['ok'] = true;
            $result['id'] = (int)(int)DB::conn()->insert_id;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::create: ' . $e->getMessage());
            $result['error'] = 'Failed to create task.';
        }

        return $result;
    }

    /**
     * Update an existing task (admin only — all fields).
     *
     * @param array<string,mixed> $data
     * @return array{ok: bool, error: string}
     */
    public static function update(int $id, array $data): array
    {
        $result = ['ok' => false, 'error' => ''];

        $title    = trim((string)($data['title'] ?? ''));
        $desc     = trim((string)($data['description'] ?? ''));
        $priority = self::sanitizePriority((string)($data['priority'] ?? 'medium'));
        $status   = self::sanitizeStatus((string)($data['status'] ?? 'open'));
        $dueDate  = self::sanitizeDate((string)($data['due_date'] ?? ''));
        $dueShift = substr(trim((string)($data['due_shift'] ?? '')), 0, 60);
        $assignedTo = (int)($data['assigned_to'] ?? 0);

        if ($title === '') {
            $result['error'] = 'Task title is required.';
            return $result;
        }

        // Compute timestamp fields
        $completedAt = null;
        $cancelledAt = null;
        if ($status === self::STATUS_DONE) {
            // Preserve original completed_at if already set
            $existing = self::getById($id);
            $completedAt = $existing['completed_at'] ?? null;
            if ($completedAt === null) {
                $completedAt = date('Y-m-d H:i:s');
            }
        } elseif ($status === self::STATUS_CANCELLED) {
            $cancelledAt = date('Y-m-d H:i:s');
        }

        try {
            DB::query(
                "UPDATE operator_tasks
                    SET title = ?, description = ?, priority = ?, status = ?,
                        due_date = ?, due_shift = ?, assigned_to = ?,
                        completed_at = ?, cancelled_at = ?,
                        updated_at = NOW()
                  WHERE id = ?",
                [
                    $title,
                    $desc !== '' ? $desc : null,
                    $priority,
                    $status,
                    $dueDate !== '' ? $dueDate : null,
                    $dueShift !== '' ? $dueShift : null,
                    $assignedTo > 0 ? $assignedTo : null,
                    $completedAt,
                    $cancelledAt,
                    $id,
                ]
            );
            $result['ok'] = true;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::update: ' . $e->getMessage());
            $result['error'] = 'Failed to update task.';
        }

        return $result;
    }

    /**
     * Operator advances their own task status.
     * Allowed transitions: open → in_progress, in_progress → done.
     * Returns ['ok' => bool, 'error' => string].
     *
     * @return array{ok: bool, error: string}
     */
    public static function advanceStatus(int $taskId, int $operatorUserId): array
    {
        $result = ['ok' => false, 'error' => ''];

        $task = self::getById($taskId);
        if ($task === null) {
            $result['error'] = 'Task not found.';
            return $result;
        }

        if ((int)$task['assigned_to'] !== $operatorUserId) {
            $result['error'] = 'Not authorized to update this task.';
            return $result;
        }

        $current = (string)$task['status'];
        $next    = match ($current) {
            self::STATUS_OPEN        => self::STATUS_IN_PROGRESS,
            self::STATUS_IN_PROGRESS => self::STATUS_DONE,
            default                  => null,
        };

        if ($next === null) {
            $result['error'] = 'Task cannot be advanced from current status.';
            return $result;
        }

        $completedAt = $next === self::STATUS_DONE ? date('Y-m-d H:i:s') : null;

        try {
            DB::query(
                "UPDATE operator_tasks
                    SET status = ?, completed_at = ?, updated_at = NOW()
                  WHERE id = ? AND assigned_to = ?",
                [$next, $completedAt, $taskId, $operatorUserId]
            );
            $result['ok'] = true;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::advanceStatus: ' . $e->getMessage());
            $result['error'] = 'Failed to update task status.';
        }

        return $result;
    }

    /**
     * Cancel a task (admin action).
     *
     * @return array{ok: bool, error: string}
     */
    public static function cancel(int $taskId): array
    {
        $result = ['ok' => false, 'error' => ''];
        try {
            DB::query(
                "UPDATE operator_tasks
                    SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
                  WHERE id = ?",
                [$taskId]
            );
            $result['ok'] = true;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::cancel: ' . $e->getMessage());
            $result['error'] = 'Failed to cancel task.';
        }
        return $result;
    }

    /**
     * Delete a task permanently (admin action). Only cancelled/done tasks may be deleted.
     *
     * @return array{ok: bool, error: string}
     */
    public static function delete(int $taskId): array
    {
        $result = ['ok' => false, 'error' => ''];
        try {
            // Safety: only allow deleting finished/cancelled tasks
            $task = self::getById($taskId);
            if ($task === null) {
                $result['error'] = 'Task not found.';
                return $result;
            }
            if (!in_array((string)$task['status'], [self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
                $result['error'] = 'Only completed or cancelled tasks can be deleted.';
                return $result;
            }
            DB::query("DELETE FROM operator_tasks WHERE id = ?", [$taskId]);
            $result['ok'] = true;
        } catch (\Throwable $e) {
            error_log('OperatorTaskService::delete: ' . $e->getMessage());
            $result['error'] = 'Failed to delete task.';
        }
        return $result;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Returns list of operators (app_user / app_admin) for the assignment dropdown.
     *
     * @return array<int,array{id: int, display_name: string, email: string}>
     */
    public static function listOperatorUsers(): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT u.id, u.display_name, u.email
                   FROM users u
                   JOIN user_dashboard_assignments da ON da.user_id = u.id
                  WHERE da.account_class IN ('app_user','app_admin')
                    AND u.account_status = 'active'
                  ORDER BY u.display_name ASC, u.email ASC"
            );
            return array_map(static fn($r) => [
                'id'           => (int)$r['id'],
                'display_name' => (string)($r['display_name'] ?? $r['email']),
                'email'        => (string)$r['email'],
            ], $rows);
        } catch (\Throwable) {
            return [];
        }
    }

    private static function isOverdue(array $row): bool
    {
        if (in_array((string)($row['status'] ?? ''), [self::STATUS_DONE, self::STATUS_CANCELLED], true)) {
            return false;
        }
        $due = (string)($row['due_date'] ?? '');
        return $due !== '' && $due < date('Y-m-d');
    }

    private static function sanitizePriority(string $v): string
    {
        return in_array($v, [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT], true) ? $v : self::PRIORITY_MEDIUM;
    }

    private static function sanitizeStatus(string $v): string
    {
        return in_array($v, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_DONE, self::STATUS_CANCELLED], true) ? $v : self::STATUS_OPEN;
    }

    private static function sanitizeDate(string $v): string
    {
        if ($v === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return '';
        }
        return $v;
    }

    public static function priorityLabel(string $p): string
    {
        return match ($p) {
            self::PRIORITY_LOW    => 'Low',
            self::PRIORITY_HIGH   => 'High',
            self::PRIORITY_URGENT => 'Urgent',
            default               => 'Medium',
        };
    }

    public static function statusLabel(string $s): string
    {
        return match ($s) {
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_DONE        => 'Done',
            self::STATUS_CANCELLED   => 'Cancelled',
            default                  => 'Open',
        };
    }
}
