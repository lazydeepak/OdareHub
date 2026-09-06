<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionSnapshotService;
use Apps\Studio\Services\StudioDeletionSnapshotStore;

require_once dirname(__DIR__) . '/Services/StudioDeletionSnapshotService.php';

$pass = 0;
$fail = 0;
$assert = static function (bool $condition, string $message) use (&$pass, &$fail): void {
    if ($condition) { $pass++; echo "PASS: {$message}\n"; return; }
    $fail++; echo "FAIL: {$message}\n";
};

$root = sys_get_temp_dir() . '/snapshot-v1-' . bin2hex(random_bytes(4));
mkdir($root . '/apps/Manufacturing/modules/Products/config', 0777, true);
file_put_contents($root . '/apps/Manufacturing/modules/Products/a.txt', 'alpha');
file_put_contents($root . '/apps/Manufacturing/modules/Products/config/b.json', '{"b":2}');
$destination = 'storage/studio/deletion-execution-snapshots/Manufacturing__Products/deletion-change-set__current/apps__Manufacturing__modules__Products';
$readiness = [
    'status' => 'ok', 'effect' => 'verify', 'snapshot_readiness' => 'ready', 'snapshot_ready' => 'yes',
    'claim_valid' => 'yes', 'executor_identity_valid' => 'yes', 'source_available' => 'yes', 'destination_available' => 'yes',
    'can_snapshot' => 'no', 'would_write' => 'no', 'would_archive' => 'no', 'would_delete' => 'no',
    'can_execute' => 'no', 'can_apply' => 'no', 'can_archive' => 'no', 'can_delete' => 'no', 'grants_execution_authority' => 'no',
    'requires_separate_snapshot_capability' => 'yes', 'requires_separate_execution_capability' => 'yes', 'blocking_reasons' => [],
    'current_packet' => [
        'change_set_fingerprint' => 'deletion-change-set:current', 'plan_fingerprint' => 'deletion-plan:current',
        'readiness_fingerprint' => 'deletion-readiness:current', 'dry_run_fingerprint' => 'dry-run:current',
        'approval_record_id' => 'approval-1', 'approval_record_fingerprint' => 'approval-fp',
        'request_id' => 'request-1', 'request_fingerprint' => 'request-fp',
        'target' => ['owner_key' => 'Manufacturing/Products', 'target_type' => 'owner', 'target_path' => 'apps/Manufacturing/modules/Products'],
    ],
    'claim_evidence' => [
        'present' => 'yes', 'claim_id' => 'claim-1', 'claim_fingerprint' => 'claim-fp',
        'executor' => ['actor_id' => '42', 'display_name' => 'Executor', 'authority_role' => 'platform_admin'],
    ],
    'actor_evidence' => ['actor_id' => '42', 'display_name' => 'Executor', 'authority_role' => 'platform_admin'],
    'snapshot_contract' => [
        'required' => 'yes', 'source_path' => 'apps/Manufacturing/modules/Products', 'destination_path' => $destination,
        'source_exists' => 'yes', 'source_readable' => 'yes', 'destination_exists' => 'no',
        'archive_mode' => 'copy_only', 'overwrite_allowed' => 'no', 'requires_post_write_manifest' => 'yes',
    ],
    'snapshot_readiness_fingerprint' => 'snapshot-ready-fp',
];
$input = [
    'snapshot_readiness_fingerprint' => 'snapshot-ready-fp', 'claim_id' => 'claim-1', 'claim_fingerprint' => 'claim-fp',
    'snapshot_confirmed' => 'yes', 'reason' => 'Create rollback snapshot.',
];
$actor = ['user_id' => '42', 'display_name' => 'Executor', 'authority_role' => 'platform_admin'];
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-19T02:00:00Z');

$result = StudioDeletionSnapshotService::create($readiness, $input, $actor, $root, null, $clock);
$assert(($result['status'] ?? '') === 'recorded', 'snapshot recorded');
$assert(($result['snapshot_state'] ?? '') === 'created', 'snapshot created state');
$assert(($result['manifest_version'] ?? '') === StudioDeletionSnapshotStore::MANIFEST_VERSION, 'manifest version');
$assert(($result['effect'] ?? '') === 'mutate', 'effect mutate');
$assert(($result['immutable'] ?? '') === 'yes', 'immutable');
$assert(($result['append_only'] ?? '') === 'yes', 'append only');
$assert(($result['atomic_publish'] ?? '') === 'yes', 'atomic publish');
$assert(($result['can_execute'] ?? '') === 'no', 'no execute');
$assert(($result['can_delete'] ?? '') === 'no', 'no delete');
$assert(($result['source']['claim_id'] ?? '') === 'claim-1', 'claim id bound');
$assert(($result['source']['snapshot_readiness_fingerprint'] ?? '') === 'snapshot-ready-fp', 'readiness bound');
$assert(($result['executor']['actor_id'] ?? '') === '42', 'executor bound');
$assert(($result['created_at_utc'] ?? '') === '2026-07-19T02:00:00.000000Z', 'clock used');
$assert(($result['summary']['file_count'] ?? 0) === 2, 'file count');
$assert(($result['summary']['directory_count'] ?? 0) === 2, 'directory count source root and config');
$assert(($result['summary']['total_bytes'] ?? 0) === 12, 'total bytes');
$assert(count($result['files'] ?? []) === 2, 'file manifest entries');
$assert(($result['files'][0]['path'] ?? '') === 'payload/Products/a.txt', 'manifest files are sorted');
$assert(str_starts_with((string)($result['manifest_fingerprint'] ?? ''), 'deletion-snapshot-manifest:'), 'manifest fingerprint');
$assert(strlen((string)($result['summary']['tree_checksum_sha256'] ?? '')) === 64, 'tree checksum');
$absolute = $root . '/' . $destination;
$assert(($result['files'][0]['sha256'] ?? '') === hash_file('sha256', $absolute . '/payload/Products/a.txt'), 'manifest checksum matches payload');
$assert(is_dir($absolute), 'destination exists');
$assert(is_file($absolute . '/.studio-snapshot-manifest.json'), 'manifest file exists');
$diskManifest = json_decode((string)file_get_contents($absolute . '/.studio-snapshot-manifest.json'), true);
$assert(($diskManifest['manifest_fingerprint'] ?? '') === ($result['manifest_fingerprint'] ?? ''), 'on-disk manifest fingerprint matches result');
$assert(($result['storage']['manifest_relative_path'] ?? '') === $destination . '/.studio-snapshot-manifest.json', 'manifest storage path is explicit');
$assert(file_get_contents($absolute . '/payload/Products/a.txt') === 'alpha', 'payload file copied');
$assert(file_get_contents($absolute . '/payload/Products/config/b.json') === '{"b":2}', 'nested payload copied');
$assert(is_dir($root . '/apps/Manufacturing/modules/Products'), 'source remains');
$assert(file_get_contents($root . '/apps/Manufacturing/modules/Products/a.txt') === 'alpha', 'source unmodified');
$manifest = StudioDeletionSnapshotStore::read($destination, $root);
$assert(($manifest['snapshot_id'] ?? '') === ($result['snapshot_id'] ?? ''), 'read manifest snapshot id');
$assert(($manifest['storage']['storage_scope'] ?? '') === 'studio_snapshot', 'read storage evidence');

$duplicate = StudioDeletionSnapshotService::create($readiness, $input, $actor, $root, null, $clock);
$assert(($duplicate['status'] ?? '') === 'error', 'duplicate fails');
$assert(($duplicate['diagnostics'][0]['code'] ?? '') === 'DELETION_SNAPSHOT_CREATION_ALREADY_EXISTS', 'duplicate code');
$stale = $input; $stale['claim_fingerprint'] = 'stale';
$error = StudioDeletionSnapshotService::create($readiness, $stale, $actor, $root, null, $clock);
$assert(($error['status'] ?? '') === 'error', 'stale binding fails');
$assert(in_array('DELETION_SNAPSHOT_CREATION_BINDING_STALE', array_column($error['diagnostics'] ?? [], 'code'), true), 'stale diagnostic');
$wrong = ['user_id' => '99', 'display_name' => 'Wrong', 'authority_role' => 'platform_admin'];
$error = StudioDeletionSnapshotService::create($readiness, $input, $wrong, $root, null, $clock);
$assert(in_array('DELETION_SNAPSHOT_CREATION_ACTOR_MISMATCH', array_column($error['diagnostics'] ?? [], 'code'), true), 'wrong actor diagnostic');
$notReady = $readiness; $notReady['snapshot_readiness'] = 'snapshot_exists'; $notReady['snapshot_ready'] = 'no';
$error = StudioDeletionSnapshotService::create($notReady, $input, $actor, $root, null, $clock);
$assert(in_array('DELETION_SNAPSHOT_CREATION_READINESS_INVALID', array_column($error['diagnostics'] ?? [], 'code'), true), 'not ready fails');
$unconfirmed = $input; $unconfirmed['snapshot_confirmed'] = 'no';
$error = StudioDeletionSnapshotService::create($readiness, $unconfirmed, $actor, $root, null, $clock);
$assert(in_array('DELETION_SNAPSHOT_CREATION_CONFIRMATION_REQUIRED', array_column($error['diagnostics'] ?? [], 'code'), true), 'confirmation required');
$noReason = $input; $noReason['reason'] = '';
$error = StudioDeletionSnapshotService::create($readiness, $noReason, $actor, $root, null, $clock);
$assert(in_array('DELETION_SNAPSHOT_CREATION_REASON_REQUIRED', array_column($error['diagnostics'] ?? [], 'code'), true), 'reason required');
$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionSnapshotService.php');
$assert(is_string($source) && !preg_match('/delete_target|archive_target|unlink\s*\(|rmdir\s*\(|PDO|DELETE\s+FROM/i', $source), 'service has no deletion authority');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);
echo "\nResults: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
