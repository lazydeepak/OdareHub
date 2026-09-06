<?php
declare(strict_types=1);

namespace Plugins\Base\Services;

require_once __DIR__ . '/UserDashboardAssignmentService.php';

/**
 * Read-only bridge diagnostics for the target ResolvedExperience model.
 *
 * This service intentionally does not render UI and does not grant access. It
 * mirrors current runtime inputs so migration work can compare planned law with
 * the real admin/operator/display surfaces before any renderer changes.
 */
final class ResolvedExperienceDiagnosticsService
{
    private const NONE_TOKEN = '__none';

    private const OPERATOR_VIEWS = [
        'dashboard', 'work-entry', 'data-exchange', 'critical', 'recent', 'tasks',
        'production', 'demand', 'orders', 'parts', 'coverage', 'machines',
        'processing', 'assembly', 'qc', 'fulfillment', 'preparation', 'dispatch',
        'materials', 'handoff', 'account', 'notifications', 'messages', 'preferences',
        'sbaio',
    ];

    private const OPERATOR_VIEW_ALIASES = [
        'parts-detail' => 'parts',
        'dispatch-detail' => 'dispatch',
        'dispatch-adapter' => 'dispatch',
        'alerts' => 'notifications',
    ];

    private const OPERATOR_VIEW_ALLOW_ALWAYS = ['dashboard', 'account'];

    private const DISPLAY_PANELS = ['overview', 'machines', 'dispatch', 'qc', 'activity'];

    /**
     * @param array<string,mixed> $assignmentRow
     * @param array<string,mixed>|null $resolvedProfile
     * @return array<string,mixed>
     */
    public static function resolve(array $assignmentRow, ?array $resolvedProfile = null): array
    {
        $surface = self::surfaceForRow($assignmentRow);
        $catalog = self::capabilityCatalog($surface);
        $items = [];

        foreach ($catalog as $capability) {
            $items[] = self::resolveCapability($capability, $assignmentRow, $resolvedProfile);
        }
        $staleReferences = self::staleReferences($surface, $catalog, $assignmentRow, $resolvedProfile);
        $parity = self::runtimeParity($surface, $catalog, $items, $assignmentRow);

        return [
            'version' => 'resolved_experience.diagnostic.v1',
            'mode' => 'read_only',
            'surface' => $surface,
            'target_user_id' => (int)($assignmentRow['id'] ?? $assignmentRow['user_id'] ?? 0),
            'authority_role' => (string)($assignmentRow['authority_role'] ?? ''),
            'dashboard_type' => (string)($assignmentRow['dashboard_type'] ?? ''),
            'workspace_profile' => [
                'profile_key' => (string)($resolvedProfile['profile_key'] ?? $assignmentRow['workspace_profile_key'] ?? ''),
                'name' => (string)($resolvedProfile['name'] ?? ''),
                'is_pinned' => !empty($resolvedProfile['is_pinned']) || trim((string)($assignmentRow['workspace_profile_key'] ?? '')) !== '',
            ],
            'summary' => self::summary($catalog, $items, $staleReferences, $parity),
            'stale_references' => $staleReferences,
            'runtime_parity' => $parity,
            'items' => $items,
        ];
    }

    /**
     * Profile-only diagnostics: resolves what a workspace profile alone would
     * expose, without a target user. Synthetic assignment row is built from the
     * profile so user-override fields are intentionally absent (no override
     * hides apply).
     *
     * @param array<string,mixed> $profile
     * @return array<string,mixed>
     */
    public static function resolveForProfile(array $profile): array
    {
        $authority = strtolower(trim((string)($profile['authority_role'] ?? '')));
        $assignedApps = (string)($profile['assigned_apps'] ?? '');

        $visibleModules = [];
        $rawModVis = $profile['module_visibility'] ?? null;
        if (is_string($rawModVis) && $rawModVis !== '') {
            $decoded = json_decode($rawModVis, true);
            $rawModVis = is_array($decoded) ? $decoded : null;
        }
        if (is_array($rawModVis)) {
            foreach ($rawModVis as $moduleKey => $level) {
                $lvl = strtolower(trim((string)$level));
                if ($lvl !== '' && $lvl !== 'none') {
                    $visibleModules[] = strtolower(trim((string)$moduleKey));
                }
            }
        }

        $syntheticRow = [
            'id' => 0,
            'user_id' => 0,
            'authority_role' => $authority,
            'dashboard_type' => '',
            'assigned_apps' => $assignedApps,
            'module_visibility' => implode(',', array_filter($visibleModules)),
            'workspace_profile_key' => (string)($profile['profile_key'] ?? ''),
        ];

        $resolved = self::resolve($syntheticRow, $profile);
        $resolved['mode'] = 'profile_only';
        return $resolved;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function capabilityCatalog(string $surface): array
    {
        return match ($surface) {
            'display' => self::displayCatalog(),
            'operator' => self::operatorCatalog(),
            default => self::adminCatalog(),
        };
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @param array<int,array<string,mixed>> $items
     * @return array<string,int>
     */
    private static function summary(array $catalog, array $items, array $staleReferences = [], array $parity = []): array
    {
        $visible = 0;
        $aclDenied = 0;
        $profileHidden = 0;
        $overrideHidden = 0;

        foreach ($items as $item) {
            if (!empty($item['visible'])) {
                $visible++;
            }
            if (($item['hidden_reason'] ?? '') === 'acl_denied') {
                $aclDenied++;
            }
            if (($item['hidden_reason'] ?? '') === 'profile_hidden') {
                $profileHidden++;
            }
            if (($item['hidden_reason'] ?? '') === 'user_override_hidden') {
                $overrideHidden++;
            }
        }

        return [
            'owner_capabilities_count' => count($catalog),
            'visible_count' => $visible,
            'hidden_count' => max(0, count($catalog) - $visible),
            'acl_denied_count' => $aclDenied,
            'profile_hidden_count' => $profileHidden,
            'user_override_hidden_count' => $overrideHidden,
            'stale_reference_count' => count($staleReferences),
            'runtime_parity_missing_count' => count((array)($parity['missing_in_diagnostics'] ?? [])),
            'runtime_parity_extra_count' => count((array)($parity['extra_in_diagnostics'] ?? [])),
        ];
    }

    /**
     * @param array<string,mixed> $capability
     * @param array<string,mixed> $row
     * @param array<string,mixed>|null $profile
     * @return array<string,mixed>
     */
    private static function resolveCapability(array $capability, array $row, ?array $profile): array
    {
        $acl = self::aclDecision($capability, $row);
        $profileDecision = self::profileDecision($capability, $profile);
        $override = self::overrideDecision($capability, $row);

        $visible = $acl['allowed'] && $profileDecision['included'] && $override['included'];
        $hiddenReason = null;
        if (!$acl['allowed']) {
            $hiddenReason = 'acl_denied';
        } elseif (!$profileDecision['included']) {
            $hiddenReason = 'profile_hidden';
        } elseif (!$override['included']) {
            $hiddenReason = 'user_override_hidden';
        }

        return [
            'key' => (string)$capability['key'],
            'label' => (string)$capability['label'],
            'surface' => (string)$capability['surface'],
            'kind' => (string)$capability['kind'],
            'owner_type' => (string)$capability['owner_type'],
            'owner_key' => (string)$capability['owner_key'],
            'route' => (string)($capability['route'] ?? ''),
            'allowed_by_acl' => $acl['allowed'],
            'acl_reason' => $acl['reason'],
            'included_by_workspace_profile' => $profileDecision['included'],
            'profile_reason' => $profileDecision['reason'],
            'included_by_user_override' => $override['included'],
            'override_reason' => $override['reason'],
            'visible' => $visible,
            'hidden_reason' => $hiddenReason,
            'required_apps' => array_values((array)($capability['required_apps'] ?? [])),
            'required_modules' => array_values((array)($capability['required_modules'] ?? [])),
            'required_permissions' => array_values((array)($capability['required_permissions'] ?? [])),
        ];
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function surfaceForRow(array $row): string
    {
        $authority = strtolower(trim((string)($row['authority_role'] ?? '')));
        $dashboard = strtolower(trim((string)($row['dashboard_type'] ?? '')));
        if ($authority === 'tv_display' || $dashboard === 'display') {
            return 'display';
        }
        if ($authority === 'app_user') {
            return 'operator';
        }
        return 'admin';
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function adminCatalog(): array
    {
        $items = [];
        foreach (UserDashboardAssignmentService::meDashboardBlockCatalog() as $key => $label) {
            $items[] = [
                'key' => 'admin.block.' . $key,
                'token' => (string)$key,
                'label' => (string)$label,
                'surface' => 'admin',
                'kind' => 'dashboard_block',
                'owner_type' => in_array((string)$key, ['admin_dashboard_panels', 'platform_admin_tools'], true) ? 'app' : 'module',
                'owner_key' => in_array((string)$key, ['admin_dashboard_panels', 'platform_admin_tools'], true) ? 'platform' : 'manufacturing',
                'route' => '/admin/{user}',
                'override_field' => 'me_dashboard_blocks',
            ];
        }
        foreach (UserDashboardAssignmentService::mePluginCardCatalog() as $key => $label) {
            $owner = self::adminCardOwner((string)$key);
            $items[] = [
                'key' => 'admin.card.' . $key,
                'token' => (string)$key,
                'label' => (string)$label,
                'surface' => 'admin',
                'kind' => 'quick_link_card',
                'owner_type' => 'app',
                'owner_key' => $owner,
                'route' => self::adminCardRoute((string)$key),
                'override_field' => 'me_plugin_cards',
                'required_apps' => $owner !== 'platform' ? [$owner] : [],
                'required_permissions' => in_array($owner, ['platform'], true) ? ['platform.admin'] : [],
            ];
        }
        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function operatorCatalog(): array
    {
        $items = [];
        foreach (self::OPERATOR_VIEWS as $view) {
            $owner = self::operatorOwner($view);
            $items[] = [
                'key' => 'operator.view.' . $view,
                'token' => $view,
                'label' => self::tr('operator.sidebar.' . str_replace('-', '_', $view), self::humanize($view)),
                'surface' => 'operator',
                'kind' => 'view',
                'owner_type' => $owner === 'my_work' ? 'app' : 'module',
                'owner_key' => $owner === 'my_work' ? 'platform' : $owner,
                'route' => '/u/{user}/' . $view,
                'override_field' => 'operator_views',
                'required_apps' => in_array($owner, ['manufacturing', 'sbaio'], true) ? [$owner] : [],
                'required_modules' => self::operatorModule($view),
            ];
        }
        foreach (self::contributedOperatorFocuses() as $focus) {
            $items[] = [
                'key' => 'operator.view.' . $focus['token'],
                'token' => $focus['token'],
                'label' => self::tr('operator.sidebar.' . $focus['token'], self::humanize($focus['token'])),
                'surface' => 'operator',
                'kind' => 'view',
                'owner_type' => 'app',
                'owner_key' => $focus['app'],
                'route' => '/u/{user}/' . $focus['token'],
                'override_field' => 'operator_views', // assignment-row payload field (compat bridge)
                'required_apps' => [$focus['app']],
                'required_modules' => [],
            ];
        }
        return $items;
    }

    /**
     * Generic discovery of DECLARED app-contributed operator focus views.
     *
     * Capability truth is OWNED by contributing apps: each focus_views hook
     * declares its tokens additively via the `focus_tokens` field inside the
     * EXISTING operator_surface hook contract. This bridge only reads the
     * materialized declaration from core_app_hooks.payload_json for ENABLED
     * apps - it never executes business providers and never infers tokens from
     * hook-key naming (hook identity != capability identity).
     *
     * Safety rules: tokens validated against a normalized route-token pattern;
     * built-in compatibility tokens always win collisions; duplicates collapse;
     * disabled/uninstalled apps disappear via the enabled-status join;
     * non-focus_views declarations are never consulted here.
     *
     * @return array<int,array{token:string,app:string}>
     */
    private static function contributedOperatorFocuses(): array
    {
        $cache = [];
        try {
            $rows = \App\Core\DB::fetchAll(
                "SELECT h.app_key AS app_key, h.payload_json AS payload_json"
                . " FROM core_app_hooks h"
                . " INNER JOIN core_apps a ON a.app_key = h.app_key AND a.status = 'enabled'"
                . " WHERE h.hook_type = 'operator_surface' AND h.is_enabled = 1"
            );
        } catch (\Throwable $e) {
            return $cache;
        }
        $seen = [];
        foreach ($rows as $row) {
            $app = (string)$row['app_key'];
            $payload = json_decode((string)$row['payload_json'], true);
            if (!is_array($payload) || !isset($payload['focus_tokens']) || !is_array($payload['focus_tokens'])) {
                continue;
            }
            foreach ($payload['focus_tokens'] as $rawToken) {
                $token = strtolower(trim((string)$rawToken));
                if ($token === '' || !preg_match('/^[a-z0-9_-]+$/', $token)) {
                    continue;
                }
                if (in_array($token, self::OPERATOR_VIEWS, true)) { // compat: built-in ownership authoritative for this row-payload token
                    continue; // built-in compatibility ownership is authoritative
                }
                if (isset($seen[$token])) {
                    continue;
                }
                $seen[$token] = true;
                $cache[] = ['token' => $token, 'app' => $app];
            }
        }
        return $cache;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function displayCatalog(): array
    {
        $labels = [
            'overview' => self::tr('admin.access.diagnostics.display_overview', 'Overview'),
            'machines' => self::tr('admin.access.diagnostics.display_machines', 'Machines'),
            'dispatch' => self::tr('admin.access.diagnostics.display_dispatch', 'Dispatch'),
            'qc' => self::tr('admin.access.diagnostics.display_qc', 'Quality Control'),
            'activity' => self::tr('admin.access.diagnostics.display_activity', 'Activity'),
        ];
        $items = [];
        foreach (self::DISPLAY_PANELS as $panel) {
            $items[] = [
                'key' => 'display.panel.' . $panel,
                'token' => $panel,
                'label' => (string)($labels[$panel] ?? self::humanize($panel)),
                'surface' => 'display',
                'kind' => 'panel',
                'owner_type' => 'module',
                'owner_key' => 'manufacturing',
                'route' => '/displays/user/{user}',
                'override_field' => 'display_surfaces',
                'required_apps' => ['manufacturing'],
                'required_modules' => $panel === 'overview' ? [] : [$panel],
            ];
        }
        return $items;
    }

    /**
     * @param array<string,mixed> $capability
     * @param array<string,mixed> $row
     * @return array{allowed:bool,reason:string}
     */
    private static function aclDecision(array $capability, array $row): array
    {
        $surface = (string)($capability['surface'] ?? '');
        $authority = strtolower(trim((string)($row['authority_role'] ?? '')));
        $assignedApps = self::tokens((string)($row['assigned_apps'] ?? ''));
        $moduleVisibility = self::tokens((string)($row['module_visibility'] ?? ''));
        $requiredApps = array_values((array)($capability['required_apps'] ?? []));
        $requiredModules = array_values((array)($capability['required_modules'] ?? []));

        if ($surface === 'admin' && !in_array($authority, ['platform_admin', 'app_admin'], true)) {
            return ['allowed' => false, 'reason' => 'admin_surface_requires_admin_authority'];
        }

        if ($surface === 'operator' && $authority === 'tv_display') {
            return ['allowed' => false, 'reason' => 'display_user_jailed_to_display_surface'];
        }

        if ($surface === 'display' && !in_array($authority, ['tv_display', 'platform_admin', 'app_admin'], true)) {
            return ['allowed' => false, 'reason' => 'display_surface_requires_display_or_admin_authority'];
        }

        foreach ($requiredApps as $app) {
            $app = strtolower(trim((string)$app));
            if ($app !== '' && !in_array($app, $assignedApps, true)) {
                return ['allowed' => false, 'reason' => 'required_app_missing:' . $app];
            }
        }

        if ($moduleVisibility !== []) {
            foreach ($requiredModules as $module) {
                $module = strtolower(trim((string)$module));
                if ($module !== '' && !in_array($module, $moduleVisibility, true)) {
                    return ['allowed' => false, 'reason' => 'module_not_visible:' . $module];
                }
            }
        }

        return ['allowed' => true, 'reason' => 'acl_allowed'];
    }

    /**
     * @param array<string,mixed> $capability
     * @param array<string,mixed>|null $profile
     * @return array{included:bool,reason:string}
     */
    private static function profileDecision(array $capability, ?array $profile): array
    {
        if (!is_array($profile) || $profile === []) {
            return ['included' => true, 'reason' => 'profile_not_resolved'];
        }

        $surface = (string)($capability['surface'] ?? '');
        $token = (string)($capability['token'] ?? '');
        $route = (string)($capability['route'] ?? '');

        if ($surface === 'operator') {
            $nav = $profile['nav_sections'] ?? null;
            if (is_array($nav) && $nav !== []) {
                return self::profileContainsRoute($nav, $route, $token)
                    ? ['included' => true, 'reason' => 'profile_nav_includes']
                    : ['included' => false, 'reason' => 'profile_nav_missing'];
            }
        }

        $dash = $profile['dashboard_blocks'] ?? null;
        if ($surface === 'admin' && is_array($dash) && $dash !== []) {
            $tokens = self::profileTokenSet($dash);
            if ($tokens !== []) {
                return in_array($token, $tokens, true)
                    ? ['included' => true, 'reason' => 'profile_dashboard_includes']
                    : ['included' => false, 'reason' => 'profile_dashboard_missing'];
            }
        }

        return ['included' => true, 'reason' => 'profile_no_specific_rule'];
    }

    /**
     * @param array<string,mixed> $capability
     * @param array<string,mixed> $row
     * @return array{included:bool,reason:string}
     */
    private static function overrideDecision(array $capability, array $row): array
    {
        $field = (string)($capability['override_field'] ?? '');
        $token = strtolower(trim((string)($capability['token'] ?? '')));
        $raw = strtolower(trim((string)($row[$field] ?? '')));

        if ($field === '' || $token === '') {
            return ['included' => true, 'reason' => 'override_not_applicable'];
        }

        $tokens = self::tokens($raw);
        if (in_array(self::NONE_TOKEN, $tokens, true)) {
            return ['included' => false, 'reason' => 'user_override_explicit_none'];
        }

        if ($tokens === []) {
            return ['included' => true, 'reason' => 'user_override_not_set'];
        }

        if ($field === 'operator_views') {
            $tokens = self::normalizeOperatorOverrideTokens($raw);
        }

        return in_array($token, $tokens, true)
            ? ['included' => true, 'reason' => 'user_override_includes']
            : ['included' => false, 'reason' => 'user_override_hidden'];
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @param array<string,mixed> $row
     * @param array<string,mixed>|null $profile
     * @return array<int,array{source:string,field:string,token:string,normalized_token:string,reason:string}>
     */
    private static function staleReferences(string $surface, array $catalog, array $row, ?array $profile): array
    {
        $knownByField = self::catalogTokensByOverrideField($catalog);
        $stale = [];

        foreach ($knownByField as $field => $knownTokens) {
            $raw = self::rawOverrideValue($row, (string)$field);
            foreach (self::rawTokens($raw) as $token) {
                if ($token === self::NONE_TOKEN) {
                    continue;
                }
                $normalized = $field === 'operator_views'
                    ? self::normalizeOperatorViewToken($token)
                    : strtolower(trim($token));
                if ($normalized === '' || isset($knownTokens[$normalized])) {
                    continue;
                }
                $stale[] = [
                    'source' => 'user_override',
                    'field' => (string)$field,
                    'token' => $token,
                    'normalized_token' => $normalized,
                    'reason' => 'not_in_owner_capability_catalog',
                ];
            }
        }

        if (is_array($profile) && $profile !== []) {
            if ($surface === 'operator' && is_array($profile['nav_sections'] ?? null)) {
                $known = $knownByField['operator_views'] ?? [];
                foreach (self::profileRouteTokens((array)$profile['nav_sections']) as $token) {
                    $normalized = self::normalizeOperatorViewToken($token);
                    if ($normalized !== '' && !isset($known[$normalized])) {
                        $stale[] = [
                            'source' => 'workspace_profile',
                            'field' => 'nav_sections',
                            'token' => $token,
                            'normalized_token' => $normalized,
                            'reason' => 'not_in_owner_capability_catalog',
                        ];
                    }
                }
            }
            if ($surface === 'admin' && is_array($profile['dashboard_blocks'] ?? null)) {
                $known = $knownByField['me_dashboard_blocks'] ?? [];
                foreach (self::profileTokenSet((array)$profile['dashboard_blocks']) as $token) {
                    if ($token !== '' && !isset($known[$token])) {
                        $stale[] = [
                            'source' => 'workspace_profile',
                            'field' => 'dashboard_blocks',
                            'token' => $token,
                            'normalized_token' => $token,
                            'reason' => 'not_in_owner_capability_catalog',
                        ];
                    }
                }
            }
        }

        return $stale;
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function runtimeParity(string $surface, array $catalog, array $items, array $row): array
    {
        $catalogByToken = [];
        foreach ($catalog as $capability) {
            $token = strtolower(trim((string)($capability['token'] ?? '')));
            if ($token !== '') {
                $catalogByToken[$token] = $capability;
            }
        }

        $expected = [];
        foreach (self::runtimeExpectedTokens($surface, $catalog, $row) as $token) {
            if (!isset($catalogByToken[$token])) {
                continue;
            }
            $acl = self::aclDecision($catalogByToken[$token], $row);
            if (!empty($acl['allowed'])) {
                $expected[$token] = $token;
            }
        }

        $actual = [];
        foreach ($items as $item) {
            if (!empty($item['visible'])) {
                $token = strtolower(trim((string)($item['key'] ?? '')));
                $token = preg_replace('/^(admin\.block\.|admin\.card\.|operator\.view\.|display\.panel\.)/', '', $token) ?: $token;
                if ($token !== '') {
                    $actual[$token] = $token;
                }
            }
        }

        return [
            'surface' => $surface,
            'runtime_expected_tokens' => array_values($expected),
            'diagnostic_visible_tokens' => array_values($actual),
            'missing_in_diagnostics' => array_values(array_diff_key($expected, $actual)),
            'extra_in_diagnostics' => array_values(array_diff_key($actual, $expected)),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @return array<int,string>
     */
    private static function runtimeExpectedTokens(string $surface, array $catalog, array $row): array
    {
        if ($surface === 'operator') {
            $raw = trim((string)($row['operator_views'] ?? ''));
            if ($raw === '') {
                return self::catalogTokens($catalog);
            }
            if (in_array(self::NONE_TOKEN, self::tokens($raw), true)) {
                return [];
            }
            return self::normalizeOperatorOverrideTokens($raw);
        }

        if ($surface === 'display') {
            $raw = trim((string)($row['display_surfaces'] ?? ''));
            if ($raw === '') {
                return self::catalogTokens($catalog);
            }
            if (in_array(self::NONE_TOKEN, self::tokens($raw), true)) {
                return [];
            }
            return self::tokens($raw);
        }

        $tokens = [];
        foreach ($catalog as $capability) {
            $field = (string)($capability['override_field'] ?? '');
            $token = strtolower(trim((string)($capability['token'] ?? '')));
            if ($field === '' || $token === '') {
                continue;
            }
            $raw = trim((string)($row[$field] ?? ''));
            if ($raw === '') {
                $tokens[$token] = $token;
                continue;
            }
            $selected = self::tokens($raw);
            if (!in_array(self::NONE_TOKEN, $selected, true) && in_array($token, $selected, true)) {
                $tokens[$token] = $token;
            }
        }

        return array_values($tokens);
    }

    /**
     * @param array<int,mixed> $sections
     */
    private static function profileContainsRoute(array $sections, string $route, string $token): bool
    {
        $needleRoute = str_replace('{user}', '', strtolower($route));
        $needleToken = strtolower($token);
        $stack = $sections;
        while ($stack !== []) {
            $item = array_pop($stack);
            if (is_array($item)) {
                $itemRoute = strtolower((string)($item['route'] ?? $item['url'] ?? ''));
                $itemKey = strtolower((string)($item['key'] ?? $item['id'] ?? ''));
                if ($itemKey === $needleToken || ($itemRoute !== '' && str_contains($itemRoute, '/' . $needleToken))) {
                    return true;
                }
                if ($needleRoute !== '' && $itemRoute !== '' && str_contains(str_replace('{user}', '', $itemRoute), $needleRoute)) {
                    return true;
                }
                foreach ($item as $value) {
                    if (is_array($value)) {
                        $stack[] = $value;
                    }
                }
            }
        }
        return false;
    }

    /**
     * @param array<int|string,mixed> $value
     * @return array<int,string>
     */
    private static function profileTokenSet(array $value): array
    {
        $tokens = [];
        array_walk_recursive($value, static function ($leaf) use (&$tokens): void {
            $token = strtolower(trim((string)$leaf));
            if ($token !== '') {
                $tokens[$token] = $token;
            }
        });
        return array_values($tokens);
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @return array<string,array<string,bool>>
     */
    private static function catalogTokensByOverrideField(array $catalog): array
    {
        $out = [];
        foreach ($catalog as $capability) {
            $field = (string)($capability['override_field'] ?? '');
            $token = strtolower(trim((string)($capability['token'] ?? '')));
            if ($field !== '' && $token !== '') {
                $out[$field][$token] = true;
            }
        }
        return $out;
    }

    /**
     * @param array<int,array<string,mixed>> $catalog
     * @return array<int,string>
     */
    private static function catalogTokens(array $catalog): array
    {
        $tokens = [];
        foreach ($catalog as $capability) {
            $token = strtolower(trim((string)($capability['token'] ?? '')));
            if ($token !== '') {
                $tokens[$token] = $token;
            }
        }
        return array_values($tokens);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rawOverrideValue(array $row, string $field): string
    {
        $rawField = 'raw_' . $field;
        if (array_key_exists($rawField, $row)) {
            return (string)$row[$rawField];
        }
        return (string)($row[$field] ?? '');
    }

    /**
     * @return array<int,string>
     */
    private static function rawTokens(string $csv): array
    {
        $out = [];
        foreach (preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [] as $token) {
            $token = strtolower(trim($token));
            if ($token !== '') {
                $out[] = $token;
            }
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private static function normalizeOperatorOverrideTokens(string $csv): array
    {
        $allowed = array_flip(self::OPERATOR_VIEWS);
        foreach (self::contributedOperatorFocuses() as $focus) {
            $allowed[$focus['token']] = true;
        }
        $out = [];
        foreach (self::rawTokens($csv) as $token) {
            $token = self::normalizeOperatorViewToken($token);
            if ($token !== '' && isset($allowed[$token])) {
                $out[$token] = $token;
            }
        }
        foreach (self::OPERATOR_VIEW_ALLOW_ALWAYS as $required) {
            $out[$required] = $required;
        }
        return array_values($out);
    }

    private static function normalizeOperatorViewToken(string $token): string
    {
        $token = strtolower(trim($token, " \t\n\r\0\x0B/"));
        if ($token === '') {
            return '';
        }
        $token = explode('/', $token)[0] ?? $token;
        return self::OPERATOR_VIEW_ALIASES[$token] ?? $token;
    }

    /**
     * @param array<int,mixed> $sections
     * @return array<int,string>
     */
    private static function profileRouteTokens(array $sections): array
    {
        $tokens = [];
        $stack = $sections;
        while ($stack !== []) {
            $item = array_pop($stack);
            if (!is_array($item)) {
                continue;
            }
            foreach (['key', 'id'] as $field) {
                $token = self::normalizeOperatorViewToken((string)($item[$field] ?? ''));
                if ($token !== '') {
                    $tokens[$token] = $token;
                }
            }
            $route = strtolower((string)($item['route'] ?? $item['url'] ?? ''));
            if ($route !== '') {
                $parts = array_values(array_filter(explode('/', trim($route, '/')), static fn(string $part): bool => $part !== ''));
                $last = (string)end($parts);
                $token = self::normalizeOperatorViewToken($last);
                if ($token !== '' && !in_array($token, ['u', '{user}', ':user', '{username}', ':username'], true)) {
                    $tokens[$token] = $token;
                }
            }
            foreach ($item as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                }
            }
        }
        return array_values($tokens);
    }

    /**
     * @return array<int,string>
     */
    private static function tokens(string $csv): array
    {
        $out = [];
        foreach (preg_split('/\s*,\s*/', strtolower(trim($csv))) ?: [] as $token) {
            $token = trim($token);
            if ($token !== '') {
                $out[$token] = $token;
            }
        }
        return array_values($out);
    }

    private static function operatorOwner(string $view): string
    {
        return match ($view) {
            'sbaio' => 'sbaio',
            'dashboard', 'work-entry', 'data-exchange', 'critical', 'recent', 'tasks',
            'handoff', 'account', 'notifications', 'messages', 'preferences' => 'my_work',
            default => 'manufacturing',
        };
    }

    /**
     * @return array<int,string>
     */
    private static function operatorModule(string $view): array
    {
        return match ($view) {
            'production', 'machines', 'processing' => ['production'],
            'demand', 'orders' => ['demands'],
            'parts', 'materials' => ['materials'],
            'coverage' => ['coverage'],
            'assembly' => ['assembly'],
            'qc' => ['qc'],
            'fulfillment', 'dispatch' => ['dispatch'],
            'preparation' => ['dispatch'],
            default => [],
        };
    }

    private static function adminCardOwner(string $key): string
    {
        return match ($key) {
            'platform_setup', 'access_control_board', 'user_control_board',
            'user_dashboard', 'admin_tools', 'route_diagnostics' => 'platform',
            default => 'manufacturing',
        };
    }

    private static function adminCardRoute(string $key): string
    {
        return match ($key) {
            'platform_setup' => '/admin/setup',
            'access_control_board' => '/ops/access-control',
            'user_control_board' => '/ops/user-control',
            'user_dashboard' => '/ops/user-dashboard',
            'admin_tools' => '/admin/apps',
            'route_diagnostics' => '/admin/routes',
            default => '/admin/{user}',
        };
    }

    private static function humanize(string $token): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $token));
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
