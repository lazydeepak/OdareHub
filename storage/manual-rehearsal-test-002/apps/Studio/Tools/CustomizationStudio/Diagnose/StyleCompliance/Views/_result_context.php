<?php
declare(strict_types=1);

$totalFindings = (int)($summary['total_tokens'] ?? 0);
$scopeLabel = $scope === 'owner' ? $sourceScopeLabel('owner') : $sourceScopeLabel($scope);
$ownersScanned = isset($styleComplianceResult['owners_scanned']) ? (int)$styleComplianceResult['owners_scanned'] : 0;
$filesScanned = isset($styleComplianceResult['files_scanned']) ? (int)$styleComplianceResult['files_scanned'] : 0;
$scanResultOwnerKey = isset($styleComplianceResult['owner_key']) ? (string)$styleComplianceResult['owner_key'] : '';
$ownerLabel = $scanResultOwnerKey !== '' ? $scanResultOwnerKey : ($scope === 'all_owners' && $ownersScanned > 0 ? $ownersScanned . ' owners' : $sc('not_applicable'));
$isAllOwners = $scope === 'all_owners' && $ownersScanned > 0;
$executionCapability = isset($executionCapability) && is_array($executionCapability) ? $executionCapability : [];
$guardedExecutionEnabled = !empty($executionCapability['executor_enabled']);
?>
<section class="sc-workspace-context" aria-labelledby="scWorkspaceContextTitle">
    <div>
        <h3 id="scWorkspaceContextTitle"><?= e($sc('workspace_context_title')) ?></h3>
        <p><?= e($sc($guardedExecutionEnabled ? 'workspace_context_guarded' : 'workspace_context_readonly')) ?></p>
    </div>
    <dl class="sc-context-metrics">
        <div>
            <dt><?= e($sc('context_current_scope')) ?></dt>
            <dd><?= e($scopeLabel) ?></dd>
        </div>
        <?php if ($isAllOwners): ?>
        <div>
            <dt><?= e($sc('context_owners_scanned')) ?></dt>
            <dd><?= (int)$ownersScanned ?></dd>
        </div>
        <?php if ($filesScanned > 0): ?>
        <div>
            <dt><?= e($sc('context_files_scanned')) ?></dt>
            <dd><?= (int)$filesScanned ?></dd>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div>
            <dt><?= e($sc('context_current_owner')) ?></dt>
            <dd><?= e($ownerLabel) ?></dd>
        </div>
        <?php endif; ?>
        <div>
            <dt><?= e($sc('context_total_findings')) ?></dt>
            <dd><?= (int)$totalFindings ?></dd>
        </div>
        <div>
            <dt><?= e($sc('workspace_readonly_status')) ?></dt>
            <dd><?= e($sc($guardedExecutionEnabled ? 'workspace_guarded_value' : 'workspace_readonly_value')) ?></dd>
        </div>
    </dl>
</section>
