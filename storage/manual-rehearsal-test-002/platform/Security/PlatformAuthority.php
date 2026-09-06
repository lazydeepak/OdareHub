<?php
declare(strict_types=1);

namespace Platform\Security;

final class PlatformAuthority
{
    /**
     * Verify that the given actor is a platform admin.
     */
    public static function canManageOwnerWorkspace(?array $actor): bool
    {
        return self::isPlatformAdmin($actor);
    }

    /**
     * Verify that the given actor can manage engineering workspaces.
     */
    public static function canManageEngineeringWorkspaces(?array $actor): bool
    {
        return self::isPlatformAdmin($actor);
    }

    /**
     * Resolve the current actor context.
     *
     * @return array<string,mixed>|null
     */
    public static function resolveCurrentActor(): ?array
    {
        if (!class_exists('App\Core\Auth') || !\App\Core\Auth::isLoggedIn()) {
            return null;
        }
        $user = \App\Core\Auth::user();
        if ($user === null || $user === []) {
            return null;
        }
        if (!function_exists('platform_user_context_contract')) {
            require_once APP_ROOT . '/apps/Platform/bootstrap.php';
        }
        return platform_user_context_contract()->resolveUserContext($user);
    }

    private static function isPlatformAdmin(?array $actor): bool
    {
        if ($actor === null || $actor === []) {
            return false;
        }
        $role = strtolower(trim((string)($actor['authority_role'] ?? 'app_user')));
        return $role === 'platform_admin';
    }
}
