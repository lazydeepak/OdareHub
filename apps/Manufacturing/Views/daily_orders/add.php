<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<?php $error = isset($error) ? (string)$error : ''; ?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('module.daily_orders.add')) ?></h2><a class="btn" href="/daily-orders"><?= e(t('common.back_to_list')) ?></a></div></div>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>
<div class="card">
<form method="post" action="/daily-orders/add" class="row">
<input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.order_date')) ?> *</label><input class="input" type="date" name="order_date" required value="<?= e((string)($old['order_date'] ?? '')) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.required_date')) ?></label><input class="input" type="date" name="required_date" value="<?= e((string)($old['required_date'] ?? '')) ?>"></div>
<div class="u-style-471dcf6cc8"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.customer')) ?> *</label><input class="input" name="customer_name" required value="<?= e((string)($old['customer_name'] ?? '')) ?>"></div>
<div class="u-style-35f3431aa2"><label class="muted u-style-98847c28df"><?= e(t('common.part')) ?> *</label><select name="product_id" required><option value=""><?= e(t('select_part')) ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('common.qty')) ?> *</label><input class="input" type="number" step="0.01" name="qty" required value="<?= e((string)($old['qty'] ?? '')) ?>"></div>
<div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df"><?= e(t('module.daily_orders.dispatch_deadline')) ?></label><input class="input" type="datetime-local" name="dispatch_deadline" value="<?= e((string)($old['dispatch_deadline'] ?? '')) ?>"></div>
<div class="u-style-eb62184e30"><label class="muted u-style-98847c28df"><?= e(t('common.status')) ?></label><input class="input" name="status" value="<?= e((string)($old['status'] ?? 'Open')) ?>"></div>
<div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea></div>
<div class="muted u-style-4ca11fcb42"><?= e(t('module.daily_orders.coverage_snapshot')) ?></div>
<div class="row u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('module.daily_orders.save')) ?></button><a class="btn" href="/daily-orders"><?= e(t('common.cancel')) ?></a></div>
</form></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
