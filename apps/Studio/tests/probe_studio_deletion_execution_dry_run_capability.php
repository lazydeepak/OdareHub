<?php
declare(strict_types=1);

use Apps\Studio\Services\StudioDeletionExecutionDryRunService;

require_once dirname(__DIR__) . '/Services/StudioDeletionExecutionDryRunService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) { $passes++; echo "PASS: {$message}\n"; return; }
    $fails++; echo "FAIL: {$message}\n";
};

$root = sys_get_temp_dir() . '/studio-deletion-dry-run-' . bin2hex(random_bytes(4));
$targetPath = 'apps/Manufacturing/modules/Products';
$referencePath = 'apps/Sales/modules/Orders/manifest.php';
mkdir($root . '/' . $targetPath, 0777, true);
mkdir(dirname($root . '/' . $referencePath), 0777, true);
file_put_contents($root . '/' . $referencePath, "<?php return ['depends_on' => ['Manufacturing/Products']];\n");
file_put_contents($root . '/' . $targetPath . '/manifest.php', "<?php return [];\n");

$operations = [[
    'sequence' => 1,
    'operation_id' => 'op-remove-ref',
    'phase' => 'prepare',
    'operation_type' => 'remove_blocking_reference',
    'owner_key' => 'Sales/Orders',
    'path' => $referencePath,
    'line_numbers' => [1],
    'reference_count' => 1,
    'severity' => 'blocking',
    'required' => 'yes',
    'blocking' => 'yes',
    'execution_state' => 'blocked',
    'depends_on' => [],
], [
    'sequence' => 2,
    'operation_id' => 'op-archive',
    'phase' => 'archive',
    'operation_type' => 'archive_target',
    'owner_key' => 'Manufacturing/Products',
    'target_type' => 'owner',
    'path' => $targetPath,
    'severity' => 'required',
    'required' => 'yes',
    'blocking' => 'no',
    'execution_state' => 'planned',
    'depends_on' => ['op-remove-ref'],
], [
    'sequence' => 3,
    'operation_id' => 'op-delete',
    'phase' => 'delete',
    'operation_type' => 'delete_target',
    'owner_key' => 'Manufacturing/Products',
    'target_type' => 'owner',
    'path' => $targetPath,
    'severity' => 'required',
    'required' => 'yes',
    'blocking' => 'no',
    'execution_state' => 'planned',
    'depends_on' => ['op-remove-ref', 'op-archive'],
], [
    'sequence' => 4,
    'operation_id' => 'op-verify-absent',
    'phase' => 'verify',
    'operation_type' => 'verify_target_absent',
    'owner_key' => 'Manufacturing/Products',
    'target_type' => 'owner',
    'path' => $targetPath,
    'required' => 'yes',
    'depends_on' => ['op-delete'],
], [
    'sequence' => 5,
    'operation_id' => 'op-verify-refs',
    'phase' => 'verify',
    'operation_type' => 'verify_no_inbound_references',
    'owner_key' => 'Manufacturing/Products',
    'target_type' => 'owner',
    'path' => $targetPath,
    'required' => 'yes',
    'depends_on' => ['op-delete'],
], [
    'sequence' => 6,
    'operation_id' => 'op-verify-owner',
    'phase' => 'verify',
    'operation_type' => 'verify_owner_unregistered',
    'owner_key' => 'Manufacturing/Products',
    'target_type' => 'owner',
    'path' => $targetPath,
    'required' => 'yes',
    'depends_on' => ['op-delete'],
]];

$changeSet = [
    'status' => 'ok',
    'change_set_version' => 'studio.deletion-change-set.v1',
    'change_set_state' => 'ready_for_review',
    'immutable' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'requires_approval' => 'yes',
    'requires_snapshot' => 'yes',
    'target' => [
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'target_path' => $targetPath,
    ],
    'source_plan' => [
        'version' => 'studio.deletion-plan.v1',
        'state' => 'ready',
        'fingerprint' => 'deletion-plan:dry-run',
    ],
    'operation_manifest' => $operations,
    'snapshot_contract' => ['required' => 'yes'],
    'verification_contract' => ['checks' => [[
        'check_id' => 'verify-target-absent',
        'check_type' => 'verify_target_absent',
        'path' => $targetPath,
        'expected_evidence' => 'target_path_absent',
    ], [
        'check_id' => 'verify-no-refs',
        'check_type' => 'verify_no_inbound_references',
        'path' => $targetPath,
        'expected_evidence' => 'inbound_reference_count_zero',
    ], [
        'check_id' => 'verify-owner-gone',
        'check_type' => 'verify_owner_unregistered',
        'path' => $targetPath,
        'expected_evidence' => 'owner_resolution_not_found',
    ]]],
    'change_set_fingerprint' => 'deletion-change-set:dry-run',
];
$readiness = [
    'status' => 'ok',
    'effect' => 'verify',
    'readiness_version' => 'studio.deletion-execution-readiness.v1',
    'execution_readiness' => 'ready',
    'execution_eligible' => 'yes',
    'approval_valid' => 'yes',
    'can_execute' => 'no',
    'can_apply' => 'no',
    'grants_execution_authority' => 'no',
    'current_packet' => [
        'change_set_fingerprint' => 'deletion-change-set:dry-run',
        'plan_fingerprint' => 'deletion-plan:dry-run',
    ],
    'approval_evidence' => [
        'exact_packet_match' => 'yes',
        'decision' => 'approved',
        'record_id' => 'approval-dry-run-001',
        'record_fingerprint' => 'deletion-approval-record:dry-run',
    ],
    'readiness_fingerprint' => 'deletion-execution-readiness:dry-run',
];

$beforeFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $item) { if ($item->isFile()) { $beforeFiles[] = str_replace($root . '/', '', $item->getPathname()); } }
sort($beforeFiles);

$result = StudioDeletionExecutionDryRunService::simulate($changeSet, $readiness, $root);
$assert(($result['status'] ?? '') === 'ok', 'valid simulation returns ok');
$assert(($result['effect'] ?? '') === 'simulate', 'dry run declares simulate effect');
$assert(($result['dry_run_version'] ?? '') === StudioDeletionExecutionDryRunService::VERSION, 'dry-run version is explicit');
$assert(($result['dry_run_state'] ?? '') === 'ready', 'valid approved packet yields ready dry run');
$assert(($result['simulation_complete'] ?? '') === 'yes', 'operation sequence is fully simulated');
$assert(($result['execution_eligible_at_simulation'] ?? '') === 'yes', 'eligible state is preserved at simulation');
$assert(($result['would_execute'] ?? '') === 'no', 'dry run executes nothing');
$assert(($result['would_apply'] ?? '') === 'no', 'dry run applies nothing');
$assert(($result['would_write'] ?? '') === 'no', 'dry run writes nothing');
$assert(($result['would_archive'] ?? '') === 'no', 'dry run archives nothing');
$assert(($result['would_delete'] ?? '') === 'no', 'dry run deletes nothing');
$assert(($result['can_execute'] ?? '') === 'no', 'dry-run capability cannot execute');
$assert(($result['grants_execution_authority'] ?? '') === 'no', 'dry run grants no authority');
$assert(($result['source']['change_set_fingerprint'] ?? '') === 'deletion-change-set:dry-run', 'source change-set fingerprint is preserved');
$assert(($result['source']['readiness_fingerprint'] ?? '') === 'deletion-execution-readiness:dry-run', 'source readiness fingerprint is preserved');
$assert(($result['source']['approval_record_id'] ?? '') === 'approval-dry-run-001', 'source approval record is preserved');
$assert(($result['summary']['operation_count'] ?? 0) === 6, 'all operations are simulated');
$assert(($result['summary']['preflight_check_count'] ?? 0) === 2, 'preflight checks are counted');
$assert(($result['summary']['verification_command_count'] ?? 0) === 3, 'verification commands are counted');
$assert(($result['blocking_reasons'] ?? []) === [], 'ready dry run has no blockers');
$assert(str_starts_with((string)($result['dry_run_fingerprint'] ?? ''), 'deletion-execution-dry-run:'), 'dry-run fingerprint is emitted');
$assert(($result['preflight_checks'][0]['check_type'] ?? '') === 'target_exists', 'target existence is checked');
$assert(($result['preflight_checks'][0]['passed'] ?? '') === 'yes', 'existing target passes preflight');
$assert(($result['preflight_checks'][1]['check_type'] ?? '') === 'snapshot_destination_available', 'snapshot destination is checked');
$assert(($result['snapshot_plan']['would_create'] ?? '') === 'yes', 'snapshot creation is simulated');
$assert(($result['snapshot_plan']['would_write'] ?? '') === 'no', 'snapshot simulation writes nothing');
$assert(str_starts_with((string)($result['snapshot_plan']['destination_path'] ?? ''), 'storage/studio/deletion-execution-snapshots/'), 'snapshot destination is Studio confined');
$assert(($result['simulated_operations'][0]['simulation_action'] ?? '') === 'would_remove_blocking_reference', 'reference removal is simulated');
$assert(($result['simulated_operations'][1]['simulation_action'] ?? '') === 'would_archive_target', 'archive is simulated');
$assert(($result['simulated_operations'][2]['simulation_action'] ?? '') === 'would_delete_target', 'deletion is simulated');
$assert(($result['simulated_operations'][3]['simulation_action'] ?? '') === 'would_verify_target_absent', 'absence verification is simulated');
$assert(($result['simulated_operations'][4]['simulation_action'] ?? '') === 'would_rerun_reference_discovery', 'reference verification is simulated');
$assert(($result['simulated_operations'][5]['simulation_action'] ?? '') === 'would_rerun_owner_discovery', 'owner verification is simulated');
$assert(($result['simulated_operations'][2]['dependencies_satisfied'] ?? '') === 'yes', 'operation dependencies are ordered and satisfied');
$assert(($result['verification_commands'][0]['would_execute'] ?? '') === 'no', 'verification commands are not executed');
$assert(str_contains((string)($result['verification_commands'][1]['command'] ?? ''), 'studio_reference_discovery'), 'reference verification command is deterministic');
$assert(str_contains((string)($result['verification_commands'][2]['command'] ?? ''), 'studio_owner_discovery'), 'owner verification command is deterministic');

$afterFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $item) { if ($item->isFile()) { $afterFiles[] = str_replace($root . '/', '', $item->getPathname()); } }
sort($afterFiles);
$assert($afterFiles === $beforeFiles, 'dry run creates no files');
$assert(is_dir($root . '/' . $targetPath), 'dry run leaves target present');
$assert(is_file($root . '/' . $referencePath), 'dry run leaves reference file unchanged');

$stale = $readiness;
$stale['execution_readiness'] = 'stale';
$stale['execution_eligible'] = 'no';
$stale['approval_valid'] = 'no';
$staleResult = StudioDeletionExecutionDryRunService::simulate($changeSet, $stale, $root);
$assert(($staleResult['dry_run_state'] ?? '') === 'stale', 'stale readiness blocks simulation as stale');
$assert(($staleResult['simulation_complete'] ?? '') === 'no', 'stale readiness does not simulate operations');
$assert(in_array('READINESS_NOT_READY', $staleResult['blocking_reasons'] ?? [], true), 'stale readiness blocker is explicit');

$badDependency = $changeSet;
$badDependency['operation_manifest'][1]['depends_on'] = ['missing-operation'];
$blocked = StudioDeletionExecutionDryRunService::simulate($badDependency, $readiness, $root);
$assert(($blocked['dry_run_state'] ?? '') === 'blocked', 'missing dependency blocks dry run');
$assert(in_array('OPERATION_DEPENDENCY_MISSING:missing-operation', $blocked['blocking_reasons'] ?? [], true), 'missing dependency is explicit');

$unsupported = $changeSet;
$unsupported['operation_manifest'][0]['operation_type'] = 'launch_missiles';
$blocked = StudioDeletionExecutionDryRunService::simulate($unsupported, $readiness, $root);
$assert(($blocked['dry_run_state'] ?? '') === 'blocked', 'unsupported operation blocks dry run');
$assert(in_array('UNSUPPORTED_OPERATION:launch_missiles', $blocked['blocking_reasons'] ?? [], true), 'unsupported operation blocker is explicit');

@rename($root . '/' . $targetPath, $root . '/' . $targetPath . '-missing');
$missingTarget = StudioDeletionExecutionDryRunService::simulate($changeSet, $readiness, $root);
$assert(($missingTarget['dry_run_state'] ?? '') === 'blocked', 'missing target blocks dry run');
$assert(in_array('TARGET_EXISTS', $missingTarget['blocking_reasons'] ?? [], true), 'missing target blocker is explicit');
@rename($root . '/' . $targetPath . '-missing', $root . '/' . $targetPath);

$snapshotPath = (string)($result['snapshot_plan']['destination_path'] ?? '');
mkdir($root . '/' . $snapshotPath, 0777, true);
$snapshotConflict = StudioDeletionExecutionDryRunService::simulate($changeSet, $readiness, $root);
$assert(($snapshotConflict['dry_run_state'] ?? '') === 'blocked', 'existing snapshot destination blocks dry run');
$assert(in_array('SNAPSHOT_DESTINATION_AVAILABLE', $snapshotConflict['blocking_reasons'] ?? [], true), 'snapshot conflict blocker is explicit');

$unsafe = $changeSet;
$unsafe['target']['target_path'] = '../outside';
$unsafeResult = StudioDeletionExecutionDryRunService::simulate($unsafe, $readiness, $root);
$assert(($unsafeResult['dry_run_state'] ?? '') === 'blocked', 'unsafe target path fails closed');
$assert(in_array('PACKET_IDENTITY_MISSING', $unsafeResult['blocking_reasons'] ?? [], true), 'unsafe path produces identity blocker');

$source = file_get_contents(dirname(__DIR__) . '/Services/StudioDeletionExecutionDryRunService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'dry-run capability contains no mutation implementation');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($iterator as $item) { $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname()); }
@rmdir($root);

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
