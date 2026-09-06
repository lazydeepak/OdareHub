<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\DB;
use App\Services\MyAccountService;
use Apps\Manufacturing\Services\DemandEngineService;
use Apps\Manufacturing\Services\DispatchOpsService;
use Apps\Platform\Services\UserAssignmentContext;
use Apps\Shell\Services\AdminLayerService;
use Apps\Shell\Services\DisplayLayerService;
use Apps\Shell\Services\OperatorLayerService;
use Apps\Shell\Services\WorkspaceWrapperRegistry;
use Plugins\Base\Services\NotificationService;
use Plugins\Base\Services\ResolvedExperienceConsumerService;

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/Services/BrandIdentityService.php';
require_once __DIR__ . '/Services/AdminLayerService.php';
require_once __DIR__ . '/Services/DisplayLayerService.php';
require_once __DIR__ . '/Services/OperatorLayerService.php';
require_once __DIR__ . '/Services/OperatorLayerSidebarService.php';
require_once __DIR__ . '/Services/LandingPageService.php';
require_once __DIR__ . '/Services/WorkspaceWrapperRegistry.php';
require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';
require_once __DIR__ . '/Composers/AdminSurfaceComposer.php';
require_once __DIR__ . '/Services/AdminDashboardLauncherComposer.php';
require_once __DIR__ . '/Services/ShellRuntimeMenuComposer.php';
require_once APP_ROOT . '/apps/Platform/Services/AdminUpgradeWorkbenchService.php';
require_once __DIR__ . '/Composers/DisplaySurfaceComposer.php';
require_once __DIR__ . '/Composers/WorkEntryComposer.php';
require_once __DIR__ . '/Composers/DataExchangeComposer.php';
require_once __DIR__ . '/Services/DataExchangeService.php';

$buildEmbeddedOperatorFragmentContext = static function ($user): array {
    $userArray = is_array($user) ? $user : (array)$user;
    $ctx = UserAssignmentContext::context()->resolveUserContext($userArray);
    $assignedApps = array_values((array)($ctx['active_assigned_apps'] ?? $ctx['assigned_apps'] ?? []));
    $operatorViews = (string)($ctx['operator_views'] ?? '');
    $userId = (int)($userArray['id'] ?? 0);

    if ($userId > 0) {
        try {
            $assignmentRow = DB::fetchOne(
                'SELECT user_id, authority_role, dashboard_type, assigned_apps, module_visibility, display_surfaces, workspace_profile_key, operator_views, me_dashboard_blocks, me_plugin_cards FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
                [$userId]
            );
            if (is_array($assignmentRow)) {
                $operatorViews = implode(',', ResolvedExperienceConsumerService::operatorViews($assignmentRow));
            }
        } catch (\Throwable) {
            // Fall back to merged user-context values when the assignment row is unavailable.
        }
    }

    return [
        'user_email' => (string)($userArray['email'] ?? $userArray['user_email'] ?? ''),
        'authority_role' => (string)($ctx['authority_role'] ?? 'app_user'),
        'dashboard_type' => (string)($ctx['dashboard_type'] ?? 'operator'),
        'assigned_apps' => $assignedApps,
        'active_assigned_apps' => $assignedApps,
        'module_visibility' => array_values((array)($ctx['module_visibility'] ?? [])),
        'operator_views' => $operatorViews,
    ];
};

// Serve branding assets (logo uploads) stored outside public root
$router->get('/file/branding', function () {
    $file = trim((string)($_GET['f'] ?? ''));
    // Strict: filename only, no path segments, no traversal
    if ($file === '' || strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
        http_response_code(400);
        exit;
    }
    $fullPath = APP_ROOT . '/storage/branding/' . $file;
    if (!is_file($fullPath)) {
        http_response_code(404);
        exit;
    }
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    if (!isset($allowed[$ext])) {
        http_response_code(403);
        exit;
    }
    header('Content-Type: ' . $allowed[$ext]);
    header('Cache-Control: public, max-age=86400');
    readfile($fullPath);
    exit;
});


$router->get('/apps/shell', function () {
    Auth::bootSession();
    if (!Auth::isLoggedIn()) {
        Auth::rememberIntendedUrl('/apps/shell');
        header('Location: /login', true, 302);
        exit;
    }

    $user = Auth::user();
    $ctx = UserAssignmentContext::context()->resolveUserContext($user);
    $username = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($user->username ?? ''),
        'email' => (string)($user->email ?? ''),
    ]);
    $landing = WorkspaceWrapperRegistry::landingPath($ctx, $username);
    header('Location: ' . $landing, true, 302);
    exit;
});

// Admin layer: /admin with username passed as $_GET['admin_username'] (set by index.php preprocessor)
$router->get('/admin', function () use ($view) {
    $username = trim((string)($_GET['admin_username'] ?? ''));
    $normalized = WorkspaceWrapperRegistry::normalizeHandle($username);
    Auth::bootSession();

    if (empty($normalized)) {
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/admin');
            header('Location: /login', true, 302);
            exit;
        }

        $user = Auth::user();
        $resolvedFromSession = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($user->username ?? ''),
            'email' => (string)($user->email ?? ''),
        ]);
        if ($resolvedFromSession === '') {
            header('HTTP/1.1 400 Bad Request', true, 400);
            echo '<h1>Bad Request</h1><p>Username is required. Use: /admin/your-username</p>';
            exit;
        }

        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: /admin/' . rawurlencode($resolvedFromSession) . ($qs !== '' ? '?' . $qs : ''), true, 302);
        exit;
    }

    // Normalize and redirect if needed
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($normalized !== $username) {
        header('Location: /admin/' . rawurlencode($normalized) . ($qs !== '' ? '?' . $qs : ''), true, 302);
        exit;
    }

    // Admin homes are personalized to the signed-in account. A different handle
    // never selects another user's dashboard; redirect it to the session identity.
    if (Auth::isLoggedIn()) {
        $sessionUser = is_array(Auth::user()) ? Auth::user() : (array)Auth::user();
        $sessionIdentity = [
            'username' => (string)($sessionUser['username'] ?? $sessionUser['user_name'] ?? ''),
            'email' => (string)($sessionUser['email'] ?? $sessionUser['user_email'] ?? ''),
        ];
        if (!WorkspaceWrapperRegistry::matchesIdentityHandle($normalized, $sessionIdentity)) {
            $sessionHandle = WorkspaceWrapperRegistry::handleFromIdentity($sessionIdentity);
            if ($sessionHandle !== '') {
                parse_str($qs, $queryParts);
                unset($queryParts['admin_username']);
                $cleanQuery = http_build_query($queryParts);
                header('Location: /admin/' . rawurlencode($sessionHandle) . ($cleanQuery !== '' ? '?' . $cleanQuery : ''), true, 302);
                exit;
            }
        }
    }

    // Render admin dashboard for the specified user
    AdminLayerService::render($view, $_GET, '/admin/' . $normalized);
    return null;
});

// Legacy /me entrypoint: redirect to /admin/{current_user_handle}
// Legacy /me alias — permanently redirected to /admin/{username}.
// Keep this route indefinitely as a 301 tombstone for old bookmarks, emails,
// and any DB-stored default_landing_page values that still contain '/me'.
// Do NOT emit new /me links from application code — use /admin/{username} or / instead.
$router->get('/me', function () {
    Auth::bootSession();
    if (!Auth::isLoggedIn()) {
        Auth::rememberIntendedUrl('/');
        header('Location: /login', true, 302);
        exit;
    }

    // Legacy /me alias. Route to the correct surface for this user's authority_role.
    // tv_display → /displays/..., platform_admin/app_admin → /admin/{username}, app_user → /u/{username}/...
    // Preserve query string for admin users who bookmark /me?... links.
    $landing = \Apps\Shell\Services\LandingPageService::getLandingOrLogin();
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($qs !== '' && str_starts_with($landing, '/admin/')) {
        $landing .= (str_contains($landing, '?') ? '&' : '?') . $qs;
    }
    header('Location: ' . $landing, true, 302);
    exit;
});

// Operator layer: /u with username passed as $_GET['u_username'] (set by index.php preprocessor)
$router->get('/u', function () {
    // Known operator view slugs — bare paths like /u/production land here with the slug
    // misread as a username by the URL preprocessor. Detect and redirect to the correct
    // canonical form: /u/{resolved-username}/{view}.
    static $KNOWN_VIEW_SLUGS = [
        'dashboard', 'work-entry', 'critical', 'recent',
        'parts', 'production', 'demand', 'orders', 'processing', 'preparation',
        'dispatch', 'coverage', 'qc', 'machines', 'assembly', 'materials', 'alerts', 'notifications', 'messages', 'handoff', 'account', 'preferences', 'tasks', 'sbaio', 'data-exchange', 'hospitality',
    ];

    $username = trim((string)($_GET['u_username'] ?? ''));
    $normalized = WorkspaceWrapperRegistry::normalizeHandle($username);
    \App\Core\Auth::bootSession();

    // Resolve real session username so we can use it in all branches below.
    $resolveSessionHandle = static function (): string {
        if (!\App\Core\Auth::isLoggedIn()) {
            return '';
        }
        $userArr = is_array(\App\Core\Auth::user()) ? \App\Core\Auth::user() : (array)\App\Core\Auth::user();
        return WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($userArr['username'] ?? $userArr['user_name'] ?? ''),
            'email'    => (string)($userArr['email']    ?? $userArr['user_email'] ?? ''),
        ]);
    };

    if (empty($normalized)) {
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl('/u');
            header('Location: /login', true, 302);
            exit;
        }

        $resolvedFromSession = $resolveSessionHandle();
        if ($resolvedFromSession === '') {
            header('HTTP/1.1 400 Bad Request', true, 400);
            echo '<h1>Bad Request</h1><p>Username is required. Use: /u/your-username</p>';
            exit;
        }

        $qs = $_SERVER['QUERY_STRING'] ?? '';
        header('Location: /u/' . rawurlencode($resolvedFromSession) . '/dashboard' . ($qs !== '' ? '?' . $qs : ''), true, 302);
        exit;
    }

    // Compatibility redirect: legacy bare /u/{view} paths (no username segment) arrive here
    // with $normalized equal to the view slug. Detect this case and redirect to the canonical
    // /u/{real-username}/{view} form to preserve the intended view.
    if (in_array($normalized, $KNOWN_VIEW_SLUGS, true)) {
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl('/u/' . $normalized);
            header('Location: /login', true, 302);
            exit;
        }

        $resolvedFromSession = $resolveSessionHandle();
        if ($resolvedFromSession === '') {
            header('HTTP/1.1 400 Bad Request', true, 400);
            echo '<h1>Bad Request</h1><p>Username is required. Use: /u/your-username/' . htmlspecialchars($normalized) . '</p>';
            exit;
        }

        $qs = $_SERVER['QUERY_STRING'] ?? '';
        // Strip the misread u_username param so it doesn't appear in the redirect QS.
        parse_str($qs, $qsArr);
        unset($qsArr['u_username']);
        $cleanQs = http_build_query($qsArr);
        header('Location: /u/' . rawurlencode($resolvedFromSession) . '/' . rawurlencode($normalized) . ($cleanQs !== '' ? '?' . $cleanQs : ''), true, 302);
        exit;
    }

    $qs = $_SERVER['QUERY_STRING'] ?? '';
    if ($normalized !== $username) {
        header('Location: /u/' . rawurlencode($normalized) . '/dashboard' . ($qs !== '' ? '?' . $qs : ''), true, 302);
        exit;
    }

    header('Location: /u/' . rawurlencode($normalized) . '/dashboard' . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
});

$operatorRouteTr = static function (string $key, string $fallback, array $params = []): string {
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

$resolveOperatorSessionHandle = static function (): string {
    if (!\App\Core\Auth::isLoggedIn()) {
        return '';
    }

    $user = \App\Core\Auth::user();
    $userArr = is_array($user) ? $user : (array)$user;

    return WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($userArr['username'] ?? $userArr['user_name'] ?? ''),
        'email' => (string)($userArr['email'] ?? $userArr['user_email'] ?? ''),
    ]);
};

$buildOperatorCanonicalPath = static function (string $username, string $viewPath): string {
    $path = '/u/' . rawurlencode($username);
    $trimmedViewPath = trim($viewPath, '/');
    if ($trimmedViewPath !== '') {
        $path .= '/' . $trimmedViewPath;
    }

    return $path;
};

$resolveOperatorGetRoute = static function (string $viewPath, string $requiredApp = '') use ($operatorRouteTr, $resolveOperatorSessionHandle, $buildOperatorCanonicalPath): array {
    \App\Core\Auth::bootSession();

    $username = trim((string)($_GET['u_username'] ?? ''));
    $normalized = WorkspaceWrapperRegistry::normalizeHandle($username);
    $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    $intendedPath = '/u/' . trim($viewPath, '/');

    if ($normalized === '') {
        if (!\App\Core\Auth::isLoggedIn()) {
            \App\Core\Auth::rememberIntendedUrl($intendedPath);
            header('Location: /login', true, 302);
            exit;
        }

        $resolvedFromSession = $resolveOperatorSessionHandle();
        if ($resolvedFromSession === '') {
            header('HTTP/1.1 400 Bad Request', true, 400);
            $title = $operatorRouteTr('common.bad_request', 'Bad Request');
            $message = $operatorRouteTr('operator.route.username_required', 'Username is required. Use: {path}', [
                'path' => '/u/your-username/' . trim($viewPath, '/'),
            ]);
            echo '<h1>' . htmlspecialchars($title) . '</h1><p>' . htmlspecialchars($message) . '</p>';
            exit;
        }

        header('Location: ' . $buildOperatorCanonicalPath($resolvedFromSession, $viewPath) . ($queryString !== '' ? '?' . $queryString : ''), true, 302);
        exit;
    }

    if (!\App\Core\Auth::isLoggedIn()) {
        $canonicalPath = $buildOperatorCanonicalPath($normalized, $viewPath);
        \App\Core\Auth::rememberIntendedUrl($canonicalPath . ($queryString !== '' ? '?' . $queryString : ''));
        header('Location: /login', true, 302);
        exit;
    }

    if ($normalized !== $username) {
        header('Location: ' . $buildOperatorCanonicalPath($normalized, $viewPath) . ($queryString !== '' ? '?' . $queryString : ''), true, 302);
        exit;
    }

    // ── Surface routing pipeline ──────────────────────────────────────────────────
    // Step 1: authority class  → surface jail (must precede all other gates)
    // Step 2: account type     → resolved from full ctx inside OperatorLayerService
    // Step 3: app assignment   → explicit DB row gates module-specific routes
    // Steps 4–8: default app, control scope, interaction profile,
    //            operational focus, home surface — resolved inside render()
    // ─────────────────────────────────────────────────────────────────────────────

    // Step 1: authority class — tv_display is jailed to /displays/* before anything else.
    $routeUser    = \App\Core\Auth::user();
    $routeUserArr = is_array($routeUser) ? $routeUser : (array)$routeUser;
    if (strtolower(trim((string)($routeUserArr['authority_role'] ?? ''))) === 'tv_display') {
        $tvHandle = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($routeUserArr['username'] ?? $routeUserArr['user_name'] ?? ''),
            'email'    => (string)($routeUserArr['email']    ?? $routeUserArr['user_email'] ?? ''),
        ]);
        header('Location: ' . ($tvHandle !== '' ? '/displays/user/' . rawurlencode($tvHandle) : '/displays'), true, 302);
        exit;
    }

    // Step 2: account type — account_class must match the operator surface class.
    // Admins (platform_operations, platform_security, app_administration) are
    // allowed through because they may legitimately view another user's /u/* workspace.
    // Only tv_display account class is jailed here (authority_role check above already
    // covers this, but verify the assignment row as defense-in-depth).
    $routeUserId = (int)($routeUserArr['id'] ?? 0);
    if ($routeUserId > 0) {
        $accountClassRow = \App\Core\DB::fetchOne(
            'SELECT COALESCE(account_class, \'\') AS account_class FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
            [$routeUserId]
        );
        if (is_array($accountClassRow) && strtolower(trim((string)($accountClassRow['account_class'] ?? ''))) === 'tv_display') {
            $tvHandle2 = WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($routeUserArr['username'] ?? $routeUserArr['user_name'] ?? ''),
                'email'    => (string)($routeUserArr['email']    ?? $routeUserArr['user_email'] ?? ''),
            ]);
            header('Location: ' . ($tvHandle2 !== '' ? '/displays/user/' . rawurlencode($tvHandle2) : '/displays'), true, 302);
            exit;
        }
    }

    // Step 3: app assignment — requiredApp gate (explicit DB row, no profile merging).
    if ($requiredApp !== '') {
        $appUser = \App\Core\Auth::user();
        $appUserArr = is_array($appUser) ? $appUser : (array)$appUser;
        $routeCtx = UserAssignmentContext::context()->resolveUserContext($appUserArr);
        $effectiveApps = array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($routeCtx['active_assigned_apps'] ?? $routeCtx['assigned_apps'] ?? [])
        ), static fn (string $value): bool => $value !== ''));
        $isAssigned = in_array(strtolower($requiredApp), $effectiveApps, true);
        if (!$isAssigned) {
            header('Location: ' . $buildOperatorCanonicalPath($normalized, 'dashboard'), true, 302);
            exit;
        }
    }

    $viewUserId = (int)($routeUserArr['id'] ?? 0);
    if ($viewUserId > 0) {
        try {
            $viewRow = \App\Core\DB::fetchOne(
                'SELECT user_id, authority_role, dashboard_type, assigned_apps, module_visibility, display_surfaces, workspace_profile_key, operator_views, me_dashboard_blocks, me_plugin_cards FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
                [$viewUserId]
            );
        } catch (\Throwable) {
            $viewRow = [];
        }
        $allowedViews = is_array($viewRow) ? ResolvedExperienceConsumerService::operatorViews($viewRow) : [];
        $viewToken = strtolower(trim($viewPath, '/'));
        $viewToken = explode('/', $viewToken)[0] ?? $viewToken;
        $viewToken = match ($viewToken) {
            'parts-detail' => 'parts',
            'dispatch-detail', 'dispatch-adapter' => 'dispatch',
            'alerts' => 'notifications',
            default => $viewToken,
        };
        if ($viewToken === '') {
            $viewToken = 'dashboard';
        }
        if (!in_array($viewToken, $allowedViews, true)) {
            header('Location: ' . $buildOperatorCanonicalPath($normalized, 'dashboard'), true, 302);
            exit;
        }
    }

    return [
        'username' => $normalized,
        'query_string' => $queryString,
        'canonical_path' => $buildOperatorCanonicalPath($normalized, $viewPath),
    ];
};

$resolveOperatorPostRoute = static function (string $viewPath, bool $rememberIntended = false, string $requiredApp = '') use ($buildOperatorCanonicalPath): array {
    \App\Core\Auth::bootSession();

    $username = trim((string)($_GET['u_username'] ?? ''));
    $normalized = WorkspaceWrapperRegistry::normalizeHandle($username);

    if ($normalized === '' || !\App\Core\Auth::isLoggedIn()) {
        if ($rememberIntended && $normalized !== '') {
            \App\Core\Auth::rememberIntendedUrl($buildOperatorCanonicalPath($normalized, $viewPath));
        }
        header('Location: /login', true, 302);
        exit;
    }

    $user = \App\Core\Auth::user();
    $userArr = is_array($user) ? $user : (array)$user;

    // ── Surface routing pipeline ──────────────────────────────────────────────────
    // Step 1: authority class  → surface jail
    // Step 2: account type     → account_class surface class gate
    // Step 3: app assignment   → requiredApp gate
    // Steps 4–8: resolved inside OperatorLayerService::render()
    // ─────────────────────────────────────────────────────────────────────────────

    // Step 1: authority class.
    if (strtolower(trim((string)($userArr['authority_role'] ?? ''))) === 'tv_display') {
        $tvHandleP = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($userArr['username'] ?? $userArr['user_name'] ?? ''),
            'email'    => (string)($userArr['email']    ?? $userArr['user_email'] ?? ''),
        ]);
        header('Location: ' . ($tvHandleP !== '' ? '/displays/user/' . rawurlencode($tvHandleP) : '/displays'), true, 302);
        exit;
    }

    // Step 2: account type — tv_display account class.
    $postUserId = (int)($userArr['id'] ?? 0);
    if ($postUserId > 0) {
        $postAccountRow = \App\Core\DB::fetchOne(
            'SELECT COALESCE(account_class, \'\') AS account_class FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
            [$postUserId]
        );
        if (is_array($postAccountRow) && strtolower(trim((string)($postAccountRow['account_class'] ?? ''))) === 'tv_display') {
            $tvHandleP2 = WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($userArr['username'] ?? $userArr['user_name'] ?? ''),
                'email'    => (string)($userArr['email']    ?? $userArr['user_email'] ?? ''),
            ]);
            header('Location: ' . ($tvHandleP2 !== '' ? '/displays/user/' . rawurlencode($tvHandleP2) : '/displays'), true, 302);
            exit;
        }
    }

    // Step 3: app assignment — requiredApp gate.
    if ($requiredApp !== '') {
        $routeCtx = UserAssignmentContext::context()->resolveUserContext($userArr);
        $effectiveApps = array_values(array_filter(array_map(
            static fn ($value): string => strtolower(trim((string)$value)),
            (array)($routeCtx['active_assigned_apps'] ?? $routeCtx['assigned_apps'] ?? [])
        ), static fn (string $value): bool => $value !== ''));
        $isAssigned = in_array(strtolower($requiredApp), $effectiveApps, true);
        if (!$isAssigned) {
            header('Location: ' . $buildOperatorCanonicalPath($normalized, 'dashboard'), true, 302);
            exit;
        }
    }

    $viewUserId = (int)($userArr['id'] ?? 0);
    if ($viewUserId > 0) {
        try {
            $viewRow = \App\Core\DB::fetchOne(
                'SELECT user_id, authority_role, dashboard_type, assigned_apps, module_visibility, display_surfaces, workspace_profile_key, operator_views, me_dashboard_blocks, me_plugin_cards FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
                [$viewUserId]
            );
        } catch (\Throwable) {
            $viewRow = [];
        }
        $allowedViews = is_array($viewRow) ? ResolvedExperienceConsumerService::operatorViews($viewRow) : [];
        $viewToken = strtolower(trim($viewPath, '/'));
        $viewToken = explode('/', $viewToken)[0] ?? $viewToken;
        $viewToken = match ($viewToken) {
            'parts-detail' => 'parts',
            'dispatch-detail', 'dispatch-adapter' => 'dispatch',
            'alerts' => 'notifications',
            default => $viewToken,
        };
        if ($viewToken === '') {
            $viewToken = 'dashboard';
        }
        if (!in_array($viewToken, $allowedViews, true)) {
            header('Location: ' . $buildOperatorCanonicalPath($normalized, 'dashboard'), true, 302);
            exit;
        }
    }

    return [
        'username' => $normalized,
        'canonical_path' => $buildOperatorCanonicalPath($normalized, $viewPath),
        'user' => $userArr,
    ];
};

$normalizeOperatorRedirect = static function (string $username, string $fallbackViewPath, string $redirectTo = '') use ($buildOperatorCanonicalPath): string {
    $fallback = $buildOperatorCanonicalPath($username, $fallbackViewPath);
    $candidate = trim($redirectTo);
    if ($candidate === '' || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
        return $fallback;
    }

    $allowedPrefix = '/u/' . rawurlencode($username);
    if (!str_starts_with($candidate, $allowedPrefix)) {
        return $fallback;
    }

    return $candidate;
};

$router->get('/u/dashboard', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('dashboard');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'dashboard',
    ]), $operatorRoute['canonical_path']);
    return null;
});

// ─── Operator layer sub-pages ────────────────────────────────────────────────

$router->get('/u/work-entry', function () use ($resolveOperatorGetRoute, $buildEmbeddedOperatorFragmentContext) {
    $operatorRoute = $resolveOperatorGetRoute('work-entry');
    $normalized = $operatorRoute['username'];

    // Embedded mode is used by the /u dashboard section iframe.
    if ((string)($_GET['embedded'] ?? '') === '1') {
        $user = \App\Core\Auth::user();
        \Apps\Shell\Composers\WorkEntryComposer::render($normalized, $buildEmbeddedOperatorFragmentContext($user), $_GET);
        return null;
    }

    // Default: render inside /u wrapper container.
    OperatorLayerService::render($normalized, array_merge($_GET, [
        'focus' => 'work-entry',
    ]), $operatorRoute['canonical_path']);
    return null;

});

$router->get('/u/data-exchange', function () use ($resolveOperatorGetRoute, $buildEmbeddedOperatorFragmentContext) {
    $operatorRoute = $resolveOperatorGetRoute('data-exchange');
    $normalized = $operatorRoute['username'];

    if ((string)($_GET['embedded'] ?? '') === '1') {
        $user = \App\Core\Auth::user();
        \Apps\Shell\Composers\DataExchangeComposer::renderFragment($normalized, $buildEmbeddedOperatorFragmentContext($user), $_GET);
        return null;
    }

    OperatorLayerService::render($normalized, array_merge($_GET, [
        'focus' => 'data-exchange',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/data-exchange/import', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('data-exchange');
    $normalized = $operatorRoute['username'];
    $redirectTo = $normalizeOperatorRedirect($normalized, 'data-exchange', (string)($_POST['redirect_to'] ?? ''));
    $dxTr = static function (string $key, string $fallback, array $params = []): string {
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

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

        $user = \App\Core\Auth::user();
        $ctx = \Apps\Platform\Services\UserAssignmentContext::context()->resolveUserContext($user);
        $adapterKey = strtolower(trim((string)($_POST['adapter'] ?? '')));
        $definition = \Apps\Shell\Services\DataExchangeService::findDefinition($ctx, $adapterKey);
        if (!is_array($definition)) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.adapter_unavailable', 'Adapter is not available in your workspace context.'));
        }
        if (empty($definition['supports_import'])) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.import_disabled', 'Import is not enabled for this adapter.'));
        }

        $result = \Apps\Shell\Services\DataExchangeService::processImportUpload($definition, (array)($_FILES['import_file'] ?? []));
        if (!$result['ok']) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.import_rejected', 'Import rejected: {error}', [
                'error' => (string)($result['error'] ?? 'unknown'),
            ]));
        }

        $execution = \Apps\Shell\Services\DataExchangeService::executeImportFromFile($definition, (string)($result['stored_path'] ?? ''));
        if (!$execution['ok']) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.execution_failed', 'Import execution failed: {error}', [
                'error' => (string)($execution['error'] ?? 'unknown'),
            ]));
        }

        $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
        \Apps\Shell\Services\DataExchangeService::logAudit($userId, 'import', $definition, [
            'file_name' => (string)($result['file_name'] ?? ''),
            'row_count' => (int)($result['row_count'] ?? 0),
            'stored_path' => (string)($result['stored_path'] ?? ''),
            'execution_mode' => (string)($execution['mode'] ?? ''),
            'processed_rows' => (int)($execution['processed_rows'] ?? 0),
            'inserted_rows' => (int)($execution['inserted_rows'] ?? 0),
            'skipped_rows' => (int)($execution['skipped_rows'] ?? 0),
        ]);

        if (($execution['mode'] ?? '') === 'queued') {
            $jobId = \Apps\Shell\Services\DataExchangeService::enqueueImportJob(
                $definition,
                $userId,
                (string)($result['file_name'] ?? ''),
                (string)($result['stored_path'] ?? ''),
                (int)($result['row_count'] ?? 0),
                (array)($result['header'] ?? [])
            );
            $_SESSION['operator_data_exchange_flash_ok'] = $dxTr(
                'operator.data_exchange.flash.import_queued',
                'Import staged and queued for approval. Job #{job_id}, rows: {rows}',
                [
                    'job_id' => (string)$jobId,
                    'rows' => (string)((int)($result['row_count'] ?? 0)),
                ]
            );
        } else {
            $_SESSION['operator_data_exchange_flash_ok'] = $dxTr(
                'operator.data_exchange.flash.import_applied',
                'Import applied. Inserted: {inserted}, skipped: {skipped}',
                [
                    'inserted' => (string)((int)($execution['inserted_rows'] ?? 0)),
                    'skipped' => (string)((int)($execution['skipped_rows'] ?? 0)),
                ]
            );
        }
    } catch (\Throwable $e) {
        $_SESSION['operator_data_exchange_flash_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/data-exchange/job/approve', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('data-exchange');
    $normalized = $operatorRoute['username'];
    $redirectTo = $normalizeOperatorRedirect($normalized, 'data-exchange', (string)($_POST['redirect_to'] ?? ''));
    $dxTr = static function (string $key, string $fallback, array $params = []): string {
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

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

        $user = \App\Core\Auth::user();
        $userArr = is_array($user) ? $user : (array)$user;
        $authorityRole = strtolower(trim((string)($userArr['authority_role'] ?? '')));
        if (!in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.approval_forbidden', 'Only admin roles can approve queued import jobs.'));
        }

        $jobId = (int)($_POST['job_id'] ?? 0);
        if ($jobId <= 0) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.invalid_job', 'Invalid job selected.'));
        }

        $userId = (int)($userArr['id'] ?? 0);
        $result = \Apps\Shell\Services\DataExchangeService::approveQueuedJob($jobId, $userId);
        if (!$result['ok']) {
            throw new \RuntimeException($dxTr('operator.data_exchange.error.job_approval_failed', 'Job approval failed: {error}', [
                'error' => (string)($result['error'] ?? 'unknown'),
            ]));
        }

        $execution = (array)($result['execution'] ?? []);
        $definition = [
            'key' => (string)($execution['adapter_key'] ?? ''),
            'app_key' => (string)($execution['app_key'] ?? ''),
            'module_key' => (string)($execution['module_key'] ?? ''),
            'title' => (string)($execution['title'] ?? ''),
        ];
        \Apps\Shell\Services\DataExchangeService::logAudit($userId, 'approve', $definition, [
            'job_id' => $jobId,
            'mode' => (string)($result['status'] ?? ''),
            'execution' => $execution,
        ]);
        $_SESSION['operator_data_exchange_flash_ok'] = $dxTr(
            'operator.data_exchange.flash.job_approved',
            'Job approved and applied. Inserted: {inserted}, skipped: {skipped}',
            [
                'inserted' => (string)((int)($execution['inserted_rows'] ?? 0)),
                'skipped' => (string)((int)($execution['skipped_rows'] ?? 0)),
            ]
        );
    } catch (\Throwable $e) {
        $_SESSION['operator_data_exchange_flash_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->get('/u/data-exchange/export-template', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('data-exchange/export-template');
    $dxTr = static function (string $key, string $fallback, array $params = []): string {
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

    $user = \App\Core\Auth::user();
    $ctx = \Apps\Platform\Services\UserAssignmentContext::context()->resolveUserContext($user);
    $adapterKey = strtolower(trim((string)($_GET['adapter'] ?? '')));
    $definition = \Apps\Shell\Services\DataExchangeService::findDefinition($ctx, $adapterKey);
    if (!is_array($definition) || empty($definition['supports_export'])) {
        http_response_code(404);
        echo htmlspecialchars($dxTr('operator.data_exchange.error.export_unavailable', 'Export template not available.'));
        exit;
    }

    $content = \Apps\Shell\Services\DataExchangeService::buildExportTemplateCsv($definition);
    $fileName = trim((string)($definition['template_name'] ?? 'data_exchange_template.csv'));
    if ($fileName === '') {
        $fileName = 'data_exchange_template.csv';
    }

    $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
    \Apps\Shell\Services\DataExchangeService::logAudit($userId, 'export', $definition, [
        'template_name' => $fileName,
    ]);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Cache-Control: no-store');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
    echo $content;
    exit;
});

$router->get('/u/critical', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('critical');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'critical',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/recent', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('recent');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'recent',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/parts', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('parts');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'parts',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/production', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('production', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'production',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/production/plan/status', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('production', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $planId = (int)($_POST['plan_id'] ?? 0);
    $redirectTo = $normalizeOperatorRedirect($normalized, 'production', (string)($_POST['redirect_to'] ?? ''));

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $allowed = ['In Progress', 'Completed'];
        if ($planId <= 0 || !in_array($newStatus, $allowed, true)) {
            throw new \RuntimeException($operatorRouteTr('operator.production.error.invalid_status', 'Invalid plan or status.'));
        }

        DB::query(
            'UPDATE production_plans SET status = ? WHERE id = ? LIMIT 1',
            [$newStatus, $planId]
        );

        $_SESSION['operator_production_flash_ok'] = $operatorRouteTr('operator.production.flash.status_updated', 'Status updated.');
    } catch (\Throwable $e) {
        $_SESSION['operator_production_flash_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/production/plan/create', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('production', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $planDate = trim((string)($_POST['plan_date'] ?? date('Y-m-d')));
    $machineId = (int)($_POST['machine_id'] ?? 0);
    $productId = (int)($_POST['product_id'] ?? 0);

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/production');

        $plannedQty = round((float)($_POST['planned_qty'] ?? 0), 2);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $planDate) || $machineId <= 0 || $productId <= 0 || $plannedQty <= 0) {
            throw new \RuntimeException($operatorRouteTr('operator.production.error.required', 'Plan date, machine, part, and planned quantity are required.'));
        }

        $user     = $operatorRoute['user'];
        $addedBy  = trim((string)($user['email'] ?? $user['username'] ?? 'operator'));
        $notes    = trim((string)($_POST['notes'] ?? ''));
        $status   = 'Planned';
        $planType = 'Manual';

        DB::query(
            'INSERT INTO production_plans
                (plan_date, machine_id, product_id, planned_qty, sequence_no, status, plan_type,
                 coverage_pct, shortage_qty, auto_created, notes, added_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$planDate, $machineId, $productId, $plannedQty, 1, $status, $planType, 0, 0, 0, $notes, $addedBy]
        );

        $_SESSION['operator_production_flash_ok'] = $operatorRouteTr('operator.production.flash.created', 'Production plan created.');
    } catch (\Throwable $e) {
        $_SESSION['operator_production_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/production?focus=production&production_date=' . rawurlencode($planDate), true, 302);
    exit;
});

$router->get('/u/processing', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('processing', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'processing',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/fulfillment', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('fulfillment', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'fulfillment',
    ]), $operatorRoute['canonical_path']);
    return null;
});

// Legacy aliases — redirect to unified fulfillment page
$router->get('/u/preparation', function () use ($resolveOperatorGetRoute, $buildOperatorCanonicalPath) {
    $operatorRoute = $resolveOperatorGetRoute('preparation', 'manufacturing');
    $qs = $operatorRoute['query_string'];

    header('Location: ' . $buildOperatorCanonicalPath($operatorRoute['username'], 'fulfillment') . ($qs !== '' ? '?' . $qs : ''), true, 302);
    exit;
});

$router->get('/u/dispatch', function () use ($resolveOperatorGetRoute, $buildOperatorCanonicalPath) {
    $operatorRoute = $resolveOperatorGetRoute('dispatch', 'manufacturing');
    $qs = $operatorRoute['query_string'];

    header('Location: ' . $buildOperatorCanonicalPath($operatorRoute['username'], 'fulfillment') . '?tab=dispatch' . ($qs !== '' ? '&' . $qs : ''), true, 302);
    exit;
});

$router->get('/u/dispatch/detail', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('dispatch/detail', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'dispatch-detail',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/coverage', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('coverage', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'coverage',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/demand', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('demand', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'demand',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/orders', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('orders', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'orders',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/qc', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('qc', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'qc',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/machines', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('machines', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'machines',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/assembly', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('assembly', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'assembly',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/materials', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('materials', 'manufacturing');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'materials',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/sbaio', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('sbaio', 'sbaio');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'sbaio',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->get('/u/hospitality', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('hospitality', 'hospitality');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'hospitality',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/hospitality/housekeeping/status', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('hospitality', false, 'hospitality');

    // Operator mutations are self-workspace only: the requested /u/{username}
    // must belong to the authenticated user. Privileged admin inspection of
    // another user's workspace does not confer cross-user mutation authority;
    // admins use the Hospitality admin surface for other-user changes.
    $authenticatedHandle = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($operatorRoute['user']['username'] ?? ''),
        'email' => (string)($operatorRoute['user']['email'] ?? ''),
    ]);
    if ($authenticatedHandle === '' || !hash_equals($authenticatedHandle, (string)$operatorRoute['username'])) {
        $safeHandle = $authenticatedHandle !== '' ? $authenticatedHandle : (string)$operatorRoute['username'];
        header('Location: /u/' . rawurlencode($safeHandle) . '/dashboard', true, 302);
        exit;
    }

    \Apps\Hospitality\Controllers\OperatorActions::housekeepingStatus($operatorRoute);
    return null;
});

$router->post('/u/hospitality/front-desk/check-in', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('hospitality', false, 'hospitality');

    // Same self-workspace rule as the Housekeeping action: mutation executes only
    // for the authenticated user's own handle; admin inspection of another user's
    // workspace confers no cross-user operator mutation authority.
    $authenticatedHandleFc = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($operatorRoute['user']['username'] ?? ''),
        'email' => (string)($operatorRoute['user']['email'] ?? ''),
    ]);
    if ($authenticatedHandleFc === '' || !hash_equals($authenticatedHandleFc, (string)$operatorRoute['username'])) {
        $safeHandleFc = $authenticatedHandleFc !== '' ? $authenticatedHandleFc : (string)$operatorRoute['username'];
        header('Location: /u/' . rawurlencode($safeHandleFc) . '/dashboard', true, 302);
        exit;
    }

    \Apps\Hospitality\Controllers\OperatorActions::frontDeskCheckIn($operatorRoute);
    return null;
});

$router->post('/u/hospitality/front-desk/check-out', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('hospitality', false, 'hospitality');

    // Same self-workspace rule as the other Hospitality operator actions.
    $authenticatedHandleCo = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($operatorRoute['user']['username'] ?? ''),
        'email' => (string)($operatorRoute['user']['email'] ?? ''),
    ]);
    if ($authenticatedHandleCo === '' || !hash_equals($authenticatedHandleCo, (string)$operatorRoute['username'])) {
        $safeHandleCo = $authenticatedHandleCo !== '' ? $authenticatedHandleCo : (string)$operatorRoute['username'];
        header('Location: /u/' . rawurlencode($safeHandleCo) . '/dashboard', true, 302);
        exit;
    }

    \Apps\Hospitality\Controllers\OperatorActions::frontDeskCheckOut($operatorRoute);
    return null;
});

$router->post('/u/hospitality/front-desk/charges/add', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('hospitality', false, 'hospitality');

    // Same self-workspace rule as the other Hospitality operator actions.
    $authenticatedHandleAc = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($operatorRoute['user']['username'] ?? ''),
        'email' => (string)($operatorRoute['user']['email'] ?? ''),
    ]);
    if ($authenticatedHandleAc === '' || !hash_equals($authenticatedHandleAc, (string)$operatorRoute['username'])) {
        $safeHandleAc = $authenticatedHandleAc !== '' ? $authenticatedHandleAc : (string)$operatorRoute['username'];
        header('Location: /u/' . rawurlencode($safeHandleCa) . '/dashboard', true, 302);
        exit;
    }

    \Apps\Hospitality\Controllers\OperatorActions::frontDeskAddCharge($operatorRoute);
    return null;
});

$router->post('/u/hospitality/front-desk/cancel', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('hospitality', false, 'hospitality');

    $authenticatedHandleCa = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($operatorRoute['user']['username'] ?? ''),
        'email' => (string)($operatorRoute['user']['email'] ?? ''),
    ]);
    if ($authenticatedHandleCa === '' || !hash_equals($authenticatedHandleCa, (string)$operatorRoute['username'])) {
        $safeHandleCa = $authenticatedHandleCa !== '' ? $authenticatedHandleAc : (string)$operatorRoute['username'];
        header('Location: /u/' . rawurlencode($safeHandleCa) . '/dashboard', true, 302);
        exit;
    }

    \Apps\Hospitality\Controllers\OperatorActions::frontDeskCancelReservation($operatorRoute);
    return null;
});

$router->post('/u/hospitality/front-desk/no-show', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('hospitality', false, 'hospitality');
    // Self-workspace binding guard (same pattern as cancel/check-in/checkout).
    $authHandleNs = WorkspaceWrapperRegistry::handleFromIdentity([
        'username' => (string)($operatorRoute['user']['username'] ?? ''),
        'email' => (string)($operatorRoute['user']['email'] ?? ''),
    ]);
    if ($authHandleNs === '' || !hash_equals($authHandleNs, (string)$operatorRoute['username'])) {
        $safeHdl = $authHandleNs !== '' ? $authHandleNs : (string)$operatorRoute['username'];
        header('Location: /u/' . rawurlencode($safeHdl) . '/dashboard', true, 302);
        exit;
    }
    \Apps\Hospitality\Controllers\OperatorActions::frontDeskMarkNoShow($operatorRoute);
    return null;
});

$router->get('/u/notifications', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('notifications');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'notifications',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/notifications/read', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('notifications', true);
    $normalized = $operatorRoute['username'];
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::markRead((int)($_POST['id'] ?? 0), $operatorRoute['user']);
    $redirectTo = $normalizeOperatorRedirect($normalized, 'notifications', (string)($_POST['redirect_to'] ?? ''));
    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/notifications/dismiss', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('notifications', true);
    $normalized = $operatorRoute['username'];
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::dismiss((int)($_POST['id'] ?? 0), $operatorRoute['user']);
    $redirectTo = $normalizeOperatorRedirect($normalized, 'notifications', (string)($_POST['redirect_to'] ?? ''));
    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/notifications/read-all', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('notifications', true);
    $normalized = $operatorRoute['username'];
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::markAllRead($operatorRoute['user']);
    $redirectTo = $normalizeOperatorRedirect($normalized, 'notifications', (string)($_POST['redirect_to'] ?? ''));
    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->get('/u/messages', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('messages');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'messages',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/messages/read', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('messages', true);
    $normalized = $operatorRoute['username'];

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::markRead((int)($_POST['id'] ?? 0), $operatorRoute['user']);

    $redirectTo = $normalizeOperatorRedirect($normalized, 'messages', (string)($_POST['redirect_to'] ?? ''));
    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/messages/dismiss', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('messages', true);
    $normalized = $operatorRoute['username'];

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::dismiss((int)($_POST['id'] ?? 0), $operatorRoute['user']);

    $redirectTo = $normalizeOperatorRedirect($normalized, 'messages', (string)($_POST['redirect_to'] ?? ''));
    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/messages/read-all', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('messages', true);
    $normalized = $operatorRoute['username'];

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::markAllRead($operatorRoute['user']);

    $redirectTo = $normalizeOperatorRedirect($normalized, 'messages', (string)($_POST['redirect_to'] ?? ''));
    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->get('/u/alerts', function () use ($resolveOperatorGetRoute, $buildOperatorCanonicalPath) {
    $operatorRoute = $resolveOperatorGetRoute('alerts');
    $qs = $operatorRoute['query_string'];

    header('Location: ' . $buildOperatorCanonicalPath($operatorRoute['username'], 'notifications') . ($qs !== '' ? '?' . $qs : ''), true, 302);
    return null;
});

$router->get('/u/handoff', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('handoff', 'manufacturing');
    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'handoff',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/handoff/create', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('handoff', false, 'manufacturing');
    $normalized = $operatorRoute['username'];
    $redirectTo = '/u/' . rawurlencode($normalized) . '/handoff';

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

    $authUser    = \App\Core\Auth::user();
    $userId      = (int)($authUser['id'] ?? 0);
    $displayName = htmlspecialchars((string)($authUser['display_name'] ?? $authUser['username'] ?? $authUser['email'] ?? ''));
    $noteText    = trim((string)($_POST['note_text'] ?? ''));
    $shiftLabel  = trim((string)($_POST['shift_label'] ?? ''));

    $result = \Apps\Shell\Services\ShiftHandoffService::createNote([
        'author_user_id' => $userId,
        'author_display'  => $displayName,
        'note_text'       => $noteText,
        'shift_date'      => date('Y-m-d'),
        'shift_label'     => $shiftLabel,
    ]);

    if (!$result['ok']) {
        header('Location: ' . $redirectTo . '?error=' . rawurlencode($result['error']), true, 302);
        exit;
    }

    header('Location: ' . $redirectTo . '?saved=1', true, 302);
    exit;
});

$router->post('/u/handoff/ack', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('handoff', false, 'manufacturing');
    $normalized = $operatorRoute['username'];
    $redirectTo = '/u/' . rawurlencode($normalized) . '/handoff';

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

    $userId    = (int)(\App\Core\Auth::user()['id'] ?? 0);
    $handoffId = (int)($_POST['handoff_id'] ?? 0);
    $ackAll    = (($_POST['ack_all'] ?? '') === '1');

    if ($ackAll) {
        \Apps\Shell\Services\ShiftHandoffService::acknowledgeAll($userId);
    } elseif ($handoffId > 0) {
        \Apps\Shell\Services\ShiftHandoffService::acknowledgeNote($handoffId, $userId);
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

// Operator Tasks
$router->get('/u/tasks', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('tasks');
    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'tasks',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/tasks/advance', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect) {
    $operatorRoute = $resolveOperatorPostRoute('tasks', true);
    $normalized    = $operatorRoute['username'];
    $redirectTo    = '/u/' . rawurlencode($normalized) . '/tasks';

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

    $userId = (int)(\App\Core\Auth::user()['id'] ?? 0);
    $taskId = (int)($_POST['task_id'] ?? 0);

    $result = \Apps\Shell\Services\OperatorTaskService::advanceStatus($taskId, $userId);
    $qs = $result['ok']
        ? ('?done=' . ($result['ok'] && \Apps\Shell\Services\OperatorTaskService::getById($taskId) !== null
            ? '0' : '1') . '&saved=1')
        : '?error=' . rawurlencode($result['error']);

    // Simplify: always redirect with ?saved=1 on success, ?error= on failure
    header('Location: ' . $redirectTo . ($result['ok'] ? '?saved=1' : '?error=' . rawurlencode($result['error'])), true, 302);
    exit;
});

$router->get('/u/preferences', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('preferences');
    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'preferences',
    ]), $operatorRoute['canonical_path']);
    return null;
});

// Live KPI feed — lightweight JSON endpoint for client-side polling (Phase 8).
// Returns [{key, value}] for all six dashboard tiles. Auth-gated; no page render.
$router->get('/u/kpi-feed', function () use ($resolveOperatorGetRoute) {
    \App\Core\Auth::bootSession();
    if (!\App\Core\Auth::isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'unauthenticated'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $kpis = \Apps\Shell\Services\OperatorKpiFeedService::getKpis();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['kpis' => $kpis, 'ts' => time()], JSON_UNESCAPED_UNICODE);
    exit;
});

$router->post('/u/preferences/save', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('preferences');
    $normalized    = $operatorRoute['username'];
    $redirectTo    = '/u/' . rawurlencode($normalized) . '/preferences';

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

    $userId = (int)((\App\Core\Auth::user())['id'] ?? 0);
    $values = [
        'date_range'         => trim((string)($_POST['date_range']         ?? '')),
        'refresh_rate'       => trim((string)($_POST['refresh_rate']       ?? '')),
        'notification_level' => trim((string)($_POST['notification_level'] ?? '')),
        'realtime_enabled'   => trim((string)($_POST['realtime_enabled']   ?? '')),
        'default_view'       => trim((string)($_POST['default_view']       ?? '')),
        'theme'              => trim((string)($_POST['theme']              ?? '')),
    ];

    $result = \Apps\Shell\Services\OperatorPreferencesService::saveAll($userId, $values);

    if (!$result['ok']) {
        header('Location: ' . $redirectTo . '?error=' . rawurlencode($result['error']), true, 302);
        exit;
    }

    header('Location: ' . $redirectTo . '?saved=1', true, 302);
    exit;
});

$router->post('/u/theme/apply', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('account');
    $normalized    = $operatorRoute['username'];
    $redirectTo    = '/u/' . rawurlencode($normalized) . '/account';

    \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

    $user = \App\Core\Auth::user();
    $userId = (int)((is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0)) ?? 0);
    if ($userId <= 0) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'user_required'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $requested = (string)($_POST['theme'] ?? '');
    $normalizedTheme = \Apps\Shell\Services\ThemePreferenceService::normalizePreference($requested);
    $result = \Apps\Shell\Services\OperatorPreferencesService::saveAll($userId, [
        'theme' => $normalizedTheme,
    ]);

    if (!isset($_SESSION['operator_theme_preference_overrides']) || !is_array($_SESSION['operator_theme_preference_overrides'])) {
        $_SESSION['operator_theme_preference_overrides'] = [];
    }
    $_SESSION['operator_theme_preference_overrides'][(string)$userId] = $normalizedTheme;

    if (!$result['ok']) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'theme' => $normalizedTheme,
            'persisted' => 'session_fallback',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'theme' => $normalizedTheme,
        'persisted' => 'operator_preferences',
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

$router->get('/u/parts/detail', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('parts/detail');
    $normalized = $operatorRoute['username'];

    $partId = (int)($_GET['part_id'] ?? 0);
    $hasLegacyParams = isset($_GET['focus']) || isset($_GET['q']) || isset($_GET['part_number']) || isset($_GET['part_q']);
    if ($partId > 0 && $hasLegacyParams) {
        header('Location: /u/' . rawurlencode($normalized) . '/parts/detail?part_id=' . rawurlencode((string)$partId), true, 302);
        exit;
    }

    OperatorLayerService::render($normalized, array_merge($_GET, [
        'focus' => 'parts-detail',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/processing/assembly/status', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('processing', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $demandId = (int)($_POST['demand_id'] ?? 0);
    $redirectTo = $normalizeOperatorRedirect($normalized, 'processing', (string)($_POST['redirect_to'] ?? ''));

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $allowed = ['approved'];
        if ($demandId <= 0 || !in_array(strtolower($newStatus), $allowed, true)) {
            throw new \RuntimeException($operatorRouteTr('operator.processing.error.invalid_status', 'Invalid demand or status.'));
        }

        DB::query(
            'UPDATE mfg_part_demands SET status = ? WHERE id = ? LIMIT 1',
            ['approved', $demandId]
        );

        $_SESSION['operator_processing_flash_ok'] = $operatorRouteTr('operator.processing.flash.assembly_approved', 'Assembly plan approved.');
    } catch (\Throwable $e) {
        $_SESSION['operator_processing_flash_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/processing/qc/status', function () use ($resolveOperatorPostRoute, $normalizeOperatorRedirect, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('processing', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $qcPlanId = (int)($_POST['qc_plan_id'] ?? 0);
    $redirectTo = $normalizeOperatorRedirect($normalized, 'processing', (string)($_POST['redirect_to'] ?? ''));

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), $redirectTo);

        $newStatus = trim((string)($_POST['new_status'] ?? ''));
        $allowed = ['verified by qc', 'approved by authority'];
        if ($qcPlanId <= 0 || !in_array(strtolower($newStatus), $allowed, true)) {
            throw new \RuntimeException($operatorRouteTr('operator.processing.error.invalid_qc_status', 'Invalid QC plan or status.'));
        }

        DB::query(
            'UPDATE qc_plans SET status = ? WHERE id = ? LIMIT 1',
            [$newStatus, $qcPlanId]
        );

        $_SESSION['operator_processing_flash_ok'] = $operatorRouteTr('operator.processing.flash.qc_updated', 'QC plan status updated.');
    } catch (\Throwable $e) {
        $_SESSION['operator_processing_flash_err'] = $e->getMessage();
    }

    header('Location: ' . $redirectTo, true, 302);
    exit;
});

$router->post('/u/processing/assembly/create', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('processing', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $planDate = trim((string)($_POST['plan_date'] ?? date('Y-m-d')));
    $productId = (int)($_POST['product_id'] ?? 0);

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/processing');

        $plannedQty = (float)($_POST['planned_qty'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $planDate) || $productId <= 0 || $plannedQty <= 0) {
            throw new \RuntimeException($operatorRouteTr('operator.processing.error.required', 'Plan date, part, and planned quantity are required.'));
        }

        DemandEngineService::ensureSchema();

        $note = trim((string)($_POST['notes'] ?? ''));
        $user = $operatorRoute['user'];
        $userLabel = (string)($user['email'] ?? $user['username'] ?? 'operator');
        $sourceSummary = json_encode([
            'manual' => true,
            'source' => 'operator_processing_surface',
            'notes' => $note,
        ], JSON_UNESCAPED_SLASHES);
        if ($sourceSummary === false) {
            $sourceSummary = '{}';
        }

        $existing = DB::fetchOne(
            'SELECT id FROM mfg_part_demands WHERE product_id=? AND demand_date=? AND demand_type=? LIMIT 1',
            [$productId, $planDate, 'assembly']
        );

        if (is_array($existing) && (int)($existing['id'] ?? 0) > 0) {
            DB::query(
                'UPDATE mfg_part_demands SET system_qty=?, adjusted_qty=?, adjustment_note=?, adjusted_by=?, source_summary_json=?, status=?, updated_at=NOW() WHERE id=?',
                [$plannedQty, $plannedQty, $note, $userLabel, $sourceSummary, 'adjusted', (int)$existing['id']]
            );
        } else {
            DB::query(
                'INSERT INTO mfg_part_demands (product_id, demand_date, demand_type, system_qty, adjusted_qty, approved_qty, adjustment_note, adjusted_by, source_summary_json, status) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$productId, $planDate, 'assembly', $plannedQty, $plannedQty, null, $note, $userLabel, $sourceSummary, 'adjusted']
            );
        }

        $_SESSION['operator_processing_flash_ok'] = $operatorRouteTr('operator.processing.flash.assembly_saved', 'Assembly plan saved.');
    } catch (\Throwable $e) {
        $_SESSION['operator_processing_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/processing?focus=processing&processing_date=' . rawurlencode($planDate) . '&product_id=' . rawurlencode((string)$productId), true, 302);
    exit;
});

$router->post('/u/processing/qc/create', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('processing', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $planDate = trim((string)($_POST['plan_date'] ?? date('Y-m-d')));
    $productId = (int)($_POST['product_id'] ?? 0);

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/processing');

        $plannedQty = (float)($_POST['planned_qty'] ?? 0);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $planDate) || $productId <= 0 || $plannedQty <= 0) {
            throw new \RuntimeException($operatorRouteTr('operator.processing.error.required', 'Plan date, part, and planned quantity are required.'));
        }

        $priority = trim((string)($_POST['priority'] ?? 'Normal'));
        if (!in_array($priority, ['Critical', 'High', 'Medium', 'Normal', 'Low'], true)) {
            $priority = 'Normal';
        }

        $notes = trim((string)($_POST['notes'] ?? ''));
        $user = $operatorRoute['user'];
        $userLabel = (string)($user['email'] ?? $user['username'] ?? 'operator');

        DB::query(
            'INSERT INTO qc_plans (plan_date, required_date, product_id, daily_order_id, production_entry_id, planned_qty, estimated_time_minutes, priority, status, notes, assigned_to, added_by, verified_by, approved_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$planDate, null, $productId, null, null, $plannedQty, 0, $priority, 'System Generated', $notes, '', $userLabel, '', '']
        );

        $_SESSION['operator_processing_flash_ok'] = $operatorRouteTr('operator.processing.flash.qc_saved', 'QC plan saved.');
    } catch (\Throwable $e) {
        $_SESSION['operator_processing_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/processing?focus=processing&processing_date=' . rawurlencode($planDate) . '&product_id=' . rawurlencode((string)$productId), true, 302);
    exit;
});

$router->post('/u/preparation/bundle/create', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('preparation', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $dispatchDate = trim((string)($_POST['dispatch_date'] ?? date('Y-m-d')));
    $productId = (int)($_POST['product_id'] ?? 0);
    $lineProductIdsRaw = $_POST['line_product_ids'] ?? [];
    $lineQtysRaw = $_POST['line_qtys'] ?? [];

    $lineItems = [];
    if (is_array($lineProductIdsRaw) && is_array($lineQtysRaw) && $lineProductIdsRaw !== [] && $lineQtysRaw !== []) {
        $count = min(count($lineProductIdsRaw), count($lineQtysRaw));
        for ($i = 0; $i < $count; $i++) {
            $lineProductId = (int)($lineProductIdsRaw[$i] ?? 0);
            $lineQty = round((float)($lineQtysRaw[$i] ?? 0), 2);
            if ($lineProductId <= 0 || $lineQty <= 0) {
                continue;
            }

            if (!isset($lineItems[$lineProductId])) {
                $lineItems[$lineProductId] = 0.0;
            }
            $lineItems[$lineProductId] += $lineQty;
        }
    }

    if ($lineItems === [] && $productId > 0) {
        $fallbackQty = round((float)($_POST['bundle_qty'] ?? 0), 2);
        if ($fallbackQty > 0) {
            $lineItems[$productId] = $fallbackQty;
        }
    }

    $redirectProductId = $productId;
    if ($redirectProductId <= 0 && $lineItems !== []) {
        $firstProductId = array_key_first($lineItems);
        $redirectProductId = $firstProductId !== null ? (int)$firstProductId : 0;
    }

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/preparation');
        DispatchOpsService::ensureSchema();

        $destination = trim((string)($_POST['destination'] ?? ''));
        $palletCode = trim((string)($_POST['pallet_code'] ?? ''));
        $bundleCode = trim((string)($_POST['bundle_code'] ?? ''));
        $etaLoadAt = trim((string)($_POST['eta_load_at'] ?? ''));
        $driverName = trim((string)($_POST['driver_name'] ?? ''));
        $truckNo = trim((string)($_POST['truck_no'] ?? ''));
        $carrierName = trim((string)($_POST['carrier_name'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dispatchDate) || $lineItems === []) {
            throw new \RuntimeException($operatorRouteTr('operator.preparation.error.required', 'Dispatch date and at least one part with quantity are required.'));
        }
        if ($destination === '' || $palletCode === '') {
            throw new \RuntimeException($operatorRouteTr('operator.preparation.error.destination_pallet_required', 'Destination and pallet code are required.'));
        }

        $etaDateKey = $dispatchDate;
        if ($etaLoadAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $etaLoadAt)) {
            $etaDateKey = substr($etaLoadAt, 0, 10);
            $etaLoadAt = str_replace('T', ' ', $etaLoadAt) . ':00';
        } else {
            $etaLoadAt = $dispatchDate . ' 08:00:00';
        }

        $existingHeader = DB::fetchOne(
            "SELECT id
             FROM mfg_dispatch_bundle_headers
             WHERE destination = ?
               AND pallet_code = ?
               AND DATE(COALESCE(eta_load_at, CURRENT_DATE())) = ?
               AND LOWER(COALESCE(status, 'draft')) IN ('draft', 'open')
             ORDER BY id DESC
             LIMIT 1",
            [$destination, $palletCode, $etaDateKey]
        );

        $user = $operatorRoute['user'];
        $createdBy = (string)($user['email'] ?? $user['username'] ?? 'operator');

        if (is_array($existingHeader) && (int)($existingHeader['id'] ?? 0) > 0) {
            $bundleId = (int)$existingHeader['id'];
            DB::query(
                'UPDATE mfg_dispatch_bundle_headers SET bundle_code=?, eta_load_at=?, driver_name=?, truck_no=?, carrier_name=?, notes=?, updated_at=NOW() WHERE id=?',
                [$bundleCode !== '' ? $bundleCode : null, $etaLoadAt, $driverName !== '' ? $driverName : null, $truckNo !== '' ? $truckNo : null, $carrierName !== '' ? $carrierName : null, $notes !== '' ? $notes : null, $bundleId]
            );
        } else {
            DB::query(
                'INSERT INTO mfg_dispatch_bundle_headers (bundle_code, pallet_code, destination, eta_load_at, driver_name, truck_no, carrier_name, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)',
                [$bundleCode !== '' ? $bundleCode : null, $palletCode, $destination, $etaLoadAt, $driverName !== '' ? $driverName : null, $truckNo !== '' ? $truckNo : null, $carrierName !== '' ? $carrierName : null, 'draft', $notes !== '' ? $notes : null, $createdBy]
            );
            $lastInsertRow = DB::fetchOne('SELECT LAST_INSERT_ID() AS id');
            $bundleId = (int)($lastInsertRow['id'] ?? 0);
        }

        $loadReference = $bundleCode !== '' ? $bundleCode : ('B-' . $bundleId);
        foreach ($lineItems as $lineProductId => $lineQty) {
            $dispatchMatches = DB::fetchAll(
                "SELECT id, daily_order_id, dispatchable_qty, destination
                 FROM dispatch_entries
                 WHERE product_id = ?
                   AND dispatch_date = ?
                   AND LOWER(COALESCE(completion_status, 'draft')) IN ('draft', 'ready', 'prepared')
                   AND (
                        COALESCE(NULLIF(TRIM(destination), ''), '') = ''
                        OR destination = ?
                   )
                 ORDER BY
                    CASE WHEN destination = ? THEN 0 ELSE 1 END,
                    id ASC
                 LIMIT 10",
                [(int)$lineProductId, $dispatchDate, $destination, $destination]
            );

            $resolvedDispatchEntryId = null;
            $resolvedDailyOrderId = null;
            $targetQty = round((float)$lineQty, 2);
            if (count($dispatchMatches) === 1) {
                $resolvedDispatchEntryId = (int)($dispatchMatches[0]['id'] ?? 0);
                $resolvedDailyOrderId = (int)($dispatchMatches[0]['daily_order_id'] ?? 0);
            } elseif (count($dispatchMatches) > 1) {
                $exactQtyMatches = array_values(array_filter($dispatchMatches, static function ($row) use ($targetQty): bool {
                    return abs(((float)($row['dispatchable_qty'] ?? 0)) - $targetQty) < 0.0001;
                }));
                if (count($exactQtyMatches) === 1) {
                    $resolvedDispatchEntryId = (int)($exactQtyMatches[0]['id'] ?? 0);
                    $resolvedDailyOrderId = (int)($exactQtyMatches[0]['daily_order_id'] ?? 0);
                }
            }

            foreach ($dispatchMatches as $dispatchMatch) {
                $dispatchEntryId = (int)($dispatchMatch['id'] ?? 0);
                if ($dispatchEntryId <= 0) {
                    continue;
                }

                DB::query(
                    'UPDATE dispatch_entries SET destination=?, eta_load_at=?, driver_name=?, truck_no=?, carrier_name=?, load_reference=?, updated_at=NOW() WHERE id=?',
                    [
                        $destination,
                        $etaLoadAt,
                        $driverName !== '' ? $driverName : null,
                        $truckNo !== '' ? $truckNo : null,
                        $carrierName !== '' ? $carrierName : null,
                        $loadReference,
                        $dispatchEntryId,
                    ]
                );
            }

            DB::query(
                'INSERT INTO mfg_dispatch_bundle_lines (bundle_id, dispatch_entry_id, daily_order_id, product_id, dispatch_date, qty) VALUES (?,?,?,?,?,?)',
                [
                    $bundleId,
                    $resolvedDispatchEntryId !== null && $resolvedDispatchEntryId > 0 ? $resolvedDispatchEntryId : null,
                    $resolvedDailyOrderId !== null && $resolvedDailyOrderId > 0 ? $resolvedDailyOrderId : null,
                    (int)$lineProductId,
                    $dispatchDate,
                    $targetQty,
                ]
            );
        }

        $_SESSION['operator_preparation_flash_ok'] = $operatorRouteTr('operator.preparation.flash.saved', 'Prepare order saved.');
    } catch (\Throwable $e) {
        $_SESSION['operator_preparation_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/preparation?focus=preparation&preparation_date=' . rawurlencode($dispatchDate) . '&product_id=' . rawurlencode((string)$redirectProductId), true, 302);
    exit;
});

$router->post('/u/dispatch/create', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('dispatch', false, 'manufacturing');
    $normalized = $operatorRoute['username'];

    $dispatchDate = trim((string)($_POST['dispatch_date'] ?? date('Y-m-d')));
    $productId = (int)($_POST['product_id'] ?? 0);

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/dispatch');
        DemandEngineService::ensureSchema();

        $destination = trim((string)($_POST['destination'] ?? ''));
        $dispatchQty = round((float)($_POST['dispatchable_qty'] ?? 0), 2);
        $etaLoadAt = trim((string)($_POST['eta_load_at'] ?? ''));
        $driverName = trim((string)($_POST['driver_name'] ?? ''));
        $truckNo = trim((string)($_POST['truck_no'] ?? ''));
        $loadReference = trim((string)($_POST['load_reference'] ?? ''));
        $remarks = trim((string)($_POST['remarks'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dispatchDate) || $productId <= 0 || $dispatchQty <= 0) {
            throw new \RuntimeException($operatorRouteTr('operator.dispatch.error.required', 'Dispatch date, part, and quantity are required.'));
        }
        if ($destination === '') {
            throw new \RuntimeException($operatorRouteTr('operator.dispatch.error.destination_required', 'Destination is required.'));
        }

        $etaValue = null;
        if ($etaLoadAt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $etaLoadAt)) {
            $etaValue = str_replace('T', ' ', $etaLoadAt) . ':00';
        }

        DB::query(
            'INSERT INTO dispatch_entries (dispatch_date, product_id, dispatchable_qty, destination, dispatch_type, dispatch_status, approval_status, completion_status, eta_load_at, driver_name, truck_no, load_reference, remarks, prepared_by, prepared_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())',
            [
                $dispatchDate,
                $productId,
                $dispatchQty,
                $destination,
                'Regular',
                'Ready',
                'Draft',
                'draft',
                $etaValue,
                $driverName !== '' ? $driverName : null,
                $truckNo !== '' ? $truckNo : null,
                $loadReference !== '' ? $loadReference : null,
                $remarks !== '' ? $remarks : null,
                (string)(($operatorRoute['user']['email'] ?? $operatorRoute['user']['username'] ?? 'operator')),
            ]
        );

        $_SESSION['operator_dispatch_flash_ok'] = $operatorRouteTr('operator.dispatch.flash.saved', 'Dispatch entry saved.');
    } catch (\Throwable $e) {
        $_SESSION['operator_dispatch_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/dispatch?focus=dispatch&dispatch_date=' . rawurlencode($dispatchDate) . '&product_id=' . rawurlencode((string)$productId), true, 302);
    exit;
});

$router->get('/u/account', function () use ($resolveOperatorGetRoute) {
    $operatorRoute = $resolveOperatorGetRoute('account');

    OperatorLayerService::render($operatorRoute['username'], array_merge($_GET, [
        'focus' => 'account',
    ]), $operatorRoute['canonical_path']);
    return null;
});

$router->post('/u/account/profile', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('account');
    $normalized = $operatorRoute['username'];

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/account');
        $userId = (int)($operatorRoute['user']['id'] ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException($operatorRouteTr('operator.account.error.user_required', 'Could not resolve the current user.'));
        }

        $service = new MyAccountService();
        $service->updateProfile($userId, $_POST);
        $_SESSION['my_account_flash_ok'] = $operatorRouteTr('operator.account.flash.profile_updated', 'Profile updated.');
    } catch (\Throwable $e) {
        $_SESSION['my_account_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/account', true, 302);
    exit;
});

$router->post('/u/account/password', function () use ($resolveOperatorPostRoute, $operatorRouteTr) {
    $operatorRoute = $resolveOperatorPostRoute('account');
    $normalized = $operatorRoute['username'];

    try {
        \App\Core\Auth::requireCsrf((string)($_POST['csrf'] ?? ''), '/u/' . $normalized . '/account');
        $userId = (int)($operatorRoute['user']['id'] ?? 0);
        if ($userId <= 0) {
            throw new \RuntimeException($operatorRouteTr('operator.account.error.user_required', 'Could not resolve the current user.'));
        }

        $service = new MyAccountService();
        $service->changePassword($userId, $_POST);
        $_SESSION['my_account_flash_ok'] = $operatorRouteTr('operator.account.flash.password_updated', 'Password changed successfully.');
    } catch (\Throwable $e) {
        $_SESSION['my_account_flash_err'] = $e->getMessage();
    }

    header('Location: /u/' . rawurlencode($normalized) . '/account', true, 302);
    exit;
});

$router->post('/u/work-entry', function () use ($resolveOperatorPostRoute) {
    $operatorRoute = $resolveOperatorPostRoute('work-entry');
    $normalized = $operatorRoute['username'];
    $user = $operatorRoute['user'];
    $ctx  = \Apps\Platform\Services\UserAssignmentContext::context()->resolveUserContext($user);
    \Apps\Shell\Composers\WorkEntryComposer::handlePost($normalized, [
        'user_email'     => (string)($user['email'] ?? ''),
        'authority_role'   => (string)($ctx['authority_role'] ?? 'app_user'),
        'dashboard_type' => (string)($ctx['dashboard_type'] ?? 'operator'),
        'assigned_apps'  => array_values((array)($ctx['assigned_apps'] ?? [])),
        'user_id'        => (int)($user['id'] ?? 0),
    ], $_POST);
    return null;
});

// Display layer: /displays base route — redirects to user or device display
$router->get('/displays', function () {
    DisplayLayerService::renderBase($_GET);
    return null;
});

// Display layer: /displays/user/{username} — user-specific readonly floor display
$router->get('/displays/user', function () {
    $username = trim((string)($_GET['display_username'] ?? ''));
    if ($username === '') {
        http_response_code(400);
        echo '<h1>Bad Request</h1><p>Username is required. Use: /displays/user/your-username</p>';
        exit;
    }
    DisplayLayerService::renderUser($username, $_GET);
    return null;
});

// Display layer: /displays/device/{device_id} — device-specific readonly kiosk display
$router->get('/displays/device', function () {
    $deviceId = trim((string)($_GET['display_device_id'] ?? ''));
    if ($deviceId === '') {
        http_response_code(400);
        echo '<h1>Bad Request</h1><p>Device ID is required. Use: /displays/device/your-device-id</p>';
        exit;
    }
    DisplayLayerService::renderDevice($deviceId, $_GET);
    return null;
});
