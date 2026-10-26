<?php
declare(strict_types=1);

namespace Plugins\Expenses\Services;

use App\Core\DB;

final class ExpensesService
{
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
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        $amount = self::validateAmount($input['amount'] ?? null);
        if ($amount === null) {
            throw new \InvalidArgumentException('Invalid expense amount.');
        }

        DB::query(
            'INSERT INTO sbaio_expenses (expense_ref, category_name, amount, expense_status, expense_date) VALUES (?,?,?,?,?)',
            [
                trim((string)($input['expense_ref'] ?? '')),
                self::nullIfBlank((string)($input['category_name'] ?? '')),
                $amount,
                self::nullIfBlank((string)($input['expense_status'] ?? '')) ?? 'submitted',
                self::nullIfBlank((string)($input['expense_date'] ?? '')),
            ]
        );
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

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
