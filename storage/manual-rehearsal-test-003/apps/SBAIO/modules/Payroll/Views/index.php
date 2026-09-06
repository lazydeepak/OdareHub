<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$tt = static function (string $key): string {
  return t($key);
};
?>
<?php
$approvedPeriods = is_array($approvedPeriods ?? null) ? $approvedPeriods : [];
$runRows = is_array($runRows ?? null) ? $runRows : [];
$recordRows = is_array($recordRows ?? null) ? $recordRows : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_payroll_parity'), 'url' => '/apps/sbaio/payroll/parity'],
  ['label' => t('nav.sbaio_timecards'), 'url' => '/apps/sbaio/timecards'],
  ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/payroll/export'],
];
$suiteLinks = [
  ['label' => t('sbaio.common.approved_periods'), 'value' => (string)count($approvedPeriods)],
  ['label' => t('sbaio.common.runs'), 'value' => (string)count($runRows)],
  ['label' => t('sbaio.common.records'), 'value' => (string)count($recordRows)],
];
$moduleFilters = [
  ['label' => t('sbaio.common.view'), 'value' => 'Runs + output records'],
  ['label' => t('sbaio.common.prerequisite'), 'value' => t('sbaio.common.approved_periods')],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_feedback.php';
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_filters.php';
?>
<section class="card">
  <div class="card u-mb-14">
    <h3 class="u-mt-0"><?= e(t('sbaio.payroll.create_run')) ?></h3>
    <div class="muted mb-10"><?= e(t('sbaio.payroll.create_run_desc')) ?></div>
    <form method="post" action="/apps/sbaio/payroll/create-run" class="grid form-grid-2">
      <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
      <label><?= e(t('sbaio.payroll.approved_period')) ?>
        <select name="period_key" required>
          <option value=""><?= e(t('sbaio.payroll.select_period')) ?></option>
          <?php foreach ($approvedPeriods as $period): ?>
            <option value="<?= e((string)($period['period_key'] ?? '')) ?>"><?= e((string)($period['period_key'] ?? '')) ?> (<?= e((string)($period['period_start'] ?? '')) ?> to <?= e((string)($period['period_end'] ?? '')) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <div class="d-flex-end"><button class="btn btn-primary" type="submit"><?= e(t('sbaio.payroll.create_scaffold')) ?></button></div>
    </form>
    <div class="muted mt-8"><?= e(t('sbaio.payroll.create_note')) ?></div>
  </div>
  <h3 class="u-mt-0"><?= e(t('sbaio.payroll.runs')) ?></h3>
  <?php if ($runRows === []): ?>
    <?php
    $emptyTitle = t('sbaio.payroll.runs');
    $emptyMessage = t('sbaio.payroll.runs_empty');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Period</th><th>Month</th><th>Variant</th><th>Start</th><th>End</th><th> <?= e($tt('payroll.run_status_column')) ?> </th><th>Approval</th><th>Locked</th></tr></thead>
        <tbody>
          <?php foreach ($runRows as $row): ?>
            <tr>
              <td><?= e((string)($row['period_key'] ?? '')) ?></td>
              <td><?= e(trim((string)($row['period_month_label'] ?? '') . ' ' . (string)($row['period_year'] ?? ''))) ?></td>
              <td><?= e((string)($row['payslip_variant'] ?? '')) ?></td>
              <td><?= e((string)($row['period_start'] ?? '')) ?></td>
              <td><?= e((string)($row['period_end'] ?? '')) ?></td>
              <td><?= e((string)($row['run_status'] ?? '')) ?></td>
              <td><?= e((string)($row['approval_status'] ?? '')) ?></td>
              <td><?= e((string)($row['locked_at'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <h3><?= e(t('sbaio.payroll.records')) ?></h3>
  <?php if ($recordRows === []): ?>
    <?php
    $emptyTitle = t('sbaio.payroll.records');
    $emptyMessage = t('sbaio.payroll.records_empty');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_empty.php';
    ?>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Staff</th><th>Name Ref</th><th>Variant</th><th>Salary Type</th><th>Rate</th><th>Reference</th><th>Worked Days</th><th>Workdays</th><th>Worked Minutes</th><th>Worked Hours</th><th>OT Min</th><th>Leave</th><th>Holiday</th><th>Health</th><th>Pension</th><th>Employ.</th><th>Total Ded.</th><th>Base Salary</th><th>Hourly Pay</th><th>Gross</th><th>Net</th></tr></thead>
        <tbody>
          <?php foreach ($recordRows as $row): ?>
            <tr>
              <td><?= e((string)($row['full_name'] ?? '')) ?></td>
              <td><?= e((string)($row['legacy_name_ref'] ?? '')) ?></td>
              <td><?= e((string)($row['payslip_variant'] ?? '')) ?></td>
              <td><?= e((string)($row['salary_type'] ?? '')) ?></td>
              <td><?= e($row['salary_rate'] === null ? '' : number_format((float)$row['salary_rate'], 2, '.', ',')) ?></td>
              <td><?= e($row['salary_reference_amount'] === null ? '' : number_format((float)$row['salary_reference_amount'], 2, '.', ',')) ?></td>
              <td><?= e((string)($row['worked_days'] ?? '0')) ?></td>
              <td><?= e((string)($row['workday_count'] ?? '0')) ?></td>
              <td><?= e((string)($row['worked_minutes'] ?? '0')) ?></td>
              <td><?= e((string)($row['worked_hours'] ?? '0')) ?></td>
              <td><?= e((string)($row['overtime_minutes'] ?? '0')) ?></td>
              <td><?= e((string)($row['leave_days'] ?? '0')) ?></td>
              <td><?= e((string)($row['holiday_days'] ?? '0')) ?></td>
              <td><?= e($row['health_insurance_amount'] === null ? '' : number_format((float)$row['health_insurance_amount'], 2, '.', ',')) ?></td>
              <td><?= e($row['pension_insurance_amount'] === null ? '' : number_format((float)$row['pension_insurance_amount'], 2, '.', ',')) ?></td>
              <td><?= e($row['employment_insurance_amount'] === null ? '' : number_format((float)$row['employment_insurance_amount'], 2, '.', ',')) ?></td>
              <td><?= e($row['total_deductions_amount'] === null ? '' : number_format((float)$row['total_deductions_amount'], 2, '.', ',')) ?></td>
              <td><?= e($row['base_salary_amount'] === null ? '' : number_format((float)$row['base_salary_amount'], 2, '.', ',')) ?></td>
              <td><?= e($row['hourly_pay_amount'] === null ? '' : number_format((float)$row['hourly_pay_amount'], 2, '.', ',')) ?></td>
              <td><?= e(number_format((float)($row['gross_amount'] ?? 0), 2, '.', ',')) ?></td>
              <td><?= e(number_format((float)($row['net_amount'] ?? 0), 2, '.', ',')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <div class="muted mt-8"><?= e(t('sbaio.payroll.deferred_note')) ?></div>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
