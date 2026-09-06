<?php
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$report = $report ?? \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.production_queue.overview');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <h2 class="u-style-1169661891">Production Queue Export</h2>
  <?php if (is_array($report)): ?>
    <p class="muted">Module-owned export is active for <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted">Export is unavailable because its module is inactive or not registered.</p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
