<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$staffRows = is_array($staffRows ?? null) ? $staffRows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_tasks'), 'url' => '/apps/sbaio/tasks'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/notices/export'],
];
$suiteLinks = [['label' => t('sbaio.common.records'), 'value' => (string)count($rows)]];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.notices.feed')],
  ['label' => t('sbaio.common.scope'), 'value' => t('sbaio.notices.scope_active_feed')],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.notices.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.notices.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/notices/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label><?= e(t('sbaio.notices.title')) ?><input class="input" type="text" name="title" required></label>
      <label><?= e(t('common.type')) ?><input class="input" type="text" name="notice_type" placeholder="<?= e(t('sbaio.notices.type_placeholder')) ?>"></label>
      <label><?= e(t('sbaio.notices.target_period')) ?><input class="input" type="text" name="target_period" placeholder="<?= e(t('sbaio.notices.period_placeholder')) ?>"></label>
      <label><?= e(t('sbaio.notices.level')) ?><input class="input" type="text" name="notice_level" value="info"></label>
      <label><?= e(t('sbaio.notices.related_staff')) ?>
        <select name="related_staff_id">
          <option value=""><?= e(t('sbaio.common.none')) ?></option>
          <?php foreach ($staffRows as $staff): ?>
            <option value="<?= (int)($staff['id'] ?? 0) ?>"><?= e(trim((string)($staff['employee_code'] ?? '') . ' ' . (string)($staff['full_name'] ?? ''))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><input type="checkbox" name="requires_ack" value="1"> <?= e(t('sbaio.notices.requires_ack')) ?></label>
      <label><input type="checkbox" name="is_active" value="1" checked> <?= e(t('sbaio.common.active')) ?></label>
      <label class="col-span-full"><?= e(t('sbaio.notices.body')) ?><textarea name="body" rows="4"></textarea></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.notices.create')) ?></button>
      </div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.notices.feed');
    $emptyMessage = t('sbaio.notices.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.notices.feed')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th><?= e(t('sbaio.notices.title')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('sbaio.notices.target_period')) ?></th><th><?= e(t('sbaio.notices.level')) ?></th><th><?= e(t('sbaio.notices.ack')) ?></th><th><?= e(t('common.active')) ?></th><th><?= e(t('common.created')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['title'] ?? '')) ?></td>
              <td><?= e((string)($row['notice_type'] ?? '')) ?></td>
              <td><?= e((string)($row['target_period'] ?? '')) ?></td>
              <td><?= e((string)($row['notice_level'] ?? '')) ?></td>
              <td><?= !empty($row['requires_ack']) ? e(t('sbaio.common.yes')) : e(t('sbaio.common.no')) ?></td>
              <td><?= !empty($row['is_active']) ? e(t('sbaio.common.yes')) : e(t('sbaio.common.no')) ?></td>
              <td><?= e((string)($row['created_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
