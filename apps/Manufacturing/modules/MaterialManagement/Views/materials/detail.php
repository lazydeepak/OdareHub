<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$material = is_array($material ?? null) ? $material : [];
$stock = is_array($stock ?? null) ? $stock : null;
$coverage = is_array($coverage ?? null) ? $coverage : null;
$planning = is_array($planning ?? null) ? $planning : null;
$orders = is_array($orders ?? null) ? $orders : [];
$capacity = is_array($capacity ?? null) ? $capacity : [];
$mappings = is_array($mappings ?? null) ? $mappings : [];
$reservations = is_array($reservations ?? null) ? $reservations : [];
$recentLedger = is_array($recentLedger ?? null) ? $recentLedger : [];
$canManage = (bool)($canManage ?? false);

if ($material === []): ?>
  <section class="card">
    <div class="muted"> <?= e($tt('material_management.detail_message')) ?> </div>
    <div class="row u-mt-10">
      <a class="btn" href="/apps/manufacturing/materials/master">Back to Master</a>
    </div>
  </section>
</div>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
<?php return; endif; ?>

<?php
$coverageStatus = (string)($coverage['coverage_status'] ?? $planning['coverage_status'] ?? 'Balanced');
$statusClass = match ($coverageStatus) {
    'Critical' => 'danger',
    'Low' => 'warning',
    'Overflow Risk' => 'danger',
    'High' => 'warning',
    default => 'info',
};
$delayed = !empty($planning['has_delayed_inbound']) || !empty($coverage['has_delayed_inbound']);
$ledgerTypeLabel = static function (string $type): string {
    return match ($type) {
        'receipt' => 'Receipt',
        'issue_to_production' => 'Issue to Production',
        'adjustment_plus' => 'Adjustment In',
        'adjustment_minus' => 'Adjustment Out',
        'reservation' => 'Reservation',
        'reservation_release' => 'Reservation Release',
        'opening' => 'Opening Balance',
        default => ucwords(str_replace('_', ' ', $type)),
    };
};
$ledgerDeltaClass = static function (float $delta): string {
    return $delta >= 0 ? 'info' : 'warning';
};
?>

<!-- Material Identity Card -->
<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e((string)($material['material_name'] ?? '-')) ?></h3>
      <div class="muted">
        <?= e((string)($material['material_number'] ?? $material['material_code'] ?? '')) ?>
        <?php if (!empty($material['material_type'])): ?> &middot; <?= e((string)$material['material_type']) ?><?php endif; ?>
        <?php if (!empty($material['uom'] ?? $material['unit'])): ?> &middot; <?= e((string)($material['uom'] ?? $material['unit'])) ?><?php endif; ?>
      </div>
      <?php if ($delayed): ?><div class="u-mt-4"><span class="status-chip warning">Delayed Inbound</span></div><?php endif; ?>
    </div>
    <div class="row">
      <span class="status-chip <?= e($statusClass) ?>"><?= e($coverageStatus) ?></span>
      <a class="btn" href="/apps/manufacturing/materials/master">← Master</a>
      <a class="btn" href="/apps/manufacturing/materials/stock?material_id=<?= (int)($material['id'] ?? 0) ?>">Stock</a>
      <?php if ($canManage): ?>
        <a class="btn" href="/apps/manufacturing/materials/receipt?material_id=<?= (int)($material['id'] ?? 0) ?>">Receipt</a>
        <a class="btn" href="/apps/manufacturing/materials/orders?material_id=<?= (int)($material['id'] ?? 0) ?>">Orders</a>
      <?php endif; ?>
    </div>
  </div>

  <div class="form-grid u-mt-10">
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Supplier</div>
      <div class="ui-block"><?= e((string)($material['vendor_name'] ?? $material['supplier_name'] ?? '—')) ?></div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Location</div>
      <div class="ui-block"><?= e((string)($material['storage_location'] ?? '—')) ?></div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Lead Time</div>
      <div class="ui-block"><?= (int)($material['lead_time_days'] ?? 0) ?> days</div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Standard Cost</div>
      <div class="ui-block"><?= number_format((float)($material['standard_cost'] ?? 0), 4) ?></div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Safety Stock</div>
      <div class="ui-block"><?= number_format((float)($material['minimum_stock_qty'] ?? $material['safety_stock_qty'] ?? 0), 2) ?></div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Reorder Point</div>
      <div class="ui-block"><?= number_format((float)($material['reorder_point_qty'] ?? 0), 2) ?></div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Max Stock</div>
      <div class="ui-block"><?= number_format((float)($material['maximum_stock_qty'] ?? $material['max_storage_qty'] ?? 0), 2) ?></div>
    </div>
    <div class="ui-block">
      <div class="muted u-style-1966ed0ce7">Scrap Rate</div>
      <div class="ui-block"><?= number_format((float)($material['scrap_rate_pct'] ?? 0), 2) ?>%</div>
    </div>
  </div>
  <?php if (!empty($material['description'])): ?>
    <div class="muted u-mt-10"><?= e((string)$material['description']) ?></div>
  <?php endif; ?>
</section>

<!-- Stock Position -->
<section class="form-grid">
  <article class="card">
    <div class="section-head">
      <div class="ui-block"><h3 class="u-style-1169661891">Stock Position</h3></div>
    </div>
    <?php if ($stock !== null): ?>
      <div class="kpi-grid u-mt-10 u-style-893dde6064">
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($stock['on_hand_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label">On Hand</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($stock['available_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label">Available</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($stock['reserved_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label">Reserved</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($stock['incoming_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label"> <?= e($tt('material_management.confirmed_incoming_action')) ?> </div>
        </div>
      </div>
    <?php else: ?>
      <div class="muted u-mt-10">No stock ledger entries found.</div>
    <?php endif; ?>
  </article>

  <!-- Coverage Summary -->
  <article class="card">
    <div class="section-head">
      <div class="ui-block"><h3 class="u-style-1169661891"> <?= e($tt('material_management.coverage_summary_title')) ?> </h3></div>
    </div>
    <?php if ($planning !== null): ?>
      <div class="kpi-grid u-mt-10 u-style-893dde6064">
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($planning['demand_30_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label">Demand 30d</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($planning['confirmed_incoming_30_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label"> <?= e($tt('material_management.confirmed_inbound_30d_action')) ?> </div>
        </div>
        <div class="kpi-card <?= ($planning['shortage_qty'] ?? 0) > 0 ? 'kpi-danger' : '' ?>">
          <div class="kpi-value"><?= number_format((float)($planning['shortage_qty'] ?? 0), 2) ?></div>
          <div class="kpi-label">Shortage Qty</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-value"><?= number_format((float)($planning['coverage_pct'] ?? 0), 1) ?>%</div>
          <div class="kpi-label">Coverage %</div>
        </div>
      </div>
      <?php if (!empty($planning['recommended_action'])): ?>
        <div class="u-mt-10">
          <span class="muted">Recommended: </span>
          <strong><?= e((string)$planning['recommended_action']) ?></strong>
        </div>
      <?php endif; ?>
      <?php if ($delayed && !empty($planning['delayed_inbound_reason'])): ?>
        <div class="u-mt-6 muted"><?= e((string)$planning['delayed_inbound_reason']) ?></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="muted u-mt-10">No planning data available.</div>
    <?php endif; ?>
  </article>
</section>

<!-- Open Orders -->
<?php if ($orders !== []): ?>
<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"> <?= e($tt('material_management.open_title')) ?> <?= count($orders) ?>)</h3>
      <div class="muted">Inbound supply orders affecting this material's stock position.</div>
    </div>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Order ID</th>
          <th>Planned Qty</th>
          <th>Received Qty</th>
          <th>Remaining Qty</th>
          <th>Expected Date</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order): ?>
          <?php
            $orderStatus = (string)($order['status'] ?? 'planned');
            $hasDelay = !empty($order['has_delayed_inbound']);
          ?>
          <tr <?= $hasDelay ? 'class="row-warning"' : '' ?>>
            <td>#<?= (int)($order['id'] ?? 0) ?></td>
            <td><?= number_format((float)($order['planned_qty'] ?? 0), 2) ?></td>
            <td><?= number_format((float)($order['received_qty'] ?? 0), 2) ?></td>
            <td><?= number_format((float)($order['remaining_qty'] ?? 0), 2) ?></td>
            <td><?= !empty($order['expected_delivery_date']) ? e((string)$order['expected_delivery_date']) : '<span class="muted">—</span>' ?></td>
            <td>
              <span class="status-chip <?= in_array($orderStatus, ['delayed', 'cancelled'], true) ? 'warning' : 'info' ?>">
                <?= e((string)($order['status_label'] ?? ucfirst($orderStatus))) ?>
              </span>
              <?php if ($hasDelay): ?><span class="status-chip warning">Delayed</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Bill of Materials Mappings -->
<?php if ($mappings !== []): ?>
<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Used in Parts (<?= count($mappings) ?>)</h3>
      <div class="muted">Parts consuming this material, with usage quantities per part.</div>
    </div>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Part</th>
          <th>Qty per Part</th>
          <th>UOM</th>
          <th>Scrap %</th>
          <th>Effective From</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($mappings as $map): ?>
          <tr>
            <td>
              <strong><?= e((string)($map['parts_name'] ?? '-')) ?></strong>
              <?php if (!empty($map['parts_number'])): ?>
                <div class="muted"><?= e((string)$map['parts_number']) ?></div>
              <?php endif; ?>
            </td>
            <td><?= number_format((float)($map['qty_per_part'] ?? $map['usage_qty'] ?? 0), 4) ?></td>
            <td><?= e((string)($map['uom'] ?? $map['usage_unit'] ?? '-')) ?></td>
            <td><?= number_format((float)($map['scrap_rate_pct'] ?? 0), 2) ?>%</td>
            <td><?= !empty($map['effective_from']) ? e((string)$map['effective_from']) : '<span class="muted">—</span>' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Active Reservations -->
<?php if ($reservations !== []): ?>
<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"> <?= e($tt('material_management.active_reservations_title')) ?> <?= count($reservations) ?>)</h3>
      <div class="muted">Quantities reserved for production and other downstream requirements.</div>
    </div>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Ref Type</th>
          <th>Reference ID</th>
          <th>Reserved Qty</th>
          <th>Required By</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($reservations as $res): ?>
          <tr>
            <td><?= e((string)($res['reference_type'] ?? '-')) ?></td>
            <td><?= !empty($res['reference_id']) ? '#' . (int)$res['reference_id'] : '<span class="muted">—</span>' ?></td>
            <td><?= number_format((float)($res['reserved_qty'] ?? 0), 2) ?></td>
            <td><?= !empty($res['required_by_date']) ? e((string)$res['required_by_date']) : '<span class="muted">—</span>' ?></td>
            <td><span class="muted"><?= e((string)($res['notes'] ?? '')) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- Recent Ledger -->
<?php if ($recentLedger !== []): ?>
<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Recent Ledger (last <?= count($recentLedger) ?>)</h3>
      <div class="muted">Latest stock movement entries for this material.</div>
    </div>
  </div>
  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Movement</th>
          <th>Qty Delta</th>
          <th>Reserved Delta</th>
          <th>Reference</th>
          <th>Date</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentLedger as $entry): ?>
          <?php $delta = (float)($entry['qty_delta'] ?? 0); ?>
          <tr>
            <td><?= e($ledgerTypeLabel((string)($entry['movement_type'] ?? ''))) ?></td>
            <td>
              <span class="status-chip <?= e($ledgerDeltaClass($delta)) ?>">
                <?= $delta >= 0 ? '+' : '' ?><?= number_format($delta, 2) ?>
              </span>
            </td>
            <td><?= number_format((float)($entry['reserved_delta'] ?? 0), 2) ?></td>
            <td>
              <?= !empty($entry['ledger_reference']) ? e((string)$entry['ledger_reference']) : '<span class="muted">—</span>' ?>
              <?php if (!empty($entry['reference_type'])): ?>
                <div class="muted"><?= e((string)$entry['reference_type']) ?> #<?= (int)($entry['reference_id'] ?? 0) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="muted"><?= e((string)($entry['created_at'] ?? '')) ?></span></td>
            <td><span class="muted"><?= e((string)($entry['notes'] ?? '')) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="u-mt-10 row">
    <a class="btn" href="/apps/manufacturing/materials/stock?material_id=<?= (int)($material['id'] ?? 0) ?>">Full Stock View</a>
    <?php if ($canManage): ?>
      <a class="btn" href="/apps/manufacturing/materials/receipt?material_id=<?= (int)($material['id'] ?? 0) ?>">Record Receipt</a>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
