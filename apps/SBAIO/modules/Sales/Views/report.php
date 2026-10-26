<?php
$tt = static function (string $key): string {
  return t($key);
};
$report = is_array($report ?? null) ? $report : null;
$reportData = is_array($reportData ?? null) ? $reportData : [];
$summary = is_array($reportData['summary'] ?? null) ? $reportData['summary'] : [];
$byStatus = is_array($reportData['byStatus'] ?? null) ? $reportData['byStatus'] : [];
$topCustomers = is_array($reportData['topCustomers'] ?? null) ? $reportData['topCustomers'] : [];
$recent = is_array($reportData['recent'] ?? null) ? $reportData['recent'] : [];
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('sales.sales_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('sales.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/sales">&larr; Sales</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong><?= e($tt('sales.report_key_label')) ?>:</strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/sales/export"> <?= e($tt('sales.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('sales.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('sales.90_day_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Orders</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Total Amount</div><div class="u-stat-num"><strong><?= number_format((float)$summary['amount']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">This Month</div><div class="u-stat-num"><strong><?= (int)$summary['month'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Month Amount</div><div class="u-stat-num"><strong><?= number_format((float)$summary['month_amount']) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('sales.closed_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['closed'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('common.open_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['open'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('sales.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th class="u-text-right">Count</th><th class="u-text-right">Amount</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['sale_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td><td class="u-text-right"><?= number_format((float)$r['amount']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">Top Customers</h4>
      <?php if (!$topCustomers): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Customer</th><th class="u-text-right">Orders</th><th class="u-text-right">Amount</th></tr></thead><tbody>
          <?php foreach ($topCustomers as $r): ?>
            <tr><td><?= e((string)$r['customer_name']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td><td class="u-text-right"><?= number_format((float)$r['amount']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Sales</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No sales recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Ref</th><th>Date</th><th>Customer</th><th class="u-text-right">Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['sale_ref'] ?? '')) ?></td>
            <td><?= e((string)$r['sale_date']) ?></td>
            <td><?= e((string)($r['customer_name'] ?? '')) ?></td>
            <td class="u-text-right"><?= number_format((float)$r['amount']) ?></td>
            <td><?= e((string)$r['sale_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
