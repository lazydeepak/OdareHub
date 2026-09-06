<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('assembly_entries.add_title')) ?> </h2>
      <div class="muted"> <?= e($tt('assembly_entries.form_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/manufacturing/assembly-queue">&larr; Assembly Queue</a>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
