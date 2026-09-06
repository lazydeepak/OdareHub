<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class StudioDataContractService
{
    /**
     * @param array<string,mixed> $bundle
     * @param array<string,mixed> $importModel
     * @return array<string,mixed>
     */
    public static function extract(array $bundle, array $importModel = []): array
    {
        $viewDefinition = is_array($bundle['view_definition'] ?? null) ? $bundle['view_definition'] : [];
        $moduleManifest = is_array($bundle['module_manifest'] ?? null) ? $bundle['module_manifest'] : [];

        $fields = self::extractFields($moduleManifest, $viewDefinition);
        $bindings = self::extractBindings($viewDefinition, $importModel);
        $requiredFields = self::extractRequiredFields($viewDefinition);

        $knownBindingPrefixes = [
            'module.rows',
            'module.fields',
            'module.metrics',
            'module.description',
            'module.meta',
        ];

        $dataSources = self::extractDataSources($viewDefinition, $bindings);

        $unknownBindings = [];
        foreach ($bindings as $binding) {
            $path = trim((string)($binding['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $known = false;
            foreach ($knownBindingPrefixes as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    $known = true;
                    break;
                }
            }
            if (!$known) {
                $unknownBindings[] = $path;
            }
        }

        $fieldMap = [];
        foreach ($fields as $field) {
            $key = trim((string)($field['key'] ?? ''));
            if ($key !== '') {
                $fieldMap[$key] = true;
            }
        }

        $missingRequiredFields = [];
        foreach ($requiredFields as $requiredField) {
            if (!isset($fieldMap[$requiredField])) {
                $missingRequiredFields[] = $requiredField;
            }
        }

        $checks = [
            [
                'key' => 'bindings_extracted',
                'ok' => $bindings !== [],
                'detail' => ['count' => count($bindings)],
            ],
            [
                'key' => 'bindings_known',
                'ok' => $unknownBindings === [],
                'detail' => ['unknown_paths' => $unknownBindings],
            ],
            [
                'key' => 'required_fields_resolved',
                'ok' => $missingRequiredFields === [],
                'detail' => ['missing_fields' => $missingRequiredFields],
            ],
            [
                'key' => 'data_sources_declared',
                'ok' => $dataSources !== [],
                'detail' => ['count' => count($dataSources)],
            ],
        ];

        $passedChecks = 0;
        foreach ($checks as $check) {
            if (!empty($check['ok'])) {
                $passedChecks++;
            }
        }

        $confidence = 'none';
        if ($passedChecks === count($checks) && $passedChecks > 0) {
            $confidence = 'full';
        } elseif ($passedChecks >= 2) {
            $confidence = 'partial';
        }

        $issues = [];
        if ($unknownBindings !== []) {
            $issues[] = 'unknown_bindings';
        }
        if ($missingRequiredFields !== []) {
            $issues[] = 'missing_required_fields';
        }
        if ($dataSources === []) {
            $issues[] = 'missing_data_sources';
        }

        return [
            'fields' => $fields,
            'bindings' => $bindings,
            'required_fields' => $requiredFields,
            'data_sources' => $dataSources,
            'checks' => $checks,
            'issues' => $issues,
            'confidence' => $confidence,
            'summary' => [
                'fields' => count($fields),
                'bindings' => count($bindings),
                'required_fields' => count($requiredFields),
                'data_sources' => count($dataSources),
                'checks_total' => count($checks),
                'checks_passed' => $passedChecks,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $moduleManifest
     * @param array<string,mixed> $viewDefinition
     * @return array<int,array<string,mixed>>
     */
    private static function extractFields(array $moduleManifest, array $viewDefinition): array
    {
        $fieldSources = [];
        if (is_array($moduleManifest['fields'] ?? null)) {
            $fieldSources[] = $moduleManifest['fields'];
        }
        if (is_array($viewDefinition['fields'] ?? null)) {
            $fieldSources[] = $viewDefinition['fields'];
        }
        $view = is_array($viewDefinition['view'] ?? null) ? $viewDefinition['view'] : [];
        if (is_array($view['fields'] ?? null)) {
            $fieldSources[] = $view['fields'];
        }
        $dataContract = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];
        if (is_array($dataContract['required_fields'] ?? null)) {
            $fieldSources[] = array_map(
                static fn($fieldKey): array => ['key' => (string)$fieldKey, 'type' => 'string', 'required' => true],
                $dataContract['required_fields']
            );
        }

        $fieldMap = [];
        foreach ($fieldSources as $fieldsRaw) {
            foreach ($fieldsRaw as $field) {
                $fieldRow = is_array($field) ? $field : ['key' => (string)$field];
                $key = trim((string)($fieldRow['key'] ?? $fieldRow['name'] ?? ''));
                if ($key === '') {
                    continue;
                }
                $fieldMap[$key] = [
                    'key' => $key,
                    'type' => strtolower((string)($fieldRow['type'] ?? ($fieldMap[$key]['type'] ?? 'string'))),
                    'required' => !empty($fieldRow['required']) || !empty($fieldMap[$key]['required']),
                ];
            }
        }

        return array_values($fieldMap);
    }

    /**
     * @param array<string,string> $binding
     * @param array<string,bool> $seen
     * @param array<int,array<string,string>> $bindings
     */
    private static function appendBinding(array $binding, array &$seen, array &$bindings): void
    {
        $itemId = trim((string)($binding['item_id'] ?? ''));
        $path = trim((string)($binding['path'] ?? $binding['data_binding'] ?? ''));
        if ($itemId === '' || $path === '') {
            return;
        }
        $dedupeKey = $itemId . '|' . $path;
        if (isset($seen[$dedupeKey])) {
            return;
        }
        $seen[$dedupeKey] = true;
        $bindings[] = ['item_id' => $itemId, 'path' => $path];
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,string>
     */
    private static function layoutItemBinding(array $item): array
    {
        $props = is_array($item['props'] ?? null) ? $item['props'] : [];
        $path = trim((string)($item['data_binding'] ?? ''));
        if ($path === '') {
            $path = trim((string)($props['data_binding'] ?? $props['binding'] ?? $props['source'] ?? ''));
        }
        return [
            'item_id' => trim((string)($item['id'] ?? $item['item_id'] ?? '')),
            'path' => $path,
        ];
    }

    /**
     * @param array<string,mixed> $viewDefinition
     * @return array<int,array<string,mixed>>
     */
    private static function extractLayoutItems(array $viewDefinition): array
    {
        $layout = is_array($viewDefinition['layout'] ?? null) ? $viewDefinition['layout'] : [];
        $items = is_array($layout['items'] ?? null)
            ? array_values(array_filter($layout['items'], 'is_array'))
            : [];
        if ($items !== []) {
            return $items;
        }

        $rows = is_array($layout['structure'] ?? null) ? $layout['structure'] : [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowItems = is_array($row['items'] ?? null) ? $row['items'] : [];
            foreach ($rowItems as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $viewDefinition
     * @param array<string,mixed> $importModel
     * @return array<int,array<string,string>>
     */
    private static function extractBindings(array $viewDefinition, array $importModel): array
    {
        $bindings = [];
        $seen = [];

        $componentBindings = is_array($viewDefinition['component_bindings'] ?? null)
            ? array_values(array_filter($viewDefinition['component_bindings'], 'is_array'))
            : [];
        foreach ($componentBindings as $binding) {
            self::appendBinding([
                'item_id' => (string)($binding['item_id'] ?? $binding['id'] ?? ''),
                'path' => (string)($binding['path'] ?? $binding['data_binding'] ?? ''),
            ], $seen, $bindings);
        }

        foreach (self::extractLayoutItems($viewDefinition) as $item) {
            self::appendBinding(self::layoutItemBinding($item), $seen, $bindings);
        }

        if (is_array($importModel['bindings'] ?? null)) {
            foreach ($importModel['bindings'] as $itemId => $path) {
                self::appendBinding([
                    'item_id' => (string)$itemId,
                    'path' => (string)$path,
                ], $seen, $bindings);
            }
        }

        return $bindings;
    }

    /**
     * @param array<string,mixed> $viewDefinition
     * @return array<int,string>
     */
    private static function extractRequiredFields(array $viewDefinition): array
    {
        $dataContract = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];
        $requiredRaw = is_array($dataContract['required_fields'] ?? null)
            ? $dataContract['required_fields']
            : [];

        $required = [];
        foreach ($requiredRaw as $fieldKey) {
            $key = trim((string)$fieldKey);
            if ($key !== '') {
                $required[$key] = true;
            }
        }

        return array_values(array_keys($required));
    }

    /**
     * @param array<string,mixed> $viewDefinition
     * @param array<int,array<string,string>> $bindings
     * @return array<int,string>
     */
    private static function extractDataSources(array $viewDefinition, array $bindings): array
    {
        $dataContract = is_array($viewDefinition['data_contract'] ?? null) ? $viewDefinition['data_contract'] : [];
        $dataSourcesRaw = is_array($dataContract['data_sources'] ?? null) ? $dataContract['data_sources'] : [];

        $sources = [];
        foreach ($dataSourcesRaw as $source) {
            if (is_array($source)) {
                $value = trim((string)($source['path'] ?? $source['source'] ?? $source['key'] ?? $source['id'] ?? ''));
            } else {
                $value = trim((string)$source);
            }
            if ($value !== '') {
                $sources[$value] = true;
            }
        }

        foreach ($bindings as $binding) {
            $path = trim((string)($binding['path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $segments = explode('.', $path);
            if (count($segments) >= 2) {
                $sources[$segments[0] . '.' . $segments[1]] = true;
            }
        }

        return array_values(array_keys($sources));
    }
}
