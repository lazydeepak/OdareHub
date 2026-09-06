<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Phase 1F: Guarded navigation → view linking proposal for Studio-generated artifacts.
 *
 * This service handles the Analyze / Changes / Apply safety gate for proposing a
 * nav url update on a Generated app's navigation.php. It operates only on
 * apps/Generated/ artifacts — code-managed navigations (Manufacturing, Platform, etc.)
 * are owned by those apps and must not be written here.
 *
 * Ownership rule: Studio may write to apps/Generated/{appKey}/{moduleKey}/navigation.php.
 * No other paths are permitted.
 *
 * Safety gates enforced:
 *   1. Proposed URL must pass the safe-route-path regex.
 *   2. Target file must be inside apps/Generated/ (no path traversal).
 *   3. Apply requires a fingerprint computed from the analysis result.
 *   4. Fingerprint mismatch is rejected (prevents double-apply and stale apply).
 *   5. Update mode requires explicit acknowledgment when an existing URL is present.
 *   6. Studio never grants permissions — nav url is presentation metadata only.
 *   7. Nav file is validated as a Studio-generated navigation.v1 contract before writing.
 */
final class StudioNavLinkingService
{
    private const VERSION = 'studio.nav_linking.v1';

    // Relative path prefix that applies are restricted to.
    private const GENERATED_ROOT = 'apps/Generated';

    // URL must match this pattern (same regex as route linking).
    private const SAFE_ROUTE_REGEX = '#^/apps/[a-z0-9][a-z0-9_\-]*(/[a-z0-9][a-z0-9_\-]*){1,3}$#';

    // Max proposed URL length.
    private const MAX_URL_LENGTH = 200;

    /**
     * Analyze a proposed nav → view link.
     *
     * @param array<string,mixed> $params {
     *   app_key: string,
     *   module_key: string,
     *   proposed_url: string,
     *   mode: 'create'|'update',
     *   current_url: string,            // current nav target_route, may be empty
     *   nav_label: string,              // display label, pre-filled from bundle
     *   upgrade_acknowledged: bool,     // required when mode=update and current_url non-empty
     * }
     * @return array<string,mixed>
     */
    public static function analyzeProposal(array $params): array
    {
        $appKey = self::generatedKey((string)($params['app_key'] ?? ''));
        $moduleKey = self::generatedKey((string)($params['module_key'] ?? ''));
        $proposedUrl = trim((string)($params['proposed_url'] ?? ''));
        $mode = strtolower(trim((string)($params['mode'] ?? 'create')));
        if (!in_array($mode, ['create', 'update'], true)) {
            $mode = 'create';
        }
        $currentUrl = trim((string)($params['current_url'] ?? ''));
        $navLabel = trim((string)($params['nav_label'] ?? ''));
        $upgradeAcknowledged = !empty($params['upgrade_acknowledged']);

        $blocked = 0;
        $warnings = 0;
        $changes = [];
        $errors = [];

        // Validate identity
        if ($appKey === '' || $moduleKey === '') {
            $errors[] = ['code' => 'missing_identity', 'message' => 'app_key and module_key are required.'];
            $blocked++;
        }

        // Build nav file path
        $navRelPath = '';
        $navAbsPath = '';
        if ($appKey !== '' && $moduleKey !== '') {
            $navRelPath = self::GENERATED_ROOT . '/' . $appKey . '/' . $moduleKey . '/navigation.php';
            $navAbsPath = self::absolutePath($navRelPath);

            if (!self::isSafeGeneratedNavPath($navRelPath)) {
                $errors[] = ['code' => 'unsafe_target_path', 'message' => 'Target navigation.php path failed safety check.'];
                $blocked++;
                $navRelPath = '';
                $navAbsPath = '';
            }
        }

        // Validate proposed URL
        if ($proposedUrl === '') {
            $errors[] = ['code' => 'empty_proposed_url', 'message' => 'proposed_url must not be empty.'];
            $blocked++;
        } elseif (strlen($proposedUrl) > self::MAX_URL_LENGTH) {
            $errors[] = ['code' => 'url_too_long', 'message' => 'proposed_url exceeds maximum allowed length.'];
            $blocked++;
        } elseif (!self::isSafeGeneratedRoutePath($proposedUrl)) {
            $errors[] = ['code' => 'unsafe_route_path', 'message' => 'proposed_url must match /apps/{app}/{...} and contain only lowercase alphanumeric, hyphens, underscores, and slashes.'];
            $blocked++;
        }

        // Check upgrade acknowledgment
        if ($mode === 'update' && $currentUrl !== '' && !$upgradeAcknowledged) {
            $errors[] = ['code' => 'upgrade_not_acknowledged', 'message' => 'Changing an existing nav URL requires upgrade_acknowledged=true. Verify this is intentional.'];
            $blocked++;
        }

        // Warn if no-op
        if ($proposedUrl !== '' && $proposedUrl === $currentUrl) {
            $warnings++;
            $errors[] = ['code' => 'url_unchanged', 'message' => 'proposed_url is identical to the current nav URL. No change will be made.', 'severity' => 'warning'];
        }

        // Read existing nav file values for change description
        $existingNav = [];
        if ($navAbsPath !== '' && is_file($navAbsPath)) {
            $existingNav = self::readNavFile($navAbsPath);
        }

        // Build change description
        if ($proposedUrl !== '' && $appKey !== '' && $moduleKey !== '' && $navRelPath !== '') {
            $changeType = ($existingNav !== [] && $currentUrl !== '') ? 'update' : 'set';
            $changes[] = [
                'target_file' => $navRelPath,
                'field' => 'url',
                'before' => $currentUrl !== '' ? $currentUrl : null,
                'after' => $proposedUrl,
                'change_type' => $changeType,
                'severity' => $currentUrl !== '' ? 'additive' : 'safe',
            ];
        }

        $canApply = $blocked === 0;

        $plan = [];
        $fingerprint = '';
        if ($canApply && $proposedUrl !== '' && $navRelPath !== '') {
            // Resolve nav meta (prefer existing file values, then provided, then derived)
            $existingKey = (string)($existingNav['key'] ?? '');
            $existingLabel = (string)($existingNav['label'] ?? '');
            $existingSection = (string)($existingNav['section'] ?? '');
            $existingOrder = isset($existingNav['order']) ? (int)$existingNav['order'] : 50;
            $existingPriority = isset($existingNav['priority']) ? (int)$existingNav['priority'] : 80;

            $navKeyFinal = $existingKey !== '' ? $existingKey : ($appKey . '_' . $moduleKey);
            $navLabelFinal = $navLabel !== '' ? $navLabel : ($existingLabel !== '' ? $existingLabel : str_replace('_', ' ', $moduleKey));
            $navSectionFinal = $existingSection !== '' ? $existingSection : $appKey;

            $plan = self::buildChangePlan([
                'app_key' => $appKey,
                'module_key' => $moduleKey,
                'proposed_url' => $proposedUrl,
                'current_url' => $currentUrl,
                'mode' => $mode,
                'nav_rel_path' => $navRelPath,
                'nav_key' => $navKeyFinal,
                'nav_label' => $navLabelFinal,
                'nav_section' => $navSectionFinal,
                'nav_order' => $existingOrder,
                'nav_priority' => $existingPriority,
            ]);
            $fingerprint = self::computePlanFingerprint($plan);
        }

        return [
            'version' => self::VERSION,
            'mode' => $mode,
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'proposed_url' => $proposedUrl,
            'current_url' => $currentUrl,
            'analyze' => [
                'target_artifact' => 'generated_nav',
                'nav_path' => $navRelPath,
                'changes_count' => count($changes),
                'nav_file_exists' => $navAbsPath !== '' && is_file($navAbsPath),
            ],
            'changes' => $changes,
            'errors' => $errors,
            'apply_gate' => [
                'status' => $canApply ? 'READY' : 'BLOCKED',
                'can_apply' => $canApply,
                'blocked_count' => $blocked,
                'warning_count' => $warnings,
                'required_gate' => 'studio_nav_link_v1',
            ],
            'plan' => $plan,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Build a deterministic, non-mutating change plan from analysis inputs.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public static function buildChangePlan(array $params): array
    {
        return [
            'version' => self::VERSION,
            'app_key' => (string)($params['app_key'] ?? ''),
            'module_key' => (string)($params['module_key'] ?? ''),
            'mode' => (string)($params['mode'] ?? 'create'),
            'proposed_url' => (string)($params['proposed_url'] ?? ''),
            'current_url' => (string)($params['current_url'] ?? ''),
            'nav_rel_path' => (string)($params['nav_rel_path'] ?? ''),
            'nav_key' => (string)($params['nav_key'] ?? ''),
            'nav_label' => (string)($params['nav_label'] ?? ''),
            'nav_section' => (string)($params['nav_section'] ?? ''),
            'nav_order' => (int)($params['nav_order'] ?? 50),
            'nav_priority' => (int)($params['nav_priority'] ?? 80),
        ];
    }

    /**
     * Compute a deterministic fingerprint for a change plan.
     */
    public static function computePlanFingerprint(array $plan): string
    {
        $material = json_encode([
            'app_key' => (string)($plan['app_key'] ?? ''),
            'module_key' => (string)($plan['module_key'] ?? ''),
            'proposed_url' => (string)($plan['proposed_url'] ?? ''),
            'current_url' => (string)($plan['current_url'] ?? ''),
            'mode' => (string)($plan['mode'] ?? ''),
            'nav_key' => (string)($plan['nav_key'] ?? ''),
            'nav_label' => (string)($plan['nav_label'] ?? ''),
            'nav_section' => (string)($plan['nav_section'] ?? ''),
            'nav_order' => (int)($plan['nav_order'] ?? 0),
            'nav_priority' => (int)($plan['nav_priority'] ?? 0),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($material)) {
            return '';
        }

        return 'nav-link:' . substr(sha1($material), 0, 20);
    }

    /**
     * Apply an approved nav-link plan with fingerprint confirmation.
     *
     * @param array<string,mixed> $plan
     * @param string $confirmedFingerprint
     * @return array<string,mixed>
     */
    public static function applyApprovedPlan(array $plan, string $confirmedFingerprint): array
    {
        // Verify fingerprint
        $expectedFingerprint = self::computePlanFingerprint($plan);
        if ($expectedFingerprint === '' || $confirmedFingerprint === '') {
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'missing_fingerprint', 'message' => 'Apply fingerprint is required.'];
        }
        if (!hash_equals($expectedFingerprint, $confirmedFingerprint)) {
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'fingerprint_mismatch', 'message' => 'Apply fingerprint does not match the analyzed plan. Re-analyze before applying.'];
        }

        $appKey = (string)($plan['app_key'] ?? '');
        $moduleKey = (string)($plan['module_key'] ?? '');
        $proposedUrl = trim((string)($plan['proposed_url'] ?? ''));
        $navRelPath = trim((string)($plan['nav_rel_path'] ?? ''));
        $navKey = (string)($plan['nav_key'] ?? '');
        $navLabel = (string)($plan['nav_label'] ?? '');
        $navSection = (string)($plan['nav_section'] ?? '');
        $navOrder = (int)($plan['nav_order'] ?? 50);
        $navPriority = (int)($plan['nav_priority'] ?? 80);

        if ($proposedUrl === '') {
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'empty_proposed_url', 'message' => 'Plan contains no proposed URL.'];
        }
        if (!self::isSafeGeneratedRoutePath($proposedUrl)) {
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'unsafe_route_path', 'message' => 'Proposed URL failed safety check during apply.'];
        }
        if (!self::isSafeGeneratedNavPath($navRelPath)) {
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'unsafe_target_path', 'message' => 'Target navigation.php path failed safety check during apply.'];
        }

        $navAbsPath = self::absolutePath($navRelPath);
        $navDir = dirname($navAbsPath);

        if (!is_dir($navDir)) {
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'module_dir_not_found', 'message' => 'Module directory not found for ' . $appKey . '/' . $moduleKey . '.'];
        }

        // Read existing values to fill any gaps not provided in the plan
        $previousUrl = '';
        if (is_file($navAbsPath)) {
            $existing = self::readNavFile($navAbsPath);
            $previousUrl = (string)($existing['url'] ?? '');
            if ($navKey === '') {
                $navKey = (string)($existing['key'] ?? '');
            }
            if ($navLabel === '') {
                $navLabel = (string)($existing['label'] ?? '');
            }
            if ($navSection === '') {
                $navSection = (string)($existing['section'] ?? '');
            }
            if ($navOrder === 50 && isset($existing['order'])) {
                $navOrder = (int)$existing['order'];
            }
            if ($navPriority === 80 && isset($existing['priority'])) {
                $navPriority = (int)$existing['priority'];
            }
        }

        // Apply derived defaults for missing fields
        if ($navKey === '') {
            $navKey = $appKey . '_' . $moduleKey;
        }
        if ($navLabel === '') {
            $navLabel = str_replace('_', ' ', $moduleKey);
        }
        if ($navSection === '') {
            $navSection = $appKey;
        }

        // Regenerate navigation.php
        $newContent = self::generateNavigationFileContent(
            $appKey,
            $moduleKey,
            $navKey,
            $navLabel,
            $navSection,
            $proposedUrl,
            $navOrder,
            $navPriority
        );

        $tmp = $navDir . '/.' . basename($navRelPath, '.php') . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $wrote = file_put_contents($tmp, $newContent, LOCK_EX) !== false && rename($tmp, $navAbsPath);
        if (!$wrote) {
            @unlink($tmp);
            return ['ok' => false, 'status' => 'FAILED', 'code' => 'write_failed', 'message' => 'Failed to write navigation.php.'];
        }

        return [
            'ok' => true,
            'status' => 'APPLIED',
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'proposed_url' => $proposedUrl,
            'applied' => [[
                'target_file' => $navRelPath,
                'field' => 'url',
                'previous_value' => $previousUrl,
                'new_value' => $proposedUrl,
            ]],
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function isSafeGeneratedNavPath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
            return false;
        }
        return preg_match('#^apps/Generated/[a-z0-9][a-z0-9_]*(/[a-z0-9][a-z0-9_]*)+/navigation\.php$#i', $path) === 1;
    }

    private static function isSafeGeneratedRoutePath(string $path): bool
    {
        return preg_match(self::SAFE_ROUTE_REGEX, $path) === 1;
    }

    private static function generatedKey(string $key): string
    {
        $clean = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($key)));
        return is_string($clean) ? $clean : '';
    }

    private static function absolutePath(string $relPath): string
    {
        return dirname(__DIR__, 3) . '/' . $relPath;
    }

    /**
     * Read nav fields from navigation.php using regex extraction (no eval/require).
     *
     * @return array<string,mixed>
     */
    public static function readNavFile(string $absPath): array
    {
        if (!is_file($absPath)) {
            return [];
        }
        $raw = @file_get_contents($absPath);
        if ($raw === false || $raw === '') {
            return [];
        }

        // Only accept Studio-generated navigation.v1 contracts
        if (!str_contains($raw, "'contract' => 'navigation.v1'")) {
            return [];
        }

        $extractString = static function (string $pattern) use ($raw): string {
            return preg_match($pattern, $raw, $m) === 1 ? trim((string)($m[1] ?? '')) : '';
        };
        $extractInt = static function (string $pattern) use ($raw): int {
            return preg_match($pattern, $raw, $m) === 1 ? (int)($m[1] ?? 0) : 0;
        };

        return [
            'key' => $extractString("/'key'\\s*=>\\s*'([^']+)'/"),
            'label' => $extractString("/'label'\\s*=>\\s*'([^']+)'/"),
            'url' => $extractString("/'url'\\s*=>\\s*'([^']+)'/"),
            'section' => $extractString("/'section'\\s*=>\\s*'([^']+)'/"),
            'visible_if' => $extractString("/'visible_if'\\s*=>\\s*'([^']+)'/"),
            'order' => $extractInt("/'order'\\s*=>\\s*([0-9]+)/"),
            'priority' => $extractInt("/'priority'\\s*=>\\s*([0-9]+)/"),
        ];
    }

    /**
     * Generate navigation.php content from fields.
     * Uses the same template as GuiStudioService::generatedNavigationFile().
     */
    private static function generateNavigationFileContent(
        string $appKey,
        string $moduleKey,
        string $navKey,
        string $navLabel,
        string $navSection,
        string $navUrl,
        int $navOrder,
        int $navPriority
    ): string {
        return "<?php\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'contract' => 'navigation.v1',\n"
            . "    'owner' => " . var_export($appKey, true) . ",\n"
            . "    'items' => [[\n"
            . "        'source_key' => 'studio.generated." . $appKey . "." . $moduleKey . "',\n"
            . "        'group' => 'Apps',\n"
            . "        'section' => " . var_export($navSection, true) . ",\n"
            . "        'module' => " . var_export($moduleKey, true) . ",\n"
            . "        'owner' => " . var_export($appKey, true) . ",\n"
            . "        'key' => " . var_export($navKey, true) . ",\n"
            . "        'label' => " . var_export($navLabel, true) . ",\n"
            . "        'url' => " . var_export($navUrl, true) . ",\n"
            . "        'visible_if' => 'role_platform_admin_or_sysadmin',\n"
            . "        'nav_visible' => true,\n"
            . "        'order' => " . $navOrder . ",\n"
            . "        'priority' => " . $navPriority . ",\n"
            . "    ]],\n"
            . "];\n";
    }
}
