<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\DB;

require_once __DIR__ . '/OperatorSurfaceContributionRegistry.php';

/**
 * OperatorLayerSidebarService
 * 
 * Manages Gmail-style sidebar composition for operator layer.
 * 
 * Architecture:
 * - Primary app rail: Major zones (My Work, Manufacturing, SBAIO, etc.)
 * - Contextual sidebar: Views for selected app zone
 * - Smart filtering: By user assignment, role, permissions
 * 
 * Design principle:
 * "Mimic Gmail's sidebar: app rail for major zones, contextual drawer for current app views,
 *  soft rounded pill navigation, assignment-aware filtering, compact collapse, mobile drawer."
 */
final class OperatorLayerSidebarService
{
    private array $context;
    private const OPERATOR_VIEW_ALIASES = [
        'parts-detail' => 'parts',
        'dispatch-detail' => 'dispatch',
        'dispatch-adapter' => 'dispatch',
        'alerts' => 'notifications',
    ];
    private const OPERATOR_VIEW_ALLOW_ALWAYS = ['dashboard', 'account'];
    private const OPERATOR_VIEW_KEYS = [
        'dashboard', 'work-entry', 'data-exchange', 'critical', 'recent', 'tasks',
        'production', 'demand', 'orders', 'parts', 'coverage', 'machines',
        'processing', 'assembly', 'qc',
        'fulfillment', 'preparation', 'dispatch',
        'materials', 'handoff', 'account', 'notifications', 'messages', 'preferences',
        'sbaio',
    ];

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

    public function __construct(array $context = [])
    {
        $this->context = $context;
    }

    /**
     * @return array<int,string>
     */
    public static function normalizeOperatorViews(string $csv): array
    {
        $allowed = array_flip(self::OPERATOR_VIEW_KEYS);
        $out = [];
        foreach (preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [] as $token) {
            $token = trim($token);
            $token = self::OPERATOR_VIEW_ALIASES[$token] ?? $token;
            if ($token !== '' && isset($allowed[$token])) {
                $out[$token] = $token;
            }
        }
        foreach (self::OPERATOR_VIEW_ALLOW_ALWAYS as $required) {
            $out[$required] = $required;
        }
        return array_values($out);
    }

    public static function operatorViewAllowed(string $viewPath, string $csv): bool
    {
        $raw = trim($csv);
        if ($raw === '') {
            return true;
        }
        $view = strtolower(trim($viewPath, '/'));
        $view = explode('/', $view)[0] ?? $view;
        $view = self::OPERATOR_VIEW_ALIASES[$view] ?? $view;
        if ($view === '') {
            $view = 'dashboard';
        }
        $allowed = array_flip(self::normalizeOperatorViews($raw));
        return isset($allowed[$view]);
    }

    /**
     * Get primary app rail items (major zones)
     * Shows only: My Work, Manufacturing, SBAIO, Susankhya OS governance (if admin), Platform Admin (if admin)
     * 
     * @return array<int, array{
     *   id: string,
     *   label: string,
     *   icon: string,
     *   key: string,
     *   badge?: int,
     *   description?: string
     * }>
     */
    public function getPrimaryAppRail(): array
    {
        $rail = [];
        $assigned_apps = (array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? []);
        $authority_role = (string)($this->context['authority_role'] ?? '');
        $dashboard_type = (string)($this->context['dashboard_type'] ?? '');
        // New model: per-app operational roles indexed by app_key.
        $app_roles = (array)($this->context['app_roles'] ?? []);

        // Always show: My Work
        $rail[] = [
            'id' => 'my-work',
            'label' => $this->tr('operator.sidebar.my_work', 'My Work'),
            'icon' => '🏠',
            'key' => 'my_work',
            'description' => $this->tr('operator.sidebar.my_work_description', 'Personal dashboard and assigned tasks'),
            'badge' => 0,
        ];

        // Show Manufacturing if assigned
        if (in_array('manufacturing', $assigned_apps, true)) {
            $rail[] = [
                'id' => 'manufacturing',
                'label' => $this->tr('operator.sidebar.manufacturing', 'Manufacturing'),
                'icon' => '🏭',
                'key' => 'manufacturing',
                'description' => $this->tr('operator.sidebar.manufacturing_description', 'Production, QC, dispatch, material management'),
                'badge' => 0,
            ];
        }

        // Show SBAIO if assigned
        if (in_array('sbaio', $assigned_apps, true)) {
            $rail[] = [
                'id' => 'sbaio',
                'label' => $this->tr('operator.sidebar.sbaio', 'SBAIO'),
                'icon' => '👥',
                'key' => 'sbaio',
                'description' => $this->tr('operator.sidebar.sbaio_description', 'Payroll, timecards, attendance, leave'),
                'badge' => 0,
            ];
        }

        // Show Platform if admin
        if ($authority_role === 'platform_admin') {
            $rail[] = [
                'id' => 'platform-admin',
                'label' => $this->tr('operator.sidebar.platform_admin', 'Platform Admin'),
                'icon' => '⚙️',
                'key' => 'platform_admin',
                'description' => $this->tr('operator.sidebar.platform_admin_description', 'Apps, users, access control, system settings'),
            ];
        }

        return $rail;
    }

    /**
     * Get contextual sidebar for selected app zone
     * Unified main sidebar only (no app/role/focus submenus)
     * 
     * @param string $selected_app = 'my-work' | 'manufacturing' | 'sbaio' | 'platform-admin'
     * @return array<string, mixed> Organized with sections
     */
    public function getContextualSidebar(string $selected_app = 'my-work'): array
    {
        return $this->getMainSidebar();
    }

    /**
     * Main sidebar - built following the canonical 8-step surface resolution pipeline.
     *
     * Step 6: interaction profile — workspace_profile.nav_sections overrides structure entirely.
     * Steps 1-5 (authority class, account type, app assignment, default app, control scope)
     *   gate which sections and items appear.
     */
    private function getMainSidebar(): array
    {
        // Step 6: interaction profile override — if workspace_profile provides nav_sections,
        // use them verbatim (profile author is responsible for assignment-aware content).
        $profileNavSections = ($this->context['workspace_profile'] ?? [])['nav_sections'] ?? null;
        if (is_array($profileNavSections) && $profileNavSections !== []) {
            return [
                'app_id'    => 'my-work',
                'app_label' => $this->tr('operator.surface.workspace', 'Workspace'),
                'sections'  => $profileNavSections,
            ];
        }

        // Step 3: app assignment — resolve which apps are explicitly assigned.
        $assignedApps   = array_map(
            static fn (string $v): string => strtolower(trim($v)),
            (array)($this->context['active_assigned_apps'] ?? $this->context['assigned_apps'] ?? [])
        );
        $hasMfg  = in_array('manufacturing', $assignedApps, true);
        $hasSBAIO = in_array('sbaio', $assignedApps, true);

        // Step 5: control scope — `operator_views` should already contain the
        // consumer-resolved token set provided by the caller. `module_visibility`
        // remains available only as an explicit compatibility fallback for
        // legacy/non-runtime callers that have not been upgraded yet.
        $moduleVisibility = array_map(
            static fn (string $v): string => strtolower(trim($v)),
            (array)($this->context['module_visibility'] ?? [])
        );
        $resolvedOperatorViews = self::normalizeOperatorViews((string)($this->context['operator_views'] ?? ''));
        $hasResolvedOperatorViews = trim((string)($this->context['operator_views'] ?? '')) !== '';
        $allowLegacyModuleVisibilityFallback = !empty($this->context['allow_legacy_sidebar_module_visibility']);
        // Helper: true if the module key is in visibility list OR list is empty (no restriction).
        $canSee = static function (string $moduleKey) use ($moduleVisibility): bool {
            return $moduleVisibility === [] || in_array(strtolower($moduleKey), $moduleVisibility, true);
        };
        $canSeeAny = static function (array $moduleKeys) use ($moduleVisibility): bool {
            if ($moduleVisibility === []) {
                return true;
            }
            foreach ($moduleKeys as $moduleKey) {
                if (in_array(strtolower(trim((string)$moduleKey)), $moduleVisibility, true)) {
                    return true;
                }
            }
            return false;
        };
        $canSeeView = static function (string $viewKey, array $moduleKeys = []) use ($hasResolvedOperatorViews, $resolvedOperatorViews, $allowLegacyModuleVisibilityFallback, $canSeeAny): bool {
            $normalizedViewKey = strtolower(trim($viewKey));
            if ($hasResolvedOperatorViews) {
                return in_array($normalizedViewKey, $resolvedOperatorViews, true);
            }
            if (!$allowLegacyModuleVisibilityFallback) {
                return in_array($normalizedViewKey, self::OPERATOR_VIEW_ALLOW_ALWAYS, true);
            }
            return $moduleKeys === [] || $canSeeAny($moduleKeys);
        };

        // ── Dashboard section (always visible) ─────────────────────────────
        $sections = [
            [
                'title' => $this->tr('operator.sidebar.dashboard', 'Dashboard'),
                'items' => [
                    ['icon' => '📊', 'label' => $this->tr('operator.sidebar.dashboard', 'Dashboard'), 'route' => '/u/{user}/dashboard', 'badge' => null],
                ],
            ],
        ];

        // ── My Tasks section ────────────────────────────────────────────────
        // Work Entry and Recent Activity are always available.
        // Critical Items and Shift Handoff are manufacturing-related — shown only when assigned.
        $myTaskItems = [
            ['icon' => '📝', 'label' => $this->tr('operator.sidebar.work_entry', 'Work Entry'), 'route' => '/u/{user}/work-entry', 'badge' => null],
            ['icon' => '📥', 'label' => $this->tr('operator.sidebar.data_exchange', 'Data Exchange'), 'route' => '/u/{user}/data-exchange', 'badge' => null],
        ];
        if ($hasMfg && $canSeeView('critical')) {
            $myTaskItems[] = ['icon' => '🔥', 'label' => $this->tr('operator.sidebar.critical_items', 'Critical Items'), 'route' => '/u/{user}/critical', 'badge' => $this->countCriticalItems()];
        }
        $myTaskItems[] = ['icon' => '⏱️', 'label' => $this->tr('operator.sidebar.recent_activity', 'Recent Activity'), 'route' => '/u/{user}/recent', 'badge' => null];
        if ($hasMfg && $canSeeView('handoff')) {
            $myTaskItems[] = ['icon' => '🔁', 'label' => $this->tr('operator.sidebar.shift_handoff', 'Shift Handoff'), 'route' => '/u/{user}/handoff', 'badge' => $this->countUnackedHandoffs()];
        }
        $myTaskItems[] = ['icon' => '✅', 'label' => $this->tr('operator.sidebar.tasks', 'My Tasks'), 'route' => '/u/{user}/tasks', 'badge' => $this->countOpenTasks()];
        $sections[] = [
            'title' => $this->tr('operator.sidebar.my_tasks', 'My Tasks'),
            'items' => $myTaskItems,
        ];

        // ── Operations and app-owned sections ───────────────────────────────
        // First load app/module-owned operator sidebar contributions dynamically.
        $contributedSections = OperatorSurfaceContributionRegistry::sidebarSections($this->context);
        foreach ($contributedSections as $section) {
            $sections[] = $section;
        }

        // Backward-compatible fallback for manufacturing Operations while apps migrate.
        if ($hasMfg && $contributedSections === []) {
            $opsItems = [];
            if ($canSeeView('production', ['production', 'demands', 'coverage'])) {
                $opsItems[] = ['icon' => '🏭', 'label' => $this->tr('operator.sidebar.production_control', 'Production'), 'route' => '/u/{user}/production', 'badge' => null];
            }
            if ($canSeeView('demand', ['demands'])) {
                $opsItems[] = ['icon' => '📈', 'label' => $this->tr('operator.sidebar.demand', 'Demand'), 'route' => '/u/{user}/demand', 'badge' => null];
            }
            if ($canSeeView('orders', ['demands'])) {
                $opsItems[] = ['icon' => '📋', 'label' => $this->tr('operator.sidebar.orders', 'Orders'), 'route' => '/u/{user}/orders', 'badge' => null];
            }
            if ($canSeeView('parts', ['materials'])) {
                $opsItems[] = ['icon' => '🧩', 'label' => $this->tr('operator.sidebar.parts', 'Parts'), 'route' => '/u/{user}/parts', 'badge' => null];
            }
            if ($canSeeView('coverage', ['coverage'])) {
                $opsItems[] = ['icon' => '📶', 'label' => $this->tr('operator.sidebar.coverage', 'Coverage'), 'route' => '/u/{user}/coverage', 'badge' => null];
            }
            if ($canSeeView('machines', ['production'])) {
                $opsItems[] = ['icon' => '🛠️', 'label' => $this->tr('operator.sidebar.machines', 'Machines'), 'route' => '/u/{user}/machines', 'badge' => null];
            }
            if ($canSeeView('processing', ['production', 'assembly', 'qc'])) {
                $opsItems[] = ['icon' => '⚙️', 'label' => $this->tr('operator.sidebar.processing_control', 'Processing'), 'route' => '/u/{user}/processing', 'badge' => null];
            }
            if ($canSeeView('assembly', ['assembly'])) {
                $opsItems[] = ['icon' => '🔧', 'label' => $this->tr('operator.sidebar.assembly', 'Assembly'), 'route' => '/u/{user}/assembly', 'badge' => null];
            }
            if ($canSeeView('qc', ['qc'])) {
                $opsItems[] = ['icon' => '🧪', 'label' => $this->tr('operator.sidebar.qc', 'Quality Control'), 'route' => '/u/{user}/qc', 'badge' => null];
            }
            if ($canSeeView('fulfillment', ['dispatch'])) {
                $opsItems[] = ['icon' => '🚚', 'label' => $this->tr('operator.sidebar.dispatch', 'Dispatch'), 'route' => '/u/{user}/fulfillment?tab=dispatch', 'badge' => null];
            }
            if ($canSeeView('preparation', ['dispatch'])) {
                $opsItems[] = ['icon' => '📦', 'label' => $this->tr('operator.sidebar.preparation', 'Preparation'), 'route' => '/u/{user}/fulfillment?tab=prepare', 'badge' => null];
            }
            if ($canSeeView('materials', ['materials'])) {
                $opsItems[] = ['icon' => '📦', 'label' => $this->tr('operator.sidebar.materials', 'Materials'), 'route' => '/u/{user}/materials', 'badge' => null];
            }
            if ($opsItems !== []) {
                $sections[] = [
                    'title' => $this->tr('operator.sidebar.operations', 'Operations'),
                    'items' => $opsItems,
                ];
            }
        }

        // ── Account section (always visible) ────────────────────────────────
        $sections[] = [
            'title' => $this->tr('operator.sidebar.account', 'Account'),
            'items' => [
                ['icon' => '👤', 'label' => $this->tr('operator.sidebar.account',       'Account'),       'route' => '/u/{user}/account',        'badge' => null],
                ['icon' => '🔔', 'label' => $this->tr('operator.sidebar.notifications', 'Notifications'), 'route' => '/u/{user}/notifications', 'badge' => (int)($this->context['notifications_count'] ?? 0)],
                ['icon' => '✉️', 'label' => $this->tr('operator.sidebar.messages',      'Messages'),      'route' => '/u/{user}/messages',      'badge' => (int)($this->context['messages_count'] ?? 0)],
                ['icon' => '⚙️', 'label' => $this->tr('operator.sidebar.preferences',   'Preferences'),   'route' => '/u/{user}/preferences',   'badge' => null],
            ],
        ];

        return [
            'app_id'   => 'my-work',
            'app_label' => $this->tr('operator.surface.workspace', 'Workspace'),
            'sections' => $sections,
        ];
    }

    /**
     * Count critical items (parts with shortage today) for sidebar badge.
     * Returns 0 on any DB error so a failing query never breaks navigation.
     */
    private function countCriticalItems(): int
    {
        try {
            $row = DB::fetchOne(
                "SELECT COUNT(DISTINCT d.product_id) AS cnt
                 FROM daily_orders d
                 WHERE d.order_date = CURDATE()
                   AND LOWER(COALESCE(d.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
                   AND (COALESCE(d.shortage_qty, 0) > 0 OR COALESCE(d.coverage_pct, 100) < 100)"
            );
            return (int)($row['cnt'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Count unacknowledged shift handoff notes for today for the current user.
     * Returns 0 on any DB error so a failing query never breaks navigation.
     */
    private function countUnackedHandoffs(): int
    {
        try {
            $user   = \App\Core\Auth::user();
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0) {
                return 0;
            }
            return \Apps\Shell\Services\ShiftHandoffService::countUnacked($userId);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Count open (open + in_progress) tasks for the current user.
     * Returns 0 on any DB error so a failing query never breaks navigation.
     */
    private function countOpenTasks(): int
    {
        try {
            $user   = \App\Core\Auth::user();
            $userId = (int)($user['id'] ?? 0);
            if ($userId <= 0) {
                return 0;
            }
            return \Apps\Shell\Services\OperatorTaskService::countOpen($userId);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Get current app selection (from context or default)
     */
    public function getCurrentAppSelection(): string
    {
        return (string)($this->context['current_app'] ?? 'my-work');
    }

    /**
     * Format sidebar items with user context (e.g., replace {user} placeholder)
     */
    public function formatSidebarItems(array $sidebar, string $username): array
    {
        $formatted = $sidebar;
        $encodedUser = rawurlencode(trim($username));
        foreach ($formatted['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                $route = str_replace('{user}', $encodedUser, (string)($item['route'] ?? ''));
                $item['route'] = $this->normalizeContributedSidebarRoute($route, $encodedUser);
            }
        }
        return $formatted;
    }

    private function normalizeContributedSidebarRoute(string $route, string $encodedUser): string
    {
        $fallback = '/u/' . $encodedUser . '/dashboard';
        $normalized = OperatorLayerWidgetService::normalizeOperatorWidgetUrl($route, $this->context);
        $trimmed = trim($normalized);
        if ($trimmed === '' || !str_starts_with($trimmed, '/')) {
            return $fallback;
        }

        $path = trim((string)parse_url($trimmed, PHP_URL_PATH));
        if ($path === '' || !str_starts_with($path, '/u/')) {
            return $fallback;
        }

        $segments = explode('/', trim($path, '/'));
        if (count($segments) < 3 || strtolower((string)($segments[0] ?? '')) !== 'u') {
            return $fallback;
        }

        $segments[1] = $encodedUser;
        $rebuilt = '/' . implode('/', $segments);
        $query = trim((string)parse_url($trimmed, PHP_URL_QUERY));
        if ($query !== '') {
            $rebuilt .= '?' . $query;
        }

        return $rebuilt;
    }
}
