<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<div class="card"><div class="row u-style-2d7d2729a4"><h2 class="u-style-1169661891"><?= e(t('edit')) ?> QC Plan #<?= (int)$row['id'] ?></h2><a class="btn" href="/qc-plans"><?= e(t('back_to_list')) ?></a></div></div>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card"><form method="post" action="/qc-plans/edit" class="row qc-edit-form u-style-45ee13f2f2"><input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="qc-date-field"><label class="muted u-style-98847c28df">Plan Date *</label><input class="input" type="date" name="plan_date" required value="<?= e((string)$row['plan_date']) ?>"></div>
<div class="qc-date-field"><label class="muted u-style-98847c28df">Required Date</label><input class="input" type="date" name="required_date" value="<?= e((string)($row['required_date'] ?? '')) ?>"></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df"><?= e(t('part')) ?> *</label><select name="product_id" required><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)$row['product_id'] === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df">Daily Order</label><select name="daily_order_id"><option value="0">None</option><?php foreach ($daily_orders as $d): ?><option value="<?= (int)$d['id'] ?>" <?= ((int)($row['daily_order_id'] ?? 0) === (int)$d['id']) ? 'selected' : '' ?>>#<?= (int)$d['id'] ?> <?= e((string)$d['customer_name']) ?></option><?php endforeach; ?></select></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df">Production Entry</label><select name="production_entry_id"><option value="0">None</option><?php foreach ($production_entries as $pe): ?><option value="<?= (int)$pe['id'] ?>" <?= ((int)($row['production_entry_id'] ?? 0) === (int)$pe['id']) ? 'selected' : '' ?>>#<?= (int)$pe['id'] ?> <?= e((string)$pe['production_date']) ?></option><?php endforeach; ?></select></div>
<div class="qc-qty-field"><label class="muted u-style-98847c28df">Planned Qty *</label><input class="input" type="number" step="0.01" name="planned_qty" required value="<?= e((string)$row['planned_qty']) ?>"></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df">Est. Minutes</label><input class="input" type="number" id="estimated_time_minutes" name="estimated_time_minutes" value="<?= e((string)$row['estimated_time_minutes']) ?>"></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df">Priority</label><select name="priority"><option value="Critical" <?= ((string)$row['priority'] === 'Critical') ? 'selected' : '' ?>>Critical</option><option value="High" <?= ((string)$row['priority'] === 'High') ? 'selected' : '' ?>>High</option><option value="Medium" <?= ((string)$row['priority'] === 'Medium') ? 'selected' : '' ?>>Medium</option><option value="Normal" <?= ((string)$row['priority'] === 'Normal') ? 'selected' : '' ?>>Normal</option><option value="Low" <?= ((string)$row['priority'] === 'Low') ? 'selected' : '' ?>>Low</option></select></div>
<div class="qc-select-field"><label class="muted u-style-98847c28df"> <?= e($tt('common.status_label')) ?> </label><select name="status"><?php foreach (($status_options ?? []) as $opt): ?><option value="<?= e((string)$opt) ?>" <?= ((string)$row['status'] === (string)$opt) ? 'selected' : '' ?>><?= e((string)$opt) ?></option><?php endforeach; ?></select></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df">Added by</label><input class="input" type="text" name="added_by" placeholder="Owner name" value="<?= e((string)($row['added_by'] ?? '')) ?>"></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df">Verified by</label><input class="input" type="text" name="verified_by" placeholder="QC owner name" value="<?= e((string)($row['verified_by'] ?? '')) ?>"></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df"> <?= e($tt('q_c_plans.approved_by_label')) ?> </label><input class="input" type="text" name="approved_by" placeholder="Authority name" value="<?= e((string)($row['approved_by'] ?? '')) ?>"></div>
<div class="qc-text-field"><label class="muted u-style-98847c28df">Assigned to</label><input class="input" type="text" name="assigned_to" placeholder="Name or email" value="<?= e((string)($row['assigned_to'] ?? '')) ?>"></div>
<div class="qc-notes-field"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($row['notes'] ?? '')) ?></textarea></div>
<div class="row qc-form-actions u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('update_qc_plan')) ?></button><a class="btn" href="/qc-plans"><?= e(t('cancel')) ?></a></div>
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
