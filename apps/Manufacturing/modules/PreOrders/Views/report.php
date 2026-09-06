<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.pre_orders.overview');

$summary = ['total'=>0,'planned'=>0.0,'balance'=>0.0,'due_30d'=>0];
$byType = [];
$byPriority = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(planned_qty),0) AS planned,
                    COALESCE(SUM(balance_qty),0) AS balance,
                    COALESCE(SUM(CASE WHEN required_date IS NOT NULL AND required_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END),0) AS due_30d
             FROM pre_orders"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byType = DB::fetchAll(
            "SELECT COALESCE(NULLIF(forecast_type,''),'(none)') AS forecast_type, COUNT(*) AS n
             FROM pre_orders GROUP BY forecast_type ORDER BY n DESC"
        ) ?: [];

        $byPriority = DB::fetchAll(
            "SELECT COALESCE(NULLIF(planning_priority,''),'(none)') AS planning_priority, COUNT(*) AS n
             FROM pre_orders GROUP BY planning_priority ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT po.id, po.forecast_type, po.planning_priority, po.planned_qty, po.balance_qty,
                    po.required_date, p.parts_number, p.parts_name
             FROM pre_orders po
             INNER JOIN products p ON p.id = po.product_id
             ORDER BY po.required_date ASC, po.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('pre_orders.pre_orders_report_title')) ?> </h2>
      <div class="muted">Module-owned forecast demand pipeline (planned, balance, due window).</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/pre-orders">&larr; Pre-Orders</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('pre_orders.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/pre-orders/export"> <?= e($tt('pre_orders.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('pre_orders.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('pre_orders.pre_order_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Pre-Orders</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Planned Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['planned']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Balance Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['balance']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Due &le; 30d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['due_30d'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Type &amp; Priority</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">By Forecast Type</h4>
      <?php if (!$byType): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byType as $r): ?>
            <tr><td><?= e((string)$r['forecast_type']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">By Priority</h4>
      <?php if (!$byPriority): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Priority</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byPriority as $r): ?>
            <tr><td><?= e((string)$r['planning_priority']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recent Pre-Orders</h3>
  <?php if (!$recent): ?><p class="muted u-style-1169661891">No pre-orders recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Required</th><th>Part</th><th>Type</th><th>Priority</th><th class="u-style-54c2afb7ba">Planned</th><th class="u-style-54c2afb7ba">Balance</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="/pre-orders/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)($r['required_date'] ?? '')) ?></td>
            <td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td>
            <td><?= e((string)$r['forecast_type']) ?></td>
            <td><?= e((string)$r['planning_priority']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['planned_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['balance_qty']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
