<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.dispatch_entries.execution');

$summary = [
    'open_entries' => 0,
    'total_qty' => 0.0,
    'total_cases' => 0,
    'total_pallets' => 0,
    'today_count' => 0,
    'seven_day_count' => 0,
    'ready_count' => 0,
    'blocked_count' => 0,
    'dispatched_count' => 0,
];
$byStatus = [];
$byCompletion = [];
$upcoming = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT
                COUNT(*) AS open_entries,
                COALESCE(SUM(dispatchable_qty), 0) AS total_qty,
                COALESCE(SUM(cases_count), 0) AS total_cases,
                COALESCE(SUM(pallets_count), 0) AS total_pallets,
                COALESCE(SUM(CASE WHEN dispatch_date = CURDATE() THEN 1 ELSE 0 END), 0) AS today_count,
                COALESCE(SUM(CASE WHEN dispatch_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS seven_day_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(dispatch_status,'')) = 'ready' THEN 1 ELSE 0 END), 0) AS ready_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(dispatch_status,'')) = 'blocked' THEN 1 ELSE 0 END), 0) AS blocked_count,
                COALESCE(SUM(CASE WHEN LOWER(COALESCE(dispatch_status,'')) IN ('dispatched','completed') THEN 1 ELSE 0 END), 0) AS dispatched_count
             FROM dispatch_entries
             WHERE dispatch_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               AND dispatch_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(dispatch_status, ''), '(none)') AS status, COUNT(*) AS n
             FROM dispatch_entries
             WHERE dispatch_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               AND dispatch_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             GROUP BY dispatch_status ORDER BY n DESC"
        ) ?: [];

        $byCompletion = DB::fetchAll(
            "SELECT COALESCE(NULLIF(completion_status, ''), '(none)') AS status, COUNT(*) AS n
             FROM dispatch_entries
             WHERE dispatch_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
               AND dispatch_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
             GROUP BY completion_status ORDER BY n DESC"
        ) ?: [];

        $upcoming = DB::fetchAll(
            "SELECT d.id, d.dispatch_date, d.dispatchable_qty, d.cases_count, d.pallets_count,
                    d.destination, d.dispatch_type, d.dispatch_status, d.completion_status,
                    d.dispatch_mode, d.source_type,
                    p.parts_number, p.parts_name
             FROM dispatch_entries d
             INNER JOIN products p ON p.id = d.product_id
             WHERE d.dispatch_date >= CURDATE()
               AND d.dispatch_date <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
               AND LOWER(COALESCE(d.dispatch_status, '')) NOT IN ('dispatched', 'completed', 'cancelled', 'canceled')
             ORDER BY d.dispatch_date ASC, d.id ASC
             LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('dispatch_entries.dispatch_execution_report_title')) ?> </h2>
      <div class="muted">Module-owned outbound execution: ready vs. blocked, delivery modes, and upcoming dispatch load.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/dispatch-entries">&larr; Dispatch Entries</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('dispatch_entries.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/dispatch-entries/export"> <?= e($tt('dispatch_entries.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('dispatch_entries.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40">Execution Snapshot (-7d &hellip; +14d)</h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['open_entries'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Dispatchable Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Cases</div><div class="u-style-b9199e22b2"><strong><?= number_format((int)$summary['total_cases']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Pallets</div><div class="u-style-b9199e22b2"><strong><?= number_format((int)$summary['total_pallets']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Today</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['today_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['seven_day_count'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('dispatch_entries.status_pressure_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Ready</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['ready_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Blocked</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['blocked_count'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('dispatch_entries.dispatched_completed_message')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['dispatched_count'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Status &amp; Completion Mix</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e"> <?= e($tt('dispatch_entries.dispatch_status_title')) ?> </h4>
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
      <h4 class="u-style-ad7f18b19e"> <?= e($tt('dispatch_entries.completion_status_title')) ?> </h4>
      <?php if (!$byCompletion): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th> <?= e($tt('common.status_label')) ?> </th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byCompletion as $r): ?>
            <tr><td><?= e((string)$r['status']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Upcoming Dispatches</h3>
  <?php if (!$upcoming): ?>
    <p class="muted u-style-1169661891">No upcoming dispatch entries in the next 14 days.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Entry #</th><th>Date</th><th>Part</th><th>Destination</th><th>Mode</th><th>Source</th><th class="u-style-54c2afb7ba">Qty</th><th class="u-style-54c2afb7ba">Cases</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($upcoming as $r): ?>
          <tr>
            <td><a href="/dispatch-entries/<?= (int)$r['id'] ?>">#<?= (int)$r['id'] ?></a></td>
            <td><?= e((string)$r['dispatch_date']) ?></td>
            <td><?= e((string)($r['parts_number'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['parts_name'] ?? '')) ?></span></td>
            <td><?= e((string)($r['destination'] ?? '')) ?></td>
            <td><?= e((string)$r['dispatch_mode']) ?></td>
            <td><?= e((string)$r['source_type']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['dispatchable_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((int)$r['cases_count']) ?></td>
            <td><?= e((string)$r['dispatch_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
