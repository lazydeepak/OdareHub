<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionChangeSetService;

require_once dirname(__DIR__) . '/Services/StudioDeletionChangeSetService.php';

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

$plan = [
    'status' => 'ok',
    'plan_version' => 'studio.deletion-plan.v1',
    'plan_state' => 'ready',
    'can_execute' => 'no',
    'target' => [
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'target_path' => 'apps/Manufacturing/modules/Products',
    ],
    'operations' => [[
        'operation_id' => 'prepare:docs',
        'phase' => 'prepare',
        'operation_type' => 'cleanup_reference',
        'owner_key' => 'Studio',
        'owner_type' => 'app',
        'path' => 'docs/products.md',
        'line_numbers' => [9, 3, 9],
        'reference_count' => 2,
        'relevance' => ['DOCUMENTATION_HISTORY'],
        'severity' => 'cleanup',
        'required' => 'no',
        'blocking' => 'no',
        'execution_state' => 'planned',
        'reason' => 'Clean documentation reference.',
        'depends_on' => [],
    ], [
        'operation_id' => 'archive:target',
        'phase' => 'archive',
        'operation_type' => 'archive_target',
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'path' => 'apps/Manufacturing/modules/Products',
        'severity' => 'required',
        'required' => 'yes',
        'blocking' => 'no',
        'execution_state' => 'planned',
        'reason' => 'Archive target.',
        'depends_on' => [],
    ], [
        'operation_id' => 'delete:target',
        'phase' => 'delete',
        'operation_type' => 'delete_target',
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'path' => 'apps/Manufacturing/modules/Products',
        'severity' => 'required',
        'required' => 'yes',
        'blocking' => 'no',
        'execution_state' => 'planned',
        'reason' => 'Delete target.',
        'depends_on' => ['archive:target'],
    ], [
        'operation_id' => 'verify:target',
        'phase' => 'verify',
        'operation_type' => 'verify_target_absent',
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'path' => 'apps/Manufacturing/modules/Products',
        'severity' => 'required',
        'required' => 'yes',
        'blocking' => 'no',
        'execution_state' => 'planned',
        'reason' => 'Verify target absence.',
        'depends_on' => ['delete:target'],
    ]],
    'archive_scope' => [[
        'scope' => 'target',
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'path' => 'apps/Manufacturing/modules/Products',
        'required' => 'yes',
        'reason' => 'ROLLBACK_SOURCE_REQUIRED',
    ]],
    'post_deletion_checks' => [[
        'check_id' => 'verify:target',
        'check_type' => 'verify_target_absent',
        'owner_key' => 'Manufacturing/Products',
        'path' => 'apps/Manufacturing/modules/Products',
        'required' => 'yes',
    ]],
    'blocking_reasons' => [],
    'dependent_owners' => [],
    'plan_fingerprint' => 'deletion-plan:test-ready',
    'diagnostics' => [],
];

$packet = StudioDeletionChangeSetService::compose([], $plan);
$assert(($packet['status'] ?? '') === 'ok', 'ready plan produces an ok packet');
$assert(($packet['effect'] ?? '') === 'plan', 'packet declares plan effect');
$assert(($packet['change_set_version'] ?? '') === 'studio.deletion-change-set.v1', 'packet version is explicit');
$assert(($packet['change_set_state'] ?? '') === 'ready_for_review', 'ready plan becomes ready for review');
$assert(($packet['immutable'] ?? '') === 'yes', 'packet is immutable');
$assert(($packet['can_execute'] ?? '') === 'no' && ($packet['can_apply'] ?? '') === 'no', 'packet grants no execution authority');
$assert(($packet['requires_approval'] ?? '') === 'yes' && ($packet['requires_snapshot'] ?? '') === 'yes', 'approval and snapshot are required');
$assert(($packet['source_plan']['fingerprint'] ?? '') === 'deletion-plan:test-ready', 'source plan fingerprint is bound');
$assert(($packet['summary']['operation_count'] ?? 0) === 4, 'operation count is preserved');
$assert(($packet['summary']['file_change_count'] ?? 0) === 1, 'file changes are derived');
$assert(($packet['summary']['archive_change_count'] ?? 0) === 1, 'archive changes are derived');
$assert(($packet['summary']['delete_change_count'] ?? 0) === 1, 'delete changes are derived');
$assert(($packet['summary']['verification_check_count'] ?? 0) === 1, 'verification checks are derived');
$assert(($packet['operation_manifest'][0]['line_numbers'] ?? []) === [3, 9], 'line evidence is normalized deterministically');
$assert(($packet['proposed_changes']['file_changes'][0]['decision_required'] ?? '') === 'no', 'cleanup change needs no decision');
$assert(($packet['proposed_changes']['archive_changes'][0]['operation_id'] ?? '') === 'archive:target', 'archive scope binds to archive operation');
$assert(($packet['proposed_changes']['delete_changes'][0]['depends_on'] ?? []) === ['archive:target'], 'delete dependencies are preserved');
$assert(($packet['approval_contract']['approval_readiness'] ?? '') === 'ready', 'approval readiness is explicit');
$assert(($packet['approval_contract']['approval_binds_to']['change_set_fingerprint'] ?? '') === ($packet['change_set_fingerprint'] ?? ''), 'approval binds to exact packet fingerprint');
$assert(in_array('snapshot_created', $packet['approval_contract']['required_confirmations'] ?? [], true), 'snapshot confirmation is required');
$assert(($packet['snapshot_contract']['archive_operation_ids'] ?? []) === ['archive:target'], 'snapshot contract names archive operation');
$assert(($packet['verification_contract']['checks'][0]['expected_evidence'] ?? '') === 'target_path_absent', 'verification evidence is explicit');
$assert(($packet['integrity_contract']['reject_on_mismatch'] ?? '') === 'yes', 'fingerprint mismatch fails closed');
$assert(StudioDeletionChangeSetService::compose([], $plan)['change_set_fingerprint'] === $packet['change_set_fingerprint'], 'fingerprint is deterministic');

$reviewPlan = $plan;
$reviewPlan['plan_state'] = 'needs_review';
$reviewPlan['plan_fingerprint'] = 'deletion-plan:test-review';
$reviewPlan['operations'][0]['severity'] = 'review';
$reviewPlan['operations'][0]['required'] = 'yes';
$reviewPlan['operations'][0]['operation_type'] = 'review_reference';
$reviewPacket = StudioDeletionChangeSetService::compose([], $reviewPlan);
$assert(($reviewPacket['change_set_state'] ?? '') === 'review_required', 'review plan remains review required');
$assert(($reviewPacket['approval_contract']['approval_readiness'] ?? '') === 'review_required', 'review packet is not approval ready');
$assert(in_array('PLAN_REVIEW_REQUIRED', $reviewPacket['blocking_reasons'] ?? [], true), 'review blocker is explicit');
$assert(($reviewPacket['proposed_changes']['file_changes'][0]['decision_required'] ?? '') === 'yes', 'review change requires a decision');

$blockedPlan = $plan;
$blockedPlan['plan_state'] = 'blocked';
$blockedPlan['plan_fingerprint'] = 'deletion-plan:test-blocked';
$blockedPlan['blocking_reasons'] = ['RUNTIME_REFERENCES_EXIST'];
$blockedPlan['operations'][0]['severity'] = 'blocking';
$blockedPacket = StudioDeletionChangeSetService::compose([], $blockedPlan);
$assert(($blockedPacket['change_set_state'] ?? '') === 'blocked', 'blocked plan stays blocked');
$assert(($blockedPacket['approval_contract']['approval_readiness'] ?? '') === 'blocked', 'blocked packet cannot be approved');
$assert(in_array('RUNTIME_REFERENCES_EXIST', $blockedPacket['blocking_reasons'] ?? [], true), 'source blocker is preserved');
$assert(in_array('PLAN_BLOCKED', $blockedPacket['blocking_reasons'] ?? [], true), 'derived blocker is added');

$invalidPlan = $plan;
unset($invalidPlan['plan_fingerprint']);
$invalidPacket = StudioDeletionChangeSetService::compose([], $invalidPlan);
$assert(($invalidPacket['change_set_state'] ?? '') === 'unknown', 'missing plan identity fails closed');
$assert(($invalidPacket['operation_manifest'] ?? []) === [], 'invalid plan produces no operation manifest');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $invalidPacket['diagnostics'] ?? []);
$assert(in_array('DELETION_CHANGE_SET_PLAN_INVALID', $codes, true), 'invalid plan diagnostic is emitted');

$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionChangeSetService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'capability contains no mutation authority');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
