<?php
declare(strict_types=1);

namespace Apps\Studio\Services;

/**
 * Studio-side gate for Workspace Profile and per-user experience override edits.
 *
 * Studio is allowed to compose presentation artifacts, but it must not create
 * access. This service analyzes proposed experience changes against a
 * ResolvedExperience snapshot and blocks anything that is unknown to the owner
 * catalog or denied by ACL.
 */
final class StudioExperienceGovernanceService
{
    private const VERSION = 'studio.experience_governance.v1';
    private const USER_OVERRIDE_TARGET_FIELDS = [
        'user_surface_overrides.operator_views' => 'operator_views',
        'user_surface_overrides.display_surfaces' => 'display_surfaces',
        'user_surface_overrides.me_dashboard_blocks' => 'me_dashboard_blocks',
        'user_surface_overrides.me_plugin_cards' => 'me_plugin_cards',
    ];
    private const WORKSPACE_PROFILE_TOKEN_FIELDS = [
        'workspace_profiles.module_visibility' => 'module_visibility',
        'workspace_profiles.dashboard_blocks' => 'dashboard_blocks',
    ];
    private const WORKSPACE_PROFILE_QUICK_ACTION_FIELDS = [
        'workspace_profiles.quick_actions' => 'quick_actions',
    ];
    private const WORKSPACE_PROFILE_NAV_SECTION_FIELDS = [
        'workspace_profiles.nav_sections' => 'nav_sections',
    ];

    /**
     * @param array<string,mixed> $resolvedExperience
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    public static function analyzeProposal(array $resolvedExperience, array $proposal): array
    {
        $artifactType = strtolower(trim((string)($proposal['artifact_type'] ?? '')));
        if (!in_array($artifactType, ['workspace_profile', 'user_override'], true)) {
            $artifactType = 'unknown';
        }

        $catalog = self::indexResolvedItems((array)($resolvedExperience['items'] ?? []));
        $requested = self::proposalItems($proposal);
        $targetArtifact = self::targetArtifact($resolvedExperience, $proposal, $artifactType);
        $changes = [];
        $blocked = 0;
        $warnings = 0;
        if (($targetArtifact['status'] ?? '') === 'blocked') {
            $blocked++;
        }

        foreach ($requested as $request) {
            $decision = self::decisionForRequest($request, $catalog, $artifactType);
            if (($decision['status'] ?? '') === 'blocked') {
                $blocked++;
            }
            if (($decision['status'] ?? '') === 'warning') {
                $warnings++;
            }
            $changes[] = $decision;
        }

        $canApply = $blocked === 0;

        return [
            'version' => self::VERSION,
            'mode' => 'analyze_changes_apply_gate',
            'artifact_type' => $artifactType,
            'surface' => (string)($proposal['surface'] ?? $resolvedExperience['surface'] ?? ''),
            'target_user_id' => (int)($proposal['target_user_id'] ?? $resolvedExperience['target_user_id'] ?? 0),
            'target_artifact' => $targetArtifact,
            'analyze' => [
                'resolved_version' => (string)($resolvedExperience['version'] ?? ''),
                'catalog_items' => count($catalog),
                'requested_changes' => count($requested),
            ],
            'changes' => $changes,
            'apply_gate' => [
                'status' => $canApply ? 'READY' : 'BLOCKED',
                'can_apply' => $canApply,
                'blocked_count' => $blocked,
                'warning_count' => $warnings,
                'required_gate' => 'acl_catalog_resolved_experience_check',
            ],
        ];
    }

    /**
     * Build a deterministic, non-mutating apply plan from a completed analysis.
     *
     * This is the "Changes -> Apply" bridge contract. It intentionally returns
     * planned writes only; callers must persist through a later approved endpoint.
     *
     * @param array<string,mixed> $analysis
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    public static function buildApplyPlan(array $analysis, array $proposal): array
    {
        $gate = is_array($analysis['apply_gate'] ?? null) ? (array)$analysis['apply_gate'] : [];
        $canApply = !empty($gate['can_apply']) && (string)($gate['status'] ?? '') === 'READY';
        $artifactType = (string)($analysis['artifact_type'] ?? $proposal['artifact_type'] ?? 'unknown');
        $surface = (string)($analysis['surface'] ?? $proposal['surface'] ?? '');
        $targetUserId = (int)($analysis['target_user_id'] ?? $proposal['target_user_id'] ?? 0);
        $targetArtifact = is_array($analysis['target_artifact'] ?? null) ? (array)$analysis['target_artifact'] : [];

        if (!$canApply) {
            return [
                'version' => self::VERSION,
                'mode' => 'apply_plan',
                'status' => 'BLOCKED',
                'can_apply' => false,
                'reason' => 'analysis_gate_blocked',
                'planned_writes' => [],
                'provenance' => self::provenance($artifactType, $surface, $targetUserId, [], $targetArtifact),
            ];
        }

        $plannedWrites = [];
        foreach ((array)($analysis['changes'] ?? []) as $change) {
            if (!is_array($change)) {
                continue;
            }
            if (!in_array((string)($change['status'] ?? ''), ['allowed', 'warning'], true)) {
                continue;
            }
            $plannedWrites[] = self::plannedWrite($artifactType, $surface, $targetUserId, $change, $targetArtifact);
        }

        return [
            'version' => self::VERSION,
            'mode' => 'apply_plan',
            'status' => 'READY',
            'can_apply' => true,
            'reason' => 'analysis_gate_ready',
            'planned_writes' => $plannedWrites,
            'provenance' => self::provenance($artifactType, $surface, $targetUserId, $plannedWrites, $targetArtifact),
        ];
    }

    /**
     * @param array<string,mixed> $resolvedExperience
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    public static function evaluateProposal(array $resolvedExperience, array $proposal): array
    {
        $analysis = self::analyzeProposal($resolvedExperience, $proposal);
        $applyPlan = self::buildApplyPlan($analysis, $proposal);

        return [
            'version' => self::VERSION,
            'mode' => 'analyze_changes_apply_plan',
            'ok' => true,
            'analysis' => $analysis,
            'apply_plan' => $applyPlan,
        ];
    }

    /**
     * Apply a READY plan. Rich Workspace Profile fields remain intentionally
     * blocked until each target field has an explicit merge strategy.
     *
     * @param array<string,mixed> $applyPlan
     * @param callable|null $persister Receives one planned write and returns a result array.
     * @return array<string,mixed>
     */
    public static function applyApprovedPlan(array $applyPlan, string $expectedFingerprint = '', ?callable $persister = null): array
    {
        $provenance = is_array($applyPlan['provenance'] ?? null) ? (array)$applyPlan['provenance'] : [];
        $actualFingerprint = (string)($provenance['fingerprint'] ?? '');
        if ((string)($applyPlan['status'] ?? '') !== 'READY' || empty($applyPlan['can_apply'])) {
            return self::applyResult('BLOCKED', false, 'apply_plan_not_ready', [], $actualFingerprint);
        }
        if ($expectedFingerprint !== '' && !hash_equals($expectedFingerprint, $actualFingerprint)) {
            return self::applyResult('BLOCKED', false, 'apply_fingerprint_mismatch', [], $actualFingerprint);
        }
        if (!empty($provenance['permissions_granted'])) {
            return self::applyResult('BLOCKED', false, 'permission_write_not_allowed', [], $actualFingerprint);
        }

        $writes = [];
        foreach ((array)($applyPlan['planned_writes'] ?? []) as $write) {
            if (!is_array($write)) {
                continue;
            }
            $target = (string)($write['target'] ?? '');
            if (
                !isset(self::USER_OVERRIDE_TARGET_FIELDS[$target])
                && !isset(self::WORKSPACE_PROFILE_TOKEN_FIELDS[$target])
                && !isset(self::WORKSPACE_PROFILE_QUICK_ACTION_FIELDS[$target])
                && !isset(self::WORKSPACE_PROFILE_NAV_SECTION_FIELDS[$target])
            ) {
                return self::applyResult('BLOCKED', false, 'unsupported_apply_target', [], $actualFingerprint);
            }
            if (!empty($write['permission_write'])) {
                return self::applyResult('BLOCKED', false, 'permission_write_not_allowed', [], $actualFingerprint);
            }
            $writes[] = $write;
        }

        $applied = [];
        $persist = $persister ?? [self::class, 'persistPlannedWrite'];
        foreach ($writes as $write) {
            try {
                $result = $persist($write);
            } catch (\Throwable $e) {
                $result = ['ok' => false, 'reason' => 'persist_exception', 'message' => $e->getMessage()];
            }
            $applied[] = is_array($result) ? $result : ['ok' => false, 'reason' => 'invalid_persist_result'];
            if (empty($applied[array_key_last($applied)]['ok'])) {
                return self::applyResult('FAILED', false, 'persist_failed', $applied, $actualFingerprint);
            }
        }

        return self::applyResult('APPLIED', true, 'apply_complete', $applied, $actualFingerprint);
    }

    /**
     * @param array<int,mixed> $items
     * @return array<string,array<string,mixed>>
     */
    private static function indexResolvedItems(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $surface = strtolower(trim((string)($item['surface'] ?? '')));
            $kind = strtolower(trim((string)($item['kind'] ?? '')));
            $token = self::tokenForResolvedItem($item);
            if ($surface === '' || $kind === '' || $token === '') {
                continue;
            }
            $out[$surface . ':' . $kind . ':' . $token] = $item;
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $item
     */
    private static function tokenForResolvedItem(array $item): string
    {
        $token = strtolower(trim((string)($item['token'] ?? '')));
        if ($token !== '') {
            return $token;
        }
        $key = strtolower(trim((string)($item['key'] ?? '')));
        if ($key === '') {
            return '';
        }
        $pos = strrpos($key, '.');
        return $pos === false ? $key : substr($key, $pos + 1);
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<int,array<string,mixed>>
     */
    private static function proposalItems(array $proposal): array
    {
        $items = [];
        $surface = strtolower(trim((string)($proposal['surface'] ?? '')));
        $kind = strtolower(trim((string)($proposal['kind'] ?? '')));

        foreach ((array)($proposal['items'] ?? []) as $item) {
            if (is_string($item)) {
                $items[] = [
                    'surface' => $surface,
                    'kind' => $kind,
                    'token' => strtolower(trim($item)),
                    'action' => 'include',
                ];
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $itemSurface = strtolower(trim((string)($item['surface'] ?? $surface)));
            $itemKind = strtolower(trim((string)($item['kind'] ?? $kind)));
            $token = strtolower(trim((string)($item['token'] ?? '')));
            if ($token === '') {
                $token = self::tokenFromKey((string)($item['key'] ?? ''));
            }
            $items[] = [
                'surface' => $itemSurface,
                'kind' => $itemKind,
                'token' => $token,
                'action' => strtolower(trim((string)($item['action'] ?? 'include'))),
                'label' => trim((string)($item['label'] ?? '')),
                'url' => trim((string)($item['url'] ?? '')),
                'route' => trim((string)($item['route'] ?? $item['url'] ?? '')),
                'icon' => trim((string)($item['icon'] ?? '')),
                'section' => trim((string)($item['section'] ?? $item['section_title'] ?? '')),
            ];
        }

        return array_values(array_filter(
            $items,
            static fn(array $item): bool => (string)$item['surface'] !== '' && (string)$item['kind'] !== '' && (string)$item['token'] !== ''
        ));
    }

    private static function tokenFromKey(string $key): string
    {
        $key = strtolower(trim($key));
        if ($key === '') {
            return '';
        }
        $pos = strrpos($key, '.');
        return $pos === false ? $key : substr($key, $pos + 1);
    }

    /**
     * @param array<string,mixed> $resolvedExperience
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private static function targetArtifact(array $resolvedExperience, array $proposal, string $artifactType): array
    {
        if (!in_array($artifactType, ['workspace_profile', 'user_override'], true)) {
            return [
                'status' => 'blocked',
                'reason' => 'unsupported_artifact_type',
                'artifact_type' => $artifactType,
            ];
        }

        $resolvedUserId = (int)($resolvedExperience['target_user_id'] ?? 0);
        $proposalUserId = (int)($proposal['target_user_id'] ?? $resolvedUserId);

        if ($artifactType === 'user_override') {
            if ($proposalUserId <= 0) {
                return [
                    'status' => 'blocked',
                    'reason' => 'target_user_required',
                    'artifact_type' => $artifactType,
                ];
            }
            if ($resolvedUserId > 0 && $proposalUserId !== $resolvedUserId) {
                return [
                    'status' => 'blocked',
                    'reason' => 'target_user_mismatch',
                    'artifact_type' => $artifactType,
                    'target_user_id' => $proposalUserId,
                    'resolved_user_id' => $resolvedUserId,
                ];
            }

            return [
                'status' => 'ready',
                'artifact_type' => $artifactType,
                'target_user_id' => $proposalUserId,
                'artifact_key' => 'user:' . $proposalUserId,
            ];
        }

        $profileKey = strtolower(trim((string)($proposal['profile_key'] ?? $proposal['target_profile_key'] ?? '')));
        if ($profileKey === '') {
            $profile = is_array($resolvedExperience['workspace_profile'] ?? null) ? (array)$resolvedExperience['workspace_profile'] : [];
            $profileKey = strtolower(trim((string)($profile['profile_key'] ?? '')));
        }
        if ($profileKey === '') {
            return [
                'status' => 'blocked',
                'reason' => 'workspace_profile_key_required',
                'artifact_type' => $artifactType,
            ];
        }

        return [
            'status' => 'ready',
            'artifact_type' => $artifactType,
            'profile_key' => $profileKey,
            'artifact_key' => 'workspace_profile:' . $profileKey,
        ];
    }

    /**
     * @param array<string,mixed> $request
     * @param array<string,array<string,mixed>> $catalog
     * @return array<string,mixed>
     */
    private static function decisionForRequest(array $request, array $catalog, string $artifactType): array
    {
        $lookup = $request['surface'] . ':' . $request['kind'] . ':' . $request['token'];
        $base = [
            'surface' => $request['surface'],
            'kind' => $request['kind'],
            'token' => $request['token'],
            'action' => $request['action'],
        ];

        if ($request['action'] === 'grant_permission') {
            return $base + [
                'status' => 'blocked',
                'reason' => 'studio_cannot_grant_permission',
            ];
        }

        if (!isset($catalog[$lookup])) {
            return $base + [
                'status' => 'blocked',
                'reason' => 'not_in_owner_capability_catalog',
            ];
        }

        $resolvedItem = $catalog[$lookup];
        if (empty($resolvedItem['allowed_by_acl'])) {
            return $base + [
                'status' => 'blocked',
                'reason' => 'acl_denied',
                'acl_reason' => (string)($resolvedItem['acl_reason'] ?? ''),
            ];
        }

        if ($artifactType === 'user_override' && empty($resolvedItem['included_by_workspace_profile'])) {
            return $base + [
                'status' => 'blocked',
                'reason' => 'blocked_by_profile',
                'profile_reason' => (string)($resolvedItem['profile_reason'] ?? ''),
            ];
        }

        $targetField = self::persistenceTargetFor($artifactType, $request['surface'], $request['kind']);
        if ($targetField === '') {
            return $base + [
                'status' => 'blocked',
                'reason' => 'unsupported_experience_artifact_field',
            ];
        }
        if ($targetField === 'workspace_profiles.quick_actions' && !self::quickActionPayloadReady($request)) {
            return $base + [
                'status' => 'blocked',
                'reason' => 'quick_action_payload_required',
            ];
        }
        if ($targetField === 'workspace_profiles.nav_sections' && !self::navItemPayloadReady($request)) {
            return $base + [
                'status' => 'blocked',
                'reason' => 'nav_item_payload_required',
            ];
        }

        if (empty($resolvedItem['included_by_workspace_profile'])) {
            return $base + [
                'status' => 'warning',
                'reason' => 'profile_currently_hides_capability',
                'profile_reason' => (string)($resolvedItem['profile_reason'] ?? ''),
                'target_field' => $targetField,
                'owner_type' => (string)($resolvedItem['owner_type'] ?? ''),
                'owner_key' => (string)($resolvedItem['owner_key'] ?? ''),
                'quick_action' => self::quickActionPayload($request),
                'nav_item' => self::navItemPayload($request),
            ];
        }

        return $base + [
            'status' => 'allowed',
            'reason' => 'acl_allowed',
            'owner_type' => (string)($resolvedItem['owner_type'] ?? ''),
            'owner_key' => (string)($resolvedItem['owner_key'] ?? ''),
            'target_field' => $targetField,
            'quick_action' => self::quickActionPayload($request),
            'nav_item' => self::navItemPayload($request),
        ];
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function quickActionPayloadReady(array $request): bool
    {
        $action = strtolower(trim((string)($request['action'] ?? 'include')));
        if (in_array($action, ['hide', 'exclude', 'remove'], true)) {
            return true;
        }
        return trim((string)($request['label'] ?? '')) !== '' && trim((string)($request['url'] ?? '')) !== '';
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,string>
     */
    private static function quickActionPayload(array $request): array
    {
        return [
            'key' => (string)($request['token'] ?? ''),
            'label' => trim((string)($request['label'] ?? '')),
            'url' => trim((string)($request['url'] ?? '')),
            'icon' => trim((string)($request['icon'] ?? '')),
        ];
    }

    /**
     * @param array<string,mixed> $request
     */
    private static function navItemPayloadReady(array $request): bool
    {
        $action = strtolower(trim((string)($request['action'] ?? 'include')));
        if (in_array($action, ['hide', 'exclude', 'remove'], true)) {
            return true;
        }
        return trim((string)($request['label'] ?? '')) !== ''
            && trim((string)($request['route'] ?? $request['url'] ?? '')) !== ''
            && trim((string)($request['section'] ?? '')) !== '';
    }

    /**
     * @param array<string,mixed> $request
     * @return array<string,string>
     */
    private static function navItemPayload(array $request): array
    {
        return [
            'key' => (string)($request['token'] ?? ''),
            'label' => trim((string)($request['label'] ?? '')),
            'route' => trim((string)($request['route'] ?? $request['url'] ?? '')),
            'icon' => trim((string)($request['icon'] ?? '')),
            'section' => trim((string)($request['section'] ?? '')),
        ];
    }

    private static function persistenceTargetFor(string $artifactType, string $surface, string $kind): string
    {
        $surface = strtolower(trim($surface));
        $kind = strtolower(trim($kind));

        if ($artifactType === 'workspace_profile') {
            return match ($surface . ':' . $kind) {
                'operator:view' => 'workspace_profiles.nav_sections',
                'operator:quick_action', 'admin:quick_action' => 'workspace_profiles.quick_actions',
                'operator:module', 'admin:module', 'display:module' => 'workspace_profiles.module_visibility',
                'admin:block', 'display:panel' => 'workspace_profiles.dashboard_blocks',
                default => '',
            };
        }

        if ($artifactType === 'user_override') {
            return match ($surface . ':' . $kind) {
                'operator:view' => 'user_surface_overrides.operator_views',
                'display:panel' => 'user_surface_overrides.display_surfaces',
                'admin:block' => 'user_surface_overrides.me_dashboard_blocks',
                'admin:card' => 'user_surface_overrides.me_plugin_cards',
                default => '',
            };
        }

        return '';
    }

    /**
     * @param array<string,mixed> $change
     * @param array<string,mixed> $targetArtifact
     * @return array<string,mixed>
     */
    private static function plannedWrite(string $artifactType, string $surface, int $targetUserId, array $change, array $targetArtifact): array
    {
        $target = (string)($change['target_field'] ?? '');

        return [
            'target' => $target,
            'operation' => (string)($change['action'] ?? 'include'),
            'surface' => (string)($change['surface'] ?? $surface),
            'kind' => (string)($change['kind'] ?? ''),
            'token' => (string)($change['token'] ?? ''),
            'target_user_id' => $artifactType === 'user_override' ? $targetUserId : 0,
            'target_artifact_key' => (string)($targetArtifact['artifact_key'] ?? ''),
            'target_profile_key' => $artifactType === 'workspace_profile' ? (string)($targetArtifact['profile_key'] ?? '') : '',
            'capability_owner_type' => (string)($change['owner_type'] ?? ''),
            'capability_owner_key' => (string)($change['owner_key'] ?? ''),
            'permission_write' => false,
            'runtime_owner_preserved' => trim((string)($change['owner_key'] ?? '')) !== '',
            'quick_action' => is_array($change['quick_action'] ?? null) ? (array)$change['quick_action'] : [],
            'nav_item' => is_array($change['nav_item'] ?? null) ? (array)$change['nav_item'] : [],
        ];
    }

    /**
     * @param array<string,mixed> $write
     * @return array<string,mixed>
     */
    private static function persistPlannedWrite(array $write): array
    {
        $target = (string)($write['target'] ?? '');
        if (isset(self::USER_OVERRIDE_TARGET_FIELDS[$target])) {
            return self::persistUserOverrideWrite($write);
        }
        if (isset(self::WORKSPACE_PROFILE_TOKEN_FIELDS[$target])) {
            return self::persistWorkspaceProfileTokenWrite($write);
        }
        if (isset(self::WORKSPACE_PROFILE_QUICK_ACTION_FIELDS[$target])) {
            return self::persistWorkspaceProfileQuickActionWrite($write);
        }
        if (isset(self::WORKSPACE_PROFILE_NAV_SECTION_FIELDS[$target])) {
            return self::persistWorkspaceProfileNavSectionWrite($write);
        }
        return ['ok' => false, 'reason' => 'unsupported_apply_target'];
    }

    /**
     * @param array<string,mixed> $write
     * @return array<string,mixed>
     */
    private static function persistUserOverrideWrite(array $write): array
    {
        $target = (string)($write['target'] ?? '');
        $field = self::USER_OVERRIDE_TARGET_FIELDS[$target] ?? '';
        $userId = (int)($write['target_user_id'] ?? 0);
        $token = strtolower(trim((string)($write['token'] ?? '')));
        $operation = strtolower(trim((string)($write['operation'] ?? 'include')));

        if ($field === '' || $userId <= 0 || $token === '') {
            return ['ok' => false, 'reason' => 'invalid_user_override_write'];
        }

        $current = self::loadCurrentUserOverrideValue($userId, $field);
        $next = self::mutateCsvTokenSet($current, $token, $operation);
        $actor = trim((string)(\App\Core\Auth::user()['email'] ?? 'studio'));

        \App\Core\DB::query(
            'INSERT INTO user_surface_overrides (user_id, override_field, raw_value)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE raw_value = VALUES(raw_value)',
            [$userId, $field, $next]
        );

        $column = self::assignmentOverrideColumn($field);
        $inlineWritePolicy = \Plugins\Base\Services\UserSurfaceOverrideService::inlineWritePolicy();
        if ($column !== '' && (($inlineWritePolicy[$field] ?? true) === true)) {
            \App\Core\DB::query(
                'UPDATE user_dashboard_assignments SET ' . $column . ' = ?, updated_by = ?, updated_at = NOW() WHERE user_id = ?',
                [$next, $actor, $userId]
            );
        }

        return [
            'ok' => true,
            'target' => $target,
            'override_field' => $field,
            'target_user_id' => $userId,
            'operation' => $operation,
            'token' => $token,
            'raw_value' => $next,
        ];
    }

    /**
     * @param array<string,mixed> $write
     * @return array<string,mixed>
     */
    private static function persistWorkspaceProfileTokenWrite(array $write): array
    {
        $target = (string)($write['target'] ?? '');
        $column = self::WORKSPACE_PROFILE_TOKEN_FIELDS[$target] ?? '';
        $profileKey = strtolower(trim((string)($write['target_profile_key'] ?? '')));
        $token = strtolower(trim((string)($write['token'] ?? '')));
        $operation = strtolower(trim((string)($write['operation'] ?? 'include')));

        if ($column === '' || $profileKey === '' || $token === '') {
            return ['ok' => false, 'reason' => 'invalid_workspace_profile_write'];
        }

        $row = \App\Core\DB::fetchOne(
            'SELECT profile_key, is_system, ' . $column . ' AS raw_value FROM workspace_profiles WHERE profile_key = ? LIMIT 1',
            [$profileKey]
        );
        if (!is_array($row) || $row === []) {
            return ['ok' => false, 'reason' => 'workspace_profile_not_found'];
        }
        if ((int)($row['is_system'] ?? 0) === 1) {
            return ['ok' => false, 'reason' => 'workspace_profile_system_locked'];
        }

        $next = self::mutateJsonTokenList((string)($row['raw_value'] ?? ''), $token, $operation);
        if (($next['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'reason' => (string)($next['reason'] ?? 'unsupported_workspace_profile_json_shape'),
                'target' => $target,
                'profile_key' => $profileKey,
            ];
        }

        $actor = trim((string)(\App\Core\Auth::user()['email'] ?? 'studio'));
        \App\Core\DB::query(
            'UPDATE workspace_profiles SET ' . $column . ' = ?, updated_by = ?, updated_at = NOW() WHERE profile_key = ? AND is_system = 0 LIMIT 1',
            [$next['raw_value'], $actor, $profileKey]
        );

        return [
            'ok' => true,
            'target' => $target,
            'profile_key' => $profileKey,
            'operation' => $operation,
            'token' => $token,
            'raw_value' => $next['raw_value'],
        ];
    }

    /**
     * @param array<string,mixed> $write
     * @return array<string,mixed>
     */
    private static function persistWorkspaceProfileQuickActionWrite(array $write): array
    {
        $target = (string)($write['target'] ?? '');
        $column = self::WORKSPACE_PROFILE_QUICK_ACTION_FIELDS[$target] ?? '';
        $profileKey = strtolower(trim((string)($write['target_profile_key'] ?? '')));
        $token = strtolower(trim((string)($write['token'] ?? '')));
        $operation = strtolower(trim((string)($write['operation'] ?? 'include')));
        $payload = is_array($write['quick_action'] ?? null) ? (array)$write['quick_action'] : [];

        if ($column === '' || $profileKey === '' || $token === '') {
            return ['ok' => false, 'reason' => 'invalid_workspace_profile_quick_action_write'];
        }

        $row = \App\Core\DB::fetchOne(
            'SELECT profile_key, is_system, ' . $column . ' AS raw_value FROM workspace_profiles WHERE profile_key = ? LIMIT 1',
            [$profileKey]
        );
        if (!is_array($row) || $row === []) {
            return ['ok' => false, 'reason' => 'workspace_profile_not_found'];
        }
        if ((int)($row['is_system'] ?? 0) === 1) {
            return ['ok' => false, 'reason' => 'workspace_profile_system_locked'];
        }

        $next = self::mutateJsonQuickActions((string)($row['raw_value'] ?? ''), $token, $operation, $payload);
        if (($next['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'reason' => (string)($next['reason'] ?? 'unsupported_workspace_profile_quick_actions_shape'),
                'target' => $target,
                'profile_key' => $profileKey,
            ];
        }

        $actor = trim((string)(\App\Core\Auth::user()['email'] ?? 'studio'));
        \App\Core\DB::query(
            'UPDATE workspace_profiles SET ' . $column . ' = ?, updated_by = ?, updated_at = NOW() WHERE profile_key = ? AND is_system = 0 LIMIT 1',
            [$next['raw_value'], $actor, $profileKey]
        );

        return [
            'ok' => true,
            'target' => $target,
            'profile_key' => $profileKey,
            'operation' => $operation,
            'token' => $token,
            'raw_value' => $next['raw_value'],
        ];
    }

    /**
     * @param array<string,mixed> $write
     * @return array<string,mixed>
     */
    private static function persistWorkspaceProfileNavSectionWrite(array $write): array
    {
        $target = (string)($write['target'] ?? '');
        $column = self::WORKSPACE_PROFILE_NAV_SECTION_FIELDS[$target] ?? '';
        $profileKey = strtolower(trim((string)($write['target_profile_key'] ?? '')));
        $token = strtolower(trim((string)($write['token'] ?? '')));
        $operation = strtolower(trim((string)($write['operation'] ?? 'include')));
        $payload = is_array($write['nav_item'] ?? null) ? (array)$write['nav_item'] : [];

        if ($column === '' || $profileKey === '' || $token === '') {
            return ['ok' => false, 'reason' => 'invalid_workspace_profile_nav_write'];
        }

        $row = \App\Core\DB::fetchOne(
            'SELECT profile_key, is_system, ' . $column . ' AS raw_value FROM workspace_profiles WHERE profile_key = ? LIMIT 1',
            [$profileKey]
        );
        if (!is_array($row) || $row === []) {
            return ['ok' => false, 'reason' => 'workspace_profile_not_found'];
        }
        if ((int)($row['is_system'] ?? 0) === 1) {
            return ['ok' => false, 'reason' => 'workspace_profile_system_locked'];
        }

        $next = self::mutateJsonNavSections((string)($row['raw_value'] ?? ''), $token, $operation, $payload);
        if (($next['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'reason' => (string)($next['reason'] ?? 'unsupported_workspace_profile_nav_shape'),
                'target' => $target,
                'profile_key' => $profileKey,
            ];
        }

        $actor = trim((string)(\App\Core\Auth::user()['email'] ?? 'studio'));
        \App\Core\DB::query(
            'UPDATE workspace_profiles SET ' . $column . ' = ?, updated_by = ?, updated_at = NOW() WHERE profile_key = ? AND is_system = 0 LIMIT 1',
            [$next['raw_value'], $actor, $profileKey]
        );

        return [
            'ok' => true,
            'target' => $target,
            'profile_key' => $profileKey,
            'operation' => $operation,
            'token' => $token,
            'raw_value' => $next['raw_value'],
        ];
    }

    private static function loadCurrentUserOverrideValue(int $userId, string $field): string
    {
        $row = \App\Core\DB::fetchOne(
            'SELECT raw_value FROM user_surface_overrides WHERE user_id = ? AND override_field = ? LIMIT 1',
            [$userId, $field]
        );
        if (is_array($row) && $row !== [] && $row['raw_value'] !== null) {
            return (string)$row['raw_value'];
        }

        $column = self::assignmentOverrideColumn($field);
        if ($column === '') {
            return '';
        }
        $assignment = \App\Core\DB::fetchOne(
            'SELECT ' . $column . ' AS raw_value FROM user_dashboard_assignments WHERE user_id = ? LIMIT 1',
            [$userId]
        );
        return is_array($assignment) ? (string)($assignment['raw_value'] ?? '') : '';
    }

    private static function assignmentOverrideColumn(string $field): string
    {
        return in_array($field, self::USER_OVERRIDE_TARGET_FIELDS, true) ? $field : '';
    }

    private static function mutateCsvTokenSet(string $current, string $token, string $operation): ?string
    {
        $tokens = [];
        foreach (preg_split('/\s*,\s*/', strtolower(trim($current))) ?: [] as $part) {
            $part = trim($part);
            if ($part !== '' && preg_match('/^[a-z0-9_.:-]+$/', $part)) {
                $tokens[$part] = $part;
            }
        }

        if (in_array($operation, ['hide', 'exclude', 'remove'], true)) {
            unset($tokens[$token]);
        } else {
            $tokens[$token] = $token;
        }

        if ($tokens === []) {
            return null;
        }
        return implode(',', array_values($tokens));
    }

    /**
     * @return array{ok:bool,raw_value?:?string,reason?:string}
     */
    private static function mutateJsonTokenList(string $current, string $token, string $operation): array
    {
        $current = trim($current);
        $decoded = [];
        if ($current !== '') {
            $decoded = json_decode($current, true);
            if (!is_array($decoded)) {
                return ['ok' => false, 'reason' => 'invalid_workspace_profile_json'];
            }
            if (!array_is_list($decoded)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_json_shape'];
            }
        }

        $tokens = [];
        foreach ($decoded as $item) {
            if (!is_scalar($item)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_json_shape'];
            }
            $value = strtolower(trim((string)$item));
            if ($value !== '' && preg_match('/^[a-z0-9_.:-]+$/', $value)) {
                $tokens[$value] = $value;
            }
        }

        if (in_array($operation, ['hide', 'exclude', 'remove'], true)) {
            unset($tokens[$token]);
        } else {
            $tokens[$token] = $token;
        }

        if ($tokens === []) {
            return ['ok' => true, 'raw_value' => null];
        }

        return [
            'ok' => true,
            'raw_value' => json_encode(array_values($tokens), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,raw_value?:?string,reason?:string}
     */
    private static function mutateJsonQuickActions(string $current, string $token, string $operation, array $payload): array
    {
        $current = trim($current);
        $decoded = [];
        if ($current !== '') {
            $decoded = json_decode($current, true);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_quick_actions_shape'];
            }
        }

        $actions = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_quick_actions_shape'];
            }
            $key = strtolower(trim((string)($item['key'] ?? $item['token'] ?? '')));
            if ($key === '') {
                $key = strtolower(trim((string)($item['label'] ?? '')));
            }
            if ($key === '' || !preg_match('/^[a-z0-9_.:-]+$/', $key)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_quick_actions_shape'];
            }
            $actions[$key] = [
                'key' => $key,
                'label' => trim((string)($item['label'] ?? '')),
                'url' => trim((string)($item['url'] ?? '')),
                'icon' => trim((string)($item['icon'] ?? '')),
            ];
        }

        if (in_array($operation, ['hide', 'exclude', 'remove'], true)) {
            unset($actions[$token]);
        } else {
            $label = trim((string)($payload['label'] ?? ''));
            $url = trim((string)($payload['url'] ?? ''));
            if ($label === '' || $url === '') {
                return ['ok' => false, 'reason' => 'quick_action_payload_required'];
            }
            $actions[$token] = [
                'key' => $token,
                'label' => $label,
                'url' => $url,
                'icon' => trim((string)($payload['icon'] ?? '')),
            ];
        }

        if ($actions === []) {
            return ['ok' => true, 'raw_value' => null];
        }

        return [
            'ok' => true,
            'raw_value' => json_encode(array_values($actions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{ok:bool,raw_value?:?string,reason?:string}
     */
    private static function mutateJsonNavSections(string $current, string $token, string $operation, array $payload): array
    {
        $current = trim($current);
        $sections = [];
        if ($current !== '') {
            $decoded = json_decode($current, true);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_nav_shape'];
            }
            $sections = $decoded;
        }

        $sectionName = trim((string)($payload['section'] ?? ''));
        $label = trim((string)($payload['label'] ?? ''));
        $route = trim((string)($payload['route'] ?? ''));
        $icon = trim((string)($payload['icon'] ?? ''));

        foreach ($sections as $idx => $section) {
            if (!is_array($section)) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_nav_shape'];
            }
            if (isset($section['modules'])) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_nav_shape'];
            }
            $items = $section['items'] ?? [];
            if ($items !== [] && (!is_array($items) || !array_is_list($items))) {
                return ['ok' => false, 'reason' => 'unsupported_workspace_profile_nav_shape'];
            }
            foreach ((array)$items as $item) {
                if (!is_array($item)) {
                    return ['ok' => false, 'reason' => 'unsupported_workspace_profile_nav_shape'];
                }
            }
            $sections[$idx]['items'] = array_values((array)$items);
        }

        $sectionIndex = null;
        foreach ($sections as $idx => $section) {
            $candidate = trim((string)($section['key'] ?? $section['id'] ?? $section['title'] ?? $section['section'] ?? ''));
            if ($candidate !== '' && strcasecmp($candidate, $sectionName) === 0) {
                $sectionIndex = $idx;
                break;
            }
        }

        if (in_array($operation, ['hide', 'exclude', 'remove'], true)) {
            foreach ($sections as $idx => $section) {
                $items = [];
                foreach ((array)($section['items'] ?? []) as $item) {
                    $itemKey = strtolower(trim((string)($item['key'] ?? $item['id'] ?? '')));
                    $itemRoute = strtolower(trim((string)($item['route'] ?? $item['url'] ?? '')));
                    if ($itemKey === $token || ($itemRoute !== '' && str_contains($itemRoute, '/' . $token))) {
                        continue;
                    }
                    $items[] = $item;
                }
                $sections[$idx]['items'] = $items;
            }
        } else {
            if ($sectionName === '' || $label === '' || $route === '') {
                return ['ok' => false, 'reason' => 'nav_item_payload_required'];
            }
            if ($sectionIndex === null) {
                $sections[] = [
                    'title' => $sectionName,
                    'items' => [],
                ];
                $sectionIndex = array_key_last($sections);
            }

            $items = [];
            foreach ((array)($sections[$sectionIndex]['items'] ?? []) as $item) {
                $itemKey = strtolower(trim((string)($item['key'] ?? $item['id'] ?? '')));
                $itemRoute = strtolower(trim((string)($item['route'] ?? $item['url'] ?? '')));
                if ($itemKey === $token || ($itemRoute !== '' && str_contains($itemRoute, '/' . $token))) {
                    continue;
                }
                $items[] = $item;
            }
            $items[] = [
                'key' => $token,
                'icon' => $icon,
                'label' => $label,
                'route' => $route,
                'badge' => null,
            ];
            $sections[$sectionIndex]['items'] = $items;
        }

        if ($sections === []) {
            return ['ok' => true, 'raw_value' => null];
        }

        return [
            'ok' => true,
            'raw_value' => json_encode(array_values($sections), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $applied
     * @return array<string,mixed>
     */
    private static function applyResult(string $status, bool $ok, string $reason, array $applied, string $fingerprint): array
    {
        return [
            'version' => self::VERSION,
            'mode' => 'apply',
            'status' => $status,
            'ok' => $ok,
            'reason' => $reason,
            'applied_writes' => $applied,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $plannedWrites
     * @param array<string,mixed> $targetArtifact
     * @return array<string,mixed>
     */
    private static function provenance(string $artifactType, string $surface, int $targetUserId, array $plannedWrites, array $targetArtifact): array
    {
        $owners = [];
        foreach ($plannedWrites as $write) {
            $ownerType = trim((string)($write['capability_owner_type'] ?? ''));
            $ownerKey = trim((string)($write['capability_owner_key'] ?? ''));
            if ($ownerType !== '' && $ownerKey !== '') {
                $owners[$ownerType . ':' . $ownerKey] = [
                    'owner_type' => $ownerType,
                    'owner_key' => $ownerKey,
                ];
            }
        }

        $fingerprintMaterial = [
            'artifact_type' => $artifactType,
            'surface' => $surface,
            'target_user_id' => $targetUserId,
            'target_artifact_key' => (string)($targetArtifact['artifact_key'] ?? ''),
            'writes' => $plannedWrites,
        ];
        $encoded = json_encode($fingerprintMaterial, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            'created_by' => 'studio',
            'studio_role' => 'governed_composition_agent',
            'artifact_type' => $artifactType,
            'surface' => $surface,
            'target_user_id' => $targetUserId,
            'target_artifact_key' => (string)($targetArtifact['artifact_key'] ?? ''),
            'capability_owners' => array_values($owners),
            'owner_handoff_required' => $plannedWrites !== [],
            'permissions_granted' => false,
            'fingerprint' => is_string($encoded) ? substr(sha1($encoded), 0, 20) : '',
        ];
    }
}
