<section class="gs-mode-panel gs-zone-block" data-gs-mode-panel>
  <h4 class="gs-mode-panel-title\"><?= e($gsBatch1('mode_panel_title')) ?></h4>
  <div class="gs-mode-strip">
    <span class="gs-mode-chip" data-gs-mode-chip="read_only" data-mode-active="1">
      <strong><?= e($gsBatch1('mode_read_only')) ?></strong>
      <span class="gs-mode-state" data-gs-mode-state><?= e($gsBatch1('mode_state_active')) ?></span>
    </span>
    <span class="gs-mode-chip" data-gs-mode-chip="create" data-mode-active="0">
      <strong><?= e($gsBatch1('mode_create')) ?></strong>
      <span class="gs-mode-state" data-gs-mode-state><?= e($gsBatch1('mode_state_planned')) ?></span>
    </span>
    <span class="gs-mode-chip" data-gs-mode-chip="edit" data-mode-active="0">
      <strong><?= e($gsBatch1('mode_edit')) ?></strong>
      <span class="gs-mode-state" data-gs-mode-state><?= e($gsBatch1('mode_state_not_active')) ?></span>
    </span>
    <span class="gs-mode-chip" data-gs-mode-chip="upgrade" data-mode-active="0">
      <strong><?= e($gsBatch1('mode_upgrade')) ?></strong>
      <span class="gs-mode-state" data-gs-mode-state><?= e($gsBatch1('mode_state_requires_governance')) ?></span>
    </span>
  </div>
  <p class="gs-mode-panel-helper\"><?= e($gsBatch1('mode_panel_helper')) ?></p>
</section>
