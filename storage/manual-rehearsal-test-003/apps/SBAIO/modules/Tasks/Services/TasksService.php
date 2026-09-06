<?php
declare(strict_types=1);

namespace Plugins\Tasks\Services;

use App\Core\DB;

final class TasksService
{
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
        $relatedStaffId = (int)($input['related_staff_id'] ?? 0);

        DB::query(
            'INSERT INTO sbaio_tasks (task_title, owner_name, task_type, task_bucket, related_staff_id, related_date, task_status, priority, due_date) VALUES (?,?,?,?,?,?,?,?,?)',
            [
                trim((string)($input['task_title'] ?? '')),
                self::nullIfBlank((string)($input['owner_name'] ?? '')),
                self::nullIfBlank((string)($input['task_type'] ?? '')),
                self::nullIfBlank((string)($input['task_bucket'] ?? '')),
                $relatedStaffId > 0 ? $relatedStaffId : null,
                self::nullIfBlank((string)($input['related_date'] ?? '')),
                self::nullIfBlank((string)($input['task_status'] ?? '')) ?? 'open',
                self::nullIfBlank((string)($input['priority'] ?? '')) ?? 'normal',
                self::nullIfBlank((string)($input['due_date'] ?? '')),
            ]
        );
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
