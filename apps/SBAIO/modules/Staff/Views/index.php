<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_schedules'), 'url' => '/apps/sbaio/schedules'],
  ['label' => t('nav.sbaio_payroll'), 'url' => '/apps/sbaio/payroll'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/staff/export'],
];
$suiteLinks = [
  ['label' => t('sbaio.common.records'), 'value' => (string)count($rows)],
];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => t('sbaio.staff.directory')],
  ['label' => t('sbaio.common.sort'), 'value' => 'Newest first'],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.staff.form_title')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.staff.form_desc')) ?></div>
    <form method="post" action="/apps/sbaio/staff/create" class="grid form-grid-4">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label>Legacy SN<input class="input" type="text" name="legacy_sn"></label>
      <label>Employee Code<input class="input" type="text" name="employee_code"></label>
      <label>Full Name<input class="input" type="text" name="full_name" required></label>
      <label>Name Ref<input class="input" type="text" name="name_ref" placeholder="<?= e(t('sbaio.staff.name_ref_placeholder')) ?>"></label>
      <label>Email<input class="input" type="email" name="email"></label>
      <label> <?= e($tt('staff.role_label_label')) ?> <input class="input" type="text" name="role_label"></label>
      <label>Staff Type<input class="input" type="text" name="staff_type" placeholder="<?= e(t('sbaio.staff.staff_type_placeholder')) ?>"></label>
      <label>Salary Type<input class="input" type="text" name="salary_type" placeholder="<?= e(t('sbaio.staff.salary_type_placeholder')) ?>"></label>
      <label>Salary Rate<input class="input" type="number" name="salary_rate" step="0.01" min="0"></label>
      <label>Helper Classification<input class="input" type="text" name="helper_classification"></label>
      <label>Nationality<input class="input" type="text" name="nationality"></label>
      <label>Gender<input class="input" type="text" name="gender"></label>
      <label>Date Of Birth<input class="input" type="date" name="date_of_birth"></label>
      <label> <?= e($tt('staff.employment_status_label')) ?> <input class="input" type="text" name="employment_status" value="active"></label>
      <label>Department<input class="input" type="text" name="department_name"></label>
      <label>Branch<input class="input" type="text" name="branch_name"></label>
      <label>Hire Date<input class="input" type="date" name="hire_date"></label>
      <label>Join Date<input class="input" type="date" name="join_date"></label>
      <label>Exit Date<input class="input" type="date" name="exit_date"></label>
      <label>Legacy Job Details<input class="input" type="text" name="legacy_job_details"></label>
      <label>Residence Card Front<input class="input" type="text" name="residence_card_front"></label>
      <label>Residence Card Back<input class="input" type="text" name="residence_card_back"></label>
      <div class="col-span-full">
        <button class="btn btn-primary" type="submit"><?= e(t('sbaio.staff.create')) ?></button>
      </div>
    </form>
  </div>
  <?php if ($rows === []): ?>
    <?php
    $emptyTitle = t('sbaio.staff.directory');
    $emptyMessage = t('sbaio.staff.empty_desc');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <h3 class="u-mt-0"><?= e(t('sbaio.staff.directory')) ?></h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Legacy SN</th>
            <th>Code</th>
            <th>Name</th>
            <th>Name Ref</th>
            <th>Email</th>
            <th>Type</th>
            <th>Salary Type</th>
            <th>Rate</th>
            <th>Employment</th>
            <th>Department</th>
            <th>Join</th>
            <th>Exit</th>
            <th>Job</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <tr>
              <td><?= e((string)($row['legacy_sn'] ?? '')) ?></td>
              <td><?= e((string)($row['employee_code'] ?? '')) ?></td>
              <td><?= e((string)($row['full_name'] ?? '')) ?></td>
              <td><?= e((string)($row['name_ref'] ?? '')) ?></td>
              <td><?= e((string)($row['email'] ?? '')) ?></td>
              <td><?= e((string)($row['staff_type'] ?? '')) ?></td>
              <td><?= e((string)($row['salary_type'] ?? '')) ?></td>
              <td><?= e($row['salary_rate'] === null ? '' : number_format((float)$row['salary_rate'], 2, '.', ',')) ?></td>
              <td><?= e((string)($row['employment_status'] ?? '')) ?></td>
              <td><?= e((string)($row['department_name'] ?? '')) ?></td>
              <td><?= e((string)($row['join_date'] ?? '')) ?></td>
              <td><?= e((string)($row['exit_date'] ?? '')) ?></td>
              <td><?= e((string)($row['legacy_job_details'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
