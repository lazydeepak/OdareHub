<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

$report = $report ?? \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.part_machine_map.overview');
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <h2 class="u-style-1169661891"> <?= e($tt('part_machine_map.part_machine_map_title')) ?> </h2>
  <?php if (is_array($report)): ?>
    <p class="muted"> <?= e($tt('part_machine_map.export_active_message')) ?> <strong><?= e((string)($report['report_key'] ?? '-')) ?></strong>.</p>
  <?php else: ?>
    <p class="muted"> <?= e($tt('part_machine_map.export_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
