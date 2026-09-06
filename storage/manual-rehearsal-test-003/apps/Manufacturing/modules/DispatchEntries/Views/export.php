<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.dispatch_entries.execution');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('dispatch_entries.dispatch_entry_export_title')) ?> </h2>
      <div class="muted"> <?= e($tt('dispatch_entries.export_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/dispatch-entries">&larr; Dispatch Entries</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <p class="muted u-style-1169661891"> <?= e($tt('dispatch_entries.export_active_message')) ?> <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('dispatch_entries.export_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
