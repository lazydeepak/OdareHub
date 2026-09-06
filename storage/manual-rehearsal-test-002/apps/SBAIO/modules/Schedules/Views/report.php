<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.schedules.overview');

$summary = ['templates'=>0,'assignments'=>0,'upcoming'=>0,'this_week'=>0,'minutes'=>0];
$byStatus = [];
$byJob = [];
$upcoming = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                 (SELECT COUNT(*) FROM sbaio_schedule_templates WHERE is_active=1) AS templates,
                 (SELECT COUNT(*) FROM sbaio_schedule_assignments) AS assignments,
                 (SELECT COUNT(*) FROM sbaio_schedule_assignments WHERE schedule_date >= CURDATE()) AS upcoming,
                 (SELECT COUNT(*) FROM sbaio_schedule_assignments WHERE schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS this_week,
                 (SELECT COALESCE(SUM(scheduled_minutes),0) FROM sbaio_schedule_assignments WHERE schedule_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) AS minutes"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(assignment_status,''),'(none)') AS assignment_status, COUNT(*) AS n
             FROM sbaio_schedule_assignments
             WHERE schedule_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             GROUP BY assignment_status ORDER BY n DESC"
        ) ?: [];

        $byJob = DB::fetchAll(
            "SELECT COALESCE(NULLIF(job_label,''),'(none)') AS job_label, COUNT(*) AS n
             FROM sbaio_schedule_assignments
             WHERE schedule_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             GROUP BY job_label ORDER BY n DESC LIMIT 15"
        ) ?: [];

        $upcoming = DB::fetchAll(
            "SELECT sa.id, sa.schedule_date, sa.expected_start_time, sa.expected_end_time, sa.job_label,
                    sa.assignment_status, sa.legacy_name_ref, s.full_name, s.employee_code
             FROM sbaio_schedule_assignments sa
             LEFT JOIN sbaio_staff s ON s.id = sa.staff_id
             WHERE sa.schedule_date >= CURDATE()
             ORDER BY sa.schedule_date ASC, sa.expected_start_time ASC LIMIT 30"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('schedules.schedules_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('schedules.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/schedules">&larr; Schedules</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('schedules.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/schedules/export"> <?= e($tt('schedules.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('schedules.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('schedules.schedule_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted"> <?= e($tt('schedules.active_templates_message')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['templates'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Assignments</div><div class="u-stat-num"><strong><?= (int)$summary['assignments'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Upcoming</div><div class="u-stat-num"><strong><?= (int)$summary['upcoming'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">This Week</div><div class="u-stat-num"><strong><?= (int)$summary['this_week'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Week Minutes</div><div class="u-stat-num"><strong><?= number_format((int)$summary['minutes']) ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution (&plusmn;30d)</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('schedules.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['assignment_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">Top Jobs</h4>
      <?php if (!$byJob): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Job</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byJob as $r): ?>
            <tr><td><?= e((string)$r['job_label']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Upcoming Assignments</h3>
  <?php if (!$upcoming): ?><p class="muted u-m-0">No upcoming assignments.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Date</th><th>Staff</th><th>Start</th><th>End</th><th>Job</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['schedule_date']) ?></td>
            <td><?= e((string)($r['employee_code'] ?? '')) ?> <span class="muted"><?= e((string)($r['full_name'] ?? $r['legacy_name_ref'] ?? '')) ?></span></td>
            <td><?= e((string)($r['expected_start_time'] ?? '')) ?></td>
            <td><?= e((string)($r['expected_end_time'] ?? '')) ?></td>
            <td><?= e((string)($r['job_label'] ?? '')) ?></td>
            <td><?= e((string)$r['assignment_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
