<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\DB;

final class ManufacturingGateway
{
    private static ?ManufacturingGateway $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function create(string $entityKey, array $row): int
    {
        $table = $this->getTableForEntity($entityKey);
        $this->ensureTableExists($table);

        $columns = array_keys($row);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnList = implode(', ', $columns);

        $params = array_values($row);
        $types = $this->getTypesForParams($params);

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";

        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException("Prepare failed: " . DB::conn()->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new \RuntimeException("Execute failed: " . $stmt->error);
        }

        return (int)$stmt->insert_id;
    }

    public function read(string $entityKey, int $id): ?array
    {
        $table = $this->getTableForEntity($entityKey);
        $this->ensureTableExists($table);

        $sql = "SELECT * FROM {$table} WHERE id = ? LIMIT 1";
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException("Prepare failed: " . DB::conn()->error);
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new \RuntimeException("Execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row ?: null;
    }

    public function update(string $entityKey, int $id, array $row): bool
    {
        $table = $this->getTableForEntity($entityKey);
        $this->ensureTableExists($table);

        if (empty($row)) {
            return true;
        }

        $setParts = [];
        $params = [];
        $types = '';

        foreach ($row as $column => $value) {
            $setParts[] = "{$column} = ?";
            $params[] = $value;
            $types .= $this->getTypeForValue($value);
        }

        $params[] = $id;
        $types .= 'i';

        $setClause = implode(', ', $setParts);
        $sql = "UPDATE {$table} SET {$setClause}, updated_at = NOW() WHERE id = ?";

        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException("Prepare failed: " . DB::conn()->error);
        }

        $stmt->bind_param($types, ...$params);
        if (!$stmt->execute()) {
            throw new \RuntimeException("Execute failed: " . $stmt->error);
        }

        return $stmt->affected_rows >= 0;
    }

    public function delete(string $entityKey, int $id): bool
    {
        $table = $this->getTableForEntity($entityKey);
        $this->ensureTableExists($table);

        $sql = "DELETE FROM {$table} WHERE id = ?";
        $stmt = DB::conn()->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException("Prepare failed: " . DB::conn()->error);
        }

        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new \RuntimeException("Execute failed: " . $stmt->error);
        }

        return $stmt->affected_rows > 0;
    }

    private function getTableForEntity(string $entityKey): string
    {
        return match ($entityKey) {
            'QCEntry' => 'qc_entries',
            'DispatchEntry' => 'dispatch_entries',
            'ProductionPlan' => 'production_plans',
            'DailyOrder' => 'daily_orders',
            'ProductionEntry' => 'production_entries',
            'AssemblyPlan' => 'mfg_part_demands',
            'AssemblyEntry' => 'mfg_assembly_entries',
            default => throw new \InvalidArgumentException("Unknown entity: {$entityKey}"),
        };
    }

    private function ensureTableExists(string $table): void
    {
        $check = DB::fetchOne("SHOW TABLES LIKE '" . DB::conn()->real_escape_string($table) . "'");
        if ($check === null) {
            throw new \RuntimeException("Table does not exist: {$table}");
        }
    }

    private function getTypesForParams(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            $types .= $this->getTypeForValue($param);
        }
        return $types;
    }

    private function getTypeForValue(mixed $value): string
    {
        if (is_int($value)) {
            return 'i';
        }
        if (is_float($value)) {
            return 'd';
        }
        return 's';
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
