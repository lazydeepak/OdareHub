<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.products.overview');

$summary = [
    'total' => 0, 'active' => 0, 'requires_assembly' => 0, 'requires_qc' => 0,
    'in_house' => 0, 'third_party' => 0, 'with_cycle_time' => 0,
];
$bySupply = [];
$byFulfill = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_active), 0) AS active,
                    COALESCE(SUM(requires_assembly), 0) AS requires_assembly,
                    COALESCE(SUM(requires_qc), 0) AS requires_qc,
                    COALESCE(SUM(CASE WHEN production_source='in_house' THEN 1 ELSE 0 END), 0) AS in_house,
                    COALESCE(SUM(CASE WHEN production_source='third_party' THEN 1 ELSE 0 END), 0) AS third_party,
                    COALESCE(SUM(CASE WHEN cycle_time IS NOT NULL AND cycle_time > 0 THEN 1 ELSE 0 END), 0) AS with_cycle_time
             FROM products"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $bySupply = DB::fetchAll(
            "SELECT COALESCE(NULLIF(supply_mode,''), '(none)') AS supply_mode, COUNT(*) AS n
             FROM products GROUP BY supply_mode ORDER BY n DESC"
        ) ?: [];

        $byFulfill = DB::fetchAll(
            "SELECT COALESCE(NULLIF(fulfillment_mode,''), '(none)') AS fulfillment_mode, COUNT(*) AS n
             FROM products GROUP BY fulfillment_mode ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT id, parts_number, parts_name, model, producer, is_active,
                    supply_mode, fulfillment_mode, cycle_time, requires_qc, requires_assembly
             FROM products ORDER BY updated_at DESC, id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('products.parts_master_report_title')) ?> </h2>
      <div class="muted">Module-owned product catalog health, supply/fulfillment mix, and recently touched parts.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/products">&larr; Parts Master</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('products.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/products/export">Open Export</a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('products.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('products.catalog_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Total Parts</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('products.active_column')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['active'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">In-House</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['in_house'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Third-Party</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['third_party'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Requires Assembly</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['requires_assembly'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Requires QC</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['requires_qc'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">With Cycle Time</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['with_cycle_time'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Supply &amp; Fulfillment Mix</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">Supply Mode</h4>
      <?php if (!$bySupply): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Mode</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($bySupply as $r): ?>
            <tr><td><?= e((string)$r['supply_mode']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">Fulfillment Mode</h4>
      <?php if (!$byFulfill): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Mode</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byFulfill as $r): ?>
            <tr><td><?= e((string)$r['fulfillment_mode']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recently Updated Parts</h3>
  <?php if (!$recent): ?>
    <p class="muted u-style-1169661891">No products registered.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Part #</th><th>Name</th><th>Model</th><th>Producer</th><th>Supply</th><th>Fulfillment</th><th class="u-style-54c2afb7ba">Cycle</th><th>QC</th><th>Asm</th><th> <?= e($tt('products.active_column')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><a href="/products/<?= (int)$r['id'] ?>"><?= e((string)$r['parts_number']) ?></a></td>
            <td><?= e((string)$r['parts_name']) ?></td>
            <td><?= e((string)($r['model'] ?? '')) ?></td>
            <td><?= e((string)($r['producer'] ?? '')) ?></td>
            <td><?= e((string)$r['supply_mode']) ?></td>
            <td><?= e((string)$r['fulfillment_mode']) ?></td>
            <td class="u-style-54c2afb7ba"><?= $r['cycle_time'] !== null ? number_format((float)$r['cycle_time'], 2) : '-' ?></td>
            <td><?= ((int)$r['requires_qc']) ? 'Yes' : '-' ?></td>
            <td><?= ((int)$r['requires_assembly']) ? 'Yes' : '-' ?></td>
            <td><?= ((int)$r['is_active']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
