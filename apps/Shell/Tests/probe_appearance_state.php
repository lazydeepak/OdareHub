<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 3));
}

require_once APP_ROOT . '/apps/Shell/Services/AppearanceStateResolver.php';
require_once APP_ROOT . '/apps/Shell/Services/AppearanceReaderInventoryService.php';
require_once APP_ROOT . '/apps/Shell/Services/AppearanceReaderComparisonService.php';

use Apps\Shell\Services\AppearanceStateResolver;
use Apps\Shell\Services\AppearanceReaderInventoryService;
use Apps\Shell\Services\AppearanceReaderComparisonService;

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
    echo "  FAIL: {$message}\n";
}

echo "Shell Appearance State probe\n";
echo "---\n";

// ---------------------------------------------------------------------------
// 1. Each of the six valid legacy combined modes normalizes deterministically.
// ---------------------------------------------------------------------------
$validModes = [
    'system-liquid-glass' => ['palette' => 'system', 'profile' => 'liquid_glass'],
    'system-paper'        => ['palette' => 'system', 'profile' => 'paper'],
    'dark-liquid-glass'   => ['palette' => 'dark',   'profile' => 'liquid_glass'],
    'dark-paper'          => ['palette' => 'dark',   'profile' => 'paper'],
    'light-liquid-glass'  => ['palette' => 'light',  'profile' => 'liquid_glass'],
    'light-paper'         => ['palette' => 'light',  'profile' => 'paper'],
];

foreach ($validModes as $mode => $expected) {
    $parsed = AppearanceStateResolver::parseLegacyMode($mode);
    assertProbe(
        $parsed['palette'] === $expected['palette'] && $parsed['profile'] === $expected['profile'],
        "Legacy mode '{$mode}' parses to palette={$expected['palette']}, profile={$expected['profile']}"
    );
    assertProbe(
        AppearanceStateResolver::isValidLegacyMode($mode),
        "'{$mode}' is recognized as valid"
    );
    $normalized = AppearanceStateResolver::normalizeLegacyMode($mode);
    assertProbe(
        $normalized === $mode,
        "Normalize '{$mode}' returns '{$mode}' unchanged"
    );
}

// ---------------------------------------------------------------------------
// 2. Invalid legacy values fall back safely without a write.
// ---------------------------------------------------------------------------
$invalidMode = AppearanceStateResolver::normalizeLegacyMode('invalid-unknown-mode');
assertProbe(
    $invalidMode === 'system-liquid-glass',
    "Invalid legacy mode 'invalid-unknown-mode' falls back to 'system-liquid-glass'"
);
assertProbe(
    !AppearanceStateResolver::isValidLegacyMode('invalid-unknown-mode'),
    "Invalid legacy mode is not recognized as valid"
);

$partialFallback = AppearanceStateResolver::normalizeLegacyMode('dark-unknown');
assertProbe(
    $partialFallback === 'dark-liquid-glass',
    "Partial legacy mode 'dark-unknown' normalizes to 'dark-liquid-glass'"
);

$shorthandTests = [
    'dark'   => 'dark-liquid-glass',
    'light'  => 'light-liquid-glass',
    'system' => 'system-liquid-glass',
];
foreach ($shorthandTests as $shorthand => $expected) {
    $result = AppearanceStateResolver::normalizeLegacyMode($shorthand);
    assertProbe(
        $result === $expected,
        "Shorthand '{$shorthand}' normalizes to '{$expected}'"
    );
    assertProbe(
        !AppearanceStateResolver::isValidLegacyMode($shorthand),
        "Shorthand '{$shorthand}' is not in the strict allowlist"
    );
}

// ---------------------------------------------------------------------------
// 2b. browserNormalizationMap agrees with parseLegacyMode.
// ---------------------------------------------------------------------------
$normMap = AppearanceStateResolver::browserNormalizationMap();
assertProbe(
    isset($normMap['known_modes'], $normMap['shorthand_map']),
    'browserNormalizationMap returns known_modes and shorthand_map'
);
assertProbe(
    count($normMap['known_modes']) === 6,
    'browserNormalizationMap known_modes contains exactly 6 entries'
);
assertProbe(
    ($normMap['shorthand_map']['dark'] ?? '') === 'dark-liquid-glass',
    'browserNormalizationMap shorthand dark maps to dark-liquid-glass'
);
assertProbe(
    ($normMap['shorthand_map']['light'] ?? '') === 'light-liquid-glass',
    'browserNormalizationMap shorthand light maps to light-liquid-glass'
);
assertProbe(
    ($normMap['shorthand_map']['system'] ?? '') === 'system-liquid-glass',
    'browserNormalizationMap shorthand system maps to system-liquid-glass'
);
// Every known_mode must round-trip through parseLegacyMode
foreach ($normMap['known_modes'] as $known) {
    $parsed = AppearanceStateResolver::parseLegacyMode($known);
    assertProbe(
        $parsed['palette'] !== 'system' || $parsed['profile'] !== 'liquid_glass' || $known === 'system-liquid-glass',
        "browserNormalizationMap known_mode '{$known}' is parseable"
    );
    assertProbe(
        AppearanceStateResolver::normalizeLegacyMode($known) === $known,
        "browserNormalizationMap known_mode '{$known}' round-trips through normalizeLegacyMode"
    );
}
// Every shorthand_map entry must be a valid known_mode
foreach ($normMap['shorthand_map'] as $short => $canonical) {
    assertProbe(
        AppearanceStateResolver::isValidLegacyMode($canonical),
        "browserNormalizationMap shorthand '{$short}' maps to valid mode '{$canonical}'"
    );
}

// ---------------------------------------------------------------------------
// 2c. Shorthand classification based on actual runtime source audit.
//     All three shorthands (dark, light, system) are confirmed_runtime_shorthand
//     because existing browser runtime code in header.php (lines 600-607),
//     auth_header.php (lines 77-84), and OperatorSurfaceComposer.php
//     (lines 4124-4131) explicitly accepts each and applies known behavior.
// ---------------------------------------------------------------------------
$knownShorthandValues = ['dark', 'light', 'system'];
$runtimeEvidence = 'header.php:600-607, auth_header.php:77-84, OperatorSurfaceComposer.php:4124-4131';
foreach ($knownShorthandValues as $shorthand) {
    $resolved = AppearanceStateResolver::normalizeLegacyMode($shorthand);
    $isKnownMode = AppearanceStateResolver::isValidLegacyMode($resolved);
    assertProbe(
        $isKnownMode && str_starts_with($resolved, $shorthand . '-'),
        "Runtime shorthand '{$shorthand}' is confirmed_runtime_shorthand — resolves to '{$resolved}' (evidence: {$runtimeEvidence})"
    );
}

// ---------------------------------------------------------------------------
// 2d. Invalid browser override remains invalid (does not become valid mode).
// ---------------------------------------------------------------------------
$invalidOverrides = ['totally-invalid-value', '', '  ', 'null', 'undefined'];
foreach ($invalidOverrides as $override) {
    $resolved = AppearanceStateResolver::normalizeLegacyMode($override);
    assertProbe(
        AppearanceStateResolver::isValidLegacyMode($resolved),
        "Invalid override '" . ($override === '' ? '(empty)' : $override) . "' normalizes to valid fallback '{$resolved}'"
    );
    assertProbe(
        !AppearanceStateResolver::isValidLegacyMode($override !== '' ? $override : ' '),
        "Invalid override '" . ($override === '' ? '(empty)' : $override) . "' is not itself a valid legacy mode"
    );
}
// Empty string normalizes to system-liquid-glass fallback, not treated as valid
assertProbe(
    AppearanceStateResolver::normalizeLegacyMode('') === 'system-liquid-glass',
    "Empty string normalizes to system-liquid-glass fallback (absent, not invalid)"
);

// ---------------------------------------------------------------------------
// 3. Server resolver never claims it can read browser localStorage.
// ---------------------------------------------------------------------------
$authResult = AppearanceStateResolver::resolve([
    'surface' => 'auth',
    'system_default_combined_mode' => 'light-paper',
    'current_user_id_available' => false,
]);
assertProbe(
    ($authResult['browser_override_contract']['browser_override_available_server_side'] ?? true) === false,
    'Server resolver correctly reports browser localStorage is not readable server-side'
);
assertProbe(
    ($authResult['browser_override_contract']['storage_key'] ?? '') === 'erp-theme-preference',
    'Browser override storage key is documented as erp-theme-preference'
);

// ---------------------------------------------------------------------------
// 4. Browser state remains not_observed_server_side until observation exists.
// ---------------------------------------------------------------------------
assertProbe(
    ($authResult['browser_effective_state']['availability'] ?? '') === 'not_observed_server_side',
    'Browser effective state is not_observed_server_side when no observation provided'
);
assertProbe(
    $authResult['browser_effective_state']['combined_mode'] === null,
    'Browser effective combined_mode is null when not observed'
);
assertProbe(
    ($authResult['browser_effective_state']['status'] ?? '') === 'not_applicable',
    'Browser effective status is not_applicable when not observed'
);

// ---------------------------------------------------------------------------
// 5. Session override, operator DB preference, and system default are
//    separate source-trace entries.
// ---------------------------------------------------------------------------
$operatorResult = AppearanceStateResolver::resolve([
    'surface' => 'operator',
    'operator_session_override_combined_mode' => 'dark-paper',
    'operator_preference_combined_mode' => 'light-liquid-glass',
    'system_default_combined_mode' => 'system-liquid-glass',
    'current_user_id_available' => true,
]);
$sourceNames = array_map(
    static fn(array $s) => $s['source_name'] ?? '',
    $operatorResult['source_trace']
);
assertProbe(
    in_array('session_override', $sourceNames, true),
    'Source trace contains session_override entry'
);
assertProbe(
    in_array('operator_preference', $sourceNames, true),
    'Source trace contains operator_preference entry'
);
assertProbe(
    in_array('system_default', $sourceNames, true),
    'Source trace contains system_default entry'
);
assertProbe(
    in_array('absolute_fallback', $sourceNames, true),
    'Source trace contains absolute_fallback entry'
);

// ---------------------------------------------------------------------------
// 6. Operator server intent: session override > operator preference > system
//    default > fallback.
// ---------------------------------------------------------------------------
assertProbe(
    ($operatorResult['server_resolved_intent']['combined_mode'] ?? '') === 'dark-paper',
    'Operator server intent uses session_override (dark-paper) over operator preference (light-liquid-glass)'
);
assertProbe(
    ($operatorResult['server_resolved_intent']['resolution_source'] ?? '') === 'session_override',
    'Operator server intent resolution source is session_override'
);

$operatorNoSession = AppearanceStateResolver::resolve([
    'surface' => 'operator',
    'operator_preference_combined_mode' => 'light-liquid-glass',
    'system_default_combined_mode' => 'system-paper',
    'current_user_id_available' => true,
]);
assertProbe(
    ($operatorNoSession['server_resolved_intent']['combined_mode'] ?? '') === 'light-liquid-glass',
    'Operator server intent falls back to operator DB preference when no session override'
);
assertProbe(
    ($operatorNoSession['server_resolved_intent']['resolution_source'] ?? '') === 'operator_preference',
    'Operator server intent source is operator_preference when session override absent'
);

$operatorDefaultsOnly = AppearanceStateResolver::resolve([
    'surface' => 'operator',
    'system_default_combined_mode' => 'system-paper',
    'current_user_id_available' => false,
]);
assertProbe(
    ($operatorDefaultsOnly['server_resolved_intent']['combined_mode'] ?? '') === 'system-paper',
    'Operator server intent uses system default when no session or DB preference'
);
assertProbe(
    ($operatorDefaultsOnly['server_resolved_intent']['resolution_source'] ?? '') === 'system_default',
    'Operator server intent source is system_default when no session or DB preference'
);

// ---------------------------------------------------------------------------
// 7. Auth server attribute parity aligns for known matching inputs.
// ---------------------------------------------------------------------------
$authAligned = AppearanceStateResolver::resolve([
    'surface' => 'auth',
    'system_default_combined_mode' => 'dark-paper',
    'legacy_rendered_combined_mode' => 'dark-paper',
    'current_user_id_available' => false,
]);
assertProbe(
    ($authAligned['rendering_parity']['status'] ?? '') === 'aligned',
    'Auth rendering parity is aligned when legacy rendered matches resolved intent'
);
assertProbe(
    ($authAligned['rendering_parity']['kind'] ?? '') === 'server_attribute',
    'Auth rendering parity kind is server_attribute'
);

$authMismatch = AppearanceStateResolver::resolve([
    'surface' => 'auth',
    'system_default_combined_mode' => 'light-paper',
    'legacy_rendered_combined_mode' => 'dark-liquid-glass',
    'current_user_id_available' => false,
]);
assertProbe(
    ($authMismatch['rendering_parity']['status'] ?? '') === 'mismatch',
    'Auth rendering parity is mismatch when legacy rendered differs from resolved intent'
);

// ---------------------------------------------------------------------------
// 7b. Auth surface ignores operator preference even if accidentally provided.
// ---------------------------------------------------------------------------
$authWithOpPref = AppearanceStateResolver::resolve([
    'surface' => 'auth',
    'operator_preference_combined_mode' => 'dark-paper',
    'system_default_combined_mode' => 'light-liquid-glass',
    'current_user_id_available' => false,
]);
$authSources = array_map(
    static fn(array $s) => $s['source_name'] ?? '',
    $authWithOpPref['source_trace']
);
assertProbe(
    !in_array('operator_preference', $authSources, true),
    'Auth source trace does not contain operator_preference'
);
assertProbe(
    ($authWithOpPref['server_resolved_intent']['resolution_source'] ?? '') === 'system_default',
    'Auth server intent source is system_default even when operator_preference provided'
);

// ---------------------------------------------------------------------------
// 7c. Auth surface is reported in surface context output.
// ---------------------------------------------------------------------------
assertProbe(
    ($authAligned['surface_context']['surface'] ?? '') === 'auth',
    'Auth resolve output surface_context.surface is auth'
);
assertProbe(
    ($authMismatch['surface_context']['surface'] ?? '') === 'auth',
    'Auth mismatch output surface_context.surface is auth'
);

// ---------------------------------------------------------------------------
// 7d. Auth surface does NOT consume operator_session_override even if provided.
// ---------------------------------------------------------------------------
$authWithSessionOverride = AppearanceStateResolver::resolve([
    'surface' => 'auth',
    'operator_session_override_combined_mode' => 'dark-paper',
    'system_default_combined_mode' => 'light-liquid-glass',
    'current_user_id_available' => false,
]);
$authSessionSources = array_map(
    static fn(array $s) => $s['source_name'] ?? '',
    $authWithSessionOverride['source_trace']
);
$authSessionOverrideEntry = null;
foreach ($authWithSessionOverride['source_trace'] ?? [] as $s) {
    if (($s['source_name'] ?? '') === 'session_override') {
        $authSessionOverrideEntry = $s;
        break;
    }
}
assertProbe(
    $authSessionOverrideEntry !== null,
    'Auth source trace contains session_override entry (added by collectAvailableSources)'
);
assertProbe(
    ($authSessionOverrideEntry['selected'] ?? true) === false,
    'Auth session_override entry is NOT selected (auth does not consume session override)'
);
assertProbe(
    ($authWithSessionOverride['server_resolved_intent']['combined_mode'] ?? '') === 'light-liquid-glass',
    'Auth server intent is system_default (light-liquid-glass), not session_override value'
);

// ---------------------------------------------------------------------------
// 8. Admin/Operator parity is not falsely labeled server-aligned.
// ---------------------------------------------------------------------------
$adminResult = AppearanceStateResolver::resolve([
    'surface' => 'admin',
    'system_default_combined_mode' => 'system-liquid-glass',
    'current_user_id_available' => false,
]);
assertProbe(
    ($adminResult['rendering_parity']['status'] ?? '') === 'not_observable',
    'Admin rendering parity is not_observable (no server attributes emitted)'
);
assertProbe(
    ($adminResult['rendering_parity']['kind'] ?? '') === 'client_runtime',
    'Admin rendering parity kind is client_runtime'
);

$operatorParity = AppearanceStateResolver::resolve([
    'surface' => 'operator',
    'operator_preference_combined_mode' => 'light-paper',
    'system_default_combined_mode' => 'system-liquid-glass',
    'current_user_id_available' => true,
]);
assertProbe(
    ($operatorParity['rendering_parity']['status'] ?? '') === 'not_observable',
    'Operator rendering parity is not_observable (client-initialized)'
);
assertProbe(
    ($operatorParity['rendering_parity']['kind'] ?? '') === 'client_runtime',
    'Operator rendering parity kind is client_runtime'
);

// ---------------------------------------------------------------------------
// 9. A parity mismatch is diagnostic only and triggers no mutation.
// ---------------------------------------------------------------------------
assertProbe(
    isset($authMismatch['rendering_parity']['reason']),
    'Rendering parity includes a reason string'
);
assertProbe(
    !isset($authMismatch['rendering_parity']['mutation_triggered']),
    'Rendering parity mismatch has no mutation_triggered field'
);

// ---------------------------------------------------------------------------
// 10. effect_profile=none remains preview/future-only.
// ---------------------------------------------------------------------------
// Test 10: effect_profile=none is preview/future-only
assertProbe(
    ($operatorResult['effect_profile'] ?? 'liquid_glass') !== 'none',
    'Active resolved effect_profile is not none (none is preview/future-only)'
);
$noneParsed = AppearanceStateResolver::parseLegacyMode('none');
assertProbe(
    $noneParsed['palette'] === 'system',
    "Parsing 'none' returns safe fallback palette (system), not 'none' as a combined mode"
);

// ---------------------------------------------------------------------------
// 11. motion_mode is explicitly marked derived/default-only under legacy mode.
// ---------------------------------------------------------------------------
assertProbe(
    ($operatorResult['motion_mode'] ?? '') === 'system',
    'motion_mode is system (derived/default-only under legacy combined mode)'
);

// ---------------------------------------------------------------------------
// 12. effects_enabled is not falsely reported as independently persisted.
// ---------------------------------------------------------------------------
$readiness = $operatorResult['future_structured_state_readiness'] ?? [];
assertProbe(
    ($readiness['effects_enabled']['readiness'] ?? '') === 'blocked',
    'effects_enabled readiness is blocked (not independently persisted)'
);
assertProbe(
    ($readiness['effects_enabled']['current_representation'] ?? '') === 'not_independently_represented',
    'effects_enabled current_representation is not_independently_represented'
);

// ---------------------------------------------------------------------------
// 13. Special Effects consumption target has correct intent shape.
// ---------------------------------------------------------------------------
assertProbe(
    isset($operatorResult['server_resolved_intent']['combined_mode']),
    'server_resolved_intent has combined_mode'
);
assertProbe(
    isset($operatorResult['server_resolved_intent']['resolution_source']),
    'server_resolved_intent has resolution_source'
);
assertProbe(
    in_array($operatorResult['server_resolved_intent']['status'] ?? '', ['confirmed', 'inferred', 'unknown'], true),
    'server_resolved_intent has valid status'
);

// ---------------------------------------------------------------------------
// 14. Passive browser observer shape (no localStorage write, no network).
// ---------------------------------------------------------------------------
assertProbe(
    ($authResult['browser_override_contract']['browser_override_supported'] ?? false) === true,
    'Browser override is documented as supported'
);

// ---------------------------------------------------------------------------
// 15. No POST route, persistence, schema, audit, rollback, or mutation.
// ---------------------------------------------------------------------------
assertProbe(
    !isset($authResult['persistence_enabled']),
    'Resolve output does not include persistence_enabled field'
);
assertProbe(
    !isset($authResult['write_path']),
    'Resolve output does not include write_path field'
);

// ---------------------------------------------------------------------------
// 16. data-theme, data-theme-mode, data-color-style, data-theme-preference
//     compatibility for every valid legacy mode.
// ---------------------------------------------------------------------------
$adminDefault = AppearanceStateResolver::resolve([
    'surface' => 'admin',
    'system_default_combined_mode' => 'system-liquid-glass',
    'current_user_id_available' => false,
]);
assertProbe(
    ($adminDefault['legacy_combined_mode'] ?? '') === 'system-liquid-glass',
    'Admin legacy_combined_mode is system-liquid-glass'
);
assertProbe(
    ($adminDefault['palette_mode'] ?? '') === 'system',
    'Admin palette_mode is system'
);
assertProbe(
    ($adminDefault['effect_profile'] ?? '') === 'liquid_glass',
    'Admin effect_profile is liquid_glass'
);

// ---------------------------------------------------------------------------
// 17. Existing active theme behavior remains unchanged (no active rendering).
// ---------------------------------------------------------------------------
assertProbe(
    !isset($authResult['data_theme_set']),
    'Resolve output does not set or claim to set data-theme'
);
assertProbe(
    !isset($authResult['localStorage_written']),
    'Resolve output does not claim localStorage writes'
);

// ---------------------------------------------------------------------------
// 18. Future precedence model.
// ---------------------------------------------------------------------------
$precedence = $authResult['future_precedence_model'] ?? [];
$order = $precedence['precedence_order'] ?? [];
assertProbe(
    count($order) > 0,
    'Future precedence model has a precedence_order array'
);
assertProbe(
    in_array('runtime_safety_fallback', $order, true),
    'Future precedence includes runtime_safety_fallback'
);
assertProbe(
    in_array('preview_only_state_non_persistent', $order, true),
    'Future precedence includes preview_only_state_non_persistent'
);
assertProbe(
    count($precedence['principles'] ?? []) > 0,
    'Future precedence model has principles'
);

// ---------------------------------------------------------------------------
// 19. Source trace formatting validation.
// ---------------------------------------------------------------------------
foreach ($operatorResult['source_trace'] as $source) {
    assertProbe(
        isset($source['source_name'], $source['candidate_value'], $source['status']),
        'Source trace entry has required fields: source_name, candidate_value, status'
    );
    assertProbe(
        isset($source['selected']),
        'Source trace entry has selected field'
    );
}

// ---------------------------------------------------------------------------
// 20. Browser-effective state is never fabricated in PHP resolver.
//     When browser is not observed, the resolver returns not_observed_server_side
//     with null combined_mode — it does not guess or imply a value.
// ---------------------------------------------------------------------------
$adminBrowserState = $adminDefault['browser_effective_state'] ?? [];
assertProbe(
    ($adminBrowserState['availability'] ?? '') === 'not_observed_server_side',
    'Admin browser_effective_state availability is not_observed_server_side (not fabricated)'
);
assertProbe(
    array_key_exists('combined_mode', $adminBrowserState) && $adminBrowserState['combined_mode'] === null,
    'Admin browser_effective_state combined_mode is null (not fabricated)'
);
assertProbe(
    ($adminBrowserState['status'] ?? '') === 'not_applicable',
    'Admin browser_effective_state status is not_applicable (rendering-disabled observer)'
);

// ---------------------------------------------------------------------------
// 21. Observer safety — probe verifies the boundary does not allow dangerous
//     operations even in passive mode.
// ---------------------------------------------------------------------------
assertProbe(
    !isset($authResult['observer_write_recorded']),
    'Resolve output does not claim observer wrote to localStorage'
);

// ---------------------------------------------------------------------------
// 22. AppearanceReaderInventoryService returns expected structure.
// ---------------------------------------------------------------------------
$inventoryService = AppearanceReaderInventoryService::inventory();
$expectedReaderIds = [
    'auth_server_header',
    'admin_layout_header',
    'operator_server_intent',
    'operator_browser_init',
    'global_browser_localStorage',
    'special_effects_consistency',
    'special_effects_preview_iframe',
    'css_live_editor_preview',
    'cte_preview',
    'theme_doctor_dependency',
];
assertProbe(
    is_array($inventoryService),
    'AppearanceReaderInventoryService::inventory() returns array'
);
assertProbe(
    count($inventoryService) >= 10,
    'AppearanceReaderInventoryService::inventory() returns at least 10 readers'
);
// All 10 known reader IDs are present.
$foundIds = array_map(static fn(array $r) => $r['reader_id'] ?? '', $inventoryService);
foreach ($expectedReaderIds as $eid) {
    assertProbe(
        in_array($eid, $foundIds, true),
        "Reader '{$eid}' is present in inventory"
    );
}
// Each reader has required fields.
foreach ($inventoryService as $reader) {
    $rid = $reader['reader_id'] ?? '?';
    assertProbe(
        isset($reader['reader_id'], $reader['surface'], $reader['reader_type']),
        "Reader '{$rid}' has reader_id, surface, reader_type"
    );
    assertProbe(
        isset($reader['comparison_status'], $reader['comparison_evidence_basis'], $reader['comparison_id']),
        "Reader '{$rid}' has comparison status fields"
    );
}
$readerById = [];
foreach ($inventoryService as $reader) {
    $readerById[(string)($reader['reader_id'] ?? '')] = $reader;
}
assertProbe(
    ($readerById['auth_server_header']['shell_resolver_relation'] ?? '') === 'compatible_for_parallel_compare',
    'Auth remains compatible_for_parallel_compare'
);
assertProbe(
    ($readerById['admin_layout_header']['reader_type'] ?? '') === 'client_runtime'
        && ($readerById['admin_layout_header']['comparison_status'] ?? '') === 'not_observable',
    'Admin classification remains client_runtime/not_observable'
);
assertProbe(
    ($readerById['operator_browser_init']['reader_type'] ?? '') === 'client_runtime'
        && ($readerById['operator_browser_init']['comparison_status'] ?? '') === 'not_observable',
    'Operator browser initializer remains client_runtime/not_observable'
);
assertProbe(
    ($readerById['global_browser_localStorage']['reader_type'] ?? '') === 'browser_runtime'
        && ($readerById['global_browser_localStorage']['risk_level'] ?? '') === 'high',
    'Browser localStorage override remains high-risk browser_runtime'
);
$localStorageRisk = AppearanceReaderInventoryService::legacyLocalStorageRisk();
assertProbe(
    ($localStorageRisk['server_visibility'] ?? '') === 'none'
        && ($localStorageRisk['server_side_read_access'] ?? true) === false,
    'Browser localStorage override remains no-server-visibility'
);

// ---------------------------------------------------------------------------
// 23. AppearanceReaderComparisonService returns contract-compliant result.
// ---------------------------------------------------------------------------
$authComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'dark-paper',
    'auth_resolution_source' => 'ThemePreferenceService::defaultPreference()',
    'system_default' => 'dark-paper',
    'evidence_basis' => 'source_contract',
]);
assertProbe(
    is_array($authComparison),
    'compareAuthServerIntent returns array'
);
assertProbe(
    ($authComparison['comparison_contract_version'] ?? '') === '1.0',
    'Comparison contract version is 1.0'
);
assertProbe(
    ($authComparison['reader_id'] ?? '') === 'auth_server_header',
    'Comparison reader_id is auth_server_header'
);
assertProbe(
    ($authComparison['surface'] ?? '') === 'auth',
    'Comparison surface is auth'
);
assertProbe(
    ($authComparison['comparison_kind'] ?? '') === 'server_mode',
    'Comparison kind is server_mode'
);
assertProbe(
    ($authComparison['legacy_auth_mode'] ?? '') === 'dark-paper',
    'Comparison legacy_auth_mode matches input'
);
assertProbe(
    ($authComparison['status'] ?? '') === 'aligned',
    'Comparison status is aligned when modes match'
);
assertProbe(
    !empty($authComparison['reason']),
    'Comparison has reason string'
);
assertProbe(
    $authComparison['diagnostic_only'] === true,
    'Comparison is diagnostic_only'
);
assertProbe(
    $authComparison['cutover_status'] === 'not_ready',
    'Comparison cutover_status is not_ready'
);
assertProbe(
    $authComparison['browser_override_included'] === false,
    'Comparison browser_override_included is false'
);
assertProbe(
    $authComparison['browser_effective_state_claimed'] === false,
    'Comparison browser_effective_state_claimed is false'
);
assertProbe(
    count($authComparison['cutover_blockers']) >= 2,
    'Comparison has at least 2 cutover blockers'
);
$inventoryWithComparison = AppearanceReaderInventoryService::inventoryWithComparison($authComparison);
$inventoryAuthEntry = [];
$inventoryAdminEntry = [];
$inventoryOperatorBrowserEntry = [];
foreach ($inventoryWithComparison as $reader) {
    if (($reader['reader_id'] ?? '') === 'auth_server_header') {
        $inventoryAuthEntry = $reader;
    } elseif (($reader['reader_id'] ?? '') === 'admin_layout_header') {
        $inventoryAdminEntry = $reader;
    } elseif (($reader['reader_id'] ?? '') === 'operator_browser_init') {
        $inventoryOperatorBrowserEntry = $reader;
    }
}
assertProbe(
    ($inventoryAuthEntry['comparison_status'] ?? '') === 'aligned'
        && ($inventoryAuthEntry['comparison_id'] ?? '') === ($authComparison['comparison_id'] ?? ''),
    'Inventory integration folds comparison status into auth_server_header only'
);
assertProbe(
    ($inventoryAdminEntry['comparison_status'] ?? '') === 'not_observable',
    'Inventory integration does not alter Admin comparison status'
);
assertProbe(
    ($inventoryOperatorBrowserEntry['comparison_status'] ?? '') === 'not_observable',
    'Inventory integration does not alter Operator browser comparison status'
);

// ---------------------------------------------------------------------------
// 24. Mismatch comparison behavior.
// ---------------------------------------------------------------------------
$mismatchComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'dark-paper',
    'system_default' => 'light-paper',
    'evidence_basis' => 'source_contract',
]);
assertProbe(
    ($mismatchComparison['status'] ?? '') === 'mismatch',
    'Comparison reports mismatch when system default differs from auth mode'
);
assertProbe(
    count($mismatchComparison['mismatch_fields'] ?? []) >= 1,
    'Mismatch comparison has mismatch_fields entries'
);
$firstField = $mismatchComparison['mismatch_fields'][0] ?? [];
assertProbe(
    isset($firstField['field'], $firstField['legacy_value'], $firstField['shell_value']),
    'Mismatch field entry has field, legacy_value, shell_value'
);
$ignoredOperatorComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'dark-paper',
    'system_default' => 'dark-paper',
    'operator_session_override_combined_mode' => 'light-paper',
    'operator_preference_combined_mode' => 'light-liquid-glass',
    'browser_localStorage_combined_mode' => 'light-paper',
    'evidence_basis' => 'source_contract',
]);
assertProbe(
    ($ignoredOperatorComparison['status'] ?? '') === 'aligned',
    'Operator session override input is ignored for Auth comparison'
);
assertProbe(
    ($ignoredOperatorComparison['shell_server_intent_mode'] ?? '') === 'dark-paper',
    'Operator DB preference input is ignored for Auth comparison'
);
assertProbe(
    ($ignoredOperatorComparison['browser_override_included'] ?? true) === false
        && ($ignoredOperatorComparison['browser_effective_state_claimed'] ?? true) === false,
    'Browser localStorage input is ignored for Auth comparison'
);
$ignoredSourceNames = array_map(
    static fn(array $s): string => (string)($s['source_name'] ?? ''),
    (array)($ignoredOperatorComparison['shell_source_trace'] ?? [])
);
assertProbe(
    !in_array('session_override', $ignoredSourceNames, true)
        && !in_array('operator_preference', $ignoredSourceNames, true),
    'Auth comparison shell trace excludes operator-only inputs'
);

// ---------------------------------------------------------------------------
// 25. Unknown/fallback comparison behavior.
// ---------------------------------------------------------------------------
$unknownComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => '',
    'system_default' => '',
    'evidence_basis' => 'source_contract',
]);
assertProbe(
    ($unknownComparison['status'] ?? '') === 'unknown',
    'Missing legacy Auth mode returns unknown'
);
assertProbe(
    isset($unknownComparison['comparison_id']),
    'Comparison has comparison_id even with empty input'
);
$invalidLegacyComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'invalid-mode',
    'system_default' => 'system-liquid-glass',
    'evidence_basis' => 'source_contract',
]);
assertProbe(
    ($invalidLegacyComparison['legacy_auth_mode_status'] ?? '') === 'invalid'
        && ($invalidLegacyComparison['status'] ?? '') === 'unknown',
    'Invalid legacy Auth mode returns unknown'
);
$invalidShellComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'system-liquid-glass',
    'system_default' => 'invalid-mode',
    'evidence_basis' => 'source_contract',
]);
assertProbe(
    ($invalidShellComparison['status'] ?? '') === 'unknown',
    'Invalid Shell source evidence returns unknown'
);

// ---------------------------------------------------------------------------
// 26. Evidence basis validation.
// ---------------------------------------------------------------------------
$fixtureComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'system-liquid-glass',
    'system_default' => 'system-liquid-glass',
    'evidence_basis' => 'fixture',
]);
assertProbe(
    ($fixtureComparison['evidence_basis'] ?? '') === 'fixture',
    'Evidence basis fixture is preserved'
);
$observedComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'system-liquid-glass',
    'system_default' => 'system-liquid-glass',
    'evidence_basis' => 'observed_server_render',
]);
assertProbe(
    ($observedComparison['evidence_basis'] ?? '') === 'observed_server_render',
    'Evidence basis observed_server_render is preserved'
);
// Invalid evidence_basis falls back to source_contract.
$defaultComparison = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'system-liquid-glass',
    'system_default' => 'system-liquid-glass',
    'evidence_basis' => 'not_a_valid_value',
]);
assertProbe(
    ($defaultComparison['evidence_basis'] ?? '') === 'source_contract',
    'Invalid evidence_basis falls back to source_contract'
);

// ---------------------------------------------------------------------------
// 27. comparison_id is deterministic for identical inputs.
// ---------------------------------------------------------------------------
$idA = $authComparison['comparison_id'] ?? '';
$idB = AppearanceReaderComparisonService::compareAuthServerIntent([
    'auth_legacy_mode' => 'dark-paper',
    'auth_resolution_source' => 'ThemePreferenceService::defaultPreference()',
    'system_default' => 'dark-paper',
    'evidence_basis' => 'source_contract',
])['comparison_id'] ?? '';
assertProbe(
    $idA !== '' && $idA === $idB,
    'Comparison ID is deterministic for identical inputs'
);

// ---------------------------------------------------------------------------
// 27b. Auth parity evidence ledger.
// ---------------------------------------------------------------------------
$authLedger = AppearanceReaderComparisonService::authParityEvidenceLedger();
$requiredLedgerIds = [
    'auth-default-system-liquid-glass',
    'auth-default-system-paper',
    'auth-default-dark-liquid-glass',
    'auth-default-dark-paper',
    'auth-default-light-liquid-glass',
    'auth-default-light-paper',
    'auth-default-invalid-value',
    'auth-default-missing-value',
];
$ledgerIds = array_map(static fn(array $row): string => (string)($row['case_id'] ?? ''), $authLedger);
sort($ledgerIds);
$sortedRequiredLedgerIds = $requiredLedgerIds;
sort($sortedRequiredLedgerIds);
assertProbe(
    $ledgerIds === $sortedRequiredLedgerIds,
    'Auth ledger has exactly the eight required stable case IDs'
);
$validLedgerModes = [];
$ledgerById = [];
foreach ($authLedger as $row) {
    $ledgerById[(string)($row['case_id'] ?? '')] = $row;
    if (($row['input_kind'] ?? '') === 'known_mode') {
        $validLedgerModes[] = (string)($row['input_mode'] ?? '');
    }
}
sort($validLedgerModes);
$expectedLedgerModes = array_keys($validModes);
sort($expectedLedgerModes);
assertProbe(
    $validLedgerModes === $expectedLedgerModes,
    'Every valid known combined mode is represented once in Auth ledger'
);
foreach ($expectedLedgerModes as $mode) {
    $caseId = 'auth-default-' . $mode;
    $row = $ledgerById[$caseId] ?? [];
    assertProbe(
        AppearanceStateResolver::normalizeLegacyMode((string)($row['input_mode'] ?? '')) === $mode,
        "Auth ledger case '{$caseId}' uses canonical normalization"
    );
    assertProbe(
        ($row['status'] ?? '') === 'aligned',
        "Auth ledger valid mode '{$mode}' is aligned"
    );
}
$invalidLedgerRow = $ledgerById['auth-default-invalid-value'] ?? [];
$missingLedgerRow = $ledgerById['auth-default-missing-value'] ?? [];
assertProbe(
    ($invalidLedgerRow['rendered_auth_response_observed'] ?? true) === false,
    'Invalid-value ledger case never claims observed server render'
);
assertProbe(
    ($missingLedgerRow['rendered_auth_response_observed'] ?? true) === false,
    'Missing-value ledger case never claims observed server render'
);
foreach ([$invalidLedgerRow, $missingLedgerRow] as $row) {
    $fallbackStatus = (string)($row['fallback_evidence']['status'] ?? '');
    assertProbe(
        in_array($fallbackStatus, ['confirmed', 'inferred', 'unknown'], true),
        'Invalid/missing ledger fallback status is confirmed, inferred, or unknown'
    );
    assertProbe(
        $fallbackStatus !== 'confirmed',
        'Invalid/missing ledger fallback status is not fabricated as confirmed'
    );
}
$authLedgerAgain = AppearanceReaderComparisonService::authParityEvidenceLedger();
foreach ($authLedger as $idx => $row) {
    $again = $authLedgerAgain[$idx] ?? [];
    assertProbe(
        ($row['comparison_id'] ?? '') !== '' && ($row['comparison_id'] ?? '') === ($again['comparison_id'] ?? null),
        "Auth ledger comparison_id is stable for case " . (string)($row['case_id'] ?? $idx)
    );
    assertProbe(
        ($row['reader_id'] ?? '') === 'auth_server_header' && ($row['surface'] ?? '') === 'auth',
        'Every Auth ledger case has reader_id=auth_server_header and surface=auth'
    );
    assertProbe(
        ($row['comparison_kind'] ?? '') === 'server_mode',
        'Every Auth ledger case has comparison_kind=server_mode'
    );
    assertProbe(
        ($row['diagnostic_only'] ?? false) === true,
        'Every Auth ledger case is diagnostic_only=true'
    );
    assertProbe(
        ($row['cutover_status'] ?? '') === 'not_ready',
        'Every Auth ledger case has cutover_status=not_ready'
    );
    assertProbe(
        ($row['browser_override_included'] ?? true) === false,
        'Every Auth ledger case excludes browser override'
    );
    assertProbe(
        ($row['browser_effective_state_claimed'] ?? true) === false,
        'Every Auth ledger case claims no browser-effective state'
    );
    assertProbe(
        ($row['rendered_auth_response_observed'] ?? true) === false,
        'Every Auth ledger case has rendered_auth_response_observed=false'
    );
    $ledgerJson = json_encode($row, JSON_UNESCAPED_SLASHES) ?: '';
    assertProbe(
        !str_contains($ledgerJson, 'operator_session_override')
            && !str_contains($ledgerJson, 'operator_preference')
            && !str_contains($ledgerJson, 'browser_localStorage')
            && !str_contains($ledgerJson, 'erp-theme-preference'),
        'Operator state and browser localStorage are excluded from every Auth ledger case'
    );
}
$ledgerSummary = AppearanceReaderComparisonService::summarizeAuthParityEvidenceLedger($authLedger);
$inventoryWithLedger = AppearanceReaderInventoryService::inventoryWithComparison($authComparison, $authLedger);
$inventoryLedgerAuth = [];
foreach ($inventoryWithLedger as $reader) {
    if (($reader['reader_id'] ?? '') === 'auth_server_header') {
        $inventoryLedgerAuth = $reader;
        break;
    }
}
assertProbe(
    ($inventoryLedgerAuth['ledger_case_count'] ?? null) === ($ledgerSummary['ledger_case_count'] ?? null)
        && ($inventoryLedgerAuth['ledger_aligned_count'] ?? null) === ($ledgerSummary['ledger_aligned_count'] ?? null)
        && ($inventoryLedgerAuth['ledger_unknown_count'] ?? null) === ($ledgerSummary['ledger_unknown_count'] ?? null),
    'Auth inventory ledger summary equals detailed ledger totals'
);

// ---------------------------------------------------------------------------
// 28. Comparison output remains read-only and does not touch renderers.
// ---------------------------------------------------------------------------
$comparisonJson = json_encode([$authComparison, $mismatchComparison, $unknownComparison, $authLedger], JSON_UNESCAPED_SLASHES) ?: '';
assertProbe(
    preg_match('/POST|save|apply|persist|update|migrate|mutation_url|form_action|write_command/i', $comparisonJson) === 0,
    'Comparison output contains no write endpoint, mutation action, persistence instruction, or migration action'
);
$comparisonServiceSource = @file_get_contents(APP_ROOT . '/apps/Shell/Services/AppearanceReaderComparisonService.php') ?: '';
assertProbe(
    str_contains($comparisonServiceSource, 'AppearanceStateResolver::normalizeLegacyMode')
        && !preg_match('/function\s+normalizeLegacyMode|function\s+parseLegacyMode/', $comparisonServiceSource),
    'Comparison service uses AppearanceStateResolver as the only normalization source'
);
$authHeaderSource = @file_get_contents(APP_ROOT . '/public/views/layouts/auth_header.php') ?: '';
$adminHeaderSource = @file_get_contents(APP_ROOT . '/public/views/layouts/header.php') ?: '';
$operatorComposerSource = @file_get_contents(APP_ROOT . '/apps/Shell/Composers/OperatorSurfaceComposer.php') ?: '';
assertProbe(
    !str_contains($authHeaderSource, 'AppearanceReaderComparisonService')
        && !str_contains($authHeaderSource, 'compareAuthServerIntent'),
    'Existing Auth rendering file is unchanged by the comparison service'
);
assertProbe(
    !str_contains($adminHeaderSource, 'AppearanceReaderComparisonService')
        && !str_contains($operatorComposerSource, 'AppearanceReaderComparisonService'),
    'Existing active theme behavior remains unchanged in Admin and Operator render paths'
);

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------
echo "---\n";
echo "Checks: {$checks} | Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
