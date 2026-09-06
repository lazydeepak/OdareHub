<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.timecards.overview');

$summary = ['periods'=>0,'days'=>0,'worked_minutes'=>0,'ot_minutes'=>0,'approved'=>0];
$byStatus = [];
$byDayStatus = [];
$recentPeriods = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                 (SELECT COUNT(*) FROM sbaio_timecard_periods) AS periods,
                 (SELECT COUNT(*) FROM sbaio_timecards_daily) AS days,
                 (SELECT COALESCE(SUM(worked_minutes),0) FROM sbaio_timecards_daily) AS worked_minutes,
                 (SELECT COALESCE(SUM(overtime_minutes),0) FROM sbaio_timecards_daily) AS ot_minutes,
                 (SELECT COUNT(*) FROM sbaio_timecard_periods WHERE LOWER(COALESCE(approval_status,''))='approved') AS approved"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(approval_status,''),'(none)') AS approval_status, COUNT(*) AS n
             FROM sbaio_timecard_periods GROUP BY approval_status ORDER BY n DESC"
        ) ?: [];

        $byDayStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(day_status,''),'(none)') AS day_status, COUNT(*) AS n
             FROM sbaio_timecards_daily
             WHERE work_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY day_status ORDER BY n DESC"
        ) ?: [];

        $recentPeriods = DB::fetchAll(
            "SELECT tp.id, tp.period_key, tp.period_start, tp.period_end, tp.total_days,
                    tp.total_worked_minutes, tp.overtime_minutes, tp.exception_count, tp.approval_status,
                    tp.legacy_name_ref, s.full_name, s.employee_code
             FROM sbaio_timecard_periods tp
             LEFT JOIN sbaio_staff s ON s.id = tp.staff_id
             ORDER BY tp.period_start DESC, tp.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('timecards.timecards_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('timecards.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/timecards">&larr; Timecards</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('timecards.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/timecards/export"> <?= e($tt('timecards.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('timecards.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('timecards.timecard_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Periods</div><div class="u-stat-num"><strong><?= (int)$summary['periods'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Daily Rows</div><div class="u-stat-num"><strong><?= (int)$summary['days'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Worked Hours</div><div class="u-stat-num"><strong><?= number_format(((int)$summary['worked_minutes']) / 60, 1) ?></strong></div></div>
    <div class="ui-block"><div class="muted">OT Hours</div><div class="u-stat-num"><strong><?= number_format(((int)$summary['ot_minutes']) / 60, 1) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('timecards.approved_periods_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['approved'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">Period Approval</h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Approval</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['approval_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('timecards.day_status_30d_title')) ?> </h4>
      <?php if (!$byDayStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byDayStatus as $r): ?>
            <tr><td><?= e((string)$r['day_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Periods</h3>
  <?php if (!$recentPeriods): ?><p class="muted u-m-0">No timecard periods recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Period</th><th>Start</th><th>End</th><th>Staff</th><th class="u-text-right">Days</th><th class="u-text-right">Worked (min)</th><th class="u-text-right">OT</th><th class="u-text-right">Excp</th><th>Approval</th></tr></thead>
      <tbody>
        <?php foreach ($recentPeriods as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['period_key'] ?? '')) ?></td>
            <td><?= e((string)$r['period_start']) ?></td>
            <td><?= e((string)$r['period_end']) ?></td>
            <td><?= e((string)($r['employee_code'] ?? '')) ?> <span class="muted"><?= e((string)($r['full_name'] ?? $r['legacy_name_ref'] ?? '')) ?></span></td>
            <td class="u-text-right"><?= (int)$r['total_days'] ?></td>
            <td class="u-text-right"><?= number_format((int)$r['total_worked_minutes']) ?></td>
            <td class="u-text-right"><?= (int)$r['overtime_minutes'] ?></td>
            <td class="u-text-right"><?= (int)$r['exception_count'] ?></td>
            <td><?= e((string)$r['approval_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
