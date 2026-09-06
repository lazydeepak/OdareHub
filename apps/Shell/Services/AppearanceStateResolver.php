<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * Shell-owned canonical appearance-state shadow resolver.
 *
 * Resolves the server-intended appearance state from the available
 * sources (session override, operator DB preference, system default,
 * legacy fallback) and models the relationship with browser runtime
 * and rendering surface context.
 *
 * This is a parallel read model only — it does not change, persist,
 * write, or enforce appearance behavior.
 */
final class AppearanceStateResolver
{
    private const CONTRACT_VERSION = 'v1';

    private const LEGACY_MODES = [
        'system-liquid-glass' => ['palette' => 'system', 'profile' => 'liquid_glass'],
        'system-paper'        => ['palette' => 'system', 'profile' => 'paper'],
        'dark-liquid-glass'   => ['palette' => 'dark',   'profile' => 'liquid_glass'],
        'dark-paper'          => ['palette' => 'dark',   'profile' => 'paper'],
        'light-liquid-glass'  => ['palette' => 'light',  'profile' => 'liquid_glass'],
        'light-paper'         => ['palette' => 'light',  'profile' => 'paper'],
    ];

    private const ABSOLUTE_FALLBACK = 'system-liquid-glass';

    private const ALLOWED_SURFACES = ['auth', 'admin', 'operator'];

    /**
     * Resolve the full canonical appearance-state shadow model.
     *
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public static function resolve(array $input): array
    {
        $surface = self::normalizeSurface((string)($input['surface'] ?? 'admin'));
        $availableSources = self::collectAvailableSources($input);
        $parsedFallback = self::parseLegacyMode(self::ABSOLUTE_FALLBACK);

        $serverIntent = self::resolveServerIntent($surface, $availableSources, $parsedFallback);
        $browserContract = self::browserOverrideContract();
        $browserState = self::browserEffectiveState($input);
        $renderingParity = self::resolveRenderingParity($surface, $input, $serverIntent, $browserState);

        $legacyCombined = $serverIntent['combined_mode'];
        $parsed = self::parseLegacyMode($legacyCombined);

        return [
            'appearance_contract_version' => self::CONTRACT_VERSION,
            'surface' => $surface,
            'surface_context' => [
                'surface' => $surface,
            ],
            'legacy_combined_mode' => $legacyCombined,
            'palette_mode' => $parsed['palette'],
            'effect_profile' => $parsed['profile'],
            'effects_enabled' => $parsed['profile'] !== 'none',
            'motion_mode' => 'system',

            'server_resolved_intent' => $serverIntent,
            'browser_override_contract' => $browserContract,
            'browser_effective_state' => $browserState,
            'rendering_parity' => $renderingParity,

            'source_trace' => $availableSources,
            'compatibility_status' => $serverIntent['status'],

            'future_structured_state_readiness' => self::buildReadinessMatrix(),
            'future_precedence_model' => self::buildFuturePrecedenceModel(),
        ];
    }

    // -------------------------------------------------------------------------
    // Legacy Combined-Mode Parser (Step 3)
    // -------------------------------------------------------------------------

    /**
     * Parse a legacy combined mode into palette and profile components.
     *
     * @return array{palette: string, profile: string}
     */
    public static function parseLegacyMode(string $mode): array
    {
        $normalized = strtolower(trim($mode));
        if (isset(self::LEGACY_MODES[$normalized])) {
            return self::LEGACY_MODES[$normalized];
        }

        $parts = explode('-', $normalized);
        $palette = $parts[0] ?? 'system';
        if (!in_array($palette, ['system', 'light', 'dark'], true)) {
            $palette = 'system';
        }
        $profile = implode('-', array_slice($parts, 1));
        if ($profile === '' || !in_array($profile, ['liquid-glass', 'paper', 'liquid_glass'], true)) {
            $profile = 'liquid-glass';
        }

        return [
            'palette' => $palette,
            'profile' => str_replace('-', '_', $profile),
        ];
    }

    /**
     * Test whether a legacy combined mode is a known valid value.
     */
    public static function isValidLegacyMode(string $mode): bool
    {
        return isset(self::LEGACY_MODES[strtolower(trim($mode))]);
    }

    /**
     * Normalize a legacy mode to its canonical form, falling back safely.
     */
    public static function normalizeLegacyMode(string $mode, ?string $fallback = null): string
    {
        $normalized = strtolower(trim($mode));
        if (isset(self::LEGACY_MODES[$normalized])) {
            return $normalized;
        }
        $parsed = self::parseLegacyMode($mode);
        $candidate = $parsed['palette'] . '-' . str_replace('_', '-', $parsed['profile']);
        if (isset(self::LEGACY_MODES[$candidate])) {
            return $candidate;
        }
        if ($fallback !== null && isset(self::LEGACY_MODES[strtolower(trim($fallback))])) {
            return strtolower(trim($fallback));
        }
        return self::ABSOLUTE_FALLBACK;
    }

    // -------------------------------------------------------------------------
    // Server-Resolved Precedence and Trace (Step 4)
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $input
     * @return array<int,array<string,mixed>>
     */
    private static function collectAvailableSources(array $input): array
    {
        $systemDefault = (string)($input['system_default_combined_mode'] ?? '');
        $operatorPreference = (string)($input['operator_preference_combined_mode'] ?? '');
        $sessionOverride = (string)($input['operator_session_override_combined_mode'] ?? '');
        $legacyRendered = (string)($input['legacy_rendered_combined_mode'] ?? '');
        $userIdAvailable = (bool)($input['current_user_id_available'] ?? false);

        $sources = [];

        if ($sessionOverride !== '') {
            $sources[] = [
                'source_name' => 'session_override',
                'candidate_value' => $sessionOverride,
                'selected' => false,
                'availability' => 'server',
                'status' => 'confirmed',
                'reason' => self::isValidLegacyMode($sessionOverride)
                    ? 'session override candidate available'
                    : 'session override candidate invalid, will fall back',
            ];
        }

        if ($operatorPreference !== '' && $userIdAvailable) {
            $sources[] = [
                'source_name' => 'operator_preference',
                'candidate_value' => $operatorPreference,
                'selected' => false,
                'availability' => 'server',
                'status' => 'confirmed',
                'reason' => 'operator DB preference candidate available',
            ];
        }

        if ($systemDefault !== '') {
            $sources[] = [
                'source_name' => 'system_default',
                'candidate_value' => $systemDefault,
                'selected' => false,
                'availability' => 'server',
                'status' => 'confirmed',
                'reason' => 'system default from core_settings or ThemePreferenceService',
            ];
        }

        if ($legacyRendered !== '') {
            $sources[] = [
                'source_name' => 'legacy_rendered',
                'candidate_value' => $legacyRendered,
                'selected' => false,
                'availability' => 'server',
                'status' => 'inferred',
                'reason' => 'legacy rendered or observed combined mode',
            ];
        }

        $sources[] = [
            'source_name' => 'absolute_fallback',
            'candidate_value' => self::ABSOLUTE_FALLBACK,
            'selected' => false,
            'availability' => 'server',
            'status' => 'inferred',
            'reason' => 'absolute fallback when no other candidate is valid',
        ];

        return $sources;
    }

    /**
     * @param array<int,array<string,mixed>> $availableSources
     * @param array{palette: string, profile: string} $parsedFallback
     * @return array<string,mixed>
     */
    private static function resolveServerIntent(
        string $surface,
        array &$availableSources,
        array $parsedFallback
    ): array {
        $candidates = self::serverCandidatesForSurface($surface, $availableSources);

        $selected = null;
        $selectedSource = null;

        foreach ($candidates as $idx) {
            $source = &$availableSources[$idx];
            $value = (string)($source['candidate_value'] ?? '');
            if ($value !== '' && self::isValidLegacyMode($value)) {
                $source['selected'] = true;
                $selected = $value;
                $selectedSource = $source['source_name'];
                break;
            }
            if ($value !== '') {
                $source['selected'] = true;
                $source['status'] = 'inferred';
                $source['reason'] = 'candidate present but invalid; normalization applied';
                $selected = self::normalizeLegacyMode($value);
                $selectedSource = $source['source_name'] . '_normalized';
                break;
            }
        }

        if ($selected === null) {
            $fallbackIdx = count($availableSources) - 1;
            $availableSources[$fallbackIdx]['selected'] = true;
            $selected = self::ABSOLUTE_FALLBACK;
            $selectedSource = 'absolute_fallback';
        }

        $parsed = self::parseLegacyMode($selected);
        $status = self::isValidLegacyMode($selected) ? 'confirmed' : 'inferred';

        return [
            'combined_mode' => $selected,
            'palette_mode' => $parsed['palette'],
            'effect_profile' => $parsed['profile'],
            'effects_enabled' => $parsed['profile'] !== 'none',
            'motion_mode' => 'system',
            'resolution_source' => $selectedSource,
            'status' => $status,
        ];
    }

    /**
     * Determine which source indices apply for a given surface.
     *
     * @param array<int,array<string,mixed>> $sources
     * @return array<int,int>
     */
    private static function serverCandidatesForSurface(string $surface, array $sources): array
    {
        $nameOrder = [
            'operator' => ['session_override', 'operator_preference', 'system_default', 'legacy_rendered', 'absolute_fallback'],
            'admin'    => ['system_default', 'legacy_rendered', 'absolute_fallback'],
            'auth'     => ['system_default', 'legacy_rendered', 'absolute_fallback'],
        ];

        $order = $nameOrder[$surface] ?? $nameOrder['admin'];
        $indices = [];
        foreach ($order as $name) {
            foreach ($sources as $idx => $src) {
                if (($src['source_name'] ?? '') === $name) {
                    $indices[] = $idx;
                }
            }
        }
        return $indices;
    }

    // -------------------------------------------------------------------------
    // Browser Override Contract
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    private static function browserOverrideContract(): array
    {
        return [
            'storage_key' => 'erp-theme-preference',
            'browser_override_supported' => true,
            'browser_override_available_server_side' => false,
            'browser_override_precedence' => 'client_runtime_only',
            'validation' => 'legacy_combined_mode_allowlist',
            'status' => 'confirmed',
        ];
    }

    // -------------------------------------------------------------------------
    // Browser-Effective State (Step 2/6)
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    private static function browserEffectiveState(array $input): array
    {
        $observed = (bool)($input['browser_observation_available'] ?? false);
        $observedMode = (string)($input['browser_observed_combined_mode'] ?? '');

        if ($observed && $observedMode !== '') {
            $parsed = self::parseLegacyMode($observedMode);
            return [
                'availability' => 'observed',
                'combined_mode' => $observedMode,
                'palette_mode' => $parsed['palette'],
                'effect_profile' => $parsed['profile'],
                'effects_enabled' => $parsed['profile'] !== 'none',
                'motion_mode' => 'system',
                'status' => 'confirmed',
            ];
        }

        return [
            'availability' => 'not_observed_server_side',
            'combined_mode' => null,
            'palette_mode' => null,
            'effect_profile' => null,
            'effects_enabled' => null,
            'motion_mode' => null,
            'status' => 'not_applicable',
        ];
    }

    // -------------------------------------------------------------------------
    // Surface-Specific Shadow Rendering Parity (Step 5)
    // -------------------------------------------------------------------------

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $serverIntent
     * @param array<string,mixed> $browserState
     * @return array<string,mixed>
     */
    private static function resolveRenderingParity(
        string $surface,
        array $input,
        array $serverIntent,
        array $browserState
    ): array {
        $legacyRendered = (string)($input['legacy_rendered_combined_mode'] ?? '');

        if ($surface === 'auth') {
            if ($legacyRendered === '') {
                return [
                    'kind' => 'server_attribute',
                    'status' => 'unknown',
                    'legacy_rendered_mode' => null,
                    'shadow_resolved_mode' => $serverIntent['combined_mode'],
                    'reason' => 'Auth surface: no legacy rendered mode available for comparison',
                ];
            }
            $aligned = self::normalizeLegacyMode($legacyRendered) === $serverIntent['combined_mode'];
            return [
                'kind' => 'server_attribute',
                'status' => $aligned ? 'aligned' : 'mismatch',
                'legacy_rendered_mode' => $legacyRendered,
                'shadow_resolved_mode' => $serverIntent['combined_mode'],
                'reason' => $aligned
                    ? 'Auth server-rendered attribute matches resolved intent'
                    : 'Auth server-rendered attribute differs from resolved intent (diagnostic only)',
            ];
        }

        if ($surface === 'admin') {
            return [
                'kind' => 'client_runtime',
                'status' => 'not_observable',
                'legacy_rendered_mode' => null,
                'shadow_resolved_mode' => $serverIntent['combined_mode'],
                'reason' => 'Admin active appearance is initialized client-side; server attributes are not emitted',
            ];
        }

        if ($surface === 'operator') {
            return [
                'kind' => 'client_runtime',
                'status' => 'not_observable',
                'legacy_rendered_mode' => null,
                'shadow_resolved_mode' => $serverIntent['combined_mode'],
                'reason' => 'Operator active appearance is initialized by browser runtime',
            ];
        }

        return [
            'kind' => 'unknown',
            'status' => 'unknown',
            'legacy_rendered_mode' => null,
            'shadow_resolved_mode' => $serverIntent['combined_mode'],
            'reason' => 'Unknown surface: no rendering parity model',
        ];
    }

    // -------------------------------------------------------------------------
    // Future Structured-State Readiness Matrix (Step 8)
    // -------------------------------------------------------------------------

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function buildReadinessMatrix(): array
    {
        return [
            'palette_mode' => [
                'current_representation' => 'legacy_combined_mode',
                'current_source_owner' => 'Shell ThemePreferenceService',
                'legacy_compatibility_available' => true,
                'future_persistence_owner' => 'Shell (recommended)',
                'existing_write_path_reusable' => true,
                'audit_available' => false,
                'rollback_available' => false,
                'runtime_application_path_known' => true,
                'theme_doctor_dependency_known' => false,
                'migration_required' => false,
                'readiness' => 'partial',
                'blockers' => ['audit_contract', 'rollback_contract'],
            ],
            'effect_profile' => [
                'current_representation' => 'legacy_combined_mode',
                'current_source_owner' => 'Shell ThemePreferenceService',
                'legacy_compatibility_available' => true,
                'future_persistence_owner' => 'Shell (recommended)',
                'existing_write_path_reusable' => true,
                'audit_available' => false,
                'rollback_available' => false,
                'runtime_application_path_known' => true,
                'theme_doctor_dependency_known' => false,
                'migration_required' => false,
                'readiness' => 'partial',
                'blockers' => ['audit_contract', 'rollback_contract'],
            ],
            'effects_enabled' => [
                'current_representation' => 'not_independently_represented',
                'current_source_owner' => 'none',
                'legacy_compatibility_available' => false,
                'future_persistence_owner' => 'Shell (recommended)',
                'existing_write_path_reusable' => false,
                'audit_available' => false,
                'rollback_available' => false,
                'runtime_application_path_known' => false,
                'theme_doctor_dependency_known' => false,
                'migration_required' => true,
                'readiness' => 'blocked',
                'blockers' => [
                    'no_existing_source_of_truth_for_effects_enabled',
                    'audit_contract',
                    'rollback_contract',
                    'runtime_application_path',
                ],
            ],
            'motion_mode' => [
                'current_representation' => 'not_independently_represented',
                'current_source_owner' => 'none',
                'legacy_compatibility_available' => false,
                'future_persistence_owner' => 'Shell (recommended)',
                'existing_write_path_reusable' => false,
                'audit_available' => false,
                'rollback_available' => false,
                'runtime_application_path_known' => false,
                'theme_doctor_dependency_known' => false,
                'migration_required' => true,
                'readiness' => 'blocked',
                'blockers' => [
                    'no_existing_source_of_truth_for_motion_mode',
                    'audit_contract',
                    'rollback_contract',
                    'runtime_application_path',
                ],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Future Precedence and Split-Brain Prevention (Step 9)
    // -------------------------------------------------------------------------

    /**
     * @return array<string,mixed>
     */
    public static function buildFuturePrecedenceModel(): array
    {
        return [
            'precedence_order' => [
                'system_safety_and_runtime_constraints',
                'future_organization_policy',
                'future_user_preference_where_policy_permits',
                'legacy_browser_localStorage_override_where_policy_permits',
                'preview_only_state_non_persistent',
                'runtime_safety_fallback',
            ],
            'principles' => [
                'one_canonical_persisted_appearance_source_of_truth',
                'preview_never_persists',
                'preview_never_overrides_policy',
                'user_preference_never_bypasses_organization_policy',
                'organization_policy_never_bypasses_runtime_safety_fallback',
                'legacy_localStorage_must_not_become_future_canonical_authority',
            ],
            'potential_conflict_sources' => [
                ['source' => 'Current global combined mode (legacy)', 'status' => 'confirmed_contract'],
                ['source' => 'Operator session override', 'status' => 'confirmed_contract'],
                ['source' => 'Operator DB preference', 'status' => 'confirmed_contract'],
                ['source' => 'Future organization policy', 'status' => 'unknown_requires_implementation'],
                ['source' => 'Future user preference', 'status' => 'partial_contract'],
                ['source' => 'Legacy browser localStorage override', 'status' => 'confirmed_contract'],
                ['source' => 'Preview-only state', 'status' => 'confirmed_contract'],
                ['source' => 'Runtime safety fallback', 'status' => 'inferred_contract'],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Browser Observer Normalization Contract (canonical parser parity)
    // -------------------------------------------------------------------------

    /**
     * Return a server-produced map that the browser observer uses for
     * normalization, ensuring PHP and JS stay in lockstep.
     *
     * The 'shorthand_map' covers values that existing client code already
     * normalizes (dark→dark-liquid-glass, etc.).  The 'known_modes' list
     * is the strict allowlist of valid legacy combined modes.
     *
     * @return array{shorthand_map: array<string,string>, known_modes: list<string>}
     */
    public static function browserNormalizationMap(): array
    {
        $knownModes = array_keys(self::LEGACY_MODES);
        $preferred = 'liquid-glass';
        $shorthandMap = [
            'dark'   => 'dark-' . $preferred,
            'light'  => 'light-' . $preferred,
            'system' => 'system-' . $preferred,
        ];
        return [
            'known_modes' => $knownModes,
            'shorthand_map' => $shorthandMap,
        ];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function normalizeSurface(string $surface): string
    {
        $surface = strtolower(trim($surface));
        return in_array($surface, self::ALLOWED_SURFACES, true) ? $surface : 'admin';
    }
}
