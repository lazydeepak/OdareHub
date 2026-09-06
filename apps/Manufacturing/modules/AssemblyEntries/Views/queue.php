<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$stageLabel = static function (string $stage): string {
  return match (strtolower(trim($stage))) {
    'packaging' => (string)t('mfg.qc.stage.preparation'),
    'qc'        => (string)t('mfg.qc.stage.qc'),
    default     => ucfirst($stage),
  };
};

$viewer = \App\Core\Auth::user();
$viewerCtx = [];
if (class_exists('\Plugins\\Base\\Services\\UserDashboardAssignmentService')) {
  $viewerCtx = \Plugins\Base\Services\UserDashboardAssignmentService::resolveUserContext(is_array($viewer) ? $viewer : []);
}
$viewerTokens = array_values(array_filter(array_map(
  static fn($token): string => strtolower(trim((string)$token)),
  array_merge((array)($viewerCtx['access_profiles'] ?? []), (array)($viewerCtx['duty_codes'] ?? []))
), static fn(string $token): bool => $token !== ''));
$isReadOnlyView = in_array('read_only', $viewerTokens, true) || in_array('display', $viewerTokens, true);
$isDisplayView = in_array('display', $viewerTokens, true);

$routePolicy = function_exists('platform_user_access_policy_contract') ? platform_user_access_policy_contract() : null;
$canOpen = static function (string $url) use ($routePolicy, $viewer): bool {
  $path = trim((string)parse_url($url, PHP_URL_PATH));
  if ($path === '' || !is_object($routePolicy)) {
    return true;
  }
  $decision = $routePolicy->routeAccessDecision(is_array($viewer) ? $viewer : null, $path, 'GET');
  return (bool)($decision['allowed'] ?? false);
};

$headerLinks = [
  ['label' => (string)t('mfg.asm.link.assembly_plans'),    'url' => '/apps/manufacturing/assembly-plans'],
  ['label' => (string)t('mfg.asm.link.stage_board'),       'url' => '/apps/manufacturing/stage-board'],
  ['label' => (string)t('mfg.asm.link.demand_engine'),     'url' => '/apps/manufacturing/demands'],
  ['label' => (string)t('mfg.asm.link.dispatch_workbench'),'url' => '/apps/manufacturing/dispatch-ops'],
];
$headerLinks = array_values(array_filter($headerLinks, static fn(array $link): bool => $canOpen((string)($link['url'] ?? ''))));

$rows = is_array($rows ?? null) ? $rows : [];
$currentRow = $rows[0] ?? null;
$nextRow = $rows[1] ?? null;
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.asm.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.asm.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <?php foreach ($headerLinks as $link): ?>
        <a class="btn" href="<?= e((string)($link['url'] ?? '#')) ?>"><?= e((string)($link['label'] ?? '')) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.asm.focus.title')) ?></h2></div>
  <div class="coverage-kpi-grid">
    <div class="coverage-kpi">
      <div class="muted"><?= e((string)t('mfg.asm.focus.current_job')) ?></div>
      <div class="coverage-kpi-value"><?= e((string)($currentRow['parts_name'] ?? t('mfg.asm.focus.no_active_job'))) ?></div>
      <?php if (!empty($currentRow['parts_number'])): ?><div class="muted"><?= e((string)$currentRow['parts_number']) ?></div><?php endif; ?>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e((string)t('mfg.asm.focus.next_job')) ?></div>
      <div class="coverage-kpi-value"><?= e((string)($nextRow['parts_name'] ?? t('mfg.asm.focus.queue_clear'))) ?></div>
      <?php if (!empty($nextRow['parts_number'])): ?><div class="muted"><?= e((string)$nextRow['parts_number']) ?></div><?php endif; ?>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e((string)t('mfg.asm.focus.required_action')) ?></div>
      <div class="coverage-kpi-value"><?= $isReadOnlyView ? e((string)t('mfg.asm.focus.readonly_label')) : e((string)t('mfg.asm.focus.action_label')) ?></div>
      <div class="muted"><?= $isReadOnlyView ? e((string)t('mfg.asm.focus.readonly_hint')) : e((string)t('mfg.asm.focus.action_hint')) ?></div>
    </div>
    <div class="coverage-kpi">
      <div class="muted"><?= e((string)t('mfg.asm.focus.queue_status')) ?></div>
      <div class="coverage-kpi-value"><?= count($rows) ?></div>
      <div class="muted"><?= e((string)t('mfg.asm.focus.open_rows')) ?></div>
    </div>
  </div>
</div>

<div class="card">
  <form method="get" action="/apps/manufacturing/assembly-queue" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.asm.filter.from')) ?></label>
      <input class="input" type="date" name="from_date" value="<?= e((string)($from_date ?? '')) ?>">
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.asm.filter.to')) ?></label>
      <input class="input" type="date" name="to_date" value="<?= e((string)($to_date ?? '')) ?>">
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('mfg.asm.filter.apply')) ?></button>
      <a class="btn" href="/apps/manufacturing/assembly-queue"><?= e((string)t('mfg.asm.filter.reset')) ?></a>
    </div>
  </form>
</div>

<?php if (!$isDisplayView): ?>
<?php
$ownership_board = is_array($ownership_board ?? null) ? $ownership_board : [];
$ownership_board_title = (string)t('mfg.portal.ownership.workboard_title');
$ownership_board_empty_hint = (string)t('mfg.portal.qc_priorities.empty');
require APP_ROOT . '/public/views/partials/ownership_workboard.php';
?>
<?php endif; ?>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('mfg.asm.col.date')) ?></th>
          <th><?= e((string)t('mfg.asm.col.part')) ?></th>
          <th><?= e((string)t('mfg.asm.col.prod_released')) ?></th>
          <th><?= e((string)t('mfg.asm.col.system_asm')) ?></th>
          <th><?= e((string)t('mfg.asm.col.effective_asm')) ?></th>
          <th><?= e((string)t('mfg.asm.col.open_workload')) ?></th>
          <th><?= e((string)t('mfg.asm.col.completed')) ?></th>
          <th><?= e((string)t('mfg.asm.col.completion_source')) ?></th>
          <th><?= e((string)t('mfg.asm.col.status')) ?></th>
          <th><?= e((string)t('mfg.asm.col.release_downstream')) ?></th>
          <th><?= e((string)t('mfg.asm.col.next_stage_state')) ?></th>
          <th><?= e((string)t('mfg.asm.col.flags')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach (($rows ?? []) as $r):
        $productionReleased = !empty($r['production_released']);
        $asmReleased  = !empty($r['asm_explicitly_released']);
        $nextStage    = (string)($r['next_stage'] ?? 'qc');
        $demandDate   = (string)$r['demand_date'];
        $productId    = (int)$r['product_id'];
        $openWl       = (float)$r['open_assembly_workload'];
        $effectiveQty = (float)$r['effective_qty'];
        $completedQty = (float)($r['effective_completed_qty'] ?? 0);
        $completionRatio = $effectiveQty > 0 ? $completedQty / $effectiveQty : 0;
        $wlClass         = $openWl > 0 ? 'qc-wl-open' : 'qc-wl-done';
        $completedClass  = $completionRatio >= 0.95 ? 'qc-pass-good' : '';
        $statusClass     = match((string)$r['status']) {
          'approved' => 'approved',
          'adjusted' => 'adjusted',
          default    => 'default',
        };
      ?>
        <tr>
          <td><?= e($demandDate) ?></td>
          <td>
            <?= e((string)$r['parts_name']) ?>
            <span class="muted">(<?= e((string)$r['parts_number']) ?>)</span>
            <div class="qc-plan-link"><a class="btn" href="/apps/manufacturing/assembly-plans/detail?id=<?= (int)$r['id'] ?>"><?= e((string)t('mfg.asm.link.open_plan')) ?></a></div>
          </td>

          <td class="qc-col-center">
            <?php if ($productionReleased): ?>
              <span class="qc-badge-released"><?= e((string)t('mfg.asm.badge.released')) ?></span>
              <?php if (!empty($r['production_released_by'])): ?>
                <div class="qc-text-xs muted asm-released-by" title="<?= e((string)$r['production_released_by']) ?>">
                  <?= e((string)t('mfg.asm.badge.by')) ?> <?= e(substr((string)$r['production_released_by'], 0, 14)) ?>
                </div>
              <?php endif; ?>
            <?php else: ?>
              <span class="qc-badge-auto"><?= e((string)t('mfg.asm.badge.auto')) ?></span>
            <?php endif; ?>
          </td>

          <td><?= e((string)$r['system_qty']) ?></td>
          <td><?= e((string)$r['effective_qty']) ?></td>
          <td class="<?= $wlClass ?>"><?= e((string)$r['open_assembly_workload']) ?></td>
          <td class="<?= $completedClass ?>"><?= e((string)$completedQty) ?></td>
          <td>
            <span class="qc-text-xs muted"><?= e((string)($r['completion_source'] ?? 'dispatch_proxy')) ?></span>
          </td>
          <td>
            <span class="qc-status-badge <?= $statusClass ?>">
              <?= e(ucfirst((string)$r['status'])) ?>
            </span>
          </td>

          <td>
            <?php
            $canRelease = !$asmReleased && !empty($r['downstream_can_release']);
            if ($canRelease && !$isReadOnlyView):
            ?>
              <form method="post" action="/apps/manufacturing/stage-release" class="qc-release-form">
                <input type="hidden" name="csrf"       value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="product_id" value="<?= $productId ?>">
                <input type="hidden" name="ref_date"   value="<?= e($demandDate) ?>">
                <input type="hidden" name="stage"      value="<?= e($nextStage) ?>">
                <input type="hidden" name="redirect"
                       value="/apps/manufacturing/assembly-queue?from_date=<?= urlencode((string)($from_date ?? '')) ?>&to_date=<?= urlencode((string)($to_date ?? '')) ?>">
                <input class="input" type="number" name="qty" min="0" step="0.01"
                       value="<?= round((float)($r['downstream_releasable_qty'] ?? $completedQty), 2) ?>">
                <button class="btn ok" type="submit">
                  → <?= e($stageLabel($nextStage)) ?>
                </button>
              </form>
            <?php elseif ($asmReleased): ?>
              <span class="qc-text-xs qc-text-info"><?= e((string)t('mfg.asm.release.done')) ?></span>
            <?php elseif ($isReadOnlyView): ?>
              <span class="qc-text-xs muted"><?= e((string)t('mfg.asm.release.readonly')) ?></span>
            <?php else: ?>
              <span class="qc-text-xs muted">
                <?= !empty($r['downstream_block_reason']) ? e((string)$r['downstream_block_reason']) : e((string)t('mfg.asm.release.awaiting')) ?>
              </span>
            <?php endif; ?>
          </td>

          <td class="qc-text-xs">
            <?php if (!empty($r['downstream_stage_status'])): ?>
              <div class="ui-block"><?= e($stageLabel($nextStage)) ?>: <?= e((string)$r['downstream_stage_status']) ?></div>
            <?php endif; ?>
            <?php if (isset($r['downstream_releasable_qty'])): ?>
              <div class="muted"><?= e((string)t('mfg.asm.badge.releasable_qty')) ?> <?= e(number_format((float)$r['downstream_releasable_qty'], 2, '.', '')) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['workflow_note'])): ?>
              <?php $wfClass = str_contains((string)$r['workflow_note'], 'Blocked') ? 'qc-text-danger' : 'qc-text-soft-info'; ?>
              <div class="<?= $wfClass ?>"><?= e((string)$r['workflow_note']) ?></div>
            <?php endif; ?>
          </td>

          <td>
            <?php if ((string)($r['supply_mode'] ?? '') === 'third_party'): ?>
              <div class="qc-text-xs muted"><?= e((string)t('mfg.asm.flag.third_party')) ?></div>
            <?php endif; ?>
            <?php if (!empty($r['block_reason'])): ?>
              <div class="qc-text-xs qc-text-danger"><?= e((string)t('mfg.asm.flag.blocked')) ?> <?= e((string)$r['block_reason']) ?></div>
            <?php endif; ?>
            <?php if ((int)($r['dispatch_as_is'] ?? 0) === 1): ?>
              <div class="qc-text-xs muted"><?= e((string)t('mfg.asm.flag.dispatch_as_is')) ?></div>
            <?php endif; ?>
            <?php if ((int)($r['is_passive'] ?? 0) === 1): ?>
              <div class="qc-text-xs qc-text-warn-soft"><?= e((string)t('mfg.asm.flag.passive_part')) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($rows ?? [])): ?>
        <tr><td colspan="12" class="muted"><?= e((string)t('mfg.asm.empty')) ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
