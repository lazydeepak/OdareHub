<?php
/** @var array<string,mixed> $summary */
/** @var array<int,array<string,mixed>> $risk_rows */
/** @var array<int,array<string,string>> $quick_links */

$summary = is_array($summary ?? null) ? $summary : [];
$riskRows = is_array($risk_rows ?? null) ? $risk_rows : [];
$quickLinks = is_array($quick_links ?? null) ? $quick_links : [];
$roleLabel = (string)($role_label ?? 'Operations');
$suiteRoleTemplateLabels = is_array($suite_role_template_labels ?? null) ? $suite_role_template_labels : [];
$modulePermissionTemplateLabels = is_array($module_permission_template_labels ?? null) ? $module_permission_template_labels : [];

require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';
?>

<div class="card">
  <div class="section-head">
    <h2><?= e(t('mfg.demand.title')) ?></h2>
    <div class="muted"><?= e(t('mfg.demand.subtitle')) ?></div>
  </div>
  <?php if ($suiteRoleTemplateLabels !== [] || $modulePermissionTemplateLabels !== []): ?>
    <div class="row u-style-340bde38b6">
      <?php foreach ($suiteRoleTemplateLabels as $label): ?>
        <span class="pill"><?= e((string)$label) ?></span>
      <?php endforeach; ?>
      <?php foreach ($modulePermissionTemplateLabels as $label): ?>
        <span class="pill"><?= e((string)$label) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <div class="row u-style-567eded8a2">
    <a class="btn" href="/apps/manufacturing/daily-orders"><?= e(t('mfg.demand.daily_orders_link')) ?></a>
    <a class="btn" href="/apps/manufacturing/coverage"><?= e(t('mfg.demand.coverage_link')) ?></a>
    <a class="btn" href="/apps/manufacturing/production-plans"><?= e(t('mfg.demand.prod_plans_link')) ?></a>
    <a class="btn" href="/apps/manufacturing/demands"><?= e(t('mfg.demand.demand_workspace_link')) ?></a>
    <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e(t('mfg.demand.dispatch_link')) ?></a>
    <a class="btn" href="/apps/manufacturing/qc-queue"><?= e(t('mfg.demand.qc_queue_link')) ?></a>
  </div>
</div>

<div class="card">
  <div class="muted u-style-761d3addb2"><?= e(t('mfg.demand.role_lens_label')) ?>: <?= e($roleLabel) ?></div>
  <div class="stat-row">
    <div class="stat-box">
      <div class="stat-box-label"><?= e(t('mfg.demand.open_orders_kpi')) ?></div>
      <div class="stat-box-value"><?= number_format((int)($summary['open_orders'] ?? 0)) ?></div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label"><?= e(t('mfg.demand.due_today_kpi')) ?></div>
      <div class="stat-box-value"><?= number_format((int)($summary['due_today'] ?? 0)) ?></div>
    </div>
    <div class="stat-box <?= (int)($summary['delayed'] ?? 0) > 0 ? 'u-tone-danger' : '' ?>">
      <div class="stat-box-label"><?= e(t('mfg.demand.delayed_kpi')) ?></div>
      <div class="stat-box-value"><?= number_format((int)($summary['delayed'] ?? 0)) ?></div>
    </div>
    <div class="stat-box <?= (float)($summary['shortage_qty'] ?? 0) > 0 ? 'u-tone-warning' : '' ?>">
      <div class="stat-box-label"><?= e(t('mfg.demand.coverage_shortage_kpi')) ?></div>
      <div class="stat-box-value"><?= number_format((float)($summary['coverage_pct'] ?? 0), 1) ?>%</div>
    </div>
    <div class="stat-box">
      <div class="stat-box-label"><?= e(t('mfg.demand.prod_demand_kpi')) ?></div>
      <div class="stat-box-value"><?= number_format((int)($summary['production_demand_rows'] ?? 0)) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <div class="section-head">
    <h2><?= e(t('mfg.demand.top_risk_orders_title')) ?></h2>
    <div class="muted"><?= e(t('mfg.demand.top_risk_desc')) ?></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e(t('mfg.demand.order_col')) ?></th>
          <th><?= e(t('mfg.demand.date_col')) ?></th>
          <th><?= e(t('mfg.demand.required_col')) ?></th>
          <th><?= e(t('mfg.demand.customer_col')) ?></th>
          <th><?= e(t('mfg.demand.part_col')) ?></th>
          <th><?= e(t('mfg.demand.qty_col')) ?></th>
          <th><?= e(t('mfg.demand.coverage_col')) ?></th>
          <th><?= e(t('mfg.demand.shortage_col')) ?></th>
          <th><?= e(t('mfg.demand.action_col')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($riskRows as $row): ?>
          <tr>
            <td>#<?= (int)($row['id'] ?? 0) ?></td>
            <td><?= e((string)($row['order_date'] ?? '')) ?></td>
            <td><?= e((string)($row['required_date'] ?? '')) ?></td>
            <td><?= e((string)($row['customer_name'] ?? '')) ?></td>
            <td>
              <?= e((string)($row['parts_name'] ?? '')) ?>
              <span class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></span>
            </td>
            <td><?= number_format((float)($row['qty'] ?? 0), 2) ?></td>
            <td><?= number_format((float)($row['coverage_pct'] ?? 0), 1) ?>%</td>
            <td><?= number_format((float)($row['shortage_qty'] ?? 0), 2) ?></td>
            <td><a class="btn" href="/apps/manufacturing/daily-orders/360?id=<?= (int)($row['id'] ?? 0) ?>"><?= e(t('mfg.demand.open_360_link')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($riskRows)): ?>
          <tr>
            <td colspan="9" class="muted"><?= e(t('mfg.demand.no_risk_orders')) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
