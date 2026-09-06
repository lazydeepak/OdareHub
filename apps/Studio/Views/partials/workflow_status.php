<section class="gs-workflow-status-panel gs-zone-block" data-gs-workflow-status-panel>
  <h4 class="gs-workflow-status-title"><?= e($gsBatch1('workflow_status_title')) ?></h4>
  <dl class="gs-workflow-status-grid">
    <div class="gs-workflow-status-item">
      <dt><?= e($gsBatch1('workflow_stage_analyze')) ?></dt>
      <dd data-gs-workflow-analyze><?= e($gsBatch1('workflow_state_load_before_analysis')) ?></dd>
    </div>
    <div class="gs-workflow-status-item">
      <dt><?= e($gsBatch1('workflow_stage_changes')) ?></dt>
      <dd data-gs-workflow-changes><?= e($gsBatch1('workflow_state_no_diff')) ?></dd>
    </div>
    <div class="gs-workflow-status-item">
      <dt><?= e($gsBatch1('workflow_stage_preview')) ?></dt>
      <dd data-gs-workflow-preview><?= e($gsBatch1('workflow_state_no_preview')) ?></dd>
    </div>
    <div class="gs-workflow-status-item">
      <dt><?= e($gsBatch1('workflow_stage_approval')) ?></dt>
      <dd data-gs-workflow-approval><?= e($gsBatch1('workflow_state_no_approval')) ?></dd>
    </div>
    <div class="gs-workflow-status-item">
      <dt><?= e($gsBatch1('workflow_stage_apply')) ?></dt>
      <dd data-gs-workflow-apply><?= e($gsBatch1('workflow_state_no_apply')) ?></dd>
    </div>
  </dl>
  <p class="gs-workflow-status-helper"><?= e($gsBatch1('workflow_status_helper')) ?></p>
</section>
