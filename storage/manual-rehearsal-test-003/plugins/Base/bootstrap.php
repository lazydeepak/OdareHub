<?php
// plugins/Base/bootstrap.php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\AclPolicy;
use App\Core\DB;
require_once __DIR__ . '/Services/SuitePermissionTemplateService.php';
require_once __DIR__ . '/Services/UserDashboardAssignmentService.php';
use Plugins\Base\Services\UserDashboardAssignmentService;

if (!function_exists('base_user_role_slug')) {
    function base_user_role_slug(?array $user = null): string
    {
        $u = $user ?? Auth::user();
        return strtolower(trim((string)($u['role'] ?? '')));
    }
}

if (!function_exists('base_is_admin_user')) {
    function base_is_admin_user(?array $user = null): bool
    {
        return base_can_access_admin_tools($user);
    }
}

if (!function_exists('base_can_access_admin_tools')) {
    function base_can_access_admin_tools(?array $user = null): bool
    {
        if (!Auth::isLoggedIn() && $user === null) {
            return false;
        }

        if (class_exists(AclPolicy::class)) {
            return AclPolicy::can('admin.tools.access', $user ?? Auth::user());
        }

        $allowed = ['admin', 'it admin', 'it-admin', 'it_admin', 'sysadmin', 'system admin', 'system-admin', 'system_admin'];
        return in_array(base_user_role_slug($user), $allowed, true);
    }
}

if (!function_exists('base_can_access_builder')) {
    function base_can_access_builder(?array $user = null): bool
    {
        if (!Auth::isLoggedIn()) {
            return false;
        }

        if (class_exists(AclPolicy::class)) {
            $subject = $user ?? Auth::user();
            if (function_exists('app_is_production') && app_is_production()) {
                return AclPolicy::can('admin.tools.access', $subject);
            }
            return AclPolicy::canAny(['admin.base.builder', 'admin.tools.access'], $subject);
        }

        if (function_exists('app_is_production') && app_is_production()) {
            return base_can_access_admin_tools($user);
        }

        $allowed = ['admin', 'it admin', 'it-admin', 'it_admin', 'sysadmin', 'system admin', 'system-admin', 'system_admin', 'system developer', 'system_developer', 'developer'];
        return in_array(base_user_role_slug($user), $allowed, true);
    }
}

if (!function_exists('base_can_access_supervisor_cockpit')) {
    function base_can_access_supervisor_cockpit(?array $user = null): bool
    {
        if (!Auth::isLoggedIn() && $user === null) {
            return false;
        }

        if (class_exists(AclPolicy::class)) {
            return AclPolicy::can('ops.cockpit.view', $user ?? Auth::user());
        }

        $allowed = [
            'admin',
            'it admin',
            'it-admin',
            'it_admin',
            'manager',
            'supervisor',
            'gm',
            'general manager',
            'general_manager',
            'generalmanager',
        ];

        return in_array(base_user_role_slug($user), $allowed, true);
    }
}

if (!function_exists('current_role')) {
    function current_role(): string
    {
        $user = Auth::user();
        return (string)($user['role'] ?? 'anonymous');
    }
}
if (!function_exists('can')) {
    function can(string $permKey): bool
    {
        if (class_exists(AclPolicy::class)) {
            return AclPolicy::can($permKey, Auth::user());
        }
        return current_role() === 'Admin';
    }
}

if (!function_exists('acl_can_any')) {
    /**
     * @param array<int,string> $permKeys
     */
    function acl_can_any(array $permKeys): bool
    {
        foreach ($permKeys as $permKey) {
            if (can((string)$permKey)) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('acl_require')) {
    function acl_require(string $permKey, ?string $intendedUrl = null): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl($intendedUrl);
            header('Location: /login');
            exit;
        }
        if (!can($permKey)) {
            http_response_code(403);
            $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || str_contains((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest');
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Forbidden']);
            } else {
                echo 'Access denied.';
            }
            exit;
        }

        if (function_exists('base_enforce_assignment_access')) {
            $path = parse_url($intendedUrl ?: (string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
            base_enforce_assignment_access($path, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        }
    }
}

if (!function_exists('acl_require_any')) {
    /**
     * @param array<int,string> $permKeys
     */
    function acl_require_any(array $permKeys, ?string $intendedUrl = null): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl($intendedUrl);
            header('Location: /login');
            exit;
        }
        if (!acl_can_any($permKeys)) {
            http_response_code(403);
            $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || str_contains((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest');
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Forbidden']);
            } else {
                echo 'Access denied.';
            }
            exit;
        }

        if (function_exists('base_enforce_assignment_access')) {
            $path = parse_url($intendedUrl ?: (string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
            base_enforce_assignment_access($path, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        }
    }
}

if (!function_exists('base_enforce_assignment_access')) {
    function base_enforce_assignment_access(string $path, string $method = 'GET'): void
    {
        if (!Auth::isLoggedIn()) {
            return;
        }

        if (!function_exists('platform_user_access_policy_contract')) {
            // Route-level enforcement is inactive because the Platform app contract is not loaded.
            // Expected during initial setup; should not occur in a fully-bootstrapped production environment.
            error_log('[ACL] base_enforce_assignment_access: platform_user_access_policy_contract not available — route enforcement skipped for ' . $method . ' ' . $path);
            return;
        }

        $decision = platform_user_access_policy_contract()->routeAccessDecision(Auth::user(), $path, $method);
        if (!($decision['allowed'] ?? false)) {
            // For /admin/* paths denied with platform_only, redirect to the operator layer
            // instead of 403-ing — AdminLayerService would do the same redirect but bootstrap fires first.
            if (in_array($decision['reason'] ?? '', ['platform_only', 'governance_only'], true) && str_starts_with($path, '/admin')) {
                $user = Auth::user();
                $username = (string)($user['username'] ?? $user['email'] ?? '');
                header('Location: /u/' . rawurlencode($username) . '/dashboard', true, 302);
                exit;
            }
            http_response_code(403);
            $wantsJson = str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
                || str_contains((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest');
            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Forbidden', 'reason' => (string)($decision['reason'] ?? 'access_denied')]);
            } else {
                echo 'Access denied by assignment policy.';
            }
            exit;
        }
    }
}

// Menu registry helpers
if (!function_exists('base_register_menus')) {
    function base_register_menus(array $menus): void {
        foreach ($menus as $m) {
            DB::query(
                "INSERT INTO menus (menu_key,label,url,parent_key,display_order,perm_key)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE label=VALUES(label), url=VALUES(url), parent_key=VALUES(parent_key),
                 display_order=VALUES(display_order), perm_key=VALUES(perm_key)",
                [
                    (string)$m['key'],
                    (string)$m['label'],
                    $m['url'] ?? null,
                    $m['parent'] ?? null,
                    (int)($m['order'] ?? 100),
                    $m['perm'] ?? null,
                ]
            );
        }
    }
}

if (!function_exists('base_render_sidebar')) {
    function base_render_sidebar(): void {
        $user = Auth::user();
        $accessPolicy = function_exists('platform_user_access_policy_contract') ? platform_user_access_policy_contract() : null;

        $routeAllowed = static function (?object $policy, ?array $userCtx, string $url): bool {
            $path = trim((string)parse_url($url, PHP_URL_PATH));
            if ($path === '' || $path === '#') {
                return true;
            }
            if (!is_object($policy)) {
                return true;
            }
            $decision = $policy->routeAccessDecision($userCtx, $path, 'GET');
            return (bool)($decision['allowed'] ?? false);
        };

        $rows = DB::fetchAll("SELECT * FROM menus ORDER BY COALESCE(parent_key,''), display_order ASC, label ASC");
        $tree = [];
        foreach ($rows as $r) {
            $parent = $r['parent_key'] ?: '__root__';
            $tree[$parent][] = $r;
        }

        echo '<nav class="menu">';
        foreach (($tree['__root__'] ?? []) as $group) {
            $children = $tree[$group['menu_key']] ?? [];
            $perm = $group['perm_key'] ?: null;
            if ($perm && !can($perm)) continue;

            $groupUrl = trim((string)($group['url'] ?? ''));
            $groupAllowed = $groupUrl === '' || $routeAllowed($accessPolicy, $user, $groupUrl);

            $visibleChildren = [];
            foreach ($children as $ch) {
                $perm2 = $ch['perm_key'] ?: null;
                if ($perm2 && !can($perm2)) {
                    continue;
                }

                $url = trim((string)($ch['url'] ?? ''));
                if ($url !== '' && !$routeAllowed($accessPolicy, $user, $url)) {
                    continue;
                }

                $visibleChildren[] = $ch;
            }

            if (!$groupAllowed && $visibleChildren === []) {
                continue;
            }

            echo '<div class="group">';
            echo '<div class="gtitle">' . htmlspecialchars($group['label']) . '</div>';

            if ($groupUrl !== '' && $groupAllowed) {
                echo '<a class="item" href="' . htmlspecialchars($group['url']) . '">Open</a>';
            }
            foreach ($visibleChildren as $ch) {
                $url = $ch['url'] ?: '#';
                echo '<a class="item" href="' . htmlspecialchars($url) . '">' . htmlspecialchars($ch['label']) . '</a>';
            }
            echo '</div>';
        }
        echo '</nav>';
    }
}

// Seed Base menus on boot
$baseMenus = require __DIR__ . '/menu.php';
base_register_menus($baseMenus);
