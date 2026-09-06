<?php
declare(strict_types=1);

namespace Generated\RollbackApp\Providers;

final class RollbackModuleProvider
{
    /** @return array<int,array<string,mixed>> */
    public static function fields(): array
    {
        return [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'string',
                'required' => true,
                'default' => '',
                'options' => [],
            ],
            [
                'key' => 'version',
                'label' => 'Version',
                'type' => 'string',
                'required' => false,
                'default' => '',
                'options' => [],
            ],
        ];
    }

    /** @param array<string,mixed> $query @return array<string,mixed> */
    public static function records(array $query = []): array
    {
        $rows = self::readRows();
        $search = strtolower(trim((string)($query['q'] ?? '')));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, static function (array $row) use ($search): bool {
                foreach ($row as $value) {
                    if (str_contains(strtolower((string)$value), $search)) {
                        return true;
                    }
                }
                return false;
            }));
        }
        $filters = [];
        foreach (self::fields() as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '' || (string)($field['type'] ?? '') !== 'select') {
                continue;
            }
            $filterKey = 'filter_' . $key;
            $filterValue = trim((string)($query[$filterKey] ?? ''));
            if ($filterValue === '') {
                continue;
            }
            $filters[$key] = $filterValue;
            $rows = array_values(array_filter($rows, static fn(array $row): bool => (string)($row[$key] ?? '') === $filterValue));
        }
        $sort = self::fieldKey((string)($query['sort'] ?? ''));
        $dir = strtolower((string)($query['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
        if ($sort !== '' && self::fieldByKey($sort) !== null) {
            usort($rows, static function (array $a, array $b) use ($sort, $dir): int {
                $cmp = strnatcasecmp((string)($a[$sort] ?? ''), (string)($b[$sort] ?? ''));
                return $dir === 'desc' ? -$cmp : $cmp;
            });
        }
        return ['rows' => array_map([self::class, 'formatRow'], $rows), 'filters' => $filters, 'sort' => ['field' => $sort, 'dir' => $dir]];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function save(array $input): array
    {
        $validation = self::validate($input);
        if (empty($validation['valid'])) {
            return ['ok' => false, 'message' => 'validation_failed', 'errors' => $validation['errors'] ?? []];
        }
        $rows = self::readRows();
        $rowId = self::recordId((string)($input['row_id'] ?? ''));
        $row = $rowId !== '' ? self::findRow($rows, $rowId) : [];
        $row['id'] = $rowId !== '' ? $rowId : self::newId();
        $row['updated_at'] = gmdate('c');
        if (!isset($row['created_at'])) {
            $row['created_at'] = gmdate('c');
        }
        foreach (self::fields() as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $row[$key] = $validation['values'][$key] ?? '';
        }
        $updated = false;
        foreach ($rows as $index => $existing) {
            if ((string)($existing['id'] ?? '') === (string)$row['id']) {
                $rows[$index] = $row;
                $updated = true;
                break;
            }
        }
        if (!$updated) {
            $rows[] = $row;
        }
        return self::writeRows($rows) ? ['ok' => true, 'message' => 'generated_row_saved', 'row_id' => (string)$row['id']] : ['ok' => false, 'message' => 'generated_row_save_failed'];
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    public static function validate(array $input): array
    {
        $errors = [];
        $values = [];
        foreach (self::fields() as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $value = trim((string)($input[$key] ?? ($field['default'] ?? '')));
            if (!empty($field['required']) && $value === '') {
                $errors[] = 'required:' . $key;
            }
            $type = (string)($field['type'] ?? 'string');
            if ($type === 'integer') {
                if ($value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                    $errors[] = 'type_integer:' . $key;
                }
                $values[$key] = $value === '' ? '' : (string)(int)$value;
                continue;
            }
            if ($type === 'select') {
                $options = array_map('strval', (array)($field['options'] ?? []));
                if ($value === '' && $options !== []) {
                    $value = (string)($field['default'] ?? $options[0]);
                }
                if ($value !== '' && !in_array($value, $options, true)) {
                    $errors[] = 'option:' . $key;
                    $value = (string)($field['default'] ?? ($options[0] ?? ''));
                }
                $values[$key] = $value;
                continue;
            }
            $values[$key] = substr(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '', 0, 240);
        }
        return ['valid' => $errors === [], 'errors' => array_values(array_unique($errors)), 'values' => $values];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function formatRow(array $row): array
    {
        foreach (self::fields() as $field) {
            $key = (string)($field['key'] ?? '');
            if ($key !== '' && !array_key_exists($key, $row)) {
                $row[$key] = (string)($field['default'] ?? '');
            }
        }
        return $row;
    }

    private static function dataPath(): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 5)), '/') . '/storage/appstudio/generated_data/rollback_app/rollback_module.json';
    }

    /** @return array<int,array<string,mixed>> */
    private static function readRows(): array
    {
        $path = self::dataPath();
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        $decoded = $raw !== false ? json_decode((string)$raw, true) : null;
        $rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : [];
        return array_values(array_filter($rows, 'is_array'));
    }

    /** @param array<int,array<string,mixed>> $rows */
    private static function writeRows(array $rows): bool
    {
        $path = self::dataPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            return false;
        }
        $payload = ['schema_version' => 'studio.generated-data.v1', 'app_key' => 'rollback_app', 'module_key' => 'rollback_module', 'updated_at' => gmdate('c'), 'rows' => array_values($rows)];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || json_decode($json, true) === null) {
            return false;
        }
        $tmp = $dir . '/.rollback_module.' . bin2hex(random_bytes(4)) . '.tmp';
        return file_put_contents($tmp, $json, LOCK_EX) !== false && rename($tmp, $path);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<string,mixed> */
    private static function findRow(array $rows, string $rowId): array
    {
        foreach ($rows as $row) {
            if ((string)($row['id'] ?? '') === $rowId) {
                return $row;
            }
        }
        return [];
    }

    private static function recordId(string $value): string
    {
        return preg_match('/^[a-f0-9\-]{16,64}$/i', $value) ? $value : '';
    }

    private static function newId(): string
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
    }

    private static function fieldKey(string $value): string
    {
        $value = strtolower(trim(str_replace('-', '_', $value)));
        $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private static function fieldByKey(string $key): ?array
    {
        foreach (self::fields() as $field) {
            if ((string)($field['key'] ?? '') === $key) {
                return $field;
            }
        }
        return null;
    }
}
