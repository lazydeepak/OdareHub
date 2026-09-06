<?php
declare(strict_types=1);

namespace Apps\Shell\Services;

/**
 * Shell-owned read-only appearance-reader inventory and cutover-readiness
 * analysis.
 *
 * Lists every meaningful consumer or resolver of appearance state across
 * all rendering surfaces, classifies each reader, and provides a
 * cutover-readiness matrix with risk assessment.
 */
final class AppearanceReaderInventoryService
{
    /**
     * Return the full reader inventory.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function inventory(): array
    {
        return [
            [
                'reader_id' => 'auth_server_header',
                'label' => 'Auth server header',
                'surface' => 'auth',
                'file_path' => 'public/views/layouts/auth_header.php',
                'reader_type' => 'server_render',
                'current_inputs' => [
                    'ThemePreferenceService::defaultPreference()',
                    'ThemePreferenceService::themeChoices()',
                    'ThemePreferenceService::modeFromPreference()',
                    'ThemePreferenceService::colorStyleFromPreference()',
                    'static-auth first-boot hardcoded fallback (system-liquid-glass)',
                ],
                'current_output' => 'data-theme, data-theme-mode, data-color-style, data-theme-preference on <html>',
                'legacy_mode_usage' => 'primary',
                'shell_resolver_relation' => 'compatible_for_parallel_compare',
                'comparison_kind' => 'server_mode',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'low',
                'confirmed_status' => 'confirmed',
                'blockers' => ['browser_runtime_override_can_alter_effective_state'],
                'reason' => 'Auth server-rendered attributes can be overridden by browser localStorage on page load.',
            ],
            [
                'reader_id' => 'admin_layout_header',
                'label' => 'Admin layout header',
                'surface' => 'admin',
                'file_path' => 'public/views/layouts/header.php',
                'reader_type' => 'client_runtime',
                'current_inputs' => [
                    'data-theme-preference attribute',
                    'erp-theme-preference localStorage',
                    'browser runtime init script',
                ],
                'current_output' => 'Browser-effective theme, data-theme, data-theme-mode, data-color-style',
                'legacy_mode_usage' => 'primary',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_observable',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'medium',
                'confirmed_status' => 'confirmed',
                'blockers' => [
                    'no_server_attributes_emitted_for_admin',
                    'no_browser_observation_contract',
                    'client_runtime_not_observable_from_php',
                ],
                'reason' => 'Admin appearance is initialized entirely client-side. No server-observable path exists.',
            ],
            [
                'reader_id' => 'operator_server_intent',
                'label' => 'Operator server intent composer',
                'surface' => 'operator',
                'file_path' => 'apps/Shell/Composers/OperatorSurfaceComposer.php',
                'reader_type' => 'server_render',
                'current_inputs' => [
                    'operator_session_override_combined_mode',
                    'operator_preference_combined_mode',
                    'system_default_combined_mode',
                ],
                'current_output' => 'data-theme, data-theme-mode, data-color-style, data-theme-preference on <html>',
                'legacy_mode_usage' => 'primary',
                'shell_resolver_relation' => 'compatible_for_parallel_compare',
                'comparison_kind' => 'server_mode',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'low',
                'confirmed_status' => 'confirmed',
                'blockers' => ['browser_runtime_override_can_alter_effective_state'],
                'reason' => 'Operator server attributes are emitted but browser runtime may still override them.',
            ],
            [
                'reader_id' => 'operator_browser_init',
                'label' => 'Operator browser initializer',
                'surface' => 'operator',
                'file_path' => 'apps/Shell/Composers/OperatorSurfaceComposer.php (inline JS)',
                'reader_type' => 'client_runtime',
                'current_inputs' => [
                    'data-theme-preference attribute',
                    'erp-theme-preference localStorage',
                    'browser runtime init script',
                ],
                'current_output' => 'Browser-effective theme application',
                'legacy_mode_usage' => 'primary',
                'shell_resolver_relation' => 'future_candidate',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_observable',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'medium',
                'confirmed_status' => 'confirmed',
                'blockers' => [
                    'no_browser_observation_contract',
                    'client_runtime_not_observable_from_php',
                ],
                'reason' => 'Operator browser initialization is client-side only. No server observation path.',
            ],
            [
                'reader_id' => 'global_browser_localStorage',
                'label' => 'Global browser localStorage override',
                'surface' => 'global',
                'file_path' => 'header.php, auth_header.php, OperatorSurfaceComposer.php (inline JS)',
                'reader_type' => 'browser_runtime',
                'current_inputs' => ['erp-theme-preference localStorage key (no server visibility)'],
                'current_output' => 'Overrides data-theme-preference on every page load',
                'legacy_mode_usage' => 'override',
                'shell_resolver_relation' => 'blocked',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_observable',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'high',
                'confirmed_status' => 'confirmed',
                'blockers' => [
                    'no_server_side_read_access_to_localStorage',
                    'no_localStorage_policy_defined',
                    'no_audit_or_rollback_contract',
                ],
                'reason' => 'Browser localStorage override is entirely client-side with zero server visibility. Must not become future canonical authority by accident.',
            ],
            [
                'reader_id' => 'special_effects_consistency',
                'label' => 'Special Effects consistency consumer',
                'surface' => 'customization_studio',
                'file_path' => 'apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Views/index.php',
                'reader_type' => 'server_render',
                'current_inputs' => [
                    'AppearanceStateResolver::resolve()',
                    'ThemePreferenceService::defaultPreference()',
                ],
                'current_output' => 'Diagnostic comparison (passive observer, read-only)',
                'legacy_mode_usage' => 'diagnostic',
                'shell_resolver_relation' => 'already_consumer',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'none',
                'confirmed_status' => 'confirmed',
                'blockers' => [],
                'reason' => 'Special Effects already consumes AppearanceStateResolver for diagnostic comparison. No rendering impact.',
            ],
            [
                'reader_id' => 'special_effects_preview_iframe',
                'label' => 'Special Effects isolated preview iframe',
                'surface' => 'customization_studio',
                'file_path' => 'apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php',
                'reader_type' => 'preview_state',
                'current_inputs' => [
                    'query-param preview state (palette, effect profile, effects enabled, motion)',
                    'Assets from /assets/theme.css',
                ],
                'current_output' => 'Isolated preview document with CSP sandbox',
                'legacy_mode_usage' => 'preview',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'none',
                'confirmed_status' => 'confirmed',
                'blockers' => [],
                'reason' => 'Preview iframe is isolated, sandboxed, and has no effect on active appearance.',
            ],
            [
                'reader_id' => 'css_live_editor_preview',
                'label' => 'CSS Live Editor preview',
                'surface' => 'css_live_editor',
                'file_path' => 'apps/Studio/Tools/CustomizationStudio/Advanced/CssLiveEditor/',
                'reader_type' => 'preview_state',
                'current_inputs' => ['Viewer Styles (read-only), platform admin only'],
                'current_output' => 'Read-only element rules viewer',
                'legacy_mode_usage' => 'none',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'none',
                'confirmed_status' => 'confirmed',
                'blockers' => [],
                'reason' => 'CSS Live Editor is a read-only element inspector, not an appearance consumer.',
            ],
            [
                'reader_id' => 'cte_preview',
                'label' => 'CSS Token Editor preview',
                'surface' => 'css_token_editor',
                'file_path' => 'apps/Studio/Tools/CustomizationStudio/DesignSystem/DesignTokenEditor/',
                'reader_type' => 'preview_state',
                'current_inputs' => [
                    'public/assets/theme.css (runtime compilation output)',
                    'resources/themes/ (source theme files)',
                ],
                'current_output' => 'Read-only token value preview',
                'legacy_mode_usage' => 'none',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'none',
                'confirmed_status' => 'confirmed',
                'blockers' => [],
                'reason' => 'CTE is a theme value editor, not an appearance-state consumer.',
            ],
            [
                'reader_id' => 'theme_doctor_dependency',
                'label' => 'Theme Doctor health dependency',
                'surface' => 'theme_tool',
                'file_path' => 'apps/Studio/Tools/CustomizationStudio/Diagnose/ThemeDoctor/',
                'reader_type' => 'diagnostic',
                'current_inputs' => ['compiled_runtime_asset_health, effect_profile_request'],
                'current_output' => 'Readiness diagnostics for theme asset health',
                'legacy_mode_usage' => 'none',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_kind' => 'not_comparable',
                'comparison_status' => 'not_run',
                'comparison_evidence_basis' => '',
                'comparison_id' => '',
                'risk_level' => 'none',
                'confirmed_status' => 'confirmed',
                'blockers' => [],
                'reason' => 'Theme Doctor is a diagnostic tool, not an appearance consumer.',
            ],
        ];
    }

    /**
     * Return inventory with a read-only comparison record folded into the
     * matching Auth reader entry.
     *
     * @param array<string,mixed> $comparison
     * @return array<int, array<string,mixed>>
     */
    public static function inventoryWithComparison(array $comparison, array $ledger = []): array
    {
        $inventory = self::inventory();
        $status = (string)($comparison['status'] ?? 'unknown');
        if (!in_array($status, ['aligned', 'mismatch', 'unknown', 'not_run'], true)) {
            $status = 'unknown';
        }
        $basis = (string)($comparison['evidence_basis'] ?? 'source_contract');
        if (!in_array($basis, ['source_contract', 'fixture', 'observed_server_render'], true)) {
            $basis = 'source_contract';
        }
        $comparisonId = (string)($comparison['comparison_id'] ?? '');
        $ledgerSummary = $ledger !== []
            ? AppearanceReaderComparisonService::summarizeAuthParityEvidenceLedger($ledger)
            : [];

        foreach ($inventory as &$reader) {
            if (($reader['reader_id'] ?? '') !== 'auth_server_header') {
                continue;
            }
            $reader['comparison_status'] = $status;
            $reader['comparison_evidence_basis'] = $basis;
            $reader['comparison_id'] = $comparisonId;
            foreach ($ledgerSummary as $summaryKey => $summaryValue) {
                $reader[$summaryKey] = $summaryValue;
            }
            break;
        }
        unset($reader);

        return $inventory;
    }

    /**
     * Return the cutover-readiness matrix.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function cutoverReadinessMatrix(): array
    {
        return [
            [
                'reader_id' => 'auth_server_header',
                'current_owner' => 'auth_header.php',
                'input_source' => 'ThemePreferenceService::defaultPreference()',
                'reader_type' => 'server_render',
                'shell_resolver_relation' => 'compatible_for_parallel_compare',
                'comparison_feasibility' => 'feasible',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'server_default_output_replaced_by_resolved_intent',
                'readiness' => 'ready_for_inventory_only',
                'primary_blocker' => 'browser runtime override alters effective state after server render',
                'recommended_next_action' => 'collect_server_parity_evidence',
            ],
            [
                'reader_id' => 'admin_layout_header',
                'current_owner' => 'header.php',
                'input_source' => 'Client runtime (localStorage, data-theme-preference)',
                'reader_type' => 'client_runtime',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_feasibility' => 'not_feasible',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'blocked',
                'primary_blocker' => 'no server-observable rendering path; client runtime only',
                'recommended_next_action' => 'define_browser_observation_contract',
            ],
            [
                'reader_id' => 'operator_server_intent',
                'current_owner' => 'OperatorSurfaceComposer.php',
                'input_source' => 'Session override, operator DB preference, system default',
                'reader_type' => 'server_render',
                'shell_resolver_relation' => 'compatible_for_parallel_compare',
                'comparison_feasibility' => 'feasible',
                'browser_behavior_understood' => true,
                'write_path_understood' => true,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'server_attributes_output_replaced_by_resolved_intent',
                'readiness' => 'ready_for_inventory_only',
                'primary_blocker' => 'browser runtime override alters effective state after server render',
                'recommended_next_action' => 'collect_server_parity_evidence',
            ],
            [
                'reader_id' => 'operator_browser_init',
                'current_owner' => 'OperatorSurfaceComposer.php (inline JS)',
                'input_source' => 'Client runtime (localStorage, data-theme-preference)',
                'reader_type' => 'client_runtime',
                'shell_resolver_relation' => 'future_candidate',
                'comparison_feasibility' => 'not_feasible',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'blocked',
                'primary_blocker' => 'client runtime; no server observation path',
                'recommended_next_action' => 'define_client_runtime_adapter',
            ],
            [
                'reader_id' => 'global_browser_localStorage',
                'current_owner' => 'header.php, auth_header.php, OperatorSurfaceComposer.php',
                'input_source' => 'localStorage key erp-theme-preference (no server visibility)',
                'reader_type' => 'browser_runtime',
                'shell_resolver_relation' => 'blocked',
                'comparison_feasibility' => 'not_feasible',
                'browser_behavior_understood' => true,
                'write_path_understood' => true,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'blocked',
                'primary_blocker' => 'no server-side read access to localStorage; no policy defined',
                'recommended_next_action' => 'define_localStorage_policy',
            ],
            [
                'reader_id' => 'special_effects_consistency',
                'current_owner' => 'special-effects.php',
                'input_source' => 'AppearanceStateResolver, ThemePreferenceService',
                'reader_type' => 'server_render',
                'shell_resolver_relation' => 'already_consumer',
                'comparison_feasibility' => 'already_using',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'not_applicable',
                'primary_blocker' => 'diagnostic-only; already consumes resolver',
                'recommended_next_action' => 'retain_current_reader',
            ],
            [
                'reader_id' => 'special_effects_preview_iframe',
                'current_owner' => 'SpecialEffectsRegistryService.php',
                'input_source' => 'Query params, theme assets',
                'reader_type' => 'preview_state',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_feasibility' => 'not_applicable',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'not_applicable',
                'primary_blocker' => 'preview-only; no impact on active appearance',
                'recommended_next_action' => 'retain_current_reader',
            ],
            [
                'reader_id' => 'css_live_editor_preview',
                'current_owner' => 'CssLiveEditor',
                'input_source' => 'CSSOM, manifest stylesheets',
                'reader_type' => 'preview_state',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_feasibility' => 'not_applicable',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'not_applicable',
                'primary_blocker' => 'read-only inspector; no appearance consumption',
                'recommended_next_action' => 'retain_current_reader',
            ],
            [
                'reader_id' => 'cte_preview',
                'current_owner' => 'CssTokenEditor',
                'input_source' => 'Theme source files, compiled CSS',
                'reader_type' => 'preview_state',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_feasibility' => 'not_applicable',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'not_applicable',
                'primary_blocker' => 'token editor; no appearance-state consumption',
                'recommended_next_action' => 'retain_current_reader',
            ],
            [
                'reader_id' => 'theme_doctor_dependency',
                'current_owner' => 'ThemeTool',
                'input_source' => 'Compiled asset health, profile readiness',
                'reader_type' => 'diagnostic',
                'shell_resolver_relation' => 'not_applicable',
                'comparison_feasibility' => 'not_applicable',
                'browser_behavior_understood' => true,
                'write_path_understood' => false,
                'audit_rollback_available' => false,
                'backward_compatibility_path' => 'not_applicable',
                'readiness' => 'not_applicable',
                'primary_blocker' => 'diagnostic tool; no appearance consumption',
                'recommended_next_action' => 'retain_current_reader',
            ],
        ];
    }

    /**
     * Return the legacy localStorage risk assessment.
     *
     * @return array<string, mixed>
     */
    public static function legacyLocalStorageRisk(): array
    {
        return [
            'reader_id' => 'global_browser_localStorage',
            'risk_level' => 'high',
            'reader_type' => 'browser_runtime',
            'server_visibility' => 'none',
            'server_side_read_access' => false,
            'policy_defined' => false,
            'audit_available' => false,
            'rollback_available' => false,
            'blockers' => [
                'no_server_side_read_access_to_localStorage',
                'no_localStorage_policy_defined',
                'no_audit_or_rollback_contract',
            ],
            'conclusion' => 'The legacy browser localStorage override must not become future canonical appearance authority by accident. Before any future reader cutover or persistence work, Shell must define whether this value remains a cache, becomes a policy-governed user preference, is migrated, is reconciled, or is deliberately ignored once canonical state exists.',
        ];
    }
}
