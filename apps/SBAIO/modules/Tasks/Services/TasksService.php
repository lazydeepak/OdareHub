<?php
declare(strict_types=1);

namespace Plugins\Tasks\Services;

use App\Core\DB;

final class TasksService
{
    private const MAX_SEARCH_LIMIT = 50;

    /**
     * Build the existing Task overview report payload without changing its
     * predicates, aggregation, ordering, limits, or default-on-error behavior.
     *
     * @return array{summary:array<string,mixed>,byStatus:array<int,array<string,mixed>>,byPriority:array<int,array<string,mixed>>,recent:array<int,array<string,mixed>>}
     */
    public static function reportData(): array
    {
        $summary = ['total'=>0,'open'=>0,'done'=>0,'overdue'=>0,'due_7d'=>0];
        $byStatus = [];
        $byPriority = [];
        $recent = [];

        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(CASE WHEN LOWER(COALESCE(task_status,'')) IN ('open','in_progress','pending','todo') THEN 1 ELSE 0 END),0) AS open,
                        COALESCE(SUM(CASE WHEN LOWER(COALESCE(task_status,'')) IN ('done','completed','closed') THEN 1 ELSE 0 END),0) AS done,
                        COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND LOWER(COALESCE(task_status,'')) NOT IN ('done','completed','closed') THEN 1 ELSE 0 END),0) AS overdue,
                        COALESCE(SUM(CASE WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS due_7d
                 FROM sbaio_tasks"
            );
            if (is_array($row)) { $summary = array_merge($summary, $row); }

            $byStatus = DB::fetchAll(
                "SELECT COALESCE(NULLIF(task_status,''),'(none)') AS task_status, COUNT(*) AS n
                 FROM sbaio_tasks GROUP BY task_status ORDER BY n DESC"
            ) ?: [];

            $byPriority = DB::fetchAll(
                "SELECT COALESCE(NULLIF(priority,''),'(none)') AS priority, COUNT(*) AS n
                 FROM sbaio_tasks GROUP BY priority ORDER BY n DESC"
            ) ?: [];

            $recent = DB::fetchAll(
                "SELECT id, task_title, owner_name, task_type, task_status, priority, due_date
                 FROM sbaio_tasks ORDER BY due_date ASC, id DESC LIMIT 25"
            ) ?: [];
        } catch (\Throwable) {
            // Preserve the report view's existing fail-closed/default payload.
        }

        return compact('summary', 'byStatus', 'byPriority', 'recent');
    }

    /**
     * Validate and normalize the existing Task create payload without
     * touching persistence or interpreting Staff identity.
     *
     * @param array<string,mixed> $input
     * @return array{valid:bool,errors:list<string>,values:array<string,mixed>}
     */
    public static function validateCreateInput(array $input): array
    {
        $errors = [];
        $taskTitle = self::scalarString($input, 'task_title');
        $ownerName = self::scalarString($input, 'owner_name');
        $taskType = self::scalarString($input, 'task_type');
        $taskBucket = self::scalarString($input, 'task_bucket');
        $taskStatus = self::scalarString($input, 'task_status');
        $priority = self::scalarString($input, 'priority');
        $relatedDate = self::scalarString($input, 'related_date');
        $dueDate = self::scalarString($input, 'due_date');

        if ($taskTitle === null) {
            $errors[] = 'task_title_invalid';
            $taskTitle = '';
        } else {
            $taskTitle = trim($taskTitle);
            if ($taskTitle === '') {
                $errors[] = 'task_title_required';
            } elseif (strlen($taskTitle) > 190) {
                $errors[] = 'task_title_too_long';
            }
        }

        $ownerName = self::validateOptionalText($ownerName, 190, 'owner_name', $errors);
        $taskType = self::validateOptionalText($taskType, 80, 'task_type', $errors);
        $taskBucket = self::validateOptionalText($taskBucket, 80, 'task_bucket', $errors);

        $relatedStaffId = self::normalizeRelatedStaffId($input['related_staff_id'] ?? null);
        if ($relatedStaffId === false) {
            $errors[] = 'related_staff_id_invalid';
            $relatedStaffId = null;
        }

        $relatedDate = self::validateOptionalDate($relatedDate, 'related_date', $errors);

        if ($taskStatus === null) {
            $errors[] = 'task_status_invalid';
            $taskStatus = 'open';
        } else {
            $taskStatus = trim($taskStatus);
            if ($taskStatus === '') {
                $taskStatus = 'open';
            } elseif (strlen($taskStatus) > 40) {
                $errors[] = 'task_status_too_long';
            }
        }

        if ($priority === null) {
            $errors[] = 'priority_invalid';
            $priority = 'normal';
        } else {
            $priority = trim($priority);
            if ($priority === '') {
                $priority = 'normal';
            } elseif (strlen($priority) > 40) {
                $errors[] = 'priority_too_long';
            }
        }

        $dueDate = self::validateOptionalDate($dueDate, 'due_date', $errors);

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'values' => [
                'task_title' => $taskTitle,
                'owner_name' => $ownerName,
                'task_type' => $taskType,
                'task_bucket' => $taskBucket,
                'related_staff_id' => $relatedStaffId,
                'related_date' => $relatedDate,
                'task_status' => $taskStatus,
                'priority' => $priority,
                'due_date' => $dueDate,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        return DB::fetchAll(
            'SELECT id, task_title, owner_name, task_type, task_bucket, task_status, priority, related_date FROM sbaio_tasks ORDER BY id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Discover Task records with exact filters and forward keyset pagination.
     * Queue modes are read-only projections over existing due dates/statuses.
     *
     * @param array<string,mixed> $filters
     * @return array{valid:bool,available:bool,rows:array<int,array<string,mixed>>,truncated:bool,limit:int,filters:array{queue:string,status:string,priority:string,task_bucket:string,from:string,to:string},next_cursor:?string}
     */
    public static function search(array $filters, int $limit = self::MAX_SEARCH_LIMIT, ?string $cursor = null): array
    {
        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $normalized = self::normalizeSearchFilters($filters);
        $fingerprint = self::searchFilterFingerprint($normalized);
        $cursorData = self::decodeSearchCursor($cursor, $fingerprint);
        $where = [];
        $params = [];

        if ($normalized['status'] !== '') {
            $where[] = 'task_status = ?';
            $params[] = $normalized['status'];
        }
        if ($normalized['priority'] !== '') {
            $where[] = 'priority = ?';
            $params[] = $normalized['priority'];
        }
        if ($normalized['task_bucket'] !== '') {
            $where[] = 'task_bucket = ?';
            $params[] = $normalized['task_bucket'];
        }
        if ($normalized['from'] !== '') {
            $where[] = 'due_date >= ?';
            $params[] = $normalized['from'];
        }
        if ($normalized['to'] !== '') {
            $where[] = 'due_date <= ?';
            $params[] = $normalized['to'];
        }

        $activePredicate = "LOWER(COALESCE(task_status,'')) NOT IN ('done','completed','closed')";
        if ($normalized['queue'] === 'overdue') {
            $where[] = "due_date IS NOT NULL AND due_date < CURDATE() AND {$activePredicate}";
        } elseif ($normalized['queue'] === 'upcoming') {
            $where[] = "due_date IS NOT NULL AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND {$activePredicate}";
        }

        if ($cursorData !== null) {
            if ($cursorData['due_date'] === null) {
                $where[] = '(due_date IS NULL AND id < ?)';
                $params[] = $cursorData['id'];
            } else {
                $where[] = '(due_date < ? OR due_date IS NULL OR (due_date = ? AND id < ?))';
                $params[] = $cursorData['due_date'];
                $params[] = $cursorData['due_date'];
                $params[] = $cursorData['id'];
            }
        }

        $rows = DB::fetchAll(
            "SELECT id, task_title, owner_name, task_type, task_bucket, related_staff_id, related_date, task_status, priority, due_date,
                    CASE
                      WHEN due_date IS NOT NULL AND due_date < CURDATE() AND {$activePredicate} THEN 'overdue'
                      WHEN due_date IS NOT NULL AND due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND {$activePredicate} THEN 'upcoming'
                      ELSE 'historical'
                    END AS queue_state
             FROM sbaio_tasks"
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY due_date DESC, id DESC LIMIT ' . ($limit + 1),
            $params
        );
        $truncated = count($rows) > $limit;
        $displayRows = array_values(array_slice($rows, 0, $limit));
        $nextCursor = $truncated && $displayRows !== []
            ? self::encodeSearchCursor($displayRows[count($displayRows) - 1], $fingerprint)
            : null;

        return [
            'valid' => true,
            'available' => true,
            'rows' => $displayRows,
            'truncated' => $truncated,
            'limit' => $limit,
            'filters' => $normalized,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function activeStaff(): array
    {
        return DB::fetchAll(
            "SELECT id, full_name, employee_code FROM sbaio_staff WHERE employment_status='active' OR employment_status IS NULL ORDER BY full_name ASC"
        );
    }

    /**
     * Read one Task by its database-generated identity.
     *
     * @param mixed $id
     * @return array<string,mixed>|null
     */
    public static function findById(mixed $id): ?array
    {
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException('Invalid Task record ID.');
        }
        if (is_int($id)) {
            $recordId = $id;
        } elseif (preg_match('/^[1-9][0-9]*$/D', $id)) {
            $recordId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($recordId === false) {
                throw new \InvalidArgumentException('Invalid Task record ID.');
            }
        } else {
            throw new \InvalidArgumentException('Invalid Task record ID.');
        }
        if ($recordId <= 0) {
            throw new \InvalidArgumentException('Invalid Task record ID.');
        }

        return DB::fetchOne(
            'SELECT id, task_title, owner_name, task_type, task_bucket, related_staff_id, related_date, task_status, priority, due_date FROM sbaio_tasks WHERE id = ? LIMIT 1',
            [(int)$recordId]
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{queue:string,status:string,priority:string,task_bucket:string,from:string,to:string}
     */
    private static function normalizeSearchFilters(array $filters): array
    {
        $allowed = ['queue', 'status', 'priority', 'task_bucket', 'from', 'to'];
        if (array_diff(array_keys($filters), $allowed) !== []) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_FILTER_INVALID');
        }
        $values = [];
        foreach ($allowed as $key) {
            $value = $filters[$key] ?? '';
            if (!is_string($value)) {
                throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_FILTER_INVALID');
            }
            $values[$key] = trim($value);
        }
        $values['queue'] = strtolower($values['queue']);
        if (!in_array($values['queue'], ['all', 'overdue', 'upcoming', 'historical'], true)
            || strlen($values['status']) > 40
            || strlen($values['priority']) > 40
            || strlen($values['task_bucket']) > 80
            || ($values['from'] !== '' && !self::isCalendarDate($values['from']))
            || ($values['to'] !== '' && !self::isCalendarDate($values['to']))
            || ($values['from'] !== '' && $values['to'] !== '' && $values['from'] > $values['to'])) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_FILTER_INVALID');
        }

        return $values;
    }

    /** @param array{queue:string,status:string,priority:string,task_bucket:string,from:string,to:string} $filters */
    private static function searchFilterFingerprint(array $filters): string
    {
        return hash('sha256', (string)json_encode($filters, JSON_UNESCAPED_SLASHES));
    }

    /** @return array{due_date:?string,id:int}|null */
    private static function decodeSearchCursor(?string $cursor, string $fingerprint): ?array
    {
        if ($cursor === null || trim($cursor) === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) || (strlen($cursor) % 4) === 1) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_CURSOR_INVALID');
        }
        $encoded = strtr($cursor, '-_', '+/');
        $encoded .= str_repeat('=', (4 - (strlen($encoded) % 4)) % 4);
        $json = base64_decode($encoded, true);
        if ($json === false) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_CURSOR_INVALID');
        }
        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_CURSOR_INVALID');
        }
        if (!is_array($payload)
            || array_diff(array_keys($payload), ['v', 'due_date', 'id', 'fingerprint']) !== []
            || count($payload) !== 4
            || ($payload['v'] ?? null) !== 1
            || !is_string($payload['fingerprint'] ?? null)
            || !hash_equals($fingerprint, $payload['fingerprint'])) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_CURSOR_INVALID');
        }
        $dueDate = $payload['due_date'] ?? null;
        if ($dueDate !== null && (!is_string($dueDate) || !self::isCalendarDate($dueDate))) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_CURSOR_INVALID');
        }
        $id = $payload['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new \InvalidArgumentException('SBAIO_TASKS_SEARCH_CURSOR_INVALID');
        }
        return ['due_date' => $dueDate, 'id' => $id];
    }

    /** @param array<string,mixed> $row */
    private static function encodeSearchCursor(array $row, string $fingerprint): string
    {
        $id = $row['id'] ?? null;
        if (is_string($id) && ctype_digit($id)) {
            $id = (int)$id;
        }
        $dueDate = $row['due_date'] ?? null;
        if (!is_int($id) || $id < 1 || ($dueDate !== null && (!is_string($dueDate) || !self::isCalendarDate($dueDate)))) {
            throw new \UnexpectedValueException('SBAIO_TASKS_SEARCH_ROW_INVALID');
        }
        $json = json_encode([
            'v' => 1,
            'due_date' => $dueDate,
            'id' => $id,
            'fingerprint' => $fingerprint,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): int
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid task input.');
        }
        $values = $validated['values'];

        $db = DB::conn();
        $transactionStarted = false;
        try {
            if ($db->begin_transaction() !== true) {
                throw new \RuntimeException('Task transaction could not start.');
            }
            $transactionStarted = true;

            DB::query(
                'INSERT INTO sbaio_tasks (task_title, owner_name, task_type, task_bucket, related_staff_id, related_date, task_status, priority, due_date) VALUES (?,?,?,?,?,?,?,?,?)',
                [
                    $values['task_title'],
                    self::nullIfBlank($values['owner_name']),
                    self::nullIfBlank($values['task_type']),
                    self::nullIfBlank($values['task_bucket']),
                    $values['related_staff_id'],
                    self::nullIfBlank($values['related_date']),
                    $values['task_status'],
                    $values['priority'],
                    self::nullIfBlank($values['due_date']),
                ]
            );

            $insertId = $db->insert_id;
            if (is_int($insertId) && $insertId > 0) {
                $createdId = $insertId;
            } elseif (is_string($insertId) && preg_match('/^[1-9][0-9]*$/D', $insertId)) {
                $normalizedId = filter_var($insertId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($normalizedId !== false) {
                    $createdId = (int)$normalizedId;
                }
            }
            if (!isset($createdId)) {
                throw new \RuntimeException('Task insert identity was not available.');
            }

            if ($db->commit() !== true) {
                throw new \RuntimeException('Task transaction commit failed.');
            }

            return $createdId;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                try {
                    if ($db->rollback() !== true) {
                        throw new \RuntimeException('Task transaction rollback failed.');
                    }
                } catch (\Throwable $rollbackError) {
                    throw new \RuntimeException('Task transaction rollback failed.', 0, $e);
                }
            }
            throw $e;
        }
    }

    /** @param array<string,mixed> $input */
    private static function scalarString(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return '';
        }
        if (!is_string($input[$key])) {
            return null;
        }
        return $input[$key];
    }

    /** @param list<string> $errors */
    private static function validateOptionalText(?string $value, int $maxLength, string $field, array &$errors): string
    {
        if ($value === null) {
            $errors[] = $field . '_invalid';
            return '';
        }
        $value = trim($value);
        if (strlen($value) > $maxLength) {
            $errors[] = $field . '_too_long';
        }
        return $value;
    }

    /** @return int|null|false */
    private static function normalizeRelatedStaffId(mixed $value): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            if ($value === 0) {
                return null;
            }
            return $value > 0 && $value <= 2147483647 ? $value : false;
        }
        if (!is_string($value)) {
            return false;
        }
        $value = trim($value);
        if ($value === '' || !preg_match('/^\d+$/D', $value)) {
            return false;
        }
        $value = ltrim($value, '0');
        if ($value === '') {
            return null;
        }
        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 2147483647]]);
        return $normalized === false ? false : (int)$normalized;
    }

    /** @param list<string> $errors */
    private static function validateOptionalDate(?string $value, string $field, array &$errors): string
    {
        if ($value === null) {
            $errors[] = $field . '_invalid';
            return '';
        }
        $value = trim($value);
        if ($value !== '' && !self::isCalendarDate($value)) {
            $errors[] = $field . '_invalid';
        }
        return $value;
    }

    private static function isCalendarDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            return false;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ((int)($errors['warning_count'] ?? 0) === 0 && (int)($errors['error_count'] ?? 0) === 0))
            && $date->format('Y-m-d') === $value;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
