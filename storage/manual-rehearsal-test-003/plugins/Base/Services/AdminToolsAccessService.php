<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

use App\Core\Auth;

final class AdminToolsAccessService
{
    /**
     * Check if user has admin tools access and redirect/exit if not.
     * Ensures user is authenticated, is owner, and has access to admin tools.
     *
     * @return void Exits with 403 if access denied, otherwise continues
     */
    public static function requireAdminToolsAccess(): void
    {
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl();
            header('Location: /login');
            exit;
        }

        Auth::requireOwner();
        $u = Auth::user();
        if (!self::canAccessAdminTools($u)) {
            http_response_code(403);
            echo 'Access denied.';
            exit;
        }
    }

    /**
     * Check if a user has permission to access admin tools.
     *
     * @param array|null $user User array with id, role, etc.
     * @return bool True if user can access admin tools, false otherwise
     */
    public static function canAccessAdminTools(?array $user = null): bool
    {
        if (!is_array($user) || empty($user)) {
            return false;
        }

        // Check if user has admin tools permission via acl_require if available
        if (function_exists('acl_require')) {
            try {
                // Attempt to check permission; if it throws, user doesn't have access
                acl_require('admin.tools.access');
                return true;
            } catch (\Throwable $e) {
                return false;
            }
        }

        // Fallback: check if user is owner/admin
        $role = strtolower(trim((string)($user['role'] ?? '')));
        return in_array($role, ['admin', 'owner', 'platform_admin', 'app_admin'], true);
    }
}
