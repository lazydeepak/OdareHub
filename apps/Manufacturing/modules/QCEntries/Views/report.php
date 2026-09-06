<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.qcentries.overview');

$summary = [
    'entries' => 0, 'checked' => 0.0, 'passed' => 0.0, 'failed' => 0.0,
    'today' => 0, 'week' => 0, 'open' => 0, 'approved' => 0,
];
$byStatus = [];
$byType = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS entries,
                    COALESCE(SUM(checked_qty),0) AS checked,
                    COALESCE(SUM(pass_qty),0) AS passed,
                    COALESCE(SUM(fail_qty),0) AS failed,
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END),0) AS today,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS week,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(status,''))='open' THEN 1 ELSE 0 END),0) AS open,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(approval_status,''))='approved' THEN 1 ELSE 0 END),0) AS approved
             FROM qc_entries
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(status,''),'(none)') AS status, COUNT(*) AS n
             FROM qc_entries
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY status ORDER BY n DESC"
        ) ?: [];

        $byType = DB::fetchAll(
            "SELECT COALESCE(NULLIF(qc_type,''),'(none)') AS qc_type, COUNT(*) AS n
             FROM qc_entries
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
             GROUP BY qc_type ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT qe.id, qe.qc_type, qe.checked_qty, qe.pass_qty, qe.fail_qty, qe.status, qe.approval_status,
                    qe.created_at, p.parts_number, p.parts_name
             FROM qc_entries qe
             INNER JOIN products p ON p.id = qe.product_id
             ORDER BY qe.created_at DESC, qe.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}

$totalChecked = (float)$summary['checked'];
$passRate = $totalChecked > 0 ? (((float)$summary['passed']) / $totalChecked * 100.0) : 0.0;
$failRate = $totalChecked > 0 ? (((float)$summary['failed']) / $totalChecked * 100.0) : 0.0;
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('q_c_entries.qc_entries_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('q_c_entries.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/qc-entries">&larr; QC Entries</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('q_c_entries.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/qc-entries/export"> <?= e($tt('q_c_entries.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('q_c_entries.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('q_c_entries.14_day_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['entries'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Checked</div><div class="u-style-b9199e22b2"><strong><?= number_format($totalChecked) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Passed</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['passed']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Failed</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['failed']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Pass %</div><div class="u-style-b9199e22b2"><strong><?= number_format($passRate, 1) ?>%</strong></div></div>
    <div class="ui-block"><div class="muted">Fail %</div><div class="u-style-b9199e22b2"><strong><?= number_format($failRate, 1) ?>%</strong></div></div>
    <div class="ui-block"><div class="muted">Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['today'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('common.open_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['open'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('q_c_entries.approved_action')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['approved'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Status &amp; Type</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e"> <?= e($tt('q_c_entries.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">By QC Type</h4>
      <?php if (!$byType): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byType as $r): ?>
            <tr><td><?= e((string)$r['qc_type']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recent Entries</h3>
  <?php if (!$recent): ?><p class="muted u-style-1169661891">No recent QC entries.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Entry #</th><th>Date</th><th>Part</th><th>Type</th><th class="u-style-54c2afb7ba">Checked</th><th class="u-style-54c2afb7ba">Pass</th><th class="u-style-54c2afb7ba">Fail</th><th> <?= e($tt('common.status_label')) ?> </th><th>Approval</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="/qc-entries/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)$r['created_at']) ?></td>
            <td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td>
            <td><?= e((string)$r['qc_type']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['checked_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['pass_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['fail_qty']) ?></td>
            <td><?= e((string)$r['status']) ?></td>
            <td><?= e((string)$r['approval_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
