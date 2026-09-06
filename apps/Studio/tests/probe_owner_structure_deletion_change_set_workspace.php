<?php
declare(strict_types=1);

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionChangeSetWorkspaceService;

require_once dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionChangeSetWorkspaceService.php';

$passes = 0;
$fails = 0;
$assert = static function (bool $condition, string $message) use (&$passes, &$fails): void {
    if ($condition) {
        $passes++;
        echo "PASS: {$message}\n";
        return;
    }
    $fails++;
    echo "FAIL: {$message}\n";
};

$owners = [[
    'owner_key' => 'Manufacturing/Products',
    'owner_type' => 'module',
    'root_path' => 'apps/Manufacturing/modules/Products',
]];
$plan = [
    'plan_version' => 'studio.deletion-plan.v1',
    'plan_state' => 'ready',
    'plan_fingerprint' => 'deletion-plan:workspace',
    'can_execute' => 'no',
    'target' => [
        'owner_key' => 'Manufacturing/Products',
        'target_type' => 'owner',
        'target_path' => 'apps/Manufacturing/modules/Products',
    ],
];
$changeSet = [
    'status' => 'ok',
    'change_set_state' => 'ready_for_review',
    'summary' => ['operation_count' => 6, 'file_change_count' => 2],
    'source_plan' => ['fingerprint' => 'deletion-plan:workspace'],
    'operation_manifest' => [['operation_id' => 'op-1']],
    'proposed_changes' => [
        'file_changes' => [['change_id' => 'file-1']],
        'archive_changes' => [['change_id' => 'archive-1']],
        'delete_changes' => [['change_id' => 'delete-1']],
    ],
    'approval_contract' => ['approval_readiness' => 'ready'],
    'snapshot_contract' => ['required' => 'yes'],
    'verification_contract' => ['checks' => [['check_id' => 'check-1']]],
    'blocking_reasons' => [],
    'change_set_fingerprint' => 'deletion-change-set:workspace',
    'diagnostics' => [],
];

$builderCalls = 0;
$builder = static function (array $owner, array $options, ?array $incomingPlan, ?string $root) use (&$builderCalls, $changeSet): array {
    $builderCalls++;
    if (($owner['owner_key'] ?? '') !== 'Manufacturing/Products') {
        throw new RuntimeException('wrong owner');
    }
    if (($incomingPlan['plan_fingerprint'] ?? '') !== 'deletion-plan:workspace') {
        throw new RuntimeException('wrong plan');
    }
    return $changeSet;
};

$idle = OwnerStructureDeletionChangeSetWorkspaceService::build($owners, 'Manufacturing/Products', false, $plan, null, $builder);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not build packet');
$assert(($idle['change_set'] ?? null) === null, 'idle workspace has no packet');
$assert($builderCalls === 0, 'idle workspace does not invoke builder');

$ready = OwnerStructureDeletionChangeSetWorkspaceService::build($owners, 'Manufacturing/Products', true, $plan, null, $builder);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($builderCalls === 1, 'requested workspace invokes builder once');
$assert(($ready['change_set_state'] ?? '') === 'ready_for_review', 'change-set state is exposed');
$assert(($ready['summary']['operation_count'] ?? 0) === 6, 'summary is exposed');
$assert(($ready['source_plan']['fingerprint'] ?? '') === 'deletion-plan:workspace', 'source plan is exposed');
$assert(count($ready['operation_manifest'] ?? []) === 1, 'operation manifest is exposed');
$assert(count($ready['proposed_changes']['file_changes'] ?? []) === 1, 'file changes are exposed');
$assert(count($ready['proposed_changes']['archive_changes'] ?? []) === 1, 'archive changes are exposed');
$assert(count($ready['proposed_changes']['delete_changes'] ?? []) === 1, 'delete changes are exposed');
$assert(($ready['approval_contract']['approval_readiness'] ?? '') === 'ready', 'approval contract is exposed');
$assert(($ready['snapshot_contract']['required'] ?? '') === 'yes', 'snapshot contract is exposed');
$assert(count($ready['verification_contract']['checks'] ?? []) === 1, 'verification contract is exposed');
$assert(($ready['change_set_fingerprint'] ?? '') === 'deletion-change-set:workspace', 'change-set fingerprint is exposed');

$missingOwner = OwnerStructureDeletionChangeSetWorkspaceService::build($owners, 'Missing/Owner', true, $plan, null, $builder);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails safely');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_CHANGE_SET_OWNER_UNAVAILABLE', 'missing owner diagnostic is explicit');

$missingPlan = OwnerStructureDeletionChangeSetWorkspaceService::build($owners, 'Manufacturing/Products', true, null, null, $builder);
$assert(($missingPlan['status'] ?? '') === 'error', 'missing plan fails safely');
$assert(($missingPlan['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_CHANGE_SET_PLAN_REQUIRED', 'missing plan diagnostic is explicit');

$invalid = OwnerStructureDeletionChangeSetWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $plan,
    null,
    static fn(): string => 'invalid'
);
$assert(($invalid['status'] ?? '') === 'error', 'invalid capability result fails safely');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_CHANGE_SET_RESULT_INVALID', 'invalid result diagnostic is explicit');

$thrown = OwnerStructureDeletionChangeSetWorkspaceService::build(
    $owners,
    'Manufacturing/Products',
    true,
    $plan,
    null,
    static function (): array {
        throw new RuntimeException('probe failure');
    }
);
$assert(($thrown['status'] ?? '') === 'error', 'capability exception fails safely');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_CHANGE_SET_WORKSPACE_FAILED', 'exception diagnostic is explicit');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'probe failure', 'exception message is preserved');

$postludes = [
    'preview.postlude.deletion-plan.php',
    'preview.postlude.zz-deletion-change-set.php',
];
sort($postludes, SORT_NATURAL | SORT_FLAG_CASE);
$assert($postludes === ['preview.postlude.deletion-plan.php', 'preview.postlude.zz-deletion-change-set.php'], 'change-set postlude loads after deletion plan');

$source = file_get_contents(dirname(__DIR__) . '/Tools/OwnerStructureScan/Services/OwnerStructureDeletionChangeSetWorkspaceService.php');
$assert(is_string($source) && !preg_match('/file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $source), 'workspace contains no mutation authority');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
