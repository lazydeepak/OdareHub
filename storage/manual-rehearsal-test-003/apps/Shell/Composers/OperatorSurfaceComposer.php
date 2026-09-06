<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\Auth;
use App\Core\DB;
use App\Services\MyAccountService;
use Apps\Platform\Services\UserAssignmentContext;
use Apps\Manufacturing\Services\OperatorLayerAdapters\AssemblyAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\CoverageAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\DailyOrdersAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\DemandAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\DispatchAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MachinesAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\MaterialsAdapter;
use Apps\Shell\Services\OperatorLayerAdapters\PartDetailAdapter;
use Apps\Manufacturing\Services\OperatorLayerAdapters\QcAdapter;
use Apps\Shell\Services\OperatorLayerSidebarService;
use Apps\Shell\Services\OperatorSurfaceContributionRegistry;
use Apps\Shell\Services\PlatformWidgetBlueprintRuntimeService;
use Apps\Shell\Services\PartsTableService;
use Apps\Shell\Services\OperatorPreferencesService;
use Apps\Shell\Services\ThemePreferenceService;
use Platform\Search\AuthorizedSearchIndexService;
use Plugins\Base\Services\NotificationService;

require_once __DIR__ . '/../Services/OperatorSurfaceContributionRegistry.php';
require_once __DIR__ . '/../Services/PlatformWidgetBlueprintRuntimeService.php';
require_once __DIR__ . '/OperatorAvatarMenuComposer.php';
require_once __DIR__ . '/OperatorDashboardComposer.php';
require_once __DIR__ . '/OperatorFocusLabelComposer.php';
require_once __DIR__ . '/OperatorHeaderComposer.php';
require_once __DIR__ . '/OperatorInteractionScriptComposer.php';
require_once __DIR__ . '/OperatorNotificationPresentationComposer.php';
require_once __DIR__ . '/OperatorNavigationComposer.php';
require_once __DIR__ . '/OperatorDashboardInsightsComposer.php';
require_once __DIR__ . '/OperatorPartPlanComposer.php';
require_once __DIR__ . '/OperatorProfileQuickActionComposer.php';
require_once __DIR__ . '/OperatorSearchComposer.php';
require_once __DIR__ . '/OperatorSidebarComposer.php';
require_once __DIR__ . '/../Services/ShellOverlayFramework.php';
require_once __DIR__ . '/../Overlay/Compatibility/Adapters/OperatorSurface/OperatorSearchResultsAdapter.php';
require_once __DIR__ . '/../Overlay/Compatibility/Adapters/OperatorSurface/OperatorHamburgerDrawerAdapter.php';
require_once __DIR__ . '/../Overlay/Compatibility/Adapters/OperatorSurface/OperatorAvatarPanelAdapter.php';
require_once __DIR__ . '/../Overlay/Compatibility/Adapters/OperatorSurface/OperatorMobileActionSheetAdapter.php';
require_once __DIR__ . '/../Overlay/Compatibility/Adapters/Shared/CameraScanOverlayAdapter.php';

/**
 * Operator Surface Composer
 * 
 * Composes operator layer workspace with:
 * - Contextual sidebar: Views for selected app
 * - Assignment-aware: Shows only assigned apps/views
 * - Role-aware: Different sidebar content for different roles
 * - Responsive: Desktop collapsed rail + mobile drawer
 * 
 * Design: Mimic Gmail's calm, light, spacious, rounded app-like interface
 */
final class OperatorSurfaceComposer
{
    private string $username;
    private array $context;
    private OperatorLayerSidebarService $sidebarService;
    private array $contextualSidebar;
    /** @var array<string,string> */
    private array $recentEntitySummaryCache = [];

    private function tr(string $key, string $fallback, array $params = []): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key, $params);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }
        if ($params === []) {
            return $fallback;
        }
        $replace = [];
        foreach ($params as $paramKey => $paramValue) {
            $replace['{' . $paramKey . '}'] = (string)$paramValue;
        }
        return strtr($fallback, $replace);
    }

    public function __construct(string $username, array $context)
    {
        $this->username = $username;
        $this->context = $context;
        
        // Initialize sidebar service for Gmail-style navigation
        $this->sidebarService = new OperatorLayerSidebarService($context);
        $this->contextualSidebar = $this->sidebarService->getContextualSidebar();
    }

    private function operatorUrl(string $viewPath, array $query = []): string
    {
        $url = '/u/' . rawurlencode($this->username) . '/' . ltrim($viewPath, '/');
        if ($query !== []) {
            $queryString = http_build_query($query);
            if ($queryString !== '') {
                $url .= '?' . $queryString;
            }
        }

        return $url;
    }

    /**
     * Resolve the current operator's date_range preference in days (0 = today only).
     * Cached per request via a static local variable.
     */
    private function resolveDateRangeDays(): int
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }
        try {
            $user = Auth::user();
            $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : 0);
            $resolved = $userId > 0
                ? OperatorPreferencesService::dateRangeDays($userId)
                : 0;
        } catch (\Throwable) {
            $resolved = 0;
        }
        return $resolved;
    }

    /**
     * Resolve the current operator's notification_level preference.
     * Returns "all" | "critical" | "none". Cached per request.
     */
    private function resolveNotificationLevel(int $userId = 0): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }
        try {
            if ($userId <= 0) {
                $user = Auth::user();
                $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : 0);
            }
            $level = $userId > 0
                ? OperatorPreferencesService::get($userId, 'notification_level')
                : 'all';
            $resolved = in_array($level, ['all', 'critical', 'none'], true) ? $level : 'all';
        } catch (\Throwable) {
            $resolved = 'all';
        }
        return $resolved;
    }

    private function hasAssignedApp(string $appKey): bool
    {
        $needle = strtolower(trim($appKey));
        if ($needle === '') {
            return false;
        }

        $assignedApps = array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? [])
        );

        return in_array($needle, $assignedApps, true);
    }

    /** @return array<string,mixed> */
    private function emptyDailyOrderChartData(): array
    {
        return [
            'title' => $this->tr('nav.daily_orders', 'Daily Orders'),
            'model_label' => $this->tr('common.model', 'Model'),
            'filter_label' => $this->tr('operator.dashboard.parts', 'Parts'),
            'all_label' => $this->tr('common.all', 'All'),
            'date_from_label' => $this->tr('common.date_range_from', 'From'),
            'date_to_label' => $this->tr('common.date_range_to', 'To'),
            'date_label' => $this->tr('common.date', 'Date'),
            'qty_label' => $this->tr('common.qty', 'Qty'),
            'empty_label' => $this->tr('nav.topbar_search_no_matches', 'No results found'),
            'dates' => [],
            'models' => [],
            'products' => [],
        ];
    }

    /**
     * Compose all navigation elements for operator layer
     */
    public function buildNavigation(): array
    {
        // Navigation used by the current operator surface.
        return [
            'top_header_menu' => $this->composeTopHeaderMenu(),
            'mobile_top_navigation' => $this->composeMobileTopNav(),
            'mobile_quick_navigation' => $this->composeMobileQuickNav(),
        ];
    }

    /**
     * Compose top header menu (workspace controls, notifications, profile)
     */
    private function composeTopHeaderMenu(): array
    {
        return OperatorNavigationComposer::composeTopHeaderMenu(
            fn (string $key, string $fallback, array $params = []): string => $this->tr($key, $fallback, $params),
            fn (string $viewPath, array $query = []): string => $this->operatorUrl($viewPath, $query)
        );
    }

    /**
     * Compose mobile top navigation
     */
    private function composeMobileTopNav(): array
    {
        return OperatorNavigationComposer::composeMobileTopNav(
            fn (string $key, string $fallback, array $params = []): string => $this->tr($key, $fallback, $params),
            fn (string $viewPath, array $query = []): string => $this->operatorUrl($viewPath, $query)
        );
    }

    /**
     * Compose mobile quick actions (bottom bar)
     */
    private function composeMobileQuickNav(): array
    {
        // If the workspace_profile defines quick_actions, use those for the mobile bottom bar
        // (up to 4 items; fall back to defaults for any missing entries).
        $wpActions = $this->resolveProfileQuickActions();
        if (count($wpActions) >= 2) {
            $out = [];
            foreach (array_slice($wpActions, 0, 4) as $qa) {
                $out[] = [
                    'label' => (string)($qa['label'] ?? ''),
                    'url'   => OperatorProfileQuickActionComposer::sanitizeUrl((string)($qa['url'] ?? ''), $this->username),
                    'icon'  => (string)($qa['icon'] ?? ''),
                ];
            }
            return $out;
        }

        return [
            ['label' => $this->tr('operator.surface.start_task', 'Start Task'), 'url' => $this->operatorUrl('work-entry', ['action' => 'add']), 'icon' => ''],
            ['label' => $this->tr('operator.surface.resume_work', 'Resume Work'), 'url' => $this->operatorUrl('work-entry', ['action' => 'update']), 'icon' => ''],
            ['label' => $this->tr('operator.surface.scan_input', 'Scan / Input'), 'url' => $this->operatorUrl('work-entry', ['action' => 'report']), 'icon' => ''],
            ['label' => $this->tr('operator.surface.escalate', 'Escalate'), 'url' => $this->operatorUrl('work-entry', ['action' => 'report', 'type' => 'machine_issue']), 'icon' => ''],
        ];
    }

    /**
     * Return structured quick_actions from the resolved workspace_profile, or empty array.
     * Each entry: ['label' => string, 'url' => string, 'icon' => string]
     *
     * @return array<int,array{label:string,url:string,icon:string}>
     */
    private function resolveProfileQuickActions(): array
    {
        return OperatorProfileQuickActionComposer::resolve((array)$this->context, $this->username);
    }

    /**
     * Compose major workflow entry links for the shared top action strip.
     * Sidebar remains navigation; this strip is for operational entry points.
     *
     * @return array<int, array{label:string,url:string,is_active:bool}>
     */
    private function composeWorkflowEntryNav(array $query = []): array
    {
        return OperatorNavigationComposer::composeWorkflowEntryNav(
            $query,
            $this->username,
            fn (string $key, string $fallback, array $params = []): string => $this->tr($key, $fallback, $params)
        );
    }

    /**
     * Build top dashboard tiles and prioritize by role/dashboard type.
     * @return array<int, array{key:string,label:string,value:string,primary:bool,subtitle?:string,items?:array<int,string>,trends?:array<int,array{period:string,delta_label:string,tone:string}>,bars?:array<int,array{period:string,value:int,value_label:string,percent:float}>}>
     */
    private function buildRoleAwareDashboardTiles(): array
    {
        $dashboardType = strtolower(trim((string)($this->context['dashboard_type'] ?? 'operator')));
        $tr = fn (string $key, string $fallback, array $params = []): string => $this->tr($key, $fallback, $params);
        $ordersInsights = OperatorDashboardInsightsComposer::buildOrdersInsights($tr);
        $partsInsights = OperatorDashboardInsightsComposer::buildPartsInsights();
        $overstockInsights = OperatorDashboardInsightsComposer::buildOverstockInsights($tr);
        $wasteInsights = OperatorDashboardInsightsComposer::buildWasteInsights();
        $zairyoInsights = OperatorDashboardInsightsComposer::buildZairyoInsights();
        $dispatchInsights = OperatorDashboardInsightsComposer::buildDispatchInsights($tr);

        $metrics = [
            'orders' => (string)($ordersInsights['today'] ?? '--'),
            'parts' => (string)($partsInsights['count'] ?? '--'),
            'overstock' => (string)($overstockInsights['count'] ?? '--'),
            'plans' => (string)($wasteInsights['total'] ?? '--'),
            'processing' => (string)($zairyoInsights['count'] ?? '--'),
            'dispatch' => (string)($dispatchInsights['total'] ?? '--'),
        ];

        $tiles = [
            [
                'key' => 'orders',
                'label' => $this->tr('operator.dashboard.orders', 'Orders'),
                'value' => $metrics['orders'],
                'primary' => false,
                'trends' => (array)($ordersInsights['trends'] ?? []),
                'bars' => (array)($ordersInsights['bars'] ?? []),
            ],
            [
                'key' => 'parts',
                'label' => $this->tr('operator.dashboard.critical_parts', 'Critical Parts'),
                'value' => $metrics['parts'],
                'primary' => false,
                'items' => (array)($partsInsights['items'] ?? []),
            ],
            [
                'key' => 'overstock',
                'label' => $this->tr('operator.dashboard.over_stock', 'Overstock'),
                'value' => $metrics['overstock'],
                'primary' => false,
                'items' => (array)($overstockInsights['items'] ?? []),
            ],
            [
                'key' => 'plans',
                'label' => $this->tr('operator.dashboard.furyo_waste', 'Waste / Defect'),
                'value' => $metrics['plans'],
                'primary' => false,
                'items' => (array)($wasteInsights['items'] ?? []),
            ],
            [
                'key' => 'processing',
                'label' => $this->tr('operator.dashboard.zairyo_material', 'Material'),
                'value' => $metrics['processing'],
                'primary' => false,
                'items' => (array)($zairyoInsights['items'] ?? []),
            ],
            [
                'key' => 'dispatch',
                'label' => $this->tr('operator.dashboard.dispatch', 'Dispatch'),
                'value' => $metrics['dispatch'],
                'primary' => false,
                'items' => (array)($dispatchInsights['items'] ?? []),
            ],
        ];

        $priorityMap = [
            'dispatch_leader' => ['dispatch', 'processing', 'orders', 'parts', 'overstock', 'plans'],
            'qc_leader' => ['processing', 'plans', 'dispatch', 'orders', 'parts', 'overstock'],
            'assembly_leader' => ['processing', 'plans', 'orders', 'parts', 'overstock', 'dispatch'],
            'production_leader' => ['plans', 'processing', 'orders', 'parts', 'overstock', 'dispatch'],
            'operator' => ['processing', 'orders', 'parts', 'overstock', 'plans', 'dispatch'],
            'ops' => ['orders', 'parts', 'overstock', 'plans', 'processing', 'dispatch'],
            'platform_admin' => ['orders', 'parts', 'overstock', 'plans', 'processing', 'dispatch'],
        ];

        $orderedKeys = $priorityMap[$dashboardType] ?? ['orders', 'parts', 'overstock', 'plans', 'processing', 'dispatch'];
        $indexByKey = array_flip($orderedKeys);

        usort($tiles, static function (array $a, array $b) use ($indexByKey): int {
            return ($indexByKey[$a['key']] ?? 999) <=> ($indexByKey[$b['key']] ?? 999);
        });

        if ($tiles !== []) {
            $tiles[0]['primary'] = true;
        }

        return $tiles;
    }

    /**
     * Keep the top dashboard surface minimal while preserving operator essentials.
     *
     * @param array<int, array<string,mixed>> $tiles
     * @return array<int, array<string,mixed>>
     */
    private function selectMustHaveDashboardTiles(array $tiles): array
    {
        $dashboardType = strtolower(trim((string)($this->context['dashboard_type'] ?? 'operator')));

        $mustHaveMap = [
            'dispatch_leader' => ['dispatch', 'processing', 'orders', 'parts'],
            'qc_leader' => ['processing', 'plans', 'orders', 'dispatch'],
            'assembly_leader' => ['processing', 'orders', 'plans', 'parts'],
            'production_leader' => ['plans', 'processing', 'orders', 'dispatch'],
            'operator' => ['processing', 'orders', 'parts', 'dispatch'],
            'ops' => ['orders', 'parts', 'dispatch', 'processing'],
            'platform_admin' => ['orders', 'parts', 'dispatch', 'processing'],
        ];

        $mustHaveKeys = $mustHaveMap[$dashboardType] ?? ['processing', 'orders', 'parts', 'dispatch'];
        $byKey = [];
        foreach ($tiles as $tile) {
            $key = trim((string)($tile['key'] ?? ''));
            if ($key !== '') {
                $byKey[$key] = $tile;
            }
        }

        $selected = [];
        foreach ($mustHaveKeys as $key) {
            if (isset($byKey[$key])) {
                $selected[] = $byKey[$key];
            }
        }

        // Fallback: keep first 4 available tiles if role mapping misses any key.
        if ($selected === []) {
            $selected = array_slice($tiles, 0, 4);
        }

        foreach ($selected as $idx => $tile) {
            $selected[$idx]['primary'] = ($idx === 0);
        }

        return $selected;
    }

    /**
     * @param array<string,mixed> $tasksFocus
     * @param array<string,mixed> $notificationsFocus
     * @param array<string,mixed> $messagesFocus
     * @param array<string,mixed> $recentActivity
     * @return array<int,array<string,mixed>>
     */
    private function buildCommonHomeTiles(
        array $tasksFocus,
        array $notificationsFocus,
        array $messagesFocus,
        array $recentActivity
    ): array
    {
        $assignedApps = array_values(array_filter((array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? []), static function ($value): bool {
            return trim((string)$value) !== '';
        }));

        $appItems = array_map(function ($appKey): string {
            return strtoupper((string)$appKey);
        }, array_slice($assignedApps, 0, 4));

        return [
            [
                'key' => 'apps',
                'label' => $this->tr('operator.home.apps.label', 'Assigned Apps'),
                'value' => (string)count($assignedApps),
                'subtitle' => $this->tr('operator.home.apps.subtitle', 'Apps available in this workspace'),
                'items' => $appItems !== [] ? $appItems : [$this->tr('operator.home.apps.none', 'No apps assigned')],
                'primary' => true,
            ],
            [
                'key' => 'tasks',
                'label' => $this->tr('operator.home.tasks.label', 'Open Tasks'),
                'value' => (string)((int)($tasksFocus['open_count'] ?? 0)),
                'subtitle' => $this->tr('operator.home.tasks.subtitle', 'Tasks waiting for your action'),
                'items' => [],
            ],
            [
                'key' => 'notifications',
                'label' => $this->tr('operator.home.notifications.label', 'Unread Alerts'),
                'value' => (string)((int)($notificationsFocus['unread'] ?? 0)),
                'subtitle' => $this->tr('operator.home.notifications.subtitle', 'Operational notifications in your queue'),
                'items' => [],
            ],
            [
                'key' => 'messages',
                'label' => $this->tr('operator.home.messages.label', 'Messages'),
                'value' => (string)((int)($messagesFocus['unread'] ?? 0)),
                'subtitle' => $this->tr('operator.home.messages.subtitle', 'Unread admin and guide messages'),
                'items' => [
                    $this->tr('operator.home.recent.subtitle', 'Recent activity entries') . ': ' . (string)((int)($recentActivity['total'] ?? 0)),
                ],
            ],
        ];
    }

    /**
     * Compose work surface content area.
     */
    public function buildContent(): array
    {
        $hasManufacturing = $this->hasAssignedApp('manufacturing');
        $notificationsFocus = $this->buildNotificationsFocusData();
        $messagesFocus = $this->buildMessagesFocusData();
        $tasksFocus = $this->buildTasksFocusData();
        $recentActivity = $this->buildRecentActivityData();
        $commonHomeTiles = $this->buildCommonHomeTiles($tasksFocus, $notificationsFocus, $messagesFocus, $recentActivity);
        $generatedWidgetTiles = PlatformWidgetBlueprintRuntimeService::resolveDashboardTiles($this->context);
        $generatedSummaryTiles = [];
        $generatedSecondaryTiles = [];
        foreach ($generatedWidgetTiles as $generatedTile) {
            if (!is_array($generatedTile)) {
                continue;
            }
            $zone = strtolower(trim((string)($generatedTile['placement_zone'] ?? 'dashboard_summary')));
            if (in_array($zone, ['monitoring', 'operator_actions'], true)) {
                $generatedSecondaryTiles[] = $generatedTile;
                continue;
            }
            $generatedSummaryTiles[] = $generatedTile;
        }
        if ($generatedSummaryTiles !== []) {
            $commonHomeTiles = array_merge($commonHomeTiles, $generatedSummaryTiles);
        }
        $allTiles = $hasManufacturing ? $this->buildRoleAwareDashboardTiles() : [];
        $tiles = $hasManufacturing ? $this->selectMustHaveDashboardTiles($allTiles) : [];
        if ($generatedSecondaryTiles !== []) {
            $tiles = array_merge($generatedSecondaryTiles, $tiles);
        }

        return [
            'dashboard_title' => $this->tr('operator.overview.title', 'Overview'),
            'manufacturing_available' => $hasManufacturing,
            'common_home_tiles' => $commonHomeTiles,
            'dashboard_tiles' => $tiles,
            'daily_order_chart' => $hasManufacturing ? $this->buildDailyOrderChartData() : $this->emptyDailyOrderChartData(),
            'overview_cards' => $hasManufacturing ? $this->buildOverviewCards($allTiles) : [],
            'critical_title' => $this->tr('operator.critical.title', 'Critical Items'),
            'critical_cards' => $hasManufacturing ? [
                $this->buildCriticalPartsDetailData(),
                $this->buildCriticalZairyoDetailData(),
            ] : [],
            'recent_activity' => $recentActivity,
            'parts_table' => PartsTableService::getPartsTable(),
            'part_detail_focus' => $hasManufacturing ? $this->buildPartDetailFocusData() : [],
            'production_focus' => $hasManufacturing ? $this->buildProductionFocusData() : [],
            'processing_focus' => $hasManufacturing ? $this->buildProcessingFocusData() : [],
            'preparation_focus' => $hasManufacturing ? $this->buildPreparationFocusData() : [],
            'dispatch_focus' => $hasManufacturing ? $this->buildDispatchFocusData() : [],
            'dispatch_detail_focus' => $hasManufacturing ? $this->buildDispatchDetailFocusData() : [],
            'orders_focus'    => $hasManufacturing ? $this->buildOrdersFocusData() : [],
            'demand_focus'    => $hasManufacturing ? $this->buildDemandFocusData() : [],
            'coverage_focus'  => $hasManufacturing ? $this->buildCoverageFocusData() : [],
            'qc_focus'        => $hasManufacturing ? $this->buildQcFocusData() : [],
            'dispatch_adapter_focus' => $hasManufacturing ? $this->buildDispatchAdapterFocusData() : [],
            'machines_focus'  => $hasManufacturing ? $this->buildMachinesFocusData() : [],
            'assembly_focus'  => $hasManufacturing ? $this->buildAssemblyFocusData() : [],
            'materials_focus' => $hasManufacturing ? $this->buildMaterialsFocusData() : [],
            'notifications_focus' => $notificationsFocus,
            'messages_focus' => $messagesFocus,
            'handoff_focus' => $hasManufacturing ? $this->buildHandoffFocusData() : [],
            'preferences_focus' => $this->buildPreferencesFocusData(),
            'tasks_focus'     => $tasksFocus,
            'account_panel' => $this->buildAccountPanelData(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPartDetailFocusData(): array
    {
        try {
            return PartDetailAdapter::getData((array)($this->context['current_query'] ?? []), $this->username);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildPartDetailFocusData: ' . $e->getMessage());
            return ['product' => null, 'metrics' => [], 'context' => [], 'actions' => [], 'orders' => [], 'activity' => [], 'empty' => true, 'error' => 'operator.parts.detail.error.load'];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDispatchFocusData(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $query = (array)($this->context['current_query'] ?? []);
        $selectedDate = trim((string)($query['dispatch_date'] ?? $today));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = $today;
        }
        $toDate = (new \DateTimeImmutable($selectedDate))->modify('+6 days')->format('Y-m-d');

        $selectedProductId = (int)($query['product_id'] ?? 0);

        $products = [];
        $rows = [];
        $destinations = [];
        $defaultDriver = '';
        $defaultTruck = '';

        try {
            $products = DB::fetchAll(
                "SELECT id, parts_name, parts_number
                 FROM products
                 ORDER BY parts_name ASC, id ASC
                 LIMIT 300"
            );

            $sql =
                "SELECT
                    h.id AS bundle_id,
                    MIN(l.dispatch_date) AS dispatch_date,
                    COALESCE(NULLIF(TRIM(h.destination), ''), 'Unspecified Destination') AS destination,
                    COALESCE(NULLIF(TRIM(h.driver_name), ''), '-') AS driver_name,
                    COALESCE(NULLIF(TRIM(h.truck_no), ''), '-') AS truck_no,
                    COALESCE(NULLIF(TRIM(h.bundle_code), ''), CONCAT('B-', h.id)) AS load_reference,
                    h.eta_load_at,
                    ROUND(COALESCE(SUM(l.qty), 0), 2) AS dispatchable_qty,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', l.product_id)) ORDER BY p.parts_name ASC SEPARATOR ', ') AS part_name,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') ORDER BY p.parts_number ASC SEPARATOR ', ') AS part_number,
                    COALESCE(NULLIF(TRIM(h.status), ''), 'draft') AS bundle_status,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(TRIM(d.dispatch_status), ''), 'Ready') ORDER BY d.dispatch_status ASC SEPARATOR ', ') AS dispatch_statuses,
                    GROUP_CONCAT(DISTINCT COALESCE(NULLIF(TRIM(d.completion_status), ''), 'draft') ORDER BY d.completion_status ASC SEPARATOR ', ') AS completion_statuses
                 FROM mfg_dispatch_bundle_headers h
                 INNER JOIN mfg_dispatch_bundle_lines l ON l.bundle_id = h.id
                 INNER JOIN products p ON p.id = l.product_id
                 LEFT JOIN dispatch_entries d ON d.id = l.dispatch_entry_id
                 WHERE l.dispatch_date BETWEEN ? AND ?";
            $params = [$selectedDate, $toDate];
            if ($selectedProductId > 0) {
                $sql .= ' AND l.product_id = ?';
                $params[] = $selectedProductId;
            }
            $sql .=
                " GROUP BY h.id, destination, driver_name, truck_no, load_reference, h.eta_load_at, bundle_status
                  ORDER BY COALESCE(h.eta_load_at, CONCAT(MIN(l.dispatch_date), ' 23:59:59')) ASC, destination ASC, h.id ASC
                  LIMIT 240";
            $rows = DB::fetchAll($sql, $params);

            $destinationRows = DB::fetchAll(
                "SELECT DISTINCT COALESCE(NULLIF(TRIM(destination), ''), '') AS destination
                 FROM mfg_dispatch_bundle_headers
                 WHERE destination IS NOT NULL
                 ORDER BY destination ASC
                 LIMIT 200"
            );
            foreach ($destinationRows as $destinationRow) {
                $destination = trim((string)($destinationRow['destination'] ?? ''));
                if ($destination !== '') {
                    $destinations[] = $destination;
                }
            }

            $defaultRow = DB::fetchOne(
                "SELECT
                    COALESCE(NULLIF(TRIM(driver_name), ''), '') AS driver_name,
                    COALESCE(NULLIF(TRIM(truck_no), ''), '') AS truck_no
                      FROM mfg_dispatch_bundle_headers
                 WHERE COALESCE(NULLIF(TRIM(driver_name), ''), '') <> ''
                    OR COALESCE(NULLIF(TRIM(truck_no), ''), '') <> ''
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 1"
            );
            if (is_array($defaultRow)) {
                $defaultDriver = trim((string)($defaultRow['driver_name'] ?? ''));
                $defaultTruck = trim((string)($defaultRow['truck_no'] ?? ''));
            }
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildDispatchFocusData: ' . $e->getMessage());
        }

        $flash = (string)($_SESSION['operator_dispatch_flash_ok'] ?? '');
        $error = (string)($_SESSION['operator_dispatch_flash_err'] ?? '');
        unset($_SESSION['operator_dispatch_flash_ok'], $_SESSION['operator_dispatch_flash_err']);

        return [
            'title' => $this->tr('operator.dispatch.title', 'Dispatch'),
            'subtitle' => $this->tr('operator.dispatch.subtitle', 'Dispatch queue is populated from Preparation ready bundles and sorted by next schedule.'),
            'selected_date' => $selectedDate,
            'selected_to_date' => $toDate,
            'selected_product_id' => $selectedProductId,
            'products' => $products,
            'rows' => $rows,
            'destinations' => array_values(array_unique($destinations)),
            'default_driver_name' => $defaultDriver,
            'default_truck_no' => $defaultTruck,
            'flash' => $flash,
            'error' => $error,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDispatchDetailFocusData(): array
    {
        $query = (array)($this->context['current_query'] ?? []);
        $bundleId = (int)($query['bundle_id'] ?? 0);

        $header = null;
        $lines = [];
        if ($bundleId > 0) {
            try {
                $header = DB::fetchOne(
                    "SELECT
                        h.id,
                        COALESCE(NULLIF(TRIM(h.bundle_code), ''), CONCAT('B-', h.id)) AS bundle_code,
                        COALESCE(NULLIF(TRIM(h.pallet_code), ''), '-') AS pallet_code,
                        COALESCE(NULLIF(TRIM(h.destination), ''), 'Unspecified Destination') AS destination,
                        h.eta_load_at,
                        COALESCE(NULLIF(TRIM(h.driver_name), ''), '-') AS driver_name,
                        COALESCE(NULLIF(TRIM(h.truck_no), ''), '-') AS truck_no,
                        COALESCE(NULLIF(TRIM(h.status), ''), 'draft') AS bundle_status,
                        COALESCE(NULLIF(TRIM(h.notes), ''), '-') AS notes
                     FROM mfg_dispatch_bundle_headers h
                     WHERE h.id = ?
                     LIMIT 1",
                    [$bundleId]
                );

                $lines = DB::fetchAll(
                    "SELECT
                        l.id,
                        l.dispatch_date,
                        l.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', l.product_id)) AS part_name,
                        COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                        ROUND(COALESCE(l.qty, 0), 2) AS qty,
                        COALESCE(NULLIF(TRIM(d.dispatch_status), ''), '-') AS dispatch_status,
                        COALESCE(NULLIF(TRIM(d.completion_status), ''), '-') AS completion_status,
                        COALESCE(NULLIF(TRIM(d.load_reference), ''), '-') AS load_reference
                     FROM mfg_dispatch_bundle_lines l
                     INNER JOIN products p ON p.id = l.product_id
                     LEFT JOIN dispatch_entries d ON d.id = l.dispatch_entry_id
                     WHERE l.bundle_id = ?
                     ORDER BY l.dispatch_date ASC, p.parts_name ASC, l.id ASC",
                    [$bundleId]
                );
            } catch (\Throwable $e) {
                error_log('OperatorSurfaceComposer::buildDispatchDetailFocusData: ' . $e->getMessage());
                $header = null;
                $lines = [];
            }
        }

        return [
            'bundle_id' => $bundleId,
            'header' => $header,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildCoverageFocusData(): array
    {
        try {
            return CoverageAdapter::getData();
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildCoverageFocusData: ' . $e->getMessage());
            return [
                'summary' => ['open_orders' => 0, 'demand_qty' => 0.0, 'shortage_qty' => 0.0, 'coverage_pct' => 0.0, 'critical_orders_count' => 0, 'low_coverage_orders_count' => 0, 'fully_covered_orders_count' => 0, 'window_today_count' => 0, 'window_3day_count' => 0, 'window_7day_count' => 0],
                'risk_bands' => [],
                'demand_window' => [],
                'critical_orders' => [],
                'low_coverage_orders' => [],
                'empty' => true,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildOrdersFocusData(): array
    {
        try {
            $query  = (array)($this->context['current_query'] ?? []);
            $userId = (int)((Auth::user())['id'] ?? 0);

            // Keys that constitute a structural filter choice (not search/date)
            $filterKeys = ['status', 'coverage', 'range'];
            $hasExplicit = count(array_intersect_key($query, array_flip($filterKeys))) > 0;

            // Auto-save when user explicitly submits filters
            if ($hasExplicit && !empty($query['save_filters'])) {
                $toSave = [];
                foreach ($filterKeys as $k) {
                    if (isset($query[$k])) {
                        $toSave[$k] = (string)$query[$k];
                    }
                }
                OperatorPreferencesService::saveFilters($userId, 'orders', $toSave);
            }

            // On a clean load (no filter params in URL), restore saved filters
            if (!$hasExplicit) {
                $saved = OperatorPreferencesService::getFilters($userId, 'orders');
                if (!empty($saved)) {
                    // Saved values fill in — explicit URL keys (focus, q, date) keep priority
                    $query = array_merge($saved, $query);
                }
            }

            return DailyOrdersAdapter::getData($query);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildOrdersFocusData: ' . $e->getMessage());
            return ['summary' => [], 'rows' => [], 'date' => date('Y-m-d'), 'status' => 'open', 'range_mode' => 'week', 'empty' => true, 'error' => 'operator.orders.error.load'];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDemandFocusData(): array
    {
        try {
            $user = Auth::user();
            $userArr = is_array($user) ? $user : (array)$user;
            return DemandAdapter::getData((array)($this->context['current_query'] ?? []), (int)($userArr['id'] ?? 0));
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildDemandFocusData: ' . $e->getMessage());
            return ['summary' => [], 'rows' => [], 'anchor_date' => date('Y-m-d'), 'scope_mode' => 'my', 'empty' => true, 'error' => 'operator.demand.error.load'];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildQcFocusData(): array
    {
        try {
            $days = $this->resolveDateRangeDays();
            return QcAdapter::getData($days);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildQcFocusData: ' . $e->getMessage());
            return ['kpi' => [], 'run_now' => [], 'pending_qc' => [], 'failed_recheck' => [], 'ready_dispatch' => [], 'sla' => [], 'today' => date('Y-m-d'), 'days' => 0, 'empty' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildDispatchAdapterFocusData(): array
    {
        try {
            return DispatchAdapter::getData();
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildDispatchAdapterFocusData: ' . $e->getMessage());
            return ['kpi' => [], 'ready_now' => [], 'blocked_hold' => [], 'partial_queue' => [], 'aging_overdue' => [], 'release_candidates' => [], 'today' => date('Y-m-d'), 'empty' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildMachinesFocusData(): array
    {
        try {
            return MachinesAdapter::getData();
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildMachinesFocusData: ' . $e->getMessage());
            return ['kpi' => [], 'run_now' => [], 'next_queue' => [], 'delayed_jobs' => [], 'waiting_qc' => [], 'sections' => [], 'today' => date('Y-m-d'), 'empty' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildAssemblyFocusData(): array
    {
        try {
            $days = $this->resolveDateRangeDays();
            return AssemblyAdapter::getData($days);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildAssemblyFocusData: ' . $e->getMessage());
            return ['kpi' => [], 'today_entries' => [], 'in_progress' => [], 'pending_approval' => [], 'today' => date('Y-m-d'), 'days' => 0, 'empty' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildMaterialsFocusData(): array
    {
        try {
            return MaterialsAdapter::getData();
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildMaterialsFocusData: ' . $e->getMessage());
            return ['kpi' => [], 'low_stock' => [], 'open_orders' => [], 'critical_shortage' => [], 'empty' => true, 'error' => $e->getMessage()];
        }
    }

    private function buildNotificationsFocusData(): array
    {
        try {
            $user = Auth::user();
            $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : 0);
            $notifLevel = $this->resolveNotificationLevel($userId);

            // If preference is "none", suppress all notifications
            if ($notifLevel === 'none') {
                return ['alerts' => [], 'total_critical' => 0, 'total_warning' => 0, 'total_info' => 0, 'total_all' => 0, 'unread' => 0, 'empty' => true, 'error' => null, 'suppressed' => true];
            }

            $rows = NotificationService::listForUser(is_array($user) ? $user : (array)$user, NotificationService::STATUS_NEW);

            $alerts = [];
            $critical = 0;
            $warning = 0;
            $info = 0;

            foreach ($rows as $row) {
                $eventType = strtolower(trim((string)($row['event_type'] ?? '')));
                if (in_array($eventType, [NotificationService::EVENT_ADMIN_MESSAGE, NotificationService::EVENT_ADMIN_GUIDE], true)) {
                    continue;
                }

                $severity = OperatorNotificationPresentationComposer::mapSeverityToFocus((string)($row['severity'] ?? NotificationService::SEVERITY_INFO));

                // When preference is "critical", skip non-critical notifications
                if ($notifLevel === 'critical' && $severity !== 'critical') {
                    continue;
                }

                if ($severity === 'critical') {
                    $critical++;
                } elseif ($severity === 'warning') {
                    $warning++;
                } else {
                    $info++;
                }

                $title = trim((string)($row['title'] ?? ''));
                if ($title === '') {
                    $title = NotificationService::eventLabel($eventType);
                }

                $rowStatus = strtolower(trim((string)($row['status'] ?? NotificationService::STATUS_NEW)));

                $alerts[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'type' => $eventType,
                    'severity' => $severity,
                    'status' => $rowStatus,
                    'title' => $title,
                    'description' => (string)($row['message'] ?? ''),
                    'count' => 1,
                    'link' => OperatorNotificationPresentationComposer::normalizeUrl(
                        (string)($row['action_url'] ?? ''),
                        '/u/' . rawurlencode($this->username) . '/notifications',
                        (array)$this->context,
                        $this->username
                    ),
                    'icon' => OperatorNotificationPresentationComposer::iconForEvent($eventType, $severity),
                    'timestamp' => (string)($row['created_at'] ?? ''),
                ];
            }

            // History: dismissed + read rows from last 7 days (max 30)
            $historyRows = NotificationService::listForUser(is_array($user) ? $user : (array)$user, '');
            $history = [];
            $cutoff = strtotime('-7 days');
            foreach ($historyRows as $hr) {
                $hrStatus = strtolower(trim((string)($hr['status'] ?? '')));
                if ($hrStatus !== NotificationService::STATUS_DISMISSED && $hrStatus !== NotificationService::STATUS_READ) {
                    continue;
                }
                $hrEventType = strtolower(trim((string)($hr['event_type'] ?? '')));
                if (in_array($hrEventType, [NotificationService::EVENT_ADMIN_MESSAGE, NotificationService::EVENT_ADMIN_GUIDE], true)) {
                    continue;
                }
                // Only include rows dismissed/read within the last 7 days
                $ts = max((int)strtotime((string)($hr['dismissed_at'] ?? '')), (int)strtotime((string)($hr['read_at'] ?? '')));
                if ($ts < $cutoff) {
                    continue;
                }
                $hrSeverity = OperatorNotificationPresentationComposer::mapSeverityToFocus((string)($hr['severity'] ?? NotificationService::SEVERITY_INFO));
                $hrTitle = trim((string)($hr['title'] ?? ''));
                if ($hrTitle === '') {
                    $hrTitle = NotificationService::eventLabel($hrEventType);
                }
                $history[] = [
                    'id'       => (int)($hr['id'] ?? 0),
                    'severity' => $hrSeverity,
                    'status'   => $hrStatus,
                    'title'    => $hrTitle,
                    'icon'     => OperatorNotificationPresentationComposer::iconForEvent($hrEventType, $hrSeverity),
                    'ts'       => $ts,
                ];
                if (count($history) >= 30) {
                    break;
                }
            }

            return [
                'alerts' => $alerts,
                'total_critical' => $critical,
                'total_warning' => $warning,
                'total_info' => $info,
                'total_all' => count($alerts),
                'unread' => count($alerts),
                'empty' => count($alerts) === 0,
                'error' => null,
                'suppressed' => false,
                'history' => $history,
            ];
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildNotificationsFocusData: ' . $e->getMessage());
            return ['alerts' => [], 'total_critical' => 0, 'total_warning' => 0, 'total_info' => 0, 'total_all' => 0, 'empty' => true, 'error' => $e->getMessage(), 'suppressed' => false, 'history' => []];
        }
    }

    private function buildHandoffFocusData(): array
    {
        try {
            $user   = Auth::user();
            $userId = (int)($user['id'] ?? 0);
            return \Apps\Shell\Services\ShiftHandoffService::getForDate($userId, (string)($_GET['shift_date'] ?? ''));
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildHandoffFocusData: ' . $e->getMessage());
            return ['notes' => [], 'unacked_count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function buildTasksFocusData(): array
    {
        try {
            $user   = Auth::user();
            $userId = (int)($user['id'] ?? 0);
            return \Apps\Shell\Services\OperatorTaskService::getForUser($userId);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildTasksFocusData: ' . $e->getMessage());
            return ['tasks' => [], 'open_count' => 0, 'error' => $e->getMessage()];
        }
    }

    private function buildPreferencesFocusData(): array
    {
        try {
            $user   = Auth::user();
            $userId = (int)($user['id'] ?? 0);
            return OperatorPreferencesService::getAll($userId);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildPreferencesFocusData: ' . $e->getMessage());
            return OperatorPreferencesService::DEFAULTS;
        }
    }

    private function buildMessagesFocusData(): array
    {
        try {
            $user = Auth::user();
            $rows = NotificationService::listForUser(is_array($user) ? $user : (array)$user, '');
            $items = [];
            $unread = 0;
            $warning = 0;
            $info = 0;

            foreach ($rows as $row) {
                $eventType = strtolower(trim((string)($row['event_type'] ?? '')));
                if (!in_array($eventType, [NotificationService::EVENT_ADMIN_MESSAGE, NotificationService::EVENT_ADMIN_GUIDE], true)) {
                    continue;
                }

                $status = strtolower(trim((string)($row['status'] ?? NotificationService::STATUS_NEW)));
                if ($status === NotificationService::STATUS_DISMISSED) {
                    continue;
                }

                $severity = OperatorNotificationPresentationComposer::mapSeverityToFocus((string)($row['severity'] ?? NotificationService::SEVERITY_INFO));
                if ($severity === 'warning') {
                    $warning++;
                } else {
                    $info++;
                }

                if ($status === NotificationService::STATUS_NEW) {
                    $unread++;
                }

                $title = trim((string)($row['title'] ?? ''));
                if ($title === '') {
                    $title = NotificationService::eventLabel($eventType);
                }

                $items[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'icon' => OperatorNotificationPresentationComposer::iconForEvent($eventType, $severity),
                    'title' => $title,
                    'message' => (string)($row['message'] ?? ''),
                    'count' => 1,
                    'status' => $status,
                    'severity' => $severity,
                    'link' => OperatorNotificationPresentationComposer::normalizeUrl(
                        (string)($row['action_url'] ?? ''),
                        '/u/' . rawurlencode($this->username) . '/messages',
                        (array)$this->context,
                        $this->username
                    ),
                ];
            }

            return [
                'items' => $items,
                'total' => count($items),
                'unread' => $unread,
                'total_warning' => $warning,
                'total_info' => $info,
                'empty' => count($items) === 0,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildMessagesFocusData: ' . $e->getMessage());
            return ['items' => [], 'total' => 0, 'unread' => 0, 'total_warning' => 0, 'total_info' => 0, 'empty' => true, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPreparationFocusData(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $query = (array)($this->context['current_query'] ?? []);
        $selectedDate = trim((string)($query['preparation_date'] ?? $today));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = $today;
        }
        $toDate = (new \DateTimeImmutable($selectedDate))->modify('+7 days')->format('Y-m-d');

        $selectedProductId = (int)($query['product_id'] ?? 0);
        $dashboardType = strtolower(trim((string)($this->context['dashboard_type'] ?? 'operator')));
        $user = Auth::user();
        $userId = (int)($user['id'] ?? 0);

        $products = [];
        $readyRows = [];
        $bundleRows = [];
        $assignedPartIds = [];
        $destinationDefaults = [];
        $defaultDriverName = '';
        $defaultTruckNo = '';

        try {
            $products = DB::fetchAll(
                "SELECT id, parts_name, parts_number
                 FROM products
                 ORDER BY parts_name ASC, id ASC
                 LIMIT 300"
            );

            if ($userId > 0) {
                try {
                    $assignmentRows = DB::fetchAll(
                        "SELECT DISTINCT part_id
                         FROM user_operational_scopes
                         WHERE user_id = ?
                           AND is_active = 1
                           AND COALESCE(part_id, 0) > 0",
                        [$userId]
                    );
                    foreach ($assignmentRows as $assignmentRow) {
                        $partId = (int)($assignmentRow['part_id'] ?? 0);
                        if ($partId > 0) {
                            $assignedPartIds[] = $partId;
                        }
                    }
                    $assignedPartIds = array_values(array_unique($assignedPartIds));
                } catch (\Throwable $e) {
                    error_log('OperatorSurfaceComposer::buildPreparationFocusData (scope query): ' . $e->getMessage());
                    $assignedPartIds = [];
                }
            }

            $readyAssignedOrder = '1';
            $readyAssignedParams = [];
            if ($assignedPartIds !== []) {
                $readyAssignedPlaceholders = implode(',', array_fill(0, count($assignedPartIds), '?'));
                $readyAssignedOrder = "CASE WHEN d.product_id IN ({$readyAssignedPlaceholders}) THEN 0 ELSE 1 END";
                $readyAssignedParams = $assignedPartIds;
            }

            $readySql =
                "SELECT
                    COALESCE(NULLIF(TRIM(d.customer_name), ''), 'Unspecified Destination') AS destination,
                    COALESCE(d.required_date, d.order_date) AS dispatch_date,
                    d.product_id,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                    ROUND(COALESCE(SUM(d.qty), 0), 2) AS ready_qty,
                    CASE
                        WHEN ? = 'dispatch_leader' THEN
                            CASE
                                WHEN LOWER(COALESCE(s.override_status, '')) IN ('eligible', 'released', 'ready') THEN 0
                                ELSE 1
                            END
                        ELSE 0
                    END AS role_sort
                 FROM daily_orders d
                 INNER JOIN products p ON p.id = d.product_id
                 LEFT JOIN mfg_stage_readiness s
                    ON s.product_id = d.product_id
                   AND s.ref_date = COALESCE(d.required_date, d.order_date)
                   AND s.stage = 'dispatch'
                 WHERE COALESCE(d.required_date, d.order_date) BETWEEN ? AND ?
                   AND LOWER(COALESCE(d.status, 'open')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                   AND (
                        LOWER(COALESCE(s.override_status, '')) IN ('eligible', 'released', 'ready')
                        OR LOWER(COALESCE(d.status, '')) IN ('ready', 'packed', 'ready_dispatch', 'ready to dispatch')
                   )";
            $readyParams = [$dashboardType, $selectedDate, $toDate];
            if ($selectedProductId > 0) {
                $readySql .= ' AND d.product_id = ?';
                $readyParams[] = $selectedProductId;
            }
            $readySql .=
                " GROUP BY destination, dispatch_date, d.product_id, part_name, part_number, role_sort
                  ORDER BY role_sort ASC, {$readyAssignedOrder} ASC, dispatch_date ASC, destination ASC, part_name ASC
                  LIMIT 120";
            $readyParams = array_merge($readyParams, $readyAssignedParams);
            $readyRows = DB::fetchAll($readySql, $readyParams);

            $bundleSql =
                "SELECT
                    h.id,
                    COALESCE(NULLIF(TRIM(h.bundle_code), ''), CONCAT('B-', h.id)) AS bundle_code,
                    COALESCE(NULLIF(TRIM(h.pallet_code), ''), '-') AS pallet_code,
                    COALESCE(NULLIF(TRIM(h.destination), ''), 'Unspecified Destination') AS destination,
                    h.eta_load_at,
                    COALESCE(NULLIF(TRIM(h.driver_name), ''), '-') AS driver_name,
                    COALESCE(NULLIF(TRIM(h.truck_no), ''), '-') AS truck_no,
                    COALESCE(NULLIF(TRIM(h.status), ''), 'draft') AS status,
                    MIN(l.dispatch_date) AS first_dispatch_date,
                    ROUND(COALESCE(SUM(l.qty), 0), 2) AS total_qty,
                    COUNT(DISTINCT l.product_id) AS part_count,
                    GROUP_CONCAT(CONCAT(COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', l.product_id)), ' ', ROUND(l.qty, 2)) ORDER BY p.parts_name ASC SEPARATOR ', ') AS parts_summary,
                    CASE
                        WHEN ? = 'dispatch_leader' THEN
                            CASE LOWER(COALESCE(h.status, 'draft'))
                                WHEN 'draft' THEN 0
                                WHEN 'open' THEN 1
                                WHEN 'ready' THEN 2
                                ELSE 3
                            END
                        ELSE 0
                    END AS role_sort
                 FROM mfg_dispatch_bundle_headers h
                 INNER JOIN mfg_dispatch_bundle_lines l ON l.bundle_id = h.id
                 INNER JOIN products p ON p.id = l.product_id
                 WHERE l.dispatch_date BETWEEN ? AND ?";
            $bundleParams = [$dashboardType, $selectedDate, $toDate];
            if ($selectedProductId > 0) {
                $bundleSql .= ' AND l.product_id = ?';
                $bundleParams[] = $selectedProductId;
            }
            $bundleSql .=
                " GROUP BY h.id, bundle_code, pallet_code, destination, h.eta_load_at, driver_name, truck_no, status, role_sort
                                    ORDER BY role_sort ASC, COALESCE(h.eta_load_at, CONCAT(first_dispatch_date, ' 23:59:59')) ASC, destination ASC, pallet_code ASC
                  LIMIT 120";
            $bundleRows = DB::fetchAll($bundleSql, $bundleParams);

            $defaultRows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(destination), ''), '') AS destination,
                    COALESCE(NULLIF(TRIM(driver_name), ''), '') AS driver_name,
                    COALESCE(NULLIF(TRIM(truck_no), ''), '') AS truck_no
                 FROM mfg_dispatch_bundle_headers
                 ORDER BY updated_at DESC, id DESC
                 LIMIT 300"
            );
            foreach ($defaultRows as $defaultRow) {
                $destination = trim((string)($defaultRow['destination'] ?? ''));
                $driverName = trim((string)($defaultRow['driver_name'] ?? ''));
                $truckNo = trim((string)($defaultRow['truck_no'] ?? ''));

                if ($defaultDriverName === '' && $driverName !== '') {
                    $defaultDriverName = $driverName;
                }
                if ($defaultTruckNo === '' && $truckNo !== '') {
                    $defaultTruckNo = $truckNo;
                }

                if ($destination === '' || isset($destinationDefaults[$destination])) {
                    continue;
                }

                $destinationDefaults[$destination] = [
                    'driver_name' => $driverName,
                    'truck_no' => $truckNo,
                ];
            }
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildPreparationFocusData: ' . $e->getMessage());
        }

        $destinations = [];
        foreach ($readyRows as $readyRow) {
            $destination = trim((string)($readyRow['destination'] ?? ''));
            if ($destination !== '') {
                $destinations[$destination] = true;
            }
        }

        $readyIndex = [];
        foreach ($readyRows as $readyRow) {
            $dispatchDate = trim((string)($readyRow['dispatch_date'] ?? ''));
            $destination = trim((string)($readyRow['destination'] ?? ''));
            $productId = (int)($readyRow['product_id'] ?? 0);
            $readyQty = (float)($readyRow['ready_qty'] ?? 0);
            if ($dispatchDate === '' || $destination === '' || $productId <= 0 || $readyQty <= 0) {
                continue;
            }

            $indexKey = strtolower($dispatchDate . '|' . $destination);
            if (!isset($readyIndex[$indexKey]) || !is_array($readyIndex[$indexKey])) {
                $readyIndex[$indexKey] = [];
            }

            $readyIndex[$indexKey][] = [
                'product_id' => $productId,
                'part_name' => trim((string)($readyRow['part_name'] ?? '')),
                'part_number' => trim((string)($readyRow['part_number'] ?? '')),
                'ready_qty' => round($readyQty, 2),
            ];
        }

        $flash = (string)($_SESSION['operator_preparation_flash_ok'] ?? '');
        $error = (string)($_SESSION['operator_preparation_flash_err'] ?? '');
        unset($_SESSION['operator_preparation_flash_ok'], $_SESSION['operator_preparation_flash_err']);

        return [
            'title' => $this->tr('operator.preparation.title', 'Preparation'),
            'subtitle' => $this->tr('operator.preparation.subtitle', 'Bundle dispatch-ready parts into pallet-destination loads.'),
            'selected_date' => $selectedDate,
            'selected_product_id' => $selectedProductId,
            'products' => $products,
            'ready_rows' => $readyRows,
            'ready_index' => $readyIndex,
            'bundle_rows' => $bundleRows,
            'destinations' => array_keys($destinations),
            'destination_defaults' => $destinationDefaults,
            'default_driver_name' => $defaultDriverName,
            'default_truck_no' => $defaultTruckNo,
            'flash' => $flash,
            'error' => $error,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildProcessingFocusData(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $query = (array)($this->context['current_query'] ?? []);
        $selectedDate = trim((string)($query['processing_date'] ?? $today));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
            $selectedDate = $today;
        }

        $selectedProductId = (int)($query['product_id'] ?? 0);
        $toDate = (new \DateTimeImmutable($selectedDate))->modify('+14 days')->format('Y-m-d');
        $dashboardType = strtolower(trim((string)($this->context['dashboard_type'] ?? 'operator')));
        $user = Auth::user();
        $userId = (int)($user['id'] ?? 0);
        $assemblyRows = [];
        $qcRows = [];
        $products = [];
        $assignedPartIds = [];

        try {
            $products = DB::fetchAll(
                "SELECT id, parts_name, parts_number
                 FROM products
                 ORDER BY parts_name ASC, id ASC
                 LIMIT 300"
            );

            if ($userId > 0) {
                try {
                    $assignmentRows = DB::fetchAll(
                        "SELECT DISTINCT part_id
                         FROM user_operational_scopes
                         WHERE user_id = ?
                           AND is_active = 1
                           AND COALESCE(part_id, 0) > 0",
                        [$userId]
                    );
                    foreach ($assignmentRows as $assignmentRow) {
                        $partId = (int)($assignmentRow['part_id'] ?? 0);
                        if ($partId > 0) {
                            $assignedPartIds[] = $partId;
                        }
                    }
                    $assignedPartIds = array_values(array_unique($assignedPartIds));
                } catch (\Throwable $e) {
                    error_log('OperatorSurfaceComposer::buildProcessingFocusData (scope query): ' . $e->getMessage());
                    $assignedPartIds = [];
                }
            }

            $assemblyAssignedOrder = '1';
            $assemblyAssignedParams = [];
            if ($assignedPartIds !== []) {
                $assemblyAssignedPlaceholders = implode(',', array_fill(0, count($assignedPartIds), '?'));
                $assemblyAssignedOrder = "CASE WHEN d.product_id IN ({$assemblyAssignedPlaceholders}) THEN 0 ELSE 1 END";
                $assemblyAssignedParams = $assignedPartIds;
            }

            $assemblySql =
                "SELECT
                    d.id,
                    d.demand_date,
                    d.status,
                    COALESCE(d.approved_qty, d.adjusted_qty, d.system_qty, 0) AS planned_qty,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                    CASE
                        WHEN ? = 'assembly_leader' THEN
                            CASE LOWER(COALESCE(d.status, 'calculated'))
                                WHEN 'approved' THEN 0
                                WHEN 'adjusted' THEN 1
                                ELSE 2
                            END
                        WHEN ? = 'production_leader' THEN
                            CASE LOWER(COALESCE(d.status, 'calculated'))
                                WHEN 'adjusted' THEN 0
                                WHEN 'approved' THEN 1
                                ELSE 2
                            END
                        ELSE
                            CASE LOWER(COALESCE(d.status, 'calculated'))
                                WHEN 'adjusted' THEN 0
                                WHEN 'approved' THEN 1
                                ELSE 2
                            END
                    END AS role_sort
                 FROM mfg_part_demands d
                 INNER JOIN products p ON p.id = d.product_id
                 WHERE d.demand_type = 'assembly'
                   AND d.demand_date BETWEEN ? AND ?";
            $assemblyParams = [$dashboardType, $dashboardType, $selectedDate, $toDate];
            if ($selectedProductId > 0) {
                $assemblySql .= " AND d.product_id = ?";
                $assemblyParams[] = $selectedProductId;
            }
            $assemblySql .= " ORDER BY role_sort ASC, {$assemblyAssignedOrder} ASC, d.demand_date ASC, d.id DESC LIMIT 40";
            $assemblyParams = array_merge($assemblyParams, $assemblyAssignedParams);
            $assemblyRows = DB::fetchAll($assemblySql, $assemblyParams);

            $qcAssignedOrder = '1';
            $qcAssignedParams = [];
            if ($assignedPartIds !== []) {
                $qcAssignedPlaceholders = implode(',', array_fill(0, count($assignedPartIds), '?'));
                $qcAssignedOrder = "CASE WHEN q.product_id IN ({$qcAssignedPlaceholders}) THEN 0 ELSE 1 END";
                $qcAssignedParams = $assignedPartIds;
            }

            $qcSql =
                "SELECT
                    q.id,
                    q.plan_date,
                    q.status,
                    q.priority,
                    COALESCE(q.planned_qty, 0) AS planned_qty,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', q.product_id)) AS part_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                    CASE
                        WHEN ? = 'qc_leader' THEN
                            CASE LOWER(COALESCE(q.status, 'system generated'))
                                WHEN 'system generated' THEN 0
                                WHEN 'verified by qc' THEN 1
                                WHEN 'adjusted by qc' THEN 2
                                WHEN 'approved by authority' THEN 3
                                ELSE 4
                            END
                        ELSE
                            CASE LOWER(COALESCE(q.priority, 'normal'))
                                WHEN 'critical' THEN 0
                                WHEN 'high' THEN 1
                                WHEN 'medium' THEN 2
                                WHEN 'normal' THEN 3
                                ELSE 4
                            END
                    END AS role_sort
                 FROM qc_plans q
                 INNER JOIN products p ON p.id = q.product_id
                 WHERE q.plan_date BETWEEN ? AND ?";
            $qcParams = [$dashboardType, $selectedDate, $toDate];
            if ($selectedProductId > 0) {
                $qcSql .= " AND q.product_id = ?";
                $qcParams[] = $selectedProductId;
            }
            $qcSql .= " ORDER BY role_sort ASC, {$qcAssignedOrder} ASC, q.plan_date ASC, q.id DESC LIMIT 40";
            $qcParams = array_merge($qcParams, $qcAssignedParams);
            $qcRows = DB::fetchAll($qcSql, $qcParams);
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildProcessingFocusData: ' . $e->getMessage());
        }

        $flash = (string)($_SESSION['operator_processing_flash_ok'] ?? '');
        $error = (string)($_SESSION['operator_processing_flash_err'] ?? '');
        unset($_SESSION['operator_processing_flash_ok'], $_SESSION['operator_processing_flash_err']);

        return [
            'title' => $this->tr('operator.processing.title', 'Processing'),
            'subtitle' => $this->tr('operator.processing.subtitle', 'Minimal workbench for assembly and QC planning queues.'),
            'selected_date' => $selectedDate,
            'selected_product_id' => $selectedProductId,
            'products' => $products,
            'assembly_rows' => $assemblyRows,
            'qc_rows' => $qcRows,
            'flash' => $flash,
            'error' => $error,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildProductionFocusData(): array
    {
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $summary = [
            'low_coverage' => 0,
            'shortage_parts' => 0,
            'at_risk_today' => 0,
            'overstock_parts' => 0,
        ];

        $coverageRows = [];
        $queueRows = [];
        $products = [];
        $machines = [];

        try {
            $summaryRow = DB::fetchOne(
                "SELECT
                    SUM(CASE WHEN LOWER(COALESCE(coverage_status, '')) = 'low' THEN 1 ELSE 0 END) AS low_coverage,
                    SUM(CASE WHEN COALESCE(shortage_qty, 0) > 0 THEN 1 ELSE 0 END) AS shortage_parts,
                    SUM(CASE WHEN LOWER(COALESCE(coverage_status, '')) = 'low' OR COALESCE(shortage_qty, 0) > 0 THEN 1 ELSE 0 END) AS at_risk_today
                 FROM daily_orders
                 WHERE order_date = ?",
                [$today]
            ) ?: [];

            $overstockRow = DB::fetchOne(
                "SELECT COUNT(*) AS c
                 FROM (
                    SELECT d.product_id
                    FROM daily_orders d
                    WHERE d.order_date = ?
                    GROUP BY d.product_id
                    HAVING COALESCE(MAX(d.usable_stock_qty), 0) > COALESCE(SUM(d.qty), 0)
                 ) overstock_sub",
                [$today]
            ) ?: [];

            $summary = [
                'low_coverage' => (int)($summaryRow['low_coverage'] ?? 0),
                'shortage_parts' => (int)($summaryRow['shortage_parts'] ?? 0),
                'at_risk_today' => (int)($summaryRow['at_risk_today'] ?? 0),
                'overstock_parts' => (int)($overstockRow['c'] ?? 0),
            ];

            $coverageRows = DB::fetchAll(
                "SELECT
                    d.product_id AS part_id,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                    MIN(COALESCE(d.coverage_pct, 100)) AS coverage_pct,
                    SUM(COALESCE(d.shortage_qty, 0)) AS shortage_qty
                 FROM daily_orders d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE d.order_date = ?
                   AND (LOWER(COALESCE(d.coverage_status, '')) = 'low' OR COALESCE(d.shortage_qty, 0) > 0)
                 GROUP BY d.product_id, part_name, part_number
                 ORDER BY shortage_qty DESC, coverage_pct ASC, part_name ASC
                 LIMIT 8",
                [$today]
            );

            $queueRows = DB::fetchAll(
                "SELECT
                    pp.id,
                    pp.plan_date,
                    pp.planned_qty,
                    pp.status,
                    COALESCE(pp.coverage_pct, 0) AS coverage_pct,
                    COALESCE(pp.shortage_qty, 0) AS shortage_qty,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', pp.product_id)) AS part_name,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), '-') AS part_number,
                    COALESCE(NULLIF(TRIM(m.machine_name), ''), COALESCE(NULLIF(TRIM(m.machine_no), ''), '-')) AS machine_label
                 FROM production_plans pp
                 LEFT JOIN products p ON p.id = pp.product_id
                 LEFT JOIN machines m ON m.id = pp.machine_id
                 WHERE pp.plan_date >= ?
                 ORDER BY pp.plan_date ASC, pp.id DESC
                 LIMIT 30",
                [$today]
            );

            $products = DB::fetchAll(
                "SELECT id, parts_name, parts_number
                 FROM products
                 ORDER BY parts_name ASC, id ASC
                 LIMIT 200"
            );

            $machines = DB::fetchAll(
                "SELECT id, machine_no, machine_name
                 FROM machines
                 ORDER BY machine_no ASC, machine_name ASC, id ASC"
            );
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildProductionFocusData: ' . $e->getMessage());
        }

        $productionFlash = (string)($_SESSION['operator_production_flash_ok'] ?? '');
        $productionError = (string)($_SESSION['operator_production_flash_err'] ?? '');
        unset($_SESSION['operator_production_flash_ok'], $_SESSION['operator_production_flash_err']);

        return [
            'title' => $this->tr('operator.production.title', 'Production'),
            'subtitle' => $this->tr('operator.production.subtitle', 'Plans queue, coverage, and quick plan creation in one workspace.'),
            'summary' => $summary,
            'coverage_rows' => $coverageRows,
            'queue_rows' => $queueRows,
            'products' => $products,
            'machines' => $machines,
            'today' => $today,
            'create_action' => '/u/' . urlencode($this->username) . '/production/plan/create',
            'list_url' => '/u/' . $this->username . '/production',
            'queue_url' => '/u/' . $this->username . '/production',
            'flash' => $productionFlash,
            'error' => $productionError,
        ];
    }

    /**
     * @return array{account:array<string,mixed>,policy:array<string,mixed>,flash:string,error:string}
     */
    private function buildAccountPanelData(): array
    {
        $empty = [
            'account' => [],
            'policy' => [],
            'flash' => '',
            'error' => '',
        ];

        $user = Auth::user();
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return $empty;
        }

        try {
            $service = new MyAccountService();
            return [
                'account' => $service->accountForUser($userId),
                'policy' => $service->passwordPolicy(),
                'flash' => (string)($_SESSION['my_account_flash_ok'] ?? ''),
                'error' => (string)($_SESSION['my_account_flash_err'] ?? ''),
            ];
        } catch (\Throwable $e) {
            return [
                'account' => [],
                'policy' => [],
                'flash' => '',
                'error' => $e->getMessage(),
            ];
        } finally {
            unset($_SESSION['my_account_flash_ok'], $_SESSION['my_account_flash_err']);
        }
    }

    /**
     * @param array<string,mixed> $panel
     */
    private function renderAccountPanelMarkup(array $panel): string
    {
        $viewPath = APP_ROOT . '/plugins/Base/Views/account/my_account.php';
        if (!is_file($viewPath)) {
            return '<div class="recent-focus-empty">' . htmlspecialchars($this->tr('account.load_error', 'Unable to load account page.')) . '</div>';
        }

        $account = is_array($panel['account'] ?? null) ? (array)$panel['account'] : [];
        $policy = is_array($panel['policy'] ?? null) ? (array)$panel['policy'] : [];
        $flash = (string)($panel['flash'] ?? '');
        $error = (string)($panel['error'] ?? '');
        $account_base_url = '/u/' . rawurlencode($this->username) . '/account';

        ob_start();
        include $viewPath;
        return (string)ob_get_clean();
    }

    /**
     * @return array{title:string,subtitle:string,empty_label:string,total:int,unique_events:int,event_bars:array<int,array{label:string,count:int,percent:float}>,rows:array<int,array{when:string,event_type:string,action:string,module:string,note:string,actor:string,event_display:string,action_display:string}>}
     */
    private function buildRecentActivityData(): array
    {
        $empty = [
            'title' => $this->tr('operator.recent.title', 'Recent User Activity'),
            'subtitle' => $this->tr('operator.recent.subtitle', 'Latest logged actions for this operator account.'),
            'empty_label' => $this->tr('operator.recent.empty', 'No activity log entries found.'),
            'total' => 0,
            'unique_events' => 0,
            'event_bars' => [],
            'rows' => [],
        ];

        $userEmail = trim((string)($this->context['user_email'] ?? ''));
        if ($userEmail === '') {
            return $empty;
        }

        try {
            $rows = DB::fetchAll(
                "SELECT
                    created_at,
                    event_type,
                    action_name,
                    actor_email,
                    actor_display_name,
                    entity_type,
                    entity_id,
                    app_key,
                    module_key,
                    note_text,
                    reason_text,
                    new_state
                 FROM audit_activity_log
                 WHERE actor_email = ?
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY created_at DESC, id DESC
                 LIMIT 200",
                [$userEmail]
            );
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildRecentActivityData: ' . $e->getMessage());
            return $empty;
        }

        if ($rows === []) {
            return $empty;
        }

        $eventCounts = [];
        $formattedRows = [];
        foreach ((array)$rows as $row) {
            $eventType = trim((string)($row['event_type'] ?? ''));
            $eventKey = strtolower($eventType);
            $eventLabel = $eventType !== ''
                ? ucwords((string)preg_replace('/[\/_\-]+/', ' ', $eventType))
                : $this->tr('operator.recent.event_unknown', 'Unknown');
            if ($eventKey === 'workflow') {
                $eventLabel = $this->tr('operator.recent.event_workflow', 'Approval Workflow');
            } elseif ($eventKey === 'approval') {
                $eventLabel = $this->tr('operator.recent.event_approval', 'Approval Event');
            }
            $eventCounts[$eventLabel] = (int)($eventCounts[$eventLabel] ?? 0) + 1;

            $actionName = trim((string)($row['action_name'] ?? ''));
            $actionKey = strtolower($actionName);
            $actionLabel = $actionName !== ''
                ? ucwords((string)preg_replace('/[\/_\-]+/', ' ', $actionName))
                : $this->tr('operator.recent.action_unknown', 'Action logged');
            if ($actionKey === 'approved') {
                $actionLabel = $this->tr('operator.recent.action_approved', 'Approved Request');
            } elseif ($actionKey === 'rejected') {
                $actionLabel = $this->tr('operator.recent.action_rejected', 'Rejected Request');
            } elseif ($actionKey === 'submitted') {
                $actionLabel = $this->tr('operator.recent.action_submitted', 'Submitted Request');
            }

            $actorLabel = trim((string)($row['actor_display_name'] ?? ''));
            if ($actorLabel === '') {
                $actorLabel = trim((string)($row['actor_email'] ?? ''));
            }
            if ($actorLabel === '') {
                $actorLabel = $this->tr('operator.recent.actor_system', 'System');
            }

            $appKey = trim((string)($row['app_key'] ?? ''));
            $moduleKey = trim((string)($row['module_key'] ?? ''));
            $moduleText = trim($appKey . ($moduleKey !== '' ? ' / ' . $moduleKey : ''));
            if ($moduleText === '') {
                $moduleText = $this->tr('operator.recent.module_unknown', 'Unscoped');
            }

            $entityType = trim((string)($row['entity_type'] ?? ''));
            $entityId = (int)($row['entity_id'] ?? 0);
            $entitySummary = $this->buildRecentEntitySummary($entityType, $entityId);
            $activityDate = $this->formatRecentActivityDate((string)($row['created_at'] ?? ''));

            $newStateText = trim((string)($row['new_state'] ?? ''));
            $stateLabel = $newStateText === ''
                ? ''
                : sprintf(
                    $this->tr('operator.recent.state_label', 'status: %s'),
                    ucwords((string)preg_replace('/[\/_\-]+/', ' ', $newStateText))
                );

            $eventDisplay = $entitySummary;
            if ($stateLabel !== '') {
                $eventDisplay .= ' | ' . $stateLabel;
            }
            $eventDisplay .= ' | ' . $moduleText;

            $actionDisplay = sprintf(
                $this->tr('operator.recent.action_display_specific', '%1$s: %2$s on %3$s by %4$s'),
                $actionLabel,
                $entitySummary,
                $activityDate,
                $actorLabel
            );

            $note = trim((string)($row['note_text'] ?? ''));
            if ($note === '') {
                $note = trim((string)($row['reason_text'] ?? ''));
            }
            if ($note === '') {
                $note = trim((string)($row['new_state'] ?? ''));
            }
            if ($note === '') {
                $note = '-';
            }

            $formattedRows[] = [
                'when' => trim((string)($row['created_at'] ?? '')),
                'event_type' => $eventLabel,
                'action' => $actionLabel,
                'module' => $moduleText,
                'note' => $note,
                'actor' => $actorLabel,
                'event_display' => $eventDisplay,
                'action_display' => $actionDisplay,
            ];
        }

        arsort($eventCounts);
        $maxCount = 0;
        foreach ($eventCounts as $count) {
            $maxCount = max($maxCount, (int)$count);
        }

        $eventBars = [];
        foreach (array_slice($eventCounts, 0, 6, true) as $label => $count) {
            $eventBars[] = [
                'label' => (string)$label,
                'count' => (int)$count,
                'percent' => $maxCount > 0 ? round(((int)$count / $maxCount) * 100, 2) : 0.0,
            ];
        }

        return [
            'title' => $empty['title'],
            'subtitle' => $empty['subtitle'],
            'empty_label' => $empty['empty_label'],
            'total' => count($formattedRows),
            'unique_events' => count($eventCounts),
            'event_bars' => $eventBars,
            'rows' => $formattedRows,
        ];
    }

    private function formatRecentActivityDate(string $rawDateTime): string
    {
        $rawDateTime = trim($rawDateTime);
        if ($rawDateTime === '') {
            return '-';
        }

        try {
            $dt = new \DateTimeImmutable($rawDateTime);
            return $dt->format('m/d');
        } catch (\Throwable $e) {
            return $rawDateTime;
        }
    }

    private function buildRecentEntitySummary(string $entityType, int $entityId): string
    {
        $normalizedType = strtolower(trim($entityType));
        if ($entityId <= 0) {
            return $this->tr('operator.recent.entity_unknown', 'record');
        }

        $cacheKey = $normalizedType . ':' . $entityId;
        if (isset($this->recentEntitySummaryCache[$cacheKey])) {
            return $this->recentEntitySummaryCache[$cacheKey];
        }

        $summary = '';
        try {
            if ($normalizedType === 'production_plan') {
                $row = DB::fetchOne(
                    "SELECT
                        pp.plan_date,
                        pp.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', pp.product_id)) AS part_name
                     FROM production_plans pp
                     LEFT JOIN products p ON p.id = pp.product_id
                     WHERE pp.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $planDate = $this->formatRecentActivityDate((string)($row['plan_date'] ?? ''));
                    if ($partName === '') {
                        $partName = 'Part #' . (int)($row['product_id'] ?? 0);
                    }
                    $summary = sprintf(
                        $this->tr('operator.recent.entity.production_plan_with_part_date', 'production plan for %1$s dated %2$s'),
                        $partName,
                        $planDate
                    );
                }
            } elseif ($normalizedType === 'qc_plan') {
                $row = DB::fetchOne(
                    "SELECT
                        qp.plan_date,
                        qp.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', qp.product_id)) AS part_name
                     FROM qc_plans qp
                     LEFT JOIN products p ON p.id = qp.product_id
                     WHERE qp.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $summary = sprintf(
                        $this->tr('operator.recent.entity.qc_plan_with_part_date', 'qc plan for %1$s dated %2$s'),
                        trim((string)($row['part_name'] ?? 'Part #' . (int)($row['product_id'] ?? 0))),
                        $this->formatRecentActivityDate((string)($row['plan_date'] ?? ''))
                    );
                }
            } elseif ($normalizedType === 'daily_order') {
                $row = DB::fetchOne(
                    "SELECT
                        d.order_date,
                        d.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', d.product_id)) AS part_name
                     FROM daily_orders d
                     LEFT JOIN products p ON p.id = d.product_id
                     WHERE d.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $summary = sprintf(
                        $this->tr('operator.recent.entity.daily_order_with_part_date', 'daily order for %1$s dated %2$s'),
                        trim((string)($row['part_name'] ?? 'Part #' . (int)($row['product_id'] ?? 0))),
                        $this->formatRecentActivityDate((string)($row['order_date'] ?? ''))
                    );
                }
            } elseif ($normalizedType === 'procurement_request') {
                $row = DB::fetchOne(
                    "SELECT
                        r.request_ref,
                        r.needed_date,
                        r.product_id,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), CONCAT('Part #', r.product_id)) AS part_name
                     FROM procurement_requests r
                     LEFT JOIN products p ON p.id = r.product_id
                     WHERE r.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $requestRef = trim((string)($row['request_ref'] ?? 'REQ-' . $entityId));
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $neededDate = $this->formatRecentActivityDate((string)($row['needed_date'] ?? ''));
                    if ($partName !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_request_with_part_date', 'procurement request %1$s for %2$s needed by %3$s'),
                            $requestRef,
                            $partName,
                            $neededDate
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_request_with_date', 'procurement request %1$s needed by %2$s'),
                            $requestRef,
                            $neededDate
                        );
                    }
                }
            } elseif ($normalizedType === 'procurement_order') {
                $row = DB::fetchOne(
                    "SELECT
                        po.po_ref,
                        po.expected_date,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), '') AS part_name
                     FROM procurement_purchase_orders po
                     LEFT JOIN procurement_purchase_order_lines pol ON pol.po_id = po.id
                     LEFT JOIN products p ON p.id = pol.product_id
                     WHERE po.id = ?
                     ORDER BY pol.id ASC
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $poRef = trim((string)($row['po_ref'] ?? 'PO-' . $entityId));
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $expectedDate = $this->formatRecentActivityDate((string)($row['expected_date'] ?? ''));
                    if ($partName !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_order_with_part_date', 'purchase order %1$s for %2$s expected %3$s'),
                            $poRef,
                            $partName,
                            $expectedDate
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_order_with_date', 'purchase order %1$s expected %2$s'),
                            $poRef,
                            $expectedDate
                        );
                    }
                }
            } elseif ($normalizedType === 'procurement_receipt') {
                $row = DB::fetchOne(
                    "SELECT
                        r.receipt_ref,
                        r.receipt_date,
                        COALESCE(NULLIF(TRIM(p.parts_name), ''), NULLIF(TRIM(p.parts_number), ''), '') AS part_name
                     FROM procurement_receipts r
                     LEFT JOIN procurement_purchase_order_lines pol ON pol.id = r.po_line_id
                     LEFT JOIN products p ON p.id = pol.product_id
                     WHERE r.id = ?
                     LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $receiptRef = trim((string)($row['receipt_ref'] ?? 'GRN-' . $entityId));
                    $partName = trim((string)($row['part_name'] ?? ''));
                    $receiptDate = $this->formatRecentActivityDate((string)($row['receipt_date'] ?? ''));
                    if ($partName !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_receipt_with_part_date', 'receipt %1$s for %2$s dated %3$s'),
                            $receiptRef,
                            $partName,
                            $receiptDate
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_receipt_with_date', 'receipt %1$s dated %2$s'),
                            $receiptRef,
                            $receiptDate
                        );
                    }
                }
            } elseif ($normalizedType === 'procurement_supplier') {
                $row = DB::fetchOne(
                    "SELECT supplier_code, supplier_name FROM procurement_suppliers WHERE id = ? LIMIT 1",
                    [$entityId]
                );
                if (is_array($row) && $row !== []) {
                    $supplierCode = trim((string)($row['supplier_code'] ?? ''));
                    $supplierName = trim((string)($row['supplier_name'] ?? 'Supplier #' . $entityId));
                    if ($supplierCode !== '') {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_supplier_with_code', 'supplier %1$s (%2$s)'),
                            $supplierName,
                            $supplierCode
                        );
                    } else {
                        $summary = sprintf(
                            $this->tr('operator.recent.entity.procurement_supplier', 'supplier %1$s'),
                            $supplierName
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildRecentEntitySummary: ' . $e->getMessage());
            $summary = '';
        }

        if ($summary === '') {
            $readableType = $normalizedType !== ''
                ? strtolower((string)preg_replace('/[\/_\-]+/', ' ', $normalizedType))
                : $this->tr('operator.recent.entity_record', 'record');
            $summary = sprintf(
                $this->tr('operator.recent.entity.generic_with_id', '%1$s #%2$d'),
                $readableType,
                $entityId
            );
        }

        $this->recentEntitySummaryCache[$cacheKey] = $summary;
        return $summary;
    }

    /**
     * @return array{key:string,title:string,subtitle:string,metric_label:string,empty_label:string,rows:array<int,array{name:string,value_label:string,bar_percent:float,detail_left:string,detail_right:string}>}
     */
    private function buildCriticalPartsDetailData(): array
    {
        $empty = [
            'key' => 'parts',
            'title' => $this->tr('operator.critical.parts_detail_title', 'Parts Criticality'),
            'subtitle' => $this->tr('operator.critical.parts_detail_subtitle', 'Shortage and coverage risk by part (today).'),
            'metric_label' => $this->tr('operator.critical.shortage', 'Shortage'),
            'empty_label' => $this->tr('operator.critical.no_data', 'No critical records found.'),
            'rows' => [],
        ];

        try {
            $rows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', d.product_id)) AS part_name,
                    COALESCE(SUM(GREATEST(COALESCE(d.shortage_qty, 0), 0)), 0) AS shortage_qty,
                    COALESCE(SUM(COALESCE(d.qty, 0)), 0) AS demand_qty,
                    COALESCE(MAX(COALESCE(d.usable_stock_qty, 0)), 0) AS stock_qty,
                    MIN(COALESCE(d.coverage_pct, 100)) AS min_coverage
                 FROM daily_orders d
                 LEFT JOIN products p ON p.id = d.product_id
                 WHERE d.order_date = ?
                 GROUP BY d.product_id, part_name
                 HAVING shortage_qty > 0 OR min_coverage < 100
                 ORDER BY shortage_qty DESC, min_coverage ASC, part_name ASC
                 LIMIT 8",
                [date('Y-m-d')]
            );
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildCriticalPartsDetailData: ' . $e->getMessage());
            return $empty;
        }

        $maxShortage = 0.0;
        foreach ((array)$rows as $row) {
            $maxShortage = max($maxShortage, (float)($row['shortage_qty'] ?? 0));
        }

        $formatted = [];
        foreach ((array)$rows as $row) {
            $shortage = max(0.0, (float)($row['shortage_qty'] ?? 0));
            $coverage = max(0.0, (float)($row['min_coverage'] ?? 0));
            $demand = max(0.0, (float)($row['demand_qty'] ?? 0));
            $stock = max(0.0, (float)($row['stock_qty'] ?? 0));
            $formatted[] = [
                'name' => trim((string)($row['part_name'] ?? '')),
                'value_label' => number_format($shortage, 0, '.', ''),
                'bar_percent' => $maxShortage > 0 ? round(($shortage / $maxShortage) * 100, 2) : 0.0,
                'detail_left' => $this->tr('operator.critical.coverage', 'Coverage') . ': ' . number_format($coverage, 1, '.', '') . '%',
                'detail_right' => $this->tr('operator.critical.stock_demand', 'Stock / Demand') . ': ' . number_format($stock, 0, '.', '') . ' / ' . number_format($demand, 0, '.', ''),
            ];
        }

        $empty['rows'] = $formatted;
        return $empty;
    }

    /**
     * @return array{key:string,title:string,subtitle:string,metric_label:string,empty_label:string,rows:array<int,array{name:string,value_label:string,bar_percent:float,detail_left:string,detail_right:string}>}
     */
    private function buildCriticalZairyoDetailData(): array
    {
        $empty = [
            'key' => 'zairyo',
            'title' => $this->tr('operator.critical.zairyo_detail_title', 'Material Pressure'),
            'subtitle' => $this->tr('operator.critical.zairyo_detail_subtitle', 'Material shortage against active production plans.'),
            'metric_label' => $this->tr('operator.critical.shortage', 'Shortage'),
            'empty_label' => $this->tr('operator.critical.no_data', 'No critical records found.'),
            'rows' => [],
        ];

        try {
            $rows = DB::fetchAll(
                "SELECT
                    COALESCE(NULLIF(TRIM(m.material_name), ''), CONCAT('Material #', m.id)) AS material_name,
                    COALESCE(SUM(pp.planned_qty * COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) * (1 + COALESCE(pm.scrap_pct, 0) / 100)), 0) AS required_qty,
                    COALESCE(led.on_hand_qty, 0) AS on_hand_qty,
                    COALESCE(res.reserved_qty, 0) AS reserved_qty,
                    GREATEST(
                        COALESCE(SUM(pp.planned_qty * COALESCE(NULLIF(pm.qty_per_part, 0), pm.usage_qty, 0) * (1 + COALESCE(pm.scrap_pct, 0) / 100)), 0)
                        - (COALESCE(led.on_hand_qty, 0) - COALESCE(res.reserved_qty, 0)),
                        0
                    ) AS shortage_qty,
                    COALESCE(NULLIF(m.uom, ''), m.unit, 'kg') AS uom
                 FROM production_plans pp
                 INNER JOIN part_material_map pm ON pm.product_id = pp.product_id
                 INNER JOIN materials m ON m.id = pm.material_id
                 LEFT JOIN (
                     SELECT material_id, SUM(qty_delta) AS on_hand_qty
                     FROM material_ledger
                     GROUP BY material_id
                 ) led ON led.material_id = m.id
                 LEFT JOIN (
                     SELECT material_id, SUM(GREATEST(reserved_qty - fulfilled_qty, 0)) AS reserved_qty
                     FROM material_reservations
                     WHERE LOWER(COALESCE(status, 'active')) IN ('active', 'partial')
                     GROUP BY material_id
                 ) res ON res.material_id = m.id
                 WHERE pp.plan_date = ?
                   AND LOWER(COALESCE(pp.status, 'planned')) NOT IN ('cancelled', 'canceled', 'completed', 'closed')
                   AND COALESCE(pm.is_active, 1) = 1
                 GROUP BY m.id, material_name, uom, led.on_hand_qty, res.reserved_qty
                 HAVING shortage_qty > 0
                 ORDER BY shortage_qty DESC, material_name ASC
                 LIMIT 8",
                [date('Y-m-d')]
            );
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildCriticalZairyoDetailData: ' . $e->getMessage());
            return $empty;
        }

        $maxShortage = 0.0;
        foreach ((array)$rows as $row) {
            $maxShortage = max($maxShortage, (float)($row['shortage_qty'] ?? 0));
        }

        $formatted = [];
        foreach ((array)$rows as $row) {
            $shortage = max(0.0, (float)($row['shortage_qty'] ?? 0));
            $required = max(0.0, (float)($row['required_qty'] ?? 0));
            $available = (float)($row['on_hand_qty'] ?? 0) - (float)($row['reserved_qty'] ?? 0);
            $uom = trim((string)($row['uom'] ?? 'kg'));
            $formatted[] = [
                'name' => trim((string)($row['material_name'] ?? '')),
                'value_label' => number_format($shortage, 2, '.', '') . ' ' . ($uom !== '' ? $uom : 'kg'),
                'bar_percent' => $maxShortage > 0 ? round(($shortage / $maxShortage) * 100, 2) : 0.0,
                'detail_left' => $this->tr('operator.critical.required', 'Required') . ': ' . number_format($required, 2, '.', '') . ' ' . ($uom !== '' ? $uom : 'kg'),
                'detail_right' => $this->tr('operator.critical.available', 'Available') . ': ' . number_format($available, 2, '.', '') . ' ' . ($uom !== '' ? $uom : 'kg'),
            ];
        }

        $empty['rows'] = $formatted;
        return $empty;
    }

    /**
     * @return array{title:string,filter_label:string,all_label:string,date_label:string,qty_label:string,empty_label:string,dates:array<int,string>,products:array<int,array{key:string,label:string,series:array<int,int>,total:int,color_index:int}>}
     */
    private function buildDailyOrderChartData(): array
    {
        $empty = [
            'title' => $this->tr('nav.daily_orders', 'Daily Orders'),
            'model_label' => $this->tr('common.model', 'Model'),
            'filter_label' => $this->tr('operator.dashboard.parts', 'Parts'),
            'all_label' => $this->tr('common.all', 'All'),
            'date_from_label' => $this->tr('common.date_range_from', 'From'),
            'date_to_label' => $this->tr('common.date_range_to', 'To'),
            'date_label' => $this->tr('common.date', 'Date'),
            'qty_label' => $this->tr('common.qty', 'Qty'),
            'empty_label' => $this->tr('nav.topbar_search_no_matches', 'No results found'),
            'dates' => [],
            'models' => [],
            'products' => [],
        ];

        try {
            $dateRows = DB::fetchAll(
                'SELECT DISTINCT order_date FROM daily_orders ORDER BY order_date DESC LIMIT 60'
            );
            $dates = [];
            foreach ((array)$dateRows as $row) {
                $rawDate = trim((string)($row['order_date'] ?? ''));
                if ($rawDate !== '') {
                    $dates[] = $rawDate;
                }
            }
            if ($dates === []) {
                return $empty;
            }
            sort($dates);

            $datePlaceholders = implode(',', array_fill(0, count($dates), '?'));

            $allProducts = DB::fetchAll(
                "SELECT
                    p.id AS product_id,
                    COALESCE(NULLIF(TRIM(p.parts_name), ''), CONCAT('Part #', p.id)) AS product_name,
                    COALESCE(
                        NULLIF(TRIM(p.model), ''),
                        CASE
                            WHEN INSTR(COALESCE(NULLIF(TRIM(p.parts_number), ''), CONCAT('P-', p.id)), '-') > 0
                            THEN SUBSTRING_INDEX(COALESCE(NULLIF(TRIM(p.parts_number), ''), CONCAT('P-', p.id)), '-', 1)
                            ELSE COALESCE(NULLIF(TRIM(p.parts_number), ''), CONCAT('P-', p.id))
                        END
                    ) AS model_key,
                    COALESCE(NULLIF(TRIM(p.parts_number), ''), CONCAT('P-', p.id)) AS parts_number
                 FROM products p
                 ORDER BY product_name ASC",
                []
            );

            if ($allProducts === []) {
                return $empty;
            }

            $productIds = [];
            $products = [];
            $models = [];
            $dateIndex = [];
            foreach ($dates as $index => $dateKey) {
                $dateIndex[$dateKey] = $index;
            }

            $colorIndex = 0;
            foreach ((array)$allProducts as $productRow) {
                $productId = (int)($productRow['product_id'] ?? 0);
                if ($productId <= 0) {
                    continue;
                }
                $productIds[] = $productId;
                $modelKey = trim((string)($productRow['model_key'] ?? ''));
                if ($modelKey === '') {
                    $modelKey = 'M' . $productId;
                }
                $products[$productId] = [
                    'key' => 'p_' . $productId,
                    'label' => trim((string)($productRow['product_name'] ?? ('Part #' . $productId))),
                    'model_key' => $modelKey,
                    'series' => array_fill(0, count($dates), 0),
                    'total' => 0,
                    'color_index' => $colorIndex,
                ];
                $models[$modelKey] = [
                    'key' => $modelKey,
                    'label' => $modelKey,
                ];
                $colorIndex++;
            }

            ksort($models, SORT_NATURAL);

            if ($productIds === []) {
                return $empty;
            }

            $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
            $rows = DB::fetchAll(
                "SELECT
                    order_date,
                    product_id,
                    COALESCE(SUM(COALESCE(qty, 0)), 0) AS total_qty
                 FROM daily_orders
                 WHERE order_date IN ($datePlaceholders)
                   AND product_id IN ($productPlaceholders)
                 GROUP BY order_date, product_id",
                array_merge($dates, $productIds)
            );

            foreach ((array)$rows as $qtyRow) {
                $rowDate = trim((string)($qtyRow['order_date'] ?? ''));
                $rowProductId = (int)($qtyRow['product_id'] ?? 0);
                $qty = (int)round((float)($qtyRow['total_qty'] ?? 0));
                if (!isset($products[$rowProductId], $dateIndex[$rowDate])) {
                    continue;
                }
                $idx = (int)$dateIndex[$rowDate];
                $products[$rowProductId]['series'][$idx] = $qty;
                $products[$rowProductId]['total'] += $qty;
            }

            return [
                'title' => $this->tr('nav.daily_orders', 'Daily Orders'),
                'model_label' => $this->tr('common.model', 'Model'),
                'filter_label' => $this->tr('operator.dashboard.parts', 'Parts'),
                'all_label' => $this->tr('common.all', 'All'),
                'date_from_label' => $this->tr('common.date_range_from', 'From'),
                'date_to_label' => $this->tr('common.date_range_to', 'To'),
                'date_label' => $this->tr('common.date', 'Date'),
                'qty_label' => $this->tr('common.qty', 'Qty'),
                'empty_label' => $this->tr('nav.topbar_search_no_matches', 'No results found'),
                'dates' => array_values($dates),
                'models' => array_values($models),
                'products' => array_values($products),
            ];
        } catch (\Throwable $e) {
            error_log('OperatorSurfaceComposer::buildDailyOrderChartData: ' . $e->getMessage());
            return $empty;
        }
    }

    /**
     * @param array<int, array<string,mixed>> $tiles
     * @return array<int, array<string,mixed>>
     */
    private function buildOverviewCards(array $tiles): array
    {
        $assignedApps = array_values((array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? []));
        $dashboardType = trim((string)($this->context['dashboard_type'] ?? 'operator'));
        $authorityRole = trim((string)($this->context['authority_role'] ?? 'app_user'));
        $appRoles = (array)($this->context['app_roles'] ?? []);
        // Derive display operational_role: prefer app_roles[default_app], then dashboard_type.
        $defaultApp = trim((string)($this->context['default_app'] ?? ''));
        $operationalRole = $appRoles[$defaultApp] ?? $dashboardType;

        $ordersTile = null;
        $primaryTile = null;
        $values = [];

        foreach ($tiles as $tile) {
            $key = trim((string)($tile['key'] ?? ''));
            if ($key !== '') {
                $values[$key] = (string)($tile['value'] ?? '--');
            }
            if ($ordersTile === null && $key === 'orders') {
                $ordersTile = $tile;
            }
            if ($primaryTile === null && !empty($tile['primary'])) {
                $primaryTile = $tile;
            }
        }

        $contextRows = [
            ['label' => $this->tr('operator.overview.context.operational_role', 'Operational Role'), 'value' => $operationalRole !== '' ? $operationalRole : '-'],
            ['label' => $this->tr('operator.overview.context.authority_role', 'Authority Role'), 'value' => $authorityRole !== '' ? $authorityRole : '-'],
            ['label' => $this->tr('operator.overview.context.assigned_apps', 'Assigned Apps'), 'value' => (string)count($assignedApps)],
            ['label' => $this->tr('operator.overview.context.updated_at', 'Updated'), 'value' => date('Y-m-d H:i')],
        ];

        $trendRows = [];
        foreach ((array)($ordersTile['trends'] ?? []) as $trend) {
            $trendRows[] = [
                'label' => (string)($trend['period'] ?? ''),
                'value' => (string)($trend['delta_label'] ?? '0'),
            ];
        }

        $chartBars = [];
        foreach ((array)($ordersTile['bars'] ?? []) as $bar) {
            $chartBars[] = [
                'period' => (string)($bar['period'] ?? ''),
                'value' => (int)($bar['value'] ?? 0),
                'value_label' => (string)($bar['value_label'] ?? '0'),
                'percent' => (float)($bar['percent'] ?? 0.0),
            ];
        }

        $signalRows = [];
        $primaryLabel = trim((string)($primaryTile['label'] ?? ''));
        foreach ((array)($primaryTile['items'] ?? []) as $item) {
            $raw = trim((string)$item);
            if ($raw === '') {
                continue;
            }
            $signalRows[] = [
                'label' => $primaryLabel !== '' ? $primaryLabel : $this->tr('operator.overview.priority.signal', 'Signal'),
                'value' => $raw,
            ];
            if (count($signalRows) >= 4) {
                break;
            }
        }
        if ($signalRows === []) {
            $signalRows = [
                ['label' => $this->tr('operator.dashboard.critical_parts', 'Critical Parts'), 'value' => (string)($values['parts'] ?? '--')],
                ['label' => $this->tr('operator.dashboard.over_stock', 'Overstock'), 'value' => (string)($values['overstock'] ?? '--')],
                ['label' => $this->tr('operator.dashboard.furyo_waste', 'Waste / Defect'), 'value' => (string)($values['plans'] ?? '--')],
                ['label' => $this->tr('operator.dashboard.dispatch', 'Dispatch'), 'value' => (string)($values['dispatch'] ?? '--')],
            ];
        }

        return [
            [
                'title' => $this->tr('operator.overview.trend.title', 'Orders Trend Pulse'),
                'tone' => 'accent',
                'rows' => $trendRows,
                'kind' => 'bar_chart',
                'metric_label' => $this->tr('operator.dashboard.parts', 'Parts'),
                'bars' => $chartBars,
            ],
            ['title' => $this->tr('operator.overview.priority.title', 'Priority Signals'), 'tone' => 'warn', 'rows' => $signalRows],
        ];
    }

    /**
     * Compose footer with layer switching links
     */
    public function buildFooter(): array
    {
        $normalizedUser = rawurlencode(trim((string)$this->username));
        return [
            'switch_admin_url' => '/admin/' . $normalizedUser,
            'current_layer' => 'operator',
        ];
    }

    // ── Operator Header ───────────────────────────────────────────────────────
    // Edit this method to change the operator topbar and avatar/account panel.
    // Called once from render(). Uses: $data (page data), $i18n (translations),
    // $operatorLanguageOptions, $operatorLanguage, $operatorCurrency, $operatorThemeChoices.
    private function buildHeaderMarkup(
        array $data,
        array $i18n,
        array $operatorLanguageOptions,
        string $operatorLanguage,
        string $operatorCurrency,
        array $operatorThemeChoices
    ): string {
        return OperatorHeaderComposer::render(
            $data,
            $i18n,
            $operatorLanguageOptions,
            $operatorLanguage,
            $operatorCurrency,
            $operatorThemeChoices
        );
    }

    // ── Operator Sidebar ──────────────────────────────────────────────────────
    // Edit this method to change the left contextual navigation sidebar.
    // Called once from render(). Uses: $data['contextual_sidebar'] which contains
    // app_label, sections[], and each section has items[]{route, icon, label, badge}.
    private function buildSidebarMarkup(array $data): string
    {
        return OperatorSidebarComposer::render($data);
    }

    /**
     * Render complete operator layer HTML page with Gmail-style sidebar
     */
    public function render(): string
    {
        $navigation = $this->buildNavigation();
        $content = $this->buildContent();
        $footer = $this->buildFooter();
        $authorityRole = strtolower(trim((string)($this->context['authority_role'] ?? 'app_user')));
        $canSwitchToAdmin = $authorityRole === 'platform_admin';
        $hasManufacturing = (bool)($content['manufacturing_available'] ?? false);

        // When on manufacturing-focused pages, force sidebar context to Manufacturing
        // so the left nav always matches what the user is doing.
        $focusApp = (string)(($this->context['current_query'] ?? [])['focus'] ?? '');
        $renderQuery = (array)($this->context['current_query'] ?? []);
        $manufacturingFocuses = ['critical', 'parts', 'parts-detail', 'production', 'demand', 'orders', 'processing', 'preparation', 'dispatch', 'dispatch-detail', 'fulfillment', 'coverage', 'qc', 'dispatch-adapter', 'machines', 'assembly', 'materials', 'handoff'];
        if (!$hasManufacturing && in_array($focusApp, $manufacturingFocuses, true)) {
            unset($renderQuery['focus']);
            $renderQuery['notice'] = 'manufacturing_unavailable';
            $focusApp = '';
        }

        $sidebarAppOverride = in_array($focusApp, ['production', 'demand', 'processing', 'preparation', 'dispatch', 'dispatch-detail', 'qc', 'dispatch-adapter', 'machines', 'assembly', 'materials'], true) ? 'manufacturing' : null;

        // Format sidebar items with username
        $sidebarItems = $sidebarAppOverride !== null
            ? $this->sidebarService->getContextualSidebar($sidebarAppOverride)
            : $this->contextualSidebar;

        if (in_array($focusApp, ['production', 'processing'], true)) {
            $sidebarItems = $this->sidebarService->getContextualSidebar('my-work');
        }

        $formatted_contextual = $this->sidebarService->formatSidebarItems(
            $sidebarItems,
            $this->username
        );
        $currentApp = $sidebarAppOverride ?? $this->sidebarService->getCurrentAppSelection();

        // Determine which sidebar route should be highlighted as active.
        // Sub-pages (machines, qc, etc.) light up their parent sidebar item.
        $fulfillmentTab = trim((string)($renderQuery['tab'] ?? ''));
        $dispatchSidebarRoute = '/u/' . rawurlencode($this->username) . '/fulfillment?tab=dispatch';
        $preparationSidebarRoute = '/u/' . rawurlencode($this->username) . '/fulfillment?tab=prepare';
        $sidebarActiveRouteMap = [
            'dashboard'        => '/u/' . rawurlencode($this->username) . '/dashboard',
            'work-entry'       => '/u/' . rawurlencode($this->username) . '/work-entry',
            'data-exchange'    => '/u/' . rawurlencode($this->username) . '/data-exchange',
            'critical'         => '/u/' . rawurlencode($this->username) . '/critical',
            'recent'           => '/u/' . rawurlencode($this->username) . '/recent',
            'production'       => '/u/' . rawurlencode($this->username) . '/production',
            'demand'           => '/u/' . rawurlencode($this->username) . '/production',
            'machines'         => '/u/' . rawurlencode($this->username) . '/production',
            'coverage'         => '/u/' . rawurlencode($this->username) . '/production',
            'parts'            => '/u/' . rawurlencode($this->username) . '/production',
            'parts-detail'     => '/u/' . rawurlencode($this->username) . '/production',
            'processing'       => '/u/' . rawurlencode($this->username) . '/processing',
            'assembly'         => '/u/' . rawurlencode($this->username) . '/processing',
            'qc'               => '/u/' . rawurlencode($this->username) . '/processing',
            'fulfillment'      => $fulfillmentTab === 'dispatch' ? $dispatchSidebarRoute : $preparationSidebarRoute,
            'preparation'      => $preparationSidebarRoute,
            'dispatch'         => $dispatchSidebarRoute,
            'dispatch-detail'  => $dispatchSidebarRoute,
            'dispatch-adapter' => '/u/' . rawurlencode($this->username) . '/fulfillment',
            'materials'        => '/u/' . rawurlencode($this->username) . '/materials',
            'sbaio'            => '/u/' . rawurlencode($this->username) . '/sbaio',
            'account'          => '/u/' . rawurlencode($this->username) . '/account',
            'notifications'    => '/u/' . rawurlencode($this->username) . '/notifications',
            'messages'         => '/u/' . rawurlencode($this->username) . '/messages',
            'handoff'          => '/u/' . rawurlencode($this->username) . '/handoff',
            'overview'         => '/u/' . rawurlencode($this->username) . '/dashboard',
        ];
        $sidebarActiveRoute = $sidebarActiveRouteMap[$focusApp] ?? '';

        // Load refresh_rate preference — 0 means disabled.
        $refreshSeconds = 0;
        $__uid = 0;
        try {
            $__authUser = \App\Core\Auth::user();
            $__uid = (int)(is_array($__authUser) ? ($__authUser['id'] ?? 0) : ($__authUser->id ?? 0));
            if ($__uid > 0) {
                $refreshSeconds = OperatorPreferencesService::refreshRateSeconds($__uid);
            }
        } catch (\Throwable) {
            $refreshSeconds = 0;
        }
        // Suppress auto-refresh on pages with forms the user actively edits.
        $noRefreshFocuses = ['work-entry', 'data-exchange', 'preferences', 'account'];
        if (in_array($focusApp, $noRefreshFocuses, true)) {
            $refreshSeconds = 0;
        }

        $realtimeEnabled = false;
        if ($__uid > 0) {
            $realtimeEnabled = OperatorPreferencesService::get($__uid, 'realtime_enabled') === '1';
        }

        $realtimeEligibleFocuses = [
            'dashboard', 'production', 'processing', 'preparation', 'dispatch', 'dispatch-adapter',
            'coverage', 'qc', 'machines', 'assembly', 'materials', 'handoff',
        ];
        $realtimeViewMap = [
            'dashboard' => 'dashboard',
            'production' => 'production',
            'processing' => 'production',
            'preparation' => 'dispatch',
            'dispatch' => 'dispatch',
            'dispatch-adapter' => 'dispatch',
            'coverage' => 'coverage',
            'qc' => 'qc',
            'machines' => 'machines',
            'assembly' => 'assembly',
            'materials' => 'materials',
            'handoff' => 'dashboard',
        ];
        $realtimeView = $realtimeViewMap[$focusApp] ?? '';
        $realtimeEnabled = $realtimeEnabled
            && in_array($focusApp, $realtimeEligibleFocuses, true)
            && $realtimeView !== '';

        $sessionThemeOverride = '';
        if ($__uid > 0) {
            $themeOverrides = $_SESSION['operator_theme_preference_overrides'] ?? [];
            if (is_array($themeOverrides)) {
                $sessionThemeOverride = (string)($themeOverrides[(string)$__uid] ?? '');
            }
        }

        $effectiveThemePreference = ThemePreferenceService::normalizePreference((string)($content['preferences_focus']['theme'] ?? ''));
        if ($sessionThemeOverride !== '') {
            $effectiveThemePreference = ThemePreferenceService::normalizePreference($sessionThemeOverride, $effectiveThemePreference);
        }

        return $this->renderHTML(array_merge(
            [
                'username' => $this->username,
                'authenticated_user_id' => $__uid,
                'refresh_seconds' => $refreshSeconds,
                'home_url' => '/u/' . urlencode($this->username) . '/dashboard',
                'my_account_url' => '/u/' . urlencode($this->username) . '/account',
                'notifications_url' => '/u/' . urlencode($this->username) . '/notifications',
                'messages_url' => '/u/' . urlencode($this->username) . '/messages',
                'user_email' => (string)($this->context['user_email'] ?? 'operator'),
                'company_name' => trim((string)($this->context['company_name'] ?? 'IPM Local')),
                'company_logo' => trim((string)($this->context['company_logo'] ?? '')),
                'company_logo_icon' => trim((string)($this->context['company_logo_icon'] ?? '')),
                'company_logo_svg_inline' => trim((string)($this->context['company_logo_svg_inline'] ?? '')),
                'company_logo_svg_theme' => trim((string)($this->context['company_logo_svg_theme'] ?? '')),
                'company_fallback_text' => trim((string)($this->context['company_fallback_text'] ?? 'Susankhya OS')),
                'company_fallback_text_compact' => trim((string)($this->context['company_fallback_text_compact'] ?? 'S')),
                'branch_name' => trim((string)($this->context['branch_name'] ?? ($this->context['branch'] ?? ''))),
                'notifications_count' => (int)($this->context['notifications_count'] ?? (int)(($content['notifications_focus']['unread'] ?? $content['notifications_focus']['total_all'] ?? 0))),
                'messages_count' => (int)($this->context['messages_count'] ?? (int)(($content['messages_focus']['unread'] ?? 0))),
                'authority_role' => (string)($this->context['authority_role'] ?? 'app_user'),
                'can_switch_to_admin' => $canSwitchToAdmin,
                'dashboard_type' => (string)($this->context['dashboard_type'] ?? 'operator'),
                'assigned_apps' => array_values((array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? [])),
                'active_assigned_apps' => array_values((array)($this->context['active_assigned_apps'] ?? [])),
                'current_query' => $renderQuery,
                'theme_choices' => ThemePreferenceService::themeChoices(),
                'default_theme_preference' => $effectiveThemePreference,
                // Gmail-style sidebar data
                'contextual_sidebar' => $formatted_contextual,
                'current_app' => $currentApp,
                'sidebar_active_route' => $sidebarActiveRoute,
                'page_focus_label' => OperatorFocusLabelComposer::resolveFromQuery(
                    (array)$renderQuery,
                    fn (string $key, string $fallback, array $params = []): string => $this->tr($key, $fallback, $params)
                ),
                'profile_quick_actions' => $this->resolveProfileQuickActions(),
                'workspace_profile_name' => (string)($this->context['workspace_profile']['name'] ?? ''),
                'workspace_profile_key' => (string)($this->context['workspace_profile']['profile_key'] ?? ''),
                'workspace_profile_is_pinned' => !empty($this->context['workspace_profile']['is_pinned']),
                'widget_discovery' => (string)($this->context['workspace_profile']['widget_discovery'] ?? 'auto'),
                'realtime_enabled' => $realtimeEnabled,
                'realtime_view' => $realtimeView,
            ],
            $navigation,
            $content,
            $footer
        ));
    }

    /**
     * @param array<string,mixed> $partRow
     * @return array{show_action:bool,action_label:string,action_url:string,action_helper:string,show_status:bool,status_label:string,status_url:string,status_helper:string}
     */
    private function resolvePartsDetailPlanAction(array $partRow): array
    {
        return OperatorPartPlanComposer::resolveAction(
            $partRow,
            (array)$this->context,
            $this->username,
            fn (string $key, string $fallback, array $params = []): string => $this->tr($key, $fallback, $params)
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    private function renderHTML(array $data): string
    {
        $operatorRouteSearchPrefix = '/u/' . rawurlencode((string)($data['username'] ?? $this->username));
        $operatorSearchUsername = trim((string)($data['username'] ?? $this->username));
        $operatorRouteSearchIndex = OperatorSearchComposer::buildRouteIndex(
            $data,
            $operatorSearchUsername,
            $this->tr('operator.surface.workspace', 'Workspace'),
            $this->tr('common.my_account', 'My Account')
        );
        $operatorEntitySearchIndex = OperatorSearchComposer::buildEntityIndex(
            $data,
            $operatorRouteSearchIndex,
            $operatorSearchUsername,
            $this->tr('operator.surface.part_fallback_label', 'Part #{id}')
        );
        $operatorSearchScope = AuthorizedSearchIndexService::publish(
            ['id' => (int)($data['authenticated_user_id'] ?? 0)],
            'operator',
            array_merge($operatorRouteSearchIndex, $operatorEntitySearchIndex),
            ['path_prefix' => $operatorRouteSearchPrefix]
        );
        ob_start();
        $operatorLanguage = function_exists('current_lang') ? (string)current_lang() : 'en';
        $operatorCurrency = function_exists('current_currency') ? (string)current_currency() : 'usd';
        $operatorLanguageOptions = function_exists('supported_language_labels')
            ? (array)supported_language_labels()
            : ['en' => 'English'];
        $operatorThemeChoices = (array)($data['theme_choices'] ?? ThemePreferenceService::themeChoices());
        $operatorDefaultThemePreference = ThemePreferenceService::normalizePreference((string)($data['default_theme_preference'] ?? ThemePreferenceService::defaultPreference()));
        $operatorThemeMode = ThemePreferenceService::modeFromPreference($operatorDefaultThemePreference);
        $operatorColorStyle = ThemePreferenceService::colorStyleFromPreference($operatorDefaultThemePreference);
        $operatorEffectiveTheme = $operatorThemeMode === 'light' ? 'light' : 'dark';
        $tr = function (string $key, string $fallback, array $params = []) {
            if (function_exists('t')) {
                $translated = (string)t($key, $params);
                if ($translated !== '' && $translated !== $key) {
                    return $translated;
                }
            }
            if ($params === []) {
                return $fallback;
            }
            $replace = [];
            foreach ($params as $paramKey => $paramValue) {
                $replace['{' . $paramKey . '}'] = (string)$paramValue;
            }
            return strtr($fallback, $replace);
        };
        $workspaceTitle = $tr('operator.surface.workspace_title', '{username}\'s Workspace', ['username' => (string)($data['username'] ?? '')]);
        $pageFocusLabel = (string)($data['page_focus_label'] ?? '');
        if ($pageFocusLabel !== '' && strtolower($pageFocusLabel) !== 'dashboard') {
            $workspaceTitle = $pageFocusLabel . ' | ' . $workspaceTitle;
        }
        $i18n = [
            'menu' => $tr('operator.surface.menu', 'Menu'),
            'company_home' => $tr('operator.surface.company_home', 'Company Home'),
            'search' => $tr('common.search', 'Search'),
            'scan' => $tr('operator.surface.scan', 'Scan'),
            'profile' => $tr('operator.surface.profile', 'Profile'),
            'workspace' => $tr('operator.surface.workspace', 'Workspace'),
            'company_name' => $tr('operator.surface.company_name', 'Company Name'),
            'branch' => $tr('operator.surface.branch', 'Branch'),
            'username' => $tr('operator.surface.username', 'Username'),
            'email' => $tr('operator.surface.email', 'Email'),
            'my_account' => $tr('common.my_account', 'My Account'),
            'notifications' => $tr('operator.surface.notifications', 'Notifications'),
            'message' => $tr('common.message', 'Message'),
            'language' => $tr('common.language', 'Language'),
            'currency' => $tr('common.currency', 'Currency'),
            'theme' => $tr('common.theme', 'Theme'),
            'sign_out' => $tr('common.sign_out', 'Sign out'),
            'sign_out_confirm' => $tr('operator.surface.sign_out_confirm', 'Sign out now?'),
            'operator_layer' => $tr('operator.surface.operator_layer', 'Operator Layer'),
            'shift_ready' => $tr('operator.surface.shift_ready', 'Shift Ready'),
            'shift_subtitle' => $tr('operator.surface.shift_subtitle', 'Your workspace is configured. Use modules and quick actions to jump into queue work.'),
            'quick_actions' => $tr('operator.surface.quick_actions', 'Quick Actions'),
            'actions' => $tr('common.actions', 'Actions'),
            'operator_workspace' => $tr('operator.surface.operator_workspace', 'Operator Workspace'),
            'switch_to_admin' => $tr('operator.surface.switch_to_admin', 'Switch to Admin'),
            'switch_to_me' => $tr('operator.surface.switch_to_me', 'Switch to Admin'),
            'scan_prompt_manual' => $tr('operator.surface.scan_prompt_manual', 'Enter scanned value'),
            'scan_panel_title' => $tr('operator.surface.scan_panel_title', 'Scan code'),
            'scan_starting_camera' => $tr('operator.surface.scan_starting_camera', 'Starting camera...'),
            'scan_enter_value' => $tr('operator.surface.scan_enter_value', 'Enter value'),
            'cancel' => $tr('common.cancel', 'Cancel'),
            'scan_scanning' => $tr('operator.surface.scan_scanning', 'Scanning...'),
            'scan_unavailable' => $tr('operator.surface.scan_unavailable', 'Scanner unavailable'),
            'search_no_route' => $tr('operator.surface.search_no_route', 'No matching /u route found'),
            'search_no_match' => $tr('operator.surface.search_no_match', 'No matching result found'),
            'search_result_route' => $tr('operator.surface.search_result_route', 'Route'),
            'search_result_content' => $tr('operator.surface.search_result_content', 'Content'),
            'search_result_entity' => $tr('operator.surface.search_result_entity', 'Entity'),
            'scan_function_unavailable' => $tr('operator.surface.scan_function_unavailable', 'Scan function is unavailable on this surface'),
            'action_placeholder' => $tr('operator.surface.action_placeholder', 'Action not wired yet: {label}'),
            'offline_banner' => $tr('operator.surface.offline_banner', 'You\'re offline — showing cached data. Changes will not be saved.'),
        ];
        ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($operatorLanguage); ?>" data-theme="<?php echo htmlspecialchars($operatorEffectiveTheme); ?>" data-theme-mode="<?php echo htmlspecialchars($operatorThemeMode); ?>" data-color-style="<?php echo htmlspecialchars($operatorColorStyle); ?>" data-theme-preference="<?php echo htmlspecialchars($operatorDefaultThemePreference); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars((string)\App\Core\Auth::csrfToken()); ?>">
    <?php $__refreshSeconds = (int)($data['refresh_seconds'] ?? 0); ?>
    <?php if ($__refreshSeconds > 0): ?>
    <meta http-equiv="refresh" content="<?php echo $__refreshSeconds; ?>">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($workspaceTitle); ?></title>
    <script>
    (function () {
      var storageKey = 'erp-theme-preference';
      var fallbackPreference = <?php echo json_encode($operatorDefaultThemePreference, JSON_UNESCAPED_SLASHES); ?>;
      var allowedPreferences = <?php echo json_encode(array_values(array_keys($operatorThemeChoices)), JSON_UNESCAPED_SLASHES); ?>;

      function normalizeThemePreference(value) {
        var raw = (value || '').toString().trim().toLowerCase();
        var fallbackParts = (fallbackPreference || 'system-liquid-glass').split('-');
        var fallbackStyle = fallbackParts.slice(1).join('-') || 'liquid-glass';
        if (allowedPreferences.indexOf(raw) !== -1) return raw;
        if (raw === 'light') return 'light-' + fallbackStyle;
        if (raw === 'dark') return 'dark-' + fallbackStyle;
        if (raw === 'system') return 'system-' + fallbackStyle;
        return fallbackPreference || 'system-liquid-glass';
      }

      function resolveSystemTheme() {
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)
          ? 'dark' : 'light';
      }

      function applyThemePreference(value) {
        var preference = normalizeThemePreference(value);
        var parts = preference.split('-');
        var mode = parts[0] === 'light' || parts[0] === 'dark' ? parts[0] : 'system';
        var colorStyle = parts.slice(1).join('-') || 'liquid-glass';
        var effectiveTheme = mode === 'system' ? resolveSystemTheme() : mode;
        document.documentElement.setAttribute('data-theme-mode', mode);
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        document.documentElement.setAttribute('data-color-style', colorStyle);
        document.documentElement.setAttribute('data-theme-preference', preference);
        document.documentElement.style.colorScheme = effectiveTheme;
      }

      try {
        applyThemePreference(window.localStorage.getItem(storageKey) || fallbackPreference);
      } catch (_e) {
        applyThemePreference(fallbackPreference);
      }
    })();
    </script>
        <link rel="stylesheet" href="/assets/branding/ipm-logo.css">
    <!-- All CSS managed by StyleRegistryService -->
    <?php
        if (class_exists('\Apps\Shell\Services\StyleRegistryService')) {
            $allStyles = array_merge(
                \Apps\Shell\Services\StyleRegistryService::globals(),
                \Apps\Shell\Services\StyleRegistryService::forSurface('operator', $data ?? [])
            );
            foreach ($allStyles as $style) {
                $styleUrl = $style['url'] ?? '';
                $styleVersion = $style['version'] ?? '1';
                if ($styleUrl !== '') {
                    echo '    <link rel="stylesheet" href="' . htmlspecialchars($styleUrl) . '?v=' . htmlspecialchars($styleVersion) . '">' . "\n";
                }
            }
        }
    ?>
    <?php $__focusApp = trim((string)(($data['current_query'] ?? [])['focus'] ?? '')); ?>
    <?php if ($__focusApp === 'coverage'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    <?php if ($__focusApp === 'qc' || $__focusApp === 'dispatch-adapter' || $__focusApp === 'machines' || $__focusApp === 'assembly' || $__focusApp === 'materials'): ?>
    <!-- No extra scripts required for Phase 4 adapter views -->
    <?php endif; unset($__focusApp); ?>
    <?php if (!empty($data['realtime_enabled'])): ?>
    <link rel="stylesheet" href="/assets/operator-realtime.css?v=1">
    <script src="/assets/operator-realtime.js?v=1" defer></script>
    <?php endif; ?>
    <!-- Service Worker registration (Phase 9 — offline support for /u/ pages) -->
    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/operator-sw.js', { scope: '/u/' })
            .catch(function () { /* non-fatal */ });
    }
    </script>
</head>
<body class="layout-shell operator-shell-page">
    <!-- Offline banner (Phase 9) — shown by JS when network is unavailable -->
    <div class="u-style-5f57447038" id="op-offline-banner" role="alert" aria-live="assertive">
        <?php echo htmlspecialchars($i18n['offline_banner'] ?? 'You\'re offline — showing cached data. Changes will not be saved.'); ?>
    </div>
    <script>
    (function () {
        var banner = document.getElementById('op-offline-banner');
        function update() { if (banner) banner.style.display = navigator.onLine ? 'none' : 'block'; }
        update();
        window.addEventListener('online', update);
        window.addEventListener('offline', update);
    }());
    </script>
    <?php if (!empty($data['realtime_enabled']) && !empty($data['realtime_view'])): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.OperatorRealtimeClient) {
            return;
        }

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';
        if (!csrfToken) {
            return;
        }

        const protocol = window.location.protocol === 'https:';
        const client = new window.OperatorRealtimeClient(
            <?php echo json_encode((string)($data['username'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
            <?php echo json_encode((string)($data['realtime_view'] ?? ''), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
            csrfToken,
            {
                host: window.location.hostname,
                port: 8001,
                useSecure: protocol,
            }
        );

        client.connect();
        window.operatorRealtimeClient = client;
    });
    </script>
    <?php endif; ?>
    <?php if ($__refreshSeconds > 0): ?><div class="op-refresh-badge" aria-hidden="true">&#8635; <?php echo $__refreshSeconds; ?>s</div><?php endif; ?>
    <!-- Header + Hamburger Menu — edit: buildHeaderMarkup() -->
    <?php echo $this->buildHeaderMarkup($data, $i18n, $operatorLanguageOptions, $operatorLanguage, $operatorCurrency, $operatorThemeChoices); ?>

    <!-- Breadcrumbs (via OperatorLayerWrapperComposer) -->
    <?php
    $__opWrapperComposer = new \Apps\Shell\Services\OperatorLayerWrapperComposer([
        'username'      => (string)($data['username'] ?? $this->username),
        'company_name'  => (string)($data['company_name'] ?? ''),
        'company_logo'  => (string)($data['company_logo'] ?? ''),
        'assigned_apps' => (array)($data['assigned_apps'] ?? []),
        'current_focus' => trim((string)(($data['current_query'] ?? [])['focus'] ?? '')),
    ]);
    // Keep instance alive — renderBottomNav() called before </body>
    ?>

    <!-- App Shell (Single Contextual Sidebar + Content) -->
    <div class="app-shell" id="appShell">
        <!-- Contextual Sidebar — edit: buildSidebarMarkup() -->
        <?php echo $this->buildSidebarMarkup($data); ?>

        <!-- Main Content Area -->
        <div class="main-content workspace-surface shell-inactive-layer" id="operatorMainContent">
            <?php echo OperatorDashboardComposer::renderFocusStack(
                $data,
                $i18n,
                (array)$this->context,
                fn (array $partRow): array => $this->resolvePartsDetailPlanAction($partRow),
                fn (array $panel): string => $this->renderAccountPanelMarkup($panel)
            ); ?>
    </div>
    </div><!-- /.app-shell -->

    <?php
    $layoutFooterShowLayerLinks = false;
    include APP_ROOT . '/public/views/partials/layout-footer.php';
    ?>



    <?php echo \Apps\Shell\Services\ShellOverlayFramework::renderInfrastructureScript(); ?>
    <?php echo \Apps\Shell\Overlay\Compatibility\Adapters\OperatorSurface\OperatorSearchResultsAdapter::renderScript(); ?>
    <?php echo \Apps\Shell\Overlay\Compatibility\Adapters\OperatorSurface\OperatorHamburgerDrawerAdapter::renderScript(); ?>
    <?php echo \Apps\Shell\Overlay\Compatibility\Adapters\OperatorSurface\OperatorAvatarPanelAdapter::renderScript(); ?>
    <?php echo \Apps\Shell\Overlay\Compatibility\Adapters\OperatorSurface\OperatorMobileActionSheetAdapter::renderScript(); ?>
    <?php echo \Apps\Shell\Overlay\Compatibility\Adapters\Shared\CameraScanOverlayAdapter::renderScript(); ?>
    <?php echo OperatorInteractionScriptComposer::render(
        $data,
        $i18n,
        $operatorSearchScope,
        $operatorDefaultThemePreference,
        $operatorThemeChoices,
        (string)($data['username'] ?? $this->username)
    ); ?>
    <!-- Live KPI polling (Phase 8) — updates dashboard tile values every 30 s without a full reload -->
    <script>
    (function () {
        'use strict';
        var KPI_FEED_URL = '/u/kpi-feed';
        var POLL_INTERVAL_MS = 30000;
        var KPI_KEY_ATTR = 'data-dashboard-key';
        var VALUE_CLASS = 'dashboard-tile-value';
        var DOT_ID = 'op-kpi-live-dot';
        var LABEL_ID = 'op-kpi-live-label';

        function updateDot(state) {
            var dot = document.getElementById(DOT_ID);
            var lbl = document.getElementById(LABEL_ID);
            if (!dot || !lbl) return;
            if (state === 'live') {
                dot.style.background = '#22c55e';
                dot.title = 'Live';
            } else if (state === 'error') {
                dot.style.background = '#ef4444';
                dot.title = 'Update failed';
            } else {
                dot.style.background = '#94a3b8';
                dot.title = 'Connecting…';
            }
        }

        function applyKpis(kpis) {
            if (!Array.isArray(kpis)) return;
            kpis.forEach(function (item) {
                var key = item.key;
                var val = String(item.value);
                var tile = document.querySelector('[' + KPI_KEY_ATTR + '="' + key + '"]');
                if (!tile) return;
                var el = tile.querySelector('.' + VALUE_CLASS);
                if (el && el.textContent !== val) {
                    el.textContent = val;
                    el.classList.add('op-kpi-flash');
                    setTimeout(function () { el.classList.remove('op-kpi-flash'); }, 600);
                }
            });
        }

        function poll() {
            fetch(KPI_FEED_URL, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    if (!r.ok) throw new Error('status ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    applyKpis(data.kpis || []);
                    updateDot('live');
                })
                .catch(function () {
                    updateDot('error');
                });
        }

        // Inject live indicator dot into the dashboard title area (if present)
        document.addEventListener('DOMContentLoaded', function () {
            var titleEl = document.querySelector('.dashboard-top-title');
            if (titleEl) {
                var dot = document.createElement('span');
                dot.id = DOT_ID;
                dot.style.cssText = 'display:inline-block;width:8px;height:8px;border-radius:50%;background: var(--style-subtle-bg);margin-left:8px;vertical-align:middle;transition:background .4s;cursor:default;';
                dot.title = 'Connecting…';
                var lbl = document.createElement('span');
                lbl.id = LABEL_ID;
                titleEl.appendChild(dot);
                titleEl.appendChild(lbl);
            }
            poll();
            setInterval(poll, POLL_INTERVAL_MS);
        });
    }());
    </script>
    <!-- Shared row-click navigation -->
    <?php echo $__opWrapperComposer->renderRowClickScript(); ?>
    <!-- Mobile bottom navigation -->
    <?php echo $__opWrapperComposer->renderBottomNav(); unset($__opWrapperComposer); ?>
    <?php echo OperatorAvatarMenuComposer::render($data, $i18n, $operatorLanguageOptions, $operatorLanguage, $operatorCurrency, $operatorThemeChoices); ?>
</body>
</html>
        <?php
        return ob_get_clean() ?: '';
    }
}
