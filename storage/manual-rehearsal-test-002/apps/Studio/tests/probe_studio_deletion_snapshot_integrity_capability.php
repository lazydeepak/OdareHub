<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionSnapshotIntegrityService;

require_once dirname(__DIR__) . '/Services/StudioDeletionSnapshotIntegrityService.php';

$pass = 0;
$fail = 0;
$assert = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "PASS: {$message}\n"; return; }
    $fail++; echo "FAIL: {$message}\n";
};
$sort = static function ($value) use (&$sort) {
    if (!is_array($value)) { return $value; }
    if (array_is_list($value)) { return array_map($sort, $value); }
    ksort($value);
    foreach ($value as $key => $item) { $value[$key] = $sort($item); }
    return $value;
};
$json = static fn(array $value): string => (string)json_encode($sort($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$manifestFingerprint = static function (array $manifest) use ($json): string {
    unset($manifest['manifest_fingerprint'], $manifest['storage']);
    return 'deletion-snapshot-manifest:' . substr(sha1($json($manifest)), 0, 24);
};

$root = sys_get_temp_dir() . '/studio-snapshot-integrity-' . bin2hex(random_bytes(4));
$targetPath = 'apps/Manufacturing/modules/Products';
$destinationPath = 'storage/studio/deletion-execution-snapshots/Manufacturing__Products/deletion-change-set__current/Products';
$sourceAbsolute = $root . '/' . $targetPath;
$snapshotAbsolute = $root . '/' . $destinationPath;
mkdir($sourceAbsolute . '/sub', 0777, true);
mkdir($snapshotAbsolute . '/payload/Products/sub', 0777, true);
file_put_contents($sourceAbsolute . '/a.txt', "alpha\n");
file_put_contents($sourceAbsolute . '/sub/b.txt', "beta\n");
file_put_contents($snapshotAbsolute . '/payload/Products/a.txt', "alpha\n");
file_put_contents($snapshotAbsolute . '/payload/Products/sub/b.txt', "beta\n");

$changeSet = [
    'immutable' => 'yes', 'can_execute' => 'no', 'can_apply' => 'no',
    'change_set_fingerprint' => 'deletion-change-set:current',
    'source_plan' => ['fingerprint' => 'deletion-plan:current'],
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_type' => 'owner', 'target_path' => $targetPath],
];
$approval = [
    'effect' => 'verify', 'execution_readiness' => 'ready', 'execution_eligible' => 'yes', 'approval_valid' => 'yes',
    'can_execute' => 'no', 'can_apply' => 'no', 'grants_execution_authority' => 'no',
    'readiness_fingerprint' => 'deletion-execution-readiness:current',
    'approval_evidence' => ['record_id' => 'approval-current', 'record_fingerprint' => 'approval-fingerprint-current'],
];
$dryRun = [
    'effect' => 'simulate', 'dry_run_state' => 'ready', 'simulation_complete' => 'yes', 'execution_eligible_at_simulation' => 'yes',
    'would_execute' => 'no', 'would_apply' => 'no', 'would_write' => 'no', 'would_archive' => 'no', 'would_delete' => 'no',
    'can_execute' => 'no', 'can_apply' => 'no', 'grants_execution_authority' => 'no', 'blocking_reasons' => [],
    'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
    'snapshot_plan' => ['required' => 'yes', 'source_path' => $targetPath, 'destination_path' => $destinationPath],
];
$claim = [
    'status' => 'recorded', 'claim_state' => 'claimed', 'immutable' => 'yes', 'append_only' => 'yes',
    'claim_id' => 'claim-current', 'claim_fingerprint' => 'deletion-execution-claim:current',
    'executor' => ['actor_id' => 'executor-42', 'display_name' => 'Executor 42', 'authority_role' => 'platform_admin'],
];
$sourceBindings = [
    'change_set_fingerprint' => 'deletion-change-set:current',
    'plan_fingerprint' => 'deletion-plan:current',
    'readiness_fingerprint' => 'deletion-execution-readiness:current',
    'dry_run_fingerprint' => 'deletion-execution-dry-run:current',
    'approval_record_id' => 'approval-current',
    'approval_record_fingerprint' => 'approval-fingerprint-current',
    'request_id' => 'request-current',
    'request_fingerprint' => 'deletion-execution-request:current',
    'claim_id' => 'claim-current',
    'claim_fingerprint' => 'deletion-execution-claim:current',
];
$executor = [
    'effect' => 'verify', 'executor_readiness' => 'consumed', 'request_valid' => 'yes', 'single_use_available' => 'no',
    'can_claim' => 'no', 'can_execute' => 'no', 'can_apply' => 'no', 'can_archive' => 'no', 'can_delete' => 'no', 'grants_execution_authority' => 'no',
    'request_evidence' => [
        'request_id' => 'request-current', 'request_fingerprint' => 'deletion-execution-request:current',
        'expires_at_utc' => '2026-07-18T13:00:00.000000Z',
    ],
    'current_packet' => array_merge(array_diff_key($sourceBindings, ['claim_id' => true, 'claim_fingerprint' => true]), ['target' => $changeSet['target']]),
];
$actor = ['user_id' => 'executor-42', 'display_name' => 'Executor 42', 'authority_role' => 'platform_admin'];
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-18T12:00:00Z');

$files = [
    ['path' => 'payload/Products/a.txt', 'size_bytes' => filesize($snapshotAbsolute . '/payload/Products/a.txt'), 'sha256' => hash_file('sha256', $snapshotAbsolute . '/payload/Products/a.txt')],
    ['path' => 'payload/Products/sub/b.txt', 'size_bytes' => filesize($snapshotAbsolute . '/payload/Products/sub/b.txt'), 'sha256' => hash_file('sha256', $snapshotAbsolute . '/payload/Products/sub/b.txt')],
];
$manifest = [
    'version' => 'studio.deletion-snapshot.v1', 'effect' => 'mutate', 'snapshot_id' => 'snapshot-current',
    'created_at_utc' => '2026-07-18T11:30:00.000000Z', 'target' => $changeSet['target'], 'source' => $sourceBindings,
    'executor' => $claim['executor'], 'reason' => 'Rollback source required before deletion.',
    'snapshot' => [
        'source_path' => $targetPath, 'destination_path' => $destinationPath,
        'payload_path' => $destinationPath . '/payload/Products',
        'manifest_path' => $destinationPath . '/.studio-snapshot-manifest.json',
        'copy_mode' => 'copy_only', 'overwrite_allowed' => 'no', 'checksum_algorithm' => 'sha256',
    ],
    'creation_contract' => [
        'claim_required' => 'yes', 'snapshot_readiness_required' => 'yes', 'copy_only' => 'yes',
        'atomic_publish_required' => 'yes', 'overwrite_allowed' => 'no', 'manifest_required' => 'yes',
        'checksum_algorithm' => 'sha256', 'snapshot_is_execution_authority' => 'no',
    ],
    'status' => 'recorded', 'snapshot_state' => 'created', 'manifest_version' => 'studio.deletion-snapshot-manifest.v1',
    'immutable' => 'yes', 'append_only' => 'yes', 'atomic_publish' => 'yes',
    'can_execute' => 'no', 'can_apply' => 'no', 'can_archive' => 'no', 'can_delete' => 'no',
    'execution_authorized' => 'no', 'grants_execution_authority' => 'no', 'requires_separate_execution_capability' => 'yes',
    'summary' => [
        'file_count' => 2, 'directory_count' => 2,
        'total_bytes' => array_sum(array_column($files, 'size_bytes')),
        'tree_checksum_sha256' => hash('sha256', $json($files)),
    ],
    'files' => $files,
];
$manifest['manifest_fingerprint'] = $manifestFingerprint($manifest);
$manifest['storage'] = [
    'storage_scope' => 'studio_snapshot', 'relative_path' => $destinationPath,
    'manifest_relative_path' => $destinationPath . '/.studio-snapshot-manifest.json',
    'immutable' => 'yes', 'append_only' => 'yes', 'atomic_publish' => 'yes',
];
file_put_contents($snapshotAbsolute . '/.studio-snapshot-manifest.json', json_encode(array_diff_key($manifest, ['storage' => true]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$ready = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $manifest, $actor, $root, null, $clock);
$assert(($ready['status'] ?? '') === 'ok', 'valid snapshot returns ok status');
$assert(($ready['effect'] ?? '') === 'verify', 'integrity capability declares verify effect');
$assert(($ready['snapshot_integrity_version'] ?? '') === StudioDeletionSnapshotIntegrityService::VERSION, 'integrity version is explicit');
$assert(($ready['snapshot_integrity'] ?? '') === 'ready', 'valid snapshot yields ready state');
$assert(($ready['snapshot_integrity_valid'] ?? '') === 'yes', 'snapshot integrity is valid');
$assert(($ready['post_snapshot_execution_ready'] ?? '') === 'yes', 'post-snapshot execution readiness is yes');
$assert(($ready['manifest_valid'] ?? '') === 'yes', 'manifest is valid');
$assert(($ready['checksums_valid'] ?? '') === 'yes', 'checksums are valid');
$assert(($ready['source_matches_snapshot'] ?? '') === 'yes', 'live source matches snapshot');
$assert(($ready['claim_valid'] ?? '') === 'yes', 'claim remains valid');
$assert(($ready['executor_identity_valid'] ?? '') === 'yes', 'executor identity remains valid');
$assert(($ready['can_execute'] ?? '') === 'no', 'verifier cannot execute');
$assert(($ready['can_apply'] ?? '') === 'no', 'verifier cannot apply');
$assert(($ready['can_archive'] ?? '') === 'no', 'verifier cannot archive');
$assert(($ready['can_delete'] ?? '') === 'no', 'verifier cannot delete');
$assert(($ready['grants_execution_authority'] ?? '') === 'no', 'verifier grants no execution authority');
$assert(($ready['requires_separate_execution_capability'] ?? '') === 'yes', 'separate execution capability remains required');
$assert(($ready['snapshot_evidence']['actual_snapshot_file_count'] ?? 0) === 2, 'actual snapshot file count is exposed');
$assert(($ready['snapshot_evidence']['actual_source_file_count'] ?? 0) === 2, 'actual source file count is exposed');
$assert(count($ready['snapshot_checks'] ?? []) === 2, 'per-file snapshot checks are emitted');
$assert(count($ready['source_checks'] ?? []) === 2, 'per-file source checks are emitted');
$assert(($ready['blocking_reasons'] ?? []) === [], 'ready snapshot has no blockers');
$assert(str_starts_with((string)($ready['snapshot_integrity_fingerprint'] ?? ''), 'deletion-snapshot-integrity:'), 'integrity fingerprint is emitted');

$missing = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, null, $actor, $root, null, $clock);
$assert(($missing['snapshot_integrity'] ?? '') === 'snapshot_missing', 'missing manifest yields snapshot-missing state');
$assert(in_array('SNAPSHOT_MANIFEST_REQUIRED', $missing['blocking_reasons'] ?? [], true), 'missing manifest blocker is explicit');

$staleManifest = $manifest;
$staleManifest['source']['change_set_fingerprint'] = 'deletion-change-set:old';
$staleManifest['manifest_fingerprint'] = $manifestFingerprint($staleManifest);
$stale = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $staleManifest, $actor, $root, null, $clock);
$assert(($stale['snapshot_integrity'] ?? '') === 'stale', 'old packet binding yields stale state');
$assert(in_array('MANIFEST_SOURCE_MISMATCH:CHANGE_SET_FINGERPRINT', $stale['blocking_reasons'] ?? [], true), 'stale packet blocker is explicit');

$tamperedManifest = $manifest;
$tamperedManifest['reason'] = 'tampered';
$invalid = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $tamperedManifest, $actor, $root, null, $clock);
$assert(($invalid['snapshot_integrity'] ?? '') === 'manifest_invalid', 'tampered manifest fails integrity');
$assert(in_array('MANIFEST_FINGERPRINT_INVALID', $invalid['blocking_reasons'] ?? [], true), 'manifest fingerprint blocker is explicit');

file_put_contents($snapshotAbsolute . '/payload/Products/a.txt', "changed\n");
$mismatch = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $manifest, $actor, $root, null, $clock);
$assert(($mismatch['snapshot_integrity'] ?? '') === 'checksum_mismatch', 'changed snapshot payload yields checksum mismatch');
$assert(($mismatch['checksums_valid'] ?? '') === 'no', 'checksum mismatch is invalid');
$assert(in_array('SNAPSHOT_FILE_MISMATCH:payload/Products/a.txt', $mismatch['blocking_reasons'] ?? [], true), 'changed snapshot file blocker is explicit');
file_put_contents($snapshotAbsolute . '/payload/Products/a.txt', "alpha\n");

file_put_contents($snapshotAbsolute . '/payload/Products/extra.txt', "extra\n");
$extra = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $manifest, $actor, $root, null, $clock);
$assert(($extra['snapshot_integrity'] ?? '') === 'checksum_mismatch', 'extra snapshot file blocks readiness');
$assert(in_array('SNAPSHOT_EXTRA_FILE:payload/Products/extra.txt', $extra['blocking_reasons'] ?? [], true), 'extra snapshot file blocker is explicit');
unlink($snapshotAbsolute . '/payload/Products/extra.txt');

file_put_contents($sourceAbsolute . '/a.txt', "source changed\n");
$sourceChanged = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $manifest, $actor, $root, null, $clock);
$assert(($sourceChanged['snapshot_integrity'] ?? '') === 'source_changed', 'changed live source yields source-changed state');
$assert(($sourceChanged['checksums_valid'] ?? '') === 'yes', 'snapshot remains intact when source changed');
$assert(($sourceChanged['source_matches_snapshot'] ?? '') === 'no', 'source mismatch is exposed');
$assert(in_array('SOURCE_FILE_CHANGED:payload/Products/a.txt', $sourceChanged['blocking_reasons'] ?? [], true), 'source change blocker is explicit');
file_put_contents($sourceAbsolute . '/a.txt', "alpha\n");

$wrongActor = ['user_id' => 'other', 'display_name' => 'Other', 'authority_role' => 'platform_admin'];
$wrong = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $manifest, $wrongActor, $root, null, $clock);
$assert(($wrong['snapshot_integrity'] ?? '') === 'wrong_executor', 'wrong executor is rejected');
$assert(in_array('CURRENT_ACTOR_NOT_SNAPSHOT_EXECUTOR', $wrong['blocking_reasons'] ?? [], true), 'wrong-executor blocker is explicit');

$expiredClock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-18T13:00:00Z');
$expired = StudioDeletionSnapshotIntegrityService::assess($changeSet, $approval, $dryRun, $executor, $claim, $manifest, $actor, $root, null, $expiredClock);
$assert(($expired['snapshot_integrity'] ?? '') === 'expired', 'expired request blocks execution readiness');
$assert(in_array('EXECUTION_REQUEST_EXPIRED', $expired['blocking_reasons'] ?? [], true), 'expiry blocker is explicit');

$invalidPacket = $changeSet;
$invalidPacket['can_execute'] = 'yes';
$unknown = StudioDeletionSnapshotIntegrityService::assess($invalidPacket, $approval, $dryRun, $executor, $claim, $manifest, $actor, $root, null, $clock);
$assert(($unknown['snapshot_integrity'] ?? '') === 'unknown', 'over-authoritative packet fails closed');
$assert(in_array('CHANGE_SET_CONTRACT_INVALID', $unknown['blocking_reasons'] ?? [], true), 'packet authority blocker is explicit');

$serviceSource = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionSnapshotIntegrityService.php');
$assert(is_string($serviceSource) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $serviceSource), 'integrity verifier contains no mutation implementation');
$assert(is_dir($sourceAbsolute), 'verification leaves owner target intact');
$assert(is_dir($snapshotAbsolute), 'verification leaves snapshot intact');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
