<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private const INTENDED_URL_SESSION_KEY = 'auth_intended_url';
    private static ?bool $hasAccountStatusColumn = null;
    private static ?bool $hasAuthorityRoleColumn = null;
    private static ?bool $hasRoleTierColumn = null;
    private static ?bool $hasAuthSessionVersionColumn = null;
    private static ?bool $hasUsernameColumn = null;

    // Optional owner lock. Leave empty to allow any Admin account.
    public const OWNER_EMAIL = '';

    // ✅ How long a "recent 2FA" remains valid for dangerous actions
    public const RECENT_2FA_TTL_SECONDS = 300; // 5 minutes

    public static function bootSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
                || (strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            // Keep secure cookies on HTTPS, but allow localhost HTTP development.
            ini_set('session.cookie_secure', $isHttps ? '1' : '0');
            ini_set('session.cookie_samesite', 'Lax');
            ini_set('session.gc_maxlifetime', '7200');

            $sessionName = trim((string)getenv('ERP_SESSION_NAME'));
            if ($sessionName !== '' && preg_match('/^[A-Za-z0-9_-]{3,40}$/', $sessionName)) {
                session_name($sessionName);
            }

            session_start();
        }

        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
    }

    public static function csrfToken(): string
    {
        self::bootSession();
        return (string)($_SESSION['csrf'] ?? '');
    }

    public static function requireCsrf(string $token, ?string $returnPath = null): void
    {
        self::bootSession();
        if (!hash_equals((string)($_SESSION['csrf'] ?? ''), (string)$token)) {
            self::renderErrorResponse(
                419,
                'Invalid or expired form token.',
                'Refresh the page and retry the action. Your session may have expired.',
                $returnPath ?? self::currentRequestPath()
            );
        }
    }

    public static function isLoggedIn(): bool
    {
        self::bootSession();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0 || !empty($_SESSION['pending_2fa_user_id'])) {
            return false;
        }

        if (!self::hasAccountStatusColumn()) {
            return true;
        }

        $authVersionExpr = self::hasAuthSessionVersionColumn() ? ', auth_session_version' : '';
        $row = DB::fetchOne('SELECT account_status' . $authVersionExpr . ' FROM users WHERE id=? LIMIT 1', [$userId]);
        $status = strtolower(trim((string)($row['account_status'] ?? 'active')));
        if ($row && $status === 'active') {
            if (self::hasAuthSessionVersionColumn()) {
                $sessionVersion = (int)($_SESSION['auth_session_version'] ?? 0);
                $dbVersion = (int)($row['auth_session_version'] ?? 1);
                if ($sessionVersion > 0 && $dbVersion !== $sessionVersion) {
                    self::logout();
                    return false;
                }
            }
            return true;
        }

        self::logout();
        return false;
    }

    public static function user(): ?array
    {
        self::bootSession();
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id <= 0 || !self::isLoggedIn()) return null;

        $usernameExpr = self::hasUsernameColumn() ? 'username' : "'' AS username";
        $authorityRoleExpr = self::hasAuthorityRoleColumn() ? 'authority_role' : "'' AS authority_role";
        $roleTierExpr = self::hasRoleTierColumn() ? 'role_tier' : "'' AS role_tier";
        $authVersionExpr = self::hasAuthSessionVersionColumn() ? 'auth_session_version' : '1 AS auth_session_version';
        $sql = "SELECT id,email,{$usernameExpr},role,{$authorityRoleExpr},{$roleTierExpr},twofa_enabled,{$authVersionExpr} FROM users WHERE id=? LIMIT 1";
        $rows = DB::fetchAll($sql, [$id]);
        return $rows[0] ?? null;
    }

    private static function hasUsernameColumn(): bool
    {
        if (self::$hasUsernameColumn !== null) {
            return self::$hasUsernameColumn;
        }

        try {
            $row = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'username'");
            self::$hasUsernameColumn = is_array($row) && $row !== [];
        } catch (\Throwable $e) {
            self::$hasUsernameColumn = false;
        }

        return self::$hasUsernameColumn;
    }

    private static function roleSlug(?string $role): string
    {
        $raw = strtolower(trim((string)$role));
        if ($raw === '') {
            return '';
        }
        $flat = preg_replace('/[^a-z0-9]+/', '', $raw) ?? '';
        return $flat;
    }

    private static function isAdminLikeRole(?string $role): bool
    {
        $slug = self::roleSlug($role);
        return in_array($slug, ['admin', 'itadmin', 'itadministrator', 'sysadmin', 'systemadmin', 'systemadministrator'], true);
    }

    private static function isPlatformAdmin(?array $user): bool
    {
        $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($authorityRole === 'platform_admin') {
            return true;
        }

        return self::isAdminLikeRole((string)($user['role'] ?? ''));
    }

    private static function ownerEmail(): string
    {
        $env = trim((string)getenv('ERP_OWNER_EMAIL'));
        if ($env !== '') return strtolower($env);
        return strtolower((string)self::OWNER_EMAIL);
    }

    // ✅ Whole-site lock: must be logged in AND must be OWNER_EMAIL
    public static function requireOwner(): void
    {
        $u = self::user();
        if (!$u) {
            self::rememberIntendedUrl();
            header("Location: /login");
            exit;
        }

        $owner = self::ownerEmail();
        if ($owner !== '' && strtolower((string)$u['email']) !== $owner) {
            self::logout();
            http_response_code(403);
            echo "Access denied (site locked).";
            exit;
        }
    }

    // ✅ Admin gate (kept), also enforces owner lock
    public static function requireAdmin(): void
    {
        self::bootSession();
        $u = self::user();
        if (!$u) {
            self::rememberIntendedUrl();
            header('Location: /login');
            exit;
        }

        $owner = self::ownerEmail();
        if ($owner !== '' && strtolower((string)($u['email'] ?? '')) !== $owner) {
            self::logout();
            self::renderErrorResponse(403, 'Access denied (site locked).');
        }

        if (!self::isPlatformAdmin($u)) {
            self::renderErrorResponse(403, 'Access denied.');
        }
    }

    public static function requireRouteAccess(?string $intendedUrl = null, string $method = 'GET'): void
    {
        self::bootSession();
        if (!self::isLoggedIn()) {
            self::rememberIntendedUrl($intendedUrl);
            header('Location: /login');
            exit;
        }

        if (function_exists('base_enforce_assignment_access')) {
            $path = parse_url($intendedUrl ?: (string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
            base_enforce_assignment_access($path, $method);
        }
    }


    /**
     * Require login AND the given app in assigned_apps (or platform admin).
     * Replaces requireAdmin() for normal app-level routes under simplified access model.
     */
    public static function requireAppAccess(string $appKey, ?string $intendedUrl = null): void
    {
        self::bootSession();
        $u = self::user();
        if (!$u) {
            self::rememberIntendedUrl($intendedUrl);
            header('Location: /login');
            exit;
        }

        if (self::isPlatformAdmin($u)) {
            return;
        }

        // tv_display users are jailed in /displays/* — they must never access app routes
        // even if the app appears in their assigned_apps (which is a kiosk data hint only).
        $authorityRole = strtolower(trim((string)($u['authority_role'] ?? '')));
        if ($authorityRole === 'tv_display') {
            self::renderErrorResponse(403, 'Access denied.');
        }

        if (class_exists('\\Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
            $ctx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext($u);
            $assigned = array_values((array)($ctx['assigned_apps'] ?? []));
            if (in_array(strtolower(trim($appKey)), $assigned, true)) {
                return;
            }
        }

        self::renderErrorResponse(403, 'Access denied.');
    }

    public static function rememberIntendedUrl(?string $url = null): void
    {
        self::bootSession();
        $candidate = self::sanitizeRedirectTarget($url ?? (string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($candidate !== null) {
            $_SESSION[self::INTENDED_URL_SESSION_KEY] = $candidate;
        }
    }

    public static function intendedUrl(string $fallback = '/'): string
    {
        self::bootSession();
        $stored = self::sanitizeRedirectTarget((string)($_SESSION[self::INTENDED_URL_SESSION_KEY] ?? ''));
        return $stored ?? $fallback;
    }

    public static function consumeIntendedUrl(string $fallback = '/'): string
    {
        self::bootSession();
        $target = self::intendedUrl($fallback);
        unset($_SESSION[self::INTENDED_URL_SESSION_KEY]);
        return $target;
    }

    private static function sanitizeRedirectTarget(string $target): ?string
    {
        $target = trim($target);
        if ($target === '' || !str_starts_with($target, '/')) {
            return null;
        }
        if (str_starts_with($target, '//')) {
            return null;
        }
        return $target;
    }

    private static function currentRequestPath(): ?string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $query = trim((string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_QUERY) ?? ''));
        $target = $path;
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return self::sanitizeRedirectTarget($target);
    }

    private static function renderErrorResponse(int $statusCode, string $title, string $detail = '', ?string $returnPath = null): void
    {
        http_response_code($statusCode);
        header('Content-Type: text/html; charset=UTF-8');

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeDetail = htmlspecialchars($detail, ENT_QUOTES, 'UTF-8');
        $safeReturnPath = self::sanitizeRedirectTarget((string)$returnPath);

        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . $safeTitle . '</title></head><body style="font-family:system-ui,-apple-system,sans-serif;padding:32px;line-height:1.5">';
        echo '<h1 style="margin-top:0">' . $safeTitle . '</h1>';
        if ($safeDetail !== '') {
            echo '<p>' . $safeDetail . '</p>';
        }
        if ($safeReturnPath !== null) {
            echo '<p><a href="' . htmlspecialchars($safeReturnPath, ENT_QUOTES, 'UTF-8') . '">Return</a></p>';
        }
        echo '</body></html>';
        exit;
    }

    // ✅ For dangerous changes: require 2FA within TTL
    public static function requireRecent2fa(?int $ttlSeconds = null): void
    {
        self::requireAdmin();
        self::bootSession();

        $ttl = $ttlSeconds ?? self::RECENT_2FA_TTL_SECONDS;
        $last = (int)($_SESSION['last_2fa_at'] ?? 0);

        if ($last <= 0 || (time() - $last) > $ttl) {
            // Require fresh 2FA before system actions
            $_SESSION['force_2fa_reason'] = 'system_change';
            header("Location: /2fa");
            exit;
        }
    }

    public static function noAdminExists(): bool
    {
        $conditions = [
            "LOWER(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(role, '')), ' ', ''), '-', ''), '_', '')) IN ('admin', 'itadmin', 'itadministrator', 'sysadmin', 'systemadmin', 'systemadministrator', 'platformoperations')",
        ];
        if (self::hasAuthorityRoleColumn()) {
            array_unshift($conditions, "COALESCE(NULLIF(TRIM(authority_role), ''), '') = 'platform_admin'");
        }

        $rows = DB::fetchAll(
            "SELECT id
             FROM users
             WHERE " . implode(' OR ', $conditions) . "
             LIMIT 1"
        );
        return empty($rows);
    }

    public static function beginPasswordLogin(string $email, string $password): void
    {
        self::bootSession();
        \App\Services\PasswordResetService::ensureSchema();

        $email = trim(strtolower($email));
        $rows = DB::fetchAll("SELECT * FROM users WHERE email=? LIMIT 1", [$email]);
        $u = $rows[0] ?? null;
        if (!$u) throw new \RuntimeException('Invalid credentials');

        if (!password_verify($password, (string)$u['password_hash'])) {
            throw new \RuntimeException('Invalid credentials');
        }

        if (self::hasAccountStatusColumn()) {
            $status = strtolower(trim((string)($u['account_status'] ?? 'active')));
            if ($status !== '' && $status !== 'active') {
                throw new \RuntimeException('Account locked');
            }
        }

        $verificationStatus = strtolower(trim((string)($u['verification_status'] ?? 'ready')));
        $securityStatus = strtolower(trim((string)($u['security_status'] ?? 'standard')));
        if (in_array($verificationStatus, ['invite_pending', 'setup_pending'], true) || $securityStatus === 'password_setup_pending') {
            throw new \RuntimeException('Account locked');
        }

        // If site lock is enabled, only allow owner email.
        $owner = self::ownerEmail();
        if ($owner !== '' && strtolower((string)$u['email']) !== $owner) {
            throw new \RuntimeException('Access denied (site locked).');
        }

        if (!empty($u['twofa_enabled'])) {
            $_SESSION['pending_2fa_user_id'] = (int)$u['id'];
            $_SESSION['pending_2fa_email'] = (string)$u['email'];
            return;
        }

        self::completeLogin((int)$u['id']);
    }

    public static function verify2fa(string $code): void
    {
        self::bootSession();
        $pid = (int)($_SESSION['pending_2fa_user_id'] ?? 0);
        if ($pid <= 0) throw new \RuntimeException('No pending 2FA session');

        $rows = DB::fetchAll("SELECT id,email,twofa_enabled,twofa_secret FROM users WHERE id=? LIMIT 1", [$pid]);
        $u = $rows[0] ?? null;
        if (!$u || empty($u['twofa_enabled']) || empty($u['twofa_secret'])) {
            throw new \RuntimeException('2FA not configured');
        }

        // Enforce site lock again
        $owner = self::ownerEmail();
        if ($owner !== '' && strtolower((string)$u['email']) !== $owner) {
            throw new \RuntimeException('Access denied (site locked).');
        }

        if (!TOTP::verify((string)$u['twofa_secret'], $code)) {
            throw new \RuntimeException('Invalid 2FA code');
        }

        self::completeLogin((int)$u['id']);
        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email']);
    }

    public static function completeLogin(int $userId): void
    {
        self::completeLoginInPlace($userId);

        // 🚀 Safe post-login landing redirect
        if (class_exists('\\Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
            $user = self::user();
            if ($user) {
                $safeLanding = \Plugins\Base\Services\UserDashboardAssignmentService::safePostLoginLanding($user);
                header('Location: ' . $safeLanding, true, 302);
                exit;
            }
        }
    }

    /**
     * Complete login without issuing a redirect.
     * Use this for JSON/API endpoints (e.g. WebAuthn) where the caller
     * must control the response. Performs identical session mutation to
     * completeLogin() — no drift possible.
     */
    public static function completeLoginInPlace(int $userId): void
    {
        self::bootSession();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;

        if (self::hasAuthSessionVersionColumn()) {
            $row = DB::fetchOne('SELECT auth_session_version FROM users WHERE id=? LIMIT 1', [$userId]);
            $_SESSION['auth_session_version'] = (int)($row['auth_session_version'] ?? 1);
        }

        // Mark 2FA as "recent" after successful login (or after /2fa)
        $_SESSION['last_2fa_at'] = time();

        unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email']);
    }

    public static function logout(): void
    {
        self::bootSession();
        unset($_SESSION[self::INTENDED_URL_SESSION_KEY]);
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => (string)($params['path'] ?? '/'),
                'domain' => (string)($params['domain'] ?? ''),
                'secure' => (bool)($params['secure'] ?? false),
                'httponly' => (bool)($params['httponly'] ?? true),
                'samesite' => (string)($params['samesite'] ?? 'Lax'),
            ]);
        }
        session_destroy();
    }

    private static function hasAccountStatusColumn(): bool
    {
        if (self::$hasAccountStatusColumn !== null) {
            return self::$hasAccountStatusColumn;
        }

        try {
            $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'account_status'");
            self::$hasAccountStatusColumn = (bool)$exists;
        } catch (\Throwable $e) {
            self::$hasAccountStatusColumn = false;
        }

        return self::$hasAccountStatusColumn;
    }

    private static function hasAuthSessionVersionColumn(): bool
    {
        if (self::$hasAuthSessionVersionColumn !== null) {
            return self::$hasAuthSessionVersionColumn;
        }

        try {
            $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'auth_session_version'");
            self::$hasAuthSessionVersionColumn = (bool)$exists;
        } catch (\Throwable) {
            self::$hasAuthSessionVersionColumn = false;
        }

        return self::$hasAuthSessionVersionColumn;
    }

    private static function hasAuthorityRoleColumn(): bool
    {
        if (self::$hasAuthorityRoleColumn !== null) {
            return self::$hasAuthorityRoleColumn;
        }

        try {
            $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'authority_role'");
            self::$hasAuthorityRoleColumn = (bool)$exists;
        } catch (\Throwable $e) {
            self::$hasAuthorityRoleColumn = false;
        }

        return self::$hasAuthorityRoleColumn;
    }

    private static function hasRoleTierColumn(): bool
    {
        if (self::$hasRoleTierColumn !== null) {
            return self::$hasRoleTierColumn;
        }

        try {
            $exists = DB::fetchOne("SHOW COLUMNS FROM users LIKE 'role_tier'");
            self::$hasRoleTierColumn = (bool)$exists;
        } catch (\Throwable $e) {
            self::$hasRoleTierColumn = false;
        }

        return self::$hasRoleTierColumn;
    }
}
