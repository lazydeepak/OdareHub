<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use App\Core\Auth;
use App\Core\DB;

final class BaseSchemaBuilderService
{
    public static function requireSystemBuilder(): void
    {
        Auth::bootSession();
        $user = Auth::user();
        if (!$user) {
            header('Location: /login');
            exit;
        }

        if (!base_can_access_builder($user)) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }

    public static function modules(): array
    {
        return BaseSchemaBuilderHelper::modules();
    }

    public static function currentUserEmail(): string
    {
        $user = Auth::user();
        return trim((string)($user['email'] ?? 'unknown'));
    }

    public static function ensureTables(): void
    {
        DB::query("CREATE TABLE IF NOT EXISTS base_module_registry (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_key VARCHAR(80) NOT NULL UNIQUE,
            label VARCHAR(150) NOT NULL,
            table_name VARCHAR(120) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        DB::query("CREATE TABLE IF NOT EXISTS base_field_registry (
            id INT AUTO_INCREMENT PRIMARY KEY,
            module_key VARCHAR(80) NOT NULL,
            field_key VARCHAR(120) NOT NULL,
            column_name VARCHAR(120) NOT NULL,
            label VARCHAR(160) NOT NULL,
            data_type VARCHAR(40) NOT NULL,
            max_length INT NULL,
            precision_value INT NULL,
            scale_value INT NULL,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            default_value VARCHAR(255) NULL,
            options_text TEXT NULL,
            is_visible TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 100,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_synced_at DATETIME NULL,
            created_by VARCHAR(190) NULL,
            updated_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_base_field_module_column (module_key, column_name),
            UNIQUE KEY uniq_base_field_module_field_key (module_key, field_key),
            KEY idx_base_field_module (module_key),
            KEY idx_base_field_status (status),
            KEY idx_base_field_visible (is_visible)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        DB::query("CREATE TABLE IF NOT EXISTS base_schema_audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action_type VARCHAR(60) NOT NULL,
            module_key VARCHAR(80) NOT NULL,
            field_id INT NULL,
            payload_json JSON NULL,
            changed_by VARCHAR(190) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_base_audit_module (module_key),
            KEY idx_base_audit_action (action_type),
            KEY idx_base_audit_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        foreach (BaseSchemaBuilderHelper::modules() as $module) {
            DB::query(
                'INSERT INTO base_module_registry (module_key, label, table_name, is_active) VALUES (?,?,?,?)
                 ON DUPLICATE KEY UPDATE label=VALUES(label), table_name=VALUES(table_name), is_active=VALUES(is_active)',
                [(string)$module['module_key'], (string)$module['label'], (string)$module['table_name'], (int)$module['is_active']]
            );
        }
    }

    public static function audit(string $actionType, string $moduleKey, ?int $fieldId, array $payload): void
    {
        DB::query(
            'INSERT INTO base_schema_audit_log (action_type, module_key, field_id, payload_json, changed_by) VALUES (?,?,?,?,?)',
            [
                $actionType,
                $moduleKey,
                $fieldId,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                self::currentUserEmail(),
            ]
        );
    }

    public static function columnSql(array $field): string
    {
        return BaseSchemaBuilderHelper::columnSql($field);
    }

    public static function tableList(): array
    {
        return BaseSchemaBuilderHelper::tableList();
    }

    public static function makeLabel(string $tableName): string
    {
        return BaseSchemaBuilderHelper::makeLabel($tableName);
    }

    public static function makeModuleKey(string $tableName): string
    {
        return BaseSchemaBuilderHelper::makeModuleKey($tableName);
    }

    public static function syncPlanForModule(array $module): array
    {
        $moduleKey = trim((string)($module['module_key'] ?? ''));
        $tableName = trim((string)($module['table_name'] ?? ''));
        $result = [
            'module_key' => $moduleKey,
            'label' => (string)($module['label'] ?? $moduleKey),
            'table_name' => $tableName,
            'pending' => [],
            'pending_count' => 0,
            'error' => null,
        ];

        if ($moduleKey === '' || $tableName === '') {
            $result['error'] = 'Module key or table name is missing.';
            return $result;
        }

        $escapedTable = DB::conn()->real_escape_string($tableName);
        $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';
        if (DB::fetchOne("SHOW TABLES LIKE '{$escapedTable}'") === null) {
            $result['error'] = 'Target table does not exist.';
            return $result;
        }

        $dbColsRows = DB::fetchAll("SHOW COLUMNS FROM {$quotedTable}");
        $dbCols = [];
        foreach ($dbColsRows as $column) {
            $dbCols[strtolower((string)($column['Field'] ?? ''))] = true;
        }

        $fields = DB::fetchAll(
            "SELECT * FROM base_field_registry WHERE module_key=? AND status='active' ORDER BY sort_order ASC, id ASC",
            [$moduleKey]
        );

        foreach ($fields as $field) {
            $columnName = trim((string)($field['column_name'] ?? ''));
            if ($columnName === '' || isset($dbCols[strtolower($columnName)])) {
                continue;
            }
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $columnName)) {
                continue;
            }

            $result['pending'][] = [
                'field_id' => (int)($field['id'] ?? 0),
                'column_name' => $columnName,
                'definition' => BaseSchemaBuilderHelper::columnSql($field),
            ];
        }

        $result['pending_count'] = count($result['pending']);
        return $result;
    }
}

final class BaseSchemaBuilderHelper
{
    public static function modules(): array
    {
        return [
            ['module_key' => 'products', 'label' => 'Parts Master', 'table_name' => 'products', 'is_active' => 1],
            ['module_key' => 'machines', 'label' => 'Machines', 'table_name' => 'machines', 'is_active' => 1],
            ['module_key' => 'part_machine_map', 'label' => 'Part-Machine Map', 'table_name' => 'part_machine_map', 'is_active' => 1],
        ];
    }

    public static function columnSql(array $field): string
    {
        $dataType = strtolower(trim((string)($field['data_type'] ?? 'varchar')));
        $maxLength = (int)($field['max_length'] ?? 0);
        $precision = (int)($field['precision_value'] ?? 0);
        $scale = (int)($field['scale_value'] ?? 0);
        $isRequired = (int)($field['is_required'] ?? 0) === 1;
        $defaultValue = $field['default_value'] ?? null;

        $typeSql = match ($dataType) {
            'int', 'integer' => 'INT',
            'bigint' => 'BIGINT',
            'tinyint', 'boolean', 'bool' => 'TINYINT(1)',
            'decimal' => 'DECIMAL(' . max(1, $precision > 0 ? $precision : 14) . ',' . max(0, $scale > 0 ? $scale : 2) . ')',
            'date' => 'DATE',
            'datetime' => 'DATETIME',
            'text' => 'TEXT',
            'json' => 'JSON',
            default => 'VARCHAR(' . max(1, $maxLength > 0 ? $maxLength : 190) . ')',
        };

        $nullableSql = $isRequired ? 'NOT NULL' : 'NULL';
        $defaultSql = '';
        if ($defaultValue !== null && $defaultValue !== '') {
            $escaped = DB::conn()->real_escape_string((string)$defaultValue);
            $defaultSql = " DEFAULT '{$escaped}'";
        }

        return trim($typeSql . ' ' . $nullableSql . $defaultSql);
    }

    public static function tableList(): array
    {
        $rows = DB::fetchAll('SHOW TABLES');
        $tables = [];
        foreach ($rows as $row) {
            $first = array_values($row)[0] ?? null;
            if (is_string($first) && $first !== '') {
                $tables[] = $first;
            }
        }
        sort($tables);
        return $tables;
    }

    public static function makeLabel(string $tableName): string
    {
        $label = str_replace('_', ' ', strtolower($tableName));
        $label = ucwords(trim($label));
        return $label !== '' ? $label : $tableName;
    }

    public static function makeModuleKey(string $tableName): string
    {
        $key = strtolower(trim($tableName));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        $key = trim($key, '_');
        return $key !== '' ? $key : 'module';
    }
}
