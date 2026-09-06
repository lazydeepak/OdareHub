<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.assembly_entries.execution');

$summary = ['entries'=>0, 'planned'=>0.0, 'completed'=>0.0, 'rejected'=>0.0];
$byStatus = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS entries,
                    COALESCE(SUM(planned_qty),0) AS planned,
                    COALESCE(SUM(completed_qty),0) AS completed,
                    COALESCE(SUM(rejected_qty),0) AS rejected
             FROM mfg_assembly_entries
             WHERE assembly_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT status, COUNT(*) AS n
             FROM mfg_assembly_entries
             WHERE assembly_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY status ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT ae.id, ae.assembly_date, ae.planned_qty, ae.completed_qty, ae.rejected_qty, ae.status,
                    p.parts_number, p.parts_name
             FROM mfg_assembly_entries ae
             INNER JOIN products p ON p.id = ae.product_id
             ORDER BY ae.assembly_date DESC, ae.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('assembly_entries.assembly_entries_report_title')) ?> </h2>
      <div class="muted">Module-owned assembly execution snapshot (planned, completed, rejected).</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/assembly-entries">&larr; Assembly Entries</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong><?= e($tt('assembly_entries.report_key_label')) ?>:</strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/assembly-entries/export"> <?= e($tt('assembly_entries.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('assembly_entries.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('assembly_entries.30_day_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['entries'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Planned</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['planned']) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('assembly_entries.completed_column')) ?> </div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['completed']) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('assembly_entries.rejected_column')) ?> </div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['rejected']) ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('assembly_entries.by_status_title')) ?> </h3>
  <?php if (!$byStatus): ?><p class="muted u-style-1169661891">No recent assembly entries.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table u-style-0fd8744d8e"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
      <?php foreach ($byStatus as $r): ?>
        <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recent Entries</h3>
  <?php if (!$recent): ?><p class="muted u-style-1169661891">No recent entries recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Date</th><th>Part</th><th class="u-style-54c2afb7ba">Planned</th><th class="u-style-54c2afb7ba"> <?= e($tt('assembly_entries.completed_column')) ?> </th><th class="u-style-54c2afb7ba"> <?= e($tt('assembly_entries.rejected_column')) ?> </th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['assembly_date']) ?></td>
            <td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['planned_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['completed_qty']) ?></td>
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
