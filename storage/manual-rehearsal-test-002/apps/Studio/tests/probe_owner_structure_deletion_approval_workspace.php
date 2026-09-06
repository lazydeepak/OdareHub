<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionApprovalWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionApprovalWorkspaceService.php';

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
    'change_set_fingerprint' => 'deletion-change-set:workspace',
    'source_plan' => ['fingerprint' => 'deletion-plan:workspace'],
    'target' => ['owner_key' => 'Manufacturing/Products'],
    'approval_contract' => [
        'approval_readiness' => 'ready',
        'required_confirmations' => ['target_identity_confirmed', 'snapshot_created'],
    ],
];
$latest = [
    'status' => 'recorded',
    'record_id' => 'approval-latest',
    'decision' => 'approved',
    'recorded_at_utc' => '2026-07-14T12:00:00.000000Z',
];
$readerCalls = 0;
$reader = static function (array $packet, ?string $root) use (&$readerCalls, $latest): array {
    $readerCalls++;
    if (($packet['change_set_fingerprint'] ?? '') !== 'deletion-change-set:workspace') {
        throw new RuntimeException('wrong packet');
    }
    return $latest;
};
$flash = ['status' => 'recorded', 'record_id' => 'approval-flash'];

$idle = OwnerStructureDeletionApprovalWorkspaceService::build($owners, 'Manufacturing/Products', false, $changeSet, $flash, null, $reader);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace remains idle');
$assert($readerCalls === 0, 'idle workspace does not read approval history');
$assert(($idle['flash']['record_id'] ?? '') === 'approval-flash', 'idle workspace preserves flash result');

$ready = OwnerStructureDeletionApprovalWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $flash, null, $reader);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($readerCalls === 1, 'requested workspace reads approval history once');
$assert(($ready['change_set_fingerprint'] ?? '') === 'deletion-change-set:workspace', 'workspace exposes change-set fingerprint');
$assert(($ready['plan_fingerprint'] ?? '') === 'deletion-plan:workspace', 'workspace exposes plan fingerprint');
$assert(($ready['approval_readiness'] ?? '') === 'ready', 'workspace exposes approval readiness');
$assert(($ready['can_approve'] ?? '') === 'yes', 'ready packet permits approval recording');
$assert(($ready['can_reject'] ?? '') === 'yes', 'valid packet permits rejection recording');
$assert(($ready['required_confirmations'] ?? []) === ['target_identity_confirmed', 'snapshot_created'], 'workspace exposes exact confirmation contract');
$assert(($ready['latest_record']['record_id'] ?? '') === 'approval-latest', 'workspace exposes latest packet-bound record');
$assert(($ready['flash']['record_id'] ?? '') === 'approval-flash', 'workspace exposes POST result flash');

$blocked = $changeSet;
$blocked['approval_contract']['approval_readiness'] = 'blocked';
$blockedWorkspace = OwnerStructureDeletionApprovalWorkspaceService::build($owners, 'Manufacturing/Products', true, $blocked, null, null, static fn(): ?array => null);
$assert(($blockedWorkspace['status'] ?? '') === 'ready', 'blocked packet still renders decision workspace');
$assert(($blockedWorkspace['can_approve'] ?? '') === 'no', 'blocked packet disables approval');
$assert(($blockedWorkspace['can_reject'] ?? '') === 'yes', 'blocked packet still permits rejection');

$missingOwner = OwnerStructureDeletionApprovalWorkspaceService::build($owners, 'Missing/Owner', true, $changeSet, null, null, $reader);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_APPROVAL_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingPacket = OwnerStructureDeletionApprovalWorkspaceService::build($owners, 'Manufacturing/Products', true, null, null, null, $reader);
$assert(($missingPacket['status'] ?? '') === 'error', 'missing change set fails safely');
$assert(($missingPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_APPROVAL_CHANGE_SET_REQUIRED', 'missing packet diagnostic is explicit');

$mismatch = $changeSet;
$mismatch['target']['owner_key'] = 'Other/Owner';
$mismatchWorkspace = OwnerStructureDeletionApprovalWorkspaceService::build($owners, 'Manufacturing/Products', true, $mismatch, null, null, $reader);
$assert(($mismatchWorkspace['status'] ?? '') === 'error', 'owner mismatch fails safely');
$assert(($mismatchWorkspace['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_APPROVAL_CHANGE_SET_INVALID', 'owner mismatch diagnostic is explicit');

$historyFailure = OwnerStructureDeletionApprovalWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $changeSet,
    null,
    null,
    static function (): ?array { throw new RuntimeException('history failure'); }
);
$assert(($historyFailure['status'] ?? '') === 'error', 'history exception fails safely');
$assert(($historyFailure['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_APPROVAL_HISTORY_FAILED', 'history failure diagnostic is explicit');
$assert(($historyFailure['diagnostics'][0]['message'] ?? '') === 'history failure', 'history failure message is preserved');

$postludes = [
    'preview.postlude.deletion-plan.php',
    'preview.postlude.zz-deletion-change-set.php',
    'preview.postlude.zzz-deletion-approval.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[2] === 'preview.postlude.zzz-deletion-approval.php', 'approval postlude loads after current change set');

$source = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionApprovalWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'workspace presenter contains no mutation authority');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
