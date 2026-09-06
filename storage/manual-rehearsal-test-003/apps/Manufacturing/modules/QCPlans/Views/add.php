<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('add_qc_plan')) ?></h2><a class="btn" href="/qc-plans"><?= e(t('back_to_list')) ?></a></div></div>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card"><form method="post" action="/qc-plans/add" class="row qc-add-form u-style-45ee13f2f2"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
<div class="qc-date-field"><label class="muted u-style-98847c28df">Plan Date *</label><input class="input" type="date" name="plan_date" required value="<?= e((string)($old['plan_date'] ?? '')) ?>"></div>
<div class="qc-date-field"><label class="muted u-style-98847c28df">Required Date</label><input class="input" type="date" name="required_date" value="<?= e((string)($old['required_date'] ?? '')) ?>"></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df"><?= e(t('part')) ?> *</label><select name="product_id" required><option value=""><?= e(t('select_part')) ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df">Daily Order (optional)</label><select name="daily_order_id"><option value="0">None</option><?php foreach ($daily_orders as $d): ?><option value="<?= (int)$d['id'] ?>" <?= ((int)($old['daily_order_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>#<?= (int)$d['id'] ?> <?= e((string)$d['customer_name']) ?> (<?= e((string)$d['order_date']) ?>)</option><?php endforeach; ?></select></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df">Production Entry (optional)</label><select name="production_entry_id"><option value="0">None</option><?php foreach ($production_entries as $pe): ?><option value="<?= (int)$pe['id'] ?>" <?= ((int)($old['production_entry_id'] ?? 0) === (int)$pe['id']) ? 'selected' : '' ?>>#<?= (int)$pe['id'] ?> <?= e((string)$pe['production_date']) ?> (good <?= e((string)$pe['good_qty']) ?>)</option><?php endforeach; ?></select></div>
<div class="qc-qty-field"><label class="muted u-style-98847c28df">Planned Qty *</label><input class="input" type="number" step="0.01" name="planned_qty" required value="<?= e((string)($old['planned_qty'] ?? '')) ?>"></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df">Est. Minutes</label><input class="input" type="number" id="estimated_time_minutes" name="estimated_time_minutes" value="<?= e((string)($old['estimated_time_minutes'] ?? '0')) ?>"></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df">Priority</label><select name="priority"><option value="Critical" <?= ((string)($old['priority'] ?? 'Normal') === 'Critical') ? 'selected' : '' ?>>Critical</option><option value="High" <?= ((string)($old['priority'] ?? 'Normal') === 'High') ? 'selected' : '' ?>>High</option><option value="Medium" <?= ((string)($old['priority'] ?? 'Normal') === 'Medium') ? 'selected' : '' ?>>Medium</option><option value="Normal" <?= ((string)($old['priority'] ?? 'Normal') === 'Normal') ? 'selected' : '' ?>>Normal</option><option value="Low" <?= ((string)($old['priority'] ?? 'Normal') === 'Low') ? 'selected' : '' ?>>Low</option></select></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df"> <?= e($tt('q_c_plans.added_by_label')) ?> </label><input class="input" type="text" name="added_by" placeholder="Owner name" value="<?= e((string)($old['added_by'] ?? '')) ?>"></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df">Assigned to</label><input class="input" type="text" name="assigned_to" placeholder="Name or email" value="<?= e((string)($old['assigned_to'] ?? '')) ?>"></div>
<div class="qc-notes-field"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea></div>
<input type="hidden" name="status" value="System Generated">
<input type="hidden" name="verified_by" value="<?= e((string)($old['verified_by'] ?? '')) ?>">
<input type="hidden" name="approved_by" value="<?= e((string)($old['approved_by'] ?? '')) ?>">
<div class="row qc-form-actions u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('save_qc_plan')) ?></button><a class="btn" href="/qc-plans"><?= e(t('cancel')) ?></a></div>
</form></div>
<script>
  (function() {
    const productsData = <?= json_encode(array_reduce($products, function($carry, $p) {
      $carry[(int)$p['id']] = (float)($p['qc_time_per_item'] ?? 0);
      return $carry;
    }, [])) ?>;
    
    const productSelect = document.querySelector('select[name="product_id"]');
    const plannedQtyInput = document.querySelector('input[name="planned_qty"]');
    const estTimeInput = document.getElementById('estimated_time_minutes');
    
    function calculateEstTime() {
      const productId = parseInt(productSelect.value);
      const plannedQty = parseFloat(plannedQtyInput.value) || 0;
      const qcTimePerItem = productsData[productId] || 0;
      
      if (qcTimePerItem > 0 && plannedQty > 0) {
        estTimeInput.value = Math.round(qcTimePerItem * plannedQty);
      }
    }
    
    if (productSelect) productSelect.addEventListener('change', calculateEstTime);
    if (plannedQtyInput) plannedQtyInput.addEventListener('change', calculateEstTime);
    if (plannedQtyInput) plannedQtyInput.addEventListener('input', calculateEstTime);
  })();
</script>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
