<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$summary = isset($summary) && is_array($summary) ? $summary : [];
$prod = (array)($summary['production'] ?? []);
$qc = (array)($summary['qc'] ?? []);
$assembly = (array)($summary['assembly'] ?? []);
$qcTopRows = isset($qc_top_rows) && is_array($qc_top_rows) ? $qc_top_rows : [];
$assemblyTopRows = isset($assembly_top_rows) && is_array($assembly_top_rows) ? $assembly_top_rows : [];

$totalApprovedDemand = (float)($prod['approved_qty'] ?? 0) + (float)($qc['approved_qty'] ?? 0) + (float)($assembly['approved_qty'] ?? 0);
$dispatchReady = (float)($summary['dispatch_ready_qty'] ?? 0);
$dispatchBlocked = (float)($summary['dispatch_blocked_qty'] ?? 0);
$pendingDemandApproval = (int)($prod['pending_rows'] ?? 0) + (int)($qc['pending_rows'] ?? 0) + (int)($assembly['pending_rows'] ?? 0);
$qcWaiting = (int)($qc['pending_rows'] ?? 0);
$assemblyWaiting = (int)($assembly['pending_rows'] ?? 0);
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.portal.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.portal.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/demands"><?= e((string)t('mfg.portal.action.demand_workspace')) ?></a>
      <a class="btn" href="/apps/manufacturing/production-queue"><?= e((string)t('mfg.portal.action.production_queue')) ?></a>
      <a class="btn" href="/apps/manufacturing/qc-queue"><?= e((string)t('mfg.portal.action.qc_queue')) ?></a>
      <a class="btn" href="/apps/manufacturing/assembly-queue"><?= e((string)t('mfg.portal.action.assembly_queue')) ?></a>
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e((string)t('mfg.portal.action.dispatch_ops')) ?></a>
      <a class="btn" href="/apps/manufacturing/stage-board"><?= e((string)t('mfg.portal.action.stage_board')) ?></a>
    </div>
  </div>
</div>

<div class="stat-row">
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.state.approved_demand')) ?></div>
    <div class="stat-box-value"><?= number_format($totalApprovedDemand, 0) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.state.dispatch_ready_qty')) ?></div>
    <div class="stat-box-value u-tone-info"><?= number_format($dispatchReady, 0) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.state.dispatch_blocked_qty')) ?></div>
    <div class="stat-box-value <?= $dispatchBlocked > 0 ? 'u-tone-danger' : 'u-tone-success' ?>"><?= number_format($dispatchBlocked, 0) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.attention.pending_demand_approval')) ?></div>
    <div class="stat-box-value <?= $pendingDemandApproval > 0 ? 'u-tone-warning' : 'u-tone-success' ?>"><?= $pendingDemandApproval ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.attention.qc_waiting')) ?></div>
    <div class="stat-box-value <?= $qcWaiting > 0 ? 'u-tone-warning' : 'u-tone-success' ?>"><?= $qcWaiting ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.portal.attention.assembly_waiting')) ?></div>
    <div class="stat-box-value <?= $assemblyWaiting > 0 ? 'u-tone-warning' : 'u-tone-success' ?>"><?= $assemblyWaiting ?></div>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.portal.action.qc_queue')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('mfg.qc.col.open_workload')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($qcTopRows === []): ?>
          <tr><td colspan="3" class="muted"><?= e((string)t('mfg.qc.empty')) ?></td></tr>
        <?php else: ?>
          <?php foreach (array_slice($qcTopRows, 0, 5) as $row): ?>
            <tr>
              <td><?= e((string)($row['parts_name'] ?? '')) ?> <span class="muted">(<?= e((string)($row['parts_number'] ?? '')) ?>)</span></td>
              <td><?= e((string)($row['open_qc_workload'] ?? 0)) ?></td>
              <td><a class="btn" href="/apps/manufacturing/qc-queue"><?= e((string)t('common.open')) ?></a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.portal.action.assembly_queue')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('mfg.qc.col.open_workload')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($assemblyTopRows === []): ?>
          <tr><td colspan="3" class="muted"><?= e((string)t('mfg.qc.empty')) ?></td></tr>
        <?php else: ?>
          <?php foreach (array_slice($assemblyTopRows, 0, 5) as $row): ?>
            <tr>
              <td><?= e((string)($row['parts_name'] ?? '')) ?> <span class="muted">(<?= e((string)($row['parts_number'] ?? '')) ?>)</span></td>
              <td><?= e((string)($row['open_assembly_workload'] ?? 0)) ?></td>
              <td><a class="btn" href="/apps/manufacturing/assembly-queue"><?= e((string)t('common.open')) ?></a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>