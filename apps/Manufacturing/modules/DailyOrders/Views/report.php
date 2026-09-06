<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.daily_orders.overview');

$summary = [
    'total_open' => 0,
    'total_qty' => 0.0,
    'total_shortage' => 0.0,
    'due_today' => 0,
    'due_7day' => 0,
    'low_coverage' => 0,
    'full_coverage' => 0,
];
$byStatus = [];
$upcoming = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS total_open,
                COALESCE(SUM(qty), 0) AS total_qty,
                COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS total_shortage,
                COALESCE(SUM(CASE WHEN DATE(COALESCE(required_date, order_date)) <= CURDATE() THEN 1 ELSE 0 END), 0) AS due_today,
                COALESCE(SUM(CASE WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS due_7day,
                COALESCE(SUM(CASE WHEN coverage_status = 'Low' THEN 1 ELSE 0 END), 0) AS low_coverage,
                COALESCE(SUM(CASE WHEN coverage_status = 'Full' THEN 1 ELSE 0 END), 0) AS full_coverage
             FROM daily_orders
             WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
        );
        if (is_array($row)) {
            $summary = array_merge($summary, $row);
        }
        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(status, ''), '(none)') AS status, COUNT(*) AS n
             FROM daily_orders
             GROUP BY status
             ORDER BY n DESC"
        ) ?: [];
        $upcoming = DB::fetchAll(
            "SELECT d.id, d.customer_name, d.order_date, d.required_date, d.qty,
                    COALESCE(d.shortage_qty, 0) AS shortage_qty,
                    COALESCE(d.coverage_status, '') AS coverage_status,
                    p.parts_number, p.parts_name
             FROM daily_orders d
             INNER JOIN products p ON p.id = d.product_id
             WHERE LOWER(COALESCE(d.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND COALESCE(d.required_date, d.order_date) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
             ORDER BY COALESCE(d.required_date, d.order_date) ASC, d.id ASC
             LIMIT 20"
        ) ?: [];
    } catch (\Throwable $e) {
        // Fall back to empty summary
    }
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('daily_orders.daily_orders_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('daily_orders.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/manufacturing/daily-orders">&larr; Daily Orders</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('daily_orders.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block">
        <a class="btn" href="/apps/manufacturing/daily-orders/export"> <?= e($tt('daily_orders.open_link')) ?> </a>
      </div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('daily_orders.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('daily_orders.open_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Open Orders</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['total_open'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Total Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Shortage Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_shortage']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Due Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['due_today'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Due &le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['due_7day'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Low Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['low_coverage'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Full Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['full_coverage'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('daily_orders.status_distribution_title')) ?> </h3>
  <?php if (!$byStatus): ?>
    <p class="muted u-style-1169661891">No orders recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-style-54c2afb7ba">Count</th></tr></thead>
      <tbody>
        <?php foreach ($byStatus as $r): ?>
          <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('daily_orders.upcoming_7_day_title')) ?> </h3>
  <?php if (!$upcoming): ?>
    <p class="muted u-style-1169661891">No open orders due in the next 7 days.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Order #</th><th>Customer</th><th>Part</th><th>Required</th><th class="u-style-54c2afb7ba">Qty</th><th class="u-style-54c2afb7ba">Shortage</th><th>Coverage</th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
          <tr>
            <td><a href="/apps/manufacturing/daily-orders/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)($r['customer_name'] ?? '')) ?></td>
            <td><?= e((string)($r['parts_number'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['parts_name'] ?? '')) ?></span></td>
            <td><?= e((string)($r['required_date'] ?? $r['order_date'] ?? '')) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['shortage_qty']) ?></td>
            <td><?= e((string)$r['coverage_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
