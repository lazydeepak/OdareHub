<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services;

use App\Core\DB;
use Throwable;

/**
 * FrontDesk workboard (L3) plus the v1 LOCAL folio/charges records.
 *
 * Extraction-safety boundaries (per docs/architecture/shared-app-extension-readiness.md):
 * - folio lives only in hosp_folios / hosp_folio_charges (separate, cleanly keyed tables)
 * - no payments, invoices, tax, accounting, export, or posting of any kind
 * - totals are computed on read; nothing denormalized
 * - charge categories stay local/simple: room_rate | fnb | misc
 * - lifecycle rules are NOT duplicated: check-in/out delegate to ReservationsService
 */
final class FrontDeskService
{
    public const CHARGE_TYPES = ['room_rate', 'fnb', 'misc'];

    public static function requireSchema(): void
    {
        foreach (['hosp_reservations', 'hosp_folios', 'hosp_folio_charges'] as $table) {
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
     * Workboard: arrivals (booked, today or upcoming) and in-house stays.
     * @return array{arrivals:array<int,array<string,mixed>>,inhouse:array<int,array<string,mixed>>>}|null
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

        return [
            'arrivals' => DB::fetchAll(
                "SELECT r.id, r.check_in_date, r.check_out_date, r.adults, r.children, r.reservation_status,
                        g.full_name AS guest_label, rm.room_number AS room_label
                 FROM hosp_reservations r
                 LEFT JOIN hosp_guests g ON g.id = r.guest_id
                 LEFT JOIN hosp_rooms rm ON rm.id = r.room_id
                 WHERE r.reservation_status = 'booked' AND r.check_in_date >= CURDATE()
                 ORDER BY r.check_in_date ASC, r.id ASC
                 LIMIT 200"
            ),
            'inhouse' => DB::fetchAll(
                "SELECT r.id, r.room_id, r.actual_check_in_at,
                        g.full_name AS guest_label, rm.room_number AS room_label
                 FROM hosp_reservations r
                 LEFT JOIN hosp_guests g ON g.id = r.guest_id
                 LEFT JOIN hosp_rooms rm ON rm.id = r.room_id
                 WHERE r.reservation_status = 'checked_in'
                 ORDER BY r.actual_check_in_at ASC, r.id ASC
                 LIMIT 200"
            ),
        ];
    }

    /**
     * Folio summary for in-house rows: folio row, charges, computed total.
     * @return array<int,array<string,mixed>>
     */
    public static function folioSummaries(): array
    {
        self::requireSchema();
        $map = [];
        $folios = DB::fetchAll(
            'SELECT f.*, r.id AS reservation_id
             FROM hosp_folios f
             INNER JOIN hosp_reservations r ON r.id = f.reservation_id
             WHERE r.reservation_status = \'checked_in\'
             ORDER BY f.id ASC'
        );
        foreach ($folios as $folio) {
            $map[(int)$folio['reservation_id']] = [
                'folio' => $folio,
                'charges' => self::listCharges((int)$folio['id']),
                'total' => self::openTotal((int)$folio['id']),
            ];
        }
        return $map;
    }

    /**
     * Lifecycle delegation: the reservation status machine stays owned by
     * ReservationsService. On successful checkout the local folio is closed.
     */
    public static function checkIn(int $reservationId): void
    {
        ReservationsService::transition($reservationId, 'checked_in');
    }

    /** Canonical operator-facing cancellation entry. Delegates only to the lifecycle truth. */
    /** Canonical operator-facing no-show entry. Delegates only to lifecycle truth. */
    public static function markReservationNoShow(int $reservationId): void
    {
        ReservationsService::transition($reservationId, 'no_show');
    }

    public static function cancelReservation(int $reservationId): void
    {
        ReservationsService::transition($reservationId, 'cancelled');
    }

    public static function checkout(int $reservationId): void
    {
        self::requireSchema();

        $db = DB::conn();
        $db->begin_transaction();
        try {
            // Serialize the reservation row: two simultaneous checkouts cannot
            // both pass through the canonical transition, and the lifecycle
            // state machine below remains the single validation truth.
            $lock = $db->prepare('SELECT id FROM hosp_reservations WHERE id=? FOR UPDATE');
            $lock->bind_param('i', $reservationId);
            $lock->execute();
            $exists = $lock->get_result()->fetch_assoc();
            $lock->close();
            if (!is_array($exists)) {
                $db->rollback();
                throw new \RuntimeException('HOSPITALITY_RES_NOT_FOUND');
            }

            ReservationsService::transition($reservationId, 'checked_out');

            DB::query(
                "UPDATE hosp_folios SET folio_status='closed', closed_at = NOW()
                 WHERE reservation_id=? AND folio_status='open'",
                [$reservationId]
            );

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    /**
     * Idempotently ensure a local folio exists for the stay. Returns folio id.
     */
    public static function ensureFolio(int $reservationId): int
    {
        self::requireSchema();
        $exists = DB::fetchOne('SELECT id FROM hosp_reservations WHERE id=? LIMIT 1', [$reservationId]);
        if (!is_array($exists)) {
            throw new \RuntimeException('HOSPITALITY_RES_NOT_FOUND');
        }
        $row = DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$reservationId]);
        if (is_array($row)) {
            return (int)$row['id'];
        }
        DB::query("INSERT INTO hosp_folios (reservation_id, folio_status, opened_at) VALUES (?,'open',NOW())", [$reservationId]);
        $created = DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$reservationId]);
        return (int)($created['id'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listCharges(int $folioId): array
    {
        return DB::fetchAll(
            'SELECT id, charge_type, description, qty, unit_amount, charge_status, posted_at
             FROM hosp_folio_charges WHERE folio_id=? ORDER BY id ASC',
            [$folioId]
        );
    }

    /** Computed on read: posted charges only. */
    public static function openTotal(int $folioId): float
    {
        $row = DB::fetchOne(
            "SELECT COALESCE(SUM(qty * unit_amount), 0) AS total
             FROM hosp_folio_charges WHERE folio_id=? AND charge_status='posted'",
            [$folioId]
        );
        return round((float)($row['total'] ?? 0), 2);
    }

    /**
     * Canonical reservation-scoped charge command.
     *
     * Enforces reservation existence + checked_in state server-side (closing the
     * crafted-POST gap), serializes first-folio creation on the reservation row,
     * and makes folio creation + charge insertion ATOMIC: an invalid first charge
     * against a folio-less stay leaves no folio residue. Charge-field validation
     * remains solely in addCharge().
     *
     * @return int inserted charge id
     */
    public static function addChargeForReservation(int $reservationId, array $post): int
    {
        self::requireSchema();

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $lock = $db->prepare('SELECT id, reservation_status FROM hosp_reservations WHERE id=? FOR UPDATE');
            $lock->bind_param('i', $reservationId);
            $lock->execute();
            $reservation = $lock->get_result()->fetch_assoc();
            $lock->close();

            if (!is_array($reservation)) {
                $db->rollback();
                throw new \RuntimeException('HOSPITALITY_RES_NOT_FOUND');
            }
            if ((string)($reservation['reservation_status'] ?? '') !== 'checked_in') {
                $db->rollback();
                throw new \RuntimeException('HOSPITALITY_RES_NOT_CHECKED_IN');
            }

            $folioRow = DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$reservationId]);
            $folioId = is_array($folioRow) ? (int)$folioRow['id'] : 0;
            if ($folioId === 0) {
                DB::query("INSERT INTO hosp_folios (reservation_id, folio_status, opened_at) VALUES (?,'open',NOW())", [$reservationId]);
                $folioId = (int)(DB::fetchOne('SELECT id FROM hosp_folios WHERE reservation_id=? LIMIT 1', [$reservationId])['id'] ?? 0);
            }

            $chargeId = self::addCharge($folioId, $post);

            $db->commit();
            return $chargeId;
        } catch (\Throwable $e) {
            try { $db->rollback(); } catch (\Throwable $ignored) {}
            throw $e;
        }
    }

    public static function addCharge(int $folioId, array $post): int
    {
        self::requireSchema();
        $folio = DB::fetchOne('SELECT id, folio_status FROM hosp_folios WHERE id=? LIMIT 1', [$folioId]);
        if (!is_array($folio)) {
            throw new \RuntimeException('HOSPITALITY_FOLIO_NOT_FOUND');
        }
        if (($folio['folio_status'] ?? '') !== 'open') {
            throw new \RuntimeException('HOSPITALITY_FOLIO_CLOSED');
        }

        $type = strtolower(trim((string)($post['charge_type'] ?? '')));
        if (!in_array($type, self::CHARGE_TYPES, true)) {
            throw new \RuntimeException('HOSPITALITY_CHARGE_TYPE_INVALID');
        }
        $description = trim((string)($post['description'] ?? ''));
        if ($description === '' || mb_strlen($description) > 255) {
            throw new \RuntimeException('HOSPITALITY_CHARGE_DESC_INVALID');
        }
        $qty = trim((string)($post['qty'] ?? '1'));
        if (!preg_match('/^\d{1,4}$/', $qty) || (int)$qty < 1) {
            throw new \RuntimeException('HOSPITALITY_CHARGE_QTY_INVALID');
        }
        $amount = trim((string)($post['unit_amount'] ?? ''));
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $amount)) {
            throw new \RuntimeException('HOSPITALITY_CHARGE_AMOUNT_INVALID');
        }

        DB::query(
            "INSERT INTO hosp_folio_charges (folio_id, charge_type, description, qty, unit_amount, charge_status, posted_at)
             VALUES (?,?,?,?,?,'posted',NOW())",
            [$folioId, $type, $description, (int)$qty, $amount]
        );
        $row = DB::fetchOne('SELECT id FROM hosp_folio_charges ORDER BY id DESC LIMIT 1');
        return (int)($row['id'] ?? 0);
    }

    /**
     * Void is allowed only while the stay is checked_in, the folio is open and
     * the charge was posted. Atomic + concurrency-safe: resolves the charge's
     * reservation identity, serializes on the reservation row (same lock order
     * as check-in/checkout/add-charge), re-reads canonical state under the
     * lock, then flips exactly one posted charge to voided. Immutable business
     * facts (type/description/qty/amount/posted_at) are never rewritten.
     */
    public static function voidCharge(int $chargeId): void
    {
        self::requireSchema();

        $db = DB::conn();
        $db->begin_transaction();
        try {
            // Resolve the charge's reservation identity (plain read; no locks yet).
            $resolve = DB::fetchOne(
                'SELECT c.id AS charge_id, f.reservation_id AS reservation_id
                 FROM hosp_folio_charges c INNER JOIN hosp_folios f ON f.id = c.folio_id
                 WHERE c.id = ? LIMIT 1',
                [$chargeId]
            );
            if (!is_array($resolve)) {
                throw new \RuntimeException('HOSPITALITY_CHARGE_NOT_FOUND');
            }
            $reservationId = (int)$resolve['reservation_id'];

            // Serialize on the reservation row (canonical Front Desk lock order).
            $lock = $db->prepare('SELECT id FROM hosp_reservations WHERE id=? FOR UPDATE');
            $lock->bind_param('i', $reservationId);
            $lock->execute();
            $lock->get_result()->fetch_assoc();
            $lock->close();

            // Re-read full canonical state AFTER obtaining serialization.
            $state = DB::fetchOne(
                'SELECT c.charge_status AS charge_status, f.folio_status AS folio_status,
                        r.reservation_status AS reservation_status
                 FROM hosp_folio_charges c
                 INNER JOIN hosp_folios f ON f.id = c.folio_id
                 INNER JOIN hosp_reservations r ON r.id = f.reservation_id
                 WHERE c.id = ? LIMIT 1',
                [$chargeId]
            );
            if (!is_array($state)) {
                throw new \RuntimeException('HOSPITALITY_CHARGE_NOT_FOUND');
            }

            if ((string)$state['reservation_status'] !== 'checked_in') {
                throw new \RuntimeException('HOSPITALITY_RES_NOT_CHECKED_IN');
            }
            if ((string)$state['folio_status'] !== 'open') {
                throw new \RuntimeException('HOSPITALITY_FOLIO_CLOSED');
            }
            if ((string)$state['charge_status'] !== 'posted') {
                throw new \RuntimeException('HOSPITALITY_CHARGE_NOT_POSTED');
            }

            DB::query("UPDATE hosp_folio_charges SET charge_status='voided' WHERE id=? LIMIT 1", [$chargeId]);

            $db->commit();
        } catch (\Throwable $e) {
            try { $db->rollback(); } catch (\Throwable $ignored) {}
            throw $e;
        }
    }
}
