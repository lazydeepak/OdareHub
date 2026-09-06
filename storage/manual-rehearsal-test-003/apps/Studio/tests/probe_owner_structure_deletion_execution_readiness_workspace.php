<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionApprovalRecordStore;
use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionReadinessWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionReadinessWorkspaceService.php';

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
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_path' => 'apps/Manufacturing/modules/Products'],
];
$exactRecord = [
    'record_id' => 'approval-current',
    'decision' => 'approved',
    'source' => ['change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current'],
];
$ownerRecord = $exactRecord;
$assessment = [
    'status' => 'ok',
    'execution_readiness' => 'ready',
    'execution_eligible' => 'yes',
    'approval_valid' => 'yes',
    'current_packet' => ['change_set_fingerprint' => 'deletion-change-set:current'],
    'approval_evidence' => ['decision' => 'approved', 'record_id' => 'approval-current'],
    'blocking_reasons' => [],
    'readiness_fingerprint' => 'deletion-execution-readiness:workspace',
    'diagnostics' => [],
];

$exactCalls = 0;
$ownerCalls = 0;
$assessorCalls = 0;
$exactReader = static function (string $ownerKey, string $fingerprint, ?string $root) use (&$exactCalls, $exactRecord): array {
    $exactCalls++;
    if ($ownerKey !== 'Manufacturing/Products' || $fingerprint !== 'deletion-change-set:current') { throw new RuntimeException('wrong exact lookup'); }
    return $exactRecord;
};
$ownerReader = static function (string $ownerKey, ?string $root) use (&$ownerCalls, $ownerRecord): array {
    $ownerCalls++;
    if ($ownerKey !== 'Manufacturing/Products') { throw new RuntimeException('wrong owner lookup'); }
    return $ownerRecord;
};
$assessor = static function (array $owner, array $packet, ?array $exact, ?array $latest) use (&$assessorCalls, $assessment): array {
    $assessorCalls++;
    if (($owner['owner_key'] ?? '') !== 'Manufacturing/Products') { throw new RuntimeException('wrong owner'); }
    if (($packet['change_set_fingerprint'] ?? '') !== 'deletion-change-set:current') { throw new RuntimeException('wrong packet'); }
    if (($exact['record_id'] ?? '') !== 'approval-current' || ($latest['record_id'] ?? '') !== 'approval-current') { throw new RuntimeException('wrong records'); }
    return $assessment;
};

$idle = OwnerStructureDeletionExecutionReadinessWorkspaceService::build($owners, 'Manufacturing/Products', false, $changeSet, null, $exactReader, $ownerReader, $assessor);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not assess');
$assert(($idle['assessment'] ?? null) === null, 'idle workspace has no assessment');
$assert($exactCalls === 0 && $ownerCalls === 0 && $assessorCalls === 0, 'idle workspace invokes no readers or assessor');

$ready = OwnerStructureDeletionExecutionReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, null, $exactReader, $ownerReader, $assessor);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($exactCalls === 1, 'exact approval reader invoked once');
$assert($ownerCalls === 1, 'owner approval reader invoked once');
$assert($assessorCalls === 1, 'canonical assessor invoked once');
$assert(($ready['execution_readiness'] ?? '') === 'ready', 'execution readiness is exposed');
$assert(($ready['execution_eligible'] ?? '') === 'yes', 'execution eligibility is exposed');
$assert(($ready['approval_valid'] ?? '') === 'yes', 'approval validity is exposed');
$assert(($ready['current_packet']['change_set_fingerprint'] ?? '') === 'deletion-change-set:current', 'current packet is exposed');
$assert(($ready['approval_evidence']['record_id'] ?? '') === 'approval-current', 'approval evidence is exposed');
$assert(($ready['readiness_fingerprint'] ?? '') === 'deletion-execution-readiness:workspace', 'readiness fingerprint is exposed');
$assert(($ready['blocking_reasons'] ?? []) === [], 'ready workspace exposes no blockers');

$missingOwner = OwnerStructureDeletionExecutionReadinessWorkspaceService::build($owners, 'Missing/Owner', true, $changeSet);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_READINESS_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingPacket = OwnerStructureDeletionExecutionReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, null);
$assert(($missingPacket['status'] ?? '') === 'error', 'missing change set fails safely');
$assert(($missingPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_READINESS_CHANGE_SET_REQUIRED', 'missing change-set diagnostic is explicit');

$wrongPacket = $changeSet;
$wrongPacket['target']['owner_key'] = 'Manufacturing/Other';
$wrong = OwnerStructureDeletionExecutionReadinessWorkspaceService::build($owners, 'Manufacturing/Products', true, $wrongPacket);
$assert(($wrong['status'] ?? '') === 'error', 'wrong packet owner fails safely');
$assert(($wrong['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_READINESS_CHANGE_SET_INVALID', 'wrong packet diagnostic is explicit');

$invalid = OwnerStructureDeletionExecutionReadinessWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $changeSet,
    null,
    static fn(): ?array => null,
    static fn(): ?array => null,
    static fn(): string => 'invalid'
);
$assert(($invalid['status'] ?? '') === 'error', 'invalid assessor result fails safely');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_READINESS_RESULT_INVALID', 'invalid result diagnostic is explicit');

$thrown = OwnerStructureDeletionExecutionReadinessWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $changeSet,
    null,
    static function (): array { throw new RuntimeException('readiness probe failure'); },
    static fn(): ?array => null,
    $assessor
);
$assert(($thrown['status'] ?? '') === 'error', 'reader exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_READINESS_FAILED', 'reader exception diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'readiness probe failure', 'reader exception message is preserved');

$root = sys_get_temp_dir() . '/studio-readiness-workspace-' . bin2hex(random_bytes(4));
$base = $root . '/storage/studio/deletion-approvals/Manufacturing__Products';
mkdir($base . '/deletion-change-set__old', 0777, true);
mkdir($base . '/deletion-change-set__current', 0777, true);
file_put_contents($base . '/deletion-change-set__old/old.json', json_encode(['record_id' => 'old', 'recorded_at_utc' => '2026-07-14T01:00:00Z', 'decision' => 'approved'], JSON_PRETTY_PRINT));
file_put_contents($base . '/deletion-change-set__current/current.json', json_encode(['record_id' => 'current', 'recorded_at_utc' => '2026-07-15T01:00:00Z', 'decision' => 'rejected'], JSON_PRETTY_PRINT));
$latestExact = StudioDeletionApprovalRecordStore::latest('Manufacturing/Products', 'deletion-change-set:old', $root);
$assert(($latestExact['record_id'] ?? '') === 'old', 'store preserves exact-packet latest lookup');
$latestOwner = StudioDeletionApprovalRecordStore::latestForOwner('Manufacturing/Products', $root);
$assert(($latestOwner['record_id'] ?? '') === 'current', 'store resolves latest decision across owner packet versions');
$assert(($latestOwner['storage']['storage_scope'] ?? '') === 'studio_provenance', 'owner latest lookup preserves storage provenance');
$assert(StudioDeletionApprovalRecordStore::latestForOwner('Missing/Owner', $root) === null, 'missing owner history returns null');

$postludes = ['preview.postlude.zz-deletion-change-set.php', 'preview.postlude.zzz-deletion-approval.php', 'preview.postlude.zzzz-deletion-execution-readiness.php'];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[2] === 'preview.postlude.zzzz-deletion-execution-readiness.php', 'readiness postlude loads after approval');

$workspaceSource = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionReadinessWorkspaceService.php');
$assert(is_string($workspaceSource) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $workspaceSource), 'workspace presenter contains no mutation implementation');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
