<?php
declare(strict_types=1);

namespace Plugins\Base\Controllers;

use App\Core\Auth;
use App\Core\Container;
use App\Core\DB;
use App\Core\RouteRuntimeAuthority;
use App\Core\View;
use App\Services\InviteLifecycleService;
use App\Services\PasswordResetService;
use App\Services\TileActionResolverService;
use Apps\Platform\Services\AppAdminDashboardScopeService;
use Plugins\Base\Services\NotificationService;
use Plugins\Base\Services\ResolvedExperienceDiagnosticsService;
use Plugins\Base\Services\UserDashboardAssignmentService;

require_once __DIR__ . '/../Services/ResolvedExperienceDiagnosticsService.php';

final class RoleDashboardsController
{
    private const DETAIL_TABS = ['access2', 'visibility', 'overrides', 'experience', 'diagnostics'];

    public static function redirectToRoleDashboard(): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/dashboard');
            header('Location: /login');
            exit;
        }

        header('Location: /me');
        exit;
    }

    public static function render(View $view, string $dashboardType): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/dashboard');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canAccessDashboardType($ctx, $dashboardType)) {
            header('Location: ' . UserDashboardAssignmentService::primaryLandingForContext($ctx));
            exit;
        }

        $dashboardType = self::canonicalDashboardType($dashboardType);
        $dashboard = self::dashboardPayloadForContext($ctx, $dashboardType, Auth::user());

        $viewData = [
            'pageTitle' => (string)($dashboard['title'] ?? 'Role Dashboard'),
            'dashboard' => $dashboard,
            'currentOperationalProfile' => (string)($ctx['operational_role'] ?? ($ctx['legacy_role'] ?? '')),
            'currentAccountType' => (string)($ctx['authority_role'] ?? ''),
            'dashboardType' => (string)($ctx['dashboard_type'] ?? ''),
            'notificationCount' => NotificationService::unreadCountForUser(Auth::user()),
        ];

        // Include platform mode data for platform admin dashboard
        if ($dashboardType === 'platform_admin') {
            $viewData['platformMode'] = \App\Services\PlatformModeService::currentMode();
            $viewData['platformModeAvailable'] = \App\Services\PlatformModeService::availableModes();
        }

        $view->render('Base::ops/role_dashboard.php', $viewData);
    }

    /**
     * @param array<string,mixed> $ctx
     * @param array<string,mixed>|null $user
     * @return array<string,mixed>
     */
    public static function dashboardPayloadForContext(array $ctx, string $dashboardType, ?array $user = null): array
    {
        $dashboardType = self::canonicalDashboardType($dashboardType);
        
        // Try Manufacturing contribution service for Manufacturing-specific roles
        $operationalRole = trim((string)($ctx['operational_role'] ?? ($ctx['legacy_role'] ?? '')));
        $manufacturingRoles = ['production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader'];
        if (in_array($operationalRole, $manufacturingRoles, true) 
            && class_exists('Apps\\Manufacturing\\Services\\HostSurfaceContributionService')) {
            $mfgDashboard = \Apps\Manufacturing\Services\HostSurfaceContributionService::getOperatorDashboardPayload($operationalRole, $ctx);
            if (is_array($mfgDashboard) && !empty($mfgDashboard)) {
                $dashboard = $mfgDashboard;
                $dashboard = self::filterDashboardByAssignments($dashboard, $user);
                return self::annotateTileActions($dashboard, $dashboardType);
            }
        }
        
        // Fall back to Base controller dashboard methods for platform roles
        $dashboard = match ($dashboardType) {
            'platform_admin' => self::platformAdminDashboard(),
            'app_admin' => self::appAdminDashboard($ctx),
            'my_work' => self::productionLeaderDashboard($ctx),
            'production_leader' => self::productionLeaderDashboard($ctx),
            'assembly_leader' => self::assemblyLeaderDashboard($ctx),
            'qc_leader' => self::qcLeaderDashboard($ctx),
            'dispatch_leader' => self::dispatchLeaderDashboard($ctx),
            default => self::productionLeaderDashboard($ctx),
        };

        $dashboard = self::filterDashboardByAssignments($dashboard, $user);
        return self::annotateTileActions($dashboard, $dashboardType);
    }

    /**
     * @param array<string,mixed> $dashboard
     * @return array<string,mixed>
     */
    private static function annotateTileActions(array $dashboard, string $dashboardType): array
    {
        $cards = is_array($dashboard['cards'] ?? null) ? (array)$dashboard['cards'] : [];
        if ($cards === []) {
            return $dashboard;
        }

        $dashboard['cards'] = TileActionResolverService::annotateRoleDashboardCards($dashboardType, $cards);
        return $dashboard;
    }

    public static function renderAssignmentConsole(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            header('Location: /ops/dashboard');
            exit;
        }

        $filters = [
            'search' => trim((string)($_GET['q'] ?? '')),
            'account_class' => trim((string)($_GET['account_class'] ?? '')),
            'assigned_app' => trim((string)($_GET['assigned_app'] ?? '')),
            'role_pack' => trim((string)($_GET['role_pack'] ?? '')),
            'workspace_profile' => trim((string)($_GET['workspace_profile'] ?? '')),
            'status' => trim((string)($_GET['status'] ?? 'all')),
        ];

        $rows = UserDashboardAssignmentService::listAssignmentRows($filters, false);
        $lifecycleRows = UserDashboardAssignmentService::listLifecycleUsers($filters);
        $accessProfileRegistry = UserDashboardAssignmentService::accessProfileRegistry();
        $assignmentUiConfig = UserDashboardAssignmentService::assignmentUiConfig();
        $workspaceProfileCatalog = [];
        try {
            $profileRows = DB::fetchAll(
                'SELECT id, profile_key, name, is_active FROM workspace_profiles ORDER BY name ASC'
            );
            foreach ($profileRows as $profileRow) {
                $profileKey = strtolower(trim((string)($profileRow['profile_key'] ?? '')));
                if ($profileKey === '') {
                    continue;
                }
                $workspaceProfileCatalog[$profileKey] = [
                    'id' => (int)($profileRow['id'] ?? 0),
                    'key' => $profileKey,
                    'name' => (string)($profileRow['name'] ?? $profileKey),
                    'is_active' => (int)($profileRow['is_active'] ?? 1),
                ];
            }
        } catch (\Throwable) {
            // Non-fatal.
        }
        $targetRoles = [];
        foreach ($rows as $row) {
            $role = trim((string)($row['operational_role'] ?? ($row['role'] ?? '')));
            if ($role === '') {
                continue;
            }
            $targetRoles[strtolower($role)] = $role;
        }

        $flash = self::pullFlash('ok');
        $error = self::pullFlash('err');
        $openAddUser = ((int)($_GET['open_add_user'] ?? 0)) === 1;

        $view->render('Base::ops/dashboard_assignments.php', [
            'pageTitle' => 'Access Control Board',
            'assignmentRows' => $rows,
            'lifecycleRows' => $lifecycleRows,
            'operationalProfiles' => UserDashboardAssignmentService::operationalProfiles(),
            'accessProfileRegistry' => $accessProfileRegistry,
            'roleDefaultsMap' => UserDashboardAssignmentService::roleDefaultsForUi(),
            'assignmentUiConfig' => $assignmentUiConfig,
            'workspaceProfileCatalog' => $workspaceProfileCatalog,
            'accessRegistryRows' => self::buildAccessRegistryRows($rows, $assignmentUiConfig, $accessProfileRegistry),
            'targetRoles' => array_values($targetRoles),
            'filters' => $filters,
            'currentUserId' => (int)($ctx['user_id'] ?? 0),
            'flash' => $flash,
            'error' => $error,
            'addUserOpen' => $error !== '' || $openAddUser,
        ]);
    }

    public static function renderUserDashboardBoard(View $view): void
    {
        $target = '/ops/user-control';
        $query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
        if ($query !== '') {
            $target .= '?' . $query;
        }
        header('Location: ' . $target, true, 302);
        exit;
    }

    public static function renderUserControlBoard(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/user-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        PasswordResetService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            header('Location: /ops/dashboard');
            exit;
        }

        $rows = self::buildUserControlRows([]);
        $accessProfileRegistry = UserDashboardAssignmentService::accessProfileRegistry();
        $assignmentUiConfig = UserDashboardAssignmentService::assignmentUiConfig();
        $error = self::pullFlash('err');
        $openAddUser = ((int)($_GET['open_add_user'] ?? 0)) === 1 || $error !== '';

        $view->render('Base::ops/user_control_board.php', [
            'pageTitle' => 'User Control Board',
            'rows' => $rows,
            'blockCatalog' => UserDashboardAssignmentService::meDashboardBlockCatalog(),
            'pluginCardCatalog' => UserDashboardAssignmentService::mePluginCardCatalog(),
            'operationalProfiles' => UserDashboardAssignmentService::operationalProfiles(),
            'accessProfileRegistry' => $accessProfileRegistry,
            'assignmentUiConfig' => $assignmentUiConfig,
            'flash' => self::pullFlash('ok'),
            'error' => $error,
            'addUserOpen' => $openAddUser,
            'csrf' => Auth::csrfToken(),
        ]);
    }

    public static function renderUserControlDetail(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/user-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        PasswordResetService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Forbidden';
            return;
        }

        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Missing user id';
            return;
        }

        $rows = self::buildUserControlRows(['user_id' => $userId]);
        $row = $rows[0] ?? null;
        if (!is_array($row)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'User not found';
            return;
        }

        $view->render('Base::ops/user_control_detail.php', [
            'pageTitle' => 'User Control Detail',
            'row' => $row,
            'securityEvents' => self::userSecurityEvents($userId, 12),
            'blockCatalog' => UserDashboardAssignmentService::meDashboardBlockCatalog(),
            'pluginCardCatalog' => UserDashboardAssignmentService::mePluginCardCatalog(),
            'flash' => self::pullFlash('ok'),
            'error' => self::pullFlash('err'),
            'csrf' => Auth::csrfToken(),
        ]);
    }

    public static function renderAssignmentDetail(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            http_response_code(401);
            if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || str_contains((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest')) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized']);
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Unauthorized';
            }
            return;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            http_response_code(403);
            if (str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || str_contains((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest')) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Forbidden']);
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Forbidden';
            }
            return;
        }

        $itemType = strtolower(trim((string)($_GET['item_type'] ?? '')));
        $itemKey = strtolower(trim((string)($_GET['item_key'] ?? '')));
        if ($itemType !== '' && $itemKey !== '') {
            $rows = UserDashboardAssignmentService::listAssignmentRows([], false);
            $accessProfileRegistry = UserDashboardAssignmentService::accessProfileRegistry();
            $assignmentUiConfig = UserDashboardAssignmentService::assignmentUiConfig();
            $item = self::buildAccessRegistryDetail($rows, $itemType, $itemKey, $assignmentUiConfig, $accessProfileRegistry);
            if (!is_array($item)) {
                http_response_code(404);
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Access item not found';
                return;
            }

            $payload = [
                'item' => $item,
                'pageTitle' => 'Access Item Detail',
            ];
            $fullPage = ((int)($_GET['full'] ?? 0)) === 1;
            if ($fullPage) {
                $view->render('Base::ops/dashboard_assignment_item_detail_page.php', $payload);
                return;
            }

            $view->renderRaw('Base::ops/dashboard_assignment_item_detail_page.php', $payload);
            return;
        }

        $fullPage = ((int)($_GET['full'] ?? 0)) === 1;
        $userId = (int)($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            if ($fullPage) {
                self::flash('err', 'Missing user id for access detail.');
                header('Location: /ops/access-control');
                exit;
            }
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Missing user id';
            return;
        }

        try {
            $rows = UserDashboardAssignmentService::listAssignmentRows(['user_id' => $userId], true);
        } catch (\Throwable $e) {
            if ($fullPage) {
                self::flash('err', 'Access detail is temporarily unavailable for this user.');
                header('Location: /ops/access-control?user_id=' . $userId);
                exit;
            }
            http_response_code(200);
            echo '<div class="card"><div class="muted">Access detail is temporarily unavailable for this user.</div></div>';
            return;
        }

        $row = $rows[0] ?? null;
        if (!is_array($row)) {
            if ($fullPage) {
                self::flash('err', 'Access detail user not found.');
                header('Location: /ops/access-control?user_id=' . $userId);
                exit;
            }
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Assignment not found';
            return;
        }

        // Fetch per-app operational roles for App Roles section.
        $appRoles = DB::fetchAll(
            'SELECT id, app_key, operational_role, is_primary FROM user_app_roles WHERE user_id = ? ORDER BY is_primary DESC, app_key ASC',
            [$userId]
        );

        // Resolve effective workspace_profile for this user (read-only informational).
        $resolvedProfile = null;
        try {
            $profileUser = DB::fetchOne('SELECT id, role, authority_role, role_tier FROM users WHERE id = ? LIMIT 1', [$userId]);
            if (is_array($profileUser) && $profileUser !== []) {
                $ctx = UserDashboardAssignmentService::resolveUserContext($profileUser);
                $resolvedProfile = $ctx['workspace_profile'] ?? null;
                if (is_array($resolvedProfile)) {
                    $profileKey = strtolower(trim((string)($resolvedProfile['profile_key'] ?? '')));
                    $profileId = (int)($resolvedProfile['id'] ?? 0);
                    if ($profileId <= 0 && $profileKey !== '') {
                        $profileLookup = DB::fetchOne(
                            'SELECT id FROM workspace_profiles WHERE profile_key = ? LIMIT 1',
                            [$profileKey]
                        );
                        if (is_array($profileLookup) && (int)($profileLookup['id'] ?? 0) > 0) {
                            $resolvedProfile['id'] = (int)$profileLookup['id'];
                        }
                    }
                }
            }
        } catch (\Throwable) {
            // Non-fatal.
        }

        // Load active workspace profiles for the pin dropdown.
        // Prefer profiles whose authority_role matches this user, but also show all (grouped).
        $userAuthorityRole = (string)($row['authority_role'] ?? '');
        $profileOptions = [];
        try {
            $profileRows = DB::fetchAll(
                "SELECT id, profile_key, name, authority_role, app_key, owner_type, owner_key, default_app, assigned_apps, access_profiles, landing_route
                   FROM workspace_profiles
                  WHERE is_active = 1
                  ORDER BY (authority_role = ?) DESC, name ASC",
                [$userAuthorityRole]
            );
            foreach ($profileRows as $pr) {
                $profileOptions[] = [
                    'id'            => (int)($pr['id'] ?? 0),
                    'key'           => (string)($pr['profile_key'] ?? ''),
                    'name'          => (string)($pr['name'] ?? ''),
                    'authority_role'=> (string)($pr['authority_role'] ?? ''),
                    'app_key'       => (string)($pr['app_key'] ?? ''),
                    'owner_type'    => (string)($pr['owner_type'] ?? ''),
                    'owner_key'     => (string)($pr['owner_key'] ?? ''),
                    'default_app'   => (string)($pr['default_app'] ?? ''),
                    'assigned_apps' => (string)($pr['assigned_apps'] ?? ''),
                    'access_profiles' => (string)($pr['access_profiles'] ?? ''),
                    'landing_route' => (string)($pr['landing_route'] ?? ''),
                ];
            }
        } catch (\Throwable) {
            // Non-fatal.
        }

        $payload = [
            'row' => $row,
            'appRoles' => $appRoles,
            'resolvedProfile' => $resolvedProfile,
            'resolvedExperienceDiagnostics' => ResolvedExperienceDiagnosticsService::resolve($row, is_array($resolvedProfile) ? $resolvedProfile : null),
            'profileOptions' => $profileOptions,
            'currentUserCanManageAssignments' => self::canManageAssignments($ctx),
            'operationalProfiles' => UserDashboardAssignmentService::operationalProfiles(),
            'accessProfileRegistry' => UserDashboardAssignmentService::accessProfileRegistry(),
            'assignmentUiConfig' => UserDashboardAssignmentService::assignmentUiConfig(),
            'selectedTab' => self::normalizeDetailTab((string)($_GET['tab'] ?? 'access2')),
        ];

        $attemptedInput = self::pullFlashData('access_input');
        if (is_array($attemptedInput) && (int)($attemptedInput['user_id'] ?? 0) === $userId) {
            $row = array_merge($row, $attemptedInput);
            $payload['row'] = $row;
        }

        if ($fullPage) {
            $view->render('Base::ops/dashboard_assignment_detail_page.php', array_merge($payload, [
'pageTitle' => 'Access Governance Detail',
            ]));
            return;
        }

        $view->renderRaw('Base::ops/dashboard_assignment_detail_access2.php', $payload);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $assignmentUiConfig
     * @param array<string,mixed> $accessProfileRegistry
     * @return array<int,array<string,mixed>>
     */
    private static function buildAccessRegistryRows(array $rows, array $assignmentUiConfig, array $accessProfileRegistry): array
    {
        $typeOrder = [
            'app' => 10,
            'profile' => 20,
            'module' => 30,
            'permission' => 40,
            'view' => 50,
            'table' => 60,
            'chart' => 70,
            'plugin_card' => 80,
        ];

        $appLabels = [
            'platform' => 'Platform',
            'manufacturing' => 'Manufacturing',
            'accounting' => 'Accounting',
            'hr' => 'HR',
            'inventory' => 'Inventory',
            'sales' => 'Sales',
            'sbaio' => 'SBAIO',
        ];
        foreach ((array)($assignmentUiConfig['appOptions'] ?? []) as $app) {
            $appKey = strtolower(trim((string)$app));
            if ($appKey !== '' && !isset($appLabels[$appKey])) {
                $appLabels[$appKey] = self::humanizeToken($appKey);
            }
        }

        $profileLabels = [];
        foreach ((array)($assignmentUiConfig['rolePacksByApp'] ?? []) as $packs) {
            foreach ((array)$packs as $packKey => $packLabel) {
                $profileLabels[strtolower(trim((string)$packKey))] = trim((string)$packLabel) !== ''
                    ? (string)$packLabel
                    : self::humanizeToken((string)$packKey);
            }
        }
        foreach ($accessProfileRegistry as $profileKey => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $normalizedKey = strtolower(trim((string)$profileKey));
            if ($normalizedKey === '') {
                continue;
            }
            $profileLabels[$normalizedKey] = trim((string)($cfg['label'] ?? '')) !== ''
                ? (string)$cfg['label']
                : self::humanizeToken($normalizedKey);
        }

        $pluginCardLabels = [];
        foreach ((array)($assignmentUiConfig['mePluginCards'] ?? []) as $cardKey => $cardLabel) {
            $normalizedKey = strtolower(trim((string)$cardKey));
            if ($normalizedKey === '') {
                continue;
            }
            $pluginCardLabels[$normalizedKey] = trim((string)$cardLabel) !== ''
                ? (string)$cardLabel
                : self::humanizeToken($normalizedKey);
        }

        $items = [];
        foreach ($rows as $row) {
            $userId = (int)($row['id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $userRoles = [];
            $dashboardType = trim((string)($row['dashboard_type'] ?? ''));
            if ($dashboardType !== '') {
                $userRoles[strtolower($dashboardType)] = self::humanizeToken($dashboardType);
            }
            foreach (self::csvList((string)($row['selected_role_packs'] ?? (string)($row['access_profiles'] ?? ''))) as $profileKey) {
                $userRoles[$profileKey] = (string)($profileLabels[$profileKey] ?? self::humanizeToken($profileKey));
            }

            $userSummary = [
                'id' => $userId,
                'email' => (string)($row['email'] ?? ''),
                'display_name' => (string)($row['display_name'] ?? ''),
                'authority_role' => (string)($row['authority_role'] ?? ''),
                'account_class' => (string)($row['account_class'] ?? ''),
                'dashboard_type' => $dashboardType,
                'assigned_apps' => self::csvList((string)($row['assigned_apps'] ?? '')),
                'profiles' => array_values($userRoles),
            ];

            $tokenSets = [
                'app' => self::csvList((string)($row['assigned_apps'] ?? '')),
                'profile' => self::csvList((string)($row['selected_role_packs'] ?? (string)($row['access_profiles'] ?? ''))),
                'module' => self::csvList((string)($row['module_visibility'] ?? '')),
                'permission' => self::csvList((string)($row['permissions'] ?? '')),
                'view' => self::csvList((string)($row['view_access'] ?? '')),
                'table' => self::csvList((string)($row['table_access'] ?? '')),
                'chart' => self::csvList((string)($row['chart_access'] ?? '')),
                'plugin_card' => self::csvList((string)($row['me_plugin_cards'] ?? '')),
            ];

            foreach ($tokenSets as $type => $tokens) {
                foreach ($tokens as $token) {
                    $bucketKey = $type . ':' . $token;
                    if (!isset($items[$bucketKey])) {
                        $label = match ($type) {
                            'app' => (string)($appLabels[$token] ?? self::humanizeToken($token)),
                            'profile' => (string)($profileLabels[$token] ?? self::humanizeToken($token)),
                            'plugin_card' => (string)($pluginCardLabels[$token] ?? self::humanizeToken($token)),
                            default => self::humanizeToken($token),
                        };
                        $items[$bucketKey] = [
                            'type' => $type,
                            'type_label' => self::accessRegistryTypeLabel($type),
                            'key' => $token,
                            'label' => $label,
                            'users' => [],
                            'role_labels' => [],
                        ];
                    }

                    $items[$bucketKey]['users'][$userId] = $userSummary;
                    foreach ($userRoles as $roleKey => $roleLabel) {
                        $items[$bucketKey]['role_labels'][$roleKey] = $roleLabel;
                    }
                }
            }
        }

        $registry = [];
        foreach ($items as $item) {
            $users = array_values((array)($item['users'] ?? []));
            usort($users, static function (array $left, array $right): int {
                return strcmp((string)($left['email'] ?? ''), (string)($right['email'] ?? ''));
            });

            $roleLabels = array_values((array)($item['role_labels'] ?? []));
            natcasesort($roleLabels);
            $roleLabels = array_values($roleLabels);

            $registry[] = [
                'type' => (string)$item['type'],
                'type_label' => (string)$item['type_label'],
                'key' => (string)$item['key'],
                'label' => (string)$item['label'],
                'assigned_users_count' => count($users),
                'assigned_roles_count' => count($roleLabels),
                'user_preview' => array_slice(array_map(static function (array $user): string {
                    $display = trim((string)($user['display_name'] ?? ''));
                    return $display !== '' ? $display : (string)($user['email'] ?? '');
                }, $users), 0, 3),
                'role_preview' => array_slice($roleLabels, 0, 4),
                'detail_url' => '/ops/access-control/detail?item_type=' . rawurlencode((string)$item['type']) . '&item_key=' . rawurlencode((string)$item['key']) . '&full=1',
                '_sort_type' => (int)($typeOrder[(string)$item['type']] ?? 999),
                '_sort_label' => strtolower((string)$item['label']),
            ];
        }

        usort($registry, static function (array $left, array $right): int {
            $typeCompare = ((int)($left['_sort_type'] ?? 999)) <=> ((int)($right['_sort_type'] ?? 999));
            if ($typeCompare !== 0) {
                return $typeCompare;
            }
            return strcmp((string)($left['_sort_label'] ?? ''), (string)($right['_sort_label'] ?? ''));
        });

        foreach ($registry as &$row) {
            unset($row['_sort_type'], $row['_sort_label']);
        }
        unset($row);

        return $registry;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array<string,mixed> $assignmentUiConfig
     * @param array<string,mixed> $accessProfileRegistry
     * @return array<string,mixed>|null
     */
    private static function buildAccessRegistryDetail(array $rows, string $itemType, string $itemKey, array $assignmentUiConfig, array $accessProfileRegistry): ?array
    {
        foreach (self::buildAccessRegistryRows($rows, $assignmentUiConfig, $accessProfileRegistry) as $item) {
            if ((string)($item['type'] ?? '') !== $itemType || (string)($item['key'] ?? '') !== $itemKey) {
                continue;
            }

            $matchedUsers = [];
            $matchedRoleLabels = [];
            foreach ($rows as $row) {
                $tokenSets = [
                    'app' => self::csvList((string)($row['assigned_apps'] ?? '')),
                    'profile' => self::csvList((string)($row['selected_role_packs'] ?? (string)($row['access_profiles'] ?? ''))),
                    'module' => self::csvList((string)($row['module_visibility'] ?? '')),
                    'permission' => self::csvList((string)($row['permissions'] ?? '')),
                    'view' => self::csvList((string)($row['view_access'] ?? '')),
                    'table' => self::csvList((string)($row['table_access'] ?? '')),
                    'chart' => self::csvList((string)($row['chart_access'] ?? '')),
                    'plugin_card' => self::csvList((string)($row['me_plugin_cards'] ?? '')),
                ];

                if (!in_array($itemKey, (array)($tokenSets[$itemType] ?? []), true)) {
                    continue;
                }

                $profiles = self::csvList((string)($row['selected_role_packs'] ?? (string)($row['access_profiles'] ?? '')));
                $matchedUsers[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'email' => (string)($row['email'] ?? ''),
                    'display_name' => (string)($row['display_name'] ?? ''),
                    'authority_role' => (string)($row['authority_role'] ?? ''),
                    'account_class' => (string)($row['account_class'] ?? ''),
                    'dashboard_type' => (string)($row['dashboard_type'] ?? ''),
                    'assigned_apps' => self::csvList((string)($row['assigned_apps'] ?? '')),
                    'profiles' => array_map([self::class, 'humanizeToken'], $profiles),
                    'user_detail_url' => '/ops/access-control/detail?user_id=' . (int)($row['id'] ?? 0) . '&full=1',
                    'user_control_url' => '/ops/user-control/detail?user_id=' . (int)($row['id'] ?? 0),
                ];
                $dashboardType = trim((string)($row['dashboard_type'] ?? ''));
                if ($dashboardType !== '') {
                    $matchedRoleLabels[strtolower($dashboardType)] = self::humanizeToken($dashboardType);
                }
                foreach ($profiles as $profileKey) {
                    $matchedRoleLabels[$profileKey] = self::humanizeToken($profileKey);
                }
            }

            usort($matchedUsers, static function (array $left, array $right): int {
                return strcmp((string)($left['email'] ?? ''), (string)($right['email'] ?? ''));
            });
            natcasesort($matchedRoleLabels);

            $detail = $item;
            $detail['assigned_users'] = $matchedUsers;
            $detail['role_labels'] = array_values($matchedRoleLabels);
            $detail['summary'] = match ($itemType) {
                'app' => 'Assigned app access controls which users can enter this app.',
                'profile' => 'Role and profile grants shape the user experience and default token coverage.',
                'module' => 'Module visibility controls which product areas appear in operational navigation.',
                'permission' => 'Permissions are explicit action-level grants and compatibility overrides.',
                'view', 'table', 'chart' => 'Surface tokens control which boards, tables, and analytics surfaces are available.',
                'plugin_card' => 'App Quick Link cards control which app-contributed quick links appear in /me.',
                default => 'Access item registry detail.',
            };

            return $detail;
        }

        return null;
    }

    private static function accessRegistryTypeLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'app' => 'App',
            'profile' => 'Role / Profile',
            'module' => 'Module',
            'permission' => 'Permission',
            'view' => 'View Token',
            'table' => 'Table Token',
            'chart' => 'Chart Token',
            'plugin_card' => 'App Quick Link Card',
            default => self::humanizeToken($type),
        };
    }

    private static function humanizeToken(string $token): string
    {
        $normalized = trim(strtolower($token));
        if ($normalized === '') {
            return '';
        }
        $normalized = str_replace(['.', '_', '-'], ' ', $normalized);
        return trim((string)preg_replace('/\s+/', ' ', ucwords($normalized)));
    }

    public static function normalizeAssignments(): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to normalize assignments.');
            header('Location: /ops/dashboard');
            exit;
        }

        try {
            Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
            $normalized = UserDashboardAssignmentService::normalizeExistingAssignments(Auth::user());
            if (empty($normalized)) {
                self::flash('ok', 'No assignment mismatches required normalization.');
            } else {
                $emails = array_map(static fn(array $r): string => (string)($r['email'] ?? ''), $normalized);
                self::flash('ok', 'Normalized ' . count($normalized) . ' user assignment rows: ' . implode(', ', array_filter($emails)));
            }
        } catch (\Throwable $e) {
            self::flash('err', 'Normalization failed: ' . $e->getMessage());
        }

        header('Location: /ops/access-control');
        exit;
    }

    public static function createUser(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/user-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to create users.');
            header('Location: /ops/dashboard');
            exit;
        }

        $redirect = self::safeRedirectTarget('/ops/user-control');

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $created = UserDashboardAssignmentService::createUserFromInput($input, Auth::user());
            if (!empty($created['invite_sent'])) {
                self::flash('ok', 'User created and setup link sent: ' . (string)($created['email'] ?? '') . '.');
            } elseif (trim((string)($created['invite_error'] ?? '')) !== '') {
                self::flash('ok', 'User created: ' . (string)($created['email'] ?? '') . '. Setup is still pending, but the invite email could not be sent: ' . (string)($created['invite_error'] ?? ''));
            } elseif ((string)($created['verification_status'] ?? '') === 'setup_pending') {
                self::flash('ok', 'User created: ' . (string)($created['email'] ?? '') . '. Setup is pending until a setup link is sent.');
            } else {
                self::flash('ok', 'User created: ' . (string)($created['email'] ?? '') . '.');
            }
            header('Location: ' . $redirect);
            exit;
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
            header('Location: ' . self::appendQueryParam($redirect, 'open_add_user', '1') . '#userCreatePanel');
            exit;
        }
    }

    public static function updateBasicUser(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to edit users.');
            header('Location: /ops/dashboard');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            UserDashboardAssignmentService::updateBasicUserFromInput($input, Auth::user());
            self::flash('ok', 'User profile updated.');
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        header('Location: ' . self::safeRedirectTarget('/ops/access-control'));
        exit;
    }

    public static function setUserStatus(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to change user status.');
            header('Location: /ops/dashboard');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $status = UserDashboardAssignmentService::setUserStatusFromInput($input, Auth::user());
            if ($status === 'active') {
                self::flash('ok', 'User reactivated.');
            } else {
                self::flash('ok', 'User deactivated.');
            }
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        header('Location: ' . self::safeRedirectTarget('/ops/access-control'));
        exit;
    }

    public static function sendUserRecoveryLink(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/user-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        PasswordResetService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to send recovery links.');
            header('Location: /ops/dashboard');
            exit;
        }

        $redirect = self::safeRedirectTarget('/ops/user-control');

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $userId = (int)($input['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new \InvalidArgumentException('Missing user id.');
            }

            $user = DB::fetchOne(
                'SELECT id, email, display_name, account_status
                 FROM users
                 WHERE id = ?
                 LIMIT 1',
                [$userId]
            );
            if (!$user) {
                throw new \RuntimeException('User not found.');
            }

            $status = strtolower(trim((string)($user['account_status'] ?? 'active')));
            if ($status === 'disabled') {
                throw new \RuntimeException('Recovery links are blocked for disabled users. Reactivate the user first.');
            }

            $service = new PasswordResetService();
            $service->requestReset(
                (string)($user['email'] ?? ''),
                trim((string)($_SERVER['REMOTE_ADDR'] ?? '')),
                trim((string)($_SERVER['HTTP_USER_AGENT'] ?? ''))
            );

            self::flash('ok', 'Recovery link sent to ' . (string)($user['email'] ?? '') . '.');
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        header('Location: ' . $redirect);
        exit;
    }

    public static function sendUserSetupLink(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/user-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        InviteLifecycleService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to send setup links.');
            header('Location: /ops/dashboard');
            exit;
        }

        $redirect = self::safeRedirectTarget('/ops/user-control');
        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $userId = (int)($input['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new \InvalidArgumentException('Missing user id.');
            }

            $inviteService = new InviteLifecycleService();
            $inviteService->issueSetupLinkForUser($userId, true);
            self::flash('ok', 'Setup link sent.');
        } catch (\Throwable $e) {
            self::flash('err', $e->getMessage());
        }

        header('Location: ' . $redirect);
        exit;
    }

    public static function saveAssignment(array $input): void
    {
        self::saveAccessGovernance($input);
    }

    public static function saveAccessGovernance(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to manage the Access Control Board.');
            header('Location: /ops/dashboard');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $saveInput = self::isImmediateAssignedAppSave($input)
                ? self::hydrateImmediateAssignedAppSave($input)
                : self::hydrateAccessGovernanceSave($input);
            UserDashboardAssignmentService::saveFromInput($saveInput, Auth::user());
            self::flash('ok', 'Access governance updated.');
        } catch (\Throwable $e) {
            self::flashData('access_input', self::captureAccessAttemptInput($input));
            self::flash('err', 'Save failed: ' . $e->getMessage());
        }

        header('Location: ' . self::safeRedirectTarget('/ops/access-control'));
        exit;
    }

    public static function saveExperienceLayout(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to manage the Access Control Board.');
            header('Location: /ops/dashboard');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            UserDashboardAssignmentService::saveExperienceLayoutFromInput($input, Auth::user());
            self::flash('ok', 'Experience layout updated.');
        } catch (\Throwable $e) {
            self::flash('err', 'Save failed: ' . $e->getMessage());
        }

        header('Location: ' . self::safeRedirectTarget('/ops/access-control'));
        exit;
    }

    public static function saveAppRole(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', self::tr('ops.access_control.app_role.forbidden', 'Not authorized to manage app roles.'));
            header('Location: /ops/access-control');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $userId = (int)($input['user_id'] ?? 0);
            $appKey = strtolower(trim((string)($input['app_key'] ?? '')));
            $operationalRole = strtolower(trim((string)($input['operational_role'] ?? '')));
            if ($userId <= 0 || $appKey === '' || $operationalRole === '') {
                throw new \InvalidArgumentException('Missing required fields: user_id, app_key, operational_role.');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $appKey)) {
                throw new \InvalidArgumentException('Invalid app_key format.');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $operationalRole)) {
                throw new \InvalidArgumentException('Invalid operational_role format.');
            }
            $rolesByApp = UserDashboardAssignmentService::appOperationalRolesByKey();
            $allowedRoles = (array)($rolesByApp[$appKey] ?? []);
            if (!isset($allowedRoles[$operationalRole])) {
                throw new \InvalidArgumentException('Operational role is not available for the selected app.');
            }
            $isPrimary = ($input['is_primary'] ?? '0') === '1' ? 1 : 0;
            DB::query(
                'INSERT INTO user_app_roles (user_id, app_key, operational_role, is_primary)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE operational_role = VALUES(operational_role), is_primary = VALUES(is_primary), updated_at = NOW()',
                [$userId, $appKey, $operationalRole, $isPrimary]
            );
            self::flash('ok', self::tr('ops.access_control.app_role.saved', 'App role saved.'));
        } catch (\Throwable $e) {
            self::flash('err', 'Save failed: ' . $e->getMessage());
        }

        $redirectTo = trim((string)($input['redirect_to'] ?? ''));
        header('Location: ' . self::safeRedirectTarget($redirectTo !== '' ? $redirectTo : '/ops/access-control'));
        exit;
    }

    public static function deleteAppRole(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', self::tr('ops.access_control.app_role.forbidden', 'Not authorized to manage app roles.'));
            header('Location: /ops/access-control');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $userId = (int)($input['user_id'] ?? 0);
            $appKey = strtolower(trim((string)($input['app_key'] ?? '')));
            if ($userId <= 0 || $appKey === '') {
                throw new \InvalidArgumentException('Missing required fields: user_id, app_key.');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $appKey)) {
                throw new \InvalidArgumentException('Invalid app_key format.');
            }
            DB::query(
                'DELETE FROM user_app_roles WHERE user_id = ? AND app_key = ? LIMIT 1',
                [$userId, $appKey]
            );
            self::flash('ok', self::tr('ops.access_control.app_role.deleted', 'App role removed.'));
        } catch (\Throwable $e) {
            self::flash('err', 'Delete failed: ' . $e->getMessage());
        }

        $redirectTo = trim((string)($input['redirect_to'] ?? ''));
        header('Location: ' . self::safeRedirectTarget($redirectTo !== '' ? $redirectTo : '/ops/access-control'));
        exit;
    }

    public static function saveWorkspaceProfilePin(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to manage workspace profile pins.');
            header('Location: /ops/access-control');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $userId = (int)($input['user_id'] ?? 0);
            if ($userId <= 0) {
                throw new \InvalidArgumentException('Missing user_id.');
            }
            $rawKey = strtolower(trim((string)($input['workspace_profile_key'] ?? '')));
            $profileRow = null;
            if ($rawKey !== '' && !preg_match('/^[a-z0-9_]+$/', $rawKey)) {
                throw new \InvalidArgumentException('Invalid profile key format.');
            }
            // Verify profile exists and is active (if not clearing).
            if ($rawKey !== '') {
                $profileRow = DB::fetchOne(
                    "SELECT id, profile_key, authority_role, app_key, landing_route, default_app, assigned_apps, access_profiles
                     FROM workspace_profiles
                     WHERE profile_key = ? AND is_active = 1
                     LIMIT 1",
                    [$rawKey]
                );
                if (!is_array($profileRow) || $profileRow === []) {
                    throw new \InvalidArgumentException('Workspace profile not found or is inactive.');
                }

                // Apply profile defaults through the canonical access-governance save pipeline.
                $profileSaveInput = [
                    'user_id' => $userId,
                    'dashboard_mode' => 'auto',
                ];

                $authorityRole = strtolower(trim((string)($profileRow['authority_role'] ?? '')));
                if (in_array($authorityRole, ['platform_admin', 'app_admin', 'app_user', 'tv_display'], true)) {
                    $profileSaveInput['authority_role'] = $authorityRole;
                }

                $defaultApp = strtolower(trim((string)($profileRow['default_app'] ?? '')));
                if ($defaultApp === '') {
                    $defaultApp = strtolower(trim((string)($profileRow['app_key'] ?? '')));
                }
                if ($defaultApp !== '') {
                    $profileSaveInput['default_app'] = $defaultApp;
                    $profileSaveInput['default_app_mode'] = 'manual';
                }

                $landingRoute = trim((string)($profileRow['landing_route'] ?? ''));
                if ($landingRoute !== '' && str_starts_with($landingRoute, '/')) {
                    $profileSaveInput['default_landing_page'] = $landingRoute;
                    $profileSaveInput['landing_mode'] = 'manual';
                }

                $assignedApps = strtolower(trim((string)($profileRow['assigned_apps'] ?? '')));
                if ($assignedApps !== '') {
                    $profileSaveInput['assigned_apps'] = $assignedApps;
                }

                $accessProfiles = strtolower(trim((string)($profileRow['access_profiles'] ?? '')));
                if ($accessProfiles !== '') {
                    $profileSaveInput['access_profiles'] = $accessProfiles;
                    $profileSaveInput['access_profiles_mode'] = 'manual';
                }

                $profileSaveInput = self::hydrateAccessGovernanceSave($profileSaveInput);
                UserDashboardAssignmentService::saveFromInput($profileSaveInput, Auth::user());
            }
            $actorLabel = self::tr('actor_label', Auth::user()['email'] ?? 'admin');
            // Strict-mode-safe upsert: assignment row almost always exists by
            // the time an admin can pin a profile (the detail page wouldn't
            // render otherwise). Probe first, then UPDATE or INSERT — avoids
            // the NOT-NULL trap on `dashboard_type` when row is new.
            $existing = DB::fetchOne(
                "SELECT user_id FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1",
                [$userId]
            );
            if (is_array($existing) && $existing !== []) {
                DB::query(
                    "UPDATE user_dashboard_assignments
                        SET workspace_profile_key = ?,
                            updated_by = ?,
                            updated_at = NOW()
                      WHERE user_id = ?",
                    [$rawKey !== '' ? $rawKey : null, $actorLabel, $userId]
                );
            } else {
                // No existing row — seed dashboard_type with a safe placeholder;
                // admin refines it via the main governance form afterwards.
                DB::query(
                    "INSERT INTO user_dashboard_assignments
                       (user_id, dashboard_type, workspace_profile_key, updated_by, updated_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$userId, 'my_work', $rawKey !== '' ? $rawKey : null, $actorLabel]
                );
            }
            self::flash('ok', t('ops.access_control.workspace_profile.pin_saved'));
        } catch (\Throwable $e) {
            self::flash('err', 'Save failed: ' . $e->getMessage());
        }

        $redirectTo = trim((string)($input['redirect_to'] ?? ''));
        header('Location: ' . self::safeRedirectTarget($redirectTo !== '' ? $redirectTo : '/ops/access-control'));
        exit;
    }

    /**
     * @param array<string,mixed> $input
     */
    private static function isImmediateAssignedAppSave(array $input): bool
    {
        return ((int)($input['immediate_assigned_apps_only'] ?? 0)) === 1;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function hydrateImmediateAssignedAppSave(array $input): array
    {
        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Missing user id.');
        }

        $rows = UserDashboardAssignmentService::listAssignmentRows(['user_id' => $userId], true);
        $row = $rows[0] ?? null;
        if (!is_array($row)) {
            throw new \RuntimeException('User not found.');
        }

        $baseline = [
            'user_id' => $userId,
            'authority_role' => (string)($row['authority_role'] ?? ''),
            'dashboard_type' => (string)($row['dashboard_type'] ?? ''),
            'default_app' => (string)($row['default_app'] ?? ''),
            'default_landing_page' => (string)($row['default_landing_page'] ?? ''),
            'dashboard_mode' => (string)($row['dashboard_mode'] ?? 'auto'),
            'default_app_mode' => (string)($row['default_app_mode'] ?? 'auto'),
            'landing_mode' => (string)($row['landing_mode'] ?? 'auto'),
            'access_profiles_mode' => (string)($row['access_profiles_mode'] ?? 'auto'),
            'module_visibility_mode' => (string)($row['module_visibility_mode'] ?? 'auto'),
            'account_class' => (string)($row['account_class'] ?? ''),
            'operational_role' => (string)($row['operational_role'] ?? ($row['role'] ?? '')),
            'suite_role_templates' => (string)($row['suite_role_templates'] ?? ''),
            'module_permission_templates' => (string)($row['module_permission_templates'] ?? ''),
            'access_profiles' => (string)($row['access_profiles'] ?? ''),
            'permissions' => (string)($row['permissions'] ?? ''),
            'cross_functional_access' => (string)($row['cross_functional_access'] ?? ''),
            'me_dashboard_blocks' => (string)($row['me_dashboard_blocks'] ?? ''),
            'me_plugin_cards' => (string)($row['me_plugin_cards'] ?? ''),
            'scope_machine_ids' => (string)($row['scope_machine_ids'] ?? ''),
            'scope_part_ids' => (string)($row['scope_part_ids'] ?? ''),
            'scope_task_types' => (string)($row['scope_task_types'] ?? ''),
            'scope_department_code' => (string)($row['scope_department_code'] ?? ''),
            'scope_branch_code' => (string)($row['scope_branch_code'] ?? ''),
            'scope_ownership_role' => (string)($row['scope_ownership_role'] ?? ''),
            'view_access' => (string)($row['view_access'] ?? ''),
            'table_access' => (string)($row['table_access'] ?? ''),
            'chart_access' => (string)($row['chart_access'] ?? ''),
            'duty_codes' => (string)($row['duty_codes'] ?? ''),
            'duty_notes' => (string)($row['duty_notes'] ?? ''),
            'assigned_apps' => (string)($row['assigned_apps'] ?? ''),
        ];

        foreach ($baseline as $key => $value) {
            if (!array_key_exists($key, $input)) {
                $input[$key] = $value;
                continue;
            }

            if ($key === 'assigned_apps' || !is_string($input[$key])) {
                continue;
            }

            if (trim((string)$input[$key]) === '' && trim($value) !== '') {
                $input[$key] = $value;
            }
        }

        $input['assigned_apps'] = (string)($input['assigned_apps'] ?? '');
        return $input;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function hydrateAccessGovernanceSave(array $input): array
    {
        $userId = (int)($input['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Missing user id.');
        }

        $rows = UserDashboardAssignmentService::listAssignmentRows(['user_id' => $userId], true);
        $row = $rows[0] ?? null;
        if (!is_array($row)) {
            throw new \RuntimeException('User not found.');
        }

        $focus = strtolower(trim((string)($input['operational_focus'] ?? '')));
        if ($focus !== '' && trim((string)($input['operational_role'] ?? '')) === '') {
            $input['operational_role'] = $focus;
        }

        $baseline = [
            'user_id' => $userId,
            'workspace_profile_key' => (string)($row['workspace_profile_key'] ?? ''),
            'dashboard_type' => (string)($row['dashboard_type'] ?? ''),
            'default_app' => (string)($row['default_app'] ?? ''),
            'default_landing_page' => (string)($row['default_landing_page'] ?? ''),
            'dashboard_mode' => (string)($row['dashboard_mode'] ?? 'auto'),
            'default_app_mode' => (string)($row['default_app_mode'] ?? 'auto'),
            'landing_mode' => (string)($row['landing_mode'] ?? 'auto'),
            'access_profiles_mode' => (string)($row['access_profiles_mode'] ?? 'auto'),
            'module_visibility_mode' => (string)($row['module_visibility_mode'] ?? 'auto'),
            'account_class' => (string)($row['account_class'] ?? ''),
            'operational_role' => (string)($row['operational_role'] ?? ($row['role'] ?? '')),
            'suite_role_templates' => (string)($row['suite_role_templates'] ?? ''),
            'module_permission_templates' => (string)($row['module_permission_templates'] ?? ''),
            'access_profiles' => (string)($row['access_profiles'] ?? ''),
            'permissions' => (string)($row['permissions'] ?? ''),
            'cross_functional_access' => (string)($row['cross_functional_access'] ?? ''),
            'me_dashboard_blocks' => (string)($row['me_dashboard_blocks'] ?? ''),
            'me_plugin_cards' => (string)($row['me_plugin_cards'] ?? ''),
            'scope_machine_ids' => (string)($row['scope_machine_ids'] ?? ''),
            'scope_part_ids' => (string)($row['scope_part_ids'] ?? ''),
            'scope_task_types' => (string)($row['scope_task_types'] ?? ''),
            'scope_department_code' => (string)($row['scope_department_code'] ?? ''),
            'scope_branch_code' => (string)($row['scope_branch_code'] ?? ''),
            'scope_ownership_role' => (string)($row['scope_ownership_role'] ?? ''),
            'view_access' => (string)($row['view_access'] ?? ''),
            'table_access' => (string)($row['table_access'] ?? ''),
            'chart_access' => (string)($row['chart_access'] ?? ''),
            'duty_codes' => (string)($row['duty_codes'] ?? ''),
            'duty_notes' => (string)($row['duty_notes'] ?? ''),
            'assigned_apps' => (string)($row['assigned_apps'] ?? ''),
        ];

        foreach ($baseline as $key => $value) {
            if (!array_key_exists($key, $input)) {
                $input[$key] = $value;
                continue;
            }

            if (!is_string($input[$key])) {
                continue;
            }

            if (trim((string)$input[$key]) === '' && trim($value) !== '') {
                $input[$key] = $value;
            }
        }

        $authorityRole = strtolower(trim((string)($input['authority_role'] ?? '')));
        if ($authorityRole === '') {
            $authorityRole = strtolower(trim((string)($row['authority_role'] ?? '')));
        }
        if ($authorityRole !== '') {
            $input['authority_role'] = $authorityRole;
        }

        $workspaceProfileKey = strtolower(trim((string)($input['workspace_profile_key'] ?? '')));
        if ($workspaceProfileKey !== '' && !preg_match('/^[a-z0-9_]+$/', $workspaceProfileKey)) {
            throw new \InvalidArgumentException('Invalid workspace profile key format.');
        }
        $input['workspace_profile_key'] = $workspaceProfileKey;

        return $input;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function captureAccessAttemptInput(array $input): array
    {
        $allowed = [
            'user_id',
            'authority_role',
            'governance_scope',
            'interaction_profile',
            'operational_focus',
            'operational_role',
            'account_class',
            'default_app',
            'assigned_apps',
            'access_profiles',
            'readonly_profiles',
            'display_surfaces',
            'module_visibility',
            'module_access_hints',
            'view_access',
            'table_access',
            'chart_access',
            'me_plugin_cards',
            'permissions',
            'dashboard_type',
            'default_landing_page',
            'dashboard_mode',
            'default_app_mode',
            'landing_mode',
            'access_profiles_mode',
            'module_visibility_mode',
            'workspace_profile_key',
        ];

        $captured = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $input)) {
                continue;
            }
            $value = $input[$key];
            if (is_array($value)) {
                $captured[$key] = implode(',', array_map(static fn($v): string => trim((string)$v), $value));
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $captured[$key] = (string)$value;
            }
        }

        return $captured;
    }

    private static function normalizeDetailTab(string $tab): string
    {
        $normalized = strtolower(trim($tab));
        // Backward-compat: legacy 'access' tab was retired; redirect to access2.
        if ($normalized === 'access') {
            return 'access2';
        }
        return in_array($normalized, self::DETAIL_TABS, true) ? $normalized : 'access2';
    }

    public static function sendAdminCommunication(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/access-control');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            self::flash('err', 'Not authorized to send admin communications.');
            header('Location: /ops/dashboard');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $redirectTo = trim((string)($input['redirect_to'] ?? '/ops/access-control'));
            if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
                $redirectTo = '/ops/access-control';
            }

            $targetType = strtolower(trim((string)($input['target_type'] ?? 'all')));
            $targetUserId = $targetType === 'user' ? (int)($input['target_user_id'] ?? 0) : 0;
            $targetRole = $targetType === 'role' ? (string)($input['target_role'] ?? '') : '';

            $count = NotificationService::sendAdminCommunication(
                (string)($input['message_kind'] ?? 'message'),
                (string)($input['title'] ?? ''),
                (string)($input['message'] ?? ''),
                (string)($input['severity'] ?? NotificationService::SEVERITY_INFO),
                $targetUserId,
                $targetRole
            );

            if ($count <= 0) {
                self::flash('err', 'Message not sent. Check title/message and target.');
            } else {
                self::flash('ok', 'Message dispatched to ' . $count . ' target(s).');
            }
            header('Location: ' . $redirectTo);
            exit;
        } catch (\Throwable $e) {
            self::flash('err', 'Send failed: ' . $e->getMessage());
        }

        $redirectTo = trim((string)($input['redirect_to'] ?? '/ops/access-control'));
        if ($redirectTo === '' || !str_starts_with($redirectTo, '/')) {
            $redirectTo = '/ops/access-control';
        }
        header('Location: ' . $redirectTo);
        exit;
    }

    public static function renderNavigationTree(View $view, Container $c): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/navigation-tree');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();
        $ctx = self::currentUserContext();
        if (!self::canManageAssignments($ctx)) {
            header('Location: /ops/dashboard');
            exit;
        }

        $view->render('Base::ops/navigation_tree.php', [
            'pageTitle' => 'System Navigation Tree',
            'tree' => self::navigationTreeData($c),
            'dashboardType' => (string)($ctx['dashboard_type'] ?? ''),
            'notificationCount' => NotificationService::unreadCountForUser(Auth::user()),
        ]);
    }

    private static function canAccessDashboardType(array $ctx, string $targetType): bool
    {
        $targetType = self::canonicalDashboardType($targetType);
        $authorityRole = (string)($ctx['authority_role'] ?? 'app_user');
        $assignedType = self::canonicalDashboardType((string)($ctx['dashboard_type'] ?? 'my_work'));
        if ($targetType === $assignedType) {
            return true;
        }

        if ($authorityRole === 'platform_admin') {
            return in_array($targetType, ['platform_admin', 'app_admin'], true);
        }

        if ($authorityRole === 'app_admin') {
            return $targetType === 'app_admin';
        }

        return false;
    }

    private static function canonicalDashboardType(string $dashboardType): string
    {
        return match (strtolower(trim($dashboardType))) {
            'admin', 'itadmin', 'sysadmin', 'platform_admin' => 'platform_admin',
            'accountadmin', 'app_admin' => 'app_admin',
            'operator', 'my_work' => 'my_work',
            'production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader' => strtolower(trim($dashboardType)),
            default => strtolower(trim($dashboardType)),
        };
    }

    private static function canManageAssignments(array $ctx): bool
    {
        if ((string)($ctx['authority_role'] ?? 'app_user') !== 'platform_admin') {
            return false;
        }
        // Double-check the acl.manage permission through the full AclPolicy chain so that
        // any deny override set in the ACL matrix (now respected after fix #3) is honoured
        // at this layer too.  Without this, denying acl.manage for platform_admin via the
        // matrix would have no effect on the assignment management UI.
        return \App\Core\AclPolicy::can('acl.manage', \App\Core\Auth::user());
    }

    private static function currentUserContext(): array
    {
        $user = Auth::user();
        return UserDashboardAssignmentService::resolveUserContext($user);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    private static function buildUserControlRows(array $filters = []): array
    {
        $rows = UserDashboardAssignmentService::listAssignmentRows([
            'search' => (string)($filters['search'] ?? ''),
            'status' => (string)($filters['status'] ?? 'all'),
            'user_id' => (int)($filters['user_id'] ?? 0),
        ], false);

        $userIds = array_values(array_filter(array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows)));
        $eventsByUser = self::securityEventsByUser($userIds, 10);

        $filtered = [];
        foreach ($rows as $row) {
            $row = self::decorateUserControlRow($row, $eventsByUser[(int)($row['id'] ?? 0)] ?? []);

            $accountTypeFilter = strtolower(trim((string)($filters['authority_role'] ?? 'all')));
            if ($accountTypeFilter !== '' && $accountTypeFilter !== 'all' && strtolower((string)($row['authority_role'] ?? '')) !== $accountTypeFilter) {
                continue;
            }

            $verificationFilter = strtolower(trim((string)($filters['verification_status'] ?? 'all')));
            if ($verificationFilter !== '' && $verificationFilter !== 'all' && strtolower((string)($row['verification_status_key'] ?? '')) !== $verificationFilter) {
                continue;
            }

            $securityFilter = strtolower(trim((string)($filters['security_status'] ?? 'all')));
            if ($securityFilter !== '' && $securityFilter !== 'all' && strtolower((string)($row['security_status_key'] ?? '')) !== $securityFilter) {
                continue;
            }

            $landingFilter = strtolower(trim((string)($filters['landing_type'] ?? 'all')));
            if ($landingFilter !== '' && $landingFilter !== 'all' && strtolower((string)($row['landing_mode'] ?? 'auto')) !== $landingFilter) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,array<string,mixed>> $events
     * @return array<string,mixed>
     */
    private static function decorateUserControlRow(array $row, array $events): array
    {
        $verification = self::verificationStatusForRow($row);
        $security = self::securityStatusForRow($row, $events);
        $row['display_label'] = trim((string)($row['display_name'] ?? '')) !== ''
            ? (string)$row['display_name']
            : (string)($row['email'] ?? ('User #' . (int)($row['id'] ?? 0)));
        $row['account_type_label'] = self::accountTypeLabel((string)($row['authority_role'] ?? ''));
        $row['dashboard_type_label'] = self::dashboardTypeLabel((string)($row['dashboard_type'] ?? ''));
        $row['landing_summary'] = self::landingSummaryForRow($row);
        $row['landing_destination'] = self::landingDestinationForRow($row);
        $row['status_label'] = self::accountStatusLabel((string)($row['account_status'] ?? 'active'));
        $row['verification_status_key'] = $verification['key'];
        $row['verification_status_label'] = $verification['label'];
        $row['verification_status_tone'] = $verification['tone'];
        $row['verification_status_note'] = $verification['note'];
        $row['security_status_key'] = $security['key'];
        $row['security_status_label'] = $security['label'];
        $row['security_status_tone'] = $security['tone'];
        $row['security_status_note'] = $security['note'];
        $row['assigned_app_list'] = self::csvList((string)($row['assigned_apps'] ?? ''));
        $row['security_events'] = $events;
        $row['last_recovery_action'] = self::lastRecoveryActionSummary($events);
        return $row;
    }

    /**
     * @param array<string,mixed> $row
     * @return array{key:string,label:string,tone:string,note:string}
     */
    private static function verificationStatusForRow(array $row): array
    {
        $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
        $verificationStatus = strtolower(trim((string)($row['verification_status'] ?? 'ready')));
        if ($verificationStatus === 'invite_pending') {
            return [
                'key' => 'invite_pending',
                'label' => self::tr('identity.verification.invite_pending.label', 'Invite Pending'),
                'tone' => 'warn',
                'note' => self::tr('identity.verification.invite_pending.note', 'A setup link was issued and is waiting for the user to complete account setup.'),
            ];
        }
        if ($verificationStatus === 'setup_pending') {
            return [
                'key' => 'setup_pending',
                'label' => self::tr('identity.verification.setup_pending.label', 'Setup Pending'),
                'tone' => 'warn',
                'note' => self::tr('identity.verification.setup_pending.note', 'Account is waiting for password setup before it becomes ready.'),
            ];
        }
        if ($status === 'pending') {
            return [
                'key' => 'setup_pending',
                'label' => self::tr('identity.verification.setup_pending.label', 'Setup Pending'),
                'tone' => 'warn',
                'note' => self::tr('identity.verification.setup_pending.note', 'Account is waiting for initial setup or first verified sign-in.'),
            ];
        }

        if ($status === 'disabled') {
            return [
                'key' => 'review_needed',
                'label' => self::tr('identity.verification.review_needed.label', 'Review Needed'),
                'tone' => 'muted',
                'note' => self::tr('identity.verification.review_needed.note', 'Disabled accounts need admin review before they return to a ready state.'),
            ];
        }

        return [
            'key' => 'ready',
            'label' => self::tr('identity.verification.ready.label', 'Ready'),
            'tone' => 'ok',
            'note' => self::tr('identity.verification.ready.note', 'User can enter through /me under the assigned experience.'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @param array<int,array<string,mixed>> $events
     * @return array{key:string,label:string,tone:string,note:string}
     */
    private static function securityStatusForRow(array $row, array $events): array
    {
        $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
        $securityStatus = strtolower(trim((string)($row['security_status'] ?? 'standard')));
        if ($status === 'disabled') {
            return [
                'key' => 'suspended',
                'label' => self::tr('identity.security.suspended.label', 'Suspended'),
                'tone' => 'muted',
                'note' => self::tr('identity.security.suspended.note', 'Lifecycle state is disabled, so recovery actions should be reviewed carefully.'),
            ];
        }

        if ($securityStatus === 'password_setup_pending') {
            return [
                'key' => 'password_setup_pending',
                'label' => self::tr('identity.security.password_setup_pending.label', 'Password Setup Pending'),
                'tone' => 'warn',
                'note' => self::tr('identity.security.password_setup_pending.note', 'The user still needs to complete the first password setup flow.'),
            ];
        }
        if ($securityStatus === 'security_attention') {
            return [
                'key' => 'security_attention',
                'label' => self::tr('identity.security.security_attention.label', 'Security Attention Needed'),
                'tone' => 'danger',
                'note' => self::tr('identity.security.security_attention.note', 'A security condition has been flagged for administrator follow-up.'),
            ];
        }

        $hasCompletedReset = false;
        foreach ($events as $event) {
            $et = strtolower(trim((string)($event['event_type'] ?? '')));
            $oc = strtolower(trim((string)($event['outcome'] ?? '')));
            if ($et === 'password_reset_completed' && $oc === 'success') {
                $hasCompletedReset = true;
                break;
            }
        }

        if ($hasCompletedReset) {
            return [
                'key' => 'recovery_recent',
                'label' => self::tr('identity.security.recovery_recent.label', 'Recent Recovery Activity'),
                'tone' => 'info',
                'note' => self::tr('identity.security.recovery_recent.note', 'A password reset was recently completed for this user.'),
            ];
        }

        foreach ($events as $event) {
            $eventType = strtolower(trim((string)($event['event_type'] ?? '')));
            $outcome = strtolower(trim((string)($event['outcome'] ?? '')));
            if ($outcome === 'failed' || $outcome === 'invalid_token') {
                return [
                    'key' => 'recovery_attention',
                    'label' => self::tr('identity.security.recovery_attention.label', 'Recovery Attention Needed'),
                    'tone' => 'danger',
                    'note' => self::tr('identity.security.recovery_attention.note', 'Recent recovery activity had a failure or invalid token outcome.'),
                ];
            }
            if (in_array($eventType, ['password_reset_requested', 'password_reset_email'], true) && in_array($outcome, ['accepted', 'sent'], true)) {
                return [
                    'key' => 'recovery_in_progress',
                    'label' => self::tr('identity.security.recovery_in_progress.label', 'Recovery In Progress'),
                    'tone' => 'warn',
                    'note' => self::tr('identity.security.recovery_in_progress.note', 'A recovery or password reset flow was recently triggered for this user.'),
                ];
            }
        }

        if (!empty($row['twofa_enabled'])) {
            return [
                'key' => 'twofa_enabled',
                'label' => self::tr('identity.security.twofa_enabled.label', '2FA Enabled'),
                'tone' => 'ok',
                'note' => self::tr('identity.security.twofa_enabled.note', 'Secondary verification is enabled for sign-in.'),
            ];
        }

        return [
            'key' => 'standard',
            'label' => self::tr('identity.security.standard.label', 'Standard'),
            'tone' => 'info',
            'note' => self::tr('identity.security.standard.note', 'Password-only sign-in with no active recovery warnings.'),
        ];
    }

    private static function accountTypeLabel(string $authorityRole): string
    {
        return match (strtolower(trim($authorityRole))) {
            'platform_admin' => 'Platform Admin',
            'app_admin' => 'App Admin',
            default => 'App User',
        };
    }

    private static function dashboardTypeLabel(string $dashboardType): string
    {
        return match (strtolower(trim($dashboardType))) {
            'platform_admin' => 'Platform Admin',
            'app_admin' => 'App Admin',
            'production_leader' => 'Production Leader',
            'assembly_leader' => 'Assembly Leader',
            'qc_leader' => 'QC Leader',
            'dispatch_leader' => 'Dispatch Leader',
            'my_work', 'operator' => 'Home',
            default => trim(str_replace('_', ' ', ucwords($dashboardType, '_'))),
        };
    }

    private static function accountStatusLabel(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => self::tr('identity.account_status.pending', 'Pending'),
            'disabled' => self::tr('identity.account_status.disabled', 'Disabled'),
            default => self::tr('identity.account_status.active', 'Active'),
        };
    }

    private static function tr(string $key, string $fallback): string
    {
        if (!function_exists('t')) {
            return $fallback;
        }

        $translated = (string)t($key);
        if ($translated === '' || $translated === $key) {
            return $fallback;
        }

        return $translated;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function landingDestinationForRow(array $row): string
    {
        $landingMode = strtolower(trim((string)($row['landing_mode'] ?? 'auto')));
        if ($landingMode === 'manual') {
            $manual = trim((string)($row['default_landing_page'] ?? ''));
            return $manual !== '' ? $manual : '/';
        }

        return '/';
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function landingSummaryForRow(array $row): string
    {
        $landingMode = strtolower(trim((string)($row['landing_mode'] ?? 'auto')));
        $landingLabel = $landingMode === 'manual' ? 'Manual' : 'Default /me';
        return $landingLabel . ' -> ' . self::landingDestinationForRow($row) . ' · ' . self::dashboardTypeLabel((string)($row['dashboard_type'] ?? ''));
    }

    /**
     * @param string $csv
     * @return array<int,string>
     */
    private static function csvList(string $csv): array
    {
        $parts = preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $value = trim($part);
            if ($value === '') {
                continue;
            }
            $out[] = $value;
        }
        return array_values(array_unique($out));
    }

    /**
     * @param array<int,int> $userIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function securityEventsByUser(array $userIds, int $limitPerUser = 8): array
    {
        $userIds = array_values(array_filter(array_map('intval', $userIds)));
        if ($userIds === []) {
            return [];
        }

        $holders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = DB::fetchAll(
            "SELECT user_id, event_type, outcome, created_at, metadata_json
             FROM identity_security_events
             WHERE user_id IN ({$holders})
             ORDER BY created_at DESC, id DESC",
            $userIds
        );

        $grouped = [];
        foreach ($rows as $row) {
            $userId = (int)($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $bucket = $grouped[$userId] ?? [];
            if (count($bucket) >= $limitPerUser) {
                continue;
            }
            $bucket[] = [
                'event_type' => (string)($row['event_type'] ?? ''),
                'outcome' => (string)($row['outcome'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'metadata_json' => (string)($row['metadata_json'] ?? ''),
            ];
            $grouped[$userId] = $bucket;
        }

        return $grouped;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function userSecurityEvents(int $userId, int $limit = 12): array
    {
        if ($userId <= 0) {
            return [];
        }

        return DB::fetchAll(
            'SELECT event_type, outcome, created_at, metadata_json
             FROM identity_security_events
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . max(1, $limit),
            [$userId]
        );
    }

    /**
     * @param array<int,array<string,mixed>> $events
     */
    private static function lastRecoveryActionSummary(array $events): string
    {
        foreach ($events as $event) {
            $eventType = strtolower(trim((string)($event['event_type'] ?? '')));
            if (!in_array($eventType, ['password_reset_requested', 'password_reset_email', 'password_reset_completed', 'password_changed_notice'], true)) {
                continue;
            }
            $label = match ($eventType) {
                'password_reset_requested' => 'Reset requested',
                'password_reset_email' => 'Recovery email',
                'password_reset_completed' => 'Password reset',
                'password_changed_notice' => 'Password changed notice',
                default => 'Recovery action',
            };
            $outcome = trim((string)($event['outcome'] ?? ''));
            $at = trim((string)($event['created_at'] ?? ''));
            return trim($label . ($outcome !== '' ? ' · ' . ucfirst(str_replace('_', ' ', $outcome)) : '') . ($at !== '' ? ' · ' . $at : ''));
        }

        return 'No recovery activity recorded';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function userControlSummary(array $rows): array
    {
        $summary = [
            'total' => count($rows),
            'active' => 0,
            'pending' => 0,
            'disabled' => 0,
            'verification_pending' => 0,
            'security_attention' => 0,
            'queues' => [
                'pending_setup' => [],
                'verification_review' => [],
                'security_attention' => [],
                'disabled_review' => [],
            ],
        ];

        foreach ($rows as $row) {
            $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
            $verificationKey = strtolower(trim((string)($row['verification_status_key'] ?? 'ready')));
            $securityKey = strtolower(trim((string)($row['security_status_key'] ?? 'standard')));

            if (isset($summary[$status])) {
                $summary[$status]++;
            } elseif ($status === 'active') {
                $summary['active']++;
            }

            if ($verificationKey !== 'ready') {
                $summary['verification_pending']++;
            }

            if (in_array($securityKey, ['recovery_attention', 'recovery_in_progress', 'suspended'], true)) {
                $summary['security_attention']++;
            }
            // recovery_recent is a resolved state - excluded from attention counts

            if ($verificationKey === 'setup_pending' && count($summary['queues']['pending_setup']) < 5) {
                $summary['queues']['pending_setup'][] = $row;
            }
            if ($verificationKey !== 'ready' && count($summary['queues']['verification_review']) < 5) {
                $summary['queues']['verification_review'][] = $row;
            }
            if (in_array($securityKey, ['recovery_attention', 'recovery_in_progress'], true) && count($summary['queues']['security_attention']) < 5) {
                $summary['queues']['security_attention'][] = $row;
            }
            if ($status === 'disabled' && count($summary['queues']['disabled_review']) < 5) {
                $summary['queues']['disabled_review'][] = $row;
            }
        }

        return $summary;
    }

    private static function safeRedirectTarget(string $default): string
    {
        $requested = trim((string)($_POST['redirect_to'] ?? $_GET['redirect_to'] ?? ''));
        if ($requested !== '' && str_starts_with($requested, '/') && !str_starts_with($requested, '//')) {
            return $requested;
        }

        return $default;
    }

    private static function appendQueryParam(string $url, string $key, string $value): string
    {
        $fragment = '';
        $base = $url;
        $fragmentPos = strpos($url, '#');
        if ($fragmentPos !== false) {
            $fragment = substr($url, $fragmentPos);
            $base = substr($url, 0, $fragmentPos);
        }

        $queryPos = strpos($base, '?');
        $path = $queryPos === false ? $base : substr($base, 0, $queryPos);
        $query = $queryPos === false ? '' : substr($base, $queryPos + 1);
        parse_str($query, $params);
        $params[$key] = $value;

        $rebuilt = $path;
        $queryString = http_build_query($params);
        if ($queryString !== '') {
            $rebuilt .= '?' . $queryString;
        }

        return $rebuilt . $fragment;
    }

    private static function platformAdminDashboard(): array
    {
        $dashboard = self::itAdminDashboard();
        $dashboard['title'] = 'Platform Admin Dashboard';
        return $dashboard;
    }

    private static function appAdminDashboard(array $ctx): array
    {
        $assignedApps = AppAdminDashboardScopeService::assignedApps($ctx);
        if (!AppAdminDashboardScopeService::allowsApp($ctx, 'manufacturing')) {
            return self::genericAppAdminDashboard($assignedApps);
        }

        $dashboard = self::accountAdminDashboard();
        $dashboard['title'] = 'Manufacturing App Admin Dashboard';
        array_unshift($dashboard['cards'], [
            'key' => 'assigned_app_count',
            'label' => 'Assigned Applications',
            'value' => count($assignedApps),
            'tone' => 'info',
        ]);
        array_unshift($dashboard['sections'], [
            'key' => 'assigned_application_scope',
            'title' => 'Assigned Application Scope',
            'items' => [[
                'key' => 'assigned_applications',
                'label' => 'Applications',
                'value' => implode(', ', $assignedApps),
            ]],
        ]);
        return $dashboard;
    }

    /** @param array<int,string> $assignedApps @return array<string,mixed> */
    private static function genericAppAdminDashboard(array $assignedApps): array
    {
        return [
            'title' => 'App Admin Dashboard',
            'subtitle' => 'Assigned application administration and compatibility status.',
            'cards' => [[
                'key' => 'assigned_app_count',
                'label' => 'Assigned Applications',
                'value' => count($assignedApps),
                'tone' => 'info',
            ]],
            'sections' => [[
                'key' => 'assigned_application_scope',
                'title' => 'Assigned Application Scope',
                'items' => [[
                    'key' => 'assigned_applications',
                    'label' => 'Applications',
                    'value' => $assignedApps !== [] ? implode(', ', $assignedApps) : 'No applications assigned',
                ]],
            ]],
            'quick_links' => [],
            'placeholders' => [
                'No assigned application has contributed a compatibility dashboard payload.',
            ],
        ];
    }

    private static function productionLeaderDashboard(array $ctx): array
    {
        $scope = (array)($ctx['scope'] ?? []);
        $assignedMachines = count((array)($scope['machine_ids'] ?? []));
        $assignedParts = count((array)($scope['part_ids'] ?? []));
        $assignedTasks = count((array)($scope['task_types'] ?? []));

        $filters = self::scopeFilters($scope, 'production_plans', ['product_id' => 'part_ids', 'machine_id' => 'machine_ids']);
        $openQueue = self::countFrom('production_plans', "LOWER(COALESCE(status,'')) NOT IN ('completed','closed')" . $filters['sql'], $filters['params']);
        $delays = self::countFrom('production_plans', "COALESCE(plan_date,CURDATE()) < CURDATE() AND LOWER(COALESCE(status,'')) NOT IN ('completed','closed')" . $filters['sql'], $filters['params']);
        $plannedToday = self::sumFrom('production_plans', 'planned_qty', 'COALESCE(plan_date,CURDATE())=CURDATE()' . $filters['sql'], $filters['params']);

        $filtersEntries = self::scopeFilters($scope, 'production_entries', ['product_id' => 'part_ids', 'machine_id' => 'machine_ids']);
        $producedToday = self::sumFrom('production_entries', 'good_qty', 'COALESCE(production_date,CURDATE())=CURDATE()' . $filtersEntries['sql'], $filtersEntries['params']);
        $rejectedToday = self::sumFrom('production_entries', 'rejected_qty', 'COALESCE(production_date,CURDATE())=CURDATE()' . $filtersEntries['sql'], $filtersEntries['params']);
        $shortages = self::countFrom('daily_orders', "COALESCE(shortage_qty,0) > 0" . self::partScopeSql($scope, 'daily_orders'), self::partScopeParams($scope));
        $planVsProducedGap = round($plannedToday - $producedToday, 2);

        return [
            'title' => 'Production Workspace',
            'subtitle' => 'Assigned production scope: queues, delays, output, and shortages.',
            'cards' => [
                ['key' => 'assigned_machines', 'label' => 'Assigned Machines', 'value' => $assignedMachines, 'tone' => 'info'],
                ['key' => 'assigned_parts', 'label' => 'Assigned Parts', 'value' => $assignedParts, 'tone' => 'info'],
                ['key' => 'assigned_queue_rows', 'label' => 'Assigned Queue Rows', 'value' => $openQueue, 'tone' => 'warn'],
                ['key' => 'production_delays', 'label' => 'Production Delays', 'value' => $delays, 'tone' => $delays > 0 ? 'danger' : 'ok'],
                ['key' => 'planned_today', 'label' => 'Planned Today', 'value' => number_format($plannedToday, 2, '.', ','), 'tone' => 'warn'],
                ['key' => 'produced_today', 'label' => 'Produced Today', 'value' => number_format($producedToday, 2, '.', ','), 'tone' => 'ok'],
                ['key' => 'plan_production_gap', 'label' => 'Plan-Production Gap', 'value' => number_format($planVsProducedGap, 2, '.', ','), 'tone' => $planVsProducedGap > 0 ? 'warn' : 'ok'],
                ['key' => 'rejected_today', 'label' => 'Rejected Today', 'value' => number_format($rejectedToday, 2, '.', ','), 'tone' => $rejectedToday > 0 ? 'danger' : 'ok'],
                ['key' => 'shortage_affected_orders', 'label' => 'Shortage-Affected Orders', 'value' => $shortages, 'tone' => $shortages > 0 ? 'danger' : 'ok'],
            ],
            'sections' => [
                [
                    'title' => 'Assigned Workload Scope',
                    'items' => [
                        ['label' => 'Task Types', 'value' => $assignedTasks > 0 ? implode(', ', (array)$scope['task_types']) : '-'],
                        ['label' => 'Department', 'value' => (string)($scope['department_code'] ?? '-')],
                        ['label' => 'Branch', 'value' => (string)($scope['branch_code'] ?? '-')],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'Production Queue', 'url' => '/manufacturing/production-queue'],
                ['label' => 'Production Plans', 'url' => '/apps/manufacturing/production-plans'],
                ['label' => 'Daily Orders', 'url' => '/apps/manufacturing/daily-orders'],
                ['label' => 'Coverage Analytics', 'url' => '/apps/manufacturing/coverage'],
            ],
            'placeholders' => [],
        ];
    }

    private static function assemblyLeaderDashboard(array $ctx): array
    {
        $scope = (array)($ctx['scope'] ?? []);
        $assemblyFilters = self::scopeFilters($scope, 'mfg_part_demands', ['product_id' => 'part_ids']);
        $openAssembly = self::countFrom('mfg_part_demands', "demand_type='assembly' AND status <> 'approved'" . $assemblyFilters['sql'], $assemblyFilters['params']);
        $readyAssembly = self::countScopedStageRows($scope, "r.stage='assembly' AND (r.override_status='released' OR COALESCE(r.released_qty,0)>0)");
        $blockedByProduction = self::countScopedStageRows($scope, "r.stage='production' AND r.override_status='blocked'");
        $readyForQc = self::countScopedStageRows($scope, "r.stage='qc' AND COALESCE(r.override_status,'') NOT IN ('blocked','released') AND COALESCE(r.released_qty,0)=0");

        $entryFilters = self::scopeFilters($scope, 'mfg_assembly_entries', ['product_id' => 'part_ids']);
        $completedQty = self::sumFrom('mfg_assembly_entries', 'completed_qty', "LOWER(COALESCE(status,'')) IN ('completed','approved')" . $entryFilters['sql'], $entryFilters['params']);
        $pendingQty = self::sumFrom('mfg_assembly_entries', 'GREATEST(planned_qty-completed_qty,0)', "LOWER(COALESCE(status,'')) NOT IN ('cancelled','approved')" . $entryFilters['sql'], $entryFilters['params']);

        return [
            'title' => 'Assembly Dashboard',
            'subtitle' => 'Assigned assembly scope: production-released parts, completion pressure, and QC handoff readiness.',
            'cards' => [
                ['key' => 'assembly_demand_workload', 'label' => 'Assembly Demand Workload', 'value' => $openAssembly, 'tone' => 'warn'],
                ['key' => 'assembly_ready_releases', 'label' => 'Assembly-Ready Releases', 'value' => $readyAssembly, 'tone' => 'info'],
                ['key' => 'blocked_upstream_production', 'label' => 'Blocked by Upstream Production', 'value' => $blockedByProduction, 'tone' => $blockedByProduction > 0 ? 'danger' : 'ok'],
                ['key' => 'pending_qc_handoff', 'label' => 'Pending QC Handoff', 'value' => $readyForQc, 'tone' => 'info'],
                ['key' => 'completed_assembly_qty', 'label' => 'Completed Assembly Qty', 'value' => number_format($completedQty, 2, '.', ','), 'tone' => 'ok'],
                ['key' => 'pending_assembly_qty', 'label' => 'Pending Assembly Qty', 'value' => number_format($pendingQty, 2, '.', ','), 'tone' => 'warn'],
            ],
            'sections' => [
                [
                    'title' => 'Scope',
                    'items' => [
                        ['label' => 'Assigned Parts', 'value' => self::scopeCountLabel((array)($scope['part_ids'] ?? []))],
                        ['label' => 'Assigned Task Types', 'value' => self::scopeTextLabel((array)($scope['task_types'] ?? []))],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'Assembly Queue', 'url' => '/manufacturing/assembly-queue'],
                ['label' => 'Assembly Plans', 'url' => '/manufacturing/assembly-plans'],
                ['label' => 'Stage Board', 'url' => '/manufacturing/stage-board'],
                ['label' => 'QC Entries', 'url' => '/qc-entries'],
            ],
            'placeholders' => [],
        ];
    }

    private static function qcLeaderDashboard(array $ctx): array
    {
        $scope = (array)($ctx['scope'] ?? []);
        $qcFilters = self::scopeFilters($scope, 'mfg_part_demands', ['product_id' => 'part_ids']);
        $qcWork = self::countFrom('mfg_part_demands', "demand_type='qc' AND status <> 'approved'" . $qcFilters['sql'], $qcFilters['params']);
        $qcFailed = self::sumFrom('qc_entries', 'GREATEST(checked_qty-pass_qty,0)', '1=1' . self::partScopeSql($scope, 'qc_entries'), self::partScopeParams($scope));
        $qcPending = self::countScopedStageRows($scope, "r.stage='qc' AND COALESCE(r.override_status,'') <> 'released'");
        $releaseCandidates = self::countScopedStageRows($scope, "r.stage='qc' AND (r.override_status='released' OR COALESCE(r.released_qty,0)>0)");
        $blockedRows = self::countScopedStageRows($scope, "r.stage='qc' AND r.override_status='blocked'");

        return [
            'title' => 'QC Workspace',
            'subtitle' => 'QC-required workload, failure pressure, and release-to-packaging flow.',
            'cards' => [
                ['key' => 'qc_required_workload', 'label' => 'QC Required Workload', 'value' => $qcWork, 'tone' => 'warn'],
                ['key' => 'failed_qty', 'label' => 'Failed Qty', 'value' => number_format($qcFailed, 2, '.', ','), 'tone' => $qcFailed > 0 ? 'danger' : 'ok'],
                ['key' => 'pending_qc_checks', 'label' => 'Pending QC Checks', 'value' => $qcPending, 'tone' => 'warn'],
                ['key' => 'packaging_release_candidates', 'label' => 'Packaging Release Candidates', 'value' => $releaseCandidates, 'tone' => 'info'],
                ['key' => 'qc_blocked_exceptions', 'label' => 'QC Blocked Exceptions', 'value' => $blockedRows, 'tone' => $blockedRows > 0 ? 'danger' : 'ok'],
            ],
            'sections' => [
                [
                    'title' => 'Scope',
                    'items' => [
                        ['label' => 'Assigned Parts', 'value' => self::scopeCountLabel((array)($scope['part_ids'] ?? []))],
                        ['label' => 'QC Lanes/Tasks', 'value' => self::scopeTextLabel((array)($scope['task_types'] ?? []))],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'QC Queue', 'url' => '/manufacturing/qc-queue'],
                ['label' => 'QC Plans', 'url' => '/qc-plans'],
                ['label' => 'QC Entries', 'url' => '/qc-entries'],
                ['label' => 'Stage Board', 'url' => '/manufacturing/stage-board'],
                ['label' => 'Order Processing (Packaging)', 'url' => '/manufacturing/dispatch-ops'],
            ],
            'placeholders' => [],
        ];
    }

    private static function dispatchLeaderDashboard(array $ctx): array
    {
        $scope = (array)($ctx['scope'] ?? []);
        $filters = self::scopeFilters($scope, 'dispatch_entries', ['product_id' => 'part_ids']);
        $readyToPack = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='ready'" . $filters['sql'], $filters['params']);
        $packedPending = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='prepared'" . $filters['sql'], $filters['params']);
        $casesPalletPending = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft')) IN ('ready','prepared') AND (COALESCE(cases_count,0)=0 OR COALESCE(pallets_count,0)=0)" . $filters['sql'], $filters['params']);
        $blockedDispatches = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='draft'" . $filters['sql'], $filters['params']);
        $completedToday = self::sumFrom('dispatch_entries', 'dispatchable_qty', "COALESCE(dispatch_date,CURDATE())=CURDATE() AND LOWER(COALESCE(completion_status,'draft'))='completed'" . $filters['sql'], $filters['params']);
        $pendingToday = self::sumFrom('dispatch_entries', 'dispatchable_qty', "COALESCE(dispatch_date,CURDATE())=CURDATE() AND LOWER(COALESCE(completion_status,'draft')) IN ('draft','ready','prepared')" . $filters['sql'], $filters['params']);

        return [
            'title' => 'Dispatch Workspace',
            'subtitle' => 'Assigned dispatch workload: packaging-released items, packing progress, and final outbound execution.',
            'cards' => [
                ['key' => 'ready_to_pack', 'label' => 'Ready-to-Pack', 'value' => $readyToPack, 'tone' => 'info'],
                ['key' => 'packed_pending_complete', 'label' => 'Packed/Pending Complete', 'value' => $packedPending, 'tone' => 'warn'],
                ['key' => 'cases_pallet_updates_pending', 'label' => 'Cases/Pallet Updates Pending', 'value' => $casesPalletPending, 'tone' => $casesPalletPending > 0 ? 'danger' : 'ok'],
                ['key' => 'blocked_dispatches', 'label' => 'Blocked Dispatches', 'value' => $blockedDispatches, 'tone' => $blockedDispatches > 0 ? 'danger' : 'ok'],
                ['key' => 'completed_qty_today', 'label' => 'Completed Qty Today', 'value' => number_format($completedToday, 2, '.', ','), 'tone' => 'ok'],
                ['key' => 'pending_qty_today', 'label' => 'Pending Qty Today', 'value' => number_format($pendingToday, 2, '.', ','), 'tone' => 'warn'],
            ],
            'sections' => [
                [
                    'title' => 'Scope',
                    'items' => [
                        ['label' => 'Assigned Parts', 'value' => self::scopeCountLabel((array)($scope['part_ids'] ?? []))],
                        ['label' => 'Assigned Task Types', 'value' => self::scopeTextLabel((array)($scope['task_types'] ?? []))],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'Dispatch Workbench', 'url' => '/manufacturing/dispatch-ops'],
                ['label' => 'Order Preparation Form', 'url' => '/manufacturing/dispatch-ops/preparation'],
                ['label' => 'Dispatch Entries', 'url' => '/dispatch-entries'],
                ['label' => 'Assembly Queue', 'url' => '/manufacturing/assembly-queue'],
            ],
            'placeholders' => [],
        ];
    }

    private static function adminDashboard(): array
    {
        $today = date('Y-m-d');
        $openApprovals = self::countFrom('mfg_part_demands', "status <> 'approved'");
        $prodAwaiting = self::countFrom('mfg_part_demands', "demand_type='production' AND status <> 'approved'");
        $qcAwaiting = self::countFrom('mfg_part_demands', "demand_type='qc' AND status <> 'approved'");
        $asmAwaiting = self::countFrom('mfg_part_demands', "demand_type='assembly' AND status <> 'approved'");
        $dispatchBlockers = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status, 'draft'))='draft'");
        $lowCoverage = self::countFrom('daily_orders', "COALESCE(shortage_qty,0) > 0 OR LOWER(COALESCE(coverage_status,''))='low'");

        $todayProdQty = self::sumFrom('production_entries', 'good_qty', 'production_date=?', [$today]);
        $todayQcQty = self::sumByDate('qc_entries', 'pass_qty', $today);
        $todayDispatchQty = self::sumFrom('dispatch_entries', 'dispatchable_qty', 'dispatch_date=? AND LOWER(COALESCE(completion_status,\'draft\'))=\'completed\'', [$today]);

        $recentAuditSensitive = self::countRecentAuditChanges();

        return [
            'title' => 'Admin Dashboard',
            'subtitle' => 'Business oversight: approvals, workflow pressure, and control points.',
            'cards' => [
                ['label' => 'Open Approvals', 'value' => $openApprovals, 'tone' => 'warn'],
                ['label' => 'Demand Awaiting Approval', 'value' => $prodAwaiting, 'tone' => 'warn'],
                ['label' => 'QC Demand Awaiting Approval', 'value' => $qcAwaiting, 'tone' => 'warn'],
                ['label' => 'Assembly Demand Awaiting Approval', 'value' => $asmAwaiting, 'tone' => 'warn'],
                ['label' => 'Dispatch Blockers', 'value' => $dispatchBlockers, 'tone' => 'danger'],
                ['label' => 'Low Coverage / Shortage Pressure', 'value' => $lowCoverage, 'tone' => 'danger'],
            ],
            'sections' => [
                [
                    'title' => 'Today\'s Throughput Status',
                    'items' => [
                        ['label' => 'Production Good Qty', 'value' => number_format($todayProdQty, 2, '.', ',')],
                        ['label' => 'QC Pass Qty', 'value' => number_format($todayQcQty, 2, '.', ',')],
                        ['label' => 'Dispatch Completed Qty', 'value' => number_format($todayDispatchQty, 2, '.', ',')],
                    ],
                ],
                [
                    'title' => 'Control Backlog',
                    'items' => [
                        ['label' => 'Pending Admin Actions', 'value' => (string)($openApprovals + $dispatchBlockers + $lowCoverage)],
                        ['label' => 'Recent Audit-Sensitive Changes', 'value' => (string)$recentAuditSensitive],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'Approval Inbox', 'url' => '/ops/approval-inbox'],
                ['label' => 'Demand Engine', 'url' => '/manufacturing/demands'],
                ['label' => 'Stage Board', 'url' => '/manufacturing/stage-board'],
                ['label' => 'Dispatch Workbench', 'url' => '/manufacturing/dispatch-ops'],
                ['label' => 'Coverage Analytics', 'url' => '/manufacturing/coverage'],
                ['label' => 'Admin Tools', 'url' => '/admin/apps'],
            ],
            'placeholders' => [],
        ];
    }

    private static function itAdminDashboard(): array
    {
        $assignmentCount = self::countFrom('user_dashboard_assignments', '1=1');
        $scopeRows = self::countFrom('user_operational_scopes', '1=1');
        $moduleRows = self::countFrom('user_module_visibility', '1=1');
        $activeApps = self::countFrom('core_apps', "status='enabled'");
        $installedApps = self::countFrom('installed_plugins', "status='active'");
        $failedMigrations = self::countFrom('core_migrations', "LOWER(COALESCE(status,''))='failed'");
        $missingCoreTables = self::missingCoreTableCount();
        [$logFiles, $logBytes] = self::logSummary();

        $cards = [];

        // Core administration cards (always shown)
        $cards[] = [
            'key' => 'active_apps',
            'label' => 'Active Apps',
            'value' => $activeApps,
            'tone' => 'info',
            'mini_action' => ['url' => '/admin/apps', 'label' => 'Open'],
        ];

        $cards[] = [
            'key' => 'installed_apps',
            'label' => 'Installed Apps/Plugins',
            'value' => $installedApps,
            'tone' => 'info',
            'mini_action' => ['url' => '/admin/apps', 'label' => 'Open'],
        ];

        // Developer/diagnostic cards (only in dev mode)
        if (should_show_feature('admin_diagnostics')) {
            $cards[] = [
                'key' => 'failed_migrations',
                'label' => 'Failed Migrations',
                'value' => $failedMigrations,
                'tone' => $failedMigrations > 0 ? 'danger' : 'ok',
                'mini_action' => ['url' => '/admin/apps', 'label' => 'Review'],
            ];

            $cards[] = [
                'key' => 'schema_sync_gaps',
                'label' => 'Schema Sync Gaps',
                'value' => $missingCoreTables,
                'tone' => $missingCoreTables > 0 ? 'danger' : 'ok',
                'mini_action' => [
                    'method' => 'post',
                    'url' => '/admin/apps/action',
                    'label' => 'Sync',
                    'confirm' => 'Apply packages and run pending SQL migrations now?',
                    'fields' => [
                        'action' => 'apply_and_update_all',
                        'name' => '*',
                    ],
                ],
                'sync_action' => [
                    'url' => '/admin/apps/action',
                    'action' => 'apply_and_update_all',
                    'name' => '*',
                    'label' => 'Sync',
                    'confirm' => 'Apply packages and run pending SQL migrations now?',
                ],
            ];

            $cards[] = [
                'key' => 'log_files',
                'label' => 'Log Files',
                'value' => $logFiles,
                'tone' => 'warn',
                'mini_action' => ['url' => '/admin/system-tools', 'label' => 'Inspect'],
            ];

            $cards[] = [
                'key' => 'log_size_mb',
                'label' => 'Log Size (MB)',
                'value' => number_format($logBytes / (1024 * 1024), 2, '.', ','),
                'tone' => 'warn',
                'mini_action' => ['url' => '/admin/system-tools', 'label' => 'Inspect'],
            ];
        }

        // Core access control cards (always shown)
        $cards[] = [
            'key' => 'access_control_assignments',
            'label' => 'Access Control Board',
            'value' => $assignmentCount,
            'tone' => 'info',
            'mini_action' => ['url' => '/ops/access-control', 'label' => 'Open'],
        ];

        $cards[] = [
            'key' => 'scope_assignment_rows',
            'label' => 'Scope Assignment Rows',
            'value' => $scopeRows,
            'tone' => 'info',
            'mini_action' => ['url' => '/ops/access-control', 'label' => 'Manage'],
        ];

        $cards[] = [
            'key' => 'module_visibility_rules',
            'label' => 'Module Visibility Rules',
            'value' => $moduleRows,
            'tone' => 'info',
            'mini_action' => ['url' => '/ops/access-control', 'label' => 'Manage'],
        ];

        return [
            'title' => 'Platform Admin Dashboard',
            'subtitle' => 'Technical operations and platform maintenance control panel.',
            'cards' => $cards,
            'sections' => [
                [
                    'key' => 'platform_admin_authority',
                    'title' => 'Platform Admin Authority',
                    'items' => [
                        ['key' => 'dashboard_type_assignment', 'label' => 'Dashboard Type Assignment', 'value' => 'Assign platform, app admin, and operational dashboard types per user'],
                        ['key' => 'module_visibility_assignment', 'label' => 'Module Visibility Assignment', 'value' => 'Set user-level module visibility and app defaults'],
                        ['key' => 'operational_scope_assignment', 'label' => 'Operational Scope Assignment', 'value' => 'Assign machine/part/task/department/branch/ownership scope'],
                    ],
                ],
                [
                    'key' => 'environment',
                    'title' => 'Environment',
                    'items' => [
                        ['key' => 'php_version', 'label' => 'PHP Version', 'value' => PHP_VERSION],
                        ['key' => 'runtime_environment', 'label' => 'Environment', 'value' => function_exists('app_env') ? (string)app_env() : 'production'],
                        ['key' => 'route_integrity', 'label' => 'Route Integrity Checks', 'value' => 'Use Admin Routes for live route validation'],
                    ],
                ],
            ],
            'quick_links_title' => 'Parent Admin Surfaces',
            'quick_links' => self::platformAdminParentSurfaces(),
            'placeholders' => [],
        ];
    }

    /**
     * @return array<int,array{label:string,url:string,description?:string}>
     */
    private static function platformAdminParentSurfaces(): array
    {
        $manifestPath = APP_ROOT . '/apps/Platform/manifest.json';
        if (!is_file($manifestPath)) {
            return [];
        }

        $raw = @file_get_contents($manifestPath);
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $manifest = json_decode($raw, true);
        if (!is_array($manifest)) {
            return [];
        }

        $entries = is_array($manifest['admin_parent_surfaces'] ?? null)
            ? (array)$manifest['admin_parent_surfaces']
            : [];

        if ($entries === []) {
            return [];
        }

        $seen = [];
        $links = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $url = self::normalizePath((string)($entry['url'] ?? ''));
            if ($url === '' || !self::isParentAdminSurfaceRoute($url)) {
                continue;
            }

            // Parent hub should not expose aliases as first-level links.
            $canonical = self::canonicalRouteFor($url);
            if ($canonical !== '' && $canonical !== $url) {
                continue;
            }

            if (!RouteRuntimeAuthority::hasLoadedRoute($url, 'GET')) {
                continue;
            }

            $dedupeKey = strtolower($url);
            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $label = trim((string)($entry['label'] ?? ''));
            if ($label === '') {
                $label = self::labelFromPath($url);
            }

            $links[] = [
                'label' => $label,
                'url' => $url,
                'description' => trim((string)($entry['description'] ?? '')),
                '_priority' => (int)($entry['priority'] ?? 999),
            ];
            $seen[$dedupeKey] = true;
        }

        usort($links, static function (array $a, array $b): int {
            $priority = ((int)($a['_priority'] ?? 999)) <=> ((int)($b['_priority'] ?? 999));
            if ($priority !== 0) {
                return $priority;
            }

            return strcasecmp((string)($a['label'] ?? ''), (string)($b['label'] ?? ''));
        });

        foreach ($links as &$link) {
            unset($link['_priority']);
        }
        unset($link);

        return $links;
    }

    private static function isParentAdminSurfaceRoute(string $url): bool
    {
        if (!str_starts_with($url, '/')) {
            return false;
        }

        // Parent admin surfaces only; avoid app-user and operational workboard links.
        if (str_starts_with($url, '/ops/access-control') || str_starts_with($url, '/ops/user-control') || str_starts_with($url, '/ops/navigation-tree')) {
            return true;
        }

        if (str_starts_with($url, '/admin/apps') || str_starts_with($url, '/admin/setup') || str_starts_with($url, '/admin/routes') || str_starts_with($url, '/admin/base') || str_starts_with($url, '/admin/system-tools')) {
            return true;
        }

        return false;
    }

    private static function canonicalRouteFor(string $url): string
    {
        if (!method_exists(RouteRuntimeAuthority::class, 'diagnostics')) {
            return $url;
        }

        try {
            $diagnostics = (array)RouteRuntimeAuthority::diagnostics();
            $contracts = is_array($diagnostics['route_contracts'] ?? null) ? (array)$diagnostics['route_contracts'] : [];
            foreach ($contracts as $contract) {
                if (!is_array($contract)) {
                    continue;
                }
                $path = self::normalizePath((string)($contract['path'] ?? ''));
                if ($path !== $url) {
                    continue;
                }

                $kind = strtolower(trim((string)($contract['kind'] ?? 'canonical')));
                $canonicalTarget = self::normalizePath((string)($contract['canonical_target'] ?? ''));
                if ($kind === 'alias' && $canonicalTarget !== '') {
                    return $canonicalTarget;
                }

                return $url;
            }
        } catch (\Throwable) {
            return $url;
        }

        return $url;
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }

        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return rtrim($path, '/') ?: '/';
    }

    private static function labelFromPath(string $url): string
    {
        $parts = array_values(array_filter(explode('/', trim($url, '/')), static fn(string $part): bool => $part !== ''));
        if ($parts === []) {
            return 'Admin Surface';
        }

        $last = str_replace(['-', '_'], ' ', (string)end($parts));
        return ucwords($last);
    }

    private static function sysAdminDashboard(): array
    {
        $settingsCount = self::countFrom('core_settings', '1=1');
        $adminUsers = self::countFrom('users', "COALESCE(NULLIF(TRIM(authority_role), ''), '') = 'platform_admin'");
        $roleAnomalies = self::countFrom('users', "TRIM(COALESCE(role,''))=''");
        $auditHighlights = self::countFrom('mfg_stage_readiness', "override_status IN ('blocked','skipped') AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $failedMigrations = self::countFrom('core_migrations', "LOWER(COALESCE(status,''))='failed'");

        return [
            'title' => 'Platform Security Dashboard',
            'subtitle' => 'System governance, security posture, and configuration oversight.',
            'cards' => [
                ['label' => 'System Settings Entries', 'value' => $settingsCount, 'tone' => 'info'],
                ['label' => 'Privileged Users', 'value' => $adminUsers, 'tone' => 'warn'],
                ['label' => 'Role/Access Anomalies', 'value' => $roleAnomalies, 'tone' => $roleAnomalies > 0 ? 'danger' : 'ok'],
                ['label' => 'Audit Highlights (7d)', 'value' => $auditHighlights, 'tone' => 'warn'],
                ['label' => 'Platform Warnings (Migrations)', 'value' => $failedMigrations, 'tone' => $failedMigrations > 0 ? 'danger' : 'ok'],
            ],
            'sections' => [
                [
                    'title' => 'Governance Focus',
                    'items' => [
                        ['label' => 'Security/Access Review', 'value' => 'Run role-permission review from admin tools'],
                        ['label' => 'Audit Trail Review', 'value' => 'Monitor blocked/skipped stage overrides and admin actions'],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'Admin Tools', 'url' => '/admin/apps'],
                ['label' => 'Base', 'url' => '/admin/base'],
                ['label' => 'Routes', 'url' => '/admin/routes'],
                ['label' => 'Audit (Stage Board)', 'url' => '/manufacturing/stage-board'],
                ['label' => 'ACL / Access', 'url' => '/admin/apps'],
            ],
            'placeholders' => [
                'Deep infrastructure warning feeds are currently placeholder-level and can be linked to external monitoring later.',
            ],
        ];
    }

    private static function accountAdminDashboard(): array
    {
        $monthStart = date('Y-m-01');
        $dispatchFollowup = self::countFrom('dispatch_entries', "LOWER(COALESCE(completion_status,'draft'))='completed' AND dispatch_date >= ?", [$monthStart]);
        $coveredQty = self::sumFrom('daily_orders', 'GREATEST(COALESCE(qty,0)-COALESCE(shortage_qty,0),0)', 'order_date >= ?', [$monthStart]);
        $dispatchedQty = self::sumFrom('dispatch_entries', 'dispatchable_qty', "dispatch_date >= ? AND LOWER(COALESCE(completion_status,'draft'))='completed'", [$monthStart]);
        $prodMonth = self::sumFrom('production_entries', 'good_qty', 'production_date >= ?', [$monthStart]);
        $qcMonth = self::sumFrom('qc_entries', 'pass_qty', 'DATE(COALESCE(updated_at, created_at)) >= ?', [$monthStart]);

        return [
            'title' => 'App Admin Dashboard',
            'subtitle' => 'App-scoped controls and monthly performance rollups.',
            'cards' => [
                ['label' => 'Dispatches Requiring Accounting Follow-up', 'value' => $dispatchFollowup, 'tone' => 'warn'],
                ['label' => 'Covered Qty (MTD)', 'value' => number_format($coveredQty, 2, '.', ','), 'tone' => 'info'],
                ['label' => 'Dispatched Qty (MTD)', 'value' => number_format($dispatchedQty, 2, '.', ','), 'tone' => 'info'],
                ['label' => 'Production Good Qty (MTD)', 'value' => number_format($prodMonth, 2, '.', ','), 'tone' => 'ok'],
                ['label' => 'QC Pass Qty (MTD)', 'value' => number_format($qcMonth, 2, '.', ','), 'tone' => 'ok'],
            ],
            'sections' => [
                [
                    'title' => 'Finance/Admin Control Areas',
                    'items' => [
                        ['label' => 'Covered vs Dispatched Gap', 'value' => number_format(max(0.0, $coveredQty - $dispatchedQty), 2, '.', ',')],
                        ['label' => 'Sales/Financial Area', 'value' => 'Placeholder: dedicated finance module not yet active'],
                        ['label' => 'Payroll/Accounting (SBAIO)', 'value' => 'Placeholder: future SBAIO accounting suite'],
                    ],
                ],
            ],
            'quick_links' => [
                ['label' => 'Coverage Analytics', 'url' => '/manufacturing/coverage'],
                ['label' => 'Dispatch Entries', 'url' => '/dispatch-entries'],
                ['label' => 'Order Processing', 'url' => '/manufacturing/dispatch-ops'],
                ['label' => 'Home Dashboard', 'url' => '/'],
            ],
            'placeholders' => [
                'Additional app-admin modules can be activated by app assignment and permissions.',
            ],
        ];
    }

    private static function countRecentAuditChanges(): int
    {
        $stageChanges = self::countFrom('mfg_stage_readiness', 'updated_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)');
        $approvalChanges = self::countFrom('mfg_part_demands', 'approved_at IS NOT NULL AND approved_at >= DATE_SUB(NOW(), INTERVAL 3 DAY)');
        return $stageChanges + $approvalChanges;
    }

    private static function missingCoreTableCount(): int
    {
        $required = [
            'users',
            'core_apps',
            'installed_plugins',
        ];

        if (self::pluginIsInstalled('DailyOrders')) {
            $required[] = 'daily_orders';
        }

        if (self::pluginIsInstalled('DispatchEntries')) {
            $required[] = 'dispatch_entries';
        }

        if (self::appIsInstalled('manufacturing')) {
            $required[] = 'mfg_part_demands';
        }

        $missing = 0;
        foreach ($required as $table) {
            if (!self::tableExists($table)) {
                $missing++;
            }
        }
        return $missing;
    }

    private static function logSummary(): array
    {
        $files = glob(APP_ROOT . '/storage/logs/*');
        if (!is_array($files)) {
            return [0, 0];
        }

        $count = 0;
        $bytes = 0;
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }
            $count++;
            $size = filesize($file);
            if ($size !== false) {
                $bytes += (int)$size;
            }
        }

        return [$count, $bytes];
    }

    private static function countFrom(string $table, string $where = '1=1', array $params = []): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }

        try {
            $row = DB::fetchOne("SELECT COUNT(*) AS c FROM {$table} WHERE {$where}", $params);
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function sumFrom(string $table, string $columnExpr, string $where = '1=1', array $params = []): float
    {
        if (!self::tableExists($table)) {
            return 0.0;
        }

        try {
            $row = DB::fetchOne("SELECT COALESCE(SUM({$columnExpr}),0) AS v FROM {$table} WHERE {$where}", $params);
            return (float)($row['v'] ?? 0.0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private static function sumByDate(string $table, string $columnExpr, string $date): float
    {
        if (!self::tableExists($table)) {
            return 0.0;
        }

        try {
            $row = DB::fetchOne(
                "SELECT COALESCE(SUM({$columnExpr}),0) AS v FROM {$table} WHERE DATE(COALESCE(updated_at, created_at))=?",
                [$date]
            );
            return (float)($row['v'] ?? 0.0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    private static function tableExists(string $table): bool
    {
        try {
            $row = DB::fetchOne(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
                [$table]
            );
            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function pluginIsInstalled(string $pluginName): bool
    {
        if (!self::tableExists('installed_plugins')) {
            return false;
        }

        try {
            $row = DB::fetchOne('SELECT name FROM installed_plugins WHERE name=? LIMIT 1', [$pluginName]);
            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function appIsInstalled(string $appKey): bool
    {
        if (!self::tableExists('core_apps')) {
            return false;
        }

        try {
            $row = DB::fetchOne(
                "SELECT app_key FROM core_apps WHERE app_key=? AND status IN ('installed','enabled','disabled','upgrade_pending','broken') LIMIT 1",
                [$appKey]
            );
            return is_array($row);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function scopeFilters(array $scope, string $table, array $columnToScopeKey): array
    {
        $effective = $columnToScopeKey;
        if (self::columnExists($table, 'department_code')) {
            $effective['department_code'] = 'department_code';
        }
        if (self::columnExists($table, 'branch_code')) {
            $effective['branch_code'] = 'branch_code';
        }

        return UserDashboardAssignmentService::scopeFiltersForTable($scope, $table, $effective);
    }

    private static function filterDashboardByAssignments(array $dashboard, ?array $user): array
    {
        $links = [];
        foreach ((array)($dashboard['quick_links'] ?? []) as $link) {
            if (!is_array($link)) {
                continue;
            }
            $url = trim((string)($link['url'] ?? ''));
            if ($url === '') {
                $links[] = $link;
                continue;
            }

            $decision = UserDashboardAssignmentService::routeAccessDecision($user, $url, 'GET');
            if ((bool)($decision['allowed'] ?? false)) {
                $links[] = $link;
            }
        }

        $dashboard['quick_links'] = $links;
        return $dashboard;
    }

    private static function navigationTreeData(Container $c): array
    {
        $routes = (array)$c->get('router')->listRoutes();
        $getRoutes = array_values(array_unique(array_map('strval', array_keys((array)($routes['GET'] ?? [])))));
        $postRoutes = array_values(array_unique(array_map('strval', array_keys((array)($routes['POST'] ?? [])))));
        sort($getRoutes);
        sort($postRoutes);

        $dashboards = [
            ['type' => 'platform_admin', 'url' => UserDashboardAssignmentService::routeForDashboardType('platform_admin')],
            ['type' => 'app_admin', 'url' => UserDashboardAssignmentService::routeForDashboardType('app_admin')],
            ['type' => 'my_work', 'url' => UserDashboardAssignmentService::routeForDashboardType('my_work')],
            ['type' => 'production_leader', 'url' => UserDashboardAssignmentService::routeForDashboardType('production_leader')],
            ['type' => 'assembly_leader', 'url' => UserDashboardAssignmentService::routeForDashboardType('assembly_leader')],
            ['type' => 'qc_leader', 'url' => UserDashboardAssignmentService::routeForDashboardType('qc_leader')],
            ['type' => 'dispatch_leader', 'url' => UserDashboardAssignmentService::routeForDashboardType('dispatch_leader')],
        ];

        $dashboardRows = [];
        $referencedGet = [];
        foreach ($dashboards as $row) {
            $url = (string)$row['url'];
            $exists = in_array($url, $getRoutes, true);
            $dashboardRows[] = [
                'type' => (string)$row['type'],
                'url' => $url,
                'exists' => $exists,
            ];
            $referencedGet[$url] = true;
        }

        $sidebarItems = [];
        $sidebarPath = APP_ROOT . '/app/Navigation/sidebar.php';
        if (is_file($sidebarPath)) {
            $config = require $sidebarPath;
            foreach ((array)($config['groups'] ?? []) as $group) {
                $groupKey = (string)($group['key'] ?? 'group');
                $section = (string)($group['section'] ?? 'misc');
                foreach ((array)($group['items'] ?? []) as $item) {
                    $url = trim((string)($item['url'] ?? ''));
                    if ($url === '' || !str_starts_with($url, '/')) {
                        continue;
                    }

                    $isGet = in_array($url, $getRoutes, true);
                    $isPostOnly = !$isGet && in_array($url, $postRoutes, true);
                    $sidebarItems[] = [
                        'section' => $section,
                        'group' => $groupKey,
                        'key' => (string)($item['key'] ?? ''),
                        'label' => (string)($item['label'] ?? ($item['label_key'] ?? (string)($item['key'] ?? 'item'))),
                        'url' => $url,
                        'visible_if' => (string)($item['visible_if'] ?? 'always'),
                        'status' => $isGet ? 'ok' : ($isPostOnly ? 'misused_post_only' : 'unused_missing_route'),
                    ];
                    $referencedGet[$url] = true;
                }
            }
        }

        usort($sidebarItems, static function (array $a, array $b): int {
            $left = ($a['section'] ?? '') . '|' . ($a['group'] ?? '') . '|' . ($a['key'] ?? '');
            $right = ($b['section'] ?? '') . '|' . ($b['group'] ?? '') . '|' . ($b['key'] ?? '');
            return strcmp($left, $right);
        });

        $routeTree = self::groupRoutesByFirstSegment($getRoutes);
        $actionTree = self::groupRoutesByFirstSegment($postRoutes);

        $opsAdminOrphans = [];
        foreach ($getRoutes as $route) {
            if (!(str_starts_with($route, '/ops') || str_starts_with($route, '/admin'))) {
                continue;
            }
            if (isset($referencedGet[$route])) {
                continue;
            }
            $opsAdminOrphans[] = $route;
        }

        $viewTokens = self::usageTokens('view_access');
        $tableTokens = self::usageTokens('table_access');
        $chartTokens = self::usageTokens('chart_access');

        $sidebarMissing = 0;
        $sidebarMisused = 0;
        foreach ($sidebarItems as $item) {
            if (($item['status'] ?? '') === 'unused_missing_route') {
                $sidebarMissing++;
            } elseif (($item['status'] ?? '') === 'misused_post_only') {
                $sidebarMisused++;
            }
        }

        $missingDashboardRoutes = 0;
        foreach ($dashboardRows as $row) {
            if (!($row['exists'] ?? false)) {
                $missingDashboardRoutes++;
            }
        }

        return [
            'summary' => [
                'total_get_routes' => count($getRoutes),
                'total_post_routes' => count($postRoutes),
                'sidebar_links' => count($sidebarItems),
                'dashboard_routes' => count($dashboardRows),
                'orphans_ops_admin' => count($opsAdminOrphans),
                'sidebar_missing_routes' => $sidebarMissing,
                'sidebar_post_only_links' => $sidebarMisused,
                'missing_dashboard_routes' => $missingDashboardRoutes,
                'view_tokens' => count($viewTokens),
                'table_tokens' => count($tableTokens),
                'chart_tokens' => count($chartTokens),
            ],
            'dashboards' => $dashboardRows,
            'sidebar_items' => $sidebarItems,
            'routes_tree' => $routeTree,
            'actions_tree' => $actionTree,
            'orphans' => $opsAdminOrphans,
            'usage_tokens' => [
                'view' => $viewTokens,
                'table' => $tableTokens,
                'chart' => $chartTokens,
            ],
        ];
    }

    /**
     * @param array<int,string> $routes
     * @return array<string,array<int,string>>
     */
    private static function groupRoutesByFirstSegment(array $routes): array
    {
        $grouped = [];
        foreach ($routes as $route) {
            $trimmed = ltrim(trim((string)$route), '/');
            $first = $trimmed === '' ? 'root' : (explode('/', $trimmed)[0] ?: 'root');
            $grouped[$first][] = (string)$route;
        }

        ksort($grouped);
        foreach ($grouped as &$items) {
            sort($items);
        }
        unset($items);

        return $grouped;
    }

    /**
     * @return array<int,array{token:string,count:int}>
     */
    private static function usageTokens(string $column): array
    {
        if (!self::tableExists('user_dashboard_assignments')) {
            return [];
        }

        $safe = preg_replace('/[^a-z_]/', '', strtolower($column)) ?: '';
        if (!in_array($safe, ['view_access', 'table_access', 'chart_access'], true)) {
            return [];
        }

        try {
            $rows = DB::fetchAll("SELECT {$safe} AS v FROM user_dashboard_assignments");
        } catch (\Throwable $e) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $csv = strtolower(trim((string)($row['v'] ?? '')));
            if ($csv === '') {
                continue;
            }
            $parts = preg_split('/\s*,\s*/', $csv) ?: [];
            foreach ($parts as $part) {
                $token = trim($part);
                if ($token === '' || !preg_match('/^[a-z0-9_.:-]+$/', $token)) {
                    continue;
                }
                $counts[$token] = (int)($counts[$token] ?? 0) + 1;
            }
        }

        ksort($counts);
        $out = [];
        foreach ($counts as $token => $count) {
            $out[] = ['token' => (string)$token, 'count' => (int)$count];
        }
        return $out;
    }

    private static function partScopeSql(array $scope, string $table): string
    {
        if (!self::columnExists($table, 'product_id')) {
            return '';
        }
        $parts = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        if (!$parts) {
            return '';
        }
        $holders = implode(',', array_fill(0, count($parts), '?'));
        return " AND product_id IN ({$holders})";
    }

    private static function partScopeParams(array $scope): array
    {
        return array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
    }

    private static function scopeCountLabel(array $values): string
    {
        return $values ? (string)count($values) : '-';
    }

    private static function scopeTextLabel(array $values): string
    {
        return $values ? implode(', ', $values) : '-';
    }

    private static function columnExists(string $table, string $column): bool
    {
        try {
            $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: '';
            if ($safeTable === '') {
                return false;
            }
            $safeColumn = DB::conn()->real_escape_string($column);
            return DB::fetchOne("SHOW COLUMNS FROM {$safeTable} LIKE '{$safeColumn}'") !== null;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function countScopedStageRows(array $scope, string $extraWhere): int
    {
        if (!self::tableExists('mfg_stage_readiness')) {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS c FROM mfg_stage_readiness r WHERE {$extraWhere}";
        $params = [];

        $partIds = array_values(array_filter(array_map('intval', (array)($scope['part_ids'] ?? [])), static fn(int $v): bool => $v > 0));
        if ($partIds) {
            if (!self::tableExists('mfg_part_demands')) {
                return 0;
            }
            $holders = implode(',', array_fill(0, count($partIds), '?'));
            $sql = "SELECT COUNT(*) AS c
                    FROM mfg_stage_readiness r
                    INNER JOIN mfg_part_demands d
                      ON d.product_id=r.product_id AND d.demand_date=r.ref_date
                    WHERE {$extraWhere} AND d.product_id IN ({$holders})";
            foreach ($partIds as $id) {
                $params[] = $id;
            }
        }

        try {
            $row = DB::fetchOne($sql, $params);
            return (int)($row['c'] ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function flash(string $key, string $msg): void
    {
        $_SESSION['dashboard_assignment_flash_' . $key] = $msg;
    }

    /**
     * @param array<string,mixed> $value
     */
    private static function flashData(string $key, array $value): void
    {
        $_SESSION['dashboard_assignment_flash_data_' . $key] = $value;
    }

    private static function pullFlash(string $key): string
    {
        $k = 'dashboard_assignment_flash_' . $key;
        $v = (string)($_SESSION[$k] ?? '');
        unset($_SESSION[$k]);
        return $v;
    }

    /**
     * @return array<string,mixed>
     */
    private static function pullFlashData(string $key): array
    {
        $sessionKey = 'dashboard_assignment_flash_data_' . $key;
        $value = $_SESSION[$sessionKey] ?? [];
        unset($_SESSION[$sessionKey]);
        return is_array($value) ? $value : [];
    }

    // -----------------------------------------------------------------------
    // Workspace Profile Catalog
    // -----------------------------------------------------------------------

    /**
     * @return array<string,string>
     */
    private static function workspaceProfileOwnerTypeLabels(): array
    {
        return [
            'acl' => self::tr('ops.workspace_profiles.owner_type.acl', 'ACL'),
            'app' => self::tr('ops.workspace_profiles.owner_type.app', 'App'),
            'module' => self::tr('ops.workspace_profiles.owner_type.module', 'Module'),
        ];
    }

    /**
     * @return array<string,string>
     */
    private static function workspaceProfileOwnershipUiLabels(): array
    {
        return [
            'col_owner' => self::tr('ops.workspace_profiles.col_owner', 'Ownership'),
            'field_owner_type' => self::tr('ops.workspace_profiles.field_owner_type', 'Owner Type'),
            'field_owner_target' => self::tr('ops.workspace_profiles.field_owner_target', 'Owner Target'),
        ];
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,string> $appLabels
     * @param array<string,string> $moduleLabels
     * @return array{type_label:string,target_label:string,summary:string,note:string}
     */
    private static function workspaceProfileOwnerPresentation(array $profile, array $appLabels, array $moduleLabels): array
    {
        $typeLabels = self::workspaceProfileOwnerTypeLabels();
        $ownerType = strtolower(trim((string)($profile['owner_type'] ?? 'app')));
        $ownerKey = trim((string)($profile['owner_key'] ?? ''));
        $typeLabel = (string)($typeLabels[$ownerType] ?? self::tr('ops.workspace_profiles.owner_type.unknown', 'Owned'));

        $targetLabel = match ($ownerType) {
            'acl' => self::tr('ops.workspace_profiles.owner_target.acl', 'Base ACL'),
            'app' => (string)($appLabels[$ownerKey] ?? ($ownerKey !== '' ? strtoupper($ownerKey) : self::tr('ops.workspace_profiles.owner_target.unassigned', 'Not assigned'))),
            'module' => (string)($moduleLabels[$ownerKey] ?? ($ownerKey !== '' ? $ownerKey : self::tr('ops.workspace_profiles.owner_target.unassigned', 'Not assigned'))),
            default => $ownerKey !== '' ? $ownerKey : self::tr('ops.workspace_profiles.owner_target.unassigned', 'Not assigned'),
        };

        $summary = $typeLabel;
        if ($targetLabel !== '') {
            $summary .= ' · ' . $targetLabel;
        }

        $note = match ($ownerType) {
            'acl' => self::tr('ops.workspace_profiles.owner_note.acl', 'Locked system baseline maintained by access control.'),
            'app' => self::tr('ops.workspace_profiles.owner_note.app', 'Editable work profile owned by an application surface.'),
            'module' => self::tr('ops.workspace_profiles.owner_note.module', 'Editable work profile owned by a module-level surface.'),
            default => '',
        };

        return [
            'type_label' => $typeLabel,
            'target_label' => $targetLabel,
            'summary' => $summary,
            'note' => $note,
        ];
    }

    public static function renderWorkspaceProfiles(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/workspace-profiles');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();

        $ctx = self::currentUserContext();
        if (strtolower(trim((string)($ctx['authority_role'] ?? ''))) !== 'platform_admin') {
            self::flash('err', self::tr('ops.workspace_profiles.forbidden', 'Access restricted to platform administrators.'));
            header('Location: /ops/dashboard');
            exit;
        }

        $filterAuthority = trim((string)($_GET['authority'] ?? ''));
        $filterApp       = trim((string)($_GET['app']       ?? ''));
        $filterActive    = isset($_GET['active']) ? (string)$_GET['active'] : '';

        $where  = [];
        $params = [];

        $allowedAuthority = ['platform_admin', 'app_admin', 'app_user', 'tv_display'];
        if ($filterAuthority !== '' && in_array($filterAuthority, $allowedAuthority, true)) {
            $where[]  = 'authority_role = ?';
            $params[] = $filterAuthority;
        }
        if ($filterApp !== '') {
            if ($filterApp === '__none__') {
                $where[] = '(app_key IS NULL OR app_key = \'\')';
            } else {
                $where[]  = 'app_key = ?';
                $params[] = $filterApp;
            }
        }
        if ($filterActive === '1') {
            $where[] = 'is_active = 1';
        } elseif ($filterActive === '0') {
            $where[] = 'is_active = 0';
        }

        $sql = 'SELECT id, profile_key, name, description, app_key, owner_type, owner_key, authority_role, governance_scope,
                   landing_route, default_app, assigned_apps, is_active, is_system, updated_at
                FROM workspace_profiles'
             . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
             . ' ORDER BY is_system DESC, is_active DESC, app_key ASC, name ASC LIMIT 500';

        $profiles = DB::fetchAll($sql, $params);

        // Build user count map: profile_key => count of users pinned to it
        $userCounts = [];
        try {
            $countRows = DB::fetchAll(
                "SELECT workspace_profile_key, COUNT(*) AS cnt
                 FROM user_dashboard_assignments
                 WHERE workspace_profile_key IS NOT NULL AND workspace_profile_key != ''
                 GROUP BY workspace_profile_key"
            );
            foreach (($countRows ?? []) as $cr) {
                $userCounts[(string)($cr['workspace_profile_key'] ?? '')] = (int)($cr['cnt'] ?? 0);
            }
        } catch (\Throwable) {
            // non-fatal
        }

        // Distinct app keys for filter dropdown
        $appOptions = [];
        try {
            $appRows = DB::fetchAll(
                "SELECT app_key, COALESCE(app_name, app_key) AS label
                 FROM core_apps
                 WHERE status IN ('installed','enabled')
                 ORDER BY app_key ASC"
            );
            foreach (($appRows ?? []) as $ar) {
                $k = (string)($ar['app_key'] ?? '');
                if ($k !== '') {
                    $appOptions[$k] = (string)($ar['label'] ?? $k);
                }
            }
        } catch (\Throwable) {
            // non-fatal
        }
        foreach ($profiles as $profileRow) {
            if (!is_array($profileRow)) {
                continue;
            }
            $appKey = trim((string)($profileRow['app_key'] ?? ''));
            if ($appKey !== '' && !isset($appOptions[$appKey])) {
                $appOptions[$appKey] = $appKey;
            }
        }

        $moduleLabels = [];
        try {
            $moduleRows = DB::fetchAll(
                "SELECT module_key, COALESCE(module_name, module_key) AS label
                 FROM core_app_modules
                 WHERE is_enabled = 1
                 ORDER BY app_key ASC, module_key ASC"
            );
            foreach (($moduleRows ?? []) as $mr) {
                $moduleKey = strtolower(trim((string)($mr['module_key'] ?? '')));
                if ($moduleKey !== '') {
                    $moduleLabels[$moduleKey] = (string)($mr['label'] ?? $moduleKey);
                }
            }
        } catch (\Throwable) {
            // non-fatal
        }

        foreach ($profiles as &$profileRow) {
            if (!is_array($profileRow)) {
                continue;
            }
            $ownerPresentation = self::workspaceProfileOwnerPresentation($profileRow, $appOptions, $moduleLabels);
            $profileRow['owner_type_label'] = $ownerPresentation['type_label'];
            $profileRow['owner_target_label'] = $ownerPresentation['target_label'];
            $profileRow['owner_summary_label'] = $ownerPresentation['summary'];
            $profileRow['owner_note'] = $ownerPresentation['note'];
        }
        unset($profileRow);

        $flash = self::pullFlash('ok');
        $error = self::pullFlash('err');

        $view->render('Base::ops/workspace_profiles.php', [
            'pageTitle'       => self::tr('ops.workspace_profiles.page_title', 'Workspace Profile Catalog'),
            'profiles'        => $profiles,
            'userCounts'      => $userCounts,
            'appOptions'      => $appOptions,
            'filterAuthority' => $filterAuthority,
            'filterApp'       => $filterApp,
            'filterActive'    => $filterActive,
            'ownershipUiLabels' => self::workspaceProfileOwnershipUiLabels(),
            'flash'           => $flash,
            'error'           => $error,
            'csrf'            => Auth::csrfToken(),
        ]);
    }

    public static function renderWorkspaceProfileDetail(View $view): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/ops/workspace-profiles');
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();

        $ctx = self::currentUserContext();
        if (strtolower(trim((string)($ctx['authority_role'] ?? ''))) !== 'platform_admin') {
            self::flash('err', self::tr('ops.workspace_profiles.forbidden', 'Access restricted to platform administrators.'));
            header('Location: /ops/workspace-profiles');
            exit;
        }

        $profileId = (int)($_GET['id'] ?? 0);
        $profile = null;
        $pinnedUsers = [];
        if ($profileId > 0) {
            $profile = DB::fetchOne('SELECT * FROM workspace_profiles WHERE id = ? LIMIT 1', [$profileId]);
        }

        if (is_array($profile) && trim((string)($profile['profile_key'] ?? '')) !== '') {
            $profileKey = trim((string)$profile['profile_key']);
            $pinnedUsers = DB::fetchAll(
                "SELECT u.id,
                        u.email,
                        COALESCE(u.display_name, '') AS display_name,
                        COALESCE(NULLIF(TRIM(u.authority_role), ''), '') AS authority_role,
                        COALESCE(NULLIF(TRIM(u.account_status), ''), 'active') AS account_status,
                        COALESCE(a.dashboard_type, '') AS dashboard_type
                 FROM user_dashboard_assignments a
                 INNER JOIN users u ON u.id = a.user_id
                 WHERE a.workspace_profile_key = ?
                 ORDER BY u.email ASC
                 LIMIT 250",
                [$profileKey]
            );
        }

        // Decode JSON columns for form editing
        if (is_array($profile)) {
            foreach (['nav_sections', 'quick_actions', 'module_visibility', 'permissions', 'dashboard_blocks'] as $col) {
                if (isset($profile[$col]) && is_string($profile[$col]) && $profile[$col] !== '') {
                    $decoded = json_decode($profile[$col], true);
                    $profile[$col . '_json'] = json_encode($decoded ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    $profile[$col . '_json'] = '';
                }
            }
        }

        $flash = self::pullFlash('ok');
        $error = self::pullFlash('err');

        // App registry for dropdown
        $appRows = DB::fetchAll(
            "SELECT app_key, COALESCE(app_name, app_key) AS label FROM core_apps WHERE status IN ('installed','enabled') ORDER BY app_key ASC LIMIT 100"
        );
        $appOptions = [];
        foreach ($appRows as $ar) {
            $appOptions[(string)$ar['app_key']] = (string)($ar['label'] ?? $ar['app_key']);
        }
        if ($appOptions === []) {
            $appOptions = ['platform' => 'Platform', 'manufacturing' => 'Manufacturing', 'sbaio' => 'SBAIO'];
        }

        // Load all enabled modules grouped by app_key for the module visibility matrix.
        $allModules = [];
        $moduleLabels = [];
        try {
            $moduleRows = DB::fetchAll(
                "SELECT app_key, module_key, module_name FROM core_app_modules WHERE is_enabled = 1 ORDER BY app_key ASC, module_key ASC"
            );
            foreach (($moduleRows ?? []) as $mr) {
                $ak = (string)($mr['app_key'] ?? '');
                $mk = strtolower(trim((string)($mr['module_key'] ?? '')));
                if ($ak !== '') {
                    $allModules[$ak][] = [
                        'module_key'  => (string)($mr['module_key'] ?? ''),
                        'module_name' => (string)($mr['module_name'] ?? $mr['module_key'] ?? ''),
                    ];
                }
                if ($mk !== '') {
                    $moduleLabels[$mk] = (string)($mr['module_name'] ?? $mr['module_key'] ?? $mk);
                }
            }
        } catch (\Throwable) {
            // Non-fatal — matrix will be hidden if empty.
        }

        $ownerMeta = [
            'type_label' => self::tr('ops.workspace_profiles.owner_type.unknown', 'Owned'),
            'target_label' => self::tr('ops.workspace_profiles.owner_target.pending', 'Will be inferred from scope and app/module selection after save.'),
            'summary' => self::tr('ops.workspace_profiles.owner_pending', 'Ownership will be inferred after save.'),
            'note' => self::tr('ops.workspace_profiles.owner_pending_note', 'System profiles are ACL-owned. Editable profiles are inferred as app-owned or module-owned from their scope and module visibility.'),
        ];
        if (is_array($profile)) {
            $ownerMeta = self::workspaceProfileOwnerPresentation($profile, $appOptions, $moduleLabels);
        }

        $resolvedExperienceDiagnostics = is_array($profile)
            ? ResolvedExperienceDiagnosticsService::resolveForProfile($profile)
            : null;

        $view->render('Base::ops/workspace_profile_detail.php', [
            'pageTitle'   => $profile !== null
                ? self::tr('ops.workspace_profiles.edit_title', 'Edit Workspace Profile')
                : self::tr('ops.workspace_profiles.new_title', 'New Workspace Profile'),
            'profile'     => $profile,
            'ownerMeta'   => $ownerMeta,
            'ownershipUiLabels' => self::workspaceProfileOwnershipUiLabels(),
            'pinnedUsers' => $pinnedUsers,
            'appOptions'  => $appOptions,
            'allModules'  => $allModules,
            'resolvedExperienceDiagnostics' => $resolvedExperienceDiagnostics,
            'flash'       => $flash,
            'error'       => $error,
            'csrf'        => Auth::csrfToken(),
        ]);
    }

    public static function saveWorkspaceProfile(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();

        $ctx = self::currentUserContext();
        if (strtolower(trim((string)($ctx['authority_role'] ?? ''))) !== 'platform_admin') {
            self::flash('err', self::tr('ops.workspace_profiles.forbidden', 'Not authorized.'));
            header('Location: /ops/workspace-profiles');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));

            $profileId    = (int)($input['id'] ?? 0);
            $profileKey   = strtolower(trim(preg_replace('/[^a-z0-9_]/', '_', (string)($input['profile_key'] ?? '')) ?: ''));
            $name         = trim((string)($input['name'] ?? ''));
            $description  = trim((string)($input['description'] ?? ''));
            $appKey       = strtolower(trim((string)($input['app_key'] ?? '')));
            $authorityRole = strtolower(trim((string)($input['authority_role'] ?? 'app_user')));
            $govScope     = strtolower(trim((string)($input['governance_scope'] ?? 'app')));
            $landingRoute = trim((string)($input['landing_route'] ?? '/'));
            $defaultApp   = strtolower(trim((string)($input['default_app'] ?? '')));
            $assignedApps = strtolower(trim((string)($input['assigned_apps'] ?? '')));
            $accessProfiles = trim((string)($input['access_profiles'] ?? ''));
            $widgetDiscovery = strtolower(trim((string)($input['widget_discovery'] ?? 'auto')));
            $isActive     = ($input['is_active'] ?? '0') === '1' ? 1 : 0;
            $actor        = trim((string)(Auth::user()['email'] ?? 'platform-admin'));

            if ($profileKey === '' || $name === '') {
                throw new \InvalidArgumentException('Profile key and name are required.');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $profileKey)) {
                throw new \InvalidArgumentException('Profile key must contain only lowercase letters, digits, and underscores.');
            }
            if (!in_array($authorityRole, ['platform_admin', 'app_admin', 'app_user', 'tv_display'], true)) {
                throw new \InvalidArgumentException('Invalid authority_role value.');
            }
            if (!in_array($govScope, ['platform', 'app', 'module', 'personal', 'shared'], true)) {
                throw new \InvalidArgumentException('Invalid governance_scope value.');
            }
            if (!preg_match('#^/#', $landingRoute)) {
                $landingRoute = '/' . $landingRoute;
            }

            // Parse JSON columns (store null when empty, validate JSON when provided)
            $jsonCols = [];
            foreach (['nav_sections', 'quick_actions', 'module_visibility', 'permissions', 'dashboard_blocks'] as $col) {
                $raw = trim((string)($input[$col . '_json'] ?? ''));
                if ($raw === '' || $raw === 'null') {
                    $jsonCols[$col] = null;
                } else {
                    $decoded = json_decode($raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new \InvalidArgumentException('Invalid JSON in ' . $col . ': ' . json_last_error_msg());
                    }
                    $jsonCols[$col] = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $existingSystem = false;
            if ($profileId > 0) {
                $existingMeta = DB::fetchOne('SELECT is_system FROM workspace_profiles WHERE id = ? LIMIT 1', [$profileId]);
                $existingSystem = $existingMeta && (int)($existingMeta['is_system'] ?? 0) === 1;
            }
            $ownership = UserDashboardAssignmentService::inferWorkspaceProfileOwnership(
                $profileKey,
                $authorityRole,
                $govScope,
                $appKey,
                $defaultApp,
                $jsonCols['module_visibility'],
                $existingSystem
            );

            if ($profileId > 0) {
                // Check is_system — cannot edit system profiles
                $existing = DB::fetchOne('SELECT is_system FROM workspace_profiles WHERE id = ? LIMIT 1', [$profileId]);
                if ($existing && (int)($existing['is_system'] ?? 0) === 1) {
                    throw new \RuntimeException('System profiles cannot be modified.');
                }
                DB::query(
                    'UPDATE workspace_profiles SET
                        profile_key = ?, name = ?, description = ?, app_key = ?, owner_type = ?, owner_key = ?, authority_role = ?,
                        governance_scope = ?, landing_route = ?, default_app = ?, assigned_apps = ?,
                        access_profiles = ?, widget_discovery = ?, is_active = ?,
                        nav_sections = ?, quick_actions = ?, module_visibility = ?,
                        permissions = ?, dashboard_blocks = ?, updated_by = ?
                     WHERE id = ? LIMIT 1',
                    [
                        $profileKey, $name, $description, $appKey !== '' ? $appKey : null, $ownership['owner_type'], $ownership['owner_key'], $authorityRole,
                        $govScope, $landingRoute, $defaultApp !== '' ? $defaultApp : null, $assignedApps !== '' ? $assignedApps : null,
                        $accessProfiles !== '' ? $accessProfiles : null, $widgetDiscovery, $isActive,
                        $jsonCols['nav_sections'], $jsonCols['quick_actions'], $jsonCols['module_visibility'],
                        $jsonCols['permissions'], $jsonCols['dashboard_blocks'], $actor,
                        $profileId,
                    ]
                );
                self::flash('ok', self::tr('ops.workspace_profiles.flash_updated', 'Profile updated.'));
            } else {
                DB::query(
                    'INSERT INTO workspace_profiles
                        (profile_key, name, description, app_key, owner_type, owner_key, authority_role, governance_scope,
                         landing_route, default_app, assigned_apps, access_profiles, widget_discovery,
                         is_active, nav_sections, quick_actions, module_visibility, permissions,
                         dashboard_blocks, created_by, updated_by)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $profileKey, $name, $description, $appKey !== '' ? $appKey : null, $ownership['owner_type'], $ownership['owner_key'], $authorityRole,
                        $govScope, $landingRoute, $defaultApp !== '' ? $defaultApp : null, $assignedApps !== '' ? $assignedApps : null,
                        $accessProfiles !== '' ? $accessProfiles : null, $widgetDiscovery, $isActive,
                        $jsonCols['nav_sections'], $jsonCols['quick_actions'], $jsonCols['module_visibility'],
                        $jsonCols['permissions'], $jsonCols['dashboard_blocks'], $actor, $actor,
                    ]
                );
                $newId = (int)DB::conn()->insert_id;
                self::flash('ok', self::tr('ops.workspace_profiles.flash_created', 'Profile created.'));
                header('Location: /ops/workspace-profiles/detail?id=' . $newId);
                exit;
            }
        } catch (\Throwable $e) {
            self::flash('err', 'Save failed: ' . $e->getMessage());
        }

        $redirectTo = trim((string)($input['redirect_to'] ?? ''));
        header('Location: ' . self::safeRedirectTarget($redirectTo !== '' ? $redirectTo : '/ops/workspace-profiles'));
        exit;
    }

    public static function deleteWorkspaceProfile(array $input): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            header('Location: /login');
            exit;
        }

        UserDashboardAssignmentService::ensureSchema();

        $ctx = self::currentUserContext();
        if (strtolower(trim((string)($ctx['authority_role'] ?? ''))) !== 'platform_admin') {
            self::flash('err', self::tr('ops.workspace_profiles.forbidden', 'Not authorized.'));
            header('Location: /ops/workspace-profiles');
            exit;
        }

        try {
            Auth::requireCsrf((string)($input['csrf'] ?? ''));
            $profileId = (int)($input['id'] ?? 0);
            if ($profileId <= 0) {
                throw new \InvalidArgumentException('Missing profile id.');
            }
            $existing = DB::fetchOne('SELECT is_system FROM workspace_profiles WHERE id = ? LIMIT 1', [$profileId]);
            if (!$existing) {
                throw new \RuntimeException('Profile not found.');
            }
            if ((int)($existing['is_system'] ?? 0) === 1) {
                throw new \RuntimeException('System profiles cannot be deleted.');
            }
            DB::query('DELETE FROM workspace_profiles WHERE id = ? AND is_system = 0 LIMIT 1', [$profileId]);
            self::flash('ok', self::tr('ops.workspace_profiles.flash_deleted', 'Profile deleted.'));
        } catch (\Throwable $e) {
            self::flash('err', 'Delete failed: ' . $e->getMessage());
        }

        header('Location: /ops/workspace-profiles');
        exit;
    }
}
