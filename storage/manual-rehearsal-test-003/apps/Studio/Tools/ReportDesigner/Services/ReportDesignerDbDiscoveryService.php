<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\ReportDesigner\Services;

use App\Core\DB;

final class ReportDesignerDbDiscoveryService
{
    /**
     * @return array<int,string>
     */
    public static function tables(): array
    {
        $tables = [];
        foreach (DB::fetchAll('SHOW TABLES') as $row) {
            $table = array_values($row)[0] ?? null;
            if (is_string($table) && preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                $tables[] = $table;
            }
        }

        sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values(array_unique($tables));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function columns(string $table): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return [];
        }

        $columns = [];
        foreach (DB::fetchAll('SHOW COLUMNS FROM `' . $table . '`') as $row) {
            $name = trim((string)($row['Field'] ?? ''));
            $dbType = strtolower(trim((string)($row['Type'] ?? '')));
            if ($name === '') {
                continue;
            }

            $filter = self::filterMetadata($name, $dbType);
            $columns[] = [
                'key' => $name,
                'label' => self::label($name),
                'db_type' => $dbType,
                'nullable' => strtoupper((string)($row['Null'] ?? 'YES')) === 'YES',
                'key_type' => trim((string)($row['Key'] ?? '')),
                'filter_kind' => $filter['kind'],
                'filter_label' => $filter['label'],
            ];
        }

        return $columns;
    }

    /**
     * @return array{kind:string,label:string}
     */
    private static function filterMetadata(string $name, string $dbType): array
    {
        $normalized = strtolower($name);
        if (preg_match('/date|time|year/', $dbType) || preg_match('/(^|_)(date|time|at|on)$/', $normalized)) {
            return ['kind' => 'date', 'label' => 'Date range'];
        }

        if (preg_match('/(^|_)(status|state|type|category|kind|mode)$/', $normalized)
            || str_starts_with($dbType, 'enum(')
            || str_starts_with($dbType, 'set(')
        ) {
            return ['kind' => 'select', 'label' => 'Select value'];
        }

        if (preg_match('/tinyint|smallint|mediumint|bigint|int|decimal|numeric|float|double|real/', $dbType)) {
            return ['kind' => 'range', 'label' => 'Numeric range'];
        }

        return ['kind' => 'text', 'label' => 'Contains or equals'];
    }

    private static function label(string $value): string
    {
        $words = preg_split('/_+/', strtolower(trim($value))) ?: [];
        $labels = [
            'id' => 'ID',
            'ipm' => 'IPM',
            'qc' => 'QC',
            'qty' => 'Quantity',
        ];

        return implode(' ', array_map(
            static fn(string $word): string => $labels[$word] ?? ucfirst($word),
            array_values(array_filter($words, static fn(string $word): bool => $word !== ''))
        ));
    }
}
