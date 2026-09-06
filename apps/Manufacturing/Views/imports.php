<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$templates = is_array($templates ?? null) ? $templates : [];
$preview = is_array($preview ?? null) ? $preview : null;
$history = is_array($history ?? null) ? $history : [];
$csrf = (string)($csrf ?? '');
$flashOk = (string)($flash_ok ?? '');
$flashErr = (string)($flash_err ?? '');
$previewPayload = is_array($preview['preview'] ?? null) ? $preview['preview'] : [];
$previewTemplate = is_array($previewPayload['template'] ?? null) ? $previewPayload['template'] : [];
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.io.imports.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.io.imports.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing"><?= e((string)t('mfg.io.link.back_manufacturing')) ?></a>
      <a class="btn" href="/apps/manufacturing/exports"><?= e((string)t('mfg.io.link.exports')) ?></a>
      <a class="btn" href="/apps/manufacturing/restores"><?= e((string)t('mfg.io.link.restores')) ?></a>
    </div>
  </div>
</div>

<?php if ($flashOk !== ''): ?><div class="card dsp-flash-ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="card dsp-flash-err"><?= e($flashErr) ?></div><?php endif; ?>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.imports.templates')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('mfg.io.col.template')) ?></th>
          <th><?= e((string)t('mfg.io.col.description')) ?></th>
          <th><?= e((string)t('mfg.io.col.mode')) ?></th>
          <th><?= e((string)t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($templates as $template): ?>
          <tr>
            <td><?= e((string)($template['label'] ?? '')) ?></td>
            <td><?= e((string)($template['description'] ?? '')) ?></td>
            <td><?= e((string)($template['mode'] ?? '')) ?></td>
            <td><a class="btn" href="/apps/manufacturing/imports/template?import_type=<?= urlencode((string)($template['key'] ?? '')) ?>"><?= e((string)t('mfg.io.action.download_template')) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($templates === []): ?><tr><td colspan="4" class="muted"><?= e((string)t('mfg.io.empty.templates')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.imports.upload_preview')) ?></h2></div>
  <form method="post" action="/apps/manufacturing/imports/preview" enctype="multipart/form-data" class="mfg-filter-row">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.io.field.import_type')) ?></label>
      <select name="import_type" required>
        <?php foreach ($templates as $template): ?>
          <option value="<?= e((string)($template['key'] ?? '')) ?>"><?= e((string)($template['label'] ?? '')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.io.field.import_file')) ?></label>
      <input class="input" type="file" name="import_file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    </div>
    <div class="row">
      <button class="btn ok" type="submit"><?= e((string)t('mfg.io.action.preview_import')) ?></button>
    </div>
  </form>
</div>

<?php if (is_array($preview)): ?>
  <div class="card">
    <div class="mfg-page-header">
      <div class="ui-block">
        <h2 class="mfg-page-title"><?= e((string)t('mfg.io.imports.preview_summary')) ?></h2>
        <div class="muted"><?= e((string)t('mfg.io.meta.type')) ?>: <?= e((string)($previewPayload['import_type'] ?? '')) ?> · <?= e((string)t('common.source')) ?>: <?= e((string)($preview['source_file'] ?? '')) ?> · <?= e((string)t('mfg.io.col.mode')) ?>: <?= e((string)($previewTemplate['mode'] ?? '')) ?></div>
      </div>
      <?php if (($previewTemplate['mode'] ?? '') === 'ready'): ?>
        <form method="post" action="/apps/manufacturing/imports/commit">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="preview_token" value="<?= e((string)($preview['preview_token'] ?? '')) ?>">
          <button class="btn ok" type="submit"><?= e((string)t('mfg.io.action.commit_import')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>

  <div class="stat-row">
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.valid_rows')) ?></div><div class="stat-box-value"><?= (int)($previewPayload['valid_rows'] ?? 0) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.invalid_rows')) ?></div><div class="stat-box-value"><?= (int)($previewPayload['invalid_rows'] ?? 0) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.skipped_rows')) ?></div><div class="stat-box-value"><?= (int)($previewPayload['skipped_rows'] ?? 0) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.duplicate_rows')) ?></div><div class="stat-box-value"><?= (int)($previewPayload['duplicate_rows'] ?? 0) ?></div></div>
  </div>

  <?php if (!empty($previewPayload['warnings'])): ?>
    <div class="card"><div class="muted"><?= e(implode(' ', array_map('strval', (array)$previewPayload['warnings']))) ?></div></div>
  <?php endif; ?>

  <div class="card">
    <div class="section-head"><h2><?= e((string)t('mfg.io.imports.preview_rows')) ?></h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e((string)t('mfg.io.col.line')) ?></th>
            <th><?= e((string)t('mfg.io.col.preview')) ?></th>
            <th><?= e((string)t('mfg.io.col.issues')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)($previewPayload['sample_rows'] ?? []) as $row): ?>
            <tr>
              <td><?= e((string)($row['line_number'] ?? '')) ?></td>
              <td><pre><?= e(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre></td>
              <td><?= e(!empty($row['errors']) ? implode(' | ', array_map('strval', (array)$row['errors'])) : (!empty($row['duplicate']) ? (string)t('mfg.io.issue.duplicate_update') : (string)t('mfg.io.issue.ready'))) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if ((array)($previewPayload['sample_rows'] ?? []) === []): ?><tr><td colspan="3" class="muted"><?= e((string)t('mfg.io.empty.preview_rows')) ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.history.imports')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.when')) ?></th>
          <th><?= e((string)t('common.type')) ?></th>
          <th><?= e((string)t('common.source')) ?></th>
          <th><?= e((string)t('common.status')) ?></th>
          <th><?= e((string)t('mfg.io.col.rows')) ?></th>
          <th><?= e((string)t('mfg.io.col.user')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($history as $run): ?>
          <tr>
            <td><?= e((string)($run['created_at'] ?? '')) ?></td>
            <td><?= e((string)($run['import_type'] ?? '')) ?></td>
            <td><?= e((string)($run['source_file'] ?? '')) ?></td>
            <td><?= e((string)($run['status'] ?? '')) ?></td>
            <td><?= (int)($run['imported_rows'] ?? 0) ?> / <?= (int)($run['invalid_rows'] ?? 0) ?> / <?= (int)($run['duplicate_rows'] ?? 0) ?></td>
            <td><?= e((string)($run['created_by'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if ($history === []): ?><tr><td colspan="6" class="muted"><?= e((string)t('mfg.io.empty.history')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>