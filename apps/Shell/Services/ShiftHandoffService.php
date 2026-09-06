<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

/**
 * ShiftHandoffService
 *
 * Manages operator shift handoff notes.
 * Operators write notes at end of shift; incoming operators read and acknowledge.
 *
 * Tables:
 *   operator_shift_handoff      — the notes
 *   operator_shift_handoff_ack  — read acknowledgements (user × note)
 */
final class ShiftHandoffService
{
    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Return notes for a given date (default: today), newest-first.
     *
     * @return array{
     *   notes: array<int,array<string,mixed>>,
     *   unacked_count: int,
     *   error: string
     * }
     */
    public static function getForDate(int $userId, string $date = ''): array
    {
        $result = ['notes' => [], 'unacked_count' => 0, 'error' => ''];

        if ($date === '') {
            $date = date('Y-m-d');
        }

        try {
            $rows = DB::fetchAll(
                "SELECT h.id, h.author_user_id, h.author_display,
                        h.note_text, h.shift_date, h.shift_label,
                        h.created_at,
                        a.acked_at
                   FROM operator_shift_handoff h
                   LEFT JOIN operator_shift_handoff_ack a
                          ON a.handoff_id = h.id AND a.user_id = ?
                  WHERE h.shift_date = ?
                  ORDER BY h.created_at DESC",
                [$userId, $date]
            );

            $unacked = 0;
            foreach ($rows as &$row) {
                $row['acked']      = ($row['acked_at'] !== null);
                $row['is_own']     = ((int)$row['author_user_id'] === $userId);
                $row['note_safe']  = nl2br(htmlspecialchars((string)$row['note_text']));
                if (!$row['acked'] && !$row['is_own']) {
                    $unacked++;
                }
            }
            unset($row);

            $result['notes']        = $rows;
            $result['unacked_count'] = $unacked;
        } catch (\Throwable $e) {
            $result['error'] = 'Unable to load handoff notes.';
        }

        return $result;
    }

    /**
     * Count unacknowledged notes for today for a user.
     * Used for sidebar badge. Returns 0 on DB error.
     */
    public static function countUnacked(int $userId): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS cnt
                   FROM operator_shift_handoff h
                   LEFT JOIN operator_shift_handoff_ack a
                          ON a.handoff_id = h.id AND a.user_id = ?
                  WHERE h.shift_date = CURDATE()
                    AND a.acked_at IS NULL
                    AND h.author_user_id <> ?",
                [$userId, $userId]
            );
            return (int)($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Create a new handoff note.
     *
     * @param array{
     *   author_user_id: int,
     *   author_display: string,
     *   note_text: string,
     *   shift_date?: string,
     *   shift_label?: string
     * } $data
     * @return array{ok: bool, id: int, error: string}
     */
    public static function createNote(array $data): array
    {
        $authorId      = (int)($data['author_user_id'] ?? 0);
        $authorDisplay = substr(trim((string)($data['author_display'] ?? '')), 0, 190);
        $noteText      = trim((string)($data['note_text'] ?? ''));
        $shiftDate     = trim((string)($data['shift_date'] ?? date('Y-m-d')));
        $shiftLabel    = substr(trim((string)($data['shift_label'] ?? '')), 0, 40);

        if ($authorId <= 0) {
            return ['ok' => false, 'id' => 0, 'error' => 'Invalid author.'];
        }
        if ($noteText === '') {
            return ['ok' => false, 'id' => 0, 'error' => 'Note cannot be empty.'];
        }
        if (strlen($noteText) > 5000) {
            return ['ok' => false, 'id' => 0, 'error' => 'Note too long (max 5000 characters).'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $shiftDate)) {
            $shiftDate = date('Y-m-d');
        }

        try {
            DB::execute(
                "INSERT INTO operator_shift_handoff
                   (author_user_id, author_display, note_text, shift_date, shift_label, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$authorId, $authorDisplay, $noteText, $shiftDate, $shiftLabel]
            );
            $id = (int)DB::lastInsertId();
            return ['ok' => true, 'id' => $id, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'id' => 0, 'error' => 'Failed to save note.'];
        }
    }

    /**
     * Acknowledge (mark read) a handoff note for a user.
     *
     * @return array{ok: bool, error: string}
     */
    public static function acknowledgeNote(int $handoffId, int $userId): array
    {
        if ($handoffId <= 0 || $userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid parameters.'];
        }
        try {
            // Verify the note exists and belongs to today (prevent mass ack of old notes)
            $row = DB::fetchOne(
                "SELECT id FROM operator_shift_handoff WHERE id = ? AND shift_date = CURDATE()",
                [$handoffId]
            );
            if ($row === null || $row === false) {
                return ['ok' => false, 'error' => 'Note not found or not from today.'];
            }

            DB::execute(
                "INSERT IGNORE INTO operator_shift_handoff_ack (handoff_id, user_id, acked_at)
                 VALUES (?, ?, NOW())",
                [$handoffId, $userId]
            );
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Failed to acknowledge note.'];
        }
    }

    /**
     * Acknowledge all unread notes for today for a user.
     *
     * @return array{ok: bool, error: string}
     */
    public static function acknowledgeAll(int $userId): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'error' => 'Invalid user.'];
        }
        try {
            DB::execute(
                "INSERT IGNORE INTO operator_shift_handoff_ack (handoff_id, user_id, acked_at)
                 SELECT h.id, ?, NOW()
                   FROM operator_shift_handoff h
                   LEFT JOIN operator_shift_handoff_ack a
                          ON a.handoff_id = h.id AND a.user_id = ?
                  WHERE h.shift_date = CURDATE()
                    AND a.acked_at IS NULL
                    AND h.author_user_id <> ?",
                [$userId, $userId, $userId]
            );
            return ['ok' => true, 'error' => ''];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Failed to acknowledge notes.'];
        }
    }
}
