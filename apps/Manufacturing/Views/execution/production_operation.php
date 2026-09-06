<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$payload = isset($payload) && is_array($payload) ? $payload : [];
$summary = isset($payload['summary']) && is_array($payload['summary']) ? $payload['summary'] : [];
$rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
$machines = isset($payload['machines']) && is_array($payload['machines']) ? $payload['machines'] : [];
$date = (string)($payload['date'] ?? date('Y-m-d'));
$machineId = (int)($payload['machine_id'] ?? 0);
$flash = isset($flash) ? (string)$flash : '';
$error = isset($error) ? (string)$error : '';

$viewer = \App\Core\Auth::user();
$viewerCtx = [];
if (class_exists('\\Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
  $viewerCtx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext(is_array($viewer) ? $viewer : []);
}
$viewerTokens = array_values(array_filter(array_map(
  static fn($token): string => strtolower(trim((string)$token)),
  array_merge((array)($viewerCtx['access_profiles'] ?? []), (array)($viewerCtx['duty_codes'] ?? []))
), static fn(string $token): bool => $token !== ''));
$isReadOnlyView = in_array('read_only', $viewerTokens, true) || in_array('display', $viewerTokens, true);

$targetQty = (float)($summary['target_qty'] ?? 0);
$goodQty = (float)($summary['good_qty'] ?? 0);
$remainingQty = (float)($summary['remaining_qty'] ?? 0);
$blockedRows = (int)($summary['blocked_rows'] ?? 0);
$fmt = static fn(float $v): string => number_format($v, 2, '.', ',');
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.portal.action.production_queue')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.portal.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode($date) ?><?= $machineId > 0 ? '&machine_id=' . $machineId : '' ?>"><?= e((string)t('mfg.portal.action.production_queue')) ?></a>
      <a class="btn" href="/apps/manufacturing/stage-board?date=<?= urlencode($date) ?>"><?= e((string)t('mfg.portal.action.stage_board')) ?></a>
      <?php if (!$isReadOnlyView): ?>
        <a class="btn ok" href="/apps/manufacturing/production-entries/add"><?= e((string)t('mfg.prodq.link.add_entry')) ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($flash !== ''): ?><div class="card dsp-flash-ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/production-operation" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.machine')) ?></label>
      <select name="machine_id">
        <option value="0"><?= e((string)t('mfg.prodq.filter.all_machines')) ?></option>
        <?php foreach ($machines as $machine): ?>
          <option value="<?= (int)($machine['id'] ?? 0) ?>" <?= $machineId === (int)($machine['id'] ?? 0) ? 'selected' : '' ?>>
            <?= e((string)($machine['machine_no'] ?? '')) ?> - <?= e((string)($machine['machine_name'] ?? '')) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('common.filter')) ?></button>
      <a class="btn" href="/apps/manufacturing/production-operation"><?= e((string)t('common.reset')) ?></a>
    </div>
  </form>
</div>

<div class="stat-row">
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.target')) ?></div><div class="stat-box-value"><?= e($fmt($targetQty)) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.done')) ?></div><div class="stat-box-value"><?= e($fmt($goodQty)) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('module.processing_operation.remaining')) ?></div><div class="stat-box-value <?= $remainingQty > 0 ? 'u-tone-warning' : 'u-tone-success' ?>"><?= e($fmt($remainingQty)) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.blocked_rows')) ?></div><div class="stat-box-value <?= $blockedRows > 0 ? 'u-tone-danger' : 'u-tone-success' ?>"><?= $blockedRows ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.prodq.stat.plans')) ?></div><div class="stat-box-value"><?= count($rows) ?></div></div>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.machine')) ?></th>
          <th><?= e((string)t('common.sequence')) ?></th>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('module.processing_operation.target')) ?></th>
          <th><?= e((string)t('module.processing_operation.done')) ?></th>
          <th><?= e((string)t('module.processing_operation.remaining')) ?></th>
          <th><?= e((string)t('module.processing_operation.material')) ?></th>
          <th><?= e((string)t('common.stage')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <?php
          $materialState = is_array($row['material_state'] ?? null) ? $row['material_state'] : [];
          $workflowState = is_array($row['workflow_state'] ?? null) ? $row['workflow_state'] : [];
          $laneUrl = '/apps/manufacturing/production-queue?date=' . urlencode($date) . '&machine_id=' . (int)($row['machine_id'] ?? 0);
        ?>
        <tr>
          <td><?= e((string)($row['machine_no'] ?? '')) ?> - <?= e((string)($row['machine_name'] ?? '')) ?></td>
          <td><?= (int)($row['sequence_no'] ?? 0) ?></td>
          <td>
            <a href="/apps/manufacturing/products/360?id=<?= (int)($row['product_id'] ?? 0) ?>"><?= e((string)($row['parts_name'] ?? '-')) ?></a>
            <div class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></div>
          </td>
          <td><?= e($fmt((float)($row['planned_qty'] ?? 0))) ?></td>
          <td><?= e($fmt((float)($row['good_qty'] ?? 0))) ?></td>
          <td><?= e($fmt((float)($row['remaining_qty'] ?? 0))) ?></td>
          <td>
            <span class="pill"><?= e((string)($materialState['top_status'] ?? t('module.processing_operation.material.balanced'))) ?></span>
            <div class="muted"><?= e((string)($materialState['top_material'] ?? '-')) ?></div>
          </td>
          <td><?= e((string)($workflowState['next_stage'] ?? '-')) ?></td>
          <td>
            <div class="row u-style-ca6f5db40b">
              <?php if (!$isReadOnlyView): ?>
                <a class="btn" href="/apps/manufacturing/production-entries/add?quick_update=1&production_date=<?= urlencode($date) ?>&product_id=<?= (int)($row['product_id'] ?? 0) ?>&machine_id=<?= (int)($row['machine_id'] ?? 0) ?>&redirect=<?= urlencode('/apps/manufacturing/production-operation?date=' . $date . ($machineId > 0 ? '&machine_id=' . $machineId : '')) ?>"><?= e((string)t('mfg.prodq.card.action_entry')) ?></a>
              <?php endif; ?>
              <a class="btn" href="<?= e($laneUrl) ?>"><?= e((string)t('common.open')) ?></a>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="9" class="muted"><?= e((string)t('mfg.prodq.empty_with_filter')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>