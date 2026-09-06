<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionSnapshotIntegrityWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotIntegrityWorkspaceService.php';

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
$changeSet = [
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_path' => 'apps/Manufacturing/modules/Products'],
];
$approval = ['readiness_fingerprint' => 'approval-ready'];
$dryRun = [
    'dry_run_fingerprint' => 'dry-run-current',
    'snapshot_plan' => ['destination_path' => 'storage/studio/deletion-execution-snapshots/current'],
];
$executor = [
    'request_evidence' => ['request_id' => 'request-current'],
];
$actor = ['user_id' => 'executor-42', 'authority_role' => 'platform_admin'];
$claim = ['claim_id' => 'claim-current'];
$manifest = ['snapshot_id' => 'snapshot-current'];
$assessment = [
    'status' => 'ok', 'effect' => 'verify',
    'snapshot_integrity' => 'ready', 'snapshot_integrity_valid' => 'yes', 'post_snapshot_execution_ready' => 'yes',
    'manifest_valid' => 'yes', 'checksums_valid' => 'yes', 'source_matches_snapshot' => 'yes',
    'claim_valid' => 'yes', 'executor_identity_valid' => 'yes',
    'current_packet' => ['change_set_fingerprint' => 'current'],
    'claim_evidence' => ['claim_id' => 'claim-current'],
    'actor_evidence' => ['actor_id' => 'executor-42'],
    'snapshot_evidence' => ['snapshot_id' => 'snapshot-current'],
    'snapshot_checks' => [['check_id' => 'snapshot-file']],
    'source_checks' => [['check_id' => 'source-file']],
    'blocking_reasons' => [],
    'snapshot_integrity_fingerprint' => 'deletion-snapshot-integrity:workspace',
    'diagnostics' => [],
];

$claimCalls = 0;
$manifestCalls = 0;
$assessorCalls = 0;
$claimReader = static function (string $requestId, ?string $root) use (&$claimCalls, $claim): array {
    $claimCalls++;
    if ($requestId !== 'request-current') { throw new RuntimeException('wrong request id'); }
    return $claim;
};
$manifestReader = static function (string $destinationPath, ?string $root) use (&$manifestCalls, $manifest): array {
    $manifestCalls++;
    if ($destinationPath !== 'storage/studio/deletion-execution-snapshots/current') { throw new RuntimeException('wrong destination'); }
    return $manifest;
};
$assessor = static function (array $owner, array $packet, array $approvalEvidence, array $simulation, array $executorEvidence, ?array $claimEvidence, ?array $manifestEvidence, ?array $currentActor, ?string $root) use (&$assessorCalls, $assessment): array {
    $assessorCalls++;
    if (($owner['owner_key'] ?? '') !== 'Manufacturing/Products') { throw new RuntimeException('wrong owner'); }
    if (($claimEvidence['claim_id'] ?? '') !== 'claim-current') { throw new RuntimeException('wrong claim'); }
    if (($manifestEvidence['snapshot_id'] ?? '') !== 'snapshot-current') { throw new RuntimeException('wrong manifest'); }
    return $assessment;
};

$idle = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build($owners, 'Manufacturing/Products', false, $changeSet, $approval, $dryRun, $executor, $actor, null, $claimReader, $manifestReader, $assessor);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not verify');
$assert(($idle['assessment'] ?? null) === null, 'idle workspace has no assessment');
$assert($claimCalls === 0 && $manifestCalls === 0 && $assessorCalls === 0, 'idle workspace performs no reads or assessment');

$ready = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $approval, $dryRun, $executor, $actor, null, $claimReader, $manifestReader, $assessor);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace becomes ready');
$assert($claimCalls === 1, 'claim reader is invoked once');
$assert($manifestCalls === 1, 'manifest reader is invoked once');
$assert($assessorCalls === 1, 'canonical assessor is invoked once');
$assert(($ready['snapshot_integrity'] ?? '') === 'ready', 'integrity state is exposed');
$assert(($ready['snapshot_integrity_valid'] ?? '') === 'yes', 'integrity validity is exposed');
$assert(($ready['post_snapshot_execution_ready'] ?? '') === 'yes', 'post-snapshot readiness is exposed');
$assert(($ready['manifest_valid'] ?? '') === 'yes', 'manifest validity is exposed');
$assert(($ready['checksums_valid'] ?? '') === 'yes', 'checksum validity is exposed');
$assert(($ready['source_matches_snapshot'] ?? '') === 'yes', 'source match is exposed');
$assert(($ready['claim_valid'] ?? '') === 'yes', 'claim validity is exposed');
$assert(($ready['executor_identity_valid'] ?? '') === 'yes', 'executor identity validity is exposed');
$assert(($ready['snapshot_evidence']['snapshot_id'] ?? '') === 'snapshot-current', 'snapshot evidence is exposed');
$assert(count($ready['snapshot_checks'] ?? []) === 1, 'snapshot checks are exposed');
$assert(count($ready['source_checks'] ?? []) === 1, 'source checks are exposed');
$assert(($ready['snapshot_integrity_fingerprint'] ?? '') === 'deletion-snapshot-integrity:workspace', 'integrity fingerprint is exposed');
$assert(($ready['blocking_reasons'] ?? []) === [], 'ready workspace has no blockers');

$missingOwner = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build($owners, 'Missing/Owner', true, $changeSet, $approval, $dryRun, $executor, $actor);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_INTEGRITY_OWNER_UNAVAILABLE', 'missing-owner diagnostic is explicit');

$missingPrerequisite = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build($owners, 'Manufacturing/Products', true, null, $approval, $dryRun, $executor, $actor);
$assert(($missingPrerequisite['status'] ?? '') === 'error', 'missing prerequisite fails safely');
$assert(($missingPrerequisite['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_INTEGRITY_PREREQUISITES_REQUIRED', 'missing-prerequisite diagnostic is explicit');

$wrongPacket = $changeSet;
$wrongPacket['target']['owner_key'] = 'Manufacturing/Other';
$invalidPacket = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build($owners, 'Manufacturing/Products', true, $wrongPacket, $approval, $dryRun, $executor, $actor);
$assert(($invalidPacket['status'] ?? '') === 'error', 'wrong packet owner fails safely');
$assert(($invalidPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_INTEGRITY_PACKET_INVALID', 'wrong-packet diagnostic is explicit');

$invalidResult = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $changeSet, $approval, $dryRun, $executor, $actor, null,
    static fn(): array => $claim,
    static fn(): array => $manifest,
    static fn(): string => 'invalid'
);
$assert(($invalidResult['status'] ?? '') === 'error', 'invalid assessor result fails safely');
$assert(($invalidResult['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_INTEGRITY_RESULT_INVALID', 'invalid-result diagnostic is explicit');

$thrown = OwnerStructureDeletionSnapshotIntegrityWorkspaceService::build(
    $owners, 'Manufacturing/Products', true, $changeSet, $approval, $dryRun, $executor, $actor, null,
    static function (): array { throw new RuntimeException('snapshot history failure'); },
    $manifestReader,
    $assessor
);
$assert(($thrown['status'] ?? '') === 'error', 'reader exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_SNAPSHOT_INTEGRITY_FAILED', 'reader-exception diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'snapshot history failure', 'reader exception message is preserved');

$postludes = [
    'preview.postlude.zzzzzzzzzz-deletion-snapshot-creation.php',
    'preview.postlude.zzzzzzzzzzz-deletion-snapshot-integrity.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[1] === 'preview.postlude.zzzzzzzzzzz-deletion-snapshot-integrity.php', 'integrity postlude loads after snapshot creation');

$source = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionSnapshotIntegrityWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'workspace presenter contains no mutation implementation');

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
