<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.production_entries.overview');

$summary = [
    'entries' => 0, 'produced' => 0.0, 'good' => 0.0, 'rejected' => 0.0,
    'today' => 0, 'week' => 0, 'draft' => 0, 'approved' => 0,
];
$byStatus = [];
$byShift = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS entries,
                    COALESCE(SUM(produced_qty),0) AS produced,
                    COALESCE(SUM(good_qty),0) AS good,
                    COALESCE(SUM(rejected_qty),0) AS rejected,
                    COALESCE(SUM(CASE WHEN production_date = CURDATE() THEN 1 ELSE 0 END),0) AS today,
                    COALESCE(SUM(CASE WHEN production_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS week,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,''))='draft' THEN 1 ELSE 0 END),0) AS draft,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,''))='approved' THEN 1 ELSE 0 END),0) AS approved
             FROM production_entries
             WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(status,''),'(none)') AS status, COUNT(*) AS n
             FROM production_entries
             WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY status ORDER BY n DESC"
        ) ?: [];

        $byShift = DB::fetchAll(
            "SELECT COALESCE(NULLIF(shift,''),'(none)') AS shift, COUNT(*) AS n
             FROM production_entries
             WHERE production_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY shift ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT pe.id, pe.production_date, pe.shift, pe.produced_qty, pe.good_qty, pe.rejected_qty, pe.status,
                    p.parts_number, p.parts_name,
                    m.machine_no, m.machine_name
             FROM production_entries pe
             INNER JOIN products p ON p.id = pe.product_id
             LEFT JOIN machines m ON m.id = pe.machine_id
             ORDER BY pe.production_date DESC, pe.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('production_entries.production_entries_report_title')) ?> </h2>
      <div class="muted">Module-owned 14-day production execution (produced, good, rejected) with shift and status breakdowns.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/production-entries">&larr; Production Entries</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('production_entries.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/production-entries/export"> <?= e($tt('production_entries.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('production_entries.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('production_entries.14_day_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['entries'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Produced</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['produced']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Good</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['good']) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('production_entries.rejected_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['rejected']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['today'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['week'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Draft</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['draft'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('production_entries.approved_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['approved'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Status &amp; Shift</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e"> <?= e($tt('production_entries.by_status_title')) ?> </h4>
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
      <h4 class="u-style-ad7f18b19e">By Shift</h4>
      <?php if (!$byShift): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Shift</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byShift as $r): ?>
            <tr><td><?= e((string)$r['shift']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recent Entries</h3>
  <?php if (!$recent): ?><p class="muted u-style-1169661891">No recent production entries.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Entry #</th><th>Date</th><th>Shift</th><th>Part</th><th>Machine</th><th class="u-style-54c2afb7ba">Produced</th><th class="u-style-54c2afb7ba">Good</th><th class="u-style-54c2afb7ba"> <?= e($tt('production_entries.rejected_action')) ?> </th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="/production-entries/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)$r['production_date']) ?></td>
            <td><?= e((string)$r['shift']) ?></td>
            <td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td>
            <td><?= e((string)($r['machine_no'] ?? '')) ?> <span class="muted"><?= e((string)($r['machine_name'] ?? '')) ?></span></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['produced_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['good_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['rejected_qty']) ?></td>
            <td><?= e((string)$r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
