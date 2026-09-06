<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\View;
use Apps\Platform\Services\UserAssignmentContext;
use Apps\Shell\Composers\AdminSurfaceComposer;
use Apps\Shell\Services\WorkspaceWrapperRegistry;
use Plugins\Base\Services\ResolvedExperienceConsumerService;

require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';

/**
 * AdminLayerService
 *
 * Orchestrates the role-filtered admin layer for administrative accounts.
 *
 * Analogous to OperatorLayerService for the /u layer.
 *
 * Responsibilities:
 * - Authentication guard (redirect to /login if not authenticated)
 * - Authorization guard (platform_admin and app_admin may access the admin wrapper)
 * - Context resolution via UserAssignmentContext
 * - Handoff to AdminSurfaceComposer for full-page rendering
 *
 * Security:
 * - Auth check at service layer — not deferred to template
 * - Account type is verified from server-side session context, not from request parameters
 * - Non-admin users are redirected to their canonical landing (usually /u/{username})
 */
final class AdminLayerService
{
    /**
     * Render the admin layer (/me) page.
     *
     * @param View $view  View renderer instance (injected from routes.php)
     * @param array<string,mixed> $query  Query parameters from $_GET
     * @param string $intendedUrl  URL to remember for post-login redirect
     */
    public static function render(View $view, array $query = [], string $intendedUrl = '/'): void
    {
        Auth::bootSession();

        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl($intendedUrl);
            header('Location: /login', true, 302);
            exit;
        }

        $userRaw = Auth::user();
        $user = is_array($userRaw) ? $userRaw : (array)$userRaw;

        $ctx = UserAssignmentContext::context()->resolveUserContext($user);

        // The wrapper registry admits administrative account types. Route and
        // capability authorization still filter every destination within it.
        if (!WorkspaceWrapperRegistry::canAccessWrapper($ctx, WorkspaceWrapperRegistry::WRAPPER_ADMIN)) {
            $username = WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($user['username'] ?? ''),
                'email'    => (string)($user['email'] ?? ''),
            ]);
            $target = WorkspaceWrapperRegistry::landingPath($ctx, $username);
            header('Location: ' . $target, true, 302);
            exit;
        }

        $ctx = self::applyResolvedExperienceAdminOverrides($user, $ctx);

        $composer = new AdminSurfaceComposer($view, $user, $ctx, $query);
        $composer->renderHTML();
    }

    /**
     * Phase 5 cutover: route the admin `/me` blocks and cards through the
     * ResolvedExperience consumer. The consumer delegates account-type policy
     * back to the canonical normalize routines, so the rendered output is
     * unchanged but the data path now flows through ResolvedExperience.
     *
     * Logs a parity warning when the consumer disagrees with the legacy ctx
     * value so any regression during the migration window is visible.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $ctx
     * @return array<string,mixed>
     */
    private static function applyResolvedExperienceAdminOverrides(array $user, array $ctx): array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            return $ctx;
        }
        try {
            $row = DB::fetchOne(
                'SELECT user_id, authority_role, dashboard_type, assigned_apps, module_visibility, display_surfaces, workspace_profile_key, operator_views, me_dashboard_blocks, me_plugin_cards FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
                [$userId]
            );
            if (!is_array($row)) {
                return $ctx;
            }

            $resolvedBlocks = ResolvedExperienceConsumerService::adminDashboardBlocks($row);
            $resolvedCards = ResolvedExperienceConsumerService::adminPluginCards($row);

            self::logAdminResolvedExperienceParity($userId, $ctx, $resolvedBlocks, $resolvedCards);

            $ctx['me_dashboard_blocks'] = $resolvedBlocks;
            $ctx['me_plugin_cards'] = $resolvedCards;
            return $ctx;
        } catch (\Throwable $e) {
            error_log('[resolved_experience.parity] admin cutover failed: ' . $e->getMessage());
            return $ctx;
        }
    }

    /**
     * @param array<string,mixed> $ctx
     * @param array<int,string> $resolvedBlocks
     * @param array<int,string> $resolvedCards
     */
    private static function logAdminResolvedExperienceParity(int $userId, array $ctx, array $resolvedBlocks, array $resolvedCards): void
    {
        $legacyBlocks = array_map('strval', array_values((array)($ctx['me_dashboard_blocks'] ?? [])));
        $legacyCards = array_map('strval', array_values((array)($ctx['me_plugin_cards'] ?? [])));
        $resolvedBlocksSorted = $resolvedBlocks;
        $resolvedCardsSorted = $resolvedCards;
        sort($legacyBlocks);
        sort($legacyCards);
        sort($resolvedBlocksSorted);
        sort($resolvedCardsSorted);
        if ($legacyBlocks !== $resolvedBlocksSorted) {
            error_log(sprintf(
                '[resolved_experience.parity] surface=admin kind=dashboard_block user_id=%d legacy=%s resolved=%s',
                $userId,
                implode(',', $legacyBlocks),
                implode(',', $resolvedBlocksSorted)
            ));
        }
        if ($legacyCards !== $resolvedCardsSorted) {
            error_log(sprintf(
                '[resolved_experience.parity] surface=admin kind=quick_link_card user_id=%d legacy=%s resolved=%s',
                $userId,
                implode(',', $legacyCards),
                implode(',', $resolvedCardsSorted)
            ));
        }
    }
}
