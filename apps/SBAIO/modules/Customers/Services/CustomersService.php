<?php
declare(strict_types=1);

namespace Plugins\Customers\Services;

use App\Core\DB;

final class CustomersService
{
    private const MAX_LIST_LIMIT = 25;
    private const MAX_SEARCH_LIMIT = 50;

    /**
     * Validate and normalize the existing customer create payload without
     * touching persistence.
     *
     * @param array<string,mixed> $input
     * @return array{valid:bool,errors:list<string>,values:array<string,string>}
     */
    public static function validateCreateInput(array $input): array
    {
        $inputFields = ['customer_name', 'contact_name', 'email', 'phone', 'status'];
        $typeErrors = [];
        foreach ($inputFields as $field) {
            if (array_key_exists($field, $input) && $input[$field] !== null && !is_string($input[$field])) {
                $typeErrors[] = 'input_type_invalid';
            }
        }
        if ($typeErrors !== []) {
            return [
                'valid' => false,
                'errors' => array_values(array_unique($typeErrors)),
                'values' => [
                    'customer_name' => '',
                    'contact_name' => '',
                    'email' => '',
                    'phone' => '',
                    'status' => '',
                ],
            ];
        }

        $values = [
            'customer_name' => trim($input['customer_name'] ?? ''),
            'contact_name' => trim($input['contact_name'] ?? ''),
            'email' => trim($input['email'] ?? ''),
            'phone' => trim($input['phone'] ?? ''),
            'status' => trim($input['status'] ?? ''),
        ];
        $errors = [];

        if ($values['customer_name'] === '') {
            $errors[] = 'required';
        } elseif (strlen($values['customer_name']) > 190) {
            $errors[] = 'customer_name_too_long';
        }
        if (strlen($values['contact_name']) > 190) {
            $errors[] = 'contact_name_too_long';
        }
        if ($values['email'] !== '' && (strlen($values['email']) > 190 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false)) {
            $errors[] = 'email_invalid';
        }
        if (strlen($values['phone']) > 60) {
            $errors[] = 'phone_too_long';
        }

        if ($values['status'] === '') {
            $values['status'] = 'active';
        } elseif (strlen($values['status']) > 40) {
            $errors[] = 'status_too_long';
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'values' => $values,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        $limit = min(max($limit, 1), self::MAX_LIST_LIMIT);
        return DB::fetchAll(
            'SELECT id, customer_name, contact_name, email, status FROM sbaio_customers ORDER BY id DESC LIMIT ' . $limit
        );
    }

    /**
     * Build the existing Customers overview report payload without changing
     * its predicates, aggregation, ordering, limits, or default-on-error
     * behavior.
     *
     * @return array{summary:array<string,mixed>,byStatus:array<int,array<string,mixed>>,recent:array<int,array<string,mixed>>}
     */
    public static function reportData(): array
    {
        $summary = ['total'=>0,'active'=>0,'new_30d'=>0,'with_email'=>0,'with_phone'=>0];
        $byStatus = [];
        $recent = [];

        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,''))='active' THEN 1 ELSE 0 END),0) AS active,
                        COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END),0) AS new_30d,
                        COALESCE(SUM(CASE WHEN COALESCE(email,'') <> '' THEN 1 ELSE 0 END),0) AS with_email,
                        COALESCE(SUM(CASE WHEN COALESCE(phone,'') <> '' THEN 1 ELSE 0 END),0) AS with_phone
                 FROM sbaio_customers"
            );
            if (is_array($row)) { $summary = array_merge($summary, $row); }

            $byStatus = DB::fetchAll(
                "SELECT COALESCE(NULLIF(status,''),'(none)') AS status, COUNT(*) AS n
                 FROM sbaio_customers GROUP BY status ORDER BY n DESC"
            ) ?: [];

            $recent = DB::fetchAll(
                "SELECT id, customer_name, contact_name, email, phone, status, created_at
                 FROM sbaio_customers ORDER BY created_at DESC, id DESC LIMIT 25"
            ) ?: [];
        } catch (\Throwable) {
            // Preserve the report view's existing fail-closed/default payload.
        }

        return compact('summary', 'byStatus', 'recent');
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): int
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid customer input.');
        }
        $values = $validated['values'];

        $db = DB::conn();
        $transactionStarted = false;
        try {
            if ($db->begin_transaction() !== true) {
                throw new \RuntimeException('Customer transaction could not start.');
            }
            $transactionStarted = true;

            DB::query(
                'INSERT INTO sbaio_customers (customer_name, contact_name, email, phone, status) VALUES (?,?,?,?,?)',
                [
                    $values['customer_name'],
                    self::nullIfBlank($values['contact_name']),
                    self::nullIfBlank($values['email']),
                    self::nullIfBlank($values['phone']),
                    $values['status'],
                ]
            );

            $insertId = $db->insert_id;
            if (is_int($insertId) && $insertId > 0) {
                $createdId = $insertId;
            } elseif (is_string($insertId) && preg_match('/^[1-9][0-9]*$/D', $insertId)) {
                $normalizedId = filter_var($insertId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if ($normalizedId !== false) {
                    $createdId = (int)$normalizedId;
                }
            }
            if (!isset($createdId)) {
                throw new \RuntimeException('Customer insert identity was not available.');
            }

            if ($db->commit() !== true) {
                throw new \RuntimeException('Customer transaction commit failed.');
            }

            return $createdId;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                try {
                    if ($db->rollback() !== true) {
                        throw new \RuntimeException('Customer transaction rollback failed.');
                    }
                } catch (\Throwable $rollbackError) {
                    throw new \RuntimeException('Customer transaction rollback failed.', 0, $e);
                }
            }
            throw $e;
        }
    }

    /**
     * Read one Customer record by its database-generated identity.
     *
     * @param mixed $id
     * @return array<string,mixed>|null
     */
    public static function findById(mixed $id): ?array
    {
        if (!is_int($id) && !is_string($id)) {
            throw new \InvalidArgumentException('Invalid Customer record ID.');
        }
        if (is_int($id)) {
            $recordId = $id;
        } elseif (preg_match('/^[1-9][0-9]*$/D', $id)) {
            $recordId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($recordId === false) {
                throw new \InvalidArgumentException('Invalid Customer record ID.');
            }
        } else {
            throw new \InvalidArgumentException('Invalid Customer record ID.');
        }
        if ($recordId <= 0) {
            throw new \InvalidArgumentException('Invalid Customer record ID.');
        }

        return DB::fetchOne(
            'SELECT id, customer_name, contact_name, email, phone, status FROM sbaio_customers WHERE id = ? LIMIT 1',
            [(int)$recordId]
        );
    }

    /**
     * Search historical Customers using exact scalar filters only. An empty
     * filter set is rejected so this path cannot become unrestricted browsing.
     * The read is bounded and uses forward keyset pagination.
     *
     * @param array<string,mixed> $filters
     * @return array{valid:bool,available:bool,rows:array<int,array<string,mixed>>,truncated:bool,limit:int,filters:array{customer_name:string,email:string,phone:string,status:string,from:string,to:string},next_cursor:?string}
     */
    public static function search(array $filters, int $limit = 50, ?string $cursor = null): array
    {
        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $normalized = self::normalizeSearchFilters($filters);
        $fingerprint = self::searchFilterFingerprint($normalized);
        $cursorData = self::decodeSearchCursor($cursor, $fingerprint);
        $where = [];
        $params = [];

        if ($normalized['customer_name'] !== '') {
            $where[] = 'customer_name = ?';
            $params[] = $normalized['customer_name'];
        }
        if ($normalized['email'] !== '') {
            $where[] = 'email = ?';
            $params[] = $normalized['email'];
        }
        if ($normalized['phone'] !== '') {
            $where[] = 'phone = ?';
            $params[] = $normalized['phone'];
        }
        if ($normalized['status'] !== '') {
            $where[] = 'status = ?';
            $params[] = $normalized['status'];
        }
        if ($normalized['from'] !== '') {
            $where[] = 'created_at >= ?';
            $params[] = $normalized['from'] . ' 00:00:00';
        }
        if ($normalized['to'] !== '') {
            $where[] = 'created_at < DATE_ADD(?, INTERVAL 1 DAY)';
            $params[] = $normalized['to'] . ' 00:00:00';
        }
        if ($cursorData !== null) {
            if ($cursorData['created_at'] === null) {
                $where[] = '(created_at IS NULL AND id < ?)';
                $params[] = $cursorData['id'];
            } else {
                $where[] = '(created_at < ? OR created_at IS NULL OR (created_at = ? AND id < ?))';
                $params[] = $cursorData['created_at'];
                $params[] = $cursorData['created_at'];
                $params[] = $cursorData['id'];
            }
        }

        $rows = DB::fetchAll(
            'SELECT id, customer_name, contact_name, email, phone, status, created_at
             FROM sbaio_customers'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . ($limit + 1),
            $params
        );
        $truncated = count($rows) > $limit;
        $displayRows = array_values(array_slice($rows, 0, $limit));
        $nextCursor = $truncated && $displayRows !== []
            ? self::encodeSearchCursor($displayRows[count($displayRows) - 1], $fingerprint)
            : null;

        return [
            'valid' => true,
            'available' => true,
            'rows' => $displayRows,
            'truncated' => $truncated,
            'limit' => $limit,
            'filters' => $normalized,
            'next_cursor' => $nextCursor,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{customer_name:string,email:string,phone:string,status:string,from:string,to:string}
     */
    private static function normalizeSearchFilters(array $filters): array
    {
        $allowed = ['customer_name', 'email', 'phone', 'status', 'from', 'to'];
        if (array_diff(array_keys($filters), $allowed) !== []) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_FILTER_INVALID');
        }
        $values = [];
        foreach ($allowed as $key) {
            $value = $filters[$key] ?? '';
            if (!is_string($value)) {
                throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_FILTER_INVALID');
            }
            $values[$key] = trim($value);
        }
        if ($values['customer_name'] === ''
            && $values['email'] === ''
            && $values['phone'] === ''
            && $values['status'] === ''
            && $values['from'] === ''
            && $values['to'] === '') {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_FILTER_REQUIRED');
        }
        if (strlen($values['customer_name']) > 160
            || strlen($values['email']) > 254
            || strlen($values['phone']) > 80
            || strlen($values['status']) > 40
            || ($values['from'] !== '' && !self::isCalendarDate($values['from']))
            || ($values['to'] !== '' && !self::isCalendarDate($values['to']))
            || ($values['from'] !== '' && $values['to'] !== '' && $values['from'] > $values['to'])) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_FILTER_INVALID');
        }

        return $values;
    }

    /** @param array{customer_name:string,email:string,phone:string,status:string,from:string,to:string} $filters */
    private static function searchFilterFingerprint(array $filters): string
    {
        return hash('sha256', (string)json_encode($filters, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{created_at:?string,id:int}|null
     */
    private static function decodeSearchCursor(?string $cursor, string $fingerprint): ?array
    {
        if ($cursor === null || trim($cursor) === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) || (strlen($cursor) % 4) === 1) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_CURSOR_INVALID');
        }
        $encoded = strtr($cursor, '-_', '+/');
        $encoded .= str_repeat('=', (4 - (strlen($encoded) % 4)) % 4);
        $json = base64_decode($encoded, true);
        if ($json === false) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_CURSOR_INVALID');
        }
        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_CURSOR_INVALID');
        }
        if (!is_array($payload)
            || array_diff(array_keys($payload), ['v', 'created_at', 'id', 'fingerprint']) !== []
            || count($payload) !== 4
            || ($payload['v'] ?? null) !== 1
            || !is_string($payload['fingerprint'] ?? null)
            || !hash_equals($fingerprint, $payload['fingerprint'])) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_CURSOR_INVALID');
        }
        $createdAt = $payload['created_at'] ?? null;
        if ($createdAt !== null && (!is_string($createdAt) || !self::isDateTime($createdAt))) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_CURSOR_INVALID');
        }
        $id = $payload['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new \InvalidArgumentException('SBAIO_CUSTOMERS_SEARCH_CURSOR_INVALID');
        }
        return ['created_at' => $createdAt, 'id' => $id];
    }

    /** @param array<string,mixed> $row */
    private static function encodeSearchCursor(array $row, string $fingerprint): string
    {
        $id = $row['id'] ?? null;
        if (is_string($id) && ctype_digit($id)) {
            $id = (int)$id;
        }
        $createdAt = $row['created_at'] ?? null;
        if (!is_int($id) || $id < 1 || ($createdAt !== null && (!is_string($createdAt) || !self::isDateTime($createdAt)))) {
            throw new \UnexpectedValueException('SBAIO_CUSTOMERS_SEARCH_ROW_INVALID');
        }
        $json = json_encode([
            'v' => 1,
            'created_at' => $createdAt,
            'id' => $id,
            'fingerprint' => $fingerprint,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
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

    private static function isDateTime(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
        $errors = \DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ((int)($errors['warning_count'] ?? 0) === 0 && (int)($errors['error_count'] ?? 0) === 0))
            && $date->format('Y-m-d H:i:s') === $value;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
