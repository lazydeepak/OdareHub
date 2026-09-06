<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\OwnerStructureScan\Services;

final class OwnerStructureArtifactClassifierService
{
    private const GROUPS = [
        'structural_containers' => 'Structural Containers',
        'controllers' => 'Controllers',
        'services' => 'Services',
        'views' => 'Views',
        'routes' => 'Routes',
        'navigation' => 'Navigation',
        'localization' => 'Localization',
        'assets_css' => 'Assets / CSS',
        'assets_javascript' => 'Assets / JavaScript',
        'assets_images' => 'Assets / Images',
        'labels' => 'Labels',
        'reports' => 'Reports',
        'configuration' => 'Configuration',
        'database' => 'Database',
        'tests' => 'Tests',
        'documentation' => 'Documentation',
        'generated' => 'Generated',
        'runtime' => 'Runtime',
        'shell_root_contracts' => 'Shell Root Contracts',
        'shell_runtime_css' => 'Shell Runtime CSS',
        'shell_style_governance' => 'Shell Design System Governance',
        'shell_boot_essential_css' => 'Shell Boot / Essential CSS',
        'shell_rendering_foundation' => 'Shell Rendering Foundation',
        'shell_composition' => 'Shell Composition Layer',
        'shell_navigation_sources' => 'Shell Navigation Sources',
        'unknown' => 'Unknown / Unclassified',
    ];

    /**
     * @param array<int,array<string,mixed>> $entries
     * @return array<string,mixed>
     */
    public static function classify(array $entries): array
    {
        $groups = [];
        foreach (self::GROUPS as $key => $label) {
            $groups[$key] = [
                'key' => $key,
                'label' => $label,
                'physical_locations' => [],
                'relative_locations' => [],
                'file_count' => 0,
                'folder_count' => 0,
                'total_size' => 0,
                'entries' => [],
            ];
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $groupKey = self::groupKey($entry);
            $type = (string)($entry['type'] ?? 'file');
            if ($type === 'folder') {
                $groups[$groupKey]['folder_count']++;
            } else {
                $groups[$groupKey]['file_count']++;
                $groups[$groupKey]['total_size'] += (int)($entry['size'] ?? 0);
            }
            $groups[$groupKey]['entries'][] = $entry;

            $physicalLocation = self::locationFor((string)($entry['physical_path'] ?? ''), $type);
            $relativeLocation = self::locationFor((string)($entry['relative_path'] ?? ''), $type);
            if ($physicalLocation !== '') {
                $groups[$groupKey]['physical_locations'][$physicalLocation] = true;
            }
            if ($relativeLocation !== '') {
                $groups[$groupKey]['relative_locations'][$relativeLocation] = true;
            }
        }

        foreach ($groups as $key => $group) {
            $physical = array_keys((array)$group['physical_locations']);
            $relative = array_keys((array)$group['relative_locations']);
            natcasesort($physical);
            natcasesort($relative);
            usort($group['entries'], static function (array $a, array $b): int {
                return strcasecmp((string)($a['relative_path'] ?? ''), (string)($b['relative_path'] ?? ''));
            });
            $groups[$key]['physical_locations'] = array_values($physical);
            $groups[$key]['relative_locations'] = array_values($relative);
            $groups[$key]['entries'] = $group['entries'];
        }

        return [
            'groups' => array_values($groups),
            'unknown_count' => (int)$groups['unknown']['file_count'] + (int)$groups['unknown']['folder_count'],
        ];
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function groupKey(array $entry): string
    {
        $relativePath = (string)($entry['relative_path'] ?? '');
        $path = strtolower($relativePath);
        $name = strtolower((string)($entry['name'] ?? ''));

        $shellGroup = self::shellGroupKey($entry);
        if ($shellGroup !== '') {
            return $shellGroup;
        }
        if (self::isStructuralContainer($entry)) {
            return 'structural_containers';
        }
        if (str_contains($path, '/resources/labels/')) {
            return 'labels';
        }
        if (str_contains($path, '/resources/lang/') || str_ends_with($path, '/resources/lang')) {
            return 'localization';
        }
        if (str_contains($path, '/migrations/') || str_ends_with($path, '/migrations') || str_ends_with($name, '.sql')) {
            return 'database';
        }
        if (str_contains($path, '/controllers/') || str_ends_with($name, 'controller.php')) {
            return 'controllers';
        }
        if (str_contains($path, '/services/')) {
            return 'services';
        }
        if (str_contains($path, '/views/')) {
            return 'views';
        }
        if ($name === 'routes.php' || str_contains($path, '/routes/')) {
            return 'routes';
        }
        if ($name === 'menu.php' || $name === 'navigation.php' || str_contains($path, '/navigation/')) {
            return 'navigation';
        }
        if (str_contains($path, '/tests/') || str_ends_with($path, '/tests') || str_ends_with($name, 'test.php')) {
            return 'tests';
        }
        if (str_contains($path, '/docs/') || str_ends_with($path, '/docs') || str_ends_with($name, '.md')) {
            return 'documentation';
        }
        if (str_contains($path, '/generated/') || str_ends_with($path, '/generated') || str_contains($name, 'generated') || str_contains($name, 'backup')) {
            return 'generated';
        }
        if (str_contains($path, '/runtime/') || str_contains($path, '/cache/') || str_contains($path, '/logs/') || str_contains($path, '/storage/')) {
            return 'runtime';
        }
        if (str_contains($path, '/reports/') || str_contains($name, 'report')) {
            return 'reports';
        }
        if (self::isCss($path) || str_contains($path, '/styles/')) {
            return 'assets_css';
        }
        if (self::isJavaScript($path) || str_contains($path, '/assets/js/')) {
            return 'assets_javascript';
        }
        if (self::isImage($path)) {
            return 'assets_images';
        }
        if (self::isConfiguration($path, $name)) {
            return 'configuration';
        }

        return 'unknown';
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function shellGroupKey(array $entry): string
    {
        $relativePath = trim(str_replace('\\', '/', (string)($entry['relative_path'] ?? '')), '/');
        $ownerPath = trim(str_replace('\\', '/', (string)($entry['owner_relative_path'] ?? '')), '/');
        if (!str_starts_with(strtolower($relativePath), 'apps/shell/')) {
            return '';
        }

        if ($ownerPath === 'styles' || str_starts_with($ownerPath, 'styles/')) {
            return 'shell_runtime_css';
        }
        if (
            $ownerPath === 'Style'
            || str_starts_with($ownerPath, 'Style/')
            || $ownerPath === 'DesignSystem'
            || str_starts_with($ownerPath, 'DesignSystem/')
        ) {
            return 'shell_style_governance';
        }
        if ($ownerPath === 'dashboard_widgets.php' || $ownerPath === 'layout_contract.php' || $ownerPath === 'sidebar.php') {
            return 'shell_root_contracts';
        }
        if ($ownerPath === 'Resources/css' || $ownerPath === 'Resources/css/essential' || str_starts_with($ownerPath, 'Resources/css/essential/')) {
            return 'shell_boot_essential_css';
        }
        if ($ownerPath === 'Resources/rendering' || str_starts_with($ownerPath, 'Resources/rendering/')) {
            return 'shell_rendering_foundation';
        }
        if ($ownerPath === 'Composers' || str_starts_with($ownerPath, 'Composers/')) {
            return 'shell_composition';
        }
        if ($ownerPath === 'Overlay' || str_starts_with($ownerPath, 'Overlay/')) {
            return 'runtime';
        }
        if ($ownerPath === 'sidebar_sources' || str_starts_with($ownerPath, 'sidebar_sources/')) {
            return 'shell_navigation_sources';
        }

        return '';
    }

    /**
     * @param array<string,mixed> $entry
     */
    private static function isStructuralContainer(array $entry): bool
    {
        if ((string)($entry['type'] ?? '') !== 'folder') {
            return false;
        }

        $ownerPath = trim(str_replace('\\', '/', (string)($entry['owner_relative_path'] ?? '')), '/');
        if ($ownerPath === '' || $ownerPath === '.') {
            return true;
        }

        return in_array($ownerPath, [
            'Controllers',
            'Services',
            'Views',
            'Views/Partials',
            'Resources',
            'Resources/lang',
            'Resources/labels',
            'Resources/labels/contexts',
            'Resources/labels/templates',
            'Resources/labels/rules',
            'lifecycle',
            'Database',
            'Database/migrations',
            'Database/seeds',
            'Tests',
            'Docs',
        ], true);
    }

    private static function isCss(string $path): bool
    {
        return str_ends_with($path, '.css') || str_ends_with($path, '.scss') || str_ends_with($path, '.sass') || str_ends_with($path, '.less');
    }

    private static function isJavaScript(string $path): bool
    {
        return str_ends_with($path, '.js') || str_ends_with($path, '.mjs') || str_ends_with($path, '.cjs') || str_ends_with($path, '.ts');
    }

    private static function isImage(string $path): bool
    {
        foreach (['.png', '.jpg', '.jpeg', '.gif', '.webp', '.svg', '.ico', '.avif'] as $extension) {
            if (str_ends_with($path, $extension)) {
                return true;
            }
        }
        return false;
    }

    private static function isConfiguration(string $path, string $name): bool
    {
        if (in_array($name, ['plugin.json', 'manifest.php', 'manifest.json', 'bootstrap.php', 'install.php', 'uninstall.php'], true)) {
            return true;
        }
        return str_contains($path, '/config/') || str_ends_with($path, '/config') || str_ends_with($name, '.json') || str_ends_with($name, '.yaml') || str_ends_with($name, '.yml');
    }

    private static function locationFor(string $path, string $type): string
    {
        if ($path === '') {
            return '';
        }
        if ($type === 'folder') {
            return $path;
        }
        $location = str_replace('\\', '/', dirname($path));
        return $location === '.' ? $path : $location;
    }
}
