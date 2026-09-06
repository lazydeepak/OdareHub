<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$date = (string)($dashboard['date'] ?? date('Y-m-d'));
$today = (string)($dashboard['today'] ?? date('Y-m-d'));
$priority = (string)($dashboard['priority'] ?? '');
$filters = is_array($dashboard['filters'] ?? null) ? $dashboard['filters'] : [];
$kpi = is_array($dashboard['kpi'] ?? null) ? $dashboard['kpi'] : [];
$runNow = is_array($dashboard['run_now'] ?? null) ? $dashboard['run_now'] : [];
$pendingQc = is_array($dashboard['pending_qc'] ?? null) ? $dashboard['pending_qc'] : [];
$failedQueue = is_array($dashboard['failed_recheck'] ?? null) ? $dashboard['failed_recheck'] : [];
$readyDispatch = is_array($dashboard['ready_dispatch'] ?? null) ? $dashboard['ready_dispatch'] : [];
$backlog = is_array($dashboard['backlog_by_priority'] ?? null) ? $dashboard['backlog_by_priority'] : [];
$sla = is_array($dashboard['sla'] ?? null) ? $dashboard['sla'] : [];

$priorities = is_array($filters['priorities'] ?? null) ? $filters['priorities'] : [];
$urgentCount = (int)($kpi['urgent'] ?? 0);

function qc_risk_badge(float $coveragePct, float $shortageQty): string {
    if ($shortageQty > 0 || $coveragePct < 35) return 'qc-badge-high';
    if ($coveragePct < 80) return 'qc-badge-medium';
    return 'qc-badge-low';
}

function qc_priority_badge(string $priority): string {
    $p = strtolower(trim($priority));
    if ($p === 'critical') return 'qc-badge-high';
    if ($p === 'high') return 'qc-badge-medium';
    return 'qc-badge-low';
}
?>

<section class="card">
  <div class="qc-hero">
    <div class="ui-block">
      <h2 class="qc-title"><?= e(t('ops.qc_leader.title')) ?></h2>
      <div class="qc-sub"><?= e(t('ops.qc_leader.subtitle')) ?></div>
      <div class="u-style-de88cb98f9">
        <span class="qc-pill"><?= e(t('common.date')) ?>: <?= e($date) ?><?= $date === $today ? ' (' . e(t('ops.qc_leader.today')) . ')' : '' ?></span>
        <a class="btn" href="/qc-entries"><?= e(t('nav.qc_entries')) ?></a>
        <a class="btn" href="/qc-plans"><?= e(t('nav.qc_plans')) ?></a>
      </div>
    </div>
    <div class="u-style-3de8f987ba">
      <a class="btn" href="/ops/approval-inbox?module=qc_entry"><?= e(t('action.open_approval_inbox')) ?></a>
      <a class="btn" href="/qc-entries/add"><?= e(t('ops.qc_leader.start_qc_entry')) ?></a>
      <a class="btn" href="/qc-plans/add"><?= e(t('ops.qc_leader.add_qc_plan')) ?></a>
    </div>
  </div>

  <div class="qc-priority-board">
    <div class="ui-block">
      <div class="qc-priority-count"><?= e(t('ops.qc_leader.urgent_right_now')) ?>: <?= $urgentCount ?></div>
      <div class="muted"><?= e(t('ops.qc_leader.urgent_hint')) ?></div>
    </div>
    <div class="qc-jump">
      <a class="btn" href="#run-now"><?= e(t('ops.qc_leader.run_now')) ?></a>
      <a class="btn" href="#failed-recheck"><?= e(t('ops.qc_leader.failed_recheck')) ?></a>
      <a class="btn" href="#sla-risk"><?= e(t('ops.qc_leader.sla_risk')) ?></a>
    </div>
  </div>

  <div class="qc-kpis">
    <div class="qc-kpi"><div class="qc-kpi-label"><?= e(t('ops.qc_leader.kpi_run_now')) ?></div><div class="qc-kpi-value"><?= (int)($kpi['run_now'] ?? 0) ?></div></div>
    <div class="qc-kpi"><div class="qc-kpi-label"><?= e(t('ops.qc_leader.kpi_pending_qc')) ?></div><div class="qc-kpi-value"><?= (int)($kpi['pending_qc'] ?? 0) ?></div></div>
    <div class="qc-kpi"><div class="qc-kpi-label"><?= e(t('ops.qc_leader.kpi_failed_recheck')) ?></div><div class="qc-kpi-value u-style-f53de8f2a5"><?= (int)($kpi['failed_recheck'] ?? 0) ?></div></div>
    <div class="qc-kpi"><div class="qc-kpi-label"><?= e(t('ops.qc_leader.kpi_ready_for_dispatch')) ?></div><div class="qc-kpi-value u-style-568b1a9ea1"><?= (int)($kpi['ready_dispatch'] ?? 0) ?></div></div>
    <div class="qc-kpi"><div class="qc-kpi-label"><?= e(t('ops.qc_leader.kpi_overdue_checks')) ?></div><div class="qc-kpi-value u-style-4104211b5c"><?= (int)($kpi['overdue'] ?? 0) ?></div></div>
  </div>
</section>

<?php $ownership_board = is_array($dashboard['ownership_board'] ?? null) ? $dashboard['ownership_board'] : []; $ownership_board_title = 'Ownership Workboard'; require APP_ROOT . '/public/views/partials/ownership_workboard.php'; ?>

<section class="card">
  <form method="get" action="/manufacturing/qc-workboard" class="qc-filter">
    <div class="ui-block">
      <label class="muted u-style-d765493d8f"><?= e(t('common.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="ui-block">
      <label class="muted u-style-d765493d8f"><?= e(t('common.priority')) ?></label>
      <select name="priority">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($priorities as $p): ?>
          <option value="<?= e((string)$p) ?>" <?= $priority === (string)$p ? 'selected' : '' ?>><?= e((string)$p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-49cd09213e">
      <button class="btn" type="submit"><?= e(t('common.filter')) ?></button>
      <a class="btn" href="/manufacturing/qc-workboard"><?= e(t('common.reset')) ?></a>
    </div>
  </form>
</section>

<section class="card" id="run-now">
  <div class="qc-section-head">
    <h2 class="u-style-d462248a40"><?= e(t('ops.qc_leader.run_now')) ?></h2>
    <span class="qc-pill"><?= e(t('ops.qc_leader.checks_to_perform_immediately')) ?></span>
  </div>
  <?php if (empty($runNow)): ?>
    <div class="qc-empty"><?= e(t('ops.qc_leader.no_run_now_qc_plans')) ?></div>
  <?php else: ?>
    <ul class="qc-list qc-run">
      <?php foreach ($runNow as $row): ?>
        <?php
          $riskClass = qc_risk_badge((float)($row['coverage_pct'] ?? 100), (float)($row['shortage_qty'] ?? 0));
          $remainingQty = (float)($row['remaining_qty'] ?? 0);
          $openEntryId = (int)($row['open_entry_id'] ?? 0);
        ?>
        <li>
          <div class="qc-headline">
            <div class="ui-block">
              <div class="qc-item-title">#<?= (int)$row['id'] ?> <?= e((string)$row['parts_name']) ?> (<?= e((string)$row['parts_number']) ?>)</div>
              <div class="qc-meta"><?= e(t('ops.machine_leader.plan')) ?> <?= e((string)$row['plan_date']) ?> | <?= e(t('common.required_date')) ?> <?= e((string)($row['required_date'] ?? t('common.na'))) ?> | <?= e(t('common.priority')) ?> <?= e((string)$row['priority']) ?></div>
            </div>
            <span class="qc-badge <?= $riskClass ?>"><?= (int)($row['is_overdue'] ?? 0) === 1 ? e(t('ops.qc_leader.overdue_badge')) : e(t('ops.qc_leader.due_badge')) ?></span>
          </div>
          <div class="qc-meta"><?= e(t('ops.qc_leader.checked_planned_remaining', [
            'checked' => number_format((float)$row['checked_qty'], 2, '.', ','),
            'planned' => number_format((float)$row['planned_qty'], 2, '.', ','),
            'remaining' => number_format($remainingQty, 2, '.', ','),
          ])) ?></div>
          <div class="u-style-f64978436d">
            <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
            <?php if ($openEntryId > 0): ?>
              <a class="btn" href="/qc-entries/edit?id=<?= $openEntryId ?>"><?= e(t('ops.qc_leader.open_qc_entry')) ?></a>
              <a class="btn" href="/qc-entries/edit?id=<?= $openEntryId ?>"><?= e(t('ops.qc_leader.complete_qc')) ?></a>
            <?php else: ?>
              <a class="btn" href="/qc-entries/add?qc_plan_id=<?= (int)$row['id'] ?>&product_id=<?= (int)$row['product_id'] ?>&daily_order_id=<?= (int)$row['daily_order_id'] ?>&status=Open"><?= e(t('ops.qc_leader.open_qc_entry')) ?></a>
              <a class="btn" href="/qc-entries/add?qc_plan_id=<?= (int)$row['id'] ?>&product_id=<?= (int)$row['product_id'] ?>&daily_order_id=<?= (int)$row['daily_order_id'] ?>&status=Closed"><?= e(t('ops.qc_leader.complete_qc')) ?></a>
            <?php endif; ?>
            <a class="btn" href="/daily-orders?q=<?= urlencode((string)$row['parts_number']) ?>"><?= e(t('ops.qc_leader.escalate')) ?></a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="qc-grid-2">
  <div class="card">
    <div class="qc-section-head">
      <h2 class="u-style-d462248a40"><?= e(t('ops.qc_leader.pending_qc')) ?></h2>
      <span class="qc-pill"><?= e(t('ops.qc_leader.output_waiting_checks')) ?></span>
    </div>
    <?php if (empty($pendingQc)): ?>
      <div class="qc-empty"><?= e(t('ops.qc_leader.no_pending_output_waiting_qc')) ?></div>
    <?php else: ?>
      <div class="qc-table-wrap">
        <table class="qc-table">
          <thead>
            <tr>
              <th><?= e(t('common.id')) ?></th>
              <th><?= e(t('ops.qc_leader.product')) ?></th>
              <th><?= e(t('ops.qc_leader.good_qty')) ?></th>
              <th><?= e(t('ops.qc_leader.checked')) ?></th>
              <th><?= e(t('ops.qc_leader.pending')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingQc as $row): ?>
            <tr class="qc-clickable-row" tabindex="0" role="link" aria-label="Open QC entry form" onclick="window.location='/qc-entries/add?production_entry_id=<?= (int)$row['production_entry_id'] ?>&product_id=<?= (int)$row['product_id'] ?>&status=Open'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location='/qc-entries/add?production_entry_id=<?= (int)$row['production_entry_id'] ?>&product_id=<?= (int)$row['product_id'] ?>&status=Open';}">
              <td>#<?= (int)$row['production_entry_id'] ?> <span class="muted"><?= e((string)$row['production_date']) ?></span></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td><?= e(number_format((float)$row['good_qty'], 2, '.', ',')) ?></td>
              <td><?= e(number_format((float)$row['checked_qty'], 2, '.', ',')) ?></td>
              <td class="u-style-4104211b5c"><?= e(number_format((float)$row['pending_qty'], 2, '.', ',')) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" id="failed-recheck">
    <div class="qc-section-head">
      <h2 class="u-style-d462248a40"><?= e(t('ops.qc_leader.failed_recheck_queue')) ?></h2>
      <span class="qc-pill"><?= e(t('ops.qc_leader.high_risk_quality_actions')) ?></span>
    </div>
    <?php if (empty($failedQueue)): ?>
      <div class="qc-empty"><?= e(t('ops.qc_leader.no_failed_recheck_queue')) ?></div>
    <?php else: ?>
      <div class="qc-table-wrap">
        <table class="qc-table">
          <thead>
            <tr>
              <th><?= e(t('ops.qc_leader.qc_entry')) ?></th>
              <th><?= e(t('ops.qc_leader.product')) ?></th>
              <th><?= e(t('ops.qc_leader.fail_qty')) ?></th>
              <th><?= e(t('common.status')) ?></th>
              <th><?= e(t('ops.qc_leader.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($failedQueue as $row): ?>
            <tr>
              <td>#<?= (int)$row['id'] ?></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td class="u-style-f53de8f2a5"><?= e(number_format((float)$row['fail_qty'], 2, '.', ',')) ?></td>
              <td><?= e((string)$row['status']) ?></td>
              <td>
                <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
                <a class="btn" href="/qc-entries/edit?id=<?= (int)$row['id'] ?>"><?= e(t('ops.qc_leader.complete_qc')) ?></a>
                <a class="btn" href="/qc-entries/add?product_id=<?= (int)$row['product_id'] ?>&daily_order_id=<?= (int)$row['daily_order_id'] ?>&production_entry_id=<?= (int)($row['production_entry_id'] ?? 0) ?>&qc_type=Recheck&status=Recheck"><?= e(t('ops.qc_leader.recheck')) ?></a>
                <a class="btn" href="/daily-orders?q=<?= urlencode((string)$row['parts_number']) ?>"><?= e(t('ops.qc_leader.escalate')) ?></a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="qc-grid-2">
  <div class="card">
    <h2 class="u-style-d462248a40"><?= e(t('ops.qc_leader.backlog_by_priority')) ?></h2>
    <?php if (empty($backlog)): ?>
      <div class="qc-empty"><?= e(t('ops.qc_leader.no_open_backlog')) ?></div>
    <?php else: ?>
      <div class="qc-table-wrap">
        <table class="qc-table u-style-85c8a158b4">
          <thead>
            <tr>
              <th><?= e(t('common.priority')) ?></th>
              <th><?= e(t('ops.qc_leader.open_plans')) ?></th>
              <th><?= e(t('ops.qc_leader.pending_qty')) ?></th>
              <th><?= e(t('ops.qc_leader.overdue')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($backlog as $row): ?>
            <tr>
              <td><span class="qc-badge <?= qc_priority_badge((string)$row['priority']) ?>"><?= e((string)$row['priority']) ?></span></td>
              <td><?= (int)$row['open_plans'] ?></td>
              <td><?= e(number_format((float)$row['pending_qty'], 2, '.', ',')) ?></td>
              <td class="u-style-4104211b5c"><?= (int)$row['overdue_plans'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="u-style-d462248a40"><?= e(t('ops.qc_leader.ready_for_dispatch_after_qc')) ?></h2>
    <?php if (empty($readyDispatch)): ?>
      <div class="qc-empty"><?= e(t('ops.qc_leader.no_pass_qty_waiting_dispatch')) ?></div>
    <?php else: ?>
      <div class="qc-table-wrap">
        <table class="qc-table u-style-2e2cd21005">
          <thead>
            <tr>
              <th><?= e(t('ops.qc_leader.qc_entry')) ?></th>
              <th><?= e(t('ops.qc_leader.product')) ?></th>
              <th><?= e(t('ops.qc_leader.ready_qty')) ?></th>
              <th><?= e(t('ops.qc_leader.action')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($readyDispatch as $row): ?>
            <tr>
              <td>#<?= (int)$row['qc_entry_id'] ?></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td class="u-style-568b1a9ea1"><?= e(number_format((float)$row['ready_qty'], 2, '.', ',')) ?></td>
              <td><a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><a class="btn" href="/dispatch-entries/add?qc_entry_id=<?= (int)$row['qc_entry_id'] ?>&product_id=<?= (int)$row['product_id'] ?>&daily_order_id=<?= (int)$row['daily_order_id'] ?>"><?= e(t('ops.qc_leader.release_to_dispatch')) ?></a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="card" id="sla-risk">
  <h2 class="u-style-d462248a40"><?= e(t('ops.qc_leader.delay_sla_risk')) ?></h2>
  <div class="qc-alert">
    <?= e(t('ops.qc_leader.sla_overdue_plans', ['count' => (string)((int)($sla['overdue_plans'] ?? 0))])) ?> |
    <?= e(t('ops.qc_leader.sla_pending_checks_older', ['count' => (string)((int)($sla['pending_overdue'] ?? 0))])) ?> |
    <?= e(t('ops.qc_leader.sla_failed_queue_stale', ['count' => (string)((int)($sla['stale_failures'] ?? 0))])) ?>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
