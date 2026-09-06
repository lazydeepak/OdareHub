<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-open.php'; ?>
<?php
$suite = is_array($suite ?? null) ? $suite : [];
$modules = is_array($modules ?? null) ? $modules : [];
$history = is_array($history ?? null) ? $history : [];
$csrf = (string)($csrf ?? '');
$flashOk = (string)($flash_ok ?? '');
$flashErr = (string)($flash_err ?? '');
$previewPayload = is_array($preview ?? null) ? $preview : [];
$previewData = is_array($previewPayload['preview'] ?? null) ? $previewPayload['preview'] : [];
$scopes = array_values(array_map('strval', (array)($previewData['available_restore_scopes'] ?? [])));
$selectedScope = in_array('full', $scopes, true) ? 'full' : ($scopes[0] ?? 'package_only');
?>

<div class="card">
  <div class="mfg-page-header">
    <div class="ui-block">
      <h2 class="mfg-page-title"><?= e((string)t('mfg.io.restores.title')) ?></h2>
      <div class="muted"><?= e((string)t('mfg.io.restores.subtitle')) ?></div>
    </div>
    <div class="mfg-page-header-links">
      <a class="btn" href="/apps/manufacturing"><?= e((string)t('mfg.io.link.back_manufacturing')) ?></a>
      <a class="btn" href="/apps/manufacturing/exports"><?= e((string)t('mfg.io.link.exports')) ?></a>
      <a class="btn" href="/apps/manufacturing/imports"><?= e((string)t('mfg.io.link.imports')) ?></a>
    </div>
  </div>
</div>

<?php if ($flashOk !== ''): ?><div class="card dsp-flash-ok"><?= e($flashOk) ?></div><?php endif; ?>
<?php if ($flashErr !== ''): ?><div class="card dsp-flash-err"><?= e($flashErr) ?></div><?php endif; ?>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.restores.preview_restore')) ?></h2></div>
  <form method="post" action="/apps/manufacturing/restores/preview" enctype="multipart/form-data" class="mfg-filter-row">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="ui-block">
      <label class="muted mfg-filter-label"><?= e((string)t('mfg.io.field.backup_file')) ?></label>
      <input class="input" type="file" name="backup_file" accept=".zip,.json" required>
    </div>
    <div class="row">
      <button class="btn ok" type="submit"><?= e((string)t('mfg.io.action.preview_restore')) ?></button>
    </div>
  </form>
</div>

<?php if ($previewData !== []): ?>
  <div class="stat-row">
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.backup_type')) ?></div><div class="stat-box-value"><?= e((string)($previewData['backup_type'] ?? '')) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.schema_compatibility')) ?></div><div class="stat-box-value"><?= e((string)($previewData['schema_compatibility'] ?? '')) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.warning_conflict')) ?></div><div class="stat-box-value"><?= (int)($previewData['conflict_summary']['warning_count'] ?? 0) ?> / <?= (int)($previewData['conflict_summary']['conflict_count'] ?? 0) ?></div></div>
    <div class="stat-box"><div class="stat-box-label"><?= e((string)t('mfg.io.kpi.duplicate_risk_rows')) ?></div><div class="stat-box-value"><?= (int)($previewData['duplicate_risk_rows'] ?? 0) ?></div></div>
  </div>

  <div class="card">
    <div class="section-head"><h2><?= e((string)t('mfg.io.restores.verification')) ?></h2></div>
    <div class="table-wrap">
      <table>
        <tbody>
          <tr><th><?= e((string)t('common.source')) ?></th><td><?= e((string)($previewData['source_backup'] ?? '')) ?></td></tr>
          <tr><th><?= e((string)t('mfg.io.meta.target')) ?></th><td><?= e(strtoupper((string)($previewData['target_type'] ?? 'suite'))) ?>: <?= e((string)($previewData['target_key'] ?? 'manufacturing')) ?></td></tr>
          <tr><th><?= e((string)t('mfg.io.meta.live_status')) ?></th><td><?= e((string)($previewData['live_state']['status'] ?? '')) ?></td></tr>
          <tr><th><?= e((string)t('mfg.io.meta.live_version')) ?></th><td><?= e((string)($previewData['live_state']['version'] ?? '')) ?></td></tr>
          <tr><th><?= e((string)t('mfg.io.meta.has_package')) ?></th><td><?= !empty($previewData['has_package']) ? e((string)t('mfg.io.bool.yes')) : e((string)t('mfg.io.bool.no')) ?></td></tr>
          <tr><th><?= e((string)t('mfg.io.meta.has_data')) ?></th><td><?= !empty($previewData['has_data']) ? e((string)t('mfg.io.bool.yes')) : e((string)t('mfg.io.bool.no')) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="section-head"><h2><?= e((string)t('mfg.io.restores.conflicts')) ?></h2></div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th><?= e((string)t('mfg.io.col.category')) ?></th>
            <th><?= e((string)t('mfg.io.col.details')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ((array)($previewData['conflicts'] ?? []) as $item): ?>
            <tr><td><?= e((string)t('mfg.io.meta.conflict')) ?></td><td><?= e((string)$item) ?></td></tr>
          <?php endforeach; ?>
          <?php foreach ((array)($previewData['overwrite_risk'] ?? []) as $item): ?>
            <tr><td><?= e((string)t('mfg.io.meta.overwrite_risk')) ?></td><td><?= e((string)$item) ?></td></tr>
          <?php endforeach; ?>
          <?php if ((array)($previewData['conflicts'] ?? []) === [] && (array)($previewData['overwrite_risk'] ?? []) === []): ?>
            <tr><td colspan="2" class="muted"><?= e((string)t('mfg.io.empty.conflicts')) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($scopes !== []): ?>
    <div class="card">
      <div class="section-head"><h2><?= e((string)t('mfg.io.restores.apply')) ?></h2></div>
      <form method="post" action="/apps/manufacturing/restores/commit" class="mfg-filter-row">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>">
        <div class="ui-block">
          <label class="muted mfg-filter-label"><?= e((string)t('mfg.io.field.restore_scope')) ?></label>
          <select name="restore_scope">
            <?php foreach ($scopes as $scope): ?>
              <option value="<?= e($scope) ?>" <?= $scope === $selectedScope ? 'selected' : '' ?>><?= e($scope) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="ui-block">
          <label class="muted mfg-filter-label"><?= e((string)t('mfg.io.field.conflict_strategy')) ?></label>
          <select name="conflict_strategy">
            <option value="overwrite"><?= e((string)t('mfg.io.strategy.overwrite')) ?></option>
            <option value="merge"><?= e((string)t('mfg.io.strategy.merge')) ?></option>
          </select>
        </div>
        <div class="row u-style-d9d628e2a0">
          <input type="checkbox" name="install_missing_dependencies" value="1" id="install_missing_dependencies">
          <label for="install_missing_dependencies"><?= e((string)t('mfg.io.field.install_dependencies')) ?></label>
        </div>
        <div class="row">
          <button class="btn ok" type="submit"><?= e((string)t('mfg.io.action.apply_restore')) ?></button>
        </div>
      </form>

      <form method="post" action="/apps/manufacturing/restores/cancel" class="row u-style-8c0dfc7bf7">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="preview_token" value="<?= e((string)($previewPayload['preview_token'] ?? '')) ?>">
        <button class="btn" type="submit"><?= e((string)t('mfg.io.action.cancel_review')) ?></button>
      </form>
    </div>
  <?php else: ?>
    <div class="card"><div class="muted"><?= e((string)t('mfg.io.restores.review_only')) ?></div></div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.restores.module_coverage')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.module')) ?></th>
          <th><?= e((string)t('common.status')) ?></th>
          <th><?= e((string)t('mfg.io.col.tables')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($modules as $module): ?>
        <tr>
          <td><?= e((string)($module['display_name'] ?? '')) ?></td>
          <td><?= e((string)($module['status'] ?? '')) ?></td>
          <td><?= e(implode(', ', array_map('strval', (array)($module['required_tables'] ?? [])))) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($modules === []): ?><tr><td colspan="3" class="muted"><?= e((string)t('mfg.io.empty.modules')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="section-head"><h2><?= e((string)t('mfg.io.history.restores')) ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th><?= e((string)t('common.when')) ?></th>
          <th><?= e((string)t('mfg.io.col.target')) ?></th>
          <th><?= e((string)t('common.type')) ?></th>
          <th><?= e((string)t('common.status')) ?></th>
          <th><?= e((string)t('mfg.io.col.rollback')) ?></th>
          <th><?= e((string)t('mfg.io.col.warnings_errors')) ?></th>
          <th><?= e((string)t('mfg.io.col.user')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($history as $row): ?>
        <tr>
          <td><?= e((string)($row['created_at'] ?? '')) ?></td>
          <td><?= e((string)($row['target_type'] ?? '')) ?>: <?= e((string)($row['target_key'] ?? '')) ?></td>
          <td><?= e((string)($row['restore_type'] ?? '')) ?></td>
          <td><?= e((string)($row['status'] ?? '')) ?></td>
          <td><?= !empty($row['rollback_attempted']) ? e((string)t('mfg.io.bool.attempted')) : e((string)t('mfg.io.bool.no')) ?></td>
          <td><?= e(trim((string)($row['warning_text'] ?? '') . ' ' . (string)($row['error_text'] ?? ''))) ?></td>
          <td><?= e((string)($row['created_by'] ?? '')) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($history === []): ?><tr><td colspan="7" class="muted"><?= e((string)t('mfg.io.empty.history')) ?></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require APP_ROOT . '/public/views/layouts/admin-wrapper-close.php'; ?>