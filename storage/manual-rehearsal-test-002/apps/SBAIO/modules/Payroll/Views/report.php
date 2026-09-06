<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.payroll.overview');

$summary = ['runs'=>0,'records'=>0,'gross'=>0.0,'net'=>0.0,'deductions'=>0.0,'locked'=>0];
$byStatus = [];
$recentRuns = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                 (SELECT COUNT(*) FROM sbaio_payroll_runs) AS runs,
                 (SELECT COUNT(*) FROM sbaio_payroll_records) AS records,
                 (SELECT COALESCE(SUM(gross_amount),0) FROM sbaio_payroll_records) AS gross,
                 (SELECT COALESCE(SUM(net_amount),0) FROM sbaio_payroll_records) AS net,
                 (SELECT COALESCE(SUM(total_deductions_amount),0) FROM sbaio_payroll_records) AS deductions,
                 (SELECT COUNT(*) FROM sbaio_payroll_runs WHERE locked_at IS NOT NULL) AS locked"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(run_status,''),'(none)') AS run_status, COUNT(*) AS n
             FROM sbaio_payroll_runs GROUP BY run_status ORDER BY n DESC"
        ) ?: [];

        $recentRuns = DB::fetchAll(
            "SELECT id, period_key, period_start, period_end, payslip_variant, run_status, approval_status, locked_at
             FROM sbaio_payroll_runs ORDER BY period_start DESC, id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('payroll.payroll_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('payroll.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/payroll">&larr; Payroll</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('payroll.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/payroll/export"> <?= e($tt('payroll.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('payroll.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('payroll.payroll_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Runs</div><div class="u-stat-num"><strong><?= (int)$summary['runs'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Records</div><div class="u-stat-num"><strong><?= (int)$summary['records'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Gross Total</div><div class="u-stat-num"><strong><?= number_format((float)$summary['gross']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Net Total</div><div class="u-stat-num"><strong><?= number_format((float)$summary['net']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Deductions</div><div class="u-stat-num"><strong><?= number_format((float)$summary['deductions']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Locked Runs</div><div class="u-stat-num"><strong><?= (int)$summary['locked'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('payroll.runs_by_status_title')) ?> </h3>
  <?php if (!$byStatus): ?><p class="muted u-m-0">No payroll runs yet.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table table-narrow"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-text-right">Count</th></tr></thead><tbody>
      <?php foreach ($byStatus as $r): ?>
        <tr><td><?= e((string)$r['run_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Payroll Runs</h3>
  <?php if (!$recentRuns): ?><p class="muted u-m-0">No payroll runs recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Period</th><th>Start</th><th>End</th><th>Variant</th><th> <?= e($tt('common.status_label')) ?> </th><th>Approval</th><th>Locked</th></tr></thead>
      <tbody>
        <?php foreach ($recentRuns as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['period_key'] ?? '')) ?></td>
            <td><?= e((string)($r['period_start'] ?? '')) ?></td>
            <td><?= e((string)($r['period_end'] ?? '')) ?></td>
            <td><?= e((string)($r['payslip_variant'] ?? '')) ?></td>
            <td><?= e((string)$r['run_status']) ?></td>
            <td><?= e((string)$r['approval_status']) ?></td>
            <td><?= !empty($r['locked_at']) ? 'Yes' : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
