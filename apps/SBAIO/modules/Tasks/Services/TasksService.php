<?php
declare(strict_types=1);

namespace Plugins\Tasks\Services;

use App\Core\DB;

final class TasksService
{
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
     * @return array<int,array<string,mixed>>
     */
    public static function activeStaff(): array
    {
        return DB::fetchAll(
            "SELECT id, full_name, employee_code FROM sbaio_staff WHERE employment_status='active' OR employment_status IS NULL ORDER BY full_name ASC"
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid task input.');
        }
        $values = $validated['values'];

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
