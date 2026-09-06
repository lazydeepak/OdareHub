<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$staffRows = is_array($staffRows ?? null) ? $staffRows : [];
$leaveRows = is_array($leaveRows ?? null) ? $leaveRows : [];
$holidayRows = is_array($holidayRows ?? null) ? $holidayRows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_schedules'), 'url' => '/apps/sbaio/schedules'],
  ['label' => t('nav.sbaio_timecards'), 'url' => '/apps/sbaio/timecards'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/leave/export'],
];
$suiteLinks = [
  ['label' => t('sbaio.common.leave_requests'), 'value' => (string)count($leaveRows)],
  ['label' => t('sbaio.common.holiday_entries'), 'value' => (string)count($holidayRows)],
];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.leave.filter_view')],
  ['label' => t('sbaio.common.scope'), 'value' => t('sbaio.leave.filter_scope')],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="grid form-grid-2 u-mb-14">
    <section class="card">
      <h3 class="u-mt-0"><?= e(t('sbaio.leave.add_leave')) ?></h3>
      <div class="muted mb-10"><?= e(t('sbaio.leave.add_leave_desc')) ?></div>
      <form method="post" action="/apps/sbaio/leave/create" class="grid form-grid-2">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e(t('nav.sbaio_staff')) ?>
          <select name="staff_id" required>
            <option value=""><?= e(t('sbaio.common.select_staff')) ?></option>
            <?php foreach ($staffRows as $staff): ?>
              <option value="<?= (int)($staff['id'] ?? 0) ?>"><?= e(trim((string)($staff['employee_code'] ?? '') . ' ' . (string)($staff['full_name'] ?? ''))) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><?= e(t('sbaio.leave.leave_type')) ?><input class="input" type="text" name="leave_type" value="leave"></label>
        <label><?= e(t('sbaio.leave.legacy_name_ref')) ?><input class="input" type="text" name="legacy_name_ref"></label>
        <label><?= e(t('sbaio.leave.leave_code')) ?><input class="input" type="text" name="leave_code"></label>
        <label><?= e(t('sbaio.leave.start_date')) ?><input class="input" type="date" name="start_date" required></label>
        <label><?= e(t('sbaio.leave.end_date')) ?><input class="input" type="date" name="end_date" required></label>
        <label><?= e(t('sbaio.leave.partial_day_unit')) ?><input class="input" type="text" name="partial_day_unit" placeholder="<?= e(t('sbaio.leave.partial_day_placeholder')) ?>"></label>
        <label><?= e(t('sbaio.leave.leave_status')) ?><input class="input" type="text" name="leave_status" value="approved"></label>
        <label><?= e(t('common.notes')) ?><input class="input" type="text" name="notes"></label>
        <div class="col-span-full"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.leave.save_leave')) ?></button></div>
      </form>
    </section>
    <section class="card">
      <h3 class="u-mt-0"><?= e(t('sbaio.leave.add_holiday')) ?></h3>
      <div class="muted mb-10"><?= e(t('sbaio.leave.add_holiday_desc')) ?></div>
      <form method="post" action="/apps/sbaio/leave/holiday" class="grid form-grid-2">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e(t('sbaio.leave.holiday_date')) ?><input class="input" type="date" name="holiday_date" required></label>
        <label><?= e(t('sbaio.leave.holiday_name')) ?><input class="input" type="text" name="holiday_name" required></label>
        <label><?= e(t('sbaio.leave.holiday_type')) ?><input class="input" type="text" name="holiday_type" value="public_holiday"></label>
        <label><?= e(t('sbaio.leave.holiday_code')) ?><input class="input" type="text" name="holiday_code"></label>
        <label><?= e(t('common.notes')) ?><input class="input" type="text" name="notes"></label>
        <label class="label-inline-check"><input type="checkbox" name="is_closed_day" value="1"> <?= e(t('sbaio.leave.closed_day')) ?></label>
        <label class="label-inline-check"><input type="checkbox" name="is_active" value="1" checked> <?= e(t('sbaio.common.active')) ?></label>
        <div class="col-span-full"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.leave.save_holiday')) ?></button></div>
      </form>
    </section>
  </div>
  <h3 class="u-mt-0"><?= e(t('sbaio.leave.requests')) ?></h3>
  <?php if ($leaveRows === []): ?>
    <?php
    $emptyTitle = t('sbaio.leave.requests');
    $emptyMessage = t('sbaio.leave.requests_empty');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th><?= e(t('sbaio.leave.table_start')) ?></th><th><?= e(t('sbaio.leave.table_end')) ?></th><th><?= e(t('sbaio.leave.table_staff')) ?></th><th><?= e(t('sbaio.leave.legacy_name_ref')) ?></th><th><?= e(t('sbaio.leave.leave_code')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('sbaio.leave.table_partial')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('sbaio.leave.table_approved_at')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($leaveRows as $row): ?>
            <tr>
              <td><?= e((string)($row['start_date'] ?? '')) ?></td>
              <td><?= e((string)($row['end_date'] ?? '')) ?></td>
              <td><?= e((string)($row['full_name'] ?? '')) ?></td>
              <td><?= e((string)($row['legacy_name_ref'] ?? '')) ?></td>
              <td><?= e((string)($row['leave_code'] ?? '')) ?></td>
              <td><?= e((string)($row['leave_type'] ?? '')) ?></td>
              <td><?= e((string)($row['partial_day_unit'] ?? '')) ?></td>
              <td><?= e((string)($row['leave_status'] ?? '')) ?></td>
              <td><?= e((string)($row['approved_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <h3><?= e(t('sbaio.leave.holiday_calendar')) ?></h3>
  <?php if ($holidayRows === []): ?>
    <?php
    $emptyTitle = t('sbaio.leave.holiday_calendar');
    $emptyMessage = t('sbaio.leave.holiday_empty');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th><?= e(t('common.date')) ?></th><th><?= e(t('sbaio.leave.holiday_name')) ?></th><th><?= e(t('common.type')) ?></th><th><?= e(t('sbaio.leave.holiday_code')) ?></th><th><?= e(t('sbaio.leave.closed_day')) ?></th><th><?= e(t('common.notes')) ?></th><th><?= e(t('common.active')) ?></th></tr></thead>
        <tbody>
          <?php foreach ($holidayRows as $row): ?>
            <tr>
              <td><?= e((string)($row['holiday_date'] ?? '')) ?></td>
              <td><?= e((string)($row['holiday_name'] ?? '')) ?></td>
              <td><?= e((string)($row['holiday_type'] ?? '')) ?></td>
              <td><?= e((string)($row['holiday_code'] ?? '')) ?></td>
              <td><?= !empty($row['is_closed_day']) ? e(t('sbaio.common.yes')) : e(t('sbaio.common.no')) ?></td>
              <td><?= e((string)($row['notes'] ?? '')) ?></td>
              <td><?= !empty($row['is_active']) ? e(t('sbaio.common.yes')) : e(t('sbaio.common.no')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
