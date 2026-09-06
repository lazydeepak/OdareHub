<?php
require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php';

if (!function_exists('e')) {
    function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

$flashOk = trim((string)($flashOk ?? ''));
$flashErr = trim((string)($flashErr ?? ''));
$appKey = trim((string)($appKey ?? 'studio_sales'));
$role = trim((string)($role ?? 'viewer'));
$kpiHtml = (string)($kpiHtml ?? '');
$pendingHtml = (string)($pendingHtml ?? '');
$inProgressHtml = (string)($inProgressHtml ?? '');
$completedHtml = (string)($completedHtml ?? '');
$approvalQueue = array_values(array_filter((array)($approvalQueue ?? []), 'is_array'));
$processingQueue = array_values(array_filter((array)($processingQueue ?? []), 'is_array'));
$inProgressQueue = array_values(array_filter((array)($inProgressQueue ?? []), 'is_array'));
$myTasks = array_values(array_filter((array)($myTasks ?? []), 'is_array'));
$unassignedTasks = array_values(array_filter((array)($unassignedTasks ?? []), 'is_array'));
$overdueTasks = array_values(array_filter((array)($overdueTasks ?? []), 'is_array'));
$actionLabels = (isset($actionLabels) && is_array($actionLabels)) ? $actionLabels : [];
$notifications = array_values(array_filter((array)($notifications ?? []), 'is_array'));
$newTasks = array_values(array_filter((array)($newTasks ?? []), 'is_array'));
$recentChanges = array_values(array_filter((array)($recentChanges ?? []), 'is_array'));
$unreadCount = (int)($unreadCount ?? 0);
$currentUserId = (int)($currentUserId ?? 0);
$csrf = (string)($csrf ?? '');

$roleHintKey = 'ops.my_work.role_hint.viewer';
if ($role === 'manager') {
    $roleHintKey = 'ops.my_work.role_hint.manager';
} elseif ($role === 'operator') {
    $roleHintKey = 'ops.my_work.role_hint.operator';
} elseif ($role === 'admin') {
    $roleHintKey = 'ops.my_work.role_hint.admin';
}

$renderActionButtons = static function (array $row) use ($actionLabels, $csrf, $appKey): string {
    $orderNo = trim((string)($row['order_no'] ?? ''));
    if ($orderNo === '') {
        return '';
    }

    $nextActions = array_values(array_filter((array)($row['next_actions'] ?? []), 'is_string'));
    if ($nextActions === []) {
        return '';
    }

    $supported = ['approved', 'processing', 'completed', 'cancelled'];
    $buttons = '';
    foreach ($nextActions as $toState) {
        $safeState = strtolower(trim($toState));
        if (!in_array($safeState, $supported, true)) {
            continue;
        }

        $label = trim((string)($actionLabels[$safeState] ?? $safeState));
        $buttons .= '<form method="post" action="/ops/my-work/transition">'
            . '<input type="hidden" name="csrf" value="' . e($csrf) . '">'
            . '<input type="hidden" name="app_key" value="' . e($appKey) . '">'
            . '<input type="hidden" name="order_no" value="' . e($orderNo) . '">'
            . '<input type="hidden" name="to_state" value="' . e($safeState) . '">'
            . '<button type="submit" class="btn">' . e($label) . '</button>'
            . '</form>';
    }

    return $buttons;
};

$priorityBadgeClass = static function (string $priority): string {
    if ($priority === 'high') {
        return 'badge badge--danger';
    }
    if ($priority === 'medium') {
        return 'badge badge--warn';
    }
    return 'badge badge-upcoming';
};

$priorityLabelKey = static function (string $priority): string {
    if ($priority === 'high') {
        return 'ops.my_work.priority.high';
    }
    if ($priority === 'medium') {
        return 'ops.my_work.priority.medium';
    }
    return 'ops.my_work.priority.low';
};

$renderOwnershipButtons = static function (array $row) use ($csrf, $appKey, $role, $currentUserId): string {
    $orderNo = trim((string)($row['order_no'] ?? ''));
    if ($orderNo === '') {
        return '';
    }

    $assignedTo = (int)($row['assigned_to'] ?? 0);
    $buttons = '';

    if ($assignedTo <= 0) {
        $buttons .= '<form method="post" action="/ops/my-work/task-action">'
            . '<input type="hidden" name="csrf" value="' . e($csrf) . '">'
            . '<input type="hidden" name="app_key" value="' . e($appKey) . '">'
            . '<input type="hidden" name="order_no" value="' . e($orderNo) . '">'
            . '<input type="hidden" name="action" value="claim_task">'
            . '<button type="submit" class="btn">' . e((string)t('ops.my_work.action.claim_task')) . '</button>'
            . '</form>';
    }

    if ($role === 'manager' || $role === 'admin') {
        $buttons .= '<form method="post" action="/ops/my-work/task-action">'
            . '<input type="hidden" name="csrf" value="' . e($csrf) . '">'
            . '<input type="hidden" name="app_key" value="' . e($appKey) . '">'
            . '<input type="hidden" name="order_no" value="' . e($orderNo) . '">'
            . '<input type="hidden" name="action" value="assign_task">'
            . '<input class="input" type="number" min="1" step="1" name="assigned_to" placeholder="' . e((string)t('ops.my_work.action.assign_user_id')) . '">'
            . '<button type="submit" class="btn">' . e((string)t('ops.my_work.action.assign_task')) . '</button>'
            . '</form>';
    }

    if ($assignedTo > 0 && (($role === 'manager' || $role === 'admin') || $assignedTo === $currentUserId)) {
        $buttons .= '<form method="post" action="/ops/my-work/task-action">'
            . '<input type="hidden" name="csrf" value="' . e($csrf) . '">'
            . '<input type="hidden" name="app_key" value="' . e($appKey) . '">'
            . '<input type="hidden" name="order_no" value="' . e($orderNo) . '">'
            . '<input type="hidden" name="action" value="release_task">'
            . '<button type="submit" class="btn">' . e((string)t('ops.my_work.action.release_task')) . '</button>'
            . '</form>';
    }

    return $buttons;
};
?>

<div class="card">
  <h2><?= e((string)t('ops.my_work.title')) ?></h2>
  <div class="muted"><?= e((string)t('ops.my_work.subtitle')) ?></div>
  <div class="muted"><?= e((string)t($roleHintKey)) ?></div>

  <details>
    <summary>
      <?= e((string)t('ops.my_work.notifications.bell_label')) ?>
      (<?= e((string)$unreadCount) ?> <?= e((string)t('ops.my_work.notifications.unread_suffix')) ?>)
    </summary>

    <?php if ($notifications === []): ?>
      <div class="muted"><?= e((string)t('ops.my_work.notifications.empty')) ?></div>
    <?php else: ?>
      <?php foreach ($notifications as $notification): ?>
        <?php
          $notificationId = (int)($notification['id'] ?? 0);
          $message = trim((string)($notification['message'] ?? ''));
          $isRead = (int)($notification['is_read'] ?? 0) === 1;
          $createdAt = trim((string)($notification['created_at'] ?? ''));
        ?>
        <div class="card">
          <?php if ($isRead): ?>
            <div class="muted"><?= e((string)t('ops.my_work.notifications.read_marker')) ?></div>
          <?php else: ?>
            <div class="note warning"><?= e((string)t('ops.my_work.notifications.unread_marker')) ?></div>
          <?php endif; ?>
          <div class="ui-block"><?= e($message) ?></div>
          <div class="muted"><?= e($createdAt) ?></div>
          <?php if (!$isRead && $notificationId > 0): ?>
            <form method="post" action="/ops/my-work/notification/read">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="app_key" value="<?= e($appKey) ?>">
              <input type="hidden" name="notification_id" value="<?= e((string)$notificationId) ?>">
              <button type="submit" class="btn"><?= e((string)t('ops.my_work.notifications.mark_read')) ?></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </details>
</div>

<?php if ($flashOk !== ''): ?>
  <div class="card"><div class="note success"><?= e((string)t($flashOk)) ?></div></div>
<?php endif; ?>

<?php if ($flashErr !== ''): ?>
  <div class="card"><div class="note warning"><?= e((string)t('ops.my_work.error.' . $flashErr)) ?></div></div>
<?php endif; ?>

<div class="card">
  <h3><?= e((string)t('ops.my_work.section.kpi')) ?></h3>
  <?= $kpiHtml ?>
</div>

<div class="card">
  <h3><?= e((string)t('ops.my_work.section.pending_actions')) ?></h3>
  <h4><?= e((string)t('ops.my_work.queue.approval')) ?></h4>
  <?= $pendingHtml ?>

  <h4><?= e((string)t('ops.my_work.queue.approval_actions')) ?></h4>
  <?php if ($approvalQueue === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.no_rows')) ?></div>
  <?php else: ?>
    <?php foreach ($approvalQueue as $row): ?>
      <div class="card">
        <div class="ui-block"><strong><?= e((string)($row['order_no'] ?? '')) ?></strong> - <?= e((string)($row['customer'] ?? '')) ?></div>
        <div class="muted"><?= e((string)t('ops.my_work.table.state')) ?>: <?= e((string)($row['state'] ?? '')) ?></div>
        <?= $renderActionButtons($row) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h4><?= e((string)t('ops.my_work.queue.processing')) ?></h4>
  <?php if ($processingQueue === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.no_rows')) ?></div>
  <?php else: ?>
    <?php foreach ($processingQueue as $row): ?>
      <div class="card">
        <div class="ui-block"><strong><?= e((string)($row['order_no'] ?? '')) ?></strong> - <?= e((string)($row['customer'] ?? '')) ?></div>
        <div class="muted"><?= e((string)t('ops.my_work.table.state')) ?>: <?= e((string)($row['state'] ?? '')) ?></div>
        <?= $renderActionButtons($row) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h3><?= e((string)t('ops.my_work.section.my_tasks')) ?></h3>
  <?php if ($myTasks === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.no_rows')) ?></div>
  <?php else: ?>
    <?php foreach ($myTasks as $row): ?>
      <?php
        $priority = strtolower(trim((string)($row['priority'] ?? 'medium')));
        $dueAt = trim((string)($row['due_at'] ?? ''));
        $isOverdue = !empty($row['is_overdue']);
      ?>
      <div class="card">
        <div class="ui-block"><strong><?= e((string)($row['order_no'] ?? '')) ?></strong> - <?= e((string)($row['customer'] ?? '')) ?></div>
        <div class="muted">
          <span class="<?= e($priorityBadgeClass($priority)) ?>"><?= e((string)t($priorityLabelKey($priority))) ?></span>
          <?php if ($dueAt !== ''): ?>
            <span class="muted"><?= e((string)t('ops.my_work.table.due_at')) ?>: <?= e($dueAt) ?></span>
          <?php endif; ?>
          <?php if ($isOverdue): ?>
            <span class="badge badge-overdue"><?= e((string)t('ops.my_work.sla.overdue')) ?></span>
          <?php endif; ?>
        </div>
        <?= $renderOwnershipButtons($row) ?>
        <?= $renderActionButtons($row) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3><?= e((string)t('ops.my_work.section.unassigned_tasks')) ?></h3>
  <?php if ($unassignedTasks === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.no_rows')) ?></div>
  <?php else: ?>
    <?php foreach ($unassignedTasks as $row): ?>
      <?php $priority = strtolower(trim((string)($row['priority'] ?? 'medium'))); ?>
      <div class="card">
        <div class="ui-block"><strong><?= e((string)($row['order_no'] ?? '')) ?></strong> - <?= e((string)($row['customer'] ?? '')) ?></div>
        <div class="muted"><span class="<?= e($priorityBadgeClass($priority)) ?>"><?= e((string)t($priorityLabelKey($priority))) ?></span></div>
        <?= $renderOwnershipButtons($row) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3><?= e((string)t('ops.my_work.section.overdue_tasks')) ?></h3>
  <?php if ($overdueTasks === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.no_rows')) ?></div>
  <?php else: ?>
    <?php foreach ($overdueTasks as $row): ?>
      <?php $priority = strtolower(trim((string)($row['priority'] ?? 'medium'))); ?>
      <div class="card">
        <div class="ui-block"><strong><?= e((string)($row['order_no'] ?? '')) ?></strong> - <?= e((string)($row['customer'] ?? '')) ?></div>
        <div class="muted">
          <span class="<?= e($priorityBadgeClass($priority)) ?>"><?= e((string)t($priorityLabelKey($priority))) ?></span>
          <span class="badge badge-overdue"><?= e((string)t('ops.my_work.sla.overdue')) ?></span>
          <span><?= e((string)t('ops.my_work.table.due_at')) ?>: <?= e((string)($row['due_at'] ?? '')) ?></span>
        </div>
        <?= $renderOwnershipButtons($row) ?>
        <?= $renderActionButtons($row) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h3><?= e((string)t('ops.my_work.section.new_tasks')) ?></h3>
  <?php if ($newTasks === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.notifications.no_new_tasks')) ?></div>
  <?php else: ?>
    <?php foreach ($newTasks as $task): ?>
      <div class="note warning"><?= e(trim((string)($task['message'] ?? ''))) ?></div>
    <?php endforeach; ?>
  <?php endif; ?>

  <h3><?= e((string)t('ops.my_work.section.recent_changes')) ?></h3>
  <?php if ($recentChanges === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.notifications.no_recent_changes')) ?></div>
  <?php else: ?>
    <?php foreach ($recentChanges as $change): ?>
      <div class="muted">
        <?= e(trim((string)($change['created_at'] ?? ''))) ?> -
        <?= e(trim((string)($change['message'] ?? ''))) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h3><?= e((string)t('ops.my_work.section.in_progress')) ?></h3>
  <?= $inProgressHtml ?>

  <h4><?= e((string)t('ops.my_work.queue.in_progress_actions')) ?></h4>
  <?php if ($inProgressQueue === []): ?>
    <div class="muted"><?= e((string)t('ops.my_work.no_rows')) ?></div>
  <?php else: ?>
    <?php foreach ($inProgressQueue as $row): ?>
      <div class="card">
        <div class="ui-block"><strong><?= e((string)($row['order_no'] ?? '')) ?></strong> - <?= e((string)($row['customer'] ?? '')) ?></div>
        <div class="muted"><?= e((string)t('ops.my_work.table.state')) ?>: <?= e((string)($row['state'] ?? '')) ?></div>
        <?= $renderActionButtons($row) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h3><?= e((string)t('ops.my_work.section.completed_today')) ?></h3>
  <?= $completedHtml ?>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php';
