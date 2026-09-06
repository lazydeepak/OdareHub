<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Platform/Services/ModuleReportRegistryService.php';

use Apps\Platform\Services\ModuleReportRegistryService;
use App\Core\DB;

$report = ModuleReportRegistryService::findActiveReport('platform.qrcode.overview');

$labelEligibleProducts = 0;
$stockEntries = 0;
if (is_array($report)) {
    try {
        $row = DB::fetchOne('SELECT COUNT(*) AS n FROM products WHERE is_active = 1');
        $labelEligibleProducts = (int)($row['n'] ?? 0);
    } catch (\Throwable $e) {
        $labelEligibleProducts = 0;
    }
    try {
        $row = DB::fetchOne('SELECT COUNT(*) AS n FROM stock_ledger_entries');
        $stockEntries = (int)($row['n'] ?? 0);
    } catch (\Throwable $e) {
        $stockEntries = 0;
    }
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('q_r_code.qr_code_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('q_r_code.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/qr/stock">&larr; QR Stock</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('q_r_code.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block">
        <a class="btn" href="/qr/export"> <?= e($tt('q_r_code.open_link')) ?> </a>
      </div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('q_r_code.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('q_r_code.qr_coverage_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted"> <?= e($tt('q_r_code.label_eligible_products_label')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$labelEligibleProducts ?></strong></div></div>
    <div class="ui-block"><div class="muted">Stock Ledger Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$stockEntries ?></strong></div></div>
  </div>
  <p class="muted u-style-d2c171b18b">Generate QR labels from Products &rarr; label print, or record stock actions under QR Stock.</p>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
