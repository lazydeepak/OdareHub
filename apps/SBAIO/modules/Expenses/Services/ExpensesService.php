<?php
declare(strict_types=1);

namespace Plugins\Expenses\Services;

use App\Core\DB;

final class ExpensesService
{
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
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid expense input.');
        }
        $values = $validated['values'];

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
