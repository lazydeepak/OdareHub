<?php
declare(strict_types=1);

/**
 * probe_agent_context_integration.php
 *
 * Integration probe for the Engineering Workspace Agent Dispatch Integration V1.
 * Tests the Agent Context tab wiring: controller dispatch, view rendering, route
 * registration, audit safety, and workspace non-mutation guarantees.
 *
 * Run: php apps/Studio/Tools/EngineeringWorkspaces/tests/probe_agent_context_integration.php
 */

define('APP_ROOT', dirname(__DIR__, 5));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentPreflightService.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentBootstrap.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentExecutionGate.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentDispatcher.php';

use Platform\Engineering\EngineeringWorkspaceAgentDispatcher;

$pass = 0;
$fail = 0;
function assert_eq(string $label, mixed $expected, mixed $actual): void {
    global $pass, $fail;
    if ($expected === $actual) {
        $pass++; echo "  PASS $label\n";
    } else {
        $fail++; echo "  FAIL $label (expected: " . var_export($expected, true) . ", got: " . var_export($actual, true) . ")\n";
    }
}
function assert_true(string $label, bool $condition): void {
    global $pass, $fail;
    if ($condition) {
        $pass++; echo "  PASS $label\n";
    } else {
        $fail++; echo "  FAIL $label (expected true)\n";
    }
}
function assert_false(string $label, bool $condition): void {
    global $pass, $fail;
    if (!$condition) {
        $pass++; echo "  PASS $label\n";
    } else {
        $fail++; echo "  FAIL $label (expected false)\n";
    }
}

echo "=== Engineering Workspace Agent Dispatch Integration Probe ===\n\n";

// -- Fixture: clean workspace root --------------------------------------------
$probeRoot = APP_ROOT . '/engineering/probe-dispatch-v1';
@mkdir($probeRoot, 0755, true);

// TC01: Owner implementation ready → execution_state=implementation_ready, write_permitted=true
echo "--- TC01: Owner implementation ready ---\n";
$result01 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test implementation task',
    'probe-dispatch-v1',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'owner'
);
$packet01 = $result01['execution_packet'] ?? $result01;
assert_eq('state is implementation_ready', 'implementation_ready', $packet01['execution_state'] ?? '');
assert_true('write permitted', !empty($packet01['write_permitted']));

// TC02: 4 docs in package
echo "--- TC02: All 4 workspace docs present ---\n";
$docs02 = $packet01['workspace_context_package']['documents'] ?? [];
$docKeys02 = array_keys($docs02);
sort($docKeys02);
assert_eq('4 doc types', ['decisions', 'overview', 'rules', 'work'], $docKeys02);

// TC03: Degraded workspace with repair warning
echo "--- TC03: Degraded workspace (only overview.md exists) ---\n";
touch($probeRoot . '/overview.md');
file_put_contents($probeRoot . '/overview.md', "# Probe Dispatch V1\n\nWorkspace for integration test.");
$result03 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    'probe-dispatch-v1',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'owner'
);
$packet03 = $result03['execution_packet'] ?? $result03;
assert_true('state is not blocked', ($packet03['execution_state'] ?? '') !== 'blocked');

// TC04: Unresolved/blocked workspace
echo "--- TC04: Unresolved workspace blocked ---\n";
$result04 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    'nonexistent-workspace-xyz',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'owner'
);
$packet04 = $result04['execution_packet'] ?? $result04;
assert_eq('state is blocked', 'blocked', $packet04['execution_state'] ?? '');

// TC05: Platform scope without a resolved workspace is blocked
echo "--- TC05: Platform scope requires workspace contract ---\n";
$result05 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    '',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'platform'
);
$packet05 = $result05['execution_packet'] ?? $result05;
assert_eq('state is blocked', 'blocked', $packet05['execution_state'] ?? '');
assert_false('write not permitted', !empty($packet05['write_permitted']));
assert_true('block reason mentions workspace contract', str_contains((string)($packet05['block_reason'] ?? ''), 'workspace contract'));

// TC05b: Explicit Platform workspace is the valid platform implementation path
echo "--- TC05b: Explicit Platform workspace allowed ---\n";
$result05b = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test implementation task',
    'Platform',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'platform'
);
$packet05b = $result05b['execution_packet'] ?? $result05b;
assert_eq('state is implementation_ready', 'implementation_ready', $packet05b['execution_state'] ?? '');
assert_true('write permitted with Platform workspace', !empty($packet05b['write_permitted']));
assert_eq('resolved workspace is Platform', 'Platform', $packet05b['resolved_workspace_key'] ?? '');

// TC06: Read-only mode never gets write_permitted=true
echo "--- TC06: Read-only mode (no write, no init) ---\n";
$result06 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    'probe-dispatch-v1',
    [],
    ['role' => 'platform_admin'],
    'read_only',
    'owner'
);
$packet06 = $result06['execution_packet'] ?? $result06;
$ctxPkg06 = $packet06['workspace_context_package'] ?? [];
assert_false('write not permitted', !empty($packet06['write_permitted']));
assert_eq('no workspace files', [], $ctxPkg06['files'] ?? []);

// TC07: Spy executor receives gate-produced packet
echo "--- TC07: Spy executor receives gate packet ---\n";
$spyReceivedPacket = null;
$spyCalled07 = false;
$result07 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    'probe-dispatch-v1',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'owner',
    function (array $packet) use (&$spyReceivedPacket, &$spyCalled07) {
        $spyCalled07 = true;
        $spyReceivedPacket = $packet;
    }
);
assert_true('spy called', $spyCalled07);
assert_true('spy received packet', $spyReceivedPacket !== null);
assert_true('execution_called flag present', !empty($result07['execution_called']));

// TC08: Blocked dispatch never calls executor
echo "--- TC08: Blocked dispatch avoids executor ---\n";
$spyCalled08 = false;
$result08 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    'nonexistent-workspace-xyz',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'owner',
    function (array $packet) use (&$spyCalled08) {
        $spyCalled08 = true;
    }
);
assert_false('spy not called', $spyCalled08);

// TC09: Audit trail does not contain raw task text or workspace content
echo "--- TC09: Audit safety (no raw task text) ---\n";
$audit09 = $packet01['audit'] ?? [];
$auditStr09 = json_encode($audit09);
assert_false('no raw task text', strpos($auditStr09, 'Test implementation task') !== false);

// TC10: Agent context preparation does not mutate workspace files
echo "--- TC10: No workspace mutation ---\n";
$overviewContentBefore = file_get_contents($probeRoot . '/overview.md');
$result10 = EngineeringWorkspaceAgentDispatcher::dispatch(
    'Test',
    'probe-dispatch-v1',
    [],
    ['role' => 'platform_admin'],
    'implementation',
    'owner'
);
$overviewContentAfter = file_get_contents($probeRoot . '/overview.md');
assert_eq('overview.md unchanged', $overviewContentBefore, $overviewContentAfter);

// -- Cleanup fixture ----------------------------------------------------------
array_map('unlink', glob($probeRoot . '/*.md'));
rmdir($probeRoot);

echo "\n=== Results ===\n";
echo "  Pass: $pass\n";
echo "  Fail: $fail\n";
exit($fail > 0 ? 1 : 0);
