<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.assembly_plans.readiness');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('assembly_plans.assembly_plan_export_title')) ?> </h2>
      <div class="muted"> <?= e($tt('assembly_plans.export_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/manufacturing/assembly-plans">&larr; Assembly Plans</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <p class="muted u-style-1169661891"> <?= e($tt('assembly_plans.export_active_message')) ?> <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('assembly_plans.export_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
