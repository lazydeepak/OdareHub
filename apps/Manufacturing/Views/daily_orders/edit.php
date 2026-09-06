<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php
$activityTimeline = is_array($activity_timeline ?? null) ? $activity_timeline : [];
$error = isset($error) ? (string)$error : '';
?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('module.daily_orders.update')) ?> #<?= (int)$row['id'] ?></h2><div class="row"><a class="btn" href="/daily-orders/360?id=<?= (int)$row['id'] ?>"><?= e(t('module.daily_orders.order_360')) ?></a><a class="btn" href="/daily-orders"><?= e(t('common.back_to_list')) ?></a></div></div></div>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>
<?php $ownership_title = t('module.daily_orders.operational_ownership'); require APP_ROOT . '/public/views/partials/ownership_summary.php'; ?>
<div class="card">
	<h3 class="u-style-d462248a40"><?= e(t('module.daily_orders.coverage_snapshot')) ?></h3>
	<div class="stat-row">
		<div class="stat-box"><div class="stat-box-label"><?= e(t('common.qty')) ?></div><div class="stat-box-value"><?= e(number_format((float)($row['qty'] ?? 0), 2, '.', '')) ?></div></div>
		<div class="stat-box"><div class="stat-box-label"><?= e(t('common.coverage')) ?></div><div class="stat-box-value"><?= e(number_format((float)($row['coverage_pct'] ?? 0), 2, '.', '')) ?>%</div></div>
		<div class="stat-box"><div class="stat-box-label"><?= e(t('common.shortage')) ?></div><div class="stat-box-value"><?= e(number_format((float)($row['shortage_qty'] ?? 0), 2, '.', '')) ?></div></div>
	</div>
</div>
<div class="card">
<form method="post" action="/daily-orders/edit" class="row">
<input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.order_date')) ?> *</label><input class="input" type="date" name="order_date" required value="<?= e((string)$row['order_date']) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.required_date')) ?></label><input class="input" type="date" name="required_date" value="<?= e((string)($row['required_date'] ?? '')) ?>"></div>
<div class="u-style-471dcf6cc8"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.customer')) ?> *</label><input class="input" name="customer_name" required value="<?= e((string)$row['customer_name']) ?>"></div>
<div class="u-style-35f3431aa2"><label class="muted u-style-98847c28df"><?= e(t('common.part')) ?> *</label><select name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)$row['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('common.qty')) ?> *</label><input class="input" type="number" step="0.01" name="qty" required value="<?= e((string)$row['qty']) ?>"></div>
<div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.dispatch_deadline')) ?></label><input class="input" type="datetime-local" name="dispatch_deadline" value="<?= e((string)($row['dispatch_deadline'] ?? '')) ?>"></div>
<div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('common.status')) ?></label><input class="input" name="status" value="<?= e((string)$row['status']) ?>"></div>
<div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($row['notes'] ?? '')) ?></textarea></div>
<div class="row u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('module.daily_orders.update')) ?></button><a class="btn" href="/daily-orders"><?= e(t('common.cancel')) ?></a></div>
</form></div>
<div class="card">
<h3 class="u-style-d462248a40"><?= e(t('module.daily_orders.activity_timeline')) ?></h3>
<div class="table-wrap"><table><thead><tr><th><?= e(t('common.when')) ?></th><th><?= e(t('module.production_plans.actor')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('module.production_plans.action')) ?></th><th><?= e(t('notes')) ?></th></tr></thead><tbody>
<?php if (empty($activityTimeline)): ?>
<tr><td colspan="5" class="muted"><?= e(t('module.daily_orders.no_activity')) ?></td></tr>
<?php else: foreach ($activityTimeline as $entry): ?>
<tr>
<td><?= e((string)($entry['created_at'] ?? '')) ?></td>
<td><?= e((string)($entry['actor_label'] ?? (string)t('common.system'))) ?></td>
<td><?= e((string)($entry['event_type'] ?? '')) ?></td>
<td><?= e((string)($entry['action_name'] ?? '')) ?></td>
<td><?= e(trim((string)($entry['note_text'] ?? '')) !== '' ? (string)($entry['note_text'] ?? '') : (string)($entry['reason_text'] ?? '')) ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody></table></div>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
