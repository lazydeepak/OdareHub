<?php
// Shell-owned admin context preparation.
// Normalizes host region arrays and resolves $experienceMode from the
// template variables injected by AdminSurfaceComposer.

$hostRegions = is_array($host_regions ?? null) ? $host_regions : [];
$hostHeaderActions = is_array($hostRegions['header_actions'] ?? null) ? $hostRegions['header_actions'] : [];
$hostSummaryCards = is_array($hostRegions['summary_cards'] ?? null) ? $hostRegions['summary_cards'] : [];
$hostQuickLinks = is_array($hostRegions['quick_links'] ?? null) ? $hostRegions['quick_links'] : [];
$hostMonitoringSections = is_array($hostRegions['monitoring_sections'] ?? null) ? $hostRegions['monitoring_sections'] : [];

$authorityRole = strtolower(trim((string)($authority_role ?? 'app_user')));
$dashboardType = strtolower(trim((string)($dashboard_type ?? 'operator')));
$homeAccessProfiles = array_values((array)($home_access_profiles ?? []));
$homeDutyCodes = array_values((array)($home_duty_codes ?? []));
$experienceMode = strtolower(trim((string)($home_experience_mode ?? '')));

// Parse experience layout configuration.
$enabledDashboardBlockOrder = array_values(array_filter(array_map(
    static fn($token): string => strtolower(trim((string)$token)),
    is_array($me_dashboard_blocks ?? null) ? (array)$me_dashboard_blocks : []
), static fn(string $token): bool => $token !== ''));
$enabledPluginCardOrder = array_values(array_filter(array_map(
    static fn($token): string => strtolower(trim((string)$token)),
    is_array($me_plugin_cards ?? null) ? (array)$me_plugin_cards : []
), static fn(string $token): bool => $token !== ''));
$enabledDashboardBlocks = array_flip($enabledDashboardBlockOrder);
$enabledPluginCards = array_flip($enabledPluginCardOrder);
$dashboardBlockOrderIndex = array_flip($enabledDashboardBlockOrder);
$dashboardBlocksExplicitNone = (bool)($me_dashboard_blocks_explicit_none ?? false);
$pluginCardsExplicitNone = (bool)($me_plugin_cards_explicit_none ?? false);

$tokenSet = array_values(array_filter(array_map(
    static fn($token): string => strtolower(trim((string)$token)),
    array_merge($homeAccessProfiles, $homeDutyCodes)
), static fn(string $token): bool => $token !== ''));

if ($experienceMode === '') {
    if (in_array('display', $tokenSet, true)) {
        $experienceMode = 'display';
    } elseif (in_array('read_only', $tokenSet, true)) {
        $experienceMode = 'read_only';
    } elseif (in_array($authorityRole, ['platform_admin', 'app_admin'], true)) {
        $experienceMode = 'admin';
    } elseif (in_array($dashboardType, ['production_leader', 'assembly_leader', 'qc_leader', 'dispatch_leader'], true)) {
        $experienceMode = 'leader';
    } else {
        $experienceMode = 'worker';
    }
}
