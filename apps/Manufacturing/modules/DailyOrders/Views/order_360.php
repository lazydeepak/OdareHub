<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$payload = is_array($payload ?? null) ? $payload : [];
$order = is_array($payload['order'] ?? null) ? $payload['order'] : [];
$due = is_array($payload['due'] ?? null) ? $payload['due'] : [];
$coverage = is_array($payload['coverage'] ?? null) ? $payload['coverage'] : [];
$productionPlans = is_array($payload['production_plans'] ?? null) ? $payload['production_plans'] : [];
$productionEntries = is_array($payload['production_entries'] ?? null) ? $payload['production_entries'] : [];
$qcPlans = is_array($payload['qc_plans'] ?? null) ? $payload['qc_plans'] : [];
$qcEntries = is_array($payload['qc_entries'] ?? null) ? $payload['qc_entries'] : [];
$dispatchEntries = is_array($payload['dispatch_entries'] ?? null) ? $payload['dispatch_entries'] : [];
$handoff = is_array($payload['handoff'] ?? null) ? $payload['handoff'] : [];
$current = is_array($handoff['current'] ?? null) ? $handoff['current'] : null;
$activeHandoffs = is_array($handoff['active'] ?? null) ? $handoff['active'] : [];
$notifications = is_array($payload['notifications'] ?? null) ? $payload['notifications'] : [];
$workflowState = is_array($payload['workflow_state'] ?? null) ? $payload['workflow_state'] : [];
$recommendedActions = is_array($payload['recommended_actions'] ?? null) ? $payload['recommended_actions'] : [];
$timeline = is_array($payload['timeline'] ?? null) ? $payload['timeline'] : [];
$relatedSummary = is_array($payload['related_summary'] ?? null) ? $payload['related_summary'] : [];
$currentRole = strtolower(trim((string)($current_role ?? '')));

$slaClass = static function (string $level): string {
    $v = strtolower(trim($level));
    if ($v === 'breach') {
        return 'o360-badge-high';
    }
    if ($v === 'warning') {
        return 'o360-badge-medium';
    }
    return 'o360-badge-low';
};

$roleToken = static function (string $role): string {
  $role = strtolower(trim($role));
  if ($role === '' || $role === 'all') {
    return 'all';
  }
  if (str_contains($role, 'dispatch')) {
    return 'dispatch';
  }
  if (str_contains($role, 'qc')) {
    return 'qc';
  }
  if (str_contains($role, 'machine')) {
    return 'machine';
  }
  if (str_contains($role, 'plan')) {
    return 'planning';
  }
  if (str_contains($role, 'admin')) {
    return 'admin';
  }
  return 'all';
};

$roleGroup = $roleToken($currentRole);
$actionVisible = static function (array $action, string $roleGroup): bool {
  $roles = array_map(static fn ($v): string => strtolower(trim((string)$v)), (array)($action['roles'] ?? ['all']));
  if (in_array('all', $roles, true) || in_array($roleGroup, $roles, true)) {
    return true;
  }
  return $roleGroup === 'admin';
};
?>

<section class="card">
  <div class="o360-hero">
    <div class="ui-block">
      <h2 class="o360-title"><?= e(t('order_360.title')) ?> #<?= (int)($order['id'] ?? 0) ?></h2>
      <div class="o360-sub"><?= e(t('module.daily_orders.order_360_subtitle')) ?></div>
      <div class="o360-muted u-style-8a77e5a311">
        <?= e(t('common.customer')) ?>: <strong><?= e((string)($order['customer_name'] ?? '-')) ?></strong> |
        <?= e(t('common.part')) ?>: <strong><?= e((string)($order['parts_name'] ?? '-')) ?> (<?= e((string)($order['parts_number'] ?? '')) ?>)</strong> |
        <?= e(t('common.status')) ?>: <strong><?= e((string)($order['status'] ?? '-')) ?></strong> |
        <?= e(t('module.daily_orders.cov.coverage')) ?>: <strong><?= e((string)($coverage['coverage_status'] ?? '-')) ?></strong>
      </div>
      <div class="o360-muted u-style-96ad6099e2">
        <?= e(t('order_360.label.order_date')) ?>: <?= e((string)($order['order_date'] ?? '-')) ?> |
        <?= e(t('order_360.label.required')) ?>: <?= e((string)($order['required_date'] ?? '-')) ?> |
        <?= e(t('order_360.label.dispatch_deadline')) ?>: <?= e((string)($order['dispatch_deadline'] ?? '-')) ?> |
        <?= e(t('order_360.label.due_state')) ?>: <strong><?= e((string)($due['label'] ?? '-')) ?></strong>
      </div>
      <div class="o360-muted u-style-96ad6099e2">
        <?= e(t('order_360.label.supply_mode')) ?>: <strong><?= e((string)($order['supply_mode'] ?? 'in_house')) ?></strong> |
        <?= e(t('order_360.label.fulfillment_mode')) ?>: <strong><?= e((string)($order['fulfillment_mode'] ?? 'via_ipm')) ?></strong> |
        <?= e(t('order_360.label.requires_ipm_qc')) ?>: <strong><?= ((int)($order['requires_ipm_qc'] ?? 1) === 1) ? e(t('common.yes')) : e(t('common.no')) ?></strong>
      </div>
    </div>
    <div class="o360-actions">
      <a class="btn" href="/daily-orders"><?= e(t('module.daily_orders.back_to_orders')) ?></a>
      <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)($order['product_id'] ?? 0) ?>"><?= e(t('module.production_plans.part_360')) ?></a>
      <a class="btn" href="/daily-orders/edit?id=<?= (int)($order['id'] ?? 0) ?>"><?= e(t('module.daily_orders.edit_order')) ?></a>
      <a class="btn" href="/apps/manufacturing/handoffs"><?= e(t('order_360.action.handoff_board')) ?></a>
      <a class="btn" href="/ops/notifications"><?= e(t('order_360.action.notifications')) ?></a>
    </div>
  </div>
</section>

<section class="card">
  <h3 class="o360-section-title"><?= e(t('order_360.section.current_workflow_state')) ?></h3>
  <div class="o360-grid">
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.operational_stage')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e((string)($workflowState['stage_label'] ?? '-')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.planning_needed')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= !empty($workflowState['planning_needed']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.awaiting_qc')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= !empty($workflowState['awaiting_qc']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.ready_for_dispatch')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= !empty($workflowState['dispatch_ready']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.partially_released')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= !empty($workflowState['partially_released']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.wf.supply_blocked')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= !empty($workflowState['supply_blocked']) ? e(t('common.yes')) : e(t('common.no')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.wf.planned_produced')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e(number_format((float)($workflowState['planned_qty'] ?? 0), 2, '.', ',')) ?> / <?= e(number_format((float)($workflowState['produced_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.qc_pass_released_remaining')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e(number_format((float)($workflowState['qc_pass_qty'] ?? 0), 2, '.', ',')) ?> / <?= e(number_format((float)($workflowState['released_qty'] ?? 0), 2, '.', ',')) ?> / <?= e(number_format((float)($workflowState['remaining_qty'] ?? 0), 2, '.', ',')) ?></div></div>
  </div>
</section>

<section class="card">
  <h3 class="o360-section-title"><?= e(t('order_360.section.coverage')) ?></h3>
  <div class="o360-grid">
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.cov.demand')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['demand_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.cov.covered')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['covered_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.cov.shortage')) ?></div><div class="o360-kpi u-style-fc776b69ee"><?= e(number_format((float)($coverage['shortage_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.cov.coverage')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['coverage_pct'] ?? 0), 2, '.', ',')) ?>% (<?= e((string)($coverage['coverage_status'] ?? '-')) ?>)</div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.stock_contribution')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['stock_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.planned_supply_contribution')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['plan_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.qc_contribution')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['qc_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.dispatch_contribution')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['dispatched_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('module.daily_orders.cov.net_supply')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['net_supply_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.open_demand_product')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['open_demand_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.forecast_pressure')) ?></div><div class="o360-kpi"><?= e(number_format((float)($coverage['forecast_pressure_qty'] ?? 0), 2, '.', ',')) ?></div></div>
    <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.last_recalculated')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e((string)($coverage['last_recalculated_at'] ?? '-')) ?></div></div>
  </div>
  <div class="o360-card u-style-d2c171b18b">
    <div class="o360-muted"><?= e(t('module.daily_orders.cov.explanation')) ?></div>
    <div class="ui-block"><?= e((string)($coverage['reason'] ?? '-')) ?></div>
    <div class="o360-muted u-style-fe7b4979fe"><?= e(t('order_360.text.coverage_formula')) ?></div>
  </div>
</section>

<?php if (!empty($recommendedActions)): ?>
<section class="card">
  <h3 class="o360-section-title"><?= e(t('module.daily_orders.recommended_actions')) ?></h3>
  <div class="o360-grid">
    <?php foreach ($recommendedActions as $action): ?>
      <?php if (!$actionVisible((array)$action, $roleGroup)) { continue; } ?>
      <div class="o360-card">
        <div class="u-style-e3ec02ace9"><?= e((string)($action['label'] ?? t('common.action'))) ?></div>
        <div class="o360-muted u-style-96ad6099e2"><?= e((string)($action['intent'] ?? '')) ?></div>
        <div class="u-style-8a77e5a311">
          <?php if ((string)($action['method'] ?? 'link') === 'post'): ?>
            <form method="post" action="<?= e((string)($action['url'] ?? '#')) ?>" style="margin:0;display:flex;gap:6px;flex-wrap:wrap">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <?php foreach ((array)($action['post'] ?? []) as $key => $value): ?>
                <input type="hidden" name="<?= e((string)$key) ?>" value="<?= e((string)$value) ?>">
              <?php endforeach; ?>
              <button class="btn" type="submit"><?= e((string)($action['label'] ?? t('common.action'))) ?></button>
            </form>
          <?php else: ?>
            <a class="btn" href="<?= e((string)($action['url'] ?? '#')) ?>"><?= e((string)($action['label'] ?? t('common.open'))) ?></a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <h3 class="o360-section-title"><?= e(t('order_360.section.handoff')) ?></h3>
  <?php if ($current === null): ?>
    <div class="o360-empty"><?= e(t('order_360.empty.handoff')) ?></div>
  <?php else: ?>
    <div class="o360-grid">
      <div class="o360-card"><div class="o360-muted"><?= e(t('common.current_stage')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e((string)($current['stage_label'] ?? '')) ?></div></div>
      <div class="o360-card"><div class="o360-muted"><?= e(t('common.owner')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e((string)($current['owner'] ?? '')) ?><?= !empty($current['owner_display'] ?? '') ? ' / ' . e((string)$current['owner_display']) : '' ?></div></div>
      <div class="o360-card"><div class="o360-muted"><?= e(t('common.sla')) ?></div><div class="o360-kpi u-style-951b72dbdd"><span class="o360-badge <?= e($slaClass((string)($current['sla_level'] ?? 'ok'))) ?>"><?= e((string)($current['sla_label'] ?? t('order_360.value.within_sla'))) ?></span></div></div>
      <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.stage_age')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e(number_format((float)($current['age_hours'] ?? 0), 2, '.', ',')) ?>h</div></div>
      <div class="o360-card"><div class="o360-muted"><?= e(t('common.escalation')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e((string)($current['escalation_state'] ?? t('order_360.value.not_escalated'))) ?><?= !empty($current['escalation_level'] ?? '') ? ' (' . e((string)$current['escalation_level']) . ')' : '' ?></div></div>
      <div class="o360-card"><div class="o360-muted"><?= e(t('order_360.kpi.entity_ref')) ?></div><div class="o360-kpi u-style-951b72dbdd"><?= e((string)($current['entity_type'] ?? '')) ?>-<?= (int)($current['entity_id'] ?? 0) ?></div></div>
    </div>
  <?php endif; ?>

  <?php if (!empty($activeHandoffs)): ?>
    <div class="o360-table-wrap u-style-d2c171b18b">
      <table class="o360-table">
        <thead><tr><th><?= e(t('common.entity')) ?></th><th><?= e(t('common.stage')) ?></th><th><?= e(t('common.owner')) ?></th><th><?= e(t('common.age')) ?></th><th><?= e(t('common.sla')) ?></th><th><?= e(t('common.escalation')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($activeHandoffs as $h): ?>
          <tr>
            <td><?= e((string)($h['entity_type'] ?? '')) ?>-<?= (int)($h['entity_id'] ?? 0) ?></td>
            <td><?= e((string)($h['stage_label'] ?? '')) ?></td>
            <td><?= e((string)($h['owner'] ?? '')) ?></td>
            <td><?= e(number_format((float)($h['age_hours'] ?? 0), 2, '.', ',')) ?>h</td>
            <td><span class="o360-badge <?= e($slaClass((string)($h['sla_level'] ?? 'ok'))) ?>"><?= e((string)($h['sla_label'] ?? t('order_360.value.within_sla'))) ?></span></td>
            <td><?= e((string)($h['escalation_state'] ?? t('order_360.value.not_escalated'))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?php if (!empty($timeline)): ?>
<section class="card">
  <h3 class="o360-section-title"><?= e(t('module.daily_orders.workflow_timeline')) ?></h3>
  <div class="o360-table-wrap">
    <table class="o360-table u-style-3f69389924">
      <thead><tr><th><?= e(t('common.when')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.event')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($timeline as $event): ?>
        <tr>
          <td><?= e((string)($event['when'] ?? '')) ?></td>
          <td><?= e((string)($event['type'] ?? '')) ?></td>
          <td><?= e((string)($event['title'] ?? '')) ?></td>
          <td><?= e((string)($event['status'] ?? '')) ?></td>
          <td><a class="btn" href="<?= e((string)($event['url'] ?? '#')) ?>"><?= e(t('common.open')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php
$renderList = static function(string $title, string $emptyText, array $rows, callable $renderer): void {
?>
<section class="card">
  <h3 class="o360-section-title"><?= e($title) ?></h3>
  <?php if (empty($rows)): ?>
    <div class="o360-empty"><?= e($emptyText) ?></div>
  <?php else: ?>
    <div class="o360-table-wrap">
      <?php $renderer($rows); ?>
    </div>
  <?php endif; ?>
</section>
<?php
};

$renderList(t('module.daily_orders.linked_plans'), t('module.daily_orders.no_plans'), $productionPlans, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.planned_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['plan_date'] ?? '')) ?></td>
      <td><?= e((string)($r['machine_no'] ?? '')) ?> <?= e((string)($r['machine_name'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['planned_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
      <td><?= e((string)($r['approval_status'] ?? t('common.draft'))) ?></td>
      <td><?= trim((string)($r['locked_at'] ?? '')) !== '' ? e(t('common.locked')) : e(t('common.open')) ?></td>
      <td><a class="btn" href="/production-plans/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList(t('module.daily_orders.linked_entries'), t('module.daily_orders.no_entries'), $productionEntries, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.good_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['production_date'] ?? '')) ?></td>
      <td><?= e((string)($r['machine_no'] ?? '')) ?> <?= e((string)($r['machine_name'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['good_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
      <td><a class="btn" href="/production-entries/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList(t('module.daily_orders.linked_qc_plans'), t('module.daily_orders.no_qc_plans'), $qcPlans, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.planned_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['plan_date'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['planned_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
      <td><a class="btn" href="/qc-plans/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList(t('module.daily_orders.linked_qc_entries'), t('module.daily_orders.no_qc_entries'), $qcEntries, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.qc_type')) ?></th><th><?= e(t('common.checked_qty')) ?></th><th><?= e(t('common.pass_qty')) ?></th><th><?= e(t('common.fail_qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['qc_type'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['checked_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e(number_format((float)($r['pass_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e(number_format((float)($r['fail_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
      <td><?= e((string)($r['approval_status'] ?? t('common.draft'))) ?></td>
      <td><?= trim((string)($r['locked_at'] ?? '')) !== '' ? e(t('common.locked')) : e(t('common.open')) ?></td>
      <td><a class="btn" href="/qc-entries/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList(t('module.daily_orders.linked_dispatch'), t('module.daily_orders.no_dispatch'), $dispatchEntries, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.destination')) ?></th><th><?= e(t('common.action')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['dispatch_date'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['dispatchable_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['dispatch_status'] ?? '')) ?></td>
      <td><?= e((string)($r['approval_status'] ?? t('common.draft'))) ?></td>
      <td><?= trim((string)($r['locked_at'] ?? '')) !== '' || strtolower(trim((string)($r['dispatch_status'] ?? ''))) === 'dispatched' ? e(t('common.locked')) : e(t('common.open')) ?></td>
      <td><?= e((string)($r['destination'] ?? '')) ?></td>
      <td><a class="btn" href="/dispatch-entries/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList(t('module.daily_orders.notifications'), t('module.daily_orders.no_notifications'), $notifications, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.when')) ?></th><th><?= e(t('common.severity')) ?></th><th><?= e(t('common.event')) ?></th><th><?= e(t('common.message')) ?></th><th><?= e(t('common.entity')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e((string)($r['created_at'] ?? '')) ?></td>
      <td><?= e((string)($r['severity'] ?? '')) ?></td>
      <td><?= e((string)($r['event_type'] ?? '')) ?></td>
      <td><strong><?= e((string)($r['title'] ?? '')) ?></strong><br><span class="o360-muted"><?= e((string)($r['message'] ?? '')) ?></span></td>
      <td><?= e((string)($r['entity_type'] ?? '')) ?>-<?= (int)($r['entity_id'] ?? 0) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});
?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
