<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$coverageRows = is_array($coverageRows ?? null) ? $coverageRows : [];
$canManage = (bool)($canManage ?? false);
$statusFilter = (string)($statusFilter ?? '');
$qFilter = trim((string)($qFilter ?? ''));

$statusClass = static function (string $status): string {
    return match ($status) {
        'Critical' => 'danger',
        'Low' => 'warning',
        'Overflow Risk' => 'danger',
        'High' => 'warning',
        default => 'info',
    };
};
$actionClass = static function (string $action): string {
    return match ($action) {
        'Buy / Plan Now' => 'danger',
        'Delay Inbound' => 'warning',
        default => 'info',
    };
};

// KPI rollup
$total = count($coverageRows);
$criticalCount = 0;
$lowCount = 0;
$overflowCount = 0;
$delayedCount = 0;
$actionNowCount = 0;
foreach ($coverageRows as $row) {
    $status = (string)($row['coverage_status'] ?? 'Balanced');
    if ($status === 'Critical') $criticalCount++;
    if ($status === 'Low') $lowCount++;
    if ($status === 'Overflow Risk') $overflowCount++;
    if (!empty($row['has_delayed_inbound'])) $delayedCount++;
    if ((string)($row['recommended_action'] ?? '') === 'Buy / Plan Now') $actionNowCount++;
}
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Material Coverage</h3>
      <div class="muted"> <?= e($tt('material_management.coverage_description')) ?> </div>
    </div>
  </div>
  <div class="kpi-grid u-mt-10">
    <div class="kpi-card">
      <div class="kpi-value"><?= $total ?></div>
      <div class="kpi-label">Total Materials</div>
    </div>
    <div class="kpi-card kpi-danger">
      <div class="kpi-value"><?= $criticalCount ?></div>
      <div class="kpi-label">Critical</div>
    </div>
    <div class="kpi-card kpi-warning">
      <div class="kpi-value"><?= $lowCount ?></div>
      <div class="kpi-label">Low Coverage</div>
    </div>
    <div class="kpi-card kpi-warning">
      <div class="kpi-value"><?= $overflowCount ?></div>
      <div class="kpi-label">Overflow Risk</div>
    </div>
    <div class="kpi-card kpi-danger">
      <div class="kpi-value"><?= $delayedCount ?></div>
      <div class="kpi-label">Delayed Inbound</div>
    </div>
    <div class="kpi-card kpi-danger">
      <div class="kpi-value"><?= $actionNowCount ?></div>
      <div class="kpi-label">Action Required</div>
    </div>
  </div>
</section>

<section class="card">
  <form method="get" action="/apps/manufacturing/materials/coverage" class="form-grid u-mt-10">
    <label class="form-field">
      <span class="field-label">Search</span>
      <input class="input" type="text" name="q" value="<?= e($qFilter) ?>" placeholder="Material name, code, supplier">
    </label>
    <label class="form-field">
      <span class="field-label"> <?= e($tt('material_management.coverage_status_label')) ?> </span>
      <select name="status">
        <option value="">All</option>
        <?php foreach (['Critical', 'Low', 'Balanced', 'High', 'Overflow Risk'] as $opt): ?>
          <option value="<?= e($opt) ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>><?= e($opt) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="form-actions">
      <button class="btn" type="submit"> <?= e($tt('common.filter_action')) ?> </button>
      <a class="btn" href="/apps/manufacturing/materials/coverage"> <?= e($tt('common.reset_action')) ?> </a>
    </div>
  </form>

  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th>Material</th>
          <th>On Hand</th>
          <th>Safety Stock</th>
          <th>Demand 30d</th>
          <th> <?= e($tt('material_management.confirmed_in_30d_column')) ?> </th>
          <th>Net Need 30d</th>
          <th>Shortage Qty</th>
          <th>Coverage %</th>
          <th>Inbound</th>
          <th>Status</th>
          <th>Action Required</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($coverageRows === []): ?>
          <tr><td colspan="11"><div class="muted">No coverage data available. Ensure materials and demand are configured.</div></td></tr>
        <?php else: ?>
          <?php foreach ($coverageRows as $row): ?>
            <?php
              $status = (string)($row['coverage_status'] ?? 'Balanced');
              $action = (string)($row['recommended_action'] ?? 'Monitor');
              $delayed = !empty($row['has_delayed_inbound']);
            ?>
            <tr <?= $delayed ? 'class="row-warning"' : '' ?>>
              <td>
                <a href="/apps/manufacturing/materials/detail?material_id=<?= (int)($row['material_id'] ?? 0) ?>">
                  <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                </a>
                <div class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '')) ?></div>
                <?php if (!empty($row['supplier_name'])): ?><div class="muted"><?= e((string)$row['supplier_name']) ?></div><?php endif; ?>
              </td>
              <td><?= number_format((float)($row['on_hand_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['safety_stock_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['demand_30_qty'] ?? $row['scrap_adjusted_demand_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['confirmed_incoming_30_qty'] ?? $row['inbound_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['net_need_30_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['shortage_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['coverage_pct'] ?? 0), 1) ?>%</td>
              <td>
                <?php if ($delayed): ?>
                  <span class="status-chip warning">Delayed</span>
                  <?php if (!empty($row['delayed_inbound_reason'])): ?>
                    <div class="muted"><?= e((string)$row['delayed_inbound_reason']) ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="status-chip info">On Schedule</span>
                <?php endif; ?>
              </td>
              <td><span class="status-chip <?= e($statusClass($status)) ?>"><?= e($status) ?></span></td>
              <td><span class="status-chip <?= e($actionClass($action)) ?>"><?= e($action) ?></span></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
