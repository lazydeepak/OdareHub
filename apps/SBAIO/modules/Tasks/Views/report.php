<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.tasks.overview');

$summary = ['total'=>0,'open'=>0,'done'=>0,'overdue'=>0,'due_7d'=>0];
$byStatus = [];
$byPriority = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(task_status,'')) IN ('open','in_progress','pending','todo') THEN 1 ELSE 0 END),0) AS open,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(task_status,'')) IN ('done','completed','closed') THEN 1 ELSE 0 END),0) AS done,
                    COALESCE(SUM(CASE WHEN due_date IS NOT NULL AND due_date < CURDATE() AND LOWER(COALESCE(task_status,'')) NOT IN ('done','completed','closed') THEN 1 ELSE 0 END),0) AS overdue,
                    COALESCE(SUM(CASE WHEN due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS due_7d
             FROM sbaio_tasks"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(task_status,''),'(none)') AS task_status, COUNT(*) AS n
             FROM sbaio_tasks GROUP BY task_status ORDER BY n DESC"
        ) ?: [];

        $byPriority = DB::fetchAll(
            "SELECT COALESCE(NULLIF(priority,''),'(none)') AS priority, COUNT(*) AS n
             FROM sbaio_tasks GROUP BY priority ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT id, task_title, owner_name, task_type, task_status, priority, due_date
             FROM sbaio_tasks ORDER BY due_date ASC, id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('tasks.tasks_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('tasks.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/tasks">&larr; Tasks</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('tasks.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/tasks/export"> <?= e($tt('tasks.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('tasks.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('tasks.task_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Total</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('common.open_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['open'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Done</div><div class="u-stat-num"><strong><?= (int)$summary['done'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Overdue</div><div class="u-stat-num"><strong><?= (int)$summary['overdue'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Due &le; 7d</div><div class="u-stat-num"><strong><?= (int)$summary['due_7d'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('tasks.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['task_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">By Priority</h4>
      <?php if (!$byPriority): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Priority</th><th class="u-text-right">Count</th></tr></thead><tbody>
          <?php foreach ($byPriority as $r): ?>
            <tr><td><?= e((string)$r['priority']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Tasks</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No tasks recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th> <?= e($tt('tasks.title_column')) ?> </th><th>Owner</th><th>Type</th><th>Status</th><th>Priority</th><th>Due</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['task_title'] ?? '')) ?></td>
            <td><?= e((string)($r['owner_name'] ?? '')) ?></td>
            <td><?= e((string)($r['task_type'] ?? '')) ?></td>
            <td><?= e((string)$r['task_status']) ?></td>
            <td><?= e((string)($r['priority'] ?? '')) ?></td>
            <td><?= e((string)($r['due_date'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
