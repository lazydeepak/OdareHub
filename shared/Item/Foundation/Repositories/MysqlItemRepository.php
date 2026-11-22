<?php
declare(strict_types=1);

namespace Shared\Item\Foundation\Repositories;

use App\Core\DB;
use Shared\Item\Foundation\Contracts\ItemRepositoryContract;

final class MysqlItemRepository implements ItemRepositoryContract
{
    public function findById(int $itemId): ?array
    {
        return DB::fetchOne(
            'SELECT item_id, code_ref, name, status, category_ref, metadata_ref, created_at, updated_at
             FROM shared_items WHERE item_id = ? LIMIT 1',
            [$itemId]
        );
    }

    public function findByCode(string $codeRef): ?array
    {
        return DB::fetchOne(
            'SELECT item_id, code_ref, name, status, category_ref, metadata_ref, created_at, updated_at
             FROM shared_items WHERE code_ref = ? LIMIT 1',
            [$codeRef]
        );
    }

    public function list(?string $status, ?string $categoryRef, int $limit, int $offset): array
    {
        $where = [];
        $params = [];
        if ($status !== null) {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($categoryRef !== null) {
            $where[] = 'category_ref = ?';
            $params[] = $categoryRef;
        }

        $sql = 'SELECT item_id, code_ref, name, status, category_ref, metadata_ref, created_at, updated_at
                FROM shared_items';
        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY item_id ASC LIMIT ' . $limit . ' OFFSET ' . $offset;
        return DB::fetchAll($sql, $params);
    }

    public function insert(array $fields): int
    {
        DB::query(
            'INSERT INTO shared_items (code_ref, name, status, category_ref, metadata_ref)
             VALUES (?, ?, ?, ?, ?)',
            [
                $fields['code_ref'],
                $fields['name'],
                $fields['status'],
                $fields['category_ref'],
                $fields['metadata_ref'],
            ]
        );
        $id = (int)DB::conn()->insert_id;
        if ($id <= 0) {
            throw new \RuntimeException('Shared Item insert did not return a positive identity');
        }
        return $id;
    }

    public function update(int $itemId, array $fields): void
    {
        $assignments = [];
        $params = [];
        foreach (['name', 'status', 'category_ref', 'metadata_ref'] as $field) {
            if (array_key_exists($field, $fields)) {
                $assignments[] = $field . ' = ?';
                $params[] = $fields[$field];
            }
        }
        if ($assignments === []) {
            return;
        }
        $params[] = $itemId;
        DB::query(
            'UPDATE shared_items SET ' . implode(', ', $assignments) . ', updated_at = CURRENT_TIMESTAMP
             WHERE item_id = ? LIMIT 1',
            $params
        );
    }
}
