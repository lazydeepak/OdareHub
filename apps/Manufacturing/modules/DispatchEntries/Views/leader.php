<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$dashboard = is_array($dashboard ?? null) ? $dashboard : [];
$date = (string)($dashboard['date'] ?? date('Y-m-d'));
$today = (string)($dashboard['today'] ?? date('Y-m-d'));
$kpi = is_array($dashboard['kpi'] ?? null) ? $dashboard['kpi'] : [];
$readyNow = is_array($dashboard['ready_now'] ?? null) ? $dashboard['ready_now'] : [];
$blockedHold = is_array($dashboard['blocked_hold'] ?? null) ? $dashboard['blocked_hold'] : [];
$partialQueue = is_array($dashboard['partial_queue'] ?? null) ? $dashboard['partial_queue'] : [];
$agingOverdue = is_array($dashboard['aging_overdue'] ?? null) ? $dashboard['aging_overdue'] : [];
$backlog = is_array($dashboard['backlog_by_due'] ?? null) ? $dashboard['backlog_by_due'] : [];
$releaseCandidates = is_array($dashboard['release_candidates'] ?? null) ? $dashboard['release_candidates'] : [];
$urgentCount = (int)($kpi['urgent'] ?? 0);
?>

<section class="card">
  <div class="dl-hero">
    <div class="ui-block">
      <h2 class="dl-title"><?= e(t('ops.dispatch_leader.title')) ?></h2>
      <div class="dl-sub"><?= e(t('ops.dispatch_leader.subtitle')) ?></div>
      <div class="u-style-de88cb98f9">
        <span class="dl-pill"><?= e(t('common.date')) ?>: <?= e($date) ?><?= $date === $today ? ' (' . e(t('ops.dispatch_leader.today')) . ')' : '' ?></span>
        <a class="btn" href="/dispatch-entries"><?= e(t('nav.dispatch_entries')) ?></a>
      </div>
    </div>
    <div class="u-style-a66baa435c">
      <a class="btn" href="/ops/approval-inbox?module=dispatch_entry"><?= e(t('action.open_approval_inbox')) ?></a>
      <a class="btn" href="/dispatch-entries/add?dispatch_date=<?= urlencode($date) ?>&dispatch_status=Ready"><?= e(t('ops.dispatch_leader.create_ready_dispatch')) ?></a>
      <a class="btn" href="/dispatch-entries/add?dispatch_date=<?= urlencode($date) ?>&dispatch_status=Hold&status_reason=<?= urlencode((string)t('ops.dispatch_leader.reason_capacity_hold')) ?>"><?= e(t('ops.dispatch_leader.create_hold')) ?></a>
    </div>
  </div>

  <div class="dl-priority">
    <div class="ui-block">
      <div class="dl-priority-count"><?= e(t('ops.dispatch_leader.urgent_right_now')) ?>: <?= $urgentCount ?></div>
      <div class="muted"><?= e(t('ops.dispatch_leader.urgent_hint')) ?></div>
    </div>
    <div class="dl-actions">
      <a class="btn" href="#blocked-hold"><?= e(t('ops.dispatch_leader.blocked_hold')) ?></a>
      <a class="btn" href="#aging-overdue"><?= e(t('ops.dispatch_leader.aging_overdue')) ?></a>
      <a class="btn" href="#release-candidates"><?= e(t('ops.dispatch_leader.release_today')) ?></a>
    </div>
  </div>

  <div class="dl-kpis">
    <div class="dl-kpi"><div class="dl-kpi-label"><?= e(t('ops.dispatch_leader.kpi_ready_now')) ?></div><div class="dl-kpi-value"><?= (int)($kpi['ready_now'] ?? 0) ?></div></div>
    <div class="dl-kpi"><div class="dl-kpi-label"><?= e(t('ops.dispatch_leader.kpi_blocked_hold')) ?></div><div class="dl-kpi-value u-style-f53de8f2a5"><?= (int)($kpi['blocked_hold'] ?? 0) ?></div></div>
    <div class="dl-kpi"><div class="dl-kpi-label"><?= e(t('ops.dispatch_leader.kpi_partial_queue')) ?></div><div class="dl-kpi-value u-style-4104211b5c"><?= (int)($kpi['partial_queue'] ?? 0) ?></div></div>
    <div class="dl-kpi"><div class="dl-kpi-label"><?= e(t('ops.dispatch_leader.kpi_aging_overdue')) ?></div><div class="dl-kpi-value u-style-fc776b69ee"><?= (int)($kpi['aging_overdue'] ?? 0) ?></div></div>
    <div class="dl-kpi"><div class="dl-kpi-label"><?= e(t('ops.dispatch_leader.kpi_today_release_candidates')) ?></div><div class="dl-kpi-value u-style-568b1a9ea1"><?= (int)($kpi['release_candidates'] ?? 0) ?></div></div>
  </div>
</section>

<?php $ownership_board = is_array($dashboard['ownership_board'] ?? null) ? $dashboard['ownership_board'] : []; $ownership_board_title = 'Ownership Workboard'; require APP_ROOT . '/public/views/partials/ownership_workboard.php'; ?>

<section class="card" id="ready-now">
  <div class="dl-section-head">
    <h2 class="u-style-d462248a40"><?= e(t('ops.dispatch_leader.ready_now')) ?></h2>
    <span class="dl-pill"><?= e(t('ops.dispatch_leader.dispatchable_immediately')) ?></span>
  </div>
  <?php if (empty($readyNow)): ?>
    <div class="dl-empty"><?= e(t('ops.dispatch_leader.no_ready_dispatch_rows')) ?></div>
  <?php else: ?>
    <ul class="dl-list dl-run">
      <?php foreach ($readyNow as $row): ?>
        <li>
          <div class="dl-row">
            <div class="ui-block">
              <div class="dl-title-sm">#<?= (int)$row['id'] ?> <?= e((string)$row['parts_name']) ?> (<?= e((string)$row['parts_number']) ?>)</div>
              <div class="dl-meta"><?= e(t('common.date')) ?> <?= e((string)$row['dispatch_date']) ?> | <?= e(t('module.dispatch_entries.destination')) ?> <?= e((string)($row['destination'] ?? '-')) ?> | <?= e(t('common.qty')) ?> <?= e(number_format((float)$row['dispatchable_qty'], 2, '.', ',')) ?></div>
            </div>
            <span class="dl-badge <?= (int)($row['is_overdue'] ?? 0) === 1 ? 'dl-badge-high' : 'dl-badge-low' ?>"><?= (int)($row['is_overdue'] ?? 0) === 1 ? e(t('ops.dispatch_leader.overdue_ready_badge')) : e(t('ops.dispatch_leader.ready_badge')) ?></span>
          </div>
          <div class="u-style-3620e10451">
            <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
            <?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?><a class="btn" href="/daily-orders/360?id=<?= (int)$row['daily_order_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><?php endif; ?>
            <a class="btn" href="/dispatch-entries/edit?id=<?= (int)$row['id'] ?>"><?= e(t('ops.dispatch_leader.open_dispatch_entry')) ?></a>
            <?php $approvalState = strtolower(trim((string)($row['approval_status'] ?? 'draft'))); ?>
            <?php if ($approvalState !== 'approved'): ?>
              <?php if (in_array($approvalState, ['draft', 'reopened', 'rejected'], true)): ?>
                <form class="u-style-b42afad1cb" method="post" action="/dispatch-entries/approval-action">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="action" value="submit">
                  <input type="hidden" name="redirect" value="/apps/manufacturing/dispatch-ops">
                  <input type="hidden" name="note" value="<?= e(t('ops.dispatch_leader.note_submitted_from_dashboard')) ?>">
                  <button class="btn" type="submit"><?= e(t('ops.dispatch_leader.submit_for_approval')) ?></button>
                </form>
              <?php endif; ?>
              <form class="u-style-b42afad1cb" method="post" action="/dispatch-entries/approval-action">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="redirect" value="/apps/manufacturing/dispatch-ops">
                <input class="input" name="note" value="<?= e(t('ops.dispatch_leader.note_approved_from_dashboard')) ?>" placeholder="<?= e(t('ops.dispatch_leader.approval_note')) ?>">
                <button class="btn ok" type="submit"><?= e(t('ops.approval_inbox.action_approve')) ?></button>
              </form>
            <?php else: ?>
              <form class="u-style-b42afad1cb" method="post" action="/dispatch-entries/transition">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <input type="hidden" name="target_status" value="Dispatched">
                <input type="hidden" name="redirect" value="/apps/manufacturing/dispatch-ops">
                <input type="hidden" name="reason" value="<?= e(t('ops.dispatch_leader.reason_released_from_dashboard')) ?>">
                <button class="btn" type="submit"><?= e(t('ops.dispatch_leader.mark_dispatched')) ?></button>
              </form>
            <?php endif; ?>
            <form class="u-style-b42afad1cb" method="post" action="/dispatch-entries/transition">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
              <input type="hidden" name="target_status" value="Hold">
              <input type="hidden" name="redirect" value="/apps/manufacturing/dispatch-ops">
              <input class="input" name="reason" value="<?= e(t('ops.dispatch_leader.reason_capacity_hold')) ?>" placeholder="<?= e(t('ops.dispatch_leader.hold_reason')) ?>" style="min-width:150px">
              <button class="btn" type="submit"><?= e(t('ops.dispatch_leader.hold')) ?></button>
            </form>
            <a class="btn" href="/daily-orders?q=<?= urlencode((string)$row['parts_number']) ?>"><?= e(t('ops.dispatch_leader.escalate_upstream')) ?></a>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>

<section class="dl-grid-2" id="blocked-hold">
  <div class="card">
    <h2 class="u-style-d462248a40"><?= e(t('ops.dispatch_leader.blocked_hold_queue')) ?></h2>
    <?php if (empty($blockedHold)): ?>
      <div class="dl-empty"><?= e(t('ops.dispatch_leader.no_blocked_hold_entries')) ?></div>
    <?php else: ?>
      <div class="dl-table-wrap">
        <table class="dl-table">
          <thead>
            <tr>
              <th><?= e(t('ops.dispatch_leader.table_entry')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_product')) ?></th>
              <th><?= e(t('common.status')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_blocker')) ?></th>
              <th><?= e(t('ops.qc_leader.action')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($blockedHold as $row): ?>
            <tr>
              <td>#<?= (int)$row['id'] ?> <span class="muted"><?= e((string)$row['dispatch_date']) ?></span></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td><?= e((string)$row['dispatch_status']) ?></td>
              <td><?= e((string)$row['blocker_hint']) ?></td>
              <td>
                <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
                <?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?><a class="btn" href="/daily-orders/360?id=<?= (int)$row['daily_order_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><?php endif; ?>
                <a class="btn" href="/dispatch-entries/edit?id=<?= (int)$row['id'] ?>"><?= e(t('ops.dispatch_leader.open_dispatch_entry')) ?></a>
                <form class="u-style-db3f546b2b" method="post" action="/dispatch-entries/transition">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="target_status" value="Ready">
                  <input type="hidden" name="redirect" value="/apps/manufacturing/dispatch-ops">
                  <input class="input" name="note" placeholder="<?= e(t('ops.dispatch_leader.release_note')) ?>" style="min-width:140px">
                  <button class="btn" type="submit"><?= e(t('ops.dispatch_leader.unblock')) ?></button>
                </form>
                <a class="btn" href="/qc-entries?status=Open"><?= e(t('ops.dispatch_leader.follow_up_qc')) ?></a>
                <a class="btn" href="/daily-orders?q=<?= urlencode((string)$row['parts_number']) ?>"><?= e(t('ops.qc_leader.escalate')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2 class="u-style-d462248a40"><?= e(t('ops.dispatch_leader.partial_dispatch_queue')) ?></h2>
    <?php if (empty($partialQueue)): ?>
      <div class="dl-empty"><?= e(t('ops.dispatch_leader.no_partial_dispatch_orders')) ?></div>
    <?php else: ?>
      <div class="dl-table-wrap">
        <table class="dl-table">
          <thead>
            <tr>
              <th><?= e(t('ops.dispatch_leader.table_order')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_product')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_demand')) ?></th>
              <th><?= e(t('ops.home_coverage.dispatched')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_balance')) ?></th>
              <th><?= e(t('ops.qc_leader.action')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($partialQueue as $row): ?>
            <tr>
              <td>#<?= (int)$row['daily_order_id'] ?> <span class="muted"><?= e((string)($row['dispatch_deadline'] ?? $row['required_date'] ?? '')) ?></span></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td><?= e(number_format((float)$row['demand_qty'], 2, '.', ',')) ?></td>
              <td><?= e(number_format((float)$row['dispatched_qty'], 2, '.', ',')) ?></td>
              <td class="u-style-4104211b5c"><?= e(number_format((float)$row['balance_qty'], 2, '.', ',')) ?></td>
              <td>
                <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
                <form class="u-style-1a036411e6" method="post" action="/dispatch-entries/start-draft">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                  <input type="hidden" name="daily_order_id" value="<?= (int)$row['daily_order_id'] ?>">
                  <input type="hidden" name="covered_qty" value="<?= e((string)$row['balance_qty']) ?>">
                  <input type="hidden" name="required_date" value="<?= e((string)($row['dispatch_deadline'] ?? $date)) ?>">
                  <input type="hidden" name="failure_fallback" value="/apps/manufacturing/dispatch-ops">
                  <button class="btn" type="submit"><?= e(t('ops.dispatch_leader.release_balance')) ?></button>
                </form>
                <a class="btn" href="/daily-orders/360?id=<?= (int)$row['daily_order_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="card" id="aging-overdue">
  <h2 class="u-style-d462248a40"><?= e(t('ops.dispatch_leader.aging_overdue_dispatches')) ?></h2>
  <?php if (empty($agingOverdue)): ?>
    <div class="dl-empty"><?= e(t('ops.dispatch_leader.no_aging_dispatches')) ?></div>
  <?php else: ?>
    <div class="dl-alert u-style-761d3addb2"><?= e(t('ops.dispatch_leader.aging_overdue_hint')) ?></div>
    <div class="dl-table-wrap">
      <table class="dl-table">
        <thead>
          <tr>
              <th><?= e(t('ops.dispatch_leader.table_entry')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_product')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_dispatch_date')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_deadline')) ?></th>
              <th><?= e(t('common.status')) ?></th>
              <th><?= e(t('ops.qc_leader.action')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($agingOverdue as $row): ?>
          <tr>
            <td>#<?= (int)$row['id'] ?></td>
            <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
            <td><?= e((string)$row['dispatch_date']) ?></td>
            <td><?= e((string)($row['dispatch_deadline'] ?? $row['required_date'] ?? '-')) ?></td>
            <td><?= e((string)$row['dispatch_status']) ?></td>
            <td>
              <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
              <?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?><a class="btn" href="/daily-orders/360?id=<?= (int)$row['daily_order_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><?php endif; ?>
              <a class="btn" href="/dispatch-entries/edit?id=<?= (int)$row['id'] ?>"><?= e(t('ops.dispatch_leader.open_dispatch_entry')) ?></a>
              <a class="btn" href="/apps/manufacturing/production-queue?date=<?= urlencode($date) ?>"><?= e(t('ops.dispatch_leader.follow_up_production')) ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="dl-grid-2">
  <div class="card">
    <h2 class="u-style-d462248a40"><?= e(t('ops.dispatch_leader.backlog_by_due_window')) ?></h2>
    <?php if (empty($backlog)): ?>
      <div class="dl-empty"><?= e(t('ops.dispatch_leader.no_open_dispatch_backlog')) ?></div>
    <?php else: ?>
      <div class="dl-table-wrap">
        <table class="dl-table u-style-85c8a158b4">
          <thead>
            <tr>
              <th><?= e(t('ops.dispatch_leader.table_window')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_orders')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_outstanding_qty')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_stock_risk_orders')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($backlog as $row): ?>
            <tr>
              <td><?= e((string)$row['due_bucket']) ?></td>
              <td><?= (int)$row['orders'] ?></td>
              <td><?= e(number_format((float)$row['outstanding_qty'], 2, '.', ',')) ?></td>
              <td><?= (int)$row['stock_risk_orders'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card" id="release-candidates">
    <h2 class="u-style-d462248a40"><?= e(t('ops.dispatch_leader.today_release_candidates')) ?></h2>
    <?php if (empty($releaseCandidates)): ?>
      <div class="dl-empty"><?= e(t('ops.dispatch_leader.no_qc_pass_candidates_today')) ?></div>
    <?php else: ?>
      <div class="dl-table-wrap">
        <table class="dl-table u-style-2e2cd21005">
          <thead>
            <tr>
              <th><?= e(t('ops.qc_leader.qc_entry')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_product')) ?></th>
              <th><?= e(t('ops.dispatch_leader.table_releasable_qty')) ?></th>
              <th><?= e(t('ops.qc_leader.action')) ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($releaseCandidates as $row): ?>
            <tr>
              <td>#<?= (int)$row['qc_entry_id'] ?></td>
              <td><?= e((string)$row['parts_name']) ?> <span class="muted"><?= e((string)$row['parts_number']) ?></span></td>
              <td class="u-style-568b1a9ea1"><?= e(number_format((float)$row['releasable_qty'], 2, '.', ',')) ?></td>
              <td>
                <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)$row['product_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a>
                <?php if ((int)($row['daily_order_id'] ?? 0) > 0): ?><a class="btn" href="/daily-orders/360?id=<?= (int)$row['daily_order_id'] ?>"><?= e(t('ops.supervisor_cockpit.order_360')) ?></a><?php endif; ?>
                <form class="u-style-1a036411e6" method="post" action="/dispatch-entries/start-draft">
                  <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                  <input type="hidden" name="daily_order_id" value="<?= (int)$row['daily_order_id'] ?>">
                  <input type="hidden" name="qc_entry_id" value="<?= (int)$row['qc_entry_id'] ?>">
                  <input type="hidden" name="covered_qty" value="<?= e((string)$row['releasable_qty']) ?>">
                  <input type="hidden" name="required_date" value="<?= e($date) ?>">
                  <input type="hidden" name="failure_fallback" value="/apps/manufacturing/dispatch-ops">
                  <button class="btn" type="submit"><?= e(t('ops.dispatch_leader.release_today')) ?></button>
                </form>
                <a class="btn" href="/qc-entries/edit?id=<?= (int)$row['qc_entry_id'] ?>"><?= e(t('nav.qc_entries')) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
