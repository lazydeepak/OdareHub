<?php
declare(strict_types=1);

namespace Apps\Parties\Services;

use App\Core\DB;

/**
 * Parties — canonical data owner service (Slice 4 skeleton).
 * DB-wide identity: display_name (label), email/phone (contact attributes, non-unique, non-identity),
 * party_type nullable person|organization (uncertainty-safe).
 *
 * No findOrCreate() — NO_AUTOMATIC_DEDUPE per Slice 3. Caller must explicitly choose reuse.
 */
final class PartyService
{
    /**
     * Create a Party explicitly. Never searches-and-reuses automatically.
     * @param array{party_type?:string|null,display_name?:string,email?:string|null,phone?:string|null} $input
     * @return int inserted id
     */
    public static function create(array $input): int
    {
        $displayName = trim((string)($input['display_name'] ?? ''));
        if ($displayName === '') {
            throw new \InvalidArgumentException('display_name is required');
        }
        $partyType = self::normalizePartyType($input['party_type'] ?? null);
        $email = self::nullIfBlank((string)($input['email'] ?? ''));
        $phone = self::nullIfBlank((string)($input['phone'] ?? ''));

        // Normalize email/phone blank to NULL per existing service conventions (CustomersService::nullIfBlank)
        DB::query(
            'INSERT INTO parties (party_type, display_name, email, phone) VALUES (?,?,?,?)',
            [$partyType, $displayName, $email, $phone]
        );
        $id = (int)(DB::conn()->insert_id);
        if ($id <= 0) {
            $row = DB::fetchOne('SELECT LAST_INSERT_ID() AS id');
            $id = (int)($row['id'] ?? 0);
        }
        return $id;
    }

    /**
     * Canonical ID lookup.
     * @return array<string,mixed>|null
     */
    public static function getById(int $id): ?array
    {
        if ($id <= 0) return null;
        return DB::fetchOne('SELECT id, party_type, display_name, email, phone, created_at, updated_at FROM parties WHERE id = ? LIMIT 1', [$id]);
    }

    /**
     * Read-only search to help future consumers detect possible reuse.
     * Exact/partial normalized matching, but never auto-merges or claims identity.
     * @return array<int,array<string,mixed>>
     */
    public static function searchCandidates(array $criteria, int $limit = 25): array
    {
        $limit = max(1, min(50, $limit));
        $displayName = trim((string)($criteria['display_name'] ?? ''));
        $email = self::nullIfBlank((string)($criteria['email'] ?? ''));
        $phone = self::nullIfBlank((string)($criteria['phone'] ?? ''));

        $where = [];
        $params = [];
        if ($displayName !== '') {
            $where[] = 'display_name LIKE ?';
            $params[] = '%' . $displayName . '%';
        }
        if ($email !== null) {
            $where[] = 'email = ?';
            $params[] = $email;
        }
        if ($phone !== null) {
            $where[] = 'phone = ?';
            $params[] = $phone;
        }
        if (!$where) {
            return DB::fetchAll('SELECT id, party_type, display_name, email, phone FROM parties ORDER BY updated_at DESC, id DESC LIMIT ' . (int)$limit);
        }
        $sql = 'SELECT id, party_type, display_name, email, phone FROM parties WHERE ' . implode(' OR ', $where) . ' ORDER BY updated_at DESC, id DESC LIMIT ' . (int)$limit;
        return DB::fetchAll($sql, $params);
    }

    /**
     * Update only canonical fields. No role data, no domain table writes.
     * @param array{party_type?:string|null,display_name?:string,email?:string|null,phone?:string|null} $input
     */
    public static function updateCanonical(int $id, array $input): void
    {
        if ($id <= 0) throw new \InvalidArgumentException('id required');
        $fields = [];
        $params = [];
        if (array_key_exists('display_name', $input)) {
            $v = trim((string)$input['display_name']);
            if ($v === '') throw new \InvalidArgumentException('display_name cannot be blank');
            $fields[] = 'display_name = ?';
            $params[] = $v;
        }
        if (array_key_exists('party_type', $input)) {
            $fields[] = 'party_type = ?';
            $params[] = self::normalizePartyType($input['party_type']);
        }
        if (array_key_exists('email', $input)) {
            $fields[] = 'email = ?';
            $params[] = self::nullIfBlank((string)$input['email']);
        }
        if (array_key_exists('phone', $input)) {
            $fields[] = 'phone = ?';
            $params[] = self::nullIfBlank((string)$input['phone']);
        }
        if (!$fields) return;
        $params[] = $id;
        DB::query('UPDATE parties SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ? LIMIT 1', $params);
    }

    private static function normalizePartyType(mixed $value): ?string
    {
        if ($value === null) return null;
        $v = strtolower(trim((string)$value));
        if ($v === '') return null;
        if (!in_array($v, ['person', 'organization'], true)) {
            throw new \InvalidArgumentException('party_type must be person, organization, or null');
        }
        return $v;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
