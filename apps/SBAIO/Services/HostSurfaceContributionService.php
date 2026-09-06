<?php
declare(strict_types=1);

namespace Apps\SBAIO\Services;

use App\Core\DB;

final class HostSurfaceContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<string,mixed>|array<int,array<string,mixed>>
     */
    public static function contribute(array $request): array
    {
        $surface = trim((string)($request['surface'] ?? ''));
        $region = trim((string)($request['region'] ?? ''));
        if (!self::isVisibleForRequest($request)) {
            return [];
        }

        return match ($surface . ':' . $region) {
            'me:header_actions' => self::meHeaderActions(),
            'me:summary_cards' => self::meSummaryCards(),
            'me:quick_links' => self::meQuickLinks(),
            'me:monitoring_sections' => self::meMonitoringSections(),
            default => [],
        };
    }

    /**
     * Get operational focus values supported by SBAIO app.
     *
     * @return array<string,string> Map of focus key to localized label
     */
    public static function operationalFocusValues(): array
    {
        return [
            'office_ops' => t('ops.operational_focus.office_ops'),
        ];
    }

    /**
     * Get per-app operational role values supported by SBAIO.
     *
     * @return array<string,string> Map of role key to localized label
     */
    public static function operationalRoleValues(): array
    {
        return [
            'my_work' => t('ops.access_control.app_role.role.my_work'),
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meHeaderActions(): array
    {
        return self::annotateActionWidgets([
            [
                'label' => t('nav.sbaio_dashboard'),
                'url' => '/apps/sbaio',
                'weight' => 50,
            ],
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meSummaryCards(): array
    {
        $cards = self::moduleWidgets('summary_cards');
        usort($cards, static fn(array $a, array $b): int => ((int)($a['weight'] ?? 0)) <=> ((int)($b['weight'] ?? 0)));
        return self::annotateSummaryWidgets(array_values($cards));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meQuickLinks(): array
    {
        return self::annotateReferenceWidgets([
            [
                'label' => t('nav.sbaio_staff'),
                'url' => '/apps/sbaio/staff',
                'description' => t('sbaio.host.staff_meta'),
                'weight' => 50,
            ],
            [
                'label' => t('nav.sbaio_attendance'),
                'url' => '/apps/sbaio/attendance',
                'description' => t('sbaio.host.attendance_attention'),
                'weight' => 51,
            ],
            [
                'label' => t('nav.sbaio_timecards'),
                'url' => '/apps/sbaio/timecards',
                'description' => t('sbaio.host.timecards_attention'),
                'weight' => 52,
            ],
            [
                'label' => t('nav.sbaio_payroll'),
                'url' => '/apps/sbaio/payroll',
                'description' => t('sbaio.host.payroll_attention'),
                'weight' => 53,
            ],
            [
                'label' => t('nav.sbaio_leave'),
                'url' => '/apps/sbaio/leave',
                'description' => t('sbaio.host.leave_attention'),
                'weight' => 54,
            ],
            [
                'label' => t('sbaio.host.parity_validation'),
                'url' => '/apps/sbaio/payroll/parity',
                'description' => t('sbaio.host.parity_meta'),
                'weight' => 55,
            ],
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meMonitoringSections(): array
    {
        $items = self::watchlistItems();
        $total = array_sum(array_map(
            static fn(array $item): int => (int)($item['count'] ?? 0),
            $items
        ));

        return self::annotateMonitoringWidgets([
            [
                'view_kind' => 'queue',
                'title' => t('sbaio.host.watchlist_title'),
                'kind' => 'watchlist',
                'items' => $items,
                'total_count' => $total,
                'summary' => t('sbaio.host.items_to_watch', ['count' => $total]),
                'empty_message' => t('sbaio.host.watchlist_empty'),
                'weight' => 60,
            ],
        ]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function watchlistItems(): array
    {
        $items = self::moduleWidgets('watchlist_items');

        usort($items, static function (array $left, array $right): int {
            $leftCount = (int)($left['count'] ?? 0);
            $rightCount = (int)($right['count'] ?? 0);
            if ($leftCount !== $rightCount) {
                return $rightCount <=> $leftCount;
            }

            return ((int)($left['weight'] ?? 0)) <=> ((int)($right['weight'] ?? 0));
        });

        return array_values(array_map(static function (array $item): array {
            unset($item['weight']);
            return $item;
        }, $items));
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function isVisibleForRequest(array $request): bool
    {
        $outerContext = is_array($request['context'] ?? null) ? (array)$request['context'] : [];
        $resolvedContext = is_array($outerContext['context'] ?? null) ? (array)$outerContext['context'] : [];
        $activeAssignedApps = array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            (array)($resolvedContext['active_assigned_apps'] ?? [])
        );

        if (array_key_exists('active_assigned_apps', $resolvedContext)) {
            return in_array('sbaio', $activeAssignedApps, true);
        }

        return true;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<int,array<string,mixed>>
     */
    private static function moduleWidgets(string $region, array $context = []): array
    {
        $widgets = [];
        foreach (self::moduleWidgetProviders() as $provider) {
            $regions = array_values((array)($provider['regions'] ?? []));
            if (!in_array($region, $regions, true)) {
                continue;
            }

            $class = trim((string)($provider['class'] ?? ''));
            $file  = trim((string)($provider['file'] ?? ''));
            if ($class === '' || $file === '' || !is_file($file)) {
                continue;
            }

            if (!self::isProviderModuleActive($file)) {
                continue;
            }

            if (!class_exists($class, false)) {
                require_once $file;
            }

            if (!class_exists($class) || !method_exists($class, 'contribute')) {
                continue;
            }

            try {
                $result = $class::contribute($region, $context);
            } catch (\Throwable $e) {
                continue;
            }

            if (!is_array($result)) {
                continue;
            }

            foreach ($result as $item) {
                if (is_array($item)) {
                    $widgets[] = $item;
                }
            }
        }

        return $widgets;
    }

    private static function isProviderModuleActive(string $providerFile): bool
    {
        static $statusCache = [];

        $moduleDir = dirname(dirname($providerFile));
        $manifestPath = $moduleDir . '/plugin.json';
        if (!is_file($manifestPath)) {
            return false;
        }

        $moduleName = basename($moduleDir);
        try {
            $manifest = json_decode((string)file_get_contents($manifestPath), true);
            if (is_array($manifest) && trim((string)($manifest['name'] ?? '')) !== '') {
                $moduleName = trim((string)$manifest['name']);
            }
        } catch (\Throwable $e) {
            return false;
        }

        if (isset($statusCache[$moduleName])) {
            return (bool)$statusCache[$moduleName];
        }

        try {
            $row = DB::fetchOne('SELECT status FROM installed_plugins WHERE name = ? LIMIT 1', [$moduleName]);
            $statusCache[$moduleName] = strtolower(trim((string)($row['status'] ?? 'inactive'))) === 'active';
        } catch (\Throwable $e) {
            $statusCache[$moduleName] = false;
        }

        return (bool)$statusCache[$moduleName];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function moduleWidgetProviders(): array
    {
        return [
            [
                'regions' => ['summary_cards', 'watchlist_items'],
                'class'   => 'Plugins\\Attendance\\Services\\AttendanceWidgetRegistry',
                'file'    => APP_ROOT . '/apps/SBAIO/modules/Attendance/Services/AttendanceWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'watchlist_items'],
                'class'   => 'Plugins\\Timecards\\Services\\TimecardWidgetRegistry',
                'file'    => APP_ROOT . '/apps/SBAIO/modules/Timecards/Services/TimecardWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'watchlist_items'],
                'class'   => 'Plugins\\Payroll\\Services\\PayrollWidgetRegistry',
                'file'    => APP_ROOT . '/apps/SBAIO/modules/Payroll/Services/PayrollWidgetRegistry.php',
            ],
            [
                'regions' => ['summary_cards', 'watchlist_items'],
                'class'   => 'Plugins\\Leave\\Services\\LeaveWidgetRegistry',
                'file'    => APP_ROOT . '/apps/SBAIO/modules/Leave/Services/LeaveWidgetRegistry.php',
            ],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $actions
     * @param array<int,string> $profiles
     * @return array<int,array<string,mixed>>
     */
    private static function annotateActionWidgets(array $actions, array $profiles = ['worker', 'leader', 'admin']): array
    {
        return array_values(array_map(static function (array $action) use ($profiles): array {
            $action['widget_key'] = (string)($action['widget_key'] ?? self::widgetKeyFromLabel((string)($action['label'] ?? 'action')));
            $action['view_kind'] = (string)($action['view_kind'] ?? 'cards');
            $action['widget_type'] = 'action';
            $action['placement_zone'] = 'operator_actions';
            $action['interaction_profiles'] = array_values((array)($action['interaction_profiles'] ?? $profiles));
            return $action;
        }, $actions));
    }

    /**
     * @param array<int,array<string,mixed>> $cards
     * @return array<int,array<string,mixed>>
     */
    private static function annotateSummaryWidgets(array $cards): array
    {
        return array_values(array_map(static function (array $card): array {
            $tone = strtolower(trim((string)($card['tone'] ?? '')));
            $card['widget_key'] = (string)($card['widget_key'] ?? $card['key'] ?? self::widgetKeyFromLabel((string)($card['title'] ?? 'summary')));
            $card['view_kind'] = (string)($card['view_kind'] ?? 'kpi');
            $card['widget_type'] = (string)($card['widget_type'] ?? (in_array($tone, ['warn', 'danger'], true) ? 'alert' : 'informative'));
            $card['placement_zone'] = (string)($card['placement_zone'] ?? 'dashboard_summary');
            $card['interaction_profiles'] = array_values((array)($card['interaction_profiles'] ?? ['worker', 'leader', 'admin', 'read_only']));
            return $card;
        }, $cards));
    }

    /**
     * @param array<int,array<string,mixed>> $links
     * @return array<int,array<string,mixed>>
     */
    private static function annotateReferenceWidgets(array $links): array
    {
        return array_values(array_map(static function (array $link): array {
            $link['widget_key'] = (string)($link['widget_key'] ?? $link['key'] ?? self::widgetKeyFromLabel((string)($link['label'] ?? 'reference')));
            $link['view_kind'] = (string)($link['view_kind'] ?? 'cards');
            $link['widget_type'] = (string)($link['widget_type'] ?? 'reference');
            $link['placement_zone'] = (string)($link['placement_zone'] ?? 'supporting_visibility');
            $link['interaction_profiles'] = array_values((array)($link['interaction_profiles'] ?? ['worker', 'leader', 'admin', 'read_only']));
            return $link;
        }, $links));
    }

    /**
     * @param array<int,array<string,mixed>> $sections
     * @return array<int,array<string,mixed>>
     */
    private static function annotateMonitoringWidgets(array $sections): array
    {
        return array_values(array_map(static function (array $section): array {
            $section['widget_key'] = (string)($section['widget_key'] ?? self::widgetKeyFromLabel((string)($section['title'] ?? 'monitoring')));
            $section['view_kind'] = (string)($section['view_kind'] ?? strtolower(trim((string)($section['kind'] ?? 'table'))));
            $section['widget_type'] = (string)($section['widget_type'] ?? 'queue');
            $section['placement_zone'] = (string)($section['placement_zone'] ?? 'monitoring');
            $section['interaction_profiles'] = array_values((array)($section['interaction_profiles'] ?? ['worker', 'leader', 'admin', 'read_only']));
            return $section;
        }, $sections));
    }

    private static function widgetKeyFromLabel(string $label): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($label)) ?? '');
        $normalized = trim($normalized, '_');
        return $normalized !== '' ? $normalized : 'widget';
    }
}
