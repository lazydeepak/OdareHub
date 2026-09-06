<?php
declare(strict_types=1);

use Apps\Studio\Tools\LocalizationScanExtraction\Services\InlineMigrationPlannerService;

require_once __DIR__ . '/../Services/InlineMigrationPlannerService.php';

$passed = 0;
$failed = 0;
$errors = [];

function assert_eq(mixed $expected, mixed $actual, string $label): bool {
    global $passed, $failed, $errors;
    if ($expected === $actual) {
        $passed++;
        return true;
    }
    $failed++;
    $errors[] = "$label: expected " . var_export($expected, true) . " got " . var_export($actual, true);
    return false;
}

// -- Setup
$ownerKey = 'Manufacturing/Coverage';
$planner = new InlineMigrationPlannerService($ownerKey);

// Build a finding helper
function finding(array $overrides = []): array {
    $base = [
        'type' => 'inline_text',
        'owner' => 'Manufacturing/Coverage',
        'file' => 'apps/Manufacturing/modules/Coverage/Views/dashboard.php',
        'line' => 42,
        'detected' => 'Total Coverage',
        'suggested_key' => 'coverage.total_coverage',
        'confidence' => 'high',
        'status' => 'pending',
        'context' => '    <h2>Total Coverage</h2>',
        'extractable' => false,
        'category' => 'human_facing_candidate',
        'semantic_category' => 'heading',
        'element_type' => 'heading',
        'relevance' => 'high',
        'occurrence_count' => 3,
        'occurrence_files' => ['apps/Manufacturing/modules/Coverage/Views/dashboard.php'],
    ];
    return array_merge($base, $overrides);
}

// =============================================
// STATE CLASSIFICATION TESTS
// =============================================

// TC01: Heading in view → ready_to_migrate
$f = finding();
$state = $planner->classifyFinding($f);
assert_eq('ready_to_migrate', $state, 'TC01: heading in view');

// TC02: Button action → ready_to_migrate
$f = finding(['detected' => 'Add Assembly Entry', 'semantic_category' => 'action', 'element_type' => 'button', 'context' => '<button>Add Assembly Entry</button>']);
$state = $planner->classifyFinding($f);
assert_eq('ready_to_migrate', $state, 'TC02: button action');

// TC03: Label text → ready_to_migrate
$f = finding(['detected' => 'Product Name', 'semantic_category' => 'label', 'element_type' => 'label']);
$state = $planner->classifyFinding($f);
assert_eq('ready_to_migrate', $state, 'TC03: label text');

// TC04: Status text → ready_to_migrate
$f = finding(['detected' => 'Pending Approval', 'semantic_category' => 'status', 'element_type' => 'span']);
$state = $planner->classifyFinding($f);
assert_eq('ready_to_migrate', $state, 'TC04: status text');

// TC05: Confirmation text → ready_to_migrate
$f = finding(['detected' => 'Are you sure?', 'semantic_category' => 'confirmation', 'element_type' => 'paragraph']);
$state = $planner->classifyFinding($f);
assert_eq('ready_to_migrate', $state, 'TC05: confirmation');

// TC06: Error message → ready_to_migrate
$f = finding(['detected' => 'Invalid input', 'semantic_category' => 'error_message', 'element_type' => 'span']);
$state = $planner->classifyFinding($f);
assert_eq('ready_to_migrate', $state, 'TC06: error message');

// TC07: Low confidence → NOT ready (needs_review)
$f = finding(['confidence' => 'low', 'detected' => 'Maybe visible', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('needs_review', $state, 'TC07: low confidence heading');

// TC08: Unknown element type → needs_review (even good cat)
$f = finding(['semantic_category' => 'heading', 'element_type' => 'unknown', 'context' => '']);
$state = $planner->classifyFinding($f);
assert_eq('needs_review', $state, 'TC08: heading unknown element');

// TC09: General semantic category (not in ready list) → needs_review
$f = finding(['detected' => 'Dashboard items', 'semantic_category' => 'general', 'element_type' => 'paragraph']);
$state = $planner->classifyFinding($f);
assert_eq('needs_review', $state, 'TC09: general category');

// TC10: Metric category → needs_review
$f = finding(['detected' => 'Total count', 'semantic_category' => 'metric', 'element_type' => 'paragraph']);
$state = $planner->classifyFinding($f);
assert_eq('needs_review', $state, 'TC10: metric category');

// TC11: Navigation → needs_review
$f = finding(['detected' => 'Go to Dashboard', 'semantic_category' => 'navigation', 'element_type' => 'link']);
$state = $planner->classifyFinding($f);
assert_eq('needs_review', $state, 'TC11: navigation');

// TC12: Contains $variable → rejected
$f = finding(['detected' => 'Hello $name', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC12: variable in text');

// TC13: Contains {curly} → rejected
$f = finding(['detected' => 'Hello {name}', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC13: curly braces');

// TC14: URL → rejected
$f = finding(['detected' => '/apps/manufacturing/dashboard', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC14: URL path');

// TC15: HTTP URL → rejected
$f = finding(['detected' => 'https://example.com/api', 'semantic_category' => 'general', 'element_type' => 'paragraph']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC15: HTTP URL');

// TC16: All uppercase technical → rejected
$f = finding(['detected' => 'API_KEY_SECRET', 'semantic_category' => 'general', 'element_type' => 'span']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC16: ALL_CAPS technical');

// TC17: Single char → rejected
$f = finding(['detected' => 'X', 'semantic_category' => 'label', 'element_type' => 'label']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC17: single char');

// TC18: Numeric only → rejected
$f = finding(['detected' => '42', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC18: numeric only');

// TC19: UpperCamelCase className → rejected
$f = finding(['detected' => 'ClassNameHere', 'semantic_category' => 'general', 'element_type' => 'span']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC19: UpperCamelCase technical');

// TC20: Non-inline_text type → rejected
$f = finding(['type' => 'loc_key_usage', 'category' => 'already_localized_usage']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC20: non-inline type');

// TC21: Short text (2 chars) → rejected
$f = finding(['detected' => 'No', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC21: two char text');

// TC22: CSS fragment → rejected
$f = finding(['detected' => ';font-weight:700;font-size:.78em;padding:3px 10px;border-radius:3px;', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC22: CSS fragment');

// TC23: Generated Btn label → rejected
$f = finding(['detected' => 'Btn Apply', 'semantic_category' => 'action', 'element_type' => 'button']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC23: generated Btn label');

// TC24: Generated Mode label → rejected
$f = finding(['detected' => 'Mode Qc', 'semantic_category' => 'label', 'element_type' => 'label']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC24: generated Mode label');

// TC25: Query string route → rejected
$f = finding(['detected' => '/apps/studio/tools/localization-scan-extraction?owner=Plugin%2FBase', 'semantic_category' => 'heading', 'element_type' => 'heading']);
$state = $planner->classifyFinding($f);
assert_eq('rejected', $state, 'TC25: route query string');

// =============================================
// KEY SUGGESTION TESTS
// =============================================

// KS01: Known button skip → common.save_action
$f = finding(['detected' => 'Save', 'semantic_category' => 'action', 'element_type' => 'button']);
$key = $planner->suggestKey($f);
assert_eq('common.save_action', $key, 'KS01: Save button → common.save_action');

// KS02: Known button Delete → common.delete_action
$f = finding(['detected' => 'Delete', 'semantic_category' => 'action', 'element_type' => 'button']);
$key = $planner->suggestKey($f);
assert_eq('common.delete_action', $key, 'KS02: Delete → common.delete_action');

// KS03: Known button Add → common.add_action
$f = finding(['detected' => 'Add', 'semantic_category' => 'action', 'element_type' => 'button']);
$key = $planner->suggestKey($f);
assert_eq('common.add_action', $key, 'KS03: Add → common.add_action');

// KS04: Save & Close → common.save_close_action
$f = finding(['detected' => 'Save & Close', 'semantic_category' => 'action', 'element_type' => 'button']);
$key = $planner->suggestKey($f);
assert_eq('common.save_close_action', $key, 'KS04: Save & Close → common.save_close_action');

// KS05: Heading becomes contextual short title
$f = finding(['detected' => 'Total Coverage', 'suggested_key' => 'coverage.total_coverage']);
$key = $planner->suggestKey($f);
assert_eq('coverage.total_coverage_title', $key, 'KS05: heading becomes contextual title');

// KS06: Custom heading without existing key → contextual title
$f = finding(['detected' => 'Assembly Progress', 'suggested_key' => '']);
$key = $planner->suggestKey($f);
assert_eq('coverage.assembly_progress_title', $key, 'KS06: new heading');

// KS07: Long text truncated
$f = finding(['detected' => 'This is a very long heading that should be truncated to fit the key length', 'suggested_key' => '']);
$key = $planner->suggestKey($f);
assert_eq(true, strlen($key) <= 60, 'KS07: long heading uses contextual key under 60');
if (strlen($key) > 60) {
    $errors[] = "KS07: key too long: $key (" . strlen($key) . " chars)";
    $failed--;
}

// =============================================
// ENGLISH VALUE TESTS
// =============================================

$f = finding(['detected' => 'Total Coverage']);
assert_eq('Total Coverage', $planner->suggestEnglishValue($f), 'EV01: exact match');

$f = finding(['detected' => '  Trimmed Text  ']);
assert_eq('Trimmed Text', $planner->suggestEnglishValue($f), 'EV02: trimmed');

// =============================================
// BUILD PLAN TESTS
// =============================================

// BP01: Mixed findings
$findings = [
    finding(['detected' => 'Total Coverage', 'semantic_category' => 'heading', 'element_type' => 'heading']),
    finding(['detected' => 'Go to Dashboard', 'semantic_category' => 'navigation', 'element_type' => 'link']),
    finding(['detected' => '/apps/coverage/test', 'semantic_category' => 'heading', 'element_type' => 'heading']),
    finding(['type' => 'loc_key_usage', 'category' => 'already_localized_usage', 'detected' => 'some.key']),
];
$plan = $planner->buildPlan($findings);
assert_eq('Manufacturing/Coverage', $plan['owner_key'], 'BP01: owner key');
assert_eq(3, $plan['total'], 'BP01: total candidates (incl rejected)');
assert_eq(1, $plan['counts']['ready_to_migrate'], 'BP01: ready count');
assert_eq(1, $plan['counts']['needs_review'], 'BP01: review count');
assert_eq(1, $plan['counts']['rejected'], 'BP01: rejected count (URL path)');

// BP01b: Unsafe/generated candidates cannot reach ready/apply list
$findings = [
    finding(['detected' => ';font-weight:700;font-size:.78em;padding:3px 10px;', 'semantic_category' => 'heading', 'element_type' => 'heading']),
    finding(['detected' => 'Btn Apply', 'semantic_category' => 'action', 'element_type' => 'button']),
    finding(['detected' => 'Mode Qc', 'semantic_category' => 'label', 'element_type' => 'label']),
    finding(['detected' => 'Safe Heading', 'semantic_category' => 'heading', 'element_type' => 'heading']),
];
$plan = $planner->buildPlan($findings);
assert_eq(1, $plan['counts']['ready_to_migrate'], 'BP01b: only safe heading is ready');
foreach (($plan['candidates'] ?? []) as $candidate) {
    if (($candidate['state'] ?? '') === 'ready_to_migrate') {
        assert_eq('Safe Heading', $candidate['text'] ?? '', 'BP01b: unsafe suggestions absent from ready list');
    }
}

// BP02: Internal strings excluded from plan
$findings = [
    finding(['detected' => 'Internal use', 'category' => 'internal_string', 'semantic_category' => 'general', 'element_type' => 'span']),
];
$plan = $planner->buildPlan($findings);
assert_eq(0, $plan['total'], 'BP02: internal strings excluded');

// BP03: Candidate structure
$findings = [
    finding(['detected' => 'Product Name', 'semantic_category' => 'label', 'element_type' => 'label', 'confidence' => 'high', 'occurrence_count' => 2, 'file' => 'test.php', 'line' => 10]),
];
$plan = $planner->buildPlan($findings);
$c = $plan['candidates'][0] ?? [];
assert_eq('Product Name', $c['text'], 'BP03: candidate text');
assert_eq('label', $c['semantic_category'], 'BP03: semantic category');
assert_eq('label', $c['element_type'], 'BP03: element type');
assert_eq(2, $c['occurrence_count'], 'BP03: occurrence count');
assert_eq('test.php', $c['file'], 'BP03: file');
assert_eq(10, $c['line'], 'BP03: line');
assert_eq('high', $c['confidence'], 'BP03: confidence');
assert_eq('excellent', $c['key_quality'], 'BP03: key quality');
assert_eq(true, isset($plan['key_quality_counts']['excellent']), 'BP03: key quality summary present');

// BP04: Long sentence receives contextual description key, not sentence-derived key
$findings = [
    finding([
        'detected' => 'Assembly execution entry form is currently submitted from the assembly plan detail workflow.',
        'file' => 'apps/Manufacturing/modules/AssemblyEntries/Views/add.php',
        'context' => '<div class="muted">Assembly execution entry form is currently submitted from the assembly plan detail workflow.</div>',
        'semantic_category' => 'help',
        'element_type' => 'div',
    ]),
];
$plan = (new InlineMigrationPlannerService('Manufacturing/AssemblyEntries'))->buildPlan($findings);
$c = $plan['candidates'][0] ?? [];
assert_eq('assembly_entries.form_description', $c['suggested_key'] ?? '', 'BP04: form sentence contextual key');
assert_eq('good', $c['key_quality'] ?? '', 'BP04: contextual description quality');

// =============================================
// SUMMARY
// =============================================
echo "InlineMigrationPlannerService Test Results:\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
if ($errors !== []) {
    echo "  Errors:\n";
    foreach ($errors as $e) {
        echo "    - $e\n";
    }
}
echo "\n";
exit($failed > 0 ? 1 : 0);
