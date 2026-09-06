<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
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

$rows = isset($rows) && is_array($rows) ? $rows : [];
$currentRow = $rows[0] ?? null;

$statusLabelMap = [
  'calculated' => (string)t('mfg.dem.option.calculated'),
  'adjusted' => (string)t('mfg.dem.option.adjusted'),
  'approved' => (string)t('mfg.dem.option.approved'),
];

$stageLabel = static function (string $stage): string {
  return match (strtolower(trim($stage))) {
    'packaging' => (string)t('mfg.qc.stage.preparation'),
    'qc'        => (string)t('mfg.qc.stage.qc'),
    default     => ucfirst($stage),
  };
};
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.qc.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.qc.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/stage-board"><?= e((string)t('mfg.qc.link.stage_board')) ?></a>
      <a class="btn" href="/apps/manufacturing/assembly-plans"><?= e((string)t('mfg.qc.link.assembly_plans')) ?></a>
      <a class="btn" href="/apps/manufacturing/qc-entries"><?= e((string)t('mfg.qc.link.qc_entries')) ?></a>
      <a class="btn" href="/apps/manufacturing/demands"><?= e((string)t('mfg.qc.link.demand_engine')) ?></a>
    </div>
  </div>
</div>

<div class="stat-row">
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.qc.focus.current_job')) ?></div>
    <div class="stat-box-value"><?= e((string)($currentRow['parts_name'] ?? (string)t('mfg.qc.focus.no_active_job'))) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.qc.focus.queue_status')) ?></div>
    <div class="stat-box-value"><?= count($rows) ?></div>
  </div>
  <div class="stat-box">
    <div class="stat-box-label"><?= e((string)t('mfg.qc.focus.required_action')) ?></div>
    <div class="stat-box-value"><?= $isReadOnlyView ? e((string)t('mfg.qc.focus.readonly_label')) : e((string)t('mfg.qc.focus.action_label')) ?></div>
  </div>
</div>

<div class="card">
  <form method="get" action="/apps/manufacturing/qc-queue" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.qc.filter.from')) ?></label>
      <input class="input" type="date" name="from_date" value="<?= e((string)($from_date ?? '')) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.qc.filter.to')) ?></label>
      <input class="input" type="date" name="to_date" value="<?= e((string)($to_date ?? '')) ?>">
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('mfg.qc.filter.apply')) ?></button>
      <a class="btn" href="/apps/manufacturing/qc-queue"><?= e((string)t('mfg.qc.filter.reset')) ?></a>
    </div>
  </form>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('mfg.qc.col.date')) ?></th>
          <th><?= e((string)t('mfg.qc.col.part')) ?></th>
          <th><?= e((string)t('mfg.qc.col.open_workload')) ?></th>
          <th><?= e((string)t('mfg.qc.col.pass_qty')) ?></th>
          <th><?= e((string)t('mfg.qc.col.fail_qty')) ?></th>
          <th><?= e((string)t('mfg.qc.col.qc_status')) ?></th>
          <th><?= e((string)t('mfg.qc.col.next_stage')) ?></th>
          <th><?= e((string)t('mfg.qc.col.downstream_release')) ?></th>
          <th><?= e((string)t('mfg.qc.col.flags')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <?php
          $statusKey = strtolower(trim((string)($r['status'] ?? '')));
          $statusLabel = (string)($statusLabelMap[$statusKey] ?? (string)($r['status'] ?? ''));
          $nextStage = (string)($r['next_stage'] ?? 'dispatch');
          $qcReleased = !empty($r['qc_explicitly_released']);
          $canRelease = !$qcReleased && !empty($r['downstream_can_release']) && !$isReadOnlyView;
          $demandDate = (string)($r['demand_date'] ?? '');
        ?>
        <tr>
          <td><?= e($demandDate) ?></td>
          <td><?= e((string)($r['parts_name'] ?? '')) ?> <span class="muted">(<?= e((string)($r['parts_number'] ?? '')) ?>)</span></td>
          <td class="<?= (float)($r['open_qc_workload'] ?? 0) > 0 ? 'qc-wl-open' : 'qc-wl-done' ?>"><?= e((string)($r['open_qc_workload'] ?? 0)) ?></td>
          <td><?= e((string)($r['pass_qty'] ?? 0)) ?></td>
          <td class="<?= (float)($r['fail_qty'] ?? 0) > 0 ? 'qc-text-danger' : 'muted' ?>"><?= e((string)($r['fail_qty'] ?? 0)) ?></td>
          <td><?= e($statusLabel) ?></td>
          <td><?= e($stageLabel($nextStage)) ?></td>
          <td>
            <?php if ($canRelease): ?>
              <form method="post" action="/apps/manufacturing/stage-release" class="qc-release-form">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= (int)($r['product_id'] ?? 0) ?>">
                <input type="hidden" name="ref_date" value="<?= e($demandDate) ?>">
                <input type="hidden" name="stage" value="<?= e($nextStage) ?>">
                <input type="hidden" name="redirect" value="/apps/manufacturing/qc-queue?from_date=<?= urlencode((string)($from_date ?? '')) ?>&to_date=<?= urlencode((string)($to_date ?? '')) ?>">
                <input class="input" type="number" name="qty" min="0" step="0.01" value="<?= round((float)($r['downstream_releasable_qty'] ?? $r['pass_qty'] ?? 0), 2) ?>">
                <button class="btn ok" type="submit">→ <?= e($stageLabel($nextStage)) ?></button>
              </form>
            <?php elseif ($qcReleased): ?>
              <span class="qc-text-xs qc-text-info"><?= e((string)t('mfg.qc.release.done')) ?></span>
            <?php elseif ($isReadOnlyView): ?>
              <span class="qc-text-xs muted"><?= e((string)t('mfg.qc.release.readonly')) ?></span>
            <?php else: ?>
              <span class="qc-text-xs muted"><?= e((string)t('mfg.qc.release.awaiting')) ?></span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($r['block_reason'])): ?><div class="qc-text-xs qc-text-danger"><?= e((string)t('mfg.qc.flag.blocked')) ?> <?= e((string)$r['block_reason']) ?></div><?php endif; ?>
            <?php if ((int)($r['dispatch_as_is'] ?? 0) === 1): ?><div class="qc-text-xs muted"><?= e((string)t('mfg.qc.flag.dispatch_as_is')) ?></div><?php endif; ?>
            <?php if ((int)($r['is_passive'] ?? 0) === 1): ?><div class="qc-text-xs qc-text-warn-soft"><?= e((string)t('mfg.qc.flag.passive_part')) ?></div><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($rows === []): ?>
        <tr><td colspan="9" class="muted"><?= e((string)t('mfg.qc.empty')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>