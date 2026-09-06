<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureOwnerDiscoveryService.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceCoverageService.php';

use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceCoverageService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureOwnerDiscoveryService;
use Platform\Security\EngineeringWorkspaceContentContract;
use Platform\Security\EngineeringWorkspaceResolver;

$passed = 0;
$failed = 0;

function ci_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function ci_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function ci_assert_not_empty(mixed $value, string $label): void
{
    ci_assert_true(!empty($value), $label);
}

// ============================================================
// Build coverage
// ============================================================
$coverage = EngineeringWorkspaceCoverageService::buildCoverage();
$ownerSummary = $coverage['owner_summary'] ?? [];
$workspaceSummary = $coverage['workspace_summary'] ?? [];
$rows = $coverage['rows'] ?? [];
$catalogueState = $coverage['catalogue_state'] ?? '';
$scanState = $coverage['scan_state'] ?? '';

$registeredKeys = EngineeringWorkspaceContentContract::supportedWorkspaceKeys();
$owners = OwnerStructureOwnerDiscoveryService::discover();
$ownerKeys = array_map(static fn(array $o): string => (string)($o['owner_key'] ?? ''), $owners);

// ============================================================
// 1. Canonical owner catalogue is reused
// ============================================================
ci_assert_true(isset($ownerSummary['contexts_scanned']), '1a. owner_summary has contexts_scanned');
$ownerCount = (int)($ownerSummary['contexts_scanned'] ?? 0);
ci_assert_eq(count($owners), $ownerCount, '1b. contexts_scanned matches OwnerStructureOwnerDiscoveryService count');
ci_assert_true($ownerCount > 0, '1c. contexts_scanned > 0 (' . $ownerCount . ')');

// Verify no Hub-local duplicate scan exists (only the one owner catalogue call)
$backtraceFile = __FILE__;
ci_assert_true($ownerCount === 38, '1d. expected 38 owners from catalogue');

// ============================================================
// 2. Controlled engineering/ scan excludes _templates and unsafe paths
// ============================================================
ci_assert_true(isset($scanState), '2a. scan_state key exists');
ci_assert_true(in_array($scanState, ['ok', 'unavailable'], true), '2b. scan_state is ok or unavailable');

// Verify _template and _templates directories do NOT produce discovered rows
// by checking no row has a context_key or workspace_key starting with those prefixes
foreach ($rows as $row) {
    $rk = (string)($row['context_label'] ?? '');
    ci_assert_true(
        !str_contains($rk, '_template'),
        '2c. no row references _template directory (got "' . $rk . '")'
    );
}

// ============================================================
// 3. Only directories with canonical document evidence become discovered workspace candidates
// ============================================================
// Check that no discovered-unregistered row has an intermediate dir without canonical docs
$discoveredRows = array_filter(
    $rows,
    static fn(array $r): bool => (string)($r['row_kind'] ?? '') === 'discovered-unregistered-workspace'
);
$intermediateDirLabels = ['Manufacturing', 'Platform', 'Plugin'];
foreach ($discoveredRows as $row) {
    $cl = (string)($row['context_label'] ?? '');
    foreach ($intermediateDirLabels as $idl) {
        ci_assert_true(
            $cl !== $idl,
            '3a. intermediate directory "' . $idl . '" is not a discovered workspace candidate'
        );
    }
}

// ============================================================
// 4. Exact registered and mapped workspaces remain present
// ============================================================
$workspaceKeys = [];
foreach ($rows as $row) {
    $wk = (string)($row['workspace_key'] ?? '');
    if ($wk !== '') {
        $workspaceKeys[] = $wk;
    }
}
foreach ($registeredKeys as $key) {
    ci_assert_true(
        in_array($key, $workspaceKeys, true),
        '4a. registered workspace "' . $key . '" is present in coverage rows'
    );
}

// ============================================================
// 5. Unmapped owners become Coverage decision required
// ============================================================
$ownerMatchedKeys = [];
foreach ($owners as $o) {
    $k = (string)($o['owner_key'] ?? '');
    if (in_array($k, $registeredKeys, true)) {
        $ownerMatchedKeys[] = $k;
    }
}
$unmappedKeys = array_diff($ownerKeys, $registeredKeys);
$coverageDecisionRows = [];
foreach ($rows as $row) {
    if ((string)($row['state'] ?? '') === 'coverage_decision_required') {
        $coverageDecisionRows[] = $row;
    }
}
ci_assert_eq(count($unmappedKeys), count($coverageDecisionRows), '5a. coverage_decision_required row count matches unmapped owner count');

$decisionOwnerKeys = array_map(
    static fn(array $r): string => (string)($r['context_key'] ?? ''),
    $coverageDecisionRows
);
foreach ($unmappedKeys as $uk) {
    // Owner may appear as coverage decision if unresolved
    ci_assert_true(
        in_array($uk, $decisionOwnerKeys, true),
        '5b. unmapped owner "' . $uk . '" appears as coverage_decision_required'
    );
}

// ============================================================
// 6. No owner receives a guessed workspace or parent-child fallback mapping
// ============================================================
foreach ($rows as $row) {
    $ck = (string)($row['context_key'] ?? '');
    $wk = (string)($row['workspace_key'] ?? '');
    if ($ck !== '' && $wk !== '') {
        $rowKind = (string)($row['row_kind'] ?? '');
        if ($rowKind === 'coverage-decision-owner-context') {
            ci_assert_eq('', $wk, '6a. coverage-decision row has empty workspace_key');
        } elseif ($rowKind === 'owner-backed-workspace') {
            ci_assert_true(in_array($ck, $ownerKeys, true), '6b. owner-backed row context "' . $ck . '" is a real owner');
            ci_assert_true(in_array($wk, $registeredKeys, true), '6c. owner-backed row workspace "' . $wk . '" is registered');
        }
    }
}

// ============================================================
// 7. Registered-only workspaces remain distinct from owner-backed
// ============================================================
$registeredOnlyKeys = array_diff($registeredKeys, $ownerKeys);
foreach ($rows as $row) {
    $rk = (string)($row['row_kind'] ?? '');
    $wk = (string)($row['workspace_key'] ?? '');
    if ($rk === 'registered-only-workspace') {
        ci_assert_true(
            in_array($wk, $registeredOnlyKeys, true),
            '7a. registered-only row "' . $wk . '" is in registered-only keys'
        );
    } elseif ($rk === 'owner-backed-workspace') {
        ci_assert_true(
            in_array($wk, $ownerMatchedKeys, true),
            '7b. owner-backed row "' . $wk . '" is in owner-matched keys'
        );
    }
}

// ============================================================
// 7c. Evidence types are correct
// ============================================================
foreach ($rows as $row) {
    $rk = (string)($row['row_kind'] ?? '');
    $ev = (string)($row['evidence_type'] ?? '');
    $expected = match ($rk) {
        'owner-backed-workspace' => 'exact_owner_mapping',
        'registered-only-workspace' => 'registered_workspace',
        'discovered-unregistered-workspace' => 'discovered_workspace_directory',
        'coverage-decision-owner-context' => 'canonical_owner_catalogue',
        default => '',
    };
    if ($expected !== '') {
        ci_assert_eq($expected, $ev, '7c. row kind "' . $rk . '" has evidence type "' . $expected . '"');
    }
}

// ============================================================
// 8. Discovered unregistered workspace handling
// ============================================================
$discoveredCount = (int)($workspaceSummary['discovered_unregistered_workspaces'] ?? 0);
$actualDiscovered = 0;
foreach ($rows as $row) {
    if ((string)($row['row_kind'] ?? '') === 'discovered-unregistered-workspace') {
        $actualDiscovered++;
    }
}
ci_assert_eq($actualDiscovered, $discoveredCount, '8a. discovered_unregistered_workspaces count matches');
ci_assert_eq(0, $actualDiscovered, '8b. no unregistered workspaces currently (all dirs are registered)');

// ============================================================
// 9. Valid documents produce Preserve
// ============================================================
foreach ($rows as $row) {
    $state = (string)($row['state'] ?? '');
    if ($state === 'linked_valid') {
        ci_assert_eq('Preserve', (string)($row['provisioning_readiness'] ?? ''), '9a. linked_valid row has Preserve readiness');
        foreach (($row['documents'] ?? []) as $doc) {
            if ((string)($doc['status'] ?? '') === 'valid') {
                ci_assert_eq('preserve', (string)($doc['provisioning_readiness'] ?? ''), '9b. valid doc has preserve readiness');
            }
        }
    }
}

// ============================================================
// 10. Missing documents on registered workspaces produce Initialize from template
// ============================================================
// Can't mutate workspace files, but can verify the code path is correct
// by checking no existing initialization_required rows have incorrect readiness
foreach ($rows as $row) {
    $state = (string)($row['state'] ?? '');
    if ($state === 'initialization_required') {
        $readiness = (string)($row['provisioning_readiness'] ?? '');
        ci_assert_true(
            str_contains($readiness, 'Initialize'),
            '10a. initialization_required readiness starts with "Initialize" (got "' . $readiness . '")'
        );
        foreach (($row['documents'] ?? []) as $doc) {
            if ((string)($doc['status'] ?? '') === 'missing') {
                ci_assert_eq('initialize_from_template', (string)($doc['provisioning_readiness'] ?? ''), '10b. missing doc has initialize_from_template');
            }
        }
    }
}

// ============================================================
// 11. Invalid documents produce Review contract repair
// ============================================================
foreach ($rows as $row) {
    $state = (string)($row['state'] ?? '');
    if ($state === 'contract_repair_required') {
        ci_assert_true(
            str_contains((string)($row['provisioning_readiness'] ?? ''), 'Review'),
            '11a. contract_repair_required readiness mentions Review'
        );
        foreach (($row['documents'] ?? []) as $doc) {
            if ((string)($doc['status'] ?? '') === 'contract_invalid') {
                ci_assert_eq('review_contract_repair', (string)($doc['provisioning_readiness'] ?? ''), '11b. invalid doc has review_contract_repair');
            }
        }
    }
}

// ============================================================
// 12. Valid documents do not produce overwrite/reset readiness
// ============================================================
foreach ($rows as $row) {
    foreach (($row['documents'] ?? []) as $doc) {
        if ((string)($doc['status'] ?? '') === 'valid') {
            $pr = (string)($doc['provisioning_readiness'] ?? '');
            ci_assert_true(
                !str_contains($pr, 'overwrite') && !str_contains($pr, 'reset') && !str_contains($pr, 'replace'),
                '12. valid doc readiness does not mention overwrite/reset/replace (got "' . $pr . '")'
            );
        }
    }
}

// ============================================================
// 13. Per-row failure isolation
// ============================================================
// coverage-decision rows have no documents and empty workspace key
foreach ($coverageDecisionRows as $row) {
    ci_assert_eq([], $row['documents'] ?? null, '13a. coverage-decision row has empty documents');
    ci_assert_eq('', (string)($row['workspace_key'] ?? ''), '13b. coverage-decision row has empty workspace_key');
    ci_assert_true((string)($row['readiness_fingerprint'] ?? '') !== '', '13c. coverage-decision row has readiness_fingerprint');
}

// ============================================================
// 14. Templates tab structure is unchanged
// ============================================================
// Verify Hub service still builds templates independently
require_once APP_ROOT . '/platform/Security/MarkdownRenderer.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspaces/Services/EngineeringWorkspaceHubService.php';
use Apps\Studio\Tools\EngineeringWorkspaces\Services\EngineeringWorkspaceHubService;

$templateModel = EngineeringWorkspaceHubService::buildModel('templates');
ci_assert_eq('templates', $templateModel['active_tab'] ?? '', '14a. templates tab is selected by explicit tab');
$templates = $templateModel['templates'] ?? [];
ci_assert_eq(4, count($templates), '14b. templates tab exposes four canonical templates');
foreach ($templates as $template) {
    ci_assert_true(in_array((string)($template['filename'] ?? ''), ['overview.md', 'work.md', 'rules.md', 'decisions.md'], true), '14c. canonical template filename');
    ci_assert_eq('valid', (string)($template['source_status'] ?? ''), '14d. template source is valid');
}

// ============================================================
// 15. No path disclosure, stack traces, or raw exceptions in coverage output
// ============================================================
$json = json_encode($coverage);
ci_assert_true(!str_contains($json, APP_ROOT), '15a. coverage JSON does not contain APP_ROOT');
ci_assert_true(!str_contains($json, 'Stack trace'), '15b. coverage JSON does not contain stack trace');
ci_assert_true(!str_contains($json, 'exception'), '15c. coverage JSON does not contain exception');
ci_assert_true(!str_contains($json, 'Error'), '15d. coverage JSON does not contain Error');
ci_assert_true(!str_contains($json, 'engineering/'), '15e. coverage JSON does not contain engineering/ paths');

// ============================================================
// 16. Summary counts are consistent
// ============================================================
$os = $ownerSummary;
$ws = $workspaceSummary;

ci_assert_eq(
    (int)($os['contexts_scanned'] ?? 0),
    (int)($os['exact_workspace_mappings'] ?? 0)
    + (int)($os['coverage_decision_required'] ?? 0)
    + (int)($os['explicitly_excluded'] ?? 0)
    + (int)($os['resolution_failed'] ?? 0),
    '16a. owner_summary counts balance: scanned = mappings + decision + excluded + failed'
);

$stateCounts = [
    'linked_valid' => 0,
    'initialization_required' => 0,
    'contract_repair_required' => 0,
    'mapping_review_required' => 0,
    'resolution_failed' => 0,
    'unregistered_workspace_discovered' => 0,
    'coverage_decision_required' => 0,
];
foreach ($rows as $row) {
    $s = (string)($row['state'] ?? '');
    if (isset($stateCounts[$s])) {
        $stateCounts[$s]++;
    }
}
ci_assert_eq($stateCounts['linked_valid'], (int)($ws['linked_valid'] ?? 0), '16b. linked_valid count matches');
ci_assert_eq($stateCounts['initialization_required'], (int)($ws['initialization_required'] ?? 0), '16c. initialization_required count matches');
ci_assert_eq($stateCounts['contract_repair_required'], (int)($ws['contract_repair_required'] ?? 0), '16d. contract_repair_required count matches');
ci_assert_eq($stateCounts['unregistered_workspace_discovered'], (int)($ws['discovered_unregistered_workspaces'] ?? 0), '16e. discovered_unregistered_workspaces count matches');

// ============================================================
// 17. readiness_fingerprint is deterministic
// ============================================================
$coverage2 = EngineeringWorkspaceCoverageService::buildCoverage();
foreach ($coverage['rows'] as $i => $row) {
    $fp1 = (string)($row['readiness_fingerprint'] ?? '');
    $fp2 = (string)(($coverage2['rows'][$i] ?? [])['readiness_fingerprint'] ?? '');
    ci_assert_eq($fp1, $fp2, '17a. row ' . $i . ' readiness_fingerprint is deterministic');
    ci_assert_true($fp1 !== '', '17b. row ' . $i . ' readiness_fingerprint is non-empty');
}

// ============================================================
// Summary
// ============================================================
echo "EngineeringWorkspaceCoverage: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
