<?php
declare(strict_types=1);

namespace Plugins\Products\Services;

use App\Core\DB;
use Apps\Platform\Services\OrganizationMembershipContext;

/**
 * Products-owned persistence boundary for newly owned Product records.
 *
 * The caller must provide opaque Platform context evidence. A raw organization
 * ID is deliberately not accepted, so request input cannot become ownership
 * proof at the persistence boundary.
 */
final class ProductCreationService
{
    /**
     * Insert one new Product atomically with its verified owner.
     *
     * @param array<string,mixed> $columns
     */
    public static function create(array $columns, OrganizationMembershipContext $context): int
    {
        $conn = DB::conn();
        $conn->begin_transaction();
        try {
            $id = self::insert($columns, $context);
            $conn->commit();
            return $id;
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }
    }

    /**
     * Insert or update one import row inside the caller's transaction.
     *
     * Existing NULL-owned or differently owned Products are never claimed.
     *
     * @param array<string,mixed> $columns
     */
    public static function upsertOwned(array $columns, OrganizationMembershipContext $context): int
    {
        $organizationId = $context->organizationId();
        $partsNumber = trim((string)($columns['parts_number'] ?? ''));
        if ($partsNumber === '') {
            throw new \InvalidArgumentException('Product parts number is required.');
        }

        $existing = DB::fetchOne(
            'SELECT id, organization_id FROM products WHERE parts_number=? LIMIT 1 FOR UPDATE',
            [$partsNumber]
        );
        if (is_array($existing)) {
            if ((int)($existing['organization_id'] ?? 0) !== $organizationId) {
                throw new \RuntimeException('Product ownership conflict for parts number: ' . $partsNumber);
            }

            $updates = self::filteredColumns($columns, false);
            unset($updates['parts_number']);
            if ($updates !== []) {
                $set = [];
                $params = [];
                foreach ($updates as $field => $value) {
                    $set[] = self::quoteField($field) . '=?';
                    $params[] = $value;
                }
                $params[] = (int)$existing['id'];
                DB::query(
                    'UPDATE products SET ' . implode(', ', $set) . ', updated_at=NOW() WHERE id=? LIMIT 1',
                    $params
                );
            }
            return (int)$existing['id'];
        }

        return self::insert($columns, $context);
    }

    /**
     * @param array<string,mixed> $columns
     */
    private static function insert(array $columns, OrganizationMembershipContext $context): int
    {
        $columns = self::filteredColumns($columns, true);
        $columns['organization_id'] = $context->organizationId();
        $fields = array_keys($columns);
        $quotedFields = array_map([self::class, 'quoteField'], $fields);
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        DB::query(
            'INSERT INTO products (' . implode(', ', $quotedFields) . ') VALUES (' . $placeholders . ')',
            array_values($columns)
        );
        $id = (int)DB::conn()->insert_id;
        if ($id <= 0) {
            throw new \RuntimeException('Product insert did not return a valid identity.');
        }
        return $id;
    }

    /**
     * @param array<string,mixed> $columns
     * @return array<string,mixed>
     */
    private static function filteredColumns(array $columns, bool $includePartsNumber): array
    {
        $allowed = [
            'parts_name', 'parts_number', 'model', 'producer', 'lead', 'cycle_time',
            'qc_time_per_item', 'notes', 'is_active', 'supply_mode', 'fulfillment_mode',
            'requires_ipm_qc', 'default_supplier', 'stocked_at_ipm',
            'default_procurement_lead_days', 'default_supply_note', 'requires_assembly',
            'requires_processing', 'dispatch_mode', 'dispatch_as_is', 'activity_type',
            'essential_stock_qty', 'planning_window_days', 'max_buffer_qty',
            'safety_stock_qty', 'active_machine_id', 'qty_per_case', 'case_spec',
            'case_type', 'default_case_number', 'cases_per_pallet', 'production_source',
            'requires_qc', 'delivery_flow',
        ];
        $result = [];
        foreach ($allowed as $field) {
            if ($field === 'parts_number' && !$includePartsNumber) {
                continue;
            }
            if (array_key_exists($field, $columns)) {
                $result[$field] = $columns[$field];
            }
        }
        if (!array_key_exists('parts_name', $result) || (!$includePartsNumber && $result === [])) {
            throw new \InvalidArgumentException('Product identity fields are required.');
        }
        if ($includePartsNumber && !array_key_exists('parts_number', $result)) {
            throw new \InvalidArgumentException('Product parts number is required.');
        }
        return $result;
    }

    private static function quoteField(string $field): string
    {
        return $field === 'lead' ? '`lead`' : $field;
    }
}
