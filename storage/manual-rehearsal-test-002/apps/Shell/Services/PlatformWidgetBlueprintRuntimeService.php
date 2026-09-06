<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\WidgetBlueprintRuntimeContributionService;

/**
 * PlatformWidgetBlueprintRuntimeService
 *
 * Resolves published platform-managed widget blueprints into operator dashboard tiles.
 * Phase-1 scope: informative summary tiles only; no mutating actions.
 */
final class PlatformWidgetBlueprintRuntimeService
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function resolveDashboardTiles(array $context): array
    {
        $assignedApps = self::assignedApps($context);
        if ($assignedApps === []) {
            return [];
        }

        try {
            $placeholders = implode(',', array_fill(0, count($assignedApps), '?'));
            $rows = DB::fetchAll(
                "SELECT id, app_key, module_key, widget_key, title_key, description_key, template_type,
                        dataset_key, placement_zone, config_json
                 FROM platform_widget_blueprints
                 WHERE status='published'
                   AND app_key IN ({$placeholders})
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 30",
                $assignedApps
            );
        } catch (\Throwable) {
            return [];
        }

        $tiles = [];
        foreach ((array)$rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $tile = self::rowToTile($row);
            if (is_array($tile)) {
                $tiles[] = $tile;
            }
        }

        return $tiles;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private static function rowToTile(array $row): ?array
    {
        $datasetKey = trim((string)($row['dataset_key'] ?? ''));
        if ($datasetKey === '') {
            return null;
        }

        $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
        $moduleKey = strtolower(trim((string)($row['module_key'] ?? '')));
        if (!self::isDatasetOwnershipValid($datasetKey, $appKey, $moduleKey)) {
            return null;
        }

        $titleKey = trim((string)($row['title_key'] ?? ''));
        $widgetKey = trim((string)($row['widget_key'] ?? ''));
        $descriptionKey = trim((string)($row['description_key'] ?? ''));
        $placementZone = trim((string)($row['placement_zone'] ?? 'dashboard_summary'));

        $config = self::decodeConfig((string)($row['config_json'] ?? ''));
        $metricField = trim((string)($config['metric_field'] ?? ''));
        $subtitleField = trim((string)($config['subtitle_field'] ?? ''));

        $dataset = self::loadDataset($datasetKey);
        if ($dataset === null) {
            return null;
        }

        $flat = self::flattenScalarValues($dataset);
        $value = self::resolveMetricValue($datasetKey, $flat, $metricField);
        $subtitle = self::resolveSubtitle($flat, $subtitleField, $descriptionKey);

        $tileLabel = self::translate($titleKey, $widgetKey !== '' ? $widgetKey : 'generated_widget');

        $items = [];
        $topPairs = self::topPreviewItems($flat, 3);
        foreach ($topPairs as $pair) {
            $items[] = (string)$pair;
        }

        return [
            'key' => $widgetKey !== '' ? $widgetKey : ('blueprint_' . (string)($row['id'] ?? '0')),
            'label' => $tileLabel,
            'value' => $value,
            'subtitle' => $subtitle,
            'items' => $items,
            'placement_zone' => $placementZone,
            'primary' => false,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,string>
     */
    private static function assignedApps(array $context): array
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );

        return array_values(array_filter(array_unique($apps), static fn (string $app): bool => $app !== ''));
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadDataset(string $datasetKey): ?array
    {
        if (class_exists(WidgetBlueprintRuntimeContributionService::class)) {
            return WidgetBlueprintRuntimeContributionService::loadDataset($datasetKey);
        }

        return null;
    }

    /**
     * @param array<string,mixed> $dataset
     * @return array<string,string>
     */
    private static function flattenScalarValues(array $dataset): array
    {
        $flat = [];
        self::walkScalars($dataset, '', $flat);
        return $flat;
    }

    /**
     * @param mixed $node
     * @param array<string,string> $flat
     */
    private static function walkScalars(mixed $node, string $prefix, array &$flat): void
    {
        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $path = $prefix === '' ? (string)$key : ($prefix . '.' . (string)$key);
                self::walkScalars($value, $path, $flat);
            }
            return;
        }

        if (is_scalar($node) || $node === null) {
            $flat[$prefix] = self::stringifyScalar($node);
        }
    }

    private static function stringifyScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }
        return (string)$value;
    }

    /**
     * @param array<string,string> $flat
     */
    private static function resolveMetricValue(string $datasetKey, array $flat, string $metricField): string
    {
        if ($metricField !== '' && array_key_exists($metricField, $flat)) {
            return $flat[$metricField];
        }

        $preferred = class_exists(WidgetBlueprintRuntimeContributionService::class)
            ? WidgetBlueprintRuntimeContributionService::preferredMetricCandidates($datasetKey)
            : [];

        foreach ($preferred as $candidate) {
            if (array_key_exists($candidate, $flat)) {
                return $flat[$candidate];
            }
        }

        foreach ($flat as $value) {
            if ($value !== '') {
                return $value;
            }
        }

        return '0';
    }

    /**
     * @param array<string,string> $flat
     */
    private static function resolveSubtitle(array $flat, string $subtitleField, string $descriptionKey): string
    {
        if ($subtitleField !== '' && array_key_exists($subtitleField, $flat)) {
            return $flat[$subtitleField];
        }

        if ($descriptionKey !== '') {
            return self::translate($descriptionKey, $descriptionKey);
        }

        return '';
    }

    /**
     * @param array<string,string> $flat
     * @return array<int,string>
     */
    private static function topPreviewItems(array $flat, int $limit): array
    {
        $items = [];
        foreach ($flat as $key => $value) {
            if ($value === '') {
                continue;
            }
            $items[] = $key . ': ' . $value;
            if (count($items) >= $limit) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    private static function decodeConfig(string $raw): array
    {
        if (trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function translate(string $key, string $fallback): string
    {
        $key = trim($key);
        if ($key === '') {
            return $fallback;
        }

        if (function_exists('t')) {
            // Try exact key first
            $translated = (string)t($key);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }

            // Try with .title suffix for app view titles (e.g., app.manufacturing.daily_orders.index.table.title)
            if (strpos($key, 'app.') === 0) {
                $titleKey = $key . '.title';
                $translated = (string)t($titleKey);
                if ($translated !== '' && $translated !== $titleKey) {
                    return $translated;
                }
            }
        }

        return $fallback;
    }

    private static function isDatasetOwnershipValid(string $datasetKey, string $appKey, string $moduleKey): bool
    {
        if (!class_exists(\Apps\Platform\Services\WidgetBuilderDatasetRegistryService::class)) {
            return true;
        }

        $dataset = \Apps\Platform\Services\WidgetBuilderDatasetRegistryService::findDataset($datasetKey);
        if (!is_array($dataset)) {
            return false;
        }

        $datasetApp = strtolower(trim((string)($dataset['app_key'] ?? '')));
        $datasetModule = strtolower(trim((string)($dataset['module_key'] ?? '')));

        if ($datasetApp !== '' && $appKey !== '' && $datasetApp !== $appKey) {
            return false;
        }
        if ($datasetModule !== '' && $moduleKey !== '' && $datasetModule !== $moduleKey) {
            return false;
        }

        return true;
    }
}
