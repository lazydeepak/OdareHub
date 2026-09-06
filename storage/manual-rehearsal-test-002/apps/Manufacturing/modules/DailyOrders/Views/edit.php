<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('edit')) ?> Daily Order #<?= (int)$row['id'] ?></h2><div class="row"><a class="btn" href="/daily-orders/360?id=<?= (int)$row['id'] ?>">Order 360</a><a class="btn" href="/daily-orders"><?= e(t('back_to_list')) ?></a></div></div></div>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
	<h3 class="u-style-d462248a40"> <?= e($tt('daily_orders.coverage_snapshot_title')) ?> </h3>
	<div class="row">
		<div class="u-style-5af557a50a"><div class="muted">Order Demand</div><div class="ui-block"><strong><?= e(number_format((float)($row['qty'] ?? 0), 2, '.', '')) ?></strong></div></div>
		<div class="u-style-5af557a50a"><div class="muted"> <?= e($tt('daily_orders.open_action')) ?> </div><div class="ui-block"><strong><?= e(number_format((float)($row['open_demand_qty'] ?? 0), 2, '.', '')) ?></strong></div></div>
		<div class="u-style-5af557a50a"><div class="muted">Usable Supply</div><div class="ui-block"><strong><?= e(number_format((float)($row['usable_supply_qty'] ?? 0), 2, '.', '')) ?></strong></div></div>
		<div class="u-style-5af557a50a"><div class="muted">Coverage</div><div class="ui-block"><strong><?= e(number_format((float)($row['coverage_pct'] ?? 0), 2, '.', '')) ?>%</strong> (<?= e((string)($row['coverage_status'] ?? 'Low')) ?>)</div></div>
		<div class="u-style-5af557a50a"><div class="muted">Shortage</div><div class="ui-block"><strong><?= e(number_format((float)($row['shortage_qty'] ?? 0), 2, '.', '')) ?></strong></div></div>
		<div class="u-style-e581e86b44"><div class="muted">Last Recalculated</div><div class="ui-block"><strong><?= e((string)($row['coverage_last_recalculated_at'] ?? 'Not yet')) ?></strong></div></div>
	</div>
	<hr>
	<div class="row">
		<div class="u-style-5af557a50a"><div class="muted">From Ledger Stock</div><div class="ui-block"><?= e(number_format((float)($row['usable_stock_qty'] ?? 0), 2, '.', '')) ?></div></div>
		<div class="u-style-5af557a50a"><div class="muted">From Valid Production Plans</div><div class="ui-block"><?= e(number_format((float)($row['planned_supply_qty'] ?? 0), 2, '.', '')) ?></div></div>
		<div class="u-style-5af557a50a"><div class="muted">From QC Passed</div><div class="ui-block"><?= e(number_format((float)($row['qc_pass_qty'] ?? 0), 2, '.', '')) ?></div></div>
		<div class="u-style-5af557a50a"><div class="muted">Minus Dispatched</div><div class="ui-block"><?= e(number_format((float)($row['dispatched_qty'] ?? 0), 2, '.', '')) ?></div></div>
		<div class="u-style-bec7b1d583"><div class="muted">Pre-order Forecast Pressure</div><div class="ui-block"><?= e(number_format((float)($row['forecast_pressure_qty'] ?? 0), 2, '.', '')) ?></div></div>
	</div>
</div>
<div class="card">
<form method="post" action="/daily-orders/edit" class="row">
<input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df">Order Date *</label><input class="input" type="date" name="order_date" required value="<?= e((string)$row['order_date']) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df">Required Date</label><input class="input" type="date" name="required_date" value="<?= e((string)($row['required_date'] ?? '')) ?>"></div>
<div class="u-style-471dcf6cc8"><label class="muted u-style-98847c28df">Customer *</label><input class="input" name="customer_name" required value="<?= e((string)$row['customer_name']) ?>"></div>
<div class="u-style-35f3431aa2"><label class="muted u-style-98847c28df"><?= e(t('part')) ?> *</label><select name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)$row['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Qty *</label><input class="input" type="number" step="0.01" name="qty" required value="<?= e((string)$row['qty']) ?>"></div>
<div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df">Dispatch Deadline</label><input class="input" type="datetime-local" name="dispatch_deadline" value="<?= e((string)($row['dispatch_deadline'] ?? '')) ?>"></div>
<div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('status')) ?></label><input class="input" name="status" value="<?= e((string)$row['status']) ?>"></div>
<div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($row['notes'] ?? '')) ?></textarea></div>
<div class="row u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('update_daily_order')) ?></button><a class="btn" href="/daily-orders"><?= e(t('cancel')) ?></a></div>
</form></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
