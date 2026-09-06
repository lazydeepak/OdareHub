<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$materialOptions = is_array($materialOptions ?? null) ? $materialOptions : [];
$orderRows = is_array($orderRows ?? null) ? $orderRows : [];
$orderStatuses = is_array($orderStatuses ?? null) ? $orderStatuses : [];
$statusTone = static function (string $status, bool $delayed): string {
    if ($delayed) {
        return 'danger';
    }
    return match (strtolower($status)) {
        'received' => 'success',
        'cancelled' => 'warning',
        'partial', 'delayed' => 'warning',
        default => 'info',
    };
};
?>

<section class="form-grid">
  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891"> <?= e($tt('material_management.create_title')) ?> </h3>
        <div class="muted">Use this to register planned inbound supply before receipt is posted.</div>
      </div>
    </div>
    <form method="post" action="/apps/manufacturing/materials/orders" class="form-grid u-mt-10">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label class="form-field">
        <span class="field-label">Material</span>
        <select name="material_id" required>
          <option value="">Select material</option>
          <?php foreach ($materialOptions as $option): ?>
            <option value="<?= (int)($option['id'] ?? 0) ?>"><?= e((string)($option['material_name'] ?? '-')) ?><?php if (!empty($option['material_number'])): ?> (<?= e((string)$option['material_number']) ?>)<?php endif; ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-field">
        <span class="field-label">Order Reference</span>
        <input class="input" type="text" name="order_reference" required>
      </label>
      <label class="form-field">
        <span class="field-label">Supplier</span>
        <input class="input" type="text" name="supplier_name">
      </label>
      <label class="form-field">
        <span class="field-label">Supplier Reference</span>
        <input class="input" type="text" name="supplier_ref">
      </label>
      <label class="form-field">
        <span class="field-label">Planned Qty</span>
        <input class="input" type="number" name="planned_qty" min="0" step="0.01" required>
      </label>
      <label class="form-field">
        <span class="field-label">Expected Delivery Date</span>
        <input class="input" type="date" name="expected_delivery_date">
      </label>
      <label class="form-field">
        <span class="field-label">Received Qty</span>
        <input class="input" type="number" name="received_qty" min="0" step="0.01" value="0">
      </label>
      <label class="form-field">
        <span class="field-label">Status</span>
        <select name="status">
          <?php foreach ($orderStatuses as $status): ?>
            <option value="<?= e((string)$status) ?>"><?= e(ucfirst((string)$status)) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-field">
        <span class="field-label">Unit Cost</span>
        <input class="input" type="number" name="unit_cost" min="0" step="0.01">
      </label>
      <label class="form-field form-field-full">
        <span class="field-label">Notes</span>
        <textarea name="notes" rows="3"></textarea>
      </label>
      <div class="form-actions">
        <button class="btn" type="submit"> <?= e($tt('material_management.create_title')) ?> </button>
      </div>
    </form>
  </article>

  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891">How Receipt Handoff Works</h3>
        <div class="muted"> <?= e($tt('material_management.orders_description')) ?> </div>
      </div>
    </div>
    <div class="u-mt-10">
      <div class="card">
        <strong>Plan inbound supply</strong>
        <div class="muted">Capture supplier, planned quantity, delivery date, and supporting notes here.</div>
      </div>
      <div class="card">
        <strong>Route remaining quantity</strong>
        <div class="muted">Rows with open quantity expose a receipt action that carries context into the receipt screen.</div>
      </div>
      <div class="card">
        <strong> <?= e($tt('material_management.keep_status_truthful_message')) ?> </strong>
        <div class="muted">Delayed and partial rows stay visible instead of being hidden behind a dashboard-only summary.</div>
      </div>
    </div>
  </article>
</section>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Inbound Order Queue</h3>
      <div class="muted"> <?= e($tt('material_management.orders_active_message')) ?> </div>
    </div>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Material</th>
          <th>Order Ref</th>
          <th>Supplier</th>
          <th>Planned Qty</th>
          <th>Expected Delivery</th>
          <th>Received Qty</th>
          <th>Status</th>
          <th>Notes</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($orderRows === []): ?>
          <tr><td colspan="9"><div class="muted">No inbound orders recorded yet.</div></td></tr>
        <?php else: ?>
          <?php foreach ($orderRows as $row): ?>
            <?php
            $remainingQty = (float)($row['remaining_qty'] ?? 0);
            $delayed = !empty($row['has_delayed_inbound']) || !empty($row['late_incoming_risk']);
            $status = (string)($row['status_label'] ?? $row['status'] ?? 'planned');
            ?>
            <tr>
              <td>
                <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                <div class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '')) ?></div>
              </td>
              <td><?= e((string)($row['order_reference'] ?? '-')) ?></td>
              <td><?= e((string)($row['supplier_name'] ?? '-')) ?></td>
              <td><?= number_format((float)($row['planned_qty'] ?? 0), 2) ?></td>
              <td><?= e((string)($row['expected_delivery_date'] ?? '-')) ?></td>
              <td>
                <?= number_format((float)($row['received_qty'] ?? 0), 2) ?>
                <div class="muted">Remaining <?= number_format($remainingQty, 2) ?></div>
              </td>
              <td>
                <span class="status-chip <?= e($statusTone((string)($row['status'] ?? ''), $delayed)) ?>"><?= e($status) ?></span>
                <?php if ($delayed): ?><div class="muted u-mt-6">Delayed inbound</div><?php endif; ?>
              </td>
              <td><?= e((string)($row['notes'] ?? '')) ?></td>
              <td>
                <div class="row">
                  <?php if ($remainingQty > 0.0001): ?>
                    <a class="btn" href="/apps/manufacturing/materials/receipt?order_id=<?= (int)($row['id'] ?? 0) ?>&material_id=<?= (int)($row['material_id'] ?? 0) ?>&qty=<?= urlencode((string)$remainingQty) ?>">Receive</a>
                  <?php endif; ?>
                  <a class="btn" href="/apps/manufacturing/materials/stock?q=<?= urlencode((string)($row['material_name'] ?? '')) ?>">Stock</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
