<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Platform/Services/ModuleReportRegistryService.php';

use Apps\Platform\Services\ModuleReportRegistryService;

$report = ModuleReportRegistryService::findActiveReport('platform.qrcode.overview');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('q_r_code.qr_code_export_title')) ?> </h2>
      <div class="muted"> <?= e($tt('q_r_code.export_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/qr/stock">&larr; QR Stock</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <p class="muted u-style-1169661891"> <?= e($tt('q_r_code.export_active_message')) ?> <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('q_r_code.export_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
