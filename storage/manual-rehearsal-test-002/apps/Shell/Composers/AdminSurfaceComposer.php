<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

use App\Core\View;
use App\Services\TileActionResolverService;
use Apps\Platform\Services\AdminDashboardPanelBlockService;
use Apps\Platform\Services\AdminUpgradeWorkbenchService;
use Apps\Platform\Services\UserAssignmentContext;
use Apps\Shell\Services\AdminDashboardLauncherComposer;
use Apps\Shell\Services\ShellRuntimeMenuComposer;
use Plugins\Base\Services\HostSurfaceRegistryService;
use Plugins\Base\Services\MyWorkService;

/**
 * AdminSurfaceComposer
 *
 * Composes the admin layer (/me) home page for platform_admin users.
 *
 * Analogous to OperatorSurfaceComposer for the /u operator layer.
 *
 * Responsibilities:
 * - Build all data payloads for the /me surface
 * - Orchestrate plugin-contributed regions (HostSurfaceRegistryService)
 * - Merge core quick links and admin dashboard panels
 * - Delegate rendering to the Base::ops/me.php view
 *
 * Auth + authorization are enforced upstream by AdminLayerService.
 * This class only runs after the caller has verified platform_admin access.
 *
 * Phase 1 Note:
 * This composer currently delegates rendering to the existing me.php view.
 * Phase 2 will extract me.php blocks into apps/Shell/Views/admin/ view files.
 */
final class AdminSurfaceComposer
{
    private View $view;

    /** @var array<string,mixed> */
    private array $user;

    /** @var array<string,mixed> */
    private array $ctx;

    /** @var array<string,mixed> */
    private array $query;

    /**
     * @param array<string,mixed> $user  Authenticated user record from Auth::user()
     * @param array<string,mixed> $ctx   Resolved user context from UserAssignmentContext
     * @param array<string,mixed> $query Query parameters from $_GET
     */
    public function __construct(View $view, array $user, array $ctx, array $query = [])
    {
        $this->view  = $view;
        $this->user  = $user;
        $this->ctx   = $ctx;
        $this->query = $query;
    }

    /**
     * Render the full /me admin page.
     * Builds all data, resolves plugin regions, then renders via me.php.
     */
    public function renderHTML(): void
    {
        $data = $this->safeWorkspaceData();
        $hostRegions = $this->safeHostRegions($data);
        $hostRegions = $this->mergeCoreWorkspaceRegions($hostRegions);
        $hostRegions['summary_cards'] = TileActionResolverService::annotateMyWorkSummaryCards(
            is_array($hostRegions['summary_cards'] ?? null) ? (array)$hostRegions['summary_cards'] : []
        );

        $assignedApps       = array_values((array)($this->ctx['assigned_apps'] ?? []));
        $activeAssignedApps = array_values((array)($this->ctx['active_assigned_apps'] ?? []));
        $launcherApps       = $this->launcherApps();
        $adminPanels        = $this->safeAdminDashboardPanels();
        $developerToolsVisible = (string)($this->ctx['authority_role'] ?? '') === 'platform_admin'
            && function_exists('should_show_feature')
            && should_show_feature('admin_tools');
        $adminLauncherGroups = $this->safeAdminLauncherGroups($developerToolsVisible);
        $workbenchContext = $this->ctx;
        $workbenchContext['developer_tools_visible'] = $developerToolsVisible;
        $adminUpgradeWorkbench = AdminUpgradeWorkbenchService::build($workbenchContext);
        $rawDominantApp    = count($activeAssignedApps) === 1
            ? (string)$activeAssignedApps[0]
            : (string)($this->ctx['default_app'] ?? '');
        // For app_admin users: if no meaningful default_app is set (empty or 'platform'),
        // resolve to the first non-platform active assigned app.
        $authorityRole    = (string)($this->ctx['authority_role'] ?? '');
        if ($authorityRole === 'app_admin' && ($rawDominantApp === '' || $rawDominantApp === 'platform')) {
            $businessApps = array_values(array_filter($activeAssignedApps, static fn(string $a): bool => $a !== 'platform'));
            $rawDominantApp = $businessApps[0] ?? $rawDominantApp;
        }
        $dominantApp      = $rawDominantApp;
        $isIncomplete       = empty($this->ctx['access_profiles']) && empty($this->ctx['permissions']);
        $experienceMode     = $this->experienceMode($data);

        $this->view->render('shell::admin/home.php', [
            'pageTitle'                        => 'Home',
            'user_name'                        => $data['user_name'] ?? '',
            'user_role'                        => $data['user_role'] ?? '',
            'authority_role'                     => $data['authority_role'] ?? '',
            'dashboard_type'                   => $data['dashboard_type'] ?? 'operator',
            'kpi'                              => (array)($data['kpi'] ?? []),
            'sections'                         => (array)($data['sections'] ?? []),
            'grouped_sections'                 => (array)($data['grouped_sections'] ?? []),
            'all_items'                        => (array)($data['all_items'] ?? []),
            'has_approvals'                    => (bool)($data['has_approvals'] ?? false),
            'has_overdue'                      => (bool)($data['has_overdue'] ?? false),
            'has_blocked'                      => (bool)($data['has_blocked'] ?? false),
            'role_links'                       => (array)($data['role_links'] ?? []),
            'approval_modules'                 => (array)($data['approval_modules'] ?? []),
            'notification_summary'             => (array)($data['notification_summary'] ?? []),
            'recent_notifications'             => (array)($data['recent_notifications'] ?? []),
            'unread_notifications'             => (int)($data['unread_notifications'] ?? 0),
            'cross_functional_access'          => (array)($data['cross_functional_access'] ?? []),
            'primary_work_area'                => (string)($data['primary_work_area'] ?? ''),
            'cross_work_areas'                 => (array)($data['cross_work_areas'] ?? []),
            'home_assigned_apps'               => $activeAssignedApps,
            'home_launcher_apps'               => $launcherApps,
            'home_all_assigned_apps'           => $assignedApps,
            'home_dominant_app'                => $dominantApp,
            'home_suite_role_templates'        => (array)($this->ctx['suite_role_template_labels'] ?? []),
            'home_module_permission_templates' => (array)($this->ctx['module_permission_template_labels'] ?? []),
            'home_access_profiles'             => (array)($this->ctx['access_profiles'] ?? []),
            'home_duty_codes'                  => (array)($this->ctx['duty_codes'] ?? []),
            'home_scope_snapshot'              => (array)($this->ctx['scope'] ?? []),
            'home_experience_mode'             => $experienceMode,
            'home_incomplete_mapping'          => $isIncomplete,
            'home_admin_warning'               => $isIncomplete
                ? 'Access mapping is incomplete. Rendering safe minimal home view.'
                : '',
            'admin_dashboard_panels'           => $adminPanels,
            'admin_launcher_groups'             => $adminLauncherGroups,
            'admin_upgrade_workbench'            => $adminUpgradeWorkbench,
            'host_regions'                     => $hostRegions,
            'me_dashboard_blocks'              => (array)($this->ctx['me_dashboard_blocks'] ?? []),
            'me_plugin_cards'                  => (array)($this->ctx['me_plugin_cards'] ?? []),
            'me_dashboard_blocks_explicit_none' => (bool)($this->ctx['me_dashboard_blocks_explicit_none'] ?? false),
            'me_plugin_cards_explicit_none'     => (bool)($this->ctx['me_plugin_cards_explicit_none'] ?? false),
            'home_module_visibility'           => (array)($this->ctx['module_visibility'] ?? []),
            'platform_admin_tools_links'       => UserAssignmentContext::context()->meCoreQuickLinks($this->ctx),
        ]);
    }

    /**
     * Build workspace payload with a fail-safe fallback so /admin home still renders.
     *
     * @return array<string,mixed>
     */
    private function safeWorkspaceData(): array
    {
        try {
            return MyWorkService::build($this->user, $this->query);
        } catch (\Throwable $e) {
            error_log('[AdminSurfaceComposer] MyWorkService failed: ' . $e->getMessage());
            return [
                'user_name' => (string)($this->user['display_name'] ?? ($this->user['username'] ?? $this->user['email'] ?? '')),
                'user_role' => (string)($this->ctx['dashboard_type'] ?? 'operator'),
                'authority_role' => (string)($this->ctx['authority_role'] ?? ''),
                'dashboard_type' => (string)($this->ctx['dashboard_type'] ?? 'operator'),
                'kpi' => [],
                'sections' => [],
                'grouped_sections' => [],
                'all_items' => [],
                'has_approvals' => false,
                'has_overdue' => false,
                'has_blocked' => false,
                'role_links' => [],
                'cross_functional_access' => (array)($this->ctx['cross_functional_access'] ?? []),
                'primary_work_area' => '',
                'cross_work_areas' => [],
                'approval_modules' => [],
                'notification_summary' => [],
                'recent_notifications' => [],
                'unread_notifications' => 0,
                'active_assigned_apps' => array_values((array)($this->ctx['active_assigned_apps'] ?? [])),
            ];
        }
    }

    /**
     * Build host regions safely; fallback to empty regions on provider failures.
     *
     * @param array<string,mixed> $workspaceData
     * @return array<string,mixed>
     */
    private function safeHostRegions(array $workspaceData): array
    {
        try {
            $regions = HostSurfaceRegistryService::build('me', [
                'user'      => $this->user,
                'query'     => $this->query,
                'workspace' => $workspaceData,
                'context'   => $this->ctx,
            ]);
            return is_array($regions) ? $regions : [];
        } catch (\Throwable $e) {
            error_log('[AdminSurfaceComposer] HostSurfaceRegistryService failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Build admin panels safely; fallback to empty panel list on data errors.
     *
     * @return array<int,array<string,mixed>>
     */
    private function safeAdminDashboardPanels(): array
    {
        try {
            return AdminDashboardPanelBlockService::prepare($this->ctx, $this->user);
        } catch (\Throwable $e) {
            error_log('[AdminSurfaceComposer] adminDashboardPanels failed: ' . $e->getMessage());
            return [];
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function safeAdminLauncherGroups(bool $developerToolsVisible): array
    {
        try {
            $canAccessBase = function_exists('base_can_access_builder')
                ? (bool)base_can_access_builder($this->user)
                : strtolower(trim((string)($this->ctx['authority_role'] ?? ''))) === 'platform_admin';
            $devToolsEnabled = function_exists('app_dev_tools_enabled')
                ? (bool)app_dev_tools_enabled()
                : false;
            $menu = ShellRuntimeMenuComposer::compose(AdminDashboardLauncherComposer::menuContext(
                $this->user,
                $this->ctx,
                $canAccessBase,
                $devToolsEnabled,
                '/admin/' . rawurlencode((string)($this->user['username'] ?? ''))
            ));
            return AdminDashboardLauncherComposer::compose($menu, $developerToolsVisible);
        } catch (\Throwable $e) {
            error_log('[AdminSurfaceComposer] admin launcher failed: ' . $e->getMessage());
            return [];
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Merge core quick links into host regions, deduplicating and checking route access.
     *
     * @param array<string,mixed> $hostRegions
     * @return array<string,mixed>
     */
    private function mergeCoreWorkspaceRegions(array $hostRegions): array
    {
        $coreQuickLinks = UserAssignmentContext::context()->meCoreQuickLinks($this->ctx);
        if ($coreQuickLinks === []) {
            return $hostRegions;
        }

        $accessPolicy = UserAssignmentContext::accessPolicy();
        $routeAllowed = static function (?object $policy, ?array $userCtx, string $url): bool {
            $path = trim((string)parse_url($url, PHP_URL_PATH));
            if ($path === '' || $path === '#') {
                return true;
            }
            if (!is_object($policy)) {
                return true;
            }
            try {
                $decision = $policy->routeAccessDecision($userCtx, $path, 'GET');
                return (bool)($decision['allowed'] ?? false);
            } catch (\Throwable) {
                return true;
            }
        };

        $merged = [];
        $policyObj = is_object($accessPolicy) ? $accessPolicy : null;
        foreach (array_merge($coreQuickLinks, array_values((array)($hostRegions['quick_links'] ?? []))) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = trim((string)($link['url'] ?? ''));
            if ($url !== '' && !$routeAllowed($policyObj, $this->user, $url)) {
                continue;
            }
            $dedupeKey = strtolower(trim((string)($link['key'] ?? ($link['url'] ?? ''))));
            if ($dedupeKey === '' || isset($merged[$dedupeKey])) {
                continue;
            }
            $merged[$dedupeKey] = $link;
        }

        $hostRegions['quick_links'] = array_values($merged);
        usort($hostRegions['quick_links'], static function (array $left, array $right): int {
            $weight = ((int)($left['weight'] ?? 100)) <=> ((int)($right['weight'] ?? 100));
            if ($weight !== 0) {
                return $weight;
            }
            return strcmp((string)($left['label'] ?? ''), (string)($right['label'] ?? ''));
        });

        return $hostRegions;
    }

    /**
     * Build app launcher list for this admin context.
     *
     * @return array<int,string>
     */
    private function launcherApps(): array
    {
        $apps = array_values((array)($this->ctx['active_assigned_apps'] ?? []));
        if ((string)($this->ctx['authority_role'] ?? '') === 'platform_admin' && !in_array('platform', $apps, true)) {
            array_unshift($apps, 'platform');
        }
        return $apps;
    }

    /**
     * Determine experience mode for this user context.
     *
     * @param array<string,mixed> $workspaceData
     */
    private function experienceMode(array $workspaceData): string
    {
        $authorityRole = strtolower(trim((string)($this->ctx['authority_role'] ?? 'app_user')));
        if (in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            return 'admin';
        }

        $dashboardType = strtolower(trim((string)($workspaceData['dashboard_type'] ?? ($this->ctx['dashboard_type'] ?? 'operator'))));
        $profiles = array_map(
            static fn($token): string => strtolower(trim((string)$token)),
            (array)($this->ctx['access_profiles'] ?? [])
        );
        $dutyCodes = array_map(
            static fn($token): string => strtolower(trim((string)$token)),
            (array)($this->ctx['duty_codes'] ?? [])
        );

        if (in_array('display', $profiles, true) || in_array('display', $dutyCodes, true)) {
            return 'display';
        }
        if (in_array('read_only', $profiles, true) || in_array('read_only', $dutyCodes, true)) {
            return 'read_only';
        }
        if (in_array($dashboardType, ['production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader'], true)) {
            return 'leader';
        }
        return 'worker';
    }
}
