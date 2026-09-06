<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$rows = is_array($rows ?? null) ? $rows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$suiteActions = [
    ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
    ['label' => t('nav.sbaio_payroll'), 'url' => '/apps/sbaio/payroll'],
];
$suiteLinks = [
    ['label' => t('sbaio.common.rows'), 'value' => (string)count($rows)],
    ['label' => t('sbaio.common.matches'), 'value' => (string)($summary['match'] ?? 0)],
    ['label' => t('sbaio.common.mismatches'), 'value' => (string)($summary['mismatch'] ?? 0)],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
$statusToneClass = static function (string $status): string {
  return match ($status) {
    'match' => 'ok',
    'mismatch' => 'danger',
    'missing_imported_source' => 'warn',
    'missing_payroll_record' => 'danger',
    'unmatched_staff_mapping' => 'warn',
    default => 'neutral',
  };
};
$statusClass = static function (string $status, string $base = 'sbaio-payroll-parity-status') use ($statusToneClass): string {
  $statusKey = strtolower(trim($status));
  $safeStatus = preg_replace('/[^a-z0-9_-]+/i', '-', $statusKey);
  $safeStatus = is_string($safeStatus) && $safeStatus !== '' ? $safeStatus : 'unknown';
  return trim($base . ' ' . $base . '--' . $statusToneClass($statusKey) . ' ' . $base . '--' . $safeStatus);
};
$formatValue = static function ($value): string {
    if ($value === null || $value === '') {
        return '';
    }
    if (is_numeric($value)) {
        return number_format((float)$value, 2, '.', ',');
    }
    return (string)$value;
};
$fieldCell = static function (array $field) use ($statusClass, $formatValue): string {
    $status = (string)($field['status'] ?? 'deferred');
    $imported = $formatValue($field['imported'] ?? null);
    $generated = $formatValue($field['generated'] ?? null);
  $statusLabel = t('sbaio.payroll.parity.status.' . $status);
  if ($statusLabel === 'sbaio.payroll.parity.status.' . $status) {
    $statusLabel = $status;
  }
    if ($status === 'deferred') {
    return '<span class="' . e($statusClass($status)) . ' sbaio-payroll-parity-status--inline">' . e(t('sbaio.payroll.parity.deferred')) . '</span>';
    }
  return '<div class="ui-block"><span class="sbaio-payroll-parity-field-label">' . e(t('sbaio.payroll.parity.src')) . ':</span> ' . e($imported) . '</div>'
    . '<div class="ui-block"><span class="sbaio-payroll-parity-field-label">' . e(t('sbaio.payroll.parity.gen')) . ':</span> ' . e($generated) . '</div>'
    . '<div class="ui-block ' . e($statusClass($status)) . ' sbaio-payroll-parity-status--inline">' . e($statusLabel) . '</div>';
};
?>
<section class="card">
  <div class="grid form-grid-6 u-mb-14">
    <?php foreach (['match','mismatch','missing_imported_source','missing_payroll_record','unmatched_staff_mapping','deferred'] as $status): ?>
      <div class="card">
        <div class="muted"><?= e(t('sbaio.payroll.parity.status.' . $status)) ?></div>
        <div class="ui-block sbaio-payroll-parity-summary-value"><?= e((string)($summary[$status] ?? 0)) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($rows === []): ?>
    <div class="muted"><?= e(t('sbaio.payroll.parity.empty')) ?></div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e(t('sbaio.payroll.parity.employee')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.period')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.type')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.variant')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.overall')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.reference')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.base')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.hourly')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.health')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.pension')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.deductions')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.gross')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.net_cash')) ?></th>
            <th><?= e(t('sbaio.payroll.parity.details')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $row): ?>
            <?php $overall = (string)($row['overall_status'] ?? 'deferred'); ?>
            <?php $fields = is_array($row['fields'] ?? null) ? $row['fields'] : []; ?>
            <tr>
              <td>
                <div class="ui-block"><?= e((string)($row['employee'] ?? '')) ?></div>
                <div class="muted"><?= e((string)($row['legacy_name_ref'] ?? '')) ?></div>
              </td>
              <td><?= e((string)($row['period_label'] ?? '')) ?></td>
              <td><?= e((string)($row['salary_type'] ?? '')) ?></td>
              <td><?= e((string)($row['payslip_variant'] ?? '')) ?></td>
              <td><span class="<?= e($statusClass($overall, 'sbaio-payroll-parity-overall')) ?>"><?= e($overall) ?></span></td>
              <td><?= $fieldCell($fields['salary_reference_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['base_salary_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['hourly_pay_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['health_insurance_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['pension_insurance_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['total_deductions_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['gross_amount'] ?? []) ?></td>
              <td><?= $fieldCell($fields['net_amount'] ?? ($fields['cash_payment_amount'] ?? [])) ?></td>
              <td><?= e(implode(', ', array_slice((array)($row['details'] ?? []), 0, 6))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <div class="muted mt-8"><?= e(t('sbaio.payroll.parity.deferred_note')) ?></div>
</section>
<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
