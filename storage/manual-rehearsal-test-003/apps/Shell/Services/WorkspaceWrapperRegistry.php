<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * WorkspaceWrapperRegistry
 *
 * Central registry for shell wrapper containers.
 *
 * Canonical wrapper-to-address mapping:
 *   admin   → /admin/{username}/*, /apps/*, /ops/*
 *   u       → /u/{username}/*
 *   display → /displays/*
 *
 * Address and wrapper are separate concerns. Wrapper chrome is route-driven,
 * not role-driven. Operators are jailed in /u/*; /apps/* and /ops/* are
 * admin territory regardless of who visits them.
 */
final class WorkspaceWrapperRegistry
{
    public const WRAPPER_ADMIN = 'admin';
    public const WRAPPER_U = 'u';
    public const WRAPPER_DISPLAY = 'display';

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function all(): array
    {
        return [
            self::WRAPPER_ADMIN => [
                'id' => self::WRAPPER_ADMIN,
                'label' => 'admin workspace wrapper',
                'home_template' => '/admin/{username}',
                // Canonical prefixes for documentation; runtime uses is_default catch-all.
                // Admin wrapper = every address that is NOT /u/* (operator) or /displays/* (kiosk).
                'route_prefixes' => ['/admin', '/ops', '/apps'],
                'is_default' => true,
            ],
            self::WRAPPER_U => [
                'id' => self::WRAPPER_U,
                'label' => '/u style container',
                'home_template' => '/u/{username}',
                'route_prefixes' => ['/u'],
            ],
            self::WRAPPER_DISPLAY => [
                'id' => self::WRAPPER_DISPLAY,
                'label' => 'readonly display/kiosk wrapper',
                'home_template' => '/displays',
                'route_prefixes' => ['/displays'],
            ],
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function definition(string $wrapperId): ?array
    {
        $all = self::all();
        return $all[$wrapperId] ?? null;
    }

    public static function exists(string $wrapperId): bool
    {
        return self::definition($wrapperId) !== null;
    }

    public static function homePath(string $wrapperId, string $username = ''): string
    {
        $def = self::definition($wrapperId);
        if ($def === null) {
            return '/';
        }

        $template = (string)($def['home_template'] ?? '/');
        if (str_contains($template, '{username}')) {
            $encoded = rawurlencode(trim($username));
            if ($encoded === '') {
                return '/u';
            }
            return str_replace('{username}', $encoded, $template);
        }

        return $template;
    }

    public static function wrapperForPath(string $path): ?string
    {
        $clean = trim($path);
        if ($clean === '') {
            return null;
        }

        $defaultWrapper = null;
        foreach (self::all() as $wrapperId => $def) {
            if (!empty($def['is_default'])) {
                $defaultWrapper = $wrapperId;
            }
            foreach ((array)($def['route_prefixes'] ?? []) as $prefix) {
                $p = (string)$prefix;
                if ($p !== '' && ($clean === $p || str_starts_with($clean, $p . '/'))) {
                    return $wrapperId;
                }
            }
        }

        // Admin wrapper is the catch-all: any non-empty path not claimed by /u/* or /displays/*.
        return $defaultWrapper;
    }

    /**
     * Resolve a default landing wrapper for a signed-in user context.
     *
     * @param array<string,mixed> $ctx
     */
    public static function defaultWrapperForContext(array $ctx): string
    {
        // Future-ready override hook: platform admins may set this explicitly later.
        $override = strtolower(trim((string)($ctx['landing_wrapper'] ?? '')));
        if (self::exists($override)) {
            return $override;
        }

        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));

        // Administrative account types share the role-filtered admin wrapper.
        if (in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
            return self::WRAPPER_ADMIN;
        }

        // Everyone else defaults to /u shell.
        return self::WRAPPER_U;
    }

    /**
     * Resolve landing path from user context + username.
     *
     * @param array<string,mixed> $ctx
     */
    public static function landingPath(array $ctx, string $username = ''): string
    {
        $wrapper = self::defaultWrapperForContext($ctx);
        return self::homePath($wrapper, self::normalizeHandle($username));
    }

    /**
     * Normalize user-facing /u handle to username style.
     * - prefers plain username token
     * - if email is passed, uses local-part before '@'
     */
    public static function normalizeHandle(string $raw): string
    {
        $value = strtolower(trim($raw));
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            $local = strstr($value, '@', true);
            $value = $local !== false ? $local : $value;
        }

        // Keep URL-safe handle characters only.
        $value = preg_replace('/[^a-z0-9._-]+/i', '-', $value) ?? '';
        $value = trim($value, '-._');

        return $value;
    }

    /**
     * Build /u handle from user identity fields.
     *
     * @param array<string,mixed> $identity
     */
    public static function handleFromIdentity(array $identity): string
    {
        $username = self::normalizeHandle((string)($identity['username'] ?? ''));
        if ($username !== '') {
            return $username;
        }

        $email = self::normalizeHandle((string)($identity['email'] ?? ''));
        if ($email !== '') {
            return $email;
        }

        return '';
    }

    /** @param array<string,mixed> $identity */
    public static function matchesIdentityHandle(string $requestedHandle, array $identity): bool
    {
        $requested = self::normalizeHandle($requestedHandle);
        $sessionHandle = self::handleFromIdentity($identity);
        return $requested !== '' && $sessionHandle !== '' && hash_equals($sessionHandle, $requested);
    }

    /**
     * Wrapper access policy:
     * - platform_admin: can access both admin and operator containers
     * - app_admin: can access both containers with route/ACL filtering downstream
     * - operational/display accounts: confined to their non-admin containers
     *
     * @param array<string,mixed> $ctx
     */
    public static function canAccessWrapper(array $ctx, string $wrapperId): bool
    {
        if (!self::exists($wrapperId)) {
            return false;
        }

        if (self::isAdminAuthority($ctx)) {
            return true;
        }

        return $wrapperId === self::WRAPPER_U;
    }

    /**
     * @param array<string,mixed> $ctx
     */
    public static function isPlatformAdmin(array $ctx): bool
    {
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
        return $authorityRole === 'platform_admin';
    }

    /** @param array<string,mixed> $ctx */
    public static function isAdminAuthority(array $ctx): bool
    {
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? 'app_user')));
        return in_array($authorityRole, ['platform_admin', 'app_admin'], true);
    }
}
