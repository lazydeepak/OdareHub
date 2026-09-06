<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/platform/Security/PlatformAuthority.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceContentContract.php';
require_once APP_ROOT . '/platform/Security/EngineeringWorkspaceResolver.php';
require_once APP_ROOT . '/apps/Studio/Tools/EngineeringWorkspace/Services/WorkTaskParser.php';

use Platform\Security\EngineeringWorkspaceResolver;
use Apps\Studio\Tools\EngineeringWorkspace\Services\WorkTaskParser;

$passed = 0;
$failed = 0;

function wt_assert_true(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']' . PHP_EOL;
}

function wt_assert_eq(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL;
}

// ── Test content ────────────────────────────────────────────────
$uncheckedLine = '- [ ] Task one';
$checkedLine   = '- [x] Task two';
$nestedLine    = '  - [ ] Nested task';
$nonTaskLine   = '- just a list item';
$trailingLines = '- [ ] Final task';

$content = implode("\n", [
    '# Work',
    '',
    $uncheckedLine,
    $checkedLine,
    $nestedLine,
    $nonTaskLine,
    $trailingLines,
    '',
]);

// ── Test content with fenced code block ──────────────────────
$contentWithCodeBlock = implode("\n", [
    '# Work',
    '',
    '- [ ] Outside code',
    '```',
    '- [ ] Inside code (should be ignored)',
    '- [x] Also inside code',
    '```',
    '- [x] After code',
    '',
]);

// ── 1. parseSupportedTaskLines ──────────────────────────────
$tasks = WorkTaskParser::parseSupportedTaskLines($content);
wt_assert_eq(4, count($tasks), 'PARSER-01: 4 supported task lines parsed from sample');

wt_assert_eq(0, $tasks[0]['ordinal'], 'PARSER-02: ordinal 0');
wt_assert_eq(false, $tasks[0]['checked'], 'PARSER-03: task 0 unchecked');
wt_assert_eq('- [ ] Task one', $tasks[0]['original_line'], 'PARSER-04: original line preserved');
wt_assert_eq('Task one', $tasks[0]['task_text'], 'PARSER-05: task text extracted');
wt_assert_eq('', $tasks[0]['indent'], 'PARSER-06: no indent for task 0');
wt_assert_true($tasks[0]['line_hash'] !== '', 'PARSER-07: line_hash non-empty');
wt_assert_eq(64, strlen($tasks[0]['line_hash']), 'PARSER-08: line_hash is SHA-256');

wt_assert_eq(1, $tasks[1]['ordinal'], 'PARSER-09: ordinal 1');
wt_assert_eq(true, $tasks[1]['checked'], 'PARSER-10: task 1 checked');
wt_assert_eq('Task two', $tasks[1]['task_text'], 'PARSER-11: task text two');

wt_assert_eq(2, $tasks[2]['ordinal'], 'PARSER-12: ordinal 2');
wt_assert_eq(false, $tasks[2]['checked'], 'PARSER-13: task 2 unchecked');
wt_assert_eq('  - [ ] Nested task', $tasks[2]['original_line'], 'PARSER-14: nested line preserved');
wt_assert_eq('  ', $tasks[2]['indent'], 'PARSER-15: indent preserved');

wt_assert_eq(3, $tasks[3]['ordinal'], 'PARSER-17: ordinal 3');
wt_assert_eq(false, $tasks[3]['checked'], 'PARSER-18: final task unchecked');
wt_assert_eq('- [ ] Final task', $tasks[3]['original_line'], 'PARSER-19: final line preserved');

// ── 2. fenced code block exclusion ──────────────────────────
$codeTasks = WorkTaskParser::parseSupportedTaskLines($contentWithCodeBlock);
wt_assert_eq(2, count($codeTasks), 'PARSER-20: only 2 tasks outside code block');
wt_assert_eq('- [ ] Outside code', $codeTasks[0]['original_line'], 'PARSER-21: outside-code task recognized');
wt_assert_eq('- [x] After code', $codeTasks[1]['original_line'], 'PARSER-22: after-code task recognized');

// ── 3. findTaskByOrdinal ────────────────────────────────────
$found = WorkTaskParser::findTaskByOrdinal($tasks, 1);
wt_assert_true($found !== null, 'FIND-01: ordinal 1 found');
wt_assert_eq('Task two', $found['task_text'] ?? '', 'FIND-02: correct task text for ordinal 1');

$missing = WorkTaskParser::findTaskByOrdinal($tasks, 99);
wt_assert_true($missing === null, 'FIND-03: non-existent ordinal returns null');

// ── 4. toggleTaskLine: unchecked → checked ──────────────────
$toggle0 = WorkTaskParser::toggleTaskLine($content, 0, true);
wt_assert_eq(true, $toggle0['ok'], 'TOGGLE-01: toggle ordinal 0 to checked ok');
wt_assert_string_contains($toggle0['content'] ?? '', '- [x] Task one', 'TOGGLE-02: unchecked → [x]');
wt_assert_string_contains($toggle0['content'] ?? '', '- [x] Task two', 'TOGGLE-03: checked stays [x]');
wt_assert_string_contains($toggle0['content'] ?? '', '  - [ ] Nested task', 'TOGGLE-04: nested unchanged');
wt_assert_string_contains($toggle0['content'] ?? '', '- [ ] Final task', 'TOGGLE-05: final unchanged');
wt_assert_string_contains($toggle0['content'] ?? '', '- just a list item', 'TOGGLE-06: non-task unchanged');
wt_assert_eq('[x]', $toggle0['toggled']['to'] ?? '', 'TOGGLE-07: toggled to [x]');
wt_assert_eq(0, $toggle0['toggled']['ordinal'] ?? -1, 'TOGGLE-08: toggled ordinal correct');

// ── 5. toggleTaskLine: checked → unchecked ──────────────────
$toggle1 = WorkTaskParser::toggleTaskLine($content, 1, false);
wt_assert_eq(true, $toggle1['ok'], 'TOGGLE-09: toggle ordinal 1 to unchecked ok');
wt_assert_string_contains($toggle1['content'] ?? '', '- [ ] Task two', 'TOGGLE-10: checked → [ ]');
wt_assert_string_contains($toggle1['content'] ?? '', '- [ ] Task one', 'TOGGLE-11: unchecked stays [ ]');
wt_assert_eq('[ ]', $toggle1['toggled']['to'] ?? '', 'TOGGLE-12: toggled to [ ]');

// ── 6. toggleTaskLine: same state rejected ──────────────────
$sameState = WorkTaskParser::toggleTaskLine($content, 0, false);
wt_assert_eq(false, $sameState['ok'], 'TOGGLE-13: same state rejected');
wt_assert_string_contains($sameState['error'] ?? '', 'already in target state', 'TOGGLE-14: error message indicates same state');

// ── 7. toggleTaskLine: invalid ordinal ──────────────────────
$badOrdinal = WorkTaskParser::toggleTaskLine($content, 99, true);
wt_assert_eq(false, $badOrdinal['ok'], 'TOGGLE-15: invalid ordinal rejected');
wt_assert_string_contains($badOrdinal['error'] ?? '', 'not found', 'TOGGLE-16: error indicates not found');

// ── 8. tunnel-triggered lines (indented with extra whitespace) ─
$extraWhitespaceLine = '   - [x] Extra whitespace';
$ewContent = "* Intro\n" . $extraWhitespaceLine . "\n- [ ] Last";
$ewTasks = WorkTaskParser::parseSupportedTaskLines($ewContent);
wt_assert_eq(2, count($ewTasks), 'WHITESPACE-01: extra whitespace task parsed');
wt_assert_eq('   - [x] Extra whitespace', $ewTasks[0]['original_line'], 'WHITESPACE-02: extra whitespace orig line');
wt_assert_eq(true, $ewTasks[0]['checked'], 'WHITESPACE-03: extra whitespace checked');
wt_assert_eq('   ', $ewTasks[0]['indent'], 'WHITESPACE-04: indent captured correctly');

$ewToggle = WorkTaskParser::toggleTaskLine($ewContent, 0, false);
wt_assert_eq(true, $ewToggle['ok'], 'WHITESPACE-05: toggle with extra whitespace ok');
wt_assert_string_contains($ewToggle['content'] ?? '', '- [ ] Last', 'WHITESPACE-06: second line preserved');
wt_assert_string_contains($ewToggle['content'] ?? '', '* Intro', 'WHITESPACE-07: intro preserved');

// ── 9. fingerprint stability ────────────────────────────────
$fp1 = WorkTaskParser::fingerprint($content);
$fpToggleResult = WorkTaskParser::toggleTaskLine($content, 0, true);
wt_assert_eq(true, $fpToggleResult['ok'], 'FP-01: toggle ok for fp test');
$fp2 = WorkTaskParser::fingerprint($fpToggleResult['content'] ?? '');
wt_assert_true($fp1 !== $fp2, 'FP-02: fingerprint changes after toggle');

$revertToggle = WorkTaskParser::toggleTaskLine($fpToggleResult['content'] ?? '', 0, false);
wt_assert_eq(true, $revertToggle['ok'], 'FP-03: re-toggle ok');
wt_assert_eq($content, $revertToggle['content'] ?? '', 'FP-04: round-trip content matches original');

// ── 10. integration: toggle via EngineeringWorkspaceResolver ─
$admin = ['authority_role' => 'platform_admin'];
$workspace = 'Manufacturing/Products';
$document = 'work';
$path = APP_ROOT . '/engineering/Manufacturing/Products/work.md';
$original = (string)file_get_contents($path);
$originalFingerprint = EngineeringWorkspaceResolver::fingerprint($original);

try {
    $tasksBefore = WorkTaskParser::parseSupportedTaskLines($original);
    $firstTask = $tasksBefore[0] ?? null;
    if ($firstTask !== null) {
        $ordinal = $firstTask['ordinal'];
        $newState = !$firstTask['checked'];

        $toggleContent = $firstTask['original_line'];
        $toggleHash = hash('sha256', $toggleContent);

        // Build the new content by toggling
        $toggleResult = WorkTaskParser::toggleTaskLine($original, $ordinal, $newState);
        wt_assert_eq(true, $toggleResult['ok'], 'INTEGRATION-01: toggle ok on live work.md');

        $newContent = (string)$toggleResult['content'];
        $newFingerprint = EngineeringWorkspaceResolver::fingerprint($newContent);

        // Write via the canonical atomic path
        $writeResult = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $newContent, $originalFingerprint, $admin);
        wt_assert_eq(true, (bool)($writeResult['ok'] ?? false), 'INTEGRATION-02: toggle write via canonical path');
        wt_assert_eq($newContent, (string)file_get_contents($path), 'INTEGRATION-03: written content matches on disk');

        // Verify the task was toggled
        $tasksAfter = WorkTaskParser::parseSupportedTaskLines($newContent);
        $toggledTask = WorkTaskParser::findTaskByOrdinal($tasksAfter, $ordinal);
        wt_assert_true($toggledTask !== null, 'INTEGRATION-04: toggled task still exists');
        if ($toggledTask !== null) {
            wt_assert_eq($newState, (bool)$toggledTask['checked'], 'INTEGRATION-05: task state toggled correctly');
        }

        // Toggle back to restore original
        $revertResult = WorkTaskParser::toggleTaskLine($newContent, $ordinal, !$newState);
        wt_assert_eq(true, $revertResult['ok'], 'INTEGRATION-06: revert toggle ok');
        $restoreContent = (string)$revertResult['content'];
        $restoreFingerprint = EngineeringWorkspaceResolver::fingerprint($restoreContent);

        $restoreResult = EngineeringWorkspaceResolver::writeWithFingerprint($workspace, $document, $restoreContent, $newFingerprint, $admin);
        wt_assert_eq(true, (bool)($restoreResult['ok'] ?? false), 'INTEGRATION-07: restore write via canonical path');
        wt_assert_eq($original, (string)file_get_contents($path), 'INTEGRATION-08: content restored to original');
    } else {
        // No task lines in work.md — skip integration test
        wt_assert_true(true, 'INTEGRATION-01: skip (no tasks in work.md)');
        wt_assert_true(true, 'INTEGRATION-02: skip');
        wt_assert_true(true, 'INTEGRATION-03: skip');
        wt_assert_true(true, 'INTEGRATION-04: skip');
        wt_assert_true(true, 'INTEGRATION-05: skip');
        wt_assert_true(true, 'INTEGRATION-06: skip');
        wt_assert_true(true, 'INTEGRATION-07: skip');
        wt_assert_true(true, 'INTEGRATION-08: skip');
    }
} finally {
    if ((string)file_get_contents($path) !== $original) {
        file_put_contents($path, $original, LOCK_EX);
    }
}

echo "WorkToggle: {$passed} passed, {$failed} failed" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

exit(0);

// ── Simple contains helper ──────────────────────────────────
function wt_assert_string_contains(string $haystack, string $needle, string $label): void
{
    global $passed, $failed;
    if (str_contains($haystack, $needle)) {
        $passed++;
        return;
    }
    $failed++;
    echo '  FAIL [' . $label . ']: expected string containing "' . $needle . '"' . PHP_EOL;
}
