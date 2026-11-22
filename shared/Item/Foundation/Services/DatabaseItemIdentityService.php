<?php
declare(strict_types=1);

namespace Shared\Item\Foundation\Services;

use App\Core\DB;
use Shared\Item\Foundation\Contracts\ItemIdentityContract;
use Shared\Item\Foundation\Contracts\ItemRepositoryContract;
use Shared\Item\Foundation\Repositories\MysqlItemRepository;

/**
 * Database-backed canonical Shared Item identity service.
 *
 * This is deliberately separate from ItemIdentityService: the JSON prototype
 * remains available for its historical DB-free verification contract.
 */
final class DatabaseItemIdentityService implements ItemIdentityContract
{
    private const STATUSES = ['draft', 'active', 'deprecated', 'archived'];
    private const FORBIDDEN_FIELDS = [
        'model', 'producer', 'lead', 'notes', 'cycle_time', 'supply_model',
        'qc_time_per_item', 'execution_planning_fields', 'processing_dispatch_mode',
        'part_molds', 'machine_assignment', 'packaging_profile', 'stock_quantity',
        'warehouse_id', 'price', 'cost', 'tax',
    ];
    private const ALLOWED_FIELDS = ['name', 'status', 'category_ref', 'metadata_ref'];

    public function __construct(private readonly ?ItemRepositoryContract $repository = null)
    {
    }

    private function repo(): ItemRepositoryContract
    {
        return $this->repository ?? new MysqlItemRepository();
    }

    public function getById(int $item_id): ?array
    {
        $this->assertPositiveId($item_id);
        return $this->repo()->findById($item_id);
    }

    public function getByCode(string $code_ref): ?array
    {
        $code = $this->validateText($code_ref, 'code_ref', 255);
        return $this->repo()->findByCode($code);
    }

    public function list(?array $filters = null): array
    {
        $filters ??= [];
        $unknown = array_diff(array_keys($filters), ['status', 'category_ref', 'limit', 'offset']);
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Shared Item list filter is unsupported: ' . (string)$unknown[0]);
        }
        $status = array_key_exists('status', $filters)
            ? $this->validateStatus($filters['status'])
            : null;
        $category = array_key_exists('category_ref', $filters)
            ? $this->validateNullableText($filters['category_ref'], 'category_ref', 120)
            : null;
        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        if (!is_int($limit) || $limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Shared Item list limit must be an integer from 1 to 100');
        }
        if (!is_int($offset) || $offset < 0) {
            throw new \InvalidArgumentException('Shared Item list offset must be a non-negative integer');
        }
        return $this->repo()->list($status, $category, $limit, $offset);
    }

    public function create(array $fields): array
    {
        $this->assertNoForbiddenFields($fields);
        $this->assertKnownFields($fields, ['code_ref', 'name', 'status', 'category_ref', 'metadata_ref']);
        $code = $this->validateText($fields['code_ref'] ?? null, 'code_ref', 255);
        $name = $this->validateText($fields['name'] ?? null, 'name', 500);
        $status = array_key_exists('status', $fields)
            ? $this->validateStatus($fields['status'])
            : 'draft';
        $category = $this->validateNullableText($fields['category_ref'] ?? null, 'category_ref', 120);
        $metadata = $this->validateNullableText($fields['metadata_ref'] ?? null, 'metadata_ref', 255);

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $id = $this->repo()->insert([
                'code_ref' => $code,
                'name' => $name,
                'status' => $status,
                'category_ref' => $category,
                'metadata_ref' => $metadata,
            ]);
            $item = $this->repo()->findById($id);
            if ($item === null || (int)$item['item_id'] !== $id) {
                throw new \RuntimeException('Shared Item insert identity could not be read back');
            }
            $db->commit();
            return $item;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public function update(int $item_id, array $fields): array
    {
        $this->assertPositiveId($item_id);
        $this->assertNoForbiddenFields($fields);
        $this->assertKnownFields($fields, self::ALLOWED_FIELDS);
        if ($fields === []) {
            throw new \InvalidArgumentException('Shared Item update requires at least one field');
        }
        $current = $this->repo()->findById($item_id);
        if ($current === null) {
            throw new \InvalidArgumentException("Shared Item not found: {$item_id}");
        }
        $normalized = [];
        foreach ($fields as $field => $value) {
            if ($field === 'name') {
                $normalized[$field] = $this->validateText($value, 'name', 500);
            } elseif ($field === 'status') {
                $next = $this->validateStatus($value);
                $this->assertTransition((string)$current['status'], $next);
                $normalized[$field] = $next;
            } else {
                $normalized[$field] = $this->validateNullableText($value, $field, $field === 'category_ref' ? 120 : 255);
            }
        }

        $db = DB::conn();
        $db->begin_transaction();
        try {
            $this->repo()->update($item_id, $normalized);
            $updated = $this->repo()->findById($item_id);
            if ($updated === null) {
                throw new \RuntimeException('Shared Item update could not be read back');
            }
            $db->commit();
            return $updated;
        } catch (\Throwable $e) {
            $db->rollback();
            throw $e;
        }
    }

    public function deactivate(int $item_id): array
    {
        if ($this->getById($item_id) === null) {
            throw new \InvalidArgumentException("Shared Item not found: {$item_id}");
        }
        return $this->update($item_id, ['status' => 'deprecated']);
    }

    public function activate(int $item_id): array
    {
        if ($this->getById($item_id) === null) {
            throw new \InvalidArgumentException("Shared Item not found: {$item_id}");
        }
        return $this->update($item_id, ['status' => 'active']);
    }

    public function itemRef(int $item_id, string $code_ref): array
    {
        $item = $this->getById($item_id);
        $code = $this->validateText($code_ref, 'code_ref', 255);
        if ($item === null || $item['code_ref'] !== $code) {
            throw new \InvalidArgumentException("Shared Item reference mismatch: {$item_id} / {$code}");
        }
        return [
            'item_id' => $item_id,
            'code_ref' => $code,
            'name' => $item['name'],
            'ref_shape' => 'canonical-item-identity',
            'consumer_extension_ref' => null,
        ];
    }

    private function assertTransition(string $current, string $next): void
    {
        if ($current === $next) {
            return;
        }
        $allowed = [
            'draft' => ['active', 'archived'],
            'active' => ['deprecated', 'archived'],
            'deprecated' => ['active', 'archived'],
            'archived' => [],
        ];
        if (!in_array($next, $allowed[$current] ?? [], true)) {
            throw new \InvalidArgumentException("Invalid Shared Item status transition: {$current} -> {$next}");
        }
    }

    private function validateStatus(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Shared Item status is invalid');
        }
        return $value;
    }

    private function validateText(mixed $value, string $field, int $maxLength): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("Shared Item {$field} must be a string");
        }
        $value = trim($value);
        if ($value === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Shared Item {$field} is blank or exceeds {$maxLength} characters");
        }
        return $value;
    }

    private function validateNullableText(mixed $value, string $field, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }
        return $this->validateText($value, $field, $maxLength);
    }

    private function assertPositiveId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Shared Item id must be positive');
        }
    }

    /** @param array<string,mixed> $fields */
    private function assertNoForbiddenFields(array $fields): void
    {
        foreach (self::FORBIDDEN_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                throw new \InvalidArgumentException("Shared Item field '{$field}' is consumer-specific and forbidden");
            }
        }
    }

    /** @param array<string,mixed> $fields @param array<int,string> $allowed */
    private function assertKnownFields(array $fields, array $allowed): void
    {
        $unknown = array_diff(array_keys($fields), $allowed);
        if ($unknown !== []) {
            throw new \InvalidArgumentException("Shared Item field '{$unknown[0]}' is unsupported");
        }
    }
}
