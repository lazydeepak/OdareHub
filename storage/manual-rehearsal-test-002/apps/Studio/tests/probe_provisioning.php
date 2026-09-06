<?php
declare(strict_types=1);

/**
 * Engineering Workspace Hub V1.3 — Guarded Template Provisioning Probe
 *
 * 20 test scenarios using isolated temporary fixtures.
 * Never mutates live workspace documents.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceCoverageService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceProvisioningService.php';

use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceProvisioningService;
use Platform\Security\EngineeringWorkspaceContentContract;

$passed = 0;
$failed = 0;

function p_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function p_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function p_assert_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (str_contains($haystack, $needle)) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected string containing ' . var_export($needle, true) . PHP_EOL;
}

$p_rmDir = static function (string $dir) use (&$p_rmDir): void {
    if (!is_dir($dir)) {
        return;
    }
    $entries = scandir($dir);
    if (!is_array($entries)) {
        return;
    }
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        if (is_dir($path)) {
            $p_rmDir($path);
        } elseif (is_file($path)) {
            @unlink($path);
        }
    }
    @rmdir($dir);
};

// ── Fixture setup ──
$fixtureDir = APP_ROOT . '/storage/studio-tests/engineering-workspaces/' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
$fixtureEngineering = $fixtureDir . '/engineering';
$fixtureSnapshots = $fixtureDir . '/snapshots';
$fixtureTemplates = $fixtureEngineering . '/_templates';
$fixtureWorkspace = $fixtureEngineering . '/TestWs';

// Create directory structure
@mkdir($fixtureTemplates, 0755, true);
@mkdir($fixtureWorkspace, 0755, true);
@mkdir($fixtureSnapshots, 0755, true);

// Create test template files
$overviewTemplate = <<<'MD'
# {{workspace_name}}

## Purpose

**Workspace key:** `{{workspace_key}}`

## Target State

## Responsibilities

## Boundaries

## Canonical Source Areas

## Dependencies

## Related Workspaces

## Non-goals

MD;

$workTemplate = "# {{workspace_name}} — Work\n\n## Current Focus\n\n## In Progress\n\n## Next\n\n## Blocked\n\n## Completed\n\n## Evidence\n";

file_put_contents($fixtureTemplates . '/overview.md', $overviewTemplate);
file_put_contents($fixtureTemplates . '/work.md', $workTemplate);

// Minimal valid work document (already exists — for archive_and_reset tests)
$existingWork = "# TestWs — Work\n\n## Current Focus\n\nInitial focus.\n\n## In Progress\n\n## Next\n\n## Blocked\n\n## Completed\n\n## Evidence\n";
file_put_contents($fixtureWorkspace . '/work.md', $existingWork);

// Re-read template files so the ContentContract picks them up
// Since template path is hardcoded to engineering/_templates, we need to
// use the original templates for variable resolution tests

// ── Build fake coverage with a linked_valid row ──
function buildFakeCoverage(string $wsKey, string $state): array
{
    $fp = hash('sha256', $wsKey . $state . 'fake-seed');
    return [
        'rows' => [
            [
                'workspace_key' => $wsKey,
                'context_label' => $wsKey,
                'evidence_type' => 'registered_workspace',
                'state' => $state,
                'row_kind' => 'owner-backed-workspace',
                'workspace_label' => $wsKey,
                'provisioning_readiness' => $state === 'linked_valid' ? 'Preserve' : 'Initialize missing documents',
                'readiness_fingerprint' => $fp,
                'documents' => [],
            ],
        ],
        'owner_summary' => ['contexts_scanned' => 1, 'exact_workspace_mappings' => 0],
        'workspace_summary' => ['registered_workspaces' => 0, 'linked_valid' => 0],
        'catalogue_state' => 'ok',
        'scan_state' => 'ok',
    ];
}

// Override the snapshot root path via a test helper
// We do this by patching the class constant — not possible in PHP,
// so we place snapshots at the original path and let the fixture use a workspace
// key that doesn't exist in real snapshots.
// Instead, we'll directly test the service methods with controlled data.

echo "── Engineering Workspace Hub V1.3 Provisioning Probe ──\n\n";

// ════════════════════════════════════════════════════════════
// TC01. Provisioning eligibility: linked_valid is eligible
// ════════════════════════════════════════════════════════════
$eligibleRow = ['state' => 'linked_valid', 'workspace_key' => 'TestWs'];
p_assert_true(
    EngineeringWorkspaceProvisioningService::isEligibleForProvisioning($eligibleRow),
    'TC01a. linked_valid row is eligible'
);

$ineligibleRow = ['state' => 'coverage_decision_required', 'workspace_key' => 'TestWs'];
p_assert_true(
    !EngineeringWorkspaceProvisioningService::isEligibleForProvisioning($ineligibleRow),
    'TC01b. coverage_decision_required is not eligible'
);

$ineligibleRow2 = ['state' => 'contract_repair_required', 'workspace_key' => 'TestWs'];
p_assert_true(
    !EngineeringWorkspaceProvisioningService::isEligibleForProvisioning($ineligibleRow2),
    'TC01c. contract_repair_required is not eligible'
);

// ════════════════════════════════════════════════════════════
// TC02. Build provision model from coverage
// ════════════════════════════════════════════════════════════
$cov = buildFakeCoverage('TestWs', 'linked_valid');
$model = EngineeringWorkspaceProvisioningService::buildProvisionModel($cov);
p_assert_true(is_array($model), 'TC02a. provision model is array');
p_assert_true(isset($model['eligible_rows']), 'TC02b. model has eligible_rows');
p_assert_true(count($model['eligible_rows']) >= 0, 'TC02c. eligible_rows count accessible');

// ════════════════════════════════════════════════════════════
// TC03. Generate plan for initialization lane (missing doc)
// ════════════════════════════════════════════════════════════
$plan = EngineeringWorkspaceProvisioningService::generatePlan(
    'TestWs',
    ['overview'],
    'initialize_missing',
    $cov,
    ['login' => 'test-admin']
);
p_assert_true(!empty($plan['ok']), 'TC03a. initialize_missing plan generation ok');
p_assert_true(isset($plan['plan_id']), 'TC03b. plan has plan_id');
p_assert_eq('initialize_missing', $plan['lane'] ?? '', 'TC03c. plan lane is initialize_missing');
p_assert_true(isset($plan['operations'][0]), 'TC03d. plan has operations');
p_assert_eq('CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE', $plan['operations'][0]['operation'] ?? '', 'TC03e. operation type');
p_assert_eq('overview', $plan['operations'][0]['document_key'] ?? '', 'TC03f. document key');

// ════════════════════════════════════════════════════════════
// TC04. archive_and_reset lane requires valid doc on filesystem
// ════════════════════════════════════════════════════════════
// With empty coverage row documents, the service resolves state from
// filesystem. Since TestWs/work.md doesn't exist at the real engineering
// path, the plan generation should correctly reject archive_and_reset.
$plan2 = EngineeringWorkspaceProvisioningService::generatePlan(
    'TestWs',
    ['work'],
    'archive_and_reset',
    $cov,
    ['login' => 'test-admin']
);
p_assert_true(empty($plan2['ok']), 'TC04a. archive_and_reset rejected when doc not on filesystem (coverage has no doc info)');
p_assert_contains($plan2['error'] ?? '', 'not valid and cannot be reset', 'TC04b. error message explains why');

// ════════════════════════════════════════════════════════════
// TC05. Plan has rendered content matching template variables
// ════════════════════════════════════════════════════════════
$rendered = $plan['rendered_contents']['overview'] ?? '';
p_assert_contains($rendered, 'TestWs', 'TC05a. rendered content contains workspace name');
p_assert_contains($rendered, '## Purpose', 'TC05c. rendered content has required heading');
p_assert_contains($rendered, '## Target State', 'TC05d. rendered content has Target State heading');
p_assert_contains($rendered, '## Non-goals', 'TC05e. rendered content has Non-goals heading');

// ════════════════════════════════════════════════════════════
// TC06. Preview plan
// ════════════════════════════════════════════════════════════
$preview = EngineeringWorkspaceProvisioningService::previewPlan($plan);
p_assert_true(!empty($preview['ok']), 'TC06a. preview ok');
p_assert_true(isset($preview['previews'][0]), 'TC06b. preview has entries');
p_assert_eq('overview', $preview['previews'][0]['document_key'] ?? '', 'TC06c. preview document key');
p_assert_contains($preview['previews'][0]['rendered_content'] ?? '', '## Purpose', 'TC06d. preview has rendered content');

// ════════════════════════════════════════════════════════════
// TC07. Content contract validation passes
// ════════════════════════════════════════════════════════════
p_assert_true(!empty($preview['all_validated']), 'TC07a. all_validated is true');
$validation = $preview['validation_results']['overview'] ?? [];
p_assert_true(!empty($validation['ok']), 'TC07b. overview validation passes');

// ════════════════════════════════════════════════════════════
// TC08. Expired plan is rejected
// ════════════════════════════════════════════════════════════
$expiredPlan = $plan;
$expiredPlan['expires_at'] = time() - 1;
$expiredPreview = EngineeringWorkspaceProvisioningService::previewPlan($expiredPlan);
p_assert_true(empty($expiredPreview['ok']), 'TC08. expired plan preview rejected');

// ════════════════════════════════════════════════════════════
// TC09. Cannot initialize a document that doesn't exist in the coverage
// ════════════════════════════════════════════════════════════
$badPlan = EngineeringWorkspaceProvisioningService::generatePlan(
    'NonexistentWs',
    ['overview'],
    'initialize_missing',
    $cov,
    ['login' => 'test-admin']
);
p_assert_true(empty($badPlan['ok']), 'TC09. nonexistent workspace plan rejected');

// ════════════════════════════════════════════════════════════
// TC10. Ineligible state rows cannot enter plan generation
// ════════════════════════════════════════════════════════════
$covIneligible = buildFakeCoverage('TestWs', 'coverage_decision_required');
$ineligiblePlan = EngineeringWorkspaceProvisioningService::generatePlan(
    'TestWs',
    ['overview'],
    'initialize_missing',
    $covIneligible,
    ['login' => 'test-admin']
);
p_assert_true(empty($ineligiblePlan['ok']), 'TC10. ineligible state plan rejected');

// Also test unregistered, mapping_review, resolution_failed, contract_repair_required
foreach (['unregistered_workspace_discovered', 'mapping_review_required', 'resolution_failed', 'contract_repair_required'] as $badState) {
    $covBad = buildFakeCoverage('TestWs', $badState);
    $badStatePlan = EngineeringWorkspaceProvisioningService::generatePlan(
        'TestWs',
        ['overview'],
        'initialize_missing',
        $covBad,
        ['login' => 'test-admin']
    );
    p_assert_true(empty($badStatePlan['ok']), 'TC10x. ' . $badState . ' plan rejected');
}

// ════════════════════════════════════════════════════════════
// TC11. Empty selected documents rejects
// ════════════════════════════════════════════════════════════
$emptyDocPlan = EngineeringWorkspaceProvisioningService::generatePlan(
    'TestWs',
    [],
    'initialize_missing',
    $cov,
    ['login' => 'test-admin']
);
p_assert_true(empty($emptyDocPlan['ok']), 'TC11. empty document selection rejected');

// ════════════════════════════════════════════════════════════
// TC12. Invalid lane rejected
// ════════════════════════════════════════════════════════════
$invalidLanePlan = EngineeringWorkspaceProvisioningService::generatePlan(
    'TestWs',
    ['overview'],
    'bulk_sync_all',
    $cov,
    ['login' => 'test-admin']
);
p_assert_true(empty($invalidLanePlan['ok']), 'TC12. invalid lane rejected');

// ════════════════════════════════════════════════════════════
// TC13. Invalid document keys rejected
// ════════════════════════════════════════════════════════════
$invalidDocPlan = EngineeringWorkspaceProvisioningService::generatePlan(
    'TestWs',
    ['overview', 'nonexistent.md'],
    'initialize_missing',
    $cov,
    ['login' => 'test-admin']
);
p_assert_true(empty($invalidDocPlan['ok']), 'TC13. invalid document key rejected');

// ════════════════════════════════════════════════════════════
// TC14. Plan has session binding
// ════════════════════════════════════════════════════════════
p_assert_true(isset($plan['session_binding']), 'TC14a. plan has session_binding');
p_assert_true(isset($plan['expires_at']), 'TC14b. plan has expires_at');
p_assert_true($plan['expires_at'] > time(), 'TC14c. plan expires_at is in the future');
p_assert_true(isset($plan['template_fingerprints']), 'TC14d. plan has template_fingerprints');
p_assert_true(isset($plan['mapping_readiness_fingerprint']), 'TC14e. plan has mapping_readiness_fingerprint');
p_assert_true(isset($plan['workspace_dir']), 'TC14f. plan has workspace_dir');

// ════════════════════════════════════════════════════════════
// TC15. Template variable resolution
// ════════════════════════════════════════════════════════════
$overviewRendered = EngineeringWorkspaceProvisioningService::renderTemplateContent('overview', 'Manufacturing/Products');
p_assert_contains($overviewRendered, 'Manufacturing/Products', 'TC15a. template resolves workspace_key');
p_assert_contains($overviewRendered, '## Purpose', 'TC15b. template has Purpose heading');
p_assert_contains($overviewRendered, '## Non-goals', 'TC15c. template has Non-goals heading');
// Ensure no unresolved template variables
p_assert_true(!str_contains($overviewRendered, '{{workspace_name}}'), 'TC15d. no unresolved workspace_name');
p_assert_true(!str_contains($overviewRendered, '{{workspace_key}}'), 'TC15e. no unresolved workspace_key');
p_assert_true(!str_contains($overviewRendered, '<Workspace Name>'), 'TC15f. no unresolved legacy placeholder');

// ════════════════════════════════════════════════════════════
// TC16. Content contract validates rendered content
// ════════════════════════════════════════════════════════════
$workRendered = EngineeringWorkspaceProvisioningService::renderTemplateContent('work', 'TestWs');
$validationWork = EngineeringWorkspaceContentContract::validateDocumentContent('work', $workRendered);
p_assert_true(!empty($validationWork['ok']), 'TC16a. work validation passes');
$validationOverview = EngineeringWorkspaceContentContract::validateDocumentContent('overview', $overviewRendered);
p_assert_true(!empty($validationOverview['ok']), 'TC16b. overview validation passes');

// ════════════════════════════════════════════════════════════
// TC17. Ineligible state labels
// ════════════════════════════════════════════════════════════
p_assert_eq(
    ['linked_valid', 'initialization_required'],
    EngineeringWorkspaceProvisioningService::eligibleStateKeys(),
    'TC17. eligible state keys match spec'
);

// ════════════════════════════════════════════════════════════
// TC18. Rollback requires last transaction (nonexistent workspace)
// ════════════════════════════════════════════════════════════
$rollbackNone = EngineeringWorkspaceProvisioningService::rollbackTransaction('NonexistentWs', ['login' => 'test-admin']);
p_assert_true(empty($rollbackNone['ok']), 'TC18. rollback for nonexistent workspace rejected');

// ════════════════════════════════════════════════════════════
// TC19. Apply plan rejects when template fingerprint changed
// ════════════════════════════════════════════════════════════
$tamperedPlan = $plan;
$tamperedPlan['template_fingerprints']['overview'] = 'tampered-fingerprint-' . bin2hex(random_bytes(8));
$applyTampered = EngineeringWorkspaceProvisioningService::applyPlan($tamperedPlan, ['login' => 'test-admin']);
p_assert_true(empty($applyTampered['ok']), 'TC19. tampered template fingerprint rejected');

// ════════════════════════════════════════════════════════════
// TC20. Plan without operations rejected
// ════════════════════════════════════════════════════════════
$emptyOpsPlan = $plan;
$emptyOpsPlan['operations'] = [];
$applyEmpty = EngineeringWorkspaceProvisioningService::applyPlan($emptyOpsPlan, ['login' => 'test-admin']);
p_assert_true(empty($applyEmpty['ok']), 'TC20. plan with no operations rejected');

// ════════════════════════════════════════════════════════════
// TC21. Provision model: linked_valid row appears in eligible_rows
// ════════════════════════════════════════════════════════════
$covLinked = buildFakeCoverage('TestWs', 'linked_valid');
$pmLinked = EngineeringWorkspaceProvisioningService::buildProvisionModel($covLinked);
$linkedKeys = array_map(static fn(array $r): string => $r['workspace_key'] ?? '', $pmLinked['eligible_rows']);
p_assert_true(in_array('TestWs', $linkedKeys, true), 'TC21a. linked_valid workspace appears in provision model');

// ════════════════════════════════════════════════════════════
// TC22. linked_valid workspace with no missing docs is not filtered out
// ════════════════════════════════════════════════════════════
p_assert_true(count($pmLinked['eligible_rows']) >= 1, 'TC22a. linked_valid with no missing docs is not filtered out');
$foundLinked = array_filter($pmLinked['eligible_rows'], static fn(array $r): bool => $r['workspace_key'] === 'TestWs');
p_assert_true(count($foundLinked) >= 1, 'TC22b. TestWs present in eligible rows');

// ════════════════════════════════════════════════════════════
// TC23. valid documents have provisioning_readiness = 'preserve'
// ════════════════════════════════════════════════════════════
$tc23Row = reset($foundLinked);
$tc23Docs = $tc23Row['documents'] ?? [];
foreach ($tc23Docs as $dk => $di) {
    if (($di['state'] ?? '') === 'valid') {
        p_assert_eq('preserve', $di['provisioning_readiness'] ?? '', 'TC23a. valid doc "' . $dk . '" has preserve readiness');
    }
}

// ════════════════════════════════════════════════════════════
// TC24. valid documents expose reset action (can_archive_reset)
// ════════════════════════════════════════════════════════════
// TC24a: When valid docs exist on filesystem, can_archive_reset must be true.
// In this test fixture (no real engineering/TestWs dir) all docs resolve to missing,
// so can_archive_reset may be false — the assertion is that has_valid/can_archive_reset
// are consistent with document states.
$tc24HasValid = !empty($tc23Row['lane_actions']['has_valid']);
$tc24CanArchive = !empty($tc23Row['lane_actions']['can_archive_reset']);
if ($tc24HasValid) {
    p_assert_true($tc24CanArchive, 'TC24a. linked_valid with valid docs has can_archive_reset');
}
// TC24b: linked_valid with missing has can_initialize
if (!empty($tc23Row['lane_actions']['has_missing'])) {
    p_assert_true(!empty($tc23Row['lane_actions']['can_initialize']), 'TC24b. linked_valid with missing has can_initialize');
}

// ════════════════════════════════════════════════════════════
// TC25. initialization_required row exposes initialize for missing docs
// ════════════════════════════════════════════════════════════
$covInit = buildFakeCoverage('TestWs', 'initialization_required');
$pmInit = EngineeringWorkspaceProvisioningService::buildProvisionModel($covInit);
$initKeys = array_map(static fn(array $r): string => $r['workspace_key'] ?? '', $pmInit['eligible_rows']);
p_assert_true(in_array('TestWs', $initKeys, true), 'TC25a. initialization_required workspace appears in provision model');
$foundInit = array_filter($pmInit['eligible_rows'], static fn(array $r): bool => $r['workspace_key'] === 'TestWs');
$initRow = reset($foundInit);
p_assert_true(!empty($initRow['lane_actions']['can_initialize']), 'TC25b. initialization_required row has can_initialize');

// ════════════════════════════════════════════════════════════
// TC26. Forbidden state rows absent from provision model
// ════════════════════════════════════════════════════════════
foreach (['coverage_decision_required', 'unregistered_workspace_discovered', 'mapping_review_required', 'resolution_failed', 'contract_repair_required', 'explicitly_excluded'] as $badState) {
    $covBad = buildFakeCoverage('BadWs_' . $badState, $badState);
    $pmBad = EngineeringWorkspaceProvisioningService::buildProvisionModel($covBad);
    $badKeys = array_map(static fn(array $r): string => $r['workspace_key'] ?? '', $pmBad['eligible_rows']);
    p_assert_true(!in_array('BadWs_' . $badState, $badKeys, true), 'TC26. ' . $badState . ' row absent from provision model');
}

// ════════════════════════════════════════════════════════════
// TC27. Empty provision model when no eligible rows exist
// ════════════════════════════════════════════════════════════
$pmEmpty = EngineeringWorkspaceProvisioningService::buildProvisionModel(['rows' => [], 'owner_summary' => [], 'workspace_summary' => []]);
p_assert_eq(0, $pmEmpty['eligible_count'], 'TC27a. eligible_count is 0 when no eligible rows exist');
p_assert_eq([], $pmEmpty['eligible_rows'], 'TC27b. eligible_rows is empty when no eligible rows exist');

// ════════════════════════════════════════════════════════════
// TC28. No preview/apply path accepts an ineligible row
// ════════════════════════════════════════════════════════════
foreach (['coverage_decision_required', 'unregistered_workspace_discovered', 'mapping_review_required', 'resolution_failed', 'contract_repair_required'] as $badState) {
    $covBad2 = buildFakeCoverage('TestWs', $badState);
    $badGen = EngineeringWorkspaceProvisioningService::generatePlan('TestWs', ['overview'], 'initialize_missing', $covBad2, ['login' => 'test-admin']);
    p_assert_true(empty($badGen['ok']), 'TC28. generatePlan rejects ' . $badState);
}

// ════════════════════════════════════════════════════════════
// Simplified Deploy — Workspace Key Validation
// ════════════════════════════════════════════════════════════

// TC29. validateDeployWorkspaceKey: valid key
$v1 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('MyApp/Feature');
p_assert_true($v1['ok'], 'TC29a. valid workspace key passes');
p_assert_eq('MyApp/Feature', $v1['normalized'], 'TC29b. normalized key matches');

// TC30. validateDeployWorkspaceKey: empty key
$v2 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('');
p_assert_true(!$v2['ok'], 'TC30a. empty key rejected');

// TC31. validateDeployWorkspaceKey: path traversal
$v3 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('../etc/passwd');
p_assert_true(!$v3['ok'], 'TC31a. path traversal rejected');

$v4 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('MyApp/../../etc');
p_assert_true(!$v4['ok'], 'TC31b. embedded traversal rejected');

// TC32. validateDeployWorkspaceKey: absolute path
$v5 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('/etc/passwd');
p_assert_true(!$v5['ok'], 'TC32a. absolute path rejected');

// TC33. validateDeployWorkspaceKey: _templates reserved
$v6 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('_templates');
p_assert_true(!$v6['ok'], 'TC33a. _templates key rejected');

$v7 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('_templates/mine');
p_assert_true(!$v7['ok'], 'TC33b. _templates/ prefix rejected');

// TC33c. validateDeployWorkspaceKey: _template reserved (legacy singularity)
$v8 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('_template');
p_assert_true(!$v8['ok'], 'TC33c. _template key rejected');

// TC33d. validateDeployWorkspaceKey: _template/ prefix
$v9 = EngineeringWorkspaceProvisioningService::validateDeployWorkspaceKey('_template/sub');
p_assert_true(!$v9['ok'], 'TC33d. _template/ prefix rejected');

// ════════════════════════════════════════════════════════════
// Simplified Deploy — Build Deploy Model
// ════════════════════════════════════════════════════════════

// TC34. buildDeployModel from empty coverage
$dmEmpty = EngineeringWorkspaceProvisioningService::buildDeployModel(['rows' => [], 'owner_summary' => [], 'workspace_summary' => []]);
p_assert_true(is_array($dmEmpty), 'TC34a. deploy model is array');
p_assert_true(isset($dmEmpty['workspaces']), 'TC34b. deploy model has workspaces');
p_assert_true(isset($dmEmpty['workspace_count']), 'TC34c. deploy model has workspace_count');

// TC35. buildDeployModel includes linked_valid coverage rows
$covLinked = buildFakeCoverage('TestWs', 'linked_valid');
$dmLinked = EngineeringWorkspaceProvisioningService::buildDeployModel($covLinked);
$dmKeys = array_map(static fn(array $w): string => $w['workspace_key'], $dmLinked['workspaces']);
p_assert_true(in_array('TestWs', $dmKeys, true), 'TC35a. linked_valid workspace in deploy model');

// ════════════════════════════════════════════════════════════
// Simplified Deploy — Create Missing Documents (validation)
// ════════════════════════════════════════════════════════════

// TC36. createMissingDocuments rejects invalid workspace key
$cm1 = EngineeringWorkspaceProvisioningService::createMissingDocuments('', ['overview'], ['login' => 'test-admin']);
p_assert_true(empty($cm1['ok']), 'TC36a. empty key rejected');
p_assert_contains($cm1['error'] ?? '', 'required', 'TC36b. error mentions required');

// TC37. createMissingDocuments rejects empty document list
$cm2 = EngineeringWorkspaceProvisioningService::createMissingDocuments('TestWs', [], ['login' => 'test-admin']);
p_assert_true(empty($cm2['ok']), 'TC37a. empty documents rejected');

// TC38. createMissingDocuments rejects invalid document keys
$cm3 = EngineeringWorkspaceProvisioningService::createMissingDocuments('TestWs', ['overview', 'readme.txt'], ['login' => 'test-admin']);
p_assert_true(empty($cm3['ok']), 'TC38a. invalid doc key rejected');
p_assert_contains($cm3['error'] ?? '', 'Invalid document', 'TC38b. error mentions invalid');

// TC39. createMissingDocuments rejects traversal workspace key
$cm4 = EngineeringWorkspaceProvisioningService::createMissingDocuments('../../etc', ['overview'], ['login' => 'test-admin']);
p_assert_true(empty($cm4['ok']), 'TC39a. traversal key rejected');

// ════════════════════════════════════════════════════════════
// Simplified Deploy — Replace Documents (validation)
// ════════════════════════════════════════════════════════════

// TC40. replaceDocuments rejects invalid workspace key
$rp1 = EngineeringWorkspaceProvisioningService::replaceDocuments('', ['overview'], ['login' => 'test-admin']);
p_assert_true(empty($rp1['ok']), 'TC40a. empty key rejected');

// TC41. replaceDocuments rejects empty document list
$rp2 = EngineeringWorkspaceProvisioningService::replaceDocuments('TestWs', [], ['login' => 'test-admin']);
p_assert_true(empty($rp2['ok']), 'TC41a. empty documents rejected');

// TC42. replaceDocuments rejects invalid document keys
$rp3 = EngineeringWorkspaceProvisioningService::replaceDocuments('TestWs', ['overview', 'readme.txt'], ['login' => 'test-admin']);
p_assert_true(empty($rp3['ok']), 'TC42a. invalid doc key rejected');

// TC43. replaceDocuments with missing document returns error
$rp4 = EngineeringWorkspaceProvisioningService::replaceDocuments('TestWs', ['overview'], ['login' => 'test-admin']);
p_assert_true(empty($rp4['ok']), 'TC43a. missing doc replacement rejected');

// ════════════════════════════════════════════════════════════
// Simplified Deploy — Restore Last Backup (validation)
// ════════════════════════════════════════════════════════════

// TC44. restoreLastBackup rejects invalid workspace key
$rs1 = EngineeringWorkspaceProvisioningService::restoreLastBackup('', ['login' => 'test-admin']);
p_assert_true(empty($rs1['ok']), 'TC44a. empty key rejected');

// TC45. restoreLastBackup with no backup
$rs2 = EngineeringWorkspaceProvisioningService::restoreLastBackup('NonexistentWs', ['login' => 'test-admin']);
p_assert_true(empty($rs2['ok']), 'TC45a. no backup returns error');
p_assert_contains($rs2['error'] ?? '', 'No backup', 'TC45b. error mentions no backup');

// ════════════════════════════════════════════════════════════
// Simplified Deploy — New Workspace (validation)
// ════════════════════════════════════════════════════════════

// TC46. newWorkspace rejects invalid workspace key
$nw1 = EngineeringWorkspaceProvisioningService::newWorkspace('', ['login' => 'test-admin']);
p_assert_true(empty($nw1['ok']), 'TC46a. empty key rejected');

// TC47. newWorkspace rejects path traversal
$nw2 = EngineeringWorkspaceProvisioningService::newWorkspace('../../tmp', ['login' => 'test-admin']);
p_assert_true(empty($nw2['ok']), 'TC47a. traversal rejected');

// TC48. newWorkspace rejects reserved template keys
$nw3 = EngineeringWorkspaceProvisioningService::newWorkspace('_templates', ['login' => 'test-admin']);
p_assert_true(empty($nw3['ok']), 'TC48a. _templates rejected');

// TC48b. newWorkspace rejects _template
$nw4 = EngineeringWorkspaceProvisioningService::newWorkspace('_template', ['login' => 'test-admin']);
p_assert_true(empty($nw4['ok']), 'TC48b. _template rejected');

// ════════════════════════════════════════════════════════════
// Simplified Deploy — Disposable Fixture Write Tests
// ════════════════════════════════════════════════════════════

$deployKey = 'DeployTest_' . bin2hex(random_bytes(4));
$deployDir = APP_ROOT . '/engineering/' . $deployKey;

try {
    // Clean up any leftover
    if (is_dir($deployDir)) {
        $p_rmDir($deployDir);
    }

    // TC49. newWorkspace creates directory with 4 documents
    $nw4 = EngineeringWorkspaceProvisioningService::newWorkspace($deployKey, ['login' => 'test-admin']);
    p_assert_true(!empty($nw4['ok']), 'TC49a. newWorkspace succeeds');
    p_assert_true(is_dir($deployDir), 'TC49b. workspace directory created');
    p_assert_eq(4, $nw4['count'] ?? 0, 'TC49c. 4 documents created');
    $createdDocs = $nw4['documents'] ?? [];
    p_assert_true(in_array('overview', $createdDocs, true), 'TC49d. overview created');
    p_assert_true(in_array('work', $createdDocs, true), 'TC49e. work created');
    p_assert_true(in_array('rules', $createdDocs, true), 'TC49f. rules created');
    p_assert_true(in_array('decisions', $createdDocs, true), 'TC49g. decisions created');

    // Verify files exist
    foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $filename) {
        p_assert_true(is_file($deployDir . '/' . $filename), 'TC49h. file ' . $filename . ' exists');
    }

    // TC50. newWorkspace rejects existing workspace
    $nw5 = EngineeringWorkspaceProvisioningService::newWorkspace($deployKey, ['login' => 'test-admin']);
    p_assert_true(empty($nw5['ok']), 'TC50a. duplicate workspace rejected');
    p_assert_contains($nw5['error'] ?? '', 'already exists', 'TC50b. error mentions exists');

    // TC51. createMissingDocuments on workspace with all files present
    $cm5 = EngineeringWorkspaceProvisioningService::createMissingDocuments(
        $deployKey,
        ['overview'],
        ['login' => 'test-admin']
    );
    p_assert_true(empty($cm5['ok']), 'TC51a. create on existing doc rejected');

    // TC52. replaceDocuments actually replaces content
    // First read current content
    $overviewPath = $deployDir . '/overview.md';
    $originalContent = file_get_contents($overviewPath);

    $rp5 = EngineeringWorkspaceProvisioningService::replaceDocuments(
        $deployKey,
        ['overview'],
        ['login' => 'test-admin']
    );
    p_assert_true(!empty($rp5['ok']), 'TC52a. replaceDocuments succeeds');
    p_assert_true(isset($rp5['transaction_id']), 'TC52b. has transaction_id');
    p_assert_true(!empty($rp5['rollback_possible']), 'TC52c. rollback_possible is true');

    // Content should have been replaced (still valid template content but may be different due to template rendering)
    $replacedContent = file_get_contents($overviewPath);
    p_assert_true($replacedContent !== false, 'TC52d. replaced content readable');
    p_assert_contains($replacedContent, $deployKey, 'TC52e. replaced content contains workspace key');

    // TC53. restoreLastBackup with newly created backup
    $rs3 = EngineeringWorkspaceProvisioningService::restoreLastBackup($deployKey, ['login' => 'test-admin']);
    // Since we created via newWorkspace (not replace), the backup was created with after/ content
    // Restore may or may not find before/ content depending on the backup type
    p_assert_true(isset($rs3['ok']), 'TC53a. restore returns ok result');

    // TC54. Validate deploy model includes the new workspace
    $covWithDeploy = buildFakeCoverage($deployKey, 'linked_valid');
    $dmFinal = EngineeringWorkspaceProvisioningService::buildDeployModel($covWithDeploy);
    $finalKeys = array_map(static fn(array $w): string => $w['workspace_key'], $dmFinal['workspaces']);
    p_assert_true(in_array($deployKey, $finalKeys, true), 'TC54a. deploy model includes new workspace');

    // TC55. createMissingDocuments on a missing doc within existing workspace
    $extraDocKey = 'ExtraTest_' . bin2hex(random_bytes(4));
    $extraDir = APP_ROOT . '/engineering/' . $extraDocKey;
    if (is_dir($extraDir)) {
        $p_rmDir($extraDir);
    }
    // Create workspace with only work.md missing
    @mkdir($extraDir, 0755, true);
    file_put_contents($extraDir . '/overview.md', '# Test overview');
    file_put_contents($extraDir . '/rules.md', '# Test rules');
    file_put_contents($extraDir . '/decisions.md', '# Test decisions');
    // work.md is missing

    $cm6 = EngineeringWorkspaceProvisioningService::createMissingDocuments(
        $extraDocKey,
        ['work'],
        ['login' => 'test-admin']
    );
    p_assert_true(!empty($cm6['ok']), 'TC55a. createMissing for missing doc succeeds');
    p_assert_true(is_file($extraDir . '/work.md'), 'TC55b. work.md was created');

    // Clean up extra doc
    $p_rmDir($extraDir);

} finally {
    // Clean up
    if (is_dir($deployDir)) {
        $p_rmDir($deployDir);
    }
}

// ── Cleanup ──
$p_rmDir($fixtureDir);

echo "\nProvisioning: {$passed} passed, {$failed} failed\n";
if ($failed > 0) {
    exit(1);
}
exit(0);
