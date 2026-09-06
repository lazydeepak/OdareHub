<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Unified Access Guard System
 *
 * Provides role-based access control with consistent permission checks
 * across the application. Replaces scattered Auth::requireX() calls.
 *
 * Usage:
 *   AccessGuard::require('admin');              // Require platform_admin role
 *   AccessGuard::require('acl_manager');        // Require ACL management permission
 *   AccessGuard::require('admin_tools');        // Require admin tools access
 *   AccessGuard::require('permission.key');     // Require specific ACL permission
 *   AccessGuard::check('admin');                // Check without throwing
 */
final class AccessGuard
{
    /**
     * Role and permission definitions.
     * Maps guard names to their validation logic.
     */
    private const GUARDS = [
        'admin' => 'requireAdmin',
        'owner' => 'requireOwner',
        'acl_manager' => 'requireAclManager',
        'admin_tools' => 'requireAdminTools',
        'logged_in' => 'requireLoggedIn',
    ];

    /**
     * Require a specific guard/role/permission.
     * Exits with 403 if access denied.
     *
     * @param string $guard The guard name (e.g., 'admin', 'acl_manager', 'permission.key')
     * @param string|null $intendedUrl Optional URL to remember before redirect to login
     * @return void Exits if access denied, otherwise continues
     */
    public static function require(string $guard, ?string $intendedUrl = null): void
    {
        Auth::bootSession();

        if (!self::check($guard)) {
            if (!Auth::isLoggedIn()) {
                if ($intendedUrl !== null) {
                    Auth::rememberIntendedUrl($intendedUrl);
                }
                header('Location: /login', true, 302);
                exit;
            }

            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }

    /**
     * Check if current user has access to a guard/role/permission.
     * Returns false if not authorized (does not exit).
     *
     * @param string $guard The guard name
     * @return bool True if authorized, false otherwise
     */
    public static function check(string $guard): bool
    {
        Auth::bootSession();

        if (!Auth::isLoggedIn()) {
            return false;
        }

        // Built-in guards
        if (isset(self::GUARDS[$guard])) {
            $method = self::GUARDS[$guard];
            return self::$method();
        }

        // ACL permission checks (e.g., 'ops.handoff.view', 'acl.manage')
        if (strpos($guard, '.') !== false) {
            return self::checkAclPermission($guard);
        }

        return false;
    }

    /**
     * Require user to be logged in.
     */
    private static function requireLoggedIn(): bool
    {
        return Auth::isLoggedIn();
    }

    /**
     * Require platform admin or app admin role.
     */
    private static function requireAdmin(): bool
    {
        try {
            Auth::requireAdmin();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Require account owner (same as requireAdmin for now).
     */
    private static function requireOwner(): bool
    {
        try {
            Auth::requireOwner();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Require ACL management permission.
     */
    private static function requireAclManager(): bool
    {
        return self::checkAclPermission('acl.manage');
    }

    /**
     * Require admin tools access.
     */
    private static function requireAdminTools(): bool
    {
        $user = Auth::user();
        if (!is_array($user)) {
            return false;
        }

        // Use the AdminToolsAccessService if available
        if (class_exists('\\Plugins\\Base\\Services\\AdminToolsAccessService')) {
            return \Plugins\Base\Services\AdminToolsAccessService::canAccessAdminTools($user);
        }

        // Fallback: check if user is admin
        $role = strtolower(trim((string)($user['role'] ?? '')));
        return in_array($role, ['admin', 'owner', 'platform_admin', 'app_admin'], true);
    }

    /**
     * Check if user has a specific ACL permission.
     *
     * @param string $permissionKey The permission key (e.g., 'acl.manage', 'ops.handoff.view')
     * @return bool True if user has permission, false otherwise
     */
    private static function checkAclPermission(string $permissionKey): bool
    {
        // Use acl_require if available
        if (function_exists('acl_require')) {
            try {
                acl_require($permissionKey);
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }

        // Fallback: check if user is admin
        try {
            Auth::requireAdmin();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get the current user's role/authority.
     *
     * @return string The user's authority role (e.g., 'platform_admin', 'operator')
     */
    public static function role(): string
    {
        Auth::bootSession();
        $user = Auth::user();

        if (!is_array($user)) {
            return 'guest';
        }

        return strtolower(trim((string)($user['authority_role'] ?? $user['role'] ?? 'guest')));
    }

    /**
     * Get the current user's account type.
     *
     * @return string The user's account type (e.g., 'platform_admin', 'operator')
     */
    public static function accountType(): string
    {
        Auth::bootSession();
        $user = Auth::user();

        if (!is_array($user)) {
            return 'guest';
        }

        return strtolower(trim((string)($user['account_type'] ?? $user['authority_role'] ?? $user['role'] ?? 'guest')));
    }

    /**
     * Get the current user data.
     *
     * @return array<string, mixed>|null User array or null if not logged in
     */
    public static function user(): ?array
    {
        Auth::bootSession();
        return Auth::user() ?? null;
    }
}
