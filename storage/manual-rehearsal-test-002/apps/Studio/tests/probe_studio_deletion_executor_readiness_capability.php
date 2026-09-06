<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionExecutionRequestUseEvidenceStore;
use Apps\Studio\Services\StudioDeletionExecutorReadinessService;

require_once dirname(__DIR__) . '/Services/StudioDeletionExecutorReadinessService.php';
require_once dirname(__DIR__) . '/Services/StudioDeletionExecutionRequestUseEvidenceStore.php';

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
$requestFingerprint = static function (array $request) use ($sort): string {
    $material = [
        'version' => (string)($request['version'] ?? $request['request_version'] ?? ''),
        'request_state' => (string)($request['request_state'] ?? ''),
        'requested_at_utc' => (string)($request['requested_at_utc'] ?? ''),
        'expires_at_utc' => (string)($request['expires_at_utc'] ?? ''),
        'target' => is_array($request['target'] ?? null) ? $request['target'] : [],
        'source' => is_array($request['source'] ?? null) ? $request['source'] : [],
        'requester' => is_array($request['requester'] ?? null) ? $request['requester'] : [],
        'executor_policy' => is_array($request['executor_policy'] ?? null) ? $request['executor_policy'] : [],
        'reason' => (string)($request['reason'] ?? ''),
        'execution_contract' => is_array($request['execution_contract'] ?? null) ? $request['execution_contract'] : [],
    ];
    $encoded = json_encode($sort($material), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return 'deletion-execution-request:' . substr(sha1((string)$encoded), 0, 24);
};

$changeSet = [
    'immutable' => 'yes', 'can_execute' => 'no', 'can_apply' => 'no',
    'change_set_fingerprint' => 'deletion-change-set:current',
    'source_plan' => ['fingerprint' => 'deletion-plan:current'],
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_type' => 'owner', 'target_path' => 'apps/Manufacturing/modules/Products'],
];
$readiness = [
    'effect' => 'verify', 'execution_readiness' => 'ready', 'execution_eligible' => 'yes', 'approval_valid' => 'yes',
    'can_execute' => 'no', 'can_apply' => 'no', 'grants_execution_authority' => 'no',
    'readiness_fingerprint' => 'deletion-execution-readiness:current',
    'current_packet' => ['change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current'],
    'approval_evidence' => ['exact_packet_match' => 'yes', 'decision' => 'approved', 'record_id' => 'approval-current', 'record_fingerprint' => 'approval-fingerprint-current'],
];
$dryRun = [
    'effect' => 'simulate', 'dry_run_state' => 'ready', 'simulation_complete' => 'yes', 'execution_eligible_at_simulation' => 'yes',
    'would_execute' => 'no', 'would_apply' => 'no', 'would_write' => 'no', 'would_archive' => 'no', 'would_delete' => 'no',
    'can_execute' => 'no', 'can_apply' => 'no', 'grants_execution_authority' => 'no', 'blocking_reasons' => [],
    'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
    'source' => [
        'change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current',
        'readiness_fingerprint' => 'deletion-execution-readiness:current', 'approval_record_id' => 'approval-current',
        'approval_record_fingerprint' => 'approval-fingerprint-current',
    ],
];
$request = [
    'version' => 'studio.deletion-execution-request.v1', 'status' => 'recorded', 'effect' => 'mutate',
    'request_version' => 'studio.deletion-execution-request.v1', 'request_state' => 'requested',
    'request_id' => 'deletion-execution-request-current',
    'requested_at_utc' => '2026-07-17T00:00:00.000000Z', 'expires_at_utc' => '2026-07-17T02:00:00.000000Z',
    'target' => $changeSet['target'],
    'source' => [
        'change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current',
        'readiness_fingerprint' => 'deletion-execution-readiness:current', 'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
        'approval_record_id' => 'approval-current', 'approval_record_fingerprint' => 'approval-fingerprint-current',
    ],
    'requester' => ['actor_id' => 'requester-1', 'display_name' => 'Requester', 'authority_role' => 'platform_admin'],
    'executor_policy' => [
        'executor_actor_id' => 'executor-2', 'executor_display_name' => 'Executor', 'allowed_authority_roles' => ['platform_admin'],
        'required_authority_role' => 'platform_admin', 'executor_must_differ_from_requester' => 'yes',
        'requires_identity_revalidation' => 'yes', 'requires_current_packet_revalidation' => 'yes', 'single_use' => 'yes',
    ],
    'reason' => 'Execute the approved and simulated deletion packet.',
    'execution_contract' => [
        'requires_snapshot_before_execution' => 'yes', 'requires_current_approval' => 'yes',
        'requires_current_readiness' => 'yes', 'requires_current_dry_run' => 'yes', 'request_is_authority' => 'no',
    ],
    'immutable' => 'yes', 'append_only' => 'yes', 'can_execute' => 'no', 'can_apply' => 'no', 'can_archive' => 'no', 'can_delete' => 'no',
    'execution_authorized' => 'no', 'grants_execution_authority' => 'no', 'requires_separate_execution_capability' => 'yes',
    'storage' => ['storage_scope' => 'studio_provenance', 'relative_path' => 'storage/studio/deletion-execution-requests/request.json', 'immutable' => 'yes', 'append_only' => 'yes'],
];
$request['request_fingerprint'] = $requestFingerprint($request);
$executor = ['actor_id' => 'executor-2', 'display_name' => 'Executor', 'authority_role' => 'platform_admin'];
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-17T01:00:00Z');

$ready = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $request, $request, $executor, null, $clock);
$assert(($ready['status'] ?? '') === 'ok', 'ready result returns ok status');
$assert(($ready['effect'] ?? '') === 'verify', 'service declares verify effect');
$assert(($ready['executor_readiness_version'] ?? '') === StudioDeletionExecutorReadinessService::VERSION, 'version is explicit');
$assert(($ready['executor_readiness'] ?? '') === 'ready', 'exact valid request yields ready state');
$assert(($ready['executor_eligible'] ?? '') === 'yes', 'named executor is eligible');
$assert(($ready['request_valid'] ?? '') === 'yes', 'request is valid');
$assert(($ready['executor_identity_valid'] ?? '') === 'yes', 'executor identity is valid');
$assert(($ready['single_use_available'] ?? '') === 'yes', 'single-use slot is available');
$assert(($ready['can_claim'] ?? '') === 'no', 'verifier cannot claim');
$assert(($ready['can_execute'] ?? '') === 'no', 'verifier cannot execute');
$assert(($ready['can_archive'] ?? '') === 'no' && ($ready['can_delete'] ?? '') === 'no', 'verifier cannot archive or delete');
$assert(($ready['grants_execution_authority'] ?? '') === 'no', 'verifier grants no authority');
$assert(($ready['requires_separate_claim_capability'] ?? '') === 'yes', 'separate claim capability is required');
$assert(($ready['request_evidence']['exact_packet_match'] ?? '') === 'yes', 'request is exact-packet matched');
$assert(($ready['request_evidence']['request_id'] ?? '') === 'deletion-execution-request-current', 'request identity is exposed');
$assert(($ready['actor_evidence']['actor_id'] ?? '') === 'executor-2', 'actor evidence is exposed');
$assert(($ready['blocking_reasons'] ?? []) === [], 'ready state has no blockers');
$assert(str_starts_with((string)($ready['executor_readiness_fingerprint'] ?? ''), 'deletion-executor-readiness:'), 'executor-readiness fingerprint is emitted');

$wrongActor = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $request, $request, ['actor_id' => 'other', 'authority_role' => 'platform_admin'], null, $clock);
$assert(($wrongActor['executor_readiness'] ?? '') === 'wrong_executor', 'different platform admin yields wrong-executor state');
$assert(($wrongActor['request_valid'] ?? '') === 'yes', 'request remains valid for wrong actor');
$assert(($wrongActor['executor_identity_valid'] ?? '') === 'no', 'wrong actor identity is invalid');
$assert(in_array('CURRENT_ACTOR_NOT_NAMED_EXECUTOR', $wrongActor['blocking_reasons'] ?? [], true), 'wrong actor blocker is explicit');

$wrongRole = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $request, $request, ['actor_id' => 'executor-2', 'authority_role' => 'app_admin'], null, $clock);
$assert(($wrongRole['executor_readiness'] ?? '') === 'wrong_executor', 'wrong authority role yields wrong-executor state');
$assert(in_array('CURRENT_ACTOR_NOT_PLATFORM_ADMIN', $wrongRole['blocking_reasons'] ?? [], true), 'wrong role blocker is explicit');

$expired = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $request, $request, $executor, null, static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-17T02:00:00Z'));
$assert(($expired['executor_readiness'] ?? '') === 'expired', 'expiry boundary yields expired state');
$assert(($expired['executor_eligible'] ?? '') === 'no', 'expired request is not eligible');
$assert(($expired['request_valid'] ?? '') === 'yes', 'expired request remains integrity-valid');
$assert(in_array('EXECUTION_REQUEST_EXPIRED', $expired['blocking_reasons'] ?? [], true), 'expiry blocker is explicit');

$useEvidence = [
    'use_id' => 'claim-001', 'recorded_at_utc' => '2026-07-17T00:30:00Z', 'immutable' => 'yes', 'append_only' => 'yes',
    'source' => ['request_id' => 'deletion-execution-request-current', 'request_fingerprint' => $request['request_fingerprint']],
    'storage' => ['storage_scope' => 'studio_provenance', 'relative_path' => 'storage/studio/deletion-execution-uses/use.json', 'immutable' => 'yes', 'append_only' => 'yes'],
];
$consumed = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $request, $request, $executor, $useEvidence, $clock);
$assert(($consumed['executor_readiness'] ?? '') === 'consumed', 'use evidence yields consumed state');
$assert(($consumed['single_use_available'] ?? '') === 'no', 'consumed request is unavailable');
$assert(($consumed['single_use_evidence']['use_id'] ?? '') === 'claim-001', 'use evidence is exposed');
$assert(in_array('EXECUTION_REQUEST_ALREADY_USED', $consumed['blocking_reasons'] ?? [], true), 'consumed blocker is explicit');

$badUse = $useEvidence;
$badUse['source']['request_id'] = 'other-request';
$blocked = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $request, $request, $executor, $badUse, $clock);
$assert(($blocked['executor_readiness'] ?? '') === 'blocked', 'mismatched use evidence fails closed');
$assert(in_array('USE_EVIDENCE_REQUEST_MISMATCH', $blocked['blocking_reasons'] ?? [], true), 'use mismatch blocker is explicit');

$staleRequest = $request;
$staleRequest['source']['dry_run_fingerprint'] = 'deletion-execution-dry-run:old';
$staleRequest['request_fingerprint'] = $requestFingerprint($staleRequest);
$stale = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, null, $staleRequest, $executor, null, $clock);
$assert(($stale['executor_readiness'] ?? '') === 'stale', 'older owner request yields stale state');
$assert(($stale['request_evidence']['exact_packet_match'] ?? '') === 'no', 'stale request is not exact match');
$assert(in_array('EXECUTION_REQUEST_STALE:DRY_RUN_FINGERPRINT', $stale['blocking_reasons'] ?? [], true), 'stale dry-run blocker is explicit');

$missing = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, null, null, $executor, null, $clock);
$assert(($missing['executor_readiness'] ?? '') === 'not_requested', 'missing request yields not-requested state');
$assert(in_array('EXECUTION_REQUEST_REQUIRED', $missing['blocking_reasons'] ?? [], true), 'missing request blocker is explicit');

$tampered = $request;
$tampered['reason'] = 'Tampered after recording.';
$blocked = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $tampered, $tampered, $executor, null, $clock);
$assert(($blocked['executor_readiness'] ?? '') === 'blocked', 'tampered request fails closed');
$assert(in_array('EXECUTION_REQUEST_FINGERPRINT_INVALID', $blocked['blocking_reasons'] ?? [], true), 'request fingerprint blocker is explicit');

$overAuthoritative = $request;
$overAuthoritative['execution_authorized'] = 'yes';
$overAuthoritative['request_fingerprint'] = $requestFingerprint($overAuthoritative);
$blocked = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $overAuthoritative, $overAuthoritative, $executor, null, $clock);
$assert(($blocked['executor_readiness'] ?? '') === 'blocked', 'over-authoritative request fails closed');
$assert(in_array('EXECUTION_REQUEST_AUTHORITY_INVALID:execution_authorized', $blocked['blocking_reasons'] ?? [], true), 'authority blocker is explicit');

$future = $request;
$future['requested_at_utc'] = '2026-07-17T01:30:00.000000Z';
$future['request_fingerprint'] = $requestFingerprint($future);
$blocked = StudioDeletionExecutorReadinessService::assess($changeSet, $readiness, $dryRun, $future, $future, $executor, null, $clock);
$assert(($blocked['executor_readiness'] ?? '') === 'blocked', 'future-dated request fails closed');
$assert(in_array('EXECUTION_REQUEST_NOT_YET_VALID', $blocked['blocking_reasons'] ?? [], true), 'future-date blocker is explicit');

$badPacket = $readiness;
$badPacket['readiness_fingerprint'] = 'readiness:changed';
$unknown = StudioDeletionExecutorReadinessService::assess($changeSet, $badPacket, $dryRun, $request, $request, $executor, null, $clock);
$assert(($unknown['executor_readiness'] ?? '') === 'unknown', 'changed current packet yields unknown state');
$assert(($unknown['status'] ?? '') === 'partial', 'invalid current packet returns partial status');

$root = sys_get_temp_dir() . '/studio-executor-use-' . bin2hex(random_bytes(4));
$dir = $root . '/storage/studio/deletion-execution-uses/deletion-execution-request-current';
mkdir($dir, 0777, true);
file_put_contents($dir . '/claim.json', json_encode($useEvidence, JSON_PRETTY_PRINT));
$storedUse = StudioDeletionExecutionRequestUseEvidenceStore::latest('deletion-execution-request-current', $root);
$assert(($storedUse['use_id'] ?? '') === 'claim-001', 'use store reads latest request evidence');
$assert(($storedUse['storage']['storage_scope'] ?? '') === 'studio_provenance', 'use store supplies provenance');
$assert(StudioDeletionExecutionRequestUseEvidenceStore::latest('missing', $root) === null, 'missing use evidence returns null');

$serviceSource = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionExecutorReadinessService.php');
$storeSource = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionExecutionRequestUseEvidenceStore.php');
$assert(is_string($serviceSource) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $serviceSource), 'readiness verifier contains no mutation implementation');
$assert(is_string($storeSource) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $storeSource), 'use-evidence store is read-only');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
