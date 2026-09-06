<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.machines.overview');

$summary = ['total'=>0,'active'=>0];
$byStatus = [];
$bySection = [];
$rows = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne("SELECT COUNT(*) AS total, COALESCE(SUM(is_active),0) AS active FROM machines");
        if (is_array($row)) { $summary = array_merge($summary, $row); }
        $byStatus = DB::fetchAll("SELECT COALESCE(NULLIF(status,''),'(none)') AS status, COUNT(*) AS n FROM machines GROUP BY status ORDER BY n DESC") ?: [];
        $bySection = DB::fetchAll("SELECT COALESCE(NULLIF(section,''),'(none)') AS section, COUNT(*) AS n FROM machines GROUP BY section ORDER BY n DESC") ?: [];
        $rows = DB::fetchAll(
            "SELECT id, machine_no, machine_name, section, machine_group, machine_type,
                    status, capacity_per_hour, is_active
             FROM machines ORDER BY is_active DESC, machine_no ASC LIMIT 50"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('machines.machines_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('machines.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/machines">&larr; Machines</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('machines.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/machines/export"> <?= e($tt('machines.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('machines.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('machines.fleet_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Total Machines</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('machines.active_message')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['active'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Status &amp; Section</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e"> <?= e($tt('machines.by_status_title')) ?> </h4>
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
      <h4 class="u-style-ad7f18b19e">By Section</h4>
      <?php if (!$bySection): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Section</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($bySection as $r): ?>
            <tr><td><?= e((string)$r['section']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Machines</h3>
  <?php if (!$rows): ?>
    <p class="muted u-style-1169661891">No machines registered.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>No.</th><th>Name</th><th>Section</th><th>Group</th><th>Type</th><th> <?= e($tt('common.status_label')) ?> </th><th class="u-style-54c2afb7ba">Capacity/hr</th><th> <?= e($tt('machines.active_message')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><a href="/machines/<?= (int)$r['id'] ?>"><?= e((string)$r['machine_no']) ?></a></td>
            <td><?= e((string)$r['machine_name']) ?></td>
            <td><?= e((string)($r['section'] ?? '')) ?></td>
            <td><?= e((string)($r['machine_group'] ?? '')) ?></td>
            <td><?= e((string)($r['machine_type'] ?? '')) ?></td>
            <td><?= e((string)$r['status']) ?></td>
            <td class="u-style-54c2afb7ba"><?= $r['capacity_per_hour'] !== null ? number_format((float)$r['capacity_per_hour'], 2) : '-' ?></td>
            <td><?= ((int)$r['is_active']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
