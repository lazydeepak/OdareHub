<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.coverage.overview');

$summary = [
    'open_orders' => 0,
    'demand_qty' => 0.0,
    'shortage_qty' => 0.0,
    'coverage_pct' => 0.0,
    'critical_orders_count' => 0,
    'low_coverage_orders_count' => 0,
    'fully_covered_orders_count' => 0,
    'window_today_count' => 0,
    'window_3day_count' => 0,
    'window_7day_count' => 0,
];
$hotlist = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_orders,
                COALESCE(SUM(qty), 0) AS demand_qty,
                COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS shortage_qty,
                COALESCE(ROUND(AVG(COALESCE(coverage_pct, 0)), 2), 0) AS coverage_pct,
                COALESCE(SUM(CASE
                    WHEN qty > 0 AND (
                        COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                        OR COALESCE(coverage_pct, 0) < 25
                    ) THEN 1 ELSE 0 END), 0) AS critical_orders_count,
                COALESCE(SUM(CASE
                    WHEN qty > 0
                         AND COALESCE(shortage_qty, 0) > 0
                         AND NOT (
                             COALESCE(shortage_qty, 0) / NULLIF(qty, 0) >= 0.50
                             OR COALESCE(coverage_pct, 0) < 25
                         )
                    THEN 1 ELSE 0 END), 0) AS low_coverage_orders_count,
                COALESCE(SUM(CASE
                    WHEN qty > 0 AND COALESCE(shortage_qty, 0) <= 0 THEN 1 ELSE 0 END), 0) AS fully_covered_orders_count,
                COALESCE(SUM(CASE
                    WHEN DATE(COALESCE(required_date, order_date)) <= CURDATE() THEN 1 ELSE 0 END), 0) AS window_today_count,
                COALESCE(SUM(CASE
                    WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 ELSE 0 END), 0) AS window_3day_count,
                COALESCE(SUM(CASE
                    WHEN DATE(COALESCE(required_date, order_date)) <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS window_7day_count
             FROM daily_orders
             WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')"
        );
        if (is_array($row)) {
            $summary = array_merge($summary, $row);
        }
        $hotlist = DB::fetchAll(
            "SELECT d.id, d.customer_name, d.required_date, d.order_date, d.qty,
                    COALESCE(d.shortage_qty, 0) AS shortage_qty,
                    COALESCE(d.coverage_pct, 0) AS coverage_pct,
                    COALESCE(d.coverage_status, '') AS coverage_status,
                    p.parts_number, p.parts_name
             FROM daily_orders d
             INNER JOIN products p ON p.id = d.product_id
             WHERE LOWER(COALESCE(d.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND d.qty > 0
               AND (
                 COALESCE(d.shortage_qty, 0) / NULLIF(d.qty, 0) >= 0.50
                 OR COALESCE(d.coverage_pct, 0) < 25
               )
             ORDER BY COALESCE(d.required_date, d.order_date) ASC, d.shortage_qty DESC
             LIMIT 20"
        ) ?: [];
    } catch (\Throwable $e) {
        // fallback empty
    }
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('coverage.coverage_report_title')) ?> </h2>
      <div class="muted">Module-owned demand coverage health, shortage pressure, and critical-risk hotlist.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/manufacturing/coverage">&larr; Coverage Analytics</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('coverage.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block">
        <a class="btn" href="/apps/manufacturing/coverage/export"> <?= e($tt('coverage.open_link')) ?> </a>
      </div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('coverage.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('coverage.coverage_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted"> <?= e($tt('coverage.open_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['open_orders'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Demand Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['demand_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Shortage Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['shortage_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Avg Coverage %</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['coverage_pct'], 1) ?>%</strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Risk Bands</h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Critical</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['critical_orders_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Low Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['low_coverage_orders_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Fully Covered</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['fully_covered_orders_count'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Due Window</h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Due Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['window_today_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Due &le; 3d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['window_3day_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Due &le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['window_7day_count'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Critical / Low Coverage Hotlist</h3>
  <?php if (!$hotlist): ?>
    <p class="muted u-style-1169661891">No orders at critical shortage or below 25% coverage.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Order</th><th>Customer</th><th>Part</th><th>Required</th><th class="u-style-54c2afb7ba">Qty</th><th class="u-style-54c2afb7ba">Shortage</th><th class="u-style-54c2afb7ba">Cov %</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($hotlist as $r): ?>
          <tr>
            <td><a href="/apps/manufacturing/daily-orders/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)($r['customer_name'] ?? '')) ?></td>
            <td><?= e((string)($r['parts_number'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['parts_name'] ?? '')) ?></span></td>
            <td><?= e((string)($r['required_date'] ?? $r['order_date'] ?? '')) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['shortage_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['coverage_pct'], 1) ?>%</td>
            <td><?= e((string)$r['coverage_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
