<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$materials = is_array($materials ?? null) ? $materials : [];
$canManage = (bool)($canManage ?? false);
$statusBadge = static function (bool $active): string {
    return $active ? 'info' : 'muted';
};
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Material Master</h3>
      <div class="muted"> <?= e($tt('material_management.complete_catalog_of_label')) ?> </div>
    </div>
    <?php if ($canManage): ?>
      <div class="row">
        <a class="btn" href="#add-material"> <?= e($tt('material_management.master_link')) ?> </a>
      </div>
    <?php endif; ?>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Material</th>
          <th>Code</th>
          <th>Type</th>
          <th>Supplier</th>
          <th>UOM</th>
          <th>Safety Stock</th>
          <th>Reorder Point</th>
          <th>Max Stock</th>
          <th>Std Cost</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($materials === []): ?>
          <tr><td colspan="11"><div class="muted"> <?= e($tt('material_management.master_description')) ?> </div></td></tr>
        <?php else: ?>
          <?php foreach ($materials as $row): ?>
            <tr>
              <td>
                <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                <?php if (!empty($row['description'])): ?>
                  <div class="muted"><?= e((string)$row['description']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '-')) ?></span></td>
              <td><?= e((string)($row['material_type'] ?? '-')) ?></td>
              <td><?= e((string)($row['vendor_name'] ?? $row['supplier_name'] ?? '-')) ?></td>
              <td><?= e((string)($row['uom'] ?? $row['unit'] ?? '-')) ?></td>
              <td><?= number_format((float)($row['minimum_stock_qty'] ?? $row['safety_stock_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['reorder_point_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['maximum_stock_qty'] ?? $row['max_storage_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['standard_cost'] ?? 0), 2) ?></td>
              <td>
                <span class="status-chip <?= $statusBadge((bool)($row['is_active'] ?? true)) ?>">
                  <?= (bool)($row['is_active'] ?? true) ? 'Active' : 'Inactive' ?>
                </span>
              </td>
              <td>
                <a class="btn" href="/apps/manufacturing/materials/detail?material_id=<?= (int)($row['id'] ?? 0) ?>">Detail</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($canManage): ?>
<section class="card" id="add-material">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Add Material</h3>
      <div class="muted">Register a new raw material, component, or consumable into the master catalog.</div>
    </div>
  </div>
  <form method="post" action="/apps/manufacturing/materials/master" class="form-grid u-mt-10">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label class="form-field">
      <span class="field-label">Material Name <span class="required">*</span></span>
      <input class="input" type="text" name="material_name" required placeholder="e.g. Steel Sheet 2mm">
    </label>
    <label class="form-field">
      <span class="field-label">Material Code</span>
      <input class="input" type="text" name="material_code" placeholder="e.g. SS-2MM-01">
    </label>
    <label class="form-field">
      <span class="field-label">Material Type</span>
      <select name="material_type">
        <option value="">— Select —</option>
        <option value="raw">Raw Material</option>
        <option value="component">Component</option>
        <option value="consumable">Consumable</option>
        <option value="packaging">Packaging</option>
        <option value="semi_finished">Semi-Finished</option>
        <option value="finished">Finished Good</option>
      </select>
    </label>
    <label class="form-field">
      <span class="field-label">Unit of Measure</span>
      <input class="input" type="text" name="uom" placeholder="e.g. kg, pcs, m, L">
    </label>
    <label class="form-field">
      <span class="field-label">Supplier / Vendor</span>
      <input class="input" type="text" name="supplier_name" placeholder="Primary supplier name">
    </label>
    <label class="form-field">
      <span class="field-label">Storage Location</span>
      <input class="input" type="text" name="storage_location" placeholder="e.g. Warehouse A, Row 3">
    </label>
    <label class="form-field">
      <span class="field-label">Lead Time (days)</span>
      <input class="input" type="number" name="lead_time_days" min="0" step="1" placeholder="7">
    </label>
    <label class="form-field">
      <span class="field-label">Standard Cost</span>
      <input class="input" type="number" name="standard_cost" step="0.0001" min="0" placeholder="0.00">
    </label>
    <label class="form-field">
      <span class="field-label">Safety Stock Qty</span>
      <input class="input" type="number" name="safety_stock_qty" step="0.01" min="0" placeholder="0.00">
    </label>
    <label class="form-field">
      <span class="field-label">Reorder Point Qty</span>
      <input class="input" type="number" name="reorder_point_qty" step="0.01" min="0" placeholder="0.00">
    </label>
    <label class="form-field">
      <span class="field-label">Max Storage Qty</span>
      <input class="input" type="number" name="max_storage_qty" step="0.01" min="0" placeholder="0.00">
    </label>
    <label class="form-field">
      <span class="field-label">Scrap Rate (%)</span>
      <input class="input" type="number" name="scrap_rate_pct" step="0.01" min="0" max="100" placeholder="0.00">
    </label>
    <label class="form-field form-field-full">
      <span class="field-label">Description</span>
      <textarea name="description" rows="2" placeholder="Optional material description or notes"></textarea>
    </label>
    <div class="form-actions">
      <button class="btn ok" type="submit">Save Material</button>
      <a class="btn" href="/apps/manufacturing/materials/master">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
