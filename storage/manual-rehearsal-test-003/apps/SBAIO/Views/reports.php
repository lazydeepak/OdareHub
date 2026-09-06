<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$attendanceSeries = is_array($attendanceSeries ?? null) ? $attendanceSeries : [];
$timecardSeries = is_array($timecardSeries ?? null) ? $timecardSeries : [];
$payrollSeries = is_array($payrollSeries ?? null) ? $payrollSeries : [];
$needsAttention = is_array($needsAttention ?? null) ? $needsAttention : [];
$suiteActions = [
  ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
  ['label' => t('nav.sbaio_payroll_parity'), 'url' => '/apps/sbaio/payroll/parity'],
];
$suiteLinks = [
  ['label' => t('sbaio.common.attendance_months'), 'value' => (string)count($attendanceSeries)],
  ['label' => t('sbaio.common.timecard_months'), 'value' => (string)count($timecardSeries)],
  ['label' => t('sbaio.common.payroll_months'), 'value' => (string)count($payrollSeries)],
];
require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';

$chartMax = static function(array $rows, string $key): float {
  $max = 0.0;
  foreach ($rows as $row) {
    $max = max($max, (float)($row[$key] ?? 0));
  }
  return $max > 0 ? $max : 1.0;
};

$renderBars = function(array $rows, string $labelKey, string $valueKey, string $barColor = '#5aa9ff', ?string $secondaryKey = null, string $secondaryColor = '#9b8cff') use ($chartMax): void {
  $max = $chartMax($rows, $valueKey);
  $secondaryMax = $secondaryKey !== null ? $chartMax($rows, $secondaryKey) : 1.0;
  ?>
  <div class="u-style-9aa121fcb3">
    <?php foreach ($rows as $row): ?>
      <?php
        $label = (string)($row[$labelKey] ?? '');
        $primary = (float)($row[$valueKey] ?? 0);
        $secondary = $secondaryKey !== null ? (float)($row[$secondaryKey] ?? 0) : null;
      ?>
      <div class="ui-block">
        <div class="row u-style-417360382f">
          <strong><?= e($label) ?></strong>
          <span class="muted"><?= e(number_format($primary, 2, '.', ',')) ?><?php if ($secondary !== null): ?> · <?= e(number_format($secondary, 2, '.', ',')) ?><?php endif; ?></span>
        </div>
        <div class="u-style-d23a7fd6c6">
          <div class="ui-block" style="height:10px;width:<?= e((string)max(4.0, ($primary / $max) * 100.0)) ?>%;background:<?= e($barColor) ?>;border-radius:999px"></div>
        </div>
        <?php if ($secondary !== null): ?>
          <div class="u-style-5e53a4d0da">
            <div class="ui-block" style="height:6px;width:<?= e((string)max(4.0, ($secondary / $secondaryMax) * 100.0)) ?>%;background:<?= e($secondaryColor) ?>;border-radius:999px"></div>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php
};
?>

<section class="card">
  <div class="grid u-style-0b778e7f2f">
    <?php foreach ($needsAttention as $item): ?>
      <a class="dashboard-link-card" href="<?= e((string)($item['url'] ?? '/apps/sbaio')) ?>">
        <div class="dashboard-link-top">
          <strong><?= e((string)($item['label'] ?? '')) ?></strong>
        </div>
        <div class="hero-meta-value"><?= e((string)($item['value'] ?? '0')) ?></div>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891">Attendance Quality</h3>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.reports.attendance_quality_desc')) ?></div>
    </div>
  </div>
  <?php if ($attendanceSeries === []): ?>
    <div class="muted"><?= e(t('sbaio.reports.attendance_empty')) ?></div>
  <?php else: ?>
    <?php $renderBars($attendanceSeries, 'ym', 'rows_count', '#44c4a1', 'missing_count', '#ff9b6b'); ?>
  <?php endif; ?>
</section>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e(t('sbaio.reports.timecard_throughput')) ?></h3>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.reports.timecard_throughput_desc')) ?></div>
    </div>
  </div>
  <?php if ($timecardSeries === []): ?>
    <div class="muted"><?= e(t('sbaio.reports.timecard_empty')) ?></div>
  <?php else: ?>
    <?php $renderBars($timecardSeries, 'ym', 'worked_minutes', '#5aa9ff', 'overtime_minutes', '#ffd166'); ?>
  <?php endif; ?>
</section>

<section class="card">
  <div class="section-head">
    <div class="ui-block">
      <h3 class="u-style-1169661891"><?= e(t('sbaio.reports.payroll_totals')) ?></h3>
      <div class="muted u-style-fe7b4979fe"><?= e(t('sbaio.reports.payroll_totals_desc')) ?></div>
    </div>
  </div>
  <?php if ($payrollSeries === []): ?>
    <div class="muted"><?= e(t('sbaio.reports.payroll_empty')) ?></div>
  <?php else: ?>
    <?php $renderBars($payrollSeries, 'ym', 'gross_total', '#9b8cff', 'net_total', '#44c4a1'); ?>
  <?php endif; ?>
</section>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
