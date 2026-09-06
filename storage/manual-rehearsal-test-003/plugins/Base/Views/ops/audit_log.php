<?php
$rows = is_array($rows ?? null) ? $rows : [];
$filters = is_array($filters ?? null) ? $filters : [];
$scope = is_array($scope ?? null) ? $scope : [];
$eventTypes = is_array($event_types ?? null) ? $event_types : [];
$actions = is_array($actions ?? null) ? $actions : [];

$selectedEntityType = (string)($filters['entity_type'] ?? '');
$selectedEntityId = (string)((int)($filters['entity_id'] ?? 0));
if ($selectedEntityId === '0') {
    $selectedEntityId = '';
}
$selectedApp = (string)($filters['app_key'] ?? '');
$selectedModule = (string)($filters['module_key'] ?? '');
$selectedEvent = (string)($filters['event_type'] ?? '');
$selectedAction = (string)($filters['action_name'] ?? '');
$selectedActor = (string)($filters['actor'] ?? '');
$selectedFrom = (string)($filters['from_date'] ?? '');
$selectedTo = (string)($filters['to_date'] ?? '');

$authorityRole = strtolower(trim((string)($scope['authority_role'] ?? 'app_user')));
$assignedApps = array_values(array_filter(array_map('strval', (array)($scope['assigned_apps'] ?? [])), static fn(string $v): bool => trim($v) !== ''));
$moduleVisibility = array_values(array_filter(array_map('strval', (array)($scope['module_visibility'] ?? [])), static fn(string $v): bool => trim($v) !== ''));

$entityTypeOptions = [
    '' => t('common.all'),
    'production_plan' => 'production_plan',
    'assembly_plan' => 'assembly_plan',
    'qc_entry' => 'qc_entry',
    'dispatch_entry' => 'dispatch_entry',
    'production_entry' => 'production_entry',
    'daily_order' => 'daily_order',
    'handoff_tracking' => 'handoff_tracking',
    'product' => 'product',
];

$appOptions = ['' => t('common.all')];
if ($authorityRole === 'platform_admin') {
    $appOptions['platform'] = 'platform';
    $appOptions['manufacturing'] = 'manufacturing';
    $appOptions['sbaio'] = 'sbaio';
}
foreach ($assignedApps as $appKey) {
    $appOptions[$appKey] = $appKey;
}
ksort($appOptions);

$moduleOptions = ['' => t('common.all')];
$knownModules = ['ops', 'production', 'assembly', 'qc', 'dispatch', 'demands', 'coverage'];
if ($authorityRole === 'platform_admin') {
    foreach ($knownModules as $module) {
        $moduleOptions[$module] = $module;
    }
}
foreach ($moduleVisibility as $module) {
    $moduleOptions[$module] = $module;
}
ksort($moduleOptions);
?>

<section class="card">
  <div class="u-style-d726be1351">
    <div class="ui-block">
      <h2 class="u-style-1da9facb4d"><?= t('ops.audit_log.title') ?></h2>
      <div class="muted u-style-dcc427eba7">
        <?= t('ops.audit_log.subtitle') ?>
      </div>
      <div class="muted u-style-2fd7789b39">
        <?= t('ops.audit_log.scope_label') ?> <strong><?= e($authorityRole) ?></strong>
        <?php if (!empty($assignedApps)): ?> | <?= e(t('ops.audit_log.filter_app')) ?>: <?= e(implode(', ', $assignedApps)) ?><?php endif; ?>
        <?php if (!empty($moduleVisibility)): ?> | <?= e(t('ops.audit_log.filter_module')) ?>: <?= e(implode(', ', $moduleVisibility)) ?><?php endif; ?>
      </div>
    </div>
    <div class="row">
      <a class="btn" href="/apps/manufacturing/handoffs"><?= t('ops.audit_log.handoff_board') ?></a>
      <a class="btn" href="/ops/approval-inbox"><?= t('ops.approval_inbox.title_v2') ?></a>
    </div>
  </div>
</section>

<section class="card">
  <form class="u-style-9128b654fd" method="get" action="/ops/audit-log">
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_entity') ?></label>
      <select name="entity_type">
        <?php foreach ($entityTypeOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= $selectedEntityType === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_entity_id') ?></label>
      <input class="input" type="number" name="entity_id" min="1" value="<?= e($selectedEntityId) ?>">
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_app') ?></label>
      <select name="app_key">
        <?php foreach ($appOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= $selectedApp === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_module') ?></label>
      <select name="module_key">
        <?php foreach ($moduleOptions as $value => $label): ?>
          <option value="<?= e((string)$value) ?>" <?= $selectedModule === (string)$value ? 'selected' : '' ?>><?= e((string)$label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_event') ?></label>
      <select name="event_type">
        <option value=""><?= t('common.all') ?></option>
        <?php foreach ($eventTypes as $event): ?>
          <option value="<?= e((string)$event) ?>" <?= $selectedEvent === (string)$event ? 'selected' : '' ?>><?= e((string)$event) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_action') ?></label>
      <select name="action_name">
        <option value=""><?= t('common.all') ?></option>
        <?php foreach ($actions as $action): ?>
          <option value="<?= e((string)$action) ?>" <?= $selectedAction === (string)$action ? 'selected' : '' ?>><?= e((string)$action) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('ops.audit_log.filter_actor') ?></label>
      <input class="input" name="actor" value="<?= e($selectedActor) ?>" placeholder="<?= t('ops.audit_log.actor_placeholder') ?>">
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('common.from') ?></label>
      <input class="input" type="date" name="from_date" value="<?= e($selectedFrom) ?>">
    </div>
    <div class="ui-block">
      <label class="muted u-style-3c4316eb0f"><?= t('common.to') ?></label>
      <input class="input" type="date" name="to_date" value="<?= e($selectedTo) ?>">
    </div>
    <div class="row u-style-804ac9edc8">
      <button class="btn" type="submit"><?= t('ops.audit_log.apply_filters') ?></button>
      <a class="btn" href="/ops/audit-log"><?= t('common.reset') ?></a>
    </div>
  </form>
</section>

<section class="card">
  <h3 class="u-style-291b7bbb01"><?= t('ops.audit_log.events_heading') ?> (<?= (int)count($rows) ?>)</h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= t('common.when') ?></th>
          <th><?= t('ops.audit_log.col_entity') ?></th>
          <th><?= t('ops.audit_log.col_event') ?></th>
          <th><?= t('ops.audit_log.col_action') ?></th>
          <th><?= t('ops.audit_log.filter_actor') ?></th>
          <th><?= t('common.state') ?></th>
          <th><?= t('ops.audit_log.col_diff') ?></th>
          <th><?= t('ops.audit_log.col_note') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" class="muted"><?= t('ops.audit_log.no_results') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['created_at'] ?? '')) ?></td>
              <td>
                <?= e((string)($row['entity_type'] ?? '')) ?>#<?= (int)($row['entity_id'] ?? 0) ?>
                <div class="muted u-style-41b1f51b79">
                  <?= e((string)($row['app_key'] ?? '-')) ?> / <?= e((string)($row['module_key'] ?? '-')) ?>
                </div>
              </td>
              <td><?= e((string)($row['event_type'] ?? '')) ?></td>
              <td><?= e((string)($row['action_name'] ?? '')) ?></td>
              <td><?= e((string)($row['actor_label'] ?? t('common.system'))) ?></td>
              <td>
                <?php $oldState = (string)($row['old_state'] ?? ''); $newState = (string)($row['new_state'] ?? ''); ?>
                <?php if ($oldState !== '' || $newState !== ''): ?>
                  <?= e($oldState !== '' ? $oldState : '-') ?> -> <?= e($newState !== '' ? $newState : '-') ?>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
              <td>
                <?php $diffSummary = (string)($row['diff_summary'] ?? ''); ?>
                <?= $diffSummary !== '' ? e($diffSummary) : '<span class="muted">-</span>' ?>
              </td>
              <td><?= e((string)($row['note_text'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
