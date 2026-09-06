<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.assembly_plans.readiness');

// Assembly plans source table is not yet provisioned in this environment.
// Derive what is knowable from mfg_assembly_entries groupings to approximate plan coverage.
$summary = ['plans'=>0, 'entries'=>0, 'planned'=>0.0, 'completed'=>0.0, 'ready'=>0];
$byStatus = [];
$tableAvailable = false;

if (is_array($report)) {
    try {
        $exists = DB::fetchOne("SHOW TABLES LIKE 'assembly_plans'");
        $tableAvailable = !empty($exists);
    } catch (\Throwable $e) { $tableAvailable = false; }

    try {
        $row = DB::fetchOne(
            "SELECT COUNT(DISTINCT assembly_plan_id) AS plans,
                    COUNT(*) AS entries,
                    COALESCE(SUM(planned_qty),0) AS planned,
                    COALESCE(SUM(completed_qty),0) AS completed,
                    COALESCE(SUM(CASE WHEN status IN ('completed','approved') THEN 1 ELSE 0 END),0) AS ready
             FROM mfg_assembly_entries
             WHERE assembly_plan_id IS NOT NULL"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT status, COUNT(*) AS n
             FROM mfg_assembly_entries
             WHERE assembly_plan_id IS NOT NULL
             GROUP BY status ORDER BY n DESC"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('assembly_plans.assembly_plans_readiness_title')) ?> </h2>
      <div class="muted">Module-owned assembly plan readiness indicator, derived from linked assembly entries.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/assembly-plans">&larr; Assembly Plans</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('assembly_plans.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('assembly_plans.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<?php if (!$tableAvailable): ?>
<div class="card">
  <p class="muted u-style-1169661891">The dedicated <code>assembly_plans</code> table is not yet provisioned in this environment. Readiness is approximated from linked assembly entries.</p>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('assembly_plans.plan_coverage_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Plans Referenced</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['plans'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Linked Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['entries'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Planned Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['planned']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Completed Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['completed']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Ready/Approved</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['ready'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Entry Status (linked to plans)</h3>
  <?php if (!$byStatus): ?><p class="muted u-style-1169661891">No assembly entries are linked to a plan yet.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table u-style-0fd8744d8e"><thead><tr><th>Status</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
      <?php foreach ($byStatus as $r): ?>
        <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
