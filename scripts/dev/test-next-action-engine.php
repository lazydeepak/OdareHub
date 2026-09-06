#!/usr/bin/env php
<?php
/**
 * Next Best Action Engine Validation Test
 * 
 * Test Scenarios:
 * 1. Overdue task selected as next action
 * 2. Blocking task prioritized (even if not overdue)
 * 3. Explanation displayed with downstream task count
 */

declare(strict_types=1);

require_once '/Users/lazydeepak/sbaio/app/Core/Auth.php';
require_once '/Users/lazydeepak/sbaio/apps/Studio/Services/GuiStudioService.php';

echo "=== Next Best Action Engine Validation Test ===\n\n";

// Test Scenario 1: Overdue task selected as next action
echo "TEST 1: Overdue task prioritization\n";
echo "---\n";

$task1 = [
    'id' => 'task-1',
    'title' => 'QC Review - Order #101',
    'status' => 'completed',
    'is_overdue' => true,
    'is_delayed' => true,
    'time_in_state_seconds' => 7200,
    'time_in_state_label' => '2 hours',
];

$task2 = [
    'id' => 'task-2',
    'title' => 'Dispatch - Order #102',
    'status' => 'approved',
    'is_overdue' => false,
    'is_delayed' => false,
    'time_in_state_seconds' => 600,
    'time_in_state_label' => '10 minutes',
];

$tasks = [$task1, $task2];
$result = \Apps\Studio\Services\GuiStudioService::computeNextBestAction('qc', $tasks);

echo "Tasks: 2 tasks in queue\n";
echo "  - task-1: completed, OVERDUE ✗\n";
echo "  - task-2: approved, on-track ✓\n";
echo "\nExpected: task-1 selected (overdue)\n";
echo "Result: " . ($result['next_action']['id'] ?? 'NONE') . "\n";
echo "Reason: " . ($result['reason'] ?? 'none') . "\n";

$test1Pass = ($result['next_action']['id'] ?? null) === 'task-1' && ($result['reason'] ?? null) === 'overdue';
echo "Status: " . ($test1Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test Scenario 2: Blocking task prioritized
echo "TEST 2: Blocking task prioritization\n";
echo "---\n";

$blockingTask = [
    'id' => 'task-3',
    'title' => 'Production - Order #103',
    'status' => 'in_progress',
    'is_overdue' => false,
    'is_delayed' => false,
    'time_in_state_seconds' => 1800,
    'time_in_state_label' => '30 minutes',
];

$blockedTask = [
    'id' => 'task-4',
    'title' => 'QC Review - Order #104',
    'status' => 'completed',
    'is_overdue' => false,
    'is_delayed' => false,
    'time_in_state_seconds' => 300,
    'time_in_state_label' => '5 minutes',
];

$moreNormalTasks = [
    [
        'id' => 'task-5',
        'title' => 'Assembly - Order #105',
        'status' => 'in_progress',
        'is_overdue' => false,
        'is_delayed' => false,
        'time_in_state_seconds' => 600,
        'time_in_state_label' => '10 minutes',
    ],
];

$allTasks = array_merge([$blockingTask], $moreNormalTasks, [$blockedTask]);
$result2 = \Apps\Studio\Services\GuiStudioService::computeNextBestAction('operator', $allTasks);

echo "Tasks: 3 tasks in queue\n";
echo "  - task-3: in_progress, no SLA breach, BLOCKING downstream ⚠️\n";
echo "  - task-5: in_progress, normal\n";
echo "  - task-4: completed, normal (blocked by task-3)\n";
echo "\nExpected: task-3 selected (blocking)\n";
echo "Result: " . ($result2['next_action']['id'] ?? 'NONE') . "\n";
echo "Reason: " . ($result2['reason'] ?? 'none') . "\n";
echo "Blocked downstream: " . ($result2['blocked_downstream_count'] ?? 0) . "\n";

$test2Pass = ($result2['next_action']['id'] ?? null) === 'task-3' && ($result2['reason'] ?? null) === 'blocking_others';
echo "Status: " . ($test2Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test Scenario 3: Explanation displayed
echo "TEST 3: Explanation generation\n";
echo "---\n";

echo "Explanation for blocking task:\n";
echo "  Raw: " . ($result2['explanation'] ?? 'NONE') . "\n";
echo "  Blocking: " . ($result2['blocking_explanation'] ?? 'NONE') . "\n";
echo "  Blocked count: " . ($result2['blocked_downstream_count'] ?? 0) . "\n";

$test3Pass = ($result2['blocked_downstream_count'] ?? 0) > 0 && 
             !empty($result2['blocking_explanation']) &&
             str_contains((string)($result2['blocking_explanation'] ?? ''), 'downstream');

echo "Status: " . ($test3Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Test Scenario 4: Empty queue
echo "TEST 4: Empty queue handling\n";
echo "---\n";

$emptyResult = \Apps\Studio\Services\GuiStudioService::computeNextBestAction('operator', []);
echo "Tasks: 0 tasks\n";
echo "Expected: has_next_action = false\n";
echo "Result: " . ($emptyResult['has_next_action'] ? 'true' : 'false') . "\n";
echo "Reason: " . ($emptyResult['reason'] ?? 'none') . "\n";

$test4Pass = !($emptyResult['has_next_action'] ?? false) && ($emptyResult['reason'] ?? null) === 'no_tasks';
echo "Status: " . ($test4Pass ? "✅ PASS" : "❌ FAIL") . "\n\n";

// Summary
echo "=== SUMMARY ===\n";
$allPass = $test1Pass && $test2Pass && $test3Pass && $test4Pass;
echo "Test 1 (Overdue priority): " . ($test1Pass ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Test 2 (Blocking priority): " . ($test2Pass ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Test 3 (Explanation):       " . ($test3Pass ? "✅ PASS" : "❌ FAIL") . "\n";
echo "Test 4 (Empty queue):       " . ($test4Pass ? "✅ PASS" : "❌ FAIL") . "\n";
echo "\nOverall: " . ($allPass ? "✅ ALL TESTS PASSED" : "❌ SOME TESTS FAILED") . "\n";

exit($allPass ? 0 : 1);
?>
