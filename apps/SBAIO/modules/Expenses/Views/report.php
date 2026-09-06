<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/SBAIO/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = $report ?? \Apps\SBAIO\Services\ModuleReportRegistryService::findActiveReport('sbaio.expenses.overview');

$summary = ['total'=>0,'amount'=>0.0,'month'=>0,'month_amount'=>0.0,'approved'=>0,'pending'=>0];
$byStatus = [];
$byCategory = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(amount),0) AS amount,
                    COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN 1 ELSE 0 END),0) AS month,
                    COALESCE(SUM(CASE WHEN expense_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN amount ELSE 0 END),0) AS month_amount,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(expense_status,''))='approved' THEN 1 ELSE 0 END),0) AS approved,
                    COALESCE(SUM(CASE WHEN LOWER(COALESCE(expense_status,'')) IN ('pending','submitted') THEN 1 ELSE 0 END),0) AS pending
             FROM sbaio_expenses
             WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byStatus = DB::fetchAll(
            "SELECT COALESCE(NULLIF(expense_status,''),'(none)') AS expense_status, COUNT(*) AS n,
                    COALESCE(SUM(amount),0) AS amount
             FROM sbaio_expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             GROUP BY expense_status ORDER BY n DESC"
        ) ?: [];

        $byCategory = DB::fetchAll(
            "SELECT COALESCE(NULLIF(category_name,''),'(none)') AS category_name, COUNT(*) AS n,
                    COALESCE(SUM(amount),0) AS amount
             FROM sbaio_expenses WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
             GROUP BY category_name ORDER BY amount DESC LIMIT 15"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT id, expense_ref, expense_date, category_name, amount, expense_status
             FROM sbaio_expenses ORDER BY expense_date DESC, id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('expenses.expenses_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('expenses.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/expenses">&larr; Expenses</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('expenses.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/expenses/export"> <?= e($tt('expenses.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('expenses.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('expenses.90_day_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Entries</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Total Amount</div><div class="u-stat-num"><strong><?= number_format((float)$summary['amount']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">This Month</div><div class="u-stat-num"><strong><?= (int)$summary['month'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Month Amount</div><div class="u-stat-num"><strong><?= number_format((float)$summary['month_amount']) ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('expenses.approved_action')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['approved'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('expenses.pending_message')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['pending'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Distribution</h3>
  <div class="row row-distribute">
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6"> <?= e($tt('expenses.by_status_title')) ?> </h4>
      <?php if (!$byStatus): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Status</th><th class="u-text-right">Count</th><th class="u-text-right">Amount</th></tr></thead><tbody>
          <?php foreach ($byStatus as $r): ?>
            <tr><td><?= e((string)$r['expense_status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td><td class="u-text-right"><?= number_format((float)$r['amount']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="flex-col-md">
      <h4 class="u-mt-0 u-mb-6">Top Categories</h4>
      <?php if (!$byCategory): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Category</th><th class="u-text-right">Count</th><th class="u-text-right">Amount</th></tr></thead><tbody>
          <?php foreach ($byCategory as $r): ?>
            <tr><td><?= e((string)$r['category_name']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td><td class="u-text-right"><?= number_format((float)$r['amount']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Expenses</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No expenses recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Ref</th><th>Date</th><th>Category</th><th class="u-text-right">Amount</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)($r['expense_ref'] ?? '')) ?></td>
            <td><?= e((string)$r['expense_date']) ?></td>
            <td><?= e((string)($r['category_name'] ?? '')) ?></td>
            <td class="u-text-right"><?= number_format((float)$r['amount']) ?></td>
            <td><?= e((string)$r['expense_status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
