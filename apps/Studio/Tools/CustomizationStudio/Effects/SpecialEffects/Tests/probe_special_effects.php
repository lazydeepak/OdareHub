<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 7));
}

require_once APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php';

use Apps\Studio\Tools\CustomizationStudio\Effects\SpecialEffects\Application\SpecialEffectsRegistryService;

$checks = 0;
$failed = 0;

function assertProbe(bool $condition, string $message): void
{
    global $checks, $failed;
    $checks++;
    if ($condition) {
        echo "  ok: {$message}\n";
        return;
    }
    $failed++;
    echo "  fail: {$message}\n";
}

echo "Special Effects probe\n";

$systemGlass = SpecialEffectsRegistryService::resolveCompatibility('system-liquid-glass');
assertProbe(($systemGlass['palette_mode'] ?? '') === 'system' && ($systemGlass['effect_profile'] ?? '') === 'liquid_glass', 'combined choice resolves separate palette/effect');
assertProbe(($systemGlass['combined_preference'] ?? '') === 'system-liquid-glass' && ($systemGlass['effective_visual_mode'] ?? '') === 'system-liquid-glass', 'system-liquid-glass remains backward-compatible');

$darkPaper = SpecialEffectsRegistryService::resolveCompatibility('dark-paper');
assertProbe(($darkPaper['palette_mode'] ?? '') === 'dark' && ($darkPaper['effect_profile'] ?? '') === 'paper', 'dark-paper resolves to dark palette and paper effect');

$disabled = SpecialEffectsRegistryService::resolveCompatibility('dark-liquid-glass', false);
assertProbe(($disabled['configured_effect_profile'] ?? '') === 'liquid_glass' && ($disabled['effect_profile'] ?? '') === 'paper' && ($disabled['effects_disabled_fallback'] ?? '') === 'paper', 'effects-disabled resolves paper/no-effects fallback');
assertProbe(($disabled['effective_visual_mode'] ?? '') === 'dark-paper', 'disabled effects produce compatible hyphenated visual mode');

$profiles = SpecialEffectsRegistryService::effectProfiles();
$liquid = $profiles['liquid_glass'] ?? [];
assertProbe(in_array('[data-color-style="liquid-glass"]', (array)($liquid['required_selectors'] ?? []), true), 'Liquid Glass requires selector/evidence contract');

$missingReadiness = SpecialEffectsRegistryService::readinessForProfiles($profiles, $systemGlass, ':root{--surface-card:#fff;}');
assertProbe(in_array((string)($missingReadiness['liquid_glass']['outcome'] ?? ''), ['blocked', 'degraded'], true), 'missing selector causes blocked/degraded readiness');

$reduced = SpecialEffectsRegistryService::resolveCompatibility('system-liquid-glass', true, 'reduced');
$reducedReadiness = SpecialEffectsRegistryService::readinessForProfiles($profiles, $reduced, '[data-color-style="liquid-glass"]{--glass:a;--glass-strong:b;--glass-border:c;--glass-shadow:d;}[data-color-style="paper"]{--surface-card:a;--surface-muted:b;--border-soft:c;--shadow-sm:d;}');
assertProbe(in_array('reduced_motion_requires_transition_transform_minimization', (array)($reducedReadiness['liquid_glass']['dependencies'] ?? []), true), 'reduced motion requires motion minimization behavior');

$model = SpecialEffectsRegistryService::buildWorkspaceModel('system-liquid-glass', true, 'system', 'owner', 'Studio');
$handoffs = $model['handoffs']['rows'] ?? [];
assertProbe(is_array($handoffs) && count($handoffs) >= 1, 'Style Compliance future handoff appears in Special Effects queue');

$reconciliation = $model['handoff_reconciliation'] ?? [];
assertProbe(is_array($reconciliation)
    && array_key_exists('available', $reconciliation)
    && array_key_exists('displayed', $reconciliation)
    && array_key_exists('excluded', $reconciliation)
    && array_key_exists('unmapped', $reconciliation)
    && array_key_exists('blocked_by_missing_profile_mapping', $reconciliation)
    && array_key_exists('source_context_used', $reconciliation), 'handoff reconciliation exposes V1.1 counts and source context');
assertProbe((int)($reconciliation['available'] ?? 0) >= (int)($reconciliation['displayed'] ?? 0), 'available handoff count is never below displayed count');
assertProbe((string)($reconciliation['source_context_used'] ?? '') === 'single_normalized_style_compliance_context', 'handoff reconciliation uses one normalized Style Compliance source context');

$parity = $model['handoff_parity'] ?? [];
assertProbe(is_array($parity)
    && (int)($parity['style_compliance_available'] ?? -1) === (int)($parity['special_effects_displayed'] ?? -2)
    && (int)($parity['missing_in_special_effects'] ?? -1) === 0
    && (int)($parity['unexpected_in_special_effects'] ?? -1) === 0
    && (string)($parity['parity_status'] ?? '') === 'exact', 'equivalent scope/owner context produces exact handoff parity');

$scanContext = $model['scan_context'] ?? [];
assertProbe(($scanContext['scope'] ?? '') === 'owner'
    && ($scanContext['owner'] ?? '') === 'Studio'
    && ($scanContext['source_root'] ?? '') !== ''
    && ($scanContext['scanner_contract_version'] ?? '') === 'style_compliance_handoff_contract_v1_2'
    && ($scanContext['source_revision_fingerprint'] ?? '') !== '', 'scan context exposes scope, owner, root, contract, and source fingerprint');

$runtimeReadiness = $model['runtime_readiness'] ?? [];
assertProbe(($runtimeReadiness['combined_mode'] ?? '') === 'System Liquid Glass'
    && ($runtimeReadiness['derived_palette'] ?? '') === 'system'
    && ($runtimeReadiness['derived_effect_profile'] ?? '') === 'liquid_glass'
    && ($runtimeReadiness['resolver_source'] ?? '') === 'derived_from_combined_theme_preference_read_only', 'runtime readiness exposes combined mode, derived palette/profile, and resolver source');

$badClass = false;
$missingAdapterField = false;
$unstableId = false;
foreach ((array)$handoffs as $row) {
    if (!is_array($row)) {
        continue;
    }
    if ((string)($row['proposal_class'] ?? '') !== 'future_tool_handoff'
        || (string)($row['migration_state'] ?? '') !== 'future_handoff'
        || (string)($row['governance_domain'] ?? '') !== 'special_effect') {
        $badClass = true;
    }
    foreach ([
        'handoff_id',
        'finding_id',
        'decision_id',
        'scan_scope',
        'selected_owner',
        'source_scope',
        'source_owner',
        'scan_root',
        'source_revision_fingerprint',
        'style_compliance_contract_version',
        'scanner_revision_marker',
        'handoff_source_timestamp',
        'original_governance_domain',
        'original_migration_state',
        'original_proposal_class',
        'original_effect_intent',
        'original_reason_code',
        'original_reason',
        'file_path',
        'line',
        'selector',
        'property',
        'current_value',
        'effect_category',
        'detection_confidence',
        'reason_code',
        'reason',
        'fallback_requirement',
        'profile_relevance',
        'mapping_evidence',
    ] as $field) {
        if (!array_key_exists($field, $row)) {
            $missingAdapterField = true;
        }
    }
    if (!str_starts_with((string)($row['handoff_id'] ?? ''), 'se-handoff-')) {
        $unstableId = true;
    }
}
assertProbe(!$badClass, 'deterministic fixes do not appear in handoff queue');
assertProbe(!$badClass, 'manual semantic decisions do not appear in handoff queue');
assertProbe(!$missingAdapterField, 'handoff rows expose normalized Special Effects V1.1 adapter fields');
assertProbe(!$unstableId, 'handoff rows use stable Special Effects handoff IDs');

$modelRepeat = SpecialEffectsRegistryService::buildWorkspaceModel('system-liquid-glass', true, 'system', 'owner', 'Studio');
$repeatHandoff = $modelRepeat['handoffs']['rows'][0]['handoff_id'] ?? '';
assertProbe($repeatHandoff !== '' && $repeatHandoff === ($handoffs[0]['handoff_id'] ?? ''), 'handoff IDs are stable for identical normalized inputs');

assertProbe(SpecialEffectsRegistryService::scanContextDifferenceReason(['scope' => 'owner'], ['scope' => 'shell']) === 'scope_mismatch', 'scope mismatch is reported as scope_mismatch');
assertProbe(SpecialEffectsRegistryService::scanContextDifferenceReason(['scope' => 'owner', 'owner' => 'Studio'], ['scope' => 'owner', 'owner' => 'Manufacturing']) === 'owner_mismatch', 'owner mismatch is reported as owner_mismatch');
assertProbe(SpecialEffectsRegistryService::scanContextDifferenceReason(['scope' => 'owner', 'owner' => 'Studio', 'source_revision_fingerprint' => 'a'], ['scope' => 'owner', 'owner' => 'Studio', 'source_revision_fingerprint' => 'b']) === 'source_revision_mismatch', 'source revision mismatch is reported as source_revision_mismatch');
assertProbe(SpecialEffectsRegistryService::scanContextDifferenceReason(['scope' => 'owner', 'owner' => 'Studio', 'scanner_contract_version' => 'a'], ['scope' => 'owner', 'owner' => 'Studio', 'scanner_contract_version' => 'b']) === 'scanner_contract_mismatch', 'scanner contract mismatch is reported as scanner_contract_mismatch');

$directBackdrop = SpecialEffectsRegistryService::mappingEvidenceForDeclaration('backdrop-filter', 'blur(16px)', '.glass-panel', 'future_effects_handoff', 'future_effects_handoff');
assertProbe(($directBackdrop['effect_category'] ?? '') === 'backdrop_blur' && ($directBackdrop['mapping_status'] ?? '') === 'mapped', 'direct backdrop-filter blur maps to backdrop_blur');

$genericFilter = SpecialEffectsRegistryService::mappingEvidenceForDeclaration('filter', 'grayscale(1)', '.is-disabled', '', '');
assertProbe(($genericFilter['effect_category'] ?? '') === 'review_required', 'generic filter does not map to backdrop_blur without original effect intent');

$layoutTransform = SpecialEffectsRegistryService::mappingEvidenceForDeclaration('transform', 'translateX(-50%)', '.centered-panel', '', '');
assertProbe(($layoutTransform['effect_category'] ?? '') === 'review_required', 'layout transform does not map to decorative_transform');

$disabledOpacity = SpecialEffectsRegistryService::mappingEvidenceForDeclaration('opacity', '.5', '.btn.is-disabled', 'disabled', '');
assertProbe(($disabledOpacity['effect_category'] ?? '') === 'review_required', 'disabled-state opacity does not map to decorative_opacity');

$unknownMapping = SpecialEffectsRegistryService::mappingEvidenceForDeclaration('object-fit', 'cover', '.avatar', '', '');
assertProbe(($unknownMapping['effect_category'] ?? '') === 'review_required', 'missing mapping produces review_required instead of silent exclusion');

$rules = SpecialEffectsRegistryService::mappingRuleRegistry();
$ruleText = json_encode($rules);
assertProbe(is_string($ruleText) && !preg_match('/write|apply|save|mutation|post/i', $ruleText), 'mapping rules expose no write/apply/save/mutation operation');

$safety = $model['safety_contract'] ?? [];
assertProbe(($safety['mutation_endpoint'] ?? 'x') === '' && empty($safety['write_enabled']), 'no apply/write endpoint exists in Special Effects contract');
assertProbe(empty($model['runtime_contract']['preference_write_enabled']) && empty($safety['persistent_settings_enabled']), 'no persistent setting/write operation is enabled');

$readiness = $model['readiness']['liquid_glass'] ?? [];
assertProbe(isset($readiness['dependencies'], $readiness['fallback_profile']) && (string)$readiness['fallback_profile'] === 'paper', 'profile readiness shows dependencies and fallback');
assertProbe(in_array('dark-paper', (array)($model['runtime_contract']['backward_compatible_choices'] ?? []), true), 'combined selection compatibility list includes legacy choices');

$routes = @file_get_contents(APP_ROOT . '/apps/Studio/routes.php') ?: '';
assertProbe(str_contains($routes, "\$router->get('/apps/studio/tools/customization-studio/effects'") && !str_contains($routes, "\$router->post('/apps/studio/tools/customization-studio/effects"), 'Special Effects route is GET-only');
assertProbe(str_contains($routes, "\$router->get('/apps/studio/tools/customization-studio/effects/preview'") && !str_contains($routes, "\$router->post('/apps/studio/tools/customization-studio/effects/preview"), 'Preview route is GET-only with no preview POST route');

$active = SpecialEffectsRegistryService::resolveCompatibility('system-liquid-glass', true, 'system');
$invalidPreview = SpecialEffectsRegistryService::previewStateFromQuery([
    'preview_palette_mode' => '../../dark',
    'preview_effect_profile' => 'script',
    'preview_effects_enabled' => 'wat',
    'preview_motion_mode' => 'spin',
    'preview_source' => 'filesystem',
], $active);
assertProbe(($invalidPreview['preview_palette_mode'] ?? '') === 'system'
    && ($invalidPreview['preview_effect_profile'] ?? '') === 'liquid_glass'
    && ($invalidPreview['preview_motion_mode'] ?? '') === 'system'
    && ($invalidPreview['preview_source'] ?? '') === 'active_mode', 'invalid preview query values resolve to active/fallback allowlisted values');
assertProbe(($invalidPreview['active_combined_mode'] ?? '') === 'system-liquid-glass', 'preview state never changes active combined mode');

$liquidPreview = SpecialEffectsRegistryService::previewStateFromQuery([
    'preview_palette_mode' => 'light',
    'preview_effect_profile' => 'liquid_glass',
    'preview_effects_enabled' => '1',
    'preview_motion_mode' => 'system',
], $active);
assertProbe(($liquidPreview['preview_effect_profile'] ?? '') === 'liquid_glass'
    && in_array((string)($liquidPreview['profile_readiness'] ?? ''), ['available', 'degraded'], true)
    && ($liquidPreview['rendered_profile'] ?? '') === 'liquid_glass', 'Liquid Glass available/degraded state renders selected preview contract');

$blockedLiquid = SpecialEffectsRegistryService::previewStateFromQuery([
    'preview_effect_profile' => 'liquid_glass',
    'preview_effects_enabled' => '1',
], $active, null, '');
assertProbe(($blockedLiquid['profile_readiness'] ?? '') === 'blocked'
    && in_array((string)($blockedLiquid['rendered_profile'] ?? ''), ['paper', 'none'], true), 'blocked Liquid Glass renders fallback only');

$effectsOff = SpecialEffectsRegistryService::previewStateFromQuery([
    'preview_effect_profile' => 'liquid_glass',
    'preview_effects_enabled' => '0',
], $active);
assertProbe(($effectsOff['rendered_profile'] ?? '') === 'paper'
    && ($effectsOff['effects_disabled_fallback'] ?? '') === 'paper', 'effects-off preview resolves to Paper fallback');

$reducedPreview = SpecialEffectsRegistryService::previewStateFromQuery(['preview_motion_mode' => 'reduced'], $active);
$offPreview = SpecialEffectsRegistryService::previewStateFromQuery(['preview_motion_mode' => 'off'], $active);
assertProbe(($reducedPreview['motion_behavior'] ?? '') === 'reduced' && ($offPreview['motion_behavior'] ?? '') === 'off', 'reduced and off motion are modeled independently');

$nonePreview = SpecialEffectsRegistryService::previewStateFromQuery(['preview_effect_profile' => 'none'], $active);
assertProbe(($nonePreview['preview_effect_profile'] ?? '') === 'none'
    && ($nonePreview['rendered_profile'] ?? '') === 'none', 'None profile is valid and non-error');
assertProbe(($nonePreview['focus_visibility'] ?? '') === 'preserved', 'focus-visible remains preserved in preview state');

$previewHtml = SpecialEffectsRegistryService::renderPreviewDocument([
    'preview_palette_mode' => 'dark',
    'preview_effect_profile' => 'paper',
    'preview_effects_enabled' => '1',
    'preview_motion_mode' => 'off',
]);
assertProbe(str_contains($previewHtml, '/assets/theme.css')
    && str_contains($previewHtml, 'data-theme=')
    && str_contains($previewHtml, 'data-color-style=')
    && str_contains($previewHtml, 'data-se-preview="isolated"'), 'preview uses existing runtime theme contract in an isolated document');
assertProbe(str_contains($previewHtml, 'data-se-motion="off"')
    && str_contains($previewHtml, 'transition:none!important')
    && str_contains($previewHtml, 'animation:none!important'), 'motion off suppresses decorative motion contract');
$previewHtmlWithoutStyle = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $previewHtml) ?? $previewHtml;
assertProbe(!preg_match('/<form|method=["\']post|>[^<]*(Apply|Save|Enable|Disable)[^<]*</i', $previewHtmlWithoutStyle), 'preview document renders no Apply/Save/Enable/Disable or mutation controls');

$modelBeforePreview = SpecialEffectsRegistryService::buildWorkspaceModel('system-liquid-glass', true, 'system', 'owner', 'Studio');
SpecialEffectsRegistryService::previewStateFromQuery(['preview_effect_profile' => 'none'], $active);
$modelAfterPreview = SpecialEffectsRegistryService::buildWorkspaceModel('system-liquid-glass', true, 'system', 'owner', 'Studio');
assertProbe(($modelBeforePreview['handoff_parity']['matching_handoff_ids'] ?? null) === ($modelAfterPreview['handoff_parity']['matching_handoff_ids'] ?? null), 'preview state does not alter Style Compliance handoffs or proposals');

$futureContract = SpecialEffectsRegistryService::futureSettingsContract();
$futureSystem = SpecialEffectsRegistryService::resolveFutureEffectState($futureContract);
assertProbe(($futureSystem['palette_mode'] ?? '') === 'system'
    && ($futureSystem['effect_profile'] ?? '') === 'paper'
    && !empty($futureSystem['effects_enabled'])
    && ($futureSystem['motion_mode'] ?? '') === 'system', 'future resolver applies organization defaults after system defaults');

$orgDark = $futureContract;
$orgDark['organization_effect_policy']['default_palette_mode'] = 'dark';
$orgResolved = SpecialEffectsRegistryService::resolveFutureEffectState($orgDark);
assertProbe(($orgResolved['palette_mode'] ?? '') === 'dark'
    && ($orgResolved['resolution_source'] ?? '') === 'organization', 'organization policy can override system default in read-only resolver');

$userGlass = $futureContract;
$userGlass['user_effect_preference']['effect_profile'] = 'liquid_glass';
$userResolved = SpecialEffectsRegistryService::resolveFutureEffectState($userGlass);
assertProbe(($userResolved['effect_profile'] ?? '') === 'liquid_glass'
    && ($userResolved['resolution_source'] ?? '') === 'user', 'permitted user preference applies only when organization allows override');

$noUserOverride = $userGlass;
$noUserOverride['organization_effect_policy']['allow_user_override'] = false;
$noUserResolved = SpecialEffectsRegistryService::resolveFutureEffectState($noUserOverride);
assertProbe(($noUserResolved['effect_profile'] ?? '') === 'paper'
    && ($noUserResolved['resolution_source'] ?? '') === 'organization', 'user preference is ignored when organization disallows override');

$forcedPaper = $userGlass;
$forcedPaper['organization_effect_policy']['forced_profile'] = 'paper';
$forcedResolved = SpecialEffectsRegistryService::resolveFutureEffectState($forcedPaper);
assertProbe(($forcedResolved['effect_profile'] ?? '') === 'paper'
    && !empty($forcedResolved['locked_by_policy']), 'organization forced profile overrides user effect profile');

$effectsDisallowed = $futureContract;
$effectsDisallowed['organization_effect_policy']['effects_allowed'] = false;
$effectsDisallowed['user_effect_preference']['effects_enabled'] = 'enabled';
$effectsDisallowedResolved = SpecialEffectsRegistryService::resolveFutureEffectState($effectsDisallowed);
assertProbe(empty($effectsDisallowedResolved['effects_enabled'])
    && !empty($effectsDisallowedResolved['locked_by_policy'])
    && ($effectsDisallowedResolved['fallback_reason'] ?? '') === 'organization_effects_disabled', 'organization effects disallow policy forces disabled effects');

$reducedRequired = $futureContract;
$reducedRequired['user_effect_preference']['motion_mode'] = 'system';
$reducedRequired['runtime_constraints']['reduced_motion_required'] = true;
$reducedRequiredResolved = SpecialEffectsRegistryService::resolveFutureEffectState($reducedRequired);
assertProbe(($reducedRequiredResolved['motion_mode'] ?? '') === 'reduced'
    && ($reducedRequiredResolved['resolution_source'] ?? '') === 'accessibility', 'reduced motion requirement overrides requested motion');

$blockedGlass = $userGlass;
$blockedGlass['runtime_constraints']['profile_readiness'] = 'blocked';
$blockedGlass['runtime_constraints']['paper_viable'] = true;
$blockedGlassResolved = SpecialEffectsRegistryService::resolveFutureEffectState($blockedGlass);
assertProbe(($blockedGlassResolved['effect_profile'] ?? '') === 'paper'
    && ($blockedGlassResolved['fallback_reason'] ?? '') === 'liquid_glass_blocked', 'blocked Liquid Glass resolves to Paper when Paper is viable');

$blockedNoPaper = $blockedGlass;
$blockedNoPaper['runtime_constraints']['paper_viable'] = false;
$blockedNoPaperResolved = SpecialEffectsRegistryService::resolveFutureEffectState($blockedNoPaper);
assertProbe(($blockedNoPaperResolved['effect_profile'] ?? '') === 'none', 'blocked Liquid Glass resolves to None when Paper is not viable');

$unhealthyRuntime = $futureContract;
$unhealthyRuntime['runtime_constraints']['theme_runtime_healthy'] = false;
$unhealthyRuntimeResolved = SpecialEffectsRegistryService::resolveFutureEffectState($unhealthyRuntime);
assertProbe(!empty($unhealthyRuntimeResolved['fallback_applied'])
    && in_array('runtime_readiness', array_column((array)($unhealthyRuntimeResolved['resolution_trace'] ?? []), 'step'), true), 'unhealthy theme runtime is recorded before fully healthy resolution');

$unknownCapability = $futureContract;
$unknownCapability['runtime_constraints']['capability_state'] = 'unknown';
$unknownCapabilityResolved = SpecialEffectsRegistryService::resolveFutureEffectState($unknownCapability);
assertProbe(in_array('capability_fallback', array_column((array)($unknownCapabilityResolved['resolution_trace'] ?? []), 'step'), true)
    && ($unknownCapabilityResolved['fallback_reason'] ?? null) !== 'capability_unsupported', 'unknown capability is modeled as deferred evidence, not false unavailability');

$unsupportedCapability = $userGlass;
$unsupportedCapability['runtime_constraints']['capability_state'] = 'unsupported';
$unsupportedCapabilityResolved = SpecialEffectsRegistryService::resolveFutureEffectState($unsupportedCapability);
assertProbe(($unsupportedCapabilityResolved['effect_profile'] ?? '') === 'paper'
    && ($unsupportedCapabilityResolved['fallback_reason'] ?? '') === 'capability_unsupported', 'unsupported capability falls back from Liquid Glass');

$traceSteps = array_column((array)($userResolved['resolution_trace'] ?? []), 'step');
foreach (['system_defaults', 'organization_policy', 'user_preference', 'capability_fallback', 'resolved_runtime_state'] as $requiredStep) {
    assertProbe(in_array($requiredStep, $traceSteps, true), "future resolver trace includes {$requiredStep}");
}
assertProbe(is_string($userResolved['human_summary'] ?? null) && ($userResolved['human_summary'] ?? '') !== '', 'future resolver returns human-readable evidence');

$futureModel = $model['future_effect_controls'] ?? [];
$plans = is_array($futureModel) ? (array)($futureModel['control_plans'] ?? []) : [];
$plansRepeat = SpecialEffectsRegistryService::futureControlPlans($futureContract, $futureSystem, [], SpecialEffectsRegistryService::futureScopeAuthorityContract());
$planIds = array_map(static fn($row) => is_array($row) ? (string)($row['control_plan_id'] ?? '') : '', $plansRepeat);
$planIdsAgain = array_map(static fn($row) => is_array($row) ? (string)($row['control_plan_id'] ?? '') : '', SpecialEffectsRegistryService::futureControlPlans($futureContract, $futureSystem, [], SpecialEffectsRegistryService::futureScopeAuthorityContract()));
assertProbe(count($plans) === 4, 'workspace exposes four future persistent effect control-plan rows');
assertProbe($planIds !== [] && $planIds === $planIdsAgain && count($planIds) === count(array_unique($planIds)), 'future control-plan IDs are stable and unique');

$plansHaveMutation = false;
foreach ($plans as $plan) {
    if (!is_array($plan)) {
        continue;
    }
    foreach (['mutation_url', 'form_action', 'api_command', 'write_instruction'] as $mutationField) {
        if ((string)($plan[$mutationField] ?? '') !== '') {
            $plansHaveMutation = true;
        }
    }
}
assertProbe(!$plansHaveMutation, 'future control plans contain no mutation URL, form action, API command, or write instruction');

$futureAuthority = SpecialEffectsRegistryService::futureScopeAuthorityContract();
assertProbe(count($futureAuthority) === 4
    && in_array('Preview-only local state', array_column($futureAuthority, 'scope_owner'), true)
    && in_array('Organization default', array_column($futureAuthority, 'scope_owner'), true), 'future authority contract separates organization, user, system, and preview-only scopes');

$previewReference = is_array($futureModel) ? (array)($futureModel['preview_simulation_reference'] ?? []) : [];
assertProbe(($previewReference['preview_contract_status'] ?? '') === 'non_persistent_url_state_only_not_a_draft_not_a_plan'
    && isset($previewReference['preview_requested_state'], $previewReference['preview_projected_resolved_state']), 'preview simulation remains separate from future persistent control plans');

assertProbe(($futureContract['persistence_enabled'] ?? true) === false
    && ($futureContract['contract_phase'] ?? '') === 'read_only_design_only', 'future settings contract is explicitly read-only and non-persistent');

$appearanceAudit = $model['appearance_integration_audit'] ?? [];
$appearanceMap = is_array($appearanceAudit) ? (array)($appearanceAudit['appearance_state_map'] ?? []) : [];
$appearanceEvidence = (array)($appearanceMap['evidence'] ?? []);
assertProbe(($appearanceMap['current_combined_mode'] ?? '') === ($model['runtime_contract']['combined_preference'] ?? '')
    && trim((string)($appearanceMap['state_origin'] ?? '')) !== '', 'current combined theme mode origin is reported from an identified source path or explicit origin');

$evidenceStatuses = array_values(array_filter(array_map(static fn($row) => is_array($row) ? (string)($row['status'] ?? '') : '', $appearanceEvidence)));
assertProbe(in_array('confirmed_contract', $evidenceStatuses, true)
    && in_array('inferred_contract', $evidenceStatuses, true)
    && in_array('unknown_requires_implementation', $evidenceStatuses, true)
    && in_array('unsafe_to_reuse', $evidenceStatuses, true), 'appearance-state map distinguishes confirmed, inferred, unknown, and unsafe evidence');

assertProbe(($appearanceAudit['preview_relationship']['preview_persistence'] ?? 'x') === 'none'
    && ($appearanceAudit['preview_relationship']['preview_authority'] ?? 'x') === 'none'
    && ($appearanceAudit['preview_relationship']['preview_impact_on_active_mode'] ?? 'x') === 'none', 'preview state is never treated as persisted appearance state');

$legacyModes = ['system-liquid-glass', 'system-paper', 'dark-liquid-glass', 'dark-paper', 'light-liquid-glass', 'light-paper'];
$legacyCompatible = true;
foreach ($legacyModes as $legacyMode) {
    $legacyResolved = SpecialEffectsRegistryService::resolveCompatibility($legacyMode, true, 'system');
    if (($legacyResolved['combined_preference'] ?? '') !== $legacyMode || !in_array((string)($legacyResolved['effect_profile'] ?? ''), ['paper', 'liquid_glass'], true)) {
        $legacyCompatible = false;
    }
}
assertProbe($legacyCompatible, 'existing combined modes remain compatible with future resolver contract');

$appearanceRecords = is_array($appearanceAudit) ? (array)($appearanceAudit['readiness_records'] ?? []) : [];
$recordsByKey = [];
foreach ($appearanceRecords as $record) {
    if (is_array($record)) {
        $recordsByKey[(string)($record['setting_key'] ?? '')] = $record;
    }
}
assertProbe(($recordsByKey['effects_enabled']['readiness_status'] ?? '') !== 'ready'
    && in_array('no_existing_source_of_truth_for_effects_enabled', (array)($recordsByKey['effects_enabled']['blocked_by'] ?? []), true)
    && ($recordsByKey['motion_mode']['readiness_status'] ?? '') !== 'ready'
    && in_array('no_existing_source_of_truth_for_motion_mode', (array)($recordsByKey['motion_mode']['blocked_by'] ?? []), true), 'no-effects and motion preferences require a persistence strategy beyond existing combined values');

$prematureReady = false;
foreach ($appearanceRecords as $record) {
    if (!is_array($record)) {
        continue;
    }
    $status = (string)($record['readiness_status'] ?? '');
    if ($status === 'ready' && (empty($record['authority_contract_available']) || empty($record['existing_write_path_reusable']))) {
        $prematureReady = true;
    }
    if ($status === 'ready' && (empty($record['audit_contract_available']) || empty($record['rollback_contract_available']))) {
        $prematureReady = true;
    }
}
assertProbe(!$prematureReady, 'future setting is not marked ready when authority, write path, audit, or rollback is unknown');

$precedence = (array)($appearanceAudit['conflict_prevention']['precedence_order'] ?? []);
assertProbe($precedence === [
    'system_defaults',
    'organization_policy',
    'permitted_user_preference',
    'preview_only_state_for_preview_surface_only',
    'runtime_safety_fallback',
    'resolved_runtime_state',
], 'organization policy, user preference, preview state, and runtime fallback have explicit precedence ordering');

$themeDependency = (array)($appearanceAudit['theme_doctor_dependency'] ?? []);
assertProbe(!empty($themeDependency['future_persistent_change_blocks_when']['profile_readiness_blocked'])
    && in_array('compiled_runtime_asset_health', (array)($themeDependency['theme_doctor_owns'] ?? []), true)
    && in_array('effect_profile_request', (array)($themeDependency['special_effects_owns'] ?? []), true), 'Theme Doctor runtime health is prerequisite for future effect-profile persistence');

$recordsHaveMutation = false;
foreach ($appearanceRecords as $record) {
    if (!is_array($record)) {
        continue;
    }
    foreach (['mutation_url', 'form_action', 'write_command', 'persistence_call'] as $mutationField) {
        if ((string)($record[$mutationField] ?? '') !== '') {
            $recordsHaveMutation = true;
        }
    }
}
assertProbe(!$recordsHaveMutation, 'read-only integration records contain no mutation URL, form action, write command, or persistence call');

assertProbe(($appearanceAudit['recommended_future_persistence_strategy'] ?? '') === 'C. Structured appearance state with backward-compatible combined-mode reads'
    && ($appearanceAudit['no_persistence_enabled'] ?? false) === true, 'recommended future strategy is structured state with legacy combined-mode compatibility and no persistence enabled');

$serviceSource = @file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Application/SpecialEffectsRegistryService.php') ?: '';
$viewSource = @file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Effects/SpecialEffects/Views/index.php') ?: '';
assertProbe(!preg_match('/file_put_contents|fwrite|fopen\\s*\\([^,]+,\\s*[\"\'][wa]|DB::|INSERT\\s+INTO|UPDATE\\s+[^\\s]|DELETE\\s+FROM|savePreference|writePreference|OrganizationSetting/i', $serviceSource . "\n" . $viewSource), 'preview introduces no preference, settings, source, compiled asset, database, or organization writes');
assertProbe(!preg_match('/method=["\']post|save.*effect|apply.*effect|persist.*effect|update.*effect/i', $viewSource), 'future controls UI has no persistent Special Effects form, save, apply, or update surface');
assertProbe(!preg_match('/\\$_SESSION\\s*\\[|setcookie\\s*\\(|DB::|INSERT\\s+INTO|UPDATE\\s+[^\\s]|DELETE\\s+FROM|CREATE\\s+TABLE|ALTER\\s+TABLE|OperatorPreferencesService::saveAll\\s*\\(|core_setting\\s*\\([^)]*,[^)]*\\)/i', $serviceSource . "\n" . $viewSource), 'V1.5 introduces no session, cookie, preference, core setting, database, or schema write dependency in Special Effects');

// ---------------------------------------------------------------------------
// Appearance Reader Inventory and Auth comparison structural assertions.
// ---------------------------------------------------------------------------
$inventorySectionHtml = '';
if (preg_match('/<details class="se-details">\s*<summary><\?= e\(\$se\(\'auth_comparison_inventory\'\)\) \?><\/summary>.*?<\/details>/s', $viewSource, $m)) {
    $inventorySectionHtml = $m[0];
}
$authComparisonSectionHtml = '';
if (preg_match('/<section class="se-panel">\s*<h3><\?= e\(\$se\(\'auth_comparison_title\'\)\) \?><\/h3>.*?<\/section>/s', $viewSource, $m)) {
    $authComparisonSectionHtml = $m[0];
}
// Auth reader comparison section locale keys presence.
foreach (['auth_comparison_title', 'auth_comparison_aligned', 'auth_comparison_mismatch', 'auth_comparison_unknown', 'auth_comparison_legacy_mode', 'auth_comparison_shell_mode', 'auth_comparison_comparison_id', 'auth_comparison_evidence_basis', 'auth_comparison_reason', 'auth_comparison_legacy_trace', 'auth_comparison_shell_trace', 'auth_comparison_mismatch_details', 'auth_comparison_full_contract', 'auth_comparison_inventory', 'auth_comparison_cutover_status', 'auth_comparison_blockers', 'auth_comparison_browser_override_excluded', 'auth_comparison_source_label', 'auth_comparison_no_mismatch'] as $localeKey) {
    assertProbe(
        preg_match("/['\"]{$localeKey}['\"]\\s*=>/", $viewSource) !== 0,
        "Auth comparison locale key '{$localeKey}' present in view"
    );
}
foreach (['auth_ledger_title', 'auth_ledger_desc', 'auth_ledger_known_cases', 'auth_ledger_aligned', 'auth_ledger_mismatch', 'auth_ledger_unknown', 'auth_ledger_observed_responses', 'auth_ledger_evidence_basis', 'auth_ledger_all_cases', 'auth_ledger_raw_json', 'auth_ledger_case_id', 'auth_ledger_auth_mode', 'auth_ledger_shell_mode', 'auth_ledger_status', 'auth_ledger_fallback', 'auth_ledger_source_status', 'auth_ledger_reason', 'auth_ledger_observed_flag'] as $localeKey) {
    assertProbe(
        preg_match("/['\"]{$localeKey}['\"]\\s*=>/", $viewSource) !== 0,
        "Auth ledger locale key '{$localeKey}' present in view"
    );
}
// Auth comparison section is rendered (section marker found).
assertProbe(
    preg_match('/Auth Server.Render Comparison|auth_comparison_title/i', $viewSource) !== 0,
    'Auth Server-Render Comparison section is present in view HTML'
);
assertProbe(
    preg_match('/auth_comparison_browser_override_excluded/', $viewSource) !== 0,
    'Auth comparison compact summary exposes browser override exclusion'
);
assertProbe(
    preg_match('/auth_ledger_title/', $viewSource) !== 0,
    'Auth parity evidence ledger summary exists'
);
assertProbe(
    preg_match('/auth_ledger_observed_responses/', $viewSource) !== 0,
    'Auth parity evidence ledger exposes observed-response count'
);
foreach (['auth_ledger_all_cases', 'auth_ledger_raw_json'] as $detailKey) {
    assertProbe(
        preg_match('/<details class="se-details">\s*<summary><\?= e\(\$se\(\'' . preg_quote($detailKey, '/') . '\'/', $viewSource) !== 0,
        "Auth ledger detail '{$detailKey}' is folded by default"
    );
}
foreach (['auth_comparison_legacy_trace', 'auth_comparison_shell_trace', 'auth_comparison_mismatch_details', 'auth_comparison_full_contract', 'auth_comparison_blockers', 'auth_comparison_inventory'] as $detailKey) {
    assertProbe(
        preg_match('/<details class="se-details">\s*<summary><\?= e\(\$se\(\'' . preg_quote($detailKey, '/') . '\'/', $viewSource) !== 0,
        "Auth comparison detail '{$detailKey}' is folded by default"
    );
}
assertProbe(
    trim($inventorySectionHtml) !== '',
    'Appearance Reader Inventory remains folded under the Auth comparison'
);
assertProbe(
    preg_match('/method=["\']post|<button|<input|<select|Save|Apply|Persist|Update|Migrate/i', $inventorySectionHtml) === 0,
    'Folded Appearance Reader Inventory contains no controls, forms, POST actions, or mutation labels'
);
assertProbe(
    preg_match('/method=["\']post|<button|<input|<select|Save|Apply|Persist|Update|Migrate/i', $authComparisonSectionHtml) === 0,
    'Auth comparison section adds no controls, forms, POST actions, or mutation labels'
);
assertProbe(
    preg_match('/auth_comparison_inventory/', $authComparisonSectionHtml) !== 0
        && preg_match('/auth_comparison_full_contract/', $authComparisonSectionHtml) !== 0,
    'Existing Auth comparison and reader inventory remain intact'
);

if ($failed > 0) {
    echo "RESULT: FAIL ({$failed}/{$checks} failed)\n";
    exit(1);
}

echo "RESULT: PASS ({$checks}/{$checks} passed)\n";
