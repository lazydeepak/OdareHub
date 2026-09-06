<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionSnapshotWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotWorkspaceService.php';

$pass = 0;
$fail = 0;
$assert = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "PASS: {$message}\n"; return; }
    $fail++; echo "FAIL: {$message}\n";
};

$owners = [[
    'owner_key' => 'Manufacturing/Products',
    'owner_type' => 'module',
    'root_path' => 'apps/Manufacturing/modules/Products',
]];
$destination = 'storage/studio/deletion-execution-snapshots/a/b/c';
$readiness = [
    'snapshot_readiness' => 'ready', 'snapshot_ready' => 'yes', 'claim_valid' => 'yes',
    'executor_identity_valid' => 'yes', 'source_available' => 'yes', 'destination_available' => 'yes', 'blocking_reasons' => [],
    'current_packet' => ['target' => ['owner_key' => 'Manufacturing/Products', 'target_path' => 'apps/Manufacturing/modules/Products']],
    'claim_evidence' => ['present' => 'yes', 'claim_id' => 'claim-1', 'claim_fingerprint' => 'claim-fp', 'executor' => ['actor_id' => '42']],
    'actor_evidence' => ['actor_id' => '42', 'authority_role' => 'platform_admin'],
    'snapshot_contract' => ['source_path' => 'apps/Manufacturing/modules/Products', 'destination_path' => $destination],
    'snapshot_readiness_fingerprint' => 'snapshot-ready-fp',
];

$calls = 0;
$reader = static function (string $path, ?string $root) use (&$calls, $destination): ?array {
    $calls++;
    if ($path !== $destination) { throw new RuntimeException('wrong path'); }
    return null;
};
$idle = OwnerStructureDeletionSnapshotWorkspaceService::build($owners, 'Manufacturing/Products', false, $readiness, null, null, $reader);
$assert(($idle['status'] ?? '') === 'idle', 'idle status');
$assert(($idle['can_create'] ?? '') === 'no', 'idle cannot create');
$assert($calls === 0, 'idle no read');
$ready = OwnerStructureDeletionSnapshotWorkspaceService::build($owners, 'Manufacturing/Products', true, $readiness, null, null, $reader);
$assert(($ready['status'] ?? '') === 'ready', 'requested ready');
$assert(($ready['can_create'] ?? '') === 'yes', 'can create');
$assert($calls === 1, 'reader once');
$assert(($ready['source']['snapshot_readiness_fingerprint'] ?? '') === 'snapshot-ready-fp', 'readiness fp exposed');
$assert(($ready['source']['claim_id'] ?? '') === 'claim-1', 'claim id exposed');
$assert(($ready['source']['claim_fingerprint'] ?? '') === 'claim-fp', 'claim fp exposed');
$assert(($ready['snapshot_contract']['destination_path'] ?? '') === $destination, 'contract exposed');
$assert(($ready['actor_evidence']['actor_id'] ?? '') === '42', 'actor exposed');
$assert(($ready['claim_evidence']['claim_id'] ?? '') === 'claim-1', 'claim exposed');
$assert(($ready['existing_snapshot'] ?? null) === null, 'no existing snapshot');
$assert(($ready['diagnostics'] ?? []) === [], 'no diagnostics');

$existing = ['snapshot_id' => 'snap-1', 'snapshot_state' => 'created', 'manifest_fingerprint' => 'manifest-fp'];
$blocked = OwnerStructureDeletionSnapshotWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $readiness,
    ['status' => 'recorded'],
    null,
    static fn(): array => $existing
);
$assert(($blocked['status'] ?? '') === 'ready', 'existing still renders');
$assert(($blocked['can_create'] ?? '') === 'no', 'existing disables create');
$assert(($blocked['existing_snapshot']['snapshot_id'] ?? '') === 'snap-1', 'existing exposed');
$assert(($blocked['flash']['status'] ?? '') === 'recorded', 'flash exposed');
$assert(($blocked['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_ALREADY_EXISTS', 'existing diagnostic');

$notReady = $readiness;
$notReady['snapshot_readiness'] = 'snapshot_exists';
$notReady['snapshot_ready'] = 'no';
$blocked = OwnerStructureDeletionSnapshotWorkspaceService::build($owners, 'Manufacturing/Products', true, $notReady, null, null, static fn(): ?array => null);
$assert(($blocked['can_create'] ?? '') === 'no', 'not ready disables');
$assert(($blocked['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_NOT_READY', 'not ready diagnostic');
$missing = OwnerStructureDeletionSnapshotWorkspaceService::build($owners, 'Missing/Owner', true, $readiness);
$assert(($missing['status'] ?? '') === 'error', 'missing owner error');
$assert(($missing['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_OWNER_UNAVAILABLE', 'missing owner code');
$missingReadiness = OwnerStructureDeletionSnapshotWorkspaceService::build($owners, 'Manufacturing/Products', true, null);
$assert(($missingReadiness['status'] ?? '') === 'error', 'missing readiness error');
$assert(($missingReadiness['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_READINESS_REQUIRED', 'missing readiness code');
$wrong = $readiness;
$wrong['current_packet']['target']['owner_key'] = 'Other';
$invalid = OwnerStructureDeletionSnapshotWorkspaceService::build($owners, 'Manufacturing/Products', true, $wrong);
$assert(($invalid['status'] ?? '') === 'error', 'wrong owner error');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_PACKET_INVALID', 'wrong owner code');
$thrown = OwnerStructureDeletionSnapshotWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $readiness,
    null,
    null,
    static function (): ?array { throw new RuntimeException('read fail'); }
);
$assert(($thrown['status'] ?? '') === 'error', 'reader failure error');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_HISTORY_FAILED', 'reader failure code');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'read fail', 'reader failure message');
$source = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(/i', $source), 'workspace no mutation');
$postludes = [
    'preview.postlude.zzzzzzzzz-deletion-snapshot-readiness.php',
    'preview.postlude.zzzzzzzzzz-deletion-snapshot-creation.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[1] === 'preview.postlude.zzzzzzzzzz-deletion-snapshot-creation.php', 'postlude order');

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
