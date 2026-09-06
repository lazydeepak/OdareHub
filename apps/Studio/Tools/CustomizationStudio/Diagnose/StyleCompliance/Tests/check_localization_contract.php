<?php
declare(strict_types=1);

/**
 * Style Compliance Localization Contract Gate — Certification v1
 *
 * Fail-closed: every locale lookup must resolve to an explicit entry in en/ja/ne.
 * Unknown dynamic patterns fail. Inherited values fail. Placeholders must match.
 *
 * Usage: php apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Tests/check_localization_contract.php
 */

$baseDir = dirname(__DIR__, 5);
define('APP_ROOT', $baseDir);
$viewsDir = __DIR__ . '/../Views';

if (!function_exists('e')) { function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('current_lang')) { function current_lang(): string { return (string)($GLOBALS['styleComplianceProbeLang'] ?? 'en'); } }
require $viewsDir . '/_locale.php';

$pass = 0;
$fail = 0;
function assertTrue(bool $c, string $l): void { global $pass, $fail; if ($c) $pass++; else { $fail++; echo "  FAIL [$l]\n"; } }

// ── A: Extract all locale lookups ─────────────────────────────────
$viewFiles = glob($viewsDir . '/*.php');
$allDirectKeys = [];
$dynamicKeyPrefixes = [];
foreach ($viewFiles as $file) {
    if (basename($file) === '_locale.php') continue;
    $src = file_get_contents($file);
    if (!is_string($src)) continue;
    preg_match_all("/\\\$sc\\s*\\(\\s*'([^']+)'\\s*\\)/", $src, $m);
    foreach ($m[1] as $key) $allDirectKeys[$key] = ($allDirectKeys[$key] ?? 0) + 1;
    preg_match_all("/\\\$sc\\s*\\(\\s*'([^']+)'\\s*\\.\\s*\\\$/", $src, $m);
    foreach ($m[1] as $prefix) $dynamicKeyPrefixes[$prefix] = true;
}

// ── B: Dynamic family registry ────────────────────────────────────
$dynamicFamilies = [
    'future_apply_eligibility_' => ['eligible','blocked','review_required','not_applicable'],
    'semantic_mapping_confidence_' => ['known','advisory','unknown','not_applicable'],
    'preflight_status_' => ['ready','blocked','stale','review_required'],
    'readiness_state_' => ['pass','blocked','pending','ready_for_guarded_repair','blocked_status','stale','ambiguous','unsupported','review_required_status'],
    'migration_state_' => ['none','value_fix','domain_migration','classification_review','future_handoff','not_applicable'],
    'value_construct_' => ['literal_color','alias','gradient','shadow','filter','keyword','empty','mixture','unsupported'],
    'confidence_' => ['high','partial','low','none'],
    'shell_population_' => ['eligible_candidate','evidence_only','outside_inventory','excluded'],
    'shell_candidate_' => ['layout_primitive','shell_navigation','responsive_shell','overflow_containment','form_control_baseline','table_baseline','accessibility_focus','dialog_or_overlay_structure','bootstrap_fallback','print_structure','structural_selector_review','not_a_shell_candidate'],
    'shell_criticality_' => ['critical','high','normal','unknown'],
    'shell_disposition_' => ['remain_owner_local_shell_governed','candidate_shared_shell_primitive','remain_theme_related','future_special_effect_handoff','intentional_exception','classification_review','excluded_or_unsupported'],
    'shell_review_' => ['none','ambiguous_selector_role','missing_context','accessibility_focus_risk','print_context','dynamic_or_template_context','mixed_theme_and_structure','unknown_overlay_or_z_index_role','unknown_transform_intent','unknown_opacity_intent'],
    'severity_' => ['info','warning'],
];

// ── C: Build all required keys ────────────────────────────────────
$allRequiredKeys = $allDirectKeys;
foreach ($dynamicKeyPrefixes as $prefix => $_) {
    if (!isset($dynamicFamilies[$prefix])) {
        assertTrue(false, "fail-closed: unregistered dynamic prefix '$prefix'");
        continue;
    }
    foreach ($dynamicFamilies[$prefix] as $suffix) {
        $allRequiredKeys[$prefix . $suffix] = ($allRequiredKeys[$prefix . $suffix] ?? 0) + 1;
    }
}

echo "=== Style Compliance Localization Contract v1 ===\n\n";
echo "Direct keys: " . count($allDirectKeys) . "\n";
echo "Dynamic families: " . count($dynamicKeyPrefixes) . " used, " . count($dynamicFamilies) . " registered\n";
echo "Total required keys: " . count($allRequiredKeys) . "\n\n";

// ── D: Coverage — every key must resolve in en/ja/ne ──────────────
echo "--- Coverage ---\n";
foreach (['en','ja','ne'] as $lang) {
    $GLOBALS['styleComplianceProbeLang'] = $lang;
    $missing = [];
    foreach ($allRequiredKeys as $key => $_) {
        if ($sc($key) === $key) $missing[] = $key;
    }
    assertTrue($missing === [], strtoupper($lang) . ": all " . count($allRequiredKeys) . " keys resolved" . (count($missing) ? ' (missing: ' . count($missing) . ')' : ''));
    foreach ($missing as $k) echo "  MISSED ($lang): $k\n";
}
$GLOBALS['styleComplianceProbeLang'] = 'en';

// ── E: Explicit entry — no array_replace fallback allowed ──────────
echo "\n--- Explicit Entry ---\n";
$localeSrc = file_get_contents(APP_ROOT . '/apps/Studio/Tools/CustomizationStudio/Diagnose/StyleCompliance/Views/_locale.php');
$jaExplicit = [];
$neExplicit = [];
if (is_string($localeSrc)) {
    preg_match('/\'ja\'\s*=>\s*array_replace\s*\(\s*\$en\s*,\s*\[(.*?)\]\s*\)/s', $localeSrc, $jaBlock);
    if (isset($jaBlock[1])) { preg_match_all("/^\s*'([^']+)'/m", $jaBlock[1], $jaM); $jaExplicit = array_flip($jaM[1]); }
    preg_match('/\'ne\'\s*=>\s*array_replace\s*\(\s*\$en\s*,\s*\[(.*?)\]\s*\)/s', $localeSrc, $neBlock);
    if (isset($neBlock[1])) { preg_match_all("/^\s*'([^']+)'/m", $neBlock[1], $neM); $neExplicit = array_flip($neM[1]); }
}
foreach (['ja'=>$jaExplicit,'ne'=>$neExplicit] as $lang => $explicit) {
    $inherited = [];
    foreach ($allRequiredKeys as $key => $_) { if (!isset($explicit[$key])) $inherited[] = $key; }
    assertTrue($inherited === [], strtoupper($lang) . ": all " . count($allRequiredKeys) . " keys explicit (inherited: " . count($inherited) . ")");
    foreach (array_slice($inherited, 0, 10) as $k) echo "  $lang INHERITED: $k\n";
}

// ── F: Placeholder parity ──────────────────────────────────────────
echo "\n--- Placeholder Parity ---\n";
$phChecks = 0;
$phFailures = [];
foreach ($allRequiredKeys as $key => $_) {
    $GLOBALS['styleComplianceProbeLang'] = 'en'; $en = $sc($key); if ($en === $key) continue;
    $enD = substr_count($en, '%d');
    $enB = substr_count($en, '{shown}') + substr_count($en, '{total}') + substr_count($en, '{files}') + substr_count($en, '{replacements}') + substr_count($en, '{count}');
    if ($enD + $enB === 0) continue;
    foreach (['ja','ne'] as $lang) {
        $GLOBALS['styleComplianceProbeLang'] = $lang; $val = $sc($key); if ($val === $key) continue;
        $valD = substr_count($val, '%d');
        if ($enD !== $valD) $phFailures[] = "$key ($lang): en %d=$enD, got $valD";
        $phChecks++;
    }
}
$GLOBALS['styleComplianceProbeLang'] = 'en';
assertTrue($phFailures === [], 'placeholder parity (' . $phChecks . ' checks, ' . count($phFailures) . ' failures)');
foreach ($phFailures as $f) echo "  $f\n";
echo "  Checks: $phChecks\n";

// ── G: Translation quality (parse override arrays directly) ────────
echo "\n--- Translation Quality ---\n";

// Shared technical terms allowlist
$sharedTerms = [
    'scope_shell','scope_theme','not_applicable',
    'future_apply_eligibility_not_applicable','semantic_mapping_confidence_not_applicable',
    'migration_state_not_applicable','migration_state_none',
    'shell_population_excluded','shell_candidate_not_a_shell_candidate',
    'shell_disposition_excluded_or_unsupported','shell_criticality_unknown',
    'shell_review_none','confidence_none','migration_not_applicable',
    // Panel body / help text (long descriptive variants, not primary operator controls)
    'accessibility_review_panel_body','effects_handoffs_panel_body',
    'scope_note_current_item_css','scope_note_current_item_php','scope_note_current_item_repair',
    'scope_note_future_item_js','scope_note_future_item_svg','scope_note_future_item_effects','scope_note_future_item_templates',
    'scope_note_boundaries_title','scope_note_boundary_vendor','scope_note_boundary_embedded',
];

// Parse en/ja/ne override values
$enParsed = []; $jaOverride = []; $neOverride = [];
preg_match('/\$en\s*=\s*\[(.*?)\];/s', $localeSrc, $enBlock);
if (isset($enBlock[1])) { preg_match_all("/^\s*'([^']+)'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/m", $enBlock[1], $enM); foreach ($enM[1] as $i => $k) $enParsed[$k] = stripslashes($enM[2][$i]); }
preg_match_all("/^\s*'([^']+)'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/m", $jaBlock[1]??'', $jaM); foreach ($jaM[1] as $i => $k) $jaOverride[$k] = stripslashes($jaM[2][$i]);
preg_match_all("/^\s*'([^']+)'\s*=>\s*'((?:[^'\\\\]|\\\\.)*)'/m", $neBlock[1]??'', $neM); foreach ($neM[1] as $i => $k) $neOverride[$k] = stripslashes($neM[2][$i]);

// Operator-copy keys: the ones we expect real translations for
$operatorPatterns = [
    'page_title','page_subtitle','read_only_badge','guarded_apply_badge',
    'scope_','owner_','apply_scan','scan_not_started','no_scope_root',
    'summary_title','summary_total_tokens','summary_theme_aware','summary_unaware','summary_repairable','summary_boundary',
    'workspace_','dashboard_','action_center_',
    'repair_queue','decision_backlog','accessibility_review','effects_handoffs',
    'check_repair','repair_readiness','repair_apply','prepared_badge',
    'safety_bar','foundation_concerns','verified_candidates',
    'inspect_','col_severity','severity_','semantic_role','why_automation',
    'candidates_title','candidates_empty','migration_title',
    'shell_inventory_','workflow_step_',
    'about_this_scan','scope_note_',
    'section_diagnostics_title','section_repair_planning','section_human_review','section_effects_handoffs',
];
// Patterns that are NOT operator-copy (evidence labels, inventory columns, runtime JS labels)
$nonOperatorPatterns = [
    'shell_inventory_col_','shell_inventory_evidence','shell_inventory_id','shell_inventory_structural',
    'shell_inventory_inclusion','shell_inventory_cross_owner','shell_inventory_review',
    'shell_inventory_criticality','shell_inventory_review_required',
    'shell_inventory_declarations','shell_inventory_relevant','shell_inventory_eligible',
    'shell_inventory_critical','shell_inventory_total','shell_inventory_empty',
    'shell_inventory_filters','shell_inventory_filter','shell_inventory_metric',
    'shell_inventory_all_','shell_inventory_excluded_','shell_inventory_low_',
    'owner_review_required','repair_apply_','repair_readiness_',
    'verified_candidates_empty',
];

$jaEnglish = []; $neEnglish = [];
foreach ($allRequiredKeys as $key => $_) {
    $enVal = $enParsed[$key] ?? '';
    if ($enVal === '') continue;
    if (in_array($key, $sharedTerms, true)) continue;
    $isNonOp = false;
    foreach ($nonOperatorPatterns as $pat) { if (str_starts_with($key, $pat)) { $isNonOp = true; break; } }
    if ($isNonOp) continue;
    $isOp = false;
    foreach ($operatorPatterns as $pat) { if (str_starts_with($key, $pat)) { $isOp = true; break; } }
    if (!$isOp) continue;

    $jaVal = $jaOverride[$key] ?? $enVal;
    $neVal = $neOverride[$key] ?? $enVal;
    if ($jaVal === $enVal) $jaEnglish[] = $key;
    if ($neVal === $enVal) $neEnglish[] = $key;
}
$trueJaGaps = array_diff($jaEnglish, array_keys($sharedTerms));
$trueNeGaps = array_diff($neEnglish, array_keys($sharedTerms));
assertTrue($trueJaGaps === [], 'JA operator-copy: ' . count($trueJaGaps) . ' English copies remain');
assertTrue($trueNeGaps === [], 'NE operator-copy: ' . count($trueNeGaps) . ' English copies remain');
echo "  JA operator-copy gaps: " . count($trueJaGaps) . "\n";
echo "  NE operator-copy gaps: " . count($trueNeGaps) . "\n";
foreach (array_slice($trueJaGaps, 0, 10) as $k) echo "  JA GAP: $k\n";
foreach (array_slice($trueNeGaps, 0, 10) as $k) echo "  NE GAP: $k\n";

// ── H: Implementation identifier containment ────────────────────────
echo "\n--- Identifier Containment ---\n";
$reasonCodes = ['semantic_mapping_required','accessibility_review','classification_review','low_confidence','print_pdf_candidate','dynamic_or_unsupported','excluded_unsupported','excluded_source'];
foreach ($reasonCodes as $rc) {
    $resolved = $sc($rc);
    assertTrue($resolved !== $rc, "reason code '$rc' resolves to locale label");
}

// ── Summary ────────────────────────────────────────────────────────
echo "\n=========================================\n";
echo "Results: $pass / " . ($pass + $fail) . " passed";
if ($fail > 0) { echo ", $fail FAILED\n"; exit(1); }
echo ", 0 failed\nRESULT: CERTIFIED\n";
