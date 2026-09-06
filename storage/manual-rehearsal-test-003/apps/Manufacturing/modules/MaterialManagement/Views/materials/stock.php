<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$query = (string)($query ?? '');
$statusFilter = (string)($statusFilter ?? '');
$materialIdFilter = (int)($materialIdFilter ?? 0);
$statusOptions = is_array($statusOptions ?? null) ? $statusOptions : [];
$stockRows = is_array($stockRows ?? null) ? $stockRows : [];
$canManage = (bool)($canManage ?? false);
$statusClass = static function (string $status): string {
    return match ($status) {
        'Critical', 'Overflow Risk' => 'danger',
        'Low', 'High' => 'warning',
        default => 'info',
    };
};
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Stock Position</h3>
      <div class="muted">Visible stock truth for on hand, reserved, available, and incoming inventory.</div>
    </div>
  </div>
  <form method="get" action="/apps/manufacturing/materials/stock" class="form-grid u-mt-10">
    <?php if ($materialIdFilter > 0): ?>
      <input type="hidden" name="material_id" value="<?= $materialIdFilter ?>">
    <?php endif; ?>
    <label class="form-field">
      <span class="field-label">Search</span>
      <input class="input" type="text" name="q" value="<?= e($query) ?>" placeholder="Material, code, supplier, location">
    </label>
    <label class="form-field">
      <span class="field-label">Status</span>
      <select name="status">
        <option value=""> <?= e($tt('material_management.all_statuses_message')) ?> </option>
        <?php foreach ($statusOptions as $option): ?>
          <option value="<?= e((string)$option) ?>" <?= $statusFilter === (string)$option ? 'selected' : '' ?>><?= e((string)$option) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
      <div class="form-actions">
        <button class="btn" type="submit"> <?= e($tt('material_management.filter_stock_action')) ?> </button>
        <a class="btn" href="/apps/manufacturing/materials/stock">Reset</a>
      </div>
  </form>
</section>

<section class="form-grid">
  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891">Live Stock Table</h3>
        <div class="muted">Cross-links keep stock review tied to actual order and receipt actions.</div>
      </div>
    </div>
    <div class="table-wrap u-mt-10">
      <table>
        <thead>
          <tr>
            <th>Material</th>
            <th>On Hand</th>
            <th>Reserved</th>
            <th>Available</th>
            <th>Incoming</th>
            <th>Unit</th>
            <th>Location</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($stockRows === []): ?>
            <tr><td colspan="9"><div class="muted"> <?= e($tt('material_management.stock_description')) ?> </div></td></tr>
          <?php else: ?>
            <?php foreach ($stockRows as $row): ?>
              <?php $status = (string)($row['coverage_status'] ?? 'Balanced'); ?>
              <tr>
                <td>
                  <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                  <div class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '')) ?></div>
                  <?php if (!empty($row['supplier_name'])): ?><div class="muted"><?= e((string)$row['supplier_name']) ?></div><?php endif; ?>
                </td>
                <td><?= number_format((float)($row['on_hand_qty'] ?? 0), 2) ?></td>
                <td><?= number_format((float)($row['reserved_qty'] ?? 0), 2) ?></td>
                <td><?= number_format((float)($row['available_qty'] ?? 0), 2) ?></td>
                <td><?= number_format((float)($row['incoming_qty'] ?? 0), 2) ?></td>
                <td><?= e((string)($row['unit'] ?? $row['uom'] ?? '-')) ?></td>
                <td><?= e((string)($row['storage_location'] ?? '-')) ?></td>
                <td><span class="status-chip <?= e($statusClass($status)) ?>"><?= e($status) ?></span></td>
                <td>
                  <div class="row">
                    <a class="btn" href="/apps/manufacturing/materials/orders">Orders</a>
                    <?php if ($canManage): ?>
                      <a class="btn" href="/apps/manufacturing/materials/receipt?material_id=<?= (int)($row['id'] ?? 0) ?>">Receipt</a>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </article>

  <?php if ($canManage): ?>
    <article class="card">
      <div class="section-head">
        <div class="ui-block">
          <h3 class="u-style-1169661891">Secondary: Stock Ledger Adjustment</h3>
          <div class="muted">Manual ledger entry remains available, but it is intentionally secondary to orders and receipt.</div>
        </div>
      </div>
      <form method="post" action="/apps/manufacturing/materials/stock" class="form-grid u-mt-10">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label class="form-field">
          <span class="field-label">Material ID</span>
          <input class="input" type="number" name="material_id" min="1" required>
        </label>
        <label class="form-field">
          <span class="field-label">Movement Type</span>
          <select name="movement_type">
            <option value="adjustment_plus">Adjustment In</option>
            <option value="adjustment_minus">Adjustment Out</option>
            <option value="reservation">Reserve</option>
            <option value="reservation_release">Release Reservation</option>
          </select>
        </label>
        <label class="form-field">
          <span class="field-label">Qty Delta</span>
          <input class="input" type="number" name="qty_delta" step="0.01">
        </label>
        <label class="form-field">
          <span class="field-label">Reserved Delta</span>
          <input class="input" type="number" name="reserved_delta" step="0.01">
        </label>
        <label class="form-field">
          <span class="field-label">Ledger Reference</span>
          <input class="input" type="text" name="ledger_reference">
        </label>
        <label class="form-field form-field-full">
          <span class="field-label">Notes</span>
          <textarea name="notes" rows="3"></textarea>
        </label>
        <div class="form-actions">
          <button class="btn" type="submit">Post Ledger Entry</button>
        </div>
      </form>
    </article>
  <?php endif; ?>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
