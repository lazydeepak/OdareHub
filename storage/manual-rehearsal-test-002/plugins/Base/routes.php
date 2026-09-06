<?php
// plugins/Base/routes.php
declare(strict_types=1);

use App\Core\DB;
use App\Core\PackageManager;
use App\Core\Auth;
use App\Core\AuditLogService;
use App\Core\TOTP;
use App\Core\DashboardBuilder;
use App\Services\AppRuntimeRegistryService;
use App\Services\PasswordResetService;
use App\Services\SetupStatusService;
use App\Services\TwoFactorQrService;
use Apps\Manufacturing\Controllers\HandoffBoardController;
use Apps\Manufacturing\Services\HandoffBoardService;
use Plugins\Coverage\Services\CoverageService;
use Plugins\Base\Services\GovernanceInboxService;
use Plugins\Base\Services\NotificationService;
use Plugins\Base\Services\OperatorWorkboardService;
use Plugins\Base\Services\RoleInboxService;
use Plugins\Base\Services\UserDashboardAssignmentService;
use Plugins\Base\Services\RateLimitService;
use Plugins\Base\Services\AdminToolsAccessService;
use Plugins\Base\Controllers\DashboardController;
use Plugins\Base\Controllers\MyAccountController;
use Plugins\Base\Controllers\PasskeyController;
use Plugins\Base\Controllers\RoleDashboardsController;
use Plugins\AdminTools\Controllers\AdminToolsController;
use App\Core\AccessGuard;

$adminToolsControllerPath = APP_ROOT . '/plugins/AdminTools/Controllers/AdminToolsController.php';
if (is_file($adminToolsControllerPath)) {
    require_once $adminToolsControllerPath;
}

require_once APP_ROOT . '/apps/Manufacturing/Services/HandoffBoardService.php';
require_once APP_ROOT . '/apps/Manufacturing/Controllers/HandoffBoardController.php';
require_once __DIR__ . '/Services/GovernanceInboxService.php';
require_once __DIR__ . '/Services/NotificationService.php';
require_once __DIR__ . '/Services/OperatorWorkboardService.php';
require_once __DIR__ . '/Services/RoleInboxService.php';
require_once __DIR__ . '/Services/SuitePermissionTemplateService.php';
require_once __DIR__ . '/Services/UserDashboardAssignmentService.php';
require_once __DIR__ . '/Services/RateLimitService.php';
require_once __DIR__ . '/Services/AdminToolsAccessService.php';
require_once __DIR__ . '/Controllers/DashboardController.php';
require_once __DIR__ . '/Controllers/MyAccountController.php';
require_once APP_ROOT . '/apps/Platform/Services/DashboardService.php';
require_once __DIR__ . '/Controllers/PasskeyController.php';
require_once __DIR__ . '/Controllers/RoleDashboardsController.php';
// Access guard for consistent access control
require_once APP_ROOT . '/app/Core/AccessGuard.php';

// Shell owns /me route registration; Base provides composition dependencies.
include APP_ROOT . '/apps/Shell/routes.php';
// Platform owns governance/admin route registration; Base provides shared services/controllers.
include APP_ROOT . '/apps/Platform/routes.php';

// ------------------------------------------------------
// Public
// ------------------------------------------------------
$router->get('/', function() use ($view) {
    DashboardController::landing($view);
});

$router->get('/ops/handoff-board', function() {
    acl_require('ops.handoff.view', '/ops/handoff-board');

    if (!HandoffBoardService::isAvailable()) {
        header('Location: /me', true, 302);
        exit;
    }

    $target = HandoffBoardService::canonicalUrl();
    if (!empty($_SERVER['QUERY_STRING'])) {
        $target .= '?' . (string)$_SERVER['QUERY_STRING'];
    }

    header('Location: ' . $target, true, 302);
    exit;
});

$router->get('/account', function () use ($view) {
    MyAccountController::render($view);
    return null;
});

$router->post('/account/profile', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::updateProfile($_POST);
    return null;
});

$router->post('/account/password', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::changePassword($_POST);
    return null;
});

$router->get('/account/2fa/setup', function () use ($view) {
    MyAccountController::renderTwoFactorSetup($view);
    return null;
});

$router->get('/account/2fa/manage', function () use ($view) {
    MyAccountController::renderTwoFactorManage($view);
    return null;
});

$router->post('/account/2fa/setup', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::completeTwoFactorSetup($_POST);
    return null;
});

$router->post('/account/2fa/disable', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::disableTwoFactor($_POST);
    return null;
});

$router->post('/account/2fa/regenerate', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::regenerateTwoFactorSetup($_POST);
    return null;
});

$router->get('/account/2fa/recovery-codes', function () use ($view) {
    MyAccountController::renderRecoveryCodes($view);
    return null;
});

$router->post('/account/2fa/recovery-codes/regenerate', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::regenerateRecoveryCodes($_POST);
    return null;
});

// Passkey management (authenticated)
$router->get('/account/passkey/manage', function () use ($view) {
    PasskeyController::renderManage($view);
    return null;
});

$router->post('/account/passkey/challenge', function () {
    PasskeyController::apiRegistrationChallenge();
    return null;
});

$router->post('/account/passkey/register', function () {
    PasskeyController::apiRegistrationComplete();
    return null;
});

$router->post('/account/passkey/delete', function () {
    PasskeyController::apiDelete($_POST);
    return null;
});

// Passkey login (public — no auth required)
$router->post('/passkey/challenge', function () {
    // Rate-limit unauthenticated challenge requests to prevent session-table flooding.
    $ip      = preg_replace('/[^a-fA-F0-9.:_]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $rateKey = 'passkey_challenge_' . md5($ip);
    if (!RateLimitService::attemptWithinLimit($rateKey, 20, 60)) {
        header('Content-Type: application/json');
        http_response_code(429);
        echo json_encode(['error' => 'Too many requests. Try again shortly.']);
        exit;
    }
    PasskeyController::apiLoginChallenge();
    return null;
});

$router->post('/passkey/authenticate', function () {
    PasskeyController::apiLoginComplete();
    return null;
});

$router->get('/account/setup', function () use ($view) {
    MyAccountController::renderSetup($view);
    return null;
});

$router->post('/account/setup', function () {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    MyAccountController::completeSetup($_POST);
    return null;
});

$router->get('/ops/approval-inbox', function() use ($view) {
    acl_require('ops.approval_inbox.view', '/ops/approval-inbox');
    $user = Auth::user();

    $inbox = GovernanceInboxService::build($_GET, $user);
    $view->render('Base::approval_inbox.php', [
        'pageTitle' => 'Approval Inbox',
        'inbox' => $inbox,
    ]);
    return null;
});

$router->get('/ops/notifications', function() use ($view) {
    Auth::bootSession();
    if (!Auth::isLoggedIn()) {
        Auth::rememberIntendedUrl('/ops/notifications');
        header('Location: /login');
        exit;
    }

    $user = Auth::user();
    $status = trim((string)($_GET['status'] ?? ''));
    $notifications = NotificationService::listForUser($user, $status);
    $unreadCount = NotificationService::unreadCountForUser($user);

    // Prepare admin context for message form
    $isAdmin = false;
    $targetRoles = [];
    $assignmentRows = [];
    if (class_exists(\Plugins\Base\Services\UserDashboardAssignmentService::class)) {
        \Plugins\Base\Services\UserDashboardAssignmentService::ensureSchema();
        $ctx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext($user);
        $isAdmin = (string)($ctx['authority_role'] ?? 'app_user') === 'platform_admin';
        
        if ($isAdmin) {
            $assignmentRows = \Plugins\Base\Services\UserDashboardAssignmentService::listAssignmentRows(['status' => 'all'], false);
            $roleMap = [];
            foreach ($assignmentRows as $row) {
                $role = trim((string)($row['operational_role'] ?? ($row['role'] ?? '')));
                if ($role === '') {
                    continue;
                }
                $roleMap[strtolower($role)] = $role;
            }
            $targetRoles = array_values($roleMap);
        }
    }

    $view->render('Base::notifications.php', [
        'pageTitle' => 'Notifications',
        'notifications' => $notifications,
        'status' => $status,
        'unreadCount' => $unreadCount,
        'isAdmin' => $isAdmin,
        'targetRoles' => $targetRoles,
        'assignmentRows' => $assignmentRows,
    ]);
    return null;
});

$router->post('/ops/notifications/read', function() {
    Auth::bootSession();
    if (!Auth::isLoggedIn()) {
        Auth::rememberIntendedUrl('/ops/notifications');
        header('Location: /login');
        exit;
    }

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::markRead((int)($_POST['id'] ?? 0), Auth::user());

    $redirect = trim((string)($_POST['redirect'] ?? '/ops/notifications'));
    if ($redirect === '' || !str_starts_with($redirect, '/')) {
        $redirect = '/ops/notifications';
    }
    header('Location: ' . $redirect);
    exit;
});

$router->post('/ops/notifications/dismiss', function() {
    Auth::bootSession();
    if (!Auth::isLoggedIn()) {
        Auth::rememberIntendedUrl('/ops/notifications');
        header('Location: /login');
        exit;
    }

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::dismiss((int)($_POST['id'] ?? 0), Auth::user());

    $redirect = trim((string)($_POST['redirect'] ?? '/ops/notifications'));
    if ($redirect === '' || !str_starts_with($redirect, '/')) {
        $redirect = '/ops/notifications';
    }
    header('Location: ' . $redirect);
    exit;
});

$router->post('/ops/notifications/read-all', function() {
    Auth::bootSession();
    if (!Auth::isLoggedIn()) {
        Auth::rememberIntendedUrl('/ops/notifications');
        header('Location: /login');
        exit;
    }

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    NotificationService::markAllRead(Auth::user());

    $redirect = trim((string)($_POST['redirect'] ?? '/ops/notifications'));
    if ($redirect === '' || !str_starts_with($redirect, '/')) {
        $redirect = '/ops/notifications';
    }
    header('Location: ' . $redirect);
    exit;
});

$router->post('/ops/handoff-board/assign-owner', function() {
    acl_require('ops.handoff.assign_owner', '/ops/handoff-board');

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    if (!HandoffBoardService::isAvailable()) {
        header('Location: /me', true, 302);
        exit;
    }

    HandoffBoardController::assignOwner($_POST);
    return null;
});

$router->post('/ops/handoff-board/escalate', function() {
    acl_require('ops.handoff.escalate', '/ops/handoff-board');

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
    if (!HandoffBoardService::isAvailable()) {
        header('Location: /me', true, 302);
        exit;
    }

    HandoffBoardController::escalate($_POST);
    return null;
});

// ------------------------------------------------------
// One-time setup (first Admin only)
// ------------------------------------------------------
$router->get('/setup', function() use ($view) {
    Auth::bootSession();

    if (!Auth::noAdminExists()) {
        if (Auth::isLoggedIn()) {
            header("Location: /admin/setup");
            exit;
        }
        $view->render('Base::auth/setup.php', [
            'pageTitle' => 'Continue Setup',
            'mode' => 'resume',
            'loginUrl' => '/login?redirect=' . rawurlencode('/admin/setup/onboarding'),
            'setupStatus' => SetupStatusService::publicSnapshot([
                'mode' => 'resume',
                'current_step' => 'resume',
                'login_url' => '/login?redirect=' . rawurlencode('/admin/setup/onboarding'),
                'current_page_url' => '/setup',
                'csrf' => Auth::csrfToken(),
            ]),
        ]);
        return null;
    }

    $view->render('Base::auth/setup.php', [
        'pageTitle' => 'Setup Admin',
        'mode' => 'initial',
        'loginUrl' => '/login',
        'setupStatus' => SetupStatusService::publicSnapshot([
            'mode' => 'initial',
            'current_step' => 'welcome',
            'login_url' => '/login',
            'current_page_url' => '/setup',
            'csrf' => Auth::csrfToken(),
        ]),
    ]);
    return null;
});

$router->post('/setup', function() {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    $setupIp = '';
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        $setupIp = preg_replace('/[^a-fA-F0-9\.:]/', '', (string)($parts[0] ?? '')) ?? '';
    }
    if ($setupIp === '') {
        $setupIp = preg_replace('/[^a-fA-F0-9\.:]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? '')) ?? '';
    }
    $setupRateKey = 'setup_claim_' . md5($setupIp);
    if (!RateLimitService::attemptWithinLimit($setupRateKey, 6, 900)) {
        http_response_code(429);
        echo 'Too many setup attempts. Try again later.';
        exit;
    }

    if (!Auth::noAdminExists()) {
        if (Auth::isLoggedIn()) {
            header("Location: /admin/setup");
            exit;
        }
        header("Location: /setup");
        exit;
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new \RuntimeException('Invalid email');
    }
    if (strlen($pass) < 10) {
        throw new \RuntimeException('Password must be at least 10 chars');
    }

    $hash = password_hash($pass, PASSWORD_DEFAULT);

    $existingRow = DB::fetchOne('SELECT id FROM users WHERE email=? LIMIT 1', [strtolower($email)]);
    if (is_array($existingRow)) {
        $setupUserId = (int)$existingRow['id'];
        DB::query(
            'UPDATE users SET password_hash=?, role=?, twofa_enabled=0, twofa_secret=NULL WHERE id=? LIMIT 1',
            [$hash, 'Platform Operations', $setupUserId]
        );
    } else {
        DB::query(
            "INSERT INTO users (email,password_hash,role,twofa_enabled,twofa_secret) VALUES (?,?,?,?,?)",
            [strtolower($email), $hash, 'Platform Operations', 0, null]
        );
        $newRow = DB::fetchOne('SELECT id FROM users WHERE email=? LIMIT 1', [strtolower($email)]);
        $setupUserId = (int)($newRow['id'] ?? 0);
    }

    // Provision platform_admin governance defaults for the first administrator
    if ($setupUserId > 0) {
        // Set authority_role and role_tier if columns exist
        $setParts = [];
        $setParams = [];
        $cols = DB::fetchAll("SHOW COLUMNS FROM users LIKE 'authority_role'");
        if (!empty($cols)) { $setParts[] = 'authority_role = ?'; $setParams[] = 'platform_admin'; }
        $cols2 = DB::fetchAll("SHOW COLUMNS FROM users LIKE 'role_tier'");
        if (!empty($cols2)) { $setParts[] = 'role_tier = ?'; $setParams[] = 'admin'; }
        $cols3 = DB::fetchAll("SHOW COLUMNS FROM users LIKE 'account_type'");
        if (!empty($cols3)) { $setParts[] = 'account_type = ?'; $setParams[] = 'platform_admin'; }
        if (!empty($setParts)) {
            $setParams[] = $setupUserId;
            DB::query('UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ? LIMIT 1', $setParams);
        }

        $assignmentTable = DB::fetchOne("SHOW TABLES LIKE 'user_dashboard_assignments'");
        if (is_array($assignmentTable) && $assignmentTable !== []) {
            DB::query(
                "INSERT INTO user_dashboard_assignments
                    (user_id, dashboard_type, default_app, default_landing_page, assigned_apps, access_profiles, permissions, dashboard_mode, default_app_mode, landing_mode, access_profiles_mode, module_visibility_mode, account_class, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'auto', 'auto', 'auto', 'auto', 'auto', ?, ?)
                 ON DUPLICATE KEY UPDATE
                    dashboard_type=VALUES(dashboard_type),
                    default_app=VALUES(default_app),
                    default_landing_page=VALUES(default_landing_page),
                    assigned_apps=VALUES(assigned_apps),
                    access_profiles=VALUES(access_profiles),
                    permissions=VALUES(permissions),
                    dashboard_mode=VALUES(dashboard_mode),
                    default_app_mode=VALUES(default_app_mode),
                    landing_mode=VALUES(landing_mode),
                    access_profiles_mode=VALUES(access_profiles_mode),
                    module_visibility_mode=VALUES(module_visibility_mode),
                    account_class=VALUES(account_class),
                    updated_by=VALUES(updated_by),
                    updated_at=NOW()",
                [
                    $setupUserId,
                    'platform_admin',
                    'platform',
                    '/',
                    'platform,manufacturing,sbaio',
                    'platform_administration',
                    'admin.tools.access,acl.manage',
                    'platform_operations',
                    strtolower($email),
                ]
            );
        }
    }

    unset($_SESSION['setup_email'], $_SESSION['setup_secret'], $_SESSION['setup_2fa_error']);
    header("Location: /login");
    exit;
});

$router->get('/setup/2fa', function() use ($view) {
    Auth::bootSession();

    if (empty($_SESSION['setup_email']) || empty($_SESSION['setup_secret'])) {
        if (Auth::noAdminExists()) {
            header("Location: /setup");
            exit;
        }
        if (Auth::isLoggedIn()) {
            header("Location: /admin/setup");
            exit;
        }
        header("Location: /setup");
        exit;
    }

    $email  = (string)$_SESSION['setup_email'];
    $secret = (string)$_SESSION['setup_secret'];
    $uri    = TOTP::provisioningUri(TOTP::defaultIssuer(), $email, $secret);
    $qrPayload = TwoFactorQrService::buildPayload($uri);
    $error = trim((string)($_SESSION['setup_2fa_error'] ?? ''));
    unset($_SESSION['setup_2fa_error']);

    $view->render('Base::auth/setup_2fa.php', [
        'pageTitle' => 'Setup 2FA',
        'email'     => $email,
        'secret'    => $secret,
        'uri'       => $uri,
        'error'     => $error,
        'qrImageSrc' => (string)($qrPayload['data_uri'] ?? ''),
        'qrImageError' => (string)($qrPayload['error'] ?? ''),
        'setupStatus' => SetupStatusService::publicSnapshot([
            'mode' => 'standalone_2fa',
            'current_step' => 'setup_2fa',
            'error' => $error,
            'csrf' => Auth::csrfToken(),
            'current_page_url' => '/setup/2fa',
        ]),
    ]);
    return null;
});

$router->get('/setup/2fa/qr', function() {
    Auth::bootSession();

    if (empty($_SESSION['setup_email']) || empty($_SESSION['setup_secret'])) {
        http_response_code(404);
        echo 'QR setup session not found.';
        return null;
    }

    $email  = (string)$_SESSION['setup_email'];
    $secret = (string)$_SESSION['setup_secret'];
    $uri    = TOTP::provisioningUri(TOTP::defaultIssuer(), $email, $secret);
    $qrPayload = TwoFactorQrService::buildPayload($uri);

    if (!empty($qrPayload['ok']) && (string)($qrPayload['png_bytes'] ?? '') !== '') {
        header('Content-Type: image/png');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        echo (string)$qrPayload['png_bytes'];
        return null;
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo trim((string)($qrPayload['error'] ?? '')) !== ''
        ? 'Unable to generate QR code: ' . (string)$qrPayload['error']
        : 'Unable to generate QR code.';
    return null;
});

$router->post('/setup/2fa', function() {
    Auth::bootSession();
    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    $setupIp = '';
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        $setupIp = preg_replace('/[^a-fA-F0-9\.:]/', '', (string)($parts[0] ?? '')) ?? '';
    }
    if ($setupIp === '') {
        $setupIp = preg_replace('/[^a-fA-F0-9\.:]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? '')) ?? '';
    }
    $setupEmail = strtolower(trim((string)($_SESSION['setup_email'] ?? 'unknown')));
    $setup2faRateKey = 'setup_2fa_' . md5($setupEmail . '|' . $setupIp);
    if (!RateLimitService::attemptWithinLimit($setup2faRateKey, 10, 900)) {
        $_SESSION['setup_2fa_error'] = 'Too many verification attempts. Try again later.';
        header('Location: /setup/2fa');
        exit;
    }

    $wizard = $_SESSION['setup_public_wizard'] ?? null;
    $isWizardFlow = is_array($wizard) && !empty($wizard['started']);
    $wizardStateToRestore = $isWizardFlow ? $wizard : null;
    $returnPath = $isWizardFlow ? '/setup?step=setup_2fa' : '/setup/2fa';

    try {
        $email  = strtolower(trim((string)($_SESSION['setup_email'] ?? '')));
        $secret = trim((string)($_SESSION['setup_secret'] ?? ''));
        $code   = preg_replace('/\D+/', '', (string)($_POST['code'] ?? '')) ?? '';

        if ($email === '' || $secret === '') {
            throw new \RuntimeException('Your 2FA setup session is missing. Return to the previous step and regenerate the setup secret.');
        }
        if (!TOTP::verify($secret, $code)) {
            throw new \RuntimeException('The verification code did not match. Enter the current 6-digit code from your authenticator app and try again.');
        }

        DB::query("UPDATE users SET twofa_enabled=1, twofa_secret=? WHERE email=? LIMIT 1", [$secret, $email]);

        $row = DB::fetchAll("SELECT id FROM users WHERE email=? LIMIT 1", [$email]);
        $id = (int)($row[0]['id'] ?? 0);
        if ($id <= 0) {
            throw new \RuntimeException('The administrator account could not be found after verification.');
        }

        // Ensure governance defaults are applied for the first admin
        $setParts2 = [];
        $setParams2 = [];
        $gc1 = DB::fetchAll("SHOW COLUMNS FROM users LIKE 'authority_role'");
        if (!empty($gc1)) { $setParts2[] = 'authority_role = ?'; $setParams2[] = 'platform_admin'; }
        $gc2 = DB::fetchAll("SHOW COLUMNS FROM users LIKE 'role_tier'");
        if (!empty($gc2)) { $setParts2[] = 'role_tier = ?'; $setParams2[] = 'admin'; }
        $gc3 = DB::fetchAll("SHOW COLUMNS FROM users LIKE 'account_type'");
        if (!empty($gc3)) { $setParts2[] = 'account_type = ?'; $setParams2[] = 'platform_admin'; }
        if (!empty($setParts2)) {
            $setParams2[] = $id;
            DB::query('UPDATE users SET ' . implode(', ', $setParts2) . ' WHERE id = ? LIMIT 1', $setParams2);
        }
        $asgTable = DB::fetchOne("SHOW TABLES LIKE 'user_dashboard_assignments'");
        if (is_array($asgTable) && $asgTable !== []) {
            DB::query(
                "INSERT INTO user_dashboard_assignments
                    (user_id, dashboard_type, default_app, default_landing_page, assigned_apps, access_profiles, permissions, dashboard_mode, default_app_mode, landing_mode, access_profiles_mode, module_visibility_mode, account_class, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'auto', 'auto', 'auto', 'auto', 'auto', ?, ?)
                 ON DUPLICATE KEY UPDATE
                    dashboard_type=VALUES(dashboard_type),
                    default_app=VALUES(default_app),
                    default_landing_page=VALUES(default_landing_page),
                    assigned_apps=VALUES(assigned_apps),
                    access_profiles=VALUES(access_profiles),
                    permissions=VALUES(permissions),
                    account_class=VALUES(account_class),
                    updated_by=VALUES(updated_by),
                    updated_at=NOW()",
                [
                    $id,
                    'platform_admin',
                    'platform',
                    '/',
                    'platform,manufacturing,sbaio',
                    'platform_administration',
                    'admin.tools.access,acl.manage',
                    'platform_operations',
                    $email,
                ]
            );
        }

        Auth::completeLogin($id);

        unset($_SESSION['setup_email'], $_SESSION['setup_secret'], $_SESSION['setup_2fa_error']);

        if ($isWizardFlow) {
            $wizardStateToRestore = is_array($wizardStateToRestore) ? $wizardStateToRestore : [];
            $wizardStateToRestore['started'] = true;
            $wizardStateToRestore['data'] = is_array($wizardStateToRestore['data'] ?? null) ? $wizardStateToRestore['data'] : [];
            $wizardStateToRestore['data']['two_fa_configured'] = 1;
            $wizardStateToRestore['current_step'] = 'verify';
            $wizardStateToRestore['last_visited_step'] = 'setup_2fa';
            $wizardStateToRestore['error'] = '';
            $wizardStateToRestore['notice'] = 'Two-factor authentication is configured. Review the final verification step to finish bootstrap.';
            $wizardStateToRestore['notice_type'] = '';
            $_SESSION['setup_public_wizard'] = $wizardStateToRestore;

            header("Location: /setup?step=verify");
            exit;
        }

        unset($_SESSION['setup_public_wizard']);

        header("Location: /admin/setup/onboarding");
        exit;
    } catch (\Throwable $e) {
        $message = trim((string)$e->getMessage());
        if ($message === '') {
            $message = 'Two-factor authentication could not be completed. Try again.';
        }

        if ($isWizardFlow) {
            $wizardStateToRestore = is_array($wizardStateToRestore) ? $wizardStateToRestore : [];
            $wizardStateToRestore['started'] = true;
            $wizardStateToRestore['current_step'] = 'setup_2fa';
            $wizardStateToRestore['last_visited_step'] = 'setup_2fa';
            $wizardStateToRestore['error'] = $message;
            $wizardStateToRestore['notice'] = '';
            $wizardStateToRestore['notice_type'] = '';
            $_SESSION['setup_public_wizard'] = $wizardStateToRestore;
        } else {
            $_SESSION['setup_2fa_error'] = $message;
        }

        header('Location: ' . $returnPath);
        exit;
    }
});

// ------------------------------------------------------
// Identity Recovery + Login + 2FA
// ------------------------------------------------------
$baseClientIp = static function (): string {
    $forwarded = trim((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        if (!empty($parts[0])) {
            return preg_replace('/[^a-fA-F0-9\.:]/', '', (string)$parts[0]) ?? '';
        }
    }

    return preg_replace('/[^a-fA-F0-9\.:]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? '')) ?? '';
};

$router->get('/forgot-password', function() use ($view) {
    Auth::bootSession();
    $noticeReady = !empty($_SESSION['forgot_password_notice_ready']);
    $message = $noticeReady ? (string)($_SESSION['forgot_password_notice'] ?? '') : '';
    $error = (string)($_SESSION['forgot_password_error'] ?? '');
    $email = (string)($_SESSION['forgot_password_email'] ?? '');
    unset(
        $_SESSION['forgot_password_notice_ready'],
        $_SESSION['forgot_password_notice'],
        $_SESSION['forgot_password_error'],
        $_SESSION['forgot_password_email']
    );

    $view->render('Base::auth/forgot_password.php', [
        'pageTitle' => 'Forgot Password',
        'message' => $message,
        'error' => $error,
        'email' => $email,
    ]);
    return null;
});

$router->post('/forgot-password', function() use ($baseClientIp) {
    Auth::bootSession();
    $rateKey = 'forgot_password_' . md5($baseClientIp());
    if (!RateLimitService::attemptWithinLimit($rateKey, 6, 900)) {
        $_SESSION['forgot_password_error'] = t('auth.reset_request_failed');
        header('Location: /forgot-password');
        exit;
    }

    $email = trim((string)($_POST['email'] ?? ''));
    $_SESSION['forgot_password_email'] = $email;
    unset($_SESSION['forgot_password_notice_ready'], $_SESSION['forgot_password_notice'], $_SESSION['forgot_password_error']);

    try {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['forgot_password_error'] = t('auth.reset_request_failed');
            header('Location: /forgot-password');
            exit;
        }

        $service = new PasswordResetService();
        $service->requestReset($email, $baseClientIp(), (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $_SESSION['forgot_password_notice_ready'] = 1;
        $_SESSION['forgot_password_notice'] = t('auth.reset_request_neutral');
        unset($_SESSION['forgot_password_email']);
    } catch (\Throwable $e) {
        $_SESSION['forgot_password_error'] = t('auth.reset_request_failed');
    }

    header('Location: /forgot-password');
    exit;
});

$router->get('/reset-password', function() use ($view, $baseClientIp) {
    Auth::bootSession();
    $token = trim((string)($_GET['token'] ?? ''));
    $service = new PasswordResetService();
    $record = $service->validateToken($token);

    if ($record === null && $token !== '') {
        \App\Services\IdentitySecurityLogService::log('password_reset_token_checked', null, '', $baseClientIp(), 'invalid');
    }

    $view->render('Base::auth/reset_password.php', [
        'pageTitle' => 'Reset Password',
        'token' => $token,
        'tokenValid' => $record !== null,
        'message' => (string)($_SESSION['reset_password_notice'] ?? ''),
        'error' => (string)($_SESSION['reset_password_error'] ?? ''),
        'policy' => $service->policy(),
    ]);
    unset($_SESSION['reset_password_notice'], $_SESSION['reset_password_error']);
    return null;
});

$router->post('/reset-password', function() use ($baseClientIp) {
    Auth::bootSession();
    $token = trim((string)($_POST['token'] ?? ''));
    $rateKey = 'reset_password_' . md5($baseClientIp() . '|' . $token);
    if (!RateLimitService::attemptWithinLimit($rateKey, 8, 900)) {
        $_SESSION['reset_password_error'] = t('auth.reset_request_failed');
        header('Location: /reset-password?token=' . urlencode($token));
        exit;
    }

    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['password_confirm'] ?? '');

    try {
        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        $service = new PasswordResetService();
        $service->completeReset($token, $password, $confirm, $baseClientIp());
        $_SESSION['auth_notice'] = t('auth.password_updated_notice');
        header('Location: /login');
        exit;
    } catch (\Throwable $e) {
        $_SESSION['reset_password_error'] = $e->getMessage();
        header('Location: /reset-password?token=' . urlencode($token));
        exit;
    }
});

$router->get('/login', function() use ($view) {
    Auth::bootSession();
    $redirect = trim((string)($_GET['redirect'] ?? ''));
    if ($redirect !== '') {
        Auth::rememberIntendedUrl($redirect);
    }
    $error = (string)($_SESSION['auth_error'] ?? '');
    $notice = (string)($_SESSION['auth_notice'] ?? '');
    $email = (string)($_SESSION['auth_email'] ?? '');
    unset($_SESSION['auth_error'], $_SESSION['auth_notice'], $_SESSION['auth_email']);

    $view->render('Base::auth/login.php', [
        'pageTitle' => 'Login',
        'error' => $error,
        'notice' => $notice,
        'email' => $email,
        'redirect' => Auth::intendedUrl('/'),
    ]);
    return null;
});

$router->post('/login', function() {
    Auth::bootSession();
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');
    $redirect = trim((string)($_POST['redirect'] ?? ''));

    // Basic rate limiting: max 10 attempts per 15 minutes per IP
    $ip = preg_replace('/[^a-fA-F0-9.:_]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $rateBucket = 'login_fails_' . md5($ip);
    $rateData   = $_SESSION[$rateBucket] ?? ['count' => 0, 'since' => time()];
    if ((time() - (int)$rateData['since']) > 900) {
        $rateData = ['count' => 0, 'since' => time()];
    }

    if ((int)$rateData['count'] >= 10) {
        $_SESSION['auth_error'] = __('auth.error_locked') ?: 'Account locked. Try again later.';
        $_SESSION['auth_email'] = $email;
        header("Location: /login");
        exit;
    }

    try {
        $loginIdentifier = $email;
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $usernameRows = DB::fetchAll(
                "SELECT email FROM users WHERE LOWER(COALESCE(username, ''))=? LIMIT 2",
                [strtolower($email)]
            );
            if (count($usernameRows) === 1) {
                $loginIdentifier = (string)($usernameRows[0]['email'] ?? $email);
            }
        }

        Auth::requireCsrf((string)($_POST['csrf'] ?? ''));
        Auth::rememberIntendedUrl($redirect);
        Auth::beginPasswordLogin($loginIdentifier, $pass);

        // Success — clear rate limit bucket
        unset($_SESSION[$rateBucket]);

        if (!empty($_SESSION['pending_2fa_user_id'])) {
            header("Location: /2fa");
            exit;
        }

        $next = Auth::consumeIntendedUrl('/');
        header('Location: ' . $next);
        exit;
    } catch (\Throwable $e) {
        // Increment rate limit counter
        $rateData['count'] = (int)$rateData['count'] + 1;
        $_SESSION[$rateBucket] = $rateData;

        // Normalize: never reveal whether email or password was wrong
        $message = __('auth.error_invalid') ?: 'Invalid email or password.';
        if (str_contains($e->getMessage(), 'locked') || str_contains($e->getMessage(), 'site locked')) {
            $message = __('auth.error_locked') ?: 'Account locked. Try again later.';
        }
        $_SESSION['auth_error'] = $message;
        $_SESSION['auth_email'] = $email;
        header("Location: /login");
        exit;
    }
});

$router->get('/2fa', function() use ($view) {
    Auth::bootSession();

    if (empty($_SESSION['pending_2fa_user_id'])) {
        if (Auth::isLoggedIn()) {
            $next = Auth::consumeIntendedUrl('/');
            header('Location: ' . $next);
            exit;
        }
        header("Location: /login");
        exit;
    }

    $view->render('Base::auth/2fa.php', [
        'pageTitle' => '2FA',
        'email'     => (string)($_SESSION['pending_2fa_email'] ?? ''),
        'redirect'  => Auth::intendedUrl('/'),
        'error'     => (string)($_SESSION['auth_2fa_error'] ?? ''),
    ]);
    unset($_SESSION['auth_2fa_error']);
    return null;
});

$router->post('/2fa', function() {
    Auth::bootSession();
    $ip = preg_replace('/[^a-fA-F0-9.:_]/', '', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    $pendingUser = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
    $rateKey = 'two_fa_' . md5($pendingUser . '|' . $ip);
    if (!RateLimitService::attemptWithinLimit($rateKey, 10, 900)) {
        $_SESSION['auth_error'] = __('auth.error_locked') ?: 'Account locked. Try again later.';
        header('Location: /login');
        exit;
    }

    Auth::requireCsrf((string)($_POST['csrf'] ?? ''));

    $code = trim((string)($_POST['code'] ?? ''));

    // Try recovery code path if code looks like a recovery code (10 hex chars, possibly XXXXX-XXXXX)
    $normalized = strtoupper(str_replace(['-', ' '], '', $code));
    if (strlen($normalized) === 10 && ctype_xdigit($normalized)) {
        $pendingUserId = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
        if ($pendingUserId > 0) {
            try {
                $svc = new \App\Services\MyAccountService();
                $svc->consumeRecoveryCode($pendingUserId, $normalized);
                Auth::completeLogin($pendingUserId);
                $next = Auth::consumeIntendedUrl('/');
                header('Location: ' . $next);
                exit;
            } catch (\Throwable $e) {
                $_SESSION['auth_2fa_error'] = 'Invalid or already used recovery code.';
                header('Location: /2fa');
                exit;
            }
        }
    }

    try {
        Auth::verify2fa($code);

        $next = Auth::consumeIntendedUrl('/');
        header('Location: ' . $next);
        exit;
    } catch (\Throwable $e) {
        $message = trim((string)$e->getMessage());
        if (
            str_contains(strtolower($message), 'invalid 2fa code')
            || str_contains(strtolower($message), 'no pending 2fa session')
            || str_contains(strtolower($message), '2fa not configured')
        ) {
            $message = (string)(__('auth.error_invalid_2fa') ?: 'Invalid authentication code. Please try again.');
        }
        if ($message === '') {
            $message = (string)(__('auth.error_invalid_2fa') ?: 'Invalid authentication code. Please try again.');
        }

        $_SESSION['auth_2fa_error'] = $message;
        header('Location: /2fa');
        exit;
    }
});

$router->get('/logout', function() {
    Auth::logout();
    header("Location: /login");
    exit;
});