<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

require_once __DIR__ . '/ResolvedExperienceDiagnosticsService.php';
require_once __DIR__ . '/UserDashboardAssignmentService.php';
require_once __DIR__ . '/UserSurfaceOverrideService.php';

/**
 * Runtime consumer facade for the Resolved Experience pipeline.
 *
 * Layer services (Display/Operator/Admin) call into this helper rather than
 * applying ad-hoc CSV normalization. Behaviour mirrors the legacy normalize
 * paths where compatibility defaults still matter, but canonical per-user
 * override artifacts in `user_surface_overrides` are preferred over the
 * transitional inline CSV fields (`display_surfaces`, `operator_views`,
 * `me_dashboard_blocks`, `me_plugin_cards`) when artifact rows exist.
 */
final class ResolvedExperienceConsumerService
{
    private const OPERATOR_VIEW_ALLOW_ALWAYS = ['dashboard', 'account'];

    /**
     * Display panels visible to the given assignment row.
     * Empty `display_surfaces` keeps the legacy "all panels" default.
     *
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    public static function displayPanels(array $row): array
    {
        return self::visibleTokens($row, 'display', 'panel');
    }

    /**
     * Operator views visible to the given assignment row.
     *
     * Preserves the legacy minimal-sidebar behaviour: when `operator_views` is
     * empty the runtime exposed only `dashboard` + `account`, even though the
     * ACL would allow more. This fallback keeps that contract intact until
     * Phase 6 migrates overrides into canonical artifacts.
     *
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    public static function operatorViews(array $row): array
    {
        $row = UserSurfaceOverrideService::applyArtifactToRow($row);
        $raw = trim((string)($row['operator_views'] ?? ''));
        if ($raw === '') {
            return self::OPERATOR_VIEW_ALLOW_ALWAYS;
        }
        return self::visibleTokens($row, 'operator', 'view');
    }

    /**
     * Admin `/me` dashboard block keys visible to the given assignment row.
     *
     * Applies the legacy account-type policy on top of the ResolvedExperience
     * visible-token set (e.g. `platform_admin` is guaranteed the prepended
     * `admin_dashboard_panels` and `platform_admin_tools` blocks; lower
     * authorities have those blocks stripped).
     *
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    public static function adminDashboardBlocks(array $row): array
    {
        $visible = self::visibleTokens($row, 'admin', 'dashboard_block');
        return UserDashboardAssignmentService::normalizeMeDashboardBlocksForAccountType(
            $visible,
            (string)($row['authority_role'] ?? '')
        );
    }

    /**
     * Admin `/me` quick-link card keys visible to the given assignment row.
     *
     * Applies the legacy account-type/app policy on top of the ResolvedExperience
     * visible-token set (platform-only cards, leader-only cards, and app-gated
     * cards are filtered through the canonical normalize routine).
     *
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    public static function adminPluginCards(array $row): array
    {
        $visible = self::visibleTokens($row, 'admin', 'quick_link_card');
        $assignedApps = array_values(array_filter(array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            preg_split('/\s*,\s*/', (string)($row['assigned_apps'] ?? '')) ?: []
        ), static fn(string $value): bool => $value !== ''));

        return UserDashboardAssignmentService::normalizeMePluginCardsForContext(
            $visible,
            (string)($row['authority_role'] ?? ''),
            (string)($row['dashboard_type'] ?? ''),
            $assignedApps
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<int,string>
     */
    private static function visibleTokens(array $row, string $surface, string $kind): array
    {
        $row = UserSurfaceOverrideService::applyArtifactToRow($row);
        $resolved = ResolvedExperienceDiagnosticsService::resolve(self::coerceRowForSurface($row, $surface));
        $out = [];
        foreach ((array)($resolved['items'] ?? []) as $item) {
            if (($item['surface'] ?? '') !== $surface) {
                continue;
            }
            if (($item['kind'] ?? '') !== $kind) {
                continue;
            }
            if (empty($item['visible'])) {
                continue;
            }
            $key = (string)($item['key'] ?? '');
            $pos = strrpos($key, '.');
            $token = $pos !== false ? substr($key, $pos + 1) : '';
            if ($token !== '') {
                $out[$token] = $token;
            }
        }
        return array_values($out);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function coerceRowForSurface(array $row, string $surface): array
    {
        if ($surface === 'operator') {
            $originalAuthority = strtolower(trim((string)($row['authority_role'] ?? '')));
            if ($originalAuthority === 'tv_display') {
                return $row;
            }
            $row['authority_role'] = 'app_user';
            $dashboardType = strtolower(trim((string)($row['dashboard_type'] ?? '')));
            if ($dashboardType === '' || in_array($dashboardType, ['platform_admin', 'app_admin', 'display'], true)) {
                $row['dashboard_type'] = 'operator';
            }
            if ($originalAuthority !== 'app_user') {
                $row['module_visibility'] = self::mergeOperatorModuleVisibilityForPrivilegedRow($row);
            }
            return $row;
        }

        if ($surface === 'display') {
            $row['authority_role'] = 'tv_display';
            $row['dashboard_type'] = 'display';
        }

        return $row;
    }

    private static function mergeOperatorModuleVisibilityForPrivilegedRow(array $row): string
    {
        $visible = [];
        foreach (preg_split('/\s*,\s*/', strtolower(trim((string)($row['module_visibility'] ?? '')))) ?: [] as $token) {
            $token = trim($token);
            if ($token !== '') {
                $visible[$token] = $token;
            }
        }

        foreach (preg_split('/\s*,\s*/', strtolower(trim((string)($row['operator_views'] ?? '')))) ?: [] as $view) {
            $view = trim($view);
            if ($view === '') {
                continue;
            }
            $view = match ($view) {
                'parts-detail' => 'parts',
                'dispatch-detail', 'dispatch-adapter' => 'dispatch',
                'alerts' => 'notifications',
                default => $view,
            };
            foreach (self::canonicalOperatorModulesForView($view) as $moduleKey) {
                $visible[$moduleKey] = $moduleKey;
            }
        }

        return implode(',', array_values($visible));
    }

    /**
     * @return array<int,string>
     */
    private static function canonicalOperatorModulesForView(string $view): array
    {
        return match ($view) {
            'production', 'machines', 'processing' => ['production'],
            'demand', 'orders' => ['demands'],
            'parts', 'materials' => ['materials'],
            'coverage' => ['coverage'],
            'assembly' => ['assembly'],
            'qc' => ['qc'],
            'fulfillment', 'preparation', 'dispatch' => ['dispatch'],
            default => [],
        };
    }
}
