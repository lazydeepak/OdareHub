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

    private static function nullIfBlank(string $value): ?string
    {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
