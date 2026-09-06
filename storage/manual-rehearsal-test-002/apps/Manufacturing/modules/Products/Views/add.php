<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info"><h2><?= e(t('module.products.add')) ?></h2></div>
    <div class="module-header-actions"><a class="btn" href="/products"><?= e(t('common.back_to_list')) ?></a></div>
  </div>
</div>

<?php if (!empty($error ?? '')): ?><div class="card notice-err"><?= e((string)$error) ?></div><?php endif; ?>

<div class="card">
  <form method="post" action="/products/add" class="form-grid">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('module.products.part_name')) ?> *</label>
      <input class="input" name="parts_name" required value="<?= e((string)($old['parts_name'] ?? '')) ?>">
    </div>
    <div class="form-field-wide">
      <label class="field-label"><?= e(t('module.products.part_number')) ?> *</label>
      <input class="input" name="parts_number" required value="<?= e((string)($old['parts_number'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('common.model')) ?></label>
      <input class="input" name="model" value="<?= e((string)($old['model'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('common.producer')) ?></label>
      <input class="input" name="producer" value="<?= e((string)($old['producer'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('common.lead')) ?></label>
      <input class="input" name="lead" value="<?= e((string)($old['lead'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label">Cycle Time (min/unit)</label>
      <input class="input" type="number" step="0.01" min="0" name="cycle_time" value="<?= e((string)($old['cycle_time'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label">QC Time Per Item (minutes)</label>
      <input class="input" type="number" step="0.01" min="0" name="qc_time_per_item" value="<?= e((string)($old['qc_time_per_item'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="field-label">Supply Mode</label>
      <select name="supply_mode">
        <?php foreach ((array)($supply_mode_options ?? []) as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= ((string)($old['supply_mode'] ?? 'in_house') === (string)$value) ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label">Fulfillment Mode</label>
      <select name="fulfillment_mode">
        <?php foreach ((array)($fulfillment_mode_options ?? []) as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= ((string)($old['fulfillment_mode'] ?? 'company_to_destination') === (string)$value) ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label"><?= e(t('common.status')) ?></label>
      <select name="is_active">
        <option value="1" <?= ((string)($old['is_active'] ?? '1') === '1') ? 'selected' : '' ?>><?= e(t('common.active')) ?></option>
        <option value="0" <?= ((string)($old['is_active'] ?? '1') === '0') ? 'selected' : '' ?>><?= e(t('common.inactive')) ?></option>
      </select>
    </div>
    <div class="form-field">
      <label class="field-label">Default Supplier</label>
      <input class="input" name="default_supplier" value="<?= e((string)($old['default_supplier'] ?? '')) ?>" placeholder="Supplier name or code">
    </div>
    <div class="form-field">
      <label class="field-label">Default Procurement Lead Days</label>
      <input class="input" type="number" step="0.01" min="0" name="default_procurement_lead_days" value="<?= e((string)($old['default_procurement_lead_days'] ?? '')) ?>">
    </div>
    <div class="form-field">
      <label class="checkbox-label">
        <input type="hidden" name="requires_ipm_qc" value="0">
        <input type="checkbox" name="requires_ipm_qc" value="1" <?= ((string)($old['requires_ipm_qc'] ?? '1') !== '0') ? 'checked' : '' ?>>
        <span class="field-label u-style-8000819c9b">Requires IPM QC</span>
      </label>
    </div>
    <div class="form-field">
      <label class="checkbox-label">
        <input type="hidden" name="stocked_at_ipm" value="0">
        <input type="checkbox" name="stocked_at_ipm" value="1" <?= ((string)($old['stocked_at_ipm'] ?? '1') !== '0') ? 'checked' : '' ?>>
        <span class="field-label u-style-8000819c9b">Stocked At IPM</span>
      </label>
    </div>

    <!-- ══ Operational Routing Section ══ -->
    <div class="form-section">
      <h3>Operational Routing Profile</h3>
    </div>

    <div class="form-field">
      <label class="checkbox-label">
        <input type="hidden" name="requires_assembly" value="0">
        <input type="checkbox" name="requires_assembly" value="1" <?= ((string)($old['requires_assembly'] ?? '0') !== '0') ? 'checked' : '' ?>>
        <span class="field-label u-style-8000819c9b">Requires Assembly</span>
      </label>
    </div>

    <div class="form-field">
      <label class="checkbox-label">
        <input type="hidden" name="requires_processing" value="0">
        <input type="checkbox" name="requires_processing" value="1" <?= ((string)($old['requires_processing'] ?? '1') !== '0') ? 'checked' : '' ?>>
        <span class="field-label u-style-8000819c9b">Requires Processing</span>
      </label>
    </div>

    <div class="form-field">
      <label class="field-label">Dispatch Mode</label>
      <select name="dispatch_mode">
        <?php foreach ((array)($dispatch_mode_options ?? []) as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= ((string)($old['dispatch_mode'] ?? '') === (string)$value) ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-field">
      <label class="checkbox-label">
        <input type="hidden" name="dispatch_as_is" value="0">
        <input type="checkbox" name="dispatch_as_is" value="1" <?= ((string)($old['dispatch_as_is'] ?? '0') !== '0') ? 'checked' : '' ?>>
        <span class="field-label u-style-8000819c9b">Dispatch As-Is (no assembly/processing required)</span>
      </label>
    </div>

    <!-- ══ Buffer / Planning Section ══ -->
    <div class="form-section">
      <h3>Buffer & Planning</h3>
    </div>

    <div class="form-field">
      <label class="field-label">Essential Stock Qty (safety buffer)</label>
      <input class="input" type="number" step="0.01" min="0" name="essential_stock_qty" value="<?= e((string)($old['essential_stock_qty'] ?? '')) ?>" placeholder="Minimum qty to maintain">
    </div>

    <div class="form-field">
      <label class="field-label">Planning Window (days)</label>
      <input class="input" type="number" min="0" name="planning_window_days" value="<?= e((string)($old['planning_window_days'] ?? '')) ?>" placeholder="Days ahead to plan for">
    </div>

    <div class="form-field">
      <label class="field-label">Safety Stock</label>
      <input class="input" type="number" step="0.01" min="0" name="safety_stock_qty" value="<?= e((string)($old['safety_stock_qty'] ?? $old['max_buffer_qty'] ?? '')) ?>" placeholder="Safety stock to hold against daily demand">
    </div>

    <div class="form-section">
      <h3>Packaging Profile</h3>
    </div>

    <div class="form-field">
      <label class="field-label">Qty Per Case</label>
      <input class="input" type="number" min="0" step="1" name="qty_per_case" value="<?= e((string)($old['qty_per_case'] ?? '')) ?>" placeholder="Parts packed in one case">
    </div>

    <div class="form-field">
      <label class="field-label">Case Type</label>
      <input class="input" name="case_type" value="<?= e((string)($old['case_type'] ?? '')) ?>" placeholder="Carton / Tote / Bin">
    </div>

    <div class="form-field-wide">
      <label class="field-label">Case Spec</label>
      <input class="input" name="case_spec" value="<?= e((string)($old['case_spec'] ?? '')) ?>" placeholder="Dimensions, grade, or packing note">
    </div>

    <div class="form-field">
      <label class="field-label">Default Case Number</label>
      <input class="input" name="default_case_number" value="<?= e((string)($old['default_case_number'] ?? '')) ?>" placeholder="Case code / standard pack no.">
    </div>

    <div class="form-field">
      <label class="field-label">Cases Per Pallet</label>
      <input class="input" type="number" min="0" step="1" name="cases_per_pallet" value="<?= e((string)($old['cases_per_pallet'] ?? '')) ?>" placeholder="Cases on one pallet">
    </div>

    <div class="form-field-full">
      <label class="field-label"><?= e(t('common.notes')) ?></label>
      <textarea name="notes"><?= e((string)($old['notes'] ?? '')) ?></textarea>
    </div>
    <div class="form-field-full">
      <label class="field-label">Default Supply Note</label>
      <textarea name="default_supply_note"><?= e((string)($old['default_supply_note'] ?? '')) ?></textarea>
    </div>
    <div class="form-actions">
      <button class="btn ok" type="submit"><?= e(t('module.products.save')) ?></button>
      <a class="btn" href="/products"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
