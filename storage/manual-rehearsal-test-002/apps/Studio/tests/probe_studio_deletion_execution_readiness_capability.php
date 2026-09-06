<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionExecutionReadinessService;

require_once dirname(__DIR__) . '/Services/StudioDeletionExecutionReadinessService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};

$sort = static function ($value) use (&$sort) {
    if (!is_array($value)) { return $value; }
    if (array_is_list($value)) { return array_map($sort, $value); }
    ksort($value);
    foreach ($value as $key => $item) { $value[$key] = $sort($item); }
    return $value;
};
$fingerprintRecord = static function (array $record) use ($sort): string {
    $material = [
        'version' => (string)($record['version'] ?? $record['record_version'] ?? ''),
        'decision' => (string)($record['decision'] ?? ''),
        'recorded_at_utc' => (string)($record['recorded_at_utc'] ?? ''),
        'target' => is_array($record['target'] ?? null) ? $record['target'] : [],
        'source' => is_array($record['source'] ?? null) ? $record['source'] : [],
        'approver' => is_array($record['approver'] ?? null) ? $record['approver'] : [],
        'reason' => (string)($record['reason'] ?? ''),
        'confirmations' => is_array($record['confirmations'] ?? null) ? $record['confirmations'] : [],
        'approval_readiness_at_record' => (string)($record['approval_readiness_at_record'] ?? ''),
    ];
    $encoded = json_encode($sort($material), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return 'deletion-approval-record:' . substr(sha1((string)$encoded), 0, 24);
};

$changeSet = [
    'status' => 'ok',
    'change_set_version' => 'studio.deletion-change-set.v1',
    'change_set_state' => 'ready_for_review',
    'immutable' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'requires_approval' => 'yes',
    'target' => [
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'target_path' => 'apps/Manufacturing/modules/Products',
    ],
    'source_plan' => [
        'version' => 'studio.deletion-plan.v1',
        'state' => 'ready',
        'fingerprint' => 'deletion-plan:current',
    ],
    'approval_contract' => [
        'required' => 'yes',
        'approval_readiness' => 'ready',
        'required_confirmations' => [
            'target_identity_confirmed',
            'snapshot_created',
            'verification_contract_accepted',
        ],
    ],
    'change_set_fingerprint' => 'deletion-change-set:current',
];

$approved = [
    'version' => 'studio.deletion-approval-record.v1',
    'status' => 'recorded',
    'effect' => 'mutate',
    'record_version' => 'studio.deletion-approval-record.v1',
    'record_id' => 'deletion-approval-ready-001',
    'decision' => 'approved',
    'recorded_at_utc' => '2026-07-15T01:00:00.000000Z',
    'target' => $changeSet['target'],
    'source' => [
        'change_set_version' => 'studio.deletion-change-set.v1',
        'change_set_fingerprint' => 'deletion-change-set:current',
        'plan_fingerprint' => 'deletion-plan:current',
    ],
    'approver' => ['actor_id' => '42', 'display_name' => 'Platform Admin', 'authority_role' => 'platform_admin'],
    'reason' => 'Exact packet and rollback evidence reviewed.',
    'confirmations' => [
        'required' => $changeSet['approval_contract']['required_confirmations'],
        'provided' => $changeSet['approval_contract']['required_confirmations'],
        'satisfied' => 'yes',
    ],
    'approval_readiness_at_record' => 'ready',
    'immutable' => 'yes',
    'append_only' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'grants_execution_authority' => 'no',
    'storage' => [
        'storage_scope' => 'studio_provenance',
        'relative_path' => 'storage/studio/deletion-approvals/record.json',
        'immutable' => 'yes',
        'append_only' => 'yes',
    ],
];
$approved['record_fingerprint'] = $fingerprintRecord($approved);

$ready = StudioDeletionExecutionReadinessService::assess($changeSet, $approved, $approved);
$assert(($ready['status'] ?? '') === 'ok', 'valid approved packet returns ok status');
$assert(($ready['effect'] ?? '') === 'verify', 'capability declares verify effect');
$assert(($ready['readiness_version'] ?? '') === StudioDeletionExecutionReadinessService::VERSION, 'readiness version is explicit');
$assert(($ready['execution_readiness'] ?? '') === 'ready', 'valid approval yields ready state');
$assert(($ready['execution_eligible'] ?? '') === 'yes', 'valid approval yields execution eligibility');
$assert(($ready['approval_valid'] ?? '') === 'yes', 'valid approval is marked valid');
$assert(($ready['can_execute'] ?? '') === 'no', 'verifier cannot execute');
$assert(($ready['can_apply'] ?? '') === 'no', 'verifier cannot apply');
$assert(($ready['grants_execution_authority'] ?? '') === 'no', 'verifier grants no authority');
$assert(($ready['requires_separate_execution_capability'] ?? '') === 'yes', 'separate execution capability remains required');
$assert(($ready['approval_evidence']['exact_packet_match'] ?? '') === 'yes', 'approval is bound to exact packet');
$assert(($ready['approval_evidence']['decision'] ?? '') === 'approved', 'approval decision is exposed');
$assert(($ready['approval_evidence']['record_id'] ?? '') === 'deletion-approval-ready-001', 'record id is exposed');
$assert(($ready['approval_evidence']['approver']['actor_id'] ?? '') === '42', 'approver provenance is exposed');
$assert(($ready['approval_evidence']['confirmations_satisfied'] ?? '') === 'yes', 'confirmation satisfaction is exposed');
$assert(($ready['blocking_reasons'] ?? []) === [], 'ready state has no blockers');
$assert(str_starts_with((string)($ready['readiness_fingerprint'] ?? ''), 'deletion-execution-readiness:'), 'readiness fingerprint is emitted');

$rejectedRecord = $approved;
$rejectedRecord['record_id'] = 'deletion-approval-rejected-001';
$rejectedRecord['decision'] = 'rejected';
$rejectedRecord['reason'] = 'Runtime dependency remains unresolved.';
$rejectedRecord['confirmations'] = ['required' => $changeSet['approval_contract']['required_confirmations'], 'provided' => [], 'satisfied' => 'not_required'];
$rejectedRecord['record_fingerprint'] = $fingerprintRecord($rejectedRecord);
$rejected = StudioDeletionExecutionReadinessService::assess($changeSet, $rejectedRecord, $rejectedRecord);
$assert(($rejected['execution_readiness'] ?? '') === 'rejected', 'exact rejection yields rejected state');
$assert(($rejected['execution_eligible'] ?? '') === 'no', 'rejected packet is not eligible');
$assert(in_array('APPROVAL_REJECTED', $rejected['blocking_reasons'] ?? [], true), 'rejection blocker is explicit');

$oldRecord = $approved;
$oldRecord['record_id'] = 'deletion-approval-old-001';
$oldRecord['source']['change_set_fingerprint'] = 'deletion-change-set:old';
$oldRecord['source']['plan_fingerprint'] = 'deletion-plan:old';
$oldRecord['record_fingerprint'] = $fingerprintRecord($oldRecord);
$stale = StudioDeletionExecutionReadinessService::assess($changeSet, null, $oldRecord);
$assert(($stale['execution_readiness'] ?? '') === 'stale', 'older owner approval yields stale state');
$assert(($stale['approval_evidence']['exact_packet_match'] ?? '') === 'no', 'stale evidence is not exact packet');
$assert(in_array('APPROVAL_CHANGE_SET_STALE', $stale['blocking_reasons'] ?? [], true), 'stale change-set blocker is explicit');
$assert(in_array('APPROVAL_PLAN_STALE', $stale['blocking_reasons'] ?? [], true), 'stale plan blocker is explicit');

$missing = StudioDeletionExecutionReadinessService::assess($changeSet, null, null);
$assert(($missing['execution_readiness'] ?? '') === 'not_approved', 'missing decision yields not-approved state');
$assert(($missing['approval_evidence']['present'] ?? '') === 'no', 'missing decision exposes no evidence');
$assert(in_array('APPROVAL_RECORD_REQUIRED', $missing['blocking_reasons'] ?? [], true), 'missing approval blocker is explicit');

$tampered = $approved;
$tampered['reason'] = 'Tampered after recording.';
$blocked = StudioDeletionExecutionReadinessService::assess($changeSet, $tampered, $tampered);
$assert(($blocked['execution_readiness'] ?? '') === 'blocked', 'tampered record fails closed');
$assert(($blocked['status'] ?? '') === 'partial', 'tampered record returns partial status');
$assert(in_array('APPROVAL_RECORD_FINGERPRINT_INVALID', $blocked['blocking_reasons'] ?? [], true), 'fingerprint failure blocker is explicit');

$overAuthoritative = $approved;
$overAuthoritative['grants_execution_authority'] = 'yes';
$overAuthoritative['record_fingerprint'] = $fingerprintRecord($overAuthoritative);
$blocked = StudioDeletionExecutionReadinessService::assess($changeSet, $overAuthoritative, $overAuthoritative);
$assert(($blocked['execution_readiness'] ?? '') === 'blocked', 'over-authoritative approval fails closed');
$assert(in_array('APPROVAL_RECORD_AUTHORITY_INVALID', $blocked['blocking_reasons'] ?? [], true), 'authority blocker is explicit');

$badStorage = $approved;
$badStorage['storage']['append_only'] = 'no';
$blocked = StudioDeletionExecutionReadinessService::assess($changeSet, $badStorage, $badStorage);
$assert(($blocked['execution_readiness'] ?? '') === 'blocked', 'mutable storage evidence fails closed');
$assert(in_array('APPROVAL_STORAGE_PROVENANCE_INVALID', $blocked['blocking_reasons'] ?? [], true), 'storage provenance blocker is explicit');

$changedContract = $changeSet;
$changedContract['approval_contract']['required_confirmations'][] = 'new_confirmation_required';
$contractStale = StudioDeletionExecutionReadinessService::assess($changedContract, $approved, $approved);
$assert(($contractStale['execution_readiness'] ?? '') === 'stale', 'changed confirmation contract invalidates approval');
$assert(in_array('APPROVAL_CONFIRMATION_CONTRACT_STALE', $contractStale['blocking_reasons'] ?? [], true), 'confirmation contract blocker is explicit');

$packetBlocked = $changeSet;
$packetBlocked['approval_contract']['approval_readiness'] = 'blocked';
$stalePacket = StudioDeletionExecutionReadinessService::assess($packetBlocked, $approved, $approved);
$assert(($stalePacket['execution_readiness'] ?? '') === 'stale', 'packet becoming blocked invalidates prior approval');
$assert(in_array('CURRENT_PACKET_NOT_READY', $stalePacket['blocking_reasons'] ?? [], true), 'current packet readiness blocker is explicit');

$planMismatch = $approved;
$planMismatch['source']['plan_fingerprint'] = 'deletion-plan:other';
$planMismatch['record_fingerprint'] = $fingerprintRecord($planMismatch);
$blocked = StudioDeletionExecutionReadinessService::assess($changeSet, $planMismatch, $planMismatch);
$assert(($blocked['execution_readiness'] ?? '') === 'blocked', 'exact-record plan mismatch fails closed');
$assert(in_array('APPROVAL_PLAN_FINGERPRINT_MISMATCH', $blocked['blocking_reasons'] ?? [], true), 'plan mismatch blocker is explicit');

$targetMismatch = $approved;
$targetMismatch['target']['owner_key'] = 'Manufacturing/Other';
$targetMismatch['record_fingerprint'] = $fingerprintRecord($targetMismatch);
$blocked = StudioDeletionExecutionReadinessService::assess($changeSet, $targetMismatch, $targetMismatch);
$assert(($blocked['execution_readiness'] ?? '') === 'blocked', 'target mismatch fails closed');
$assert(in_array('APPROVAL_TARGET_OWNER_MISMATCH', $blocked['blocking_reasons'] ?? [], true), 'target mismatch blocker is explicit');

$invalidPacket = $changeSet;
$invalidPacket['can_execute'] = 'yes';
$unknown = StudioDeletionExecutionReadinessService::assess($invalidPacket, $approved, $approved);
$assert(($unknown['execution_readiness'] ?? '') === 'unknown', 'invalid current packet yields unknown state');
$assert(in_array('CURRENT_PACKET_EXECUTION_AUTHORITY_INVALID', $unknown['blocking_reasons'] ?? [], true), 'invalid packet authority blocker is explicit');

$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionExecutionReadinessService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'readiness capability contains no mutation implementation');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
