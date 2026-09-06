<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2><?= e(t('platform.qrcode.stock_add_title')) ?></h2>
      <div class="muted"><?= e(t('platform.qrcode.stock_add_subtitle')) ?></div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/qr/stock"><?= e(t('platform.qrcode.back_to_stock_updates')) ?></a>
    </div>
  </div>
</div>
<?php if (!empty($flash ?? '')): ?><div class="card notice-ok"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>
<div class="card">
  <div class="muted u-style-761d3addb2"><?= e(t('platform.qrcode.stock_add_ledger_hint')) ?></div>
  <form method="post" action="/qr/stock/add" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('platform.qrcode.part_required')) ?></label>
      <select name="product_id" required>
        <option value=""><?= e(t('platform.qrcode.select_part')) ?></option>
        <?php foreach ($products as $product): ?>
          <option value="<?= (int)$product['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$product['id']) ? 'selected' : '' ?>><?= e((string)$product['parts_name']) ?> (<?= e((string)$product['parts_number']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('platform.qrcode.movement_required')) ?></label>
      <select name="movement_type" required>
        <?php foreach (['IN', 'OUT', 'ADJUST'] as $movement): ?>
          <option value="<?= e($movement) ?>" <?= ((string)($old['movement_type'] ?? 'ADJUST') === $movement) ? 'selected' : '' ?>><?= e($movement) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('platform.qrcode.quantity_required')) ?></label>
      <input class="input" type="number" step="0.01" min="0.01" name="qty" required value="<?= e((string)($old['qty'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('platform.qrcode.reference_no')) ?></label>
      <input class="input" name="reference_no" value="<?= e((string)($old['reference_no'] ?? '')) ?>">
    </div>
    <div class="form-field-full">
      <label class="field-label"><?= e(t('common.notes')) ?></label>
      <textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea>
    </div>
    <div class="form-actions">
      <button class="btn ok" type="submit"><?= e(t('platform.qrcode.save_stock_update')) ?></button>
      <a class="btn" href="/qr/stock"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
