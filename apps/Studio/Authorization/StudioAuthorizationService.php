<?php
declare(strict_types=1);

namespace Apps\Studio\Authorization;

final class StudioAuthorizationService
{
    /**
     * @param array<string,mixed> $context
     */
    public static function can(string $entity, string $action, array $context): bool
    {
        $matrix = self::permissionMatrix();
        $entityKey = strtolower(trim($entity));
        $actionKey = strtolower(trim($action));

        if ($entityKey === '' || $actionKey === '') {
            return false;
        }

        $roles = $matrix[$entityKey][$actionKey] ?? null;
        if (!is_array($roles) || $roles === []) {
            return false;
        }

        $role = self::resolveRole($context);
        return in_array($role, $roles, true);
    }

    /**
     * @param array<string,mixed> $context
     */
    public static function resolveRole(array $context): string
    {
        $user = is_array($context['user'] ?? null) ? $context['user'] : [];

        $role = strtolower(trim((string)($user['role'] ?? '')));
        if ($role === '') {
            $authorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
            if (in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
                $role = 'admin';
            } elseif ($authorityRole === 'app_user') {
                $role = 'operator';
            }
        }

        if (!in_array($role, ['admin', 'manager', 'operator', 'viewer'], true)) {
            $role = 'viewer';
        }

        return $role;
    }

    /** @return array<string,mixed> */
    private static function permissionMatrix(): array
    {
        /** @var array<string,mixed> $matrix */
        $matrix = require __DIR__ . '/PermissionMatrix.php';
        return $matrix;
    }
}
