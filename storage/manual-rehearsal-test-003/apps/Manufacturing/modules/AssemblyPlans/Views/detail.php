<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$plan = (array)($payload['plan'] ?? []);
$metrics = (array)($payload['metrics'] ?? []);
$entries = (array)($payload['entries'] ?? []);
$dailyOrders = (array)($payload['linked_daily_orders'] ?? []);
$preOrders = (array)($payload['linked_pre_orders'] ?? []);
$stage = (array)($payload['stage_readiness'] ?? []);
$stages = (array)($stage['stages'] ?? []);
$prodStage = (array)($stages['production'] ?? []);
$qcStage = (array)($stages['qc'] ?? []);
$asmStage = (array)($stages['assembly'] ?? []);
$packagingStage = (array)($stages['packaging'] ?? []);
$dispatchStage = (array)($stages['dispatch'] ?? []);
$workflow = is_array($workflow ?? null) ? $workflow : [];
$workflowState = (string)($workflow['state'] ?? 'draft');
$workflowActions = is_array($workflow['actions'] ?? null) ? $workflow['actions'] : [];
$workflowActionKeys = array_values(array_map(static fn(array $row): string => (string)($row['action'] ?? ''), $workflowActions));
$activityTimeline = is_array($activity_timeline ?? null) ? $activity_timeline : [];
?>

<div class="card">
  <div class="row u-style-5930bcd33d">
    <div class="ui-block">
      <h2 class="u-style-1169661891">Assembly Plan Detail</h2>
      <div class="muted">
        <?= e((string)($plan['parts_name'] ?? '')) ?>
        <span>(<?= e((string)($plan['parts_number'] ?? '')) ?>)</span>
        for <?= e((string)($plan['demand_date'] ?? '')) ?>
      </div>
    </div>
    <div class="row u-style-33fcd4c359">
      <a class="btn" href="/apps/manufacturing/assembly-plans">Back to Assembly Plans</a>
      <a class="btn" href="/apps/manufacturing/assembly-queue">Assembly Queue</a>
      <a class="btn" href="/apps/manufacturing/products/360?id=<?= (int)($plan['product_id'] ?? 0) ?>">Part 360</a>
      <a class="btn" href="/apps/manufacturing/demands?demand_type=assembly">Demand Planning</a>
      <a class="btn" href="/apps/manufacturing/dispatch-ops">Dispatch Workbench</a>
    </div>
  </div>
</div>

<?php if (!empty($flash ?? '')): ?><div class="card u-style-a25b37e313"><?= e((string)$flash) ?></div><?php endif; ?>
<?php if (!empty($error ?? '')): ?><div class="card u-style-64e7e2c551"><?= e((string)$error) ?></div><?php endif; ?>
<?php $ownership_title = 'Operational Ownership'; require APP_ROOT . '/public/views/partials/ownership_summary.php'; ?>

<div class="card">
  <div class="row u-style-6ba8429203">
    <div class="card u-style-c6cca584eb"><div class="muted u-style-64ef25b191">Source Demand</div><div class="u-style-2ca3aa2a26"><?= e((string)($metrics['source_demand'] ?? 'Unknown')) ?></div></div>
    <div class="card u-style-c6cca584eb"><div class="muted u-style-64ef25b191"> <?= e($tt('assembly_plans.approved_assembly_qty_action')) ?> </div><div class="u-style-ff5500165a"><?= e((string)($metrics['effective_qty'] ?? 0)) ?></div></div>
    <div class="card u-style-c6cca584eb"><div class="muted u-style-64ef25b191"> <?= e($tt('assembly_plans.completed_qty_message')) ?> </div><div class="u-style-ff5500165a"><?= e((string)($metrics['completed_qty'] ?? 0)) ?></div></div>
    <div class="card u-style-c6cca584eb"><div class="muted u-style-64ef25b191"><?= e(t('assembly_plans.detail.remaining_qty')) ?></div><div class="ui-block" style="font-size:1.2em;font-weight:700;color: var(--text)"><?= e((string)($metrics['remaining_qty'] ?? 0)) ?></div></div>
</div>

<?php if (!empty($activityTimeline)): ?>
<div class="card">
  <h3 class="u-style-d462248a40">Activity Timeline</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>When</th>
          <th>Action</th>
          <th>Type</th>
          <th> <?= e($tt('assembly_plans.state_column')) ?> </th>
          <th>Reason / Note</th>
          <th>Changes</th>
          <th>Actor</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($activityTimeline as $event): ?>
        <tr>
          <td><?= e((string)($event['created_at'] ?? '')) ?></td>
          <td><?= e((string)($event['action_name'] ?? '')) ?></td>
          <td><?= e((string)($event['event_type'] ?? '')) ?></td>
          <td><?= e((string)($event['old_state'] ?? '-')) ?> -> <?= e((string)($event['new_state'] ?? '-')) ?></td>
          <td><?= e(trim((string)($event['reason_text'] ?? '')) !== '' ? (string)$event['reason_text'] : (string)($event['note_text'] ?? '')) ?></td>
          <td>
            <?php $diff = is_array($event['diff'] ?? null) ? $event['diff'] : []; ?>
            <?php if (!empty($diff)): ?>
              <?php $items = []; foreach ($diff as $d) { $items[] = (string)($d['field'] ?? '') . ': ' . (string)($d['old'] ?? '-') . ' -> ' . (string)($d['new'] ?? '-'); } ?>
              <?= e(implode(' | ', $items)) ?>
            <?php else: ?>
              -
            <?php endif; ?>
          </td>
          <td><?= e((string)($event['actor_label'] ?? 'System')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-d462248a40"> <?= e($tt('assembly_plans.stage_readiness_context_title')) ?> </h3>
  <div class="row u-style-e4a4232f5d">
    <div class="muted"> <?= e($tt('assembly_plans.production_status_label')) ?> <strong><?= e((string)($prodStage['status'] ?? 'n/a')) ?></strong></div>
    <div class="muted"> <?= e($tt('assembly_plans.assembly_status_label')) ?> <strong><?= e((string)($asmStage['status'] ?? 'n/a')) ?></strong></div>
    <div class="muted"> <?= e($tt('assembly_plans.qc_status_label')) ?> <strong><?= e((string)($qcStage['status'] ?? 'n/a')) ?></strong></div>
    <div class="muted"> <?= e($tt('assembly_plans.preparation_status_label')) ?> <strong><?= e((string)($packagingStage['status'] ?? 'n/a')) ?></strong></div>
    <div class="muted"> <?= e($tt('assembly_plans.dispatch_status_label')) ?> <strong><?= e((string)($dispatchStage['status'] ?? 'n/a')) ?></strong></div>
    <div class="muted">Downstream effect: <?= e(((string)($asmStage['status'] ?? '') === 'complete' || !empty($asmStage['released_qty'])) ? 'Assembly is ready to feed the next downstream stage.' : 'Complete or release assembly to move this part into the next downstream stage.') ?></div>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Plan Update</h3>
  <div class="muted u-style-fdf33f2304"> <?= e($tt('assembly_plans.workflow_state_label')) ?> <strong><?= e(ucfirst(str_replace('_', ' ', $workflowState))) ?></strong></div>
  <form method="post" action="/apps/manufacturing/assembly-plans/update" class="row u-style-a4c06ee61b">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
    <div class="ui-block">
      <label class="muted u-style-98847c28df">Adjusted Qty</label>
      <input class="input" type="number" min="0" step="0.01" name="adjusted_qty" value="<?= e((string)($plan['adjusted_qty'] ?? $plan['system_qty'] ?? 0)) ?>">
    </div>
    <div class="u-style-7435d081a4">
      <label class="muted u-style-98847c28df">Notes / blockers</label>
      <input class="input" type="text" name="adjustment_note" value="<?= e((string)($plan['adjustment_note'] ?? '')) ?>" placeholder="Adjustment reason, blocker, or operational note">
    </div>
    <button class="btn" type="submit"> <?= e($tt('assembly_plans.save_action')) ?> </button>
  </form>

  <?php if (in_array('approve', $workflowActionKeys, true)): ?>
  <form method="post" action="/apps/manufacturing/assembly-plans/approve" class="row u-style-b2b3adb7fe">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
    <div class="ui-block">
      <label class="muted u-style-98847c28df"> <?= e($tt('assembly_plans.approved_qty_admin_label')) ?> </label>
      <input class="input" type="number" min="0" step="0.01" name="approved_qty" value="<?= e((string)($plan['approved_qty'] ?? $plan['adjusted_qty'] ?? $plan['system_qty'] ?? 0)) ?>">
    </div>
    <div class="ui-block">
      <label class="muted u-style-98847c28df">Note</label>
      <input class="input" type="text" name="note" placeholder="Optional approval note">
    </div>
    <button class="btn ok" type="submit"> <?= e($tt('assembly_plans.admin_approve_action')) ?> </button>
  </form>
  <?php endif; ?>

  <?php if (!empty($workflowActions)): ?>
  <form method="post" action="/apps/manufacturing/assembly-plans/transition" class="row u-style-b2b3adb7fe">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$plan['id'] ?>">
    <div class="ui-block">
      <label class="muted u-style-98847c28df">Workflow Action</label>
      <select name="action" required>
        <?php foreach ($workflowActions as $wa): ?>
          <option value="<?= e((string)($wa['action'] ?? '')) ?>"><?= e((string)($wa['label'] ?? $wa['action'] ?? '')) ?> -> <?= e((string)($wa['to_state'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-3ab4724d9f">
      <label class="muted u-style-98847c28df">Reason</label>
      <input class="input" type="text" name="reason" placeholder="Required for reject/reopen/hold/cancel">
    </div>
    <div class="u-style-3ab4724d9f">
      <label class="muted u-style-98847c28df">Note</label>
      <input class="input" type="text" name="note" placeholder="Optional transition note">
    </div>
    <button class="btn" type="submit"> <?= e($tt('assembly_plans.apply_action')) ?> </button>
  </form>
  <?php endif; ?>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Execution Tracking</h3>
  <form method="post" action="/apps/manufacturing/assembly-plans/execution-log" class="row u-style-a4c06ee61b">
    <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
    <input type="hidden" name="assembly_plan_id" value="<?= (int)$plan['id'] ?>">
    <input type="hidden" name="product_id" value="<?= (int)$plan['product_id'] ?>">
    <input type="hidden" name="assembly_date" value="<?= e((string)($plan['demand_date'] ?? date('Y-m-d'))) ?>">
    <div class="ui-block"><label class="muted u-style-98847c28df">Planned Qty</label><input class="input" type="number" min="0" step="0.01" name="planned_qty" value="<?= e((string)($metrics['effective_qty'] ?? 0)) ?>"></div>
    <div class="ui-block"><label class="muted u-style-98847c28df"> <?= e($tt('assembly_plans.completed_qty_message')) ?> </label><input class="input" type="number" min="0" step="0.01" name="completed_qty" value="0"></div>
    <div class="ui-block"><label class="muted u-style-98847c28df"> <?= e($tt('assembly_plans.rejected_qty_label')) ?> </label><input class="input" type="number" min="0" step="0.01" name="rejected_qty" value="0"></div>
    <div class="ui-block">
      <label class="muted u-style-98847c28df"> <?= e($tt('common.status_label')) ?> </label>
      <select name="status">
        <?php foreach (['draft', 'in_progress', 'completed', 'approved', 'blocked'] as $s): ?>
          <option value="<?= e($s) ?>"><?= e(ucfirst($s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="u-style-571a77ba57"><label class="muted u-style-98847c28df">Note</label><input class="input" type="text" name="note" placeholder="Shift note / blocker / handoff"></div>
    <button class="btn ok" type="submit">Log Execution</button>
  </form>

  <div class="table-wrap u-style-56f4356299">
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Date</th>
          <th>Planned</th>
          <th> <?= e($tt('assembly_plans.completed_column')) ?> </th>
          <th> <?= e($tt('assembly_plans.rejected_column')) ?> </th>
          <th> <?= e($tt('common.status_label')) ?> </th>
          <th> <?= e($tt('assembly_plans.completed_by_column')) ?> </th>
          <th> <?= e($tt('assembly_plans.approved_by_column')) ?> </th>
          <th>Note</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($entries as $e): ?>
        <tr>
          <td><?= (int)$e['id'] ?></td>
          <td><?= e((string)$e['assembly_date']) ?></td>
          <td><?= e((string)$e['planned_qty']) ?></td>
          <td><?= e((string)$e['completed_qty']) ?></td>
          <td><?= e((string)$e['rejected_qty']) ?></td>
          <td><?= e((string)$e['status']) ?></td>
          <td><?= e((string)($e['completed_by'] ?? '-')) ?></td>
          <td><?= e((string)($e['approved_by'] ?? '-')) ?></td>
          <td><?= e((string)($e['note'] ?? '')) ?></td>
          <td>
            <?php if ((string)($e['status'] ?? '') !== 'approved'): ?>
              <form class="u-style-1169661891" method="post" action="/apps/manufacturing/assembly-plans/execution-approve">
                <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                <input type="hidden" name="assembly_plan_id" value="<?= (int)$plan['id'] ?>">
                <input type="hidden" name="entry_id" value="<?= (int)$e['id'] ?>">
                <button class="btn" type="submit"> <?= e($tt('common.approve_action')) ?> </button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($entries)): ?>
        <tr><td colspan="10" class="muted">No execution entries logged yet.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3 class="u-style-d462248a40">Linked Source Documents</h3>
  <div class="row u-style-0974da4c19">
    <div class="u-style-5b19ae3b97">
      <h4 class="u-style-d60e50b0d3">Daily Orders</h4>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Order Date</th><th>Required</th><th>Qty</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
          <tbody>
          <?php foreach ($dailyOrders as $d): ?>
            <tr>
              <td><?= (int)$d['id'] ?></td>
              <td><?= e((string)$d['order_date']) ?></td>
              <td><?= e((string)$d['required_date']) ?></td>
              <td><?= e((string)$d['qty']) ?></td>
              <td><?= e((string)$d['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($dailyOrders)): ?>
            <tr><td colspan="5" class="muted">No linked daily orders for this date.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="u-style-5b19ae3b97">
      <h4 class="u-style-d60e50b0d3">Pre Orders</h4>
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Required</th><th>Planned</th><th>Balance</th><th>Priority</th></tr></thead>
          <tbody>
          <?php foreach ($preOrders as $p): ?>
            <tr>
              <td><?= (int)$p['id'] ?></td>
              <td><?= e((string)$p['required_date']) ?></td>
              <td><?= e((string)$p['planned_qty']) ?></td>
              <td><?= e((string)$p['balance_qty']) ?></td>
              <td><?= e((string)$p['planning_priority']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($preOrders)): ?>
            <tr><td colspan="5" class="muted">No linked pre orders for this date.</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
