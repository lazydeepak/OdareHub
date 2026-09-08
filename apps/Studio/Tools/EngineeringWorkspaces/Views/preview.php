<?php
declare(strict_types=1);
/* @var array $engineeringWorkspaceHubModel */

$hub = isset($engineeringWorkspaceHubModel) && is_array($engineeringWorkspaceHubModel) ? $engineeringWorkspaceHubModel : [];
$activeTab = (string)($hub['active_tab'] ?? 'workspaces');
$activeTab = in_array($activeTab, ['templates', 'provision', 'deploy', 'coverage-assignment', 'agent-context'], true) ? $activeTab : 'workspaces';
$provisionModel = isset($hub['provision_model']) && is_array($hub['provision_model']) ? $hub['provision_model'] : [];
$provisionPlan = isset($hub['provision_plan']) && is_array($hub['provision_plan']) ? $hub['provision_plan'] : null;
$provisionPreview = isset($hub['provision_preview']) && is_array($hub['provision_preview']) ? $hub['provision_preview'] : null;
$provisionResult = isset($hub['provision_result']) && is_array($hub['provision_result']) ? $hub['provision_result'] : null;
if ($provisionResult === null && isset($_SESSION['studio_provision_result'])) {
    $provisionResult = $_SESSION['studio_provision_result'];
    unset($_SESSION['studio_provision_result']);
}
$provisionFlash = (string)($hub['provision_flash'] ?? '');

$deployModel = isset($hub['deploy_model']) && is_array($hub['deploy_model']) ? $hub['deploy_model'] : [];
$deployWorkspaces = $deployModel['workspaces'] ?? [];
$templateRows = isset($hub['templates']) && is_array($hub['templates']) ? $hub['templates'] : [];
$coverage = isset($hub['coverage']) && is_array($hub['coverage']) ? $hub['coverage'] : [];
$ownerSummary = isset($coverage['owner_summary']) && is_array($coverage['owner_summary']) ? $coverage['owner_summary'] : [];
$workspaceSummary = isset($coverage['workspace_summary']) && is_array($coverage['workspace_summary']) ? $coverage['workspace_summary'] : [];
$coverageRows = isset($coverage['rows']) && is_array($coverage['rows']) ? $coverage['rows'] : [];
$catalogueState = (string)($coverage['catalogue_state'] ?? 'ok');
$scanState = (string)($coverage['scan_state'] ?? 'ok');
$assignmentRows = isset($hub['assignment_rows']) && is_array($hub['assignment_rows']) ? $hub['assignment_rows'] : [];
$existingWorkspaces = isset($hub['existing_workspaces']) && is_array($hub['existing_workspaces']) ? $hub['existing_workspaces'] : [];
$assignmentFlash = (string)($hub['assignment_flash'] ?? '');
$agentContextExistingWs = isset($hub['agent_context_existing_workspaces']) && is_array($hub['agent_context_existing_workspaces']) ? $hub['agent_context_existing_workspaces'] : [];
$agentContextRequest = isset($hub['agent_context_request']) && is_array($hub['agent_context_request']) ? $hub['agent_context_request'] : null;
$agentContextResult = isset($hub['agent_context_result']) && is_array($hub['agent_context_result']) ? $hub['agent_context_result'] : null;

$evidenceHumanLabel = static function (string $evidenceType): string {
    return match ($evidenceType) {
        'exact_owner_mapping' => 'Exact owner mapping',
        'registered_workspace' => 'Registered workspace',
        'discovered_workspace_directory' => 'Discovered workspace directory',
        'canonical_owner_catalogue' => 'Canonical owner catalogue',
        default => $evidenceType,
    };
};
$stateHumanLabel = static function (string $state): string {
    return match ($state) {
        'linked_valid' => 'Linked valid',
        'initialization_required' => 'Initialization required',
        'contract_repair_required' => 'Contract repair required',
        'mapping_review_required' => 'Mapping review required',
        'resolution_failed' => 'Resolution failed',
        'unregistered_workspace_discovered' => 'Unregistered workspace discovered',
        'coverage_decision_required' => 'Coverage decision required',
        default => $state,
    };
};

$tabUrl = static function (string $tab): string {
    if ($tab === 'workspaces') {
        return '/apps/studio/tools/engineering-workspaces';
    }
    return '/apps/studio/tools/engineering-workspaces?tab=' . rawurlencode($tab);
};
?>
<style>
.ewh-shell { max-width: 1260px; margin: 0 auto; padding: 24px; color: #1f2937; }
.ewh-hero { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; margin-bottom: 18px; }
.ewh-hero h1 { font-size: 28px; line-height: 1.2; margin: 0 0 8px; color: #111827; }
.ewh-hero p { margin: 0; color: #4b5563; max-width: 760px; }
.ewh-badge { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 10px; border-radius: 6px; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 700; white-space: nowrap; }
.ewh-tabs { display: flex; gap: 8px; border-bottom: 1px solid #d1d5db; margin: 0 0 20px; }
.ewh-tabs a { display: inline-flex; align-items: center; min-height: 40px; padding: 0 14px; color: #4b5563; text-decoration: none; border-bottom: 3px solid transparent; font-weight: 700; }
.ewh-tabs a[aria-current="page"] { color: #1d4ed8; border-bottom-color: #1d4ed8; }
.ewh-section-head { margin-bottom: 16px; }
.ewh-section-head h2 { margin: 0 0 6px; font-size: 22px; color: #111827; }
.ewh-section-head p { margin: 0; color: #4b5563; }
.ewh-summary-group { margin-bottom: 24px; }
.ewh-summary-group h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 0 0 10px; }
.ewh-summary-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.ewh-summary-item { display: flex; flex-direction: column; gap: 2px; min-width: 100px; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
.ewh-summary-num { font-size: 22px; font-weight: 800; color: #111827; line-height: 1.1; }
.ewh-summary-label { font-size: 11px; color: #6b7280; line-height: 1.3; }
.ewh-summary-linked_valid .ewh-summary-num { color: #166534; }
.ewh-summary-initialization_required .ewh-summary-num, .ewh-summary-coverage_decision_required .ewh-summary-num { color: #92400e; }
.ewh-summary-contract_repair_required .ewh-summary-num, .ewh-summary-resolution_failed .ewh-summary-num { color: #991b1b; }
.ewh-summary-unregistered_workspace_discovered .ewh-summary-num, .ewh-summary-mapping_review_required .ewh-summary-num { color: #6b21a8; }
.ewh-workspace-key { font-size: 13px; color: #4b5563; }
.ewh-table-wrap { overflow-x: auto; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; }
.ewh-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.ewh-table th, .ewh-table td { padding: 14px 16px; border-bottom: 1px solid #e5e7eb; vertical-align: top; text-align: left; }
.ewh-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; background: #f9fafb; }
.ewh-table tr:last-child td { border-bottom: 0; }
.ewh-workspace-name { font-weight: 800; color: #111827; }
.ewh-context-label { font-weight: 800; color: #111827; }
.ewh-evidence-type { font-size: 12px; color: #6b7280; }
.ewh-dash { color: #9ca3af; }
.ewh-doc-list { display: flex; gap: 8px; flex-wrap: wrap; }
.ewh-doc { display: inline-flex; align-items: center; min-height: 30px; padding: 4px 9px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; font-weight: 700; color: #374151; background: #fff; text-decoration: none; }
a.ewh-doc:hover { border-color: #1d4ed8; color: #1d4ed8; }
.ewh-doc-missing { color: #92400e; border-color: #fde68a; background: #fffbeb; }
.ewh-doc-repair { color: #991b1b; border-color: #fecaca; background: #fef2f2; }
.ewh-doc-unavailable { color: #4b5563; border-color: #d1d5db; background: #f3f4f6; }
.ewh-status { display: inline-flex; align-items: center; min-height: 28px; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 800; white-space: nowrap; }
.ewh-status-linked_valid { background: #dcfce7; color: #166534; }
.ewh-status-initialization_required { background: #fffbeb; color: #92400e; }
.ewh-status-contract_repair_required { background: #fef2f2; color: #991b1b; }
.ewh-status-mapping_review_required { background: #f3e8ff; color: #6b21a8; }
.ewh-status-resolution_failed { background: #fef2f2; color: #991b1b; }
.ewh-status-unregistered_workspace_discovered { background: #f3e8ff; color: #6b21a8; }
.ewh-status-coverage_decision_required { background: #fffbeb; color: #92400e; }
.ewh-readiness { font-size: 12px; color: #4b5563; font-weight: 600; }
.ewh-empty { padding: 20px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; color: #6b7280; }
.ewh-message { color: #6b7280; font-size: 12px; margin-top: 6px; }
.ewh-alert-banner { padding: 8px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 12px; }
.ewh-alert-warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; }
.ewh-template-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
.ewh-template { border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; overflow: hidden; }
.ewh-template-head { padding: 14px 16px; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.ewh-template-head h3 { margin: 0 0 8px; font-size: 16px; color: #111827; }
.ewh-template-meta { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.ewh-template-body { padding: 16px; }
.ewh-template-body .markdown-body { font-size: 13px; line-height: 1.55; }
.ewh-template-body .markdown-body h1 { font-size: 18px; margin: 0 0 12px; padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; }
.ewh-template-body .markdown-body h2 { font-size: 15px; margin: 14px 0 8px; }
.ewh-template-body .markdown-body p, .ewh-template-body .markdown-body ul { margin: 8px 0; }
.ewh-provision-intro { margin-bottom: 16px; }
.ewh-provision-count { font-size: 13px; font-weight: 600; color: #4b5563; }
.ewh-provision-flash { padding: 10px 14px; border-radius: 6px; background: #eef2ff; border: 1px solid #c7d2fe; color: #3730a3; font-size: 13px; margin-bottom: 16px; }
.ewh-provision-form { margin-top: 12px; }
.ewh-provision-inline-form { display: inline-flex; flex-direction: column; gap: 8px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; background: #f9fafb; }
.ewh-provision-doc-select { border: none; padding: 0; margin: 0; }
.ewh-provision-doc-select legend { font-size: 12px; font-weight: 600; color: #4b5563; margin-bottom: 6px; }
.ewh-checkbox { display: flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; cursor: pointer; }
.ewh-checkbox input[type=checkbox] { accent-color: #1d4ed8; }
.ewh-btn { display: inline-flex; align-items: center; min-height: 36px; padding: 0 16px; border: none; border-radius: 6px; background: #1d4ed8; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
.ewh-btn:hover { opacity: .9; }
.ewh-btn-sm { min-height: 30px; padding: 0 12px; font-size: 12px; }
.ewh-btn-warn { background: #b91c1c; }
.ewh-btn-warn:hover { background: #991b1b; }
.ewh-input { width: 100%; max-width: 400px; min-height: 36px; padding: 6px 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 13px; color: #1f2937; }
.ewh-provision-plan { padding: 16px; border: 1px solid #c7d2fe; border-radius: 8px; background: #eef2ff; margin-bottom: 16px; }
.ewh-provision-plan-meta { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 12px; }
.ewh-provision-doc-plan { padding: 12px; margin-bottom: 10px; border: 1px solid #e5e7eb; border-radius: 6px; background: #fff; }
.ewh-provision-doc-plan h4 { margin: 0 0 8px; font-size: 14px; color: #111827; }
.ewh-provision-diff { margin-top: 8px; }
.ewh-provision-diff summary { font-size: 12px; font-weight: 600; color: #1d4ed8; cursor: pointer; }
.ewh-provision-content-preview { padding: 12px; margin-top: 8px; background: #f3f4f6; border-radius: 6px; font-family: monospace; font-size: 12px; line-height: 1.5; white-space: pre-wrap; color: #1f2937; max-height: 400px; overflow-y: auto; }
.ewh-provision-confirm { margin-bottom: 12px; }
.ewh-provision-warning { font-size: 13px; color: #b91c1c; margin: 0 0 8px; }
.ewh-provision-workspace { padding: 16px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fff; margin-bottom: 12px; }
.ewh-provision-ws-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.ewh-provision-ws-head h3 { margin: 0; font-size: 16px; color: #111827; }
.ewh-provision-ws-docs { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.ewh-provision-actions { display: flex; gap: 12px; flex-wrap: wrap; }
.ewh-provision-last-tx { margin-top: 10px; }
.ewh-provision-last-tx summary { font-size: 12px; color: #6b7280; cursor: pointer; }
.ewh-provision-result { padding: 16px; border-radius: 8px; margin-bottom: 16px; }
.ewh-provision-result h3 { margin: 0 0 8px; font-size: 16px; }
.ewh-provision-result-ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.ewh-provision-result-fail { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }

.ewh-ca-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-start; }
.ewh-ca-field { flex: 1 1 160px; min-width: 140px; }
.ewh-ca-select { width: 100%; min-height: 32px; padding: 4px 8px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; color: #1f2937; background: #fff; }
.ewh-ca-ws-field { display: none; }
.ewh-ca-ws-field.ewh-ca-ws-visible { display: block; }
.ewh-ca-action { flex: 0 0 auto; }

@media (max-width: 720px) {
  .ewh-shell { padding: 16px; }
  .ewh-hero { flex-direction: column; }
  .ewh-tabs { overflow-x: auto; }
}
</style>

<section class="ewh-shell">
  <header class="ewh-hero">
    <div>
      <h1>Engineering Workspaces</h1>
      <p>Studio home for governing registered Engineering Workspaces, their document health, and approved starter templates.</p>
    </div>
    <span class="ewh-badge">V1.3</span>
  </header>

  <nav class="ewh-tabs" aria-label="Engineering Workspace Hub sections">
    <a href="<?= e($tabUrl('workspaces')) ?>" <?= $activeTab === 'workspaces' ? 'aria-current="page"' : '' ?>>Workspaces</a>
    <a href="<?= e($tabUrl('templates')) ?>" <?= $activeTab === 'templates' ? 'aria-current="page"' : '' ?>>Templates</a>
    <a href="<?= e($tabUrl('deploy')) ?>" <?= $activeTab === 'deploy' ? 'aria-current="page"' : '' ?>>Deploy Templates</a>
    <a href="<?= e($tabUrl('coverage-assignment')) ?>" <?= $activeTab === 'coverage-assignment' ? 'aria-current="page"' : '' ?>>Coverage Assignment</a>
    <a href="<?= e($tabUrl('agent-context')) ?>" <?= $activeTab === 'agent-context' ? 'aria-current="page"' : '' ?>>Agent Context</a>
  </nav>

  <?php if ($activeTab === 'templates'): ?>
    <section aria-labelledby="ewh-templates-title">
      <div class="ewh-section-head">
        <h2 id="ewh-templates-title">Workspace Templates</h2>
        <p>Inspect the approved starter templates used when a new Engineering Workspace document is initialized.</p>
      </div>

      <?php if ($templateRows === []): ?>
        <div class="ewh-empty">Template unavailable.</div>
      <?php else: ?>
        <div class="ewh-template-grid">
          <?php foreach ($templateRows as $template): ?>
            <?php
              $sourceStatus = (string)($template['source_status'] ?? 'unavailable');
              $renderedPreview = (string)($template['rendered_preview'] ?? '');
            ?>
            <article class="ewh-template">
              <div class="ewh-template-head">
                <h3><?= e((string)($template['filename'] ?? 'template.md')) ?></h3>
                <div class="ewh-template-meta">
                  <span class="ewh-doc"><?= e((string)($template['document_label'] ?? 'Document')) ?></span>
                  <span class="ewh-status ewh-status-<?= e($sourceStatus) ?>"><?= e((string)($template['source_status_label'] ?? 'Unavailable')) ?></span>
                  <span class="ewh-doc"><?= e((string)($template['heading_compatibility'] ?? 'Unavailable')) ?></span>
                </div>
              </div>
              <div class="ewh-template-body">
                <?php if ($renderedPreview !== ''): ?>
                  <div class="markdown-body"><?= $renderedPreview ?></div>
                <?php else: ?>
                  <div class="ewh-empty"><?= e((string)($template['message'] ?? 'Template unavailable.')) ?></div>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($activeTab === 'provision'): ?>
    <section aria-labelledby="ewh-provision-title">
      <div class="ewh-section-head">
        <h2 id="ewh-provision-title">Workspace Provisioning</h2>
        <p>Create missing governed documents from templates or explicitly archive and reset selected documents after preview and confirmation.</p>
      </div>

      <?php if (empty($provisionModel['eligible_rows'])): ?>
        <div class="ewh-empty">No eligible workspaces found. Only Linked valid and Initialization required rows may enter provisioning.</div>
      <?php else: ?>
        <?php $csrf = \App\Core\Auth::csrfToken(); ?>
        <div class="ewh-provision-intro">
          <span class="ewh-provision-count"><?= (int)$provisionModel['eligible_count'] ?> eligible workspace(s)</span>
        </div>

        <?php if ($provisionFlash !== ''): ?>
          <div class="ewh-provision-flash"><?= e($provisionFlash) ?></div>
        <?php endif; ?>

        <?php if ($provisionPlan !== null && $provisionPreview !== null): ?>
          <?php
            $wp = $provisionPlan['workspace_key'] ?? '';
            $planLane = $provisionPlan['lane'] ?? '';
            $previews = isset($provisionPreview['previews']) && is_array($provisionPreview['previews']) ? $provisionPreview['previews'] : [];
            $allValidated = !empty($provisionPreview['all_validated']);
            $expiresIn = (int)($provisionPreview['expires_in'] ?? 0);
          ?>
          <div class="ewh-provision-plan">
            <div class="ewh-section-head">
              <h3>Provisioning plan for <?= e($wp) ?></h3>
              <p>Lane: <?= e($planLane === 'initialize_missing' ? 'Initialize missing documents' : 'Archive and reset from template') ?>. Plan expires in <?= $expiresIn ?>s.</p>
            </div>

            <div class="ewh-provision-plan-meta">
              <span class="ewh-doc">Plan: <?= e($provisionPlan['plan_id'] ?? '') ?></span>
              <span class="ewh-doc"><?= count($previews) ?> document(s)</span>
              <span class="ewh-status <?= $allValidated ? 'ewh-status-linked_valid' : 'ewh-status-contract_repair_required' ?>">
                <?= $allValidated ? 'Content contract validated' : 'Content contract validation failed' ?>
              </span>
            </div>

            <?php foreach ($previews as $pv): ?>
              <?php
                $pvDoc = $pv['document_key'] ?? '';
                $pvOp = $pv['operation'] ?? '';
                $pvState = $pv['current_state'] ?? '';
                $pvContent = $pv['rendered_content'] ?? '';
              ?>
              <div class="ewh-provision-doc-plan">
                <h4><?= e(ucfirst($pvDoc)) ?> — <?= e($pvOp === 'CREATE_WORKSPACE_DOCUMENT_FROM_TEMPLATE' ? 'Initialize from template' : 'Archive and reset from template') ?></h4>
                <div class="ewh-provision-plan-meta">
                  <span class="ewh-doc">Current state: <?= e($pvState) ?></span>
                  <span class="ewh-doc">Operation: <?= e($pvOp) ?></span>
                  <?php if ($pvOp === 'ARCHIVE_AND_RESET'): ?>
                    <span class="ewh-doc ewh-doc-repair">Destructive action: current version will be archived before replacement</span>
                  <?php endif; ?>
                </div>
                <details class="ewh-provision-diff">
                  <summary>View rendered content</summary>
                  <div class="ewh-provision-content-preview"><?= e($pvContent) ?></div>
                </details>
              </div>
            <?php endforeach; ?>

            <?php if (!$allValidated): ?>
              <div class="ewh-alert-banner ewh-alert-warn">One or more generated documents failed the content contract validation. Apply is not available.</div>
            <?php else: ?>
              <form method="POST" action="/apps/studio/tools/engineering-workspaces/provision/apply" class="ewh-provision-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="workspace_key" value="<?= e($wp) ?>">
                <?php if ($planLane === 'archive_and_reset'): ?>
                  <div class="ewh-provision-confirm">
                    <p class="ewh-provision-warning">Type <strong>RESET <?= e($wp) ?></strong> to confirm archive and reset:</p>
                    <input type="text" name="reset_confirmation" class="ewh-input" placeholder="RESET <?= e($wp) ?>" required>
                  </div>
                <?php endif; ?>
                <button type="submit" class="ewh-btn"><?= $planLane === 'initialize_missing' ? 'Apply initialization' : 'Apply archive and reset' ?></button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php $txResult = isset($hub['provision_result']) && is_array($hub['provision_result']) ? $hub['provision_result'] : null; ?>
        <?php if ($txResult !== null): ?>
          <?php if (!empty($txResult['ok'])): ?>
            <div class="ewh-provision-result ewh-provision-result-ok">
              <h3>Transaction completed</h3>
              <div class="ewh-provision-plan-meta">
                <span class="ewh-doc">Transaction: <?= e($txResult['transaction_id'] ?? '') ?></span>
                <span class="ewh-doc">Workspace: <?= e($txResult['workspace_key'] ?? '') ?></span>
                <span class="ewh-doc">Timestamp: <?= e((string)($txResult['datetime'] ?? '')) ?></span>
              </div>
              <?php $txOps = isset($txResult['operations']) && is_array($txResult['operations']) ? $txResult['operations'] : []; ?>
              <?php if ($txOps !== []): ?>
                <table class="ewh-table">
                  <thead>
                    <tr>
                      <th>Document</th>
                      <th>Operation</th>
                      <th>Before</th>
                      <th>After</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($txOps as $txOp): ?>
                      <tr>
                        <td><?= e($txOp['document_key'] ?? '') ?></td>
                        <td><?= e($txOp['operation'] ?? '') ?></td>
                        <td><code><?= e(substr((string)($txOp['before_hash'] ?? '—'), 0, 12)) ?></code></td>
                        <td><code><?= e(substr((string)($txOp['after_hash'] ?? '—'), 0, 12)) ?></code></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
              <?php if (!empty($txResult['rollback_possible'])): ?>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/provision/rollback" class="ewh-provision-form">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($txResult['workspace_key'] ?? '') ?>">
                  <button type="submit" class="ewh-btn ewh-btn-warn">Rollback this transaction</button>
                </form>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="ewh-provision-result ewh-provision-result-fail">
              <h3>Transaction failed</h3>
              <p><?= e($txResult['error'] ?? 'Unknown error') ?></p>
              <?php if (!empty($txResult['rollback_completed'])): ?>
                <p class="ewh-message">All changes were rolled back.</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($provisionModel['eligible_rows'] as $er): ?>
          <?php
            $erWs = $er['workspace_key'] ?? '';
            $erState = $er['state'] ?? '';
            $erDocs = isset($er['documents']) && is_array($er['documents']) ? $er['documents'] : [];
            $erLanes = isset($er['lane_actions']) && is_array($er['lane_actions']) ? $er['lane_actions'] : [];
            $hasMissing = !empty($erLanes['can_initialize']);
            $hasValid = !empty($erLanes['can_archive_reset']);

            $readinessLabel = static function (string $r): string {
                return match ($r) {
                    'preserve' => 'Preserve',
                    'initialize_from_template' => 'Initialize from template',
                    default => $r,
                };
            };
          ?>
          <article class="ewh-provision-workspace">
            <div class="ewh-provision-ws-head">
              <h3><?= e($er['context_label'] ?? $erWs) ?></h3>
              <span class="ewh-status ewh-status-<?= e($erState) ?>"><?= e($stateHumanLabel($erState)) ?></span>
            </div>

            <div class="ewh-table-wrap" style="margin-bottom:12px">
              <table class="ewh-table">
                <thead>
                  <tr>
                    <th>Document</th>
                    <th>State</th>
                    <th>Readiness</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($erDocs as $docKey => $docInfo): ?>
                    <?php
                      $docState = $docInfo['state'] ?? 'missing';
                      $docReadiness = $docInfo['provisioning_readiness'] ?? 'investigate';
                    ?>
                    <tr>
                      <td><strong><?= e(ucfirst($docKey)) ?></strong></td>
                      <td>
                        <span class="ewh-doc <?= $docState === 'missing' ? 'ewh-doc-missing' : ($docState === 'valid' ? '' : 'ewh-doc-repair') ?>">
                          <?= e($docState === 'valid' ? 'Valid' : ($docState === 'missing' ? 'Missing' : 'Needs repair')) ?>
                        </span>
                      </td>
                      <td><span class="ewh-readiness"><?= e($readinessLabel($docReadiness)) ?></span></td>
                      <td>
                        <?php if ($docState === 'missing'): ?>
                          <span class="ewh-message">Initialize from template</span>
                        <?php elseif ($docState === 'valid'): ?>
                          <span class="ewh-message">Preserve</span>
                        <?php else: ?>
                          <span class="ewh-dash">—</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="ewh-provision-actions">
              <?php if ($hasMissing): ?>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/provision/preview" class="ewh-provision-inline-form">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($erWs) ?>">
                  <input type="hidden" name="lane" value="initialize_missing">
                  <fieldset class="ewh-provision-doc-select">
                    <legend>Initialize missing documents:</legend>
                    <?php foreach ($erDocs as $docKey => $docInfo): ?>
                      <?php if (($docInfo['state'] ?? '') === 'missing'): ?>
                        <label class="ewh-checkbox">
                          <input type="checkbox" name="documents[]" value="<?= e($docKey) ?>" checked>
                          <?= e(ucfirst($docKey)) ?>
                        </label>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </fieldset>
                  <button type="submit" class="ewh-btn ewh-btn-sm">Preview initialize</button>
                </form>
              <?php endif; ?>

              <?php if ($hasValid): ?>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/provision/preview" class="ewh-provision-inline-form">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($erWs) ?>">
                  <input type="hidden" name="lane" value="archive_and_reset">
                  <fieldset class="ewh-provision-doc-select">
                    <legend>Archive and reset from template (destructive):</legend>
                    <?php foreach ($erDocs as $docKey => $docInfo): ?>
                      <?php if (($docInfo['state'] ?? '') === 'valid'): ?>
                        <label class="ewh-checkbox">
                          <input type="checkbox" name="documents[]" value="<?= e($docKey) ?>">
                          <?= e(ucfirst($docKey)) ?>
                        </label>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </fieldset>
                  <button type="submit" class="ewh-btn ewh-btn-sm ewh-btn-warn">Preview archive and reset</button>
                </form>
              <?php endif; ?>
            </div>

            <?php $lastTx = isset($provisionModel['last_transactions'][$erWs]) ? $provisionModel['last_transactions'][$erWs] : null; ?>
            <?php if ($lastTx !== null): ?>
              <details class="ewh-provision-last-tx">
                <summary>Last transaction: <?= e($lastTx['transaction_id'] ?? '') ?> (<?= e((string)($lastTx['datetime'] ?? '')) ?>)</summary>
                <div class="ewh-provision-plan-meta">
                  <span class="ewh-doc"><?= count($lastTx['operations'] ?? []) ?> operation(s)</span>
                </div>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/provision/rollback" class="ewh-provision-form">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($erWs) ?>">
                  <button type="submit" class="ewh-btn ewh-btn-sm ewh-btn-warn">Rollback</button>
                </form>
              </details>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
  <?php elseif ($activeTab === 'deploy'): ?>
    <section aria-labelledby="ewh-deploy-title">
      <div class="ewh-section-head">
        <h2 id="ewh-deploy-title">Workspace Template Deployment</h2>
        <p>Create missing starter documents, replace selected documents from templates, restore the last backup, or create a new workspace.</p>
      </div>

      <?php
        $csrf = \App\Core\Auth::csrfToken();
        $flashSuccess = (string)($_SESSION['studio_flash_success'] ?? '');
        $flashError = (string)($_SESSION['studio_flash_error'] ?? '');
        unset($_SESSION['studio_flash_success'], $_SESSION['studio_flash_error']);
      ?>

      <?php if ($flashSuccess !== ''): ?>
        <div class="ewh-provision-flash" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;"><?= e($flashSuccess) ?></div>
      <?php endif; ?>
      <?php if ($flashError !== ''): ?>
        <div class="ewh-provision-flash" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;"><?= e($flashError) ?></div>
      <?php endif; ?>

      <?php if ($deployWorkspaces === []): ?>
        <div class="ewh-empty">No workspaces available for deployment. Create a new workspace to get started.</div>
      <?php else: ?>
        <div class="ewh-section-head">
          <h2>Deploy to Workspace</h2>
        </div>
        <?php foreach ($deployWorkspaces as $dw): ?>
          <?php
            $dwKey = $dw['workspace_key'] ?? '';
            $dwLabel = $dw['context_label'] ?? $dwKey;
            $dwDocs = $dw['documents'] ?? [];
            $dwHasMissing = !empty($dw['has_missing']);
            $dwHasValid = !empty($dw['has_valid']);
          ?>
          <article class="ewh-provision-workspace">
            <div class="ewh-provision-ws-head">
              <h3><?= e($dwLabel) ?></h3>
              <span class="ewh-workspace-key"><?= e($dwKey) ?></span>
            </div>

            <div class="ewh-table-wrap" style="margin-bottom:12px">
              <table class="ewh-table">
                <thead>
                  <tr>
                    <th>Document</th>
                    <th>State</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($dwDocs as $docKey => $docInfo): ?>
                    <?php $docState = $docInfo['state'] ?? 'missing'; ?>
                    <tr>
                      <td><strong><?= e(ucfirst($docKey)) ?></strong></td>
                      <td>
                        <span class="ewh-doc <?= $docState === 'missing' ? 'ewh-doc-missing' : '' ?>">
                          <?= $docState === 'valid' ? 'Valid' : 'Missing' ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($docState === 'missing'): ?>
                          <span class="ewh-message">Will be created from template</span>
                        <?php else: ?>
                          <span class="ewh-message">Can be replaced</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="ewh-provision-actions">
              <?php if ($dwHasMissing): ?>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/deploy/create" class="ewh-provision-inline-form" onsubmit="return confirm('Create missing documents from templates?')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($dwKey) ?>">
                  <fieldset class="ewh-provision-doc-select">
                    <legend>Select missing documents to create:</legend>
                    <?php foreach ($dwDocs as $docKey => $docInfo): ?>
                      <?php if (($docInfo['state'] ?? '') === 'missing'): ?>
                        <label class="ewh-checkbox">
                          <input type="checkbox" name="documents[]" value="<?= e($docKey) ?>" checked>
                          <?= e(ucfirst($docKey)) ?>
                        </label>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </fieldset>
                  <button type="submit" class="ewh-btn ewh-btn-sm">Create Missing Files</button>
                </form>
              <?php endif; ?>

              <?php if ($dwHasValid): ?>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/deploy/replace" class="ewh-provision-inline-form" onsubmit="return confirm('Replace selected documents from templates? The current version will be archived before replacement.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($dwKey) ?>">
                  <fieldset class="ewh-provision-doc-select">
                    <legend>Select documents to replace:</legend>
                    <?php foreach ($dwDocs as $docKey => $docInfo): ?>
                      <?php if (($docInfo['state'] ?? '') === 'valid'): ?>
                        <label class="ewh-checkbox">
                          <input type="checkbox" name="documents[]" value="<?= e($docKey) ?>">
                          <?= e(ucfirst($docKey)) ?>
                        </label>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </fieldset>
                  <button type="submit" class="ewh-btn ewh-btn-sm">Replace Selected Files</button>
                </form>
              <?php endif; ?>

              <?php if ($dwHasValid || $dwHasMissing): ?>
                <form method="POST" action="/apps/studio/tools/engineering-workspaces/deploy/restore" class="ewh-provision-inline-form" onsubmit="return confirm('Restore the last backup for this workspace? Current content will be checked against the backup snapshot.')">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="workspace_key" value="<?= e($dwKey) ?>">
                  <button type="submit" class="ewh-btn ewh-btn-sm ewh-btn-warn">Restore Last Backup</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="ewh-section-head" style="margin-top:28px;">
        <h2>New Workspace</h2>
        <p>Create a new Engineering Workspace directory with all four starter documents.</p>
      </div>
      <form method="POST" action="/apps/studio/tools/engineering-workspaces/new-workspace" class="ewh-provision-inline-form" style="max-width:500px;" onsubmit="return confirm('Create new workspace with starter documents?')">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label style="font-size:13px;font-weight:600;color:#4b5563;margin-bottom:4px;">Workspace key (e.g. "MyApp/Feature"):</label>
        <input type="text" name="workspace_key" class="ewh-input" placeholder="e.g. MyApp/Feature" required pattern="[A-Za-z0-9_/.]+" title="Alphanumeric, underscores, slashes, and dots only">
        <button type="submit" class="ewh-btn">Create Workspace</button>
      </form>

      <div class="ewh-section-head" style="margin-top:28px;">
        <h2>Deploy Missing Templates to All Owner Workspaces</h2>
        <p>Create missing starter documents (overview.md, work.md, rules.md, decisions.md) for every canonical owner workspace. Existing documents are preserved unchanged.</p>
        <form method="POST" action="/apps/studio/tools/engineering-workspaces/deploy/create-missing-all" class="ewh-provision-inline-form" style="margin-top:10px;" onsubmit="return confirm('This will create missing documents for ALL applicable owner workspaces. Existing documents will NOT be modified. Proceed?')">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <button type="submit" class="ewh-btn ewh-btn-sm" style="background:#1e40af;color:#fff;">Create Missing for All Owners</button>
        </form>
        <p style="font-size:12px;color:#6b7280;margin-top:6px;">Owners are sourced from the canonical owner catalogue. Reserved template directories (<code>_template</code>, <code>_templates</code>) are excluded. Each workspace is processed independently.</p>
      </div>
    </section>
  <?php elseif ($activeTab === 'coverage-assignment'): ?>
    <section aria-labelledby="ewh-coverage-title">
      <div class="ewh-section-head">
        <h2 id="ewh-coverage-title">Coverage Assignment</h2>
        <p>Decide what each unassigned owner context should use for Engineering Workspace coverage.</p>
      </div>

      <?php if ($assignmentFlash !== ''): ?>
        <div class="ewh-provision-flash"><?= e($assignmentFlash) ?></div>
      <?php endif; ?>

      <?php if ($assignmentRows === []): ?>
        <div class="ewh-empty">No owner contexts currently require a coverage decision.</div>
      <?php else: ?>
        <div class="ewh-table-wrap">
          <table class="ewh-table">
            <thead>
              <tr>
                <th>Owner context</th>
                <th>Suggested workspace key</th>
                <th>Assignment</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignmentRows as $ar): ?>
                <?php
                  $ao = (string)($ar['owner_key'] ?? '');
                  $al = (string)($ar['context_label'] ?? $ao);
                  $as = (string)($ar['suggested_workspace_key'] ?? $ao);
                  $ast = (string)($ar['assignment_state'] ?? '');
                  $pm = (string)($ar['prefilled_mode'] ?? '');
                  $pw = (string)($ar['prefilled_workspace_key'] ?? $as);
                ?>
                <tr>
                  <td>
                    <div class="ewh-context-label"><?= e($al) ?></div>
                    <div class="ewh-evidence-type"><?= e($ao) ?></div>
                  </td>
                  <td>
                    <?php if ($ast !== ''): ?>
                      <span class="ewh-readiness"><?= e($ast) ?></span>
                    <?php else: ?>
                      <span class="ewh-workspace-key"><?= e($as) ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($ast !== ''): ?>
                      <span class="ewh-status ewh-status-linked_valid"><?= e($ast) ?></span>
                    <?php else: ?>
                      <form method="POST" action="/apps/studio/tools/engineering-workspaces/coverage-assign" class="ewh-ca-form">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="owner_key" value="<?= e($ao) ?>">
                        <div class="ewh-ca-field">
                          <select name="mode" class="ewh-ca-select ewh-ca-mode-select" data-owner="<?= e($ao) ?>">
                            <option value="dedicated" <?= $pm === 'dedicated' ? 'selected' : '' ?>>Dedicated workspace</option>
                            <option value="covered_by" <?= $pm === 'covered_by' ? 'selected' : '' ?>>Covered by existing workspace</option>
                            <option value="skip" <?= $pm === 'skip' ? 'selected' : '' ?>>Skip for now</option>
                          </select>
                        </div>
                        <div class="ewh-ca-field ewh-ca-ws-field" id="ewh-ca-ws-<?= e($ao) ?>">
                          <select name="workspace_key" class="ewh-ca-select">
                            <option value="">— Select workspace —</option>
                            <?php foreach ($existingWorkspaces as $ew): ?>
                              <option value="<?= e($ew) ?>" <?= $pw === $ew ? 'selected' : '' ?>><?= e($ew) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>
                        <div class="ewh-ca-action">
                          <button type="submit" class="ewh-btn ewh-btn-sm">Save</button>
                        </div>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($ast !== ''): ?>
                      <span class="ewh-readiness" style="color:#166534;">Assignment saved</span>
                    <?php else: ?>
                      <span class="ewh-readiness">Pending decision</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  <?php elseif ($activeTab === 'workspaces'): ?>
    <section aria-labelledby="ewh-workspaces-title">
      <div class="ewh-section-head">
        <h2 id="ewh-workspaces-title">Engineering Workspace Coverage</h2>
        <p>Evaluate authoritative owner contexts, registered workspaces, and existing workspace artifacts to prepare safe initialization and reconciliation plans.</p>
      </div>

      <?php if ($coverageRows === [] && $ownerSummary === []): ?>
        <div class="ewh-empty">Coverage data unavailable.</div>
      <?php else: ?>
        <?php if ($catalogueState === 'unavailable'): ?>
          <div class="ewh-alert-banner ewh-alert-warn">Owner catalogue source is unavailable. Registered workspace rows are shown where possible.</div>
        <?php endif; ?>
        <?php if ($scanState === 'unavailable'): ?>
          <div class="ewh-alert-banner ewh-alert-warn">Engineering-root directory scan is unavailable. Owner-based and registered rows are unaffected.</div>
        <?php endif; ?>

        <?php
          $os = $ownerSummary;
          $osItems = [
            ['num' => (int)($os['contexts_scanned'] ?? 0), 'label' => 'Owner contexts scanned'],
            ['num' => (int)($os['exact_workspace_mappings'] ?? 0), 'label' => 'Exact workspace mappings', 'cls' => 'linked_valid'],
            ['num' => (int)($os['coverage_decision_required'] ?? 0), 'label' => 'Coverage decisions required', 'cls' => 'coverage_decision_required'],
            ['num' => (int)($os['explicitly_excluded'] ?? 0), 'label' => 'Explicitly excluded'],
            ['num' => (int)($os['resolution_failed'] ?? 0), 'label' => 'Owner-context failures', 'cls' => 'resolution_failed'],
          ];
          $ws = $workspaceSummary;
          $wsItems = [
            ['num' => (int)($ws['registered_workspaces'] ?? 0), 'label' => 'Registered workspaces', 'cls' => 'linked_valid'],
            ['num' => (int)($ws['discovered_unregistered_workspaces'] ?? 0), 'label' => 'Discovered unregistered workspaces', 'cls' => 'unregistered_workspace_discovered'],
            ['num' => (int)($ws['linked_valid'] ?? 0), 'label' => 'Linked valid', 'cls' => 'linked_valid'],
            ['num' => (int)($ws['initialization_required'] ?? 0), 'label' => 'Initialization required', 'cls' => 'initialization_required'],
            ['num' => (int)($ws['contract_repair_required'] ?? 0), 'label' => 'Contract repair required', 'cls' => 'contract_repair_required'],
            ['num' => (int)($ws['mapping_review_required'] ?? 0), 'label' => 'Mapping review required', 'cls' => 'mapping_review_required'],
            ['num' => (int)($ws['resolution_failed'] ?? 0), 'label' => 'Resolution failed', 'cls' => 'resolution_failed'],
          ];
        ?>

        <div class="ewh-summary-group">
          <h3>Owner coverage</h3>
          <div class="ewh-summary-grid">
            <?php foreach ($osItems as $item): ?>
              <div class="ewh-summary-item<?= isset($item['cls']) ? ' ewh-summary-' . e($item['cls']) : '' ?>">
                <span class="ewh-summary-num"><?= (int)$item['num'] ?></span>
                <span class="ewh-summary-label"><?= e($item['label']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="ewh-summary-group">
          <h3>Workspace readiness</h3>
          <div class="ewh-summary-grid">
            <?php foreach ($wsItems as $item): ?>
              <div class="ewh-summary-item<?= isset($item['cls']) ? ' ewh-summary-' . e($item['cls']) : '' ?>">
                <span class="ewh-summary-num"><?= (int)$item['num'] ?></span>
                <span class="ewh-summary-label"><?= e($item['label']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if ($coverageRows === []): ?>
          <div class="ewh-empty">No workspace entries found.</div>
        <?php else: ?>
          <div class="ewh-table-wrap">
            <table class="ewh-table">
              <thead>
                <tr>
                  <th>Context</th>
                  <th>Evidence</th>
                  <th>Workspace / Coverage</th>
                  <th>Documents</th>
                  <th>Provisioning readiness</th>
                  <th>State</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($coverageRows as $row): ?>
                  <?php
                    $state = (string)($row['state'] ?? 'resolution_failed');
                    $contextLabel = (string)($row['context_label'] ?? '');
                    $evidenceType = (string)($row['evidence_type'] ?? '');
                    $workspaceLabel = (string)($row['workspace_label'] ?? '');
                    $readiness = (string)($row['provisioning_readiness'] ?? '');
                    $rowKind = (string)($row['row_kind'] ?? '');
                  ?>
                  <tr>
                    <td>
                      <div class="ewh-context-label"><?= e($contextLabel) ?></div>
                    </td>
                    <td>
                      <span class="ewh-evidence-type"><?= e($evidenceHumanLabel($evidenceType)) ?></span>
                    </td>
                    <td>
                      <?php if ($workspaceLabel !== ''): ?>
                        <span class="ewh-workspace-key"><?= e($workspaceLabel) ?></span>
                      <?php else: ?>
                        <span class="ewh-dash">—</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php $docs = isset($row['documents']) && is_array($row['documents']) ? $row['documents'] : []; ?>
                      <?php if ($docs === []): ?>
                        <span class="ewh-dash">—</span>
                      <?php else: ?>
                        <div class="ewh-doc-list">
                          <?php foreach ($docs as $doc): ?>
                            <?php
                              $docStatus = (string)($doc['status'] ?? 'unavailable');
                              $docLabel = (string)($doc['label'] ?? 'Document');
                              $docUrl = (string)($doc['url'] ?? '');
                            ?>
                            <?php if ($docStatus === 'valid' && $docUrl !== ''): ?>
                              <a class="ewh-doc" href="<?= e($docUrl) ?>"><?= e($docLabel) ?></a>
                            <?php elseif ($docStatus === 'missing'): ?>
                              <span class="ewh-doc ewh-doc-missing"><?= e($docLabel) ?> · Missing</span>
                            <?php elseif ($docStatus === 'contract_invalid'): ?>
                              <span class="ewh-doc ewh-doc-repair"><?= e($docLabel) ?> · Needs repair</span>
                            <?php else: ?>
                              <span class="ewh-doc ewh-doc-unavailable"><?= e($docLabel) ?> · Unavailable</span>
                            <?php endif; ?>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="ewh-readiness"><?= e($readiness) ?></span>
                    </td>
                    <td>
                      <span class="ewh-status ewh-status-<?= e($state) ?>"><?= e($stateHumanLabel($state)) ?></span>
                      <?php if ((string)($row['reason_code'] ?? '') !== ''): ?>
                        <div class="ewh-message"><?= e((string)$row['reason_code']) ?></div>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  <?php elseif ($activeTab === 'agent-context'): ?>
    <section aria-labelledby="ewh-agent-title">
      <div class="ewh-section-head">
        <h2 id="ewh-agent-title">Prepared Engineering Workspace Agent Context</h2>
        <p>Prepare a safe Engineering Workspace execution packet for an external coding agent. This surface does not invoke a live agent — it produces the context packet that an external agent would receive.</p>
      </div>

      <form method="GET" action="/apps/studio/tools/engineering-workspaces" class="ewh-provision-inline-form" style="max-width:640px;margin-bottom:20px;">
        <input type="hidden" name="tab" value="agent-context">

        <label style="font-size:13px;font-weight:600;color:#374151;">Owner key</label>
        <select name="owner_key" class="ewh-input" style="max-width:100%;">
          <option value="">— Select a workspace —</option>
          <?php foreach ($agentContextExistingWs as $ws): ?>
            <option value="<?= e($ws) ?>" <?= ($agentContextRequest['owner_key'] ?? '') === $ws ? 'selected' : '' ?>><?= e($ws) ?></option>
          <?php endforeach; ?>
        </select>

        <div style="display:flex;gap:20px;flex-wrap:wrap;margin-top:8px;">
          <fieldset style="border:none;padding:0;margin:0;">
            <legend style="font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Requested mode</legend>
            <label class="ewh-checkbox"><input type="radio" name="requested_mode" value="read_only" <?= ($agentContextRequest['requested_mode'] ?? 'read_only') === 'read_only' ? 'checked' : '' ?>> Read only</label>
            <label class="ewh-checkbox"><input type="radio" name="requested_mode" value="implementation" <?= ($agentContextRequest['requested_mode'] ?? '') === 'implementation' ? 'checked' : '' ?>> Implementation</label>
          </fieldset>
          <fieldset style="border:none;padding:0;margin:0;">
            <legend style="font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Scope mode</legend>
            <label class="ewh-checkbox"><input type="radio" name="scope_mode" value="owner" <?= ($agentContextRequest['scope_mode'] ?? 'owner') === 'owner' ? 'checked' : '' ?>> Owner</label>
            <label class="ewh-checkbox"><input type="radio" name="scope_mode" value="platform" <?= ($agentContextRequest['scope_mode'] ?? '') === 'platform' ? 'checked' : '' ?>> Platform</label>
          </fieldset>
        </div>

        <button type="submit" class="ewh-btn" style="margin-top:10px;">Prepare Agent Context</button>
      </form>

      <?php if ($agentContextResult !== null): ?>
        <?php
          $packet = $agentContextResult['execution_packet'] ?? $agentContextResult;
          $executionState = (string)($packet['execution_state'] ?? 'unknown');
          $writePermitted = !empty($packet['write_permitted']);
          $resolvedKey = (string)($packet['resolved_workspace_key'] ?? '');
          $resolutionSource = (string)($packet['resolution_source'] ?? '');
          $blockReason = (string)($packet['block_reason'] ?? '');
          $ctxPkg = isset($packet['workspace_context_package']) && is_array($packet['workspace_context_package']) ? $packet['workspace_context_package'] : [];
          $docCount = count($ctxPkg['documents'] ?? []);
          $repairDocs = $ctxPkg['repair_required_documents'] ?? [];
          $execCalled = !empty($agentContextResult['execution_called']);
        ?>
        <div class="ewh-provision-plan" style="<?= $executionState === 'blocked' ? 'background:#fef2f2;border-color:#fecaca;' : ($executionState === 'read_only_only' ? 'background:#fffbeb;border-color:#fde68a;' : 'background:#f0fdf4;border-color:#bbf7d0;') ?>">
          <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-bottom:10px;">
            <span class="ewh-status ewh-status-<?= $executionState === 'blocked' ? 'resolution_failed' : ($executionState === 'read_only_only' ? 'initialization_required' : 'linked_valid') ?>"><?= e($executionState) ?></span>
            <span style="font-size:13px;color:#374151;font-weight:600;">Write permitted: <?= $writePermitted ? 'Yes' : 'No' ?></span>
          </div>

          <?php if ($executionState === 'blocked'): ?>
            <p style="font-size:13px;color:#991b1b;margin:0;"><?= e($blockReason) ?></p>
          <?php elseif ($executionState === 'read_only_only'): ?>
            <p style="font-size:13px;color:#92400e;margin:0;">Read-only preparation only. No write-capable execution is available.</p>
          <?php else: ?>
            <p style="font-size:13px;color:#166534;margin:0 0 8px;">Context prepared successfully. The execution packet may be handed to an external coding agent.</p>
          <?php endif; ?>

          <details style="margin-top:12px;font-size:13px;" open>
            <summary style="font-weight:600;cursor:pointer;color:#1d4ed8;">Context details</summary>
            <dl style="margin:10px 0 0;display:grid;grid-template-columns:auto 1fr;gap:6px 14px;font-size:13px;">
              <dt style="color:#6b7280;">Owner key</dt>
              <dd><?= e($resolvedKey ?: '(none)') ?></dd>
              <dt style="color:#6b7280;">Resolution source</dt>
              <dd><?= e($resolutionSource ?: '(none)') ?></dd>
              <dt style="color:#6b7280;">Requested mode</dt>
              <dd><?= e($packet['requested_mode'] ?? 'read_only') ?></dd>
              <dt style="color:#6b7280;">Scope mode</dt>
              <dd><?= e($packet['scope_mode'] ?? 'owner') ?></dd>
              <dt style="color:#6b7280;">Documents in package</dt>
              <dd><?= $docCount ?> / 4</dd>
              <?php if ($repairDocs !== []): ?>
                <dt style="color:#6b7280;">Repair required</dt>
                <dd style="color:#991b1b;"><?= e(implode(', ', $repairDocs)) ?></dd>
              <?php endif; ?>
              <dt style="color:#6b7280;">Executor invoked</dt>
              <dd><?= $execCalled ? 'Yes (spy/real)' : 'No (packet only)' ?></dd>
            </dl>
          </details>

          <details style="margin-top:8px;font-size:13px;">
            <summary style="font-weight:600;cursor:pointer;color:#1d4ed8;">Agent instruction prefix</summary>
            <pre style="padding:10px;margin:8px 0 0;background:#f3f4f6;border-radius:6px;font-size:12px;white-space:pre-wrap;color:#1f2937;max-height:200px;overflow-y:auto;"><?= e($packet['agent_instruction_prefix'] ?? '') ?></pre>
          </details>
        </div>

        <div style="margin-top:12px;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;font-size:12px;color:#6b7280;">
          <strong>Limitation:</strong> OdareHub does not contain a built-in coding agent runtime. This surface prepares a structured context packet for an external agent but does not dispatch or execute external agents.
        </div>
      <?php elseif ($agentContextRequest !== null && $agentContextResult === null): ?>
        <div class="ewh-provision-plan" style="background:#fef2f2;border-color:#fecaca;">
          <p style="font-size:13px;color:#991b1b;margin:0;">Context preparation failed. No result was returned.</p>
        </div>
      <?php else: ?>
        <div class="ewh-empty">Select a workspace and submit to prepare an agent context packet.</div>
      <?php endif; ?>
    </section>
  <?php endif; ?>
</section>
<script>
(function() {
  var selects = document.querySelectorAll('.ewh-ca-mode-select');
  function toggleWsField(select) {
    var owner = select.getAttribute('data-owner');
    var wsField = document.getElementById('ewh-ca-ws-' + owner);
    if (wsField) {
      wsField.classList.toggle('ewh-ca-ws-visible', select.value === 'covered_by');
    }
  }
  for (var i = 0; i < selects.length; i++) {
    selects[i].addEventListener('change', function() { toggleWsField(this); });
    toggleWsField(selects[i]);
  }
})();
</script>
<?php
// evidenceHumanLabel and stateHumanLabel are closures defined at the top of this view
