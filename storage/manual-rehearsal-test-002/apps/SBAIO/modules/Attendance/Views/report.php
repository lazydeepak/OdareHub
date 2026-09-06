<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.attendance.overview');

$summary = ['days'=>0,'present'=>0,'absent'=>0,'manual'=>0,'staff'=>0];
$byMarker = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS days,
                    COALESCE(SUM(CASE WHEN work_start_at IS NOT NULL THEN 1 ELSE 0 END),0) AS present,
                    COALESCE(SUM(CASE WHEN work_start_at IS NULL THEN 1 ELSE 0 END),0) AS absent,
                    COALESCE(SUM(CASE WHEN is_manual_correction=1 THEN 1 ELSE 0 END),0) AS manual,
                    COUNT(DISTINCT staff_id) AS staff
             FROM sbaio_attendance_daily
             WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byMarker = DB::fetchAll(
            "SELECT COALESCE(NULLIF(day_marker,''),'(none)') AS day_marker, COUNT(*) AS n
             FROM sbaio_attendance_daily
             WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY day_marker ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT ad.id, ad.attendance_date, ad.day_marker, ad.work_start_at, ad.work_end_at,
                    ad.is_manual_correction, s.full_name, s.employee_code
             FROM sbaio_attendance_daily ad
             LEFT JOIN sbaio_staff s ON s.id = ad.staff_id
             ORDER BY ad.attendance_date DESC, ad.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('attendance.attendance_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('attendance.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/attendance">&larr; Attendance</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('attendance.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/attendance/export"> <?= e($tt('attendance.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('attendance.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('attendance.30_day_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Records</div><div class="u-stat-num"><strong><?= (int)$summary['days'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Present</div><div class="u-stat-num"><strong><?= (int)$summary['present'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Absent</div><div class="u-stat-num"><strong><?= (int)$summary['absent'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Manual Corrections</div><div class="u-stat-num"><strong><?= (int)$summary['manual'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Unique Staff</div><div class="u-stat-num"><strong><?= (int)$summary['staff'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">By Day Marker</h3>
  <?php if (!$byMarker): ?><p class="muted u-m-0">No records yet.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table table-narrow"><thead><tr><th>Marker</th><th class="u-text-right">Count</th></tr></thead><tbody>
      <?php foreach ($byMarker as $r): ?>
        <tr><td><?= e((string)$r['day_marker']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Attendance</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No attendance records recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Date</th><th>Staff</th><th>Marker</th><th>Start</th><th>End</th><th>Manual</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['attendance_date']) ?></td>
            <td><?= e((string)($r['employee_code'] ?? '')) ?> <span class="muted"><?= e((string)($r['full_name'] ?? '')) ?></span></td>
            <td><?= e((string)$r['day_marker']) ?></td>
            <td><?= e((string)($r['work_start_at'] ?? '')) ?></td>
            <td><?= e((string)($r['work_end_at'] ?? '')) ?></td>
            <td><?= ((int)$r['is_manual_correction'] === 1) ? 'Yes' : '' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
