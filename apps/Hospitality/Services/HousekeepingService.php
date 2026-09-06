<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services;

use App\Core\DB;

/**
 * Housekeeping current-status board. One current row per active room; no
 * history/work-order workflow in v1.
 */
final class HousekeepingService
{
    public const STATUSES = ['clean', 'dirty', 'inspected', 'maintenance', 'out_of_service'];

    public static function requireSchema(): void
    {
        foreach (['hosp_rooms', 'hosp_housekeeping_status'] as $table) {
            $row = DB::fetchOne(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
                [$table]
            );
            if ((int)($row['c'] ?? 0) === 0) {
                throw new \RuntimeException('HOSPITALITY_SCHEMA_PENDING');
            }
        }
    }

    /**
     * Current status for every active room. Missing status rows are shown as
     * pending until the first update lazily creates the row.
     *
     * @return array<int,array<string,mixed>>|null
     */
    public static function board(): ?array
    {
        try {
            self::requireSchema();
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'HOSPITALITY_SCHEMA_PENDING') {
                return null;
            }
            throw $e;
        }

        return DB::fetchAll(
            "SELECT r.id AS room_id, r.room_number, r.room_type, r.floor,
                    hk.id AS housekeeping_id, hk.hk_status, hk.last_cleaned_at,
                    hk.assigned_to, hk.note, hk.updated_at
             FROM hosp_rooms r
             LEFT JOIN hosp_housekeeping_status hk ON hk.room_id = r.id
             WHERE r.room_status = 'active'
             ORDER BY r.room_number ASC
             LIMIT 500"
        );
    }

    public static function updateStatus(int $roomId, array $post): void
    {
        self::requireSchema();
        if ($roomId <= 0 || !self::activeRoomExists($roomId)) {
            throw new \RuntimeException('HOSPITALITY_HK_ROOM_NOT_FOUND');
        }

        $status = strtolower(trim((string)($post['hk_status'] ?? '')));
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('HOSPITALITY_HK_STATUS_INVALID');
        }
        $assignedTo = self::validateAssignedTo((string)($post['assigned_to'] ?? ''));
        $note = self::validateNote((string)($post['note'] ?? ''));

        $existing = DB::fetchOne('SELECT id FROM hosp_housekeeping_status WHERE room_id=? LIMIT 1', [$roomId]);
        $cleanedAtSql = in_array($status, ['clean', 'inspected'], true) ? 'NOW()' : 'last_cleaned_at';

        if (is_array($existing)) {
            DB::query(
                "UPDATE hosp_housekeeping_status
                 SET hk_status=?, assigned_to=?, note=?, last_cleaned_at={$cleanedAtSql}
                 WHERE room_id=? LIMIT 1",
                [$status, $assignedTo, $note, $roomId]
            );
            return;
        }

        DB::query(
            "INSERT INTO hosp_housekeeping_status (room_id, hk_status, assigned_to, note, last_cleaned_at)
             VALUES (?,?,?,?, " . (in_array($status, ['clean', 'inspected'], true) ? 'NOW()' : 'NULL') . ')',
            [$roomId, $status, $assignedTo, $note]
        );
    }

    private static function activeRoomExists(int $roomId): bool
    {
        $row = DB::fetchOne("SELECT id FROM hosp_rooms WHERE id=? AND room_status='active' LIMIT 1", [$roomId]);
        return is_array($row);
    }

    private static function validateAssignedTo(string $raw): string
    {
        $value = trim($raw);
        if (mb_strlen($value) > 190) {
            throw new \RuntimeException('HOSPITALITY_HK_ASSIGNED_INVALID');
        }
        return $value;
    }

    private static function validateNote(string $raw): string
    {
        return mb_substr(trim($raw), 0, 1000);
    }
}
