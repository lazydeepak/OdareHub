<?php
declare(strict_types=1);

namespace Plugins\Customers\Services;

use App\Core\DB;

final class CustomersService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function recent(int $limit = 25): array
    {
        return DB::fetchAll(
            'SELECT id, customer_name, contact_name, email, status FROM sbaio_customers ORDER BY id DESC LIMIT ' . (int)$limit
        );
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        DB::query(
            'INSERT INTO sbaio_customers (customer_name, contact_name, email, phone, status) VALUES (?,?,?,?,?)',
            [
                trim((string)($input['customer_name'] ?? '')),
                self::nullIfBlank((string)($input['contact_name'] ?? '')),
                self::nullIfBlank((string)($input['email'] ?? '')),
                self::nullIfBlank((string)($input['phone'] ?? '')),
                self::nullIfBlank((string)($input['status'] ?? '')) ?? 'active',
            ]
        );
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
