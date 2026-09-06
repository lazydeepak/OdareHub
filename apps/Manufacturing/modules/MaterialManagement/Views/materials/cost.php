<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$costRows = is_array($costRows ?? null) ? $costRows : [];
$canManage = (bool)($canManage ?? false);

$totalStockValue = 0.0;
$totalExposureValue = 0.0;
foreach ($costRows as $row) {
    $totalStockValue += (float)($row['stock_value'] ?? 0);
    $totalExposureValue += (float)($row['exposure_value'] ?? 0);
}
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Material Cost</h3>
      <div class="muted"> <?= e($tt('material_management.cost_description')) ?> </div>
    </div>
  </div>
  <div class="kpi-grid u-mt-10">
    <div class="kpi-card">
      <div class="kpi-value"><?= count($costRows) ?></div>
      <div class="kpi-label">Costed Materials</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-value"><?= number_format($totalStockValue, 2) ?></div>
      <div class="kpi-label">Total Stock Value</div>
    </div>
    <div class="kpi-card kpi-warning">
      <div class="kpi-value"><?= number_format($totalExposureValue, 2) ?></div>
      <div class="kpi-label"> <?= e($tt('material_management.open_action')) ?> </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-value"><?= number_format($totalStockValue + $totalExposureValue, 2) ?></div>
      <div class="kpi-label">Total Committed</div>
    </div>
  </div>
</section>

<section class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Material</th>
          <th>UOM</th>
          <th>Standard Cost</th>
          <th>On Hand Qty</th>
          <th>Stock Value</th>
          <th> <?= e($tt('material_management.open_order_qty_column')) ?> </th>
          <th>Exposure Value</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($costRows === []): ?>
          <tr><td colspan="8"><div class="muted">No cost data available. Ensure materials have a standard cost defined in the Material Master.</div></td></tr>
        <?php else: ?>
          <?php foreach ($costRows as $row): ?>
            <tr>
              <td>
                <a href="/apps/manufacturing/materials/detail?material_id=<?= (int)($row['id'] ?? 0) ?>">
                  <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                </a>
                <div class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '')) ?></div>
              </td>
              <td><?= e((string)($row['uom'] ?? $row['unit'] ?? '-')) ?></td>
              <td><?= number_format((float)($row['standard_cost'] ?? 0), 4) ?></td>
              <td><?= number_format((float)($row['on_hand_qty'] ?? 0), 2) ?></td>
              <td>
                <strong><?= number_format((float)($row['stock_value'] ?? 0), 2) ?></strong>
              </td>
              <td><?= number_format((float)($row['open_order_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['exposure_value'] ?? 0), 2) ?></td>
              <td>
                <a class="btn" href="/apps/manufacturing/materials/detail?material_id=<?= (int)($row['id'] ?? 0) ?>">Detail</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if ($costRows !== []): ?>
          <tr class="u-style-6558bff572">
            <td class="u-style-54c2afb7ba" colspan="4">Totals</td>
            <td><?= number_format($totalStockValue, 2) ?></td>
            <td></td>
            <td><?= number_format($totalExposureValue, 2) ?></td>
            <td></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
