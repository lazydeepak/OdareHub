<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Services\LogoUploadService;
use Apps\Platform\Services\UserAssignmentContext;
use Apps\Shell\Composers\DisplaySurfaceComposer;
use Apps\Shell\Services\WorkspaceWrapperRegistry;
use Plugins\Base\Services\ResolvedExperienceConsumerService;
use Plugins\Organization\Services\OrganizationService;

require_once APP_ROOT . '/plugins/Base/Services/ResolvedExperienceConsumerService.php';

/**
 * DisplayLayerService
 *
 * Orchestrates the display layer (/displays) rendering for tv_display users and
 * platform_admin preview access.
 *
 * Responsibilities:
 * - Authentication guard (redirect to /login if not authenticated)
 * - Authorization guard (tv_display or platform_admin only)
 * - Identity resolution for user-specific and device-specific displays
 * - Handoff to DisplaySurfaceComposer for full-page rendering
 *
 * Design constraints (readonly kiosk layer):
 * - No forms, no mutations — display is read-only
 * - Self-contained — no navigation escape to other surfaces
 * - Auto-refresh capable — suitable for floor TV displays
 * - Platform_admin may preview any display for configuration purposes
 */
final class DisplayLayerService
{
    /**
     * Render a user-specific display at /displays/user/{username}
     *
     * @param string $username Target display username (from URL segment)
     * @param array<string,mixed> $query Query parameters from $_GET
     */
    public static function renderUser(string $username, array $query = []): void
    {
        Auth::bootSession();

        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/displays/user/' . $username);
            header('Location: /login', true, 302);
            exit;
        }

        $userRaw = Auth::user();
        $user = is_array($userRaw) ? $userRaw : (array)$userRaw;
        $ctx = UserAssignmentContext::context()->resolveUserContext($user);
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? $user['authority_role'] ?? '')));

        // Authorization: tv_display users see their own display; platform_admin may preview any
        $isAdmin = in_array($authorityRole, ['platform_admin', 'app_admin'], true);
        if (!$isAdmin && $authorityRole !== 'tv_display') {
            // Non-display, non-admin users get redirected to their own workspace
            $resolvedHandle = WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($user['username'] ?? ''),
                'email'    => (string)($user['email'] ?? ''),
            ]);
            $target = WorkspaceWrapperRegistry::landingPath($ctx, $resolvedHandle);
            header('Location: ' . $target, true, 302);
            exit;
        }

        // For tv_display users, enforce they only see their own display unless admin
        $resolvedHandle = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($user['username'] ?? ''),
            'email'    => (string)($user['email'] ?? ''),
        ]);
        $targetHandle = WorkspaceWrapperRegistry::normalizeHandle($username);
        if (!$isAdmin && $targetHandle !== '' && $targetHandle !== $resolvedHandle) {
            header('Location: /displays/user/' . rawurlencode($resolvedHandle), true, 302);
            exit;
        }

        $displayHandle = ($targetHandle !== '') ? $targetHandle : $resolvedHandle;

        self::renderComposer($user, $ctx, 'user', $displayHandle, '', $query);
    }

    /**
     * Render a device-specific display at /displays/device/{device_id}
     *
     * @param string $deviceId Target display device ID (from URL segment)
     * @param array<string,mixed> $query Query parameters from $_GET
     */
    public static function renderDevice(string $deviceId, array $query = []): void
    {
        Auth::bootSession();

        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/displays/device/' . $deviceId);
            header('Location: /login', true, 302);
            exit;
        }

        $userRaw = Auth::user();
        $user = is_array($userRaw) ? $userRaw : (array)$userRaw;
        $ctx = UserAssignmentContext::context()->resolveUserContext($user);
        $authorityRole = strtolower(trim((string)($ctx['authority_role'] ?? $user['authority_role'] ?? '')));

        $isAdmin = in_array($authorityRole, ['platform_admin', 'app_admin'], true);
        if (!$isAdmin && $authorityRole !== 'tv_display') {
            $resolvedHandle = WorkspaceWrapperRegistry::handleFromIdentity([
                'username' => (string)($user['username'] ?? ''),
                'email'    => (string)($user['email'] ?? ''),
            ]);
            $target = WorkspaceWrapperRegistry::landingPath($ctx, $resolvedHandle);
            header('Location: ' . $target, true, 302);
            exit;
        }

        // For tv_display users, enforce device ownership unless admin
        if (!$isAdmin) {
            $ownDeviceId = trim((string)($user['display_device_id'] ?? ''));
            if ($ownDeviceId === '') {
                $resolvedHandle = WorkspaceWrapperRegistry::handleFromIdentity([
                    'username' => (string)($user['username'] ?? ''),
                    'email'    => (string)($user['email'] ?? ''),
                ]);
                $target = $resolvedHandle !== ''
                    ? '/displays/user/' . rawurlencode($resolvedHandle)
                    : '/displays';
                header('Location: ' . $target, true, 302);
                exit;
            }

            if ($ownDeviceId !== $deviceId) {
                header('Location: /displays/device/' . rawurlencode($ownDeviceId), true, 302);
                exit;
            }
        }

        self::renderComposer($user, $ctx, 'device', '', $deviceId, $query);
    }

    /**
     * Render the base /displays endpoint — redirect to user or device display.
     *
     * @param array<string,mixed> $query Query parameters from $_GET
     */
    public static function renderBase(array $query = []): void
    {
        Auth::bootSession();

        if (!Auth::isLoggedIn()) {
            Auth::rememberIntendedUrl('/displays');
            header('Location: /login', true, 302);
            exit;
        }

        $userRaw = Auth::user();
        $user = is_array($userRaw) ? $userRaw : (array)$userRaw;

        $deviceId = trim((string)($user['display_device_id'] ?? $user->display_device_id ?? ''));
        if ($deviceId !== '') {
            header('Location: /displays/device/' . rawurlencode($deviceId), true, 302);
            exit;
        }

        $username = WorkspaceWrapperRegistry::handleFromIdentity([
            'username' => (string)($user['username'] ?? ''),
            'email'    => (string)($user['email'] ?? ''),
        ]);

        if ($username !== '') {
            header('Location: /displays/user/' . rawurlencode($username), true, 302);
            exit;
        }

        header('HTTP/1.1 400 Bad Request', true, 400);
        echo '<h1>Display not configured</h1><p>No display device or user found for this session.</p>';
        exit;
    }

    /**
     * Internal: build context and invoke DisplaySurfaceComposer.
     *
     * @param array<string,mixed> $user
     * @param array<string,mixed> $ctx
     */
    private static function renderComposer(
        array $user,
        array $ctx,
        string $displayMode,
        string $username,
        string $deviceId,
        array $query
    ): void {
        $companyName = trim((string)($ctx['company_name'] ?? BrandIdentityService::instanceName()));
        $branchName = trim((string)($ctx['branch_name'] ?? $ctx['branch'] ?? $user['branch_name'] ?? $user['branch'] ?? ''));

        $logoData = \Apps\Shell\Services\LogoResolverService::resolve();
        $companyLogo = $logoData['logo_url'];
        $companyLogoIcon = $logoData['logo_icon_url'];
        $companyLogoSvgInline = $logoData['logo_svg'];
        $companyLogoSvgTheme = $logoData['logo_svg_theme'];

        $composer = new DisplaySurfaceComposer([
            'display_mode'       => $displayMode,
            'username'           => $username,
            'device_id'          => $deviceId,
            'display_panels'     => self::displayPanelsForTarget($displayMode, $username, $deviceId, $user),
            'company_name'       => $companyName,
            'branch_name'        => $branchName,
            'company_logo'       => $companyLogo,
            'company_logo_icon'  => $companyLogoIcon,
            'company_logo_svg_inline' => $companyLogoSvgInline,
            'company_logo_svg_theme'  => $companyLogoSvgTheme,
            'company_fallback_text' => $logoData['fallback_text'],
            'company_fallback_text_compact' => $logoData['fallback_text_compact'],
            'authority_role'       => (string)($ctx['authority_role'] ?? 'tv_display'),
            'current_query'      => $query,
        ]);

        header('Content-Type: text/html; charset=utf-8');
        echo $composer->render();
    }

    /**
     * @param array<string,mixed> $fallbackUser
     * @return array<int,string>
     */
    private static function displayPanelsForTarget(string $displayMode, string $username, string $deviceId, array $fallbackUser): array
    {
        $allowed = ['overview' => true, 'machines' => true, 'dispatch' => true, 'qc' => true, 'activity' => true];
        $targetUserId = 0;

        try {
            if ($displayMode === 'user' && trim($username) !== '') {
                $handle = WorkspaceWrapperRegistry::normalizeHandle($username);
                $target = DB::fetchOne(
                    "SELECT id FROM users
                      WHERE LOWER(COALESCE(NULLIF(username, ''), SUBSTRING_INDEX(email, '@', 1))) = ?
                      LIMIT 1",
                    [$handle]
                );
                $targetUserId = (int)($target['id'] ?? 0);
            }

            if ($targetUserId <= 0) {
                $targetUserId = (int)($fallbackUser['id'] ?? 0);
            }

            if ($targetUserId <= 0) {
                return array_keys($allowed);
            }

            $row = DB::fetchOne(
                'SELECT user_id, authority_role, dashboard_type, assigned_apps, module_visibility, display_surfaces, workspace_profile_key, operator_views, me_dashboard_blocks, me_plugin_cards FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
                [$targetUserId]
            );
            if (!is_array($row)) {
                return array_keys($allowed);
            }

            $resolved = ResolvedExperienceConsumerService::displayPanels($row);
            $resolved = array_values(array_filter(
                $resolved,
                static fn(string $token): bool => isset($allowed[$token])
            ));

            self::logDisplayResolvedExperienceParity($row, $resolved, $targetUserId);

            return $resolved;
        } catch (\Throwable) {
            return array_keys($allowed);
        }
    }

    /**
     * Fallback reader for the transitional `display_surfaces` CSV. Used only
     * when ResolvedExperience returns nothing (e.g. ACL or catalog drift) so
     * runtime stays available during Phase 5 migration.
     *
     * @param array<string,mixed> $row
     * @param array<string,bool> $allowed
     * @return array<int,string>
     */
    private static function legacyDisplayPanels(array $row, array $allowed): array
    {
        $raw = strtolower(trim((string)($row['display_surfaces'] ?? '')));
        if ($raw === '') {
            return array_keys($allowed);
        }
        $panels = [];
        foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $token) {
            $token = trim($token);
            if (isset($allowed[$token])) {
                $panels[$token] = $token;
            }
        }
        return $panels !== [] ? array_values($panels) : array_keys($allowed);
    }

    /**
     * Phase 5 post-cutover safety probe: compare the consumer-derived panel set
     * against the legacy CSV parse and log divergence so any regression during
     * the migration window is visible. The consumer output is authoritative.
     *
     * @param array<string,mixed>|null $row
     * @param array<int,string> $resolvedPanels
     */
    private static function logDisplayResolvedExperienceParity(?array $row, array $resolvedPanels, int $targetUserId): void
    {
        if (!is_array($row)) {
            return;
        }
        try {
            $allowed = ['overview' => true, 'machines' => true, 'dispatch' => true, 'qc' => true, 'activity' => true];
            $legacy = self::legacyDisplayPanels($row, $allowed);
            $legacySorted = $legacy;
            sort($legacySorted);
            $resolvedSorted = $resolvedPanels;
            sort($resolvedSorted);
            if ($legacySorted !== $resolvedSorted) {
                error_log(sprintf(
                    '[resolved_experience.parity] surface=display user_id=%d legacy=%s resolved=%s',
                    $targetUserId,
                    implode(',', $legacySorted),
                    implode(',', $resolvedSorted)
                ));
            }
        } catch (\Throwable $e) {
            error_log('[resolved_experience.parity] display probe failed: ' . $e->getMessage());
        }
    }
}
