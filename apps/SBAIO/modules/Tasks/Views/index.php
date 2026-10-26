<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$exactTask = is_array($exactTask ?? null) ? $exactTask : null;
$exactTaskId = is_int($exactTaskId ?? null) ? $exactTaskId : null;
$exactTaskState = is_string($exactTaskState ?? null) ? $exactTaskState : null;
$createdRecordId = is_int($createdRecordId ?? null) ? $createdRecordId : null;
$currentQuery = is_array($currentQuery ?? null) ? $currentQuery : [];
$searchResult = is_array($searchResult ?? null) ? $searchResult : null;
$nextSearchUrl = is_string($nextSearchUrl ?? null) ? $nextSearchUrl : null;
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_notices'), 'url' => '/apps/sbaio/notices'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/tasks/export'],
];
$suiteLinks = [['label' => t('sbaio.common.open_items'), 'value' => (string)count($rows)]];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.tasks.queue')],
  ['label' => t('sbaio.common.scope'), 'value' => 'Ops follow-up queue'],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<?php if ($createdRecordId !== null): ?>
  <section class="card u-mb-14" data-sbaio-tasks-created-record="1">
    <a class="btn btn-primary" href="/apps/sbaio/tasks?task_id=<?= e((string)$createdRecordId) ?>"><?= e(t('sbaio.tasks.open_created_record')) ?></a>
  </section>
<?php endif; ?>
<?php if ($exactTaskState !== null): ?>
  <section class="card u-mb-14" data-sbaio-tasks-exact-record="1">
    <?php if ($exactTaskState === 'invalid'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.tasks.record_id_invalid')) ?></p>
    <?php elseif ($exactTaskState === 'unavailable'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.tasks.record_unavailable')) ?></p>
    <?php elseif ($exactTaskState === 'not_found'): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.tasks.record_not_found')) ?></p>
    <?php elseif ($exactTask !== null): ?>
      <h3 class="u-mt-0"><?= e(t('sbaio.tasks.record_title')) ?></h3>
      <dl>
        <dt><?= e(t('sbaio.tasks.record_id')) ?></dt><dd><?= e((string)$exactTaskId) ?></dd>
        <dt><?= e(t('sbaio.tasks.record_task_title')) ?></dt><dd><?= e((string)($exactTask['task_title'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.tasks.record_owner')) ?></dt><dd><?= e((string)($exactTask['owner_name'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.tasks.record_type')) ?></dt><dd><?= e((string)($exactTask['task_type'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.tasks.record_status')) ?></dt><dd><?= e((string)($exactTask['task_status'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.tasks.record_priority')) ?></dt><dd><?= e((string)($exactTask['priority'] ?? '')) ?></dd>
        <dt><?= e(t('sbaio.tasks.record_due_date')) ?></dt><dd><?= e((string)($exactTask['due_date'] ?? '')) ?></dd>
      </dl>
      <a class="btn" href="/apps/sbaio/tasks?task_id=<?= e((string)$exactTaskId) ?>"><?= e(t('sbaio.tasks.open_record')) ?></a>
    <?php endif; ?>
  </section>
<?php endif; ?>
<section class="card u-mb-14" data-sbaio-tasks-search="1">
  <h3 class="u-mt-0"><?= e(t('sbaio.tasks.search_title')) ?></h3>
  <div class="muted mb-10"><?= e(t('sbaio.tasks.search_note')) ?></div>
  <form method="get" action="/apps/sbaio/tasks" class="grid form-grid-4">
    <input type="hidden" name="task_search" value="1">
    <label><?= e(t('sbaio.tasks.search_queue')) ?>
      <select name="task_queue">
        <?php foreach (['all' => 'sbaio.tasks.search_all', 'overdue' => 'sbaio.tasks.search_overdue', 'upcoming' => 'sbaio.tasks.search_upcoming', 'historical' => 'sbaio.tasks.search_historical'] as $queueValue => $queueKey): ?>
          <option value="<?= e($queueValue) ?>" <?= (($currentQuery['task_queue'] ?? 'all') === $queueValue) ? 'selected' : '' ?>><?= e(t($queueKey)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label><?= e(t('sbaio.tasks.search_status')) ?><input class="input" type="text" name="task_status_exact" value="<?= e((string)($currentQuery['task_status_exact'] ?? '')) ?>"></label>
    <label><?= e(t('sbaio.tasks.search_priority')) ?><input class="input" type="text" name="task_priority_exact" value="<?= e((string)($currentQuery['task_priority_exact'] ?? '')) ?>"></label>
    <label><?= e(t('sbaio.tasks.search_bucket')) ?><input class="input" type="text" name="task_bucket_exact" value="<?= e((string)($currentQuery['task_bucket_exact'] ?? '')) ?>"></label>
    <label><?= e(t('sbaio.tasks.search_from')) ?><input class="input" type="date" name="task_from" value="<?= e((string)($currentQuery['task_from'] ?? '')) ?>"></label>
    <label><?= e(t('sbaio.tasks.search_to')) ?><input class="input" type="date" name="task_to" value="<?= e((string)($currentQuery['task_to'] ?? '')) ?>"></label>
    <div class="col-span-full"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.tasks.search_submit')) ?></button></div>
  </form>
</section>
<?php if ($searchResult !== null): ?>
  <section class="card u-mb-14" data-sbaio-tasks-search-result="1">
    <?php if (($searchResult['valid'] ?? false) !== true): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.tasks.search_invalid')) ?></p>
    <?php elseif (($searchResult['available'] ?? false) !== true): ?>
      <p class="muted" role="alert"><?= e(t('sbaio.tasks.search_unavailable')) ?></p>
    <?php elseif ($rows === []): ?>
      <p class="muted"><?= e(t('sbaio.tasks.search_no_results')) ?></p>
    <?php else: ?>
      <p class="muted"><?= e(t('sbaio.tasks.search_order_note')) ?></p>
      <div class="table-wrap"><table>
        <thead><tr><th>#</th><th><?= e($tt('tasks.title_column')) ?></th><th><?= e(t('sbaio.tasks.record_status')) ?></th><th><?= e(t('sbaio.tasks.record_priority')) ?></th><th><?= e(t('sbaio.tasks.record_due_date')) ?></th><th><?= e(t('sbaio.tasks.search_state')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php $state = (string)($row['queue_state'] ?? 'historical'); ?>
            <tr>
              <td><a href="/apps/sbaio/tasks?task_id=<?= e((string)($row['id'] ?? '')) ?>">#<?= (int)($row['id'] ?? 0) ?></a></td>
              <td><?= e((string)($row['task_title'] ?? '')) ?></td>
              <td><?= e((string)($row['task_status'] ?? '')) ?></td>
              <td><?= e((string)($row['priority'] ?? '')) ?></td>
              <td><?= e((string)($row['due_date'] ?? '')) ?></td>
              <td><?= e(t('sbaio.tasks.queue_state_' . $state)) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($nextSearchUrl !== null): ?><p class="muted"><?= e(t('sbaio.tasks.search_limited')) ?> <a href="<?= e($nextSearchUrl) ?>"><?= e(t('sbaio.tasks.search_next')) ?></a></p><?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.tasks.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.tasks.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/tasks/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label> <?= e($tt('tasks.task_title_label')) ?> <input class="input" type="text" name="task_title" required></label>
      <label>Owner Name<input class="input" type="text" name="owner_name"></label>
      <label>Task Type<input class="input" type="text" name="task_type" placeholder="<?= e(t('sbaio.tasks.type_placeholder')) ?>"></label>
      <label><?= e(t('common.bucket')) ?><input class="input" type="text" name="task_bucket" placeholder="<?= e(t('sbaio.tasks.bucket_placeholder')) ?>"></label>
      <div class="muted" role="note"><?= e(t('sbaio.tasks.related_staff_unavailable')) ?></div>
      <label>Related Date<input class="input" type="date" name="related_date"></label>
      <label><?= e(t('common.status')) ?><input class="input" type="text" name="task_status" value="open"></label>
      <label><?= e(t('common.priority')) ?><input class="input" type="text" name="priority" value="normal"></label>
      <label>Due Date<input class="input" type="date" name="due_date"></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.tasks.create')) ?></button>
      </div>
    </form>
  </div>
  <?php if ($searchResult === null): ?>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.tasks.queue');
    $emptyMessage = t('sbaio.tasks.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.tasks.queue')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Task</th><th>Owner</th><th>Type</th><th>Bucket</th><th> <?= e($tt('common.status_label')) ?> </th><th>Priority</th><th>Related Date</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['task_title'] ?? '')) ?></td>
              <td><?= e((string)($row['owner_name'] ?? '')) ?></td>
              <td><?= e((string)($row['task_type'] ?? '')) ?></td>
              <td><?= e((string)($row['task_bucket'] ?? '')) ?></td>
              <td><?= e((string)($row['task_status'] ?? '')) ?></td>
              <td><?= e((string)($row['priority'] ?? '')) ?></td>
              <td><?= e((string)($row['related_date'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
