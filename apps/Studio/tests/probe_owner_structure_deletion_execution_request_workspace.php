<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionRequestService;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionRequestWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionRequestWorkspaceService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};

$owners = [[
    'owner_key' => 'Manufacturing/Products',
    'owner_type' => 'module',
    'root_path' => 'apps/Manufacturing/modules/Products',
]];
$changeSet = [
    'change_set_fingerprint' => 'deletion-change-set:current',
    'source_plan' => ['fingerprint' => 'deletion-plan:current'],
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_type' => 'owner', 'target_path' => 'apps/Manufacturing/modules/Products'],
];
$readiness = [
    'execution_readiness' => 'ready',
    'execution_eligible' => 'yes',
    'readiness_fingerprint' => 'deletion-execution-readiness:current',
    'approval_evidence' => ['record_id' => 'approval-001', 'record_fingerprint' => 'approval-record:001'],
];
$dryRun = [
    'dry_run_state' => 'ready',
    'simulation_complete' => 'yes',
    'execution_eligible_at_simulation' => 'yes',
    'blocking_reasons' => [],
    'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
];
$latestRequest = [
    'request_id' => 'execution-request-001',
    'request_state' => 'requested',
    'expires_at_utc' => '2026-07-16T13:00:00.000000Z',
];
$latestCalls = 0;
$latestReader = static function (array $packet, array $simulation, ?string $root) use (&$latestCalls, $latestRequest): array {
    $latestCalls++;
    if (($packet['change_set_fingerprint'] ?? '') !== 'deletion-change-set:current') { throw new RuntimeException('wrong packet'); }
    if (($simulation['dry_run_fingerprint'] ?? '') !== 'deletion-execution-dry-run:current') { throw new RuntimeException('wrong dry run'); }
    return $latestRequest;
};

$idle = OwnerStructureDeletionExecutionRequestWorkspaceService::build($owners, 'Manufacturing/Products', false, $changeSet, $readiness, $dryRun, null, null, $latestReader);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not load request data');
$assert(($idle['can_request'] ?? '') === 'no', 'idle workspace cannot request');
$assert($latestCalls === 0, 'idle workspace does not read history');

$ready = OwnerStructureDeletionExecutionRequestWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $readiness, $dryRun, ['status' => 'recorded'], null, $latestReader);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($latestCalls === 1, 'requested workspace reads exact history once');
$assert(($ready['can_request'] ?? '') === 'yes', 'ready packet can be requested');
$assert(($ready['source']['change_set_fingerprint'] ?? '') === 'deletion-change-set:current', 'change-set fingerprint is exposed');
$assert(($ready['source']['plan_fingerprint'] ?? '') === 'deletion-plan:current', 'plan fingerprint is exposed');
$assert(($ready['source']['readiness_fingerprint'] ?? '') === 'deletion-execution-readiness:current', 'readiness fingerprint is exposed');
$assert(($ready['source']['dry_run_fingerprint'] ?? '') === 'deletion-execution-dry-run:current', 'dry-run fingerprint is exposed');
$assert(($ready['source']['approval_record_id'] ?? '') === 'approval-001', 'approval id is exposed');
$assert(($ready['target']['owner_key'] ?? '') === 'Manufacturing/Products', 'target is exposed');
$assert(($ready['execution_readiness'] ?? '') === 'ready', 'readiness state is exposed');
$assert(($ready['dry_run_state'] ?? '') === 'ready', 'dry-run state is exposed');
$assert(($ready['latest_request']['request_id'] ?? '') === 'execution-request-001', 'latest exact request is exposed');
$assert(($ready['flash']['status'] ?? '') === 'recorded', 'flash result is preserved');
$assert(($ready['diagnostics'] ?? []) === [], 'ready workspace has no diagnostics');

$blockedDryRun = $dryRun;
$blockedDryRun['dry_run_state'] = 'blocked';
$blockedDryRun['execution_eligible_at_simulation'] = 'no';
$blockedDryRun['blocking_reasons'] = ['TARGET_MISSING'];
$blocked = OwnerStructureDeletionExecutionRequestWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $readiness, $blockedDryRun, null, null, static fn(): ?array => null);
$assert(($blocked['status'] ?? '') === 'ready', 'blocked packet still renders workspace');
$assert(($blocked['can_request'] ?? '') === 'no', 'blocked packet cannot be requested');
$assert(($blocked['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_REQUEST_NOT_READY', 'blocked packet diagnostic is explicit');

$missingOwner = OwnerStructureDeletionExecutionRequestWorkspaceService::build($owners, 'Missing/Owner', true, $changeSet, $readiness, $dryRun);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_REQUEST_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingPrerequisites = OwnerStructureDeletionExecutionRequestWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, null, null);
$assert(($missingPrerequisites['status'] ?? '') === 'error', 'missing readiness and dry run fail safely');
$assert(($missingPrerequisites['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_REQUEST_PREREQUISITES_REQUIRED', 'missing prerequisite diagnostic is explicit');

$wrongPacket = $changeSet;
$wrongPacket['target']['owner_key'] = 'Manufacturing/Other';
$wrong = OwnerStructureDeletionExecutionRequestWorkspaceService::build($owners, 'Manufacturing/Products', true, $wrongPacket, $readiness, $dryRun);
$assert(($wrong['status'] ?? '') === 'error', 'wrong target packet fails safely');
$assert(($wrong['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_REQUEST_PACKET_INVALID', 'wrong target diagnostic is explicit');

$thrown = OwnerStructureDeletionExecutionRequestWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $changeSet,
    $readiness,
    $dryRun,
    null,
    null,
    static function (): array { throw new RuntimeException('request history failure'); }
);
$assert(($thrown['status'] ?? '') === 'error', 'history exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_REQUEST_HISTORY_FAILED', 'history failure diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'request history failure', 'history exception message is preserved');

$mismatch = OwnerStructureDeletionExecutionRequestService::record(
    ['owner_key' => 'Manufacturing/Other'],
    $changeSet,
    $readiness,
    $dryRun,
    [],
    ['authority_role' => 'platform_admin']
);
$assert(($mismatch['status'] ?? '') === 'error', 'adapter rejects owner mismatch');
$assert(($mismatch['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_REQUEST_OWNER_MISMATCH', 'adapter mismatch diagnostic is explicit');
$assert(($mismatch['can_execute'] ?? '') === 'no', 'adapter mismatch grants no execution');

$postludes = [
    'preview.postlude.zzzz-deletion-execution-readiness.php',
    'preview.postlude.zzzzz-deletion-execution-dry-run.php',
    'preview.postlude.zzzzzz-deletion-execution-request.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[2] === 'preview.postlude.zzzzzz-deletion-execution-request.php', 'request postlude loads after dry run');

$workspaceSource = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionRequestWorkspaceService.php');
$assert(is_string($workspaceSource) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $workspaceSource), 'workspace presenter contains no mutation implementation');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
