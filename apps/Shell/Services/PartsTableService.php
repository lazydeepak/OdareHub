<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

final class PartsTableService
{
    /**
     * Fetch parts data for table view
     * Links to products table with available and calculated fields
     * 
     * @return array{
     *   rows: array<int, array{
     *     id: int,
     *     parts_name: string,
     *     parts_number: string,
     *     model: string,
     *     stock: float,
     *     today_demand: float,
     *     coverage: string,
     *     next_plan_date: string,
     *     next_plan_qty: float
     *   }>,
     *   total_count: int,
     *   empty_label: string
     * }
     */
    public static function getPartsTable(): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT
                    p.id,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', p.id)) AS parts_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), CONCAT('P-', p.id)) AS parts_number,
                    COALESCE(NULLIF(TRIM(p.model), ''), '') AS model,
                    0 AS stock,
                    0 AS today_demand,
                    '-' AS coverage,
                    '' AS next_plan_date,
                    0 AS next_plan_qty
                FROM products p
                ORDER BY p.id ASC
                LIMIT 500"
            );

            $formatted = [];
            foreach ((array)$rows as $row) {
                $formatted[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'parts_name' => trim((string)($row['parts_name'] ?? '')),
                    'parts_number' => trim((string)($row['parts_number'] ?? '')),
                    'model' => trim((string)($row['model'] ?? '')),
                    'stock' => (float)($row['stock'] ?? 0),
                    'today_demand' => (float)($row['today_demand'] ?? 0),
                    'coverage' => trim((string)($row['coverage'] ?? '-')),
                    'next_plan_date' => trim((string)($row['next_plan_date'] ?? '')),
                    'next_plan_qty' => (float)($row['next_plan_qty'] ?? 0),
                ];
            }

            return [
                'rows' => $formatted,
                'total_count' => count($formatted),
                'empty_label' => 'No products found.',
            ];
        } catch (\Throwable $e) {
            return [
                'rows' => [],
                'total_count' => 0,
                'empty_label' => 'Unable to load parts table.',
            ];
        }
    }
}
