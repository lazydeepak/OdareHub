<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionExecutionClaimService;
use Apps\Studio\Services\StudioDeletionExecutionClaimStore;
use Apps\Studio\Services\StudioDeletionExecutionRequestUseEvidenceStore;

require_once dirname(__DIR__) . '/Services/StudioDeletionExecutionClaimService.php';
require_once dirname(__DIR__) . '/Services/StudioDeletionExecutionRequestUseEvidenceStore.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};

$readiness = [
    'status' => 'ok',
    'effect' => 'verify',
    'executor_readiness_version' => 'studio.deletion-executor-readiness.v1',
    'executor_readiness' => 'ready',
    'executor_eligible' => 'yes',
    'request_valid' => 'yes',
    'executor_identity_valid' => 'yes',
    'single_use_available' => 'yes',
    'can_claim' => 'no',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'can_archive' => 'no',
    'can_delete' => 'no',
    'grants_execution_authority' => 'no',
    'requires_separate_claim_capability' => 'yes',
    'requires_separate_execution_capability' => 'yes',
    'current_packet' => [
        'change_set_fingerprint' => 'deletion-change-set:current',
        'plan_fingerprint' => 'deletion-plan:current',
        'readiness_fingerprint' => 'deletion-execution-readiness:current',
        'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
        'approval_record_id' => 'approval-current',
        'approval_record_fingerprint' => 'approval-fingerprint-current',
        'target' => [
            'owner_key' => 'Manufacturing/Products',
            'target_type' => 'owner',
            'target_path' => 'apps/Manufacturing/modules/Products',
        ],
    ],
    'request_evidence' => [
        'present' => 'yes',
        'exact_packet_match' => 'yes',
        'request_id' => 'request-current',
        'request_fingerprint' => 'deletion-execution-request:current',
        'request_state' => 'requested',
        'requested_at_utc' => '2026-07-17T01:00:00.000000Z',
        'expires_at_utc' => '2026-07-17T02:00:00.000000Z',
        'executor_policy' => [
            'executor_actor_id' => 'executor-42',
            'executor_display_name' => 'Executor 42',
            'required_authority_role' => 'platform_admin',
            'single_use' => 'yes',
        ],
    ],
    'actor_evidence' => [
        'actor_id' => 'executor-42',
        'display_name' => 'Executor 42',
        'authority_role' => 'platform_admin',
    ],
    'single_use_evidence' => [],
    'blocking_reasons' => [],
    'executor_readiness_fingerprint' => 'deletion-executor-readiness:current',
];
$input = [
    'request_id' => 'request-current',
    'request_fingerprint' => 'deletion-execution-request:current',
    'executor_readiness_fingerprint' => 'deletion-executor-readiness:current',
    'claim_confirmed' => 'yes',
    'reason' => 'I am taking responsibility for the governed execution sequence.',
];
$actor = ['user_id' => 'executor-42', 'display_name' => 'Executor 42', 'authority_role' => 'platform_admin'];
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-17T01:30:00Z');

$captured = null;
$result = StudioDeletionExecutionClaimService::claim(
    $readiness,
    $input,
    $actor,
    null,
    static function (array $record, ?string $root) use (&$captured): array {
        $captured = $record;
        return [
            'storage_scope' => 'studio_provenance',
            'relative_path' => 'storage/studio/deletion-execution-uses/request-current/claim.json',
            'immutable' => 'yes',
            'append_only' => 'yes',
            'atomic_single_use' => 'yes',
        ];
    },
    $clock
);
$assert(($result['status'] ?? '') === 'recorded', 'ready named executor records claim');
$assert(($result['effect'] ?? '') === 'mutate', 'claim declares provenance mutation effect');
$assert(($result['claim_version'] ?? '') === StudioDeletionExecutionClaimService::VERSION, 'claim version is explicit');
$assert(($result['claim_state'] ?? '') === 'claimed', 'claim state is claimed');
$assert(($result['use_type'] ?? '') === 'claim', 'claim is single-use evidence type');
$assert(($result['immutable'] ?? '') === 'yes', 'claim is immutable');
$assert(($result['append_only'] ?? '') === 'yes', 'claim is append only');
$assert(($result['atomic_single_use'] ?? '') === 'yes', 'claim declares atomic single use');
$assert(($result['can_execute'] ?? '') === 'no', 'claim cannot execute');
$assert(($result['can_apply'] ?? '') === 'no', 'claim cannot apply');
$assert(($result['can_archive'] ?? '') === 'no', 'claim cannot archive');
$assert(($result['can_delete'] ?? '') === 'no', 'claim cannot delete');
$assert(($result['execution_authorized'] ?? '') === 'no', 'claim does not authorize execution');
$assert(($result['grants_execution_authority'] ?? '') === 'no', 'claim grants no authority');
$assert(($result['requires_separate_execution_capability'] ?? '') === 'yes', 'separate execution capability remains required');
$assert(($result['source']['request_id'] ?? '') === 'request-current', 'claim binds request id');
$assert(($result['source']['request_fingerprint'] ?? '') === 'deletion-execution-request:current', 'claim binds request fingerprint');
$assert(($result['source']['executor_readiness_fingerprint'] ?? '') === 'deletion-executor-readiness:current', 'claim binds executor readiness');
$assert(($result['executor']['actor_id'] ?? '') === 'executor-42', 'claim preserves executor identity');
$assert(($result['executor']['authority_role'] ?? '') === 'platform_admin', 'claim preserves executor authority');
$assert(($result['single_use_contract']['consumes_request'] ?? '') === 'yes', 'claim consumes request');
$assert(($result['single_use_contract']['claim_is_execution_authority'] ?? '') === 'no', 'single-use contract denies execution authority');
$assert(str_starts_with((string)($result['claim_id'] ?? ''), 'deletion-execution-claim-'), 'claim id is deterministic');
$assert(str_starts_with((string)($result['claim_fingerprint'] ?? ''), 'deletion-execution-claim:'), 'claim fingerprint is emitted');
$assert(($result['storage']['atomic_single_use'] ?? '') === 'yes', 'storage evidence declares atomic single use');
$assert(is_array($captured) && ($captured['storage'] ?? null) === null, 'writer receives immutable record before storage evidence');
$assert(($captured['claimed_at_utc'] ?? '') === '2026-07-17T01:30:00.000000Z', 'claim time uses injected UTC clock');

$stale = $input;
$stale['request_fingerprint'] = 'stale';
$error = StudioDeletionExecutionClaimService::claim($readiness, $stale, $actor, null, null, $clock);
$assert(($error['status'] ?? '') === 'error', 'stale submitted binding fails');
$assert(in_array('DELETION_EXECUTION_CLAIM_BINDING_STALE', array_column($error['diagnostics'] ?? [], 'code'), true), 'stale binding diagnostic is explicit');

$wrongActor = ['user_id' => 'other', 'display_name' => 'Other', 'authority_role' => 'platform_admin'];
$error = StudioDeletionExecutionClaimService::claim($readiness, $input, $wrongActor, null, null, $clock);
$assert(($error['status'] ?? '') === 'error', 'wrong named executor fails');
$assert(in_array('DELETION_EXECUTION_CLAIM_ACTOR_MISMATCH', array_column($error['diagnostics'] ?? [], 'code'), true), 'wrong executor diagnostic is explicit');

$nonAdmin = ['user_id' => 'executor-42', 'display_name' => 'Executor 42', 'authority_role' => 'app_admin'];
$error = StudioDeletionExecutionClaimService::claim($readiness, $input, $nonAdmin, null, null, $clock);
$assert(($error['status'] ?? '') === 'error', 'non-platform-admin executor fails');
$assert(in_array('DELETION_EXECUTION_CLAIM_ACTOR_UNAUTHORIZED', array_column($error['diagnostics'] ?? [], 'code'), true), 'authority diagnostic is explicit');

$unconfirmed = $input;
$unconfirmed['claim_confirmed'] = 'no';
$error = StudioDeletionExecutionClaimService::claim($readiness, $unconfirmed, $actor, null, null, $clock);
$assert(in_array('DELETION_EXECUTION_CLAIM_CONFIRMATION_REQUIRED', array_column($error['diagnostics'] ?? [], 'code'), true), 'explicit confirmation is required');

$noReason = $input;
$noReason['reason'] = '';
$error = StudioDeletionExecutionClaimService::claim($readiness, $noReason, $actor, null, null, $clock);
$assert(in_array('DELETION_EXECUTION_CLAIM_REASON_REQUIRED', array_column($error['diagnostics'] ?? [], 'code'), true), 'claim reason is required');

$expiredClock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-17T02:00:00Z');
$error = StudioDeletionExecutionClaimService::claim($readiness, $input, $actor, null, null, $expiredClock);
$assert(in_array('DELETION_EXECUTION_CLAIM_REQUEST_EXPIRED', array_column($error['diagnostics'] ?? [], 'code'), true), 'request expiry is revalidated during claim');

$consumedReadiness = $readiness;
$consumedReadiness['single_use_available'] = 'no';
$consumedReadiness['single_use_evidence'] = ['use_id' => 'existing'];
$error = StudioDeletionExecutionClaimService::claim($consumedReadiness, $input, $actor, null, null, $clock);
$assert(($error['status'] ?? '') === 'error', 'consumed readiness fails');
$assert(in_array('DELETION_EXECUTION_CLAIM_READINESS_INVALID', array_column($error['diagnostics'] ?? [], 'code'), true), 'consumed readiness diagnostic is explicit');

$error = StudioDeletionExecutionClaimService::claim(
    $readiness,
    $input,
    $actor,
    null,
    static function (): array { throw new RuntimeException('Execution request is already claimed or claim storage cannot be created.'); },
    $clock
);
$assert(($error['status'] ?? '') === 'error', 'atomic duplicate failure returns error');
$assert(($error['diagnostics'][0]['code'] ?? '') === 'DELETION_EXECUTION_CLAIM_ALREADY_EXISTS', 'duplicate claim diagnostic is explicit');

$root = sys_get_temp_dir() . '/studio-deletion-claim-' . bin2hex(random_bytes(4));
mkdir($root . '/apps/Manufacturing/modules/Products', 0777, true);
$storeRecord = $captured;
$stored = StudioDeletionExecutionClaimStore::claim($storeRecord, $root);
$assert(($stored['atomic_single_use'] ?? '') === 'yes', 'claim store declares atomic single use');
$assert(($stored['relative_path'] ?? '') === 'storage/studio/deletion-execution-uses/request-current/claim.json', 'claim store uses canonical single-use evidence path');
$latest = StudioDeletionExecutionRequestUseEvidenceStore::latest('request-current', $root);
$assert(($latest['claim_id'] ?? '') === ($storeRecord['claim_id'] ?? ''), 'existing use-evidence reader sees claim immediately');
$assert(($latest['source']['request_fingerprint'] ?? '') === 'deletion-execution-request:current', 'stored claim remains bound to request');
$duplicateFailed = false;
try { StudioDeletionExecutionClaimStore::claim($storeRecord, $root); } catch (RuntimeException $exception) { $duplicateFailed = true; }
$assert($duplicateFailed, 'claim store atomically rejects second claim');
$assert(is_dir($root . '/apps/Manufacturing/modules/Products'), 'claim does not alter owner target');

$serviceSource = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionExecutionClaimService.php');
$assert(is_string($serviceSource) && !preg_match('/delete_target|archive_target|copy\s*\(|rename\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $serviceSource), 'claim capability contains no execution or database authority');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
