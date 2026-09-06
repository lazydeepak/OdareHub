<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.payroll.overview');
$csvExportUrl = '/apps/sbaio/payroll/export?format=csv';
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <h2 class="u-m-0"> <?= e($tt('payroll.payroll_export_title')) ?> </h2>
  <div class="ui-block"><a class="btn" href="<?= e((string)$csvExportUrl) ?>"><?= e(t('common.download_csv')) ?></a></div>
  <?php if (is_array($report)): ?>
    <p class="muted"> <?= e($tt('payroll.export_active_message')) ?> <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted"> <?= e($tt('payroll.export_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
