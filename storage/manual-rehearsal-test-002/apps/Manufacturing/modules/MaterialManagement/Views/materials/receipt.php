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
$openOrderRows = is_array($openOrderRows ?? null) ? $openOrderRows : [];
$selectedOrder = is_array($selectedOrder ?? null) ? $selectedOrder : null;
$selectedStock = is_array($selectedStock ?? null) ? $selectedStock : null;
$projection = is_array($projection ?? null) ? $projection : null;
$receiptMaterialId = (int)($receiptMaterialId ?? 0);
$receivedQtyDefault = (float)($receivedQtyDefault ?? 0);
$receiptMode = is_string($receiptMode ?? null) ? (string)$receiptMode : 'all';
$receiptModeLabel = is_string($receiptModeLabel ?? null) ? (string)$receiptModeLabel : 'All Materials Received';
$receivedItemClassDefault = is_string($receivedItemClassDefault ?? null) ? (string)$receivedItemClassDefault : 'material';
$receivedUnitDefault = trim((string)($receivedUnitDefault ?? 'kg'));
if ($receivedUnitDefault === '') {
  $receivedUnitDefault = 'kg';
}
$receivedConversionRateDefault = abs((float)($receivedConversionRateDefault ?? 1));
if ($receivedConversionRateDefault <= 0.0001) {
  $receivedConversionRateDefault = 1.0;
}
?>

<section class="form-grid">
  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891"><?= e($receiptModeLabel) ?></h3>
        <div class="muted">This is the canonical stock-in surface for MaterialManagement v1.</div>
      </div>
      <div class="row">
        <a class="btn <?= $receiptMode === 'all' ? 'ok' : '' ?>" href="/apps/manufacturing/materials/receipt?mode=all">All Received</a>
        <a class="btn <?= $receiptMode === 'resin' ? 'ok' : '' ?>" href="/apps/manufacturing/materials/receipt?mode=resin">Resin Received</a>
        <a class="btn <?= $receiptMode === 'functional' ? 'ok' : '' ?>" href="/apps/manufacturing/materials/receipt?mode=functional">Functional Received</a>
      </div>
    </div>
    <form method="post" action="/apps/manufacturing/materials/receipt" class="form-grid u-mt-10">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="receipt_mode" value="<?= e($receiptMode) ?>">
      <label class="form-field">
        <span class="field-label">Material</span>
        <select name="material_id" id="receiptMaterialSelect" required>
          <option value="">Select material</option>
          <?php foreach ($materialOptions as $option): ?>
            <?php $optionId = (int)($option['id'] ?? 0); ?>
            <?php $optionUnit = trim((string)($option['uom'] ?? $option['unit'] ?? 'kg')); ?>
            <?php $optionPackSize = abs((float)($option['pack_size'] ?? 0)); ?>
            <option value="<?= $optionId ?>" data-unit="<?= e($optionUnit !== '' ? $optionUnit : 'kg') ?>" data-pack-size="<?= e(number_format($optionPackSize, 4, '.', '')) ?>" <?= $optionId === $receiptMaterialId ? 'selected' : '' ?>>
              <?= e((string)($option['material_name'] ?? '-')) ?><?php if (!empty($option['material_number'])): ?> (<?= e((string)$option['material_number']) ?>)<?php endif; ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-field">
        <span class="field-label">Received Item Class</span>
        <select name="received_item_class" required>
          <option value="material" <?= $receivedItemClassDefault === 'material' ? 'selected' : '' ?>>Material</option>
          <option value="resin_jairo_material" <?= $receivedItemClassDefault === 'resin_jairo_material' ? 'selected' : '' ?>>Resin / Jairo / Material</option>
          <option value="consumable" <?= $receivedItemClassDefault === 'consumable' ? 'selected' : '' ?>>Consumables delivered</option>
          <option value="third_party_part" <?= $receivedItemClassDefault === 'third_party_part' ? 'selected' : '' ?>>Parts delivered by third-party producers</option>
          <option value="functional_part" <?= $receivedItemClassDefault === 'functional_part' ? 'selected' : '' ?>>Functional parts delivered</option>
          <option value="assembly_component" <?= $receivedItemClassDefault === 'assembly_component' ? 'selected' : '' ?>>Assembly components (clips, tapes, etc.)</option>
          <option value="other" <?= $receivedItemClassDefault === 'other' ? 'selected' : '' ?>>Other received item</option>
        </select>
      </label>
      <label class="form-field">
        <span class="field-label">Delivered Item Detail</span>
        <input class="input" type="text" name="received_item_detail" placeholder="e.g. ABS Resin Grade A, clip set, tape roll, outsourced part lot">
      </label>
      <label class="form-field">
        <span class="field-label">Third-Party Producer (if applicable)</span>
        <input class="input" type="text" name="third_party_producer" placeholder="Supplier / producer name for outsourced parts">
      </label>
      <label class="form-field">
        <span class="field-label" id="receivedQtyLabel">Received Qty</span>
        <input class="input" type="number" name="received_qty" min="0" step="0.01" value="<?= e(number_format($receivedQtyDefault, 2, '.', '')) ?>" required>
      </label>
      <label class="form-field">
        <span class="field-label">Measurement Unit</span>
        <input class="input" type="text" name="received_unit" id="receivedUnitInput" list="receiptUnitSuggestions" value="<?= e($receivedUnitDefault) ?>" required>
        <datalist id="receiptUnitSuggestions">
          <option value="kg"></option>
          <option value="pcs"></option>
          <option value="pack"></option>
          <option value="box"></option>
          <option value="set"></option>
          <option value="roll"></option>
          <option value="meter"></option>
          <option value="liter"></option>
        </datalist>
      </label>
      <label class="form-field">
        <span class="field-label" id="conversionRateLabel">Conversion To Base Unit</span>
        <input class="input" type="number" name="received_conversion_rate" id="receivedConversionRateInput" min="0" step="0.0001" value="<?= e(number_format($receivedConversionRateDefault, 4, '.', '')) ?>" required>
        <div class="muted" id="conversionRateHint">1 received unit equals how many base units in stock.</div>
      </label>
      <label class="form-field">
        <span class="field-label">Received Date</span>
        <input class="input" type="date" name="received_date" value="<?= e(date('Y-m-d')) ?>">
      </label>
      <label class="form-field">
        <span class="field-label">Linked Order</span>
        <select name="order_id">
          <option value="">No linked order</option>
          <?php foreach ($openOrderRows as $row): ?>
            <?php $rowId = (int)($row['id'] ?? 0); ?>
            <option value="<?= $rowId ?>" <?= $selectedOrder !== null && $rowId === (int)($selectedOrder['id'] ?? 0) ? 'selected' : '' ?>>
              <?= e((string)($row['order_reference'] ?? ('Order #' . $rowId))) ?> - <?= e((string)($row['material_name'] ?? '-')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="form-field">
        <span class="field-label">Supplier</span>
        <input class="input" type="text" name="supplier_name" value="<?= e((string)($selectedOrder['supplier_name'] ?? '')) ?>">
      </label>
      <label class="form-field">
        <span class="field-label">Reference Number</span>
        <input class="input" type="text" name="reference_no" value="<?= e((string)($selectedOrder['supplier_ref'] ?? $selectedOrder['order_reference'] ?? '')) ?>">
      </label>
      <label class="form-field">
        <span class="field-label">Purchase Order Ref</span>
        <input class="input" type="text" name="po_reference" value="<?= e((string)($selectedOrder['order_reference'] ?? '')) ?>">
      </label>
      <label class="form-field">
        <span class="field-label">Delivery Note</span>
        <input class="input" type="text" name="delivery_note">
      </label>
      <label class="form-field">
        <span class="field-label">Lot / Batch</span>
        <input class="input" type="text" name="lot_batch">
      </label>
      <label class="form-field">
        <span class="field-label">Storage / Location</span>
        <input class="input" type="text" name="storage_slot" value="<?= e((string)($selectedStock['storage_location'] ?? '')) ?>">
      </label>
      <label class="form-field form-field-full">
        <span class="field-label">Notes</span>
        <textarea name="notes" rows="3"></textarea>
      </label>
      <div class="form-actions">
        <button class="btn" type="submit">Record Receipt</button>
      </div>
    </form>
  </article>

  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891">Open Orders Ready For Receipt</h3>
        <div class="muted">Choose a row to prefill receipt against remaining inbound quantity.</div>
      </div>
    </div>
    <div class="u-mt-10">
      <?php if ($openOrderRows === []): ?>
        <div class="muted"> <?= e($tt('material_management.receipt_description')) ?> </div>
      <?php else: ?>
        <?php foreach (array_slice($openOrderRows, 0, 8) as $row): ?>
          <?php $remainingQty = (float)($row['remaining_qty'] ?? 0); ?>
          <?php $rowUnit = trim((string)($row['uom'] ?? $row['unit'] ?? 'kg')); ?>
          <div class="card">
            <strong><?= e((string)($row['order_reference'] ?? ('Order #' . (int)($row['id'] ?? 0)))) ?></strong>
            <div class="muted"><?= e((string)($row['material_name'] ?? '-')) ?><?php if (!empty($row['supplier_name'])): ?> from <?= e((string)$row['supplier_name']) ?><?php endif; ?></div>
            <div class="muted u-mt-6">Remaining qty <?= number_format($remainingQty, 2) ?> <?= e($rowUnit !== '' ? $rowUnit : 'kg') ?>, expected <?= e((string)($row['expected_delivery_date'] ?? '-')) ?></div>
            <div class="row u-mt-10">
              <a class="btn" href="/apps/manufacturing/materials/receipt?mode=<?= e($receiptMode) ?>&order_id=<?= (int)($row['id'] ?? 0) ?>&material_id=<?= (int)($row['material_id'] ?? 0) ?>&qty=<?= urlencode((string)$remainingQty) ?>&unit=<?= urlencode($rowUnit !== '' ? $rowUnit : 'kg') ?>">Use In Receipt</a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </article>
</section>

<section class="form-grid">
  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891">Current Selection</h3>
        <div class="muted">Immediate context for the material being received.</div>
      </div>
    </div>
    <?php if ($selectedOrder === null && $selectedStock === null): ?>
      <div class="muted u-mt-10">Select a material or come from an order row to load stock and order context here.</div>
    <?php else: ?>
      <div class="u-mt-10">
        <?php if ($selectedOrder !== null): ?>
          <?php $selectedOrderUnit = trim((string)($selectedOrder['uom'] ?? $selectedOrder['unit'] ?? $receivedUnitDefault)); ?>
          <div class="card">
            <strong>Linked Order</strong>
            <div class="muted"><?= e((string)($selectedOrder['order_reference'] ?? '-')) ?> for <?= e((string)($selectedOrder['material_name'] ?? '-')) ?></div>
            <div class="muted">Remaining qty <?= number_format((float)($selectedOrder['remaining_qty'] ?? 0), 2) ?> <?= e($selectedOrderUnit !== '' ? $selectedOrderUnit : 'kg') ?>, supplier <?= e((string)($selectedOrder['supplier_name'] ?? '-')) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($selectedStock !== null): ?>
          <?php $selectedStockUnit = trim((string)($selectedStock['uom'] ?? $selectedStock['unit'] ?? $receivedUnitDefault)); ?>
          <div class="card">
            <strong>Current Stock</strong>
            <div class="muted">On hand <?= number_format((float)($selectedStock['on_hand_qty'] ?? 0), 2) ?> <?= e($selectedStockUnit !== '' ? $selectedStockUnit : 'kg') ?>, reserved <?= number_format((float)($selectedStock['reserved_qty'] ?? 0), 2) ?>, available <?= number_format((float)($selectedStock['available_qty'] ?? 0), 2) ?></div>
            <div class="muted">Incoming <?= number_format((float)($selectedStock['incoming_qty'] ?? 0), 2) ?> <?= e($selectedStockUnit !== '' ? $selectedStockUnit : 'kg') ?><?php if (!empty($selectedStock['storage_location'])): ?>, location <?= e((string)$selectedStock['storage_location']) ?><?php endif; ?></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="card">
    <div class="section-head">
      <div class="ui-block">
        <h3 class="u-style-1169661891">Stock Impact Preview</h3>
        <div class="muted">Shown only from current supported stock totals.</div>
      </div>
    </div>
    <?php if ($projection === null): ?>
      <div class="muted u-mt-10">Projection becomes available when a material with stock context is selected.</div>
    <?php else: ?>
      <div class="u-mt-10">
        <?php $projectionUnit = trim((string)($selectedStock['uom'] ?? $selectedStock['unit'] ?? $receivedUnitDefault)); ?>
        <div class="card">
          <strong>On Hand After Receipt</strong>
          <div class="muted"><?= number_format((float)($projection['on_hand_qty'] ?? 0), 2) ?> -> <?= number_format((float)($projection['projected_on_hand_qty'] ?? 0), 2) ?> <?= e($projectionUnit !== '' ? $projectionUnit : 'kg') ?></div>
        </div>
        <div class="card">
          <strong>Available After Receipt</strong>
          <div class="muted"><?= number_format((float)($projection['available_qty'] ?? 0), 2) ?> -> <?= number_format((float)($projection['projected_available_qty'] ?? 0), 2) ?> <?= e($projectionUnit !== '' ? $projectionUnit : 'kg') ?></div>
        </div>
        <div class="card">
          <strong>Incoming Snapshot</strong>
          <div class="muted">Current incoming <?= number_format((float)($projection['incoming_qty'] ?? 0), 2) ?> <?= e($projectionUnit !== '' ? $projectionUnit : 'kg') ?> remains visible for order follow-through.</div>
        </div>
      </div>
    <?php endif; ?>
  </article>
</section>

<script>
  (function() {
    const materialSelect = document.getElementById('receiptMaterialSelect');
    const unitInput = document.getElementById('receivedUnitInput');
    const qtyLabel = document.getElementById('receivedQtyLabel');
    const conversionInput = document.getElementById('receivedConversionRateInput');
    const conversionLabel = document.getElementById('conversionRateLabel');
    const conversionHint = document.getElementById('conversionRateHint');
    if (!materialSelect || !unitInput || !qtyLabel || !conversionInput || !conversionLabel || !conversionHint) {
      return;
    }

    const normalizeUnit = function(value) {
      return String(value || '').trim().toLowerCase();
    };

    const selectedMaterialUnit = function() {
      const option = materialSelect.options[materialSelect.selectedIndex];
      if (!option) {
        return '';
      }
      return String(option.dataset.unit || '').trim();
    };

    const selectedMaterialPackSize = function() {
      const option = materialSelect.options[materialSelect.selectedIndex];
      if (!option) {
        return 0;
      }
      const parsed = parseFloat(String(option.dataset.packSize || '0'));
      return Number.isFinite(parsed) ? parsed : 0;
    };

    const refreshQtyLabel = function() {
      const current = String(unitInput.value || '').trim();
      qtyLabel.textContent = current !== '' ? ('Received Qty (' + current + ')') : 'Received Qty';
    };

    const refreshConversionUi = function() {
      const baseUnit = selectedMaterialUnit() || 'kg';
      const receiveUnit = String(unitInput.value || '').trim() || baseUnit;
      conversionLabel.textContent = 'Conversion To Base Unit (1 ' + receiveUnit + ' = ? ' + baseUnit + ')';
      conversionHint.textContent = 'If unit differs from base unit, set conversion rate so stock posts in ' + baseUnit + '.';

      if (normalizeUnit(receiveUnit) === normalizeUnit(baseUnit)) {
        conversionInput.value = '1.0000';
      } else if (normalizeUnit(receiveUnit) === 'pack') {
        const packSize = selectedMaterialPackSize();
        if (packSize > 0) {
          conversionInput.value = packSize.toFixed(4);
        }
      }
    };

    let autoUnit = String(unitInput.value || '').trim();

    const syncUnitFromMaterial = function(force) {
      const suggested = selectedMaterialUnit() || 'kg';
      const current = String(unitInput.value || '').trim();
      if (force || current === '' || normalizeUnit(current) === normalizeUnit(autoUnit)) {
        unitInput.value = suggested;
        autoUnit = suggested;
      }
      refreshQtyLabel();
      refreshConversionUi();
    };

    materialSelect.addEventListener('change', function() {
      syncUnitFromMaterial(false);
    });
    unitInput.addEventListener('input', function() {
      refreshQtyLabel();
      refreshConversionUi();
    });

    syncUnitFromMaterial(false);
  })();
</script>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
