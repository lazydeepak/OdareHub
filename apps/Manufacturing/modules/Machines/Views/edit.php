<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info"><h2><?= e(t('edit')) ?> <?= e(t('machines_title')) ?> #<?= (int)$row['id'] ?></h2></div>
    <div class="module-header-actions"><a class="btn" href="/machines"><?= e(t('back_to_list')) ?></a></div>
  </div>
</div>

<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/machines/edit" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <div class="form-field">
      <label class="field-label"><?= e(t('machine_no')) ?> *</label>
      <input class="input" name="machine_no" required value="<?= e((string)$row['machine_no']) ?>">
    </div>
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('machine_name')) ?> *</label>
      <input class="input" name="machine_name" required value="<?= e((string)$row['machine_name']) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('section')) ?></label>
      <input class="input" name="section" value="<?= e((string)($row['section'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.machine_group')) ?></label>
      <input class="input" name="machine_group" value="<?= e((string)($row['machine_group'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.machine_type')) ?></label>
      <input class="input" name="machine_type" value="<?= e((string)($row['machine_type'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('status')) ?></label>
      <input class="input" name="status" value="<?= e((string)($row['status'] ?? t('active'))) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('capacity_per_hour')) ?></label>
      <input class="input" name="capacity_per_hour" type="number" step="0.01" value="<?= e((string)($row['capacity_per_hour'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.clamping_force_ton')) ?></label>
      <input class="input" name="clamping_force_ton" type="number" step="0.01" value="<?= e((string)($row['clamping_force_ton'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.shot_capacity_g')) ?></label>
      <input class="input" name="shot_capacity_g" type="number" step="0.01" value="<?= e((string)($row['shot_capacity_g'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.tie_bar_spacing_mm')) ?></label>
      <input class="input" name="tie_bar_spacing_mm" value="<?= e((string)($row['tie_bar_spacing_mm'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.platen_size_mm')) ?></label>
      <input class="input" name="platen_size_mm" value="<?= e((string)($row['platen_size_mm'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.min_mold_height_mm')) ?></label>
      <input class="input" name="min_mold_height_mm" type="number" step="0.01" value="<?= e((string)($row['min_mold_height_mm'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('module.machines.max_mold_height_mm')) ?></label>
      <input class="input" name="max_mold_height_mm" type="number" step="0.01" value="<?= e((string)($row['max_mold_height_mm'] ?? '')) ?>">
    </div>
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('module.machines.preferred_materials')) ?></label>
      <input class="input" name="preferred_materials" value="<?= e((string)($row['preferred_materials'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('active')) ?></label>
      <select name="is_active">
        <option value="1" <?= ((int)($row['is_active'] ?? 1) === 1) ? 'selected' : '' ?>><?= e(t('active')) ?></option>
        <option value="0" <?= ((int)($row['is_active'] ?? 1) === 0) ? 'selected' : '' ?>><?= e(t('inactive')) ?></option>
      </select>
    </div>
    <div class="form-field-full">
      <label class="field-label"><?= e(t('notes')) ?></label>
      <textarea name="notes"><?= e((string)($row['notes'] ?? '')) ?></textarea>
    </div>
    <div class="form-actions">
      <button class="btn ok" type="submit"><?= e(t('update_machine')) ?></button>
      <a class="btn" href="/machines"><?= e(t('cancel')) ?></a>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
