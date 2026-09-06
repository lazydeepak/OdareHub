<?php
declare(strict_types=1);

namespace Plugins\Notices\Services;

use App\Core\DB;

final class NoticesService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        return DB::fetchAll(
            'SELECT id, title, notice_type, target_period, notice_level, requires_ack, is_active, created_at FROM sbaio_notices ORDER BY id DESC LIMIT ' . (int)$limit
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
            'INSERT INTO sbaio_notices (title, body, notice_type, target_period, related_staff_id, requires_ack, notice_level, is_active) VALUES (?,?,?,?,?,?,?,?)',
            [
                trim((string)($input['title'] ?? '')),
                self::nullIfBlank((string)($input['body'] ?? '')),
                self::nullIfBlank((string)($input['notice_type'] ?? '')),
                self::nullIfBlank((string)($input['target_period'] ?? '')),
                $relatedStaffId > 0 ? $relatedStaffId : null,
                !empty($input['requires_ack']) ? 1 : 0,
                self::nullIfBlank((string)($input['notice_level'] ?? '')) ?? 'info',
                !empty($input['is_active']) ? 1 : 0,
            ]
        );
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
