<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionApprovalRecordService;
use Apps\Studio\Services\StudioDeletionApprovalRecordStore;

require_once dirname(__DIR__) . '/Services/StudioDeletionApprovalRecordService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};

$changeSet = [
    'status' => 'ok',
    'change_set_version' => 'studio.deletion-change-set.v1',
    'change_set_state' => 'ready_for_review',
    'immutable' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'requires_approval' => 'yes',
    'target' => [
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'target_path' => 'apps/Manufacturing/modules/Products',
    ],
    'source_plan' => ['version' => 'studio.deletion-plan.v1', 'state' => 'ready', 'fingerprint' => 'deletion-plan:probe'],
    'approval_contract' => [
        'required' => 'yes',
        'approval_scope' => 'deletion_change_set',
        'approval_readiness' => 'ready',
        'approval_binds_to' => [
            'change_set_fingerprint' => 'deletion-change-set:probe',
            'plan_fingerprint' => 'deletion-plan:probe',
            'owner_key' => 'Manufacturing/Products',
            'target_path' => 'apps/Manufacturing/modules/Products',
        ],
        'required_confirmations' => ['target_identity_confirmed', 'snapshot_created'],
    ],
    'change_set_fingerprint' => 'deletion-change-set:probe',
];
$actor = ['user_id' => '42', 'display_name' => 'Platform Admin', 'authority_role' => 'platform_admin'];
$input = [
    'decision' => 'approved',
    'reason' => 'Reviewed exact packet and rollback evidence.',
    'change_set_fingerprint' => 'deletion-change-set:probe',
    'plan_fingerprint' => 'deletion-plan:probe',
    'confirmations' => ['target_identity_confirmed', 'snapshot_created'],
];

$writtenRecord = null;
$writerCalls = 0;
$writer = static function (array $record, ?string $root) use (&$writtenRecord, &$writerCalls): array {
    $writerCalls++;
    $writtenRecord = $record;
    return ['storage_scope' => 'studio_provenance', 'relative_path' => 'storage/studio/deletion-approvals/probe.json', 'immutable' => 'yes', 'append_only' => 'yes'];
};
$clock = static fn(): DateTimeImmutable => new DateTimeImmutable('2026-07-14T12:00:00.000000Z');
$idFactory = static fn(array $material): string => 'deletion-approval-probe-001';

$approved = StudioDeletionApprovalRecordService::record($changeSet, $input, $actor, null, $writer, $clock, $idFactory);
$assert(($approved['status'] ?? '') === 'recorded', 'valid approval is recorded');
$assert($writerCalls === 1, 'valid approval invokes writer exactly once');
$assert(($approved['decision'] ?? '') === 'approved', 'approval decision is normalized');
$assert(($approved['record_id'] ?? '') === 'deletion-approval-probe-001', 'record id is preserved');
$assert(($approved['record_version'] ?? '') === StudioDeletionApprovalRecordService::VERSION, 'record version is explicit');
$assert(($approved['effect'] ?? '') === 'mutate', 'capability declares mutate effect');
$assert(($approved['immutable'] ?? '') === 'yes', 'approval record is immutable');
$assert(($approved['append_only'] ?? '') === 'yes', 'approval record is append-only');
$assert(($approved['can_execute'] ?? '') === 'no', 'approval record cannot execute');
$assert(($approved['can_apply'] ?? '') === 'no', 'approval record cannot apply');
$assert(($approved['grants_execution_authority'] ?? '') === 'no', 'approval grants no execution authority');
$assert(($approved['source']['change_set_fingerprint'] ?? '') === 'deletion-change-set:probe', 'record binds exact change-set fingerprint');
$assert(($approved['source']['plan_fingerprint'] ?? '') === 'deletion-plan:probe', 'record binds exact plan fingerprint');
$assert(($approved['approver']['actor_id'] ?? '') === '42', 'approver id is preserved');
$assert(($approved['approver']['display_name'] ?? '') === 'Platform Admin', 'approver label is preserved');
$assert(($approved['approver']['authority_role'] ?? '') === 'platform_admin', 'approver authority is preserved');
$assert(($approved['reason'] ?? '') === $input['reason'], 'decision reason is preserved');
$assert(($approved['confirmations']['satisfied'] ?? '') === 'yes', 'approval confirmations are satisfied');
$assert(($approved['recorded_at_utc'] ?? '') === '2026-07-14T12:00:00.000000Z', 'recorded timestamp is deterministic');
$assert(str_starts_with((string)($approved['record_fingerprint'] ?? ''), 'deletion-approval-record:'), 'record fingerprint is emitted');
$assert(($approved['storage']['storage_scope'] ?? '') === 'studio_provenance', 'storage scope is Studio provenance');
$assert(is_array($writtenRecord) && ($writtenRecord['storage'] ?? null) === null, 'stored immutable record excludes derived storage metadata');

$missingConfirmation = $input;
$missingConfirmation['confirmations'] = ['target_identity_confirmed'];
$before = $writerCalls;
$result = StudioDeletionApprovalRecordService::record($changeSet, $missingConfirmation, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'approval missing confirmation fails');
$assert($writerCalls === $before, 'failed approval does not invoke writer');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_CONFIRMATIONS_MISSING', $codes, true), 'missing confirmation diagnostic is explicit');

$staleChangeSet = $input;
$staleChangeSet['change_set_fingerprint'] = 'deletion-change-set:stale';
$result = StudioDeletionApprovalRecordService::record($changeSet, $staleChangeSet, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'stale change-set fingerprint fails');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_CHANGE_SET_STALE', $codes, true), 'stale change-set diagnostic is explicit');

$stalePlan = $input;
$stalePlan['plan_fingerprint'] = 'deletion-plan:stale';
$result = StudioDeletionApprovalRecordService::record($changeSet, $stalePlan, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'stale plan fingerprint fails');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_PLAN_STALE', $codes, true), 'stale plan diagnostic is explicit');

$blocked = $changeSet;
$blocked['change_set_state'] = 'blocked';
$blocked['approval_contract']['approval_readiness'] = 'blocked';
$result = StudioDeletionApprovalRecordService::record($blocked, $input, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'blocked packet cannot be approved');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_NOT_READY', $codes, true), 'not-ready approval diagnostic is explicit');

$rejectionInput = [
    'decision' => 'rejected',
    'reason' => 'Runtime reference remains unresolved.',
    'change_set_fingerprint' => 'deletion-change-set:probe',
    'plan_fingerprint' => 'deletion-plan:probe',
    'confirmations' => [],
];
$rejected = StudioDeletionApprovalRecordService::record($blocked, $rejectionInput, $actor, null, $writer, $clock, static fn(array $material): string => 'deletion-approval-probe-002');
$assert(($rejected['status'] ?? '') === 'recorded', 'blocked packet may be rejected');
$assert(($rejected['decision'] ?? '') === 'rejected', 'rejection decision is recorded');
$assert(($rejected['confirmations']['satisfied'] ?? '') === 'not_required', 'rejection does not require approval confirmations');
$assert(($rejected['grants_execution_authority'] ?? '') === 'no', 'rejection grants no execution authority');

$missingReason = $input;
$missingReason['reason'] = '';
$result = StudioDeletionApprovalRecordService::record($changeSet, $missingReason, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'missing reason fails');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_REASON_REQUIRED', $codes, true), 'missing reason diagnostic is explicit');

$result = StudioDeletionApprovalRecordService::record($changeSet, $input, ['authority_role' => 'app_admin'], null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'non-platform-admin actor fails');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_ACTOR_UNAUTHORIZED', $codes, true), 'unauthorized actor diagnostic is explicit');

$unknownConfirmation = $input;
$unknownConfirmation['confirmations'][] = 'invented_confirmation';
$result = StudioDeletionApprovalRecordService::record($changeSet, $unknownConfirmation, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'unknown confirmation fails');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_CONFIRMATIONS_UNKNOWN', $codes, true), 'unknown confirmation diagnostic is explicit');

$executablePacket = $changeSet;
$executablePacket['can_execute'] = 'yes';
$result = StudioDeletionApprovalRecordService::record($executablePacket, $input, $actor, null, $writer, $clock, $idFactory);
$assert(($result['status'] ?? '') === 'error', 'packet claiming execution authority fails');
$codes = array_map(static fn(array $row): string => (string)($row['code'] ?? ''), $result['diagnostics'] ?? []);
$assert(in_array('DELETION_APPROVAL_CHANGE_SET_INVALID', $codes, true), 'invalid packet diagnostic is explicit');

$root = sys_get_temp_dir() . '/studio-deletion-approval-' . bin2hex(random_bytes(4));
mkdir($root, 0777, true);
$storeRecord = $writtenRecord;
$storeRecord['record_id'] = 'deletion-approval-store-001';
$storeRecord['recorded_at_utc'] = '2026-07-14T12:00:00.000000Z';
$stored = StudioDeletionApprovalRecordStore::append($storeRecord, $root);
$assert(($stored['append_only'] ?? '') === 'yes', 'store declares append-only artifact');
$assert(str_starts_with((string)($stored['relative_path'] ?? ''), 'storage/studio/deletion-approvals/'), 'store confines record to Studio provenance path');
$assert(is_file($root . '/' . str_replace('/', DIRECTORY_SEPARATOR, (string)$stored['relative_path'])), 'approval record file exists');
$latest = StudioDeletionApprovalRecordStore::latest('Manufacturing/Products', 'deletion-change-set:probe', $root);
$assert(($latest['record_id'] ?? '') === 'deletion-approval-store-001', 'store reads latest exact-packet record');
$assert(($latest['storage']['relative_path'] ?? '') === $stored['relative_path'], 'latest record includes relative storage evidence');

$duplicateFailed = false;
try { StudioDeletionApprovalRecordStore::append($storeRecord, $root); } catch (RuntimeException $exception) { $duplicateFailed = true; }
$assert($duplicateFailed, 'append-only store rejects duplicate record id');
$assert(!is_file($root . '/apps/Manufacturing/modules/Products'), 'store does not write owner target path');

$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionApprovalRecordService.php');
$assert(is_string($source) && !preg_match('/delete_target|archive_target|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'approval capability contains no deletion or database execution authority');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
