<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.qc_plans.range');

$summary = [
    'open_plans' => 0,
    'planned_qty' => 0.0,
    'est_minutes' => 0,
    'plans_today' => 0,
    'plans_7day' => 0,
    'plans_14day' => 0,
    'high_priority' => 0,
];
$byStatus = [];
$byPriority = [];
$upcoming = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_plans,
                COALESCE(SUM(planned_qty), 0) AS planned_qty,
                COALESCE(SUM(estimated_time_minutes), 0) AS est_minutes,
                COALESCE(SUM(CASE WHEN plan_date = CURDATE() THEN 1 ELSE 0 END), 0) AS plans_today,
                COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS plans_7day,
                COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END), 0) AS plans_14day,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(priority, '')) IN ('high','urgent','critical') THEN 1 ELSE 0 END), 0) AS high_priority
             FROM qc_plans
             WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND plan_date >= CURDATE()
               AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(status, ''), '(none)') AS status, COUNT(*) AS n
             FROM qc_plans
             WHERE plan_date >= CURDATE() AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             GROUP BY status ORDER BY n DESC"
        ) ?: [];

        $byPriority = DB::fetchAll(
            "SELECT COALESCE(NULLIF(priority, ''), '(none)') AS priority, COUNT(*) AS n
             FROM qc_plans
             WHERE plan_date >= CURDATE() AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             GROUP BY priority ORDER BY n DESC"
        ) ?: [];

        $upcoming = DB::fetchAll(
            "SELECT q.id, q.plan_date, q.required_date, q.planned_qty,
                    q.estimated_time_minutes, q.priority, q.status, q.assigned_to,
                    p.parts_number, p.parts_name
             FROM qc_plans q
             INNER JOIN products p ON p.id = q.product_id
             WHERE LOWER(COALESCE(q.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'canceled')
               AND q.plan_date >= CURDATE()
               AND q.plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             ORDER BY q.plan_date ASC, FIELD(LOWER(q.priority),'urgent','critical','high','normal','low') ASC, q.id ASC
             LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('q_c_plans.qc_plan_report_title')) ?> </h2>
      <div class="muted">Module-owned QC plan workload, priority mix, and upcoming schedule.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/qc-plans">&larr; QC Plans</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('q_c_plans.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/qc-plans/export"> <?= e($tt('q_c_plans.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('q_c_plans.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40">Next 14-Day Snapshot</h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted"> <?= e($tt('q_c_plans.open_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['open_plans'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Planned Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['planned_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Est. Minutes</div><div class="u-style-b9199e22b2"><strong><?= number_format((int)$summary['est_minutes']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans_today'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans_7day'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 14d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans_14day'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">High Priority</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['high_priority'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Status &amp; Priority</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e"> <?= e($tt('q_c_plans.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
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
            <tr><td><?= e((string)$r['priority']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Upcoming Plans</h3>
  <?php if (!$upcoming): ?>
    <p class="muted u-style-1169661891">No upcoming QC plans in the next 14 days.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Plan #</th><th>Plan Date</th><th>Required</th><th>Part</th><th class="u-style-54c2afb7ba">Qty</th><th class="u-style-54c2afb7ba">Est. min</th><th>Priority</th><th>Status</th><th>Assigned</th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
          <tr>
            <td><a href="/qc-plans/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)$r['plan_date']) ?></td>
            <td><?= e((string)($r['required_date'] ?? '')) ?></td>
            <td><?= e((string)($r['parts_number'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['parts_name'] ?? '')) ?></span></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['planned_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((int)$r['estimated_time_minutes']) ?></td>
            <td><?= e((string)$r['priority']) ?></td>
            <td><?= e((string)$r['status']) ?></td>
            <td><?= e((string)($r['assigned_to'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
