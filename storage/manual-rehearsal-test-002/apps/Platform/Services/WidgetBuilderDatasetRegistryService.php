<?php
declare(strict_types=1);

namespace Apps\Platform\Services;

use App\Core\DB;

final class WidgetBuilderDatasetRegistryService
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function catalog(): array
    {
        return self::loadContributedDatasets();
    }

    public static function hasDataset(string $datasetKey): bool
    {
        $datasetKey = trim($datasetKey);
        if ($datasetKey === '') {
            return false;
        }

        foreach (self::catalog() as $dataset) {
            if ((string)($dataset['dataset_key'] ?? '') === $datasetKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findDataset(string $datasetKey): ?array
    {
        $datasetKey = trim($datasetKey);
        if ($datasetKey === '') {
            return null;
        }

        foreach (self::catalog() as $dataset) {
            if ((string)($dataset['dataset_key'] ?? '') === $datasetKey) {
                return $dataset;
            }
        }

        return null;
    }

    /**
     * @return array<int,string>
     */
    public static function allowedRuntimeFields(string $datasetKey): array
    {
        $dataset = self::findDataset($datasetKey);
        if (!is_array($dataset)) {
            return [];
        }

        return self::stringList((array)($dataset['allowed_runtime_fields'] ?? []));
    }

    public static function isAllowedRuntimeField(string $datasetKey, string $field): bool
    {
        $field = trim($field);
        if ($field === '') {
            return false;
        }

        return in_array($field, self::allowedRuntimeFields($datasetKey), true);
    }

    public static function isAllowedAggregation(string $datasetKey, string $aggregation): bool
    {
        $dataset = self::findDataset($datasetKey);
        if (!is_array($dataset)) {
            return false;
        }

        $allowed = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($dataset['allowed_aggregations'] ?? [])
        );
        $needle = strtolower(trim($aggregation));

        return $needle !== '' && in_array($needle, $allowed, true);
    }

    public static function isAllowedFilter(string $datasetKey, string $filter): bool
    {
        $dataset = self::findDataset($datasetKey);
        if (!is_array($dataset)) {
            return false;
        }

        $allowed = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($dataset['allowed_filters'] ?? [])
        );
        $needle = strtolower(trim($filter));

        return $needle !== '' && in_array($needle, $allowed, true);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function loadContributedDatasets(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }

        $catalog = [];
        foreach (self::loadEnabledAppManifests() as $manifest) {
            $installPath = trim((string)($manifest['__install_path'] ?? ''));
            if ($installPath === '') {
                continue;
            }

            foreach ((array)($manifest['hooks'] ?? []) as $hook) {
                if (!is_array($hook)) {
                    continue;
                }
                if (strtolower(trim((string)($hook['type'] ?? ''))) !== 'widget_builder_dataset') {
                    continue;
                }

                $provider = trim((string)($hook['provider'] ?? ''));
                $providerFile = trim((string)($hook['provider_file'] ?? ''));
                if ($provider === '' || $providerFile === '') {
                    continue;
                }

                $providerAbsoluteFile = rtrim($installPath, '/') . '/' . ltrim($providerFile, '/');
                if (!is_file($providerAbsoluteFile)) {
                    continue;
                }

                $parts = explode('::', $provider, 2);
                $class = trim((string)($parts[0] ?? ''));
                $method = trim((string)($parts[1] ?? 'contribute'));
                if ($class === '' || $method === '') {
                    continue;
                }

                try {
                    if (!class_exists($class, false)) {
                        require_once $providerAbsoluteFile;
                    }
                    if (!class_exists($class) || !method_exists($class, $method)) {
                        continue;
                    }

                    $result = $class::$method([
                        'surface' => 'widget_builder',
                        'context' => [],
                    ]);
                } catch (\Throwable) {
                    continue;
                }

                $datasets = is_array($result) && is_array($result['datasets'] ?? null)
                    ? (array)$result['datasets']
                    : [];
                foreach ($datasets as $dataset) {
                    if (!is_array($dataset)) {
                        continue;
                    }
                    $normalized = self::normalizeDataset($dataset);
                    if ($normalized !== null) {
                        $catalog[(string)$normalized['dataset_key']] = $normalized;
                    }
                }
            }
        }

        $cache = array_values($catalog);
        return $cache;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function loadEnabledAppManifests(): array
    {
        try {
            $rows = DB::fetchAll(
                "SELECT app_key, install_path, status
                   FROM core_apps
                  WHERE LOWER(COALESCE(status, '')) IN ('enabled', 'active')
                  ORDER BY app_key"
            );
        } catch (\Throwable) {
            return [];
        }

        $manifests = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $installPath = trim((string)($row['install_path'] ?? ''));
            if ($installPath === '' || !is_dir($installPath)) {
                continue;
            }

            $manifestFile = rtrim($installPath, '/') . '/manifest.json';
            if (!is_file($manifestFile)) {
                continue;
            }

            $manifest = json_decode((string)file_get_contents($manifestFile), true);
            if (!is_array($manifest)) {
                continue;
            }
            $manifest['__install_path'] = $installPath;
            $manifests[] = $manifest;
        }

        return $manifests;
    }

    /**
     * @param array<string,mixed> $dataset
     * @return array<string,mixed>|null
     */
    private static function normalizeDataset(array $dataset): ?array
    {
        $datasetKey = strtolower(trim((string)($dataset['dataset_key'] ?? '')));
        $appKey = strtolower(trim((string)($dataset['app_key'] ?? '')));
        $moduleKey = strtolower(trim((string)($dataset['module_key'] ?? '')));
        $labelKey = trim((string)($dataset['label_key'] ?? ''));
        $descriptionKey = trim((string)($dataset['description_key'] ?? ''));

        if ($datasetKey === '' || $appKey === '' || $moduleKey === '' || $labelKey === '') {
            return null;
        }

        return [
            'dataset_key' => $datasetKey,
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'label_key' => $labelKey,
            'description_key' => $descriptionKey,
            'allowed_fields' => self::stringList((array)($dataset['allowed_fields'] ?? [])),
            'allowed_filters' => self::stringList((array)($dataset['allowed_filters'] ?? [])),
            'allowed_aggregations' => self::stringList((array)($dataset['allowed_aggregations'] ?? [])),
            'allowed_runtime_fields' => self::stringList((array)($dataset['allowed_runtime_fields'] ?? [])),
            'template_defaults' => self::templateDefaults((array)($dataset['template_defaults'] ?? [])),
        ];
    }

    /**
     * @param array<int|string,mixed> $values
     * @return array<int,string>
     */
    private static function stringList(array $values): array
    {
        return array_values(array_filter(array_map(
            static fn ($value): string => trim((string)$value),
            $values
        ), static fn (string $value): bool => $value !== ''));
    }

    /**
     * @param array<string,mixed> $defaults
     * @return array<string,array<string,mixed>>
     */
    private static function templateDefaults(array $defaults): array
    {
        $normalized = [];
        foreach ($defaults as $templateType => $config) {
            $templateKey = strtolower(trim((string)$templateType));
            if ($templateKey === '' || !is_array($config)) {
                continue;
            }
            $normalized[$templateKey] = $config;
        }

        return $normalized;
    }
}
