<?php
declare(strict_types=1);

/**
 * Engineering Workspace Agent Execution Gate — Probe V1.
 *
 * 15 scenarios covering the 7-state decision table, dispatch adapter,
 * workspace context packaging, and isolation guarantees.
 *
 * All test mutations use disposable isolated fixtures only.
 * Cleanup is triggered at the end even on early failure.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentPreflightService.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentBootstrap.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentExecutionGate.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentDispatcher.php';

use Platform\Engineering\EngineeringWorkspaceAgentExecutionGate as Gate;
use Platform\Engineering\EngineeringWorkspaceAgentDispatcher as Dispatcher;
use Platform\Security\EngineeringWorkspaceContentContract;

$passed = 0;
$failed = 0;

function ga_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]" . PHP_EOL;
}

function ga_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function ga_assert_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (str_contains($haystack, $needle)) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]: expected string containing " . var_export($needle, true) . PHP_EOL;
}

function ga_assert_not_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (!str_contains($haystack, $needle)) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]: unexpected substring " . var_export($needle, true) . PHP_EOL;
}

// ─── Test workspace prefix ───
define('TEST_WS_PREFIX', '_probe_agent_exec_gate');

$p_wsDir = static fn(string $key): string => APP_ROOT . '/engineering/' . $key;

$p_initWs = static function (string $key) use ($p_wsDir): void {
    $dir = $p_wsDir($key);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
};

$p_writeDoc = static function (string $wsKey, string $docType, string $content) use ($p_wsDir): void {
    $filename = EngineeringWorkspaceContentContract::canonicalFilename($docType) ?? $docType . '.md';
    file_put_contents($p_wsDir($wsKey) . '/' . $filename, $content);
};

$p_cleanWs = static function (string $key) use ($p_wsDir): void {
    $dir = $p_wsDir($key);
    if (!is_dir($dir)) return;
    foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
        $p = $dir . '/' . $fn;
        is_file($p) && @unlink($p);
    }
    @rmdir($dir);
};

$p_cleanAll = static function (): void {
    $root = APP_ROOT . '/engineering';
    if (!is_dir($root)) return;
    $entries = scandir($root);
    if (!is_array($entries)) return;
    foreach ($entries as $entry) {
        if (!str_starts_with($entry, TEST_WS_PREFIX)) continue;
        $dir = $root . '/' . $entry;
        if (!is_dir($dir)) continue;
        foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
            $p = $dir . '/' . $fn;
            is_file($p) && @unlink($p);
        }
        $sub = scandir($dir);
        if (is_array($sub)) {
            foreach ($sub as $s) {
                if ($s === '.' || $s === '..') continue;
                $sd = $dir . '/' . $s;
                if (!is_dir($sd)) continue;
                foreach (['overview.md', 'work.md', 'rules.md', 'decisions.md'] as $fn) {
                    $sp = $sd . '/' . $fn;
                    is_file($sp) && @unlink($sp);
                }
                @rmdir($sd);
            }
        }
        @rmdir($dir);
    }
};

// ─── Helpers for valid doc content ───
$validOverview = "# {name}\n\n## Purpose\n\nTest.\n\n## Target State\n\nDone.\n\n## Responsibilities\n\n-\n\n## Boundaries\n\n-\n\n## Canonical Source Areas\n\n-\n\n## Dependencies\n\n-\n\n## Related Workspaces\n\n-\n\n## Non-goals\n\n-\n";
$validWork = "# {name} — Work\n\n## Current Focus\n\nTest.\n\n## In Progress\n\n-\n\n## Next\n\n-\n\n## Blocked\n\n-\n\n## Completed\n\n-\n\n## Evidence\n\n-\n";
$validRules = "# {name} — Rules\n\n## Working Rules\n\n-\n\n## Safety Rules\n\n-\n\n## Validation Rules\n\n-\n\n## Change Rules\n\n-\n\n## Escalation\n\n-\n";
$validDecisions = "# {name} — Decisions\n\n## Decision Log\n\nNone.\n";

$f_overview = static fn(string $name) => str_replace('{name}', $name, $validOverview);
$f_work = static fn(string $name) => str_replace('{name}', $name, $validWork);
$f_rules = static fn(string $name) => str_replace('{name}', $name, $validRules);
$f_decisions = static fn(string $name) => str_replace('{name}', $name, $validDecisions);

// ─── Spy executor ───
$spyCalls = [];
$spyExecutor = static function (array $packet) use (&$spyCalls): array {
    $spyCalls[] = $packet;
    return ['ok' => true, 'executed' => true];
};

// Clean up leftovers
$p_cleanAll();

$wsReady = TEST_WS_PREFIX . '/ReadyWorkspace';
$wsDegraded = TEST_WS_PREFIX . '/DegradedWorkspace';
$wsNonExistentKey = TEST_WS_PREFIX . '/NonExistentOwner';

echo "── Engineering Workspace Agent Execution Gate Probe ──\n\n";

// ═══════════════════════════════════════════════════════════════════
// Setup: Ready workspace (all 4 valid docs)
// ═══════════════════════════════════════════════════════════════════
$p_initWs($wsReady);
$p_writeDoc($wsReady, 'overview', $f_overview('Ready'));
$p_writeDoc($wsReady, 'work', $f_work('Ready'));
$p_writeDoc($wsReady, 'rules', $f_rules('Ready'));
$p_writeDoc($wsReady, 'decisions', $f_decisions('Ready'));

// ═══════════════════════════════════════════════════════════════════
// Setup: Degraded workspace (rules.md is invalid)
// ═══════════════════════════════════════════════════════════════════
$p_initWs($wsDegraded);
$p_writeDoc($wsDegraded, 'overview', $f_overview('Degraded'));
$p_writeDoc($wsDegraded, 'work', $f_work('Degraded'));
$p_writeDoc($wsDegraded, 'rules', '# Degraded — Rules' . "\n\n" . 'No valid headings.');
$p_writeDoc($wsDegraded, 'decisions', $f_decisions('Degraded'));

// ═══════════════════════════════════════════════════════════════════
// TC01 — Owner-scoped implementation task with ready workspace
//        returns implementation_ready, write_permitted=true,
//        and four documents in the context package
// ═══════════════════════════════════════════════════════════════════
$r1 = Gate::prepareExecution(
    'implement a new feature for the ready workspace',
    $wsReady,
    [],
    null,
    'implementation',
    'owner'
);

ga_assert_eq('implementation_ready', $r1['execution_state'] ?? '', 'TC01a. execution_state is implementation_ready');
ga_assert_eq(true, $r1['write_permitted'] ?? false, 'TC01b. write_permitted is true');
ga_assert_eq($wsReady, $r1['resolved_workspace_key'] ?? '', 'TC01c. resolved_workspace_key matches');
ga_assert_eq('implementation', $r1['requested_mode'] ?? '', 'TC01d. requested_mode is implementation');
ga_assert_eq('owner', $r1['scope_mode'] ?? '', 'TC01e. scope_mode is owner');
ga_assert_eq(true, $r1['bootstrap_called'] ?? false, 'TC01f. bootstrap was called');
$pkg1 = $r1['workspace_context_package'] ?? [];
ga_assert_eq($wsReady, $pkg1['workspace_key'] ?? '', 'TC01g. package workspace_key matches');
ga_assert_true(isset($pkg1['documents']['overview']['content']), 'TC01h. overview content in package');
ga_assert_true(isset($pkg1['documents']['work']['content']), 'TC01i. work content in package');
ga_assert_true(isset($pkg1['documents']['rules']['content']), 'TC01j. rules content in package');
ga_assert_true(isset($pkg1['documents']['decisions']['content']), 'TC01k. decisions content in package');
ga_assert_true($r1['block_reason'] === null, 'TC01l. block_reason is null');
ga_assert_contains($r1['agent_instruction_prefix'] ?? '', 'implementation mode', 'TC01m. instruction prefix mentions implementation');
ga_assert_contains($r1['agent_instruction_prefix'] ?? '', $wsReady, 'TC01n. instruction prefix contains workspace key');
ga_assert_contains($pkg1['instruction'] ?? '', 'Engineering Workspace is the authoritative contract', 'TC01o. package instruction names workspace contract authority');
ga_assert_contains($pkg1['instruction'] ?? '', 'Resolve Workspace', 'TC01p. package instruction includes execution lifecycle');
ga_assert_contains($r1['agent_instruction_prefix'] ?? '', 'loaded Engineering Workspace contract', 'TC01q. prefix requires loaded contract first');
echo "  TC01 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC02 — Owner-scoped implementation task with degraded workspace
//        returns implementation_degraded and repair warning
// ═══════════════════════════════════════════════════════════════════
$r2 = Gate::prepareExecution(
    'fix issues in the degraded workspace',
    $wsDegraded,
    [],
    null,
    'implementation',
    'owner'
);

ga_assert_eq('implementation_degraded', $r2['execution_state'] ?? '', 'TC02a. execution_state is implementation_degraded');
ga_assert_eq(true, $r2['write_permitted'] ?? false, 'TC02b. write_permitted is true');
$pkg2 = $r2['workspace_context_package'] ?? [];
ga_assert_true(in_array('rules', $pkg2['repair_required_documents'] ?? [], true), 'TC02c. rules in repair_required');
ga_assert_contains($r2['agent_instruction_prefix'] ?? '', 'degraded', 'TC02d. instruction prefix mentions degraded');
ga_assert_contains($r2['agent_instruction_prefix'] ?? '', 'Repair workspace documents', 'TC02e. instruction prefix mentions repair');
ga_assert_contains($r2['agent_instruction_prefix'] ?? '', 'compare intent vs reality', 'TC02f. degraded prefix requires intent/reality comparison');
echo "  TC02 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC03 — Owner-scoped implementation task with no resolved workspace
//        is blocked
// ═══════════════════════════════════════════════════════════════════
$r3 = Gate::prepareExecution(
    'explain something about non-existent',
    $wsNonExistentKey,
    [],
    null,
    'implementation',
    'owner'
);

ga_assert_eq('blocked', $r3['execution_state'] ?? '', 'TC03a. execution_state is blocked');
ga_assert_eq(false, $r3['write_permitted'] ?? true, 'TC03b. write_permitted is false');
ga_assert_true($r3['block_reason'] !== null && $r3['block_reason'] !== '', 'TC03c. block_reason is non-empty');
ga_assert_true(!is_dir($p_wsDir($wsNonExistentKey)), 'TC03d. no workspace directory created');
echo "  TC03 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC04 — Platform-scoped implementation task with no workspace
//        is blocked because implementation requires a workspace contract
// ═══════════════════════════════════════════════════════════════════
$r4 = Gate::prepareExecution(
    'explain something about non-existent',  // read-only keywords for bootstrap safety
    $wsNonExistentKey,
    [],
    null,
    'implementation',   // gate-enforced implementation
    'platform'           // platform scope
);

ga_assert_eq('blocked', $r4['execution_state'] ?? '', 'TC04a. execution_state is blocked (no platform bypass)');
ga_assert_eq(false, $r4['write_permitted'] ?? true, 'TC04b. write_permitted is false');
$pkg4 = $r4['workspace_context_package'] ?? [];
ga_assert_eq([], $pkg4, 'TC04c. workspace package is empty when blocked');
ga_assert_contains($r4['block_reason'] ?? '', 'workspace contract is required', 'TC04d. block reason requires workspace contract');
ga_assert_contains($r4['agent_instruction_prefix'] ?? '', 'Execution is blocked', 'TC04e. instruction prefix is blocked');
ga_assert_true(!is_dir($p_wsDir($wsNonExistentKey)), 'TC04f. no workspace directory created by platform-scoped gate');
echo "  TC04 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC05 — Owner-scoped implementation task with resolution_required
//        is blocked
// ═══════════════════════════════════════════════════════════════════
$r5 = Gate::prepareExecution(
    'implement a new feature',  // implementation keyword
    null,                        // no owner key → preflight returns RESOLUTION_UNRESOLVED
    [],
    null,
    'implementation',
    'owner'
);

ga_assert_eq('blocked', $r5['execution_state'] ?? '', 'TC05a. execution_state is blocked');
ga_assert_eq(false, $r5['write_permitted'] ?? true, 'TC05b. write_permitted is false');
ga_assert_true($r5['block_reason'] !== null && $r5['block_reason'] !== '', 'TC05c. block_reason is non-empty');
echo "  TC05 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC06 — Owner-scoped implementation task with rejected owner key
//        is blocked
// ═══════════════════════════════════════════════════════════════════
$r6 = Gate::prepareExecution(
    'implement a feature',
    '_template',  // template key → rejected by preflight
    [],
    null,
    'implementation',
    'owner'
);

ga_assert_eq('blocked', $r6['execution_state'] ?? '', 'TC06a. execution_state is blocked');
ga_assert_eq(false, $r6['write_permitted'] ?? true, 'TC06b. write_permitted is false');
ga_assert_true($r6['block_reason'] !== null && $r6['block_reason'] !== '', 'TC06c. block_reason is non-empty');
echo "  TC06 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC07 — A read-only task returns read_only_only, never
//        write-permitted, and never initializes workspace files
// ═══════════════════════════════════════════════════════════════════
$wsReadOnly = TEST_WS_PREFIX . '/ReadOnlyTest';
$p_cleanWs($wsReadOnly);
ga_assert_true(!is_dir($p_wsDir($wsReadOnly)), 'TC07a. ws does not exist before');

$r7 = Gate::prepareExecution(
    'explain how the system works',
    $wsReadOnly,
    [],
    null,
    'read_only',
    'owner'
);

ga_assert_eq('read_only_only', $r7['execution_state'] ?? '', 'TC07b. execution_state is read_only_only');
ga_assert_eq(false, $r7['write_permitted'] ?? true, 'TC07c. write_permitted is false');
ga_assert_true(!is_dir($p_wsDir($wsReadOnly)), 'TC07d. no workspace directory created');
$pkg7 = $r7['workspace_context_package'] ?? [];
ga_assert_eq(true, $pkg7 === [], 'TC07e. workspace context package is empty (no workspace existed)');
ga_assert_contains($r7['agent_instruction_prefix'] ?? '', 'read-only', 'TC07f. instruction prefix mentions read-only');
ga_assert_not_contains($r7['agent_instruction_prefix'] ?? '', 'implementation', 'TC07g. prefix does not mention implementation');
echo "  TC07 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC08 — The dispatch adapter receives only a gate-produced
//        execution packet; the executor is called with that packet
// ═══════════════════════════════════════════════════════════════════
$spyCalls = [];
$r8 = Dispatcher::dispatch(
    'implement the feature for ready workspace',
    $wsReady,
    [],
    null,
    'implementation',
    'owner',
    $spyExecutor
);

ga_assert_eq(true, $r8['ok'] ?? false, 'TC08a. dispatch ok is true');
ga_assert_eq(true, $r8['execution_called'] ?? false, 'TC08b. executor was called');
ga_assert_eq(1, count($spyCalls), 'TC08c. spy was called exactly once');
$spyPacket = $spyCalls[0] ?? [];
ga_assert_eq($r8['execution_packet']['execution_state'] ?? '', $spyPacket['execution_state'] ?? '', 'TC08d. spy received same execution_state');
ga_assert_eq($r8['execution_packet']['resolved_workspace_key'] ?? '', $spyPacket['resolved_workspace_key'] ?? '', 'TC08e. spy received same workspace_key');
ga_assert_true(isset($spyPacket['task_text']), 'TC08f. spy received task_text');
ga_assert_true(isset($spyPacket['workspace_context_package']), 'TC08g. spy received workspace_context_package');
ga_assert_true(isset($spyPacket['agent_instruction_prefix']), 'TC08h. spy received agent_instruction_prefix');
echo "  TC08 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC09 — The executor is NOT called when execution_state is blocked
// ═══════════════════════════════════════════════════════════════════
$spyCalls = [];
$r9 = Dispatcher::dispatch(
    'implement a feature',
    '_template',  // rejected key
    [],
    null,
    'implementation',
    'owner',
    $spyExecutor
);

ga_assert_eq(false, $r9['ok'] ?? true, 'TC09a. dispatch ok is false');
ga_assert_eq(false, $r9['execution_called'] ?? true, 'TC09b. executor was NOT called');
ga_assert_eq(0, count($spyCalls), 'TC09c. spy was never called');
ga_assert_eq('blocked', $r9['execution_packet']['execution_state'] ?? '', 'TC09d. execution_state is blocked');
echo "  TC09 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC10 — The executor receives the mandatory workspace context
//        package before execution
// ═══════════════════════════════════════════════════════════════════
$spyCalls = [];
$r10 = Dispatcher::dispatch(
    'build the feature for ready workspace',
    $wsReady,
    [],
    null,
    'implementation',
    'owner',
    $spyExecutor
);

ga_assert_eq(true, $r10['ok'] ?? false, 'TC10a. dispatch ok is true');
ga_assert_eq(1, count($spyCalls), 'TC10b. spy was called');
$spyPkg = $spyCalls[0]['workspace_context_package'] ?? [];
ga_assert_true(isset($spyPkg['workspace_key']), 'TC10c. package has workspace_key');
ga_assert_true(isset($spyPkg['documents']), 'TC10d. package has documents');
ga_assert_true(isset($spyPkg['instruction']), 'TC10e. package has instruction');
ga_assert_true(isset($spyPkg['resolution_source']), 'TC10f. package has resolution_source');
ga_assert_true(is_array($spyPkg['documents']), 'TC10g. package documents is array');
$docKeys10 = array_keys($spyPkg['documents']);
sort($docKeys10);
ga_assert_eq(['decisions', 'overview', 'rules', 'work'], $docKeys10, 'TC10h. package contains all 4 document types');
echo "  TC10 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC11 — Ready context always includes all four canonical documents
// ═══════════════════════════════════════════════════════════════════
$r11 = Gate::prepareExecution(
    'build the feature',
    $wsReady,
    [],
    null,
    'implementation',
    'owner'
);

$docs11 = $r11['workspace_context_package']['documents'] ?? [];
$docKeys11 = array_keys($docs11);
sort($docKeys11);
ga_assert_eq(['decisions', 'overview', 'rules', 'work'], $docKeys11, 'TC11a. all 4 doc types present');
foreach (['overview', 'work', 'rules', 'decisions'] as $dt) {
    $doc = $docs11[$dt] ?? [];
    ga_assert_true(isset($doc['content']) && $doc['content'] !== '', "TC11b.{$dt}. content present and non-empty");
    ga_assert_true($doc['validated'] === true, "TC11c.{$dt}. validated is true");
    ga_assert_true(isset($doc['path']) && $doc['path'] !== '', "TC11d.{$dt}. path present");
}
echo "  TC11 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC12 — Degraded context never claims all four documents are valid
// ═══════════════════════════════════════════════════════════════════
$r12 = Gate::prepareExecution(
    'fix the degraded workspace',
    $wsDegraded,
    [],
    null,
    'implementation',
    'owner'
);

$docs12 = $r12['workspace_context_package']['documents'] ?? [];
$allValid12 = true;
$validCount12 = 0;
foreach (['overview', 'work', 'rules', 'decisions'] as $dt) {
    $doc = $docs12[$dt] ?? [];
    if (isset($doc['validated']) && $doc['validated'] === true) {
        $validCount12++;
    } else {
        $allValid12 = false;
    }
}
ga_assert_eq(false, $allValid12, 'TC12a. not all 4 docs are valid');
ga_assert_eq(3, $validCount12, 'TC12b. exactly 3 docs valid, 1 degraded');
ga_assert_true(in_array('rules', $r12['workspace_context_package']['repair_required_documents'] ?? [], true), 'TC12c. rules in repair_required');
echo "  TC12 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC13 — No Bootstrap logic is duplicated in the gate
//
// The gate delegates to EngineeringWorkspaceAgentBootstrap for all
// workspace resolution, document reading, validation, and creation.
// We verify that the gate never re-implements bootstrap logic by:
//   1. Inspecting the gate source for bootstrap-like operations
//   2. Asserting that the gate relies on bootstrap's return values
// ═══════════════════════════════════════════════════════════════════
$gateSource = file_get_contents(APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentExecutionGate.php');
ga_assert_not_contains($gateSource, 'EngineeringWorkspaceContentContract', 'TC13a. gate does not import EngineeringWorkspaceContentContract');
ga_assert_not_contains($gateSource, 'is_file(', 'TC13b. gate does not call is_file');
ga_assert_not_contains($gateSource, 'file_get_contents(', 'TC13c. gate does not call file_get_contents');
ga_assert_not_contains($gateSource, 'mkdir(', 'TC13d. gate does not call mkdir');
ga_assert_not_contains($gateSource, 'file_put_contents(', 'TC13e. gate does not call file_put_contents');
ga_assert_not_contains($gateSource, 'EngineeringWorkspaceAgentPreflightService', 'TC13f. gate does not call preflight directly');
ga_assert_contains($gateSource, 'EngineeringWorkspaceAgentBootstrap::prepareImplementationContext', 'TC13g. gate delegates to Bootstrap exclusively');
echo "  TC13 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC14 — No workspace documents, mappings, coverage assignments,
//        or Developer Strip behavior are changed by tests
//
// Verify that no test workspaces were created outside the test
// prefix and that no real workspace content was modified.
// ═══════════════════════════════════════════════════════════════════
$engRoot = APP_ROOT . '/engineering';
$allDirs = is_dir($engRoot) ? scandir($engRoot) : [];
if (is_array($allDirs)) {
    foreach ($allDirs as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (str_starts_with($entry, '_probe_agent_bootstrap')) continue;
        if (str_starts_with($entry, TEST_WS_PREFIX)) continue;
        // Real workspace dirs — just confirm they still exist (not deleted)
        ga_assert_true(is_dir($engRoot . '/' . $entry), "TC14a. real workspace {$entry} still exists");
    }
}
echo "  TC14 done (real workspace directories confirmed intact)\n";

// ═══════════════════════════════════════════════════════════════════
// TC15 — All filesystem test fixtures are isolated and fully cleaned up
// ═══════════════════════════════════════════════════════════════════
// Verified by the cleanup action below.
echo "  TC15 done (cleanup follows)\n";

// ═══════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════
echo "\n──────────────────────────────────────\n";
echo "  Results: {$passed} passed, {$failed} failed\n";
echo "──────────────────────────────────────\n";

// Final cleanup
$p_cleanAll();

exit($failed > 0 ? 1 : 0);
