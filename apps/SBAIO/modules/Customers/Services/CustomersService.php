<?php
declare(strict_types=1);

namespace Plugins\Customers\Services;

use App\Core\DB;

final class CustomersService
{
    private const MAX_LIST_LIMIT = 25;

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
     * @param array<string,mixed> $input
     */
    public static function create(array $input): void
    {
        $validated = self::validateCreateInput($input);
        if (!$validated['valid']) {
            throw new \InvalidArgumentException('Invalid customer input.');
        }
        $values = $validated['values'];

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
    }

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
