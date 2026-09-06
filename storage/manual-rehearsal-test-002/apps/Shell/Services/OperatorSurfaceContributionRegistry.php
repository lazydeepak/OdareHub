<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

/**
 * OperatorSurfaceContributionRegistry
 *
 * Loads app-owned operator surface contributions from app manifest hooks.
 * Supported hook contract:
 * - type: operator_surface
 * - surface: operator
 * - region: sidebar | focus_views | data_exchange | breadcrumb_parents | focus_labels | bottom_actions
 */
final class OperatorSurfaceContributionRegistry
{
    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function sidebarSections(array $context): array
    {
        $sections = [];
        foreach (self::loadOperatorSurfacePayloads($context, 'sidebar') as $payload) {
            $candidate = is_array($payload['sections'] ?? null)
                ? (array)$payload['sections']
                : (is_array($payload) ? (array)$payload : []);

            foreach ($candidate as $section) {
                if (!is_array($section)) {
                    continue;
                }
                $title = trim((string)($section['title'] ?? ''));
                $items = is_array($section['items'] ?? null) ? (array)$section['items'] : [];
                if ($title === '' || $items === []) {
                    continue;
                }
                $sections[] = [
                    'title' => $title,
                    'items' => $items,
                ];
            }
        }

        return $sections;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,string>
     */
    public static function focusViewMap(array $context): array
    {
        $map = [];
        foreach (self::loadOperatorSurfacePayloads($context, 'focus_views') as $payload) {
            $candidate = is_array($payload['view_map'] ?? null)
                ? (array)$payload['view_map']
                : (is_array($payload) ? (array)$payload : []);

            foreach ($candidate as $focus => $path) {
                $focusKey = strtolower(trim((string)$focus));
                $viewPath = trim((string)$path);
                if ($focusKey === '' || $viewPath === '') {
                    continue;
                }
                if (!str_starts_with($viewPath, '/')) {
                    $viewPath = APP_ROOT . '/' . ltrim($viewPath, '/');
                }
                if (!is_file($viewPath)) {
                    continue;
                }
                $map[$focusKey] = $viewPath;
            }
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    public static function dataExchangeDefinitions(array $context): array
    {
        $definitions = [];
        foreach (self::loadOperatorSurfacePayloads($context, 'data_exchange') as $payload) {
            $candidate = is_array($payload['definitions'] ?? null)
                ? (array)$payload['definitions']
                : [];

            foreach ($candidate as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $key = strtolower(trim((string)($definition['key'] ?? '')));
                $appKey = strtolower(trim((string)($definition['app_key'] ?? '')));
                $moduleKey = strtolower(trim((string)($definition['module_key'] ?? '')));
                $title = trim((string)($definition['title'] ?? ''));
                if ($key === '' || $appKey === '' || $moduleKey === '' || $title === '') {
                    continue;
                }

                $definitions[] = [
                    'key' => $key,
                    'app_key' => $appKey,
                    'module_key' => $moduleKey,
                    'title' => $title,
                    'description' => trim((string)($definition['description'] ?? '')),
                    'supports_import' => (bool)($definition['supports_import'] ?? false),
                    'supports_export' => (bool)($definition['supports_export'] ?? false),
                    'template_name' => trim((string)($definition['template_name'] ?? '')),
                    'governance_mode' => trim((string)($definition['governance_mode'] ?? 'audited')),
                    'import_columns' => array_values(array_filter(array_map(
                        static fn ($value): string => trim((string)$value),
                        (array)($definition['import_columns'] ?? [])
                    ), static fn (string $value): bool => $value !== '')),
                    'export_columns' => array_values(array_filter(array_map(
                        static fn ($value): string => trim((string)$value),
                        (array)($definition['export_columns'] ?? [])
                    ), static fn (string $value): bool => $value !== '')),
                ];
            }
        }

        return $definitions;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,array<string,string>>
     */
    public static function breadcrumbParents(array $context): array
    {
        $parents = [];
        foreach (self::loadOperatorSurfacePayloads($context, 'breadcrumb_parents') as $payload) {
            $candidate = is_array($payload['parents'] ?? null)
                ? (array)$payload['parents']
                : (is_array($payload) ? (array)$payload : []);

            foreach ($candidate as $focus => $definition) {
                if (!is_array($definition)) {
                    continue;
                }
                $focusKey = strtolower(trim((string)$focus));
                $label = trim((string)($definition['label'] ?? ''));
                $route = trim((string)($definition['route'] ?? ''));
                if ($focusKey === '' || $label === '' || $route === '') {
                    continue;
                }
                $parents[$focusKey] = [
                    'label' => $label,
                    'route' => $route,
                ];
            }
        }

        return $parents;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,string>
     */
    public static function focusLabels(array $context): array
    {
        $labels = [];
        foreach (self::loadOperatorSurfacePayloads($context, 'focus_labels') as $payload) {
            $candidate = is_array($payload['labels'] ?? null)
                ? (array)$payload['labels']
                : (is_array($payload) ? (array)$payload : []);

            foreach ($candidate as $focus => $label) {
                $focusKey = strtolower(trim((string)$focus));
                $text = trim((string)$label);
                if ($focusKey === '' || $text === '') {
                    continue;
                }
                $labels[$focusKey] = $text;
            }
        }

        return $labels;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    public static function bottomActionSets(array $context): array
    {
        $sets = [];
        foreach (self::loadOperatorSurfacePayloads($context, 'bottom_actions') as $payload) {
            $candidate = is_array($payload['action_sets'] ?? null)
                ? (array)$payload['action_sets']
                : (is_array($payload) ? (array)$payload : []);

            foreach ($candidate as $focus => $definition) {
                $focusKey = strtolower(trim((string)$focus));
                if ($focusKey === '') {
                    continue;
                }
                if (is_string($definition)) {
                    $alias = strtolower(trim($definition));
                    if ($alias !== '') {
                        $sets[$focusKey] = $alias;
                    }
                    continue;
                }
                if (!is_array($definition)) {
                    continue;
                }

                $actions = [];
                foreach ($definition as $action) {
                    if (!is_array($action)) {
                        continue;
                    }
                    $label = trim((string)($action['label'] ?? ''));
                    $href = trim((string)($action['href'] ?? ''));
                    if ($label === '' || $href === '') {
                        continue;
                    }
                    $actions[] = [
                        'icon' => trim((string)($action['icon'] ?? 'action')),
                        'label' => $label,
                        'href' => $href,
                    ];
                }
                if ($actions !== []) {
                    $sets[$focusKey] = $actions;
                }
            }
        }

        return $sets;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function loadOperatorSurfacePayloads(array $context, string $region): array
    {
        $payloads = [];
        $apps = self::activeAssignedApps($context);
        if ($apps === []) {
            return [];
        }

        foreach ($apps as $appKey) {
            $manifest = self::loadAppManifest($appKey);
            if (!is_array($manifest)) {
                continue;
            }

            foreach ((array)($manifest['hooks'] ?? []) as $hook) {
                if (!is_array($hook)) {
                    continue;
                }
                if (strtolower(trim((string)($hook['type'] ?? ''))) !== 'operator_surface') {
                    continue;
                }
                if (strtolower(trim((string)($hook['surface'] ?? ''))) !== 'operator') {
                    continue;
                }
                if (strtolower(trim((string)($hook['region'] ?? ''))) !== strtolower(trim($region))) {
                    continue;
                }

                $provider = trim((string)($hook['provider'] ?? ''));
                $providerFile = trim((string)($hook['provider_file'] ?? ''));
                if ($provider === '' || $providerFile === '') {
                    continue;
                }

                $installPath = trim((string)($manifest['__install_path'] ?? ''));
                if ($installPath === '') {
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
                        'surface' => 'operator',
                        'region' => $region,
                        'app_key' => $appKey,
                        'context' => $context,
                    ]);
                    if (is_array($result) && $result !== []) {
                        $payloads[] = $result;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $payloads;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,string>
     */
    private static function activeAssignedApps(array $context): array
    {
        $apps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($context['active_assigned_apps'] ?? $context['assigned_apps'] ?? [])
        );
        $apps = array_values(array_filter(array_unique($apps), static fn (string $value): bool => $value !== ''));

        return $apps;
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function loadAppManifest(string $appKey): ?array
    {
        static $cache = [];

        $key = strtolower(trim($appKey));
        if ($key === '') {
            return null;
        }
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $row = DB::fetchOne('SELECT install_path, status FROM core_apps WHERE LOWER(app_key)=? LIMIT 1', [$key]);
        if (!is_array($row)) {
            $cache[$key] = null;
            return null;
        }

        $status = strtolower(trim((string)($row['status'] ?? 'inactive')));
        if (!in_array($status, ['enabled', 'active'], true)) {
            $cache[$key] = null;
            return null;
        }

        $installPath = trim((string)($row['install_path'] ?? ''));
        if ($installPath === '' || !is_dir($installPath)) {
            $cache[$key] = null;
            return null;
        }

        $manifestFile = rtrim($installPath, '/') . '/manifest.json';
        if (!is_file($manifestFile)) {
            $cache[$key] = null;
            return null;
        }

        $manifest = json_decode((string)file_get_contents($manifestFile), true);
        if (!is_array($manifest)) {
            $cache[$key] = null;
            return null;
        }

        $manifest['__install_path'] = $installPath;
        $cache[$key] = $manifest;
        return $manifest;
    }
}
