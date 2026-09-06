<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.staff.overview');

$summary = ['total'=>0,'active'=>0,'helpers'=>0,'new_90d'=>0,'exited'=>0];
$byStatus = [];
$byDepartment = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(employment_status,''))='active' OR LOWER(COALESCE(status,''))='active' THEN 1 ELSE 0 END),0) AS active,
                    COALESCE(SUM(CASE WHEN COALESCE(helper_classification,'') <> '' THEN 1 ELSE 0 END),0) AS helpers,
                    COALESCE(SUM(CASE WHEN hire_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 ELSE 0 END),0) AS new_90d,
                    COALESCE(SUM(CASE WHEN exit_date IS NOT NULL THEN 1 ELSE 0 END),0) AS exited
             FROM sbaio_staff"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(employment_status,''),'(none)') AS employment_status, COUNT(*) AS n
             FROM sbaio_staff GROUP BY employment_status ORDER BY n DESC"
        ) ?: [];

        $byDepartment = DB::fetchAll(
            "SELECT COALESCE(NULLIF(department_name,''),'(none)') AS department_name, COUNT(*) AS n
             FROM sbaio_staff GROUP BY department_name ORDER BY n DESC LIMIT 15"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT id, employee_code, full_name, role_label, department_name, employment_status, hire_date, exit_date
             FROM sbaio_staff ORDER BY hire_date DESC, id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('staff.staff_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('staff.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/staff">&larr; Staff</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('staff.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/staff/export"> <?= e($tt('staff.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('staff.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('staff.staff_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Total</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('staff.active_message')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['active'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Helpers</div><div class="u-stat-num"><strong><?= (int)$summary['helpers'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Hired &le; 90d</div><div class="u-stat-num"><strong><?= (int)$summary['new_90d'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Exited</div><div class="u-stat-num"><strong><?= (int)$summary['exited'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('staff.by_employment_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['employment_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">By Department</h4>
      <?php if (!$byDepartment): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Department</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byDepartment as $r): ?>
            <tr><td><?= e((string)$r['department_name']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Staff</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No staff recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Code</th><th>Name</th><th>Role</th><th>Department</th><th> <?= e($tt('common.status_label')) ?> </th><th>Hire Date</th><th>Exit Date</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['employee_code'] ?? '')) ?></td>
            <td><?= e((string)($r['full_name'] ?? '')) ?></td>
            <td><?= e((string)($r['role_label'] ?? '')) ?></td>
            <td><?= e((string)($r['department_name'] ?? '')) ?></td>
            <td><?= e((string)($r['employment_status'] ?? '')) ?></td>
            <td><?= e((string)($r['hire_date'] ?? '')) ?></td>
            <td><?= e((string)($r['exit_date'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
