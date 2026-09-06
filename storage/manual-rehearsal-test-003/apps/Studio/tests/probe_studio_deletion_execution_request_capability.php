<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionExecutionRequestService;
use Apps\Studio\Services\StudioDeletionExecutionRequestStore;

require_once dirname(__DIR__) . '/Services/StudioDeletionExecutionRequestService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};
$codes = static fn(array $result): array => array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);

$changeSet = [
    'immutable' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'change_set_fingerprint' => 'deletion-change-set:current',
    'source_plan' => ['fingerprint' => 'deletion-plan:current'],
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_type' => 'owner', 'target_path' => 'apps/Manufacturing/modules/Products'],
];
$readiness = [
    'effect' => 'verify',
    'execution_readiness' => 'ready',
    'execution_eligible' => 'yes',
    'approval_valid' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'grants_execution_authority' => 'no',
    'readiness_fingerprint' => 'deletion-execution-readiness:current',
    'current_packet' => ['change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current'],
    'approval_evidence' => [
        'exact_packet_match' => 'yes',
        'decision' => 'approved',
        'record_id' => 'deletion-approval-current',
        'record_fingerprint' => 'deletion-approval-record:current',
    ],
];
$dryRun = [
    'effect' => 'simulate',
    'dry_run_state' => 'ready',
    'simulation_complete' => 'yes',
    'would_execute' => 'no',
    'would_apply' => 'no',
    'would_write' => 'no',
    'would_archive' => 'no',
    'would_delete' => 'no',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'grants_execution_authority' => 'no',
    'execution_eligible_at_simulation' => 'yes',
    'blocking_reasons' => [],
    'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
    'source' => [
        'change_set_fingerprint' => 'deletion-change-set:current',
        'plan_fingerprint' => 'deletion-plan:current',
        'readiness_fingerprint' => 'deletion-execution-readiness:current',
        'approval_record_id' => 'deletion-approval-current',
        'approval_record_fingerprint' => 'deletion-approval-record:current',
    ],
];
$input = [
    'change_set_fingerprint' => 'deletion-change-set:current',
    'plan_fingerprint' => 'deletion-plan:current',
    'readiness_fingerprint' => 'deletion-execution-readiness:current',
    'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
    'approval_record_id' => 'deletion-approval-current',
    'approval_record_fingerprint' => 'deletion-approval-record:current',
    'executor_actor_id' => '84',
    'executor_display_name' => 'Second Platform Admin',
    'executor_authority_role' => 'platform_admin',
    'expires_at_utc' => '2026-07-16T13:00:00Z',
    'reason' => 'Execute the exact approved packet during the scheduled maintenance window.',
];
$requester = ['user_id' => '42', 'display_name' => 'Requesting Platform Admin', 'authority_role' => 'platform_admin'];
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-16T12:00:00Z');
$writerCalls = 0;
$written = null;
$writer = static function (array $record, ?string $root) use (&$writerCalls, &$written): array {
    $writerCalls++;
    $written = $record;
    return ['storage_scope' => 'studio_provenance', 'relative_path' => 'storage/studio/deletion-execution-requests/probe.json', 'immutable' => 'yes', 'append_only' => 'yes'];
};
$idFactory = static fn(array $material): string => 'deletion-execution-request-probe-001';

$result = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $input, $requester, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'recorded', 'valid execution request is recorded');
$assert($writerCalls === 1, 'valid request invokes writer once');
$assert(($result['effect'] ?? '') === 'mutate', 'request declares provenance mutation effect');
$assert(($result['request_version'] ?? '') === StudioDeletionExecutionRequestService::VERSION, 'request version is explicit');
$assert(($result['request_id'] ?? '') === 'deletion-execution-request-probe-001', 'request id is preserved');
$assert(($result['request_state'] ?? '') === 'requested', 'request state is explicit');
$assert(($result['immutable'] ?? '') === 'yes', 'request is immutable');
$assert(($result['append_only'] ?? '') === 'yes', 'request is append-only');
$assert(($result['can_execute'] ?? '') === 'no', 'request cannot execute');
$assert(($result['can_apply'] ?? '') === 'no', 'request cannot apply');
$assert(($result['can_archive'] ?? '') === 'no', 'request cannot archive');
$assert(($result['can_delete'] ?? '') === 'no', 'request cannot delete');
$assert(($result['execution_authorized'] ?? '') === 'no', 'request does not authorize execution');
$assert(($result['grants_execution_authority'] ?? '') === 'no', 'request grants no execution authority');
$assert(($result['requires_separate_execution_capability'] ?? '') === 'yes', 'separate execution capability remains required');
$assert(($result['source']['change_set_fingerprint'] ?? '') === 'deletion-change-set:current', 'change-set binding is preserved');
$assert(($result['source']['readiness_fingerprint'] ?? '') === 'deletion-execution-readiness:current', 'readiness binding is preserved');
$assert(($result['source']['dry_run_fingerprint'] ?? '') === 'deletion-execution-dry-run:current', 'dry-run binding is preserved');
$assert(($result['source']['approval_record_id'] ?? '') === 'deletion-approval-current', 'approval record binding is preserved');
$assert(($result['requester']['actor_id'] ?? '') === '42', 'requester identity is preserved');
$assert(($result['executor_policy']['executor_actor_id'] ?? '') === '84', 'executor identity is preserved');
$assert(($result['executor_policy']['required_authority_role'] ?? '') === 'platform_admin', 'executor authority policy is explicit');
$assert(($result['executor_policy']['executor_must_differ_from_requester'] ?? '') === 'yes', 'separation of duties is explicit');
$assert(($result['executor_policy']['single_use'] ?? '') === 'yes', 'single-use policy is explicit');
$assert(($result['execution_contract']['requires_snapshot_before_execution'] ?? '') === 'yes', 'snapshot prerequisite is explicit');
$assert(($result['requested_at_utc'] ?? '') === '2026-07-16T12:00:00.000000Z', 'request timestamp is deterministic');
$assert(($result['expires_at_utc'] ?? '') === '2026-07-16T13:00:00.000000Z', 'expiry timestamp is deterministic');
$assert(str_starts_with((string)($result['request_fingerprint'] ?? ''), 'deletion-execution-request:'), 'request fingerprint is emitted');
$assert(($result['storage']['storage_scope'] ?? '') === 'studio_provenance', 'request storage is Studio provenance');
$assert(is_array($written) && !array_key_exists('storage', $written), 'stored record excludes derived storage metadata');

$before = $writerCalls;
$stale = $input;
$stale['dry_run_fingerprint'] = 'deletion-execution-dry-run:stale';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $stale, $requester, null, $writer, $clock, $idFactory);
$assert(($error['status'] ?? '') === 'error', 'stale submitted dry run fails');
$assert(in_array('DELETION_EXECUTION_REQUEST_BINDING_STALE', $codes($error), true), 'stale binding diagnostic is explicit');
$assert($writerCalls === $before, 'stale request does not invoke writer');

$blockedDryRun = $dryRun;
$blockedDryRun['dry_run_state'] = 'blocked';
$blockedDryRun['execution_eligible_at_simulation'] = 'no';
$blockedDryRun['blocking_reasons'] = ['TARGET_MISSING'];
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $blockedDryRun, $input, $requester, null, $writer, $clock, $idFactory);
$assert(($error['status'] ?? '') === 'error', 'blocked dry run cannot be requested');
$assert(in_array('DELETION_EXECUTION_REQUEST_PREREQUISITE_FAILED', $codes($error), true), 'blocked prerequisite diagnostic is explicit');

$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $input, ['user_id' => '7', 'authority_role' => 'app_admin'], null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_REQUESTER_UNAUTHORIZED', $codes($error), true), 'non-platform requester is rejected');

$sameExecutor = $input;
$sameExecutor['executor_actor_id'] = '42';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $sameExecutor, $requester, null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_EXECUTOR_NOT_DISTINCT', $codes($error), true), 'requester cannot name self as executor');

$badRole = $input;
$badRole['executor_authority_role'] = 'app_admin';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $badRole, $requester, null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_EXECUTOR_ROLE_INVALID', $codes($error), true), 'non-platform executor role is rejected');

$tooSoon = $input;
$tooSoon['expires_at_utc'] = '2026-07-16T12:04:59Z';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $tooSoon, $requester, null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_EXPIRY_TOO_SOON', $codes($error), true), 'expiry under five minutes is rejected');

$tooLate = $input;
$tooLate['expires_at_utc'] = '2026-07-17T12:00:01Z';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $tooLate, $requester, null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_EXPIRY_TOO_LATE', $codes($error), true), 'expiry beyond twenty-four hours is rejected');

$nonUtc = $input;
$nonUtc['expires_at_utc'] = '2026-07-16T18:45:00+05:45';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $nonUtc, $requester, null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_EXPIRY_INVALID', $codes($error), true), 'non-UTC expiry is rejected');

$missingReason = $input;
$missingReason['reason'] = '';
$error = StudioDeletionExecutionRequestService::record($changeSet, $readiness, $dryRun, $missingReason, $requester, null, $writer, $clock, $idFactory);
$assert(in_array('DELETION_EXECUTION_REQUEST_REASON_REQUIRED', $codes($error), true), 'reason is required');

$root = sys_get_temp_dir() . '/studio-execution-request-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);
$storeRecord = $written;
$storeRecord['request_id'] = 'deletion-execution-request-store-001';
$storeRecord['requested_at_utc'] = '2026-07-16T12:00:00.000000Z';
$stored = StudioDeletionExecutionRequestStore::append($storeRecord, $root);
$assert(($stored['append_only'] ?? '') === 'yes', 'store declares append-only provenance');
$assert(str_starts_with((string)($stored['relative_path'] ?? ''), 'storage/studio/deletion-execution-requests/'), 'store confines requests to Studio provenance path');
$latest = StudioDeletionExecutionRequestStore::latest('Manufacturing/Products', 'deletion-change-set:current', 'deletion-execution-dry-run:current', $root);
$assert(($latest['request_id'] ?? '') === 'deletion-execution-request-store-001', 'store reads latest exact request');
$assert(($latest['storage']['storage_scope'] ?? '') === 'studio_provenance', 'latest request includes storage provenance');
$latestOwner = StudioDeletionExecutionRequestStore::latestForOwner('Manufacturing/Products', $root);
$assert(($latestOwner['request_id'] ?? '') === 'deletion-execution-request-store-001', 'store reads latest owner request');
$duplicateFailed = false;
try { StudioDeletionExecutionRequestStore::append($storeRecord, $root); } catch (RuntimeException $exception) { $duplicateFailed = true; }
$assert($duplicateFailed, 'append-only store rejects duplicate request id');
$assert(!is_file($root . '/apps/Manufacturing/modules/Products'), 'request storage does not write owner target path');

$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionExecutionRequestService.php');
$assert(is_string($source) && !preg_match('/delete_target|archive_target|copy\s*\(|rename\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'request capability contains no execution or database authority');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
