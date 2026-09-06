<?php
$tt = static function (string $key): string {
  return t($key);
};
require_once APP_ROOT . '/apps/Manufacturing/Services/ModuleReportRegistryService.php';

use App\Core\DB;

$report = \Apps\Manufacturing\Services\ModuleReportRegistryService::findActiveReport('manufacturing.ledger.overview');

// The Ledger module exposes material movement history. Use material_ledger as the backing source.
$summary = ['entries'=>0, 'week'=>0, 'qty_in'=>0.0, 'qty_out'=>0.0, 'reserved_delta'=>0.0];
$byType = [];
$byRef = [];
$recent = [];

if (is_array($report)) {
    try {
        $row = DB::fetchOne(
            "SELECT COUNT(*) AS entries,
                    COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END),0) AS week,
                    COALESCE(SUM(CASE WHEN qty_delta > 0 THEN qty_delta ELSE 0 END),0) AS qty_in,
                    COALESCE(SUM(CASE WHEN qty_delta < 0 THEN -qty_delta ELSE 0 END),0) AS qty_out,
                    COALESCE(SUM(reserved_delta),0) AS reserved_delta
             FROM material_ledger
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        );
        if (is_array($row)) { $summary = array_merge($summary, $row); }

        $byType = DB::fetchAll(
            "SELECT COALESCE(NULLIF(movement_type,''),'(none)') AS movement_type, COUNT(*) AS n,
                    COALESCE(SUM(qty_delta),0) AS net_qty
             FROM material_ledger
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY movement_type ORDER BY n DESC"
        ) ?: [];

        $byRef = DB::fetchAll(
            "SELECT COALESCE(NULLIF(reference_type,''),'(none)') AS reference_type, COUNT(*) AS n
             FROM material_ledger
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
             GROUP BY reference_type ORDER BY n DESC"
        ) ?: [];

        $recent = DB::fetchAll(
            "SELECT ml.id, ml.created_at, ml.movement_type, ml.qty_delta, ml.reserved_delta,
                    ml.ledger_reference, ml.reference_type,
                    m.material_code, m.material_name
             FROM material_ledger ml
             LEFT JOIN materials m ON m.id = ml.material_id
             ORDER BY ml.created_at DESC, ml.id DESC LIMIT 25"
        ) ?: [];
    } catch (\Throwable $e) {}
}
?>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>

<div class="card">
  <div class="module-header">
    <div class="module-header-info">
      <h2 class="u-style-1169661891"> <?= e($tt('ledger.ledger_report_title')) ?> </h2>
      <div class="muted">Module-owned material ledger movement (last 30 days) with type and reference breakdown.</div>
    </div>
    <div class="module-header-actions">
      <a class="btn" href="/ledger">&larr; Ledger</a>
    </div>
  </div>
</div>

<div class="card">
  <?php if (is_array($report)): ?>
    <div class="row u-style-f679ea63c2">
      <div class="ui-block">
        <div class="ui-block"><strong> <?= e($tt('ledger.report_key_label')) ?> </strong> <?= e((string)($report['report_key'] ?? '-')) ?></div>
        <div class="muted"><strong>Owner:</strong> <?= e((string)($report['owner'] ?? 'module')) ?> &middot; <strong>Scope:</strong> <?= e((string)($report['scope'] ?? '-')) ?></div>
      </div>
      <div class="ui-block"><a class="btn" href="/ledger/export"> <?= e($tt('ledger.open_link')) ?> </a></div>
    </div>
  <?php else: ?>
    <p class="muted u-style-1169661891"> <?= e($tt('ledger.report_unavailable_message')) ?> </p>
  <?php endif; ?>
</div>

<?php if (is_array($report)): ?>
<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('ledger.30_day_snapshot_title')) ?> </h3>
  <div class="row u-style-034ef58065">
    <div class="ui-block"><div class="muted">Entries</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['entries'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">&le; 7d</div><div class="u-style-b9199e22b2"><strong><?= (int)$summary['week'] ?></strong></div></div>
    <div class="ui-block"><div class="muted">Qty In</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['qty_in']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Qty Out</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['qty_out']) ?></strong></div></div>
    <div class="ui-block"><div class="muted">Reserved &Delta;</div><div class="u-style-b9199e22b2"><strong><?= number_format((float)$summary['reserved_delta']) ?></strong></div></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Movement Mix</h3>
  <div class="row u-style-3fe271b1ae">
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">By Movement Type</h4>
      <?php if (!$byType): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Type</th><th class="u-style-54c2afb7ba">Count</th><th class="u-style-54c2afb7ba">Net Qty</th></tr></thead><tbody>
          <?php foreach ($byType as $r): ?>
            <tr><td><?= e((string)$r['movement_type']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td><td class="u-style-54c2afb7ba"><?= number_format((float)$r['net_qty']) ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <div class="u-style-a58e8b24d9">
      <h4 class="u-style-ad7f18b19e">By Reference</h4>
      <?php if (!$byRef): ?><p class="muted">None.</p>
      <?php else: ?>
        <div class="table-wrap"><table class="table"><thead><tr><th>Reference</th><th class="u-style-54c2afb7ba">Count</th></tr></thead><tbody>
          <?php foreach ($byRef as $r): ?>
            <tr><td><?= e((string)$r['reference_type']) ?></td><td class="u-style-54c2afb7ba"><?= (int)$r['n'] ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Recent Movements</h3>
  <?php if (!$recent): ?><p class="muted u-style-1169661891">No recent ledger movements.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>#</th><th>When</th><th>Material</th><th>Type</th><th>Reference</th><th class="u-style-54c2afb7ba">Qty &Delta;</th><th class="u-style-54c2afb7ba">Reserved &Delta;</th></tr></thead>
      <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td>#<?= (int)$r['id'] ?></td>
            <td><?= e((string)$r['created_at']) ?></td>
            <td><?= e((string)($r['material_code'] ?? '')) ?> &middot; <span class="muted"><?= e((string)($r['material_name'] ?? '')) ?></span></td>
            <td><?= e((string)$r['movement_type']) ?></td>
            <td><?= e((string)($r['reference_type'] ?? '')) ?><?php if (!empty($r['ledger_reference'])): ?> <span class="muted"><?= e((string)$r['ledger_reference']) ?></span><?php endif; ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['qty_delta']) ?></td>
            <td class="u-style-54c2afb7ba"><?= number_format((float)$r['reserved_delta']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
