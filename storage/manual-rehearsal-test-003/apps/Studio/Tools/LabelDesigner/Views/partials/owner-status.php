  <!-- Phase 8.2: Consistent owner status panel for all workspaces (only for lifecycle owners) -->
  <?php if ($activeWorkspace !== 'overview' && !empty($selectedReadinessSummary['is_label_lifecycle_owner'])): ?>
  <div class="ld-owner-status-panel" style="margin:16px 0;padding:12px 16px;background:var(--bg-card,#fff);border:1px solid var(--border-subtle,#ccc);border-radius:6px">
    <h4 style="margin:0 0 8px;font-size:0.95em"><?= e($ld('owner_status_title')) ?></h4>
    <div class="ld-owner-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:8px;margin:0">
      <p style="margin:0"><small><?= e($ld('owner_status_name')) ?></small><br><strong><?= e($selectedOwnerDisplayName) ?></strong></p>
      <p style="margin:0"><small><?= e($ld('owner_status_key')) ?></small><br><code><?= e($selectedOwnerKey !== '' ? $selectedOwnerKey : $ld('overview_not_selected')) ?></code></p>
      <p style="margin:0"><small><?= e($ld('owner_status_type')) ?></small><br><strong><?= e($selectedOwnerType !== '' ? ucfirst($selectedOwnerType) : $ld('overview_unknown_type')) ?></strong></p>
      <p style="margin:0"><small><?= e($ld('owner_status_lifecycle')) ?></small><br><strong><?= e($selectedOwnerHasResources ? $ld('owner_status_ready') : $ld('owner_status_not_ready')) ?></strong></p>
      <p style="margin:0"><small><?= e($ld('owner_status_resources')) ?></small><br><strong><?= e($selectedOwnerHasResources ? $ld('owner_status_has_resources') : $ld('owner_status_no_resources')) ?></strong></p>
    </div>
  </div>
  <?php endif; ?>

  <!-- Phase 8.2: Manufacturing parent guidance -->
  <?php if ($activeWorkspace !== 'overview' && strcasecmp($selectedOwnerKey, 'Manufacturing') === 0): ?>
  <div class="ld-manufacturing-guidance" style="margin:16px 0;padding:16px;background:var(--bg-info-subtle,#e3f2fd);border:1px solid var(--border-info,#1565c0);border-radius:6px">
    <h4 style="margin:0 0 8px;color:var(--color-info,#1565c0)"><?= e($ld('manufacturing_guidance_title')) ?></h4>
    <p style="margin:0 0 12px"><?= e($ld('manufacturing_guidance_explanation')) ?></p>
    <a href="/apps/studio/tools/label-designer<?= $buildUrl(['workspace' => 'overview', 'owner' => 'Manufacturing/Products']) ?>" style="display:inline-block;padding:8px 16px;background:var(--color-info,#1565c0);color:#fff;text-decoration:none;border-radius:4px;font-weight:600">
      <?= e($ld('manufacturing_guidance_action')) ?>
    </a>
  </div>
  <?php endif; ?>

  <!-- Phase 8.2: Non-ready lifecycle owner guidance (only for lifecycle owners, not Manufacturing parent) -->
  <?php if ($activeWorkspace !== 'overview' && !empty($selectedReadinessSummary['is_label_lifecycle_owner']) && !$selectedOwnerHasResources && strcasecmp($selectedOwnerKey, 'Manufacturing') !== 0): ?>
  <div class="ld-non-ready-guidance" style="margin:16px 0;padding:16px;background:var(--bg-warning-subtle,#fff8e1);border:1px solid var(--border-warning,#f57c00);border-radius:6px">
    <h4 style="margin:0 0 8px;color:var(--color-warning,#f57c00)"><?= e($ld('non_ready_guidance_title')) ?></h4>
    <?php
    $readiness = isset($selectedReadinessSummary['readiness']) && is_array($selectedReadinessSummary['readiness']) ? $selectedReadinessSummary['readiness'] : [];
    $hasContexts = isset($readiness[1]['exists']) && $readiness[1]['exists'];
    $hasTemplates = isset($readiness[2]['exists']) && $readiness[2]['exists'];
    $hasFolders = !empty($selectedReadinessSummary['all_exist']) || !empty($selectedReadinessSummary['none_exist']) === false;
    ?>
    <?php if (!$hasFolders): ?>
      <p style="margin:0"><?= e($ld('non_ready_guidance_missing_folders')) ?></p>
    <?php elseif (!$hasContexts && !$hasTemplates): ?>
      <p style="margin:0"><?= e($ld('non_ready_guidance_missing_both')) ?></p>
    <?php elseif (!$hasContexts): ?>
      <p style="margin:0"><?= e($ld('non_ready_guidance_missing_context')) ?></p>
    <?php elseif (!$hasTemplates): ?>
      <p style="margin:0"><?= e($ld('non_ready_guidance_missing_template')) ?></p>
    <?php endif; ?>
  </div>
  <?php endif; ?>
