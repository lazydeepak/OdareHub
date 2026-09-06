<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.leave.overview');

$summary = ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'upcoming'=>0];
$byStatus = [];
$byType = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(leave_status,''))='pending' THEN 1 ELSE 0 END),0) AS pending,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(leave_status,''))='approved' THEN 1 ELSE 0 END),0) AS approved,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(leave_status,''))='rejected' THEN 1 ELSE 0 END),0) AS rejected,
                    COALESCE(SUM(CASE WHEN start_date >= CURDATE() THEN 1 ELSE 0 END),0) AS upcoming
             FROM sbaio_leave_requests
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(leave_status,''),'(none)') AS leave_status, COUNT(*) AS n
             FROM sbaio_leave_requests WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
             GROUP BY leave_status ORDER BY n DESC"
        ) ?: [];

        $byType = DB::fetchAll(
            "SELECT COALESCE(NULLIF(leave_type,''),'(none)') AS leave_type, COUNT(*) AS n
             FROM sbaio_leave_requests WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 180 DAY)
             GROUP BY leave_type ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT lr.id, lr.start_date, lr.end_date, lr.leave_type, lr.leave_status,
                    lr.legacy_name_ref, s.full_name, s.employee_code
             FROM sbaio_leave_requests lr
             LEFT JOIN sbaio_staff s ON s.id = lr.staff_id
             ORDER BY lr.start_date DESC, lr.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('leave.leave_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('leave.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/leave">&larr; Leave</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('leave.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/leave/export"> <?= e($tt('leave.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('leave.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('leave.180_day_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Requests</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('leave.pending_message')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['pending'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('leave.approved_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['approved'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('leave.rejected_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['rejected'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Upcoming</div><div class="u-stat-num"><strong><?= (int)$summary['upcoming'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('leave.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['leave_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">By Type</h4>
      <?php if (!$byType): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byType as $r): ?>
            <tr><td><?= e((string)$r['leave_type']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Requests</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No leave requests recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Staff</th><th>Type</th><th>Start</th><th>End</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['employee_code'] ?? '')) ?> <span class="muted"><?= e((string)($r['full_name'] ?? $r['legacy_name_ref'] ?? '')) ?></span></td>
            <td><?= e((string)$r['leave_type']) ?></td>
            <td><?= e((string)$r['start_date']) ?></td>
            <td><?= e((string)$r['end_date']) ?></td>
            <td><?= e((string)$r['leave_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
