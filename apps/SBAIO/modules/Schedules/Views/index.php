<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$templateRows = is_array($templateRows ?? null) ? $templateRows : [];
$assignmentRows = is_array($assignmentRows ?? null) ? $assignmentRows : [];
$staffRows = is_array($staffRows ?? null) ? $staffRows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_attendance'), 'url' => '/apps/sbaio/attendance'],
  ['label' => t('nav.sbaio_leave'), 'url' => '/apps/sbaio/leave'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/schedules/export'],
];
$suiteLinks = [
  ['label' => t('sbaio.common.templates'), 'value' => (string)count($templateRows)],
  ['label' => t('sbaio.common.assignments'), 'value' => (string)count($assignmentRows)],
  ['label' => t('sbaio.common.active_staff'), 'value' => (string)count($staffRows)],
];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => 'Templates + assignments'],
  ['label' => t('sbaio.common.scope'), 'value' => 'Active staff scheduling'],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="grid form-grid-2 u-mb-14">
    <section class="card">
      <h3 class="u-mt-0"><?= e(t('sbaio.schedules.create_template')) ?></h3>
      <div class="muted mb-10"><?= e(t('sbaio.schedules.create_template_desc')) ?></div>
      <form method="post" action="/apps/sbaio/schedules/templates" class="grid form-grid-2">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label>Template Name<input class="input" type="text" name="template_name" required></label>
        <label>Shift Code<input class="input" type="text" name="shift_code"></label>
        <label>Day Of Week<input class="input" type="text" name="day_of_week" placeholder="<?= e(t('sbaio.schedules.day_of_week_placeholder')) ?>"></label>
        <label>Legacy Name Ref<input class="input" type="text" name="legacy_name_ref"></label>
        <label>Range Start<input class="input" type="date" name="range_start_date"></label>
        <label>Range End<input class="input" type="date" name="range_end_date"></label>
        <label>Expected Start<input class="input" type="time" name="expected_start_time"></label>
        <label>Expected End<input class="input" type="time" name="expected_end_time"></label>
        <label>Break Start<input class="input" type="time" name="break_start_time"></label>
        <label>Break End<input class="input" type="time" name="break_end_time"></label>
        <label>Break Minutes<input class="input" type="number" name="expected_break_minutes" min="0" value="0"></label>
        <label> <?= e($tt('schedules.job_label_label')) ?> <input class="input" type="text" name="job_label"></label>
        <label>Scheduled Minutes<input class="input" type="number" name="scheduled_minutes" min="0" value="0"></label>
        <label class="label-inline-check"><input type="checkbox" name="is_active" value="1" checked> <?= e(t('sbaio.schedules.active_checkbox')) ?></label>
        <div class="col-span-full"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.schedules.create_template_action')) ?></button></div>
      </form>
    </section>
    <section class="card">
      <h3 class="u-mt-0"><?= e(t('sbaio.schedules.assign_range')) ?></h3>
      <div class="muted mb-10"><?= e(t('sbaio.schedules.assign_range_desc')) ?></div>
      <form method="post" action="/apps/sbaio/schedules/assign" class="grid form-grid-2">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <label><?= e(t('nav.sbaio_staff')) ?>
          <select name="staff_id" required>
            <option value=""><?= e(t('sbaio.common.select_staff')) ?></option>
            <?php foreach ($staffRows as $staff): ?>
              <option value="<?= (int)($staff['id'] ?? 0) ?>"><?= e(trim((string)($staff['employee_code'] ?? '') . ' ' . (string)($staff['full_name'] ?? ''))) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Template
          <select name="template_id">
            <option value=""><?= e(t('sbaio.common.manual_none')) ?></option>
            <?php foreach ($templateRows as $template): ?>
              <option value="<?= (int)($template['id'] ?? 0) ?>"><?= e((string)($template['template_name'] ?? '')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Start Date<input class="input" type="date" name="start_date" required></label>
        <label>End Date<input class="input" type="date" name="end_date" required></label>
        <label>Day Of Week<input class="input" type="text" name="day_of_week" placeholder="<?= e(t('sbaio.schedules.day_of_week_placeholder')) ?>"></label>
        <label>Legacy Name Ref<input class="input" type="text" name="legacy_name_ref"></label>
        <label>Override Start<input class="input" type="time" name="expected_start_time"></label>
        <label>Override End<input class="input" type="time" name="expected_end_time"></label>
        <label>Break Start<input class="input" type="time" name="break_start_time"></label>
        <label>Break End<input class="input" type="time" name="break_end_time"></label>
        <label>Override Break Minutes<input class="input" type="number" name="expected_break_minutes" min="0"></label>
        <label> <?= e($tt('schedules.job_label_label')) ?> <input class="input" type="text" name="job_label"></label>
        <label>Scheduled Minutes<input class="input" type="number" name="scheduled_minutes" min="0"></label>
        <label>Workbook Range Start<input class="input" type="date" name="range_start_date"></label>
        <label>Workbook Range End<input class="input" type="date" name="range_end_date"></label>
        <div class="col-span-full"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.schedules.assign_range_action')) ?></button></div>
      </form>
    </section>
  </div>

  <h3 class="u-mt-0"><?= e(t('sbaio.schedules.shift_templates')) ?></h3>
  <?php if ($templateRows === []): ?>
    <?php
    $emptyTitle = t('sbaio.schedules.shift_templates');
    $emptyMessage = t('sbaio.schedules.shift_templates_empty');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Template</th><th>Shift Code</th><th>Day</th><th>Range</th><th>Expected Start</th><th>Expected End</th><th>Break</th><th>Job</th><th>Minutes</th><th> <?= e($tt('schedules.active_column')) ?> </th></tr></thead>
        <tbody>
          <?php foreach ($templateRows as $row): ?>
            <tr>
              <td><?= e((string)($row['template_name'] ?? '')) ?></td>
              <td><?= e((string)($row['shift_code'] ?? '')) ?></td>
              <td><?= e((string)($row['day_of_week'] ?? '')) ?></td>
              <td><?= e(trim((string)($row['range_start_date'] ?? '') . ' - ' . (string)($row['range_end_date'] ?? ''))) ?></td>
              <td><?= e((string)($row['expected_start_time'] ?? '')) ?></td>
              <td><?= e((string)($row['expected_end_time'] ?? '')) ?></td>
              <td><?= e(trim((string)($row['break_start_time'] ?? '') . ' - ' . (string)($row['break_end_time'] ?? ''))) ?> <?= e((string)($row['expected_break_minutes'] ?? '0')) ?>m</td>
              <td><?= e((string)($row['job_label'] ?? '')) ?></td>
              <td><?= e((string)($row['scheduled_minutes'] ?? '0')) ?></td>
              <td><?= !empty($row['is_active']) ? e(t('sbaio.common.yes')) : e(t('sbaio.common.no')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <h3><?= e(t('sbaio.schedules.assigned_schedules')) ?></h3>
  <?php if ($assignmentRows === []): ?>
    <?php
    $emptyTitle = t('sbaio.schedules.assigned_schedules');
    $emptyMessage = t('sbaio.schedules.assigned_schedules_empty');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Staff</th><th>Legacy Name Ref</th><th>Template</th><th>Day</th><th>Range</th><th>Expected Start</th><th>Expected End</th><th>Break</th><th>Job</th><th>Minutes</th><th> <?= e($tt('common.status_label')) ?> </th></tr></thead>
        <tbody>
          <?php foreach ($assignmentRows as $row): ?>
            <tr>
              <td><?= e((string)($row['schedule_date'] ?? '')) ?></td>
              <td><?= e((string)($row['full_name'] ?? '')) ?></td>
              <td><?= e((string)($row['legacy_name_ref'] ?? '')) ?></td>
              <td><?= e((string)($row['template_name'] ?? '')) ?></td>
              <td><?= e((string)($row['day_of_week'] ?? '')) ?></td>
              <td><?= e(trim((string)($row['range_start_date'] ?? '') . ' - ' . (string)($row['range_end_date'] ?? ''))) ?></td>
              <td><?= e((string)($row['expected_start_time'] ?? '')) ?></td>
              <td><?= e((string)($row['expected_end_time'] ?? '')) ?></td>
              <td><?= e(trim((string)($row['break_start_time'] ?? '') . ' - ' . (string)($row['break_end_time'] ?? ''))) ?> <?= e((string)($row['expected_break_minutes'] ?? '0')) ?>m</td>
              <td><?= e((string)($row['job_label'] ?? '')) ?></td>
              <td><?= e((string)($row['scheduled_minutes'] ?? '0')) ?></td>
              <td><?= e((string)($row['assignment_status'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
