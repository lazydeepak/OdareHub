<?php
declare(strict_types=1);

namespace Plugins\Sales\Services;

use App\Core\DB;

final class SalesService
{
    private const MAX_LIST_LIMIT = 25;
    private const MAX_SEARCH_LIMIT = 50;
    public const STATUSES = ['draft', 'open', 'pending', 'won', 'completed', 'closed'];

    /**
     * Validate and normalize the existing Sales create payload without
     * touching persistence. Amounts remain decimal strings throughout.
     *
     * @param array<string,mixed> $input
     * @return array{valid:bool,errors:list<string>,values:array<string,string>}
     */
    public static function validateCreateInput(array $input): array
    {
        $errors = [];
        $saleRef = self::scalarString($input, 'sale_ref');
        $customerName = self::scalarString($input, 'customer_name');
        $amountRaw = self::scalarString($input, 'amount');
        $statusRaw = self::scalarString($input, 'sale_status');
        $saleDate = self::scalarString($input, 'sale_date');

        if ($saleRef === null) {
            $errors[] = 'sale_ref_invalid';
            $saleRef = '';
        } else {
            $saleRef = trim($saleRef);
            if ($saleRef === '') {
                $errors[] = 'sale_ref_required';
            } elseif (strlen($saleRef) > 80) {
                $errors[] = 'sale_ref_too_long';
            }
        }

        if ($customerName === null) {
            $errors[] = 'customer_name_invalid';
            $customerName = '';
        } else {
            $customerName = trim($customerName);
            if (strlen($customerName) > 190) {
                $errors[] = 'customer_name_too_long';
            }
        }

        $amount = null;
        if ($amountRaw === null) {
            $errors[] = 'amount_invalid';
            $amount = '';
        } elseif (trim($amountRaw) === '') {
            $errors[] = 'amount_required';
            $amount = '';
        } else {
            $amount = self::normalizeDecimal(trim($amountRaw));
            if ($amount === null) {
                $errors[] = 'amount_invalid';
                $amount = '';
            }
        }

        if ($statusRaw === null) {
            $errors[] = 'status_invalid';
            $status = 'open';
        } else {
            $status = strtolower(trim($statusRaw));
            if ($status === '') {
                $status = 'open';
            }
            if (!in_array($status, self::STATUSES, true)) {
                $errors[] = 'status_invalid';
            }
        }

        if ($saleDate === null) {
            $errors[] = 'sale_date_invalid';
            $saleDate = '';
        } else {
            $saleDate = trim($saleDate);
            if ($saleDate !== '' && !self::isCalendarDate($saleDate)) {
                $errors[] = 'sale_date_invalid';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => array_values(array_unique($errors)),
            'values' => [
                'sale_ref' => $saleRef,
                'customer_name' => $customerName,
                'amount' => $amount,
                'sale_status' => $status,
                'sale_date' => $saleDate,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        $limit = min(max($limit, 1), self::MAX_LIST_LIMIT);
        return DB::fetchAll(
            'SELECT id, sale_ref, customer_name, amount, sale_status FROM sbaio_sales ORDER BY id DESC LIMIT ' . $limit
        );
    }

    /**
     * Search Sales records using only exact reference, established status, and
     * inclusive sale-date filters. The read is bounded and deterministic.
     *
     * @param array<string,mixed> $filters
     * @return array{valid:bool,available:bool,rows:array<int,array<string,mixed>>,truncated:bool,limit:int,filters:array{sale_ref:string,status:string,from:string,to:string},next_cursor:?string}
     */
    public static function search(array $filters, int $limit = 50, ?string $cursor = null): array
    {
        $limit = max(1, min(self::MAX_SEARCH_LIMIT, $limit));
        $normalized = self::normalizeSearchFilters($filters);
        $fingerprint = self::searchFilterFingerprint($normalized);
        $cursorData = self::decodeSearchCursor($cursor, $fingerprint);
        $where = [];
        $params = [];

        if ($normalized['sale_ref'] !== '') {
            $where[] = 'sale_ref = ?';
            $params[] = $normalized['sale_ref'];
        }
        if ($normalized['status'] !== '') {
            $where[] = 'sale_status = ?';
            $params[] = $normalized['status'];
        }
        if ($normalized['from'] !== '') {
            $where[] = 'sale_date >= ?';
            $params[] = $normalized['from'];
        }
        if ($normalized['to'] !== '') {
            $where[] = 'sale_date <= ?';
            $params[] = $normalized['to'];
        }
        if ($cursorData !== null) {
            if ($cursorData['sale_date'] === null) {
                $where[] = '(sale_date IS NULL AND id < ?)';
                $params[] = $cursorData['id'];
            } else {
                $where[] = '(sale_date < ? OR sale_date IS NULL OR (sale_date = ? AND id < ?))';
                $params[] = $cursorData['sale_date'];
                $params[] = $cursorData['sale_date'];
                $params[] = $cursorData['id'];
            }
        }

        $rows = DB::fetchAll(
            'SELECT id, sale_ref, customer_name, amount, sale_status, sale_date
             FROM sbaio_sales'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY sale_date DESC, id DESC LIMIT ' . ($limit + 1),
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
     * Build the existing Sales overview report payload without changing its
     * predicates, aggregation, ordering, limits, or default-on-error behavior.
     *
     * @return array{summary:array<string,mixed>,byStatus:array<int,array<string,mixed>>,topCustomers:array<int,array<string,mixed>>,recent:array<int,array<string,mixed>>}
     */
    public static function reportData(): array
    {
        $summary = ['total'=>0,'amount'=>0.0,'month'=>0,'month_amount'=>0.0,'closed'=>0,'open'=>0];
        $byStatus = [];
        $topCustomers = [];
        $recent = [];

        try {
            $row = DB::fetchOne(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(amount),0) AS amount,
                        COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN 1 ELSE 0 END),0) AS month,
                        COALESCE(SUM(CASE WHEN sale_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN amount ELSE 0 END),0) AS month_amount,
                        COALESCE(SUM(CASE WHEN LOWER(COALESCE(sale_status,'')) IN ('closed','completed','won') THEN 1 ELSE 0 END),0) AS closed,
                        COALESCE(SUM(CASE WHEN LOWER(COALESCE(sale_status,'')) IN ('open','pending','draft') THEN 1 ELSE 0 END),0) AS open
                 FROM sbaio_sales
                 WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"
            );
            if (is_array($row)) { $summary = array_merge($summary, $row); }

            $byStatus = DB::fetchAll(
                "SELECT COALESCE(NULLIF(sale_status,''),'(none)') AS sale_status, COUNT(*) AS n,
                        COALESCE(SUM(amount),0) AS amount
                 FROM sbaio_sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                 GROUP BY sale_status ORDER BY n DESC"
            ) ?: [];

            $topCustomers = DB::fetchAll(
                "SELECT COALESCE(NULLIF(customer_name,''),'(none)') AS customer_name, COUNT(*) AS n,
                        COALESCE(SUM(amount),0) AS amount
                 FROM sbaio_sales WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                 GROUP BY customer_name ORDER BY amount DESC LIMIT 15"
            ) ?: [];

            $recent = DB::fetchAll(
                "SELECT id, sale_ref, sale_date, customer_name, amount, sale_status
                 FROM sbaio_sales ORDER BY sale_date DESC, id DESC LIMIT 25"
            ) ?: [];
        } catch (\Throwable) {
            // Preserve the report view's existing fail-closed/default payload.
        }

        return compact('summary', 'byStatus', 'topCustomers', 'recent');
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): int
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid sales input.');
        }
        $values = $validated['values'];

        DB::query(
            'INSERT INTO sbaio_sales (sale_ref, customer_name, amount, sale_status, sale_date) VALUES (?,?,?,?,?)',
            [
                $values['sale_ref'],
                self::nullIfBlank($values['customer_name']),
                $values['amount'],
                $values['sale_status'],
                self::nullIfBlank($values['sale_date']),
            ]
        );

        $insertId = DB::conn()->insert_id;
        if (is_int($insertId)) {
            if ($insertId > 0) {
                return $insertId;
            }
        } elseif (is_string($insertId) && preg_match('/^[1-9][0-9]*$/D', $insertId)) {
            $normalizedId = filter_var($insertId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($normalizedId !== false) {
                return (int)$normalizedId;
            }
        }

        throw new \RuntimeException('Sales insert identity was not available.');
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

    /**
     * @param array<string,mixed> $filters
     * @return array{sale_ref:string,status:string,from:string,to:string}
     */
    private static function normalizeSearchFilters(array $filters): array
    {
        if (array_diff(array_keys($filters), ['sale_ref', 'status', 'from', 'to']) !== []) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_FILTER_INVALID');
        }
        $saleRef = $filters['sale_ref'] ?? '';
        $status = $filters['status'] ?? '';
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        foreach ([$saleRef, $status, $from, $to] as $value) {
            if (!is_string($value)) {
                throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_FILTER_INVALID');
            }
        }

        $saleRef = trim($saleRef);
        $status = strtolower(trim($status));
        $from = trim($from);
        $to = trim($to);
        if (strlen($saleRef) > 80
            || ($status !== '' && !in_array($status, self::STATUSES, true))
            || ($from !== '' && !self::isCalendarDate($from))
            || ($to !== '' && !self::isCalendarDate($to))
            || ($from !== '' && $to !== '' && $from > $to)) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_FILTER_INVALID');
        }

        return ['sale_ref' => $saleRef, 'status' => $status, 'from' => $from, 'to' => $to];
    }

    /** @param array{sale_ref:string,status:string,from:string,to:string} $filters */
    private static function searchFilterFingerprint(array $filters): string
    {
        return hash('sha256', (string)json_encode($filters, JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{sale_date:?string,id:int}|null
     */
    private static function decodeSearchCursor(?string $cursor, string $fingerprint): ?array
    {
        if ($cursor === null || trim($cursor) === '') {
            return null;
        }
        if (!preg_match('/^[A-Za-z0-9_-]+$/D', $cursor) || (strlen($cursor) % 4) === 1) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_CURSOR_INVALID');
        }

        $encoded = strtr($cursor, '-_', '+/');
        $encoded .= str_repeat('=', (4 - (strlen($encoded) % 4)) % 4);
        $json = base64_decode($encoded, true);
        if ($json === false) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_CURSOR_INVALID');
        }
        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_CURSOR_INVALID');
        }
        if (!is_array($payload)
            || array_diff(array_keys($payload), ['v', 'sale_date', 'id', 'fingerprint']) !== []
            || count($payload) !== 4
            || ($payload['v'] ?? null) !== 1
            || !is_string($payload['fingerprint'] ?? null)
            || !hash_equals($fingerprint, $payload['fingerprint'])
        ) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_CURSOR_INVALID');
        }

        $saleDate = $payload['sale_date'] ?? null;
        if ($saleDate !== null && (!is_string($saleDate) || !self::isCalendarDate($saleDate))) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_CURSOR_INVALID');
        }
        $id = $payload['id'] ?? null;
        if (!is_int($id) || $id < 1) {
            throw new \InvalidArgumentException('SBAIO_SALES_SEARCH_CURSOR_INVALID');
        }

        return ['sale_date' => $saleDate, 'id' => $id];
    }

    /** @param array<string,mixed> $row */
    private static function encodeSearchCursor(array $row, string $fingerprint): string
    {
        $id = $row['id'] ?? null;
        if (is_string($id) && ctype_digit($id)) {
            $id = (int)$id;
        }
        $saleDate = $row['sale_date'] ?? null;
        if (!is_int($id) || $id < 1 || ($saleDate !== null && (!is_string($saleDate) || !self::isCalendarDate($saleDate)))) {
            throw new \UnexpectedValueException('SBAIO_SALES_SEARCH_ROW_INVALID');
        }
        $json = json_encode([
            'v' => 1,
            'sale_date' => $saleDate,
            'id' => $id,
            'fingerprint' => $fingerprint,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function normalizeDecimal(string $value): ?string
    {
        if (!preg_match('/^([+-]?)(?:(\d+)(?:\.(\d{1,2}))?|\.(\d{1,2}))$/D', $value, $matches)) {
            return null;
        }

        $integer = $matches[2] ?? '0';
        $fraction = $matches[3] ?? ($matches[4] ?? '');
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
