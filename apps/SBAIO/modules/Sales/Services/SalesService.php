<?php
declare(strict_types=1);

namespace Plugins\Sales\Services;

use App\Core\DB;

final class SalesService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        return DB::fetchAll(
            'SELECT id, sale_ref, customer_name, amount, sale_status FROM sbaio_sales ORDER BY id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        $allowedStatus = ['draft', 'open', 'pending', 'won', 'completed', 'closed'];
        $statusRaw = trim((string)($input['sale_status'] ?? ''));
        $status = strtolower($statusRaw === '' ? 'open' : $statusRaw);
        if (!in_array($status, $allowedStatus, true)) {
            throw new \InvalidArgumentException('Invalid sale status: ' . $statusRaw . '. Allowed: ' . implode(', ', $allowedStatus) . '.');
        }

        DB::query(
            'INSERT INTO sbaio_sales (sale_ref, customer_name, amount, sale_status, sale_date) VALUES (?,?,?,?,?)',
            [
                trim((string)($input['sale_ref'] ?? '')),
                self::nullIfBlank((string)($input['customer_name'] ?? '')),
                self::decimalOrZero((string)($input['amount'] ?? '0')),
                $status,
                self::nullIfBlank((string)($input['sale_date'] ?? '')),
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
