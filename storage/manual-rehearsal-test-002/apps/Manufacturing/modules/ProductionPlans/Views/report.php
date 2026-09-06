<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.production_plans.next_two_weeks');

$summary = [
    'open_plans' => 0,
    'planned_qty' => 0.0,
    'total_shortage' => 0.0,
    'plans_today' => 0,
    'plans_7day' => 0,
    'plans_14day' => 0,
    'low_coverage' => 0,
    'full_coverage' => 0,
];
$byStatus = [];
$upcoming = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_plans,
                COALESCE(SUM(planned_qty), 0) AS planned_qty,
                COALESCE(SUM(COALESCE(shortage_qty, 0)), 0) AS total_shortage,
                COALESCE(SUM(CASE WHEN plan_date = CURDATE() THEN 1 ELSE 0 END), 0) AS plans_today,
                COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS plans_7day,
                COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END), 0) AS plans_14day,
                COALESCE(SUM(CASE WHEN COALESCE(coverage_pct, 0) < 50 THEN 1 ELSE 0 END), 0) AS low_coverage,
                COALESCE(SUM(CASE WHEN COALESCE(shortage_qty, 0) <= 0 THEN 1 ELSE 0 END), 0) AS full_coverage
             FROM production_plans
             WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND plan_date >= CURDATE()
               AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(status, ''), '(none)') AS status, COUNT(*) AS n
             FROM production_plans
             WHERE plan_date >= CURDATE() AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             GROUP BY status ORDER BY n DESC"
        ) ?: [];

        $upcoming = DB::fetchAll(
            "SELECT pp.id, pp.plan_date, pp.planned_qty,
                    COALESCE(pp.shortage_qty, 0) AS shortage_qty,
                    COALESCE(pp.coverage_pct, 0) AS coverage_pct,
                    pp.status, pp.sequence_no,
                    p.parts_number, p.parts_name,
                    m.machine_no, m.machine_name
             FROM production_plans pp
             INNER JOIN products p ON p.id = pp.product_id
             LEFT JOIN machines m ON m.id = pp.machine_id
             WHERE LOWER(COALESCE(pp.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND pp.plan_date >= CURDATE()
               AND pp.plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY pp.plan_date ASC, pp.sequence_no ASC, pp.id ASC
             LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('production_plans.production_plan_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('production_plans.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/production-plans">&larr; Production Plans</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('production_plans.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/production-plans/export"> <?= e($tt('production_plans.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('production_plans.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40">Next 14-Day Snapshot</h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted"> <?= e($tt('production_plans.open_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['open_plans'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Planned Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['planned_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Shortage Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_shortage']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans_today'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans_7day'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 14d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans_14day'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Low Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['low_coverage'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Full Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['full_coverage'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('production_plans.status_distribution_title')) ?> </h3>
  <?php if (!$byStatus): ?>
    <p class="muted u-style-1169661891">No plans in the 14-day window.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Status</th><th class="u-style-54c2afb7ba">Count</th></tr></thead>
      <tbody>
        <?php foreach ($byStatus as $r): ?>
          <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Upcoming Plans</h3>
  <?php if (!$upcoming): ?>
    <p class="muted u-style-1169661891">No upcoming plans in the next 14 days.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Plan #</th><th>Plan Date</th><th>Machine</th><th>Part</th><th class="u-style-54c2afb7ba">Qty</th><th class="u-style-54c2afb7ba">Shortage</th><th class="u-style-54c2afb7ba">Cov %</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
          <tr>
            <td><a href="/production-plans/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)$r['plan_date']) ?></td>
            <td><?= e((string)($r['machine_no'] ?? '')) ?> <span class="muted"><?= e((string)($r['machine_name'] ?? '')) ?></span></td>
            <td><?= e((string)($r['parts_number'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['parts_name'] ?? '')) ?></span></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['planned_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['shortage_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['coverage_pct'], 1) ?>%</td>
            <td><?= e((string)$r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
