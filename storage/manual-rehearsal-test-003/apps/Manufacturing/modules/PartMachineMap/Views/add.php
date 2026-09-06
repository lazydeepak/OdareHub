<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info"><h2><?= e(t('add_part_machine_mapping')) ?></h2></div>
    <div class="module-header-actions"><a class="btn" href="/part-machine-map"><?= e(t('back_to_list')) ?></a></div>
  </div>
</div>

<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/part-machine-map/add" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('part')) ?> *</label>
      <select name="product_id" required>
        <option value=""><?= e(t('select_part')) ?></option>
        <?php foreach ($products as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= ((int)($old['product_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
            <?= e((string)$p['parts_name']) ?> (<?= e((string)$p['parts_number']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('machine')) ?> *</label>
      <select name="machine_id" required>
        <option value=""><?= e(t('select_machine')) ?></option>
        <?php foreach ($machines as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= ((int)($old['machine_id'] ?? 0) === (int)$m['id']) ? 'selected' : '' ?>>
            <?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('active')) ?></label>
      <select name="is_active">
        <option value="1" <?= ((string)($old['is_active'] ?? '1') === '1') ? 'selected' : '' ?>><?= e(t('active')) ?></option>
        <option value="0" <?= ((string)($old['is_active'] ?? '1') === '0') ? 'selected' : '' ?>><?= e(t('inactive')) ?></option>
      </select>
    </div>
    <div class="form-field-full">
      <label class="field-label"><?= e(t('notes')) ?></label>
      <textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea>
    </div>
    <div class="form-actions">
      <button class="btn ok" type="submit"><?= e(t('save_mapping')) ?></button>
      <a class="btn" href="/part-machine-map"><?= e(t('cancel')) ?></a>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
