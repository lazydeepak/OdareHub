<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;
use Apps\Manufacturing\Services\OperatorWidgetProviderContributionService;

/**
 * OperatorLayerWidgetService
 * 
 * Aggregates and organizes module-provided widgets, views, charts, diagrams, forms, and tables
 * for display on the operator layer, grouped by provider/module.
 * 
 * Follows the widget contribution contract:
 * - view_kind: kpi, table, form, chart, cards, queue, timeline, mixed
 * - widget_type: action, informative, alert, queue, progress, chart, etc.
 * - placement_zone: primary_work, operator_actions, monitoring, supporting_visibility
 * - interaction_profiles: worker, leader, admin, read_only
 */
final class OperatorLayerWidgetService
{
    /**
     * Discover and aggregate all module-provided widgets
     * 
     * @return array<string, array<string, mixed>> Organized as [provider_name => widgets]
     */
    public static function discoverModuleWidgets(array $context = []): array
    {
        $organized = [];

        // Get all provider registries
        $providers = self::getModuleWidgetProviders();

        foreach ($providers as $provider) {
            $provider_name = trim((string)($provider['provider_name'] ?? 'Unknown'));
            $class = trim((string)($provider['class'] ?? ''));
            $file = trim((string)($provider['file'] ?? ''));

            // Skip invalid providers
            if ($class === '' || $file === '' || !is_file($file)) {
                continue;
            }

            // Skip inactive modules
            if (!self::isProviderModuleActive($file)) {
                continue;
            }

            // Load class if needed
            if (!class_exists($class, false)) {
                require_once $file;
            }

            if (!class_exists($class) || !method_exists($class, 'contribute')) {
                continue;
            }

            try {
                // Request widgets for operator layer
                $widgets = $class::contribute('summary_cards', $context);
                
                if (is_array($widgets) && !empty($widgets)) {
                    $widgets = self::normalizeOperatorWidgetCollection($widgets, $context);
                    $organized[$provider_name] = array_merge(
                        $organized[$provider_name] ?? [],
                        $widgets
                    );
                }

                // Also get monitoring sections if available
                $monitoring = $class::contribute('monitoring_sections', $context);
                if (is_array($monitoring) && !empty($monitoring)) {
                    $monitoring = self::normalizeOperatorWidgetCollection($monitoring, $context);
                    $monitoring_key = $provider_name . ' (Monitoring)';
                    $organized[$monitoring_key] = array_merge(
                        $organized[$monitoring_key] ?? [],
                        $monitoring
                    );
                }
            } catch (\Throwable $e) {
                error_log("OperatorLayerWidgetService: Failed to load widgets from {$class}: " . $e->getMessage());
                continue;
            }
        }

        // Sort providers by priority/weight
        uasort($organized, function ($a, $b) {
            $aWeight = (int)($a[0]['weight'] ?? 999);
            $bWeight = (int)($b[0]['weight'] ?? 999);
            return $aWeight <=> $bWeight;
        });

        return $organized;
    }

    /**
     * Get sidebar menu items from module providers
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function getSidebarModuleMenu(): array
    {
        $items = [];
        $providers = self::getModuleWidgetProviders();

        foreach ($providers as $provider) {
            $provider_name = trim((string)($provider['provider_name'] ?? ''));
            $class = trim((string)($provider['class'] ?? ''));
            $file = trim((string)($provider['file'] ?? ''));

            if ($provider_name === '' || !is_file($file)) {
                continue;
            }

            if (!self::isProviderModuleActive($file)) {
                continue;
            }

            $items[] = [
                'label' => $provider_name,
                'provider_key' => $provider['provider_key'] ?? '',
                'module_key' => $provider['module_key'] ?? '',
                'icon' => '📦',
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function getModuleWidgetProviders(): array
    {
        $manufacturing = class_exists(OperatorWidgetProviderContributionService::class)
            ? OperatorWidgetProviderContributionService::providerCatalog()
            : [];

        // SBAIO app modules
        $sbaio = [
            ['provider_name' => 'Attendance', 'module_key' => 'Attendance', 'provider_key' => 'attendance', 'class' => 'Plugins\\Attendance\\Services\\AttendanceWidgetRegistry', 'file' => APP_ROOT . '/apps/SBAIO/modules/Attendance/Services/AttendanceWidgetRegistry.php'],
            ['provider_name' => 'Timecards', 'module_key' => 'Timecards', 'provider_key' => 'timecards', 'class' => 'Plugins\\Timecards\\Services\\TimecardWidgetRegistry', 'file' => APP_ROOT . '/apps/SBAIO/modules/Timecards/Services/TimecardWidgetRegistry.php'],
            ['provider_name' => 'Leave', 'module_key' => 'Leave', 'provider_key' => 'leave', 'class' => 'Plugins\\Leave\\Services\\LeaveWidgetRegistry', 'file' => APP_ROOT . '/apps/SBAIO/modules/Leave/Services/LeaveWidgetRegistry.php'],
            ['provider_name' => 'Payroll', 'module_key' => 'Payroll', 'provider_key' => 'payroll', 'class' => 'Plugins\\Payroll\\Services\\PayrollWidgetRegistry', 'file' => APP_ROOT . '/apps/SBAIO/modules/Payroll/Services/PayrollWidgetRegistry.php'],
        ];

        return array_merge($manufacturing, $sbaio);
    }

    /**
     * Check if a module is active in the database
     */
    private static function isProviderModuleActive(string $file): bool
    {
        static $statusCache = [];

        // Extract module name from file path
        // e.g., /path/apps/Manufacturing/modules/Products/Services/ProductWidgetRegistry.php -> products
        if (preg_match('#/modules/([^/]+)/Services#i', $file, $matches)) {
            $moduleName = strtolower($matches[1]);
        } else {
            return true; // If we can't determine, assume active
        }

        if (isset($statusCache[$moduleName])) {
            return (bool)$statusCache[$moduleName];
        }

        try {
            $status = DB::fetchOne(
                'SELECT is_enabled FROM core_app_modules WHERE LOWER(REPLACE(module_key, "-", "")) = ? LIMIT 1',
                [str_replace(['-', '_'], '', $moduleName)]
            );
            $statusCache[$moduleName] = $status && (int)($status['is_enabled'] ?? 0) === 1;
        } catch (\Throwable $e) {
            // If table doesn't exist or query fails, assume active
            $statusCache[$moduleName] = true;
        }

        return (bool)$statusCache[$moduleName];
    }

    /**
     * Render widgets grouped by provider as HTML
     * 
     * @param array<string, array<string, mixed>> $organizedWidgets
     */
    public static function renderWidgetsByProvider(array $organizedWidgets): string
    {
        $html = '';

        foreach ($organizedWidgets as $providerName => $widgets) {
            $html .= '<div class="provider-section">';
            $html .= '<h4 class="provider-title">' . htmlspecialchars($providerName) . '</h4>';
            $html .= '<div class="provider-widgets">';

            foreach ($widgets as $widget) {
                $widgetHtml = self::renderSingleWidget($widget);
                if ($widgetHtml) {
                    $html .= $widgetHtml;
                }
            }

            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * @param array<int,array<string,mixed>> $widgets
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function normalizeOperatorWidgetCollection(array $widgets, array $context): array
    {
        return array_values(array_map(static function (array $widget) use ($context): array {
            if (array_key_exists('url', $widget)) {
                $widget['url'] = self::normalizeOperatorWidgetUrl((string)($widget['url'] ?? ''), $context);
            }

            return $widget;
        }, $widgets));
    }

    /**
     * Normalize a widget URL so operator-layer cards stay jailed inside /u/*.
     *
     * @param array<string,mixed> $context
     */
    public static function normalizeOperatorWidgetUrl(string $url, array $context = []): string
    {
        $clean = trim($url);
        if ($clean === '' || !str_starts_with($clean, '/')) {
            return $clean;
        }

        $path = trim((string)parse_url($clean, PHP_URL_PATH));
        if ($path === '') {
            return $clean;
        }

        if (str_starts_with($path, '/u/') || str_starts_with($path, '/displays/')) {
            return $clean;
        }

        if (str_starts_with($path, '/apps/sbaio')) {
            return self::normalizeSbaioOperatorUrl($path, $context);
        }

        if (str_starts_with($path, '/apps/manufacturing')) {
            return self::normalizeManufacturingOperatorUrl($path, $context);
        }

        if (str_starts_with($path, '/apps/') || str_starts_with($path, '/ops/')) {
            return self::operatorDashboardUrl($context);
        }

        return $clean;
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function normalizeSbaioOperatorUrl(string $path, array $context): string
    {
        $username = self::operatorUsername($context);
        $base = '/u/' . rawurlencode($username);
        $trimmed = trim(substr($path, strlen('/apps/sbaio')), '/');
        if ($trimmed === '') {
            return $base . '/sbaio';
        }

        $tab = strtolower(trim(explode('/', $trimmed, 2)[0]));
        $tab = match ($tab) {
            'attendance', 'timecards', 'payroll', 'leave', 'staff', 'schedules', 'tasks', 'customers' => $tab,
            default => '',
        };

        if ($tab === '') {
            return $base . '/dashboard';
        }

        return $base . '/sbaio?tab=' . rawurlencode($tab);
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function normalizeManufacturingOperatorUrl(string $path, array $context): string
    {
        $username = self::operatorUsername($context);
        $base = '/u/' . rawurlencode($username);
        $trimmed = trim(substr($path, strlen('/apps/manufacturing')), '/');
        if ($trimmed === '') {
            return $base . '/dashboard';
        }

        $segment = strtolower(trim(explode('/', $trimmed, 2)[0]));
        $focus = match ($segment) {
            'coverage' => 'coverage',
            'dispatch', 'dispatch-ops', 'dispatch-entry', 'dispatch-detail' => 'dispatch',
            'qc', 'qc-plans', 'qc-entries' => 'qc',
            'machines' => 'machines',
            'assembly', 'assembly-plans', 'assembly-entries' => 'assembly',
            'materials', 'material-management' => 'materials',
            'production', 'production-operation', 'production-entries', 'production-plans' => 'production',
            'parts', 'part-machine-map', 'parts-detail' => 'parts',
            'demand', 'demands' => 'demand',
            'orders', 'daily-orders' => 'orders',
            'processing' => 'processing',
            'preparation' => 'preparation',
            'fulfillment' => 'fulfillment',
            'handoff' => 'handoff',
            'recent' => 'recent',
            'tasks' => 'tasks',
            'messages' => 'messages',
            'notifications' => 'notifications',
            'preferences' => 'preferences',
            'account' => 'account',
            default => '',
        };

        if ($focus === '') {
            return $base . '/dashboard';
        }

        return $base . '/' . rawurlencode($focus);
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function operatorDashboardUrl(array $context): string
    {
        $username = self::operatorUsername($context);
        return '/u/' . rawurlencode($username) . '/dashboard';
    }

    /**
     * @param array<string,mixed> $context
     */
    private static function operatorUsername(array $context): string
    {
        $identity = [
            'username' => (string)($context['username'] ?? $context['user_name'] ?? ''),
            'email' => (string)($context['user_email'] ?? $context['email'] ?? ''),
        ];
        $username = WorkspaceWrapperRegistry::handleFromIdentity($identity);
        if ($username !== '') {
            return $username;
        }

        $fallback = WorkspaceWrapperRegistry::normalizeHandle((string)($context['username'] ?? ''));
        return $fallback !== '' ? $fallback : 'operator';
    }

    /**
     * Render a single widget based on view_kind
     * 
     * @param array<string, mixed> $widget
     */
    private static function renderSingleWidget(array $widget): string
    {
        $viewKind = trim((string)($widget['view_kind'] ?? 'kpi'));
        $widgetType = trim((string)($widget['widget_type'] ?? ''));
        $title = htmlspecialchars((string)($widget['title'] ?? 'Widget'));
        $value = htmlspecialchars((string)($widget['value'] ?? ''));
        $meta = htmlspecialchars((string)($widget['meta'] ?? ''));
        $url = htmlspecialchars((string)($widget['url'] ?? '#'));
        $tone = trim((string)($widget['tone'] ?? 'neutral'));

        $toneClass = match ($tone) {
            'warn' => 'widget-tone-warn',
            'danger' => 'widget-tone-danger',
            'success' => 'widget-tone-success',
            default => 'widget-tone-neutral',
        };

        return match ($viewKind) {
            'kpi' => sprintf(
                '<a href="%s" class="widget-kpi gs-card gs-kpi %s" title="%s"><div class="gs-value widget-value">%s</div><div class="gs-title widget-label">%s</div><div class="gs-meta widget-meta">%s</div></a>',
                $url,
                $toneClass,
                $title,
                $value,
                $title,
                $meta
            ),
            'table' => sprintf(
                '<a href="%s" class="widget-table gs-card gs-table" title="%s"><div class="gs-title widget-label">%s</div><div class="gs-meta widget-meta">Table View</div></a>',
                $url,
                $title,
                $title
            ),
            'chart' => sprintf(
                '<a href="%s" class="widget-chart gs-card gs-table" title="%s"><div class="gs-title widget-label">%s</div><div class="gs-meta widget-meta">Chart</div></a>',
                $url,
                $title,
                $title
            ),
            'form' => sprintf(
                '<a href="%s" class="widget-form gs-card gs-form" title="%s"><div class="gs-title widget-label">%s</div><div class="gs-meta widget-meta">Form</div></a>',
                $url,
                $title,
                $title
            ),
            'queue' => sprintf(
                '<a href="%s" class="widget-queue gs-card gs-kpi" title="%s"><div class="gs-title widget-label">%s</div><div class="gs-value widget-value">%s</div><div class="gs-meta widget-meta">%s</div></a>',
                $url,
                $title,
                $title,
                $value,
                $meta
            ),
            default => '',
        };
    }
}
