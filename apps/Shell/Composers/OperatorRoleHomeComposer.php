<?php
declare(strict_types=1);

namespace Apps\Shell\Composers;

/**
 * Shapes already-authorized operator home contributions by interaction profile.
 * It does not resolve permissions, discover business data, or infer roles from
 * labels; it only consumes explicit runtime profile and contribution metadata.
 */
final class OperatorRoleHomeComposer
{
    private const PROFILES = ['worker', 'leader', 'read_only'];

    /** @param array<string,mixed> $context */
    public static function resolveInteractionProfile(array $context): string
    {
        $resolvedDashboardType = strtolower(trim((string)($context['dashboard_type'] ?? '')));
        if (in_array($resolvedDashboardType, ['display', 'tv_display', 'platform_admin', 'app_admin'], true)) {
            return 'worker';
        }

        $explicit = strtolower(trim((string)($context['interaction_profile'] ?? '')));
        if (in_array($explicit, self::PROFILES, true)) {
            return $explicit;
        }

        if (in_array($resolvedDashboardType, ['dispatch_leader', 'qc_leader', 'assembly_leader', 'production_leader'], true)) {
            return 'leader';
        }

        $accessProfiles = array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            (array)($context['access_profiles'] ?? [])
        );
        $dutyCodes = array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            is_array($context['duty_codes'] ?? null)
                ? (array)$context['duty_codes']
                : (preg_split('/\s*,\s*/', (string)($context['duty_codes'] ?? '')) ?: [])
        );
        if (in_array('readonly_observer', $accessProfiles, true)
            || in_array('materials_readonly', $accessProfiles, true)
            || in_array('read_only', $dutyCodes, true)
        ) {
            return 'read_only';
        }

        $appRoles = array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            array_values((array)($context['app_roles'] ?? []))
        );
        if (array_intersect($appRoles, ['production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader']) !== []
            || array_intersect($accessProfiles, ['production_coordination', 'assembly_coordination', 'qc_coordination', 'dispatch_coordination', 'manufacturing_planning']) !== []
        ) {
            return 'leader';
        }
        return 'worker';
    }

    /**
     * Resolve the profile from the already-resolved assignment context. This
     * keeps the orchestration handoff generic and keeps profile interpretation
     * in one removable Shell composition helper.
     *
     * @param array<string,mixed> $assignmentContext
     */
    public static function fromAssignmentContext(array $assignmentContext): string
    {
        if (($assignmentContext['operator_home_eligible'] ?? true) !== true) {
            return 'worker';
        }
        $accessProfiles = array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            (array)($assignmentContext['access_profiles'] ?? [])
        );

        return self::resolveInteractionProfile([
            'access_profiles' => $accessProfiles,
            'duty_codes' => (string)($assignmentContext['duty_codes'] ?? ''),
            'app_roles' => (array)($assignmentContext['app_roles'] ?? []),
        ]);
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,array<int,array<string,mixed>>> $regions
     * @return array{profile:string,read_only:bool,regions:array<string,array<int,array<string,mixed>>>}
     */
    public static function compose(array $context, array $regions = []): array
    {
        $profile = self::resolveInteractionProfile($context);
        return [
            'profile' => $profile,
            'read_only' => $profile === 'read_only',
            'regions' => self::filterRegions($regions, $profile, $context),
        ];
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $regions
     * @param array<string,mixed> $context
     * @return array<string,array<int,array<string,mixed>>>
     */
    private static function filterRegions(array $regions, string $profile, array $context): array
    {
        // Role-shaped home is limited to owner-provided review/visibility
        // regions. Header actions remain on their existing specialized paths.
        $allowedRegions = ['summary_cards', 'quick_links', 'monitoring_sections'];
        $routeResolver = $context['operator_route_access_resolver'] ?? null;
        if (!is_callable($routeResolver)) {
            return [];
        }
        $filteredRegions = [];

        foreach ($allowedRegions as $region) {
            $items = [];
            foreach ((array)($regions[$region] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $profiles = array_values(array_filter(array_map(
                    static fn($value): string => strtolower(trim((string)$value)),
                    (array)($item['interaction_profiles'] ?? [])
                ), static fn(string $value): bool => $value !== ''));
                if (!in_array($profile, $profiles, true)) {
                    continue;
                }

                $widgetType = strtolower(trim((string)($item['widget_type'] ?? '')));
                $viewKind = strtolower(trim((string)($item['view_kind'] ?? $item['kind'] ?? '')));
                if ($profile === 'read_only' && in_array($widgetType, ['action', 'approval'], true)) {
                    continue;
                }
                if ($profile === 'read_only' && $viewKind === 'form') {
                    continue;
                }

                $items[] = self::sanitizeLinks($item, $context, $routeResolver);
            }

            usort($items, static function (array $left, array $right): int {
                return ((int)($left['priority'] ?? $left['weight'] ?? 100))
                    <=> ((int)($right['priority'] ?? $right['weight'] ?? 100));
            });
            if ($items !== []) {
                $filteredRegions[$region] = $items;
            }
        }

        return $filteredRegions;
    }

    /** @param array<string,mixed> $item @param array<string,mixed> $context */
    private static function sanitizeLinks(array $item, array $context, $routeResolver): array
    {
        $sanitize = static function (string $url) use ($context, $routeResolver): string {
            if ($url === '') {
                return '';
            }
            if (class_exists(\Apps\Shell\Services\OperatorLayerWidgetService::class)) {
                $url = \Apps\Shell\Services\OperatorLayerWidgetService::normalizeOperatorWidgetUrl($url, $context);
            }
            $parts = parse_url($url);
            if (!is_array($parts)
                || isset($parts['scheme'], $parts['host'], $parts['user'], $parts['pass'], $parts['fragment'])
            ) {
                return '';
            }
            $path = trim((string)($parts['path'] ?? ''));
            $username = trim((string)($context['operator_username'] ?? ''));
            $expectedPrefix = $username !== '' ? '/u/' . rawurlencode($username) : '';
            if ($path === '' || $expectedPrefix === '' || ($path !== $expectedPrefix && !str_starts_with($path, $expectedPrefix . '/'))) {
                return '';
            }
            if (is_callable($routeResolver) && !$routeResolver($path)) {
                return '';
            }
            return $url;
        };

        if (array_key_exists('url', $item)) {
            $item['url'] = $sanitize(trim((string)$item['url']));
        }
        foreach ((array)($item['rows'] ?? []) as $index => $row) {
            if (is_array($row) && array_key_exists('url', $row)) {
                $row['url'] = $sanitize(trim((string)$row['url']));
                $item['rows'][$index] = $row;
            }
        }
        return $item;
    }
}
