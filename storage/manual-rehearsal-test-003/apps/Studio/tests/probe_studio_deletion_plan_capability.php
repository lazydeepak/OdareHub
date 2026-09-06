<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Services/StudioDeletionPlanService.php';

use Apps\Studio\Services\StudioDeletionPlanService;

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$message}\n";
        return;
    }
    $fails++;
    echo "FAIL: {$message}\n";
};

$baseTarget = [
    'owner_key' => 'Sales/Orders',
    'target_type' => 'owner',
    'target_path' => 'apps/Sales/modules/Orders',
];
$blockedImpact = [
    'status' => 'ok',
    'target' => $baseTarget,
    'deletion_readiness' => 'blocked',
    'safe_to_delete' => 'no',
    'blocking_reasons' => ['RUNTIME_REFERENCES_EXIST'],
    'summary' => ['runtime_blockers' => 2, 'review_required' => 1, 'cleanup_only' => 1],
    'dependent_owners' => [[
        'owner_key' => 'Inventory/Stock',
        'owner_type' => 'module',
        'severity' => 'blocking',
        'reference_count' => 2,
        'files' => ['apps/Inventory/modules/Stock/manifest.php'],
    ]],
    'references' => [[
        'file_path' => 'apps/Inventory/modules/Stock/manifest.php',
        'line_number' => 11,
        'impact_severity' => 'blocking',
        'referencing_owner_key' => 'Inventory/Stock',
        'referencing_owner_type' => 'module',
        'relevance' => 'RUNTIME_BLOCKING',
    ], [
        'file_path' => 'apps/Inventory/modules/Stock/manifest.php',
        'line_number' => 17,
        'impact_severity' => 'blocking',
        'referencing_owner_key' => 'Inventory/Stock',
        'referencing_owner_type' => 'module',
        'relevance' => 'RUNTIME_BLOCKING',
    ], [
        'file_path' => 'apps/Studio/tests/probe_orders.php',
        'line_number' => 4,
        'impact_severity' => 'review',
        'referencing_owner_key' => 'Studio',
        'referencing_owner_type' => 'studio',
        'relevance' => 'STUDIO_TOOLING',
    ], [
        'file_path' => 'docs/orders-history.md',
        'line_number' => 8,
        'impact_severity' => 'cleanup',
        'referencing_owner_key' => '',
        'referencing_owner_type' => '',
        'relevance' => 'DOCUMENTATION_HISTORY',
    ]],
    'diagnostics' => [],
];

$plan = StudioDeletionPlanService::compose(['owner_key' => 'Sales/Orders'], $blockedImpact);
$assert(($plan['effect'] ?? '') === 'plan', 'capability declares plan effect');
$assert(($plan['plan_version'] ?? '') === 'studio.deletion-plan.v1', 'plan version is stable');
$assert(($plan['plan_state'] ?? '') === 'blocked', 'runtime references block plan');
$assert(($plan['can_execute'] ?? '') === 'no', 'V1 is never executable');
$assert(($plan['requires_approval'] ?? '') === 'yes', 'approval requirement is explicit');
$assert(($plan['requires_snapshot'] ?? '') === 'yes', 'snapshot requirement is explicit');
$assert(($plan['rollback_requirement'] ?? '') === 'archive_before_delete', 'rollback requirement is explicit');
$assert(($plan['summary']['reference_change_count'] ?? -1) === 3, 'references are grouped by severity and file');
$assert(($plan['summary']['blocking_reference_changes'] ?? -1) === 1, 'blocking file change count is correct');
$assert(($plan['summary']['review_reference_changes'] ?? -1) === 1, 'review file change count is correct');
$assert(($plan['summary']['cleanup_reference_changes'] ?? -1) === 1, 'cleanup file change count is correct');
$assert(($plan['summary']['archive_item_count'] ?? -1) === 1, 'archive scope contains target');
$assert(($plan['summary']['delete_item_count'] ?? -1) === 1, 'one target deletion is planned');
$assert(($plan['summary']['verification_count'] ?? -1) === 3, 'owner deletion has three verification checks');
$assert(count($plan['operations'] ?? []) === 8, 'blocked owner plan has eight ordered operations');
$assert(($plan['operations'][0]['operation_type'] ?? '') === 'remove_blocking_reference', 'blocking references are first');
$assert(($plan['operations'][1]['operation_type'] ?? '') === 'review_reference', 'review references follow blockers');
$assert(($plan['operations'][2]['operation_type'] ?? '') === 'cleanup_reference', 'cleanup references follow review');
$assert(($plan['operations'][0]['line_numbers'] ?? []) === [11, 17], 'same-file blocking references are consolidated');
$assert(($plan['operations'][0]['required'] ?? '') === 'yes', 'blocking reference change is required');
$assert(($plan['operations'][1]['execution_state'] ?? '') === 'review_required', 'ambiguous reference requires review');
$assert(($plan['operations'][2]['required'] ?? '') === 'no', 'documentation cleanup is non-blocking');
$assert(($plan['operations'][3]['operation_type'] ?? '') === 'archive_target', 'archive occurs before deletion');
$assert(($plan['operations'][4]['operation_type'] ?? '') === 'delete_target', 'target deletion follows archive');
$assert(($plan['operations'][4]['execution_state'] ?? '') === 'blocked', 'target deletion remains blocked');
$assert(in_array($plan['operations'][0]['operation_id'], $plan['operations'][3]['depends_on'] ?? [], true), 'archive depends on blocking cleanup');
$assert(in_array($plan['operations'][1]['operation_id'], $plan['operations'][3]['depends_on'] ?? [], true), 'archive depends on review decision');
$assert(in_array($plan['operations'][3]['operation_id'], $plan['operations'][4]['depends_on'] ?? [], true), 'delete depends on archive');
$assert(($plan['archive_scope'][0]['path'] ?? '') === 'apps/Sales/modules/Orders', 'archive scope uses canonical target path');
$assert(count($plan['deletion_order'] ?? []) === count($plan['operations'] ?? []), 'deletion order covers every operation');
$assert(($plan['post_deletion_checks'][0]['check_type'] ?? '') === 'verify_target_absent', 'target absence check is planned');
$assert(($plan['post_deletion_checks'][1]['check_type'] ?? '') === 'verify_no_inbound_references', 'reference re-scan is planned');
$assert(($plan['post_deletion_checks'][2]['check_type'] ?? '') === 'verify_owner_unregistered', 'owner registry absence check is planned');
$assert(in_array('RUNTIME_REFERENCES_EXIST', $plan['blocking_reasons'] ?? [], true), 'impact blocking reason is preserved');
$assert(($plan['dependent_owners'][0]['owner_key'] ?? '') === 'Inventory/Stock', 'dependent owners are preserved');
$assert(str_starts_with((string)($plan['plan_fingerprint'] ?? ''), 'deletion-plan:'), 'plan fingerprint has stable namespace');
$assert($plan['plan_fingerprint'] === StudioDeletionPlanService::compose(['owner_key' => 'Sales/Orders'], $blockedImpact)['plan_fingerprint'], 'same evidence produces deterministic fingerprint');

$readyImpact = $blockedImpact;
$readyImpact['deletion_readiness'] = 'ready_with_cleanup';
$readyImpact['safe_to_delete'] = 'yes';
$readyImpact['blocking_reasons'] = [];
$readyImpact['references'] = [$blockedImpact['references'][3]];
$readyPlan = StudioDeletionPlanService::compose([], $readyImpact);
$assert(($readyPlan['plan_state'] ?? '') === 'ready', 'cleanup-only impact yields ready plan');
$deleteRows = array_values(array_filter($readyPlan['operations'], static fn(array $row): bool => ($row['operation_type'] ?? '') === 'delete_target'));
$assert(($deleteRows[0]['execution_state'] ?? '') === 'planned', 'ready target deletion is planned');
$assert(($deleteRows[0]['blocking'] ?? '') === 'no', 'ready deletion is not blocked');
$assert(($readyPlan['summary']['cleanup_reference_changes'] ?? 0) === 1, 'cleanup reference remains visible');

$componentImpact = [
    'status' => 'ok',
    'target' => [
        'owner_key' => 'Sales/Orders',
        'target_type' => 'component',
        'target_path' => 'apps/Sales/modules/Orders/Services/LegacyService.php',
    ],
    'deletion_readiness' => 'ready',
    'safe_to_delete' => 'yes',
    'blocking_reasons' => [],
    'summary' => [],
    'references' => [],
    'dependent_owners' => [],
    'diagnostics' => [],
];
$componentPlan = StudioDeletionPlanService::compose([], $componentImpact);
$assert(($componentPlan['plan_state'] ?? '') === 'ready', 'component plan can be ready');
$assert(($componentPlan['summary']['verification_count'] ?? -1) === 2, 'component deletion omits owner-unregistered check');
$assert(count($componentPlan['operations'] ?? []) === 4, 'ready component plan has archive, delete, and two checks');

$reviewImpact = $componentImpact;
$reviewImpact['deletion_readiness'] = 'needs_review';
$reviewImpact['safe_to_delete'] = 'review';
$reviewPlan = StudioDeletionPlanService::compose([], $reviewImpact);
$assert(($reviewPlan['plan_state'] ?? '') === 'needs_review', 'impact review state maps to plan review state');
$assert(($reviewPlan['operations'][0]['execution_state'] ?? '') === 'review_required', 'archive waits for review decision');

$unknownPlan = StudioDeletionPlanService::compose([], [
    'status' => 'error',
    'deletion_readiness' => 'unknown',
    'blocking_reasons' => [],
    'diagnostics' => [],
]);
$assert(($unknownPlan['plan_state'] ?? '') === 'unknown', 'missing target fails closed');
$assert(($unknownPlan['can_execute'] ?? '') === 'no', 'unknown plan remains non-executable');
$assert(($unknownPlan['operations'] ?? []) === [], 'unknown plan creates no deletion operations');
$assert(in_array('IMPACT_ASSESSMENT_INCOMPLETE', $unknownPlan['blocking_reasons'] ?? [], true), 'incomplete assessment is a blocking reason');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $unknownPlan['diagnostics'] ?? []);
$assert(in_array('DELETION_PLAN_TARGET_UNAVAILABLE', $codes, true), 'missing target diagnostic is emitted');

$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionPlanService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'capability contains no mutation authority');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
