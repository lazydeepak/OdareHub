<?php
declare(strict_types=1);

namespace Apps\Studio\Tools\CustomizationStudio\Effects\SpecialEffects\Application;

use Apps\Shell\Services\StyleRegistryService;
use Apps\Studio\Tools\CustomizationStudio\Diagnose\StyleCompliance\Services\StyleComplianceScannerService;

require_once APP_ROOT . '/apps/Shell/Services/StyleRegistryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';

final class SpecialEffectsRegistryService
{
    private const COMPILED_THEME_ASSET = APP_ROOT . '/public/assets/theme.css';
    private const SCANNER_CONTRACT_VERSION = 'style_compliance_handoff_contract_v1_2';
    private const SPECIAL_EFFECTS_ADAPTER_VERSION = 'special_effects_handoff_adapter_v1_2';
    private const OPERATIONAL_FILTER_VERSION = 'style_compliance_operational_filter_v1';

    /** @var array<int,string> */
    private const PALETTE_MODES = ['system', 'light', 'dark'];

    /** @var array<int,string> */
    private const MOTION_MODES = ['system', 'reduced', 'off'];

    /**
     * @return array<string,mixed>
     */
    public static function buildWorkspaceModel(
        ?string $combinedPreference = null,
        bool $effectsEnabled = true,
        string $motionMode = 'system',
        string $scanScope = StyleComplianceScannerService::SCOPE_OWNER,
        string $scanOwner = 'Studio',
        array $previewQuery = []
    ): array
    {
        $runtimeContract = self::resolveCompatibility($combinedPreference, $effectsEnabled, $motionMode);
        $compiledCss = self::readCompiledThemeCss();
        $profiles = self::effectProfiles();
        $readiness = self::readinessForProfiles($profiles, $runtimeContract, $compiledCss);
        $previewState = self::previewStateFromQuery($previewQuery, $runtimeContract, $profiles, $compiledCss);
        $futureControlsModel = self::buildFutureControlsModel($runtimeContract, $previewState, $profiles, $compiledCss);
        $appearanceAudit = self::buildAppearanceIntegrationAudit($runtimeContract, $previewState, $readiness);
        $scanContext = self::normalizeScanContext($scanScope, $scanOwner, 'special-effects');
        $handoffs = self::collectStyleComplianceHandoffs($scanContext);
        $runtimeReadiness = self::runtimeReadiness($runtimeContract, $readiness, $handoffs);
        $blockedOrDegraded = self::countProfilesByReadiness($readiness, ['blocked', 'degraded']);

        return [
            'tool_id' => 'customization-studio.special-effects',
            'product_name' => 'Special Effects',
            'runtime_contract' => $runtimeContract,
            'profiles' => $profiles,
            'readiness' => $readiness,
            'preview_state' => $previewState,
            'future_effect_controls' => $futureControlsModel,
            'appearance_integration_audit' => $appearanceAudit,
            'scan_context' => $handoffs['scan_context'] ?? $scanContext,
            'handoffs' => $handoffs,
            'handoff_reconciliation' => $handoffs['reconciliation'] ?? [],
            'handoff_parity' => $handoffs['parity'] ?? [],
            'mapping_rules' => self::mappingRuleRegistry(),
            'runtime_readiness' => $runtimeReadiness,
            'future_controls' => self::futureControls(),
            'runtime_status' => [
                'status' => $blockedOrDegraded['blocked'] > 0 ? 'degraded' : 'available',
                'palette_mode' => $runtimeContract['palette_mode'],
                'configured_effect_profile' => $runtimeContract['configured_effect_profile'],
                'resolved_effect_profile' => $runtimeContract['effect_profile'],
                'effects_enabled' => $runtimeContract['effects_enabled'],
                'effects_disabled_fallback' => $runtimeContract['effects_disabled_fallback'],
                'motion_mode' => $runtimeContract['motion_mode'],
                'handoff_count' => (int)($handoffs['summary']['total'] ?? 0),
                'handoffs_available' => (int)($handoffs['reconciliation']['available'] ?? 0),
                'handoffs_displayed' => (int)($handoffs['reconciliation']['displayed'] ?? 0),
                'handoffs_unmapped' => (int)($handoffs['reconciliation']['unmapped'] ?? 0),
                'blocked_dependencies' => $blockedOrDegraded['blocked'],
                'degraded_dependencies' => $blockedOrDegraded['degraded'],
            ],
            'layers' => [
                [
                    'id' => 'shell_foundation',
                    'label' => 'Shell/Foundation',
                    'contract' => 'Baseline layout, accessibility, rendering, keyboard navigation, forms, and fallbacks.',
                ],
                [
                    'id' => 'theme_palette',
                    'label' => 'Theme palette',
                    'contract' => 'Semantic colors, typography, borders, and elevation.',
                ],
                [
                    'id' => 'special_effects',
                    'label' => 'Special Effects',
                    'contract' => 'Optional glass, blur, translucency, glow, decorative shadow, motion, filters, and masks.',
                ],
            ],
            'safety_contract' => [
                'effects_never_required_for' => [
                    'readability',
                    'focus',
                    'keyboard_navigation',
                    'navigation',
                    'layout',
                    'forms',
                    'fallbacks',
                ],
                'preview_enabled' => true,
                'preview_status' => 'isolated_get_only_non_persistent_profile_preview',
                'mutation_endpoint' => '',
                'write_enabled' => false,
                'persistent_settings_enabled' => false,
                'snapshot_enabled' => false,
                'rollback_enabled' => false,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function resolveCompatibility(?string $combinedPreference, bool $effectsEnabled = true, string $motionMode = 'system'): array
    {
        $normalized = self::normalizeCombinedPreference($combinedPreference);
        [$paletteMode, $styleKey] = self::splitCombinedPreference($normalized);
        $configuredProfile = self::effectProfileFromStyleKey($styleKey);
        $resolvedProfile = $effectsEnabled ? $configuredProfile : ($configuredProfile === 'none' ? 'none' : 'paper');
        $resolvedStyleKey = self::styleKeyFromEffectProfile($resolvedProfile);
        $effectiveVisualMode = $paletteMode . '-' . $resolvedStyleKey;
        $motionMode = in_array($motionMode, self::MOTION_MODES, true) ? $motionMode : 'system';

        return [
            'palette_mode' => $paletteMode,
            'effect_profile' => $resolvedProfile,
            'configured_effect_profile' => $configuredProfile,
            'effects_enabled' => $effectsEnabled,
            'motion_mode' => $motionMode,
            'combined_preference' => $normalized,
            'effective_visual_mode' => $effectiveVisualMode,
            'backward_compatible_choices' => [
                'system-liquid-glass',
                'system-paper',
                'dark-liquid-glass',
                'dark-paper',
                'light-liquid-glass',
                'light-paper',
            ],
            'effects_disabled_fallback' => $effectsEnabled ? 'not_active' : ($configuredProfile === 'none' ? 'none' : 'paper'),
            'resolver_persistence' => 'read_only_derived_values_only',
            'preference_write_enabled' => false,
        ];
    }

    /**
     * @param array<string,mixed> $query
     * @param array<string,mixed>|null $activeContract
     * @param array<string,array<string,mixed>>|null $profiles
     * @return array<string,mixed>
     */
    public static function previewStateFromQuery(array $query, ?array $activeContract = null, ?array $profiles = null, ?string $compiledCss = null): array
    {
        $activeContract = $activeContract ?? self::resolveCompatibility(null, true, 'system');
        $profiles = $profiles ?? self::effectProfiles();
        $compiledCss = $compiledCss ?? self::readCompiledThemeCss();

        $activePalette = (string)($activeContract['palette_mode'] ?? 'system');
        $activeProfile = (string)($activeContract['effect_profile'] ?? 'liquid_glass');
        $activeEffects = !empty($activeContract['effects_enabled']);
        $activeMotion = (string)($activeContract['motion_mode'] ?? 'system');

        $palette = self::allowlistedValue((string)($query['preview_palette_mode'] ?? ''), self::PALETTE_MODES, $activePalette);
        $profile = self::allowlistedValue((string)($query['preview_effect_profile'] ?? ''), ['paper', 'liquid_glass', 'none'], $activeProfile);
        $effectsEnabled = array_key_exists('preview_effects_enabled', $query)
            ? ((string)$query['preview_effects_enabled'] === '1')
            : $activeEffects;
        $motion = self::allowlistedValue((string)($query['preview_motion_mode'] ?? ''), self::MOTION_MODES, $activeMotion);
        $source = self::allowlistedValue((string)($query['preview_source'] ?? ''), ['active_mode', 'manual_preview'], 'active_mode');

        $previewContract = [
            'palette_mode' => $palette,
            'effect_profile' => $profile,
            'configured_effect_profile' => $profile,
            'effects_enabled' => $effectsEnabled,
            'motion_mode' => $motion,
            'combined_preference' => $palette . '-' . self::styleKeyFromEffectProfile($profile),
            'effective_visual_mode' => $palette . '-' . self::styleKeyFromEffectProfile($profile),
            'effects_disabled_fallback' => $effectsEnabled ? 'not_active' : ($profile === 'none' ? 'none' : 'paper'),
            'resolver_persistence' => 'preview_url_only_no_persistence',
            'preference_write_enabled' => false,
        ];

        $previewReadiness = self::readinessForProfiles($profiles, $previewContract, $compiledCss);
        $readiness = isset($previewReadiness[$profile]) && is_array($previewReadiness[$profile]) ? $previewReadiness[$profile] : [];
        $outcome = (string)($readiness['outcome'] ?? 'not_configured');
        $renderedProfile = self::previewRenderedProfile($profile, $outcome, $effectsEnabled, (string)($readiness['fallback_profile'] ?? 'paper'));
        $renderedPalette = $palette === 'system' ? 'light' : $palette;
        $renderedStyle = self::styleKeyFromEffectProfile($renderedProfile);
        $previewUrl = '/apps/studio/tools/customization-studio/effects/preview?' . http_build_query([
            'preview_palette_mode' => $palette,
            'preview_effect_profile' => $profile,
            'preview_effects_enabled' => $effectsEnabled ? '1' : '0',
            'preview_motion_mode' => $motion,
            'preview_source' => $source,
        ]);

        return [
            'preview_source' => $source,
            'preview_palette_mode' => $palette,
            'preview_effect_profile' => $profile,
            'preview_effects_enabled' => $effectsEnabled,
            'preview_motion_mode' => $motion,
            'active_combined_mode' => (string)($activeContract['effective_visual_mode'] ?? ''),
            'active_palette_mode' => $activePalette,
            'active_effect_profile' => $activeProfile,
            'active_effects_enabled' => $activeEffects,
            'active_motion_mode' => $activeMotion,
            'preview_contract' => $previewContract,
            'profile_readiness' => $outcome,
            'rendered_profile' => $renderedProfile,
            'rendered_palette' => $renderedPalette,
            'rendered_color_style' => $renderedStyle,
            'fallback_status' => $renderedProfile === $profile ? 'ready' : ($outcome === 'blocked' ? 'blocked' : 'degraded'),
            'runtime_theme_asset_health' => $compiledCss !== '' ? 'present' : 'missing',
            'required_selector_status' => empty($readiness['missing_selectors']) ? 'ready' : 'degraded',
            'required_token_status' => empty($readiness['missing_tokens']) ? 'ready' : 'degraded',
            'effects_disabled_fallback' => (string)$previewContract['effects_disabled_fallback'],
            'reduced_motion_fallback' => $motion === 'system' ? 'system' : $motion,
            'capability_evidence_status' => in_array('browser_backdrop_filter_capability_assumed_with_semantic_surface_fallback', (array)($readiness['dependencies'] ?? []), true)
                ? 'Capability check: modeled requirement - browser verification deferred.'
                : 'No special browser capability modeled.',
            'fallback_profile' => (string)($readiness['fallback_profile'] ?? 'none'),
            'focus_visibility' => 'preserved',
            'motion_behavior' => $motion,
            'effects_fallback' => $renderedProfile === $profile ? 'ready' : ($outcome === 'blocked' ? 'blocked' : 'degraded'),
            'preview_url' => $previewUrl,
            'readiness' => $readiness,
            'selector_evidence' => [
                'required' => (array)($profiles[$profile]['required_selectors'] ?? []),
                'missing' => (array)($readiness['missing_selectors'] ?? []),
            ],
            'token_evidence' => [
                'required' => (array)($profiles[$profile]['required_tokens'] ?? []),
                'missing' => (array)($readiness['missing_tokens'] ?? []),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $query
     */
    public static function renderPreviewDocument(array $query): string
    {
        $state = self::previewStateFromQuery($query);
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $palette = (string)$state['rendered_palette'];
        $style = (string)$state['rendered_color_style'];
        $preference = (string)$state['preview_palette_mode'] . '-' . self::styleKeyFromEffectProfile((string)$state['preview_effect_profile']);
        $motion = (string)$state['preview_motion_mode'];
        $motionAttr = $motion === 'off' ? 'off' : ($motion === 'reduced' ? 'reduced' : 'system');

        return '<!doctype html><html lang="en" data-theme="' . $escape($palette)
            . '" data-theme-mode="' . $escape((string)$state['preview_palette_mode'])
            . '" data-color-style="' . $escape($style)
            . '" data-theme-preference="' . $escape($preference)
            . '" data-se-preview="isolated" data-se-motion="' . $escape($motionAttr) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>Special Effects Preview</title>'
            . self::previewStylesheetLinks('admin')
            . '<link rel="stylesheet" href="/assets/apps/studio/styles/gui_studio.css">'
            . '<style>' . self::previewDocumentCss() . '</style></head><body>'
            . self::previewCanvasHtml($state, $escape)
            . '</body></html>';
    }

    private static function previewStylesheetLinks(string $surface): string
    {
        $links = '';
        foreach (StyleRegistryService::previewChain($surface) as $style) {
            $url = htmlspecialchars((string)($style['url'] ?? ''), ENT_QUOTES, 'UTF-8');
            $version = rawurlencode((string)($style['version'] ?? '1'));
            if ($url !== '') {
                $links .= '<link rel="stylesheet" href="' . $url . '?v=' . $version . '">';
            }
        }
        return $links;
    }

    /**
     * @param array<string,mixed> $activeContract
     * @param array<string,mixed> $previewState
     * @param array<string,array<string,mixed>> $profiles
     * @return array<string,mixed>
     */
    public static function buildFutureControlsModel(array $activeContract, array $previewState, array $profiles, ?string $compiledCss = null): array
    {
        $contract = self::futureSettingsContract();
        $contract['system_defaults'] = [
            'palette_mode' => (string)($activeContract['palette_mode'] ?? 'system'),
            'effect_profile' => (string)($activeContract['effect_profile'] ?? 'liquid_glass'),
            'effects_enabled' => !empty($activeContract['effects_enabled']),
            'motion_mode' => (string)($activeContract['motion_mode'] ?? 'system'),
        ];
        $contract['runtime_constraints'] = self::runtimeConstraintsForProfile(
            (string)$contract['system_defaults']['effect_profile'],
            (string)$contract['system_defaults']['motion_mode'],
            $profiles,
            $compiledCss
        );

        $resolved = self::resolveFutureEffectState($contract);
        $authority = self::futureScopeAuthorityContract();
        $plans = self::futureControlPlans($contract, $resolved, $previewState, $authority);

        return [
            'resolution_hierarchy' => [
                'system_defaults',
                'organization_policy',
                'permitted_user_preference',
                'accessibility_constraints',
                'runtime_readiness_and_capability',
                'safe_fallback',
                'resolved_runtime_state',
            ],
            'settings_contract' => $contract,
            'resolved_effect_state' => $resolved,
            'authority_contract' => $authority,
            'control_plans' => $plans,
            'audit_and_rollback_design' => self::futureAuditRollbackEvidence(),
            'preview_simulation_reference' => [
                'preview_requested_state' => [
                    'palette_mode' => (string)($previewState['preview_palette_mode'] ?? ''),
                    'effect_profile' => (string)($previewState['preview_effect_profile'] ?? ''),
                    'effects_enabled' => !empty($previewState['preview_effects_enabled']),
                    'motion_mode' => (string)($previewState['preview_motion_mode'] ?? ''),
                ],
                'preview_projected_resolved_state' => [
                    'palette_mode' => (string)($previewState['rendered_palette'] ?? ''),
                    'effect_profile' => (string)($previewState['rendered_profile'] ?? ''),
                    'effects_enabled' => !empty($previewState['preview_effects_enabled']),
                    'motion_mode' => (string)($previewState['motion_behavior'] ?? ''),
                ],
                'preview_fallback_applied' => (string)($previewState['rendered_profile'] ?? '') !== (string)($previewState['preview_effect_profile'] ?? ''),
                'preview_resolution_reason' => (string)($previewState['fallback_status'] ?? 'ready'),
                'preview_contract_status' => 'non_persistent_url_state_only_not_a_draft_not_a_plan',
            ],
            'phase_status' => 'Design only - persistence not enabled',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function futureSettingsContract(): array
    {
        return [
            'system_defaults' => [
                'palette_mode' => 'system',
                'effect_profile' => 'liquid_glass',
                'effects_enabled' => true,
                'motion_mode' => 'system',
            ],
            'organization_effect_policy' => [
                'default_palette_mode' => 'system',
                'default_effect_profile' => 'paper',
                'effects_allowed' => true,
                'motion_policy' => 'system',
                'allow_user_override' => true,
                'forced_profile' => null,
                'forced_motion_mode' => null,
            ],
            'user_effect_preference' => [
                'palette_mode' => 'inherit',
                'effect_profile' => 'inherit',
                'effects_enabled' => 'inherit',
                'motion_mode' => 'inherit',
            ],
            'runtime_constraints' => [
                'theme_runtime_healthy' => true,
                'profile_readiness' => 'available',
                'reduced_motion_required' => false,
                'capability_state' => 'unknown',
                'paper_viable' => true,
            ],
            'resolved_effect_state' => [
                'palette_mode' => 'system',
                'effect_profile' => 'liquid_glass',
                'effects_enabled' => true,
                'motion_mode' => 'system',
                'resolution_source' => 'system',
                'fallback_applied' => false,
                'fallback_reason' => null,
                'locked_by_policy' => false,
                'resolution_trace' => [],
            ],
            'persistence_enabled' => false,
            'contract_phase' => 'read_only_design_only',
        ];
    }

    /**
     * @param array<string,mixed> $contract
     * @return array<string,mixed>
     */
    public static function resolveFutureEffectState(array $contract): array
    {
        $system = isset($contract['system_defaults']) && is_array($contract['system_defaults']) ? $contract['system_defaults'] : [];
        $org = isset($contract['organization_effect_policy']) && is_array($contract['organization_effect_policy']) ? $contract['organization_effect_policy'] : [];
        $user = isset($contract['user_effect_preference']) && is_array($contract['user_effect_preference']) ? $contract['user_effect_preference'] : [];
        $runtime = isset($contract['runtime_constraints']) && is_array($contract['runtime_constraints']) ? $contract['runtime_constraints'] : [];

        $state = [
            'palette_mode' => self::allowlistedValue((string)($system['palette_mode'] ?? 'system'), self::PALETTE_MODES, 'system'),
            'effect_profile' => self::allowlistedValue((string)($system['effect_profile'] ?? 'liquid_glass'), ['paper', 'liquid_glass', 'none'], 'liquid_glass'),
            'effects_enabled' => !array_key_exists('effects_enabled', $system) || (bool)$system['effects_enabled'],
            'motion_mode' => self::allowlistedValue((string)($system['motion_mode'] ?? 'system'), self::MOTION_MODES, 'system'),
            'resolution_source' => 'system',
            'fallback_applied' => false,
            'fallback_reason' => null,
            'locked_by_policy' => false,
            'resolution_trace' => [
                ['step' => 'system_defaults', 'message' => 'Started from platform system defaults.', 'state' => []],
            ],
        ];

        if (isset($org['default_palette_mode'])) {
            $state['palette_mode'] = self::allowlistedValue((string)$org['default_palette_mode'], self::PALETTE_MODES, $state['palette_mode']);
            $state['resolution_source'] = 'organization';
        }
        if (isset($org['default_effect_profile'])) {
            $state['effect_profile'] = self::allowlistedValue((string)$org['default_effect_profile'], ['paper', 'liquid_glass', 'none'], $state['effect_profile']);
            $state['resolution_source'] = 'organization';
        }
        if (array_key_exists('effects_allowed', $org) && !(bool)$org['effects_allowed']) {
            $state['effects_enabled'] = false;
            $state['locked_by_policy'] = true;
            $state['fallback_applied'] = true;
            $state['fallback_reason'] = 'organization_effects_disabled';
        }
        if (isset($org['motion_policy'])) {
            $state['motion_mode'] = self::allowlistedValue((string)$org['motion_policy'], self::MOTION_MODES, $state['motion_mode']);
        }
        if (!empty($org['forced_profile'])) {
            $state['effect_profile'] = self::allowlistedValue((string)$org['forced_profile'], ['paper', 'liquid_glass', 'none'], $state['effect_profile']);
            $state['locked_by_policy'] = true;
            $state['resolution_source'] = 'organization';
            $state['resolution_trace'][] = ['step' => 'organization_policy', 'message' => 'Organization forced effect profile.', 'state' => ['effect_profile' => $state['effect_profile']]];
        } else {
            $state['resolution_trace'][] = ['step' => 'organization_policy', 'message' => 'Organization defaults and policy constraints applied.', 'state' => ['allow_user_override' => !empty($org['allow_user_override'])]];
        }
        if (!empty($org['forced_motion_mode'])) {
            $state['motion_mode'] = self::allowlistedValue((string)$org['forced_motion_mode'], self::MOTION_MODES, $state['motion_mode']);
            $state['locked_by_policy'] = true;
        }

        if (!empty($org['allow_user_override'])) {
            if (($user['palette_mode'] ?? 'inherit') !== 'inherit') {
                $state['palette_mode'] = self::allowlistedValue((string)$user['palette_mode'], self::PALETTE_MODES, $state['palette_mode']);
                $state['resolution_source'] = 'user';
            }
            if (($user['effect_profile'] ?? 'inherit') !== 'inherit' && empty($org['forced_profile'])) {
                $state['effect_profile'] = self::allowlistedValue((string)$user['effect_profile'], ['paper', 'liquid_glass', 'none'], $state['effect_profile']);
                $state['resolution_source'] = 'user';
            }
            if (($user['effects_enabled'] ?? 'inherit') !== 'inherit' && !empty($org['effects_allowed'])) {
                $state['effects_enabled'] = (string)$user['effects_enabled'] === 'enabled';
                $state['resolution_source'] = 'user';
            }
            if (($user['motion_mode'] ?? 'inherit') !== 'inherit' && empty($org['forced_motion_mode'])) {
                $state['motion_mode'] = self::allowlistedValue((string)$user['motion_mode'], self::MOTION_MODES, $state['motion_mode']);
                $state['resolution_source'] = 'user';
            }
            $state['resolution_trace'][] = ['step' => 'user_preference', 'message' => 'Permitted user preferences applied.', 'state' => []];
        } else {
            $state['resolution_trace'][] = ['step' => 'user_preference', 'message' => 'User preferences ignored because policy does not allow override.', 'state' => []];
        }

        if (!empty($runtime['reduced_motion_required'])) {
            $state['motion_mode'] = (string)($org['motion_policy'] ?? '') === 'off' || (string)($org['forced_motion_mode'] ?? '') === 'off' ? 'off' : 'reduced';
            $state['resolution_source'] = 'accessibility';
            $state['fallback_applied'] = true;
            $state['fallback_reason'] = $state['fallback_reason'] ?? 'reduced_motion_required';
            $state['resolution_trace'][] = ['step' => 'accessibility_constraints', 'message' => 'Reduced motion requirement overrode requested motion.', 'state' => ['motion_mode' => $state['motion_mode']]];
        }

        $readiness = (string)($runtime['profile_readiness'] ?? 'available');
        if (empty($runtime['theme_runtime_healthy'])) {
            $state['fallback_applied'] = true;
            $state['fallback_reason'] = $state['fallback_reason'] ?? 'theme_runtime_unhealthy';
            $state['resolution_trace'][] = ['step' => 'runtime_readiness', 'message' => 'Theme runtime is unhealthy; selected profile cannot be considered fully healthy.', 'state' => ['profile_readiness' => $readiness]];
        }
        if ($state['effect_profile'] === 'liquid_glass' && in_array($readiness, ['blocked', 'not_configured'], true)) {
            $state['effect_profile'] = !empty($runtime['paper_viable']) ? 'paper' : 'none';
            $state['fallback_applied'] = true;
            $state['fallback_reason'] = 'liquid_glass_blocked';
            $state['resolution_source'] = 'fallback';
        }
        if ((string)($runtime['capability_state'] ?? 'unknown') === 'unsupported' && $state['effect_profile'] === 'liquid_glass') {
            $state['effect_profile'] = !empty($runtime['paper_viable']) ? 'paper' : 'none';
            $state['fallback_applied'] = true;
            $state['fallback_reason'] = 'capability_unsupported';
            $state['resolution_source'] = 'fallback';
        }
        if ((string)($runtime['capability_state'] ?? 'unknown') === 'unknown') {
            $state['resolution_trace'][] = ['step' => 'capability_fallback', 'message' => 'Capability check is modeled; browser verification deferred.', 'state' => ['capability_state' => 'unknown']];
        }

        $state['resolution_trace'][] = ['step' => 'resolved_runtime_state', 'message' => 'Resolved state returned without persistence.', 'state' => [
            'palette_mode' => $state['palette_mode'],
            'effect_profile' => $state['effect_profile'],
            'effects_enabled' => $state['effects_enabled'],
            'motion_mode' => $state['motion_mode'],
        ]];
        $state['human_summary'] = self::resolutionHumanSummary($state, $runtime);
        return $state;
    }

    /**
     * @return array<int,array<string,string|bool>>
     */
    public static function futureScopeAuthorityContract(): array
    {
        return [
            ['setting_name' => 'Palette mode', 'scope_owner' => 'System default', 'authority_requirement' => 'platform_owned_not_editable_from_special_effects', 'user_override_allowed' => false, 'persistence_owner' => 'future_platform_defaults_registry', 'fallback_behavior' => 'system', 'current_phase' => 'Design only - persistence not enabled'],
            ['setting_name' => 'Effect profile', 'scope_owner' => 'Organization default', 'authority_requirement' => 'future_organization_authorized_configuration', 'user_override_allowed' => true, 'persistence_owner' => 'future_organization_effect_policy', 'fallback_behavior' => 'paper_or_none', 'current_phase' => 'Design only - persistence not enabled'],
            ['setting_name' => 'Effects enabled', 'scope_owner' => 'User preference', 'authority_requirement' => 'future_authenticated_user_setting_when_policy_permits', 'user_override_allowed' => true, 'persistence_owner' => 'future_user_effect_preference', 'fallback_behavior' => 'disabled_when_policy_or_runtime_requires', 'current_phase' => 'Design only - persistence not enabled'],
            ['setting_name' => 'Motion mode', 'scope_owner' => 'Preview-only local state', 'authority_requirement' => 'current_get_only_preview_state', 'user_override_allowed' => false, 'persistence_owner' => 'none_current_phase', 'fallback_behavior' => 'reduced_or_off_when_required', 'current_phase' => 'Design only - persistence not enabled'],
        ];
    }

    /**
     * @param array<string,mixed> $contract
     * @param array<string,mixed> $resolved
     * @param array<string,mixed> $previewState
     * @param array<int,array<string,mixed>> $authority
     * @return array<int,array<string,mixed>>
     */
    public static function futureControlPlans(array $contract, array $resolved, array $previewState = [], array $authority = []): array
    {
        $requested = [
            'palette_mode' => (string)($previewState['preview_palette_mode'] ?? $resolved['palette_mode'] ?? 'system'),
            'effect_profile' => (string)($previewState['preview_effect_profile'] ?? $resolved['effect_profile'] ?? 'paper'),
            'effects_enabled' => !empty($previewState) ? (!empty($previewState['preview_effects_enabled']) ? 'enabled' : 'disabled') : (!empty($resolved['effects_enabled']) ? 'enabled' : 'disabled'),
            'motion_mode' => (string)($previewState['preview_motion_mode'] ?? $resolved['motion_mode'] ?? 'system'),
        ];
        $readiness = (string)($contract['runtime_constraints']['profile_readiness'] ?? 'available');
        $authorityByName = [];
        foreach ($authority as $row) {
            if (is_array($row)) {
                $authorityByName[strtolower(str_replace(' ', '_', (string)($row['setting_name'] ?? '')))] = $row;
            }
        }

        $rows = [];
        foreach (['palette_mode', 'effect_profile', 'effects_enabled', 'motion_mode'] as $key) {
            $settingScope = in_array($key, ['effect_profile', 'effects_enabled', 'motion_mode'], true) ? 'user' : 'organization';
            $auth = $authorityByName[$key === 'effects_enabled' ? 'effects_enabled' : $key] ?? [];
            $payload = implode('|', [
                $settingScope,
                $key,
                (string)$requested[$key],
                (string)($resolved[$key] ?? ''),
                self::SCANNER_CONTRACT_VERSION,
            ]);
            $locked = !empty($resolved['locked_by_policy']);
            $blocked = [];
            if ($key === 'effect_profile' && (string)$requested[$key] !== (string)($resolved[$key] ?? '')) {
                $blocked[] = (string)($resolved['fallback_reason'] ?? 'fallback_applied');
            }
            if ($key === 'effects_enabled' && (string)$requested[$key] === 'enabled' && empty($resolved['effects_enabled'])) {
                $blocked[] = (string)($resolved['fallback_reason'] ?? 'effects_disabled_by_policy_or_runtime');
            }

            $rows[] = [
                'control_plan_id' => 'se-control-plan-' . substr(sha1($payload), 0, 16),
                'setting_scope' => $settingScope,
                'setting_key' => $key,
                'requested_value' => (string)$requested[$key],
                'current_resolved_value' => is_bool($resolved[$key] ?? null) ? (!empty($resolved[$key]) ? 'enabled' : 'disabled') : (string)($resolved[$key] ?? ''),
                'projected_resolved_value' => is_bool($resolved[$key] ?? null) ? (!empty($resolved[$key]) ? 'enabled' : 'disabled') : (string)($resolved[$key] ?? ''),
                'authority_requirement' => (string)($auth['authority_requirement'] ?? 'future_authority_required'),
                'policy_status' => $locked ? 'locked' : ($blocked === [] ? 'allowed' : 'blocked'),
                'runtime_readiness' => $readiness,
                'fallback_behavior' => (string)($auth['fallback_behavior'] ?? 'safe_fallback_required'),
                'would_require_persistence' => true,
                'would_require_audit' => true,
                'would_require_revalidation' => true,
                'required_evidence' => [
                    'authority_identity',
                    'policy_decision',
                    'runtime_readiness_at_change_time',
                    'post_change_runtime_validation',
                    'rollback_eligibility',
                ],
                'blocked_by' => $blocked,
                'mutation_url' => '',
                'form_action' => '',
                'api_command' => '',
                'write_instruction' => '',
            ];
        }
        return $rows;
    }

    /**
     * @return array<int,string>
     */
    public static function futureAuditRollbackEvidence(): array
    {
        return [
            'previous_requested_preference',
            'previous_resolved_state',
            'requested_new_value',
            'projected_resolved_state',
            'authority_identity',
            'scope',
            'timestamp',
            'policy_decision',
            'runtime_readiness_at_change_time',
            'fallback_result',
            'post_change_runtime_validation',
            'audit_event',
            'rollback_eligibility',
        ];
    }

    /**
     * @param array<string,mixed> $runtimeContract
     * @param array<string,mixed> $previewState
     * @param array<string,array<string,mixed>> $readiness
     * @return array<string,mixed>
     */
    public static function buildAppearanceIntegrationAudit(array $runtimeContract, array $previewState = [], array $readiness = []): array
    {
        $appearanceMap = self::appearanceStateMap($runtimeContract);
        $strategies = self::futurePersistenceStrategyEvaluation();
        $integrationRecords = self::futureResolverIntegrationRecords($appearanceMap, $runtimeContract);
        $mainBlockers = [];
        foreach ($integrationRecords as $record) {
            if (!is_array($record)) {
                continue;
            }
            foreach ((array)($record['blocked_by'] ?? []) as $blocker) {
                $blocker = (string)$blocker;
                if ($blocker !== '') {
                    $mainBlockers[$blocker] = $blocker;
                }
            }
        }

        return [
            'current_phase' => 'Read-only integration audit - persistence not enabled',
            'appearance_state_map' => $appearanceMap,
            'compatibility_strategy_evaluation' => $strategies,
            'recommended_future_persistence_strategy' => 'C. Structured appearance state with backward-compatible combined-mode reads',
            'recommendation_rationale' => 'Existing combined values must continue to read, but effects_enabled, none, motion, organization policy, audit, rollback, and runtime-health gates need a single structured source of truth instead of a second competing preference.',
            'recommendation_prerequisites' => [
                'Canonical appearance-state owner selected outside Special Effects.',
                'Legacy combined value reader preserved for system-liquid-glass, system-paper, dark-liquid-glass, dark-paper, light-liquid-glass, and light-paper.',
                'Organization policy model defined before user preference writes.',
                'CSRF, authorization, audit, rollback, and validation contracts defined for each setting scope.',
                'Theme Doctor readiness result available before persistent effect-profile changes.',
            ],
            'readiness_records' => $integrationRecords,
            'main_blockers' => array_values($mainBlockers),
            'conflict_prevention' => [
                'rule' => 'There must be one canonical persisted appearance source of truth. Preview state is never persisted, preview state never overrides policy, user preference never bypasses organization policy, and organization policy never bypasses system safety or runtime fallback.',
                'precedence_order' => [
                    'system_defaults',
                    'organization_policy',
                    'permitted_user_preference',
                    'preview_only_state_for_preview_surface_only',
                    'runtime_safety_fallback',
                    'resolved_runtime_state',
                ],
                'potential_conflict_sources' => [
                    ['source' => 'Current global combined mode', 'status' => 'confirmed_contract', 'impact' => 'Current persisted/browser-visible appearance input.'],
                    ['source' => 'Future organization policy', 'status' => 'unknown_requires_implementation', 'impact' => 'Must constrain user choices before persistence is enabled.'],
                    ['source' => 'Future user preference', 'status' => 'partial_contract', 'impact' => 'Existing operator theme preference exists, but effects and motion are not covered.'],
                    ['source' => 'Preview-only state', 'status' => 'confirmed_contract', 'impact' => 'GET-only iframe state; must never become a draft or persisted setting.'],
                    ['source' => 'Runtime safety fallback', 'status' => 'confirmed_contract', 'impact' => 'Resolver/Theme Doctor readiness can override requested effects.'],
                ],
            ],
            'theme_doctor_dependency' => self::themeDoctorDependencyContract($readiness),
            'preview_relationship' => [
                'preview_source' => (string)($previewState['preview_source'] ?? 'active_mode'),
                'preview_persistence' => 'none',
                'preview_authority' => 'none',
                'preview_impact_on_active_mode' => 'none',
                'preview_contract_status' => 'local_non_persistent_state_only',
            ],
            'no_persistence_enabled' => true,
        ];
    }

    /**
     * @param array<string,mixed> $runtimeContract
     * @return array<string,mixed>
     */
    public static function appearanceStateMap(array $runtimeContract): array
    {
        $currentCombined = (string)($runtimeContract['combined_preference'] ?? 'system-liquid-glass');
        $paletteMode = (string)($runtimeContract['palette_mode'] ?? 'system');
        $colorStyle = self::styleKeyFromEffectProfile((string)($runtimeContract['configured_effect_profile'] ?? 'liquid_glass'));

        return [
            'current_combined_mode' => $currentCombined,
            'state_origin' => 'Hybrid: server fallback from Shell ThemePreferenceService/Core settings, then browser localStorage may override the rendered page.',
            'state_owner' => 'hybrid_system_user_browser',
            'persistence_mechanism' => 'core_settings ui.theme/system.theme for system default; operator_preferences theme for operator/user preference; localStorage erp-theme-preference for immediate browser runtime; session fallback in /u/theme/apply on preference write failure.',
            'write_path_exists' => true,
            'write_path_summary' => 'Confirmed Shell user write paths: POST /u/preferences/save and POST /u/theme/apply delegate to the Shell operator preference writer for theme values. Browser theme selector writes localStorage only.',
            'authorization_contract' => 'Confirmed for Shell operator POST routes through resolved operator route/current user context; Special Effects does not own or call this path.',
            'csrf_contract' => 'Confirmed: Shell routes require Auth::requireCsrf for /u/preferences/save and /u/theme/apply.',
            'audit_contract' => 'Unknown for appearance preferences; OperatorPreferencesService writes operator_preferences without a dedicated appearance audit event in the inspected path.',
            'rollback_contract' => 'Unknown for appearance preferences; no dedicated rollback/snapshot path was identified for theme preference changes.',
            'rendering_path' => [
                'apps/Shell/Services/ThemePreferenceService::defaultPreference reads core_setting(ui.theme/system.theme).',
                'public/views/layouts/header.php seeds THEME_FALLBACK_PREFERENCE and allowed preferences.',
                'public/views/layouts/header.php loadThemePreference reads localStorage erp-theme-preference.',
                'public/views/layouts/header.php applyThemePreference writes data-theme-mode, data-theme, data-color-style, data-theme-preference.',
                'public/views/layouts/header.php and auth_header.php include /assets/theme.css or equivalent theme assets.',
            ],
            'runtime_dependencies' => [
                '/assets/theme.css compiled runtime asset.',
                'resources/themes/theme-manifest.json and scripts/assets/compile_theme_sources.php for runtime asset generation.',
                'apps/Shell/Resources/published-theme-options.json for allowed combined choices.',
                'window.matchMedia(prefers-color-scheme: dark) when mode is system.',
            ],
            'theme_health_dependency' => [
                'Theme Doctor owns compiled asset health, selector presence, source/runtime validation, and compile diagnostics.',
                'Special Effects must consume readiness and fallback evidence, not independently declare profile health.',
            ],
            'compatibility_constraints' => [
                'Existing combined values must keep working.',
                'Existing combined values encode palette plus paper/liquid-glass only.',
                'none, effects_enabled, and motion_mode are runtime/preview concepts today and require a future persistence strategy.',
                'LocalStorage can override server fallback in the live page, so server-only inspection is not the full active-browser state.',
            ],
            'evidence' => [
                ['status' => 'confirmed_contract', 'subject' => 'Theme choices and server default', 'source_path' => 'apps/Shell/Services/ThemePreferenceService.php'],
                ['status' => 'confirmed_contract', 'subject' => 'Core default settings ui.theme/system.theme', 'source_path' => 'app/Core/helpers.php'],
                ['status' => 'confirmed_contract', 'subject' => 'Runtime DOM attributes and localStorage override', 'source_path' => 'public/views/layouts/header.php'],
                ['status' => 'confirmed_contract', 'subject' => 'Operator preference write paths', 'source_path' => 'apps/Shell/routes.php'],
                ['status' => 'confirmed_contract', 'subject' => 'Operator preference database write', 'source_path' => 'apps/Shell/Services/OperatorPreferencesService.php'],
                ['status' => 'inferred_contract', 'subject' => 'Organization-level appearance policy', 'source_path' => 'No dedicated org effects policy source found in inspected path.'],
                ['status' => 'unknown_requires_implementation', 'subject' => 'Appearance audit and rollback', 'source_path' => 'No dedicated appearance audit/rollback contract identified.'],
                ['status' => 'unsafe_to_reuse', 'subject' => 'Preview query state as persistence source', 'source_path' => 'Special Effects preview is GET-only iframe state.'],
            ],
            'resolved_runtime_attributes' => [
                'data-theme-mode' => $paletteMode,
                'data-theme' => $paletteMode === 'system' ? 'browser_resolved_light_or_dark' : $paletteMode,
                'data-color-style' => $colorStyle,
                'data-theme-preference' => $currentCombined,
                'asset' => '/assets/theme.css',
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function futurePersistenceStrategyEvaluation(): array
    {
        return [
            [
                'strategy_id' => 'A',
                'label' => 'Extend existing combined theme mode persistence contract',
                'backward_compatibility' => 'high',
                'ownership_clarity' => 'partial',
                'user_preference_support' => 'partial',
                'organization_policy_support' => 'unknown',
                'motion_mode_support' => 'blocked',
                'no_effects_profile_support' => 'blocked',
                'theme_selector_compatibility' => 'high',
                'migration_complexity' => 'low',
                'auditability' => 'partial',
                'rollback_feasibility' => 'unknown',
                'risk_of_split_brain_state' => 'medium',
                'decision' => 'not_recommended_as_final_shape',
            ],
            [
                'strategy_id' => 'B',
                'label' => 'Add separate appearance/effects preference contract resolved with combined mode',
                'backward_compatibility' => 'medium',
                'ownership_clarity' => 'partial',
                'user_preference_support' => 'high',
                'organization_policy_support' => 'possible',
                'motion_mode_support' => 'high',
                'no_effects_profile_support' => 'high',
                'theme_selector_compatibility' => 'medium',
                'migration_complexity' => 'medium',
                'auditability' => 'possible',
                'rollback_feasibility' => 'possible',
                'risk_of_split_brain_state' => 'high_without_single_resolver_owner',
                'decision' => 'unsafe_unless_it_is_only_a_transition_adapter',
            ],
            [
                'strategy_id' => 'C',
                'label' => 'Structured appearance state with backward-compatible combined-mode reads',
                'backward_compatibility' => 'high_when_legacy_reader_is_preserved',
                'ownership_clarity' => 'high',
                'user_preference_support' => 'high',
                'organization_policy_support' => 'high',
                'motion_mode_support' => 'high',
                'no_effects_profile_support' => 'high',
                'theme_selector_compatibility' => 'requires_adapter',
                'migration_complexity' => 'medium_high',
                'auditability' => 'high',
                'rollback_feasibility' => 'high_with_snapshot_or_change_record',
                'risk_of_split_brain_state' => 'low_if_single_source_of_truth_is_enforced',
                'decision' => 'recommended',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $appearanceMap
     * @param array<string,mixed> $runtimeContract
     * @return array<int,array<string,mixed>>
     */
    public static function futureResolverIntegrationRecords(array $appearanceMap, array $runtimeContract): array
    {
        $records = [];
        $base = [
            'existing_source_of_truth' => (string)($appearanceMap['persistence_mechanism'] ?? 'unknown'),
            'authority_contract_available' => true,
            'audit_contract_available' => false,
            'rollback_contract_available' => false,
            'runtime_application_path_available' => true,
            'migration_required' => true,
            'mutation_url' => '',
            'form_action' => '',
            'write_command' => '',
            'persistence_call' => '',
        ];
        $settingRows = [
            'palette_mode' => [
                'scope' => 'user',
                'owner' => 'Shell operator appearance preference or future structured appearance owner',
                'write_reusable' => true,
                'status' => 'partial',
                'blocked_by' => ['appearance_audit_missing', 'appearance_rollback_missing', 'organization_policy_not_modeled_for_palette'],
                'work' => ['Define audited appearance change record.', 'Define rollback behavior.', 'Decide whether browser localStorage remains a cache or becomes display-only.'],
            ],
            'effect_profile' => [
                'scope' => 'organization',
                'owner' => 'future structured appearance/effects policy owner',
                'write_reusable' => false,
                'status' => 'partial',
                'blocked_by' => ['theme_doctor_dependency_gate_required', 'none_profile_not_in_existing_combined_values', 'appearance_audit_missing', 'appearance_rollback_missing'],
                'work' => ['Add Theme Doctor readiness precondition.', 'Preserve legacy paper/liquid-glass reads.', 'Model none as structured profile, not legacy combined string.'],
            ],
            'effects_enabled' => [
                'scope' => 'organization',
                'owner' => 'future structured appearance/effects policy owner',
                'write_reusable' => false,
                'status' => 'blocked',
                'blocked_by' => ['no_existing_source_of_truth_for_effects_enabled', 'organization_policy_not_implemented', 'appearance_audit_missing', 'appearance_rollback_missing'],
                'work' => ['Create canonical effects enabled field.', 'Ensure org policy constrains user preference.', 'Add audit and rollback requirements.'],
            ],
            'motion_mode' => [
                'scope' => 'user',
                'owner' => 'future structured appearance/accessibility-aware preference owner',
                'write_reusable' => false,
                'status' => 'blocked',
                'blocked_by' => ['no_existing_source_of_truth_for_motion_mode', 'reduced_motion_policy_not_persisted', 'appearance_audit_missing', 'appearance_rollback_missing'],
                'work' => ['Create motion mode field.', 'Define accessibility precedence over requested motion.', 'Add runtime application contract.'],
            ],
        ];

        foreach ($settingRows as $settingKey => $row) {
            $allConfirmed = !empty($row['write_reusable'])
                && !empty($base['authority_contract_available'])
                && !empty($base['audit_contract_available'])
                && !empty($base['rollback_contract_available'])
                && !empty($base['runtime_application_path_available'])
                && $row['blocked_by'] === [];
            $status = $allConfirmed ? 'ready' : (string)$row['status'];
            $payload = implode('|', [$settingKey, (string)$row['scope'], (string)$row['owner'], self::SPECIAL_EFFECTS_ADAPTER_VERSION]);
            $records[] = $base + [
                'integration_readiness_id' => 'se-integration-readiness-' . substr(sha1($payload), 0, 16),
                'setting_scope' => (string)$row['scope'],
                'setting_key' => $settingKey,
                'future_persistence_owner' => (string)$row['owner'],
                'existing_write_path_reusable' => (bool)$row['write_reusable'],
                'readiness_status' => $status,
                'blocked_by' => (array)$row['blocked_by'],
                'required_future_work' => (array)$row['work'],
                'current_runtime_value' => is_bool($runtimeContract[$settingKey] ?? null)
                    ? (!empty($runtimeContract[$settingKey]) ? 'enabled' : 'disabled')
                    : (string)($runtimeContract[$settingKey] ?? ''),
            ];
        }

        return $records;
    }

    /**
     * @param array<string,array<string,mixed>> $readiness
     * @return array<string,mixed>
     */
    public static function themeDoctorDependencyContract(array $readiness = []): array
    {
        $liquid = isset($readiness['liquid_glass']) && is_array($readiness['liquid_glass']) ? $readiness['liquid_glass'] : [];
        $paper = isset($readiness['paper']) && is_array($readiness['paper']) ? $readiness['paper'] : [];
        return [
            'theme_doctor_owns' => [
                'compiled_runtime_asset_health',
                'selector_presence',
                'required_theme_source_runtime_validation',
                'theme_compile_diagnostics',
            ],
            'special_effects_owns' => [
                'effect_profile_request',
                'effect_policy_resolution',
                'motion_and_effects_request',
                'fallback_selection',
                'handoff_visibility',
            ],
            'future_persistent_change_blocks_when' => [
                'theme_runtime_asset_missing' => true,
                'required_selectors_absent' => true,
                'required_tokens_missing' => true,
                'profile_readiness_blocked' => true,
                'fallback_profile_unavailable' => true,
            ],
            'current_readiness_evidence' => [
                'liquid_glass_outcome' => (string)($liquid['outcome'] ?? 'unknown'),
                'liquid_glass_missing_selectors' => (array)($liquid['missing_selectors'] ?? []),
                'liquid_glass_missing_tokens' => (array)($liquid['missing_tokens'] ?? []),
                'paper_outcome' => (string)($paper['outcome'] ?? 'unknown'),
            ],
            'repair_or_compile_triggered_in_this_slice' => false,
        ];
    }

    /**
     * @param array<string,mixed> $profiles
     * @return array<string,mixed>
     */
    private static function runtimeConstraintsForProfile(string $profile, string $motionMode, array $profiles, ?string $compiledCss): array
    {
        $contract = [
            'palette_mode' => 'system',
            'effect_profile' => $profile,
            'configured_effect_profile' => $profile,
            'effects_enabled' => true,
            'motion_mode' => $motionMode,
        ];
        $readiness = self::readinessForProfiles($profiles, $contract, $compiledCss);
        $row = isset($readiness[$profile]) && is_array($readiness[$profile]) ? $readiness[$profile] : [];
        return [
            'theme_runtime_healthy' => $compiledCss !== '',
            'profile_readiness' => (string)($row['outcome'] ?? 'not_configured'),
            'reduced_motion_required' => $motionMode === 'reduced' || $motionMode === 'off',
            'capability_state' => in_array('browser_backdrop_filter_capability_assumed_with_semantic_surface_fallback', (array)($row['dependencies'] ?? []), true) ? 'unknown' : 'available',
            'paper_viable' => isset($readiness['paper']) && (string)($readiness['paper']['outcome'] ?? '') !== 'blocked',
        ];
    }

    /**
     * @param array<string,mixed> $state
     * @param array<string,mixed> $runtime
     */
    private static function resolutionHumanSummary(array $state, array $runtime): string
    {
        if (!empty($state['fallback_applied'])) {
            return 'Requested state was adjusted by safety, policy, accessibility, runtime readiness, or capability fallback: ' . (string)($state['fallback_reason'] ?? 'fallback_applied') . '.';
        }
        if ((string)($runtime['capability_state'] ?? '') === 'unknown') {
            return 'Requested state is modeled as available with deferred browser capability verification.';
        }
        return 'Requested state resolves without fallback in the current read-only design contract.';
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    public static function effectProfiles(): array
    {
        return [
            'paper' => [
                'effect_profile_id' => 'paper',
                'label' => 'Paper',
                'description' => 'Low-effect semantic surfaces with opaque paper-style contrast.',
                'status' => 'available',
                'requires_compiled_theme' => true,
                'required_selectors' => ['[data-color-style="paper"]'],
                'required_tokens' => ['--surface-card', '--surface-muted', '--border-soft', '--shadow-sm'],
                'required_capabilities' => ['semantic_surface_fallbacks'],
                'fallback_profile' => 'none',
                'motion_behavior' => 'reduced_motion_safe_static_surfaces',
                'contrast_contract' => 'Must preserve opaque/semi-opaque readable surfaces without decorative dependency.',
                'accessibility_constraints' => [
                    'Never required for focus indication.',
                    'Never required for readability.',
                    'Must remain usable when effects are disabled.',
                ],
                'runtime_evidence' => [],
            ],
            'liquid_glass' => [
                'effect_profile_id' => 'liquid_glass',
                'label' => 'Liquid Glass',
                'description' => 'Optional translucent glass, blur, highlight, and decorative shadow effects.',
                'status' => 'available',
                'requires_compiled_theme' => true,
                'required_selectors' => ['[data-color-style="liquid-glass"]'],
                'required_tokens' => ['--glass', '--glass-strong', '--glass-border', '--glass-shadow'],
                'required_capabilities' => ['backdrop_filter', 'translucent_surface_fallback', 'contrast_safe_overlay'],
                'fallback_profile' => 'paper',
                'motion_behavior' => 'transitions_and_transforms_must_be_removed_or_minimized_when_reduced',
                'contrast_contract' => 'Backdrop effects must fall back to opaque or semi-opaque semantic surfaces.',
                'accessibility_constraints' => [
                    'Must not carry text contrast alone.',
                    'Must not be required for navigation landmarks.',
                    'Must minimize decorative motion under reduced motion.',
                ],
                'runtime_evidence' => [],
            ],
            'none' => [
                'effect_profile_id' => 'none',
                'label' => 'None',
                'description' => 'No optional visual effects; Shell and theme palette remain responsible for baseline UX.',
                'status' => 'available',
                'requires_compiled_theme' => false,
                'required_selectors' => [],
                'required_tokens' => [],
                'required_capabilities' => ['baseline_shell_and_theme_fallback'],
                'fallback_profile' => 'none',
                'motion_behavior' => 'no_decorative_motion',
                'contrast_contract' => 'Baseline Shell/Foundation and theme palette own readability and focus.',
                'accessibility_constraints' => [
                    'Must remain fully readable.',
                    'Must preserve focus and keyboard behavior.',
                    'Must not change layout semantics.',
                ],
                'runtime_evidence' => [],
            ],
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $profiles
     * @return array<string,array<string,mixed>>
     */
    public static function readinessForProfiles(array $profiles, array $runtimeContract, ?string $compiledCss = null): array
    {
        $compiledCss = $compiledCss ?? self::readCompiledThemeCss();
        $compiledAvailable = $compiledCss !== '';
        $motionMode = (string)($runtimeContract['motion_mode'] ?? 'system');
        $effectsEnabled = !empty($runtimeContract['effects_enabled']);
        $readiness = [];

        foreach ($profiles as $profileId => $profile) {
            $deps = [];
            $missingSelectors = [];
            $missingTokens = [];
            $outcome = 'available';

            if (!empty($profile['requires_compiled_theme']) && !$compiledAvailable) {
                $outcome = 'blocked';
                $deps[] = 'theme_doctor_compiled_asset_missing';
            }

            foreach ((array)($profile['required_selectors'] ?? []) as $selector) {
                if ($compiledAvailable && !str_contains($compiledCss, (string)$selector)) {
                    $missingSelectors[] = (string)$selector;
                }
            }
            foreach ((array)($profile['required_tokens'] ?? []) as $token) {
                if ($compiledAvailable && !str_contains($compiledCss, (string)$token)) {
                    $missingTokens[] = (string)$token;
                }
            }

            if ($outcome !== 'blocked' && ($missingSelectors !== [] || $missingTokens !== [])) {
                $outcome = $profileId === 'none' ? 'available' : 'degraded';
            }
            if (!$effectsEnabled && $profileId !== 'paper' && $profileId !== 'none') {
                $outcome = 'not_configured';
                $deps[] = 'effects_disabled_fallback_active';
            }
            if ($motionMode === 'reduced' && $profileId === 'liquid_glass') {
                $deps[] = 'reduced_motion_requires_transition_transform_minimization';
            } elseif ($motionMode === 'off' && $profileId !== 'none') {
                $deps[] = 'decorative_motion_off';
            }
            if (in_array('backdrop_filter', (array)($profile['required_capabilities'] ?? []), true)) {
                $deps[] = 'browser_backdrop_filter_capability_assumed_with_semantic_surface_fallback';
            }

            $readiness[(string)$profileId] = [
                'effect_profile_id' => (string)$profileId,
                'outcome' => $outcome,
                'dependencies' => array_values(array_unique($deps)),
                'missing_selectors' => $missingSelectors,
                'missing_tokens' => $missingTokens,
                'fallback_profile' => (string)($profile['fallback_profile'] ?? 'none'),
                'reduced_motion_behavior' => (string)($profile['motion_behavior'] ?? ''),
                'runtime_evidence' => [
                    'compiled_theme_asset_present' => $compiledAvailable,
                    'required_selector_count' => count((array)($profile['required_selectors'] ?? [])),
                    'required_token_count' => count((array)($profile['required_tokens'] ?? [])),
                    'effects_enabled' => $effectsEnabled,
                    'motion_mode' => $motionMode,
                ],
            ];
        }

        return $readiness;
    }

    /**
     * @return array<string,mixed>
     */
    public static function collectStyleComplianceHandoffs(?array $scanContext = null): array
    {
        $scanContext = $scanContext !== null ? self::normalizeScanContext(
            (string)($scanContext['scope'] ?? StyleComplianceScannerService::SCOPE_OWNER),
            (string)($scanContext['owner'] ?? 'Studio'),
            (string)($scanContext['workspace'] ?? 'special-effects')
        ) : self::normalizeScanContext(StyleComplianceScannerService::SCOPE_OWNER, 'Studio', 'special-effects');

        $rows = [];
        $excluded = [];
        $available = 0;
        $seen = [];
        $scan = StyleComplianceScannerService::scan((string)$scanContext['scope'], (string)$scanContext['owner']);
        $scanContext = self::scanContextFromScan($scan, $scanContext);
        $queue = $scan['theme_repair_proposals']['queues']['future_tool_handoffs'] ?? [];
        if (!is_array($queue)) {
            $queue = [];
        }

        foreach ($queue as $proposal) {
            if (!is_array($proposal) || !self::isSpecialEffectsHandoff($proposal)) {
                continue;
            }
            $id = self::handoffStableKey($proposal, $scanContext);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $available++;
            $normalized = self::normalizeHandoff($proposal, $scanContext);
            if (!self::isSupportedHandoffSource((string)$normalized['file_path'])) {
                $normalized['excluded_reason'] = 'unsupported_source_policy';
                $excluded[] = $normalized;
                continue;
            }
            $rows[] = $normalized;
        }

        usort($rows, static function (array $a, array $b): int {
            return [(string)$a['effect_category'], (string)$a['file_path'], (string)$a['selector'], (string)$a['property']]
                <=> [(string)$b['effect_category'], (string)$b['file_path'], (string)$b['selector'], (string)$b['property']];
        });

        $groups = [];
        $unmapped = 0;
        $missingProfile = 0;
        foreach ($rows as $row) {
            $category = (string)$row['effect_category'];
            if ($category === 'review_required') {
                $unmapped++;
            }
            if ((string)$row['profile_relevance'] === 'profile_mapping_required') {
                $missingProfile++;
            }
            $groups[$category][] = $row;
        }
        $parity = self::buildParityComparison($queue, $rows, $excluded, $scanContext);

        return [
            'summary' => [
                'total' => count($rows),
                'groups' => array_map('count', $groups),
                'available' => $available,
                'excluded' => count($excluded),
                'unmapped' => $unmapped,
            ],
            'groups' => $groups,
            'rows' => $rows,
            'excluded' => $excluded,
            'reconciliation' => [
                'available' => $available,
                'displayed' => count($rows),
                'excluded' => count($excluded),
                'unmapped' => $unmapped,
                'matching' => (int)($parity['matching_handoff_ids'] ?? 0),
                'missing' => (int)($parity['missing_in_special_effects'] ?? 0),
                'unexpected' => (int)($parity['unexpected_in_special_effects'] ?? 0),
                'blocked_by_missing_profile_mapping' => $missingProfile,
                'outside_current_selected_scope' => 0,
                'supported_source_policy_excluded' => count($excluded),
                'category_mapping_status' => $unmapped === 0 ? 'mapped' : 'review_required',
                'source_context_used' => 'single_normalized_style_compliance_context',
            ],
            'parity' => $parity,
            'scan_context' => $scanContext,
            'source_contract' => [
                'proposal_class' => 'future_tool_handoff',
                'migration_state' => StyleComplianceScannerService::MIGRATION_STATE_FUTURE_HANDOFF,
                'governance_domain' => 'special_effect',
                'scanner_contract_version' => self::SCANNER_CONTRACT_VERSION,
                'adapter_version' => self::SPECIAL_EFFECTS_ADAPTER_VERSION,
                'scanner_reclassified_here' => false,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function normalizeScanContext(string $scope, string $owner, string $workspace = 'special-effects'): array
    {
        $allowed = [
            StyleComplianceScannerService::SCOPE_OWNER,
            StyleComplianceScannerService::SCOPE_SHELL,
            StyleComplianceScannerService::SCOPE_THEME,
        ];
        $scope = in_array($scope, $allowed, true) ? $scope : StyleComplianceScannerService::SCOPE_OWNER;
        $owner = trim($owner);
        if ($scope === StyleComplianceScannerService::SCOPE_OWNER && $owner === '') {
            $owner = 'Studio';
        }
        if ($scope !== StyleComplianceScannerService::SCOPE_OWNER) {
            $owner = '';
        }

        return [
            'scope' => $scope,
            'owner' => $owner,
            'workspace' => $workspace,
            'source_root' => '',
            'operational_filter_version' => self::OPERATIONAL_FILTER_VERSION,
            'scanner_contract_version' => self::SCANNER_CONTRACT_VERSION,
            'scanner_revision_marker' => self::scannerRevisionMarker(),
            'source_revision_fingerprint' => '',
            'operational_filtering_status' => 'style_compliance_operational_findings_only',
        ];
    }

    /**
     * @param array<string,mixed> $expected
     * @param array<string,mixed> $actual
     */
    public static function scanContextDifferenceReason(array $expected, array $actual): string
    {
        if ((string)($expected['scope'] ?? '') !== (string)($actual['scope'] ?? '')) {
            return 'scope_mismatch';
        }
        if ((string)($expected['owner'] ?? '') !== (string)($actual['owner'] ?? '')) {
            return 'owner_mismatch';
        }
        if ((string)($expected['source_revision_fingerprint'] ?? '') !== ''
            && (string)($actual['source_revision_fingerprint'] ?? '') !== ''
            && (string)$expected['source_revision_fingerprint'] !== (string)$actual['source_revision_fingerprint']) {
            return 'source_revision_mismatch';
        }
        if ((string)($expected['scanner_contract_version'] ?? '') !== ''
            && (string)($actual['scanner_contract_version'] ?? '') !== ''
            && (string)$expected['scanner_contract_version'] !== (string)$actual['scanner_contract_version']) {
            return 'scanner_contract_mismatch';
        }
        return 'unknown';
    }

    /**
     * @param array<string,mixed> $scan
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function scanContextFromScan(array $scan, array $context): array
    {
        $descriptor = isset($scan['scope_descriptor']) && is_array($scan['scope_descriptor']) ? $scan['scope_descriptor'] : [];
        $root = (string)($descriptor['root'] ?? '');
        $tokens = isset($scan['tokens']) && is_array($scan['tokens']) ? $scan['tokens'] : [];

        $context['scope'] = (string)($scan['scope'] ?? $context['scope']);
        $context['owner'] = (string)($scan['owner_key'] ?? $context['owner']);
        $context['source_root'] = $root;
        $context['scan_root'] = $root;
        $context['source_revision_fingerprint'] = self::sourceRevisionFingerprint($tokens, $root);
        $context['scanner_revision_marker'] = self::scannerRevisionMarker();
        $context['handoff_source_timestamp'] = gmdate('c');
        return $context;
    }

    /**
     * @param array<int,mixed> $styleQueue
     * @param array<int,array<string,mixed>> $displayed
     * @param array<int,array<string,mixed>> $excluded
     * @param array<string,mixed> $scanContext
     * @return array<string,mixed>
     */
    private static function buildParityComparison(array $styleQueue, array $displayed, array $excluded, array $scanContext): array
    {
        $style = [];
        foreach ($styleQueue as $proposal) {
            if (!is_array($proposal) || !self::isSpecialEffectsHandoff($proposal)) {
                continue;
            }
            $normalized = self::normalizeHandoff($proposal, $scanContext);
            $style[(string)$normalized['handoff_id']] = $normalized;
        }

        $displayedById = [];
        foreach ($displayed as $row) {
            $displayedById[(string)($row['handoff_id'] ?? '')] = $row;
        }
        $excludedById = [];
        foreach ($excluded as $row) {
            $excludedById[(string)($row['handoff_id'] ?? '')] = $row;
        }

        $matching = 0;
        $missing = [];
        $unexpected = [];
        $diagnostics = [];

        foreach ($style as $id => $row) {
            if (isset($displayedById[$id])) {
                $matching++;
                continue;
            }
            $reason = isset($excludedById[$id])
                ? (string)($excludedById[$id]['excluded_reason'] ?? 'adapter_filter_exclusion')
                : ((string)($row['effect_category'] ?? '') === 'review_required' ? 'missing_effect_category_mapping' : 'unknown');
            $missing[] = $row;
            $diagnostics[] = self::parityDiagnosticRow($row, 'available', isset($excludedById[$id]) ? 'excluded' : 'missing', $reason);
        }

        foreach ($displayedById as $id => $row) {
            if ($id === '' || isset($style[$id])) {
                continue;
            }
            $unexpected[] = $row;
            $diagnostics[] = self::parityDiagnosticRow($row, 'missing', 'displayed', 'unexpected_in_special_effects');
        }

        return [
            'title' => 'Style Compliance ↔ Special Effects Parity',
            'style_compliance_available' => count($style),
            'special_effects_displayed' => count($displayed),
            'matching_handoff_ids' => $matching,
            'missing_in_special_effects' => count($missing),
            'unexpected_in_special_effects' => count($unexpected),
            'excluded_by_adapter' => count($excluded),
            'unmapped_category_records' => count(array_filter($displayed, static fn(array $row): bool => (string)($row['effect_category'] ?? '') === 'review_required')),
            'parity_status' => ($matching === count($style) && count($style) === count($displayed) && $excluded === []) ? 'exact' : 'difference',
            'context_message' => 'Handoff comparison uses one matching scope and owner.',
            'difference_rows' => $diagnostics,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function parityDiagnosticRow(array $row, string $styleStatus, string $effectsStatus, string $reason): array
    {
        return [
            'handoff_id' => (string)($row['handoff_id'] ?? ''),
            'finding_id' => (string)($row['finding_id'] ?? ''),
            'file_path' => (string)($row['file_path'] ?? $row['file'] ?? ''),
            'line' => (int)($row['line'] ?? 0),
            'selector_property' => trim((string)($row['selector'] ?? '') . ' / ' . (string)($row['property'] ?? '')),
            'style_compliance_status' => $styleStatus,
            'special_effects_status' => $effectsStatus,
            'difference_reason' => $reason,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function futureControls(): array
    {
        $rows = [
            ['profile_selection', 'User/org profile selection', 'user_or_org', 'platform_admin_future', 'paper_or_none', 'theme_preference_runtime_owner'],
            ['effects_enabled', 'Effects enabled/disabled', 'user_or_org', 'platform_admin_future', 'paper_or_none', 'theme_preference_runtime_owner'],
            ['motion_preference', 'Motion preference', 'user', 'authenticated_user_future', 'system_or_reduced_motion', 'accessibility_preference_runtime_owner'],
            ['organization_default', 'Organization default profile', 'organization', 'platform_admin_future', 'paper', 'governed_settings_future_owner'],
            ['user_override', 'User override', 'user', 'authenticated_user_future', 'organization_default', 'user_preferences_future_owner'],
            ['capability_fallback', 'Browser capability fallback', 'runtime_capability', 'system_owned', 'opaque_semantic_surface', 'runtime_resolver_future_owner'],
            ['reduced_motion_enforcement', 'Reduced-motion enforcement', 'accessibility_runtime', 'system_owned', 'remove_or_minimize_motion', 'runtime_resolver_future_owner'],
        ];

        return array_map(static fn(array $row): array => [
            'control_id' => $row[0],
            'label' => $row[1],
            'scope' => $row[2],
            'authority_requirement' => $row[3],
            'fallback_behavior' => $row[4],
            'persistence_owner' => $row[5],
            'enabled_now' => false,
            'status' => 'not_enabled_in_this_phase',
        ], $rows);
    }

    /**
     * @param array<int,string> $outcomes
     * @return array{blocked:int,degraded:int}
     */
    private static function countProfilesByReadiness(array $readiness, array $outcomes): array
    {
        $counts = ['blocked' => 0, 'degraded' => 0];
        foreach ($readiness as $row) {
            $outcome = (string)($row['outcome'] ?? '');
            if (in_array($outcome, $outcomes, true) && isset($counts[$outcome])) {
                $counts[$outcome]++;
            }
        }
        return $counts;
    }

    private static function normalizeCombinedPreference(?string $combinedPreference): string
    {
        $raw = strtolower(trim((string)$combinedPreference));
        $fallback = 'system-liquid-glass';
        $allowed = [
            'system-liquid-glass',
            'system-paper',
            'dark-liquid-glass',
            'dark-paper',
            'light-liquid-glass',
            'light-paper',
        ];
        if ($raw === 'system' || $raw === 'light' || $raw === 'dark') {
            return $raw . '-liquid-glass';
        }
        return in_array($raw, $allowed, true) ? $raw : $fallback;
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitCombinedPreference(string $preference): array
    {
        foreach (self::PALETTE_MODES as $mode) {
            $prefix = $mode . '-';
            if (str_starts_with($preference, $prefix)) {
                return [$mode, substr($preference, strlen($prefix)) ?: 'liquid-glass'];
            }
        }
        return ['system', 'liquid-glass'];
    }

    private static function effectProfileFromStyleKey(string $styleKey): string
    {
        return match (str_replace('-', '_', strtolower($styleKey))) {
            'paper' => 'paper',
            'none' => 'none',
            default => 'liquid_glass',
        };
    }

    private static function styleKeyFromEffectProfile(string $profile): string
    {
        return match ($profile) {
            'paper' => 'paper',
            'none' => 'paper',
            default => 'liquid-glass',
        };
    }

    /**
     * @param array<int,string> $allowed
     */
    private static function allowlistedValue(string $value, array $allowed, string $fallback): string
    {
        $value = strtolower(trim($value));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private static function previewRenderedProfile(string $profile, string $outcome, bool $effectsEnabled, string $fallback): string
    {
        if (!$effectsEnabled && $profile !== 'none') {
            return $profile === 'liquid_glass' ? 'paper' : $profile;
        }
        if ($profile === 'none') {
            return 'none';
        }
        if ($outcome === 'available') {
            return $profile;
        }
        if ($outcome === 'degraded') {
            return $profile;
        }
        return in_array($fallback, ['paper', 'none'], true) ? $fallback : 'paper';
    }

    /**
     * @param array<string,mixed> $state
     * @param callable(string):string $escape
     */
    private static function previewCanvasHtml(array $state, callable $escape): string
    {
        $rendered = (string)($state['rendered_profile'] ?? 'paper');
        $selected = (string)($state['preview_effect_profile'] ?? 'paper');
        $readiness = (string)($state['profile_readiness'] ?? 'not_configured');
        $fallback = (string)($state['fallback_status'] ?? 'ready');
        $motion = (string)($state['motion_behavior'] ?? 'system');

        return '<main class="se-preview-doc">'
            . '<section class="se-preview-hero">'
            . '<div><span class="se-preview-chip">Preview only</span><h1>Special Effects Preview</h1>'
            . '<p>This isolated document uses the runtime theme stylesheet and does not change active application preferences.</p></div>'
            . '<dl><div><dt>Selected</dt><dd>' . $escape($selected) . '</dd></div><div><dt>Rendered</dt><dd>' . $escape($rendered) . '</dd></div><div><dt>Readiness</dt><dd>' . $escape($readiness) . '</dd></div><div><dt>Fallback</dt><dd>' . $escape($fallback) . '</dd></div></dl>'
            . '</section>'
            . '<section class="se-preview-shell">'
            . '<aside><strong>Workspace</strong><span>Overview</span><span>Controls</span><span>Review</span></aside>'
            . '<div class="se-preview-main">'
            . '<header><div><strong>Application Surface</strong><span>Static sample content</span></div><button type="button" class="se-preview-focus">Focus sample</button></header>'
            . '<section class="se-preview-grid">'
            . '<article class="se-preview-card"><h2>Readable surface</h2><p>Body text remains legible with the selected profile or documented fallback.</p><div class="se-preview-actions"><button type="button" class="se-btn se-btn-primary">Primary</button><button type="button" class="se-btn">Secondary</button><button type="button" class="se-btn" disabled>Unavailable</button></div></article>'
            . '<article class="se-preview-card"><h2>Form controls</h2><label>Reference input<input value="Sample value" readonly></label><label>Reference select<select><option>Ready</option><option>Review</option></select></label></article>'
            . '</section>'
            . '<section class="se-preview-statuses"><div class="info">Information text</div><div class="success">Success text</div><div class="warning">Warning text</div><div class="danger">Danger text</div></section>'
            . '<section class="se-preview-card"><h2>List and table row</h2><div class="se-preview-row"><span>Static row</span><strong>Readable</strong><span>Boundary visible</span></div></section>'
            . '<section class="se-preview-overlay"><div role="dialog" aria-label="Preview dialog"><strong>Overlay sample</strong><p>Overlay containment and fallback surface remain visible.</p><button type="button" class="se-btn se-btn-primary">Focusable action</button></div></section>'
            . '</div></section>'
            . '<section class="se-preview-accessibility"><div><strong>Focus visibility:</strong> preserved</div><div><strong>Motion behavior:</strong> ' . $escape($motion) . '</div><div><strong>Effects fallback:</strong> ' . $escape((string)($state['effects_fallback'] ?? 'ready')) . '</div><div><strong>Capability check:</strong> ' . $escape((string)($state['capability_evidence_status'] ?? '')) . '</div></section>'
            . '</main>';
    }

    private static function previewDocumentCss(): string
    {
        return <<<'CSS'
html,body{margin:0;min-height:100%;background:var(--style-shell-bg,var(--bg,#f6f7fb));color:var(--text,#172033);font-family:var(--font-sans,system-ui,sans-serif)}
*{box-sizing:border-box}*:focus-visible{outline:3px solid var(--accent,#2563eb);outline-offset:3px}.se-preview-doc{display:grid;gap:16px;padding:18px}.se-preview-hero{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.se-preview-hero h1,.se-preview-hero p{margin:0}.se-preview-hero>div{display:grid;gap:6px}.se-preview-chip{display:inline-flex;width:max-content;border:1px solid var(--style-border,#d8deea);border-radius:999px;padding:3px 8px;background:var(--style-subtle-bg,#eef2f7);font-weight:700;font-size:12px}.se-preview-hero dl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin:0;min-width:280px}.se-preview-hero dt{font-size:11px;text-transform:uppercase;color:var(--muted,#64748b);font-weight:700}.se-preview-hero dd{margin:2px 0 0;font-weight:750}.se-preview-hero dl>div,.se-preview-card,.se-preview-overlay>div{border:1px solid var(--style-border,#d8deea);border-radius:12px;background:var(--style-content-bg,#fff);box-shadow:var(--shadow-sm,0 2px 8px rgb(15 23 42 / 8%));padding:12px}.se-preview-shell{display:grid;grid-template-columns:170px minmax(0,1fr);border:1px solid var(--style-border,#d8deea);border-radius:14px;overflow:hidden;background:var(--style-content-bg,#fff)}.se-preview-shell aside{display:flex;flex-direction:column;gap:12px;padding:14px;background:var(--style-sidebar-bg,var(--style-subtle-bg,#eef2f7))}.se-preview-main{display:grid;gap:14px;padding:14px}.se-preview-main header{display:flex;justify-content:space-between;gap:12px;align-items:center}.se-preview-main header div{display:grid;gap:3px}.se-preview-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.se-preview-card{display:grid;gap:10px}.se-preview-card h2,.se-preview-card p{margin:0}.se-preview-card label{display:grid;gap:5px}.se-preview-card input,.se-preview-card select{border:1px solid var(--style-border,#d8deea);border-radius:8px;padding:8px;background:var(--style-content-bg,#fff);color:inherit}.se-preview-actions,.se-preview-statuses{display:flex;flex-wrap:wrap;gap:8px}.se-btn,.se-preview-focus{border:1px solid var(--style-border,#d8deea);border-radius:8px;padding:8px 10px;background:var(--style-content-bg,#fff);color:inherit;font-weight:700}.se-btn-primary{background:var(--accent,#2563eb);border-color:var(--accent,#2563eb);color:#fff}.se-btn:disabled{opacity:.55}.se-preview-statuses>div{border:1px solid var(--style-border,#d8deea);border-radius:10px;padding:10px;background:var(--style-subtle-bg,#eef2f7);font-weight:700}.se-preview-statuses .success{border-color:#22c55e}.se-preview-statuses .warning{border-color:#f59e0b}.se-preview-statuses .danger{border-color:#ef4444}.se-preview-row{display:grid;grid-template-columns:1fr auto auto;gap:10px;border-top:1px solid var(--style-border,#d8deea);padding-top:10px}.se-preview-overlay{display:grid;place-items:center;min-height:150px;border-radius:14px;background:color-mix(in srgb,var(--style-shell-bg,#e5e7eb) 70%,transparent)}.se-preview-overlay>div{max-width:360px;display:grid;gap:8px}.se-preview-accessibility{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}.se-preview-accessibility>div{border:1px solid var(--style-border,#d8deea);border-radius:10px;padding:10px;background:var(--style-content-bg,#fff)}html[data-se-motion="reduced"] *,html[data-se-motion="off"] *{transition:none!important;animation:none!important;transform:none!important}@media(max-width:760px){.se-preview-hero,.se-preview-shell{display:grid;grid-template-columns:1fr}.se-preview-hero dl,.se-preview-grid{grid-template-columns:1fr}.se-preview-shell aside{display:none}}
CSS;
    }

    private static function readCompiledThemeCss(): string
    {
        $css = @file_get_contents(self::COMPILED_THEME_ASSET);
        return is_string($css) ? $css : '';
    }

    private static function scannerRevisionMarker(): string
    {
        $path = APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Services/StyleComplianceScannerService.php';
        $hash = is_file($path) ? sha1((string)@file_get_contents($path)) : 'missing';
        return self::SCANNER_CONTRACT_VERSION . ':' . substr($hash, 0, 12);
    }

    /**
     * @param array<int,mixed> $tokens
     */
    private static function sourceRevisionFingerprint(array $tokens, string $root): string
    {
        $files = [];
        foreach ($tokens as $row) {
            if (!is_array($row)) {
                continue;
            }
            $file = (string)($row['file'] ?? '');
            if ($file !== '') {
                $files[$file] = true;
            }
        }
        $parts = [$root];
        foreach (array_keys($files) as $file) {
            $absolute = str_starts_with($file, '/') ? $file : APP_ROOT . '/' . $file;
            $parts[] = $file . ':' . (is_file($absolute) ? ((string)filesize($absolute) . ':' . (string)filemtime($absolute)) : 'missing');
        }
        sort($parts);
        return 'src-' . substr(sha1(implode('|', $parts)), 0, 16);
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function isSpecialEffectsHandoff(array $proposal): bool
    {
        return (string)($proposal['proposal_class'] ?? '') === 'future_tool_handoff'
            && (string)($proposal['migration_state'] ?? '') === StyleComplianceScannerService::MIGRATION_STATE_FUTURE_HANDOFF
            && (string)($proposal['governance_domain'] ?? '') === 'special_effect';
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function handoffStableKey(array $proposal, array $scanContext = []): string
    {
        return implode('|', [
            self::SCANNER_CONTRACT_VERSION,
            (string)($scanContext['scope'] ?? $proposal['source_scope'] ?? ''),
            (string)($scanContext['owner'] ?? $proposal['source_owner'] ?? ''),
            (string)($proposal['finding_id'] ?? $proposal['declaration_id'] ?? ''),
            (string)($proposal['file_path'] ?? $proposal['file'] ?? ''),
            (string)($proposal['line'] ?? ''),
            (string)($proposal['selector'] ?? ''),
            (string)($proposal['property'] ?? ''),
            (string)($proposal['current_value'] ?? ''),
        ]);
    }

    /**
     * @param array<string,mixed> $proposal
     * @return array<string,mixed>
     */
    private static function normalizeHandoff(array $proposal, array $scanContext): array
    {
        $property = strtolower((string)($proposal['property'] ?? ''));
        $value = strtolower((string)($proposal['current_value'] ?? ''));
        $selector = (string)($proposal['selector'] ?? '');
        $effectIntent = self::effectIntentFromProposal($proposal);
        $evidence = self::mappingEvidenceForDeclaration($property, $value, $selector, $effectIntent, (string)($proposal['review_reason_code'] ?? ''));
        $category = (string)$evidence['effect_category'];
        $stableKey = self::handoffStableKey($proposal, $scanContext);
        $findingId = (string)($proposal['finding_id'] ?? $proposal['declaration_id'] ?? $stableKey);
        return [
            'handoff_id' => 'se-handoff-' . substr(sha1($stableKey), 0, 16),
            'finding_id' => $findingId,
            'decision_id' => (string)($proposal['proposal_id'] ?? $findingId),
            'proposal_id' => (string)($proposal['proposal_id'] ?? $stableKey),
            'proposal_class' => (string)($proposal['proposal_class'] ?? ''),
            'migration_state' => (string)($proposal['migration_state'] ?? ''),
            'governance_domain' => (string)($proposal['governance_domain'] ?? ''),
            'scan_scope' => (string)($scanContext['scope'] ?? ''),
            'selected_owner' => (string)($scanContext['owner'] ?? ''),
            'source_scope' => (string)($proposal['source_scope'] ?? $scanContext['scope'] ?? ''),
            'source_owner' => (string)($proposal['source_owner'] ?? $scanContext['owner'] ?? ''),
            'scan_root' => (string)($scanContext['source_root'] ?? ''),
            'source_revision_fingerprint' => (string)($scanContext['source_revision_fingerprint'] ?? ''),
            'style_compliance_contract_version' => self::SCANNER_CONTRACT_VERSION,
            'scanner_revision_marker' => (string)($scanContext['scanner_revision_marker'] ?? self::scannerRevisionMarker()),
            'handoff_source_timestamp' => (string)($scanContext['handoff_source_timestamp'] ?? ''),
            'original_governance_domain' => (string)($proposal['governance_domain'] ?? ''),
            'original_migration_state' => (string)($proposal['migration_state'] ?? ''),
            'original_proposal_class' => (string)($proposal['proposal_class'] ?? ''),
            'original_effect_intent' => $effectIntent,
            'original_reason_code' => (string)($proposal['review_reason_code'] ?? 'future_effects_handoff'),
            'original_reason' => (string)($proposal['review_reason_code'] ?? $proposal['migration_reason'] ?? 'future_effects_handoff'),
            'file_path' => (string)($proposal['file_path'] ?? $proposal['file'] ?? ''),
            'file' => (string)($proposal['file_path'] ?? $proposal['file'] ?? ''),
            'line' => (int)($proposal['line'] ?? 0),
            'selector' => $selector,
            'property' => (string)($proposal['property'] ?? ''),
            'current_value' => (string)($proposal['current_value'] ?? ''),
            'effect_category' => $category,
            'confidence' => (string)($proposal['confidence'] ?? 'low'),
            'detection_confidence' => (string)($proposal['detection_confidence'] ?? $proposal['confidence'] ?? 'low'),
            'reason_code' => (string)($proposal['review_reason_code'] ?? 'future_effects_handoff'),
            'reason' => (string)($proposal['review_reason_code'] ?? $proposal['migration_reason'] ?? 'future_effects_handoff'),
            'fallback_requirement' => self::fallbackRequirement($category),
            'profile_relevance' => self::profileRelevance($category),
            'current_profile_relevance' => self::profileRelevance($category),
            'mapping_evidence' => $evidence,
        ];
    }

    private static function effectCategory(string $property, string $value, string $selector): string
    {
        return (string)self::mappingEvidenceForDeclaration($property, $value, $selector, '', '')['effect_category'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public static function mappingRuleRegistry(): array
    {
        return [
            [
                'mapping_rule_id' => 'se_rule_backdrop_blur_direct',
                'effect_category' => 'backdrop_blur',
                'required_property_patterns' => ['backdrop-filter'],
                'required_value_patterns' => ['blur('],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'blur', 'backdrop'],
                'forbidden_contexts' => ['functional_image_treatment'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Backdrop-filter blur maps directly to backdrop_blur with Style Compliance effect intent.',
            ],
            [
                'mapping_rule_id' => 'se_rule_filter_mask_blend_direct',
                'effect_category' => 'filter_mask_blend',
                'required_property_patterns' => ['filter', 'mask', 'mix-blend-mode', 'blend'],
                'required_value_patterns' => [],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'filter', 'mask', 'blend'],
                'forbidden_contexts' => ['disabled_content', 'functional_image_treatment'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Filter, mask, and blend properties remain filter_mask_blend unless original intent proves backdrop blur.',
            ],
            [
                'mapping_rule_id' => 'se_rule_decorative_opacity',
                'effect_category' => 'decorative_opacity',
                'required_property_patterns' => ['opacity'],
                'required_value_patterns' => [],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'decorative'],
                'forbidden_contexts' => ['disabled_state', 'focus_state', 'readability'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Opacity maps only when Style Compliance evidence supports decorative intent.',
            ],
            [
                'mapping_rule_id' => 'se_rule_motion_transition',
                'effect_category' => 'motion_transition',
                'required_property_patterns' => ['transition', 'animation'],
                'required_value_patterns' => [],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'motion'],
                'forbidden_contexts' => ['required_navigation_feedback'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Transition and animation records require reduced-motion handling.',
            ],
            [
                'mapping_rule_id' => 'se_rule_decorative_transform',
                'effect_category' => 'decorative_transform',
                'required_property_patterns' => ['transform'],
                'required_value_patterns' => [],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'decorative', 'hover'],
                'forbidden_contexts' => ['layout_centering', 'positioning'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Transform maps only when selector or original intent indicates decorative interaction, not layout positioning.',
            ],
            [
                'mapping_rule_id' => 'se_rule_decorative_glow_shadow',
                'effect_category' => 'decorative_glow_shadow',
                'required_property_patterns' => ['box-shadow', 'text-shadow', 'shadow'],
                'required_value_patterns' => ['glow'],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'decorative', 'glow'],
                'forbidden_contexts' => ['semantic_elevation_only'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Glow/shadow maps only with decorative evidence.',
            ],
            [
                'mapping_rule_id' => 'se_rule_decorative_gradient',
                'effect_category' => 'decorative_gradient',
                'required_property_patterns' => ['background'],
                'required_value_patterns' => ['gradient'],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'decorative'],
                'forbidden_contexts' => ['semantic_color_surface_only'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Gradient maps when it is a decorative surface effect with semantic fallback requirements.',
            ],
            [
                'mapping_rule_id' => 'se_rule_glass_translucency',
                'effect_category' => 'glass_translucency',
                'required_property_patterns' => ['background', 'border'],
                'required_value_patterns' => ['rgba', 'hsla', 'color-mix', 'transparent'],
                'required_effect_intent' => ['future_effects_handoff', 'special_effect_candidate', 'glass', 'translucent'],
                'forbidden_contexts' => ['readability'],
                'fallback_mapping_status' => 'review_required',
                'description' => 'Glass/translucency maps only with original effect intent and fallback need.',
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function mappingEvidenceForDeclaration(string $property, string $value, string $selector = '', string $effectIntent = '', string $reasonCode = ''): array
    {
        $property = strtolower(trim($property));
        $value = strtolower(trim($value));
        $selector = strtolower(trim($selector));
        $intent = strtolower(trim($effectIntent . ' ' . $reasonCode));
        $whyNot = [];
        $ruleId = 'se_rule_review_required_default';
        $category = 'review_required';
        $status = 'review_required';
        $why = 'No mapping rule had enough original Style Compliance evidence.';

        if (str_contains($property, 'backdrop-filter') && str_contains($value, 'blur(')) {
            $ruleId = 'se_rule_backdrop_blur_direct';
            $category = 'backdrop_blur';
            $status = 'mapped';
            $why = 'Direct backdrop-filter blur evidence from the original declaration.';
            $whyNot[] = 'filter_mask_blend_not_used: backdrop-filter has a dedicated backdrop blur rule.';
        } elseif ((str_contains($property, 'filter') || str_contains($property, 'mask') || str_contains($property, 'blend'))) {
            if (str_contains($property, 'filter') && str_contains($value, 'blur(') && preg_match('/backdrop|glass|overlay|future_effects_handoff|special_effect_candidate/', $intent . ' ' . $selector) === 1) {
                $ruleId = 'se_rule_backdrop_blur_direct';
                $category = 'backdrop_blur';
                $status = 'mapped';
                $why = 'Filter blur is treated as backdrop_blur because original effect intent establishes glass/backdrop behavior.';
            } elseif (preg_match('/disabled|grayscale|functional|image/', $intent . ' ' . $selector . ' ' . $value) === 1 && !str_contains($property, 'blend')) {
                $ruleId = 'se_rule_filter_mask_blend_direct';
                $why = 'Generic filter evidence is insufficient because disabled or functional image treatment may not be decorative.';
                $whyNot[] = 'backdrop_blur_not_used: no original backdrop/glass intent.';
            } else {
                $ruleId = 'se_rule_filter_mask_blend_direct';
                $category = 'filter_mask_blend';
                $status = 'mapped';
                $why = 'Direct filter, mask, or blend property evidence.';
                $whyNot[] = 'backdrop_blur_not_used: property is not backdrop-filter and no backdrop-specific intent was required.';
            }
        } elseif (str_contains($property, 'opacity')) {
            $ruleId = 'se_rule_decorative_opacity';
            if (preg_match('/disabled|aria-disabled|is-disabled|readonly|read-only/', $selector . ' ' . $intent) === 1) {
                $why = 'Disabled-state opacity is not automatically decorative.';
                $whyNot[] = 'decorative_opacity_not_used: disabled context is forbidden.';
            } elseif (preg_match('/decorative|future_effects_handoff|special_effect_candidate/', $intent) === 1) {
                $category = 'decorative_opacity';
                $status = 'mapped';
                $why = 'Opacity has original decorative effect intent.';
            }
        } elseif (str_contains($property, 'transition') || str_contains($property, 'animation')) {
            $ruleId = 'se_rule_motion_transition';
            $category = 'motion_transition';
            $status = 'mapped';
            $why = 'Transition or animation property requires motion governance.';
        } elseif (str_contains($property, 'transform')) {
            $ruleId = 'se_rule_decorative_transform';
            if (preg_match('/translate\\(|translatex\\(-?50%\\)|translatey\\(-?50%\\)|center|layout|position/', $value . ' ' . $selector . ' ' . $intent) === 1
                && preg_match('/hover|decorative|future_effects_handoff|special_effect_candidate/', $selector . ' ' . $intent) !== 1) {
                $why = 'Transform appears to be layout positioning or centering, so it needs review.';
                $whyNot[] = 'decorative_transform_not_used: layout transform context is forbidden.';
            } elseif (preg_match('/hover|interactive|button|card|tile|quicklink|menu|decorative|future_effects_handoff|special_effect_candidate/', $selector . ' ' . $intent) === 1) {
                $category = 'decorative_transform';
                $status = 'mapped';
                $why = 'Transform has decorative or interaction evidence.';
            }
        } elseif (str_contains($property, 'shadow') || str_contains($value, 'glow')) {
            $ruleId = 'se_rule_decorative_glow_shadow';
            if (preg_match('/glow|decorative|future_effects_handoff|special_effect_candidate/', $value . ' ' . $intent) === 1) {
                $category = 'decorative_glow_shadow';
                $status = 'mapped';
                $why = 'Shadow/glow has decorative evidence.';
            } else {
                $why = 'Shadow may be semantic elevation, so decorative glow mapping requires review.';
                $whyNot[] = 'decorative_glow_shadow_not_used: semantic elevation cannot be assumed.';
            }
        } elseif (str_contains($value, 'gradient')) {
            $ruleId = 'se_rule_decorative_gradient';
            $category = 'decorative_gradient';
            $status = 'mapped';
            $why = 'Gradient value indicates decorative surface effect.';
        } elseif ((str_contains($property, 'background') || str_contains($property, 'border')) && preg_match('/rgba|hsla|color-mix|transparent|glass|alpha|\/[[:space:]]*[0-9.]+%?/', $value . ' ' . $intent) === 1) {
            $ruleId = 'se_rule_glass_translucency';
            $category = 'glass_translucency';
            $status = 'mapped';
            $why = 'Translucent surface value has glass/translucency evidence.';
        }

        if ($whyNot === []) {
            $whyNot[] = 'Other categories require stronger property, value, or original intent evidence.';
        }

        return [
            'effect_category' => $category,
            'mapping_status' => $status,
            'original_property' => $property,
            'original_value' => $value,
            'original_effect_intent' => trim($effectIntent),
            'original_reason_code' => trim($reasonCode),
            'mapping_rule_id' => $ruleId,
            'why_this_category_applies' => $why,
            'why_other_categories_do_not_apply' => $whyNot,
        ];
    }

    /**
     * @param array<string,mixed> $proposal
     */
    private static function effectIntentFromProposal(array $proposal): string
    {
        $parts = [
            (string)($proposal['review_reason_code'] ?? ''),
            (string)($proposal['migration_reason'] ?? ''),
            (string)($proposal['repair_lane'] ?? ''),
            (string)($proposal['target_tool'] ?? ''),
        ];
        $blockedBy = isset($proposal['blocked_by']) && is_array($proposal['blocked_by']) ? $proposal['blocked_by'] : [];
        foreach ($blockedBy as $blocked) {
            $parts[] = (string)$blocked;
        }
        return trim(implode(' ', array_filter($parts, static fn(string $v): bool => $v !== '')));
    }

    private static function fallbackRequirement(string $category): string
    {
        return match ($category) {
            'backdrop_blur' => 'opaque_or_semantic_surface_when_backdrop_filter_unavailable',
            'motion_transition', 'decorative_transform' => 'remove_or_minimize_under_reduced_motion',
            'decorative_opacity' => 'do_not_reduce_text_or_focus_contrast',
            'filter_mask_blend' => 'semantic_surface_without_filter_mask_or_blend',
            'decorative_glow_shadow' => 'shadow_glow_must_be_decorative_only',
            'decorative_gradient', 'glass_translucency' => 'semantic_color_surface_fallback_required',
            'review_required' => 'manual_effect_profile_mapping_required',
            default => 'paper_or_none_profile_fallback_required',
        };
    }

    private static function profileRelevance(string $category): string
    {
        return match ($category) {
            'backdrop_blur', 'decorative_glow_shadow', 'decorative_gradient', 'glass_translucency' => 'liquid_glass',
            'motion_transition', 'decorative_transform', 'filter_mask_blend' => 'liquid_glass_with_reduced_motion_constraints',
            'decorative_opacity' => 'paper_and_liquid_glass_review',
            default => 'profile_mapping_required',
        };
    }

    private static function isSupportedHandoffSource(string $filePath): bool
    {
        $path = strtolower(str_replace('\\', '/', $filePath));
        if ($path === '') {
            return false;
        }
        foreach (['/tests/', '/test/', '/fixtures/', '/vendor/', '/node_modules/', '/storage/', '/public/assets/', '.min.css', '.compiled.css'] as $blocked) {
            if (str_contains($path, $blocked)) {
                return false;
            }
        }
        return str_starts_with($path, 'apps/') || str_starts_with($path, 'public/');
    }

    /**
     * @param array<string,array<string,mixed>> $readiness
     * @param array<string,mixed> $handoffs
     * @return array<string,mixed>
     */
    private static function runtimeReadiness(array $runtimeContract, array $readiness, array $handoffs): array
    {
        $palette = (string)($runtimeContract['palette_mode'] ?? 'system');
        $profile = (string)($runtimeContract['effect_profile'] ?? 'paper');
        $combined = (string)($runtimeContract['effective_visual_mode'] ?? ($palette . '-' . self::styleKeyFromEffectProfile($profile)));
        $activeReadiness = isset($readiness[$profile]) && is_array($readiness[$profile]) ? $readiness[$profile] : [];

        return [
            'combined_mode' => ucwords(str_replace('-', ' ', $combined)),
            'derived_palette' => $palette,
            'derived_effect_profile' => $profile,
            'effects_enabled' => !empty($runtimeContract['effects_enabled']),
            'motion_mode' => (string)($runtimeContract['motion_mode'] ?? 'system'),
            'resolver_source' => 'derived_from_combined_theme_preference_read_only',
            'profile_readiness' => (string)($activeReadiness['outcome'] ?? 'not_configured'),
            'handoff_reconciliation_status' => (string)($handoffs['reconciliation']['category_mapping_status'] ?? 'unknown'),
        ];
    }
}
