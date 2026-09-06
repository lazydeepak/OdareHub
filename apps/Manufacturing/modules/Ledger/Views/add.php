<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('add_stock_ledger_entry')) ?></h2>
      <div class="muted"><?= e(t('post_manual_inventory_movement')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/ledger"><?= e(t('back_to_ledger')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <form method="post" action="/ledger/add" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('part')) ?> *</label>
      <select name="product_id" required>
        <option value=""><?= e(t('select_part')) ?></option>
        <?php foreach ($products as $product): ?>
          <option value="<?= (int)$product['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$product['id']) ? 'selected' : '' ?>><?= e((string)$product['parts_name']) ?> (<?= e((string)$product['parts_number']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('movement_type')) ?> *</label>
      <select name="movement_type" required>
        <?php foreach (['IN', 'OUT', 'ADJUST', 'PRODUCTION_IN', 'PRODUCTION_OUT', 'DISPATCH_OUT'] as $movement): ?>
          <option value="<?= e($movement) ?>" <?= ((string)($old['movement_type'] ?? 'ADJUST') === $movement) ? 'selected' : '' ?>><?= e($movement) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('quantity')) ?> *</label>
      <input class="input" type="number" step="0.01" name="qty" required value="<?= e((string)($old['qty'] ?? '')) ?>">
      <div class="muted u-style-b2a9e0c14a"><?= e(t('ledger_quantity_help')) ?></div>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('reference_no')) ?></label>
      <input class="input" name="reference_no" value="<?= e((string)($old['reference_no'] ?? '')) ?>">
    </div>
    <div class="form-field-full">
      <label class="field-label"><?= e(t('notes')) ?></label>
      <textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea>
    </div>
    <div class="form-actions">
      <button class="btn ok" type="submit"><?= e(t('post_ledger_entry')) ?></button>
      <a class="btn" href="/ledger"><?= e(t('cancel')) ?></a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
