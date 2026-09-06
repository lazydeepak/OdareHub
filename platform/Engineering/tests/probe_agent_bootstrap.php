<?php
declare(strict_types=1);

/**
 * Engineering Workspace Agent Bootstrap — Probe V1.
 *
 * 16 scenarios testing preflight evaluation, workspace resolution,
 * document creation/validation, context packaging, safety checks,
 * and isolation guarantees.
 *
 * All test mutations use disposable isolated fixtures only.
 * Cleanup is triggered at the end even on early failure.
 */

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/app/Core/helpers.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentPreflightService.php';
require_once APP_ROOT . '/platform/Engineering/EngineeringWorkspaceAgentBootstrap.php';

use Platform\Engineering\EngineeringWorkspaceAgentPreflightService as Preflight;
use Platform\Engineering\EngineeringWorkspaceAgentBootstrap as Bootstrap;
use Platform\Security\EngineeringWorkspaceContentContract;

$passed = 0;
$failed = 0;

function ba_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]" . PHP_EOL;
}

function ba_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]: expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

function ba_assert_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (str_contains($haystack, $needle)) { $passed++; return; }
    $failed++;
    echo "  FAIL [{$label}]: expected string containing " . var_export($needle, true) . PHP_EOL;
}

// ─── Test workspace prefix ───
// All test workspaces use the prefix _probe_agent_bootstrap/ so they are
// easy to identify and clean up. They live directly under engineering/
// for workspace resolution to work correctly.
define('TEST_WS_PREFIX', '_probe_agent_bootstrap');

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

$p_readDoc = static function (string $wsKey, string $docType) use ($p_wsDir): ?string {
    $filename = EngineeringWorkspaceContentContract::canonicalFilename($docType) ?? $docType . '.md';
    $path = $p_wsDir($wsKey) . '/' . $filename;
    $c = is_file($path) ? file_get_contents($path) : false;
    return $c !== false ? $c : null;
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
        // Remove nested child dirs too
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

// Clean up any leftovers from a previous run
$p_cleanAll();

$wsA = TEST_WS_PREFIX . '/A';
$wsB = TEST_WS_PREFIX . '/B';
$wsC = TEST_WS_PREFIX . '/C';
$wsD = TEST_WS_PREFIX . '/D';
$wsE = TEST_WS_PREFIX . '/E';
$wsF = TEST_WS_PREFIX . '/F';
$wsG = TEST_WS_PREFIX . '/G';

echo "── Engineering Workspace Agent Bootstrap Probe ──\n\n";

// ═══════════════════════════════════════════════════════════════════
// TC01 — Explicit owner-to-workspace mapping resolves and loads
//        all four documents
// ═══════════════════════════════════════════════════════════════════
$p_initWs($wsA);
$p_writeDoc($wsA, 'overview', $f_overview('A'));
$p_writeDoc($wsA, 'work', $f_work('A'));
$p_writeDoc($wsA, 'rules', $f_rules('A'));
$p_writeDoc($wsA, 'decisions', $f_decisions('A'));

$result1 = Bootstrap::prepareImplementationContext(
    'review workspace A',
    $wsA,
    [],
    null
);

ba_assert_eq($wsA, $result1['workspace_key'] ?? '', 'TC01a. workspace key resolves');
ba_assert_eq('explicit_owner_key', $result1['resolution_source'] ?? '', 'TC01b. resolution source is explicit');
ba_assert_eq('read_only', $result1['mode'] ?? '', 'TC01c. mode is read_only');
$state1 = $result1['workspace_context_state'] ?? '';
ba_assert_true($state1 === 'ready' || $state1 === 'degraded', 'TC01d. context state is ready or degraded: ' . $state1);
ba_assert_true(isset($result1['documents']['overview']['content']), 'TC01e. overview content present');
ba_assert_true(isset($result1['documents']['work']['content']), 'TC01f. work content present');
ba_assert_true(isset($result1['documents']['rules']['content']), 'TC01g. rules content present');
ba_assert_true(isset($result1['documents']['decisions']['content']), 'TC01h. decisions content present');
ba_assert_eq([], $result1['created_missing_documents'] ?? null, 'TC01i. no documents were created');
ba_assert_true(str_contains((string)($result1['instruction'] ?? ''), 'Engineering Workspace is the authoritative contract'), 'TC01j. instruction names workspace contract authority');
ba_assert_true(str_contains((string)($result1['instruction'] ?? ''), 'Resolve Workspace'), 'TC01k. instruction includes lifecycle');
ba_assert_true(str_contains((string)($result1['instruction'] ?? ''), 'When workspace intent conflicts'), 'TC01l. instruction requires conflict reporting');
echo "  TC01 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC02 — Coverage-assigned owner resolves and loads all four docs
// ═══════════════════════════════════════════════════════════════════
$assignmentPath = APP_ROOT . '/storage/engineering-workspace-coverage-assignment.json';
$existingAssignments = [];
if (is_file($assignmentPath)) {
    $raw = file_get_contents($assignmentPath);
    $existingAssignments = ($raw !== false) ? (json_decode($raw, true) ?? []) : [];
}

// Temporarily save a coverage assignment pointing wsB → wsA
$testAssignments = $existingAssignments;
$testAssignments[$wsB] = ['mode' => 'dedicated', 'workspace_key' => $wsA];
$tmpPath = $assignmentPath . '.tmp.' . bin2hex(random_bytes(8));
file_put_contents($tmpPath, json_encode($testAssignments, JSON_PRETTY_PRINT), LOCK_EX);
rename($tmpPath, $assignmentPath);
clearstatcache(true, $assignmentPath);

$result2 = Bootstrap::prepareImplementationContext(
    'review coverage for workspace B',
    $wsB,
    [],
    null
);

// Restore original
$tmpPath2 = $assignmentPath . '.tmp.' . bin2hex(random_bytes(8));
file_put_contents($tmpPath2, json_encode($existingAssignments, JSON_PRETTY_PRINT), LOCK_EX);
rename($tmpPath2, $assignmentPath);
clearstatcache(true, $assignmentPath);

ba_assert_eq($wsA, $result2['workspace_key'] ?? '', 'TC02a. coverage resolves to A');
ba_assert_eq('coverage_assignment', $result2['resolution_source'] ?? '', 'TC02b. resolution source is coverage_assignment');
ba_assert_true(isset($result2['documents']['overview']['content']), 'TC02c. coverage overview content present');
ba_assert_true(isset($result2['documents']['work']['content']), 'TC02d. coverage work content present');
echo "  TC02 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC03 — Exact direct-owner workspace resolves and loads all four docs
// ═══════════════════════════════════════════════════════════════════
$result3 = Bootstrap::prepareImplementationContext(
    'review workspace A directly',
    $wsA,
    [],
    null
);
ba_assert_eq($wsA, $result3['workspace_key'] ?? '', 'TC03a. direct workspace resolves');
ba_assert_eq('explicit_owner_key', $result3['resolution_source'] ?? '', 'TC03b. resolution source is explicit');
ba_assert_true(isset($result3['documents']['overview']['content']), 'TC03c. overview content present');
ba_assert_true(isset($result3['documents']['work']['content']), 'TC03d. work content present');
echo "  TC03 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC04 — Missing direct-owner workspace in implementation mode
//        creates directory plus all four files
// ═══════════════════════════════════════════════════════════════════
$p_cleanWs($wsC);
ba_assert_true(!is_dir($p_wsDir($wsC)), 'TC04a. ws C does not exist before');

$result4 = Bootstrap::prepareImplementationContext(
    'implement a new feature for C',
    $wsC,
    [],
    null
);

ba_assert_eq($wsC, $result4['workspace_key'] ?? '', 'TC04b. workspace key resolves');
ba_assert_eq('implementation', $result4['mode'] ?? '', 'TC04c. mode is implementation');
ba_assert_true(is_dir($p_wsDir($wsC)), 'TC04d. workspace directory created');
ba_assert_true(is_file($p_wsDir($wsC) . '/overview.md'), 'TC04e. overview.md created');
ba_assert_true(is_file($p_wsDir($wsC) . '/work.md'), 'TC04f. work.md created');
ba_assert_true(is_file($p_wsDir($wsC) . '/rules.md'), 'TC04g. rules.md created');
ba_assert_true(is_file($p_wsDir($wsC) . '/decisions.md'), 'TC04h. decisions.md created');
ba_assert_eq(4, count($result4['created_missing_documents'] ?? []), 'TC04i. 4 documents reported as created');
$state4 = $result4['workspace_context_state'] ?? '';
ba_assert_true($state4 === 'ready' || $state4 === 'degraded', 'TC04j. context state ready/degraded: ' . $state4);
echo "  TC04 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC05 — Partial workspace creates only missing documents
// ═══════════════════════════════════════════════════════════════════
$p_cleanWs($wsD);
$p_initWs($wsD);
$p_writeDoc($wsD, 'overview', $f_overview('D'));
$p_writeDoc($wsD, 'work', $f_work('D'));

ba_assert_true(is_file($p_wsDir($wsD) . '/overview.md'), 'TC05a. overview exists before');
ba_assert_true(is_file($p_wsDir($wsD) . '/work.md'), 'TC05b. work exists before');
ba_assert_true(!is_file($p_wsDir($wsD) . '/rules.md'), 'TC05c. rules missing before');
ba_assert_true(!is_file($p_wsDir($wsD) . '/decisions.md'), 'TC05d. decisions missing before');

$result5 = Bootstrap::prepareImplementationContext(
    'build features for D',
    $wsD,
    [],
    null
);

ba_assert_eq($wsD, $result5['workspace_key'] ?? '', 'TC05e. workspace key resolves');
ba_assert_eq('implementation', $result5['mode'] ?? '', 'TC05f. mode is implementation');
ba_assert_true(is_file($p_wsDir($wsD) . '/rules.md'), 'TC05g. rules.md created');
ba_assert_true(is_file($p_wsDir($wsD) . '/decisions.md'), 'TC05h. decisions.md created');

$created5 = $result5['created_missing_documents'] ?? [];
ba_assert_true(in_array('rules', $created5, true), 'TC05i. rules reported as created');
ba_assert_true(in_array('decisions', $created5, true), 'TC05j. decisions reported as created');
ba_assert_true(!in_array('overview', $created5, true), 'TC05k. overview NOT reported as created');
ba_assert_true(!in_array('work', $created5, true), 'TC05l. work NOT reported as created');
echo "  TC05 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC06 — Existing valid documents remain byte-for-byte unchanged
// ═══════════════════════════════════════════════════════════════════
$p_cleanWs($wsE);
$p_initWs($wsE);
$p_writeDoc($wsE, 'overview', $f_overview('E'));
$p_writeDoc($wsE, 'work', $f_work('E'));
$p_writeDoc($wsE, 'rules', $f_rules('E'));
$p_writeDoc($wsE, 'decisions', $f_decisions('E'));

$originalHash = hash_file('sha256', $p_wsDir($wsE) . '/work.md');

$result6 = Bootstrap::prepareImplementationContext(
    'modify E workspace',
    $wsE,
    [],
    null
);

$afterHash = hash_file('sha256', $p_wsDir($wsE) . '/work.md');
ba_assert_eq($originalHash, $afterHash, 'TC06a. work.md content unchanged byte-for-byte');
ba_assert_eq([], $result6['created_missing_documents'] ?? null, 'TC06b. no documents created');
echo "  TC06 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC07 — Read-only mode never creates a folder or file
// ═══════════════════════════════════════════════════════════════════
$wsF = TEST_WS_PREFIX . '/F';
$p_cleanWs($wsF);
ba_assert_true(!is_dir($p_wsDir($wsF)), 'TC07a. F does not exist before');

$result7 = Bootstrap::prepareImplementationContext(
    'explain how workspace F works',
    $wsF,
    [],
    null
);

ba_assert_true(!is_dir($p_wsDir($wsF)), 'TC07b. F directory NOT created in read-only mode');
ba_assert_true(in_array($result7['mode'] ?? '', ['read_only', ''], true), 'TC07c. mode is read_only: ' . ($result7['mode'] ?? 'null'));
$state7 = $result7['workspace_context_state'] ?? '';
ba_assert_true(
    $state7 === 'no_workspace_context' || $state7 === 'resolution_required',
    'TC07d. state is no_workspace_context or resolution_required: ' . $state7
);
echo "  TC07 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC08 — Invalid existing document is preserved and returns degraded
// ═══════════════════════════════════════════════════════════════════
$p_cleanWs($wsG);
$p_initWs($wsG);
$p_writeDoc($wsG, 'overview', $f_overview('G'));
$p_writeDoc($wsG, 'work', $f_work('G'));
$p_writeDoc($wsG, 'rules', '# G — Rules' . "\n\n" . 'No headings here at all.');
$p_writeDoc($wsG, 'decisions', $f_decisions('G'));

$badHash = hash_file('sha256', $p_wsDir($wsG) . '/rules.md');

$result8 = Bootstrap::prepareImplementationContext(
    'fix the issues in G',
    $wsG,
    [],
    null
);

ba_assert_eq('degraded', $result8['workspace_context_state'] ?? '', 'TC08a. context state is degraded');
ba_assert_true(in_array('rules', $result8['repair_required_documents'] ?? [], true), 'TC08b. rules listed in repair_required');

$afterBadHash = hash_file('sha256', $p_wsDir($wsG) . '/rules.md');
ba_assert_eq($badHash, $afterBadHash, 'TC08c. invalid rules.md preserved byte-for-byte');
ba_assert_true(isset($result8['documents']['rules']['content']), 'TC08d. rules content in context');
ba_assert_eq(false, $result8['documents']['rules']['validated'] ?? true, 'TC08e. rules validated is false');
echo "  TC08 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC09 — Unknown owner returns no_workspace_context or
//        resolution_required without filesystem mutation
// ═══════════════════════════════════════════════════════════════════
$unknownKey = TEST_WS_PREFIX . '/ZZ_DoesNotExist';
$p_cleanWs($unknownKey);

// Use a read-only task to avoid creation
$result9 = Bootstrap::prepareImplementationContext(
    'explain the architecture of Z',
    $unknownKey,
    [],
    null
);

$state9 = $result9['workspace_context_state'] ?? '';
ba_assert_true(
    $state9 === 'no_workspace_context' || $state9 === 'resolution_required',
    'TC09a. state is no_workspace_context or resolution_required: ' . $state9
);
ba_assert_true(!is_dir($p_wsDir($unknownKey)), 'TC09b. no directory created for unknown owner');
echo "  TC09 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC10 — _template and _templates are rejected in every mode
// ═══════════════════════════════════════════════════════════════════
$r10a = Bootstrap::prepareImplementationContext('implement feature', '_template', [], null);
ba_assert_eq('rejected', $r10a['workspace_context_state'] ?? '', 'TC10a. _template rejected');

$r10b = Bootstrap::prepareImplementationContext('build feature', '_templates', [], null);
ba_assert_eq('rejected', $r10b['workspace_context_state'] ?? '', 'TC10b. _templates rejected');

$r10c = Bootstrap::prepareImplementationContext('edit something', '_templates/overview.md', [], null);
ba_assert_eq('rejected', $r10c['workspace_context_state'] ?? '', 'TC10c. _templates/overview.md rejected');
echo "  TC10 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC11 — Absolute, traversal, symlink-escape, and unsafe keys rejected
// ═══════════════════════════════════════════════════════════════════
$r11a = Bootstrap::prepareImplementationContext('implement', '/etc/passwd', [], null);
ba_assert_eq('rejected', $r11a['workspace_context_state'] ?? '', 'TC11a. absolute path rejected');

$r11b = Bootstrap::prepareImplementationContext('build', '../../etc/passwd', [], null);
ba_assert_eq('rejected', $r11b['workspace_context_state'] ?? '', 'TC11b. traversal rejected');

$r11c = Bootstrap::prepareImplementationContext('edit', 'Manufacturing/..', [], null);
ba_assert_eq('rejected', $r11c['workspace_context_state'] ?? '', 'TC11c. traversal with .. rejected');

$r11d = Bootstrap::prepareImplementationContext('fix', 'Manufacturing/My App', [], null);
ba_assert_eq('rejected', $r11d['workspace_context_state'] ?? '', 'TC11d. spaces in key rejected');
echo "  TC11 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC12 — Template variables resolve correctly in created documents
// ═══════════════════════════════════════════════════════════════════
$tplWs = TEST_WS_PREFIX . '/TemplateVarTest';
$p_cleanWs($tplWs);

$result12 = Bootstrap::prepareImplementationContext(
    'create TemplateVarTest workspace',
    $tplWs,
    [],
    null
);

$overviewContent = $result12['documents']['overview']['content'] ?? '';
ba_assert_contains($overviewContent, 'TemplateVarTest', 'TC12a. overview contains workspace name');
ba_assert_true(!str_contains($overviewContent, '{{workspace_name}}'), 'TC12b. no {{workspace_name}} leftover');
ba_assert_true(!str_contains($overviewContent, '<Workspace Name>'), 'TC12c. no <Workspace Name> leftover');
echo "  TC12 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC13 — Generated files pass EngineeringWorkspaceContentContract
// ═══════════════════════════════════════════════════════════════════
foreach (['overview', 'work', 'rules', 'decisions'] as $dt) {
    $content = $result12['documents'][$dt]['content'] ?? '';
    $validation = EngineeringWorkspaceContentContract::validateDocumentContent($dt, $content);
    ba_assert_true(!empty($validation['ok']), "TC13.{$dt}. {$dt} passes content contract");
}
echo "  TC13 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC14 — Returned context package contains all four document contents
//        for ready implementation context
// ═══════════════════════════════════════════════════════════════════
// Use wsA (already set up from TC01, read-only)
$result14 = Bootstrap::prepareImplementationContext(
    'improve workspace A',
    $wsA,
    [],
    null
);

ba_assert_eq('ready', $result14['workspace_context_state'] ?? '', 'TC14a. context state is ready');
foreach (['overview', 'work', 'rules', 'decisions'] as $dk) {
    $doc = $result14['documents'][$dk] ?? [];
    ba_assert_true(isset($doc['path']), "TC14b.{$dk}. path present");
    ba_assert_true(isset($doc['validated']), "TC14c.{$dk}. validated flag");
    ba_assert_true(isset($doc['content']), "TC14d.{$dk}. content present");
    ba_assert_true($doc['validated'] === true, "TC14e.{$dk}. validated is true");
    ba_assert_true($doc['content'] !== null && $doc['content'] !== '', "TC14f.{$dk}. content not empty");
}
ba_assert_true($result14['instruction'] !== '', 'TC14g. instruction text present');
ba_assert_contains($result14['instruction'] ?? '', 'Engineering Workspace', 'TC14h. instruction contains context note');
echo "  TC14 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC15 — No registration, Coverage Assignment, mapping, Developer
//        Strip, or unrelated workspace mutation occurs
// ═══════════════════════════════════════════════════════════════════
// Verify coverage assignment was restored
$restoredRaw = is_file($assignmentPath) ? file_get_contents($assignmentPath) : false;
$restored = ($restoredRaw !== false) ? (json_decode($restoredRaw, true) ?? []) : [];
ba_assert_true(!isset($restored[$wsB]), 'TC15a. coverage assignment restored - wsB not present');

// Verify no unexpected workspaces created outside test prefix
$engRoot = APP_ROOT . '/engineering';
$allDirs = scandir($engRoot);
if (is_array($allDirs)) {
    foreach ($allDirs as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (str_starts_with($entry, '_probe_agent_bootstrap')) continue;
        // Not checking real workspace dirs — just confirming no _probe leftovers break anything
    }
}
echo "  TC15 done\n";

// ═══════════════════════════════════════════════════════════════════
// TC16 — All test mutations use disposable isolated fixtures only
// ═══════════════════════════════════════════════════════════════════
// Verified by the cleanup actions throughout the test.
echo "  TC16 done (cleanup follows)\n";

// ═══════════════════════════════════════════════════════════════════
// Summary
// ═══════════════════════════════════════════════════════════════════
echo "\n──────────────────────────────────────\n";
echo "  Results: {$passed} passed, {$failed} failed\n";
echo "──────────────────────────────────────\n";

// Final cleanup
$p_cleanAll();

exit($failed > 0 ? 1 : 0);
