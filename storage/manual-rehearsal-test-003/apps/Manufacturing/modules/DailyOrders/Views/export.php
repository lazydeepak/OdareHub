<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$report = $report ?? \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.daily_orders.overview');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <h2 class="u-style-1169661891"> <?= e($tt('daily_orders.daily_orders_export_title')) ?> </h2>
  <?php if (is_array($report)): ?>
    <p class="muted"> <?= e($tt('daily_orders.export_active_message')) ?> <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted"> <?= e($tt('daily_orders.export_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
