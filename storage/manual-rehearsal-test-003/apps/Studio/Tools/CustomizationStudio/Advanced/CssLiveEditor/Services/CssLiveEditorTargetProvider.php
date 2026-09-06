<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Advanced\CssLiveEditor\Services;

final class CssLiveEditorTargetProvider
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public static function targets(): array
    {
        return [
            [
                'id' => 'studio-component-library',
                'title' => 'Studio component library',
                'route' => '/apps/studio/tools/customization-studio/visual-customizer',
                'source_hint' => 'apps/Studio/Tools/CustomizationStudio/Resources/preview-fixtures/component-preview.json',
                'owner' => 'Studio / Customization Studio',
                'owner_root' => 'apps/Studio/Tools/CustomizationStudio',
                'css_sources' => [
                    'apps/Studio/Tools/CustomizationStudio/assets/visual-customizer.css',
                    'apps/Studio/styles/gui_studio.css',
                ],
                'target_type' => 'tool_fixture',
                'adapter_id' => 'studio.static-component-fixture.v1',
                'adapter_name' => 'Static component fixture feed',
                'eligible' => true,
                'status' => 'available',
                'reason' => '',
                'data_mode' => 'static_fixture',
            ],
            [
                'id' => 'studio-workflow-surface',
                'title' => 'Studio workflow surface',
                'route' => '/apps/studio/workflow/analyze',
                'source_hint' => 'apps/Studio/Tools/CustomizationStudio/Resources/preview-fixtures/workflow-preview.json',
                'owner' => 'Studio / Customization Studio',
                'owner_root' => 'apps/Studio/Tools/CustomizationStudio',
                'css_sources' => [
                    'apps/Studio/Tools/CustomizationStudio/assets/visual-customizer.css',
                    'apps/Studio/styles/gui_studio.css',
                ],
                'target_type' => 'tool_fixture',
                'adapter_id' => 'studio.static-workflow-fixture.v1',
                'adapter_name' => 'Static workflow fixture feed',
                'eligible' => true,
                'status' => 'available',
                'reason' => '',
                'data_mode' => 'static_fixture',
            ],
            [
                'id' => 'studio-shell-layout',
                'title' => 'Studio shell layout',
                'route' => '/apps/studio',
                'source_hint' => 'apps/Studio/Tools/CustomizationStudio/Resources/preview-fixtures/shell-preview.json',
                'owner' => 'Studio / Shell preview fixture',
                'owner_root' => 'apps/Studio',
                'css_sources' => [
                    'apps/Studio/styles/gui_studio.css',
                ],
                'target_type' => 'layout_fixture',
                'adapter_id' => 'studio.static-shell-fixture.v1',
                'adapter_name' => 'Static shell layout fixture feed',
                'eligible' => true,
                'status' => 'available',
                'reason' => '',
                'data_mode' => 'static_fixture',
            ],
            [
                'id' => 'operator-dashboard-live',
                'title' => 'Operator dashboard (adapter required)',
                'route' => '/u/{username}/dashboard',
                'source_hint' => 'apps/Shell/Views/operator/',
                'owner' => 'Shell / Operator Layer',
                'owner_root' => 'apps/Shell',
                'target_type' => 'runtime_route',
                'adapter_id' => '',
                'adapter_name' => '',
                'eligible' => false,
                'status' => 'unavailable',
                'reason' => 'No owner-provided mock or sanitized preview feed is registered.',
                'data_mode' => 'unavailable',
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function find(string $targetId): ?array
    {
        $needle = strtolower(trim($targetId));
        if ($needle === '') {
            return null;
        }

        foreach (self::targets() as $target) {
            if (strtolower((string)($target['id'] ?? '')) === $needle) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function findByRoute(string $route): ?array
    {
        $needle = self::normalizeRoute($route);
        if ($needle === '') {
            return null;
        }

        foreach (self::targets() as $target) {
            if (self::normalizeRoute((string)($target['route'] ?? '')) === $needle) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function defaultTarget(): ?array
    {
        foreach (self::targets() as $target) {
            if (!empty($target['eligible'])) {
                return $target;
            }
        }

        return null;
    }

    private static function normalizeRoute(string $route): string
    {
        $path = trim((string)(parse_url(trim($route), PHP_URL_PATH) ?: ''));
        if ($path === '') {
            return '';
        }

        return rtrim('/' . ltrim((string)preg_replace('#/+#', '/', $path), '/'), '/') ?: '/';
    }
}
