<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Phase 1E: Guarded route → view linking proposal for Studio-generated artifacts.
 *
 * This service handles the Analyze / Changes / Apply safety gate for proposing a
 * route_path update on a Generated app view. It intentionally operates only on
 * apps/Generated/ artifacts — code-managed app routes (Manufacturing, Platform,
 * etc.) are owned by those apps and must not be written here.
 *
 * Ownership rule: Studio may write to apps/Generated/{appKey}/{moduleKey}/manifest.json
 * (and module.json if present). No other paths are permitted.
 *
 * Safety gates enforced:
 *   1. Proposed route must pass the safe-route-path regex.
 *   2. Target files must be inside apps/Generated/ (no path traversal).
 *   3. Apply requires a fingerprint computed from the analysis result.
 *   4. Fingerprint mismatch is rejected (prevents double-apply and stale apply).
 *   5. Upgrade mode requires explicit acknowledgment when an existing route is present.
 *   6. Studio never grants permissions — route_path is presentation metadata only.
 */
final class StudioRouteLinkingService
{
    private const VERSION = 'studio.route_linking.v1';

    // Relative path prefix that applies are restricted to.
    private const GENERATED_ROOT = 'apps/Generated';

    // Route path must match this pattern (same as GuiStudioService::isSafeGeneratedRoutePath).
    private const SAFE_ROUTE_REGEX = '#^/apps/[a-z0-9][a-z0-9_\-]*(/[a-z0-9][a-z0-9_\-]*){1,3}$#';

    // Max proposed route length to prevent abuse.
    private const MAX_ROUTE_LENGTH = 200;

    /**
     * Analyze a proposed route → view link.
     *
     * @param array<string,mixed> $params {
     *   app_key: string,
     *   module_key: string,
     *   proposed_route: string,
     *   mode: 'create'|'upgrade',
     *   current_route: string,         // optional, current value from loaded bundle
     *   upgrade_acknowledged: bool,    // required when mode=upgrade and current_route is non-empty
     * }
     * @return array<string,mixed>
     */
    public static function analyzeProposal(array $params): array
    {
        $appKey = self::generatedKey((string)($params['app_key'] ?? ''));
        $moduleKey = self::generatedKey((string)($params['module_key'] ?? ''));
        $proposedRoute = trim((string)($params['proposed_route'] ?? ''));
        $mode = strtolower(trim((string)($params['mode'] ?? 'create')));
        if (!in_array($mode, ['create', 'upgrade'], true)) {
            $mode = 'create';
        }
        $currentRoute = trim((string)($params['current_route'] ?? ''));
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

        // Validate the target files exist (only Generated artifacts are supported)
        $manifestRelPath = '';
        $manifestAbsPath = '';
        $moduleJsonRelPath = '';
        $moduleJsonAbsPath = '';
        if ($appKey !== '' && $moduleKey !== '') {
            $manifestRelPath = self::GENERATED_ROOT . '/' . $appKey . '/' . $moduleKey . '/manifest.json';
            $manifestAbsPath = self::absolutePath($manifestRelPath);
            $moduleJsonRelPath = self::GENERATED_ROOT . '/' . $appKey . '/' . $moduleKey . '/module.json';
            $moduleJsonAbsPath = self::absolutePath($moduleJsonRelPath);

            if (!self::isSafeGeneratedManifestPath($manifestRelPath)) {
                $errors[] = ['code' => 'unsafe_target_path', 'message' => 'Target manifest path failed safety check.'];
                $blocked++;
            } elseif (!is_file($manifestAbsPath)) {
                $errors[] = ['code' => 'manifest_not_found', 'message' => 'Generated app manifest not found for ' . $appKey . '/' . $moduleKey . '. This view may not be a Studio-generated artifact.'];
                $blocked++;
            }
        }

        // Validate proposed route
        if ($proposedRoute === '') {
            $errors[] = ['code' => 'empty_proposed_route', 'message' => 'proposed_route must not be empty.'];
            $blocked++;
        } elseif (strlen($proposedRoute) > self::MAX_ROUTE_LENGTH) {
            $errors[] = ['code' => 'route_too_long', 'message' => 'proposed_route exceeds maximum allowed length.'];
            $blocked++;
        } elseif (!self::isSafeGeneratedRoutePath($proposedRoute)) {
            $errors[] = ['code' => 'unsafe_route_path', 'message' => 'proposed_route must match /apps/{app}/{...} and contain only lowercase alphanumeric, hyphens, underscores, and slashes.'];
            $blocked++;
        }

        // Check upgrade acknowledgment
        if ($mode === 'upgrade' && $currentRoute !== '' && !$upgradeAcknowledged) {
            $errors[] = ['code' => 'upgrade_not_acknowledged', 'message' => 'Changing an existing route requires upgrade_acknowledged=true. Verify this is intentional.'];
            $blocked++;
        }

        // Warn if proposed route matches current (no-op)
        if ($proposedRoute !== '' && $proposedRoute === $currentRoute) {
            $warnings++;
            $errors[] = ['code' => 'route_unchanged', 'message' => 'proposed_route is identical to the current route_path. No change will be made.', 'severity' => 'warning'];
        }

        // Build the change description (even if blocked, so the UI can show what would happen)
        if ($proposedRoute !== '' && $appKey !== '' && $moduleKey !== '') {
            $changes[] = [
                'target_file' => $manifestRelPath,
                'field' => 'route_path',
                'before' => $currentRoute !== '' ? $currentRoute : null,
                'after' => $proposedRoute,
                'change_type' => $currentRoute !== '' ? 'update' : 'set',
                'severity' => $currentRoute !== '' ? 'additive' : 'safe',
            ];

            // Also track module.json if it exists
            if (is_file($moduleJsonAbsPath)) {
                $changes[] = [
                    'target_file' => $moduleJsonRelPath,
                    'field' => 'route_path',
                    'before' => $currentRoute !== '' ? $currentRoute : null,
                    'after' => $proposedRoute,
                    'change_type' => $currentRoute !== '' ? 'update' : 'set',
                    'severity' => $currentRoute !== '' ? 'additive' : 'safe',
                ];
            }
        }

        $canApply = $blocked === 0;

        $plan = [];
        $fingerprint = '';
        if ($canApply && $proposedRoute !== '') {
            $plan = self::buildChangePlan([
                'app_key' => $appKey,
                'module_key' => $moduleKey,
                'proposed_route' => $proposedRoute,
                'current_route' => $currentRoute,
                'mode' => $mode,
                'manifest_rel_path' => $manifestRelPath,
                'module_json_rel_path' => is_file($moduleJsonAbsPath) ? $moduleJsonRelPath : '',
            ]);
            $fingerprint = self::computePlanFingerprint($plan);
        }

        return [
            'version' => self::VERSION,
            'mode' => $mode,
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'proposed_route' => $proposedRoute,
            'current_route' => $currentRoute,
            'analyze' => [
                'target_artifact' => 'generated_view',
                'manifest_path' => $manifestRelPath,
                'changes_count' => count($changes),
            ],
            'changes' => $changes,
            'errors' => $errors,
            'apply_gate' => [
                'status' => $canApply ? 'READY' : 'BLOCKED',
                'can_apply' => $canApply,
                'blocked_count' => $blocked,
                'warning_count' => $warnings,
                'required_gate' => 'studio_route_link_v1',
            ],
            'plan' => $plan,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Build a deterministic, non-mutating change plan from an analysis.
     *
     * @param array<string,mixed> $params
     * @return array<string,mixed>
     */
    public static function buildChangePlan(array $params): array
    {
        $appKey = (string)($params['app_key'] ?? '');
        $moduleKey = (string)($params['module_key'] ?? '');
        $proposedRoute = (string)($params['proposed_route'] ?? '');
        $currentRoute = (string)($params['current_route'] ?? '');
        $mode = (string)($params['mode'] ?? 'create');
        $manifestRelPath = (string)($params['manifest_rel_path'] ?? '');
        $moduleJsonRelPath = (string)($params['module_json_rel_path'] ?? '');

        $writes = [];
        if ($manifestRelPath !== '' && $proposedRoute !== '') {
            $writes[] = [
                'target_file' => $manifestRelPath,
                'field' => 'route_path',
                'value' => $proposedRoute,
            ];
        }
        if ($moduleJsonRelPath !== '' && $proposedRoute !== '') {
            $writes[] = [
                'target_file' => $moduleJsonRelPath,
                'field' => 'route_path',
                'value' => $proposedRoute,
            ];
        }

        return [
            'version' => self::VERSION,
            'app_key' => $appKey,
            'module_key' => $moduleKey,
            'mode' => $mode,
            'proposed_route' => $proposedRoute,
            'current_route' => $currentRoute,
            'writes' => $writes,
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
            'proposed_route' => (string)($plan['proposed_route'] ?? ''),
            'current_route' => (string)($plan['current_route'] ?? ''),
            'mode' => (string)($plan['mode'] ?? ''),
            'writes' => array_values(array_map(static function (array $w): array {
                return [
                    'target_file' => (string)($w['target_file'] ?? ''),
                    'field' => (string)($w['field'] ?? ''),
                    'value' => (string)($w['value'] ?? ''),
                ];
            }, array_values(array_filter(is_array($plan['writes'] ?? null) ? $plan['writes'] : [], 'is_array')))),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($material)) {
            return '';
        }

        return 'route-link:' . substr(sha1($material), 0, 20);
    }

    /**
     * Apply an approved route-link plan with fingerprint confirmation.
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
            return [
                'ok' => false,
                'status' => 'FAILED',
                'code' => 'missing_fingerprint',
                'message' => 'Apply fingerprint is required.',
            ];
        }
        if (!hash_equals($expectedFingerprint, $confirmedFingerprint)) {
            return [
                'ok' => false,
                'status' => 'FAILED',
                'code' => 'fingerprint_mismatch',
                'message' => 'Apply fingerprint does not match the analyzed plan. Re-analyze before applying.',
            ];
        }

        $proposedRoute = trim((string)($plan['proposed_route'] ?? ''));
        if ($proposedRoute === '') {
            return [
                'ok' => false,
                'status' => 'FAILED',
                'code' => 'empty_proposed_route',
                'message' => 'Plan contains no proposed route.',
            ];
        }

        if (!self::isSafeGeneratedRoutePath($proposedRoute)) {
            return [
                'ok' => false,
                'status' => 'FAILED',
                'code' => 'unsafe_route_path',
                'message' => 'Proposed route failed safety check during apply.',
            ];
        }

        $writes = array_values(array_filter(is_array($plan['writes'] ?? null) ? $plan['writes'] : [], 'is_array'));
        if ($writes === []) {
            return [
                'ok' => false,
                'status' => 'FAILED',
                'code' => 'empty_plan_writes',
                'message' => 'Plan contains no write operations.',
            ];
        }

        $applied = [];
        $failed = [];

        foreach ($writes as $write) {
            $relPath = (string)($write['target_file'] ?? '');
            $field = (string)($write['field'] ?? '');
            $value = (string)($write['value'] ?? '');

            if ($relPath === '' || $field === '' || $value === '') {
                $failed[] = ['target_file' => $relPath, 'reason' => 'incomplete_write_spec'];
                continue;
            }

            if (!self::isSafeGeneratedManifestPath($relPath)) {
                $failed[] = ['target_file' => $relPath, 'reason' => 'unsafe_target_path'];
                continue;
            }

            $absPath = self::absolutePath($relPath);
            if (!is_file($absPath)) {
                $failed[] = ['target_file' => $relPath, 'reason' => 'file_not_found'];
                continue;
            }

            $raw = @file_get_contents($absPath);
            if ($raw === false) {
                $failed[] = ['target_file' => $relPath, 'reason' => 'read_failed'];
                continue;
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                $failed[] = ['target_file' => $relPath, 'reason' => 'invalid_json'];
                continue;
            }

            // Write the field — only route_path is supported in Phase 1E
            if ($field !== 'route_path') {
                $failed[] = ['target_file' => $relPath, 'reason' => 'unsupported_field_' . $field];
                continue;
            }

            $previousValue = (string)($data[$field] ?? '');
            $data[$field] = $value;
            $data['route_link_updated_at'] = gmdate('c');

            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                $failed[] = ['target_file' => $relPath, 'reason' => 'json_encode_failed'];
                continue;
            }

            $dir = dirname($absPath);
            $tmp = $dir . '/.' . basename($relPath, '.json') . '.' . bin2hex(random_bytes(4)) . '.tmp';
            $wrote = file_put_contents($tmp, $json, LOCK_EX) !== false && rename($tmp, $absPath);
            if (!$wrote) {
                @unlink($tmp);
                $failed[] = ['target_file' => $relPath, 'reason' => 'write_failed'];
                continue;
            }

            $applied[] = [
                'target_file' => $relPath,
                'field' => $field,
                'previous_value' => $previousValue,
                'new_value' => $value,
            ];
        }

        $ok = $failed === [] && $applied !== [];

        return [
            'ok' => $ok,
            'status' => $ok ? 'APPLIED' : ($applied !== [] ? 'PARTIAL' : 'FAILED'),
            'app_key' => (string)($plan['app_key'] ?? ''),
            'module_key' => (string)($plan['module_key'] ?? ''),
            'proposed_route' => $proposedRoute,
            'applied' => $applied,
            'failed' => $failed,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function isSafeGeneratedManifestPath(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, '..')) {
            return false;
        }
        return (bool)preg_match(
            '#^apps/Generated/[a-z0-9_]+/[a-z0-9_]+/(manifest|module)\.json$#',
            $path
        );
    }

    private static function isSafeGeneratedRoutePath(string $routePath): bool
    {
        if ($routePath === '' || str_contains($routePath, '..') || str_contains($routePath, '\\')) {
            return false;
        }
        return (bool)preg_match(self::SAFE_ROUTE_REGEX, $routePath);
    }

    private static function generatedKey(string $value): string
    {
        $key = strtolower(trim($value));
        $key = str_replace('-', '_', $key);
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        return trim($key, '_');
    }

    private static function absolutePath(string $relativePath): string
    {
        return rtrim((string)(defined('APP_ROOT') ? APP_ROOT : ''), '/') . '/' . ltrim($relativePath, '/');
    }
}
