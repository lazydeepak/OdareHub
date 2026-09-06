<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Services\LogoUploadService;
use Apps\Platform\Services\UserAssignmentContext;
use Apps\Shell\Composers\OperatorSurfaceComposer;
use Apps\Shell\Services\WorkspaceWrapperRegistry;
use Apps\Shell\Services\OperatorLayerSidebarService;
use Plugins\Base\Services\ResolvedExperienceConsumerService;
use Plugins\Organization\Services\OrganizationService;

require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';

final class OperatorLayerService
{
    /**
     * @param array<int,string> $assignedApps
     * @return array<int,string>
     */
    private static function resolveActiveAssignedApps(array $assignedApps): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            static fn($value): string => strtolower(trim((string)$value)),
            $assignedApps
        ), static fn(string $value): bool => $value !== '')));

        if ($normalized === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($normalized), '?'));
        try {
            $rows = DB::fetchAll(
                "SELECT app_key FROM core_apps WHERE LOWER(app_key) IN ($placeholders) AND status = 'enabled'",
                $normalized
            );
        } catch (\Throwable) {
            return [];
        }

        $enabled = [];
        foreach ((array)$rows as $row) {
            $appKey = strtolower(trim((string)($row['app_key'] ?? '')));
            if ($appKey !== '') {
                $enabled[] = $appKey;
            }
        }

        return array_values(array_intersect($normalized, array_values(array_unique($enabled))));
    }

    /**
     * Render operator layer as standalone page (no Shell wrapper)
     * @param string $username User's username (URL parameter)
     * @param array<string,mixed> $query Query parameters
     * @param string $intendedUrl Intended URL for redirect-on-login
     */
    public static function render(string $username, array $query = [], string $intendedUrl = ''): void
    {
        // ── Surface rendering pipeline ─────────────────────────────────────────────
        // Step 1: authority class   → surface jail (tv_display jailed to /displays/*)
        // Step 2: account type      → dashboard_type from resolveUserContext()
        // Step 3: app assignment    → explicit assigned_apps from DB row
        // Step 4: default app       → ctx.default_app
        // Step 5: control scope     → ctx.scope
        // Step 6: interaction profile → ctx.workspace_profile
        // Step 7: operational focus → $query['focus']
        // Step 8: home surface      → OperatorSurfaceComposer::render()
        // ──────────────────────────────────────────────────────────────────────────
        Auth::bootSession();
        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl($intendedUrl ?: '/u/' . $username);
            header('Location: /login', true, 302);
            exit;
        }

        $authenticatedUserRaw = Auth::user();
        $authenticatedUser = is_array($authenticatedUserRaw) ? $authenticatedUserRaw : (array)$authenticatedUserRaw;

        $ctx = UserAssignmentContext::context()->resolveUserContext($authenticatedUser);

        // Step 1 (defense-in-depth): authority class — tv_display is jailed to /displays/*.
        // This check also runs at the routing layer; repeated here as a safety net.
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? $authenticatedUser['authority_role'] ?? '')));
        if ($authorityRole === 'tv_display') {
            $tvHandle = WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($authenticatedUser['username'] ?? ''),
                'email'    => (string)($authenticatedUser['email'] ?? ''),
            ]);
            $target = $tvHandle !== '' ? '/displays/user/' . rawurlencode($tvHandle) : '/displays';
            header('Location: ' . $target, true, 302);
            exit;
        }

        // Step 2: account type — dashboard_type is resolved inside resolveUserContext() above.
        // Step 3: app assignment — consume resolved context as the visibility source of truth.
        $assignedApps = array_values((array)($ctx['active_assigned_apps'] ?? $ctx['assigned_apps'] ?? []));
        $operatorViews = '';
        $userId = (int)($authenticatedUser['id'] ?? 0);
        if ($userId > 0) {
            $assignmentRow = null;
            try {
                $assignmentRow = DB::fetchOne(
                    'SELECT user_id, authority_role, dashboard_type, module_visibility, operator_views FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
                    [$userId]
                );
            } catch (\Throwable) {
                try {
                    $assignmentRow = DB::fetchOne('SELECT authority_role, dashboard_type, module_visibility, operator_views FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1', [$userId]);
                } catch (\Throwable) {
                    $assignmentRow = DB::fetchOne('SELECT operator_views FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1', [$userId]);
                }
            }
            if (is_array($assignmentRow)) {
                $resolvedViews = ResolvedExperienceConsumerService::operatorViews($assignmentRow);
                $operatorViews = implode(',', $resolvedViews);
                self::logOperatorResolvedExperienceParity($assignmentRow, $resolvedViews, $userId);
            }
        }
        $activeAssignedApps = self::resolveActiveAssignedApps($assignedApps);

        $resolvedUsername = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($authenticatedUser['username'] ?? $authenticatedUser['user_name'] ?? ''),
            'email' => (string)($authenticatedUser['email'] ?? $authenticatedUser['user_email'] ?? ''),
        ]);
        if ($resolvedUsername === '') {
            $resolvedUsername = WorkspaceWrapperRegistry::normalizeHandle($username);
        }
        if ($resolvedUsername === '') {
            $resolvedUsername = $username;
        }

        // Authorization: a user may only view their own operator workspace unless they hold
        // a platform-admin or app-admin account type (e.g. supervisors reviewing operator data).
        $requestedHandle = WorkspaceWrapperRegistry::normalizeHandle($username);
        if ($requestedHandle !== '' && $requestedHandle !== $resolvedUsername) {
            $authorityRole = (string)($authenticatedUser['authority_role'] ?? $ctx['authority_role'] ?? '');
            $isPrivileged = in_array($authorityRole, ['platform_admin', 'app_admin', 'sys_admin'], true);

            if (!$isPrivileged) {
                // Redirect to the authenticated user's own workspace.
                header('Location: /u/' . rawurlencode($resolvedUsername) . '/dashboard', true, 302);
                exit;
            }
            // Privileged user is viewing another user's workspace — render as the requested handle.
            $resolvedUsername = $requestedHandle;
        }

        $userEmail = trim((string)($authenticatedUser['email'] ?? $authenticatedUser['user_email'] ?? ''));
        if ($userEmail === '') {
            $userEmail = 'operator';
        }
        $companyName = trim((string)($ctx['company_name'] ?? BrandIdentityService::instanceName()));
        $branchName = trim((string)($ctx['branch_name'] ?? $ctx['branch'] ?? $authenticatedUser['branch_name'] ?? $authenticatedUser['branch'] ?? ''));

        $logoData = \Apps\Shell\Services\LogoResolverService::resolve();
        $companyLogo = $logoData['logo_url'];
        $companyLogoIcon = $logoData['logo_icon_url'];
        $companyLogoSvgInline = $logoData['logo_svg'];
        $companyLogoSvgTheme = $logoData['logo_svg_theme'];

        // Steps 4–8: passed to OperatorSurfaceComposer as context.
        // Step 4 default_app, Step 5 scope, Step 6 workspace_profile are extracted from ctx.
        // Step 7 operational focus is the 'focus' key in $query.
        // Step 8 home surface = OperatorSurfaceComposer::render().
        $composer = new OperatorSurfaceComposer($resolvedUsername, [
            'user_email' => $userEmail,
            'company_name' => $companyName,
            'branch_name' => $branchName,
            'company_logo' => $companyLogo,
            'company_logo_icon' => $companyLogoIcon,
            'company_logo_svg_inline' => $companyLogoSvgInline,
            'company_logo_svg_theme' => $companyLogoSvgTheme,
            'company_fallback_text' => $logoData['fallback_text'],
            'company_fallback_text_compact' => $logoData['fallback_text_compact'],
            'authority_role' => (string)($ctx['authority_role'] ?? 'app_user'),          // step 1
            'dashboard_type' => (string)($ctx['dashboard_type'] ?? 'operator'),          // step 2
            'assigned_apps' => $assignedApps,                                             // step 3
            'active_assigned_apps' => $activeAssignedApps,
            'default_app' => (string)($ctx['default_app'] ?? ''),                        // step 4
            'scope' => is_array($ctx['scope'] ?? null) ? $ctx['scope'] : [],             // step 5
            'module_visibility' => is_array($ctx['module_visibility'] ?? null) ? $ctx['module_visibility'] : [], // step 5
            'workspace_profile' => is_array($ctx['workspace_profile'] ?? null) ? $ctx['workspace_profile'] : null, // step 6
            'operator_views' => $operatorViews,
            'current_query' => $query,                                                    // step 7 (contains focus)
        ]);

        header('Content-Type: text/html; charset=utf-8');
        echo $composer->render();
}

    /**
     * Phase 5 post-cutover safety probe: compare consumer-derived operator
     * views against the legacy normalizeOperatorViews() output and log any
     * divergence so we catch regressions in the migration window. The consumer
     * output is authoritative.
     *
     * @param array<string,mixed> $row
     * @param array<int,string> $resolvedViews
     */
    private static function logOperatorResolvedExperienceParity(array $row, array $resolvedViews, int $userId): void
    {
        try {
            $legacy = OperatorLayerSidebarService::normalizeOperatorViews(
                (string)($row['operator_views'] ?? '')
            );
            $resolvedSorted = $resolvedViews;
            sort($resolvedSorted);
            $legacySorted = $legacy;
            sort($legacySorted);
            if ($resolvedSorted !== $legacySorted) {
                error_log(sprintf(
                    '[resolved_experience.parity] surface=operator user_id=%d legacy=%s resolved=%s',
                    $userId,
                    implode(',', $legacySorted),
                    implode(',', $resolvedSorted)
                ));
            }
        } catch (\Throwable $e) {
            error_log('[resolved_experience.parity] operator probe failed: ' . $e->getMessage());
        }
    }
}
