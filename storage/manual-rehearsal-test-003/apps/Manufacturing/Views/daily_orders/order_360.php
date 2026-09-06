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
$ownership_summary = $handoff;

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
      <h2 class="o360-title"><?= e(t('module.daily_orders.order_360')) ?> #<?= (int)($order['id'] ?? 0) ?></h2>
      <div class="o360-sub"><?= e(t('module.daily_orders.order_360_subtitle')) ?></div>
      <div class="o360-muted u-style-8a77e5a311">
        <?= e(t('module.daily_orders.customer')) ?>: <strong><?= e((string)($order['customer_name'] ?? '-')) ?></strong> |
        <?= e(t('common.part')) ?>: <strong><?= e((string)($order['parts_name'] ?? '-')) ?> (<?= e((string)($order['parts_number'] ?? '')) ?>)</strong> |
        <?= e(t('common.status')) ?>: <strong><?= e((string)($order['status'] ?? '-')) ?></strong> |
        <?= e(t('common.coverage')) ?>: <strong><?= e((string)($coverage['coverage_status'] ?? '-')) ?></strong>
      </div>
      <div class="o360-muted u-style-96ad6099e2">
        <?= e(t('module.daily_orders.order_date')) ?>: <?= e((string)($order['order_date'] ?? '-')) ?> |
        <?= e(t('module.daily_orders.required_date')) ?>: <?= e((string)($order['required_date'] ?? '-')) ?> |
        <?= e(t('module.daily_orders.dispatch_deadline')) ?>: <?= e((string)($order['dispatch_deadline'] ?? '-')) ?>
      </div>
    </div>
    <div class="o360-actions">
      <a class="btn" href="/daily-orders"><?= e(t('module.daily_orders.back_to_orders')) ?></a>
      <a class="btn" href="/daily-orders/edit?id=<?= (int)($order['id'] ?? 0) ?>"><?= e(t('module.daily_orders.edit_order')) ?></a>
      <a class="btn" href="/apps/manufacturing/handoffs"><?= e(t('nav.handoffs')) ?></a>
    </div>
  </div>
</section>

<section class="card">
  <h3 class="o360-section-title"><?= e(t('module.daily_orders.workflow_state')) ?></h3>
  <div class="stat-row">
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.wf.stage')) ?></div><div class="stat-box-value u-style-951b72dbdd"><?= e((string)($workflowState['stage_label'] ?? '-')) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.wf.planned_produced')) ?></div><div class="stat-box-value u-style-951b72dbdd"><?= e(number_format((float)($workflowState['planned_qty'] ?? 0), 0)) ?> / <?= e(number_format((float)($workflowState['produced_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.wf.qc_remaining')) ?></div><div class="stat-box-value u-style-951b72dbdd"><?= e(number_format((float)($workflowState['qc_pass_qty'] ?? 0), 0)) ?> / <?= e(number_format((float)($workflowState['remaining_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.wf.supply_blocked')) ?></div><div class="stat-box-value u-style-951b72dbdd"><?= !empty($workflowState['supply_blocked']) ? e(t('module.production_plans.yes')) : e(t('module.production_plans.no')) ?></div></div>
  </div>
</section>

<section class="card">
  <h3 class="o360-section-title"><?= e(t('module.daily_orders.coverage_composition')) ?></h3>
  <div class="stat-row">
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.demand')) ?></div><div class="stat-box-value"><?= e(number_format((float)($coverage['demand_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.covered')) ?></div><div class="stat-box-value"><?= e(number_format((float)($coverage['covered_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.shortage')) ?></div><div class="stat-box-value u-tone-danger"><?= e(number_format((float)($coverage['shortage_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.coverage')) ?></div><div class="stat-box-value"><?= e(number_format((float)($coverage['coverage_pct'] ?? 0), 1)) ?>%</div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('common.status')) ?></div><div class="stat-box-value u-style-951b72dbdd"><?= e((string)($coverage['coverage_status'] ?? '-')) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.stock')) ?></div><div class="stat-box-value"><?= e(number_format((float)($coverage['stock_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.plan_supply')) ?></div><div class="stat-box-value"><?= e(number_format((float)($coverage['plan_qty'] ?? 0), 0)) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e(t('module.daily_orders.cov.net_supply')) ?></div><div class="stat-box-value"><?= e(number_format((float)($coverage['net_supply_qty'] ?? 0), 0)) ?></div></div>
  </div>
  <?php if (trim((string)($coverage['reason'] ?? '')) !== ''): ?>
  <div class="card u-style-8a77e5a311"><div class="muted"><?= e(t('module.daily_orders.cov.explanation')) ?></div><div class="ui-block"><?= e((string)($coverage['reason'] ?? '')) ?></div></div>
  <?php endif; ?>
</section>

<?php if (!empty($recommendedActions)): ?>
<section class="card">
  <h3 class="o360-section-title"><?= e(t('module.daily_orders.recommended_actions')) ?></h3>
  <div class="o360-grid">
    <?php foreach ($recommendedActions as $action): ?>
      <?php if (!$actionVisible((array)$action, $roleGroup)) { continue; } ?>
      <div class="o360-card">
        <div class="u-style-e3ec02ace9"><?= e((string)($action['label'] ?? (string)t('module.production_plans.action'))) ?></div>
        <div class="o360-muted u-style-96ad6099e2"><?= e((string)($action['intent'] ?? '')) ?></div>
        <div class="u-style-8a77e5a311">
          <?php if ((string)($action['method'] ?? 'link') === 'post'): ?>
            <form method="post" action="<?= e((string)($action['url'] ?? '#')) ?>" style="margin:0;display:flex;gap:6px;flex-wrap:wrap">
              <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
              <?php foreach ((array)($action['post'] ?? []) as $key => $value): ?>
                <input type="hidden" name="<?= e((string)$key) ?>" value="<?= e((string)$value) ?>">
              <?php endforeach; ?>
              <button class="btn" type="submit"><?= e((string)($action['label'] ?? (string)t('common.save'))) ?></button>
            </form>
          <?php else: ?>
            <a class="btn" href="<?= e((string)($action['url'] ?? '#')) ?>"><?= e((string)($action['label'] ?? (string)t('common.open'))) ?></a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section>
  <?php
    $ownership_title = (string)t('module.daily_orders.operational_ownership');
    $ownership_chain_note = (string)t('module.daily_orders.order_360_subtitle');
    require APP_ROOT . '/public/views/partials/ownership_summary.php';
  ?>
</section>

<?php if (!empty($timeline)): ?>
<section class="card">
  <h3 class="o360-section-title"><?= e(t('module.daily_orders.workflow_timeline')) ?></h3>
  <div class="o360-table-wrap">
    <table class="o360-table u-style-3f69389924">
      <thead><tr><th><?= e(t('common.when')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.item')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
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

$renderList((string)t('module.daily_orders.linked_plans'), (string)t('module.daily_orders.no_plans'), $productionPlans, static function(array $rows): void {?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['plan_date'] ?? '')) ?></td>
      <td><?= e((string)($r['machine_no'] ?? '')) ?> <?= e((string)($r['machine_name'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['planned_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
      <td><?= e((string)($r['approval_status'] ?? '')) ?></td>
      <td><?= trim((string)($r['locked_at'] ?? '')) !== '' ? e(t('module.production_plans.locked')) : e(t('module.production_plans.open')) ?></td>
      <td><a class="btn" href="/production-plans/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList((string)t('module.daily_orders.linked_entries'), (string)t('module.daily_orders.no_entries'), $productionEntries, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.machine')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
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

$renderList((string)t('module.daily_orders.linked_qc_plans'), (string)t('module.daily_orders.no_qc_plans'), $qcPlans, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
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

$renderList((string)t('module.daily_orders.linked_qc_entries'), (string)t('module.daily_orders.no_qc_entries'), $qcEntries, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('module.production_plans.approval')) ?></th><th><?= e(t('module.production_plans.lock')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['qc_type'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['pass_qty'] ?? 0), 2, '.', ',')) ?> / <?= e(number_format((float)($r['fail_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
      <td><?= e((string)($r['approval_status'] ?? '')) ?></td>
      <td><?= trim((string)($r['locked_at'] ?? '')) !== '' ? e(t('module.production_plans.locked')) : e(t('module.production_plans.open')) ?></td>
      <td><a class="btn" href="/qc-entries/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList((string)t('module.daily_orders.linked_dispatch'), (string)t('module.daily_orders.no_dispatch'), $dispatchEntries, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.id')) ?></th><th><?= e(t('common.date')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('common.source')) ?></th><th><?= e(t('common.actions')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)($r['id'] ?? 0) ?></td>
      <td><?= e((string)($r['dispatch_date'] ?? '')) ?></td>
      <td><?= e(number_format((float)($r['dispatchable_qty'] ?? 0), 2, '.', ',')) ?></td>
      <td><?= e((string)($r['dispatch_status'] ?? '')) ?></td>
      <td><?= e((string)($r['destination'] ?? '')) ?></td>
      <td><a class="btn" href="/dispatch-entries/edit?id=<?= (int)($r['id'] ?? 0) ?>"><?= e(t('common.open')) ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});

$renderList((string)t('module.daily_orders.notifications'), (string)t('module.daily_orders.no_notifications'), $notifications, static function(array $rows): void {
?>
<table class="o360-table">
  <thead><tr><th><?= e(t('common.when')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('common.message')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e((string)($r['created_at'] ?? '')) ?></td>
      <td><?= e((string)($r['event_type'] ?? '')) ?></td>
      <td><strong><?= e((string)($r['title'] ?? '')) ?></strong><br><span class="o360-muted"><?= e((string)($r['message'] ?? '')) ?></span></td>
      <td><?= e((string)($r['status'] ?? '')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
});
?>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
