<?php
declare(strict_types=1);

namespace Apps\Hospitality\Services;

use App\Core\DB;

/**
 * Guests CRUD (Level 2). Hospitality-local records; a local stand-in until a shared
 * Parties app is explicitly approved. Deactivation via guest_status, never hard deletes.
 */
final class GuestsService
{
    public const STATUSES = ['active', 'inactive'];

    public static function requireSchema(): void
    {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'hosp_guests'"
        );
        if ((int)($row['c'] ?? 0) === 0) {
            throw new \RuntimeException('HOSPITALITY_SCHEMA_PENDING');
        }
        $col = DB::fetchOne(
            "SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'hosp_guests' AND column_name = 'guest_status'"
        );
        if ((int)($col['c'] ?? 0) === 0) {
            throw new \RuntimeException('HOSPITALITY_SCHEMA_PENDING');
        }
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function listGuests(): array
    {
        self::requireSchema();
        return DB::fetchAll('SELECT id, full_name, email, phone, id_document_ref, guest_status, note, created_at, updated_at FROM hosp_guests ORDER BY full_name ASC LIMIT 500');
    }

    public static function create(array $post): int
    {
        self::requireSchema();
        [$name, $email, $phone, $docRef, $note] = self::validatedFields($post);

        DB::query(
            'INSERT INTO hosp_guests (full_name, email, phone, id_document_ref, note, guest_status) VALUES (?,?,?,?,?,?)',
            [$name, $email, $phone, $docRef, $note, 'active']
        );
        $row = DB::fetchOne('SELECT id FROM hosp_guests ORDER BY id DESC LIMIT 1');
        return (int)($row['id'] ?? 0);
    }

    public static function update(int $id, array $post): void
    {
        self::requireSchema();
        if ($id <= 0 || !self::exists($id)) {
            throw new \RuntimeException('HOSPITALITY_GUEST_NOT_FOUND');
        }
        [$name, $email, $phone, $docRef, $note] = self::validatedFields($post);

        DB::query(
            'UPDATE hosp_guests SET full_name=?, email=?, phone=?, id_document_ref=?, note=? WHERE id=? LIMIT 1',
            [$name, $email, $phone, $docRef, $note, $id]
        );
    }

    public static function setStatus(int $id, string $status): void
    {
        self::requireSchema();
        if (!in_array($status, self::STATUSES, true)) {
            throw new \RuntimeException('HOSPITALITY_GUEST_STATUS_INVALID');
        }
        if ($id <= 0 || !self::exists($id)) {
            throw new \RuntimeException('HOSPITALITY_GUEST_NOT_FOUND');
        }
        DB::query('UPDATE hosp_guests SET guest_status=? WHERE id=? LIMIT 1', [$status, $id]);
    }

    private static function exists(int $id): bool
    {
        $row = DB::fetchOne('SELECT id FROM hosp_guests WHERE id=? LIMIT 1', [$id]);
        return is_array($row);
    }

    /**
     * @return array{0:string,1:?string,2:?string,3:?string,4:string}
     */
    private static function validatedFields(array $post): array
    {
        $name = trim((string)($post['full_name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 190) {
            throw new \RuntimeException('HOSPITALITY_GUEST_NAME_INVALID');
        }

        $email = trim((string)($post['email'] ?? ''));
        if ($email !== '') {
            if (mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \RuntimeException('HOSPITALITY_GUEST_EMAIL_INVALID');
            }
        } else {
            $email = null;
        }

        $phone = trim((string)($post['phone'] ?? ''));
        if ($phone !== '' && mb_strlen($phone) > 80) {
            throw new \RuntimeException('HOSPITALITY_GUEST_PHONE_INVALID');
        }
        if ($phone === '') {
            $phone = null;
        }

        $docRef = trim((string)($post['id_document_ref'] ?? ''));
        if ($docRef !== '' && mb_strlen($docRef) > 190) {
            throw new \RuntimeException('HOSPITALITY_GUEST_DOC_INVALID');
        }
        if ($docRef === '') {
            $docRef = null;
        }

        $note = mb_substr(trim((string)($post['note'] ?? '')), 0, 1000);

        return [$name, $email, $phone, $docRef, $note];
    }
}
