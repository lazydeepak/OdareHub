<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php $auditSummary = is_array($audit_summary ?? null) ? $audit_summary : []; ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('module.production_entries.title')) ?></h2>
      <div class="muted"><?= e(t('module.production_entries.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/production-entries/export"><?= e(t('common.export')) ?></a>
      <a class="btn ok" href="/production-entries/add"><?= e(t('module.production_entries.add')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <form method="get" action="/production-entries" class="control-row">
    <label class="control-field-compact">
      <span class="control-label"><?= e(t('common.date')) ?></span>
      <input class="input" type="date" name="production_date" value="<?= e((string)$production_date) ?>">
    </label>
    <label class="control-field">
      <span class="control-label"><?= e(t('common.machine')) ?></span>
      <select name="machine_id">
        <option value="0"><?= e(t('common.all')) ?></option>
        <?php foreach ($machines as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ((int)$machine_id === (int)$m['id']) ? 'selected' : '' ?>><?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="control-field">
      <span class="control-label"><?= e(t('common.part')) ?></span>
      <select name="product_id">
        <option value="0"><?= e(t('common.all')) ?></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)$product_id === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="control-field-compact">
      <span class="control-label"><?= e(t('common.status')) ?></span>
      <input class="input" name="status" value="<?= e((string)$status) ?>" placeholder="<?= e(t('module.production_entries.status_placeholder')) ?>">
    </label>
    <div class="control-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/production-entries"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>
<div class="card"><div class="table-wrap"><table><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('module.production_entries.shift')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.part')) ?></th><th><?= e(t('module.production_entries.produced_qty')) ?></th><th><?= e(t('module.production_entries.rejected_qty')) ?></th><th><?= e(t('module.production_entries.good_qty')) ?></th><th><?= e(t('common.status')) ?></th><th>Last Activity</th><th><?= e(t('common.actions')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><?php $summary = $auditSummary[(int)$r['id']] ?? []; ?><tr><td><?= (int)$r['id'] ?></td><td><?= e((string)$r['production_date']) ?></td><td><?= e((string)$r['shift']) ?></td><td><?= e((string)$r['machine_no']) ?> - <?= e((string)$r['machine_name']) ?></td><td><?= e((string)$r['parts_name']) ?> <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span></td><td><?= e((string)$r['produced_qty']) ?></td><td><?= e((string)$r['rejected_qty']) ?></td><td><?= e((string)$r['good_qty']) ?></td><td><?= e((string)$r['status']) ?></td><td><?php if (!empty($summary)): ?><div class="ui-block"><?= e((string)($summary['action'] ?? '')) ?></div><div class="muted u-style-3995822e95"><?= e((string)($summary['actor'] ?? 'System')) ?> @ <?= e((string)($summary['at'] ?? '')) ?></div><?php else: ?><span class="muted">-</span><?php endif; ?></td><td><div class="control-actions"><a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$r['product_id'] ?>">Part 360</a><a class="btn" href="/production-entries/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a><a class="btn" href="/ledger?product_id=<?= (int)$r['product_id'] ?>&source_module=ProductionEntries&source_id=<?= (int)$r['id'] ?>"><?= e(t('nav.stock_ledger')) ?></a><form method="post" action="/production-entries/delete" onsubmit="return confirm('<?= e(t('module.production_entries.delete_confirm')) ?>');"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button></form></div></td></tr><?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="11" class="muted"><?= e(t('module.production_entries.none')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
