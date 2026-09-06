<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services;

use App\Core\DB;

/**
 * Reservations lifecycle (L2 with L3 status-transition semantics).
 * Hospitality-local only: guests stay hosp_guests, rooms stay hosp_rooms.
 *
 * Status machine (audit-friendly timestamps via existing lifecycle fields):
 *   booked -> checked_in   (actual_check_in_at set)
 *   checked_in -> checked_out (actual_check_out_at set)
 *   booked -> cancelled
 *   booked -> no_show
 */
final class ReservationsService
{
    public const STATUSES = ['booked', 'checked_in', 'checked_out', 'cancelled', 'no_show'];

    /** @var array<string, string> allowed from => to transitions */
    public const TRANSITIONS = [
        'booked' => ['checked_in', 'cancelled', 'no_show'],
        'checked_in' => ['checked_out'],
        'checked_out' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    public static function requireSchema(): void
    {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hosp_reservations'"
        );
        if ((int)($row['c'] ?? 0) === 0) {
            throw new \RuntimeException('HOSPITALITY_SCHEMA_PENDING');
        }
    }

    /**
     * Reservations joined with room/guest labels for display.
     * @return array<int,array<string,mixed>>
     */
    public static function listReservations(): array
    {
        self::requireSchema();
        return DB::fetchAll(
            'SELECT r.id, r.guest_id, r.room_id, r.check_in_date, r.check_out_date, r.adults, r.children,
                    r.rate, r.reservation_status, r.actual_check_in_at, r.actual_check_out_at, r.note,
                    g.full_name AS guest_label, rm.room_number AS room_label
             FROM hosp_reservations r
             LEFT JOIN hosp_guests g ON g.id = r.guest_id
             LEFT JOIN hosp_rooms rm ON rm.id = r.room_id
             ORDER BY r.check_in_date DESC, r.id DESC
             LIMIT 500'
        );
    }

    /**
     * Active guests and rooms for the create form.
     * @return array{guests:array<int,array<string,mixed>>,rooms:array<int,array<string,mixed>>}
     */
    public static function formOptions(): array
    {
        self::requireSchema();
        return [
            'guests' => DB::fetchAll("SELECT id, full_name FROM hosp_guests WHERE guest_status = 'active' ORDER BY full_name ASC LIMIT 500"),
            'rooms' => DB::fetchAll("SELECT id, room_number FROM hosp_rooms WHERE room_status = 'active' ORDER BY room_number ASC LIMIT 500"),
        ];
    }

    public static function create(array $post): int
    {
        self::requireSchema();

        $guestId = (int)($post['guest_id'] ?? 0);
        $roomId = (int)($post['room_id'] ?? 0);
        $checkIn = self::validateDate((string)($post['check_in_date'] ?? ''), 'HOSPITALITY_RES_CHECKIN_INVALID');
        $checkOut = self::validateDate((string)($post['check_out_date'] ?? ''), 'HOSPITALITY_RES_CHECKOUT_INVALID');
        if ($checkOut <= $checkIn) {
            throw new \RuntimeException('HOSPITALITY_RES_DATE_ORDER_INVALID');
        }

        if (!self::activeGuestExists($guestId)) {
            throw new \RuntimeException('HOSPITALITY_RES_GUEST_INACTIVE');
        }
        // room is optional at booking time (unassigned stays allowed by schema)
        $roomAssigned = false;
        if ($roomId > 0) {
            if (!self::activeRoomExists($roomId)) {
                throw new \RuntimeException('HOSPITALITY_RES_ROOM_INACTIVE');
            }
            self::assertNoOverlap($roomId, $checkIn, $checkOut);
            $roomAssigned = true;
        }

        $adults = self::validateCount((string)($post['adults'] ?? '1'));
        $children = self::validateCount((string)($post['children'] ?? '0'));
        $rate = self::validateRate((string)($post['rate'] ?? ''));
        $note = mb_substr(trim((string)($post['note'] ?? '')), 0, 1000);

        DB::query(
            'INSERT INTO hosp_reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, rate, reservation_status, note)
             VALUES (?,?,?,?,?,?,?,?,?)',
            [$guestId, $roomAssigned ? $roomId : null, $checkIn, $checkOut, $adults, $children, $rate, 'booked', $note]
        );
        $row = DB::fetchOne('SELECT id FROM hosp_reservations ORDER BY id DESC LIMIT 1');
        return (int)($row['id'] ?? 0);
    }

    /**
     * Apply an allowed status transition. checked_in/checked_out write the existing
     * actual_*_at lifecycle fields; cancelled/no_show change status only.
     */
    public static function transition(int $id, string $to): void
    {
        self::requireSchema();
        if (!in_array($to, self::STATUSES, true)) {
            throw new \RuntimeException('HOSPITALITY_RES_STATUS_UNKNOWN');
        }
        $row = DB::fetchOne('SELECT id, reservation_status, actual_check_in_at FROM hosp_reservations WHERE id=? LIMIT 1', [$id]);
        if (!is_array($row)) {
            throw new \RuntimeException('HOSPITALITY_RES_NOT_FOUND');
        }
        $from = (string)$row['reservation_status'];
        if ($from === $to || !in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \RuntimeException('HOSPITALITY_RES_TRANSITION_INVALID');
        }

        // Compare-and-set guard: the UPDATE only applies when the row still
        // carries the status observed during validation. A concurrent winner
        // changes the row, so this UPDATE affects zero rows and we surface the
        // canonical invalid-transition rejection instead of overwriting the
        // winner. Safe standalone and inside an outer transaction.
        $affected = 0;
        if ($to === 'checked_in') {
            $affected = DB::query(
                "UPDATE hosp_reservations SET reservation_status='checked_in', actual_check_in_at = NOW()
                 WHERE id=? AND reservation_status='booked' LIMIT 1",
                [$id]
            ) === true ? mysqli_affected_rows(DB::conn()) : 0;
            if ($affected === 0) { throw new \RuntimeException('HOSPITALITY_RES_TRANSITION_INVALID'); }
            return;
        }
        if ($to === 'checked_out') {
            if (empty($row['actual_check_in_at'])) {
                throw new \RuntimeException('HOSPITALITY_RES_TRANSITION_INVALID');
            }
            $affected = DB::query(
                "UPDATE hosp_reservations SET reservation_status='checked_out', actual_check_out_at = NOW()
                 WHERE id=? AND reservation_status='checked_in' LIMIT 1",
                [$id]
            ) === true ? mysqli_affected_rows(DB::conn()) : 0;
            if ($affected === 0) { throw new \RuntimeException('HOSPITALITY_RES_TRANSITION_INVALID'); }
            return;
        }
        // cancelled / no_show: audit trail stays in status + updated_at + note fields
        $affected = DB::query(
            'UPDATE hosp_reservations SET reservation_status=? WHERE id=? AND reservation_status=? LIMIT 1',
            [$to, $id, $from]
        ) === true ? mysqli_affected_rows(DB::conn()) : 0;
        if ($affected === 0) { throw new \RuntimeException('HOSPITALITY_RES_TRANSITION_INVALID'); }
    }

    private static function activeGuestExists(int $guestId): bool
    {
        $row = DB::fetchOne("SELECT id FROM hosp_guests WHERE id=? AND guest_status='active' LIMIT 1", [$guestId]);
        return is_array($row);
    }

    private static function activeRoomExists(int $roomId): bool
    {
        $row = DB::fetchOne("SELECT id FROM hosp_rooms WHERE id=? AND room_status='active' LIMIT 1", [$roomId]);
        return is_array($row);
    }

    /**
     * Obvious-conflict guard: same active room already held by a booked/checked_in
     * reservation whose date range overlaps the requested range.
     */
    private static function assertNoOverlap(int $roomId, string $checkIn, string $checkOut): void
    {
        $row = DB::fetchOne(
            "SELECT id FROM hosp_reservations
             WHERE room_id = ? AND reservation_status IN ('booked','checked_in')
               AND check_in_date < ? AND check_out_date > ?
             LIMIT 1",
            [$roomId, $checkOut, $checkIn]
        );
        if (is_array($row)) {
            throw new \RuntimeException('HOSPITALITY_RES_ROOM_CONFLICT');
        }
    }

    private static function validateDate(string $raw, string $errorCode): string
    {
        $raw = trim($raw);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
            throw new \RuntimeException($errorCode);
        }
        if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
            throw new \RuntimeException($errorCode);
        }
        return $raw;
    }

    private static function validateCount(string $raw): int
    {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        if (!preg_match('/^\d{1,2}$/', $raw)) {
            throw new \RuntimeException('HOSPITALITY_RES_COUNT_INVALID');
        }
        return (int)$raw;
    }

    private static function validateRate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $raw)) {
            throw new \RuntimeException('HOSPITALITY_RES_RATE_INVALID');
        }
        return $raw;
    }
}
