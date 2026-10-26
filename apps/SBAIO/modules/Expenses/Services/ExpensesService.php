<?php
declare(strict_types=1);

namespace Plugins\Expenses\Services;

use App\Core\DB;

final class ExpensesService
{
    private const MAX_SEARCH_LIMIT = 50;

    /**
     * Validate and normalize the existing Expense create payload without
     * touching persistence.
     *
     * @param array<string,mixed> $input
     * @return array{valid:bool,errors:list<string>,values:array<string,string>}
     */
    public static function validateCreateInput(array $input): array
    {
        $errors = [];
        $expenseRef = self::scalarString($input, 'expense_ref');
        $categoryName = self::scalarString($input, 'category_name');
        $status = self::scalarString($input, 'expense_status');
        $expenseDate = self::scalarString($input, 'expense_date');

        if ($expenseRef === null) {
            $errors[] = 'expense_ref_invalid';
            $expenseRef = '';
        } else {
            $expenseRef = trim($expenseRef);
            if ($expenseRef === '') {
                $errors[] = 'required';
            } elseif (strlen($expenseRef) > 80) {
                $errors[] = 'expense_ref_too_long';
            }
        }

        if ($categoryName === null) {
            $errors[] = 'category_invalid';
            $categoryName = '';
        } else {
            $categoryName = trim($categoryName);
            if (strlen($categoryName) > 120) {
                $errors[] = 'category_too_long';
            }
        }

        $amount = self::validateAmount($input['amount'] ?? null);
        if ($amount === null) {
            $rawAmount = $input['amount'] ?? null;
            $errors[] = ($rawAmount === null || (is_string($rawAmount) && trim($rawAmount) === ''))
                ? 'amount_required'
                : 'amount_invalid';
            $amount = '';
        }

        if ($status === null) {
            $errors[] = 'status_invalid';
            $status = 'submitted';
        } else {
            $status = trim($status);
            if ($status === '') {
                $status = 'submitted';
            } elseif (strlen($status) > 40) {
                $errors[] = 'status_too_long';
            }
        }

        if ($expenseDate === null) {
            $errors[] = 'expense_date_invalid';
            $expenseDate = '';
        } else {
            $expenseDate = trim($expenseDate);
            if ($expenseDate !== '' && !self::isCalendarDate($expenseDate)) {
                $errors[] = 'expense_date_invalid';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'values' => [
                'expense_ref' => $expenseRef,
                'category_name' => $categoryName,
                'amount' => $amount,
                'expense_status' => $status,
                'expense_date' => $expenseDate,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        return DB::fetchAll(
            'SELECT id, expense_ref, category_name, amount, expense_status FROM sbaio_expenses ORDER BY id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * Read one Expense by its database-generated identity.
     *
     * @param mixed $id
     * @return array<string,mixed>|null
     */
    public static function findById(mixed $id): ?array
    {
        $recordId = self::normalizeRecordId($id);
        return DB::fetchOne(
            'SELECT id, expense_ref, category_name, amount, expense_status, expense_date FROM sbaio_expenses WHERE id = ? LIMIT 1',
            [$recordId]
        );
    }

    /**
     * Search Expense records using exact reference/status and inclusive date
     * bounds. Results are bounded and continue by an opaque keyset cursor.
     *
     * @param array<string,mixed> $filters
     * @return array{valid:bool,available:bool,rows:array<int,array<string,mixed>>,truncated:bool,limit:int,filters:array{expense_ref:string,status:string,from:string,to:string},next_cursor:?string}
     */
    public static function search(array $filters, int $limit = 50, ?string $cursor = null): array
    {
        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $normalized = self::normalizeSearchFilters($filters);
        $fingerprint = self::searchFilterFingerprint($normalized);
        $cursorData = self::decodeSearchCursor($cursor, $fingerprint);
        $where = [];
        $params = [];

        if ($normalized['expense_ref'] !== '') {
            $where[] = 'expense_ref = ?';
            $params[] = $normalized['expense_ref'];
        }
        if ($normalized['status'] !== '') {
            $where[] = 'expense_status = ?';
            $params[] = $normalized['status'];
        }
        if ($normalized['from'] !== '') {
            $where[] = 'expense_date >= ?';
            $params[] = $normalized['from'];
        }
        if ($normalized['to'] !== '') {
            $where[] = 'expense_date <= ?';
            $params[] = $normalized['to'];
        }
        if ($cursorData !== null) {
            if ($cursorData['expense_date'] === null) {
                $where[] = '(expense_date IS NULL AND id < ?)';
                $params[] = $cursorData['id'];
            } else {
                $where[] = '(expense_date < ? OR expense_date IS NULL OR (expense_date = ? AND id < ?))';
                $params[] = $cursorData['expense_date'];
                $params[] = $cursorData['expense_date'];
                $params[] = $cursorData['id'];
            }
        }

        $rows = DB::fetchAll(
            'SELECT id, expense_ref, category_name, amount, expense_status, expense_date
             FROM sbaio_expenses'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY expense_date DESC, id DESC LIMIT ' . ($limit + 1),
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
     * @param array<string,mixed> $input
     */
    public static function create(array $input): int
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid expense input.');
        }
        $values = $validated['values'];

        $db = DB::conn();
        $transactionStarted = false;
        try {
            if ($db->begin_transaction() !== true) {
                throw new \RuntimeException('Expense transaction could not start.');
            }
            $transactionStarted = true;

            DB::query(
                'INSERT INTO sbaio_expenses (expense_ref, category_name, amount, expense_status, expense_date) VALUES (?,?,?,?,?)',
                [
                    $values['expense_ref'],
                    self::nullIfBlank($values['category_name']),
                    $values['amount'],
                    $values['expense_status'],
                    self::nullIfBlank($values['expense_date']),
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
                throw new \RuntimeException('Expense insert identity was not available.');
            }

            if ($db->commit() !== true) {
                throw new \RuntimeException('Expense transaction commit failed.');
            }

            return $createdId;
        } catch (\Throwable $e) {
            if ($transactionStarted) {
                try {
                    if ($db->rollback() !== true) {
                        throw new \RuntimeException('Expense transaction rollback failed.');
                    }
                } catch (\Throwable $rollbackError) {
                    throw new \RuntimeException('Expense transaction rollback failed.', 0, $e);
                }
            }
            throw $e;
        }
    }

    /**
     * Validate a monetary input against the existing DECIMAL(12,2) schema
     * without converting through a binary float.
     *
     * @param mixed $value
     */
    public static function validateAmount(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (!preg_match('/^([+-]?)(?:(\d+)(?:\.(\d{1,2}))?|\.(\d{1,2}))$/D', $value, $matches)) {
            return null;
        }

        $integer = ($matches[2] ?? '') !== '' ? $matches[2] : '0';
        $fraction = ($matches[3] ?? '') !== '' ? $matches[3] : ($matches[4] ?? '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        if (strlen($integer) > 10) {
            return null;
        }

        $fraction = str_pad($fraction, 2, '0');
        $isNegative = ($matches[1] ?? '') === '-'
            && ($integer !== '0' || trim($fraction, '0') !== '');

        return ($isNegative ? '-' : '') . $integer . '.' . $fraction;
    }

    /** @param array<string,mixed> $input */
    private static function scalarString(array $input, string $key): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return '';
        }
        if (!is_string($input[$key])) {
            return null;
        }
        return $input[$key];
    }

    private static function normalizeRecordId(mixed $id): int
    {
        if (is_int($id)) {
            $recordId = $id;
        } elseif (is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id)) {
            $recordId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($recordId === false) {
                throw new \InvalidArgumentException('Invalid Expense record ID.');
            }
        } else {
            throw new \InvalidArgumentException('Invalid Expense record ID.');
        }
        if ($recordId <= 0) {
            throw new \InvalidArgumentException('Invalid Expense record ID.');
        }
        return (int)$recordId;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{expense_ref:string,status:string,from:string,to:string}
     */
    private static function normalizeSearchFilters(array $filters): array
    {
        if (array_diff(array_keys($filters), ['expense_ref', 'status', 'from', 'to']) !== []) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_FILTER_INVALID');
        }
        $expenseRef = $filters['expense_ref'] ?? '';
        $status = $filters['status'] ?? '';
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        foreach ([$expenseRef, $status, $from, $to] as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_FILTER_INVALID');
            }
        }

        $expenseRef = trim($expenseRef);
        $status = trim($status);
        $from = trim($from);
        $to = trim($to);
        if (strlen($expenseRef) > 80
            || strlen($status) > 40
            || ($from !== '' && !self::isCalendarDate($from))
            || ($to !== '' && !self::isCalendarDate($to))
            || ($from !== '' && $to !== '' && $from > $to)) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_FILTER_INVALID');
        }

        return ['expense_ref' => $expenseRef, 'status' => $status, 'from' => $from, 'to' => $to];
    }

    /** @param array{expense_ref:string,status:string,from:string,to:string} $filters */
    private static function searchFilterFingerprint(array $filters): string
    {
        return hash('sha256', (string)json_encode($filters, JSON_UNESCAPED_SLASHES));
    }

    /** @return array{expense_date:?string,id:int}|null */
    private static function decodeSearchCursor(?string $cursor, string $fingerprint): ?array
    {
        if ($cursor === null || trim($cursor) === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) || (strlen($cursor) % 4) === 1) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_CURSOR_INVALID');
        }

        $encoded = strtr($cursor, '-_', '+/');
        $encoded .= str_repeat('=', (4 - (strlen($encoded) % 4)) % 4);
        $json = base64_decode($encoded, true);
        if ($json === false) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_CURSOR_INVALID');
        }
        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_CURSOR_INVALID');
        }
        if (!is_array($payload)
            || array_diff(array_keys($payload), ['v', 'expense_date', 'id', 'fingerprint']) !== []
            || count($payload) !== 4
            || ($payload['v'] ?? null) !== 1
            || !is_string($payload['fingerprint'] ?? null)
            || !hash_equals($fingerprint, $payload['fingerprint'])
        ) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_CURSOR_INVALID');
        }

        $expenseDate = $payload['expense_date'] ?? null;
        if ($expenseDate !== null && (!is_string($expenseDate) || !self::isCalendarDate($expenseDate))) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_CURSOR_INVALID');
        }
        $id = $payload['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new \InvalidArgumentException('SBAIO_EXPENSES_SEARCH_CURSOR_INVALID');
        }

        return ['expense_date' => $expenseDate, 'id' => $id];
    }

    /** @param array<string,mixed> $row */
    private static function encodeSearchCursor(array $row, string $fingerprint): string
    {
        $id = $row['id'] ?? null;
        if (is_string($id) && ctype_digit($id)) {
            $id = (int)$id;
        }
        $expenseDate = $row['expense_date'] ?? null;
        if (!is_int($id) || $id < 1 || ($expenseDate !== null && (!is_string($expenseDate) || !self::isCalendarDate($expenseDate)))) {
            throw new \UnexpectedValueException('SBAIO_EXPENSES_SEARCH_ROW_INVALID');
        }
        $json = json_encode([
            'v' => 1,
            'expense_date' => $expenseDate,
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

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
