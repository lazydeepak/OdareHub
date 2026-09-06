<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php $statusOptions = ['Draft', 'Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled']; ?>
<?php $auditSummary = is_array($audit_summary ?? null) ? $audit_summary : []; ?>
<?php
$statusLabelMap = [
  'Draft' => t('module.production_plans.status.draft'),
  'Planned' => t('module.production_plans.status.planned'),
  'In Progress' => t('module.production_plans.status.in_progress'),
  'Completed' => t('module.production_plans.status.completed'),
  'On Hold' => t('module.production_plans.status.on_hold'),
  'Cancelled' => t('module.production_plans.status.cancelled'),
];
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('module.production_plans.title')) ?></h2>
      <div class="muted"><?= e(t('module.production_plans.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/production-plans/print-next-two-weeks-pdf" target="_blank" rel="noopener"><?= e(t('module.production_plans.print')) ?></a>
      <a class="btn ok" href="/production-plans/add"><?= e(t('module.production_plans.add')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <form method="get" action="/production-plans" class="control-row pp-filter-row">
    <label class="control-field-compact pp-filter-date">
      <span class="control-label"><?= e(t('module.production_plans.plan_date')) ?></span>
      <input class="input" type="date" name="plan_date" value="<?= e((string)$plan_date) ?>">
    </label>
    <label class="control-field pp-filter-machine">
      <span class="control-label"><?= e(t('common.machine')) ?></span>
      <select name="machine_id">
        <option value="0"><?= e(t('common.all')) ?></option>
        <?php foreach ($machines as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ((int)$machine_id === (int)$m['id']) ? 'selected' : '' ?>><?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="control-field-compact pp-filter-status">
      <span class="control-label"><?= e(t('common.status')) ?></span>
      <select name="status">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($statusOptions as $opt): ?>
          <option value="<?= e($opt) ?>" <?= ((string)$status === $opt) ? 'selected' : '' ?>><?= e((string)($statusLabelMap[$opt] ?? $opt)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="control-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/production-plans"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>
<div class="card"><div class="table-wrap"><table><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.part')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.sequence')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.coverage')) ?></th><th><?= e(t('common.shortage')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= (int)$r['id'] ?></td><td><?= e((string)$r['plan_date']) ?></td><td><?= e((string)$r['machine_no']) ?> - <?= e((string)$r['machine_name']) ?></td><td><?= e((string)$r['parts_name']) ?> <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span></td><td><?= e((string)$r['planned_qty']) ?></td><td><?= (int)$r['sequence_no'] ?></td><td><?= e((string)($statusLabelMap[(string)$r['status']] ?? (string)$r['status'])) ?></td><td><?= e((string)($statusLabelMap[(string)($r['approval_status'] ?? 'Draft')] ?? (string)($r['approval_status'] ?? 'Draft'))) ?></td><td><?= trim((string)($r['locked_at'] ?? '')) !== '' ? e(t('module.production_plans.locked')) : e(t('module.production_plans.open')) ?></td><td><?= e((string)$r['coverage_pct']) ?></td><td><?= e((string)$r['shortage_qty']) ?></td><td><div class="control-actions"><a class="btn" href="/production-plans/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a><form method="post" action="/production-plans/delete" onsubmit="return confirm('<?= e(t('module.production_plans.delete_confirm')) ?>');"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button></form></div></td></tr><?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="12" class="muted"><?= e(t('module.production_plans.none')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
