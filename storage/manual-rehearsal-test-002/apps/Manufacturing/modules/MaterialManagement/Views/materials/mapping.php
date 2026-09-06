<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$mappingRows = is_array($mappingRows ?? null) ? $mappingRows : [];
$productOptions = is_array($productOptions ?? null) ? $productOptions : [];
$materialOptions = is_array($materialOptions ?? null) ? $materialOptions : [];
$canManage = (bool)($canManage ?? false);
$qFilter = trim((string)($qFilter ?? ''));
$productIdFilter = (int)($productIdFilter ?? 0);
$materialIdFilter = (int)($materialIdFilter ?? 0);

// Client-side filter (GET param pre-filter applied in controller)
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Part–Material Mapping</h3>
      <div class="muted">Bill of materials linkage showing which raw materials and components are consumed per finished part.</div>
    </div>
    <?php if ($canManage): ?>
      <div class="row">
        <a class="btn" href="#add-mapping"> <?= e($tt('material_management.mapping_link')) ?> </a>
      </div>
    <?php endif; ?>
  </div>

  <form method="get" action="/apps/manufacturing/materials/mapping" class="form-grid u-mt-10">
    <label class="form-field">
      <span class="field-label">Search</span>
      <input class="input" type="text" name="q" value="<?= e($qFilter) ?>" placeholder="Part name, material name, code">
    </label>
    <label class="form-field">
      <span class="field-label"> <?= e($tt('material_management.filter_by_part_label')) ?> </span>
      <select name="product_id">
        <option value="">All parts</option>
        <?php foreach ($productOptions as $opt): ?>
          <option value="<?= (int)($opt['id'] ?? 0) ?>" <?= $productIdFilter === (int)($opt['id'] ?? 0) ? 'selected' : '' ?>>
            <?= e((string)($opt['parts_name'] ?? '')) ?> <?= !empty($opt['parts_number']) ? '(' . e((string)$opt['parts_number']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-field">
      <span class="field-label"> <?= e($tt('material_management.filter_by_material_label')) ?> </span>
      <select name="material_id">
        <option value="">All materials</option>
        <?php foreach ($materialOptions as $opt): ?>
          <option value="<?= (int)($opt['id'] ?? 0) ?>" <?= $materialIdFilter === (int)($opt['id'] ?? 0) ? 'selected' : '' ?>>
            <?= e((string)($opt['material_name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-actions">
      <button class="btn" type="submit">Filter</button>
      <a class="btn" href="/apps/manufacturing/materials/mapping">Reset</a>
    </div>
  </form>

  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Part</th>
          <th>Material</th>
          <th>Qty / Part</th>
          <th>UOM</th>
          <th>Scrap %</th>
          <th>Seq.</th>
          <th>Effective From</th>
          <th>Type</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($mappingRows === []): ?>
          <tr><td colspan="8"><div class="muted">No mappings found. Add the first part–material link below.</div></td></tr>
        <?php else: ?>
          <?php foreach ($mappingRows as $row): ?>
            <tr>
              <td>
                <strong><?= e((string)($row['parts_name'] ?? '-')) ?></strong>
                <?php if (!empty($row['parts_number'])): ?>
                  <div class="muted"><?= e((string)$row['parts_number']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '')) ?></div>
              </td>
              <td><?= number_format((float)($row['qty_per_part'] ?? $row['usage_qty'] ?? 0), 4) ?></td>
              <td><?= e((string)($row['uom'] ?? $row['usage_unit'] ?? $row['unit'] ?? '-')) ?></td>
              <td><?= number_format((float)($row['scrap_rate_pct'] ?? 0), 2) ?>%</td>
              <td><?= (int)($row['sequence_no'] ?? 0) ?></td>
              <td><?= !empty($row['effective_from']) ? e((string)$row['effective_from']) : '<span class="muted">—</span>' ?></td>
              <td><?= !empty($row['mapping_type']) ? e((string)$row['mapping_type']) : '<span class="muted">—</span>' ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($canManage): ?>
<section class="card" id="add-mapping">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Add Part–Material Mapping</h3>
      <div class="muted">Link a material or component to a finished part to build the bill of materials.</div>
    </div>
  </div>
  <form method="post" action="/apps/manufacturing/materials/mapping" class="form-grid u-mt-10">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label class="form-field">
      <span class="field-label">Part <span class="required">*</span></span>
      <select name="product_id" required>
        <option value="">— Select part —</option>
        <?php foreach ($productOptions as $opt): ?>
          <option value="<?= (int)($opt['id'] ?? 0) ?>">
            <?= e((string)($opt['parts_name'] ?? '')) ?> <?= !empty($opt['parts_number']) ? '(' . e((string)$opt['parts_number']) . ')' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-field">
      <span class="field-label">Material <span class="required">*</span></span>
      <select name="material_id" required>
        <option value="">— Select material —</option>
        <?php foreach ($materialOptions as $opt): ?>
          <option value="<?= (int)($opt['id'] ?? 0) ?>">
            <?= e((string)($opt['material_name'] ?? '')) ?> (<?= e((string)($opt['material_number'] ?? $opt['material_code'] ?? '')) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-field">
      <span class="field-label">Qty per Part <span class="required">*</span></span>
      <input class="input" type="number" name="qty_per_part" step="0.0001" min="0.0001" required placeholder="1.0000">
    </label>
    <label class="form-field">
      <span class="field-label">UOM</span>
      <input class="input" type="text" name="uom" placeholder="Leave blank to inherit from material">
    </label>
    <label class="form-field">
      <span class="field-label">Scrap Rate (%)</span>
      <input class="input" type="number" name="scrap_rate_pct" step="0.01" min="0" max="100" placeholder="0.00">
    </label>
    <label class="form-field">
      <span class="field-label">Sequence No.</span>
      <input class="input" type="number" name="sequence_no" min="0" step="1" placeholder="0">
    </label>
    <label class="form-field">
      <span class="field-label">Mapping Type</span>
      <select name="mapping_type">
        <option value="">— Optional —</option>
        <option value="primary">Primary</option>
        <option value="alternative">Alternative</option>
        <option value="optional">Optional</option>
      </select>
    </label>
    <label class="form-field">
      <span class="field-label">Effective From</span>
      <input class="input" type="date" name="effective_from">
    </label>
    <div class="form-actions">
      <button class="btn ok" type="submit">Save Mapping</button>
      <a class="btn" href="/apps/manufacturing/materials/mapping">Cancel</a>
    </div>
  </form>
</section>
<?php endif; ?>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
