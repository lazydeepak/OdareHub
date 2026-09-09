<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

final class HostSurfaceContributionService
{
    /**
     * @param array<string,mixed> $request
     * @return array<int,array<string,mixed>>
     */
    public static function contribute(array $request): array
    {
        $surface = trim((string)($request['surface'] ?? ''));
        $region = trim((string)($request['region'] ?? ''));

        if (!self::isVisibleForRequest($request)) {
            return [];
        }

        return match ($surface . ':' . $region) {
            'me:header_actions' => self::meHeaderActions(),
            'me:quick_links' => self::meQuickLinks(),
            default => [],
        };
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meHeaderActions(): array
    {
        return [
            [
                'key' => 'studio_header_action',
                'label' => self::tr('studio.host.open_studio', 'Open Studio'),
                'url' => '/apps/studio',
                'description' => self::tr('studio.host.open_studio_description', 'Open the governed app and resource workbench.'),
                'weight' => 40,
                'widget_type' => 'action',
                'view_kind' => 'cards',
                'placement_zone' => 'operator_actions',
                'interaction_profiles' => ['admin'],
                'access_authorities' => ['platform_admin'],
                'permission_profile' => 'platform_admin',
                'supports_clickthrough' => true,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function meQuickLinks(): array
    {
        return [
            [
                'key' => 'studio_workspace_quick_link',
                'label' => self::tr('studio.host.workspace', 'OdareHub Studio'),
                'title' => self::tr('studio.host.workspace', 'OdareHub Studio'),
                'url' => '/apps/studio',
                'description' => self::tr('studio.host.workspace_description', 'Governed workbench for apps, modules, resources, tools, diffs, approvals, and rollback.'),
                'weight' => 40,
                'widget_type' => 'reference',
                'view_kind' => 'cards',
                'placement_zone' => 'supporting_visibility',
                'interaction_profiles' => ['admin'],
                'access_authorities' => ['platform_admin'],
                'permission_profile' => 'platform_admin',
                'supports_clickthrough' => true,
            ],
        ];
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function isVisibleForRequest(array $request): bool
    {
        $outerContext = is_array($request['context'] ?? null) ? (array)$request['context'] : [];
        $resolvedContext = is_array($outerContext['context'] ?? null) ? (array)$outerContext['context'] : [];

        $authorityRole = strtolower(trim((string)($resolvedContext['authority_role'] ?? '')));
        if ($authorityRole === 'platform_admin') {
            return true;
        }

        $user = is_array($outerContext['user'] ?? null) ? (array)$outerContext['user'] : [];
        $userAuthorityRole = strtolower(trim((string)($user['authority_role'] ?? '')));
        if ($userAuthorityRole === 'platform_admin') {
            return true;
        }

        $role = strtolower(trim((string)($user['role'] ?? '')));
        $flatRole = preg_replace('/[^a-z0-9]+/', '', $role) ?: '';

        return in_array($flatRole, ['platformadmin', 'sysadmin', 'systemadmin', 'systemadministrator'], true);
    }

    private static function tr(string $key, string $fallback): string
    {
        if (function_exists('t')) {
            $translated = (string)t($key);
            if ($translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        return $fallback;
    }
}
