<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php $canEditAddedBy = !empty($can_edit_added_by); ?>
<?php $statusOptions = ['Draft', 'Planned', 'In Progress', 'Completed', 'On Hold', 'Cancelled']; ?>
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
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('module.production_plans.add')) ?></h2><a class="btn" href="/production-plans"><?= e(t('common.back_to_list')) ?></a></div></div>
<?php $error = isset($error) ? (string)$error : ''; ?>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>
<div class="card"><form method="post" action="/production-plans/add" class="row"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.plan_date_required')) ?></label><input class="input" type="date" name="plan_date" required value="<?= e((string)($old['plan_date'] ?? '')) ?>"></div>
<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('common.machine')) ?> *</label><select class="u-style-81a7ab4e7d" name="machine_id" required><option value=""><?= e(t('select_machine')) ?></option><?php foreach ($machines as $m): ?><option value="<?= (int)$m['id'] ?>" <?= ((int)($old['machine_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>><?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?></option><?php endforeach; ?></select></div>
<div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('common.part')) ?> *</label><select class="u-style-81a7ab4e7d" name="product_id" required><option value=""><?= e(t('select_part')) ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.planned_qty_required')) ?></label><input class="input" type="number" step="0.01" name="planned_qty" required value="<?= e((string)($old['planned_qty'] ?? '')) ?>"></div>
<div class="u-style-f09611ef0f"><label class="muted u-style-98847c28df"><?= e(t('common.sequence')) ?></label><input class="input" type="number" name="sequence_no" value="<?= e((string)($old['sequence_no'] ?? '1')) ?>"></div>
<div class="u-style-ead6a4fcdb"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.runtime')) ?></label><input class="input" type="number" step="0.01" name="runtime" value="<?= e((string)($old['runtime'] ?? '')) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('common.status')) ?></label><select class="u-style-81a7ab4e7d" name="status"><?php foreach ($statusOptions as $opt): ?><option value="<?= e($opt) ?>" <?= ((string)($old['status'] ?? 'Planned') === $opt) ? 'selected' : '' ?>><?= e((string)($statusLabelMap[$opt] ?? $opt)) ?></option><?php endforeach; ?></select></div>
<div class="u-style-4ad81db508"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.plan_type')) ?></label><input class="input" name="plan_type" value="<?= e((string)($old['plan_type'] ?? 'Manual')) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.reference_doctype')) ?></label><input class="input" name="reference_doctype" value="<?= e((string)($old['reference_doctype'] ?? '')) ?>"></div>
<div class="u-style-a17b2f9a1e"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.reference_name')) ?></label><input class="input" name="reference_name" value="<?= e((string)($old['reference_name'] ?? '')) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('common.coverage')) ?> %</label><input class="input" type="number" step="0.01" name="coverage_pct" value="<?= e((string)($old['coverage_pct'] ?? '0')) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('common.shortage')) ?> <?= e(t('common.qty')) ?></label><input class="input" type="number" step="0.01" name="shortage_qty" value="<?= e((string)($old['shortage_qty'] ?? '0')) ?>"></div>
<div class="u-style-aae0403196"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.auto_created')) ?></label><select name="auto_created"><option value="0" <?= ((string)($old['auto_created'] ?? '0') === '0') ? 'selected' : '' ?>><?= e(t('module.production_plans.no')) ?></option><option value="1" <?= ((string)($old['auto_created'] ?? '0') === '1') ? 'selected' : '' ?>><?= e(t('module.production_plans.yes')) ?></option></select></div>
<div class="u-style-9405df25bc"><label class="muted u-style-98847c28df"><?= e(t('module.production_plans.added_by')) ?></label><input class="input" name="added_by" value="<?= e((string)($old['added_by'] ?? '')) ?>" <?= $canEditAddedBy ? '' : 'readonly' ?>></div>
<div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea></div>
<div class="row u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('save_production_plan')) ?></button><a class="btn" href="/production-plans"><?= e(t('common.cancel')) ?></a></div>
</form></div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
