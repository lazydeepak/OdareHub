<section class="gs-workbench-zone gs-workbench-zone-governance" data-gs-workbench-zone="governance">
  <header class="gs-workbench-zone-header">
    <h4 class="gs-workbench-zone-title"><?= e($gs('studio_tool_approval_apply_center')) ?></h4>
    <p class="gs-workbench-zone-helper"><?= e($gsBatch1('workflow_status_helper')) ?></p>
    <div class="gs-zone-intent-row">
      <span class="gs-zone-intent-chip\"><?= e($gs('publish_governance')) ?></span>
      <span class="gs-zone-intent-chip\"><?= e($gs('apply_status')) ?></span>
      <span class="gs-zone-intent-chip\"><?= e($gsBatch1('mode_state_requires_governance')) ?></span>
    </div>
  </header>
  <div class="gs-governance-lanes">
    <?php require __DIR__ . '/workflow_status.php'; ?>
    <div class="gs-governance-lane-stack">
      <?php require __DIR__ . '/mode_panel.php'; ?>
      <section class="gs-zone-block">
        <h4 class="gs-mode-panel-title\"><?= e($gs('publish_governance')) ?></h4>
        <dl class="gs-governance-readiness-list">
          <div class="gs-governance-readiness-item">
            <dt><?= e($gs('approval_gate')) ?></dt>
            <dd><?= e($gsBatch1('workflow_state_no_approval')) ?></dd>
          </div>
          <div class="gs-governance-readiness-item">
            <dt><?= e($gs('snapshot_preview')) ?></dt>
            <dd><?= e($gs('snapshot_disabled')) ?></dd>
          </div>
          <div class="gs-governance-readiness-item">
            <dt><?= e($gs('rollback_preview')) ?></dt>
            <dd><?= e($gs('rollback_unavailable')) ?></dd>
          </div>
          <div class="gs-governance-readiness-item">
            <dt><?= e($gs('apply_status')) ?></dt>
            <dd><?= e($gsBatch1('workflow_state_no_apply')) ?></dd>
          </div>
        </dl>
        <p class="gs-governance-readiness-note\"><?= e($gsBatch1('workflow_status_helper')) ?></p>
      </section>
    </div>
  </div>
</section>
