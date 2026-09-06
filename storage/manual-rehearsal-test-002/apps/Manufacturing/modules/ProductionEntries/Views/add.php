<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$quickUpdate = !empty($quick_update);
$redirect = (string)($redirect ?? '');
$old = is_array($old ?? null) ? $old : [];
$machines = is_array($machines ?? null) ? $machines : [];
$products = is_array($products ?? null) ? $products : [];
$selectedMachineId = (int)($old['machine_id'] ?? 0);
?>

<?php if ($quickUpdate): ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891">Update Production Output</h2>
      <div class="muted">Fast production update for assigned parts and machines only.</div>
    </div>
    <div class="module-header-actions">
      <?php if ($redirect !== ''): ?>
        <a class="btn" href="<?= e($redirect) ?>">Back</a>
      <?php else: ?>
        <a class="btn" href="/production-entries">Back to Entries</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"><?= e(t('add_production_entry')) ?></h2>
      <div class="muted">Record actual production output and keep ledger-backed stock in sync.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/production-entries"><?= e(t('back_to_list')) ?></a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($error ?? '')): ?>
  <div class="card notice-err"><?= e((string)$error) ?></div>
<?php endif; ?>

<?php if ($quickUpdate): ?>
<div class="card">
  <form method="post" action="/production-entries/add" class="control-row">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <?php if ($redirect !== ''): ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
    <?php endif; ?>
    <input type="hidden" name="production_date" value="<?= e((string)($old['production_date'] ?? date('Y-m-d'))) ?>">
    <input type="hidden" name="shift" value="<?= e((string)($old['shift'] ?? 'Day')) ?>">
    <input type="hidden" name="rejected_qty" value="<?= e((string)($old['rejected_qty'] ?? '0')) ?>">
    <input type="hidden" name="status" value="<?= e((string)($old['status'] ?? 'Draft')) ?>">
    <input type="hidden" name="notes" value="<?= e((string)($old['notes'] ?? '')) ?>">

    <?php if (count($machines) === 1): ?>
      <input type="hidden" name="machine_id" value="<?= (int)($machines[0]['id'] ?? 0) ?>">
      <div class="control-field">
        <span class="control-label">Machine</span>
        <div class="ui-block"><?= e((string)($machines[0]['machine_no'] ?? '')) ?> - <?= e((string)($machines[0]['machine_name'] ?? '')) ?></div>
      </div>
    <?php else: ?>
      <label class="control-field">
        <span class="control-label"><?= e(t('machine')) ?></span>
        <select name="machine_id" required>
          <option value=""><?= e(t('select_machine')) ?></option>
          <?php foreach ($machines as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= $selectedMachineId === (int)$m['id'] ? 'selected' : '' ?>>
              <?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
    <?php endif; ?>

    <label class="control-field">
      <span class="control-label"><?= e(t('part')) ?></span>
      <select name="product_id" required>
        <option value=""><?= e(t('select_part')) ?></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
            <?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label class="control-field-compact">
      <span class="control-label">Good Output</span>
      <input class="input" type="number" step="0.01" min="0" name="produced_qty" required value="<?= e((string)($old['produced_qty'] ?? '')) ?>" placeholder="0">
    </label>

    <div class="control-actions">
      <button class="btn ok" type="submit"> <?= e($tt('production_entries.save_action')) ?> </button>
      <?php if ($redirect !== ''): ?>
        <a class="btn" href="<?= e($redirect) ?>"> <?= e($tt('common.cancel_action')) ?> </a>
      <?php else: ?>
        <a class="btn" href="/production-entries"> <?= e($tt('common.cancel_action')) ?> </a>
      <?php endif; ?>
    </div>
  </form>
</div>
<?php else: ?>
<div class="card">
  <form method="post" action="/production-entries/add" class="row">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <?php if ($redirect !== ''): ?>
      <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
    <?php endif; ?>
    <div class="u-style-eb62184e30"><label class="muted u-style-98847c28df">Production Date *</label><input class="input" type="date" name="production_date" required value="<?= e((string)($old['production_date'] ?? '')) ?>"></div>
    <div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Shift</label><input class="input" name="shift" value="<?= e((string)($old['shift'] ?? 'Day')) ?>"></div>
    <div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('machine')) ?> *</label><select class="u-style-81a7ab4e7d" name="machine_id" required><option value=""><?= e(t('select_machine')) ?></option><?php foreach ($machines as $m): ?><option value="<?= (int)$m['id'] ?>" <?= ((int)($old['machine_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>><?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?></option><?php endforeach; ?></select></div>
    <div class="u-style-6c0d09d5fa"><label class="muted u-style-98847c28df"><?= e(t('part')) ?> *</label><select class="u-style-81a7ab4e7d" name="product_id" required><option value=""><?= e(t('select_part')) ?></option><?php foreach ($products as $p): ?><option value="<?= (int)$p['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>><?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)</option><?php endforeach; ?></select></div>
    <div class="u-style-aae0403196"><label class="muted u-style-98847c28df">Produced Qty *</label><input class="input" type="number" step="0.01" name="produced_qty" required value="<?= e((string)($old['produced_qty'] ?? '')) ?>"></div>
    <div class="u-style-aae0403196"><label class="muted u-style-98847c28df"> <?= e($tt('production_entries.rejected_qty_label')) ?> </label><input class="input" type="number" step="0.01" name="rejected_qty" required value="<?= e((string)($old['rejected_qty'] ?? '0')) ?>"></div>
    <div class="u-style-2f7ab6d1f3"><label class="muted u-style-98847c28df"> <?= e($tt('common.status_label')) ?> </label><input class="input" name="status" value="<?= e((string)($old['status'] ?? 'Draft')) ?>"></div>
    <div class="u-style-4ca11fcb42"><label class="muted u-style-98847c28df"><?= e(t('notes')) ?></label><textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea></div>
    <div class="row u-style-0466783d98"><button class="btn ok" type="submit"><?= e(t('save_production_entry')) ?></button><a class="btn" href="/production-entries"><?= e(t('cancel')) ?></a></div>
  </form>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
