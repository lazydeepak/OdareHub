<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="module-header module-page-intro">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('module.qc_plans.title')) ?></h2>
      <div class="muted"><?= e(t('module.qc_plans.subtitle')) ?></div>
      <div class="muted u-style-96ad6099e2">Workflow: System Generated -> QC Verification/Adjustment -> Higher Authority Approval</div>
    </div>
    <div class="module-header-actions">
      <form method="get" action="/qc-plans/print-pdf" target="_blank" class="control-row qc-print-form">
        <label class="control-field-compact qc-print-form-field">
          <span class="control-label">From</span>
          <input class="input" type="date" name="from_date" value="<?= e((string)($print_from_date ?? '')) ?>">
        </label>
        <label class="control-field-compact qc-print-form-field">
          <span class="control-label">To</span>
          <input class="input" type="date" name="to_date" value="<?= e((string)($print_to_date ?? '')) ?>">
        </label>
        <button class="btn" type="submit"> <?= e($tt('common.print_action')) ?> </button>
      </form>
      <a class="btn" href="/qc-plans/export"><?= e(t('common.export')) ?></a>
      <a class="btn ok" href="/qc-plans/add"><?= e(t('module.qc_plans.add')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <form method="get" action="/qc-plans" class="control-row qc-filter-row">
    <label class="control-field-compact qc-filter-date">
      <span class="control-label"><?= e(t('module.qc_plans.plan_date')) ?></span>
      <input class="input" type="date" name="plan_date" value="<?= e((string)$plan_date) ?>">
    </label>
    <label class="control-field-compact qc-filter-priority">
      <span class="control-label"><?= e(t('common.priority')) ?></span>
      <select name="priority">
        <option value=""><?= e(t('common.all')) ?></option>
        <option value="Critical" <?= ((string)$priority === 'Critical') ? 'selected' : '' ?>>Critical</option>
        <option value="High" <?= ((string)$priority === 'High') ? 'selected' : '' ?>>High</option>
        <option value="Medium" <?= ((string)$priority === 'Medium') ? 'selected' : '' ?>>Medium</option>
        <option value="Normal" <?= ((string)$priority === 'Normal') ? 'selected' : '' ?>>Normal</option>
        <option value="Low" <?= ((string)$priority === 'Low') ? 'selected' : '' ?>>Low</option>
      </select>
    </label>
    <label class="control-field qc-filter-status">
      <span class="control-label"><?= e(t('common.status')) ?></span>
      <select name="status">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach (($status_options ?? []) as $opt): ?>
          <option value="<?= e((string)$opt) ?>" <?= ((string)$status === (string)$opt) ? 'selected' : '' ?>><?= e((string)$opt) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="control-actions qc-filter-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/qc-plans"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>
<div class="card">
  <div class="table-wrap">
    <table class="qc-plans-table table-mobile-cards">
      <thead>
        <tr>
          <th><?= e(t('common.id')) ?></th>
          <th><?= e(t('common.date')) ?></th>
          <th><?= e(t('common.required_date')) ?></th>
          <th><?= e(t('common.part')) ?></th>
          <th><?= e(t('module.pre_orders.planned_qty')) ?></th>
          <th><?= e(t('common.est_minutes')) ?></th>
          <th><?= e(t('common.priority')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th>Assigned to</th>
          <th>Verified by</th>
          <th> <?= e($tt('q_c_plans.approved_by_column')) ?> </th>
          <th><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td data-label="<?= e(t('common.id')) ?>"><?= (int)$r['id'] ?></td>
            <td data-label="<?= e(t('common.date')) ?>"><?= e((string)$r['plan_date']) ?></td>
            <td data-label="<?= e(t('common.required_date')) ?>"><?= e((string)($r['required_date'] ?? '')) ?></td>
            <td data-label="<?= e(t('common.part')) ?>">
              <?= e((string)$r['parts_name']) ?>
              <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span>
            </td>
            <td data-label="<?= e(t('module.pre_orders.planned_qty')) ?>"><?= e((string)$r['planned_qty']) ?></td>
            <td data-label="<?= e(t('common.est_minutes')) ?>"><?= (int)$r['estimated_time_minutes'] ?></td>
            <td data-label="<?= e(t('common.priority')) ?>"><?= e((string)$r['priority']) ?></td>
            <td data-label="<?= e(t('common.status')) ?>"><?= e((string)$r['status']) ?></td>
            <td data-label="Assigned to"><?= e((string)($r['assigned_to'] ?? '-')) ?></td>
            <td data-label="Verified by"><?= e((string)($r['verified_by'] ?? '-')) ?></td>
            <td data-label="Approved by"><?= e((string)($r['approved_by'] ?? '-')) ?></td>
            <td data-label="<?= e(t('common.actions')) ?>">
              <div class="control-actions qc-table-actions">
                <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$r['product_id'] ?>">Part 360</a>
                <a class="btn" href="/qc-plans/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a>
                <form method="post" action="/qc-plans/delete" onsubmit="return confirm('<?= e(t('module.qc_plans.delete_confirm')) ?>');">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr>
            <td colspan="12" class="muted"><?= e(t('module.qc_plans.none')) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
