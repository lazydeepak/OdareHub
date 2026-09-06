<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$staffRows = is_array($staffRows ?? null) ? $staffRows : [];
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
      <label>Related Staff
        <select name="related_staff_id">
          <option value=""><?= e(t('sbaio.common.none')) ?></option>
          <?php foreach ($staffRows as $staff): ?>
            <option value="<?= (int)($staff['id'] ?? 0) ?>"><?= e(trim((string)($staff['employee_code'] ?? '') . ' ' . (string)($staff['full_name'] ?? ''))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Related Date<input class="input" type="date" name="related_date"></label>
      <label><?= e(t('common.status')) ?><input class="input" type="text" name="task_status" value="open"></label>
      <label><?= e(t('common.priority')) ?><input class="input" type="text" name="priority" value="normal"></label>
      <label>Due Date<input class="input" type="date" name="due_date"></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.tasks.create')) ?></button>
      </div>
    </form>
  </div>
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
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
