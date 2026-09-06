<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services;

use App\Core\DB;

/**
 * Rooms CRUD (Level 2). Hospitality-local, schema-guarded, additive-safe.
 * Deactivation follows the repo safety convention of status transitions,
 * never hard deletes.
 */
final class RoomsService
{
    public const ROOM_TYPES = ['standard', 'single', 'double', 'suite', 'family'];
    public const STATUSES = ['active', 'inactive'];

    public static function requireSchema(): void
    {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hosp_rooms'"
        );
        if ((int)($row['c'] ?? 0) === 0) {
            throw new \RuntimeException('HOSPITALITY_SCHEMA_PENDING');
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listRooms(): array
    {
        self::requireSchema();
        return DB::fetchAll('SELECT id, room_number, room_type, floor, room_status, note, created_at, updated_at FROM hosp_rooms ORDER BY room_number ASC LIMIT 500');
    }

    public static function create(array $post): int
    {
        self::requireSchema();
        $number = self::validateNumber((string)($post['room_number'] ?? ''));
        self::assertNumberAvailable($number);
        $type = self::validateType((string)($post['room_type'] ?? 'standard'));
        $floor = self::validateFloor((string)($post['floor'] ?? ''));
        $note = self::validateNote((string)($post['note'] ?? ''));

        DB::query(
            'INSERT INTO hosp_rooms (room_number, room_type, floor, room_status, note) VALUES (?,?,?,?,?)',
            [$number, $type, $floor, 'active', $note]
        );
        $row = DB::fetchOne('SELECT id FROM hosp_rooms WHERE room_number = ? LIMIT 1', [$number]);
        return (int)($row['id'] ?? 0);
    }

    public static function update(int $id, array $post): void
    {
        self::requireSchema();
        if ($id <= 0 || !self::exists($id)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_NOT_FOUND');
        }
        $number = self::validateNumber((string)($post['room_number'] ?? ''));
        $existing = DB::fetchOne('SELECT id FROM hosp_rooms WHERE room_number = ? LIMIT 1', [$number]);
        if (is_array($existing) && (int)$existing['id'] !== $id) {
            throw new \RuntimeException('HOSPITALITY_ROOM_NUMBER_TAKEN');
        }
        $type = self::validateType((string)($post['room_type'] ?? 'standard'));
        $floor = self::validateFloor((string)($post['floor'] ?? ''));
        $note = self::validateNote((string)($post['note'] ?? ''));

        DB::query(
            'UPDATE hosp_rooms SET room_number=?, room_type=?, floor=?, note=? WHERE id=? LIMIT 1',
            [$number, $type, $floor, $note, $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        self::requireSchema();
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_STATUS_INVALID');
        }
        if ($id <= 0 || !self::exists($id)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_NOT_FOUND');
        }
        DB::query('UPDATE hosp_rooms SET room_status=? WHERE id=? LIMIT 1', [$status, $id]);
    }

    private static function exists(int $id): bool
    {
        $row = DB::fetchOne('SELECT id FROM hosp_rooms WHERE id=? LIMIT 1', [$id]);
        return is_array($row);
    }

    private static function assertNumberAvailable(string $number): void
    {
        $row = DB::fetchOne('SELECT id FROM hosp_rooms WHERE room_number=? LIMIT 1', [$number]);
        if (is_array($row)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_NUMBER_TAKEN');
        }
    }

    private static function validateNumber(string $raw): string
    {
        $number = trim($raw);
        if ($number === '' || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,39}$/', $number)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_NUMBER_INVALID');
        }
        return $number;
    }

    private static function validateType(string $raw): string
    {
        $type = strtolower(trim($raw));
        if (!in_array($type, self::ROOM_TYPES, true)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_TYPE_INVALID');
        }
        return $type;
    }

    private static function validateFloor(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^-?\d{1,3}$/', $raw)) {
            throw new \RuntimeException('HOSPITALITY_ROOM_FLOOR_INVALID');
        }
        return (int)$raw;
    }

    private static function validateNote(string $raw): string
    {
        return mb_substr(trim($raw), 0, 1000);
    }
}
