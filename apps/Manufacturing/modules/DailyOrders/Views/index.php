<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('module.daily_orders.title')) ?></h2>
      <div class="muted"><?= e(t('module.daily_orders.subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/daily-orders/import"> <?= e($tt('daily_orders.import_link')) ?> </a>
      <a class="btn ok" href="/daily-orders/add"><?= e(t('module.daily_orders.add')) ?></a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/daily-orders" class="module-filterbar">
    <label class="module-filter-field-wide">
      <span class="module-filter-label"><?= e(t('common.search')) ?></span>
      <input class="input" name="q" value="<?= e((string)$q) ?>" placeholder="<?= e(t('module.daily_orders.search_placeholder')) ?>">
    </label>
    <label class="module-filter-field">
      <span class="module-filter-label"><?= e(t('common.status')) ?></span>
      <input class="input" name="status" value="<?= e((string)$status) ?>" placeholder="<?= e(t('module.daily_orders.status_placeholder')) ?>">
    </label>
    <label class="module-filter-field">
      <span class="module-filter-label"><?= e(t('common.date_range_from')) ?></span>
      <input class="input" type="date" name="from_date" value="<?= e((string)$from_date) ?>">
    </label>
    <label class="module-filter-field">
      <span class="module-filter-label"><?= e(t('common.date_range_to')) ?></span>
      <input class="input" type="date" name="to_date" value="<?= e((string)$to_date) ?>">
    </label>
    <div class="module-filterbar-actions">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/daily-orders"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</div>

<div class="card"><div class="table-wrap"><table><thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('module.daily_orders.order_date')) ?></th><th><?= e(t('common.customer')) ?></th><th><?= e(t('common.part')) ?></th><th><?= e(t('common.qty')) ?></th><th>Open Demand</th><th>Usable Supply</th><th>Plan Supply</th><th>QC Pass</th><th>Dispatched</th><th><?= e(t('common.coverage')) ?></th><th><?= e(t('common.shortage')) ?></th><th>Forecast Pressure</th><th>Last Recalc</th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
<tr>
<td><?= (int)$r['id'] ?></td>
<td><?= e((string)$r['order_date']) ?></td>
<td><?= e((string)$r['customer_name']) ?></td>
<td><?= e((string)$r['parts_name']) ?> <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span></td>
<td><?= e((string)$r['qty']) ?></td>
<td><?= e((string)($r['open_demand_qty'] ?? '0')) ?></td>
<td><?= e((string)($r['usable_supply_qty'] ?? '0')) ?></td>
<td><?= e((string)$r['planned_supply_qty']) ?></td>
<td><?= e((string)($r['qc_pass_qty'] ?? '0')) ?></td>
<td><?= e((string)($r['dispatched_qty'] ?? '0')) ?></td>
<td><?= e((string)$r['coverage_pct']) ?> (<?= e((string)$r['coverage_status']) ?>)</td>
<td><?= e((string)$r['shortage_qty']) ?></td>
<td><?= e((string)($r['forecast_pressure_qty'] ?? '0')) ?></td>
<td><?= e((string)($r['coverage_last_recalculated_at'] ?? '')) ?></td>
<td><?= e((string)$r['status']) ?></td>
<td><div class="daily-orders-table-actions"><a class="btn" href="/daily-orders/360?id=<?= (int)$r['id'] ?>">Order 360</a><a class="btn" href="/daily-orders/edit?id=<?= (int)$r['id'] ?>"><?= e(t('common.edit')) ?></a><form method="post" action="/daily-orders/delete" onsubmit="return confirm('<?= e(t('module.daily_orders.delete_confirm')) ?>');"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn danger" type="submit"><?= e(t('common.delete')) ?></button></form></div></td>
</tr>
<?php endforeach; ?>
<?php if (empty($rows)): ?><tr><td colspan="16" class="muted"><?= e(t('module.daily_orders.none')) ?></td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
