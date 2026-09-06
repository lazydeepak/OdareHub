<?php
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.production_queue.overview');

// Production queue is driven by production_plans scheduled on/near today.
$summary = [
    'queued' => 0, 'today' => 0, 'tomorrow' => 0, 'week' => 0,
    'planned_qty' => 0.0, 'shortage_qty' => 0.0,
    'low_cov' => 0, 'full_cov' => 0,
    'draft' => 0, 'approved' => 0,
];
$byStatus = [];
$byMachine = [];
$today = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS queued,
                    COALESCE(SUM(CASE WHEN plan_date = CURDATE() THEN 1 ELSE 0 END),0) AS today,
                    COALESCE(SUM(CASE WHEN plan_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) THEN 1 ELSE 0 END),0) AS tomorrow,
                    COALESCE(SUM(CASE WHEN plan_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS week,
                    COALESCE(SUM(planned_qty),0) AS planned_qty,
                    COALESCE(SUM(COALESCE(shortage_qty, 0)),0) AS shortage_qty,
                    COALESCE(SUM(CASE WHEN COALESCE(coverage_pct,0) < 50 THEN 1 ELSE 0 END),0) AS low_cov,
                    COALESCE(SUM(CASE WHEN COALESCE(shortage_qty,0) <= 0 THEN 1 ELSE 0 END),0) AS full_cov,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(approval_status,''))='draft' THEN 1 ELSE 0 END),0) AS draft,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(approval_status,''))='approved' THEN 1 ELSE 0 END),0) AS approved
             FROM production_plans
             WHERE LOWER(COALESCE(status,'')) NOT IN ('closed','completed','cancelled','canceled')
               AND plan_date >= CURDATE()
               AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(status,''),'(none)') AS status, COUNT(*) AS n
             FROM production_plans
             WHERE plan_date >= CURDATE() AND plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             GROUP BY status ORDER BY n DESC"
        ) ?: [];

        $byMachine = DB::fetchAll(
            "SELECT COALESCE(m.machine_no,'(unassigned)') AS machine_no, m.machine_name, COUNT(*) AS n,
                    COALESCE(SUM(pp.planned_qty),0) AS qty
             FROM production_plans pp
             LEFT JOIN machines m ON m.id = pp.machine_id
             WHERE pp.plan_date >= CURDATE() AND pp.plan_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
               AND LOWER(COALESCE(pp.status,'')) NOT IN ('closed','completed','cancelled','canceled')
             GROUP BY pp.machine_id ORDER BY n DESC LIMIT 15"
        ) ?: [];

        $today = DB::fetchAll(
            "SELECT pp.id, pp.plan_date, pp.sequence_no, pp.status, pp.planned_qty,
                    COALESCE(pp.shortage_qty,0) AS shortage_qty,
                    COALESCE(pp.coverage_pct,0) AS coverage_pct,
                    p.parts_number, p.parts_name,
                    m.machine_no, m.machine_name
             FROM production_plans pp
             INNER JOIN products p ON p.id = pp.product_id
             LEFT JOIN machines m ON m.id = pp.machine_id
             WHERE pp.plan_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
               AND LOWER(COALESCE(pp.status,'')) NOT IN ('closed','completed','cancelled','canceled')
             ORDER BY pp.plan_date ASC, pp.sequence_no ASC, pp.id ASC LIMIT 30"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891">Production Queue Report</h2>
      <div class="muted">Module-owned queue pressure: scheduled plan volume, machine load, and today/tomorrow focus list.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/manufacturing/production-queue">&larr; Production Queue</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong>Report Key:</strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/production-queue/export">Open Export</a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891">This report is unavailable because its owner module is inactive or not registered.</p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40">Queue Snapshot (next 14 days)</h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Queued Plans</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['queued'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['today'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Tomorrow</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['tomorrow'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['week'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Planned Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['planned_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Shortage Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['shortage_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Low Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['low_cov'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Full Coverage</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['full_cov'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Draft</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['draft'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Approved</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['approved'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Distribution</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">By Status</h4>
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
      <h4 class="u-style-ad7f18b19e">By Machine</h4>
      <?php if (!$byMachine): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Machine</th><th class="u-style-54c2afb7ba">Plans</th><th class="u-style-54c2afb7ba">Qty</th></tr></thead><tbody>
          <?php foreach ($byMachine as $r): ?>
            <tr>
              <td><?= e((string)$r['machine_no']) ?> <span class="muted"><?= e((string)($r['machine_name'] ?? '')) ?></span></td>
              <td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td>
              <td class="u-style-54c2afb7ba"><?= number_format((float)$r['qty']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Focus List (today &amp; next 2 days)</h3>
  <?php if (!$today): ?><p class="muted u-style-1169661891">No plans scheduled.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Plan #</th><th>Date</th><th>Seq</th><th>Machine</th><th>Part</th><th class="u-style-54c2afb7ba">Qty</th><th class="u-style-54c2afb7ba">Shortage</th><th class="u-style-54c2afb7ba">Cov %</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($today as $r): ?>
          <tr>
            <td><a href="/production-plans/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)$r['plan_date']) ?></td>
            <td><?= (int)$r['sequence_no'] ?></td>
            <td><?= e((string)($r['machine_no'] ?? '')) ?> <span class="muted"><?= e((string)($r['machine_name'] ?? '')) ?></span></td>
            <td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td>
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
