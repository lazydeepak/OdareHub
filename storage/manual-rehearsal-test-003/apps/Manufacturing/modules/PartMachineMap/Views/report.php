<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.part_machine_map.overview');

$summary = ['total'=>0,'active'=>0,'distinct_products'=>0,'distinct_machines'=>0];
$perProduct = [];
$perMachine = [];
$rows = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_active),0) AS active,
                    COUNT(DISTINCT product_id) AS distinct_products,
                    COUNT(DISTINCT machine_id) AS distinct_machines
             FROM part_machine_map"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $perProduct = DB::fetchAll(
            "SELECT p.parts_number, p.parts_name, COUNT(*) AS n
             FROM part_machine_map pmm
             INNER JOIN products p ON p.id = pmm.product_id
             GROUP BY pmm.product_id ORDER BY n DESC LIMIT 15"
        ) ?: [];

        $perMachine = DB::fetchAll(
            "SELECT m.machine_no, m.machine_name, COUNT(*) AS n
             FROM part_machine_map pmm
             INNER JOIN machines m ON m.id = pmm.machine_id
             GROUP BY pmm.machine_id ORDER BY n DESC LIMIT 15"
        ) ?: [];

        $rows = DB::fetchAll(
            "SELECT pmm.id, pmm.is_active,
                    p.parts_number, p.parts_name,
                    m.machine_no, m.machine_name
             FROM part_machine_map pmm
             INNER JOIN products p ON p.id = pmm.product_id
             INNER JOIN machines m ON m.id = pmm.machine_id
             ORDER BY pmm.is_active DESC, p.parts_number ASC, m.machine_no ASC
             LIMIT 50"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891">Part &harr; Machine Map Report</h2>
      <div class="muted">Module-owned eligibility map between products and machines.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/part-machine-map">&larr; Part Machine Map</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('part_machine_map.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/part-machine-map/export"> <?= e($tt('part_machine_map.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('part_machine_map.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('part_machine_map.map_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Mappings</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['total'] ?></strong></div></div>
    <div class="ui-block"><div class="muted"> <?= e($tt('part_machine_map.active_message')) ?> </div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['active'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Distinct Parts</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['distinct_products'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Distinct Machines</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['distinct_machines'] ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Coverage by Part &amp; Machine</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">Top Parts (mappings)</h4>
      <?php if (!$perProduct): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Part</th><th class="u-style-54c2afb7ba">Machines</th></tr></thead><tbody>
          <?php foreach ($perProduct as $r): ?>
            <tr><td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">Top Machines (mappings)</h4>
      <?php if (!$perMachine): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Machine</th><th class="u-style-54c2afb7ba">Parts</th></tr></thead><tbody>
          <?php foreach ($perMachine as $r): ?>
            <tr><td><?= e((string)$r['machine_no']) ?> &middot; <span class="muted"><?= e((string)$r['machine_name']) ?></span></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Mappings</h3>
  <?php if (!$rows): ?><p class="muted u-style-1169661891">No mappings registered.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Part</th><th>Machine</th><th> <?= e($tt('part_machine_map.active_message')) ?> </th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= e((string)$r['parts_number']) ?> &middot; <span class="muted"><?= e((string)$r['parts_name']) ?></span></td>
            <td><?= e((string)$r['machine_no']) ?> &middot; <span class="muted"><?= e((string)$r['machine_name']) ?></span></td>
            <td><?= ((int)$r['is_active']) ? 'Yes' : 'No' ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
