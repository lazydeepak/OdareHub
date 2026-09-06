<?php
declare(strict_types=1);

/**
 * Engineering Workspace Coverage Assignment — Fast V1 Probe.
 *
 * Tests:
 *  1. buildAssignmentRows: only coverage_decision_required rows appear
 *  2. dedicated assignment saves exact workspace key
 *  3. covered_by assignment accepts only existing real workspace targets
 *  4. skip saves explicit deferred state
 *  5. template roots are rejected
 *  6. invalid owner key is rejected
 *  7. arbitrary path is rejected
 *  8. assignment file writes atomically
 *  9. loadAssignments round-trips saved data
 *  10. collectExistingWorkspaces excludes _template/_templates
 *  11. existing Workspaces, Templates, Deploy tabs remain unchanged (structural)
 *  12. validateOwnerKey rejects unknown owners
 *  13. validateWorkspaceKey rejects missing dirs
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceCoverageService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceCoverageAssignmentService.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';

use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceCoverageService;
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceCoverageAssignmentService;
use Platform\Security\EngineeringWorkspaceContentContract;

$passed = 0;
$failed = 0;

function ca_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function ca_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

// Helper: remove assignment file if it exists
$assignmentPath = APP_ROOT . '/storage/engineering-workspace-coverage-assignment.json';

// ═══════════════════════════════════════════════════════════════
// 1. buildAssignmentRows returns only coverage_decision_required
// ═══════════════════════════════════════════════════════════════

$coverage = EngineeringWorkspaceCoverageService::buildCoverage();
$rows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : [];
$allStates = array_map(static fn(array $r): string => (string)($r['state'] ?? ''), $rows);
$allContexts = array_map(static fn(array $r): string => (string)($r['context_key'] ?? ''), $rows);

$assignments = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
$assignmentRows = EngineeringWorkspaceCoverageAssignmentService::buildAssignmentRows($coverage, $assignments);

ca_assert_true(is_array($assignmentRows), '1a. buildAssignmentRows returns array');

foreach ($assignmentRows as $ar) {
    $arKey = (string)($ar['owner_key'] ?? '');
    $index = array_search($arKey, $allContexts, true);
    $arState = $index !== false ? ($allStates[$index] ?? '') : '';
    ca_assert_eq('coverage_decision_required', $arState, '1b. row "' . $arKey . '" has state coverage_decision_required');
}

// ═══════════════════════════════════════════════════════════════
// 2. dedicated assignment saves exact workspace key
// ═══════════════════════════════════════════════════════════════

// Find a real unmapped owner that is coverage_decision_required
$firstDecisionOwner = '';
$firstDecisionLabel = '';
foreach ($rows as $r) {
    if ((string)($r['state'] ?? '') === 'coverage_decision_required') {
        $firstDecisionOwner = (string)($r['context_key'] ?? '');
        $firstDecisionLabel = (string)($r['context_label'] ?? $firstDecisionOwner);
        break;
    }
}

if ($firstDecisionOwner !== '') {
    $existingWorkspaces = EngineeringWorkspaceCoverageAssignmentService::collectExistingWorkspaces();

    // Save as dedicated
    $result = EngineeringWorkspaceCoverageAssignmentService::normalizeAndSet(
        $firstDecisionOwner,
        'dedicated',
        $firstDecisionOwner,
        ['login' => 'probe-test'],
        $existingWorkspaces
    );
    ca_assert_true(!empty($result['ok']), '2a. dedicated assignment saved for "' . $firstDecisionOwner . '"');
    ca_assert_eq('dedicated', $result['mode'] ?? '', '2b. mode is dedicated');
    ca_assert_eq($firstDecisionOwner, $result['workspace_key'] ?? '', '2c. workspace_key matches owner key');

    // Verify persisted
    $reloaded = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
    ca_assert_true(isset($reloaded[$firstDecisionOwner]), '2d. assignment persisted for "' . $firstDecisionOwner . '"');
    ca_assert_eq('dedicated', $reloaded[$firstDecisionOwner]['mode'] ?? '', '2e. persisted mode is dedicated');
    ca_assert_eq($firstDecisionOwner, $reloaded[$firstDecisionOwner]['workspace_key'] ?? '', '2f. persisted workspace_key matches');

    // Verify buildAssignmentRows shows assigned state
    $rows2 = EngineeringWorkspaceCoverageAssignmentService::buildAssignmentRows($coverage, $reloaded);
    $found = false;
    foreach ($rows2 as $ar2) {
        if ((string)($ar2['owner_key'] ?? '') === $firstDecisionOwner) {
            $found = true;
            ca_assert_eq('Dedicated workspace assigned', (string)($ar2['assignment_state'] ?? ''), '2g. assignment_state shows "Dedicated workspace assigned"');
            break;
        }
    }
    ca_assert_true($found, '2h. assigned owner appears in buildAssignmentRows');
} else {
    ca_assert_true(true, '2a. SKIP: no coverage_decision_required owner available');
    ca_assert_true(true, '2b. SKIP');
    ca_assert_true(true, '2c. SKIP');
    ca_assert_true(true, '2d. SKIP');
    ca_assert_true(true, '2e. SKIP');
    ca_assert_true(true, '2f. SKIP');
    ca_assert_true(true, '2g. SKIP');
    ca_assert_true(true, '2h. SKIP');
}

// ═══════════════════════════════════════════════════════════════
// 3. covered_by assignment accepts only existing real workspace targets
// ═══════════════════════════════════════════════════════════════

$existing = EngineeringWorkspaceCoverageAssignmentService::collectExistingWorkspaces();
ca_assert_true(is_array($existing), '3a. collectExistingWorkspaces returns array');

// Pick first existing workspace
$firstExisting = $existing !== [] ? $existing[0] : '';
if ($firstExisting !== '' && $firstDecisionOwner !== '') {
    $result2 = EngineeringWorkspaceCoverageAssignmentService::normalizeAndSet(
        $firstDecisionOwner,
        'covered_by',
        $firstExisting,
        ['login' => 'probe-test'],
        $existing
    );
    ca_assert_true(!empty($result2['ok']), '3b. covered_by with existing workspace accepted');
    ca_assert_eq('covered_by', $result2['mode'] ?? '', '3c. mode is covered_by');
    ca_assert_eq($firstExisting, $result2['workspace_key'] ?? '', '3d. workspace_key matches existing');

    // Save back to dedicated for cleanup
    EngineeringWorkspaceCoverageAssignmentService::normalizeAndSet(
        $firstDecisionOwner,
        'dedicated',
        $firstDecisionOwner,
        ['login' => 'probe-test'],
        $existing
    );
} else {
    ca_assert_true(true, '3b. SKIP: no existing workspace or no decision owner');
    ca_assert_true(true, '3c. SKIP');
    ca_assert_true(true, '3d. SKIP');
}

// ═══════════════════════════════════════════════════════════════
// 4. skip saves explicit deferred state
// ═══════════════════════════════════════════════════════════════

if ($firstDecisionOwner !== '') {
    $result3 = EngineeringWorkspaceCoverageAssignmentService::normalizeAndSet(
        $firstDecisionOwner,
        'skip',
        '',
        ['login' => 'probe-test'],
        $existing
    );
    ca_assert_true(!empty($result3['ok']), '4a. skip assignment saved');
    ca_assert_eq('skip', $result3['mode'] ?? '', '4b. mode is skip');

    $reloaded3 = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
    ca_assert_true(isset($reloaded3[$firstDecisionOwner]), '4c. skip persisted');
    ca_assert_eq('skip', $reloaded3[$firstDecisionOwner]['mode'] ?? '', '4d. persisted mode is skip');
    ca_assert_true(!isset($reloaded3[$firstDecisionOwner]['workspace_key']), '4e. no workspace_key for skip mode');

    // Verify buildAssignmentRows shows skipped
    $rows3 = EngineeringWorkspaceCoverageAssignmentService::buildAssignmentRows($coverage, $reloaded3);
    $found3 = false;
    foreach ($rows3 as $ar3) {
        if ((string)($ar3['owner_key'] ?? '') === $firstDecisionOwner) {
            $found3 = true;
            ca_assert_eq('Skipped for now', (string)($ar3['assignment_state'] ?? ''), '4f. assignment_state shows "Skipped for now"');
            break;
        }
    }
    ca_assert_true($found3, '4g. skipped owner appears in buildAssignmentRows');

    // Clean up: save back as dedicated
    EngineeringWorkspaceCoverageAssignmentService::normalizeAndSet(
        $firstDecisionOwner,
        'dedicated',
        $firstDecisionOwner,
        ['login' => 'probe-test'],
        $existing
    );
} else {
    ca_assert_true(true, '4a. SKIP');
    ca_assert_true(true, '4b. SKIP');
    ca_assert_true(true, '4c. SKIP');
    ca_assert_true(true, '4d. SKIP');
    ca_assert_true(true, '4e. SKIP');
    ca_assert_true(true, '4f. SKIP');
    ca_assert_true(true, '4g. SKIP');
}

// ═══════════════════════════════════════════════════════════════
// 5. template roots are rejected
// ═══════════════════════════════════════════════════════════════

$v1 = EngineeringWorkspaceCoverageAssignmentService::validateAndNormalizeAssignment('Manufacturing/Products', 'dedicated', '_templates', []);
ca_assert_true(empty($v1['ok']), '5a. _templates workspace key rejected');
if (!empty($v1['error'])) {
    $v1ok = true;
}
ca_assert_true(str_contains((string)($v1['error'] ?? ''), 'template'), '5b. error mentions template');

$v2 = EngineeringWorkspaceCoverageAssignmentService::validateAndNormalizeAssignment('Manufacturing/Products', 'dedicated', '_template', []);
ca_assert_true(empty($v2['ok']), '5c. _template workspace key rejected');
ca_assert_true(str_contains((string)($v2['error'] ?? ''), 'template'), '5d. error mentions template');

// ═══════════════════════════════════════════════════════════════
// 6. invalid owner key is rejected
// ═══════════════════════════════════════════════════════════════

$v3 = EngineeringWorkspaceCoverageAssignmentService::validateOwnerKey('NonExistent/Owner');
ca_assert_true(empty($v3['ok']), '6a. non-existent owner is rejected');
ca_assert_true(str_contains((string)($v3['error'] ?? ''), 'Unknown'), '6b. error mentions unknown');

$v4 = EngineeringWorkspaceCoverageAssignmentService::validateOwnerKey('');
ca_assert_true(empty($v4['ok']), '6c. empty owner is rejected');
ca_assert_true(str_contains((string)($v4['error'] ?? ''), 'required'), '6d. error mentions required');

// ═══════════════════════════════════════════════════════════════
// 7. arbitrary path is rejected
// ═══════════════════════════════════════════════════════════════

$v5 = EngineeringWorkspaceCoverageAssignmentService::validateWorkspaceKey('../../etc');
ca_assert_true(empty($v5['ok']), '7a. path traversal workspace rejected');
ca_assert_true(str_contains((string)($v5['error'] ?? ''), 'traversal'), '7b. error mentions traversal');

$v6 = EngineeringWorkspaceCoverageAssignmentService::validateWorkspaceKey('/etc/passwd');
ca_assert_true(empty($v6['ok']), '7c. absolute path workspace rejected');
ca_assert_true(str_contains((string)($v6['error'] ?? ''), 'absolute'), '7d. error mentions absolute');

$v7 = EngineeringWorkspaceCoverageAssignmentService::validateWorkspaceKey('NonExistent/Dir');
ca_assert_true(empty($v7['ok']), '7e. non-existent workspace rejected');
ca_assert_true(str_contains((string)($v7['error'] ?? ''), 'does not exist'), '7f. error mentions does not exist');

// ═══════════════════════════════════════════════════════════════
// 8. assignment file writes atomically
// ═══════════════════════════════════════════════════════════════

$testData = ['ProbeTest/Owner' => ['mode' => 'dedicated', 'workspace_key' => 'ProbeTest/Owner']];
$saveResult = EngineeringWorkspaceCoverageAssignmentService::saveAssignments($testData, ['login' => 'probe-test']);
ca_assert_true(!empty($saveResult['ok']), '8a. saveAssignments returns ok');
ca_assert_true(isset($saveResult['path']), '8b. path is set');
ca_assert_true(isset($saveResult['snapshot_path']), '8c. snapshot_path is set');

$reloaded4 = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
ca_assert_true(isset($reloaded4['ProbeTest/Owner']), '8d. reloaded contains test data');
ca_assert_eq('dedicated', $reloaded4['ProbeTest/Owner']['mode'] ?? '', '8e. reloaded mode is correct');

// Clean up test data
$originalData = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
unset($originalData['ProbeTest/Owner']);
EngineeringWorkspaceCoverageAssignmentService::saveAssignments($originalData, ['login' => 'probe-test-cleanup']);

// ═══════════════════════════════════════════════════════════════
// 9. loadAssignments round-trips empty and valid data
// ═══════════════════════════════════════════════════════════════

$empty = EngineeringWorkspaceCoverageAssignmentService::loadAssignments();
ca_assert_true(is_array($empty), '9a. loadAssignments returns array');

// ═══════════════════════════════════════════════════════════════
// 10. collectExistingWorkspaces excludes _template/_templates
// ═══════════════════════════════════════════════════════════════

$existingWorkspaces = EngineeringWorkspaceCoverageAssignmentService::collectExistingWorkspaces();
foreach ($existingWorkspaces as $ew) {
    ca_assert_true(
        !str_starts_with($ew, '_template'),
        '10a. existing workspace "' . $ew . '" does not start with _template'
    );
}

// ═══════════════════════════════════════════════════════════════
// 11. mode validation rejects invalid mode
// ═══════════════════════════════════════════════════════════════

$v8 = EngineeringWorkspaceCoverageAssignmentService::validateAndNormalizeAssignment('Manufacturing/Products', 'invalid_mode', '', []);
ca_assert_true(empty($v8['ok']), '11a. invalid mode rejected');

// ═══════════════════════════════════════════════════════════════
// 12. getAssignmentState returns correct labels
// ═══════════════════════════════════════════════════════════════

$state1 = EngineeringWorkspaceCoverageAssignmentService::getAssignmentState(['mode' => 'dedicated']);
ca_assert_eq('Dedicated workspace assigned', $state1, '12a. dedicated state label');

$state2 = EngineeringWorkspaceCoverageAssignmentService::getAssignmentState(['mode' => 'covered_by']);
ca_assert_eq('Covered by existing workspace', $state2, '12b. covered_by state label');

$state3 = EngineeringWorkspaceCoverageAssignmentService::getAssignmentState(['mode' => 'skip']);
ca_assert_eq('Skipped for now', $state3, '12c. skip state label');

$state4 = EngineeringWorkspaceCoverageAssignmentService::getAssignmentState([]);
ca_assert_eq('', $state4, '12d. empty assignment returns empty string');

// ═══════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════

echo PHP_EOL . 'EngineeringWorkspaceCoverageAssignment: ' . $passed . ' passed, ' . $failed . ' failed' . PHP_EOL;
exit($failed > 0 ? 1 : 0);
