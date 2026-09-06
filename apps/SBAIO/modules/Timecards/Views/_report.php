<?php
declare(strict_types=1);

$filters = is_array($filters ?? null) ? $filters : [];
$dailyRows = is_array($dailyRows ?? null) ? $dailyRows : [];
$summary = is_array($summary ?? null) ? $summary : [];
$message = trim((string)($message ?? ''));
$error = trim((string)($error ?? ''));
$printUrl = trim((string)($printUrl ?? ''));
$pdfUrl = trim((string)($pdfUrl ?? ''));
$screenUrl = trim((string)($screenUrl ?? ''));
$resetUrl = trim((string)($resetUrl ?? '/apps/sbaio/timecards'));
$returnQuery = trim((string)($returnQuery ?? ''));
$pdfReady = (bool)($pdfReady ?? false);
$isPrintMode = (bool)($isPrintMode ?? false);
$isPdfMode = (bool)($isPdfMode ?? false);
$isExportSurface = $isPrintMode || $isPdfMode;

$selectedStaff = is_array($filters['selected_staff'] ?? null) ? $filters['selected_staff'] : null;
$staffOptions = is_array($filters['staff_options'] ?? null) ? $filters['staff_options'] : [];
$yearOptions = is_array($filters['year_options'] ?? null) ? $filters['year_options'] : [];
$monthOptions = is_array($filters['month_options'] ?? null) ? $filters['month_options'] : [];
$branchOptions = is_array($filters['branch_options'] ?? null) ? $filters['branch_options'] : [];
$departmentOptions = is_array($filters['department_options'] ?? null) ? $filters['department_options'] : [];

if (!$isExportSurface) {
    $suiteActions = [
        ['label' => t('nav.sbaio_dashboard'), 'url' => '/apps/sbaio'],
        ['label' => t('nav.sbaio_attendance'), 'url' => '/apps/sbaio/attendance'],
        ['label' => t('nav.sbaio_payroll'), 'url' => '/apps/sbaio/payroll'],
        ['label' => t('sbaio.common.export'), 'url' => '/apps/sbaio/timecards/export'],
    ];
    $suiteLinks = [
        ['label' => t('timecards.selected_staff'), 'value' => (string)($summary['selected_staff_name'] ?? t('timecards.no_staff_selected'))],
        ['label' => t('timecards.period'), 'value' => (string)($summary['period_label'] ?? '')],
    ];
    $moduleTitle = (string)t('timecards.title');
    $moduleDescription = (string)t('timecards.page_subtitle');
    require APP_ROOT . '/apps/SBAIO/Views/partials/module_page_intro.php';
}

$documentStaffName = trim((string)($summary['selected_staff_name'] ?? '')) !== ''
    ? (string)$summary['selected_staff_name']
    : (string)t('timecards.title');
$documentCompanyName = function_exists('app_display_name') ? trim(app_display_name()) : '';
if ($documentCompanyName === '') {
    $documentCompanyName = defined('APP_NAME') ? APP_NAME : 'SBAIO';
}
$documentPeriodLabel = trim((string)($summary['period_label'] ?? ''));
$summaryItems = $isExportSurface
    ? [
        [
            'label' => (string)t('timecards.kpi.total_salary'),
            'value' => (string)($summary['total_salary_label'] ?? '0.00'),
            'note' => (string)($summary['salary_note'] ?? ''),
        ],
        [
            'label' => (string)t('timecards.kpi.holiday_days'),
            'value' => (string)($summary['holiday_days'] ?? 0),
            'note' => (string)t('timecards.kpi.holiday_days_note'),
        ],
    ]
    : [
        [
            'label' => (string)t('timecards.kpi.total_salary'),
            'value' => (string)($summary['total_salary_label'] ?? '0.00'),
            'note' => (string)($summary['salary_note'] ?? ''),
        ],
        [
            'label' => (string)t('timecards.kpi.total_work_hours'),
            'value' => (string)($summary['total_work_hours_decimal'] ?? '0.00 hrs'),
            'note' => (string)t('timecards.kpi.work_hours_note'),
        ],
        [
            'label' => (string)t('timecards.kpi.total_work_days'),
            'value' => (string)($summary['total_work_days'] ?? 0),
            'note' => (string)t('timecards.kpi.work_days_note'),
        ],
        [
            'label' => (string)t('timecards.kpi.present_days'),
            'value' => (string)($summary['present_days'] ?? 0),
            'note' => (string)t('timecards.kpi.present_days_note'),
        ],
        [
            'label' => (string)t('timecards.kpi.holiday_days'),
            'value' => (string)($summary['holiday_days'] ?? 0),
            'note' => (string)t('timecards.kpi.holiday_days_note'),
        ],
    ];
?>

<?php if (!$isExportSurface && ($message !== '' || $error !== '')): ?>
  <section class="card timecard-no-print">
    <?php if ($message !== ''): ?>
      <div class="notice success"><?= e($message) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>
  </section>
<?php endif; ?>

<section class="timecard-page<?= $isExportSurface ? ' timecard-page--document' : '' ?>">
  <?php if (!$isExportSurface): ?>
    <section class="card timecard-no-print">
      <div class="timecard-toolbar">
        <div class="ui-block">
          <h2 class="u-m-0"><?= e(t('timecards.page_title')) ?></h2>
          <p class="timecard-kicker"><?= e(t('timecards.page_subtitle')) ?></p>
        </div>
        <div class="timecard-toolbar-actions">
          <a class="btn" href="<?= e($printUrl) ?>"><?= e(t('timecards.print_preview')) ?></a>
          <a class="btn btn-primary" href="<?= e($pdfUrl) ?>"><?= e($pdfReady ? t('timecards.export_pdf') : t('timecards.export_pdf_fallback')) ?></a>
        </div>
      </div>
      <form method="get" action="/apps/sbaio/timecards">
        <div class="timecard-filter-grid u-style-d6f2af6e0a">
          <label>
            <?= e(t('timecards.filter.staff')) ?>
            <select name="staff_id" onchange="this.form.submit()">
              <?php if ($staffOptions === []): ?>
                <option value="0"><?= e(t('timecards.filter.no_staff')) ?></option>
              <?php else: ?>
                <?php foreach ($staffOptions as $option): ?>
                  <option value="<?= (int)($option['id'] ?? 0) ?>" <?= (int)($filters['staff_id'] ?? 0) === (int)($option['id'] ?? 0) ? 'selected' : '' ?>>
                    <?= e((string)($option['label'] ?? '')) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </label>
          <label>
            <?= e(t('timecards.filter.year')) ?>
            <select name="year" onchange="this.form.submit()">
              <?php foreach ($yearOptions as $year): ?>
                <option value="<?= (int)$year ?>" <?= (int)($filters['year'] ?? 0) === (int)$year ? 'selected' : '' ?>>
                  <?= (int)$year ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <?= e(t('timecards.filter.month')) ?>
            <select name="month" onchange="this.form.submit()">
              <?php foreach ($monthOptions as $monthValue => $monthLabel): ?>
                <option value="<?= (int)$monthValue ?>" <?= (int)($filters['month'] ?? 0) === (int)$monthValue ? 'selected' : '' ?>>
                  <?= e((string)$monthLabel) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <?= e(t('timecards.filter.attendance_type')) ?>
            <select name="attendance_type" onchange="this.form.submit()">
              <?php foreach (['all' => 'timecards.attendance_type.all', 'present' => 'timecards.attendance_type.present', 'holiday' => 'timecards.attendance_type.holiday', 'leave' => 'timecards.attendance_type.leave'] as $value => $labelKey): ?>
                <option value="<?= e($value) ?>" <?= (string)($filters['attendance_type'] ?? 'all') === $value ? 'selected' : '' ?>>
                  <?= e(t($labelKey)) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <?= e(t('timecards.filter.branch')) ?>
            <select name="branch" onchange="this.form.submit()">
              <option value=""><?= e(t('timecards.filter.all_branches')) ?></option>
              <?php foreach ($branchOptions as $option): ?>
                <option value="<?= e((string)$option) ?>" <?= (string)($filters['branch'] ?? '') === (string)$option ? 'selected' : '' ?>>
                  <?= e((string)$option) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>
            <?= e(t('timecards.filter.department')) ?>
            <select name="department" onchange="this.form.submit()">
              <option value=""><?= e(t('timecards.filter.all_departments')) ?></option>
              <?php foreach ($departmentOptions as $option): ?>
                <option value="<?= e((string)$option) ?>" <?= (string)($filters['department'] ?? '') === (string)$option ? 'selected' : '' ?>>
                  <?= e((string)$option) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="timecard-toolbar-actions u-style-d6f2af6e0a">
          <button class="btn btn-primary" type="submit"><?= e(t('timecards.filter.apply')) ?></button>
          <a class="btn" href="<?= e($resetUrl) ?>"><?= e(t('timecards.filter.reset')) ?></a>
        </div>
      </form>
    </section>
  <?php endif; ?>

  <section class="card timecard-card timecard-card--header">
    <div class="timecard-document-header">
      <div class="timecard-header-item timecard-header-item--identity timecard-header-item--company">
        <div class="timecard-header-item-label"><?= e(t('organization.field.company')) ?></div>
        <div class="timecard-header-item-value"><?= e($documentCompanyName) ?></div>
      </div>
      <div class="timecard-header-item timecard-header-item--identity timecard-header-item--staff">
        <div class="timecard-header-item-label"><?= e(t('timecards.staff_short')) ?></div>
        <div class="timecard-header-item-value"><?= e($documentStaffName) ?></div>
      </div>
      <div class="timecard-header-item">
        <div class="timecard-header-item-label"><?= e(t('timecards.period')) ?></div>
        <div class="timecard-header-item-value"><?= e($documentPeriodLabel !== '' ? $documentPeriodLabel : '—') ?></div>
      </div>
      <div class="timecard-header-item">
        <div class="timecard-header-item-label"><?= e(t('timecards.kpi.total_work_hours')) ?></div>
        <div class="timecard-header-item-value"><?= e((string)($summary['total_work_hours_decimal'] ?? '0.00 hrs')) ?></div>
      </div>
      <div class="timecard-header-item">
        <div class="timecard-header-item-label"><?= e(t('timecards.kpi.present_days')) ?></div>
        <div class="timecard-header-item-value"><?= e((string)($summary['present_days'] ?? 0)) ?></div>
      </div>
    </div>
  </section>

  <section class="card timecard-card">
    <?php if (!$isExportSurface): ?>
      <div class="timecard-section-heading">
        <div class="ui-block">
          <h3><?= e(t('timecards.summary_title')) ?></h3>
          <p class="timecard-kicker"><?= e(t('timecards.page_subtitle')) ?></p>
        </div>
        <div class="timecard-inline-note"><?= e(t('timecards.rows_visible')) ?>: <?= e((string)($summary['row_count'] ?? 0)) ?></div>
      </div>
    <?php endif; ?>

    <div class="timecard-summary-grid">
      <?php foreach ($summaryItems as $item): ?>
        <article class="timecard-summary-card">
          <div class="timecard-summary-label"><?= e((string)$item['label']) ?></div>
          <div class="timecard-summary-value"><?= e((string)$item['value']) ?></div>
          <?php if ((string)($item['note'] ?? '') !== ''): ?>
            <div class="timecard-summary-note"><?= e((string)$item['note']) ?></div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card timecard-card timecard-card--table">
    <div class="timecard-section-heading">
      <div class="ui-block">
        <h3><?= e(t('timecards.daily_title')) ?></h3>
        <?php if (!$isExportSurface): ?>
          <p class="timecard-kicker"><?= e(t('timecards.daily_desc')) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($dailyRows === []): ?>
      <div class="timecard-empty">
        <div class="timecard-empty-title"><?= e(t('timecards.empty_title')) ?></div>
        <div class="muted"><?= e(t('timecards.empty_desc')) ?></div>
        <?php if (!$isExportSurface): ?>
          <div class="timecard-toolbar-actions">
            <a class="btn btn-primary" href="#generate-timecards"><?= e(t('timecards.generate_cta')) ?></a>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="table-wrap timecard-table-wrap">
        <table class="timecard-report-table">
          <thead>
            <tr>
              <th class="timecard-col-date-day"><?= e(t('timecards.column.date')) ?> / <?= e(t('timecards.column.day')) ?></th>
              <th class="timecard-col-time"><?= e(t('timecards.column.start_time')) ?></th>
              <th class="timecard-col-time"><?= e(t('timecards.column.end_time')) ?></th>
              <th class="timecard-col-break"><?= e(t('timecards.column.break_start')) ?></th>
              <th class="timecard-col-break"><?= e(t('timecards.column.break_end')) ?></th>
              <th class="timecard-col-hours"><?= e(t('timecards.column.work_hours')) ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dailyRows as $row): ?>
              <?php
              $dateLabel = trim((string)($row['date_label'] ?? ''));
              $dayLabel = trim((string)($row['day'] ?? ''));
              $startTime = trim((string)($row['start_time'] ?? ''));
              $endTime = trim((string)($row['end_time'] ?? ''));
              $breakStart = trim((string)($row['break_start'] ?? ''));
              $breakEnd = trim((string)($row['break_end'] ?? ''));
              $dateDisplay = $dateLabel;
              if ($dayLabel !== '') {
                  $dateDisplay .= ' (' . $dayLabel . ')';
              }
              ?>
              <tr>
                <td class="timecard-cell-date-day"><?= e($dateDisplay !== '' ? $dateDisplay : '—') ?></td>
                <td class="timecard-cell-time"><?= e($startTime !== '' ? $startTime : '—') ?></td>
                <td class="timecard-cell-time"><?= e($endTime !== '' ? $endTime : '—') ?></td>
                <td class="timecard-cell-time"><?= e($breakStart !== '' ? $breakStart : '—') ?></td>
                <td class="timecard-cell-time"><?= e($breakEnd !== '' ? $breakEnd : '—') ?></td>
                <td class="timecard-cell-hours"><?= e((string)($row['work_hours'] ?? '0.00')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if (!$isExportSurface): ?>
    <section class="card timecard-card" id="generate-timecards">
      <div class="timecard-section-heading">
        <div class="ui-block">
          <h3><?= e(t('timecards.generate_title')) ?></h3>
          <p class="timecard-kicker"><?= e(t('timecards.generate_desc')) ?></p>
        </div>
      </div>
      <form method="post" action="/apps/sbaio/timecards/generate" class="grid u-style-e1d2efe2d0">
        <input type="hidden" name="csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <input type="hidden" name="return_query" value="<?= e($returnQuery) ?>">
        <label>
          <?= e(t('timecards.period_start')) ?>
          <input class="input" type="date" name="period_start" value="<?= e((string)($filters['period_start'] ?? '')) ?>" required>
        </label>
        <label>
          <?= e(t('timecards.period_end')) ?>
          <input class="input" type="date" name="period_end" value="<?= e((string)($filters['period_end'] ?? '')) ?>" required>
        </label>
        <div class="d-flex-end">
          <button class="btn btn-primary" type="submit"><?= e(t('timecards.generate_cta')) ?></button>
        </div>
      </form>
      <div class="muted u-style-d2c171b18b"><?= e(t('timecards.generate_note')) ?></div>
    </section>
  <?php endif; ?>
</section>
