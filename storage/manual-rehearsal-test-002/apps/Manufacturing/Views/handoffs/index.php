<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$board = isset($board) && is_array($board) ? $board : [];
$date = (string)($board['date'] ?? date('Y-m-d'));
$today = (string)($board['today'] ?? date('Y-m-d'));
$kpi = isset($board['kpi']) && is_array($board['kpi']) ? $board['kpi'] : [];
$ownershipOverview = isset($board['ownership_overview']) && is_array($board['ownership_overview']) ? $board['ownership_overview'] : [];
$highRiskOrders = isset($board['high_risk_orders']) && is_array($board['high_risk_orders']) ? $board['high_risk_orders'] : [];
$bottlenecksByRole = isset($board['bottlenecks_by_role']) && is_array($board['bottlenecks_by_role']) ? $board['bottlenecks_by_role'] : [];
$flash = isset($flash) ? (string)$flash : '';
$error = isset($error) ? (string)$error : '';

$slaBadge = static function (string $level): string {
  $v = strtolower(trim($level));
  if ($v === 'breach') {
    return 'crb-badge-high';
  }
  if ($v === 'warning') {
    return 'crb-badge-medium';
  }
  return 'crb-badge-low';
};
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('ops.cross_role_handoff.title')) ?></h2>
      <div class="muted"><?= e((string)t('ops.cross_role_handoff.subtitle')) ?></div>
      <div class="muted"><?= e((string)t('common.date')) ?>: <?= e($date) ?><?= $date === $today ? ' (' . e((string)t('ops.cross_role_handoff.today')) . ')' : '' ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing/production-workboard"><?= e((string)t('nav.machine_leader')) ?></a>
      <a class="btn" href="/apps/manufacturing/qc-workboard"><?= e((string)t('nav.qc_leader')) ?></a>
      <a class="btn" href="/apps/manufacturing/dispatch-ops"><?= e((string)t('nav.dispatch_leader')) ?></a>
    </div>
  </div>
</div>

<?php if ($flash !== ''): ?><div class="card dsp-flash-ok"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="card dsp-flash-err"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="get" action="/apps/manufacturing/handoffs" class="mfg-filter-row">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('common.date')) ?></label>
      <input class="input" type="date" name="date" value="<?= e($date) ?>">
    </div>
    <div class="row">
      <button class="btn" type="submit"><?= e((string)t('common.filter')) ?></button>
      <a class="btn" href="/apps/manufacturing/handoffs"><?= e((string)t('common.reset')) ?></a>
    </div>
  </form>
</div>

<div class="stat-row">
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('ops.cross_role_handoff.waiting_for_qc')) ?></div><div class="stat-box-value"><?= (int)($kpi['waiting_for_qc'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('ops.cross_role_handoff.qc_passed_not_released')) ?></div><div class="stat-box-value"><?= (int)($kpi['qc_passed_not_released'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('ops.cross_role_handoff.dispatch_hold_blocked')) ?></div><div class="stat-box-value <?= (int)($kpi['dispatch_hold_blocked'] ?? 0) > 0 ? 'u-tone-danger' : 'u-tone-success' ?>"><?= (int)($kpi['dispatch_hold_blocked'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('ops.cross_role_handoff.aging_handoffs')) ?></div><div class="stat-box-value <?= (int)($kpi['aging_handoffs'] ?? 0) > 0 ? 'u-tone-warning' : 'u-tone-success' ?>"><?= (int)($kpi['aging_handoffs'] ?? 0) ?></div></div>
  <div class="stat-box"><div class="stat-box-label"><?= e((string)t('ops.cross_role_handoff.high_risk_orders_parts')) ?></div><div class="stat-box-value <?= (int)($kpi['high_risk_orders'] ?? 0) > 0 ? 'u-tone-danger' : 'u-tone-success' ?>"><?= (int)($kpi['high_risk_orders'] ?? 0) ?></div></div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('ops.cross_role_handoff.ownership_overview')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('ops.cross_role_handoff.item')) ?></th>
          <th><?= e((string)t('common.stage')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.owner')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.age')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.sla')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.escalation')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($ownershipOverview as $row): ?>
        <tr>
          <td><?= e((string)($row['item_ref'] ?? '')) ?></td>
          <td><?= e((string)($row['stage_label'] ?? '')) ?></td>
          <td><?= e((string)($row['current_owner_label'] ?? '')) ?></td>
          <td><?= e(number_format((float)($row['age_hours'] ?? 0), 2, '.', ',')) ?></td>
          <td><span class="crb-badge <?= e($slaBadge((string)($row['sla_level'] ?? 'ok'))) ?>"><?= e((string)($row['sla_label'] ?? t('ops.cross_role_handoff.within_sla'))) ?></span></td>
          <td><?= e((string)($row['escalation_state_text'] ?? t('ops.cross_role_handoff.not_escalated'))) ?></td>
          <td><a class="btn" href="<?= e((string)($row['detail_url'] ?? '#')) ?>"><?= e((string)t('common.open')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($ownershipOverview === []): ?><tr><td colspan="7" class="muted"><?= e((string)t('ops.cross_role_handoff.no_ownership_items')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('ops.supervisor_cockpit.bottlenecks_by_role')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('ops.cross_role_handoff.owner')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.blocked_items')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.blocked_qty')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.high_risk_orders')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($bottlenecksByRole as $row): ?>
        <tr>
          <td><?= e((string)($row['owner'] ?? '')) ?></td>
          <td><?= (int)($row['blocked_items'] ?? 0) ?></td>
          <td><?= e(number_format((float)($row['blocked_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= (int)($row['high_risk_orders'] ?? 0) ?></td>
          <td><a class="btn" href="<?= e((string)($row['next_action'] ?? '#')) ?>"><?= e((string)t('ops.cross_role_handoff.open_owner_board')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($bottlenecksByRole === []): ?><tr><td colspan="5" class="muted"><?= e((string)t('ops.cross_role_handoff.no_role_bottleneck_summary')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('ops.cross_role_handoff.high_risk_orders_parts')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('ops.supervisor_cockpit.order')) ?></th>
          <th><?= e((string)t('common.part')) ?></th>
          <th><?= e((string)t('common.coverage')) ?></th>
          <th><?= e((string)t('common.shortage')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.owner')) ?></th>
          <th><?= e((string)t('ops.cross_role_handoff.sla')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($highRiskOrders as $row): ?>
        <tr>
          <td>#<?= (int)($row['daily_order_id'] ?? 0) ?></td>
          <td><?= e((string)($row['parts_name'] ?? '')) ?> <span class="muted"><?= e((string)($row['parts_number'] ?? '')) ?></span></td>
          <td><?= e(number_format((float)($row['coverage_pct'] ?? 0), 2, '.', ',')) ?>%</td>
          <td><?= e(number_format((float)($row['shortage_qty'] ?? 0), 2, '.', ',')) ?></td>
          <td><?= e((string)($row['owner'] ?? '')) ?></td>
          <td><span class="crb-badge <?= e($slaBadge((string)($row['sla_level'] ?? 'ok'))) ?>"><?= e((string)($row['sla_label'] ?? t('ops.cross_role_handoff.within_sla'))) ?></span></td>
          <td>
            <a class="btn" href="<?= e((string)($row['next_action'] ?? '#')) ?>"><?= e((string)($row['action_label'] ?? t('common.open'))) ?></a>
            <a class="btn" href="/daily-orders/360?id=<?= (int)($row['daily_order_id'] ?? 0) ?>"><?= e((string)t('ops.cross_role_handoff.open_order_360')) ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if ($highRiskOrders === []): ?><tr><td colspan="7" class="muted"><?= e((string)t('ops.cross_role_handoff.no_high_risk_orders_now')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>