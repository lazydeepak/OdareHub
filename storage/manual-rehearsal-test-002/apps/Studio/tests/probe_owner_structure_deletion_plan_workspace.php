<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 3));

require_once APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionPlanWorkspaceService.php';

use Apps\Studio\Tools\OwnerStructureScan\Services\OwnerStructureDeletionPlanWorkspaceService;

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
    'owner_key' => 'Sales/Orders',
    'display_label' => 'Sales / Orders',
    'owner_type' => 'module',
    'owner_root_relative_path' => 'apps/Sales/modules/Orders',
]];
$impact = [
    'status' => 'ok',
    'target' => [
        'owner_key' => 'Sales/Orders',
        'target_type' => 'owner',
        'target_path' => 'apps/Sales/modules/Orders',
    ],
    'deletion_readiness' => 'blocked',
    'safe_to_delete' => 'no',
    'blocking_reasons' => ['RUNTIME_REFERENCES_EXIST'],
    'references' => [],
    'dependent_owners' => [],
    'diagnostics' => [],
];
$plan = [
    'status' => 'ok',
    'plan_state' => 'blocked',
    'summary' => ['operation_count' => 5],
    'operations' => [['operation_id' => 'op-1', 'operation_type' => 'archive_target']],
    'archive_scope' => [['path' => 'apps/Sales/modules/Orders']],
    'deletion_order' => ['op-1'],
    'post_deletion_checks' => [['check_id' => 'check-1', 'check_type' => 'verify_target_absent']],
    'blocking_reasons' => ['RUNTIME_REFERENCES_EXIST'],
    'plan_fingerprint' => 'deletion-plan:abc123',
    'diagnostics' => [],
];

$plannerCalls = 0;
$planner = static function (array $owner, array $options, ?array $impactAssessment, ?string $root) use (&$plannerCalls, $plan): array {
    $plannerCalls++;
    return $plan;
};

$idle = OwnerStructureDeletionPlanWorkspaceService::build($owners, 'Sales/Orders', false, null, null, $planner);
$assert(($idle['status'] ?? '') === 'idle', 'idle workspace does not plan');
$assert($plannerCalls === 0, 'idle workspace does not invoke planner');
$assert(($idle['plan_state'] ?? '') === 'not_planned', 'idle state is explicit');

$ready = OwnerStructureDeletionPlanWorkspaceService::build($owners, 'Sales/Orders', true, $impact, null, $planner);
$assert(($ready['status'] ?? '') === 'ready', 'requested workspace is ready');
$assert($plannerCalls === 1, 'requested workspace invokes planner once');
$assert(($ready['plan_state'] ?? '') === 'blocked', 'plan state is projected');
$assert(($ready['summary']['operation_count'] ?? 0) === 5, 'summary is projected');
$assert(count($ready['operations'] ?? []) === 1, 'operations are projected');
$assert(count($ready['archive_scope'] ?? []) === 1, 'archive scope is projected');
$assert(($ready['deletion_order'] ?? []) === ['op-1'], 'deletion order is projected');
$assert(count($ready['post_deletion_checks'] ?? []) === 1, 'post checks are projected');
$assert(($ready['blocking_reasons'] ?? []) === ['RUNTIME_REFERENCES_EXIST'], 'blocking reasons are projected');
$assert(($ready['plan_fingerprint'] ?? '') === 'deletion-plan:abc123', 'fingerprint is projected');

$missingImpact = OwnerStructureDeletionPlanWorkspaceService::build($owners, 'Sales/Orders', true, null, null, $planner);
$assert(($missingImpact['status'] ?? '') === 'error', 'plan fails closed without impact assessment');
$assert(($missingImpact['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_PLAN_IMPACT_REQUIRED', 'missing impact diagnostic is stable');
$assert($plannerCalls === 1, 'planner is not called without impact evidence');

$missingOwner = OwnerStructureDeletionPlanWorkspaceService::build($owners, 'Unknown/Owner', true, $impact, null, $planner);
$assert(($missingOwner['status'] ?? '') === 'error', 'missing owner fails closed');
$assert(($missingOwner['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_PLAN_OWNER_UNAVAILABLE', 'missing owner diagnostic is stable');

$invalid = OwnerStructureDeletionPlanWorkspaceService::build($owners, 'Sales/Orders', true, $impact, null, static fn(): string => 'invalid');
$assert(($invalid['status'] ?? '') === 'error', 'invalid planner result fails closed');
$assert(($invalid['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_PLAN_RESULT_INVALID', 'invalid result diagnostic is stable');

$thrown = OwnerStructureDeletionPlanWorkspaceService::build($owners, 'Sales/Orders', true, $impact, null, static function (): array {
    throw new RuntimeException('planned failure');
});
$assert(($thrown['status'] ?? '') === 'error', 'planner exception is contained');
$assert(($thrown['diagnostics'][0]['code'] ?? '') === 'OSS_DELETION_PLAN_WORKSPACE_FAILED', 'exception diagnostic is stable');
$assert(($thrown['diagnostics'][0]['message'] ?? '') === 'planned failure', 'exception message is preserved');

$workspaceSource = file_get_contents(APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionPlanWorkspaceService.php');
$adapterSource = file_get_contents(APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Services/OwnerStructureDeletionPlanService.php');
$viewSource = file_get_contents(APP_ROOT . '/apps/Studio/Tools/OwnerStructureScan/Views/preview.postlude.deletion-plan.php');
$assert(is_string($workspaceSource) && str_contains($workspaceSource, 'OwnerStructureDeletionPlanService::plan'), 'workspace delegates to adapter');
$assert(is_string($adapterSource) && str_contains($adapterSource, 'StudioDeletionPlanService::compose'), 'adapter reuses current impact assessment');
$assert(is_string($viewSource) && str_contains($viewSource, 'OwnerStructureDeletionPlanWorkspaceService::build'), 'view invokes workspace adapter');
$assert(is_string($viewSource) && str_contains($viewSource, 'name="deletion_plan"'), 'plan is explicitly on demand');
$assert(is_string($viewSource) && str_contains($viewSource, 'method="get"'), 'plan workspace is GET-only');
$assert(is_string($viewSource) && !preg_match('/<form[^>]+method="post"|file_put_contents|unlink\s*\(|rename\s*\(|OwnerStructureDeletionPlanService::plan|StudioDeletionPlanService::/i', $viewSource), 'view contains no mutation or domain-service bypass');
$assert(is_string($workspaceSource) && !preg_match('/file_put_contents|unlink\s*\(|rename\s*\(|mkdir\s*\(|rmdir\s*\(|PDO|INSERT\s+INTO|UPDATE\s+.+SET|DELETE\s+FROM/i', $workspaceSource), 'workspace service contains no mutation authority');

echo "\nResults: {$passes} passed, {$fails} failed\n";
exit($fails > 0 ? 1 : 0);
