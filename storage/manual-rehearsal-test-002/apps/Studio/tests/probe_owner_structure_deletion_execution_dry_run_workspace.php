<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionExecutionDryRunWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionDryRunWorkspaceService.php';

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
    'target' => ['owner_key' => 'Manufacturing/Products', 'target_path' => 'apps/Manufacturing/modules/Products'],
];
$readiness = [
    'execution_readiness' => 'ready',
    'readiness_fingerprint' => 'deletion-execution-readiness:workspace',
];
$dryRun = [
    'status' => 'ok',
    'effect' => 'simulate',
    'dry_run_state' => 'ready',
    'simulation_complete' => 'yes',
    'execution_eligible_at_simulation' => 'yes',
    'source' => ['change_set_fingerprint' => 'deletion-change-set:workspace'],
    'summary' => ['operation_count' => 4, 'preflight_check_count' => 2, 'verification_command_count' => 2, 'blocking_reason_count' => 0],
    'preflight_checks' => [['check_type' => 'target_exists', 'passed' => 'yes']],
    'snapshot_plan' => ['destination_path' => 'storage/studio/deletion-execution-snapshots/x'],
    'simulated_operations' => [['operation_id' => 'op-1', 'simulation_action' => 'would_archive_target']],
    'verification_commands' => [['check_id' => 'check-1', 'would_execute' => 'no']],
    'blocking_reasons' => [],
    'dry_run_fingerprint' => 'deletion-execution-dry-run:workspace',
    'diagnostics' => [],
];

$calls = 0;
$simulator = static function (array $owner, array $packet, array $ready, ?string $root) use (&$calls, $dryRun): array {
    $calls++;
    if (($owner['owner_key'] ?? '') !== 'Manufacturing/Products') { throw new RuntimeException('wrong owner'); }
    if (($packet['change_set_fingerprint'] ?? '') !== 'deletion-change-set:workspace') { throw new RuntimeException('wrong packet'); }
    if (($ready['execution_readiness'] ?? '') !== 'ready') { throw new RuntimeException('wrong readiness'); }
    return $dryRun;
};

$idle = OwnerStructureDeletionExecutionDryRunWorkspaceService::build($owners, 'Manufacturing/Products', false, $changeSet, $readiness, null, $simulator);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not simulate');
$assert(($idle['dry_run'] ?? null) === null, 'idle workspace has no dry run');
$assert($calls === 0, 'idle workspace invokes no simulator');

$ready = OwnerStructureDeletionExecutionDryRunWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, $readiness, null, $simulator);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($calls === 1, 'simulator invoked once');
$assert(($ready['dry_run_state'] ?? '') === 'ready', 'dry-run state is exposed');
$assert(($ready['simulation_complete'] ?? '') === 'yes', 'simulation completeness is exposed');
$assert(($ready['execution_eligible_at_simulation'] ?? '') === 'yes', 'eligibility at simulation is exposed');
$assert(($ready['source']['change_set_fingerprint'] ?? '') === 'deletion-change-set:workspace', 'source identity is exposed');
$assert(($ready['summary']['operation_count'] ?? 0) === 4, 'summary is exposed');
$assert(count($ready['preflight_checks'] ?? []) === 1, 'preflight checks are exposed');
$assert(($ready['snapshot_plan']['destination_path'] ?? '') !== '', 'snapshot plan is exposed');
$assert(count($ready['simulated_operations'] ?? []) === 1, 'simulated operations are exposed');
$assert(count($ready['verification_commands'] ?? []) === 1, 'verification commands are exposed');
$assert(($ready['blocking_reasons'] ?? []) === [], 'blocking reasons are exposed');
$assert(($ready['dry_run_fingerprint'] ?? '') === 'deletion-execution-dry-run:workspace', 'dry-run fingerprint is exposed');

$missingOwner = OwnerStructureDeletionExecutionDryRunWorkspaceService::build($owners, 'Missing/Owner', true, $changeSet, $readiness);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_DRY_RUN_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingPacket = OwnerStructureDeletionExecutionDryRunWorkspaceService::build($owners, 'Manufacturing/Products', true, null, $readiness);
$assert(($missingPacket['status'] ?? '') === 'error', 'missing change set fails safely');
$assert(($missingPacket['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_DRY_RUN_CHANGE_SET_REQUIRED', 'missing change-set diagnostic is explicit');

$missingReadiness = OwnerStructureDeletionExecutionDryRunWorkspaceService::build($owners, 'Manufacturing/Products', true, $changeSet, null);
$assert(($missingReadiness['status'] ?? '') === 'error', 'missing readiness fails safely');
$assert(($missingReadiness['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_DRY_RUN_READINESS_REQUIRED', 'missing readiness diagnostic is explicit');

$invalid = OwnerStructureDeletionExecutionDryRunWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $changeSet,
    $readiness,
    null,
    static fn(): string => 'invalid'
);
$assert(($invalid['status'] ?? '') === 'error', 'invalid simulator result fails safely');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_DRY_RUN_RESULT_INVALID', 'invalid result diagnostic is explicit');

$thrown = OwnerStructureDeletionExecutionDryRunWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $changeSet,
    $readiness,
    null,
    static function (): array { throw new RuntimeException('dry-run probe failure'); }
);
$assert(($thrown['status'] ?? '') === 'error', 'simulator exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_EXECUTION_DRY_RUN_FAILED', 'simulator exception diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'dry-run probe failure', 'simulator exception message is preserved');

$postludes = [
    'preview.postlude.zzz-deletion-approval.php',
    'preview.postlude.zzzz-deletion-execution-readiness.php',
    'preview.postlude.zzzzz-deletion-execution-dry-run.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes[2] === 'preview.postlude.zzzzz-deletion-execution-dry-run.php', 'dry-run postlude loads after readiness');

$workspaceSource = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionDryRunWorkspaceService.php');
$adapterSource = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionExecutionDryRunService.php');
$assert(is_string($workspaceSource) && !preg_match('/file_put_contents|fwrite|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|copy\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $workspaceSource), 'workspace presenter contains no mutation implementation');
$assert(is_string($adapterSource) && str_contains($adapterSource, 'StudioDeletionExecutionDryRunService::simulate'), 'adapter delegates canonical dry-run capability');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
