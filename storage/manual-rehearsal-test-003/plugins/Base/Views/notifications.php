<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$notifications = is_array($notifications ?? null) ? $notifications : [];
$status = trim((string)($status ?? ''));
$unreadCount = (int)($unreadCount ?? 0);

$sevClass = static function (string $severity): string {
    $v = strtolower(trim($severity));
    if ($v === 'critical') {
        return 'ns-chip-critical';
    }
    if ($v === 'warning') {
        return 'ns-chip-warning';
    }
    if ($v === 'action_required') {
        return 'ns-chip-action';
    }
    if ($v === 'approval_required') {
        return 'ns-chip-approval';
    }
    return 'ns-chip-info';
};

$groupOrder = ['action_required', 'approval_signals', 'escalations', 'system'];
$grouped = ['action_required' => [], 'approval_signals' => [], 'escalations' => [], 'system' => [], 'read_recent' => []];

foreach ($notifications as $row) {
    $isRead = (string)($row['status'] ?? 'new') === 'read';
    if ($isRead && $status === '') {
        $grouped['read_recent'][] = $row;
        continue;
    }
    $group = \Plugins\Base\Services\NotificationService::signalGroup((array)$row);
    if (!isset($grouped[$group])) {
        $group = 'system';
    }
    $grouped[$group][] = $row;
}

$recordRef = static function (array $row): string {
    $entityType = trim((string)($row['entity_type'] ?? ''));
    $entityId = (int)($row['entity_id'] ?? 0);
    if ($entityType === '' || $entityId <= 0) {
        return '-';
    }
    return strtoupper(str_replace('_', ' ', $entityType)) . ' #' . $entityId;
};

$renderRow = static function (array $row, string $status, callable $sevClass, callable $recordRef): void {
    $eventLabel = \Plugins\Base\Services\NotificationService::eventLabel((string)($row['event_type'] ?? ''));
    $severityLabel = \Plugins\Base\Services\NotificationService::severityLabel((string)($row['severity'] ?? 'info'));
    $actions = \Plugins\Base\Services\NotificationService::recommendedActions($row);
    $redirect = '/ops/notifications' . ($status !== '' ? '?status=' . urlencode($status) : '');
    ?>
    <article class="ns-row">
      <div class="ns-row-head">
        <div class="ns-row-head-left">
          <span class="ns-chip <?= e($sevClass((string)($row['severity'] ?? 'info'))) ?>"><?= e($severityLabel) ?></span>
          <span class="ns-chip ns-chip-event"><?= e($eventLabel) ?></span>
        </div>
        <div class="ns-time"><?= e((string)($row['created_at'] ?? '-')) ?></div>
      </div>
      <div class="ns-what"><strong><?= e((string)($row['title'] ?? t('ops.notifications.default_title'))) ?></strong></div>
      <div class="ns-why"><?= e((string)($row['message'] ?? '')) ?></div>
      <div class="ns-meta">
        <span><strong><?= e(t('ops.notifications.record')) ?>:</strong> <?= e($recordRef($row)) ?></span>
        <span><strong><?= e(t('ops.notifications.stage')) ?>:</strong> <?= e((string)($row['stage'] ?? '-')) ?></span>
        <span><strong><?= e(t('common.status')) ?>:</strong> <?= e((string)($row['status'] ?? 'new')) ?></span>
      </div>
      <div class="ns-actions">
        <a class="btn" href="<?= e((string)$actions['primary_url']) ?>"><?= e((string)$actions['primary_label']) ?></a>
        <?php if (trim((string)($actions['secondary_url'] ?? '')) !== '' && (string)$actions['secondary_url'] !== (string)$actions['primary_url']): ?>
          <a class="btn" href="<?= e((string)$actions['secondary_url']) ?>"><?= e((string)$actions['secondary_label']) ?></a>
        <?php endif; ?>
        <?php if ((string)($row['status'] ?? '') === 'new'): ?>
          <form method="post" action="/ops/notifications/read" class="ns-inline">
            <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <button class="btn" type="submit"><?= e(t('ops.notifications.mark_read')) ?></button>
          </form>
        <?php endif; ?>
        <?php if ((string)($row['status'] ?? '') !== 'dismissed'): ?>
          <form method="post" action="/ops/notifications/dismiss" class="ns-inline">
            <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)($row['id'] ?? 0) ?>">
            <input type="hidden" name="redirect" value="<?= e($redirect) ?>">
            <button class="btn" type="submit"><?= e(t('ops.notifications.dismiss')) ?></button>
          </form>
        <?php endif; ?>
      </div>
    </article>
    <?php
};
?>

<section class="card">
  <div class="ns-head">
    <div class="ui-block">
      <h2 class="ns-title"><?= e(t('ops.notifications.title_v2')) ?></h2>
      <div class="ns-sub"><?= e(t('ops.notifications.subtitle_v2')) ?></div>
    </div>
    <div class="ns-kpis">
      <div class="ns-kpi ns-kpi-unread"><div class="label"><?= e(t('ops.notifications.unread')) ?></div><div class="value"><?= (int)$unreadCount ?></div></div>
      <div class="ns-kpi ns-kpi-action"><div class="label"><?= e(t('ops.notifications.kpi_action_required')) ?></div><div class="value"><?= count($grouped['action_required']) ?></div></div>
      <div class="ns-kpi ns-kpi-approval"><div class="label"><?= e(t('ops.notifications.kpi_approval_signals')) ?></div><div class="value"><?= count($grouped['approval_signals']) ?></div></div>
    </div>
  </div>

  <div class="ns-toolbar">
    <div class="ns-tabs">
      <a class="ns-tab <?= $status === '' ? 'active' : '' ?>" href="/ops/notifications"><?= e(t('common.all')) ?></a>
      <a class="ns-tab <?= $status === 'new' ? 'active' : '' ?>" href="/ops/notifications?status=new"><?= e(t('ops.notifications.new')) ?></a>
      <a class="ns-tab <?= $status === 'read' ? 'active' : '' ?>" href="/ops/notifications?status=read"><?= e(t('ops.notifications.read')) ?></a>
      <a class="ns-tab <?= $status === 'dismissed' ? 'active' : '' ?>" href="/ops/notifications?status=dismissed"><?= e(t('ops.notifications.dismissed')) ?></a>
    </div>
    <form method="post" action="/ops/notifications/read-all" class="ns-inline">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <input type="hidden" name="redirect" value="/ops/notifications<?= $status !== '' ? '?status=' . urlencode($status) : '' ?>">
      <button class="btn" type="submit"><?= e(t('ops.notifications.mark_all_read')) ?></button>
    </form>
  </div>

  <?php if ((bool)($isAdmin ?? false)): ?>
    <section class="ns-section u-style-f2da3ac109">
      <div class="ns-section-head">
        <div class="ui-block">
          <h3 class="ns-section-title"><?= e(t('ops.notifications.admin_compose_title')) ?></h3>
          <div class="ns-section-desc"><?= e(t('ops.notifications.admin_compose_desc')) ?></div>
        </div>
      </div>
      <form class="u-style-07af141b9c" method="post" action="/ops/notifications/send-message">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="redirect_to" value="/ops/notifications">
        
        <label class="u-style-1fdb2b4302">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.compose_kind')) ?></span>
          <select class="u-style-0a6b6e03e7" name="message_kind">
            <option value="message"><?= e(t('ops.notifications.compose_kind_message')) ?></option>
            <option value="guide"><?= e(t('ops.notifications.compose_kind_guide')) ?></option>
          </select>
        </label>

        <label class="u-style-1fdb2b4302">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.severity')) ?></span>
          <select class="u-style-0a6b6e03e7" name="severity">
            <option value="info"><?= e(t('ops.notifications.compose_severity_info')) ?></option>
            <option value="warning"> <?= e($tt('base.warning')) ?> </option>
            <option value="critical"><?= e(t('ops.notifications.compose_severity_critical')) ?></option>
          </select>
        </label>

        <label class="u-style-1fdb2b4302">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.compose_target')) ?></span>
          <select class="u-style-0a6b6e03e7" name="target_type" onchange="document.getElementById('ns-target-role-sel').style.display=this.value==='role'?'flex':'none'; document.getElementById('ns-target-user-sel').style.display=this.value==='user'?'flex':'none';">
            <option value="all"><?= e(t('ops.notifications.compose_target_all')) ?></option>
            <option value="role"><?= e(t('ops.notifications.compose_target_role')) ?></option>
            <option value="user"><?= e(t('ops.notifications.compose_target_user')) ?></option>
          </select>
        </label>

        <label class="u-style-2fe82eb122" id="ns-target-role-sel">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.compose_target_role')) ?></span>
          <select class="u-style-0a6b6e03e7" name="target_role">
            <option value=""><?= e(t('ops.notifications.compose_any')) ?></option>
            <?php foreach ((array)($targetRoles ?? []) as $role): ?>
              <option value="<?= e((string)$role) ?>"><?= e((string)$role) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="u-style-2fe82eb122" id="ns-target-user-sel">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.compose_target_user')) ?></span>
          <select class="u-style-0a6b6e03e7" name="target_user_id">
            <option value="0"><?= e(t('ops.notifications.compose_none')) ?></option>
            <?php foreach ((array)($assignmentRows ?? []) as $row): ?>
              <option value="<?= (int)($row['id'] ?? 0) ?>"><?= e((string)($row['email'] ?? '')) ?> (#<?= (int)($row['id'] ?? 0) ?>)</option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="u-style-4d799c00fd">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.compose_title')) ?></span>
          <input class="u-style-0a6b6e03e7" type="text" name="title" maxlength="190" placeholder="<?= e(t('ops.notifications.compose_title_placeholder')) ?>" required>
        </label>

        <label class="u-style-4d799c00fd">
          <span class="u-style-c153be8683"><?= e(t('ops.notifications.compose_message')) ?></span>
          <textarea class="u-style-0a6b6e03e7" name="message" rows="3" maxlength="6000" placeholder="<?= e(t('ops.notifications.compose_message_placeholder')) ?>" required></textarea>
        </label>

        <div class="u-style-d62d03a142">
          <button class="btn ok u-style-f22f138efc" type="submit"><?= e(t('ops.notifications.compose_send')) ?></button>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <?php
  $groupMeta = [
      'action_required' => ['title' => t('ops.notifications.group_action_required'), 'desc' => t('ops.notifications.group_action_required_desc')],
      'approval_signals' => ['title' => t('ops.notifications.group_approval_signals'), 'desc' => t('ops.notifications.group_approval_signals_desc')],
      'escalations' => ['title' => t('ops.notifications.group_escalations'), 'desc' => t('ops.notifications.group_escalations_desc')],
      'system' => ['title' => t('ops.notifications.group_system'), 'desc' => t('ops.notifications.group_system_desc')],
  ];
  $hasAny = false;
  foreach ($groupOrder as $groupKey):
      $rows = (array)($grouped[$groupKey] ?? []);
      if ($rows === []) {
          continue;
      }
      $hasAny = true;
      ?>
      <section class="ns-section">
        <div class="ns-section-head">
          <div class="ui-block">
            <h3 class="ns-section-title"><?= e((string)($groupMeta[$groupKey]['title'] ?? $groupKey)) ?></h3>
            <div class="ns-section-desc"><?= e((string)($groupMeta[$groupKey]['desc'] ?? '')) ?></div>
          </div>
          <div class="ns-count"><?= count($rows) ?> <?= e(t('ops.notifications.items')) ?></div>
        </div>
        <?php foreach ($rows as $row): ?>
          <?php $renderRow((array)$row, $status, $sevClass, $recordRef); ?>
        <?php endforeach; ?>
      </section>
  <?php endforeach; ?>

  <?php if (!$hasAny && empty($grouped['read_recent'])): ?>
    <div class="ns-empty"><?= e(t('ops.notifications.none_for_filter')) ?></div>
  <?php endif; ?>

  <?php if (!empty($grouped['read_recent']) && $status === ''): ?>
    <details class="ns-section">
      <summary class="ns-section-title"><?= e(t('ops.notifications.group_recently_read')) ?> (<?= count($grouped['read_recent']) ?>)</summary>
      <div class="ns-section-desc"><?= e(t('ops.notifications.group_recently_read_desc')) ?></div>
      <?php foreach ($grouped['read_recent'] as $row): ?>
        <?php $renderRow((array)$row, $status, $sevClass, $recordRef); ?>
      <?php endforeach; ?>
    </details>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
