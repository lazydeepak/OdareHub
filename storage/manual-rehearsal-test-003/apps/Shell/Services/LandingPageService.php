<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\Auth;
use App\Core\DB;

/**
 * Role-aware landing page resolver.
 *
 * Routes authenticated users to their designated entry surface:
 * - platform_admin        → /admin/{username}
 * - app_admin             → /admin/{username}  (shares admin layer with platform_admin)
 * - app_user              → /u/{username}/dashboard (operator workspace)
 * - tv_display            → /displays/{display_id} (readonly kiosk layer)
 * - unauthenticated       → /login
 *
 * This service centralizes landing logic so that:
 * 1. Root / can intelligently route based on user role
 * 2. Login redirect targets can go to appropriate surface
 * 3. New wrappers (display, etc.) can be added without scattered route logic
 */
final class LandingPageService
{
    /**
     * Resolve landing URL for current authenticated user.
     *
     * @return string Absolute path to landing surface (e.g., '/admin/lazydeepak', '/u/lazydeepak/dashboard')
     * @throws \RuntimeException If user is not authenticated or authority_role is invalid
     */
    public static function resolveLanding(): string
    {
        Auth::bootSession();

        if (!Auth::isLoggedIn()) {
            throw new \RuntimeException('User is not authenticated');
        }

        $user = Auth::user();
        $authorityRole = (string)($user->authority_role ?? $user['authority_role'] ?? '');

        if ($authorityRole === '') {
            throw new \RuntimeException('User has no authority_role set');
        }

        // Get username via WorkspaceWrapperRegistry
        $username = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($user->username ?? $user['username'] ?? ''),
            'email' => (string)($user->email ?? $user['email'] ?? ''),
        ]);

        if ($username === '') {
            throw new \RuntimeException('Could not resolve username from user identity');
        }

        // Route by account type
        match ($authorityRole) {
            'platform_admin', 'app_admin' => $landing = '/admin/' . rawurlencode($username),
            'app_user' => $landing = self::resolveOperatorLanding($user, $username),
            'tv_display' => $landing = self::resolveTVDisplay($user),
            default => throw new \RuntimeException("Unknown authority_role: $authorityRole"),
        };

        return $landing;
    }

    /**
     * Resolve landing URL for app_user, respecting their default_view preference.
     *
     * @param object|array<string,mixed> $user
     */
    private static function resolveOperatorLanding($user, string $username): string
    {
        $base = '/u/' . rawurlencode($username);

        // Valid default_view values → sub-path segments
        $viewMap = [
            'dashboard'  => '/dashboard',
            'production' => '/production',
            'dispatch'   => '/dispatch',
            'machines'   => '/machines',
            'coverage'   => '/coverage',
            'materials'  => '/materials',
            'handoff'    => '/handoff',
        ];

        try {
            $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
            if ($userId > 0) {
                $row = DB::fetchOne(
                    "SELECT pref_value FROM operator_preferences
                     WHERE user_id = ? AND pref_key = 'default_view' LIMIT 1",
                    [$userId]
                );
                $pref = trim((string)($row['pref_value'] ?? ''));
                if ($pref !== '' && isset($viewMap[$pref])) {
                    return $base . $viewMap[$pref];
                }
            }
        } catch (\Throwable) {
            // Non-fatal — fall through to workspace_profile check.
        }

        // Check workspace_profile landing_route (profile-level default, lower priority than user prefs)
        try {
            $userId = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
            if ($userId > 0) {
                $ctx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext(
                    is_array($user) ? $user : (array)$user
                );
                $wpLanding = (string)(($ctx['workspace_profile'] ?? [])['landing_route'] ?? '');
                if ($wpLanding !== '' && str_starts_with($wpLanding, '/')) {
                    // Substitute {user} placeholder so profiles can store template routes.
                    $wpLanding = str_replace('{user}', rawurlencode($username), $wpLanding);
                    return $wpLanding;
                }
            }
        } catch (\Throwable) {
            // Non-fatal — fall through to default.
        }

        return $base . '/dashboard';
    }

    /**
     * Resolve display URL for TV/Display account.
     *
     * TV displays can be:
     * 1. User-specific readonly display: /displays/user/{username}
     * 2. Device-specific display: /displays/device/{device_id}
     * 3. Default display dashboard: /displays
     *
     * @param object|array<string,mixed> $user
     */
    private static function resolveTVDisplay($user): string
    {
        // If user has a linked display device ID, use that
        $displayId = (string)($user->display_device_id ?? $user['display_device_id'] ?? '');
        if ($displayId !== '') {
            return '/displays/device/' . rawurlencode($displayId);
        }

        // Fall back to user-specific display if no device is set
        $username = (string)($user->username ?? $user['username'] ?? '');
        if ($username !== '') {
            return '/displays/user/' . rawurlencode($username);
        }

        // Default display dashboard
        return '/displays';
    }

    /**
     * Get landing URL suitable for use as "intended_url" in auth flows.
     *
     * Use this after login to redirect user to their appropriate workspace.
     * Automatically handles missing auth state by returning '/login'.
     *
     * @return string Safe landing path or '/login' if not authenticated
     */
    public static function getLandingOrLogin(): string
    {
        try {
            return self::resolveLanding();
        } catch (\RuntimeException) {
            return '/login';
        }
    }
}
