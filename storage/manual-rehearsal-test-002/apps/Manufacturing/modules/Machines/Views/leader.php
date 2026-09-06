<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$date = (string)($dashboard['date'] ?? date('Y-m-d'));
$today = (string)($dashboard['today'] ?? date('Y-m-d'));
$scope = is_array($dashboard['scope'] ?? null) ? $dashboard['scope'] : [];
$filters = is_array($dashboard['filters'] ?? null) ? $dashboard['filters'] : [];
$assignedMachines = is_array($dashboard['assigned_machines'] ?? null) ? $dashboard['assigned_machines'] : [];
$runNow = is_array($dashboard['run_now'] ?? null) ? $dashboard['run_now'] : [];
$nextQueue = is_array($dashboard['next_queue'] ?? null) ? $dashboard['next_queue'] : [];
$delayedJobs = is_array($dashboard['delayed_jobs'] ?? null) ? $dashboard['delayed_jobs'] : [];
$waitingQc = is_array($dashboard['waiting_qc'] ?? null) ? $dashboard['waiting_qc'] : [];
$riskJobs = is_array($dashboard['risk_jobs'] ?? null) ? $dashboard['risk_jobs'] : [];
$kpi = is_array($dashboard['kpi'] ?? null) ? $dashboard['kpi'] : [];

$machineId = (int)($scope['machine_id'] ?? 0);
$section = (string)($scope['section'] ?? '');
$mode = (string)($scope['assignee_mode'] ?? 'operational_scope');
$machines = is_array($filters['machines'] ?? null) ? $filters['machines'] : [];
$sections = is_array($filters['sections'] ?? null) ? $filters['sections'] : [];

$todayProgressPct = (float)($kpi['today_progress_pct'] ?? 0);
$urgentCount = (int)($kpi['delayed_jobs'] ?? 0) + (int)($kpi['waiting_qc_jobs'] ?? 0) + (int)($kpi['risk_jobs'] ?? 0);
$isBusyShift = (int)($kpi['run_now_jobs'] ?? 0) > 0;

$modeLabel = 'Operational Scope';
if ($mode === 'machine_filter') {
    $modeLabel = 'Single Machine Scope';
} elseif ($mode === 'section_filter') {
    $modeLabel = 'Section Scope';
}

$modeLabelI18n = match ($mode) {
  'machine_filter' => t('ops.machine_leader.scope_single_machine'),
  'section_filter' => t('ops.machine_leader.scope_section'),
  default => t('ops.machine_leader.scope_operational'),
};

function ml_risk_badge(string $risk): string {
    $r = strtolower(trim($risk));
    if ($r === 'high') return 'risk-high';
    if ($r === 'medium') return 'risk-medium';
    return 'risk-low';
}
?>

<section class="card">
  <div class="ml-hero">
    <div class="ui-block">
      <h2 class="ml-title"><?= e(t('ops.machine_leader.title')) ?></h2>
      <div class="ml-sub"><?= e(t('ops.machine_leader.subtitle')) ?></div>
      <div class="u-style-de88cb98f9">
        <span class="ml-pill"><?= e(t('ops.machine_leader.scope')) ?>: <?= e($modeLabelI18n) ?></span>
        <span class="ml-pill"><?= e(t('common.date')) ?>: <?= e($date) ?><?= $date === $today ? ' (' . e(t('ops.machine_leader.today')) . ')' : '' ?></span>
        <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode($date) ?>"><?= e(t('ops.machine_leader.full_queue_board')) ?></a>
      </div>
    </div>
    <div class="u-style-3de8f987ba">
      <a class="btn" href="/ops/approval-inbox?module=production_plan"><?= e(t('action.open_approval_inbox')) ?></a>
      <a class="btn" href="/production-entries/add"><?= e(t('ops.machine_leader.start_update_output')) ?></a>
      <a class="btn" href="/qc-entries/add"><?= e(t('ops.machine_leader.send_to_qc')) ?></a>
      <a class="btn" href="/production-plans/add"><?= e(t('ops.machine_leader.add_plan')) ?></a>
    </div>
  </div>

  <div class="ml-priority-board <?= $urgentCount === 0 && $isBusyShift ? 'ml-strong-board' : '' ?>">
    <div class="ml-priority-text">
      <div class="ml-priority-count"><?= e(t('ops.machine_leader.urgent_right_now')) ?>: <?= $urgentCount ?></div>
      <div class="muted">
        <?php if ($urgentCount > 0): ?>
          <?= e(t('ops.machine_leader.urgent_hint')) ?>
        <?php else: ?>
          <?= e(t('ops.machine_leader.no_critical_blockers_hint')) ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="ml-alert-links">
      <a class="btn" href="#delayed-jobs"><?= e(t('ops.machine_leader.late_jobs')) ?></a>
      <a class="btn" href="#waiting-qc"><?= e(t('ops.machine_leader.qc_pending')) ?></a>
      <a class="btn" href="#coverage-risk"><?= e(t('ops.machine_leader.shortage_risk')) ?></a>
    </div>
  </div>

  <div class="ml-kpi-grid">
    <div class="ml-kpi"><div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_assigned_machines')) ?></div><div class="ml-kpi-value ml-mono"><?= (int)($kpi['assigned_machines'] ?? 0) ?></div></div>
    <div class="ml-kpi"><div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_run_now')) ?></div><div class="ml-kpi-value ml-mono"><?= (int)($kpi['run_now_jobs'] ?? 0) ?></div></div>
    <div class="ml-kpi"><div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_next_queue')) ?></div><div class="ml-kpi-value ml-mono"><?= (int)($kpi['next_queue_jobs'] ?? 0) ?></div></div>
    <div class="ml-kpi"><div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_delayed_jobs')) ?></div><div class="ml-kpi-value ml-mono u-style-f53de8f2a5"><?= (int)($kpi['delayed_jobs'] ?? 0) ?></div></div>
    <div class="ml-kpi"><div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_waiting_qc')) ?></div><div class="ml-kpi-value ml-mono u-style-4104211b5c"><?= (int)($kpi['waiting_qc_jobs'] ?? 0) ?></div></div>
    <div class="ml-kpi"><div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_coverage_risk_jobs')) ?></div><div class="ml-kpi-value ml-mono u-style-fc776b69ee"><?= (int)($kpi['risk_jobs'] ?? 0) ?></div></div>
    <div class="ml-kpi u-style-94a5b4629f">
      <div class="ml-kpi-label"><?= e(t('ops.machine_leader.kpi_produced_vs_planned')) ?> (<?= e($date) ?>)</div>
      <div class="ml-kpi-value ml-mono"><?= e(number_format((float)($kpi['today_produced_qty'] ?? 0), 2, '.', ',')) ?> / <?= e(number_format((float)($kpi['today_planned_qty'] ?? 0), 2, '.', ',')) ?></div>
      <div class="ml-progress"><span style="width:<?= e((string)max(0.0, min(100.0, $todayProgressPct))) ?>%"></span></div>
      <div class="ml-meta"><?= e(number_format($todayProgressPct, 2, '.', ',')) ?>% <?= e(t('ops.machine_leader.progress')) ?></div>
    </div>
  </div>
</section>

<?php $ownership_board = is_array($dashboard['ownership_board'] ?? null) ? $dashboard['ownership_board'] : []; $ownership_board_title = 'Ownership Workboard'; require APP_ROOT . '/public/views/partials/ownership_workboard.php'; ?>

<section class="card">
  <form method="get" action="/manufacturing/production-workboard" class="ml-filter-row">
    <div class="ui-block">
      <label class="muted u-style-d765493d8f"><?= e(t('common.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="ui-block">
      <label class="muted u-style-d765493d8f"><?= e(t('module.machines.section')) ?></label>
      <select name="section">
        <option value=""><?= e(t('ops.machine_leader.all_sections')) ?></option>
        <?php foreach ($sections as $sec): ?>
          <option value="<?= e((string)$sec) ?>" <?= $section === (string)$sec ? 'selected' : '' ?>><?= e((string)$sec) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-style-d765493d8f"><?= e(t('common.machine')) ?></label>
      <select name="machine_id">
        <option value="0"><?= e(t('ops.machine_leader.all_scoped_machines')) ?></option>
        <?php foreach ($machines as $m): ?>
          <option value="<?= (int)$m['id'] ?>" <?= $machineId === (int)$m['id'] ? 'selected' : '' ?>>
            <?= e((string)$m['machine_no']) ?> - <?= e((string)$m['machine_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-49cd09213e">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/manufacturing/production-workboard"><?= e(t('common.reset')) ?></a>
    </div>
  </form>

  <div class="ml-machine-list">
    <?php if (empty($assignedMachines)): ?>
      <span class="ml-empty"><?= e(t('ops.machine_leader.no_active_machines')) ?></span>
    <?php else: ?>
      <?php foreach ($assignedMachines as $m): ?>
        <span class="ml-chip">
          <?= e((string)$m['machine_no']) ?>
          <?php if ((string)($m['machine_name'] ?? '') !== ''): ?> - <?= e((string)$m['machine_name']) ?><?php endif; ?>
          <span class="muted">(<?= e(t('ops.machine_leader.today')) ?> <?= (int)($m['today_jobs'] ?? 0) ?>, <?= e(t('common.open')) ?> <?= (int)($m['open_jobs'] ?? 0) ?>)</span>
        </span>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<section class="card" id="run-now">
  <div class="ml-section-title">
    <h2 class="u-style-d462248a40"><?= e(t('ops.machine_leader.run_now')) ?></h2>
    <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode($date) ?>"><?= e(t('ops.machine_leader.open_full_machine_queue')) ?></a>
  </div>
    <?php if (empty($runNow)): ?>
      <div class="ml-empty"><?= e(t('ops.machine_leader.no_active_or_runnable_jobs')) ?></div>
    <?php else: ?>
      <ul class="ml-list ml-run-list">
        <?php foreach ($runNow as $row): ?>
          <li>
            <div class="ml-row">
              <div class="ui-block">
                <div class="ml-job-title"><?= e((string)$row['machine_no']) ?>: <?= e((string)$row['parts_name']) ?></div>
                <div class="ml-meta"><?= e(t('common.machine')) ?> <?= e((string)$row['machine_name']) ?> | <?= e(t('ops.machine_leader.plan')) ?> <?= e((string)$row['plan_date']) ?> | <?= e(t('common.sequence')) ?> #<?= (int)$row['sequence_no'] ?></div>
              </div>
              <span class="ml-risk <?= ml_risk_badge((string)$row['risk_level']) ?>"><?= e(strtoupper((string)$row['risk_level'])) ?> RISK</span>
            </div>
            <div class="ml-meta u-style-fe7b4979fe"><?= e((string)$row['parts_number']) ?> | Done <?= e(number_format((float)$row['good_qty'], 2, '.', ',')) ?> / Plan <?= e(number_format((float)$row['planned_qty'], 2, '.', ',')) ?> | Balance <?= e(number_format((float)$row['remaining_qty'], 2, '.', ',')) ?></div>
            <div class="u-style-f64978436d">
              <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
              <a class="btn" href="/production-entries/add?product_id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.machine_leader.update_output')) ?></a>
              <a class="btn" href="/production-entries?machine_id=<?= (int)$row['machine_id'] ?>&production_date=<?= urlencode((string)$row['plan_date']) ?>"><?= e(t('ops.machine_leader.open_entries')) ?></a>
              <a class="btn" href="/production-plans/edit?id=<?= (int)$row['id'] ?>"><?= e(t('ops.machine_leader.open_plan')) ?></a>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
</section>

<section class="ml-grid-2">
  <div class="card">
    <div class="ml-section-title">
      <h2 class="u-style-d462248a40"><?= e(t('ops.machine_leader.up_next_queue')) ?></h2>
      <span class="ml-pill"><?= e(t('ops.machine_leader.secondary_focus_after_run_now')) ?></span>
    </div>
    <?php if (empty($nextQueue)): ?>
      <div class="ml-empty"><?= e(t('ops.machine_leader.queue_clear_beyond_active_jobs')) ?></div>
    <?php else: ?>
      <div class="ml-table-wrap">
        <table class="ml-table">
          <thead>
            <tr>
              <th>Machine</th>
              <th>Plan</th>
              <th>Product</th>
              <th>Remaining</th>
              <th>Risk</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($nextQueue as $row): ?>
            <tr>
              <td><?= e((string)$row['machine_no']) ?></td>
              <td><?= e((string)$row['plan_date']) ?> / #<?= (int)$row['sequence_no'] ?></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td class="ml-mono"><?= e(number_format((float)$row['remaining_qty'], 2, '.', ',')) ?></td>
              <td><span class="ml-risk <?= ml_risk_badge((string)$row['risk_level']) ?>"><?= e(strtoupper((string)$row['risk_level'])) ?></span></td>
              <td><a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><a class="btn" href="/production-plans/edit?id=<?= (int)$row['id'] ?>"><?= e(t('ops.machine_leader.open_plan')) ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="ml-section-title">
      <h2 class="u-style-d462248a40"><?= e(t('ops.machine_leader.shift_alerts')) ?></h2>
      <span class="ml-pill"><?= e(t('ops.machine_leader.intervention_queue')) ?></span>
    </div>
    <div class="ml-table-wrap">
      <table class="ml-table u-style-e154f60266">
        <thead>
          <tr>
            <th> <?= e($tt('machines.alert_column')) ?> </th>
            <th>Count</th>
            <th>Go</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Delayed Jobs</td>
            <td class="ml-mono u-style-f53de8f2a5"><?= (int)($kpi['delayed_jobs'] ?? 0) ?></td>
            <td><a class="btn" href="#delayed-jobs"><?= e(t('ops.machine_leader.review')) ?></a></td>
          </tr>
          <tr>
            <td> <?= e($tt('machines.leader_link')) ?> </td>
            <td class="ml-mono u-style-4104211b5c"><?= (int)($kpi['waiting_qc_jobs'] ?? 0) ?></td>
            <td><a class="btn" href="#waiting-qc"><?= e(t('ops.machine_leader.review')) ?></a></td>
          </tr>
          <tr>
            <td>Coverage / Material Risk</td>
            <td class="ml-mono u-style-fc776b69ee"><?= (int)($kpi['risk_jobs'] ?? 0) ?></td>
            <td><a class="btn" href="#coverage-risk"><?= e(t('ops.machine_leader.review')) ?></a></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="card" id="delayed-jobs">
  <h2 class="u-style-d462248a40"><?= e(t('ops.machine_leader.blocked_or_delayed_jobs')) ?></h2>
  <?php if (empty($delayedJobs)): ?>
    <div class="ml-empty"><?= e(t('ops.machine_leader.no_delayed_jobs_found')) ?></div>
  <?php else: ?>
    <div class="ml-alert u-style-761d3addb2"><?= e(t('ops.machine_leader.delayed_jobs_hint')) ?></div>
    <div class="ml-table-wrap">
      <table class="ml-table">
        <thead>
          <tr>
            <th>Machine</th>
            <th>Planned Date</th>
            <th>Product</th>
            <th>Produced / Planned</th>
            <th>Remaining</th>
            <th>Coverage Risk</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($delayedJobs as $row): ?>
          <tr>
            <td><?= e((string)$row['machine_no']) ?></td>
            <td><?= e((string)$row['plan_date']) ?></td>
            <td><?= e((string)$row['parts_name']) ?></td>
            <td class="ml-mono"><?= e(number_format((float)$row['good_qty'], 2, '.', ',')) ?> / <?= e(number_format((float)$row['planned_qty'], 2, '.', ',')) ?></td>
            <td class="ml-mono"><?= e(number_format((float)$row['remaining_qty'], 2, '.', ',')) ?></td>
            <td><span class="ml-risk <?= ml_risk_badge((string)$row['risk_level']) ?>"><?= e(strtoupper((string)$row['risk_level'])) ?></span></td>
            <td>
              <a class="btn" href="/production-entries?machine_id=<?= (int)$row['machine_id'] ?>&production_date=<?= urlencode((string)$row['plan_date']) ?>"><?= e(t('ops.machine_leader.entries')) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="ml-grid-2">
  <div class="card" id="waiting-qc">
    <h2 class="u-style-d462248a40"><?= e(t('ops.machine_leader.waiting_for_qc')) ?></h2>
    <?php if (empty($waitingQc)): ?>
      <div class="ml-empty"><?= e(t('ops.machine_leader.no_production_waiting_qc')) ?></div>
    <?php else: ?>
      <div class="ml-table-wrap">
        <table class="ml-table">
          <thead>
            <tr>
              <th>Machine</th>
              <th>Product</th>
              <th>Good Qty</th>
              <th>QC Checked</th>
              <th> <?= e($tt('machines.pending_qc_column')) ?> </th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($waitingQc as $row): ?>
            <tr>
              <td><?= e((string)$row['machine_no']) ?></td>
              <td><?= e((string)$row['parts_name']) ?></td>
              <td class="ml-mono"><?= e(number_format((float)$row['good_qty'], 2, '.', ',')) ?></td>
              <td class="ml-mono"><?= e(number_format((float)$row['qc_checked_qty'], 2, '.', ',')) ?></td>
              <td class="ml-mono u-style-4104211b5c"><?= e(number_format((float)$row['pending_qc_qty'], 2, '.', ',')) ?></td>
              <td><a class="btn" href="/qc-entries?status=Open"><?= e(t('ops.machine_leader.open_qc_screen')) ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" id="coverage-risk">
    <h2 class="u-style-d462248a40"><?= e(t('ops.machine_leader.upstream_coverage_risk')) ?></h2>
    <?php if (empty($riskJobs)): ?>
      <div class="ml-empty"><?= e(t('ops.machine_leader.no_upstream_shortage_pressure')) ?></div>
    <?php else: ?>
      <div class="ml-table-wrap">
        <table class="ml-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>Machine</th>
              <th>Min Coverage</th>
              <th> <?= e($tt('machines.open_shortage_qty_column')) ?> </th>
              <th>Risk</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($riskJobs as $row): ?>
            <tr>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td><?= e((string)$row['machine_no']) ?></td>
              <td class="ml-mono"><?= e(number_format((float)$row['min_coverage_pct'], 2, '.', ',')) ?>%</td>
              <td class="ml-mono"><?= e(number_format((float)$row['demand_shortage_qty'], 2, '.', ',')) ?></td>
              <td><span class="ml-risk <?= ml_risk_badge((string)$row['risk_level']) ?>"><?= e(strtoupper((string)$row['risk_level'])) ?></span></td>
              <td><a class="btn" href="/daily-orders?q=<?= urlencode((string)$row['parts_number']) ?>"><?= e(t('nav.daily_orders')) ?></a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
