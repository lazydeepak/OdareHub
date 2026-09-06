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
        DB::query(
            'INSERT INTO sbaio_expenses (expense_ref, category_name, amount, expense_status, expense_date) VALUES (?,?,?,?,?)',
            [
                trim((string)($input['expense_ref'] ?? '')),
                self::nullIfBlank((string)($input['category_name'] ?? '')),
                self::decimalOrZero((string)($input['amount'] ?? '0')),
                self::nullIfBlank((string)($input['expense_status'] ?? '')) ?? 'submitted',
                self::nullIfBlank((string)($input['expense_date'] ?? '')),
            ]
        );
    }

    private static function decimalOrZero(string $value): float
    {
        $value = trim($value);
        return is_numeric($value) ? (float)$value : 0.0;
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
