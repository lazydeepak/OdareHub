<?php declare(strict_types=1); ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php require __DIR__ . '/partials/surface_header.php'; ?>
<?php
$planningRows = is_array($planningRows ?? null) ? $planningRows : [];
$canManage = (bool)($canManage ?? false);

$statusClass = static function (string $status): string {
    return match ($status) {
        'Critical' => 'danger',
        'Low' => 'warning',
        'Overflow Risk' => 'danger',
        'High' => 'warning',
        default => 'info',
    };
};
?>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Material Planning</h3>
      <div class="muted"> <?= e($tt('material_management.planning_description')) ?> </div>
    </div>
  </div>

  <div class="table-wrap u-mt-10">
    <table>
      <thead>
        <tr>
          <th rowspan="2">Material</th>
          <th rowspan="2"> <?= e($tt('material_management.opening_available_column')) ?> </th>
          <th rowspan="2">Safety Stock</th>
          <th class="u-style-5a6111dcca" colspan="4">Demand (Cumulative)</th>
          <th class="u-style-5a6111dcca" colspan="4">Projected Available</th>
          <th rowspan="2">Net Need 30d</th>
          <th rowspan="2">Status</th>
        </tr>
        <tr>
          <th>Today</th>
          <th>7d</th>
          <th>14d</th>
          <th>30d</th>
          <th>Today</th>
          <th>7d</th>
          <th>14d</th>
          <th>30d</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($planningRows === []): ?>
          <tr><td colspan="14"><div class="muted">No planning data available. Ensure materials, demand lines, and production plans are configured.</div></td></tr>
        <?php else: ?>
          <?php foreach ($planningRows as $row): ?>
            <?php
              $buckets = is_array($row['bucket_summaries'] ?? null) ? $row['bucket_summaries'] : [];
              $status = (string)($row['coverage_status'] ?? 'Balanced');
              $delayed = !empty($row['has_delayed_inbound']);
            ?>
            <tr <?= $delayed ? 'class="row-warning"' : '' ?>>
              <td>
                <a href="/apps/manufacturing/materials/detail?material_id=<?= (int)($row['material_id'] ?? 0) ?>">
                  <strong><?= e((string)($row['material_name'] ?? '-')) ?></strong>
                </a>
                <div class="muted"><?= e((string)($row['material_number'] ?? $row['material_code'] ?? '')) ?></div>
                <?php if (!empty($row['supplier_name'])): ?>
                  <div class="muted"><?= e((string)$row['supplier_name']) ?> &middot; LT <?= (int)($row['lead_time_days'] ?? 0) ?>d</div>
                <?php endif; ?>
                <?php if ($delayed): ?><span class="status-chip warning">Delayed Inbound</span><?php endif; ?>
              </td>
              <td><?= number_format((float)($row['effective_available_qty'] ?? $row['on_hand_qty'] ?? 0), 2) ?></td>
              <td><?= number_format((float)($row['safety_stock_qty'] ?? 0), 2) ?></td>
              <!-- Demand columns -->
              <?php foreach (['today', 'next_7', 'next_14', 'next_30'] as $bKey): ?>
                <?php $b = is_array($buckets[$bKey] ?? null) ? $buckets[$bKey] : []; ?>
                <td><?= number_format((float)($b['demand_qty'] ?? 0), 2) ?></td>
              <?php endforeach; ?>
              <!-- Projected available columns -->
              <?php foreach (['today', 'next_7', 'next_14', 'next_30'] as $bKey): ?>
                <?php
                  $b = is_array($buckets[$bKey] ?? null) ? $buckets[$bKey] : [];
                  $projAvail = (float)($b['projected_available_qty'] ?? 0);
                  $bucketStatus = (string)($b['status'] ?? 'Balanced');
                ?>
                <td class="<?= $projAvail < 0 ? 'text-danger' : '' ?>">
                  <?= number_format($projAvail, 2) ?>
                  <?php if ($bucketStatus !== 'Balanced'): ?>
                    <div class="ui-block"><span class="status-chip <?= e($statusClass($bucketStatus)) ?>" style="font-size:10px"><?= e($bucketStatus) ?></span></div>
                  <?php endif; ?>
                </td>
              <?php endforeach; ?>
              <td><?= number_format((float)($row['net_need_30_qty'] ?? 0), 2) ?></td>
              <td><span class="status-chip <?= e($statusClass($status)) ?>"><?= e($status) ?></span></td>
            </tr>
            <?php if (!empty($row['trace_shortage_date']) || !empty($row['affected_plans'])): ?>
              <tr class="row-detail-expand">
                <td class="u-style-dc510c84c1" colspan="14">
                  <?php if (!empty($row['trace_shortage_date'])): ?>
                    <span class="muted">First shortage: <strong><?= e((string)$row['trace_shortage_date']) ?></strong></span>
                  <?php endif; ?>
                  <?php if (!empty($row['affected_plans'])): ?>
                    <span class="muted u-ml-10">Affected plans: <?= count((array)$row['affected_plans']) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($row['coverage_driver_summary'])): ?>
                    <span class="muted u-ml-10"><?= e((string)$row['coverage_driver_summary']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
