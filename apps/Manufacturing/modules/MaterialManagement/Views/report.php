<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.material_management.overview');

$summary = [
    'total_materials' => 0, 'active' => 0,
    'total_min_qty' => 0.0, 'total_safety' => 0.0, 'total_reorder' => 0.0,
    'open_orders' => 0, 'open_order_qty' => 0.0,
];
$byType = [];
$recentMaterials = [];
$openOrders = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total_materials,
                    COALESCE(SUM(is_active), 0) AS active,
                    COALESCE(SUM(minimum_stock_qty), 0) AS total_min_qty,
                    COALESCE(SUM(safety_stock_qty), 0) AS total_safety,
                    COALESCE(SUM(reorder_point_qty), 0) AS total_reorder
             FROM materials"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $orderRow = DB::fetchOne(
            "SELECT COUNT(*) AS open_orders,
                    COALESCE(SUM(planned_qty), 0) AS open_order_qty
             FROM material_orders
             WHERE LOWER(COALESCE(status, '')) NOT IN ('closed', 'completed', 'cancelled', 'received')"
        );
        if (is_array($orderRow)) { $summary = array_merge($summary, $orderRow); }

        $byType = DB::fetchAll(
            "SELECT COALESCE(NULLIF(material_type,''),'(none)') AS material_type, COUNT(*) AS n
             FROM materials GROUP BY material_type ORDER BY n DESC"
        ) ?: [];

        $recentMaterials = DB::fetchAll(
            "SELECT id, material_code, material_name, material_type, unit,
                    minimum_stock_qty, safety_stock_qty, reorder_point_qty, standard_unit_cost, is_active
             FROM materials ORDER BY updated_at DESC, id DESC LIMIT 25"
        ) ?: [];

        $openOrders = DB::fetchAll(
            "SELECT mo.id, mo.order_reference, mo.planned_qty, mo.received_qty, mo.status,
                    mo.expected_delivery_date, mo.supplier_name,
                    m.material_code, m.material_name
             FROM material_orders mo
             LEFT JOIN materials m ON m.id = mo.material_id
             WHERE LOWER(COALESCE(mo.status, '')) NOT IN ('closed', 'completed', 'cancelled', 'received')
             ORDER BY mo.expected_delivery_date ASC, mo.id DESC
             LIMIT 20"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('material_management.material_management_report_title')) ?> </h2>
      <div class="muted"> <?= e($tt('material_management.report_description')) ?> </div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/apps/manufacturing/materials">&larr; Materials Workspace</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('material_management.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/apps/manufacturing/materials/export">Open Export</a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('material_management.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('material_management.material_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Materials</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['total_materials'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('material_management.active_message')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['active'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Min Stock Sum</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_min_qty']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Safety Sum</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_safety']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Reorder Sum</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['total_reorder']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Open Orders</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['open_orders'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Open Order Qty</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['open_order_qty']) ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">By Material Type</h3>
  <?php if (!$byType): ?><p class="muted u-style-1169661891">No materials registered.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
      <?php foreach ($byType as $r): ?>
        <tr><td><?= e((string)$r['material_type']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recently Updated Materials</h3>
  <?php if (!$recentMaterials): ?><p class="muted u-style-1169661891">No materials registered.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Code</th><th>Name</th><th>Type</th><th>Unit</th><th class="u-style-54c2afb7ba">Min</th><th class="u-style-54c2afb7ba">Safety</th><th class="u-style-54c2afb7ba">Reorder</th><th class="u-style-54c2afb7ba">Cost</th><th> <?= e($tt('material_management.active_message')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($recentMaterials as $r): ?>
          <tr>
            <td><a href="/apps/manufacturing/materials/<?= (int)$r['id'] ?>"><?= e((string)$r['material_code']) ?></a></td>
            <td><?= e((string)$r['material_name']) ?></td>
            <td><?= e((string)$r['material_type']) ?></td>
            <td><?= e((string)($r['unit'] ?? '')) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['minimum_stock_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['safety_stock_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['reorder_point_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['standard_unit_cost'], 2) ?></td>
            <td><?= ((int)$r['is_active']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Open Material Orders</h3>
  <?php if (!$openOrders): ?><p class="muted u-style-1169661891">No open material orders.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Reference</th><th>Material</th><th>Supplier</th><th>Expected</th><th class="u-style-54c2afb7ba">Planned</th><th class="u-style-54c2afb7ba">Received</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($openOrders as $r): ?>
          <tr>
            <td><?= e((string)($r['order_reference'] ?? ('#'.(int)$r['id']))) ?></td>
            <td><?= e((string)($r['material_code'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['material_name'] ?? '')) ?></span></td>
            <td><?= e((string)($r['supplier_name'] ?? '')) ?></td>
            <td><?= e((string)($r['expected_delivery_date'] ?? '')) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['planned_qty']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['received_qty']) ?></td>
            <td><?= e((string)$r['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
