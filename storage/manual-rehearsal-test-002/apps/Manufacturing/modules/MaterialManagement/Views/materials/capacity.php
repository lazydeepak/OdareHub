<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$capacityRows = is_array($capacityRows ?? null) ? $capacityRows : [];
$materialOptions = is_array($materialOptions ?? null) ? $materialOptions : [];
$canManage = (bool)($canManage ?? false);

$storageClass = static function (string $status): string {
    return match ($status) {
        'Near Full', 'Overflow Risk' => 'danger',
        'High' => 'warning',
        default => 'info',
    };
};

$occupancyBar = static function (float $pct): string {
    $pct = min(100.0, max(0.0, $pct));
    $color = $pct >= 90 ? '#e74c3c' : ($pct >= 70 ? '#f39c12' : '#27ae60');
    return '<div class="u-style-7d35e72867"><div class="ui-block" style="height:6px;border-radius:3px;background:' . $color . ';width:' . $pct . '%"></div></div>';
};
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Storage Capacity</h3>
      <div class="muted"> <?= e($tt('material_management.capacity_message')) ?> </div>
    </div>
    <?php if ($canManage): ?>
      <div class="row">
        <a class="btn" href="#add-capacity"> <?= e($tt('material_management.capacity_link')) ?> </a>
      </div>
    <?php endif; ?>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Material</th>
          <th>Location</th>
          <th>Current Qty</th>
          <th>Max Capacity</th>
          <th>Occupancy %</th>
          <th>Proj. Qty 30d</th>
          <th>Proj. Occupancy %</th>
          <th> <?= e($tt('common.status_label')) ?> </th>
        </tr>
      </thead>
      <tbody>
        <?php if ($capacityRows === []): ?>
          <tr><td colspan="8"><div class="muted"> <?= e($tt('material_management.capacity_description')) ?> </div></td></tr>
        <?php else: ?>
          <?php foreach ($capacityRows as $row): ?>
            <?php
              $occupancyPct = (float)($row['occupancy_pct'] ?? 0);
              $projOccupancyPct = (float)($row['projected_occupancy_pct'] ?? 0);
              $storageStatus = (string)($row['storage_status'] ?? 'OK');
            ?>
            <tr>
              <td>
                <a href="/apps/manufacturing/materials/detail?material_id=<?= (int)($row['material_id'] ?? 0) ?>">
                  <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                </a>
                <div class="muted"><?= e((string)($row['material_code'] ?? '')) ?></div>
              </td>
              <td><?= e((string)($row['storage_location'] ?? '-')) ?></td>
              <td><?= number_format((float)($row['current_qty'] ?? 0), 2) ?> <?= e((string)($row['unit'] ?? '')) ?></td>
              <td><?= number_format((float)($row['max_capacity_qty'] ?? 0), 2) ?></td>
              <td>
                <?= $occupancyBar($occupancyPct) ?>
                <div class="u-style-942bb41a34"><?= number_format($occupancyPct, 1) ?>%</div>
              </td>
              <td><?= number_format((float)($row['projected_qty_30'] ?? 0), 2) ?></td>
              <td>
                <?= $occupancyBar($projOccupancyPct) ?>
                <div class="u-style-942bb41a34"><?= number_format($projOccupancyPct, 1) ?>%</div>
              </td>
              <td><span class="status-chip <?= e($storageClass($storageStatus)) ?>"><?= e($storageStatus) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if ($canManage): ?>
<section class="card" id="add-capacity">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"> <?= e($tt('material_management.add_title')) ?> </h3>
      <div class="muted">Define a storage allocation for a material at a specific location with a maximum quantity threshold.</div>
    </div>
  </div>
  <form method="post" action="/apps/manufacturing/materials/capacity" class="form-grid u-mt-10">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <label class="form-field">
      <span class="field-label">Material <span class="required">*</span></span>
      <select name="material_id" required>
        <option value="">— Select material —</option>
        <?php foreach ($materialOptions as $opt): ?>
          <option value="<?= (int)($opt['id'] ?? 0) ?>">
            <?= e((string)($opt['material_name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="form-field">
      <span class="field-label">Storage Location</span>
      <input class="input" type="text" name="storage_location" placeholder="e.g. Warehouse A / Rack 2">
    </label>
    <label class="form-field">
      <span class="field-label">Max Capacity Qty <span class="required">*</span></span>
      <input class="input" type="number" name="max_capacity_qty" step="0.01" min="0.01" required placeholder="100.00">
    </label>
    <label class="form-field">
      <span class="field-label">Near-Full Threshold (%)</span>
      <input class="input" type="number" name="near_full_pct" step="1" min="1" max="100" placeholder="85">
    </label>
    <label class="form-field form-field-full">
      <span class="field-label">Notes</span>
      <textarea name="notes" rows="2" placeholder="Optional notes about this storage slot"></textarea>
    </label>
    <div class="form-actions">
      <button class="btn ok" type="submit"> <?= e($tt('material_management.save_action')) ?> </button>
      <a class="btn" href="/apps/manufacturing/materials/capacity"> <?= e($tt('common.cancel_action')) ?> </a>
    </div>
  </form>
</section>
<?php endif; ?>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
