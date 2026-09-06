<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$preview = is_array($preview ?? null) ? $preview : null;
$history = is_array($history ?? null) ? $history : [];
$csrf = (string)($csrf ?? '');
$previewPayload = is_array($preview['preview'] ?? null) ? $preview['preview'] : [];
$salaryPreview = is_array($previewPayload['salary_preview'] ?? null) ? $previewPayload['salary_preview'] : [];
$fixedPreview = is_array($previewPayload['fixed_preview'] ?? null) ? $previewPayload['fixed_preview'] : [];
?>

<div class="card">
  <div class="u-style-8a84800a49">
    <div class="ui-block">
      <h2 class="u-style-ad7f18b19e"><?= e(t('sbaio.imports.title')) ?></h2>
      <div class="muted"><?= e(t('sbaio.imports.description')) ?></div>
    </div>
    <a class="btn" href="/apps/sbaio"><?= e(t('sbaio.common.back_to_sbaio')) ?></a>
  </div>
</div>

<?php if (($flash_ok ?? '') !== ''): ?>
  <div class="card u-style-e640db1c6b"><?= e((string)$flash_ok) ?></div>
<?php endif; ?>
<?php if (($flash_err ?? '') !== ''): ?>
  <div class="card u-style-c2fa10bd43"><?= e((string)$flash_err) ?></div>
<?php endif; ?>

<div class="u-style-ff1e94a2a4">
  <form method="post" action="/apps/sbaio/imports/preview" enctype="multipart/form-data" class="card u-style-1169661891">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h3 class="u-style-df671843ff"><?= e(t('sbaio.imports.workbook_upload')) ?></h3>
    <div class="muted u-style-761d3addb2"><?= e(t('sbaio.imports.workbook_upload_desc')) ?></div>
    <label class="u-style-fafb8d2e89">
      <div class="muted u-style-4e420aff3f"><?= e(t('sbaio.imports.workbook_label')) ?></div>
      <input class="input" type="file" name="workbook" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    </label>
    <button class="btn ok" type="submit"><?= e(t('sbaio.imports.preview_action')) ?></button>
  </form>

  <div class="card u-style-1169661891">
    <h3 class="u-style-df671843ff"><?= e(t('sbaio.imports.coverage')) ?></h3>
    <div class="muted u-style-761d3addb2"><?= e(t('sbaio.imports.coverage_desc')) ?></div>
    <div class="u-style-fb56f232e0">
      <div class="card u-style-1169661891">
        <strong><?= e(t('sbaio.imports.salary_monthly_rates')) ?></strong>
        <div class="muted"><?= e(t('sbaio.imports.salary_monthly_rates_desc')) ?></div>
      </div>
      <div class="card u-style-1169661891">
        <strong><?= e(t('sbaio.imports.fixed_profiles')) ?></strong>
        <div class="muted"><?= e(t('sbaio.imports.fixed_profiles_desc')) ?></div>
      </div>
      <div class="card u-style-1169661891">
        <strong><?= e(t('sbaio.imports.preview_checks')) ?></strong>
        <div class="muted"><?= e(t('sbaio.imports.preview_checks_desc')) ?></div>
      </div>
    </div>
  </div>
</div>

<?php if (is_array($preview)): ?>
  <div class="card">
    <div class="u-style-8a84800a49">
      <div class="ui-block">
        <h3 class="u-style-df671843ff"><?= e(t('sbaio.imports.preview_summary')) ?></h3>
        <div class="muted"><?= e(t('sbaio.imports.source_file')) ?>: <?= e((string)($preview['source_file'] ?? '')) ?><?= !empty($previewPayload['year']) ? ' · ' . e(t('sbaio.imports.salary_year', ['year' => (string)$previewPayload['year']])) : '' ?></div>
      </div>
      <form method="post" action="/apps/sbaio/imports/commit">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="preview_token" value="<?= e((string)($preview['preview_token'] ?? '')) ?>">
        <button class="btn ok" type="submit"><?= e(t('sbaio.imports.commit_action')) ?></button>
      </form>
    </div>

    <div class="u-style-560b8d4988">
      <div class="card u-style-1169661891"><strong><?= e(t('sbaio.imports.valid_rows')) ?></strong><div class="muted"><?= e((string)($previewPayload['valid_rows'] ?? 0)) ?></div></div>
      <div class="card u-style-1169661891"><strong><?= e(t('sbaio.imports.invalid_rows')) ?></strong><div class="muted"><?= e((string)($previewPayload['invalid_rows'] ?? 0)) ?></div></div>
      <div class="card u-style-1169661891"><strong><?= e(t('sbaio.imports.skipped_rows')) ?></strong><div class="muted"><?= e((string)($previewPayload['skipped_rows'] ?? 0)) ?></div></div>
      <div class="card u-style-1169661891"><strong><?= e(t('sbaio.imports.duplicates')) ?></strong><div class="muted"><?= e((string)($previewPayload['duplicate_rows'] ?? 0)) ?></div></div>
    </div>

    <?php if (!empty($previewPayload['warnings'])): ?>
      <div class="muted u-style-1d8712c2c3"><?= e(implode(' ', array_map('strval', (array)$previewPayload['warnings']))) ?></div>
    <?php endif; ?>
  </div>

  <div class="u-style-3a5ff42e6f">
    <div class="card u-style-1169661891">
      <h3 class="u-style-df671843ff"><?= e(t('sbaio.imports.salary_preview')) ?></h3>
      <div class="muted u-style-fdf33f2304"><?= e(t('sbaio.imports.preview_totals', ['valid' => (string)($salaryPreview['valid_rows'] ?? 0), 'invalid' => (string)($salaryPreview['invalid_rows'] ?? 0), 'duplicates' => (string)($salaryPreview['duplicate_rows'] ?? 0)])) ?></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Source</th>
              <th>NameRef</th>
              <th>Month</th>
              <th>Amount</th>
              <th>Issues</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ((array)($salaryPreview['sample_rows'] ?? []) as $row): ?>
              <tr>
                <td><?= e((string)($row['source_row'] ?? '')) ?></td>
                <td><?= e((string)($row['legacy_name_ref'] ?? '')) ?></td>
                <td><?= e((string)($row['period_month'] ?? '')) ?></td>
                <td><?= e((string)($row['reference_amount'] ?? '')) ?></td>
                <td><?= e(!empty($row['errors']) ? implode(' | ', array_map('strval', (array)$row['errors'])) : (!empty($row['duplicate']) ? t('sbaio.imports.issue_update') : t('sbaio.imports.issue_ready'))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card u-style-1169661891">
      <h3 class="u-style-df671843ff"><?= e(t('sbaio.imports.fixed_preview')) ?></h3>
      <div class="muted u-style-fdf33f2304"><?= e(t('sbaio.imports.preview_totals', ['valid' => (string)($fixedPreview['valid_rows'] ?? 0), 'invalid' => (string)($fixedPreview['invalid_rows'] ?? 0), 'duplicates' => (string)($fixedPreview['duplicate_rows'] ?? 0)])) ?></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Source</th>
              <th>NameRef</th>
              <th>Basic Salary</th>
              <th>Net Pay</th>
              <th>Issues</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ((array)($fixedPreview['sample_rows'] ?? []) as $row): ?>
              <tr>
                <td><?= e((string)($row['source_column'] ?? '')) ?></td>
                <td><?= e((string)($row['legacy_name_ref'] ?? '')) ?></td>
                <td><?= e((string)($row['basic_salary_amount'] ?? '')) ?></td>
                <td><?= e((string)($row['net_pay_amount'] ?? '')) ?></td>
                <td><?= e(!empty($row['errors']) ? implode(' | ', array_map('strval', (array)$row['errors'])) : (!empty($row['duplicate']) ? t('sbaio.imports.issue_update') : t('sbaio.imports.issue_ready'))) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <h3 class="u-style-df671843ff"><?= e(t('sbaio.imports.history')) ?></h3>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>When</th>
          <th>Type</th>
          <th>Source</th>
          <th>Status</th>
          <th>Rows</th>
          <th>User</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $run): ?>
          <tr>
            <td><?= e((string)($run['created_at'] ?? '')) ?></td>
            <td><?= e((string)($run['import_type'] ?? '')) ?></td>
            <td><?= e((string)($run['source_file'] ?? '')) ?></td>
            <td><?= e((string)($run['status'] ?? '')) ?></td>
            <td><?= e(t('sbaio.imports.history_rows', ['imported' => (string)($run['imported_rows'] ?? 0), 'invalid' => (string)($run['invalid_rows'] ?? 0), 'duplicates' => (string)($run['duplicate_rows'] ?? 0)])) ?></td>
            <td><?= e((string)($run['created_by'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>
