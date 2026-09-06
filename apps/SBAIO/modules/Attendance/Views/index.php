<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$staffRows = is_array($staffRows ?? null) ? $staffRows : [];
$rows = is_array($rows ?? null) ? $rows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_timecards'), 'url' => '/apps/sbaio/timecards'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/attendance/export'],
];
$suiteLinks = [
  ['label' => t('nav.sbaio_staff'), 'value' => (string)count($staffRows)],
  ['label' => t('sbaio.common.recent_rows'), 'value' => (string)count($rows)],
];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.attendance.recent')],
  ['label' => t('sbaio.common.scope'), 'value' => t('sbaio.common.active_staff')],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.attendance.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.attendance.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/attendance/save" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label><?= e(t('nav.sbaio_staff')) ?>
        <select name="staff_id" required>
          <option value=""><?= e(t('sbaio.common.select_staff')) ?></option>
          <?php foreach ($staffRows as $staff): ?>
            <option value="<?= (int)($staff['id'] ?? 0) ?>"><?= e(trim((string)($staff['employee_code'] ?? '') . ' ' . (string)($staff['full_name'] ?? ''))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label><?= e(t('common.date')) ?><input class="input" type="date" name="attendance_date" required></label>
      <label><?= e(t('sbaio.attendance.day_marker')) ?>
        <select name="day_marker">
          <option value="workday"><?= e(t('sbaio.attendance.day_marker.workday')) ?></option>
          <option value="holiday"><?= e(t('sbaio.attendance.day_marker.holiday')) ?></option>
          <option value="leave"><?= e(t('sbaio.attendance.day_marker.leave')) ?></option>
          <option value="off_day"><?= e(t('sbaio.attendance.day_marker.off_day')) ?></option>
        </select>
      </label>
      <label><?= e(t('sbaio.attendance.manual_note')) ?><input class="input" type="text" name="correction_note" placeholder="<?= e(t('sbaio.attendance.manual_note_placeholder')) ?>"></label>
      <label><?= e(t('sbaio.attendance.work_start')) ?><input class="input" type="time" name="work_start_time"></label>
      <label><?= e(t('sbaio.attendance.work_end')) ?><input class="input" type="time" name="work_end_time"></label>
      <label><?= e(t('timecards.column.break_start')) ?><input class="input" type="time" name="break_start_time"></label>
      <label><?= e(t('timecards.column.break_end')) ?><input class="input" type="time" name="break_end_time"></label>
      <div class="col-span-full"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.attendance.save')) ?></button></div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.attendance.recent');
    $emptyMessage = t('sbaio.attendance.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.attendance.recent')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Staff</th><th>Start</th><th>End</th><th>Break Start</th><th>Break End</th><th>Marker</th><th>Source</th><th>Note</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['attendance_date'] ?? '')) ?></td>
              <td><?= e(trim((string)($row['employee_code'] ?? '') . ' ' . (string)($row['full_name'] ?? ''))) ?></td>
              <td><?= e((string)($row['work_start_at'] ?? '')) ?></td>
              <td><?= e((string)($row['work_end_at'] ?? '')) ?></td>
              <td><?= e((string)($row['break_start_at'] ?? '')) ?></td>
              <td><?= e((string)($row['break_end_at'] ?? '')) ?></td>
              <td><?= e((string)($row['day_marker'] ?? '')) ?></td>
              <td><?= e((string)($row['source_label'] ?? '')) ?></td>
              <td><?= e((string)($row['correction_note'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
