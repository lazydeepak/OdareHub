<header class="studio-header">
  <div class="breadcrumb" data-base-label="<?= e($gs('studio_root_label')) ?>"><?= e($gs('studio_root_label')) ?></div>
  <div class="status-bar">
    <span class="badge <?= $studioMode === 'edit_existing' ? 'badge-editing' : 'badge-creating' ?>" id="gs-editor-mode-badge"><?= e($gs($studioMode === 'edit_existing' ? 'editor_editing' : 'editor_creating')) ?></span>
    <span class="safe-mode"><?= e($gs('editor_safe_mode')) ?></span>
    <span class="status-chip"><?= e($gs('read_only_analysis_badge')) ?></span>
    <div class="tier-switcher" role="group" aria-label="<?= e($gs('tier_label')) ?>" id="gs-tier-switcher">
      <button type="button" class="tier-chip" data-tier-btn="simple"><?= e($gs('tier_simple')) ?></button>
      <button type="button" class="tier-chip active" data-tier-btn="guided"><?= e($gs('tier_guided')) ?></button>
      <button type="button" class="tier-chip" data-tier-btn="advanced"><?= e($gs('tier_advanced')) ?></button>
    </div>
  </div>
</header>

<?php if ($flashText !== ''): ?>
  <div class="note success u-style-f9b991f1ac"><?= e($flashText) ?></div>
<?php endif; ?>
<?php if ($errorText !== ''): ?>
  <div class="note warning u-style-f9b991f1ac"><?= e($errorText) ?></div>
<?php endif; ?>
<div id="gs-studio-toast-layer" class="studio-toast-layer" aria-live="polite"></div>

<nav class="studio-tabs">
  <button data-tab="edit" class="active">✎ <?= e($gs('tab.edit')) ?></button>
  <button class="btn" data-tab="analyze">📊 <?= e($gs('tab.analyze')) ?></button>
  <button class="btn" data-tab="changes">⇄ <?= e($gs('tab.changes')) ?></button>
  <button class="btn" data-tab="apply">✓ <?= e($gs('tab.apply')) ?></button>
</nav>
<p class="muted"><?= e($gs('downstream_tabs_helper')) ?></p>