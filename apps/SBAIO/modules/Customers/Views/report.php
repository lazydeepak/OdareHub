<?php
$tt = static function (string $key): string {
  return t($key);
};

$report = is_array($report ?? null) ? $report : null;
$reportData = is_array($reportData ?? null) ? $reportData : [];

$summary = is_array($reportData['summary'] ?? null) ? $reportData['summary'] : ['total'=>0,'active'=>0,'new_30d'=>0,'with_email'=>0,'with_phone'=>0];
$byStatus = is_array($reportData['byStatus'] ?? null) ? $reportData['byStatus'] : [];
$recent = is_array($reportData['recent'] ?? null) ? $reportData['recent'] : [];
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-m-0"> <?= e($tt('customers.customers_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('customers.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/sbaio/customers">&larr; Customers</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row row-spread">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('customers.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/sbaio/customers/export"> <?= e($tt('customers.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-m-0"> <?= e($tt('customers.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('customers.customer_snapshot_title')) ?> </h3>
  <div class="row row-stats">
    <div class="ui-block"><div class="muted">Total</div><div class="u-stat-num"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('customers.active_message')) ?> </div><div class="u-stat-num"><strong><?= (int)$summary['active'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">New &le; 30d</div><div class="u-stat-num"><strong><?= (int)$summary['new_30d'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">With Email</div><div class="u-stat-num"><strong><?= (int)$summary['with_email'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">With Phone</div><div class="u-stat-num"><strong><?= (int)$summary['with_phone'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-mt-0"> <?= e($tt('customers.by_status_title')) ?> </h3>
  <?php if (!$byStatus): ?><p class="muted u-m-0">No customers yet.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table table-narrow"><thead><tr><th>Status</th><th class="u-text-right">Count</th></tr></thead><tbody>
      <?php foreach ($byStatus as $r): ?>
        <tr><td><?= e((string)$r['status']) ?></td><td class="u-text-right"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-mt-0">Recent Customers</h3>
  <?php if (!$recent): ?><p class="muted u-m-0">No customers recorded.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>Customer</th><th>Contact</th><th>Email</th><th>Phone</th><th>Status</th><th> <?= e($tt('customers.created_column')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['customer_name']) ?></td>
            <td><?= e((string)($r['contact_name'] ?? '')) ?></td>
            <td><?= e((string)($r['email'] ?? '')) ?></td>
            <td><?= e((string)($r['phone'] ?? '')) ?></td>
            <td><?= e((string)$r['status']) ?></td>
            <td><?= e((string)$r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
