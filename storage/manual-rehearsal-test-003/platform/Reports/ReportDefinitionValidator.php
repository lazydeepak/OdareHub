<?php
declare(strict_types=1);

namespace Platform\Reports;

final class ReportDefinitionValidator
{
    public const SCHEMA_VERSION = '1.0';

    private const REQUIRED_ARRAY_SECTIONS = [
        'selected_sources',
        'selected_fields',
        'context_inputs',
        'layout_configuration',
        'presentation_configuration',
        'preview_defaults',
    ];
    private const FORBIDDEN_KEYS = [
        'sql',
        'query',
        'queries',
        'joins',
        'rows',
        'report_rows',
        'execution',
        'runtime_execution',
        'export',
        'exports',
        'pdf',
        'csv',
        'excel',
        'schedule',
    ];

    /**
     * @param array<int,string> $existingKeys
     * @return array{valid:bool,errors:array<int,string>}
     */
    public static function validate(array $definition, array $existingKeys = [], string $originalKey = ''): array
    {
        $errors = [];
        $reportKey = trim((string)($definition['report_key'] ?? ''));

        if ((string)($definition['schema_version'] ?? '') !== self::SCHEMA_VERSION) {
            $errors[] = 'Unsupported report definition schema version.';
        }
        if ($reportKey === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $reportKey) !== 1) {
            $errors[] = 'Report key must use lowercase letters, numbers, and single hyphens.';
        }
        if (trim((string)($definition['report_name'] ?? '')) === '') {
            $errors[] = 'Report name is required.';
        }
        if (trim((string)($definition['suite'] ?? '')) === '') {
            $errors[] = 'Suite is required.';
        }
        foreach (self::REQUIRED_ARRAY_SECTIONS as $section) {
            if (!array_key_exists($section, $definition) || !is_array($definition[$section])) {
                $errors[] = 'Missing or invalid section: ' . $section;
            }
        }
        if ($reportKey !== '' && $reportKey !== $originalKey && in_array($reportKey, $existingKeys, true)) {
            $errors[] = 'Report key already exists.';
        }
        if ($originalKey !== '' && $reportKey !== $originalKey) {
            $errors[] = 'Report key cannot be changed after the definition is created.';
        }
        if (isset($definition['selected_sources']) && is_array($definition['selected_sources'])) {
            if ($definition['selected_sources'] === []) {
                $errors[] = 'At least one source is required.';
            }
            foreach ($definition['selected_sources'] as $source) {
                if (!is_string($source) || trim($source) === '') {
                    $errors[] = 'Selected sources must contain non-empty source keys.';
                    break;
                }
            }
        }
        if (isset($definition['selected_fields']) && is_array($definition['selected_fields'])) {
            if ($definition['selected_fields'] === []) {
                $errors[] = 'At least one field is required.';
            }
            foreach ($definition['selected_fields'] as $field) {
                if (!is_array($field) || trim((string)($field['key'] ?? '')) === '') {
                    $errors[] = 'Selected fields must contain structured field definitions.';
                    break;
                }
            }
        }
        self::findForbiddenKeys($definition, '', $errors);

        return ['valid' => $errors === [], 'errors' => array_values(array_unique($errors))];
    }

    public static function generateKey(string $reportName): string
    {
        $key = strtolower(trim($reportName));
        $key = preg_replace('/[^a-z0-9]+/', '-', $key) ?? '';
        return trim($key, '-');
    }

    private static function findForbiddenKeys(array $value, string $path, array &$errors): void
    {
        foreach ($value as $key => $item) {
            $keyName = strtolower((string)$key);
            $itemPath = $path === '' ? $keyName : $path . '.' . $keyName;
            if (in_array($keyName, self::FORBIDDEN_KEYS, true)) {
                $errors[] = 'Forbidden runtime or query section: ' . $itemPath;
            }
            if (is_array($item)) {
                self::findForbiddenKeys($item, $itemPath, $errors);
            }
        }
    }
}
